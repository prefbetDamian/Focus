<?php
ini_set('session.cookie_secure', 0);
ini_set('session.cookie_samesite', 'Lax');

session_start();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['manager']) && !isset($_SESSION['manager_id'])) {
    echo json_encode(['success' => false, 'message' => 'Brak autoryzacji']);
    exit;
}

$config = require __DIR__ . '/../config.php';

try {
        $pdo = new PDO(
		"mysql:host={$config['db_host']};port={$config['db_port']};dbname={$config['db_name']};charset=utf8mb4",
        $config['db_user'],
        $config['db_pass'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Błąd połączenia z bazą']);
    exit;
}

// Ustal role_level i ID kierownika
$managerArr = isset($_SESSION['manager']) && is_array($_SESSION['manager']) ? $_SESSION['manager'] : null;
$roleLevel = $managerArr['role_level'] ?? ((int)($_SESSION['role_level'] ?? 0));
$managerId = $managerArr['id'] ?? ($_SESSION['manager_id'] ?? null);

if ($roleLevel < 2 || !$managerId) {
    echo json_encode(['success' => false, 'message' => 'Brak uprawnień']);
    exit;
}

// Data filtrowania – domyślnie dzisiaj (wg czasu serwera)
$date = isset($_GET['date']) && $_GET['date'] !== ''
    ? $_GET['date']
    : date('Y-m-d');

// Walidacja prostego formatu YYYY-MM-DD
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    echo json_encode(['success' => false, 'message' => 'Nieprawidłowy format daty']);
    exit;
}

try {
    if ((int)$roleLevel === 9) {
        // Admin: widzi wszystkie punkty
        $stmt = $pdo->prepare("\n            SELECT\n                ws.id,\n                ws.employee_id,\n                ws.first_name,\n                ws.last_name,\n                ws.site_name,\n                ws.site_id,\n                ws.start_time,\n                ws.lat,\n                ws.lng,\n                ws.location_source\n            FROM work_sessions ws\n            WHERE ws.lat IS NOT NULL\n              AND ws.lng IS NOT NULL\n              AND DATE(ws.start_time) = :date\n            ORDER BY ws.start_time ASC\n        ");
        $stmt->execute(['date' => $date]);
    } else {
        // Kierownik: tylko budowy przypisane do niego
        $stmt = $pdo->prepare("\n            SELECT\n                ws.id,\n                ws.employee_id,\n                ws.first_name,\n                ws.last_name,\n                ws.site_name,\n                ws.site_id,\n                ws.start_time,\n                ws.lat,\n                ws.lng,\n                ws.location_source\n            FROM work_sessions ws\n            JOIN sites s ON s.id = ws.site_id\n            JOIN site_managers sm ON sm.site_id = s.id\n            WHERE sm.manager_id = :managerId\n              AND ws.lat IS NOT NULL\n              AND ws.lng IS NOT NULL\n              AND DATE(ws.start_time) = :date\n            ORDER BY ws.start_time ASC\n        ");
        $stmt->execute([
            'managerId' => (int)$managerId,
            'date'      => $date,
        ]);
    }

    $points = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'date'    => $date,
        'points'  => $points,
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Błąd bazy danych',
    ]);
}
