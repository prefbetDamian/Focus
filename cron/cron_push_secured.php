<?php
/**
 * CRON: wysyłanie powiadomień PUSH do pracowników,
 * którzy w danym dniu mają SUMĘ czasu pracy >= 10 godzin
 * i wciąż mają otwartą sesję.
 *
 * WERSJA ZABEZPIECZONA - wymaga klucza API
 * 
 * Częstotliwość powiadomień (np. co 1 minutę) wynika
 * z harmonogramu uruchamiania tego skryptu w CRON.
 * 
 * UŻYCIE:
 * curl -s "https://test.pref-bet.com/cron/cron_push_secured.php?key=TWOJ_KLUCZ"
 */

declare(strict_types=1);

// ===== ZABEZPIECZENIE KLUCZ API =====
$apiKey = $_GET['key'] ?? $_SERVER['HTTP_X_CRON_KEY'] ?? '';

// ZMIEŃ TEN KLUCZ NA SWÓJ BEZPIECZNY!
$expectedKey = 'RCP_CRON_2026_' . md5('urlopy@pref-bet.com'); 

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

try {
    $pdo = require __DIR__ . '/../core/db.php';
    require __DIR__ . '/../core/push.php';

    // Znajdź pracowników, którzy DZISIAJ mają sumę czasu pracy >= 10h
    // (zamknięte sesje + bieżąca otwarta) i nadal pracują (co najmniej 1 otwarta sesja)
    $sql = "
        SELECT
            ws.employee_id,
            SUM(
                CASE
                    WHEN ws.end_time IS NULL THEN TIMESTAMPDIFF(SECOND, ws.start_time, NOW())
                    ELSE ws.duration_seconds
                END
            ) AS total_seconds,
            SUM(CASE WHEN ws.end_time IS NULL THEN 1 ELSE 0 END) AS open_sessions
        FROM work_sessions ws
        WHERE ws.employee_id IS NOT NULL
          AND DATE(ws.start_time) = CURDATE()
          AND (ws.absence_group_id IS NULL OR ws.absence_group_id = 0)
          AND (ws.status IS NULL OR ws.status <> 'REJECTED')
        GROUP BY ws.employee_id
        HAVING total_seconds >= 10 * 3600
           AND open_sessions > 0
    ";

    $stmt = $pdo->query($sql);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $sentCount = 0;
    $errorCount = 0;
    $employeeIds = [];

    foreach ($rows as $row) {
        $employeeId = (int)$row['employee_id'];
        if ($employeeId <= 0) {
            continue;
        }

        $employeeIds[] = $employeeId;

        try {
            sendPushToEmployee(
                $pdo,
                $employeeId,
                'RCP – ponad 10 godzin pracy',
                'Przepracowałeś już dzisiaj ponad 10 godzin i nadal pracujesz. Sprawdź swój czas pracy w panelu.',
                'panel.php'
            );
            $sentCount++;
        } catch (Throwable $e) {
            $errorCount++;
            error_log(sprintf(
                "[CRON PUSH SECURED] Error sending to employee %d: %s",
                $employeeId,
                $e->getMessage()
            ));
        }
    }

    // Loguj podsumowanie wykonania crona
    $summary = sprintf(
        "[CRON PUSH SECURED] Execution completed. Checked: %d employees, Sent: %d notifications, Errors: %d",
        count($rows),
        $sentCount,
        $errorCount
    );
    error_log($summary);

    // Zwróć JSON z wynikami
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'timestamp' => date('Y-m-d H:i:s'),
        'checked' => count($rows),
        'sent' => $sentCount,
        'errors' => $errorCount,
        'employee_ids' => $employeeIds
    ], JSON_PRETTY_PRINT);

} catch (Throwable $e) {
    $errorMsg = sprintf(
        "[CRON PUSH SECURED] Critical error: %s in %s:%d",
        $e->getMessage(),
        $e->getFile(),
        $e->getLine()
    );
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
