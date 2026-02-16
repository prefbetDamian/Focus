<?php
ini_set('session.cookie_secure', '1');
ini_set('session.cookie_samesite', 'None');

session_start();
header("Content-Type: application/json");

if (!isset($_SESSION['manager'])) {
    echo json_encode([
        "success" => false,
        "message" => "Brak autoryzacji"
    ]);
    exit;
}

$data = json_decode(file_get_contents("php://input"), true);

$machine_name    = trim($data['machine_name'] ?? '');
$short_name      = trim($data['short_name'] ?? '');
$owner           = $data['owner'] ?? '';
$registry_number = trim($data['registry_number'] ?? '');
$renter          = isset($data['renter']) ? trim((string)$data['renter']) : '';
$workshop_tag    = isset($data['workshop_tag']) ? trim((string)$data['workshop_tag']) : '';
$hour_rate       = isset($data['hour_rate']) ? (float)$data['hour_rate'] : 0.0;
$fuel_norm       = isset($data['fuel_norm_l_per_mh']) && $data['fuel_norm_l_per_mh'] !== null
    ? (float)$data['fuel_norm_l_per_mh']
    : null;

if (!$machine_name || !$owner || !$registry_number) {
    echo json_encode([
        "success" => false,
        "message" => "Brak wymaganych danych (nazwa, właściciel, numer)"
    ]);
    exit;
}

// Dozwoleni właściciele wg ENUM w tabeli machines
if (!in_array($owner, ['PREFBET','BG','MARBUD','PUH','DRWAL','MERITUM','ZB'], true)) {
    echo json_encode([
        "success" => false,
        "message" => "Nieprawidłowy właściciel"
    ]);
    exit;
}

if ($hour_rate < 0) {
    echo json_encode([
        "success" => false,
        "message" => "Nieprawidłowa stawka godzinowa"
    ]);
    exit;
}

if ($fuel_norm !== null && $fuel_norm < 0) {
    echo json_encode([
        "success" => false,
        "message" => "Nieprawidłowa norma spalania"
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

/* unikalny numer */
$stmt = $pdo->prepare("
    SELECT id FROM machines WHERE registry_number = ?
");
$stmt->execute([$registry_number]);

if ($stmt->fetch()) {
    echo json_encode([
        "success" => false,
        "message" => "Maszyna o tym numerze już istnieje"
    ]);
    exit;
}

$stmtInsert = $pdo->prepare("
    INSERT INTO machines (
        machine_name,
        short_name,
        owner,
        registry_number,
        hour_rate,
        renter,
        workshop_tag,
        fuel_norm_l_per_mh,
        workshop_status
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
");

$stmtInsert->execute([
    $machine_name,
    $short_name !== '' ? $short_name : null,
    $owner,
    $registry_number,
    $hour_rate,
    $renter !== '' ? $renter : null,
    $workshop_tag !== '' ? $workshop_tag : null,
    $fuel_norm,
    'free'
]);

echo json_encode([
	"success" => true,
	"message" => "Dodano maszynę"
]);
