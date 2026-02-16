<?php
/**
 * CORE: Autoryzacja i kontrola dostępu
 * Centralny system sprawdzania uprawnień
 */

require_once __DIR__.'/session.php';

/**
 * Wymaga zalogowanego pracownika
 * Używane w modułach dla pracowników (np. start pracy, WZ)
 */
function requireUser(): array {
    if (!isset($_SESSION['user_id'])) {
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode([
            'status' => 'error',
            'message' => 'Brak autoryzacji - wymagane logowanie'
        ]);
        exit;
    }
    
    return [
        'id' => $_SESSION['user_id'],
        'first_name' => $_SESSION['first_name'] ?? '',
        'last_name' => $_SESSION['last_name'] ?? '',
        'is_operator' => $_SESSION['is_operator'] ?? 0
    ];
}

/**
 * Wymaga zalogowanego menedżera
 * Używane w panelu administracyjnym
 * 
 * @param int $minRole Minimalny poziom uprawnień (1=podstawowy, 2=kierownik, 9=admin)
 */
function requireManager(int $minRole = 1): array {
    if (!isset($_SESSION['manager'])) {
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode([
            'status' => 'error',
            'message' => 'Brak autoryzacji - wymagane konto menedżera'
        ]);
        exit;
    }
    
    $userRole = (int)($_SESSION['role_level'] ?? 0);
    
    if ($userRole < $minRole) {
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode([
            'status' => 'error',
            'message' => 'Brak wystarczających uprawnień (wymagany poziom: '.$minRole.')'
        ]);
        exit;
    }
    
    return [
        'id' => $_SESSION['manager_id'] ?? 0,
        'name' => $_SESSION['manager'],
        'role_level' => $userRole
    ];
}

/**
 * Wymaga zalogowanego menedżera dla stron HTML (przekierowanie zamiast JSON)
 * Używaj na plikach typu dashboard.php, employees.php, pending_sessions.php itd.
 */
function requireManagerPage(int $minRole = 1): array {
    // Timeout sesji 30 minut dla panelu menedżera
    if (isset($_SESSION['login_time']) && (time() - (int)$_SESSION['login_time'] > 1800)) {
        logout();
        header('Location: ../index.html');
        exit;
    }

    if (!isset($_SESSION['manager'])) {
        header('Location: ../index.html');
        exit;
    }

    $userRole = (int)($_SESSION['role_level'] ?? 0);
    if ($userRole < $minRole) {
        header('Location: ../index.html');
        exit;
    }

    return [
        'id' => $_SESSION['manager_id'] ?? 0,
        'name' => $_SESSION['manager'],
        'role_level' => $userRole
    ];
}

/**
 * Sprawdza czy użytkownik jest zalogowany (bez wymuszania)
 */
function isLoggedIn(): bool {
    return isset($_SESSION['user_id']) || isset($_SESSION['manager']);
}

/**
 * Wylogowuje użytkownika
 */
function logout(): void {
    $_SESSION = [];
    
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'],
            $params['domain'],
            $params['secure'],
            $params['httponly']
        );
    }
    
    session_destroy();
}
