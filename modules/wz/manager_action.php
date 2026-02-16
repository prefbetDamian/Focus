<?php
/**
 * Akcje kierownika WZ: odrzucenie dokumentu
 */

ini_set('display_errors', 0);
error_reporting(E_ALL);

require_once __DIR__ . '/../../core/session.php';
require_once __DIR__ . '/../../core/auth.php';
require_once __DIR__ . '/../../core/push.php';

header('Content-Type: application/json; charset=utf-8');

try {
    $manager = requireManager(2);

    $pdo = require __DIR__ . '/../../core/db.php';

    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    $id = isset($input['id']) ? (int)$input['id'] : 0;
    $action = $input['action'] ?? '';
    $notes = trim($input['notes'] ?? '');

    if (!$id || $action !== 'reject') {
        throw new Exception('Nieprawidłowe dane wejściowe');
    }

    // Pobierz dokument WZ
    $stmt = $pdo->prepare("SELECT 
        w.id,
        w.document_number,
        w.status,
        w.manager_id,
        w.employee_id,
        w.operator_id,
        w.notes,
        w.site_id,
        s.name AS site_name
    FROM wz_scans w
    LEFT JOIN sites s ON w.site_id = s.id
    WHERE w.id = ?
    LIMIT 1");
    $stmt->execute([$id]);
    $doc = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$doc) {
        throw new Exception('Dokument nie istnieje');
    }

    if ($doc['status'] !== 'waiting_manager') {
        throw new Exception('Ten dokument nie oczekuje na akceptację kierownika');
    }

    // Odrzucenie przez kierownika - wymagany powód
    if ($notes === '') {
        throw new Exception('Powód odrzucenia jest wymagany');
    }
    
    $newNotes = $doc['notes'] ?? '';
    $managerName = $manager['first_name'] . ' ' . $manager['last_name'];
    $prefix = $newNotes !== '' ? "\nKierownik (" . $managerName . "): " : "Kierownik (" . $managerName . "): ";
    $newNotes .= $prefix . $notes;

    $update = $pdo->prepare("UPDATE wz_scans SET status = 'rejected', notes = ?, updated_at = NOW() WHERE id = ?");
    $update->execute([$newNotes, $id]);

    // PUSH do operatora (odbiorcy materiału)
    if (!empty($doc['operator_id'])) {
        try {
            $title = 'Dokument WZ odrzucony przez kierownika';
            $body = 'WZ ' . ($doc['document_number'] ?? '') . ' został odrzucony przez kierownika.';
            if ($notes !== '') {
                $body .= " \nPowód: " . $notes;
            }

            sendPushToEmployee(
                $pdo,
                (int)$doc['operator_id'],
                $title,
                $body,
                'modules/wz/operator.php'
            );
        } catch (Throwable $e) {
            error_log('WZ manager reject PUSH to operator error: ' . $e->getMessage());
        }
    }

    // PUSH do osoby która utworzyła dokument (manager lub employee)
    try {
        $title = 'Dokument WZ odrzucony przez kierownika';
        $body = 'WZ ' . ($doc['document_number'] ?? '') . ' został odrzucony przez kierownika.';
        if ($notes !== '') {
            $body .= " \nPowód: " . $notes;
        }

        if (!empty($doc['manager_id'])) {
            sendPushToManager(
                $pdo,
                (int)$doc['manager_id'],
                $title,
                $body,
                'modules/wz/list_wz.php'
            );
        } elseif (!empty($doc['employee_id'])) {
            sendPushToEmployee(
                $pdo,
                (int)$doc['employee_id'],
                $title,
                $body,
                'modules/wz/operator.php'
            );
        }
    } catch (Throwable $e) {
        error_log('WZ manager reject PUSH to creator error: ' . $e->getMessage());
    }

    echo json_encode([
        'success' => true,
        'message' => 'Dokument został odrzucony. Transport/materiał nie będzie realizowany.'
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
    ]);
}
