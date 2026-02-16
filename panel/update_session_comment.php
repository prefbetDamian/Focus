<?php
/**
 * Aktualizuj komentarz do aktywnej sesji pracy
 */

ini_set('session.cookie_secure', 0);
ini_set('session.cookie_samesite', 'Lax');
session_start();

if (!isset($_SESSION['manager'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Brak dostępu']);
    exit;
}

header('Content-Type: application/json');

$config = require __DIR__.'/../config.php';

$data = json_decode(file_get_contents('php://input'), true);
$sessionId = (int)($data['session_id'] ?? 0);
$comment = trim($data['comment'] ?? '');
$managerId = (int)$_SESSION['manager_id'];

if (!$sessionId) {
    echo json_encode(['success' => false, 'message' => 'Brak ID sesji']);
    exit;
}

try {
        $pdo = new PDO(
		"mysql:host={$config['db_host']};port={$config['db_port']};dbname={$config['db_name']};charset=utf8mb4",
        $config['db_user'],
        $config['db_pass'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    // Pobierz powiązanego pracownika do wysłania POWIADOMIENIA PUSH
    $stmt = $pdo->prepare("
        SELECT ws.employee_id, e.first_name, e.last_name
        FROM work_sessions ws
        LEFT JOIN employees e ON e.id = ws.employee_id
        WHERE ws.id = ?
        LIMIT 1
    ");
    $stmt->execute([$sessionId]);
    $sessionRow = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

    $stmt = $pdo->prepare("
        UPDATE work_sessions 
        SET manager_comment = ?, manager_id = ?
        WHERE id = ? AND end_time IS NULL
    ");
    $stmt->execute([$comment, $managerId, $sessionId]);

    // Wyślij PUSH tylko jeśli jest komentarz oraz znamy pracownika
    if ($sessionRow && !empty($sessionRow['employee_id']) && $comment !== '') {
        require_once __DIR__ . '/../core/push.php';

        $employeeId = (int)$sessionRow['employee_id'];
        $empName = trim(($sessionRow['first_name'] ?? '') . ' ' . ($sessionRow['last_name'] ?? ''));

        // Przygotuj zwięzły tekst powiadomienia
        $shortComment = mb_substr($comment, 0, 120, 'UTF-8');
        if (mb_strlen($comment, 'UTF-8') > 120) {
            $shortComment .= '…';
        }

        $title = 'Nowy komentarz kierownika';
        $body  = ($empName ? ($empName . ': ') : '') . $shortComment;

        // Używamy ścieżki względnej (panel.php), aby działała zarówno z /Rcp/, jak i bez
        sendPushToEmployee($pdo, $employeeId, $title, $body, 'panel.php');
    }

    echo json_encode(['success' => true, 'message' => 'Komentarz do sesji zapisany']);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
