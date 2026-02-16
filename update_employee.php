<?php
$config = require __DIR__.'/config.php';
ini_set('session.cookie_secure', 0);
ini_set('session.cookie_samesite', 'Lax');

ini_set('display_errors', 0);
error_reporting(E_ALL);
header('Content-Type: application/json');
session_start();

try {
    if (!isset($_SESSION['manager'])) {
        echo json_encode(['success' => false, 'message' => 'Brak uprawnień']);
        exit;
    }

    // Tylko role 4 i 9 mogą edytować pełny wpis pracownika
    $role = (int)($_SESSION['role_level'] ?? 0);
    if (!in_array($role, [4, 9], true)) {
        echo json_encode(['success' => false, 'message' => 'Brak uprawnień do edycji']);
        exit;
    }

    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);

    if (!is_array($data)) {
        echo json_encode(['success' => false, 'message' => 'Nieprawidłowe dane']);
        exit;
    }

    $id             = isset($data['id']) ? (int)$data['id'] : 0;
    $resetDevice    = !empty($data['reset_device']);

    if ($id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Brak wymaganych danych']);
        exit;
    }

        $pdo = new PDO(
		"mysql:host={$config['db_host']};port={$config['db_port']};dbname={$config['db_name']};charset=utf8mb4",
        $config['db_user'],
        $config['db_pass'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    if ($resetDevice) {
        $stmt = $pdo->prepare('UPDATE employees SET device_id = NULL, ip_address = NULL WHERE id = ?');
        $stmt->execute([$id]);

        echo json_encode(['success' => true]);
        exit;
    }

    $first_name     = trim($data['first_name'] ?? '');
    $last_name      = trim($data['last_name'] ?? '');
    $is_operator    = isset($data['is_operator']) ? (int)$data['is_operator'] : 0;
    $hour_rate      = isset($data['hour_rate']) ? (float)$data['hour_rate'] : 0;
    $vacation_days  = isset($data['vacation_days']) ? (int)$data['vacation_days'] : 0;

    if ($first_name === '' || $last_name === '' || $hour_rate <= 0 || $vacation_days < 0) {
        echo json_encode(['success' => false, 'message' => 'Brak wymaganych danych']);
        exit;
    }

    $stmt = $pdo->prepare('UPDATE employees SET first_name = ?, last_name = ?, is_operator = ?, hour_rate = ?, vacation_days = ? WHERE id = ?');
    $stmt->execute([$first_name, $last_name, $is_operator, $hour_rate, $vacation_days, $id]);

    echo json_encode(['success' => true]);
} catch (Throwable $e) {
    error_log('update_employee.php error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Błąd serwera']);
}
