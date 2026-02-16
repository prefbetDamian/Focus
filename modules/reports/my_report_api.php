<?php
require_once __DIR__ . '/../../core/session.php';
require_once __DIR__ . '/../../core/auth.php';

header('Content-Type: application/json; charset=utf-8');

$user = requireUser();

try {
    $pdo = require __DIR__ . '/../../core/db.php';

    // Funkcja zaokrąglania do pół godziny (tak jak w raportach PDF)
    function roundToIntervals(int $seconds): int {
        if ($seconds <= 0) return 0;
        $h = floor($seconds / 3600);
        $m = floor(($seconds % 3600) / 60);
        if ($m < 30) return $h * 3600;
        return $h * 3600 + 1800;
    }

    // Formatowanie do pełnych godzin HH:MM
    function formatFullHours(int $seconds): string {
        if ($seconds <= 0) return '00:00';
        $h = floor($seconds / 3600);
        $m = floor(($seconds % 3600) / 60);
        return sprintf('%02d:%02d', $h, $m);
    }

    $rawMonth = isset($_GET['month']) ? trim($_GET['month']) : '';
    if ($rawMonth === '') {
        // domyślnie bieżący miesiąc
        $rawMonth = date('Y-m');
    }

    if (!preg_match('/^([0-9]{4})-([0-9]{2})$/', $rawMonth, $m)) {
        throw new RuntimeException('Nieprawidłowy format miesiąca');
    }

    $year  = (int) $m[1];
    $month = (int) $m[2];

    // Zakres dat: od pierwszego dnia miesiąca do pierwszego dnia następnego miesiąca
    $from = sprintf('%04d-%02d-01 00:00:00', $year, $month);
    // proste wyznaczenie kolejnego miesiąca
    if ($month === 12) {
        $toYear  = $year + 1;
        $toMonth = 1;
    } else {
        $toYear  = $year;
        $toMonth = $month + 1;
    }
    $to = sprintf('%04d-%02d-01 00:00:00', $toYear, $toMonth);

    // Statystyki tylko z zaakceptowanych/rozliczonych sesji (OK, MANUAL)
    // --- PODZIAŁ NA BUDOWY ---
    $sqlSites = "
        SELECT
            ws.site_name,
            COUNT(*)                  AS sessions_count,
            SUM(ws.duration_seconds)  AS total_seconds,
            SEC_TO_TIME(SUM(ws.duration_seconds)) AS total_time
        FROM work_sessions ws
        WHERE ws.employee_id = :emp_id
          AND ws.duration_seconds IS NOT NULL
          AND ws.status IN ('OK','MANUAL')
          AND ws.start_time >= :from
          AND ws.start_time < :to
          AND (ws.absence_group_id IS NULL OR ws.absence_group_id = 0)
        GROUP BY ws.site_name
        ORDER BY ws.site_name
    ";

    $stmt = $pdo->prepare($sqlSites);
    $stmt->execute([
        ':emp_id' => $user['id'],
        ':from'   => $from,
        ':to'     => $to,
    ]);
    $sitesRaw = $stmt->fetchAll();

    // Zaokrąglij czas dla każdej budowy
    $sites = [];
    foreach ($sitesRaw as $row) {
        $rawSec = (int)($row['total_seconds'] ?? 0);
        $roundedSec = roundToIntervals($rawSec);
        $sites[] = [
            'site_name' => $row['site_name'],
            'sessions_count' => $row['sessions_count'],
            'total_seconds' => $roundedSec,
            'total_time' => formatFullHours($roundedSec),
        ];
    }

    // Suma miesięczna po wszystkich budowach (z zaokrągleniem do interwałów pół godziny)
    $overallSecondsRaw = 0;
    foreach ($sites as $row) {
        $overallSecondsRaw += (int) ($row['total_seconds'] ?? 0);
    }

    $overallSeconds = roundToIntervals($overallSecondsRaw);

    $overallTime = formatFullHours($overallSeconds);

    // --- PODZIAŁ NA DNI (do wykresu / kalendarza) ---
    // Dni pracy (bez urlopu)
    $sqlDays = "
        SELECT
            DATE(ws.start_time)       AS work_date,
            SUM(ws.duration_seconds)  AS total_seconds,
            'work' AS day_type
        FROM work_sessions ws
        WHERE ws.employee_id = :emp_id
          AND ws.duration_seconds IS NOT NULL
          AND ws.status IN ('OK','MANUAL')
          AND ws.start_time >= :from
          AND ws.start_time < :to
          AND (ws.absence_group_id IS NULL OR ws.absence_group_id = 0)
        GROUP BY DATE(ws.start_time)
    ";

    $stmt = $pdo->prepare($sqlDays);
    $stmt->execute([
        ':emp_id' => $user['id'],
        ':from'   => $from,
        ':to'     => $to,
    ]);
    $daysRows = $stmt->fetchAll();

    // Dni urlopowe
    $sqlAbsenceDays = "
        SELECT DISTINCT
            DATE(ws.start_time) AS work_date,
            ws.site_name AS absence_type,
            'absence' AS day_type
        FROM work_sessions ws
        WHERE ws.employee_id = :emp_id
          AND ws.start_time >= :from
          AND ws.start_time < :to
          AND ws.absence_group_id IS NOT NULL
          AND ws.absence_group_id > 0
    ";

    $stmt = $pdo->prepare($sqlAbsenceDays);
    $stmt->execute([
        ':emp_id' => $user['id'],
        ':from'   => $from,
        ':to'     => $to,
    ]);
    $absenceDaysRows = $stmt->fetchAll();

    $days = [];
    $workDaysCount = 0;
    $longestDaySeconds = 0;
    foreach ($daysRows as $row) {
        $sec = (int)($row['total_seconds'] ?? 0);
        if ($sec <= 0) {
            continue;
        }
        // Zaokrąglamy dzienną sumę do interwałów 15 minut
        $roundedSec = roundToIntervals($sec);
        $hours = $roundedSec / 3600;
        $days[] = [
            'date'  => $row['work_date'],
            'hours' => round($hours, 2),
        ];
        $workDaysCount++;
        if ($sec > $longestDaySeconds) {
            $longestDaySeconds = $sec;
        }
    }

    // --- STATUSY ---
    $sqlStatuses = "
        SELECT
            ws.status,
            SUM(ws.duration_seconds) AS total_seconds
        FROM work_sessions ws
        WHERE ws.employee_id = :emp_id
          AND ws.duration_seconds IS NOT NULL
          AND ws.start_time >= :from
          AND ws.start_time < :to
          AND (ws.absence_group_id IS NULL OR ws.absence_group_id = 0)
        GROUP BY ws.status
    ";

    $stmt = $pdo->prepare($sqlStatuses);
    $stmt->execute([
        ':emp_id' => $user['id'],
        ':from'   => $from,
        ':to'     => $to,
    ]);
    $statusRows = $stmt->fetchAll();

    $statuses = [];
    foreach ($statusRows as $row) {
        $st  = (string)($row['status'] ?? '');
        $sec = (int)($row['total_seconds'] ?? 0);
        $statuses[$st] = [
            'seconds' => $sec,
            'hours'   => round($sec / 3600, 2),
        ];
    }

    // --- PODSUMOWANIE ---
    // liczba sesji i najdłuższa pojedyncza sesja
    $sqlSummary = "
        SELECT
            COUNT(*)               AS sessions,
            MAX(duration_seconds)  AS longest_session
        FROM work_sessions ws
        WHERE ws.employee_id = :emp_id
          AND ws.duration_seconds IS NOT NULL
          AND ws.status IN ('OK','MANUAL')
          AND ws.start_time >= :from
          AND ws.start_time < :to
          AND (ws.absence_group_id IS NULL OR ws.absence_group_id = 0)
    ";

    $stmt = $pdo->prepare($sqlSummary);
    $stmt->execute([
        ':emp_id' => $user['id'],
        ':from'   => $from,
        ':to'     => $to,
    ]);
    $summaryRow = $stmt->fetch() ?: ['sessions' => 0, 'longest_session' => 0];

    $sessionsCount      = (int)($summaryRow['sessions'] ?? 0);
    $longestSessionSecs = (int)($summaryRow['longest_session'] ?? 0);

    // --- DNI URLOPOWE ---
    $sqlAbsence = "
        SELECT COUNT(DISTINCT DATE(ws.start_time)) AS absence_days
        FROM work_sessions ws
        WHERE ws.employee_id = :emp_id
          AND ws.start_time >= :from
          AND ws.start_time < :to
          AND ws.absence_group_id IS NOT NULL
          AND ws.absence_group_id > 0
    ";
    
    $stmt = $pdo->prepare($sqlAbsence);
    $stmt->execute([
        ':emp_id' => $user['id'],
        ':from'   => $from,
        ':to'     => $to,
    ]);
    $absenceRow = $stmt->fetch() ?: ['absence_days' => 0];
    $absenceDays = (int)($absenceRow['absence_days'] ?? 0);

    // --- WNIOSKI URLOPOWE (z tabeli absence_requests) ---
    $absenceRequests = [];
    try {
        $sqlAbsenceRequests = "
            SELECT 
                ar.id,
                ar.start_date,
                ar.end_date,
                ar.type,
                ar.status,
                ar.reason
            FROM absence_requests ar
            WHERE ar.employee_id = :emp_id
              AND (
                (ar.start_date >= :from_date AND ar.start_date < :to_date)
                OR (ar.end_date >= :from_date AND ar.end_date < :to_date)
                OR (ar.start_date <= :from_date AND ar.end_date >= :to_date)
              )
            ORDER BY ar.start_date
        ";
        
        $fromDate = sprintf('%04d-%02d-01', $year, $month);
        $toDate = sprintf('%04d-%02d-01', $toYear, $toMonth);
        
        $stmt = $pdo->prepare($sqlAbsenceRequests);
        $stmt->execute([
            ':emp_id' => $user['id'],
            ':from_date' => $fromDate,
            ':to_date' => $toDate,
        ]);
        $absenceRequests = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        // Jeśli tabela absence_requests nie istnieje lub jest błąd, kontynuuj bez wniosków
        error_log('Error fetching absence_requests: ' . $e->getMessage());
    }

    $avgDailySeconds = ($workDaysCount > 0) ? (int)floor($overallSeconds / $workDaysCount) : 0;
    $avgDailyRounded = roundToIntervals($avgDailySeconds);
    $longestDayRounded = roundToIntervals($longestDaySeconds);
    $longestSessionRounded = roundToIntervals($longestSessionSecs);

    $summary = [
        'total_time'       => $overallTime,
        'total_seconds'    => $overallSeconds,
        'work_days'        => $workDaysCount,
        'absence_days'     => $absenceDays,
        'sessions'         => $sessionsCount,
        'avg_daily_time'   => formatFullHours($avgDailyRounded),
        'longest_day_time' => formatFullHours($longestDayRounded),
        'longest_session'  => formatFullHours($longestSessionRounded),
    ];

    echo json_encode([
        'success' => true,
        'month'   => $rawMonth,
        'year'    => $year,
        'monthNum'=> $month,
        // nowa, bogatsza struktura
        'summary' => $summary,
        'days'    => $days,
        'sites'   => $sites,
        'statuses'=> $statuses,
        'absence_requests' => $absenceRequests,
        // stare pola dla wstecznej kompatybilności z widokiem
        'stats'   => $sites,
        'overall_seconds' => $overallSeconds,
        'overall_time'    => $overallTime,
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    error_log('my_report_api.php Error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => $e->getTraceAsString(),
    ]);
}
