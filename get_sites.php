<?php
session_start();

// Sprawdzenie uprawnień managera
if (!isset($_SESSION['manager'])) {
    http_response_code(403);
    header("Content-Type: application/json");
    echo json_encode(['error' => 'Brak autoryzacji']);
    exit;
}

$config = require __DIR__.'/config.php';
$pdo = new PDO(
    "mysql:host={$config['db_host']};port={$config['db_port']};dbname={$config['db_name']};charset=utf8mb4",
    $config['db_user'],
    $config['db_pass'],
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

// status=active (domyślnie) lub status=archived
$status = $_GET['status'] ?? 'active';
$activeValue = $status === 'archived' ? 0 : 1;

$stmt = $pdo->prepare("
    SELECT 
        s.id,
        s.name,
           GROUP_CONCAT(DISTINCT CONCAT(m.first_name, ' ', m.last_name) ORDER BY m.first_name, m.last_name SEPARATOR ', ') AS managers,
           GROUP_CONCAT(DISTINCT m.id ORDER BY m.first_name, m.last_name SEPARATOR ',') AS manager_ids
    FROM sites s
    LEFT JOIN site_managers sm ON sm.site_id = s.id
    LEFT JOIN managers m ON m.id = sm.manager_id
    WHERE s.active = ?
    GROUP BY s.id, s.name
    ORDER BY s.name
");
$stmt->execute([$activeValue]);

echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));