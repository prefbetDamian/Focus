<?php
/**
 * Middleware sprawdzający autentykację administratora
 * Dołącz na początku każdej strony panelu admin
 */

session_start();

$config = require __DIR__ . '/admin_config.php';

// Sprawdź sesję admina
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

// Sprawdź ważność sesji (timeout)
if (isset($_SESSION['admin_login_time'])) {
    $sessionAge = time() - $_SESSION['admin_login_time'];
    
    if ($sessionAge > $config['session_timeout']) {
        session_destroy();
        header('Location: login.php?expired=1');
        exit;
    }
}

// Sprawdź czy IP się nie zmienił (dodatkowe zabezpieczenie)
if (isset($_SESSION['admin_ip']) && $_SESSION['admin_ip'] !== $_SERVER['REMOTE_ADDR']) {
    session_destroy();
    header('Location: login.php?security=1');
    exit;
}

// Odśwież czas ostatniej aktywności
$_SESSION['admin_last_activity'] = time();
