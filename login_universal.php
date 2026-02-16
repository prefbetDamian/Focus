<?php
/**
 * Uniwersalny endpoint logowania
 * Obsługuje:
 * - Pracowników (firstName + lastName + PIN)
 * - Kierowników (firstName + lastName + PIN)
 */

declare(strict_types=1);

require_once __DIR__ . '/core/session.php';
header('Content-Type: application/json; charset=utf-8');

try {
    $config = require __DIR__ . '/config.php';

    $data = json_decode(file_get_contents('php://input'), true) ?? [];
    $deviceId = trim($data['device_id'] ?? '');

    if ($deviceId === '') {
        echo json_encode(['success' => false, 'message' => 'Brak danych urządzenia']);
        exit;
    }

        $pdo = new PDO(
		"mysql:host={$config['db_host']};port={$config['db_port']};dbname={$config['db_name']};charset=utf8mb4",
        $config['db_user'],
        $config['db_pass'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    $pin       = trim($data['pin'] ?? '');
    $firstName = trim($data['firstName'] ?? '');
    $lastName  = trim($data['lastName'] ?? '');

    if ($pin === '' || $firstName === '' || $lastName === '') {
        echo json_encode(['success' => false, 'message' => 'Brak wymaganych danych']);
        exit;
    }

    // KROK 1: PRACOWNIK
    $stmt = $pdo->prepare('
        SELECT id, is_operator, pin_hash, first_name, last_name
        FROM employees
        WHERE BINARY first_name = ? AND BINARY last_name = ?
        LIMIT 1
    ');
    $stmt->execute([$firstName, $lastName]);
    $emp = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

    if ($emp) {
        // Sprawdź blokadę logowania dla pracownika
        $stmt = $pdo->prepare('
            SELECT blocked_until, attempts
            FROM login_attempts
            WHERE employee_id = ? AND context = "rcp"
            LIMIT 1
        ');
        $stmt->execute([$emp['id']]);
        $loginAttempt = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

        if ($loginAttempt && !empty($loginAttempt['blocked_until'])) {
            $blockedUntil = strtotime($loginAttempt['blocked_until']);
            if ($blockedUntil > time()) {
                $minutesLeft = (int)ceil(($blockedUntil - time()) / 60);
                echo json_encode([
                    'success' => false,
                    'message' => "Konto zablokowane na $minutesLeft minut. Skontaktuj się z kierownikiem.",
                ]);
                exit;
            }
        }

        // Poprawny PIN
        if (!empty($emp['pin_hash']) && password_verify($pin, $emp['pin_hash'])) {
            // Wyzeruj licznik prób
            $stmt = $pdo->prepare('
                SELECT id
                FROM login_attempts
                WHERE employee_id = ? AND context = "rcp"
                LIMIT 1
            ');
            $stmt->execute([$emp['id']]);
            $existingAttempt = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

            if ($existingAttempt) {
                $stmt = $pdo->prepare('
                    UPDATE login_attempts
                    SET attempts = 0,
                        blocked_until = NULL,
                        last_attempt = NOW()
                    WHERE id = ?
                ');
                $stmt->execute([$existingAttempt['id']]);
            }

            // Multi-device blokada na podstawie aktywnej sesji pracy
            $stmt = $pdo->prepare('
                SELECT device_id
                FROM work_sessions
                WHERE employee_id = ? AND end_time IS NULL
                LIMIT 1
            ');
            $stmt->execute([$emp['id']]);
            $activeSession = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

            if ($activeSession && $activeSession['device_id'] !== $deviceId) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Już pracujesz na innym urządzeniu. Zakończ tam pracę lub poczekaj do północy.',
                ]);
                exit;
            }

            // Jedno urządzenie na konto (employees.device_id) – nie blokuj, jeśli kolumny nie ma
            try {
                $clientIp = $_SERVER['REMOTE_ADDR'] ?? null;

                $stmt = $pdo->prepare('
                    SELECT device_id
                    FROM employees
                    WHERE id = ?
                    LIMIT 1
                ');
                $stmt->execute([$emp['id']]);
                $empRow = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

                if ($empRow && !empty($empRow['device_id']) && $empRow['device_id'] !== $deviceId) {
                    echo json_encode([
                        'success' => false,
                        'message' => 'Twoje konto jest już powiązane z innym urządzeniem. Skontaktuj się z kierownikiem, aby zresetować dostęp lub skorzystaj z panelu reset PIN.',
                    ]);
                    exit;
                }

                if ($empRow && empty($empRow['device_id'])) {
                    $upd = $pdo->prepare('
                        UPDATE employees
                        SET device_id = ?, ip_address = ?
                        WHERE id = ?
                    ');
                    $upd->execute([$deviceId, $clientIp, $emp['id']]);
                }
            } catch (Throwable $e) {
                // Jeśli kolumny jeszcze nie ma / inny błąd – nie blokuj logowania
            }

            // Załóż sesję pracownika
            session_regenerate_id(true);
            $_SESSION['user_id'] = $emp['id'];
            $_SESSION['employee'] = [
                'id'          => $emp['id'],
                'first_name'  => $emp['first_name'],
                'last_name'   => $emp['last_name'],
                'is_operator' => (int)$emp['is_operator'],
            ];
            $_SESSION['first_name']  = $emp['first_name'];
            $_SESSION['last_name']   = $emp['last_name'];
            $_SESSION['is_operator'] = (int)$emp['is_operator'];
            $_SESSION['login_time']  = time();
            $_SESSION['device_id']   = $deviceId;

            // Czyść ewentualną sesję kierownika
            unset($_SESSION['manager'], $_SESSION['manager_id'], $_SESSION['role_level']);

            // Dla is_operator=3 (ładowarka) ustaw trwałą sesję (30 dni)
            if ((int)$emp['is_operator'] === 3) {
                setPersistentSession();
            }

            echo json_encode(['success' => true, 'redirect' => 'panel.php', 'user_type' => 'employee']);
            exit;
        }

        // Zły PIN dla istniejącego pracownika – zlicz próby i ewentualnie zablokuj
        $stmt = $pdo->prepare('
            SELECT id, attempts
            FROM login_attempts
            WHERE employee_id = ? AND context = "rcp"
            LIMIT 1
        ');
        $stmt->execute([$emp['id']]);
        $attempt = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

        $newAttempts = (int)($attempt['attempts'] ?? 0) + 1;
        $maxAttempts = 3;

        if ($attempt) {
            if ($newAttempts >= $maxAttempts) {
                $stmt = $pdo->prepare('
                    UPDATE login_attempts
                    SET attempts = ?, blocked_until = DATE_ADD(NOW(), INTERVAL 30 MINUTE), last_attempt = NOW()
                    WHERE id = ?
                ');
                $stmt->execute([$newAttempts, $attempt['id']]);

                echo json_encode([
                    'success' => false,
                    'message' => "Konto zablokowane na 30 minut po $maxAttempts nieudanych próbach. Skontaktuj się z kierownikiem.",
                ]);
                exit;
            }

            $stmt = $pdo->prepare('UPDATE login_attempts SET attempts = ?, last_attempt = NOW() WHERE id = ?');
            $stmt->execute([$newAttempts, $attempt['id']]);
        } else {
            try {
                $clientIp = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
                $stmt = $pdo->prepare('
                    INSERT INTO login_attempts (employee_id, ip, context, attempts, last_attempt)
                    VALUES (?, ?, "rcp", ?, NOW())
                ');
                $stmt->execute([$emp['id'], $clientIp, $newAttempts]);
            } catch (PDOException $e) {
                error_log('Error creating login_attempt: ' . $e->getMessage());
            }
        }

        $attemptsLeft = max(0, $maxAttempts - $newAttempts);
        echo json_encode([
            'success' => false,
            'message' => "Nieprawidłowy PIN. Pozostało prób: $attemptsLeft",
        ]);
        exit;
    }

    // KROK 2: KIEROWNIK (tylko jeśli nie znaleziono pracownika)
    $stmt = $pdo->prepare('
        SELECT id, first_name, last_name, pin_hash, role_level
        FROM managers
        WHERE BINARY first_name = ? AND BINARY last_name = ?
        LIMIT 1
    ');
    $stmt->execute([$firstName, $lastName]);
    $mgr = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

    if ($mgr && !empty($mgr['pin_hash']) && password_verify($pin, $mgr['pin_hash'])) {
        session_regenerate_id(true);
        $_SESSION['manager'] = [
            'id'         => $mgr['id'],
            'first_name' => $mgr['first_name'],
            'last_name'  => $mgr['last_name'],
            'role_level' => (int)$mgr['role_level'],
        ];
        $_SESSION['manager_name'] = $mgr['first_name'] . ' ' . $mgr['last_name'];
        $_SESSION['manager_id']   = $mgr['id'];
        $_SESSION['role_level']   = (int)$mgr['role_level'];
        $_SESSION['login_time']   = time();
        $_SESSION['device_id']    = $deviceId;

        // Czyść ewentualną sesję pracownika
        unset($_SESSION['user_id'], $_SESSION['employee'], $_SESSION['first_name'], $_SESSION['last_name'], $_SESSION['is_operator']);

        // Dla role_level=2 (kierownik) ustaw trwałą sesję (30 dni)
        if ((int)$mgr['role_level'] === 2) {
            setPersistentSession();
        }

        echo json_encode(['success' => true, 'redirect' => 'panel/dashboard.php', 'user_type' => 'manager']);
        exit;
    }

    // Brak poprawnych danych logowania
    echo json_encode(['success' => false, 'message' => 'Nieprawidłowe dane logowania']);
} catch (Throwable $e) {
    error_log('Login error (universal): ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Błąd serwera podczas logowania']);
}
