<?php
ini_set('session.cookie_secure', 0);
ini_set('session.cookie_samesite', 'Lax');

session_start();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['manager'])) {
    echo json_encode(['success' => false, 'error' => 'Brak autoryzacji']);
    exit;
}

$config = require __DIR__ . '/../config.php';

$pdo = new PDO(
    "mysql:host={$config['db_host']};port={$config['db_port']};dbname={$config['db_name']};charset=utf8mb4",
    $config['db_user'],
    $config['db_pass'],
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

$data = json_decode(file_get_contents('php://input'), true) ?? [];
$siteId = (int)($data['site_id'] ?? 0);
$managerId = (int)($data['manager_id'] ?? 0);

if ($siteId <= 0 || $managerId <= 0) {
    echo json_encode(['success' => false, 'error' => 'Nieprawidłowe dane wejściowe']);
    exit;
}

$stmt = $pdo->prepare('DELETE FROM site_managers WHERE site_id = ? AND manager_id = ?');
$stmt->execute([$siteId, $managerId]);

echo json_encode(['success' => true]);
