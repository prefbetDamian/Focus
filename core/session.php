<?php
/**
 * CORE: Zarządzanie sesją
 * Uruchamia sesję z bezpiecznymi ustawieniami
 */

if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.use_strict_mode', '1');
    ini_set('session.cookie_httponly', '1');
    
    // Secure cookie tylko gdy HTTPS
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] === '443');
    
    ini_set('session.cookie_secure', $isHttps ? '1' : '0');
    ini_set('session.cookie_samesite', $isHttps ? 'None' : 'Lax');
    
    session_start();
}

/**
 * Ustawia trwałą sesję (30 dni) dla wybranych użytkowników
 * Używane dla role_level=2 (kierownik) i is_operator=3 (ładowarka)
 */
function setPersistentSession(): void {
    $sessionName = session_name();
    $sessionId = session_id();
    
    if (empty($sessionName) || empty($sessionId)) {
        return;
    }
    
    // 30 dni w sekundach
    $lifetime = 30 * 24 * 60 * 60;
    
    // Ustaw długi czas życia dla ciasteczka sesji
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
