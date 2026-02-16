<?php
session_start();
header("Content-Type: application/json");
error_reporting(E_ALL);

// Sprawdzenie uprawnień managera
if (!isset($_SESSION['manager'])) {
    echo json_encode([
        "success" => false,
        "message" => "Brak autoryzacji"
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

$data = json_decode(file_get_contents("php://input"), true);

$employeeId = (int)($data['employee_id'] ?? 0);
$type       = trim($data['type'] ?? '');
$from       = $data['from'] ?? '';
$to         = $data['to'] ?? '';

if (!$employeeId || !$type || !$from || !$to) {
    echo json_encode(["success"=>false,"message"=>"Brak danych"]);
    exit;
}

if (!in_array($type, ['URLOP','L4'], true)) {
    echo json_encode(["success"=>false,"message"=>"Nieprawidłowy typ"]);
    exit;
}

/* ===== dane pracownika ===== */
$stmt = $pdo->prepare("
    SELECT first_name, last_name, COALESCE(vacation_days, 0) AS vacation_days
    FROM employees
    WHERE id = ?
");
$stmt->execute([$employeeId]);
$emp = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$emp) {
    echo json_encode(["success"=>false,"message"=>"Nie znaleziono pracownika"]);
    exit;
}

/* ===== NOWY absence_group_id ===== */
$groupId = (int)$pdo->query("
    SELECT IFNULL(MAX(absence_group_id),0) + 1
    FROM work_sessions
")->fetchColumn();

/* ===== zakres dat ===== */
$startDate = new DateTime($from);
$endDate   = new DateTime($to);
$endDate->modify('+1 day'); // inclusive

$interval = new DateInterval('P1D');
$period   = new DatePeriod($startDate, $interval, $endDate);

// Policzenie liczby dni (włącznie z datą końcową)
$days = 0;
foreach ($period as $_) {
    $days++;
}

// Jeśli to URLOP, odejmij dni z puli urlopowej (z zabezpieczeniem przed zejściem poniżej zera)
$remainingVacation = (int)$emp['vacation_days'];
if ($type === 'URLOP' && $days > 0) {
    $stmt = $pdo->prepare("UPDATE employees SET vacation_days = GREATEST(vacation_days - ?, 0) WHERE id = ?");
    $stmt->execute([$days, $employeeId]);

    // Pobierz zaktualizowaną liczbę dni urlopowych
    $stmt = $pdo->prepare("SELECT COALESCE(vacation_days, 0) FROM employees WHERE id = ?");
    $stmt->execute([$employeeId]);
    $remainingVacation = (int)$stmt->fetchColumn();
}

/* ===== INSERT ===== */
$stmt = $pdo->prepare("
    INSERT INTO work_sessions
    (
        employee_id,
        first_name,
        last_name,
        site_name,
        start_time,
        end_time,
        duration_seconds,
        machine_id,
        absence_group_id
    )
    VALUES (?,?,?,?,?,?,?,?,?)
");

foreach ($period as $day) {

    $start = $day->format("Y-m-d") . " 08:00:00";
    $end   = $day->format("Y-m-d") . " 16:00:00";

    $stmt->execute([
        $employeeId,
        $emp['first_name'],
        $emp['last_name'],
        $type,                 // URLOP / L4
        $start,
        $end,
        8 * 3600,
        null,
        $groupId
    ]);
}

$response = [
    "success" => true,
    "message" => "✔ Dodano $type od $from do $to"
];

// Do URLOP-u dokładamy w odpowiedzi aktualną liczbę dni urlopowych
if ($type === 'URLOP') {
    $response['vacation_days'] = $remainingVacation;
}

echo json_encode($response);
