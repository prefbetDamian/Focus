<?php
/**
 * CRON: Zamyka automatycznie otwarte sesje pracy o północy
 * Maksymalny czas pracy w danym dniu to 8 godzin
 * 
 * WERSJA ZABEZPIECZONA - wymaga klucza API
 * 
 * URUCHOMIENIE:
 * curl -s "https://test.pref-bet.com/cron/cron_close_secured.php?key=TWOJ_KLUCZ"
 * 
 * CRON EXPRESSION: 59 23 * * * (każdego dnia o 23:59)
 */

declare(strict_types=1);

// ===== ZABEZPIECZENIE KLUCZ API =====
$apiKey = $_GET['key'] ?? $_SERVER['HTTP_X_CRON_KEY'] ?? '';

// ZMIEŃ TEN KLUCZ NA SWÓJ BEZPIECZNY!
$expectedKey = 'RCP_CLOSE_2026_' . md5('close-sessions@pref-bet.com'); 

if ($apiKey !== $expectedKey) {
    http_response_code(403);
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'error' => 'Invalid API key',
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    exit;
}
// ===== KONIEC ZABEZPIECZENIA =====

// Logowanie dla debugowania
$logFile = __DIR__ . '/../cron_log.txt';

try {
    file_put_contents($logFile, date('Y-m-d H:i:s') . " - [SECURED] Cron close started\n", FILE_APPEND);

    $config = require __DIR__.'/../config.php';

    $pdo = new PDO(
        "mysql:host={$config['db_host']};port={$config['db_port']};dbname={$config['db_name']};charset=utf8mb4",
        $config['db_user'],
        $config['db_pass'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    require __DIR__ . '/../core/push.php';

    /*
     * Zamykamy wszystkie otwarte sesje jako AUTO, pilnując żeby
     * łączny czas pracy danego pracownika w danym dniu nie przekroczył 8h.
     * Jeśli w danym dniu pracownik miał już jakieś sesje, to cały dzień
     * (suma wszystkich sesji) jest ścinany / dokładany do stałych 8 godzin.
     */

    // Pobierz wszystkie otwarte sesje
    $stmtOpen = $pdo->query("
        SELECT id, employee_id, start_time, site_name
        FROM work_sessions
        WHERE end_time IS NULL
    ");

    $openSessions = $stmtOpen->fetchAll(PDO::FETCH_ASSOC);
    $closedCount  = 0;
    $pushErrorCount = 0;
    $sessionIds = [];

    foreach ($openSessions as $session) {
        $employeeId = (int)$session['employee_id'];
        $startDate  = substr($session['start_time'], 0, 10); // YYYY-MM-DD
        $sessionIds[] = (int)$session['id'];

        // Suma dotychczasowych zakończonych sesji w tym dniu dla pracownika
        $stmtSum = $pdo->prepare("
            SELECT COALESCE(SUM(duration_seconds), 0)
            FROM work_sessions
            WHERE employee_id = ?
              AND DATE(start_time) = ?
              AND end_time IS NOT NULL
        ");
        $stmtSum->execute([$employeeId, $startDate]);
        $closedSeconds = (int)$stmtSum->fetchColumn();

        // Ile sekund brakuje do pełnych 8h (28800s) w tym dniu
        $remaining = 28800 - $closedSeconds;
        if ($remaining < 0) {
            $remaining = 0; // już ma >8h, więc nie dokładamy nic więcej
        }

        $endTime = $startDate . ' 23:59:59';

        $stmtUpdate = $pdo->prepare("
            UPDATE work_sessions
            SET end_time = ?,
                duration_seconds = ?,
                status = 'AUTO'
            WHERE id = ?
        ");
        $stmtUpdate->execute([$endTime, $remaining, (int)$session['id']]);

        // PUSH do pracownika o automatycznym zamknięciu sesji (status AUTO)
        try {
            if ($employeeId > 0) {
                $siteName = $session['site_name'] ?? '';
                $title = 'Sesja automatycznie zamknięta';
                $body = sprintf(
                    'Twoja sesja pracy %s została automatycznie zakończona i oczekuje na akceptację kierownika.',
                    $siteName ? ('na budowie ' . $siteName) : ''
                );

                sendPushToEmployee($pdo, $employeeId, $title, $body, 'panel.php');
            }
        } catch (Throwable $e) {
            $pushErrorCount++;
            error_log(sprintf(
                "[CRON CLOSE SECURED] Push error for employee %d: %s",
                $employeeId,
                $e->getMessage()
            ));
        }

        $closedCount++;
    }

    $message = date('Y-m-d H:i:s') . " - [SECURED] Closed $closedCount open sessions (AUTO, max 8h/day)";
    if ($pushErrorCount > 0) {
        $message .= ", Push errors: $pushErrorCount";
    }
    $message .= "\n";
    
    file_put_contents($logFile, $message, FILE_APPEND);

    error_log(sprintf(
        "[CRON CLOSE SECURED] Execution completed. Closed: %d sessions, Push errors: %d",
        $closedCount,
        $pushErrorCount
    ));

    // Zwróć JSON z wynikami
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'timestamp' => date('Y-m-d H:i:s'),
        'closed' => $closedCount,
        'push_errors' => $pushErrorCount,
        'session_ids' => $sessionIds
    ], JSON_PRETTY_PRINT);

} catch (Throwable $e) {
    $errorMsg = sprintf(
        "[CRON CLOSE SECURED] Critical error: %s in %s:%d",
        $e->getMessage(),
        $e->getFile(),
        $e->getLine()
    );
    
    file_put_contents($logFile, date('Y-m-d H:i:s') . " - ERROR: " . $e->getMessage() . "\n", FILE_APPEND);
    error_log($errorMsg);
    
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    exit(1);
}
