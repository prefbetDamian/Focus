<?php
/**
 * START pracy - zapis do bazy
 */

require_once __DIR__.'/../../core/session.php';
require_once __DIR__.'/../../core/auth.php';
require_once __DIR__.'/../../core/functions.php';
require_once __DIR__.'/../../core/geo.php';

header('Content-Type: application/json; charset=utf-8');

$user = requireUser();

try {
    $pdo = require __DIR__.'/../../core/db.php';
    
    $data = getJSONInput();

    $siteId   = (int)($data['site_id'] ?? 0);
    $siteName = trim($data['site_name'] ?? '');
    $machineId = isset($data['machine_id']) ? (int)$data['machine_id'] : null;

    // Dane lokalizacyjne z frontu (GPS) – mogą być null
    $lat = isset($data['lat']) ? (float)$data['lat'] : null;
    $lng = isset($data['lng']) ? (float)$data['lng'] : null;

    $ip = getClientIP();
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $locationSource = null;

    if ($lat !== null && $lng !== null) {
        $locationSource = 'GPS';
    } else {
        // Fallback: próba ustalenia przybliżonej lokalizacji po IP
        $geo = getGeoFromIP(getClientIpForGeo());
        $lat = $geo['lat'];
        $lng = $geo['lng'];
        if ($lat !== null && $lng !== null) {
            $locationSource = 'IP';
        }
    }
    
    if (!$siteId || !$siteName) {
        jsonError('Brak danych budowy');
    }

    // Sprawdź czy pracownik nie ma zatwierdzonej lub oczekującej nieobecności w tym dniu
    $stmt = $pdo->prepare("
        SELECT id, type, status
        FROM absence_requests
        WHERE employee_id = ?
          AND status IN ('approved', 'pending')
          AND CURDATE() BETWEEN start_date AND end_date
        LIMIT 1
    ");
    $stmt->execute([$user['id']]);
    $absence = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($absence) {
        $absenceType = $absence['type'] === 'urlop' ? 'urlop' : 
                       ($absence['type'] === 'L4' ? 'zwolnienie lekarskie (L4)' : 'nieobecność');
        $statusText = $absence['status'] === 'approved' ? 'zatwierdzoną' : 'oczekującą';
        jsonError('Nie możesz rozpocząć pracy - masz ' . $statusText . ' nieobecność typu: ' . $absenceType);
    }

    // Limit maks. 4 sesji w jednym dniu dla pracownika
    $stmt = $pdo->prepare("\n        SELECT COUNT(*)\n        FROM work_sessions\n        WHERE employee_id = ?\n          AND DATE(start_time) = CURDATE()\n          AND (absence_group_id IS NULL OR absence_group_id = 0)\n    ");
    $stmt->execute([$user['id']]);
    $todaySessions = (int)$stmt->fetchColumn();

    if ($todaySessions >= 4) {
        jsonError('Osiągnąłeś dzienny limit 4 sesji pracy');
    }
    
    // Sprawdź czy użytkownik nie ma już aktywnej sesji
    $stmt = $pdo->prepare("
        SELECT id FROM work_sessions 
        WHERE employee_id = ? AND end_time IS NULL
    ");
    $stmt->execute([$user['id']]);
    
    if ($stmt->fetch()) {
        jsonError('Masz już aktywną sesję pracy');
    }
    
    // Dla operatora sprawdź czy maszyna nie jest zajęta
    if ($machineId) {
        // Najpierw sprawdź nr ewidencyjny maszyny
        $stmt = $pdo->prepare("SELECT registry_number FROM machines WHERE id = ?");
        $stmt->execute([$machineId]);
        $registryNumber = $stmt->fetchColumn();

        // Maszyna o nr ewidencyjnym 1 nigdy nie jest blokowana jako zajęta
        if ((int)$registryNumber !== 1) {
            $stmt = $pdo->prepare("
                SELECT id FROM work_sessions 
                WHERE machine_id = ? AND end_time IS NULL
            ");
            $stmt->execute([$machineId]);
            
            if ($stmt->fetch()) {
                jsonError('Ta maszyna jest już używana');
            }
        }
    }
    
    $pdo->beginTransaction();

    // START sesji pracy + zapis lokalizacji
		$stmt = $pdo->prepare("
            INSERT INTO work_sessions (
                employee_id,
                first_name,
                last_name,
                site_name,
                site_id,
                start_time,
                machine_id,
                ip,
                device_id,
                lat,
                lng,
                location_source,
                user_agent,
                status
            ) VALUES (?, ?, ?, ?, ?, NOW(), ?, ?, ?, ?, ?, ?, ?, ?)
        ");

	$status = 'OK';

	$stmt->execute([
        $user['id'],
        $user['first_name'],
        $user['last_name'],
        $siteName,
			$siteId,
        $machineId,
        $ip,
        $_SESSION['device_id'] ?? 'web',
        $lat,
        $lng,
        $locationSource,
		$userAgent,
		$status
    ]);
    
    $workSessionId = (int)$pdo->lastInsertId();
    
    // Jeśli operator z maszyną - dodaj sesję maszyny
    if ($machineId) {
        $stmt = $pdo->prepare("
            INSERT INTO machine_sessions (
                machine_id,
                employee_id,
                work_session_id,
                site_name,
                start_time
            ) VALUES (?, ?, ?, ?, NOW())
        ");
        
        $stmt->execute([
            $machineId,
            $user['id'],
            $workSessionId,
            $siteName
        ]);
    }
    
    $pdo->commit();
    
    jsonSuccess('Praca rozpoczęta', [
        'work_session_id' => $workSessionId
    ]);
    
} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    jsonError('Błąd serwera: ' . $e->getMessage(), 500);
}
