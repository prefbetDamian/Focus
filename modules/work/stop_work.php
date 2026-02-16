<?php
/**
 * STOP pracy - zakończenie sesji
 */

require_once __DIR__.'/../../core/session.php';
require_once __DIR__.'/../../core/auth.php';
require_once __DIR__.'/../../core/functions.php';

$user = requireUser();
$pdo = require __DIR__.'/../../core/db.php';

// Pobierz aktywną sesję
$stmt = $pdo->prepare("
    SELECT 
        ws.id,
        ws.site_name,
        ws.start_time,
        ws.machine_id,
        m.machine_name,
        m.registry_number
    FROM work_sessions ws
    LEFT JOIN machines m ON m.id = ws.machine_id
    WHERE ws.employee_id = ? AND ws.end_time IS NULL
");
$stmt->execute([$user['id']]);
$session = $stmt->fetch();

if (!$session) {
    header('Location: ../../panel.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Zakończ pracę</title>

    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, sans-serif;
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }
        .container {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 20px;
            padding: 40px;
            max-width: 500px;
            width: 100%;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        }
        h1 {
            text-align: center;
            color: #333;
            margin-bottom: 10px;
            font-size: 28px;
        }
        .subtitle {
            text-align: center;
            color: #666;
            margin-bottom: 30px;
        }
        .summary {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
        }
        .summary-item {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid #e9ecef;
        }
        .summary-item:last-child {
            border-bottom: none;
        }
        .summary-label {
            font-weight: bold;
            color: #666;
        }
        .summary-value {
            color: #333;
        }
        .timer {
            text-align: center;
            font-size: 48px;
            font-weight: bold;
            color: #f5576c;
            margin: 20px 0;
        }
        .actions {
            display: flex;
            gap: 10px;
        }
        .btn {
            flex: 1;
            padding: 15px;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        .btn-back {
            background: #6c757d;
            color: white;
        }
        .btn-back:hover {
            background: #5a6268;
        }
        .btn-stop {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            color: white;
            font-size: 18px;
            font-weight: bold;
        }
        .btn-stop:hover {
            opacity: 0.9;
        }
        .message {
            text-align: center;
            padding: 15px;
            border-radius: 10px;
            margin-top: 20px;
            display: none;
        }
        .message.error {
            background: #f8d7da;
            color: #721c24;
            display: block;
        }
        .message.success {
            background: #d4edda;
            color: #155724;
            display: block;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🛑 Zakończ pracę</h1>
        <div class="subtitle">Podsumowanie Twojej sesji</div>

        <div class="summary">
            <div class="summary-item">
                <span class="summary-label">Budowa:</span>
                <span class="summary-value"><?= htmlspecialchars($session['site_name']) ?></span>
            </div>
            <?php if ($session['machine_id']): ?>
            <div class="summary-item">
                <span class="summary-label">Maszyna:</span>
                <span class="summary-value"><?= htmlspecialchars($session['machine_name'].' ('.$session['registry_number'].')') ?></span>
            </div>
            <?php endif; ?>
            <div class="summary-item">
                <span class="summary-label">Start:</span>
                <span class="summary-value"><?= date('d.m.Y H:i', strtotime($session['start_time'])) ?></span>
            </div>
        </div>

        <div class="timer" id="timer">00:00</div>

        <div class="actions">
            <button class="btn btn-back" onclick="back()">⬅️ Anuluj</button>
            <button class="btn btn-stop" id="btnStop" onclick="stopWork()">⏹️ ZAKOŃCZ</button>
        </div>

        <div class="message" id="message"></div>
    </div>

    <script>
        const startTime = new Date('<?= $session['start_time'] ?>').getTime();

        function updateTimer() {
            const now = Date.now();
            const diff = now - startTime;
            
            const hours = Math.floor(diff / 3600000);
            const minutes = Math.floor((diff % 3600000) / 60000);
            
            document.getElementById('timer').textContent = 
                String(hours).padStart(2, '0') + ':' + String(minutes).padStart(2, '0');
        }

        updateTimer();
        setInterval(updateTimer, 1000);

        async function stopWork() {
            if (!confirm('Czy na pewno chcesz zakończyć pracę?')) {
                return;
            }

            const btn = document.getElementById('btnStop');
            btn.disabled = true;
            btn.textContent = '⏳ Kończę...';

            try {
                const res = await fetch('stop.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' }
                });

                const data = await res.json();

                if (data.success) {
                    showMessage('✅ Praca zakończona! Przekierowuję...', 'success');
                    setTimeout(() => {
                        window.location.href = '../../panel.php';
                    }, 1500);
                } else {
                    showMessage('❌ ' + data.message, 'error');
                    btn.disabled = false;
                    btn.textContent = '⏹️ ZAKOŃCZ';
                }
            } catch (err) {
                showMessage('❌ Błąd połączenia z serwerem', 'error');
                btn.disabled = false;
                btn.textContent = '⏹️ ZAKOŃCZ';
            }
        }

        function showMessage(text, type) {
            const msg = document.getElementById('message');
            msg.textContent = text;
            msg.className = 'message ' + type;
        }

        function back() {
            window.location.href = '../../panel.php';
        }
    </script>
</body>
</html>
