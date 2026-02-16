<?php
ini_set('session.cookie_secure', '1');
ini_set('session.cookie_samesite', 'None');

ini_set('display_errors', 0);
error_reporting(E_ALL);
header("Content-Type: application/json");
session_start();

try {
    if (!isset($_SESSION["manager"])) {
        echo json_encode([
            "success" => false,
            "message" => "Brak uprawnień"
        ]);
        exit;
    }

    $raw = file_get_contents("php://input");
    $data = json_decode($raw, true);

    if (!is_array($data) || !isset($data["id"])) {
        echo json_encode([
            "success" => false,
            "message" => "Brak ID"
        ]);
        exit;
    }

    $machineId = (int)$data["id"];
    if ($machineId <= 0) {
        echo json_encode([
            "success" => false,
            "message" => "Nieprawidłowe ID maszyny"
        ]);
        exit;
    }

    $config = require __DIR__.'/../config.php';

        $pdo = new PDO(
		"mysql:host={$config['db_host']};port={$config['db_port']};dbname={$config['db_name']};charset=utf8mb4",
        $config['db_user'],
        $config['db_pass'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    /* 🔒 BLOKADA – aktywna sesja maszyny */
    $stmt = $pdo->prepare("
        SELECT 1
        FROM work_sessions
        WHERE machine_id = ?
          AND end_time IS NULL
        LIMIT 1
    ");
    $stmt->execute([$machineId]);

    if ($stmt->fetch()) {
        echo json_encode([
            "success" => false,
            "message" => "Nie można usunąć maszyny – ma aktywną sesję"
        ]);
        exit;
    }

    /* 🗑️ USUWANIE MASZYNY */
    $stmt = $pdo->prepare("DELETE FROM machines WHERE id = ?");
    $stmt->execute([$machineId]);

    echo json_encode(["success" => true]);

} catch (Throwable $e) {
    echo json_encode([
        "success" => false,
        "message" => "Błąd serwera"
    ]);
}
