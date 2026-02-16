<?php
declare(strict_types=1);

// Sprawdź czy vendor/autoload.php istnieje
$autoloadPath = __DIR__ . '/../vendor/autoload.php';
if (!file_exists($autoloadPath)) {
    error_log("Web Push library not installed - push functions disabled");
    return; // Wyjście z pliku bez definiowania funkcji
}

use Minishlink\WebPush\WebPush;
use Minishlink\WebPush\Subscription;

require_once $autoloadPath;

// Walidacja wstępna - sprawdź czy push_config.php istnieje i ma podstawowe dane
$pushConfigCheck = require __DIR__ . '/../push_config.php';
if (empty($pushConfigCheck['publicKey']) || empty($pushConfigCheck['privateKey']) || empty($pushConfigCheck['subject'])) {
    throw new RuntimeException('Brak pełnej konfiguracji VAPID w push_config.php (wymagane: publicKey, privateKey, subject)');
}
unset($pushConfigCheck); // Nie potrzebujemy już tej zmiennej

/**
 * Zwraca singleton WebPush skonfigurowany z kluczami VAPID.
 */
function getWebPush(): WebPush
{
    static $webPush = null;

    if ($webPush === null) {
        // Załaduj config bezpośrednio w funkcji (bez global)
        $pushConfig = require __DIR__ . '/../push_config.php';
        
        if (empty($pushConfig['subject'])) {
            throw new RuntimeException('Brak "subject" w push_config.php');
        }
        
        $webPush = new WebPush([
            'VAPID' => [
                'subject' => $pushConfig['subject'],
                'publicKey' => $pushConfig['publicKey'],
                'privateKey' => $pushConfig['privateKey'],
            ],
        ]);
    }

    return $webPush;
}

/**
 * Wyślij powiadomienie PUSH do konkretnego pracownika.
 */
function sendPushToEmployee(PDO $pdo, int $employeeId, string $title, string $body, ?string $url = null): void
{
    // Proste logowanie diagnostyczne do pliku push_error.log w katalogu głównym projektu
    $logFile = __DIR__ . '/../push_error.log';

    try {
        file_put_contents($logFile, sprintf("[%s] sendPushToEmployee start for employee_id=%d\n", date('Y-m-d H:i:s'), $employeeId), FILE_APPEND);

        $stmt = $pdo->prepare('SELECT endpoint, p256dh, auth FROM push_subscriptions WHERE employee_id = ?');
        $stmt->execute([$employeeId]);
        $s = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$s) {
            file_put_contents($logFile, sprintf("[%s] no subscription found for employee_id=%d\n", date('Y-m-d H:i:s'), $employeeId), FILE_APPEND);
            return; // brak subskrypcji dla tego pracownika
        }

        $sub = Subscription::create([
            'endpoint' => $s['endpoint'],
            'keys' => [
                'p256dh' => $s['p256dh'],
                'auth'   => $s['auth'],
            ],
        ]);

        $payload = [
            'title' => $title,
            'body'  => $body,
        ];

        if ($url !== null) {
            $payload['url'] = $url;
        }

        $webPush = getWebPush();
        file_put_contents($logFile, sprintf("[%s] WebPush instance created, sending notification...\n", date('Y-m-d H:i:s')), FILE_APPEND);

        $webPush->sendOneNotification($sub, json_encode($payload, JSON_UNESCAPED_UNICODE));

        foreach ($webPush->flush() as $report) {
            $endpoint = $report->getEndpoint();
            $reason   = $report->getReason();
            $success  = $report->isSuccess() ? 'YES' : 'NO';
            $expired  = $report->isSubscriptionExpired() ? 'YES' : 'NO';

            file_put_contents(
                $logFile,
                sprintf(
                    "[%s] Report endpoint=%s success=%s expired=%s reason=%s\n",
                    date('Y-m-d H:i:s'),
                    $endpoint,
                    $success,
                    $expired,
                    $reason
                ),
                FILE_APPEND
            );

            // Jeśli subskrypcja wygasła, w przyszłości można ją usunąć z bazy
        }

        file_put_contents($logFile, sprintf("[%s] sendPushToEmployee finished without exception for employee_id=%d\n\n", date('Y-m-d H:i:s'), $employeeId), FILE_APPEND);
    } catch (Throwable $e) {
        file_put_contents(
            $logFile,
            sprintf("[%s] ERROR in sendPushToEmployee for employee_id=%d: %s\n%s\n\n", date('Y-m-d H:i:s'), $employeeId, $e->getMessage(), $e->getTraceAsString()),
            FILE_APPEND
        );
    }
}

/**
 * Wyślij powiadomienie PUSH do konkretnego kierownika (tabela managers).
 */
function sendPushToManager(PDO $pdo, int $managerId, string $title, string $body, ?string $url = null): void
{
    $logFile = __DIR__ . '/../push_error.log';

    try {
        file_put_contents($logFile, sprintf("[%s] sendPushToManager start for manager_id=%d\n", date('Y-m-d H:i:s'), $managerId), FILE_APPEND);

        $stmt = $pdo->prepare('SELECT endpoint, p256dh, auth FROM push_subscriptions WHERE manager_id = ?');
        $stmt->execute([$managerId]);
        $s = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$s) {
            file_put_contents($logFile, sprintf("[%s] no subscription found for manager_id=%d\n", date('Y-m-d H:i:s'), $managerId), FILE_APPEND);
            return;
        }

        $sub = Subscription::create([
            'endpoint' => $s['endpoint'],
            'keys' => [
                'p256dh' => $s['p256dh'],
                'auth'   => $s['auth'],
            ],
        ]);

        $payload = [
            'title' => $title,
            'body'  => $body,
        ];

        if ($url !== null) {
            $payload['url'] = $url;
        }

        $webPush = getWebPush();
        file_put_contents($logFile, sprintf("[%s] WebPush instance created, sending notification (manager) ...\n", date('Y-m-d H:i:s')), FILE_APPEND);

        $webPush->sendOneNotification($sub, json_encode($payload, JSON_UNESCAPED_UNICODE));

        foreach ($webPush->flush() as $report) {
            $endpoint = $report->getEndpoint();
            $reason   = $report->getReason();
            $success  = $report->isSuccess() ? 'YES' : 'NO';
            $expired  = $report->isSubscriptionExpired() ? 'YES' : 'NO';

            file_put_contents(
                $logFile,
                sprintf(
                    "[%s] Manager report endpoint=%s success=%s expired=%s reason=%s\n",
                    date('Y-m-d H:i:s'),
                    $endpoint,
                    $success,
                    $expired,
                    $reason
                ),
                FILE_APPEND
            );
        }

        file_put_contents($logFile, sprintf("[%s] sendPushToManager finished without exception for manager_id=%d\n\n", date('Y-m-d H:i:s'), $managerId), FILE_APPEND);
    } catch (Throwable $e) {
        file_put_contents(
            $logFile,
            sprintf("[%s] ERROR in sendPushToManager for manager_id=%d: %s\n%s\n\n", date('Y-m-d H:i:s'), $managerId, $e->getMessage(), $e->getTraceAsString()),
            FILE_APPEND
        );
    }
}
