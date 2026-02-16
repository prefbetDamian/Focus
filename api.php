<?php
declare(strict_types=1);

// Sesja dla nowego systemu modułowego
require_once __DIR__ . '/core/session.php';
// Uruchom wewnętrzny scheduler (nieblokujące sprawdzenie zadań)
require_once __DIR__ . '/core/scheduler.php';
register_shutdown_function(function() {
    try {
        runScheduler();
    } catch (Throwable $e) {
        // Ignoruj błędy schedulera aby nie wpływały na główne API
    }
});
header("Content-Type: application/json; charset=utf-8");

/* ================= CONFIG ================= */
$config = require __DIR__.'/config.php';
$MAX_ATTEMPTS = 3;
$BLOCK_HOURS  = 8;

/* ================= INPUT ================= */
$data = json_decode(file_get_contents("php://input"), true) ?? [];
usleep(random_int(300000,700000));

$action = $data['action'] ?? null;

// Odczyt statusu subskrypcji PUSH (pracownik) – do ekranu ustawień
if ($action === 'get_push_status') {
    if (!isset($_SESSION['user_id'])) {
        echo json_encode(["status" => "error", "message" => "Brak autoryzacji"]);
        exit;
    }

    $employeeId = (int)($_SESSION['user_id'] ?? 0);
    if ($employeeId <= 0) {
        echo json_encode(["status" => "error", "message" => "Nieprawidłowy pracownik"]);
        exit;
    }

    try {
        $pdo = new PDO(
            "mysql:host={$config['db_host']};port={$config['db_port']};dbname={$config['db_name']};charset=utf8mb4",
            $config['db_user'],
            $config['db_pass'],
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );

        $stmt = $pdo->prepare("SELECT COUNT(*) FROM push_subscriptions WHERE employee_id = ?");
        $stmt->execute([$employeeId]);
        $count = (int)$stmt->fetchColumn();

        echo json_encode([
            "status" => "ok",
            "enabled" => $count > 0,
        ]);
    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode(["status" => "error", "message" => "Błąd odczytu statusu PUSH"]);
    }
    exit;
}

