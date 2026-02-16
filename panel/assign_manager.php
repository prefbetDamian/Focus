<?php
ini_set('session.cookie_secure', 0);
ini_set('session.cookie_samesite', 'Lax');

session_start();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['manager'])) {
    echo json_encode(['success' => false, 'error' => 'Brak autoryzacji']);
    exit;
}

$config = require __DIR__ . '/../config.php';

$pdo = new PDO(
    "mysql:host={$config['db_host']};port={$config['db_port']};dbname={$config['db_name']};charset=utf8mb4",
    $config['db_user'],
    $config['db_pass'],
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

$data = json_decode(file_get_contents('php://input'), true) ?? [];
$siteId = (int)($data['site_id'] ?? 0);
$managerId = (int)($data['manager_id'] ?? 0);

if ($siteId <= 0 || $managerId <= 0) {
    echo json_encode(['success' => false, 'error' => 'Nieprawidłowe dane wejściowe']);
    exit;
}

// Walidacja: czy manager istnieje i ma odpowiedni poziom roli
// Dozwolone: 2 - kierownik budowy, 9 - administrator
$stmt = $pdo->prepare('SELECT role_level FROM managers WHERE id = ?');
$stmt->execute([$managerId]);
$roleLevel = $stmt->fetchColumn();

if ($roleLevel === false) {
    echo json_encode(['success' => false, 'error' => 'Kierownik nie istnieje']);
    exit;
}

if (!in_array((int)$roleLevel, [2, 9], true)) {
    echo json_encode(['success' => false, 'error' => 'Wybrany użytkownik nie ma roli kierownika budowy (wymagane role_level 2 lub 9)']);
    exit;
}

// Upewnij się, że budowa istnieje
$stmt = $pdo->prepare('SELECT id FROM sites WHERE id = ?');
$stmt->execute([$siteId]);
if (!$stmt->fetchColumn()) {
    echo json_encode(['success' => false, 'error' => 'Budowa nie istnieje']);
    exit;
}

// Przypisanie kierownika do budowy (unikalna para site_id + manager_id)
$stmt = $pdo->prepare('
    INSERT IGNORE INTO site_managers (site_id, manager_id)
    VALUES (?, ?)
');
$stmt->execute([$siteId, $managerId]);

echo json_encode(['success' => true]);
