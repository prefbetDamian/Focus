<?php
/**
 * API obsługi wniosków urlopowych
 */

session_start();
header('Content-Type: application/json; charset=utf-8');

// Loguj wszystkie błędy (nie wyświetlaj na ekranie)
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

try {
    $pdo = require_once __DIR__ . '/core/db.php';
    require_once __DIR__ . '/core/email.php';
    require_once __DIR__ . '/core/push.php';

    // Informacje o zalogowanym użytkowniku
    $isEmployee = isset($_SESSION['employee']) || isset($_SESSION['user_id']);
    $isManager  = isset($_SESSION['manager']) || isset($_SESSION['manager_id']);

    if (!$isEmployee && !$isManager) {
        echo json_encode(['success' => false, 'message' => 'Brak autoryzacji']);
        exit;
    }

    $rawInput = file_get_contents('php://input');
    $input = json_decode($rawInput, true);
    if (!is_array($input)) {
        $input = [];
    }

    $action = $_GET['action'] ?? ($input['action'] ?? null);
    if (!$action) {
        throw new Exception('Brak akcji');
    }

    switch ($action) {
        case 'create':
            // Złożenie nowego wniosku urlopowego przez pracownika
            if (!$isEmployee) {
                throw new Exception('Tylko pracownik może składać wniosek urlopowy');
            }

            $employeeId = $_SESSION['employee']['id'] ?? $_SESSION['user_id'] ?? null;
            if (!$employeeId) {
                throw new Exception('Nie można określić ID pracownika');
            }

            $startDate = trim((string)($input['start_date'] ?? ''));
            $endDate   = trim((string)($input['end_date'] ?? ''));
            $type      = $input['type'] ?? 'urlop';
            $reason    = $input['reason'] ?? null;

            if ($startDate === '' || $endDate === '') {
                throw new Exception('Brak wymaganych dat');
            }

            // Walidacja dat
            $startDt = new DateTime($startDate);
            $endDt   = new DateTime($endDate);
            if ($endDt < $startDt) {
                throw new Exception('Data zakończenia nie może być wcześniejsza niż data rozpoczęcia');
            }

            // Liczba dni (włącznie z dniem końcowym)
            $days = $endDt->diff($startDt)->days + 1;

            // Pobierz dane pracownika wraz z pulą urlopu i przypisanym kierownikiem
            $stmt = $pdo->prepare("SELECT first_name, last_name, COALESCE(vacation_days, 0) AS vacation_days, manager_id FROM employees WHERE id = ?");
            $stmt->execute([$employeeId]);
            $employee = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$employee) {
                throw new Exception('Pracownik nie znaleziony');
            }

            // Jeśli typ to urlop i brakuje dni z puli, automatycznie odrzuć wniosek
            if (strtolower((string)$type) === 'urlop' && $days > (int)$employee['vacation_days']) {
                $autoNotes = 'Automatycznie odrzucono: nie masz wystarczającej ilości dni urlopowych.';

                $stmt = $pdo->prepare("\n                    INSERT INTO absence_requests \n                    (employee_id, start_date, end_date, type, reason, status, reviewed_by, reviewed_at, notes)\n                    VALUES (?, ?, ?, ?, ?, 'rejected', NULL, NOW(), ?)\n                ");
                $stmt->execute([$employeeId, $startDate, $endDate, $type, $reason, $autoNotes]);
                $requestId = $pdo->lastInsertId();

                echo json_encode([
                    'success'    => false,
                    'message'    => 'Nie masz wystarczającej ilości dni urlopowych. Dostępne: ' . (int)$employee['vacation_days'] . ', wniosek o: ' . $days . ' dni.',
                    'request_id' => $requestId,
                ]);
                break;
            }

            // Przypisany kierownik pracownika (z ustawień)
            $employeeManagerId = isset($employee['manager_id']) ? (int)$employee['manager_id'] : null;

            // Sprawdź czy pracownik nie ma sesji pracy w dniach objętych wnioskiem
            $stmt = $pdo->prepare("
                SELECT DATE(start_time) as work_date
                FROM work_sessions
                WHERE employee_id = ?
                AND (absence_group_id IS NULL OR absence_group_id = 0)
                AND DATE(start_time) >= ?
                AND DATE(start_time) <= ?
                GROUP BY DATE(start_time)
                LIMIT 1
            ");
            $stmt->execute([$employeeId, $startDate, $endDate]);
            $workSession = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($workSession) {
                throw new Exception('Nie możesz złożyć wniosku - masz już sesję pracy w dniu: ' . date('d.m.Y', strtotime($workSession['work_date'])));
            }

            // Sprawdź czy nie ma już wniosku na te same lub pokrywające się daty
            $stmt = $pdo->prepare("
                SELECT id, start_date, end_date, type, status
                FROM absence_requests
                WHERE employee_id = ?
                AND status IN ('approved', 'pending')
                AND start_date <= ?
                AND end_date >= ?
            ");
            $stmt->execute([$employeeId, $endDate, $startDate]);
            $conflictingRequests = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Sprawdź również ręcznie dodane nieobecności w work_sessions
            $stmt = $pdo->prepare("
                SELECT
                    absence_group_id,
                    DATE(MIN(start_time)) AS start_date,
                    DATE(MAX(end_time)) AS end_date,
                    site_name AS type
                FROM work_sessions
                WHERE employee_id = ?
                AND absence_group_id IS NOT NULL
                AND site_name IN ('URLOP', 'L4')
                AND DATE(start_time) <= ?
                AND DATE(end_time) >= ?
                GROUP BY absence_group_id, site_name
            ");
            $stmt->execute([$employeeId, $endDate, $startDate]);
            $conflictingManual = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $allConflicts = array_merge($conflictingRequests, $conflictingManual);

            if (!empty($allConflicts)) {
                $conflictInfo = [];
                foreach ($allConflicts as $req) {
                    $source = isset($req['absence_group_id']) ? 'ręcznie dodana' : $req['status'];
                    $statusText = $source === 'approved' ? 'zaakceptowany' : ($source === 'pending' ? 'oczekujący' : $source);
                    $conflictInfo[] = sprintf(
                        '%s do %s (%s, %s)',
                        $req['start_date'],
                        $req['end_date'],
                        $req['type'],
                        $statusText
                    );
                }
                throw new Exception('Nie możesz złożyć wniosku - masz już wniosek na te daty: ' . implode('; ', $conflictInfo));
            }

            // Standardowy wniosek (oczekujący na rozpatrzenie)
            $stmt = $pdo->prepare("\n                INSERT INTO absence_requests \n                (employee_id, start_date, end_date, type, reason, status, assigned_manager_id)\n                VALUES (?, ?, ?, ?, ?, 'pending', ?)\n            ");
            $stmt->execute([$employeeId, $startDate, $endDate, $type, $reason, $employeeManagerId]);
            $requestId = $pdo->lastInsertId();

            // Kierownicy kadr (role_level = 4)
            $stmt = $pdo->query("\n                SELECT id, email, first_name, last_name \n                FROM managers \n                WHERE role_level = 4 AND email IS NOT NULL AND email != ''\n            ");
            $managers = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // E-mail z wnioskiem urlopowym na wspólną skrzynkę kadr
            $subject = '🔔 Nowy wniosek urlopowy - ' . $employee['first_name'] . ' ' . $employee['last_name'];
            $body  = "<html><body style='font-family: Arial, sans-serif;'>";
            $body .= '<h2>Nowy wniosek urlopowy</h2>';
            $body .= '<p><strong>Pracownik:</strong> ' . htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name']) . '</p>';
            $body .= '<p><strong>Typ:</strong> ' . htmlspecialchars($type) . '</p>';
            $body .= '<p><strong>Data:</strong> ' . htmlspecialchars($startDate) . ' - ' . htmlspecialchars($endDate) . ' (' . $days . ' dni)</p>';
            if ($reason) {
                $body .= '<p><strong>Powód:</strong> ' . nl2br(htmlspecialchars($reason)) . '</p>';
            }
            $body .= '<hr>';
            $body .= "<div style='text-align: center; margin: 30px 0;'>";
            $body .= "<a href='https://test.pref-bet.com/panel/dashboard.php' style='background: #4CAF50; color: white; padding: 18px 40px; text-decoration: none; border-radius: 10px; display: inline-block; font-size: 18px; font-weight: bold; box-shadow: 0 4px 8px rgba(0,0,0,0.2); text-transform: uppercase;'>🔔 KLIKNIJ ABY ROZPATRZYĆ WNIOSEK</a>";
            $body .= '</div>';
            $body .= "<hr><small style='color: #666;'>System RCP - Rejestracja Czasu Pracy</small>";
            $body .= '</body></html>';

            // Wspólna skrzynka kadr
            sendEmail('urlopy@pref-bet.com', $subject, $body);

            // PUSH: kierownicy kadr (rola 4)
            foreach ($managers as $manager) {
                try {
                    sendPushToManager(
                        $pdo,
                        (int)$manager['id'],
                        'Nowy wniosek urlopowy',
                        $employee['first_name'] . ' ' . $employee['last_name'] . ' złożył nowy wniosek urlopowy.',
                        'panel/dashboard.php'
                    );
                } catch (Throwable $e) {
                    // Błąd PUSH nie blokuje złożenia wniosku
                }
            }

            // PUSH: jeśli pracownik ma przypisanego kierownika (manager_id w employees), powiadom też jego
            if ($employeeManagerId > 0) {
                try {
                    sendPushToManager(
                        $pdo,
                        $employeeManagerId,
                        'Nowy wniosek urlopowy pracownika',
                        $employee['first_name'] . ' ' . $employee['last_name'] . ' złożył nowy wniosek urlopowy.',
                        'panel/dashboard.php'
                    );
                } catch (Throwable $e) {
                    // Błąd PUSH do przypisanego kierownika również nie blokuje złożenia wniosku
                }
            }

            echo json_encode([
                'success'    => true,
                'message'    => 'Wniosek złożony pomyślnie',
                'request_id' => $requestId,
            ]);
            break;

        case 'list':
            // Lista wniosków dla pracownika lub kierownika
            if (isset($_SESSION['employee']) || isset($_SESSION['user_id'])) {
                // Pracownik – widzi tylko swoje wnioski
                $employeeId = $_SESSION['employee']['id'] ?? $_SESSION['user_id'] ?? null;
                if (!$employeeId) {
                    throw new Exception('Nie można określić ID pracownika');
                }

                $stmt = $pdo->prepare("\n                    SELECT ar.*, e.first_name, e.last_name,\n                           m.first_name AS reviewer_first_name, m.last_name AS reviewer_last_name,\n                           am.first_name AS assigned_manager_first_name, am.last_name AS assigned_manager_last_name\n                    FROM absence_requests ar\n                    JOIN employees e ON ar.employee_id = e.id\n                    LEFT JOIN managers m ON ar.reviewed_by = m.id\n                    LEFT JOIN managers am ON ar.assigned_manager_id = am.id\n                    WHERE ar.employee_id = ?\n                    ORDER BY ar.requested_at DESC\n                ");
                $stmt->execute([$employeeId]);
            } elseif (isset($_SESSION['manager']) || isset($_SESSION['manager_id'])) {
                // Kierownik – widzi wnioski zależnie od roli
                $roleLevel = is_array($_SESSION['manager'] ?? null)
                    ? (int)($_SESSION['manager']['role_level'] ?? 0)
                    : (int)($_SESSION['role_level'] ?? 0);

                $managerId = $_SESSION['manager']['id'] ?? $_SESSION['manager_id'] ?? null;
                $status    = $_GET['status'] ?? null;

                if ($roleLevel >= 4) {
                    // Kadry / admin – wszystkie wnioski
                    $sql = "\n                        SELECT ar.*, e.first_name, e.last_name,\n                               m.first_name AS reviewer_first_name, m.last_name AS reviewer_last_name,\n                               am.first_name AS assigned_manager_first_name, am.last_name AS assigned_manager_last_name\n                        FROM absence_requests ar\n                        JOIN employees e ON ar.employee_id = e.id\n                        LEFT JOIN managers m ON ar.reviewed_by = m.id\n                        LEFT JOIN managers am ON ar.assigned_manager_id = am.id\n                    ";
                    if ($status) {
                        $sql .= ' WHERE ar.status = ? ';
                        $stmt = $pdo->prepare($sql . ' ORDER BY ar.requested_at DESC');
                        $stmt->execute([$status]);
                    } else {
                        $stmt = $pdo->query($sql . ' ORDER BY ar.requested_at DESC');
                    }
                } elseif ($roleLevel === 2 && $managerId) {
                    // Kierownik (rola 2) – tylko wnioski jego pracowników (employees.manager_id = ten kierownik)
                    $sql = "\n                        SELECT ar.*, e.first_name, e.last_name,\n                               m.first_name AS reviewer_first_name, m.last_name AS reviewer_last_name,\n                               am.first_name AS assigned_manager_first_name, am.last_name AS assigned_manager_last_name\n                        FROM absence_requests ar\n                        JOIN employees e ON ar.employee_id = e.id\n                        LEFT JOIN managers m ON ar.reviewed_by = m.id\n                        LEFT JOIN managers am ON ar.assigned_manager_id = am.id\n                        WHERE e.manager_id = :managerId\n                    ";
                    if ($status) {
                        $sql .= ' AND ar.status = :status ';
                    }
                    $sql .= ' ORDER BY ar.requested_at DESC';

                    $stmt = $pdo->prepare($sql);
                    $params = [':managerId' => $managerId];
                    if ($status) {
                        $params[':status'] = $status;
                    }
                    $stmt->execute($params);
                } else {
                    throw new Exception('Brak uprawnień do przeglądania wniosków');
                }
            } else {
                throw new Exception('Brak autoryzacji');
            }

            $requests = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode([
                'success'  => true,
                'requests' => $requests,
            ]);
            break;

        case 'approve':
            // Akceptacja wniosku
            if (!isset($_SESSION['manager']) && !isset($_SESSION['manager_id'])) {
                throw new Exception('Brak uprawnień do akceptacji wniosków');
            }

            $roleLevel = is_array($_SESSION['manager'] ?? null)
                ? (int)($_SESSION['manager']['role_level'] ?? 0)
                : (int)($_SESSION['role_level'] ?? 0);

            $requestId = $input['request_id'] ?? null;
            $notes     = $input['notes'] ?? null;
            $managerId = $_SESSION['manager']['id'] ?? $_SESSION['manager_id'] ?? null;

            if (!$requestId) {
                throw new Exception('Brak ID wniosku');
            }

            // Pobierz wniosek wraz z przypisanym kierownikiem pracownika
            $stmt = $pdo->prepare("\n                SELECT ar.*, e.first_name, e.last_name, e.manager_id AS employee_manager_id\n                FROM absence_requests ar\n                JOIN employees e ON ar.employee_id = e.id\n                WHERE ar.id = ?\n            ");
            $stmt->execute([$requestId]);
            $request = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$request) {
                throw new Exception('Wniosek nie znaleziony');
            }

            if ($request['status'] !== 'pending') {
                throw new Exception('Wniosek został już rozpatrzony');
            }

            // Sprawdź, czy ten kierownik może akceptować ten wniosek
            $employeeManagerId = isset($request['employee_manager_id']) ? (int)$request['employee_manager_id'] : 0;
            if ($roleLevel < 4) {
                // Dla roli 2: musi być przypisanym kierownikiem tego pracownika
                if ($roleLevel !== 2 || !$managerId || $employeeManagerId !== (int)$managerId) {
                    throw new Exception('Brak uprawnień do akceptacji tego wniosku');
                }
            }

            // Oblicz liczbę dni wniosku (włącznie z datą końcową)
            $startDt = new DateTime($request['start_date']);
            $endDt   = new DateTime($request['end_date']);
            $days    = $endDt->diff($startDt)->days + 1;

            // Jeśli typ to urlop, odejmij dni z puli urlopowej pracownika
            if (strtolower((string)$request['type']) === 'urlop') {
                $upd = $pdo->prepare('UPDATE employees SET vacation_days = GREATEST(vacation_days - ?, 0) WHERE id = ?');
                $upd->execute([$days, (int)$request['employee_id']]);
            }

            // Aktualizuj status wniosku
            $stmt = $pdo->prepare("\n                UPDATE absence_requests \n                SET status = 'approved', reviewed_by = ?, reviewed_at = NOW(), notes = ?\n                WHERE id = ?\n            ");
            $stmt->execute([$managerId, $notes, $requestId]);

            // Generuj unikalny absence_group_id
            $absenceGroupId = (int)(microtime(true) * 1000);

            // Twórz wpisy w tabeli work_sessions dla każdego dnia
            $startDate = new DateTime($request['start_date']);
            $endDate   = new DateTime($request['end_date']);
            $endDate->modify('+1 day'); // Include end date

            // Przygotuj site_name na podstawie typu (URLOP / L4 itd.)
            $siteName = strtoupper($request['type']);

            $insertStmt = $pdo->prepare("\n                INSERT INTO work_sessions \n                (employee_id, first_name, last_name, site_name, start_time, end_time, \n                 duration_seconds, absence_group_id, manager_id, manager_comment)\n                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)\n            ");

            $interval = new DateInterval('P1D');
            $period   = new DatePeriod($startDate, $interval, $endDate);

            foreach ($period as $date) {
                $dayStart        = $date->format('Y-m-d 08:00:00');
                $dayEnd          = $date->format('Y-m-d 16:00:00');
                $durationSeconds = 8 * 3600; // 8 godzin

                $insertStmt->execute([
                    $request['employee_id'],
                    $request['first_name'],
                    $request['last_name'],
                    $siteName,
                    $dayStart,
                    $dayEnd,
                    $durationSeconds,
                    $absenceGroupId,
                    $managerId,
                    'Zaakceptowany wniosek urlopowy. ' . ($notes ?? ''),
                ]);
            }

            // PUSH: powiadom pracownika o akceptacji wniosku
            try {
                $title = '✅ Wniosek urlopowy zaakceptowany';

                $bodyParts   = [];
                $bodyParts[] = trim($request['first_name'] . ' ' . $request['last_name']);
                $bodyParts[] = sprintf('Termin: %s - %s (%d dni)', $request['start_date'], $request['end_date'], $days);
                if (!empty($notes)) {
                    $bodyParts[] = 'Notatka: ' . $notes;
                }

                $body = implode(" \n", $bodyParts);

                // panel.php – pracownik po kliknięciu przejdzie do panelu
                sendPushToEmployee($pdo, (int)$request['employee_id'], $title, $body, 'panel.php');
            } catch (Throwable $e) {
                // W razie problemu z PUSH nie blokujemy akceptacji wniosku – logujemy tylko błąd
                error_log('Absence approve PUSH error: ' . $e->getMessage());
            }

            echo json_encode([
                'success' => true,
                'message' => 'Wniosek zaakceptowany i dodany do kalendarza',
            ]);
            break;

        case 'reject':
            // Odrzucenie wniosku
            if (!isset($_SESSION['manager']) && !isset($_SESSION['manager_id'])) {
                throw new Exception('Brak uprawnień do odrzucania wniosków');
            }

            $roleLevel = is_array($_SESSION['manager'] ?? null)
                ? (int)($_SESSION['manager']['role_level'] ?? 0)
                : (int)($_SESSION['role_level'] ?? 0);

            $requestId = $input['request_id'] ?? null;
            $notes     = $input['notes'] ?? null;
            $managerId = $_SESSION['manager']['id'] ?? $_SESSION['manager_id'] ?? null;

            if (!$requestId) {
                throw new Exception('Brak ID wniosku');
            }

            // Pobierz wniosek wraz z przypisanym kierownikiem pracownika
            $stmt = $pdo->prepare("\n                SELECT ar.*, e.first_name, e.last_name, e.manager_id AS employee_manager_id\n                FROM absence_requests ar\n                JOIN employees e ON ar.employee_id = e.id\n                WHERE ar.id = ?\n            ");
            $stmt->execute([$requestId]);
            $request = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$request) {
                throw new Exception('Wniosek nie znaleziony');
            }

            if ($request['status'] !== 'pending') {
                throw new Exception('Wniosek został już rozpatrzony');
            }

            // Sprawdź, czy ten kierownik może odrzucić ten wniosek
            $employeeManagerId = isset($request['employee_manager_id']) ? (int)$request['employee_manager_id'] : 0;
            if ($roleLevel < 4) {
                // Dla roli 2: musi być przypisanym kierownikiem tego pracownika
                if ($roleLevel !== 2 || !$managerId || $employeeManagerId !== (int)$managerId) {
                    throw new Exception('Brak uprawnień do odrzucenia tego wniosku');
                }
            }

            // Aktualizuj status wniosku
            $stmt = $pdo->prepare("\n                UPDATE absence_requests \n                SET status = 'rejected', reviewed_by = ?, reviewed_at = NOW(), notes = ?\n                WHERE id = ?\n            ");
            $stmt->execute([$managerId, $notes, $requestId]);

            // PUSH: powiadom pracownika o odrzuceniu wniosku
            try {
                $title = '❌ Wniosek urlopowy odrzucony';

                $bodyParts   = [];
                $bodyParts[] = trim($request['first_name'] . ' ' . $request['last_name']);
                $bodyParts[] = sprintf('Termin: %s - %s', $request['start_date'], $request['end_date']);
                if (!empty($notes)) {
                    $bodyParts[] = 'Powód: ' . $notes;
                }

                $body = implode(" \n", $bodyParts);

                sendPushToEmployee($pdo, (int)$request['employee_id'], $title, $body, 'panel.php');
            } catch (Throwable $e) {
                error_log('Absence reject PUSH error: ' . $e->getMessage());
            }

            echo json_encode([
                'success' => true,
                'message' => 'Wniosek odrzucony',
            ]);
            break;

        case 'count_pending':
            // Licznik oczekujących wniosków dla kierownika
            if (!isset($_SESSION['manager']) && !isset($_SESSION['manager_id'])) {
                throw new Exception('Brak uprawnień');
            }

            $roleLevel = is_array($_SESSION['manager'] ?? null)
                ? (int)($_SESSION['manager']['role_level'] ?? 0)
                : (int)($_SESSION['role_level'] ?? 0);
            $managerId = $_SESSION['manager']['id'] ?? $_SESSION['manager_id'] ?? null;

            if ($roleLevel >= 4) {
                // Kadry / admin – wszystkie wnioski
                $stmt   = $pdo->query("SELECT COUNT(*) AS count FROM absence_requests WHERE status = 'pending'");
                $result = $stmt->fetch(PDO::FETCH_ASSOC);

                echo json_encode([
                    'success' => true,
                    'count'   => (int)$result['count'],
                ]);
            } elseif ($roleLevel === 2 && $managerId) {
                // Kierownik (rola 2) – tylko wnioski jego pracowników
                $stmt = $pdo->prepare("\n                    SELECT COUNT(*) AS count\n                    FROM absence_requests ar\n                    JOIN employees e ON ar.employee_id = e.id\n                    WHERE ar.status = 'pending'\n                      AND e.manager_id = ?\n                ");
                $stmt->execute([$managerId]);
                $result = $stmt->fetch(PDO::FETCH_ASSOC);

                echo json_encode([
                    'success' => true,
                    'count'   => (int)($result['count'] ?? 0),
                ]);
            } else {
                throw new Exception('Brak uprawnień');
            }
            break;

        default:
            throw new Exception('Nieznana akcja: ' . $action);
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Błąd bazy danych: ' . $e->getMessage(),
    ]);
    error_log('Absence API PDO Error: ' . $e->getMessage());
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
    ]);
    error_log('Absence API Error: ' . $e->getMessage());
}
