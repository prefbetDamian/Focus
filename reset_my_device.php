<?php
ini_set('display_errors', 0);
error_reporting(E_ALL);
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/core/auth.php';

try {
    $user = requireUser();

    $config = require __DIR__ . '/config.php';

        $pdo = new PDO(
		"mysql:host={$config['db_host']};port={$config['db_port']};dbname={$config['db_name']};charset=utf8mb4",
        $config['db_user'],
        $config['db_pass'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    $stmt = $pdo->prepare('UPDATE employees SET device_id = NULL, ip_address = NULL WHERE id = ?');
    $stmt->execute([$user['id']]);

    echo json_encode([
        'success' => true,
        'message' => 'Urządzenie zostało odpięte. Przy następnym logowaniu możesz użyć nowego telefonu.'
    ]);
} catch (Throwable $e) {
    error_log('reset_my_device.php error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Błąd serwera podczas odpinania urządzenia.'
    ]);
}
