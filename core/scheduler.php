<?php
/**
 * Wewnętrzny Scheduler - wykonuje zadania cron bez polegania na systemowym cron
 * 
 * System sprawdza przy każdym wywołaniu czy nadszedł czas na wykonanie zadań.
 * Używa pliku lock do zapobiegania równoległemu uruchamianiu tego samego zadania.
 */

declare(strict_types=1);

class Scheduler {
    private $pdo;
    private $lockDir;
    private $logFile;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
        $this->lockDir = __DIR__ . '/../cron/locks';
        $this->logFile = __DIR__ . '/../cron_log.txt';
        
        // Sprawdź i utwórz katalog locks
        if (!is_dir($this->lockDir)) {
            if (!@mkdir($this->lockDir, 0775, true)) {
                throw new RuntimeException(
                    "Cannot create locks directory: {$this->lockDir}. " .
                    "Please create it manually with: mkdir -p cron/locks && chmod 775 cron/locks"
                );
            }
        }
        
        // Sprawdź uprawnienia zapisu do locks
        if (!is_writable($this->lockDir)) {
            throw new RuntimeException(
                "Locks directory is not writable: {$this->lockDir}. " .
                "Please fix permissions with: chmod 775 cron/locks"
            );
        }
        
        // Sprawdź/utwórz plik logów
        if (!file_exists($this->logFile)) {
            if (!@touch($this->logFile)) {
                throw new RuntimeException(
                    "Cannot create log file: {$this->logFile}. " .
                    "Please create it manually with: touch cron_log.txt && chmod 664 cron_log.txt"
                );
            }
        }
        
