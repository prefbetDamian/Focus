<?php
session_start();
header("Content-Type: application/json");

// Sprawdzenie uprawnień managera
if (!isset($_SESSION['manager'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Brak autoryzacji']);
    exit;
}

$config = require __DIR__.'/../config.php';

try {

        $pdo = new PDO(
		"mysql:host={$config['db_host']};port={$config['db_port']};dbname={$config['db_name']};charset=utf8mb4",
        $config['db_user'],
        $config['db_pass'],
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );

    /*
      active = 1 → istnieje AKTYWNA sesja pracy z tą maszyną
      korzystamy WYŁĄCZNIE z work_sessions
    */
    $stmt = $pdo->query("
    SELECT
        m.id,
        m.machine_name,
        m.owner,
        m.registry_number,
        m.short_name,
        ws.start_time,
        m.hour_rate,
        m.renter,

        IF(ws.id IS NULL, 0, 1) AS active,

        e.first_name AS operator_first_name,
        e.last_name  AS operator_last_name

    FROM machines m

    LEFT JOIN work_sessions ws
        ON ws.machine_id = m.id
       AND ws.end_time IS NULL

    LEFT JOIN employees e
        ON e.id = ws.employee_id

    ORDER BY m.registry_number
");


    $rows = $stmt->fetchAll();

    /* ZAWSZE TABLICA */
    echo json_encode($rows);
    exit;

} catch (Throwable $e) {

    /* nawet przy błędzie → ZAWSZE tablica */
    http_response_code(500);
    echo json_encode([]);
    exit;
}
