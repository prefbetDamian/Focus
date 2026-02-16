<?php
/**
 * Sprawdzenie czy zalogowany pracownik jest operatorem
 */

require_once __DIR__.'/../../core/session.php';
require_once __DIR__.'/../../core/functions.php';

header('Content-Type: application/json; charset=utf-8');

// Sprawdź czy użytkownik jest zalogowany jako pracownik
if (!isset($_SESSION['user_id'])) {
    echo json_encode([
        'authorized' => false,
        'message' => 'Nie jesteś zalogowany'
    ]);
    exit;
}

try {
    $pdo = require __DIR__.'/../../core/db.php';
    
    $userId = $_SESSION['user_id'];
    
    // Pobierz dane pracownika
    $stmt = $pdo->prepare("
        SELECT id, first_name, last_name, is_operator
        FROM employees
        WHERE id = ?
    ");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    
    if (!$user) {
        echo json_encode([
            'authorized' => false,
            'message' => 'Nie znaleziono użytkownika'
        ]);
        exit;
    }

    // Sprawdź czy ma uprawnienia operatora do tankowania (1, 2 lub 3)
    $opRole = (int)$user['is_operator'];
    if ($opRole !== 1 && $opRole !== 2 && $opRole !== 3) {
        echo json_encode([
            'authorized' => false,
            'message' => 'Nie masz uprawnień operatora'
        ]);
        exit;
    }

    // Spróbuj pobrać aktualnie aktywną maszynę z sesji pracy
    $stmt = $pdo->prepare("\n        SELECT\n            ws.machine_id,\n            m.machine_name,\n            m.registry_number,\n            m.owner\n        FROM work_sessions ws\n        LEFT JOIN machines m ON m.id = ws.machine_id\n        WHERE ws.employee_id = ?\n          AND ws.end_time IS NULL\n          AND ws.machine_id IS NOT NULL\n        LIMIT 1\n    ");
    $stmt->execute([$userId]);
    $active = $stmt->fetch();

    echo json_encode([
        'authorized' => true,
        'user_id' => $user['id'],
        'first_name' => $user['first_name'],
        'last_name' => $user['last_name'],
        'active_machine_id' => $active['machine_id'] ?? null,
        'active_machine_name' => $active['machine_name'] ?? null,
        'active_machine_registry' => $active['registry_number'] ?? null,
        'active_machine_owner' => $active['owner'] ?? null
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'authorized' => false,
        'message' => 'Błąd serwera'
    ]);
}
