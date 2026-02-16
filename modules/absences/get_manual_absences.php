<?php
/**
 * Zwraca ręcznie dodane nieobecności (URLOP / L4) dla zalogowanego pracownika
 * na podstawie rekordów w work_sessions (absence_group_id).
 */

require_once __DIR__ . '/../../core/session.php';
require_once __DIR__ . '/../../core/auth.php';

header('Content-Type: application/json; charset=utf-8');

try {
    $user = requireUser();
    $employeeId = (int)$user['id'];

    $pdo = require __DIR__ . '/../../core/db.php';

    // Pobierz ręcznie dodane nieobecności (które NIE mają powiązanego wniosku w absence_requests)
    $stmt = $pdo->prepare("
        SELECT
            ws.absence_group_id,
            MIN(ws.start_time) AS start_date,
            MAX(ws.end_time)   AS end_date,
            ws.site_name       AS type,
            ws.manager_comment
        FROM work_sessions ws
        WHERE ws.employee_id = ?
          AND ws.absence_group_id IS NOT NULL
          AND ws.site_name IN ('URLOP','L4')
          AND NOT EXISTS (
              SELECT 1 FROM absence_requests ar
              WHERE ar.employee_id = ws.employee_id
                AND ar.status = 'approved'
                AND DATE(ws.start_time) >= ar.start_date
                AND DATE(ws.start_time) <= ar.end_date
          )
        GROUP BY ws.absence_group_id, ws.site_name
        ORDER BY start_date DESC
    ");
    $stmt->execute([$employeeId]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $absences = [];

    foreach ($rows as $row) {
        $groupId = (int)($row['absence_group_id'] ?? 0);
        $rawType = strtoupper((string)($row['type'] ?? ''));

        // Normalizacja typu do tego, co widzi frontend (urlop / L4 / inny)
        if ($rawType === 'URLOP') {
            $type = 'urlop';
        } elseif ($rawType === 'L4') {
            $type = 'L4';
        } else {
            $type = 'inny';
        }

        $startDateTime = $row['start_date'] ?? null;
        $endDateTime   = $row['end_date'] ?? null;

        // Wyciągnij same daty (YYYY-MM-DD)
        $startDate = $startDateTime ? substr($startDateTime, 0, 10) : null;
        $endDate   = $endDateTime ? substr($endDateTime, 0, 10) : null;

        $absences[] = [
            'id'           => 'm_' . $groupId,
            'start_date'   => $startDate,
            'end_date'     => $endDate,
            'type'         => $type,
            'reason'       => null,
            'status'       => 'approved',
            'requested_at' => $startDateTime ?? $startDate,
            'reviewed_at'  => $endDateTime ?? $endDate,
            'notes'        => 'Dodano ręcznie przez kierownika w panelu.',
            'source'       => 'manual'
        ];
    }

    echo json_encode([
        'success'   => true,
        'absences'  => $absences
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Błąd serwera podczas odczytu ręcznych nieobecności'
    ]);
}
