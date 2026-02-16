<?php
/**
 * Sprawdzenie statusu zalogowanego pracownika
 * Zwraca informacje o użytkowniku i aktywnej sesji pracy
 */

require_once __DIR__.'/../../core/session.php';
require_once __DIR__.'/../../core/functions.php';

header('Content-Type: application/json; charset=utf-8');

// Sprawdź typ użytkownika (pracownik lub kierownik)
$isManager = isset($_SESSION['manager']) || isset($_SESSION['manager_id']);
$isEmployee = isset($_SESSION['user_id']);

// ZABEZPIECZENIE: Jeśli obie sesje istnieją, to błąd - wyloguj
if ($isManager && $isEmployee) {
    session_destroy();
    echo json_encode([
        'logged_in' => false,
        'error' => 'Konflikt sesji - wylogowano'
    ]);
    exit;
}

if (!$isManager && !$isEmployee) {
    echo json_encode([
        'logged_in' => false
    ]);
    exit;
}

try {
    $pdo = require __DIR__.'/../../core/db.php';
    
    // KIEROWNIK (PRIORYTET - sprawdź najpierw)
    if ($isManager && !$isEmployee) {
        echo json_encode([
            'logged_in' => true,
            'is_manager' => true,
            'manager_id' => $_SESSION['manager_id'] ?? null,
            'manager_name' => $_SESSION['manager'],
            'role_level' => (int)($_SESSION['role_level'] ?? 1)
        ]);
        exit;
    }
    
    // PRACOWNIK (tylko jeśli NIE jest kierownikiem)
    if ($isEmployee && !$isManager) {
        $userId = $_SESSION['user_id'];
    
        // Pobierz dane pracownika
        $stmt = $pdo->prepare("
        SELECT id, first_name, last_name, is_operator, manager_id
        FROM employees
        WHERE id = ?
    ");
            // Pobierz dane pracownika (wraz z przypisanym kierownikiem)
            $stmt = $pdo->prepare("
                SELECT id, first_name, last_name, is_operator, manager_id
                FROM employees
                WHERE id = ?
            ");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();
        
        if (!$user) {
            echo json_encode(['logged_in' => false]);
            exit;
        }
        
        // Sprawdź aktywną sesję pracy
        $stmt = $pdo->prepare("
                        SELECT 
                                ws.id,
                                ws.site_name,
                                ws.start_time,
                                ws.machine_id,
                                ws.manager_comment,
                                m.machine_name,
                                m.registry_number
            FROM work_sessions ws
            LEFT JOIN machines m ON m.id = ws.machine_id
            WHERE ws.employee_id = ?
              AND ws.end_time IS NULL
            LIMIT 1
        ");
    $stmt->execute([$userId]);
    $activeWork = $stmt->fetch();
    
        echo json_encode([
            'logged_in'    => true,
            'is_manager'   => false,
            'user_id'      => $user['id'],
            'first_name'   => $user['first_name'],
            'last_name'    => $user['last_name'],
            'is_operator'  => (int)$user['is_operator'],
            'manager_id'   => isset($user['manager_id']) ? (int)$user['manager_id'] : null,
            'active_work'  => $activeWork ?: null
        ]);
    exit;
    } // Zamknięcie if ($isEmployee && !$isManager)
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'logged_in' => false,
        'error' => 'Błąd serwera'
    ]);
}