        // Sprawdź uprawnienia zapisu do logów
        if (!is_writable($this->logFile)) {
            throw new RuntimeException(
                "Log file is not writable: {$this->logFile}. " .
                "Please fix permissions with: chmod 664 cron_log.txt"
            );
        }
    }
    
    /**
     * Główna metoda - sprawdza i wykonuje zadania które powinny się wykonać
     */
    public function run(): void {
        try {
            // Zadanie 1: Zamykanie sesji - wykonywane codziennie o 23:59
            $this->runIfNeeded('close_sessions', 86400, function() {
                // Sprawdź czy jest między 23:59 a 00:30
                $hour = (int)date('H');
                $minute = (int)date('i');
                
                // Wykonaj tylko w oknie 23:59-00:30
                if (($hour === 23 && $minute >= 59) || ($hour === 0 && $minute <= 30)) {
                    $this->closeOpenWorkSessions();
                }
            });
            
            // Zadanie 2: Powiadomienia push - co 15 MINUT (odświeżanie schedulera)
            $this->runIfNeeded('push_notifications', 900, function() {
                $this->sendPushNotifications();
            });
            
        } catch (Throwable $e) {
            $this->log("Scheduler error: " . $e->getMessage());
        }
    }
    
    /**
     * Uruchamia zadanie tylko jeśli minął odpowiedni czas
     * 
     * @param string $taskName Nazwa zadania
     * @param int $intervalSeconds Interwał w sekundach
     * @param callable $callback Funkcja do wykonania
     */
    private function runIfNeeded(string $taskName, int $intervalSeconds, callable $callback): void {
        $lockFile = $this->lockDir . '/' . $taskName . '.lock';
        
        // Sprawdź czy plik lock istnieje i kiedy było ostatnie wykonanie
        if (file_exists($lockFile)) {
            $lastRun = (int)file_get_contents($lockFile);
            $timeSinceLastRun = time() - $lastRun;
            
            // Jeśli nie minął wymagany czas, nie wykonuj
            if ($timeSinceLastRun < $intervalSeconds) {
                return;
            }
        }
        
        // Utwórz plik lock z obecnym timestampem
        file_put_contents($lockFile, (string)time());
        
        try {
            // Wykonaj zadanie
            $callback();
            $this->log("Task '$taskName' completed successfully");
        } catch (Throwable $e) {
            $this->log("Task '$taskName' failed: " . $e->getMessage());
        }
    }
    
    /**
     * Zamyka otwarte sesje pracy o północy (max 8h/dzień)
     */
    private function closeOpenWorkSessions(): void {
        require_once __DIR__ . '/push.php';
        
        // Pobierz wszystkie otwarte sesje DZISIAJ (tego dnia o 23:59)
        $today = date('Y-m-d');
        
        $stmtOpen = $this->pdo->prepare("
            SELECT id, employee_id, start_time, site_name
            FROM work_sessions
            WHERE end_time IS NULL
              AND DATE(start_time) = ?
        ");
        $stmtOpen->execute([$today]);
        
        $openSessions = $stmtOpen->fetchAll(PDO::FETCH_ASSOC);
        $closedCount = 0;
        
        foreach ($openSessions as $session) {
            $employeeId = (int)$session['employee_id'];
            $startDate = substr($session['start_time'], 0, 10); // YYYY-MM-DD
            
            // Suma dotychczasowych zakończonych sesji w tym dniu
            $stmtSum = $this->pdo->prepare("
                SELECT COALESCE(SUM(duration_seconds), 0)
                FROM work_sessions
                WHERE employee_id = ?
                  AND DATE(start_time) = ?
                  AND end_time IS NOT NULL
            ");
            $stmtSum->execute([$employeeId, $startDate]);
            $closedSeconds = (int)$stmtSum->fetchColumn();
            
            // Ile sekund brakuje do pełnych 8h (28800s)
            $remaining = 28800 - $closedSeconds;
            if ($remaining < 0) {
                $remaining = 0;
            }
            
            $endTime = $startDate . ' 23:59:59';
            
            $stmtUpdate = $this->pdo->prepare("
                UPDATE work_sessions
                SET end_time = ?,
                    duration_seconds = ?,
                    status = 'AUTO'
                WHERE id = ?
            ");
            $stmtUpdate->execute([$endTime, $remaining, (int)$session['id']]);
            
            // Wyślij powiadomienie push
            try {
                if ($employeeId > 0) {
                    $siteName = $session['site_name'] ?? '';
                    $title = 'Sesja automatycznie zamknięta';
                    $body = sprintf(
                        'Twoja sesja pracy %s została automatycznie zakończona i oczekuje na akceptację kierownika.',
                        $siteName ? ('na budowie ' . $siteName) : ''
                    );
                    
                    sendPushToEmployee($this->pdo, $employeeId, $title, $body, 'panel.php');
                }
            } catch (Throwable $e) {
                $this->log("Push error for employee $employeeId: " . $e->getMessage());
            }
            
            $closedCount++;
        }
        
        $this->log("Close sessions: Closed $closedCount open sessions (AUTO, max 8h/day)");
    }
    
    /**
     * Wysyła powiadomienia push do pracowników pracujących >10h DZISIAJ
     */
    private function sendPushNotifications(): void {
        require_once __DIR__ . '/push.php';
        
        // Znajdź pracowników z sumą czasu pracy >= 10h dzisiaj
        $sql = "
            SELECT
                ws.employee_id,
                SUM(
                    CASE
                        WHEN ws.end_time IS NULL THEN TIMESTAMPDIFF(SECOND, ws.start_time, NOW())
                        ELSE ws.duration_seconds
                    END
                ) AS total_seconds,
                SUM(CASE WHEN ws.end_time IS NULL THEN 1 ELSE 0 END) AS open_sessions
            FROM work_sessions ws
            WHERE ws.employee_id IS NOT NULL
              AND DATE(ws.start_time) = CURDATE()
              AND (ws.absence_group_id IS NULL OR ws.absence_group_id = 0)
              AND (ws.status IS NULL OR ws.status <> 'REJECTED')
            GROUP BY ws.employee_id
            HAVING total_seconds >= 10 * 3600
               AND open_sessions > 0
        ";
        
        $stmt = $this->pdo->query($sql);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $sentCount = 0;
        
        foreach ($rows as $row) {
            $employeeId = (int)$row['employee_id'];
            if ($employeeId <= 0) {
                continue;
            }
            
            try {
                sendPushToEmployee(
                    $this->pdo,
                    $employeeId,
                    'RCP – ponad 10 godzin pracy',
                    'Przepracowałeś już dzisiaj ponad 10 godzin i nadal pracujesz. Sprawdź swój czas pracy w panelu.',
                    'panel.php'
                );
                $sentCount++;
            } catch (Throwable $e) {
                $this->log("Push notification error for employee $employeeId: " . $e->getMessage());
            }
        }
        
        $this->log("Push notifications: Sent $sentCount notifications to employees working >10h");
    }
    
    /**
     * Loguje wiadomości do pliku
     */
    private function log(string $message): void {
        $timestamp = date('Y-m-d H:i:s');
        @file_put_contents(
            $this->logFile,
            "[$timestamp] [Scheduler] $message\n",
            FILE_APPEND
        );
    }
}

/**
 * Funkcja pomocnicza do szybkiego uruchomienia schedulera
 */
function runScheduler($pdo = null): void {
    try {
        if ($pdo === null) {
            $pdo = require __DIR__ . '/db.php';
        }
        
        $scheduler = new Scheduler($pdo);
        $scheduler->run();
    } catch (Throwable $e) {
        error_log("Scheduler initialization error: " . $e->getMessage());
    }
}
