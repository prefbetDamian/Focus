<?php
ini_set('session.cookie_secure', 0);
ini_set('session.cookie_samesite', 'Lax');
session_start();

header("Content-Type: application/json");

$config = require __DIR__.'/../config.php';

if (!isset($_SESSION["manager"])) {
    echo json_encode(["success"=>false,"message"=>"Brak dostępu"]);
    exit;
}

$pdo = new PDO(
    "mysql:host={$config['db_host']};port={$config['db_port']};dbname={$config['db_name']};charset=utf8mb4",
    $config['db_user'],
    $config['db_pass'],
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

$data = json_decode(file_get_contents("php://input"), true);
$employeeId = (int)($data['employee_id'] ?? $data['id'] ?? 0);

if (!$employeeId) {
    echo json_encode(["success"=>false,"message"=>"Brak ID pracownika"]);
    exit;
}

/*
 * ODBLOKOWANIE = usunięcie wpisu z login_attempts
 * (źródło prawdy o blokadach)
 */
$stmt = $pdo->prepare("
    DELETE FROM login_attempts
    WHERE employee_id = ?
      AND context = 'rcp'
");
$stmt->execute([$employeeId]);

echo json_encode(["success"=>true]);
exit;
