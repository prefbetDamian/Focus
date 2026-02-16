<?php
/**
 * STEP 3: Potwierdzenie i START
 */

require_once __DIR__.'/../../core/session.php';
require_once __DIR__.'/../../core/auth.php';

$user = requireUser();
?>
<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Potwierdzenie</title>

    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, sans-serif;
            height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            overflow: hidden;
        }
        .bg-video {
            position: fixed;
            inset: 0;
            width: 100vw;
            height: 100vh;
            object-fit: cover;
            z-index: -2;
        }
        .video-overlay {
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.7);
            z-index: -1;
        }
        .container {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
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
        .btn-start {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
            color: white;
            font-size: 18px;
            font-weight: bold;
        }
        .btn-start:hover {
            opacity: 0.9;
        }
        .btn-start:disabled {
            opacity: 0.5;
            cursor: not-allowed;
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
    <video class="bg-video" autoplay loop muted playsinline>
        <source src="../../background.mp4" type="video/mp4">
    </video>
    <div class="video-overlay"></div>
    <div class="container">
        <h1>✅ Potwierdzenie</h1>
        <div class="subtitle">Sprawdź dane przed rozpoczęciem</div>

        <div class="summary">
            <div class="summary-item">
                <span class="summary-label">Pracownik:</span>
                <span class="summary-value"><?= htmlspecialchars($user['first_name'].' '.$user['last_name']) ?></span>
            </div>
            <div class="summary-item">
                <span class="summary-label">Budowa:</span>
                <span class="summary-value" id="siteName">-</span>
            </div>
            <div class="summary-item" id="machineRow" style="display: none;">
                <span class="summary-label">Maszyna:</span>
                <span class="summary-value" id="machineName">-</span>
            </div>
            <div class="summary-item">
                <span class="summary-label">Start:</span>
                <span class="summary-value" id="startTime">-</span>
            </div>
        </div>

        <div class="actions">
            <button class="btn btn-back" onclick="back()">⬅️ Wstecz</button>
            <button class="btn btn-start" id="btnStart" onclick="startWork()">🚀 ROZPOCZNIJ PRACĘ</button>
        </div>

        <div class="message" id="message"></div>
    </div>

    <script>
        const isOperator = <?= $user['is_operator'] ? 'true' : 'false' ?>;

        function isLocationTrackingDisabled() {
            try {
                return localStorage.getItem('rcp_disable_location') === '1';
            } catch (e) {
                console.error('Błąd odczytu ustawienia lokalizacji z localStorage w step_confirm:', e);
                return false;
            }
        }

        // Wczytaj dane z sessionStorage
        const siteId = sessionStorage.getItem('selected_site_id');
        const siteName = sessionStorage.getItem('selected_site_name');
        const machineId = sessionStorage.getItem('selected_machine_id');
        const machineName = sessionStorage.getItem('selected_machine_name');
        const machineRegistry = sessionStorage.getItem('selected_machine_registry');

        if (!siteId) {
            window.location.href = 'step_building.php';
        }

        // Wyświetl podsumowanie
        document.getElementById('siteName').textContent = siteName;
        document.getElementById('startTime').textContent = new Date().toLocaleString('pl-PL');

        if (isOperator && machineId) {
            document.getElementById('machineRow').style.display = 'flex';
            document.getElementById('machineName').textContent = 
                machineName + ' (' + machineRegistry + ')';
        }

        async function startWork() {
            const btn = document.getElementById('btnStart');
            btn.disabled = true;
            btn.textContent = '⏳ Rozpoczynam...';

            const payload = {
                site_id: siteId,
                site_name: siteName,
                machine_id: machineId || null,
                lat: null,
                lng: null
            };

            // Jeśli mamy współrzędne zapisane przy pierwszym kliknięciu START
            // w panelu (panel.php), użyj ich w pierwszej kolejności.
            try {
                const storedLat = sessionStorage.getItem('start_lat');
                const storedLng = sessionStorage.getItem('start_lng');
                if (storedLat && storedLng) {
                    payload.lat = parseFloat(storedLat);
                    payload.lng = parseFloat(storedLng);
                    sessionStorage.removeItem('start_lat');
                    sessionStorage.removeItem('start_lng');
                }
            } catch (e) {
                console.error('Błąd odczytu współrzędnych z sessionStorage:', e);
            }

            const send = async (dataToSend) => {
                try {
                    const res = await fetch('start.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify(dataToSend)
                    });

                    const data = await res.json();

                    if (data.success) {
                        showMessage('✅ Praca rozpoczęta! Przekierowuję...', 'success');

                        // Wyczyść sessionStorage
                        sessionStorage.removeItem('selected_site_id');
                        sessionStorage.removeItem('selected_site_name');
                        sessionStorage.removeItem('selected_machine_id');
                        sessionStorage.removeItem('selected_machine_name');
                        sessionStorage.removeItem('selected_machine_registry');
                        sessionStorage.removeItem('start_lat');
                        sessionStorage.removeItem('start_lng');

                        setTimeout(() => {
                            window.location.href = '../../panel.php';
                        }, 1500);
                    } else {
                        showMessage('❌ ' + data.message, 'error');
                        btn.disabled = false;
                        btn.textContent = '🚀 ROZPOCZNIJ PRACĘ';
                    }
                } catch (err) {
                    showMessage('❌ Błąd połączenia z serwerem', 'error');
                    btn.disabled = false;
                    btn.textContent = '🚀 ROZPOCZNIJ PRACĘ';
                }
            };

            // Jeśli mamy już współrzędne (panel.php -> sessionStorage), nie pytamy ponownie o GPS.
            if (payload.lat !== null && payload.lng !== null) {
                send(payload);
                return;
            }

            // W przeciwnym razie spróbuj pobrać GPS jeszcze raz na tym etapie,
            // o ile lokalizacja nie została wyłączona w ustawieniach telefonu.
            if (!isLocationTrackingDisabled() && navigator.geolocation) {
                navigator.geolocation.getCurrentPosition(
                    pos => {
                        payload.lat = pos.coords.latitude;
                        payload.lng = pos.coords.longitude;
                        send(payload);
                    },
                    () => {
                        // Brak zgody / błąd GPS – lecimy dalej z danymi IP
                        send(payload);
                    },
                    { timeout: 2000 }
                );
            } else {
                // Lokalizacja wyłączona lub brak geolokalizacji w przeglądarce – fallback IP po stronie serwera
                send(payload);
            }
        }

        function showMessage(text, type) {
            const msg = document.getElementById('message');
            msg.textContent = text;
            msg.className = 'message ' + type;
        }

        function back() {
            if (isOperator) {
                window.location.href = 'step_machine.php';
            } else {
                window.location.href = 'step_building.php';
            }
        }
    </script>
</body>
</html>
