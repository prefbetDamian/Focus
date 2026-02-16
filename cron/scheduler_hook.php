<?php
/**
 * Endpoint dla wewnętrznego schedulera
 * 
 * Może być wywoływany przez:
 * - Service Worker (okresowo, np. co 10 minut)
 * - Dowolne żądanie API (automatycznie)
 * - Ręcznie (dla testowania)
 */

declare(strict_types=1);

// Prevent output before headers
ob_start();

// Sprawdź czy wymagane pliki istnieją
$schedulerFile = __DIR__ . '/../core/scheduler.php';
$dbFile = __DIR__ . '/../core/db.php';

if (!file_exists($schedulerFile)) {
    if (ob_get_level()) ob_end_clean();
    http_response_code(500);
    header('Content-Type: application/json');
    die(json_encode([
        'success' => false,
        'error' => 'Scheduler file not found',
        'path' => 'core/scheduler.php',
        'timestamp' => date('Y-m-d H:i:s')
    ]));
}

if (!file_exists($dbFile)) {
    if (ob_get_level()) ob_end_clean();
    http_response_code(500);
    header('Content-Type: application/json');
    die(json_encode([
        'success' => false,
        'error' => 'Database config not found',
        'path' => 'core/db.php',
        'timestamp' => date('Y-m-d H:i:s')
    ]));
}

// Wymagaj includowania schedulera
require_once $schedulerFile;

// Uruchom scheduler
try {
    $pdo = require $dbFile;
    runScheduler($pdo);
    
    // Clear any buffered output
    if (ob_get_level()) ob_end_clean();
    
    // Zwróć prosty status (bez wrażliwych danych)
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'timestamp' => date('Y-m-d H:i:s'),
        'message' => 'Scheduler executed'
    ]);
    
} catch (Throwable $e) {
    // Clear buffer
    if (ob_get_level()) ob_end_clean();
    
    // Loguj szczegóły błędu
    $logFile = __DIR__ . '/../cron_log.txt';
    $errorMsg = sprintf(
        "[%s] [SCHEDULER_HOOK ERROR] %s in %s:%d\n",
        date('Y-m-d H:i:s'),
        $e->getMessage(),
        basename($e->getFile()),
        $e->getLine()
    );
    @file_put_contents($logFile, $errorMsg, FILE_APPEND);
    
    http_response_code(500);
    header('Content-Type: application/json');
    
    // ZAWSZE pokazuj szczegóły błędu (dla debugowania na hostingu)
    // Po naprawie można to zmienić
    echo json_encode([
        'success' => false,
        'error' => 'Scheduler error',
        'timestamp' => date('Y-m-d H:i:s'),
        'message' => $e->getMessage(),
        'file' => basename($e->getFile()),
        'line' => $e->getLine(),
        'trace' => array_slice($e->getTrace(), 0, 3) // Pierwsze 3 linijki stack trace
    ]);
}
