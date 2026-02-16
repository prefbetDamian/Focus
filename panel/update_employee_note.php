<?php
/**
 * Aktualizuj notatkę pracownika
 */

ini_set('session.cookie_secure', 0);
ini_set('session.cookie_samesite', 'Lax');
session_start();

if (!isset($_SESSION['manager'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Brak dostępu']);
    exit;
}

header('Content-Type: application/json');

$config = require __DIR__.'/../config.php';

$data = json_decode(file_get_contents('php://input'), true);
$employeeId = (int)($data['employee_id'] ?? 0);
$note = trim($data['note'] ?? '');

if (!$employeeId) {
    echo json_encode(['success' => false, 'message' => 'Brak ID pracownika']);
    exit;
}

try {
        $pdo = new PDO(
		"mysql:host={$config['db_host']};port={$config['db_port']};dbname={$config['db_name']};charset=utf8mb4",
        $config['db_user'],
        $config['db_pass'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    
    $stmt = $pdo->prepare("UPDATE employees SET note = ? WHERE id = ?");
    $stmt->execute([$note, $employeeId]);
    
    echo json_encode(['success' => true, 'message' => 'Komentarz zapisany']);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
