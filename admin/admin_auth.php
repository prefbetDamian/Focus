<?php
/**
 * Backend autentykacji panelu administratora
 */

session_start();
header('Content-Type: application/json; charset=utf-8');

$config = require __DIR__ . '/admin_config.php';
$logFile = __DIR__ . '/admin_access.log';

// Sprawdź blokadę
if (isset($_GET['check_lockout'])) {
    $lockData = getLockoutStatus();
    echo json_encode($lockData);
    exit;
}

// Wymuś HTTPS w produkcji
if ($config['force_https'] && (!isset($_SERVER['HTTPS']) || $_SERVER['HTTPS'] !== 'on')) {
    echo json_encode(['success' => false, 'message' => 'Wymagane połączenie HTTPS']);
    exit;
}

// Sprawdź dozwolone IP
if (!empty($config['allowed_ips']) && !in_array($_SERVER['REMOTE_ADDR'], $config['allowed_ips'])) {
    logAccess('BLOCKED_IP', $_SERVER['REMOTE_ADDR']);
    echo json_encode(['success' => false, 'message' => 'Dostęp zabroniony z tego adresu IP']);
    exit;
}

// Sprawdź blokadę przed próbą logowania
$lockData = getLockoutStatus();
if ($lockData['locked']) {
    logAccess('LOCKED_ATTEMPT', $_SERVER['REMOTE_ADDR']);
    echo json_encode([
        'success' => false,
        'locked' => true,
        'message' => 'Konto zablokowane',
        'lockout_time' => $lockData['lockout_time']
    ]);
    exit;
}

// Pobierz dane z requestu
$data = json_decode(file_get_contents('php://input'), true);
$password = $data['password'] ?? '';

if (empty($password)) {
    echo json_encode(['success' => false, 'message' => 'Brak hasła']);
    exit;
}

// Hashuj hasło i porównaj
$passwordHash = hash('sha256', $password);

if ($passwordHash === $config['admin_password_hash']) {
    // SUKCES - zalogowano
    session_regenerate_id(true);
    $_SESSION['admin_logged_in'] = true;
    $_SESSION['admin_login_time'] = time();
    $_SESSION['admin_ip'] = $_SERVER['REMOTE_ADDR'];
    
    // Wyczyść nieudane próby
    unset($_SESSION['admin_failed_attempts']);
    unset($_SESSION['admin_lockout_until']);
    
    logAccess('SUCCESS', $_SERVER['REMOTE_ADDR'], $password);
    
    echo json_encode(['success' => true]);
    exit;
    
} else {
    // BŁĄD - złe hasło
    if (!isset($_SESSION['admin_failed_attempts'])) {
        $_SESSION['admin_failed_attempts'] = 0;
    }
    $_SESSION['admin_failed_attempts']++;
    
    $attemptsLeft = $config['max_login_attempts'] - $_SESSION['admin_failed_attempts'];
    
    logAccess('FAILED', $_SERVER['REMOTE_ADDR'], $password);
    
    // Sprawdź czy przekroczono limit
    if ($_SESSION['admin_failed_attempts'] >= $config['max_login_attempts']) {
        $_SESSION['admin_lockout_until'] = time() + $config['lockout_duration'];
        
        logAccess('LOCKED', $_SERVER['REMOTE_ADDR']);
        
        echo json_encode([
            'success' => false,
            'locked' => true,
            'message' => 'Konto zablokowane na ' . ($config['lockout_duration'] / 60) . ' minut',
            'lockout_time' => $config['lockout_duration']
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Nieprawidłowe hasło',
            'attempts_left' => $attemptsLeft
        ]);
    }
}

/**
 * Sprawdź status blokady
 */
function getLockoutStatus() {
    global $config;
    
    if (isset($_SESSION['admin_lockout_until']) && $_SESSION['admin_lockout_until'] > time()) {
        $timeRemaining = $_SESSION['admin_lockout_until'] - time();
        return [
            'locked' => true,
            'lockout_time' => $timeRemaining
        ];
    }
    
    // Blokada wygasła - wyczyść
    if (isset($_SESSION['admin_lockout_until'])) {
        unset($_SESSION['admin_lockout_until']);
        unset($_SESSION['admin_failed_attempts']);
    }
    
    return ['locked' => false];
}

/**
 * Loguj próby dostępu
 */
function logAccess($status, $ip, $password = '') {
    global $config, $logFile;
    
    if (!$config['log_access']) return;
    
    $timestamp = date('Y-m-d H:i:s');
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
    
    // Maskuj hasło
    $passwordMasked = $password ? substr($password, 0, 3) . '***' : '';
    
    $logEntry = sprintf(
        "[%s] %s | IP: %s | User-Agent: %s | Password: %s\n",
        $timestamp,
        $status,
        $ip,
        $userAgent,
        $passwordMasked
    );
    
    @file_put_contents($logFile, $logEntry, FILE_APPEND);
}
