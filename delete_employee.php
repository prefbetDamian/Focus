<?php
$config = require __DIR__.'/config.php';
ini_set('session.cookie_secure', 0);
ini_set('session.cookie_samesite', 'Lax');

ini_set('display_errors', 0);
error_reporting(E_ALL);
header("Content-Type: application/json");
session_start();

try {
    if (!isset($_SESSION["manager"])) {
        echo json_encode(["success"=>false,"message"=>"Brak uprawnień"]);
        exit;
    }

    $raw = file_get_contents("php://input");
    $data = json_decode($raw, true);

    if (!is_array($data) || !isset($data["id"])) {
        echo json_encode(["success"=>false,"message"=>"Brak ID"]);
        exit;
    }

    $pdo = new PDO(
        "mysql:host={$config['db_host']};port={$config['db_port']};dbname={$config['db_name']};charset=utf8mb4",
    $config['db_user'],
    $config['db_pass'],
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

    $stmt = $pdo->prepare("DELETE FROM employees WHERE id = ?");
    $stmt->execute([$data["id"]]);

    echo json_encode(["success"=>true]);

} catch (Throwable $e) {
    echo json_encode(["success"=>false,"message"=>"Błąd serwera"]);
}
