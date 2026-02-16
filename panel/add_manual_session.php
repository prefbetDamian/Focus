<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json; charset=utf-8');
session_start();

/* ===== AUTORYZACJA ===== */
if (!isset($_SESSION['manager'], $_SESSION['role_level'])) {
    http_response_code(403);
    echo json_encode(['message' => 'Brak autoryzacji']);
    exit;
}

$role = (int)$_SESSION['role_level'];
if (!in_array($role, [2, 9], true)) {
    http_response_code(403);
    echo json_encode(['message' => 'Brak uprawnień']);
    exit;
}

/* ===== DANE ===== */
$data = json_decode(file_get_contents("php://input"), true);
if (!is_array($data)) {
    http_response_code(400);
    echo json_encode(['message' => 'Nieprawidłowe dane']);
    exit;
}

$employee_id = (int)($data['employee_id'] ?? 0);
$site_name   = trim($data['site_name'] ?? '');
$date        = $data['date'] ?? '';
$machine_id  = $data['machine_id'] ? (int)$data['machine_id'] : null;
$comment     = trim($data['comment'] ?? '');

if (!$employee_id || !$site_name || !$date || strlen($comment) < 3) {
    http_response_code(400);
    echo json_encode(['message' => 'Uzupełnij wszystkie pola']);
    exit;
}

/* ===== GODZINY NA SZTYWNO ===== */
$start_time = $date . ' 08:00:00';
$end_time   = $date . ' 16:00:00';
$duration   = 8 * 3600;

/* ===== DB ===== */
try {
    $config = require __DIR__.'/../config.php';

        $pdo = new PDO(
		"mysql:host={$config['db_host']};port={$config['db_port']};dbname={$config['db_name']};charset=utf8mb4",
        $config['db_user'],
        $config['db_pass'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    /* operator → sprawdź maszynę */
    $stmt = $pdo->prepare("
    SELECT first_name, last_name, is_operator
    FROM employees
    WHERE id = ?
");
$stmt->execute([$employee_id]);
$emp = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$emp) {
    throw new Exception("Nie znaleziono pracownika");
}

$firstName = $emp['first_name'];
$lastName  = $emp['last_name'];

if ($emp['is_operator'] == 1 && !$machine_id) {
    throw new Exception("Operator musi mieć maszynę");
}


    /* INSERT */
    $stmt = $pdo->prepare("
    INSERT INTO work_sessions (
        employee_id,
        first_name,
        last_name,
        site_name,
        start_time,
        end_time,
        duration_seconds,
        machine_id,
        ip,
        device_id,
        manager_id,
        manager_comment
    ) VALUES (
        ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?
    )
");

$stmt->execute([
    $employee_id,
    $emp['first_name'],
    $emp['last_name'],
    $site_name,
    $start_time,
    $end_time,
    $duration,
    $machine_id,
    'MANUAL',
    'MANUAL',
    $_SESSION['manager'],
    $comment
]);



    echo json_encode(['message' => '✔ Sesja dodana ręcznie']);
    exit;

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'message' => 'Błąd serwera',
        'error' => $e->getMessage() // usuń po testach
    ]);
    exit;
}
