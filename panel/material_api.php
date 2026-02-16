<?php
/**
 * API do zarządzania grupami i rodzajami materiałów
 * Dostęp tylko dla kierowników i adminów
 */

require_once __DIR__ . '/../core/session.php';
require_once __DIR__ . '/../core/auth.php';

header('Content-Type: application/json; charset=utf-8');

try {
    // Dostęp tylko dla role_level >= 2
    $managerInfo = requireManagerPage(2);
    $roleLevel = (int)($_SESSION['role_level'] ?? 0);
    
    $pdo = require __DIR__ . '/../core/db.php';
    
    $action = $_GET['action'] ?? $_POST['action'] ?? '';
    
    switch ($action) {
        case 'get_all':
            // Pobierz wszystkie grupy z ich materiałami
            $stmt = $pdo->query("SELECT id, name, display_order FROM material_groups ORDER BY display_order, name");
            $groups = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($groups as &$group) {
                $stmt = $pdo->prepare("SELECT id, name, display_order FROM material_types WHERE group_id = ? ORDER BY display_order, name");
                $stmt->execute([$group['id']]);
                $group['materials'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }
            
            echo json_encode(['success' => true, 'groups' => $groups]);
            break;
            
        case 'add_group':
            // Tylko dla role_level = 9 (administrator)
            if ($roleLevel !== 9) {
                throw new Exception('Brak uprawnień do dodawania grup');
            }
            
            $name = trim($_POST['name'] ?? '');
            if (empty($name)) {
                throw new Exception('Nazwa grupy jest wymagana');
            }
            
            // Znajdź najwyższy display_order
            $stmt = $pdo->query("SELECT COALESCE(MAX(display_order), 0) + 1 AS next_order FROM material_groups");
            $nextOrder = $stmt->fetchColumn();
            
            $stmt = $pdo->prepare("INSERT INTO material_groups (name, display_order) VALUES (?, ?)");
            $stmt->execute([$name, $nextOrder]);
            
            echo json_encode([
                'success' => true,
                'message' => 'Grupa materiałów została dodana',
                'id' => $pdo->lastInsertId()
            ]);
            break;
            
        case 'update_group':
            // Tylko dla role_level = 9 (administrator)
            if ($roleLevel !== 9) {
                throw new Exception('Brak uprawnień do edycji grup');
            }
            
            $id = (int)($_POST['id'] ?? 0);
            $name = trim($_POST['name'] ?? '');
            
            if (!$id || empty($name)) {
                throw new Exception('ID i nazwa grupy są wymagane');
            }
            
            $stmt = $pdo->prepare("UPDATE material_groups SET name = ? WHERE id = ?");
            $stmt->execute([$name, $id]);
            
            echo json_encode(['success' => true, 'message' => 'Grupa została zaktualizowana']);
            break;
            
        case 'delete_group':
            // Tylko dla role_level = 9 (administrator)
            if ($roleLevel !== 9) {
                throw new Exception('Brak uprawnień do usuwania grup');
            }
            
            $id = (int)($_POST['id'] ?? 0);
            if (!$id) {
                throw new Exception('ID grupy jest wymagane');
            }
            
            // Sprawdź czy grupa ma materiały
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM material_types WHERE group_id = ?");
            $stmt->execute([$id]);
            $count = $stmt->fetchColumn();
            
            if ($count > 0) {
                throw new Exception('Nie można usunąć grupy, która zawiera materiały. Usuń najpierw materiały.');
            }
            
            $stmt = $pdo->prepare("DELETE FROM material_groups WHERE id = ?");
            $stmt->execute([$id]);
            
            echo json_encode(['success' => true, 'message' => 'Grupa została usunięta']);
            break;
            
        case 'add_material':
            // Tylko dla role_level = 9 (administrator)
            if ($roleLevel !== 9) {
                throw new Exception('Brak uprawnień do dodawania materiałów');
            }
            
            $groupId = (int)($_POST['group_id'] ?? 0);
            $name = trim($_POST['name'] ?? '');
            
            if (!$groupId || empty($name)) {
                throw new Exception('ID grupy i nazwa materiału są wymagane');
            }
            
            // Znajdź najwyższy display_order w grupie
            $stmt = $pdo->prepare("SELECT COALESCE(MAX(display_order), 0) + 1 AS next_order FROM material_types WHERE group_id = ?");
            $stmt->execute([$groupId]);
            $nextOrder = $stmt->fetchColumn();
            
            $stmt = $pdo->prepare("INSERT INTO material_types (group_id, name, display_order) VALUES (?, ?, ?)");
            $stmt->execute([$groupId, $name, $nextOrder]);
            
            echo json_encode([
                'success' => true,
                'message' => 'Materiał został dodany',
                'id' => $pdo->lastInsertId()
            ]);
            break;
            
        case 'update_material':
            // Tylko dla role_level = 9 (administrator)
            if ($roleLevel !== 9) {
                throw new Exception('Brak uprawnień do edycji materiałów');
            }
            
            $id = (int)($_POST['id'] ?? 0);
            $name = trim($_POST['name'] ?? '');
            
            if (!$id || empty($name)) {
                throw new Exception('ID i nazwa materiału są wymagane');
            }
            
            $stmt = $pdo->prepare("UPDATE material_types SET name = ? WHERE id = ?");
            $stmt->execute([$name, $id]);
            
            echo json_encode(['success' => true, 'message' => 'Materiał został zaktualizowany']);
            break;
            
        case 'delete_material':
            // Tylko dla role_level = 9 (administrator)
            if ($roleLevel !== 9) {
                throw new Exception('Brak uprawnień do usuwania materiałów');
            }
            
            $id = (int)($_POST['id'] ?? 0);
            if (!$id) {
                throw new Exception('ID materiału jest wymagane');
            }
            
            // Sprawdź czy materiał jest używany w dokumentach WZ
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM wz_scans WHERE material_type = (SELECT name FROM material_types WHERE id = ?)");
            $stmt->execute([$id]);
            $count = $stmt->fetchColumn();
            
            if ($count > 0) {
                throw new Exception('Nie można usunąć materiału używanego w dokumentach WZ (' . $count . ' dokumentów)');
            }
            
            $stmt = $pdo->prepare("DELETE FROM material_types WHERE id = ?");
            $stmt->execute([$id]);
            
            echo json_encode(['success' => true, 'message' => 'Materiał został usunięty']);
            break;
            
        case 'reorder_groups':
            // Tylko dla role_level = 9 (administrator)
            if ($roleLevel !== 9) {
                throw new Exception('Brak uprawnień do zmiany kolejności');
            }
            
            $orders = $_POST['orders'] ?? [];
            if (!is_array($orders)) {
                throw new Exception('Nieprawidłowe dane kolejności');
            }
            
            $pdo->beginTransaction();
            try {
                $stmt = $pdo->prepare("UPDATE material_groups SET display_order = ? WHERE id = ?");
                foreach ($orders as $id => $order) {
                    $stmt->execute([(int)$order, (int)$id]);
                }
                $pdo->commit();
                echo json_encode(['success' => true, 'message' => 'Kolejność została zaktualizowana']);
            } catch (Exception $e) {
                $pdo->rollBack();
                throw $e;
            }
            break;
            
        case 'reorder_materials':
            // Tylko dla role_level = 9 (administrator)
            if ($roleLevel !== 9) {
                throw new Exception('Brak uprawnień do zmiany kolejności');
            }
            
            $orders = $_POST['orders'] ?? [];
            if (!is_array($orders)) {
                throw new Exception('Nieprawidłowe dane kolejności');
            }
            
            $pdo->beginTransaction();
            try {
                $stmt = $pdo->prepare("UPDATE material_types SET display_order = ? WHERE id = ?");
                foreach ($orders as $id => $order) {
                    $stmt->execute([(int)$order, (int)$id]);
                }
                $pdo->commit();
                echo json_encode(['success' => true, 'message' => 'Kolejność została zaktualizowana']);
            } catch (Exception $e) {
                $pdo->rollBack();
                throw $e;
            }
            break;
            
        default:
            throw new Exception('Nieznana akcja: ' . $action);
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
