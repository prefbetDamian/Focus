<?php
session_start();

// Uruchom scheduler w tle (nieblokująco)
require_once __DIR__ . '/../core/scheduler.php';
register_shutdown_function(function() {
    try {
        runScheduler();
    } catch (Throwable $e) {
        // Ignoruj błędy schedulera
    }
});

// Odświeżenie sesji
$_SESSION['last_activity'] = time();
echo json_encode(['ok' => true]);
