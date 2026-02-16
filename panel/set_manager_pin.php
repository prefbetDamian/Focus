<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
header("Content-Type: application/json");

// Ustawienia sesji zgodne z lokalnym HTTP (bez secure gdy brak HTTPS)
ini_set('session.use_strict_mode', '1');
ini_set('session.cookie_httponly', '1');
$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] === '443');
ini_set('session.cookie_secure', $isHttps ? '1' : '0');
ini_set('session.cookie_samesite', $isHttps ? 'None' : 'Lax');

session_start();

$config = require __DIR__.'/../config.php';

$pdo = new PDO(
    "mysql:host={$config['db_host']};port={$config['db_port']};dbname={$config['db_name']};charset=utf8mb4",
    $config['db_user'],
    $config['db_pass'],
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

try {
    $data = json_decode(file_get_contents("php://input"), true);

    $pin = $data['pin'] ?? '';
    $token = trim($data['token'] ?? '');

    if (!$pin) {
        throw new Exception("Brak PIN");
    }

    if (!$token) {
        throw new Exception("Brak tokenu");
    }

    if (!preg_match('/^[0-9]{4}$/', $pin)) {
        throw new Exception("PIN musi mieć 4 cyfry");
    }

    // zweryfikuj token w bazie (token jest unikalny)
    $stmt = $pdo->prepare("SELECT id, first_name, last_name, role_level FROM managers WHERE pin_token = ? LIMIT 1");
    $stmt->execute([$token]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        throw new Exception("Nieprawidłowy token");
    }

    $hash = password_hash($pin, PASSWORD_DEFAULT);

    // ustaw nowy pin i wyczyść token
    $stmt = $pdo->prepare("UPDATE managers SET pin_hash = ?, pin_token = NULL WHERE id = ?");
    $stmt->execute([$hash, $row['id']]);

    if ($stmt->rowCount() === 0) {
        throw new Exception("Błąd aktualizacji");
    }

    // NIE twórz sesji - kierownik musi się zalogować
    echo json_encode(["success" => true, "message" => "PIN ustawiony! Zaloguj się teraz.", "redirect" => "index.html"]);

} catch (Throwable $e) {
    echo json_encode([
        "success" => false,
        "message" => $e->getMessage()
    ]);
}
?>