<?php
ini_set('session.cookie_secure', 0);
ini_set('session.cookie_samesite', 'Lax');

session_start();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['manager']) && !isset($_SESSION['manager_id'])) {
    echo json_encode(['success' => false, 'message' => 'Brak autoryzacji']);
    exit;
}

require_once __DIR__ . '/../core/push.php';

$config = require __DIR__ . '/../config.php';

$pdo = new PDO(
    "mysql:host={$config['db_host']};port={$config['db_port']};dbname={$config['db_name']};charset=utf8mb4",
    $config['db_user'],
    $config['db_pass'],
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

$data = json_decode(file_get_contents('php://input'), true) ?? [];
$sessionId = (int)($data['id'] ?? 0);
$comment   = trim($data['comment'] ?? '');

$managerArr = isset($_SESSION['manager']) && is_array($_SESSION['manager']) ? $_SESSION['manager'] : null;
$roleLevel  = $managerArr['role_level'] ?? ((int)($_SESSION['role_level'] ?? 0));
$managerId  = $managerArr['id'] ?? ($_SESSION['manager_id'] ?? null);

if ($roleLevel < 2 || !$managerId || $sessionId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Brak uprawnień lub błędne dane']);
    exit;
}

try {
        if ((int)$roleLevel === 9) {
		$stmt = $pdo->prepare("\n            SELECT id, site_id, employee_id, site_name\n            FROM work_sessions\n            WHERE id = ?\n              AND end_time IS NOT NULL\n              AND status IN ('AUTO','PENDING')\n        ");
        $stmt->execute([$sessionId]);
    } else {
        $stmt = $pdo->prepare("\n            SELECT ws.id, ws.site_id, ws.employee_id, ws.site_name\n            FROM work_sessions ws\n            JOIN sites s ON s.name = ws.site_name\n            JOIN site_managers sm ON sm.site_id = s.id\n            WHERE ws.id = ?\n              AND sm.manager_id = ?\n              AND ws.end_time IS NOT NULL\n              AND ws.status IN ('AUTO','PENDING')\n        ");
        $stmt->execute([$sessionId, (int)$managerId]);
    }

    $sessionRow = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$sessionRow) {
        echo json_encode(['success' => false, 'message' => 'Sesja nie istnieje lub brak uprawnień']);
        exit;
    }

    $siteId      = isset($sessionRow['site_id']) ? (int)$sessionRow['site_id'] : null;
    $employeeId  = isset($sessionRow['employee_id']) ? (int)$sessionRow['employee_id'] : 0;
    $siteName    = $sessionRow['site_name'] ?? '';

    // Jeśli to specjalna budowa 26 - Roboty niesklasyfikowane, używamy workflow multi-akceptacji
    if ($siteId === 26) {
        // Upewnij się, że istnieją wpisy w work_session_approvals
        // dla wszystkich kierowników przypisanych do tej "budowy".
        $stmtManagers = $pdo->prepare("\n            SELECT manager_id\n            FROM site_managers\n            WHERE site_id = ?\n        ");
        $stmtManagers->execute([$siteId]);
        $managers = $stmtManagers->fetchAll(PDO::FETCH_COLUMN);

        if ($managers) {
            $ins = $pdo->prepare("\n                INSERT IGNORE INTO work_session_approvals (work_session_id, manager_id)\n                VALUES (?, ?)\n            ");

            foreach ($managers as $mid) {
                $ins->execute([$sessionId, (int)$mid]);
            }
        }

        // Zapisz decyzję tego kierownika w workflow
        $updApproval = $pdo->prepare("\n            UPDATE work_session_approvals\n            SET approved = 1,\n                approved_at = NOW(),\n                comment = ?\n            WHERE work_session_id = ?\n              AND manager_id = ?\n        ");
        $updApproval->execute([$comment, $sessionId, (int)$managerId]);

        // Policzymy, ilu już zaakceptowało
        $cnt = $pdo->prepare("\n            SELECT\n                COALESCE(SUM(approved), 0) AS approved_count,\n                COUNT(*)                  AS approvals_total\n            FROM work_session_approvals\n            WHERE work_session_id = ?\n        ");
        $cnt->execute([$sessionId]);
        $row = $cnt->fetch(PDO::FETCH_ASSOC);

        if ($row && (int)$row['approvals_total'] > 0 && (int)$row['approved_count'] === (int)$row['approvals_total']) {
            // Wszyscy kierownicy zaakceptowali → finalne zatwierdzenie sesji
            $upd = $pdo->prepare("\n                UPDATE work_sessions\n                SET status = 'MANUAL',\n                    manager_id = ?,\n                    manager_comment = ?,\n                    approved_at = NOW()\n                WHERE id = ?\n            ");
            $upd->execute([(int)$managerId, $comment, $sessionId]);
        }
    } else {
        // Zwykła budowa – zachowanie jak dotychczas (pojedyncza akceptacja)
        $upd = $pdo->prepare("\n            UPDATE work_sessions\n            SET status = 'MANUAL',\n                manager_id = ?,\n                manager_comment = ?,\n                approved_at = NOW()\n            WHERE id = ?\n        ");
        $upd->execute([(int)$managerId, $comment, $sessionId]);
    }
    
    // PUSH do pracownika – tylko przy finalnej akceptacji
    if ($employeeId > 0) {
        try {
            $title = 'Twoja sesja została zaakceptowana';
            $bodyParts = [];
            if ($siteId) {
                $bodyParts[] = 'Budowa: ' . ($siteName ?: ('ID ' . $siteId));
            }
            if ($comment !== '') {
                $shortComment = mb_substr($comment, 0, 120, 'UTF-8');
                if (mb_strlen($comment, 'UTF-8') > 120) {
                    $shortComment .= '…';
                }
                $bodyParts[] = 'Komentarz: ' . $shortComment;
            }

            $body = $bodyParts ? implode(' | ', $bodyParts) : 'Sesja pracy została zatwierdzona przez kierownika.';

            sendPushToEmployee($pdo, $employeeId, $title, $body, 'panel.php');
        } catch (Throwable $e) {
            // błąd PUSH nie blokuje akceptacji
        }
    }

    echo json_encode(['success' => true]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Błąd bazy danych']);
}
