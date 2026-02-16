<?php
/**
 * Akcje operatora WZ: potwierdzenie / odrzucenie dokumentu
 */

ini_set('display_errors', 0);
error_reporting(E_ALL);

require_once __DIR__ . '/../../core/session.php';
require_once __DIR__ . '/../../core/auth.php';
require_once __DIR__ . '/../../core/push.php';

header('Content-Type: application/json; charset=utf-8');

try {
    $user = requireUser();

    if (empty($user['is_operator'])) {
        throw new Exception('Moduł dostępny tylko dla operatorów');
    }

    $pdo = require __DIR__ . '/../../core/db.php';

    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    $id = isset($input['id']) ? (int)$input['id'] : 0;
    $action = $input['action'] ?? '';
    $notes = trim($input['notes'] ?? '');

    if (!$id || !in_array($action, ['approve', 'reject'], true)) {
        throw new Exception('Nieprawidłowe dane wejściowe');
    }

    // Pobierz dokument WZ przypisany do tego operatora
    $stmt = $pdo->prepare("SELECT 
        w.id,
        w.document_number,
        w.status,
        w.manager_id,
        w.notes,
        w.site_id,
        s.name AS site_name
    FROM wz_scans w
    LEFT JOIN sites s ON w.site_id = s.id
    WHERE w.id = ? AND w.operator_id = ?
    LIMIT 1");
    $stmt->execute([$id, $user['id']]);
    $doc = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$doc) {
        throw new Exception('Dokument nie istnieje lub nie jest przypisany do Ciebie');
    }

    if ($doc['status'] !== 'waiting_operator') {
        throw new Exception('Ten dokument nie oczekuje na potwierdzenie operatora');
    }

    if ($action === 'approve') {
        // Operator potwierdza – dokument trafia do kierownika
        $update = $pdo->prepare("UPDATE wz_scans SET status = 'waiting_manager', updated_at = NOW() WHERE id = ?");
        $update->execute([$id]);

        // PUSH do kierowników przypisanych do budowy (site_managers),
        // a jeśli brak przypisania – do wszystkich kierowników roli 2
        try {
            $managers = [];
            $siteId = isset($doc['site_id']) ? (int)$doc['site_id'] : 0;

            if ($siteId > 0) {
                $stmtManagers = $pdo->prepare("SELECT manager_id FROM site_managers WHERE site_id = ?");
                $stmtManagers->execute([$siteId]);
                $managers = $stmtManagers->fetchAll(PDO::FETCH_COLUMN);
            }

            // Fallback: jeśli brak przypisanych kierowników do budowy, wyślij do wszystkich z role_level = 2
            if (empty($managers)) {
                $stmtMgr = $pdo->query("SELECT id FROM managers WHERE role_level = 2");
                $managers = $stmtMgr->fetchAll(PDO::FETCH_COLUMN);
            }

            if (!empty($managers)) {
                $title = 'Dokument WZ do akceptacji';
                $bodyBase = 'WZ ' . ($doc['document_number'] ?? '') . ' dla budowy ' . ($doc['site_name'] ?? '-') . ' czeka na akceptację.';

                foreach ($managers as $mid) {
                    sendPushToManager(
                        $pdo,
                        (int)$mid,
                        $title,
                        $bodyBase,
                        'modules/wz/list_wz.php'
                    );
                }
            }
        } catch (Throwable $e) {
            error_log('WZ operator approve PUSH error: ' . $e->getMessage());
        }

        echo json_encode([
            'success' => true,
            'message' => 'Dokument został potwierdzony i przekazany do akceptacji kierownika.'
        ]);
        exit;
    }

    // Odrzucenie przez operatora - wymagany powód
    if ($notes === '') {
        throw new Exception('Powód odrzucenia jest wymagany');
    }
    
    $newNotes = $doc['notes'] ?? '';
    $prefix = $newNotes !== '' ? "\nOperator: " : 'Operator: ';
    $newNotes .= $prefix . $notes;

    $update = $pdo->prepare("UPDATE wz_scans SET status = 'rejected', notes = ?, updated_at = NOW() WHERE id = ?");
    $update->execute([$newNotes, $id]);

    // PUSH do managera, który wystawił dokument
    if (!empty($doc['manager_id'])) {
        try {
            $title = 'Dokument WZ odrzucony przez operatora';
            $body = 'WZ ' . ($doc['document_number'] ?? '') . ' został odrzucony przez operatora.';
            if ($notes !== '') {
                $body .= " \nPowód: " . $notes;
            }

            sendPushToManager(
                $pdo,
                (int)$doc['manager_id'],
                $title,
                $body,
                'modules/wz/list_wz.php'
            );
        } catch (Throwable $e) {
            error_log('WZ operator reject PUSH error: ' . $e->getMessage());
        }
    }

    echo json_encode([
        'success' => true,
        'message' => 'Dokument został odrzucony.'
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
    ]);
}
