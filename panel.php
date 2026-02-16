<?php
require_once __DIR__ . '/core/session.php';
require_once __DIR__ . '/core/auth.php';

// Panel pracownika: wymaga zalogowanego użytkownika (pracownika)
$user = requireUser();

// Status ostatniej zakończonej sesji pracy (informacja o akceptacji/odrzuceniu)
$lastSessionStatusText  = '';
$lastSessionStatusClass = '';

try {
    $pdo = require __DIR__ . '/core/db.php';

    $stmt = $pdo->prepare(
        "SELECT\n" .
        "    ws.status,\n" .
        "    ws.site_name,\n" .
        "    ws.manager_comment,\n" .
        "    m.first_name AS manager_first_name,\n" .
        "    m.last_name  AS manager_last_name\n" .
        "FROM work_sessions ws\n" .
        "LEFT JOIN managers m ON m.id = ws.manager_id\n" .
        "WHERE ws.employee_id = ?\n" .
        "  AND ws.end_time IS NOT NULL\n" .
        "  AND DATE(ws.start_time) = CURDATE()\n" .
        "  AND (ws.absence_group_id IS NULL OR ws.absence_group_id = 0)\n" .
        "ORDER BY ws.end_time DESC\n" .
        "LIMIT 1"
    );
    $stmt->execute([$user['id']]);
    $last = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($last) {
        $siteLabel = $last['site_name'] ?? '';
        if ($siteLabel !== '') {
            $siteLabel = ' na budowie "' . $siteLabel . '"';
        }

        $commentText = trim((string)($last['manager_comment'] ?? ''));
        $managerName = '';
        if (!empty($last['manager_first_name']) || !empty($last['manager_last_name'])) {
            $managerName = trim(($last['manager_first_name'] ?? '') . ' ' . ($last['manager_last_name'] ?? ''));
        }

        // Ujednolicenie statusu do wielkich liter – po migracji
        // kolumna status ma wartości ENUM('OK','AUTO','MANUAL','PENDING','REJECTED').
        $status = strtoupper((string)($last['status'] ?? ''));

        // Sesja oczekująca na akceptację – traktujemy zarówno PENDING,
        // jak i AUTO (sesje domknięte automatycznie przez CRON,
        // które nadal wymagają decyzji kierownika).
        if ($status === 'PENDING' || $status === 'AUTO') {
            $lastSessionStatusText  = 'Twoja sesja pracy' . $siteLabel . ' została przesłana do akceptacji do kierownika.';
            $lastSessionStatusClass = 'pending';
        } elseif ($status === 'MANUAL') {
            $lastSessionStatusText  = 'Twoja sesja pracy' . $siteLabel . ' została zaakceptowana przez kierownika.';
            if ($commentText !== '') {
                if ($managerName !== '') {
                    $lastSessionStatusText .= ' Komentarz (' . $managerName . '): ' . $commentText;
                } else {
                    $lastSessionStatusText .= ' Komentarz: ' . $commentText;
                }
            }
            $lastSessionStatusClass = 'approved';
        } elseif ($status === 'OK') {
            // Zwykła poprawnie zakończona sesja (bez dodatkowego workflow)
            $lastSessionStatusText  = 'Twoja sesja pracy' . $siteLabel . ' została zapisana w systemie.';
            $lastSessionStatusClass = 'approved';
        } elseif ($status === 'REJECTED') {
            $lastSessionStatusText = 'Twoja sesja pracy';
            if ($managerName !== '') {
                $lastSessionStatusText .= ' został odrzucony przez ' . $managerName . '.';
            } else {
                $lastSessionStatusText .= ' został odrzucony przez kierownika.';
            }

            if ($commentText !== '') {
                $lastSessionStatusText .= ' Powód: ' . $commentText;
            }

            $lastSessionStatusText .= ' Skontaktuj się ze swoim kierownikiem w celu wyjaśnienia.';
            $lastSessionStatusClass = 'rejected';
        }
    }
} catch (Throwable $e) {
    // W razie problemu z bazą po prostu nie pokazujemy komunikatu
}
?>
<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel Pracownika - RCP System</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, sans-serif;
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            overflow-x: hidden;
            overflow-y: auto;
            padding: 20px 10px;
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
            max-width: 600px;
            width: 100%;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        }

        .header {
            text-align: center;
            margin-bottom: 40px;
        }

        .header h1 {
            color: #333;
            font-size: 32px;
            margin-bottom: 10px;
        }

        .header .user-info {
            color: #666;
            font-size: 18px;
        }

        .header .user-name {
            font-weight: bold;
            color: #667eea;
        }

        .modules {
            display: flex;
            flex-direction: column;
            gap: 10px;
            margin-bottom: 30px;
        }

        /* Nowy styl przycisków mobilnych */
        .mobile-btn {
            display: flex;
            align-items: center;
            gap: 16px;
            padding: 18px 22px;
            margin: 8px 0;
            border-radius: 18px;
            color: #fff;
            text-decoration: none;
            font-size: 18px;
            font-weight: 600;
            letter-spacing: 0.2px;
            box-shadow:
                0 10px 25px rgba(0,0,0,0.25),
                inset 0 0 0 1px rgba(255,255,255,0.15);
            backdrop-filter: blur(6px);
            transition: all 0.2s ease;
            border: none;
            background: linear-gradient(135deg, #667eea, #764ba2);
            width: 100%;
            cursor: pointer;
        }

        .mobile-btn .icon {
            font-size: 28px;
            width: 40px;
            text-align: center;
        }

        .mobile-btn .label {
            flex: 1;
            text-align: left;
        }

        .mobile-btn:active {
            transform: scale(0.97);
            box-shadow: 0 6px 15px rgba(0,0,0,0.3);
        }

        /* Warianty kolorystyczne */
        .mobile-btn.blue {
            background: linear-gradient(135deg, #4facfe, #00f2fe);
        }

        .mobile-btn.purple {
            background: linear-gradient(135deg, #7f7fd5, #86a8e7, #91eae4);
        }

        .mobile-btn.violet {
            background: linear-gradient(135deg, #667eea, #764ba2);
        }

        .mobile-btn.pink {
            background: linear-gradient(135deg, #ff758c, #ff7eb3);
        }

        .mobile-btn.dark {
            background: linear-gradient(135deg, #434343, #000000);
        }

        .mobile-btn.disabled {
            opacity: 0.4;
            pointer-events: none;
        }

        .settings-center {
            margin: 10px 0 25px;
            text-align: center;
        }

        .settings-center button {
            display: inline-block;
            padding: 8px 18px;
            border-radius: 999px;
            border: 1px dashed #667eea;
            background: #ffffff;
            color: #333;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .settings-center button:hover {
            background: #eef2ff;
            box-shadow: 0 4px 10px rgba(102, 126, 234, 0.25);
        }

        .wz-badge {
            margin-left: auto;
            padding: 4px 10px;
            border-radius: 999px;
            background: #ffc107;
            color: #000;
            font-size: 12px;
            font-weight: 700;
            box-shadow: 0 0 10px rgba(0,0,0,0.2);
            animation: wz-pulse 1.5s infinite;
        }

        @keyframes wz-pulse {
            0% {
                transform: scale(1);
                box-shadow: 0 0 0 0 rgba(255, 193, 7, 0.7);
            }
            60% {
                transform: scale(1.06);
                box-shadow: 0 0 0 12px rgba(255, 193, 7, 0);
            }
            100% {
                transform: scale(1);
                box-shadow: 0 0 0 0 rgba(255, 193, 7, 0);
            }
        }

        .status-bar {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            text-align: center;
        }

        .status-bar .status {
            font-size: 16px;
            margin-bottom: 10px;
        }

        .status-bar .active {
            color: #28a745;
            font-weight: bold;
        }

        .status-bar .inactive {
            color: #6c757d;
        }

        .work-info {
            display: none;
            margin-top: 10px;
            padding: 10px;
            background: #e7f3ff;
            border-radius: 8px;
            font-size: 14px;
        }

        .work-info.show {
            display: block;
        }

        .approval-status {
            margin-top: 8px;
            font-size: 14px;
            text-align: center;
        }

        .approval-status.pending {
            color: #856404;
            background: #fff3cd;
            padding: 8px 10px;
            border-radius: 6px;
            border-left: 4px solid #ffc107;
        }

        .approval-status.approved {
            color: #155724;
            background: #d4edda;
            padding: 8px 10px;
            border-radius: 6px;
            border-left: 4px solid #28a745;
        }

        .approval-status.rejected {
            color: #721c24;
            background: #f8d7da;
            padding: 8px 10px;
            border-radius: 6px;
            border-left: 4px solid #dc3545;
        }

        .manager-comment {
            margin-top: 10px;
            padding: 8px 10px;
            background: #ffeeba;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 600;
            color: #856404;
            border-left: 4px solid #ff8800;
            text-align: left;
        }

        .push-banner {
            margin-bottom: 15px;
            padding: 10px 14px;
            border-radius: 10px;
            background: #fff3cd;
            color: #856404;
            border-left: 4px solid #ffc107;
            font-size: 14px;
            display: none;
        }

        .push-banner button {
            margin-top: 6px;
            padding: 6px 10px;
            border-radius: 6px;
            border: none;
            background: #667eea;
            color: #fff;
            font-weight: 600;
            cursor: pointer;
        }

        .logout-btn {
            background: #6c757d;
            color: white;
            border: none;
            border-radius: 10px;
            padding: 15px 30px;
            font-size: 16px;
            cursor: pointer;
            width: 100%;
            transition: all 0.3s ease;
        }

        .logout-btn:hover {
            background: #5a6268;
        }

        @media (max-width: 768px) {
            body {
                align-items: flex-start;
                padding: 10px 6px;
            }

            .container {
                max-width: 100%;
                padding: 20px 16px;
                border-radius: 12px;
                box-shadow: 0 10px 30px rgba(0,0,0,0.25);
            }

            .header h1 {
                font-size: 24px;
            }

            .header .user-info {
                font-size: 16px;
            }

            .modules {
                grid-template-columns: 1fr;
                gap: 14px;
            }

            .module-btn {
                padding: 20px 16px;
                font-size: 16px;
            }

            .module-btn .icon {
                font-size: 32px;
            }

            .status-bar {
                padding: 16px;
            }

            .logout-btn {
                padding: 12px 20px;
                font-size: 15px;
            }
        }
    </style>
</head>
<body>
    <video class="bg-video" autoplay loop muted playsinline>
        <source src="background.mp4" type="video/mp4">
    </video>
    <div class="video-overlay"></div>
    <div class="container">
        <div class="header">
            <h1>🏗️ Panel Pracownika</h1>
            <div class="user-info">
                Witaj, <span class="user-name" id="userName">Ładowanie...</span>
            </div>
        </div>

        <div class="status-bar">
            <div class="status" id="workStatus">
                <span class="inactive">❌ Aktualnie nie masz rozpoczętej pracy</span>
            </div>
            <div class="work-info" id="workInfo"></div>
            <?php if ($lastSessionStatusText): ?>
                <div class="approval-status <?php echo htmlspecialchars($lastSessionStatusClass, ENT_QUOTES, 'UTF-8'); ?>">
                    <?php echo htmlspecialchars($lastSessionStatusText, ENT_QUOTES, 'UTF-8'); ?>
                </div>
            <?php endif; ?>
        </div>

        <div id="pushBanner" class="push-banner">
            🔔 Możesz włączyć powiadomienia (PUSH).<br>
            <button id="pushButton" type="button">Włącz powiadomienia</button>
        </div>
        <div id="managerHintBanner" class="push-banner">
            👨‍💼 Nie masz jeszcze wybranego kierownika. Wejdź w Ustawienia i przypisz kierownika, do którego podlegasz.
        </div>

        <div class="modules">
            <a href="#" class="mobile-btn blue" id="btnWork" onclick="handleWork(); return false;">
                <span class="icon">⏱️</span>
                <span class="label" id="workBtnText">Rozpocznij / Zakończ pracę</span>
            </a>

            <a href="modules/absences/my_absences.php" class="mobile-btn purple">
                <span class="icon">🏖️</span>
                <span class="label">Moje nieobecności</span>
            </a>

            <a href="modules/reports/my_report.php" class="mobile-btn violet">
                <span class="icon">📊</span>
                <span class="label">Moje statystyki</span>
            </a>
        </div>

        <div class="settings-center">
            <button type="button" onclick="go('settings.php')">
                ⚙️ Ustawienia
            </button>
        </div>

        <button class="logout-btn" onclick="logout()">
            🚪 Wyloguj się
        </button>
    </div>

    <script>
        let activeSession = null;
        let isManager = false;
        let isOperator = false;        // surowa flaga (dowolny operator)
        let operatorRole = 0;          // wartość is_operator z bazy (0,1,2,...)
        let isMachineOperator = false; // is_operator === 1
        let isDriver = false;          // is_operator === 2
        let isWzOperator = false;      // is_operator === 3 (specjalny operator WZ)
        let pushInitialized = false;
        let locationRequested = false; // czy już prosiliśmy o lokalizację na tej stronie
        let employeeId = null;

        // Publiczny klucz VAPID musi być TYM SAMYM, co w push_config.php
        const VAPID_PUBLIC_KEY = 'BDttZpGMOcEb1OZNSg_eGC5FkMmPXMAl1pvlksZj923I2qWESy47AHVtHMRHCPJbxpVx9TnMQohVxAMsk1U5rhs';

        function isLocationTrackingDisabled() {
            try {
                return localStorage.getItem('rcp_disable_location') === '1';
            } catch (e) {
                console.error('Błąd odczytu ustawienia lokalizacji z localStorage:', e);
                return false;
            }
        }

        async function init() {
            try {
                const res = await fetch('modules/work/check_status.php');
                const data = await res.json();

                if (!data.logged_in) {
                    window.location.href = 'index.html';
                    return;
                }

                // Sprawdź typ konta
                isManager = data.is_manager || false;
                operatorRole = parseInt(data.is_operator ?? 0, 10) || 0;
                isOperator = !!operatorRole;
                isMachineOperator = operatorRole === 1;
                isDriver = operatorRole === 2;
                isWzOperator = operatorRole === 3;

                // Wyświetl nazwę użytkownika
                if (isManager) {
                    document.getElementById('userName').textContent = data.manager_name;
                    document.querySelector('.header h1').textContent = '👔 Panel Kierownika';
                    if (data.manager_id) {
                        setupManagerPushBanner(data.manager_id);
                    }
                } else {
                    document.getElementById('userName').textContent = 
                        data.first_name + ' ' + data.last_name;
                    if (!data.manager_id) {
                        const mgrBanner = document.getElementById('managerHintBanner');
                        if (mgrBanner) {
                            mgrBanner.style.display = 'block';
                        }
                    }
                }

                // Status pracy (tylko dla pracowników)
                if (!isManager) {
                    if (data.active_work) {
                        activeSession = data.active_work;
                        showActiveWork();
                    } else {
                        showInactive();
                    }

                    if (data.user_id) {
                        employeeId = data.user_id;
                        setupPushBanner(data.user_id);
                    }
                } else {
                    // Ukryj status pracy dla kierownika
                    document.querySelector('.status-bar').style.display = 'none';
                }

                // Poproś o lokalizację już przy wejściu do panelu (tylko pracownik, jeśli lokalizacja nie jest wyłączona)
                if (!isManager && !locationRequested && 'geolocation' in navigator && !isLocationTrackingDisabled()) {
                    locationRequested = true;
                    navigator.geolocation.getCurrentPosition(
                        pos => {
                            try {
                                sessionStorage.setItem('start_lat', String(pos.coords.latitude));
                                sessionStorage.setItem('start_lng', String(pos.coords.longitude));
                            } catch (e) {
                                console.error('Nie udało się zapisać lokalizacji w sessionStorage (init):', e);
                            }
                        },
                        err => {
                            console.warn('Geolokalizacja przy wejściu do panelu nieudana:', err);
                        },
                        { timeout: 5000 }
                    );
                }

                // Załaduj odpowiednie moduły
                loadModules();

                // Jeśli kierowca lub operator WZ – odśwież badge WZ po zbudowaniu modułów
                if (!isManager && (isDriver || isWzOperator)) {
                    updateOperatorWzBadge();
                }
            } catch (err) {
                console.error('Błąd inicjalizacji:', err);
            }
        }

        function setupPushBanner(employeeId) {
            const banner = document.getElementById('pushBanner');
            const btn = document.getElementById('pushButton');

            if (!banner || !btn) return;

            if (!('Notification' in window) || !('serviceWorker' in navigator) || !('PushManager' in window)) {
                banner.style.display = 'none';
                return;
            }

            if (!VAPID_PUBLIC_KEY) {
                banner.style.display = 'none';
                return;
            }

            const perm = Notification.permission;
            if (perm === 'granted') {
                banner.style.display = 'none';
                if (!pushInitialized) {
                    enablePush(employeeId).catch(console.error);
                    pushInitialized = true;
                }
                return;
            }

            if (perm === 'denied') {
                banner.style.display = 'none';
                return;
            }

            banner.style.display = 'block';
            btn.onclick = () => {
                enablePush(employeeId).catch(console.error);
            };
        }

        function setupManagerPushBanner(managerId) {
            const banner = document.getElementById('pushBanner');
            const btn = document.getElementById('pushButton');

            if (!banner || !btn) return;

            if (!('Notification' in window) || !('serviceWorker' in navigator) || !('PushManager' in window)) {
                banner.style.display = 'none';
                return;
            }

            if (!VAPID_PUBLIC_KEY) {
                banner.style.display = 'none';
                return;
            }

            const perm = Notification.permission;
            if (perm === 'granted') {
                banner.style.display = 'none';
                if (!pushInitialized) {
                    enableManagerPush(managerId).catch(console.error);
                    pushInitialized = true;
                }
                return;
            }

            if (perm === 'denied') {
                banner.style.display = 'none';
                return;
            }

            banner.style.display = 'block';
            btn.onclick = () => {
                enableManagerPush(managerId).catch(console.error);
            };
        }

        function loadModules() {
            const modulesContainer = document.querySelector('.modules');
            modulesContainer.innerHTML = '';

            if (isManager) {
                // Moduły dla kierownika – nowy styl przycisków mobilnych
                modulesContainer.innerHTML = `
                    <a href="panel/employees.php" class="mobile-btn violet">
                        <span class="icon">👥</span>
                        <span class="label">Pracownicy</span>
                    </a>

                    <a href="panel/machines.php" class="mobile-btn purple">
                        <span class="icon">🚜</span>
                        <span class="label">Maszyny</span>
                    </a>

                    <a href="panel/sites.php" class="mobile-btn violet">
                        <span class="icon">🏗️</span>
                        <span class="label">Budowy</span>
                    </a>

                    <a href="panel/absences.php" class="mobile-btn purple">
                        <span class="icon">📅</span>
                        <span class="label">Nieobecności</span>
                    </a>

                    <a href="panel/dashboard.php" class="mobile-btn blue">
                        <span class="icon">📊</span>
                        <span class="label">Raporty</span>
                    </a>

                    <a href="modules/work/step_building.php" class="mobile-btn blue">
                        <span class="icon">⏱️</span>
                        <span class="label">Moja praca</span>
                    </a>
                `;
            } else {
                // Moduły dla pracownika – nowy styl przycisków mobilnych
                // Na start: praca + nieobecności, statystyki dokładamy na dole
                let moduleHTML = `
                    <a href="#" class="mobile-btn blue" id="btnWork" onclick="handleWork(); return false;">
                        <span class="icon">⏱️</span>
                        <span class="label" id="workBtnText">Rozpocznij / Zakończ pracę</span>
                    </a>

                    <a href="modules/absences/my_absences.php" class="mobile-btn purple">
                        <span class="icon">🏖️</span>
                        <span class="label">Moje nieobecności</span>
                    </a>
                `;

                // Dodatkowe moduły dla operatorów wg roli
                // Tankowanie dla operatorów maszyn (1), kierowców (2) ORAZ operatorów WZ (3)
                if (isMachineOperator || isDriver || isWzOperator) {
                    const hasBlockedFueling =
                        isMachineOperator &&
                        activeSession &&
                        (String(activeSession.registry_number) === '1' || String(activeSession.machine_id) === '1');

                    if (hasBlockedFueling) {
                        moduleHTML += `
                            <a href="#" class="mobile-btn pink disabled" onclick="showFuelingBlockedMessage(); return false;">
                                <span class="icon">⛽</span>
                                <span class="label">Tankowanie maszyny</span>
                            </a>
                        `;
                    } else {
                        moduleHTML += `
                            <a href="modules/fueling/index.php" class="mobile-btn pink">
                                <span class="icon">⛽</span>
                                <span class="label">Tankowanie maszyny</span>
                            </a>
                        `;
                    }
                }

                // Dokumenty WZ dla kierowców (2) ORAZ operatorów WZ (3)
                if (isDriver || isWzOperator) {
                    moduleHTML += `
                        <a href="modules/wz/operator.php" class="mobile-btn dark">
                            <span class="icon">📄</span>
                            <span class="label">Dokumenty WZ</span>
                            <span id="wzBadge" class="wz-badge" style="display:none;"></span>
                        </a>
                    `;
                }

                // Moje statystyki zawsze na dole listy, pod ewentualnym WZ
                moduleHTML += `
                    <a href="modules/reports/my_report.php" class="mobile-btn violet">
                        <span class="icon">📊</span>
                        <span class="label">Moje statystyki</span>
                    </a>
                `;

                modulesContainer.innerHTML = moduleHTML;
            }
        }

        async function updateOperatorWzBadge() {
            if (!isDriver && !isWzOperator) return;

            const badge = document.getElementById('wzBadge');
            if (!badge) return;

            try {
                const res = await fetch('modules/wz/operator_status.php');
                const data = await res.json();

                const waiting = (data && data.success && typeof data.waiting === 'number') ? data.waiting : 0;

                badge.style.display = 'inline-block';
                if (waiting > 0) {
                    badge.textContent = `WZ do potwierdzenia: ${waiting}`;
                } else {
                    badge.textContent = 'Brak WZ do potwierdzenia';
                }
            } catch (e) {
                console.error('Błąd odczytu statusu WZ operatora:', e);
            }
        }

        function showActiveWork() {
            document.getElementById('workStatus').innerHTML = 
                '<span class="active">✅ Pracujesz</span>';

            const commentHtml = activeSession.manager_comment
                ? `<div class="manager-comment">💼 Komentarz kierownika: ${activeSession.manager_comment}</div>`
                : '';

            document.getElementById('workInfo').innerHTML = `
                <strong>Budowa:</strong> ${activeSession.site_name}<br>
                ${activeSession.machine_name ? `<strong>Maszyna:</strong> ${activeSession.machine_name}<br>` : ''}
                <strong>Start:</strong> ${formatTime(activeSession.start_time)}
                ${commentHtml}
            `;
            document.getElementById('workInfo').classList.add('show');

            const btn = document.getElementById('btnWork');
            btn.classList.remove('success');
            btn.classList.add('danger');
            btn.querySelector('#workBtnText').textContent = 'Rozpocznij / Zakończ pracę';
        }

        function showInactive() {
            document.getElementById('workStatus').innerHTML = 
                '<span class="inactive">❌ Aktualnie nie masz rozpoczętej pracy </span>';
            document.getElementById('workInfo').classList.remove('show');

            const btn = document.getElementById('btnWork');
            btn.classList.remove('danger');
            btn.classList.add('success');
            btn.querySelector('#workBtnText').textContent = 'Rozpocznij / Zakończ pracę';
        }

        function handleWork() {
            if (activeSession) {
                // Jeśli jest aktywna sesja – idziemy od razu do STOP
                window.location.href = 'modules/work/stop_work.php';
                return;
            }

            // Brak aktywnej sesji – START pracy.
            // Już na tym etapie próbujemy pobrać lokalizację
            // i zapisać ją w sessionStorage, żeby wykorzystać
            // ją później w modules/work/step_confirm.php.

            const goToBuildingStep = () => {
                window.location.href = 'modules/work/step_building.php';
            };

            // Jeśli użytkownik wyłączył lokalizację przy START/STOP,
            // nie próbujemy w ogóle pobierać GPS – od razu przechodzimy dalej.
            if (isLocationTrackingDisabled()) {
                try {
                    sessionStorage.removeItem('start_lat');
                    sessionStorage.removeItem('start_lng');
                } catch (e) {
                    console.error('Błąd czyszczenia współrzędnych z sessionStorage przy wyłączonej lokalizacji:', e);
                }
                goToBuildingStep();
                return;
            }

            // Jeżeli lokalizacja została już zapisana przy wejściu do panelu,
            // nie pytamy drugi raz – od razu przechodzimy dalej.
            try {
                const storedLat = sessionStorage.getItem('start_lat');
                const storedLng = sessionStorage.getItem('start_lng');
                if (storedLat && storedLng) {
                    goToBuildingStep();
                    return;
                }
            } catch (e) {
                console.error('Błąd odczytu lokalizacji z sessionStorage w handleWork:', e);
            }

            if ('geolocation' in navigator) {
                navigator.geolocation.getCurrentPosition(
                    pos => {
                        try {
                            sessionStorage.setItem('start_lat', String(pos.coords.latitude));
                            sessionStorage.setItem('start_lng', String(pos.coords.longitude));
                        } catch (e) {
                            console.error('Nie udało się zapisać lokalizacji w sessionStorage:', e);
                        }
                        goToBuildingStep();
                    },
                    () => {
                        // Brak zgody / błąd GPS – przechodzimy dalej,
                        // backend zrobi fallback na IP.
                        goToBuildingStep();
                    },
                    { timeout: 2000 }
                );
            } else {
                // Brak wsparcia geolokalizacji – przechodzimy dalej.
                goToBuildingStep();
            }
        }

        function showFuelingBlockedMessage() {
            alert('Tankowanie tej maszyny jest zablokowane.\nNie pracujesz na maszynie.\nZakończ pracę fizyczną,\na następnie rozpocznij pracę na maszynie\naby tankować.');
        }

        function go(url) {
            window.location.href = url;
        }

        async function logout() {
            if (activeSession) {
                alert('Najpierw zakończ pracę!');
                return;
            }

            if (confirm('Czy na pewno chcesz się wylogować?')) {
                await fetch('modules/work/logout.php');
                window.location.href = 'index.html';
            }
        }

        function formatTime(datetime) {
            return new Date(datetime).toLocaleString('pl-PL', {
                year: 'numeric',
                month: '2-digit',
                day: '2-digit',
                hour: '2-digit',
                minute: '2-digit'
            });
        }

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
                return;
            }

            if (!VAPID_PUBLIC_KEY) {
                return;
            }

            const permission = await Notification.requestPermission();
            if (permission !== 'granted') return;

            const reg = await navigator.serviceWorker.register('service-worker.js');
            
            // Uruchom wewnętrzny scheduler
            if (reg.active) {
                reg.active.postMessage({ type: 'START_SCHEDULER' });
            }
            navigator.serviceWorker.ready.then(registration => {
                if (registration.active) {
                    registration.active.postMessage({ type: 'START_SCHEDULER' });
                }
            });

            const sub = await reg.pushManager.subscribe({
                userVisibleOnly: true,
                applicationServerKey: urlBase64ToUint8Array(VAPID_PUBLIC_KEY)
            });

            await fetch('api.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'subscribe_push',
                    employee_id: employeeId,
                    subscription: sub
                })
            });

            pushInitialized = true;

            const banner = document.getElementById('pushBanner');
            if (banner) {
                banner.style.display = 'none';
            }
        }

        async function enableManagerPush(managerId) {
            if (!('Notification' in window) || !('serviceWorker' in navigator) || !('PushManager' in window)) {
                return;
            }

            if (!VAPID_PUBLIC_KEY) {
                return;
            }

            const reg = await navigator.serviceWorker.register('service-worker.js');
            
            // Uruchom wewnętrzny scheduler
            if (reg.active) {
                reg.active.postMessage({ type: 'START_SCHEDULER' });
            }
            navigator.serviceWorker.ready.then(registration => {
                if (registration.active) {
                    registration.active.postMessage({ type: 'START_SCHEDULER' });
                }
            });

            const sub = await reg.pushManager.subscribe({
                userVisibleOnly: true,
                applicationServerKey: urlBase64ToUint8Array(VAPID_PUBLIC_KEY)
            });

            await fetch('api.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'subscribe_push_manager',
                    subscription: sub
                })
            });

            pushInitialized = true;

            const banner = document.getElementById('pushBanner');
            if (banner) {
                banner.style.display = 'none';
            }
        }

        init();

        // Odświeżaj status co 30 sekund
        setInterval(init, 30000);
    </script>
</body>
</html>
