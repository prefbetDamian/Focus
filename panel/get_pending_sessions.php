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

$pdo = new PDO(
    "mysql:host={$config['db_host']};port={$config['db_port']};dbname={$config['db_name']};charset=utf8mb4",
    $config['db_user'],
    $config['db_pass'],
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

// Ustal role_level i ID kierownika
$managerArr = isset($_SESSION['manager']) && is_array($_SESSION['manager']) ? $_SESSION['manager'] : null;
$roleLevel = $managerArr['role_level'] ?? ((int)($_SESSION['role_level'] ?? 0));
$managerId = $managerArr['id'] ?? ($_SESSION['manager_id'] ?? null);

if ($roleLevel < 2 || !$managerId) {
    echo json_encode(['success' => false, 'message' => 'Brak uprawnień']);
    exit;
}

try {
    if ((int)$roleLevel === 9) {
        // Admin: widzi wszystkie sesje do akceptacji
        $stmt = $pdo->prepare("\n            SELECT\n                ws.*,\n                COALESCE(SUM(wsa.approved), 0)     AS approved_count,\n                COALESCE(COUNT(wsa.id), 0)         AS approvals_total\n            FROM work_sessions ws\n            LEFT JOIN work_session_approvals wsa\n                ON wsa.work_session_id = ws.id\n            WHERE ws.end_time IS NOT NULL\n              AND ws.status IN ('AUTO','PENDING')\n              AND (ws.absence_group_id IS NULL OR ws.absence_group_id = 0)\n            GROUP BY ws.id\n            ORDER BY ws.end_time DESC\n            LIMIT 200\n        ");
        $stmt->execute();
    } else {
        // Kierownik: tylko sesje z przypisanych budów.
        // Dodatkowo ukrywamy sesje, dla których ten kierownik
        // ma już wpis w work_session_approvals (approved_at IS NOT NULL),
        $stmt = $pdo->prepare("\n            SELECT\n                ws.*,\n                COALESCE(SUM(wsa.approved), 0)     AS approved_count,\n                COALESCE(COUNT(wsa.id), 0)         AS approvals_total\n            FROM work_sessions ws\n            JOIN sites s ON s.id = ws.site_id\n            JOIN site_managers sm ON sm.site_id = s.id\n            LEFT JOIN work_session_approvals wsa\n                ON wsa.work_session_id = ws.id\n            WHERE sm.manager_id = :managerId\n              AND ws.end_time IS NOT NULL\n              AND ws.status IN ('AUTO','PENDING')\n              AND (ws.absence_group_id IS NULL OR ws.absence_group_id = 0)\n              AND (\n                    ws.site_id = 26\n                 OR NOT EXISTS (\n                        SELECT 1\n                        FROM work_session_approvals w2\n                        WHERE w2.work_session_id = ws.id\n                          AND w2.manager_id = :managerId\n                          AND w2.approved_at IS NOT NULL\n                 )\n              )\n            GROUP BY ws.id\n            ORDER BY ws.end_time DESC\n            LIMIT 200\n        ");
        $stmt->execute(['managerId' => (int)$managerId]);
    }

    $sessions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'sessions' => $sessions,
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Błąd bazy danych',
    ]);
}
