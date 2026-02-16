<?php
session_start();

// Sprawdzenie uprawnień managera
if (!isset($_SESSION['manager'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Brak autoryzacji']);
    exit;
}

$config = require __DIR__.'/../config.php';
header("Content-Type: application/json");
error_reporting(E_ALL);

$pdo = new PDO(
    "mysql:host={$config['db_host']};port={$config['db_port']};dbname={$config['db_name']};charset=utf8mb4",
    $config['db_user'],
    $config['db_pass'],
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

$month = $_GET['month'] ?? '';

$sql = "
SELECT
    ws_grouped.id,
    ws_grouped.employee_id,
    ws_grouped.first_name,
    ws_grouped.last_name,
    ws_grouped.site_name,
    ws_grouped.`from`,
    ws_grouped.`to`,
    ar.id AS absence_request_id
FROM (
    SELECT
        ws.absence_group_id AS id,
        ws.employee_id,
        ws.first_name,
        ws.last_name,
        ws.site_name,
        DATE(MIN(ws.start_time)) AS `from`,
        DATE(MAX(ws.end_time))   AS `to`
    FROM work_sessions ws
    WHERE
        ws.site_name IN ('URLOP','L4')
        AND ws.absence_group_id IS NOT NULL
    GROUP BY
        ws.absence_group_id,
        ws.employee_id,
        ws.first_name,
        ws.last_name,
        ws.site_name
) AS ws_grouped
LEFT JOIN absence_requests ar ON (
    ar.employee_id = ws_grouped.employee_id 
    AND ar.type = ws_grouped.site_name 
    AND ar.start_date = ws_grouped.`from`
    AND ar.end_date = ws_grouped.`to`
    AND ar.status = 'approved'
)
" . ($month ? "WHERE (ws_grouped.`from` <= :lastDay2 AND ws_grouped.`to` >= :firstDay2)" : "") . "
ORDER BY
    ws_grouped.`from` DESC,
    ws_grouped.last_name ASC
";

$stmt = $pdo->prepare($sql);

if ($month) {
    $firstDay = $month . '-01';
    $lastDay = date('Y-m-t', strtotime($firstDay));
    $stmt->bindValue(':firstDay2', $firstDay);
    $stmt->bindValue(':lastDay2', $lastDay);
}

$stmt->execute();
echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
