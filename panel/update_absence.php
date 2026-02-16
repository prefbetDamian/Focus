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

$groupId = (int)($data['id'] ?? 0);
$type    = $data['type'] ?? '';
$from    = $data['from'] ?? '';
$to      = $data['to'] ?? '';

if (!$groupId || !$type || !$from || !$to) {
    echo json_encode(["success"=>false,"message"=>"Brak danych"]);
    exit;
}

if (!in_array($type, ['URLOP','L4'], true)) {
    echo json_encode(["success"=>false,"message"=>"Nieprawidłowy typ"]);
    exit;
}

try {
    $pdo->beginTransaction();

    /* pobierz dane pracownika */
    $stmt = $pdo->prepare("
        SELECT employee_id, first_name, last_name
        FROM work_sessions
        WHERE absence_group_id = ?
        LIMIT 1
    ");
    $stmt->execute([$groupId]);
    $base = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$base) {
        throw new Exception("Nie znaleziono urlopu");
    }

    $employeeId = (int)$base['employee_id'];
    $firstName  = $base['first_name'];
    $lastName   = $base['last_name'];

    /* usuń stare dni */
    $pdo->prepare("
        DELETE FROM work_sessions
        WHERE absence_group_id = ?
    ")->execute([$groupId]);

    /* przygotuj zakres dat */
    $startDate = new DateTime($from);
    $endDate   = new DateTime($to);
    $endDate->modify('+1 day');

    $period = new DatePeriod(
        $startDate,
        new DateInterval('P1D'),
        $endDate
    );

    /* insert nowych dni */
    $ins = $pdo->prepare("
        INSERT INTO work_sessions
        (employee_id, first_name, last_name, site_name,
         start_time, end_time, duration_seconds, absence_group_id)
        VALUES (?,?,?,?,?,?,?,?)
    ");

    foreach ($period as $day) {
        $date = $day->format('Y-m-d');

        $ins->execute([
            $employeeId,
            $firstName,
            $lastName,
            $type,
            $date . ' 08:00:00',
            $date . ' 16:00:00',
            8 * 3600,
            $groupId
        ]);
    }

    /* Zaktualizuj także absence_requests jeśli istnieje powiązany wniosek */
    // Najpierw sprawdź czy istniał wniosek dla starych dat (przed edycją)
    $stmtCheckRequest = $pdo->prepare("
        SELECT ar.id, ar.start_date, ar.end_date, ar.type
        FROM absence_requests ar
        WHERE ar.employee_id = ? 
        AND ar.status = 'approved'
        ORDER BY ar.reviewed_at DESC
        LIMIT 1
    ");
    $stmtCheckRequest->execute([$employeeId]);
    $existingRequest = $stmtCheckRequest->fetch(PDO::FETCH_ASSOC);

    if ($existingRequest) {
        // Zaktualizuj istniejący wniosek z nowymi datami
        $stmtUpdateRequest = $pdo->prepare("
            UPDATE absence_requests 
            SET start_date = ?, 
                end_date = ?, 
                type = ?
            WHERE id = ?
        ");
        $stmtUpdateRequest->execute([$from, $to, $type, $existingRequest['id']]);
    }

    $pdo->commit();

    echo json_encode([
        "success" => true,
        "message" => "✔ Urlop / L4 został zaktualizowany"
    ]);

} catch (Throwable $e) {
    $pdo->rollBack();
    echo json_encode([
        "success" => false,
        "message" => "Błąd: " . $e->getMessage()
    ]);
}
