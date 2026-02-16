<?php
/**
 * Sprawdza czy pracownik ma sesje pracy w podanym zakresie dat
 */

require_once __DIR__ . '/../../core/session.php';
require_once __DIR__ . '/../../core/auth.php';

header('Content-Type: application/json; charset=utf-8');

try {
    $user = requireUser();
    $employeeId = (int)$user['id'];

    $startDate = $_GET['start_date'] ?? '';
    $endDate = $_GET['end_date'] ?? '';

    if (empty($startDate) || empty($endDate)) {
        echo json_encode(['success' => false, 'message' => 'Brak dat']);
        exit;
    }

    $pdo = require __DIR__ . '/../../core/db.php';

    // Sprawdź czy są sesje pracy w tym przedziale
    $stmt = $pdo->prepare("
        SELECT DATE(start_time) as work_date
        FROM work_sessions
        WHERE employee_id = ?
        AND (absence_group_id IS NULL OR absence_group_id = 0)
        AND DATE(start_time) >= ?
        AND DATE(start_time) <= ?
        GROUP BY DATE(start_time)
        ORDER BY work_date
    ");
    $stmt->execute([$employeeId, $startDate, $endDate]);
    $workDates = $stmt->fetchAll(PDO::FETCH_COLUMN);

    echo json_encode([
        'success' => true,
        'has_work_sessions' => count($workDates) > 0,
        'work_dates' => $workDates
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Błąd serwera'
    ]);
}
