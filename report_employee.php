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

$data = json_decode(file_get_contents("php://input"), true);

$stmt = $pdo->prepare("
    SELECT
        SEC_TO_TIME(SUM(duration_seconds)) AS total_time
        FROM work_sessions
        WHERE first_name = ?
            AND last_name = ?
            AND MONTH(start_time) = ?
            AND YEAR(start_time) = ?
            AND duration_seconds IS NOT NULL
            AND status IN ('OK','MANUAL')
");


$stmt->execute([
    $data["firstName"],
    $data["lastName"],
    $data["month"],
    $data["year"]
]);

echo json_encode($stmt->fetch());
