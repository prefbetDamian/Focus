<?php
session_start();

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['manager'])) {
    echo json_encode(['success' => false, 'message' => 'Brak autoryzacji']);
    exit;
}

$config = require __DIR__ . '/config.php';

$pdo = new PDO(
    "mysql:host={$config['db_host']};port={$config['db_port']};dbname={$config['db_name']};charset=utf8mb4",
    $config['db_user'],
    $config['db_pass'],
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

// Pobieramy wszystkich managerów, którzy mają ustawione can_be_manager = TRUE
// Dzięki temu admin (role 9) może być też wybieralny jako kierownik
$stmt = $pdo->prepare("SELECT id, first_name, last_name, role_level FROM managers WHERE can_be_manager = TRUE ORDER BY first_name, last_name");
$stmt->execute();

// Zwracamy prostą listę obiektów [{id, first_name, last_name, role_level}, ...]
echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
