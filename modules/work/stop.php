<?php
/**
 * STOP - zapis zakończenia pracy
 */

require_once __DIR__.'/../../core/session.php';
require_once __DIR__.'/../../core/auth.php';
require_once __DIR__.'/../../core/functions.php';
require_once __DIR__.'/../../core/push.php';

header('Content-Type: application/json; charset=utf-8');

$user = requireUser();

try {
    $pdo = require __DIR__.'/../../core/db.php';
    
    // Pobierz aktywną sesję
    $stmt = $pdo->prepare("
           SELECT id, start_time, machine_id, site_id, site_name
        FROM work_sessions 
        WHERE employee_id = ? AND end_time IS NULL
    ");
    $stmt->execute([$user['id']]);
    $session = $stmt->fetch();
    
    if (!$session) {
        jsonError('Brak aktywnej sesji pracy');
    }
    
    // Oblicz czas trwania
    $duration = time() - strtotime($session['start_time']);
    
    $pdo->beginTransaction();
    
    // Zakończ sesję pracy
        $stmt = $pdo->prepare("
            UPDATE work_sessions
            SET end_time = NOW(),
                duration_seconds = ?
                , status = 'PENDING'
                WHERE id = ?
        ");
        $stmt->execute([$duration, $session['id']]);

        // Dla specjalnej budowy 0/26 - Roboty niesklasyfikowane (site_id = 26)
        // przygotuj wpisy w workflow akceptacji dla wszystkich przypisanych kierowników
        $managers = [];
        if (!empty($session['site_id']) && (int)$session['site_id'] === 26) {
            // Pobierz przypisanych kierowników do tej "budowy"
            $stmtManagers = $pdo->prepare("
                SELECT manager_id
                FROM site_managers
                WHERE site_id = ?
            ");
            $stmtManagers->execute([(int)$session['site_id']]);
            $managers = $stmtManagers->fetchAll(PDO::FETCH_COLUMN);

            if ($managers) {
                $ins = $pdo->prepare("
                    INSERT IGNORE INTO work_session_approvals (work_session_id, manager_id)
                    VALUES (?, ?)
                ");

                foreach ($managers as $mid) {
                    $ins->execute([$session['id'], (int)$mid]);
                }
            }
        }
    
    // Zakończ sesję maszyny (jeśli była)
    if ($session['machine_id']) {
        $stmt = $pdo->prepare("
            UPDATE machine_sessions
            SET end_time = NOW(),
                duration_seconds = ?
            WHERE work_session_id = ? AND end_time IS NULL
        ");
        $stmt->execute([$duration, $session['id']]);
    }
    
    $pdo->commit();
    
    // PUSH: najpierw powiadom pracownika o wysłaniu sesji do akceptacji,
    // a następnie kierowników o nowej sesji do zatwierdzenia
    try {
        $siteId = isset($session['site_id']) ? (int)$session['site_id'] : 0;
        $siteName = $session['site_name'] ?? '';

        // Powiadomienie do pracownika o zmianie statusu na PENDING
        try {
            $empTitle = 'Sesja wysłana do akceptacji';
            $empBody = sprintf(
                'Twoja sesja pracy %s została przesłana do akceptacji do kierownika.',
                $siteName ? ('na budowie ' . $siteName) : ''
            );

            sendPushToEmployee($pdo, (int)$user['id'], $empTitle, $empBody, 'panel.php');
        } catch (Throwable $e) {
            // Błąd PUSH do pracownika nie blokuje zakończenia
        }

        // Jeśli wcześniej nie pobrano listy kierowników (z powodu site_id != 26), pobierz teraz
        if ($siteId > 0 && empty($managers)) {
            $stmtManagers = $pdo->prepare("
                SELECT manager_id
                FROM site_managers
                WHERE site_id = ?
            ");
            $stmtManagers->execute([$siteId]);
            $managers = $stmtManagers->fetchAll(PDO::FETCH_COLUMN);
        }

        if (!empty($managers)) {
            $title = 'Nowa sesja do akceptacji';
            $body = sprintf(
                'Pracownik %s %s zakończył sesję na budowie %s.',
                $user['first_name'] ?? '',
                $user['last_name'] ?? '',
                $siteName ?: '—'
            );

            foreach ($managers as $mid) {
                sendPushToManager($pdo, (int)$mid, $title, $body, 'panel/pending_sessions.php');
            }
        }
    } catch (Throwable $e) {
        // Błąd PUSH nie blokuje zakończenia sesji
    }

    jsonSuccess('Praca zakończona', [
        'duration' => formatDuration($duration)
    ]);
    
} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    jsonError('Błąd serwera: ' . $e->getMessage(), 500);
}
