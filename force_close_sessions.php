<?php
/**
 * RĘCZNE ZAMKNIĘCIE WSZYSTKICH OTWARTYCH SESJI
 * 
 * Zamyka wszystkie otwarte sesje w work_sessions:
 * - Sesje z dzisiaj: zamyka o 23:59:59 dzisiaj
 * - Sesje z wczoraj/starsze: zamyka o 23:59:59 tego dnia
 * - Respektuje limit 8h/dzień (28800s)
 * - Wysyła powiadomienia push
 * 
 * UŻYCIE:
 * 1. Odwiedź: https://test.pref-bet.com/force_close_sessions.php
 * 2. Lub uruchom z CLI: php force_close_sessions.php
 */

require_once __DIR__ . '/core/db.php';
require_once __DIR__ . '/core/push.php';

header('Content-Type: application/json; charset=utf-8');

try {
    // Pobierz WSZYSTKIE otwarte sesje (bez względu na datę)
    $stmtOpen = $pdo->query("
        SELECT id, employee_id, start_time, site_name
        FROM work_sessions
        WHERE end_time IS NULL
        ORDER BY start_time ASC
    ");
    
    $openSessions = $stmtOpen->fetchAll(PDO::FETCH_ASSOC);
    $closedCount = 0;
    $results = [];
    
    if (empty($openSessions)) {
        echo json_encode([
            'success' => true,
            'message' => 'Brak otwartych sesji do zamknięcia',
            'closed_count' => 0
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    foreach ($openSessions as $session) {
        $sessionId = (int)$session['id'];
        $employeeId = (int)$session['employee_id'];
        $startTime = $session['start_time'];
        $startDate = substr($startTime, 0, 10); // YYYY-MM-DD
        $siteName = $session['site_name'] ?? '';
        
        // Suma dotychczasowych zakończonych sesji w tym dniu
        $stmtSum = $pdo->prepare("
            SELECT COALESCE(SUM(duration_seconds), 0)
            FROM work_sessions
            WHERE employee_id = ?
              AND DATE(start_time) = ?
              AND end_time IS NOT NULL
        ");
        $stmtSum->execute([$employeeId, $startDate]);
        $closedSeconds = (int)$stmtSum->fetchColumn();
        
        // Ile sekund brakuje do pełnych 8h (28800s)
        $remaining = 28800 - $closedSeconds;
        if ($remaining < 0) {
            $remaining = 0;
        }
        
        // Zamknij sesję o 23:59:59 tego dnia
        $endTime = $startDate . ' 23:59:59';
        
        $stmtUpdate = $pdo->prepare("
            UPDATE work_sessions
            SET end_time = ?,
                duration_seconds = ?,
                status = 'AUTO'
            WHERE id = ?
        ");
        $stmtUpdate->execute([$endTime, $remaining, $sessionId]);
        
        $closedCount++;
        
        // Wyślij powiadomienie push
        try {
            if ($employeeId > 0) {
                $title = 'Sesja automatycznie zamknięta';
                $body = sprintf(
                    'Twoja sesja pracy%s została automatycznie zakończona i oczekuje na akceptację kierownika.',
                    $siteName ? (' na budowie ' . $siteName) : ''
                );
                
                sendPushToEmployee($pdo, $employeeId, $title, $body, 'panel.php');
            }
        } catch (Throwable $pushError) {
            // Ignoruj błędy push - sesja i tak jest zamknięta
        }
        
        $results[] = [
            'session_id' => $sessionId,
            'employee_id' => $employeeId,
            'start_time' => $startTime,
            'end_time' => $endTime,
            'duration_seconds' => $remaining,
            'site_name' => $siteName
        ];
    }
    
    echo json_encode([
        'success' => true,
        'message' => "Zamknięto $closedCount sesji",
        'closed_count' => $closedCount,
        'sessions' => $results
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Database error',
        'message' => $e->getMessage(),
        'file' => basename($e->getFile()),
        'line' => $e->getLine()
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
}
