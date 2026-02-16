<?php
require_once __DIR__ . '/core/session.php';
require_once __DIR__ . '/core/auth.php';

$user = requireUser();
$employeeId = (int)$user['id'];
?>
<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ustawienia telefonu - RCP System</title>

    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .container {
            background: rgba(255,255,255,0.95);
            border-radius: 20px;
            padding: 30px 26px;
            max-width: 600px;
            width: 100%;
            box-shadow: 0 18px 50px rgba(0,0,0,0.35);
        }
        h1 {
            font-size: 24px;
            margin-bottom: 10px;
            color: #333;
        }
        .subtitle {
            font-size: 14px;
            color: #555;
            margin-bottom: 20px;
        }
        .section {
            margin-bottom: 20px;
            padding: 14px 16px;
            border-radius: 12px;
            background: #f8f9fa;
            border: 1px solid rgba(0,0,0,0.05);
        }
        .section h2 {
            font-size: 16px;
            margin-bottom: 8px;
            color: #333;
        }
        .section p {
            font-size: 13px;
            color: #555;
            margin-bottom: 10px;
        }
        .section .field-row {
            margin-top: 8px;
            display: flex;
            gap: 8px;
            align-items: center;
        }
        .section select.manager-select {
            flex: 1;
            padding: 8px 10px;
            border-radius: 8px;
            border: 1px solid #ccc;
            font-size: 14px;
            background: #fff;
        }
        button {
            padding: 10px 16px;
            border-radius: 8px;
            border: none;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            color: #fff;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            margin-right: 8px;
            margin-top: 4px;
        }
        button.secondary {
            background: #6c757d;
        }
        .back-btn {
            background: linear-gradient(135deg, #6c757d 0%, #5a6268 100%);
            margin-top: 15px;
            width: 100%;
        }
        .back-btn:hover {
            box-shadow: 0 10px 25px rgba(108, 117, 125, 0.4);
        }
    </style>
</head>
<body>
<div class="container">
    <h1>⚙️ Ustawienia telefonu</h1>
    <div class="subtitle">
        Witaj, <strong><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name'], ENT_QUOTES, 'UTF-8'); ?></strong>.<br>
        Tutaj możesz odpiąć bieżące urządzenie, włączyć powiadomienia PUSH oraz sprawdzić działanie lokalizacji (GPS).
    </div>

    <div class="section">
        <h2>🔄 Odepnięcie urządzenia</h2>
        <p>Jeśli zmieniasz telefon albo chcesz zalogować się z innego urządzenia, możesz odpiąć bieżące urządzenie od swojego konta. Przy następnym logowaniu nowe urządzenie zostanie przypisane automatycznie.</p>
        <button type="button" onclick="resetMyDevice()">Odepnij to urządzenie</button>
    </div>

    <div class="section">
        <h2>👨‍💼 Mój kierownik</h2>
        <p>Wybierz kierownika, do którego podlegasz.</p>
        <div class="field-row">
            <select id="managerSelect" class="manager-select">
                <option value="">Ładowanie listy kierowników...</option>
            </select>
            <button type="button" onclick="saveSelectedManager()">Zapisz</button>
        </div>
        <div id="managerStatus" style="margin-top:6px;font-size:13px;color:#555;">
            Status: ładowanie...
        </div>
    </div>

    <div class="section">
        <h2>🔔 Powiadomienia PUSH</h2>
        <p>Aby kierownik mógł wysyłać Ci komunikaty, wymagane jest włączenie powiadomień PUSH dla tej strony.</p>
        <label style="display:flex;align-items:center;gap:8px;font-size:14px;">
            <input type="checkbox" id="pushEnabledToggle" style="width:18px;height:18px;">
            <span>Włącz / wyłącz powiadomienia PUSH dla tego telefonu</span>
        </label>
        <div id="pushStatus" style="margin-top:6px;font-size:13px;color:#555;">
            Status: ładowanie...
        </div>
    </div>

    <div class="section">
        <h2>📍 Lokalizacja (GPS)</h2>
        <p>System RCP domyślnie próbuje pobrać Twoją lokalizację podczas rozpoczynania pracy. Możesz to przetestować poniżej lub całkowicie wyłączyć pobieranie lokalizacji przy START/STOP dla tego telefonu.</p>
        <button type="button" onclick="requestLocation()">Sprawdź lokalizację teraz</button>
        <label style="display:flex;align-items:center;gap:8px;font-size:14px;margin-top:8px;">
            <input type="checkbox" id="locationEnabledToggle" style="width:18px;height:18px;">
            <span>Używaj / nie używaj lokalizacji przy START/STOP</span>
        </label>
        <div id="locationStatus" style="margin-top:6px;font-size:13px;color:#555;">
            Status: ładowanie...
        </div>
    </div>

    <button type="button" class="back-btn" onclick="goBackToPanel()">← Powrót do panelu</button>
</div>

<script>
    const EMPLOYEE_ID = <?php echo $employeeId; ?>;
    const VAPID_PUBLIC_KEY = 'BDttZpGMOcEb1OZNSg_eGC5FkMmPXMAl1pvlksZj923I2qWESy47AHVtHMRHCPJbxpVx9TnMQohVxAMsk1U5rhs';

    function urlBase64ToUint8Array(base64String) {
        const padding = '='.repeat((4 - base64String.length % 4) % 4);
        const base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
        const rawData = window.atob(base64);
        const outputArray = new Uint8Array(rawData.length);
        for (let i = 0; i < rawData.length; ++i) {
            outputArray[i] = rawData.charCodeAt(i);
        }
        return outputArray;
    }

    async function enablePush(employeeId) {
        if (!('Notification' in window) || !('serviceWorker' in navigator) || !('PushManager' in window)) {
            alert('Twoja przeglądarka nie obsługuje powiadomień PUSH lub trybu PWA. Użyj aktualnej wersji Chrome/Edge/Firefox (Android) lub PWA z Safari (iOS 16.4+).');
            return;
        }

        if (!VAPID_PUBLIC_KEY) {
            alert('Brak konfiguracji klucza VAPID po stronie serwera. Zgłoś to kierownikowi.');
            return;
        }

        const permission = await Notification.requestPermission();
        if (permission !== 'granted') {
            alert('Nie wyraziłeś zgody na powiadomienia. Włącz powiadomienia dla tej strony w ustawieniach przeglądarki.');
            return;
        }

        const reg = await navigator.serviceWorker.register('service-worker.js');

        const sub = await reg.pushManager.subscribe({
            userVisibleOnly: true,
            applicationServerKey: urlBase64ToUint8Array(VAPID_PUBLIC_KEY)
        });

        const res = await fetch('api.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'subscribe_push',
                employee_id: employeeId,
                subscription: sub
            })
        });

        const data = await res.json().catch(() => ({ status: 'error' }));

        if (data.status === 'success' || data.status === 'ok') {
            alert('✅ Powiadomienia PUSH zostały włączone dla tego urządzenia.');
        } else {
            alert('❌ Nie udało się zapisać subskrypcji powiadomień. Spróbuj ponownie lub zgłoś problem kierownikowi.');
        }
    }

    async function resetMyDevice() {
        if (!confirm('Czy na pewno chcesz odpiąć to urządzenie od swojego konta?\nPo odpięciu przy następnym logowaniu będziesz mógł użyć nowego telefonu.')) {
            return;
        }

        try {
            const res = await fetch('reset_my_device.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({})
            });

            const data = await res.json().catch(() => ({ success: false }));

            if (data.success) {
                alert('✅ Urządzenie zostało odpięte. Nastąpi teraz wylogowanie z panelu.');
                window.location.href = 'index.html';
            } else {
                alert('❌ Nie udało się odpiąć urządzenia. ' + (data.message || 'Spróbuj ponownie lub skontaktuj się z kierownikiem.'));
            }
        } catch (e) {
            alert('❌ Błąd połączenia z serwerem podczas odpinania urządzenia.');
            console.error(e);
        }
    }

    async function enablePushFromSettings() {
        if (!EMPLOYEE_ID) {
            alert('Brak identyfikatora pracownika w sesji. Zaloguj się ponownie.');
            return;
        }

        try {
            await enablePush(EMPLOYEE_ID);
        } catch (e) {
            alert('Nie udało się włączyć powiadomień PUSH. Sprawdź ustawienia przeglądarki.');
            console.error(e);
        }
    }

    async function disablePushFromSettings() {
        if (!EMPLOYEE_ID) {
            alert('Brak identyfikatora pracownika w sesji. Zaloguj się ponownie.');
            return;
        }

        if (!confirm('Czy na pewno chcesz wyłączyć powiadomienia PUSH dla tego telefonu?')) {
            return;
        }

        try {
            const res = await fetch('api.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'unsubscribe_push',
                    employee_id: EMPLOYEE_ID
                })
            });

            const data = await res.json().catch(() => ({ status: 'error' }));

            if (data.status === 'success' || data.status === 'ok') {
                alert('🔕 Powiadomienia PUSH zostały wyłączone dla tego telefonu.');
            } else {
                alert('❌ Nie udało się wyłączyć powiadomień PUSH. Spróbuj ponownie lub zgłoś problem kierownikowi.');
            }
        } catch (e) {
            alert('❌ Błąd połączenia z serwerem podczas wyłączania powiadomień.');
            console.error(e);
        }
    }

    async function loadManagerOptions() {
        const select = document.getElementById('managerSelect');
        const statusEl = document.getElementById('managerStatus');

        if (!select || !statusEl) return;

        statusEl.textContent = 'Status: ładowanie listy kierowników...';
        select.innerHTML = '';

        try {
            const res = await fetch('api.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'get_employee_manager_settings' })
            });

            const data = await res.json().catch(() => ({ status: 'error' }));

            if (data.status !== 'ok') {
                statusEl.textContent = data.message || 'Status: nie udało się odczytać listy kierowników.';
                return;
            }

            const managers = Array.isArray(data.managers) ? data.managers : [];

            const placeholder = document.createElement('option');
            placeholder.value = '';
            placeholder.textContent = '-- wybierz kierownika --';
            select.appendChild(placeholder);

            managers.forEach(m => {
                const opt = document.createElement('option');
                opt.value = m.id;
                opt.textContent = `${m.first_name} ${m.last_name}`;
                select.appendChild(opt);
            });

            if (data.selected_manager_id) {
                select.value = String(data.selected_manager_id);
                // Sprawdź czy udało się ustawić wartość (manager może być niedostępny na liście)
                const selectedOption = select.options[select.selectedIndex];
                if (selectedOption && selectedOption.value) {
                    statusEl.textContent = `Obecnie przypisany kierownik: ${selectedOption.text}`;
                } else {
                    statusEl.textContent = 'Przypisany kierownik nie jest już dostępny na liście. Wybierz nowego kierownika.';
                    statusEl.style.color = '#ffc107';
                }
            } else {
                statusEl.textContent = 'Nie wybrano jeszcze kierownika.';
            }
        } catch (e) {
            console.error('Błąd odczytu listy kierowników:', e);
            statusEl.textContent = 'Status: błąd połączenia przy odczycie listy kierowników.';
        }
    }

    async function saveSelectedManager() {
        const select = document.getElementById('managerSelect');
        const statusEl = document.getElementById('managerStatus');

        if (!select || !statusEl) return;

        const value = select.value;
        const managerId = parseInt(value, 10) || 0;

        if (!managerId) {
            alert('Wybierz kierownika z listy.');
            return;
        }

        try {
            const res = await fetch('api.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'save_employee_manager', manager_id: managerId })
            });

            const data = await res.json().catch(() => ({ status: 'error' }));

            if (data.status === 'ok') {
                statusEl.textContent = `Obecnie przypisany kierownik: ${select.options[select.selectedIndex].text}`;
                alert('✅ Kierownik został zapisany.');
            } else {
                alert(data.message || '❌ Nie udało się zapisać kierownika.');
            }
        } catch (e) {
            console.error('Błąd zapisu kierownika:', e);
            alert('❌ Błąd połączenia z serwerem podczas zapisu kierownika.');
        }
    }

    function requestLocation() {
        if (!('geolocation' in navigator)) {
            alert('Twoje urządzenie lub przeglądarka nie obsługuje geolokalizacji.');
            return;
        }

        navigator.geolocation.getCurrentPosition(
            pos => {
                try {
                    sessionStorage.setItem('start_lat', String(pos.coords.latitude));
                    sessionStorage.setItem('start_lng', String(pos.coords.longitude));
                } catch (e) {
                    console.error('Nie udało się zapisać lokalizacji w sessionStorage (ustawienia):', e);
                }
                alert('✅ Lokalizacja została odczytana i zapisana.');
            },
            err => {
                console.warn('Błąd geolokalizacji przy sprawdzaniu w ustawieniach:', err);
                alert('❌ Nie udało się pobrać lokalizacji.\nUpewnij się, że GPS jest włączony oraz że przeglądarka ma uprawnienia do lokalizacji dla tej strony.');
            },
            { timeout: 5000 }
        );
    }

    function disableLocationTracking() {
        try {
            localStorage.setItem('rcp_disable_location', '1');
            alert('📍 Lokalizacja przy rozpoczynaniu/zakańczaniu pracy została WYŁĄCZONA dla tego telefonu. System będzie używał tylko danych z adresu IP.');
        } catch (e) {
            console.error('Błąd przy zapisie ustawienia lokalizacji:', e);
            alert('❌ Nie udało się zapisać ustawienia lokalizacji w tej przeglądarce.');
        }
    }

    function enableLocationTracking() {
        try {
            localStorage.removeItem('rcp_disable_location');
            alert('📍 Lokalizacja przy rozpoczynaniu/zakańczaniu pracy została WŁĄCZONA dla tego telefonu.');
        } catch (e) {
            console.error('Błąd przy zapisie ustawienia lokalizacji:', e);
            alert('❌ Nie udało się zapisać ustawienia lokalizacji w tej przeglądarce.');
        }
    }

    async function refreshPushStatus() {
        const statusEl = document.getElementById('pushStatus');
        const toggle = document.getElementById('pushEnabledToggle');

        if (!statusEl || !toggle) return;

        if (!EMPLOYEE_ID) {
            statusEl.textContent = 'Status: brak identyfikatora pracownika.';
            toggle.disabled = true;
            return;
        }

        try {
            const res = await fetch('api.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'get_push_status' })
            });

            const data = await res.json().catch(() => ({ status: 'error' }));

            if (data.status === 'ok') {
                toggle.checked = !!data.enabled;
                statusEl.textContent = data.enabled
                    ? 'Status: powiadomienia PUSH są WŁĄCZONE.'
                    : 'Status: powiadomienia PUSH są WYŁĄCZONE.';
            } else {
                statusEl.textContent = 'Status: nie udało się odczytać statusu powiadomień.';
            }
        } catch (e) {
            console.error('Błąd odczytu statusu PUSH:', e);
            statusEl.textContent = 'Status: błąd połączenia przy odczycie statusu powiadomień.';
        }
    }

    function refreshLocationStatus() {
        const statusEl = document.getElementById('locationStatus');
        const toggle = document.getElementById('locationEnabledToggle');

        if (!statusEl || !toggle) return;

        let disabled = false;
        try {
            disabled = localStorage.getItem('rcp_disable_location') === '1';
        } catch (e) {
            console.error('Błąd odczytu ustawienia lokalizacji:', e);
        }

        toggle.checked = !disabled;
        statusEl.textContent = disabled
            ? 'Status: lokalizacja przy START/STOP jest WYŁĄCZONA.'
            : 'Status: lokalizacja przy START/STOP jest WŁĄCZONA.';
    }

    document.addEventListener('DOMContentLoaded', () => {
        const pushToggle = document.getElementById('pushEnabledToggle');
        const locToggle = document.getElementById('locationEnabledToggle');

        if (pushToggle) {
            pushToggle.addEventListener('change', async () => {
                if (pushToggle.checked) {
                    await enablePushFromSettings();
                } else {
                    await disablePushFromSettings();
                }
                await refreshPushStatus();
            });
        }

        if (locToggle) {
            locToggle.addEventListener('change', () => {
                if (locToggle.checked) {
                    enableLocationTracking();
                } else {
                    disableLocationTracking();
                }
                refreshLocationStatus();
            });
        }

        refreshPushStatus();
        refreshLocationStatus();
        loadManagerOptions();
    });

    function goBackToPanel() {
        window.location.href = 'panel.php';
    }
</script>
</body>
</html>