// Specjalna akcja: zapis subskrypcji PUSH (pracownik)
if ($action === 'subscribe_push') {
    if (!isset($_SESSION['user_id'])) {
        echo json_encode(["status" => "error", "message" => "Brak autoryzacji"]);
        exit;
    }

    $employeeId = (int)($data['employee_id'] ?? 0);
    if ($employeeId <= 0 || $employeeId !== (int)$_SESSION['user_id']) {
        echo json_encode(["status" => "error", "message" => "Nieprawidłowy pracownik"]);
        exit;
    }

    $sub = $data['subscription'] ?? null;
    if (!is_array($sub) || empty($sub['endpoint']) || empty($sub['keys']['p256dh']) || empty($sub['keys']['auth'])) {
        echo json_encode(["status" => "error", "message" => "Nieprawidłowa subskrypcja"]);
        exit;
    }

    try {
        $pdo = new PDO(
            "mysql:host={$config['db_host']};port={$config['db_port']};dbname={$config['db_name']};charset=utf8mb4",
            $config['db_user'],
            $config['db_pass'],
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );

        $stmt = $pdo->prepare("
            INSERT INTO push_subscriptions (employee_id, endpoint, p256dh, auth)
            VALUES (?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                endpoint = VALUES(endpoint),
                p256dh  = VALUES(p256dh),
                auth    = VALUES(auth)
        ");

        $stmt->execute([
            $employeeId,
            $sub['endpoint'],
            $sub['keys']['p256dh'],
            $sub['keys']['auth']
        ]);

        echo json_encode(["status" => "ok"]);
    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode(["status" => "error", "message" => "Błąd zapisu subskrypcji"]);
    }
    exit;
}

// Specjalna akcja: wyłączenie subskrypcji PUSH (pracownik)
if ($action === 'unsubscribe_push') {
    if (!isset($_SESSION['user_id'])) {
        echo json_encode(["status" => "error", "message" => "Brak autoryzacji"]);
        exit;
    }

    $employeeId = (int)($_SESSION['user_id'] ?? 0);
    if ($employeeId <= 0) {
        echo json_encode(["status" => "error", "message" => "Nieprawidłowy pracownik"]);
        exit;
    }

    try {
        $pdo = new PDO(
            "mysql:host={$config['db_host']};port={$config['db_port']};dbname={$config['db_name']};charset=utf8mb4",
            $config['db_user'],
            $config['db_pass'],
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );

        $stmt = $pdo->prepare("DELETE FROM push_subscriptions WHERE employee_id = ?");
        $stmt->execute([$employeeId]);

        echo json_encode(["status" => "ok"]);
    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode(["status" => "error", "message" => "Błąd usuwania subskrypcji"]);
    }
    exit;
}

// Ustawienia: odczyt listy kierowników (role_level = 2) i aktualnie przypisanego kierownika pracownika
if ($action === 'get_employee_manager_settings') {
    if (!isset($_SESSION['user_id'])) {
        echo json_encode(["status" => "error", "message" => "Brak autoryzacji"]);
        exit;
    }

    $employeeId = (int)($_SESSION['user_id'] ?? 0);
    if ($employeeId <= 0) {
        echo json_encode(["status" => "error", "message" => "Nieprawidłowy pracownik"]);
        exit;
    }

    try {
        $pdo = new PDO(
            "mysql:host={$config['db_host']};port={$config['db_port']};dbname={$config['db_name']};charset=utf8mb4",
            $config['db_user'],
            $config['db_pass'],
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );

        // Lista wszystkich kierowników, którzy mogą być przypisani (can_be_manager = TRUE)
        $stmtManagers = $pdo->prepare("SELECT id, first_name, last_name, role_level FROM managers WHERE can_be_manager = TRUE ORDER BY first_name, last_name");
        $stmtManagers->execute();
        $managers = $stmtManagers->fetchAll(PDO::FETCH_ASSOC);

        // Aktualnie przypisany kierownik (kolumna manager_id w employees)
        $stmtEmp = $pdo->prepare("SELECT manager_id FROM employees WHERE id = ?");
        $stmtEmp->execute([$employeeId]);
        $currentManagerId = $stmtEmp->fetchColumn();

        echo json_encode([
            "status" => "ok",
            "managers" => $managers,
            "selected_manager_id" => $currentManagerId ? (int)$currentManagerId : null,
        ]);
    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode([
            "status" => "error",
            "message" => "Błąd odczytu listy kierowników: " . $e->getMessage()
        ]);
    }
    exit;
}

// Ustawienia: zapis wybranego kierownika dla zalogowanego pracownika
if ($action === 'save_employee_manager') {
    if (!isset($_SESSION['user_id'])) {
        echo json_encode(["status" => "error", "message" => "Brak autoryzacji"]);
        exit;
    }

    $employeeId = (int)($_SESSION['user_id'] ?? 0);
    if ($employeeId <= 0) {
        echo json_encode(["status" => "error", "message" => "Nieprawidłowy pracownik"]);
        exit;
    }

    $managerId = isset($data['manager_id']) ? (int)$data['manager_id'] : 0;

    try {
        $pdo = new PDO(
            "mysql:host={$config['db_host']};port={$config['db_port']};dbname={$config['db_name']};charset=utf8mb4",
            $config['db_user'],
            $config['db_pass'],
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );

        if ($managerId > 0) {
            // Walidacja: czy istnieje kierownik z can_be_manager = TRUE
            $stmtCheck = $pdo->prepare("SELECT id FROM managers WHERE id = ? AND can_be_manager = TRUE");
            $stmtCheck->execute([$managerId]);
            if (!$stmtCheck->fetchColumn()) {
                echo json_encode(["status" => "error", "message" => "Wybrany kierownik nie istnieje lub nie może być przypisany"]);
                exit;
            }

            $stmt = $pdo->prepare("UPDATE employees SET manager_id = ? WHERE id = ?");
            $stmt->execute([$managerId, $employeeId]);
        } else {
            // Wyczyszczenie przypisania kierownika (opcjonalne)
            $stmt = $pdo->prepare("UPDATE employees SET manager_id = NULL WHERE id = ?");
            $stmt->execute([$employeeId]);
        }

        echo json_encode(["status" => "ok"]);
    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode(["status" => "error", "message" => "Błąd zapisu kierownika"]);
    }
    exit;
}

// Specjalna akcja: zapis subskrypcji PUSH (kierownik)
if ($action === 'subscribe_push_manager') {
    if (!isset($_SESSION['manager_id'])) {
        echo json_encode(["status" => "error", "message" => "Brak autoryzacji"]);
        exit;
    }

    $managerId = (int)($_SESSION['manager_id'] ?? 0);
    if ($managerId <= 0) {
        echo json_encode(["status" => "error", "message" => "Nieprawidłowy kierownik"]);
        exit;
    }

    $sub = $data['subscription'] ?? null;
    if (!is_array($sub) || empty($sub['endpoint']) || empty($sub['keys']['p256dh']) || empty($sub['keys']['auth'])) {
        echo json_encode(["status" => "error", "message" => "Nieprawidłowa subskrypcja"]);
        exit;
    }

    try {
        $pdo = new PDO(
            "mysql:host={$config['db_host']};dbname={$config['db_name']};charset=utf8mb4",
            $config['db_user'],
            $config['db_pass'],
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );

        $stmt = $pdo->prepare("\n            INSERT INTO push_subscriptions (manager_id, endpoint, p256dh, auth)\n            VALUES (?, ?, ?, ?)\n            ON DUPLICATE KEY UPDATE\n                endpoint = VALUES(endpoint),\n                p256dh  = VALUES(p256dh),\n                auth    = VALUES(auth)\n        ");

        $stmt->execute([
            $managerId,
            $sub['endpoint'],
            $sub['keys']['p256dh'],
            $sub['keys']['auth']
        ]);

        echo json_encode(["status" => "ok"]);
    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode(["status" => "error", "message" => "Błąd zapisu subskrypcji kierownika"]);
    }
    exit;
}

$isDeviceStop = isset($data['force_stop']) && $data['force_stop'] === true;

$first      = trim($data['firstName'] ?? '');
$last       = trim($data['lastName'] ?? '');
$pin        = trim($data['pin'] ?? '');
$site       = trim($data['siteName'] ?? '');
$machineId  = $data['machineId'] ?? null;
$deviceId   = trim($data['device_id'] ?? '');
$contextKey = $data['context'] ?? 'rcp';

$honeypot = trim($data['honeypot'] ?? '');
if (!empty($honeypot)) {
    // Bot detected
    echo json_encode(["status"=>"error","message"=>"Nieprawidłowe dane logowania"]);
    exit;
}
if (!$deviceId) {
    echo json_encode(["status"=>"error","message"=>"Brak danych"]);
    exit;
}

// Jeśli mamy tylko PIN (bez imienia/nazwiska), to może to być kierownik
$isManagerLogin = empty($first) && empty($last) && !empty($pin);

if (!$isDeviceStop && !$isManagerLogin && (!$first || !$last || !$pin)) {
    echo json_encode(["status"=>"error","message"=>"Brak danych logowania"]);
    exit;
}

/* ================= DB ================= */
$pdo = new PDO(
    "mysql:host={$config['db_host']};dbname={$config['db_name']};charset=utf8mb4",
    $config['db_user'],
    $config['db_pass'],
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);
/* ================= LOGICZNE ZAMKNIĘCIE DNIA (24:00) ================= */
require_once __DIR__ . '/day_closure.php';
closeWorkDay($pdo);

/* ================= FORCE STOP (DEVICE) ================= */
if ($isDeviceStop) {

    $stmt = $pdo->prepare("
        SELECT id, start_time
        FROM work_sessions
        WHERE device_id = ?
          AND end_time IS NULL
        LIMIT 1
    ");
    $stmt->execute([$deviceId]);
    $active = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$active) {
        echo json_encode([
            "status"=>"error",
            "message"=>"Brak aktywnej sesji"
        ]);
        exit;
    }

    $duration = time() - strtotime($active['start_time']);

    /* STOP OPERATORA */
    $pdo->prepare("
        UPDATE work_sessions
        SET end_time = NOW(),
            duration_seconds = ?
        WHERE id = ?
    ")->execute([
        $duration,
        $active['id']
    ]);

    /* STOP SESJI MASZYNY – ZAWSZE PO work_session_id */
    $pdo->prepare("
        UPDATE machine_sessions
        SET end_time = NOW(),
            duration_seconds = ?
        WHERE work_session_id = ?
          AND end_time IS NULL
    ")->execute([
        $duration,
        $active['id']
    ]);

    echo json_encode(["status"=>"stop"]);
    exit;
}

