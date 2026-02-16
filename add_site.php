<?php
$config = require __DIR__.'/config.php';
ini_set('session.cookie_secure', 0);
ini_set('session.cookie_samesite', 'Lax');

session_start();
if (!isset($_SESSION["manager"])) exit;

header("Content-Type: application/json");

$pdo = new PDO(
    "mysql:host={$config['db_host']};dbname={$config['db_name']};charset=utf8mb4",
    $config['db_user'],
    $config['db_pass'],
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);


$data = json_decode(file_get_contents("php://input"), true);
$name = trim($data["name"] ?? "");

if ($name === "") {
    echo json_encode(["success"=>false]);
    exit;
}

$stmt = $pdo->prepare("INSERT IGNORE INTO sites (name) VALUES (?)");
$stmt->execute([$name]);

echo json_encode(["success"=>true]);
