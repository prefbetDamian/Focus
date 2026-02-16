<?php
/**
 * Ręczne zapisywanie tankowania przez kierownika (panel)
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../core/auth.php';

// Tylko zalogowany menedżer z poziomem >= 2 (kierownik i wyżej)
$manager = requireManager(2);

try {
    $pdo = require __DIR__ . '/../core/db.php';
} catch (Throwable $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Błąd połączenia z bazą: ' . $e->getMessage(),
    ]);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true) ?? [];
$machineId = isset($data['machine_id']) ? (int)$data['machine_id'] : 0;
$liters    = isset($data['liters']) ? (float)$data['liters'] : 0.0;
$meterMh   = isset($data['meter_mh']) ? (float)$data['meter_mh'] : 0.0;

if ($machineId <= 0) {
    echo json_encode([
        'success' => false,
        'message' => 'Wybierz maszynę z listy',
    ]);
    exit;
}

if ($liters <= 0) {
    echo json_encode([
        'success' => false,
        'message' => 'Podaj poprawną ilość litrów (wartość dodatnia)',
    ]);
    exit;
}

if ($meterMh <= 0) {
    echo json_encode([
        'success' => false,
        'message' => 'Podaj poprawny stan licznika m-h / przebieg (wartość dodatnia)',
    ]);
    exit;
}

// Pobierz dane maszyny
$stmt = $pdo->prepare('
    SELECT id, machine_name, fuel_norm_l_per_mh
    FROM machines
    WHERE id = ?
');
$stmt->execute([$machineId]);
$machineRow = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$machineRow) {
    echo json_encode([
        'success' => false,
        'message' => 'Nieprawidłowa maszyna - nie istnieje w bazie',
    ]);
    exit;
}

$machineName     = $machineRow['machine_name'];
$machineFuelNorm = $machineRow['fuel_norm_l_per_mh'];

// Wstaw podstawowy wpis do fuel_logs
$approvalNote = sprintf(
    'manual by manager %s (role %d)',
    $manager['name'],
    (int)$manager['role_level']
);

try {
    $stmt = $pdo->prepare('
        INSERT INTO fuel_logs (
            machine_id, machine_name,
            liters, meter_mh,
            supplier_id, receiver_id,
            notes
        ) VALUES (?,?,?,?,?,?,?)
    ');
    $stmt->execute([
        $machineId,
        $machineName,
        $liters,
        $meterMh,
        null,      // supplier_id (brak konkretnego pracownika)
        null,      // receiver_id
        $approvalNote
    ]);

    $newFuelId = $pdo->lastInsertId();
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Błąd zapisu do bazy: ' . $e->getMessage(),
    ]);
    exit;
}

// Pobierz właśnie dodane tankowanie
$stmt = $pdo->prepare('
    SELECT machine_id, liters, meter_mh
    FROM fuel_logs
    WHERE id = ?
');
$stmt->execute([$newFuelId]);
$current = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$current) {
    echo json_encode([
        'success' => false,
        'message' => 'Błąd odczytu zapisanego tankowania',
    ]);
    exit;
}

// Poprzedni stan licznika dla tej maszyny
$stmt = $pdo->prepare('
    SELECT meter_mh
    FROM fuel_logs
    WHERE machine_id = ?
      AND id <> ?
    ORDER BY created_at DESC
    LIMIT 1
');
$stmt->execute([$current['machine_id'], $newFuelId]);
$prevMh = $stmt->fetchColumn();

// Pierwsze tankowanie dla tej maszyny
if ($prevMh === false) {
    $pdo->prepare('
        UPDATE fuel_logs
        SET
            delta_mh = NULL,
            avg_l_per_mh = NULL,
            anomaly_score = 0
        WHERE id = ?
    ')->execute([$newFuelId]);

    echo json_encode([
        'success' => true,
        'message' => '✅ Pierwsze tankowanie zapisane pomyślnie (ręcznie)',
        'delta_mh' => null,
        'avg_l_per_mh' => null,
    ]);
    exit;
}

// Obliczenia
$deltaMh = round($current['meter_mh'] - $prevMh, 2);
$avg     = ($deltaMh > 0)
    ? round($current['liters'] / $deltaMh, 2)
    : null;

// Jeśli brak pracy maszyny – usuń wpis
if ($deltaMh <= 0) {
    $pdo->prepare('DELETE FROM fuel_logs WHERE id = ?')->execute([$newFuelId]);

    echo json_encode([
        'success' => false,
        'message' => '❌ Brak pracy maszyny od ostatniego tankowania. Musisz podać wyższy stan licznika.',
    ]);
    exit;
}

// Rolling average z 3 ostatnich wpisów
$rollingAvg = null;
if ($avg !== null) {
    $stmt = $pdo->prepare('
        SELECT avg_l_per_mh
        FROM fuel_logs
        WHERE machine_id = ?
          AND id <> ?
          AND avg_l_per_mh IS NOT NULL
        ORDER BY created_at DESC
        LIMIT 3
    ');
    $stmt->execute([$current['machine_id'], $newFuelId]);
    $vals = $stmt->fetchAll(PDO::FETCH_COLUMN);

    if (count($vals) === 3) {
        $rollingAvg = round(array_sum($vals) / 3, 2);
    }
}

// Anomaly score – podobnie jak w fuel_api
$anomalyScore = 0;

// Mało godzin pracy
if ($deltaMh < 2) {
    $anomalyScore += 20;
}

// Odchylenie od średniej ruchomej
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

// Zapis obliczeń
$pdo->prepare('
    UPDATE fuel_logs
    SET
        delta_mh = ?,
        avg_l_per_mh = ?,
        anomaly_score = ?
    WHERE id = ?
')->execute([
    $deltaMh,
    $avg,
    $anomalyScore,
    $newFuelId
]);

$message = '✅ Tankowanie zapisane pomyślnie (ręcznie)';
if ($anomalyScore >= 60) {
    $message .= ' ⚠️ Wykryto anomalię (score: ' . $anomalyScore . ')';
}

echo json_encode([
    'success' => true,
    'message' => $message,
    'delta_mh' => $deltaMh,
    'avg_l_per_mh' => $avg,
]);