/* ================= IDENTYFIKACJA PO IMIĘ + NAZWISKO ================= */
// sprawdź imię i nazwisko razem, aby nie ujawniać które istnieje
$stmt = $pdo->prepare("
    SELECT id, is_operator, pin_hash, first_name, last_name
    FROM employees
    WHERE BINARY first_name = ?
      AND BINARY last_name  = ?
    LIMIT 1
");
$stmt->execute([$first, $last]);
$emp = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$emp) {
    echo json_encode(["status"=>"error","message"=>"Nieprawidłowe dane logowania"]);
    exit;
}

/* ================= DOPIERO TERAZ SPRAWDZAJ PIN ================= */
if (
    !$emp ||
    empty($emp['pin_hash']) ||
    !password_verify($pin, $emp['pin_hash'])
) {



    // pobierz próby (jeśli w ogóle istnieją)
    $stmt = $pdo->prepare("
        SELECT attempts
        FROM login_attempts
        WHERE employee_id = ? AND context = ?
    ");
    $stmt->execute([$emp['id'] ?? null, $contextKey]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    $attempts = ($row['attempts'] ?? 0) + 1;

    if ($attempts >= $MAX_ATTEMPTS && $emp) {

        $pdo->prepare("
            INSERT INTO login_attempts
                (employee_id, context, attempts, blocked_until, last_attempt)
            VALUES
                (?, ?, ?, DATE_ADD(NOW(), INTERVAL {$BLOCK_HOURS} HOUR), NOW())
            ON DUPLICATE KEY UPDATE
                attempts = ?,
                blocked_until = DATE_ADD(NOW(), INTERVAL {$BLOCK_HOURS} HOUR),
                last_attempt = NOW()
        ")->execute([
            $emp['id'],
            $contextKey,
            $attempts,
            $attempts
        ]);

        echo json_encode([
            "status"=>"blocked",
            "message"=>"Pracownik zablokowany na {$BLOCK_HOURS} godzin"
        ]);
        exit;
    }

    $pdo->prepare("
        INSERT INTO login_attempts
            (employee_id, context, attempts, last_attempt)
        VALUES
            (?, ?, ?, NOW())
        ON DUPLICATE KEY UPDATE
            attempts = ?,
            last_attempt = NOW()
    ")->execute([
        $emp['id'] ?? null,
        $contextKey,
        $attempts,
        $attempts
    ]);

    echo json_encode([
        "status"=>"error",
        "message"=>"Błędne dane logowania",
        "attempts_left"=>max(0, $MAX_ATTEMPTS - $attempts)
    ]);
    exit;
}

