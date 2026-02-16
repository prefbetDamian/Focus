<?php
$config = require __DIR__.'/config.php';
ini_set('session.cookie_secure', 0);
ini_set('session.cookie_samesite', 'Lax');

session_start();

if (!isset($_SESSION["manager"])) {
    http_response_code(403);
    exit;
}

header("Content-Type: application/json");

try {
        $pdo = new PDO(
		"mysql:host={$config['db_host']};port={$config['db_port']};dbname={$config['db_name']};charset=utf8mb4",
        $config['db_user'],
        $config['db_pass'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    $stmt = $pdo->query("
    SELECT
        e.id,
        e.last_name,
        e.first_name,
        e.is_operator,
        e.hour_rate,
        COALESCE(e.vacation_days, 0) AS vacation_days,
        e.pin_token,
        e.device_id,
        e.ip_address,

        la.blocked_until,

        ws.id AS work_session_id,
        ws.site_name AS current_job,
        ws.start_time,
        ws.machine_id,
        ws.manager_comment,

        m.machine_name        AS machine_name,
        m.registry_number     AS machine_registry_number

    FROM employees e

    LEFT JOIN login_attempts la
        ON la.employee_id = e.id
       AND la.context = 'rcp'

    LEFT JOIN work_sessions ws
        ON ws.employee_id = e.id
       AND ws.end_time IS NULL

    LEFT JOIN machines m
        ON m.id = ws.machine_id

    ORDER BY
        (ws.start_time IS NULL),
        e.last_name,
        e.first_name
    ");

    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    
} catch (Exception $e) {
    http_response_code(500);
    error_log("get_employees.php error: " . $e->getMessage());
    echo json_encode(['error' => $e->getMessage()]);
}
