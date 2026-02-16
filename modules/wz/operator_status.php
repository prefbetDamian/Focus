<?php
/**
 * Status dokumentów WZ dla zalogowanego operatora
 * Zwraca liczbę dokumentów w statusie waiting_operator
 */

require_once __DIR__ . '/../../core/session.php';
require_once __DIR__ . '/../../core/auth.php';

header('Content-Type: application/json; charset=utf-8');

try {
    $user = requireUser();
    
    $pdo = require __DIR__ . '/../../core/db.php';

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM wz_scans WHERE operator_id = ? AND status = 'waiting_operator'");
    $stmt->execute([$user['id']]);
    $waiting = (int)$stmt->fetchColumn();

    echo json_encode([
        'success' => true,
        'waiting' => $waiting,
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'waiting' => 0,
        'error'   => 'Server error',
    ]);
}
