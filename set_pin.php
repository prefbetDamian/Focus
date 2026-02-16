<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
header('Content-Type: application/json; charset=utf-8');
ob_start();

$config = require __DIR__ . '/config.php';

$pdo = new PDO(
    "mysql:host={$config['db_host']};port={$config['db_port']};dbname={$config['db_name']};charset=utf8mb4",
    $config['db_user'],
    $config['db_pass'],
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

try {
    $data = json_decode(file_get_contents('php://input'), true);

    $token       = trim($data['token'] ?? '');
    $pin         = $data['pin'] ?? '';
    $resetDevice = !empty($data['reset_device']);

    if (!$token) {
        throw new Exception('Brak tokenu');
    }

    if ($resetDevice) {
        // Reset urządzenia wymaga poprawnego tokenu i poprawnego, już ustawionego PIN-u
        if (!$pin) {
            throw new Exception('Brak PINu');
        }

        if (!preg_match('/^[0-9]{4}$/', $pin)) {
            throw new Exception('PIN 4 cyfr');
        }

        // Znajdź pracownika po tokenie
        $stmt = $pdo->prepare('
            SELECT id, pin_hash
            FROM employees
            WHERE pin_token = ?
            LIMIT 1
        ');
        $stmt->execute([$token]);
        $emp = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

        if (!$emp) {
            throw new Exception('Nieprawidłowy token');
        }

        if (empty($emp['pin_hash']) || !password_verify($pin, $emp['pin_hash'])) {
            throw new Exception('Nieprawidłowy PIN');
        }

        // Dopiero po poprawnym PIN-ie odpinamy urządzenie
        $stmt = $pdo->prepare('
            UPDATE employees
            SET device_id = NULL, ip_address = NULL
            WHERE id = ?
        ');
        $stmt->execute([$emp['id']]);

        if (ob_get_length()) { ob_clean(); }
        echo json_encode([
            'success'  => true,
            'message'  => 'Urządzenie zostało odpięte. Możesz zalogować się z nowego urządzenia.',
            'redirect' => 'index.html',
        ]);
        exit;
    }

    // Ustawianie PINu dla pracownika (pierwsze ustawienie)
    if (!$pin) {
        throw new Exception('Brak PINu');
    }

    if (!preg_match('/^[0-9]{4}$/', $pin)) {
        throw new Exception('PIN 4 cyfr');
    }

    $hash = password_hash($pin, PASSWORD_DEFAULT);

    // Szukaj pracownika z tym tokenem i ustaw PIN
    $stmt = $pdo->prepare('
        UPDATE employees
        SET pin_hash = ?
        WHERE pin_token = ?
    ');
    $stmt->execute([$hash, $token]);

    if ($stmt->rowCount() === 0) {
        throw new Exception('Nieprawidłowy token');
    }

    if (ob_get_length()) { ob_clean(); }
    echo json_encode([
        'success'  => true,
        'message'  => 'PIN ustawiony! Zaloguj się teraz.',
        'redirect' => 'index.html',
    ]);

} catch (Throwable $e) {
    if (ob_get_length()) { ob_clean(); }
    echo json_encode([
        'success' => false,
        'msg'     => $e->getMessage(),
        'message' => $e->getMessage(),
    ]);
}
