<?php
declare(strict_types=1);

/* ===== SESJA ===== */
ini_set('session.use_strict_mode', '1');
ini_set('session.cookie_httponly', '1');
// Włącz secure cookie tylko gdy HTTPS; lokalnie (http://localhost) ustaw 0
$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] === '443');
ini_set('session.cookie_secure', $isHttps ? '1' : '0');
ini_set('session.cookie_samesite', $isHttps ? 'None' : 'Lax');

session_start();
header("Content-Type: application/json; charset=utf-8");

/* ===== KONFIG ===== */
$config = require __DIR__.'/../config.php';
$MAX_ATTEMPTS = 3;
$BLOCK_MINUTES = 15;
$contextKey = 'panel';

try {

    $pdo = new PDO(
        "mysql:host={$config['db_host']};port={$config['db_port']};dbname={$config['db_name']};charset=utf8mb4",
        $config['db_user'],
        $config['db_pass'],
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );

    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $data = json_decode(file_get_contents("php://input"), true);

    $pin      = $data['pin'] ?? '';
    $deviceId = $data['device_id'] ?? null;
    $honeypot = trim($data['honeypot'] ?? '');

    if (!empty($honeypot)) {
        echo json_encode(["success" => false]);
        exit;
    }

    if (!$pin || !$deviceId) {
        echo json_encode(["success" => false]);
        exit;
    }

    /* ===== BLOKADA IP ===== */
    $stmt = $pdo->prepare("
        SELECT attempts, blocked_until
        FROM login_attempts
        WHERE ip = ? AND context = ?
    ");
    $stmt->execute([$ip, $contextKey]);
    $block = $stmt->fetch();

    if (!empty($block['blocked_until']) && strtotime($block['blocked_until']) > time()) {
        echo json_encode(["blocked" => true]);
        exit;
    }

    // ANTI-BOT: Progressive delay - każda próba czeka dłużej
    if (!empty($block['attempts']) && $block['attempts'] > 0) {
        $delaySeconds = min($block['attempts'] * 2, 10); // 2s, 4s, 6s... max 10s
        usleep($delaySeconds * 1_000_000);
    } else {
        usleep(500_000); // 0.5s minimum delay na każde logowanie
    }

    /* ===== 1. SZUKAMY MANAGERA PO PIN LUB TOKENIE ===== */
    try {
        // Sprawdź czy to token (dłuższy niż 4 znaki lub zawiera litery)
        $isToken = strlen($pin) > 4 || !preg_match('/^\d+$/', $pin);
        
        if ($isToken) {
            // Sprawdź czy to pierwsze logowanie (token)
            $stmt = $pdo->prepare("
                SELECT id, name, pin_token, pin_hash, role_level, device_id, ip_address
                FROM managers
                WHERE pin_token = ?
            ");
            $stmt->execute([$pin]);
            $tokenManager = $stmt->fetch();

            if ($tokenManager) {
                // PIERWSZE LOGOWANIE - zapisz device_id i pokaz formularz ustawienia PIN
                // Zapis device_id podczas pierwszego logowania z tokenem
                $stmt = $pdo->prepare("
                    UPDATE managers
                    SET device_id = ?, ip_address = ?
                    WHERE id = ?
                ");
                $stmt->execute([
                    $deviceId,
                    $ip,
                    $tokenManager['id']
                ]);
                
                echo json_encode([
                    "success" => true,
                    "first_login" => true,
                    "token" => $tokenManager['pin_token'],
                    "name" => $tokenManager['name']
                ]);
                exit;
            }
        }

        // Normalne logowanie - szukaj po PIN (4 cyfry)
        if (!$isToken) {
            $stmt = $pdo->query("
                SELECT id, name, pin_hash, role_level, device_id, ip_address
                FROM managers
            ");

            $manager = null;
            $is_first_login = false;

            while ($row = $stmt->fetch()) {
                // Jeśli pin_hash jest pusty, to pomiń (czeka na token)
                if (empty($row['pin_hash'])) {
                    continue;
                }
                // Normalnego logowanie - zweryfikuj PIN wobec pin_hash
                // Wszyscy managerowie mogą się logować, niezależnie od role_level (1, 2, 4, 9)
                if (password_verify($pin, $row['pin_hash'])) {
                    $manager = $row;
                    $is_first_login = false;
                    break;
                }
            }
        } else {
            $manager = null;
        }
    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode(["success" => false, "error" => "Błąd weryfikacji PIN: " . $e->getMessage()]);
        exit;
    }

    /* ===== ZŁY PIN -> LICZYMY PRÓBY ===== */
    if (!$manager) {

        $attempts = ($block['attempts'] ?? 0) + 1;

        // Log failed attempt
        $stmt = $pdo->prepare("
            INSERT INTO login_audit (ip, pin_provided, success, device_id, context)
            VALUES (?, ?, 0, ?, ?)
        ");
        $stmt->execute([$ip, $pin, $deviceId, $contextKey]);

        if ($attempts >= $MAX_ATTEMPTS) {
            $pdo->prepare("
                INSERT INTO login_attempts (ip, context, attempts, blocked_until)
                VALUES (?, ?, ?, DATE_ADD(NOW(), INTERVAL {$BLOCK_MINUTES} MINUTE))
                ON DUPLICATE KEY UPDATE
                    attempts = ?,
                    blocked_until = DATE_ADD(NOW(), INTERVAL {$BLOCK_MINUTES} MINUTE)
            ")->execute([$ip, $contextKey, $attempts, $attempts]);
        } else {
            $pdo->prepare("
                INSERT INTO login_attempts (ip, context, attempts)
                VALUES (?, ?, ?)
                ON DUPLICATE KEY UPDATE attempts = ?
            ")->execute([$ip, $contextKey, $attempts, $attempts]);
        }

        echo json_encode([
            "success" => false,
            "attempts_left" => max(0, $MAX_ATTEMPTS - $attempts)
        ]);
        exit;
    }

    /* ===== DOBRY PIN -> RESET PRÓB ===== */
    // Log successful login
    $stmt = $pdo->prepare("
        INSERT INTO login_audit (ip, manager_id, pin_provided, success, device_id, context)
        VALUES (?, ?, ?, 1, ?, ?)
    ");
    $stmt->execute([$ip, $manager['id'], $pin, $deviceId, $contextKey]);

    $pdo->prepare("
        DELETE FROM login_attempts
        WHERE ip = ? AND context = ?
    ")->execute([$ip, $contextKey]);

    /* ===== 2. DEVICE ===== */

    if ($manager['device_id'] === null) {
        $pdo->prepare("
            UPDATE managers
            SET device_id = ?, ip_address = ?
            WHERE id = ?
        ")->execute([
            $deviceId,
            $ip,
            $manager['id']
        ]);
    } elseif ($manager['device_id'] !== $deviceId || ($manager['ip_address'] !== null && $manager['ip_address'] !== $ip)) {
        echo json_encode([
            "success" => false,
            "error" => "DEVICE_BLOCKED"
        ]);
        exit;
    }

    /* ===== 3. SESJA ===== */
    session_regenerate_id(true);
    $_SESSION['manager'] = $manager['name'];
    $_SESSION['manager_id'] = $manager['id'];
    $_SESSION['role_level'] = (int)$manager['role_level'];
    $_SESSION['login_time'] = time();

    // Dla role_level=2 (kierownik) ustaw trwałą sesję (30 dni)
    if ((int)$manager['role_level'] === 2) {
        // Panel login używa własnej sesji, więc bezpośrednio ustawiamy ciasteczko
        $sessionName = session_name();
        $sessionId = session_id();
        $lifetime = 30 * 24 * 60 * 60; // 30 dni
        
        $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] === '443');
        
        setcookie(
            $sessionName,
            $sessionId,
            [
                'expires' => time() + $lifetime,
                'path' => '/',
                'domain' => '',
                'secure' => $isHttps,
                'httponly' => true,
                'samesite' => $isHttps ? 'None' : 'Lax'
            ]
        );
    }

    echo json_encode(["success" => true, "first_login" => $is_first_login]);
    exit;

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "error" => "SERVER_ERROR",
        "debug" => $e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine()
    ]);
    exit;
}
