<?php
/**
 * API dla modułu tankowania maszyn
 * Dla operatorów w systemie RCP
 */

header("Content-Type: application/json");
error_reporting(E_ALL);
ini_set('display_errors', 0);

try {
    require_once __DIR__.'/../../core/session.php';
    require_once __DIR__.'/../../core/functions.php';
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => 'Błąd ładowania plików: ' . $e->getMessage()]);
    exit;
}

// Sprawdź czy użytkownik jest zalogowany
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Nie jesteś zalogowany']);
    exit;
}

try {
    $pdo = require __DIR__.'/../../core/db.php';
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => 'Błąd połączenia z bazą: ' . $e->getMessage()]);
    exit;
}

// Sprawdź czy jest operatorem
// 1 = operator maszyny, 2 = kierowca, 3 = ładowarka
$stmt = $pdo->prepare("SELECT is_operator FROM employees WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

if (!$user) {
    echo json_encode(['status' => 'error', 'message' => 'Brak uprawnień operatora']);
    exit;
}

$opRoleGate = (int)$user['is_operator'];
if (!in_array($opRoleGate, [1, 2, 3], true)) {
    echo json_encode(['status' => 'error', 'message' => 'Brak uprawnień operatora']);
    exit;
}

/* =========================
   FUNKCJE POMOCNICZE
========================= */
function getIP(){
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        return explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0];
    }
    return $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';
}

