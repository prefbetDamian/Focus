<?php
$config = require __DIR__.'/../config.php';
session_start();
if (!isset($_SESSION['manager'])) {
    http_response_code(403);
    exit;
}

$pdo = new PDO(
    "mysql:host={$config['db_host']};port={$config['db_port']};dbname={$config['db_name']};charset=utf8mb4",
    $config['db_user'],
    $config['db_pass'],
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

/* DANE */
$stmt = $pdo->query("
    SELECT
        f.created_at,
        f.machine_name,
        f.meter_mh,
        f.liters,
        f.delta_mh,
        f.avg_l_per_mh,
        f.anomaly_score,
        CONCAT(e1.first_name,' ',e1.last_name) supplier,
        CONCAT(e2.first_name,' ',e2.last_name) receiver
    FROM fuel_logs f
    LEFT JOIN employees e1 ON e1.id=f.supplier_id
    LEFT JOIN employees e2 ON e2.id=f.receiver_id
    ORDER BY f.created_at DESC
");


$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
