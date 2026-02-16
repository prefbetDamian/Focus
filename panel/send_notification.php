<?php
/**
 * Wysyłanie powiadomień push do pracowników
 */

require_once __DIR__ . '/../core/auth.php';
require_once __DIR__ . '/../core/db.php';
require_once __DIR__ . '/../core/push.php';

header('Content-Type: application/json; charset=utf-8');

// Tylko kierownicy (role_level >= 2)
$manager = requireManagerPage(2);

try {
    $data = json_decode(file_get_contents('php://input'), true);
    
    $employeeIdRaw = $data['employee_id'] ?? 0;
    $message = isset($data['message']) ? trim($data['message']) : '';
    
    // Obsługa "all" dla wszystkich pracowników
    $sendToAll = ($employeeIdRaw === 'all');
    $employeeId = $sendToAll ? 0 : (int)$employeeIdRaw;
    
    if (!$sendToAll && $employeeId <= 0) {
        echo json_encode(['success' => false, 'message' => 'Nie wybrano pracownika']);
        exit;
    }
    
    if ($message === '') {
        echo json_encode(['success' => false, 'message' => 'Wiadomość nie może być pusta']);
        exit;
    }
    
    $pdo = require __DIR__ . '/../core/db.php';
    
    if ($sendToAll) {
        // Pobierz wszystkich pracowników z aktywną subskrypcją
        $stmt = $pdo->query("
            SELECT DISTINCT e.id, e.first_name, e.last_name
            FROM employees e
            INNER JOIN push_subscriptions ps ON ps.employee_id = e.id
            ORDER BY e.last_name, e.first_name
        ");
        $employees = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($employees)) {
            echo json_encode([
                'success' => false,
                'message' => 'Żaden pracownik nie ma włączonych powiadomień PUSH'
            ]);
            exit;
        }
    } else {
        // Sprawdź czy pracownik istnieje
        $stmt = $pdo->prepare('SELECT first_name, last_name FROM employees WHERE id = ?');
        $stmt->execute([$employeeId]);
        $employee = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$employee) {
            echo json_encode(['success' => false, 'message' => 'Pracownik nie istnieje']);
            exit;
        }
        
        // Sprawdź czy pracownik ma aktywną subskrypcję push
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM push_subscriptions WHERE employee_id = ?');
        $stmt->execute([$employeeId]);
        $hasSubscription = (int)$stmt->fetchColumn() > 0;
        
        if (!$hasSubscription) {
            echo json_encode([
                'success' => false, 
                'message' => 'Pracownik nie ma włączonych powiadomień PUSH'
            ]);
            exit;
        }
        
        $employees = [['id' => $employeeId, 'first_name' => $employee['first_name'], 'last_name' => $employee['last_name']]];
    }
    
    // Pobierz dane kierownika
    $managerName = '';
    if (is_array($manager)) {
        $managerName = ($manager['first_name'] ?? '') . ' ' . ($manager['last_name'] ?? '');
    } elseif (isset($_SESSION['manager_name'])) {
        $managerName = $_SESSION['manager_name'];
    } elseif (isset($_SESSION['manager'])) {
        if (is_array($_SESSION['manager'])) {
            $managerName = ($_SESSION['manager']['first_name'] ?? '') . ' ' . ($_SESSION['manager']['last_name'] ?? '');
        } else {
            $managerName = $_SESSION['manager'];
        }
    }
    $managerName = trim($managerName) ?: 'Kierownik';
    
    // Wyślij powiadomienie/powiadomienia
    $title = "💬 Wiadomość od {$managerName}";
    $body = $message;
    
    $sentCount = 0;
    $failedCount = 0;
    
    foreach ($employees as $emp) {
        try {
            sendPushToEmployee($pdo, (int)$emp['id'], $title, $body);
            $sentCount++;
            
            // Zapisz do loga (opcjonalnie)
            try {
                $managerId = is_array($_SESSION['manager']) 
                    ? ($_SESSION['manager']['id'] ?? $_SESSION['manager_id'] ?? 0)
                    : ($_SESSION['manager_id'] ?? 0);
                    
                $stmt = $pdo->prepare("
                    INSERT INTO notification_log (employee_id, manager_id, message, sent_at)
                    VALUES (?, ?, ?, NOW())
                ");
                $stmt->execute([(int)$emp['id'], $managerId, $message]);
            } catch (PDOException $e) {
                // Tabela notification_log może nie istnieć - to opcjonalne
            }
        } catch (Throwable $e) {
            $failedCount++;
            error_log("Failed to send to employee {$emp['id']}: " . $e->getMessage());
        }
    }
    
    if ($sendToAll) {
        $totalAttempts = $sentCount + $failedCount;
        $message = "Wysłano powiadomienia do {$sentCount} pracownika/ów";
        if ($failedCount > 0) {
            $message .= " (nieudane: {$failedCount})";
        }
        echo json_encode(['success' => true, 'message' => $message]);
    } else {
        echo json_encode([
            'success' => true,
            'message' => "Powiadomienie wysłane do: {$employees[0]['first_name']} {$employees[0]['last_name']}"
        ]);
    }
    
} catch (Throwable $e) {
    error_log("Error in send_notification.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Błąd serwera: ' . $e->getMessage()
    ]);
}
