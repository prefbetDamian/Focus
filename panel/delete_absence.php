<?php
session_start();
header("Content-Type: application/json");

if (!isset($_SESSION['manager'])) {
    echo json_encode(["success"=>false,"message"=>"Brak dostępu"]);
    exit;
}

$data = json_decode(file_get_contents("php://input"), true);
$groupId = (int)($data['id'] ?? 0);

if ($groupId <= 0) {
    echo json_encode(["success"=>false,"message"=>"Nieprawidłowe ID"]);
    exit;
}

$config = require __DIR__.'/../config.php';

$pdo = new PDO(
    "mysql:host={$config['db_host']};port={$config['db_port']};dbname={$config['db_name']};charset=utf8mb4",
    $config['db_user'],
    $config['db_pass'],
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

/*
 Usuwamy CAŁY URLOP / L4 (wszystkie dni w grupie)
*/
$stmt = $pdo->prepare("
    DELETE FROM work_sessions
    WHERE absence_group_id = ?
      AND TRIM(UPPER(site_name)) IN ('URLOP','L4')
");

$stmt->execute([$groupId]);

if ($stmt->rowCount() === 0) {
    echo json_encode([
        "success"=>false,
        "message"=>"Nie znaleziono wpisów w tej grupie"
    ]);
    exit;
}

echo json_encode([
    "success"=>true,
    "deleted"=>$stmt->rowCount()
]);
exit;
