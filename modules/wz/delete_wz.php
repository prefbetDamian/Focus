<?php
/**
 * Usuwanie dokumentu WZ
 */

require_once __DIR__.'/../../core/session.php';
require_once __DIR__.'/../../core/auth.php';

$manager = requireManager(2);

header('Content-Type: application/json; charset=utf-8');

try {
    $input = json_decode(file_get_contents('php://input'), true);
    $id = intval($input['id'] ?? 0);
    
    if (!$id) {
        throw new Exception('Brak ID dokumentu');
    }
    
    $pdo = require __DIR__.'/../../core/db.php';
    
    // Pobierz dane dokumentu
    $stmt = $pdo->prepare("SELECT scan_file, signature_file, pdf_file FROM wz_scans WHERE id = ?");
    $stmt->execute([$id]);
    $doc = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$doc) {
        throw new Exception('Dokument nie istnieje');
    }
    
    // Usuń pliki
    $uploadDir = __DIR__.'/../../uploads/wz/';
    foreach (['scan_file', 'signature_file', 'pdf_file'] as $field) {
        if ($doc[$field] && file_exists($uploadDir . $doc[$field])) {
            unlink($uploadDir . $doc[$field]);
        }
    }
    
    // Usuń z bazy
    $stmt = $pdo->prepare("DELETE FROM wz_scans WHERE id = ?");
    $stmt->execute([$id]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Dokument usunięty pomyślnie'
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
