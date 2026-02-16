<?php
ini_set('session.cookie_secure', 0);
ini_set('session.cookie_samesite', 'Lax');

session_start();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['manager']) && !isset($_SESSION['manager_id'])) {
    echo json_encode(['success' => false, 'message' => 'Brak autoryzacji']);
    exit;
}

$config = require __DIR__ . '/../config.php';

$pdo = new PDO(
    "mysql:host={$config['db_host']};port={$config['db_port']};dbname={$config['db_name']};charset=utf8mb4",
    $config['db_user'],
    $config['db_pass'],
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

$managerArr = isset($_SESSION['manager']) && is_array($_SESSION['manager']) ? $_SESSION['manager'] : null;
$roleLevel = $managerArr['role_level'] ?? ((int)($_SESSION['role_level'] ?? 0));

if ($roleLevel < 2) {
    echo json_encode(['success' => false, 'message' => 'Brak uprawnień']);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM wz_scans WHERE status = 'waiting_manager'");
    $stmt->execute();
    $count = (int)$stmt->fetchColumn();

    echo json_encode([
        'success' => true,
        'count'   => $count,
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Błąd bazy danych',
    ]);
}
