<?php
/**
 * Zmiana PIN dla ZALOGOWANEGO kierownika
 * Wymaga aktywnej sesji kierownika
 */

ini_set('display_errors', 1);
error_reporting(E_ALL);
header("Content-Type: application/json");

require_once __DIR__.'/../core/session.php';

// Sprawdź czy kierownik zalogowany
if (!isset($_SESSION['manager_id'])) {
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

try {
    $data = json_decode(file_get_contents("php://input"), true);

    $currentPin = $data['current_pin'] ?? '';
    $newPin = $data['new_pin'] ?? '';

    if (!$currentPin || !$newPin) {
        throw new Exception("Podaj obecny i nowy PIN");
    }

    if (!preg_match('/^\d{4}$/', $currentPin) || !preg_match('/^\d{4}$/', $newPin)) {
        throw new Exception("PIN musi mieć 4 cyfry");
    }

    $managerId = $_SESSION['manager_id'];

    // Pobierz kierownika
    $stmt = $pdo->prepare("SELECT id, name, pin_hash FROM managers WHERE id = ?");
    $stmt->execute([$managerId]);
    $manager = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$manager) {
        throw new Exception("Kierownik nie znaleziony");
    }

    // Zweryfikuj obecny PIN
    if (!password_verify($currentPin, $manager['pin_hash'])) {
        throw new Exception("Obecny PIN nieprawidłowy");
    }

    // Ustaw nowy PIN
    $newHash = password_hash($newPin, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("UPDATE managers SET pin_hash = ?, updated_at = NOW() WHERE id = ?");
    $stmt->execute([$newHash, $managerId]);

    echo json_encode(["success" => true]);

} catch (Throwable $e) {
    echo json_encode([
        "success" => false,
        "message" => $e->getMessage()
    ]);
}
