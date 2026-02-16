<?php
declare(strict_types=1);

function closeWorkDay(PDO $pdo): void
{
    $today = date('Y-m-d');

    $stmt = $pdo->prepare("
        SELECT 1 FROM day_closures WHERE work_date = ?
    ");
    $stmt->execute([$today]);

    if ($stmt->fetch()) {
        return; // dzień już zamknięty
    }

    $pdo->exec("
        UPDATE work_sessions
        SET
            end_time = CONCAT(DATE(start_time), ' 16:00:00'),
            duration_seconds = GREATEST(
                0,
                TIMESTAMPDIFF(
                    SECOND,
                    start_time,
                    CONCAT(DATE(start_time), ' 16:00:00')
                )
            )
        WHERE end_time IS NULL
    ");

    $stmt = $pdo->prepare("
        INSERT INTO day_closures (work_date, closed_at)
        VALUES (?, NOW())
    ");
    $stmt->execute([$today]);
}
