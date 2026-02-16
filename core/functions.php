<?php
/**
 * CORE: Funkcje pomocnicze używane w całym systemie
 */

/**
 * Bezpieczne pobranie IP użytkownika
 */
function getClientIP(): string {
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

/**
 * Zaokrąglanie sekund do 15-minutowych interwałów
 */
function roundToIntervals(int $seconds): int {
    $interval = 15 * 60; // 15 minut
    return (int)(round($seconds / $interval) * $interval);
}

/**
 * Formatowanie czasu pracy (sekundy → HH:MM)
 */
function formatDuration(int $seconds): string {
    $hours = floor($seconds / 3600);
    $minutes = floor(($seconds % 3600) / 60);
    return sprintf('%02d:%02d', $hours, $minutes);
}

/**
 * Bezpieczne pobranie danych JSON z request body
 */
function getJSONInput(): array {
    $data = json_decode(file_get_contents("php://input"), true);
    return is_array($data) ? $data : [];
}

/**
 * Odpowiedź JSON (sukces)
 */
function jsonSuccess(string $message = 'OK', array $data = []): void {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(array_merge(['success' => true, 'message' => $message], $data));
    exit;
}

/**
 * Odpowiedź JSON (błąd)
 */
function jsonError(string $message, int $httpCode = 400): void {
    http_response_code($httpCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'message' => $message]);
    exit;
}

/**
 * Logiczne zamknięcie dnia pracy (wywoływane o północy)
 */
function closeWorkDay(PDO $pdo): void {
    require_once __DIR__.'/../day_closure.php';
    closeWorkDay($pdo);
}
