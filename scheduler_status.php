<?php
/**
 * Sprawdza status schedulera - pliki lock, ostatnie wykonania, etc.
 * 
 * ZABEZPIECZENIE: Odkomentuj poniższą linię aby wymagać logowania administratora
 */

declare(strict_types=1);

// Opcjonalne zabezpieczenie - odkomentuj aby wymagać logowania
// require_once __DIR__ . '/admin/check_admin.php';

header('Content-Type: application/json');

$lockDir = __DIR__ . '/cron/locks';
$logFile = __DIR__ . '/cron_log.txt';

$status = [
    'timestamp' => date('Y-m-d H:i:s'),
    'locks' => [],
    'last_logs' => [],
    'system_ok' => true
];

// Sprawdź pliki lock
if (is_dir($lockDir)) {
    $lockFiles = glob($lockDir . '/*.lock');
    foreach ($lockFiles as $file) {
        $taskName = basename($file, '.lock');
        $lastRun = (int)file_get_contents($file);
        $timeSince = time() - $lastRun;
        
        $status['locks'][$taskName] = [
            'last_run' => date('Y-m-d H:i:s', $lastRun),
            'seconds_ago' => $timeSince,
            'minutes_ago' => round($timeSince / 60, 1),
            'next_expected' => $taskName === 'close_sessions' ? '23:59' : 'co 30 min'
        ];
    }
} else {
    $status['system_ok'] = false;
    $status['error'] = 'Katalog locks nie istnieje';
}

// Ostatnie logi
if (file_exists($logFile)) {
    $logs = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $status['last_logs'] = array_slice($logs, -10); // ostatnie 10 wpisów
    $status['total_log_lines'] = count($logs);
} else {
    $status['last_logs'] = ['Brak logów'];
}

// Sprawdź czy scheduler był ostatnio uruchamiany (w ciągu ostatniej godziny)
$hasRecentActivity = false;
foreach ($status['locks'] as $lock) {
    if ($lock['seconds_ago'] < 3600) {
        $hasRecentActivity = true;
        break;
    }
}

$status['active'] = $hasRecentActivity;

// Info o aktualnej godzinie (dla zadania close_sessions)
$currentHour = (int)date('H');
$currentMinute = (int)date('i');
$status['current_time'] = [
    'hour' => $currentHour,
    'minute' => $currentMinute,
    'close_sessions_window' => ($currentHour === 23 && $currentMinute >= 59) || ($currentHour === 0 && $currentMinute <= 30)
];

echo json_encode($status, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
