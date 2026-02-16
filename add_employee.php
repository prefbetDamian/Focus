<?php
// ABSOLUTNIE NIC przed <?php

ini_set('display_errors', 1);
error_reporting(E_ALL);
header("Content-Type: application/json");
session_start();

// Sprawdzenie uprawnień managera
if (!isset($_SESSION['manager'])) {
    echo json_encode([
        "success" => false,
        "message" => "Brak autoryzacji"
    ]);
    exit;
}

$config = require __DIR__.'/config.php';

try {
    $pdo = new PDO(
        "mysql:host={$config['db_host']};port={$config['db_port']};dbname={$config['db_name']};charset=utf8mb4",
        $config['db_user'],
        $config['db_pass'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    $raw = file_get_contents("php://input");
    $data = json_decode($raw, true);

    if (!is_array($data)) {
        throw new Exception("Brak danych JSON");
    }

    $firstName    = trim($data["firstName"] ?? "");
    $lastName     = trim($data["lastName"] ?? "");
    $isOperator   = isset($data["operator"]) ? (int)$data["operator"] : 0;
    $hourrate     = trim($data["hour_rate"] ?? "");
    $vacationDays = isset($data["vacation_days"]) ? (int)$data["vacation_days"] : 0;

    if ($firstName === "" || $lastName === "" || $hourrate === "") {
        echo json_encode([
            "success" => false,
            "message" => "Brak danych"
        ]);
        exit;
    }

    // === GENERUJEMY TOKEN (zamiast PIN) ===
    $token = bin2hex(random_bytes(5)); // 10 znaków

    // zapis do bazy
    $stmt = $pdo->prepare("
        INSERT INTO employees (first_name, last_name, pin_token, is_operator, hour_rate, vacation_days)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([$firstName, $lastName, $token, $isOperator, $hourrate, $vacationDays]);

    // zwracamy TYLKO token
    echo json_encode([
        "success" => true,
        "message" => "Pracownik dodany",
        "token" => $token
    ]);

} catch (Throwable $e) {
    // Loguj szczegóły błędu dla debugowania
    error_log("Error in add_employee.php: " . $e->getMessage());
    echo json_encode([
        "success" => false,
        "message" => "Błąd serwera: " . $e->getMessage()
    ]);
}
