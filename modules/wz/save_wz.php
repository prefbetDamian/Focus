<?php
/**
 * Zapisywanie dokumentów WZ - API
 * Obsługuje upload skanów i podpisów
 */

require_once __DIR__.'/../../core/session.php';
require_once __DIR__.'/../../core/auth.php';
require_once __DIR__.'/../../core/push.php';

// Uprawnienia: manager (rola 2+) lub pracownik-operator WZ (is_operator = 3)
$manager = null;
$employee = null;

if (isset($_SESSION['manager_id'])) {
    // Tryb księgowy / admin
    $manager = requireManager(2);
} else {
    // Tryb specjalnego operatora WZ (pracownik z is_operator = 3)
    $employee = requireUser();
    if ((int)($employee['is_operator'] ?? 0) !== 3) {
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'message' => 'Brak uprawnień do zapisu dokumentów WZ',
        ]);
        exit;
    }
}

header('Content-Type: application/json; charset=utf-8');

try {
    $pdo = require __DIR__.'/../../core/db.php';
    
    // Weryfikacja parametrów
    $documentNumber = trim($_POST['document_number'] ?? '');
    $siteId = intval($_POST['site_id'] ?? 0);
    $materialType = trim($_POST['material_type'] ?? '');
    $materialQuantityRaw = trim($_POST['material_quantity'] ?? '');
    $notes = trim($_POST['notes'] ?? '');
    // Nowy workflow: startujemy jako oczekujące na operatora
    $status = 'waiting_operator';
    $operatorId = intval($_POST['operator_id'] ?? 0);
    
    if (!$documentNumber) {
        throw new Exception('Numer dokumentu jest wymagany');
    }
    
    if (!$siteId) {
        throw new Exception('Wybierz budowę');
    }

    if ($materialType === '') {
        throw new Exception('Wybierz rodzaj materiału');
    }

    if ($materialQuantityRaw === '') {
        throw new Exception('Podaj ilość materiału');
    }

    $materialQuantityNormalized = str_replace(',', '.', $materialQuantityRaw);
    if (!is_numeric($materialQuantityNormalized) || (float)$materialQuantityNormalized <= 0) {
        throw new Exception('Ilość materiału musi być dodatnią liczbą');
    }

    $materialQuantity = (float)$materialQuantityNormalized;

    if (!$operatorId) {
        throw new Exception('Wybierz operatora / odbiorcę materiału');
    }
    
    // Sprawdzenie czy budowa istnieje
    $stmt = $pdo->prepare("SELECT id FROM sites WHERE id = ?");
    $stmt->execute([$siteId]);
    if (!$stmt->fetch()) {
        throw new Exception('Wybrana budowa nie istnieje');
    }

    // Walidacja po aktywnych budowach:
    // wybrana budowa musi mieć co najmniej jedną aktywną sesję kierowcy (is_operator = 2)
    $stmt = $pdo->prepare("SELECT 1
            FROM work_sessions ws
            INNER JOIN employees e ON e.id = ws.employee_id
            WHERE ws.site_id = ?
              AND ws.end_time IS NULL
              AND e.is_operator = 2
            LIMIT 1");
    $stmt->execute([$siteId]);
    if (!$stmt->fetchColumn()) {
        throw new Exception('Wybrana budowa nie ma aktywnej sesji kierowcy');
    }
    
    // Sprawdzenie czy operator / kierowca istnieje (i jest oznaczony jako kierowca)
    $stmt = $pdo->prepare("SELECT id, first_name, last_name FROM employees WHERE id = ? AND is_operator = 2");
    $stmt->execute([$operatorId]);
    $operator = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$operator) {
        throw new Exception('Wybrany operator nie istnieje lub nie jest oznaczony jako kierowca');
    }

    // Katalog dla plików WZ
    $uploadDir = __DIR__.'/../../uploads/wz/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    
    $scanFile = null;
    $signatureFile = null;
    
    // Obsługa uploadu skanu dokumentu
    if (isset($_FILES['scan']) && $_FILES['scan']['error'] === UPLOAD_ERR_OK) {
        $ext = strtolower(pathinfo($_FILES['scan']['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'pdf'];
        
        if (!in_array($ext, $allowed)) {
            throw new Exception('Dozwolone formaty: JPG, PNG, PDF');
        }
        
        // Maksymalny rozmiar: 10MB
        if ($_FILES['scan']['size'] > 10 * 1024 * 1024) {
            throw new Exception('Plik zbyt duży (max 10MB)');
        }
        
        $scanFile = 'scan_' . time() . '_' . uniqid() . '.' . $ext;
        $scanPath = $uploadDir . $scanFile;
        
        if (!move_uploaded_file($_FILES['scan']['tmp_name'], $scanPath)) {
            throw new Exception('Błąd podczas zapisywania skanu');
        }
    }
    
    // Obsługa podpisu (base64 z canvas)
    if (!empty($_POST['signature_data'])) {
        $signatureData = $_POST['signature_data'];
        
        // Sprawdzenie formatu base64
        if (preg_match('/^data:image\/png;base64,(.+)$/', $signatureData, $matches)) {
            $signatureFile = 'signature_' . time() . '_' . uniqid() . '.png';
            $signaturePath = $uploadDir . $signatureFile;
            
            $imageData = base64_decode($matches[1]);
            if ($imageData === false) {
                throw new Exception('Nieprawidłowy format podpisu');
            }
            
            if (file_put_contents($signaturePath, $imageData) === false) {
                throw new Exception('Błąd podczas zapisywania podpisu');
            }
        }
    }
    
    // Zapis do bazy danych
    // employee_id – kto skanował jako pracownik (operator is 3)
    // manager_id  – kto skanował jako manager (księgowy / admin)
    $creatorEmployeeId = $employee['id'] ?? null;
    $creatorManagerId  = $manager['id'] ?? null;

    $sql = "INSERT INTO wz_scans 
            (employee_id, manager_id, operator_id, site_id, document_number, material_type, material_quantity, scan_file, signature_file, status, notes) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        $creatorEmployeeId,
        $creatorManagerId,
        $operatorId,
        $siteId,
        $documentNumber,
        $materialType,
        $materialQuantity,
        $scanFile,
        $signatureFile,
        $status,
        $notes
    ]);
    
    $wzId = $pdo->lastInsertId();
    
    // PUSH: powiadom operatora o nowym dokumencie do potwierdzenia
    try {
        $title = 'Nowy dokument WZ do potwierdzenia';
        $bodyLines = [];
        $bodyLines[] = 'Numer: ' . $documentNumber;
        $bodyLines[] = 'Operator: ' . trim(($operator['first_name'] ?? '') . ' ' . ($operator['last_name'] ?? ''));
        $bodyLines[] = 'Materiał: ' . $materialType;
        $bodyLines[] = 'Ilość: ' . $materialQuantityRaw;
        $body = implode(" \n", array_filter($bodyLines));

        // Docelowo dedykowana strona operatora WZ
        sendPushToEmployee($pdo, (int)$operatorId, $title, $body, 'modules/wz/operator.php');
    } catch (Throwable $e) {
        error_log('WZ PUSH to operator error: ' . $e->getMessage());
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Dokument WZ zapisany pomyślnie',
        'wz_id' => $wzId,
        'document_number' => $documentNumber
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