/* ================= CLEAR BLOKADY ================= */
$pdo->prepare("
    DELETE FROM login_attempts
    WHERE employee_id = ? AND context = ?
")->execute([$emp['id'], $contextKey]);

/* ================= SESJA DLA NOWEGO PANELU ================= */
$_SESSION['user_id'] = $emp['id'];
$_SESSION['first_name'] = $emp['first_name'];
$_SESSION['last_name'] = $emp['last_name'];
$_SESSION['is_operator'] = $emp['is_operator'];

$isOperator = (int)$emp['is_operator'];


/* ================= BLOKADA MULTI-DEVICE ================= */
$stmt = $pdo->prepare("
    SELECT id, device_id
    FROM work_sessions
    WHERE employee_id = ?
      AND end_time IS NULL
    LIMIT 1
");
$stmt->execute([$emp['id']]);
$empActiveSession = $stmt->fetch(PDO::FETCH_ASSOC);

if ($empActiveSession && $empActiveSession['device_id'] !== $deviceId) {
    echo json_encode([
        "status"  => "blocked",
        "message" => "Jesteś już zalogowany na innym urządzeniu"
    ]);
    exit;
}


/* ================= ABSENCE CHECK (work_sessions) ================= */

$todayStart = date('Y-m-d 00:00:00');
$todayEnd   = date('Y-m-d 23:59:59');

$stmt = $pdo->prepare("
    SELECT id, site_name
    FROM work_sessions
    WHERE employee_id = ?
      AND site_name IN ('URLOP','L4')
      AND (
            start_time BETWEEN ? AND ?
         OR end_time   BETWEEN ? AND ?
         OR (start_time <= ? AND end_time >= ?)
      )
    LIMIT 1
");

$stmt->execute([
    $emp['id'],
    $todayStart,
    $todayEnd,
    $todayStart,
    $todayEnd,
    $todayStart,
    $todayEnd
]);

$absence = $stmt->fetch(PDO::FETCH_ASSOC);

if ($absence) {
    echo json_encode([
        "status"  => "absence",
        "message" => "DZIŚ MASZ {$absence['site_name']} – LOGOWANIE ZABLOKOWANE"
    ]);
    exit;
}


/* ================= DEVICE ACTIVE INFO ================= */
$stmt = $pdo->prepare("
    SELECT id, site_name, machine_id
    FROM work_sessions
    WHERE device_id = ?
      AND end_time IS NULL
    LIMIT 1
");
$stmt->execute([$deviceId]);
$activeSession = $stmt->fetch(PDO::FETCH_ASSOC);

/* ===== BLOKADA PO GODZINACH ===== */
if (date('H:i:s') >= '16:00:00') {
    echo json_encode([
        "status"  => "after_hours",
        "message" => "Brak możliwości rozpoczęcia pracy"
    ]);
    exit;
}

/* ================= WYBÓR BUDOWY ================= */
if ($site === '') {
    echo json_encode([
        "status"=>"need_selection",
        "is_operator"=>$isOperator,
        "device_active"=>(bool)$activeSession,
        "active_session"=>$activeSession ?: null
    ]);
    exit;
}


/* ================= LIMIT SESJI DZIENNIE ================= */

// Maksymalnie 4 sesje pracy w jednym dniu dla pracownika
$stmt = $pdo->prepare("\n    SELECT COUNT(*)\n    FROM work_sessions\n    WHERE employee_id = ?\n      AND DATE(start_time) = CURDATE()\n      AND (absence_group_id IS NULL OR absence_group_id = 0)\n");
$stmt->execute([$emp['id']]);
$todaySessions = (int)$stmt->fetchColumn();

if ($todaySessions >= 4) {
    echo json_encode([
        "status"  => "limit_reached",
        "message" => "Osiągnąłeś dzienny limit 4 sesji pracy"
    ]);
    exit;
}


/* ================= START ================= */
try {

    $pdo->beginTransaction();

    /* START OPERATORA */
    $pdo->prepare("
    INSERT INTO work_sessions
    (first_name, last_name, site_name, start_time, ip, device_id, employee_id, machine_id)
    VALUES (?, ?, ?, NOW(), ?, ?, ?, ?)
")->execute([
    $emp['first_name'],   // z bazy
    $emp['last_name'],    // z bazy
    $site,
    $ip,
    $deviceId,
    $emp['id'],
    $machineId
]);


    $workSessionId = (int)$pdo->lastInsertId();

    /* START SESJI MASZYNY – TYLKO RAZEM Z OPERATOREM */
    if ($machineId !== null) {
        $pdo->prepare("
            INSERT INTO machine_sessions (
                machine_id,
                employee_id,
                work_session_id,
                site_name,
                start_time
            ) VALUES (
                ?, ?, ?, ?, NOW()
            )
        ")->execute([
            $machineId,
            $emp['id'],
            $workSessionId,
            $site
        ]);
    }

    $pdo->commit();

    echo json_encode(["status" => "start"]);
    exit;

} catch (Throwable $e) {

    $pdo->rollBack();

    echo json_encode([
        "status"  => "error",
        "message" => "Nie udało się rozpocząć pracy"
    ]);
    exit;
}