function checkPinDetailed($pin, $pdo){
    $stmt = $pdo->prepare("
        SELECT id, first_name, last_name, is_operator
        FROM employees
        WHERE pin = ?
          AND (blocked_until IS NULL OR blocked_until < NOW())
    ");
    $stmt->execute([$pin]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

/* =========================
   INPUT
========================= */
$data = json_decode(file_get_contents("php://input"), true);
$action = $data['action'] ?? '';

/* =========================
   GET MACHINES BY OWNER
========================= */
if ($action === 'get_machines') {
    $owner = trim($data['owner'] ?? '');
    $getAll = isset($data['get_all']) && $data['get_all'] === true;

    if ($getAll || $owner === '') {
        // Pobierz wszystkie maszyny (pomijając nr ewidencyjny 1)
        $stmt = $pdo->prepare("
            SELECT id, machine_name, registry_number, owner
            FROM machines
            WHERE registry_number != '1'
            ORDER BY CAST(registry_number AS UNSIGNED), registry_number
        ");
        $stmt->execute();
    } else {
        // Pobierz maszyny dla konkretnego właściciela (pomijając nr ewidencyjny 1)
        $stmt = $pdo->prepare("
            SELECT id, machine_name, registry_number, owner
            FROM machines
            WHERE REPLACE(UPPER(owner), '-', '') = REPLACE(UPPER(?), '-', '')
              AND registry_number != '1'
            ORDER BY CAST(registry_number AS UNSIGNED), registry_number
        ");
        $stmt->execute([$owner]);
    }

    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    exit;
}

/* =========================
   SAVE FUEL
========================= */
if ($action === 'save_fuel') {
    
    try {
        // WALIDACJA DANYCH WEJŚCIOWYCH (firma może być pusta przy maszynie z aktywnej sesji)
    $rawOwner = isset($data['owner']) ? trim($data['owner']) : '';

    if (!isset($data['machine_id']) || !is_numeric($data['machine_id'])) {
        echo json_encode(['status' => 'error', 'message' => 'Wybierz maszynę z listy']);
        exit;
    }

    if (!isset($data['liters']) || !is_numeric($data['liters']) || $data['liters'] <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'Podaj poprawną ilość litrów (tylko liczby)']);
        exit;
    }

    if (!isset($data['meter_mh']) || !is_numeric($data['meter_mh']) || $data['meter_mh'] <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'Podaj poprawny stan licznika m-h (tylko liczby)']);
        exit;
    }

    if (!isset($data['manager_pin']) || !preg_match('/^\d{4}$/', $data['manager_pin'])) {
        echo json_encode(['status' => 'error', 'message' => 'Podaj 4-cyfrowy PIN']);
        exit;
    }

    // Konwersja na liczby zmiennoprzecinkowe
    $liters = floatval($data['liters']);
    $meterMh = floatval($data['meter_mh']);
    $machineId = intval($data['machine_id']);
    
    // Operator = zalogowany użytkownik (musi być operatorem)
    $stmt = $pdo->prepare("SELECT id, first_name, last_name, is_operator, pin_hash FROM employees WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $operator = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$operator) {
        echo json_encode(['status' => 'error', 'message' => 'Błąd sesji użytkownika']);
        exit;
    }

    // Sprawdź czy operator faktycznie ma uprawnienia (1, 2 lub 3)
    $opRole = (int)$operator['is_operator'];
    if (!in_array($opRole, [1, 2, 3], true)) {
        echo json_encode(['status' => 'error', 'message' => 'Nie jesteś operatorem']);
        exit;
    }

    $mode = isset($data['mode']) && $data['mode'] === 'internal' ? 'internal' : 'external';

    // Walidacja PIN - sprawdź czy to PIN managera (role_level=5) LUB (dla trybu zewnętrznego) własny PIN operatora
    $manager = null;
    $isSelfApproved = false;

        // Walidacja PIN według trybu
        if ($mode === 'external') {
            // Tryb ZEWNĘTRZNY: wymagany WYŁĄCZNIE PIN bieżącego operatora
            if (!password_verify($data['manager_pin'], $operator['pin_hash'])) {
                echo json_encode(['status' => 'error', 'message' => 'Błędny PIN operatora (tankowanie zewnętrzne)']);
                exit;
            }

            $isSelfApproved = true;
            $manager = [
                'manager_name' => $operator['first_name'] . ' ' . $operator['last_name'] . ' (operator)',
                'role_level' => 'operator'
            ];
        } else {
            // Tryb WEWNĘTRZNY: wymagany PIN managera z role_level = 5
            $stmt = $pdo->prepare("
                SELECT pin_hash, role_level
                FROM managers
                WHERE role_level = 5
            ");
            $stmt->execute();
            $managers = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($managers as $mgr) {
                if (password_verify($data['manager_pin'], $mgr['pin_hash'])) {
                    $stmtMgr = $pdo->prepare("SELECT first_name, last_name FROM managers WHERE pin_hash = ? AND role_level = 5 LIMIT 1");
                    $stmtMgr->execute([$mgr['pin_hash']]);
                    $mgrDetails = $stmtMgr->fetch(PDO::FETCH_ASSOC);

                    $manager = [
                        'manager_name' => ($mgrDetails ? $mgrDetails['first_name'] . ' ' . $mgrDetails['last_name'] : 'Manager'),
                        'role_level' => $mgr['role_level']
                    ];
                    break;
                }
            }

            if (!$manager) {
                echo json_encode(['status' => 'error', 'message' => 'Błędny PIN - wymagany PIN managera (role_level 5)']);
                exit;
            }
        }

    // Pobierz dane maszyny i SPRAWDŹ CZY ISTNIEJE
    $m = $pdo->prepare("
        SELECT id, machine_name, fuel_norm_l_per_mh, owner
        FROM machines
        WHERE id = ?
    ");
    $m->execute([$machineId]);
    $machineRow = $m->fetch(PDO::FETCH_ASSOC);

    if (!$machineRow) {
        echo json_encode(['status' => 'error', 'message' => 'Nieprawidłowa maszyna - nie istnieje w bazie']);
        exit;
    }

    // SPRAWDŹ CZY MASZYNA NALEŻY DO WŁAŚCICIELA
    // Jeśli nie przysłano ownera lub przysłano 'ALL', użyj właściciela z bazy
    $effectiveOwner = ($rawOwner !== '' && $rawOwner !== 'ALL') ? $rawOwner : ($machineRow['owner'] ?? '');

    if ($effectiveOwner === '') {
        echo json_encode(['status' => 'error', 'message' => 'Brak zdefiniowanej firmy dla maszyny w bazie']);
        exit;
    }

    // Jeśli przysłano konkretną firmę (nie 'ALL'), sprawdź czy się zgadza
    if ($rawOwner !== '' && $rawOwner !== 'ALL') {
        if (strtoupper(str_replace('-', '', $machineRow['owner'])) !== strtoupper(str_replace('-', '', $effectiveOwner))) {
            echo json_encode(['status' => 'error', 'message' => 'Maszyna nie należy do wybranej firmy']);
            exit;
        }
    }

    $machineName = $machineRow['machine_name'];
    $machineFuelNorm = $machineRow['fuel_norm_l_per_mh'];

    /* INSERT DO BAZY */
    try {
        // Określ typ zatwierdzenia i zapisz szczegóły
        if ($isSelfApproved) {
            $approvalNote = 'self-approved';
        } else {
            // Manager zatwierdził - zapisz jego nazwisko
            $approvalNote = 'manager-approved:' . $manager['manager_name'];
        }
        
        $stmt = $pdo->prepare("
            INSERT INTO fuel_logs (
                machine_id, machine_name,
                liters, meter_mh,
                supplier_id, receiver_id,
                notes
            ) VALUES (?,?,?,?,?,?,?)
        ");
        $stmt->execute([
            $machineId,
            $machineName,
            $liters,
            $meterMh,
            $operator['id'],      // operator tankujący (zalogowany)
            null,                 // receiver_id (manager nie jest w tabeli employees)
            $approvalNote         // informacja o typie zatwierdzenia i kto
        ]);
        
        $newFuelId = $pdo->lastInsertId();
    } catch (PDOException $e) {
        echo json_encode(['status' => 'error', 'message' => 'Błąd zapisu do bazy: ' . $e->getMessage()]);
        exit;
    }

    /* AKTUALNE TANKOWANIE */
    $stmt = $pdo->prepare("
        SELECT machine_id, liters, meter_mh
        FROM fuel_logs
        WHERE id = ?
    ");
    $stmt->execute([$newFuelId]);
    $current = $stmt->fetch(PDO::FETCH_ASSOC);

    /* POPRZEDNIE m-h */
    $stmt = $pdo->prepare("
        SELECT meter_mh
        FROM fuel_logs
        WHERE machine_id = ?
          AND id <> ?
        ORDER BY created_at DESC
        LIMIT 1
    ");
    $stmt->execute([$current['machine_id'], $newFuelId]);
    $prevMh = $stmt->fetchColumn();

    /* PIERWSZY WPIS DLA TEJ MASZYNY */
    if ($prevMh === false) {
        $pdo->prepare("
            UPDATE fuel_logs
            SET
                delta_mh = NULL,
                avg_l_per_mh = NULL,
                anomaly_score = 0
            WHERE id = ?
        ")->execute([$newFuelId]);

        echo json_encode([
            'status' => 'ok',
            'message' => '✅ Pierwsze tankowanie zapisane pomyślnie',
            'operator' => $operator['first_name'] . ' ' . $operator['last_name'],
            'manager' => $manager['manager_name'],
            'info' => 'Pierwsze tankowanie dla tej maszyny'
        ]);
        exit;
    }

    /* ===== OBLICZENIA ===== */
    $deltaMh = round($current['meter_mh'] - $prevMh, 2);
    $avg = ($deltaMh > 0)
        ? round($current['liters'] / $deltaMh, 2)
        : null;

    /* ===== WALIDACJA: Brak pracy maszyny ===== */
    if ($deltaMh <= 0) {
        // Usuń wpis jeśli brak pracy
        $pdo->prepare("DELETE FROM fuel_logs WHERE id = ?")->execute([$newFuelId]);
        
        echo json_encode([
            'status' => 'error',
            'message' => '❌ Brak pracy maszyny od ostatniego tankowania',
            'details' => 'Poprzedni stan: ' . $prevMh . ' m-h, Aktualny stan: ' . $current['meter_mh'] . ' m-h. Musisz podać wyższy stan licznika.'
        ]);
        exit;
    }

    /* ===== ROLLING AVG (średnia ruchoma z 3 ostatnich) ===== */
    $rollingAvg = null;
    if ($avg !== null) {
        $stmt = $pdo->prepare("
            SELECT avg_l_per_mh
            FROM fuel_logs
            WHERE machine_id = ?
              AND id <> ?
              AND avg_l_per_mh IS NOT NULL
            ORDER BY created_at DESC
            LIMIT 3
        ");
        $stmt->execute([$current['machine_id'], $newFuelId]);
        $vals = $stmt->fetchAll(PDO::FETCH_COLUMN);

        if (count($vals) === 3) {
            $rollingAvg = round(array_sum($vals) / 3, 2);
        }
    }

    /* ===== ANOMALY SCORE (wykrywanie anomalii) ===== */
    $anomalyScore = 0;

    // Mało godzin pracy
    if ($deltaMh < 2) {
        $anomalyScore += 20;
    }

    // Odchylenie od średniej
    if ($avg !== null && $rollingAvg !== null) {
        $deviation = ($avg / $rollingAvg) * 100;
        if ($deviation > 200) {
            $anomalyScore += 30;
        }
    }

    // Odchylenie od normy maszyny
    if ($machineFuelNorm !== null && $avg !== null) {
        $min = $machineFuelNorm * 0.7;
        $max = $machineFuelNorm * 1.3;
        if ($avg < $min || $avg > $max) {
            $anomalyScore += 20;
        }
    }

    if ($anomalyScore > 100) {
        $anomalyScore = 100;
    }

    $anomalyFlag = ($anomalyScore >= 60) ? 1 : 0;

    /* ===== UPDATE Z OBLICZENIAMI ===== */
    $pdo->prepare("
        UPDATE fuel_logs
        SET
            delta_mh = ?,
            avg_l_per_mh = ?,
            anomaly_score = ?
        WHERE id = ?
    ")->execute([
        $deltaMh,
        $avg,
        $anomalyScore,
        $newFuelId
    ]);

    $message = '✅ Tankowanie zapisane pomyślnie';
    if ($anomalyScore >= 60) {
        $message .= ' ⚠️ Wykryto anomalię (score: ' . $anomalyScore . ')';
    }

    echo json_encode([
        'status' => 'ok',
        'message' => $message,
        'operator' => $operator['first_name'] . ' ' . $operator['last_name'],
        'manager' => $manager['manager_name'],
        'delta_mh' => $deltaMh,
        'avg_l_per_mh' => $avg
    ]);
    exit;
    
    } catch (Exception $e) {
        echo json_encode([
            'status' => 'error',
            'message' => 'Błąd zapisu: ' . $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);
        exit;
    }
} // Koniec if ($action === 'save_fuel')

/* ========================= */
echo json_encode(['status' => 'error', 'message' => 'Nieznana akcja']);
