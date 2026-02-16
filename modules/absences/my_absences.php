<?php
/**
 * Moduł Nieobecności - Moje wnioski urlopowe i L4
 */

require_once __DIR__.'/../../core/session.php';
require_once __DIR__.'/../../core/auth.php';

$user = requireUser();
$employee_id = $user['id'];

// Pobierz liczbę pozostałych dni urlopu dla zalogowanego pracownika
$vacationDays = 0;
try {
    $pdo = require __DIR__ . '/../../core/db.php';
    $stmt = $pdo->prepare('SELECT COALESCE(vacation_days, 0) AS vacation_days FROM employees WHERE id = ?');
    $stmt->execute([$employee_id]);
    $row = $stmt->fetch();
    if ($row) {
        $vacationDays = (int)$row['vacation_days'];
    }
} catch (Throwable $e) {
    // W razie błędu po prostu pokaż 0, bez wywalania modułu
    $vacationDays = 0;
}
?>
<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Moje nieobecności</title>

    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        .container {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 20px;
            padding: 40px;
            max-width: 900px;
            margin: 0 auto;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        }
        h1 {
            color: #333;
            margin-bottom: 30px;
            font-size: 28px;
        }
        .tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 30px;
            border-bottom: 2px solid #ddd;
        }
        .tab {
            padding: 12px 24px;
            cursor: pointer;
            border: none;
            background: transparent;
            font-size: 16px;
            font-weight: 600;
            color: #666;
            border-bottom: 3px solid transparent;
            transition: all 0.3s;
        }
        .tab.active {
            color: #667eea;
            border-bottom-color: #667eea;
        }
        .tab-content {
            display: none;
        }
        .tab-content.active {
            display: block;
        }
        .form-group {
    	display: flex;
    	flex-direction: column;
    	gap: 4px;
        }
        label {
    	display: inline-block;
    	font-size: 14px;
    	font-weight: 600;
    	color: #444;
        }

        input, select, textarea {
    width: 100%;
    padding: 12px;
    border: 2px solid #ddd;
    border-radius: 8px;
    font-size: 16px;
    font-family: inherit;
    box-sizing: border-box;
}
        input:focus, select:focus, textarea:focus {
            outline: none;
            border-color: #667eea;
        }
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        .btn {
            padding: 14px 28px;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(102, 126, 234, 0.4);
        }
        .btn-back {
            background: #6c757d;
            color: white;
        }
        .btn-back:hover {
            background: #5a6268;
        }
        .requests-list {
            margin-top: 30px;
        }
        .request-card {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 15px;
            border-left: 4px solid #667eea;
        }
        .request-card.pending {
            border-left-color: #ffc107;
        }
        .request-card.approved {
            border-left-color: #28a745;
        }
        .request-card.rejected {
            border-left-color: #dc3545;
        }
        .request-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }
        .request-dates {
            font-size: 18px;
            font-weight: 600;
            color: #333;
        }
        .status-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
        }
        .status-pending {
            background: #fff3cd;
            color: #856404;
        }
        .status-approved {
            background: #d4edda;
            color: #155724;
        }
        .status-rejected {
            background: #f8d7da;
            color: #721c24;
        }
        .request-info {
            color: #666;
            font-size: 14px;
            line-height: 1.6;
        }
        .message {
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            display: none;
        }
        .message.success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .message.error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #999;
        }
        .empty-state-icon {
            font-size: 60px;
            margin-bottom: 20px;
        }
        .auto-refresh-indicator {
            position: fixed;
            bottom: 20px;
            right: 20px;
            background: rgba(102, 126, 234, 0.9);
            color: white;
            padding: 10px 15px;
            border-radius: 20px;
            font-size: 12px;
            display: none;
            align-items: center;
            gap: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.2);
        }
        .auto-refresh-indicator.active {
            display: flex;
        }
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }
        .pulse-icon {
            animation: pulse 2s infinite;
        }

        .header-row {
            display:flex;
            justify-content:space-between;
            align-items:center;
            margin-bottom:20px;
            gap:16px;
            flex-wrap:wrap;
        }

        .vacation-badge {
            margin-left:auto;
            background:#f1f3ff;
            border-radius:999px;
            padding:8px 18px;
            font-size:14px;
            color:#333;
            border:1px solid #d0d4ff;
            display:flex;
            align-items:center;
            gap:8px;
            white-space:nowrap;
        }

        /* ===== MOBILE STYLES ===== */
        @media (max-width: 600px) {
            body {
                padding: 12px;
            }
            .container {
                padding: 20px;
                border-radius: 16px;
            }
            h1 {
                font-size: 22px;
                text-align: left;
            }
            .header-row {
                flex-direction: column;
                align-items: flex-start;
            }
            .vacation-badge {
                margin-left: 0;
                width: 100%;
                justify-content: flex-start;
                flex-wrap: wrap;
                row-gap: 4px;
                font-size: 13px;
            }
            .form-row {
                grid-template-columns: 1fr;
                gap: 12px;
            }
            input, select, textarea {
                font-size: 15px;
                padding: 10px;
            }
            .tabs {
                flex-wrap: wrap;
            }
            .tab {
                flex: 1 1 50%;
                text-align: center;
                font-size: 14px;
                padding: 10px 8px;
            }
            .btn {
                width: 100%;
                text-align: center;
            }
            .btn-back,
            .btn-primary {
                font-size: 15px;
                padding: 12px 16px;
            }
            .requests-list {
                margin-top: 20px;
            }
            .request-card {
                padding: 16px;
            }
            .request-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 6px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header-row">
            <h1 style="margin:0;">🏖️ Moje nieobecności</h1>
            <div class="vacation-badge">
                <span style="font-size:18px;">📅</span>
                <span>Pozostało urlopu:</span>
                <strong><?= (int)$vacationDays ?></strong>
                <span>dni</span>
            </div>
        </div>

        <div class="tabs">
            <button class="tab active" onclick="switchTab.call(this, 'new-request')">📝 Nowy wniosek</button>
            <button class="tab" onclick="switchTab.call(this, 'my-requests')">📋 Moje wnioski</button>
        </div>

        <div id="message" class="message"></div>

        <!-- Formularz nowego wniosku -->
        <div id="tab-new-request" class="tab-content active">
            <form id="requestForm">
                <div class="form-row">
                    <div class="form-group">
                        <label>Data rozpoczęcia</label>
                        <input type="date" name="start_date" required id="startDate">
                    </div>
                    <div class="form-group">
                        <label>Data zakończenia</label>
                        <input type="date" name="end_date" required id="endDate">
                    </div>
                </div>

                <div class="form-group">
                    <label>Typ nieobecności</label>
                    <select name="type" required>
                        <option value="urlop">🏖️ Urlop</option>
                        <option value="L4">🏥 Zwolnienie lekarskie (L4)</option>
                        <option value="inny">📄 Inna nieobecność</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Powód (opcjonalnie)</label>
                    <textarea name="reason" rows="4" placeholder="Np. urlop wypoczynkowy, wizyta u lekarza..."></textarea>
                </div>
            </form>
        </div>

        <!-- Lista wniosków -->
        <div id="tab-my-requests" class="tab-content">
            <div class="requests-list" id="requestsList">
                <div class="empty-state">
                    <div class="empty-state-icon">⏳</div>
                    <p>Ładowanie wniosków...</p>
                </div>
            </div>
        </div>

        <!-- Przyciski akcji -->
        <div style="display: flex; gap: 15px; margin-top: 30px; justify-content: center;">
            <button onclick="document.getElementById('requestForm').requestSubmit()" class="btn btn-primary">✅ Złóż wniosek</button>
            <a href="#" onclick="goBackToEntry('../../panel.php'); return false;" class="btn btn-back" style="text-decoration: none; display: inline-block;">⬅️ Powrót</a>
        </div>
    </div>

    <!-- Wskaźnik auto-odświeżania -->
    <div class="auto-refresh-indicator" id="autoRefreshIndicator">
        <span class="pulse-icon">🔄</span>
        <span>Auto-odświeżanie włączone</span>
    </div>

    <script>
        function goBackToEntry(fallbackUrl) {
            if (document.referrer) {
                window.location.href = document.referrer;
            } else {
                window.location.href = fallbackUrl;
            }
        }

        const employeeId = <?= $employee_id ?>;

        // Przełączanie zakładek
        function switchTab(tabName) {
            document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
            document.querySelectorAll('.tab-content').forEach(tc => tc.classList.remove('active'));
            
            this.classList.add('active');
            document.getElementById('tab-' + tabName).classList.add('active');

            if (tabName === 'my-requests') {
                loadRequests();
                startAutoRefresh(); // Włącz auto-odświeżanie gdy jesteśmy na zakładce
            } else {
                stopAutoRefresh(); // Wyłącz gdy nie na zakładce
            }
        }

        // Ustawienie minimalnej daty (dzisiaj)
        const today = new Date().toISOString().split('T')[0];
        document.getElementById('startDate').min = today;
        document.getElementById('endDate').min = today;

        // Obsługa formularza
        document.getElementById('requestForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            
            const formData = new FormData(e.target);
            const data = Object.fromEntries(formData);

            try {
                // Sprawdź czy nie ma konfliktu dat z istniejącymi wnioskami i sesjami pracy
                const [checkResponse, manualResponse, workSessionsResponse] = await Promise.all([
                    fetch(`../../absence_requests_api.php?action=list&employee_id=${employeeId}`),
                    fetch('get_manual_absences.php'),
                    fetch(`check_work_sessions.php?start_date=${data.start_date}&end_date=${data.end_date}`)
                ]);
                
                const checkResult = await checkResponse.json();
                const manualResult = await manualResponse.json().catch(() => ({ success: false, absences: [] }));
                const workSessionsResult = await workSessionsResponse.json();
                
                // Sprawdź sesje pracy
                if (workSessionsResult.success && workSessionsResult.has_work_sessions) {
                    const workDatesText = workSessionsResult.work_dates
                        .map(date => new Date(date).toLocaleDateString('pl-PL'))
                        .join(', ');
                    
                    showMessage(
                        `❌ Nie możesz złożyć wniosku - masz już sesje pracy w dniach: ${workDatesText}`,
                        'error'
                    );
                    return;
                }
                
                const allRequests = [];
                if (checkResult.success && checkResult.requests) {
                    allRequests.push(...checkResult.requests);
                }
                if (manualResult.success && manualResult.absences) {
                    allRequests.push(...manualResult.absences);
                }
                
                if (allRequests.length > 0) {
                    const newStart = new Date(data.start_date);
                    const newEnd = new Date(data.end_date);
                    
                    const conflicts = allRequests.filter(req => {
                        if (req.status !== 'approved' && req.status !== 'pending') return false;
                        
                        const existingStart = new Date(req.start_date);
                        const existingEnd = new Date(req.end_date);
                        
                        // Sprawdź czy zakresy dat się pokrywają
                        return newStart <= existingEnd && newEnd >= existingStart;
                    });
                    
                    if (conflicts.length > 0) {
                        const conflictDetails = conflicts.map(req => {
                            const source = req.source === 'manual' ? 'ręcznie dodana' : getStatusText(req.status);
                            return `${formatDate(req.start_date)} - ${formatDate(req.end_date)} (${req.type}, ${source})`;
                        }).join(', ');
                        
                        showMessage(
                            `❌ Nie możesz złożyć wniosku - masz już nieobecność na te daty: ${conflictDetails}`,
                            'error'
                        );
                        return;
                    }
                }

                const response = await fetch('../../absence_requests_api.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        action: 'create',
                        ...data
                    })
                });

                const result = await response.json();

                if (result.success) {
                    showMessage('✅ Wniosek został złożony! Poczekaj na decyzję kierownika.', 'success');
                    e.target.reset();
                    setTimeout(() => {
                        const tab = document.querySelectorAll('.tab')[1];
                        tab.click();
                    }, 2000);
                } else {
                    showMessage('❌ ' + (result.message || 'Błąd podczas składania wniosku'), 'error');
                }
            } catch (error) {
                showMessage('❌ Błąd połączenia z serwerem', 'error');
                console.error(error);
            }
        });

        // Ładowanie listy wniosków
        async function loadRequests() {
            const container = document.getElementById('requestsList');
            container.innerHTML = '<div class="empty-state"><div class="empty-state-icon">⏳</div><p>Ładowanie...</p></div>';

            try {
                const [response, manualResponse] = await Promise.all([
                    fetch(`../../absence_requests_api.php?action=list&employee_id=${employeeId}`),
                    fetch('get_manual_absences.php')
                ]);

                const result = await response.json();
                const manualResult = await manualResponse.json().catch(() => ({ success: false, absences: [] }));

                const requests = (result.success && Array.isArray(result.requests)) ? result.requests : [];
                const manual   = (manualResult.success && Array.isArray(manualResult.absences)) ? manualResult.absences : [];

                // Zapisz statusy tylko dla "prawdziwych" wniosków (do auto-odświeżania)
                requests.forEach(req => {
                    if (!previousStatuses.has(req.id)) {
                        previousStatuses.set(req.id, req.status);
                    }
                });

                const all = [...requests, ...manual];

                if (all.length > 0) {
                    container.innerHTML = all.map(req => `
                        <div class="request-card ${req.status}">
                            <div class="request-header">
                                <div class="request-dates">
                                    ${formatDate(req.start_date)} - ${formatDate(req.end_date)}
                                    <span style="color: #999; font-size: 14px;">(${calculateDays(req.start_date, req.end_date)} dni)</span>
                                </div>
                                <span class="status-badge status-${req.status}">
                                    ${getStatusText(req.status)}
                                </span>
                            </div>
                            <div class="request-info">
                                <strong>Typ:</strong> ${getTypeIcon(req.type)} ${req.type}<br>
                                ${req.reason ? `<strong>Powód:</strong> ${req.reason}<br>` : ''}
                                <strong>Złożono:</strong> ${formatDateTime(req.requested_at)}<br>
                                ${req.status === 'pending' && req.assigned_manager_first_name && req.assigned_manager_last_name ? `<strong>Przesłano do:</strong> ${req.assigned_manager_first_name} ${req.assigned_manager_last_name}<br>` : ''}
                                ${req.reviewed_at ? `<strong>Rozpatrzono:</strong> ${formatDateTime(req.reviewed_at)}` : ''}
                                ${req.reviewer_first_name && req.reviewer_last_name ? `<br><strong>Rozpatrzył:</strong> ${req.reviewer_first_name} ${req.reviewer_last_name}` : ''}
                                ${req.notes ? `<br><strong>Notatka kierownika:</strong> ${req.notes}` : ''}
                                ${req.source === 'manual' ? `<br><em style="color:#666;">(Nieobecność dodana ręcznie w panelu kierownika)</em>` : ''}
                            </div>
                        </div>
                    `).join('');
                } else {
                    container.innerHTML = '<div class="empty-state"><div class="empty-state-icon">📭</div><p>Nie masz jeszcze żadnych wniosków ani ręcznie dodanych nieobecności</p></div>';
                }
            } catch (error) {
                container.innerHTML = '<div class="empty-state"><div class="empty-state-icon">⚠️</div><p>Błąd ładowania wniosków</p></div>';
                console.error(error);
            }
        }

        function showMessage(text, type) {
            const msg = document.getElementById('message');
            msg.textContent = text;
            msg.className = 'message ' + type;
            msg.style.display = 'block';
            setTimeout(() => msg.style.display = 'none', 5000);
        }

        function formatDate(dateStr) {
            const d = new Date(dateStr);
            return d.toLocaleDateString('pl-PL');
        }

        function formatDateTime(dateStr) {
            const d = new Date(dateStr);
            return d.toLocaleString('pl-PL');
        }

        function calculateDays(start, end) {
            const s = new Date(start);
            const e = new Date(end);
            return Math.ceil((e - s) / (1000 * 60 * 60 * 24)) + 1;
        }

        function getStatusText(status) {
            const texts = {
                'pending': 'Oczekuje',
                'approved': 'Zaakceptowany',
                'rejected': 'Odrzucony'
            };
            return texts[status] || status;
        }

        function getTypeIcon(type) {
            const icons = {
                'urlop': '🏖️',
                'L4': '🏥',
                'inny': '📄'
            };
            return icons[type] || '📄';
        }

        // ========== AUTO-ODŚWIEŻANIE WNIOSKÓW ==========
        
        let autoRefreshInterval;
        let lastRequestsCount = 0;
        let previousStatuses = new Map(); // Przechowuj poprzednie statusy
        
        async function checkForUpdates() {
            try {
                const response = await fetch(`../../absence_requests_api.php?action=list&employee_id=${employeeId}`);
                const result = await response.json();
                
                if (result.success) {
                    const currentCount = result.requests.length;
                    let hasChanges = false;
                    let approvedNew = 0;
                    let rejectedNew = 0;
                    
                    // Sprawdź zmiany statusów
                    result.requests.forEach(req => {
                        const prevStatus = previousStatuses.get(req.id);
                        if (prevStatus && prevStatus !== req.status) {
                            hasChanges = true;
                            if (req.status === 'approved') approvedNew++;
                            if (req.status === 'rejected') rejectedNew++;
                        }
                        previousStatuses.set(req.id, req.status);
                    });
                    
                    // Jeśli są zmiany, załaduj ponownie listę
                    if (currentCount !== lastRequestsCount || hasChanges) {
                        lastRequestsCount = currentCount;
                        await loadRequests();
                        
                        // Pokaż powiadomienie o zmianie
                        if (approvedNew > 0) {
                            showMessage(`✅ ${approvedNew} wniosek(i) zaakceptowano!`, 'success');
                            playNotificationSound();
                        }
                        if (rejectedNew > 0) {
                            showMessage(`❌ ${rejectedNew} wniosek(i) odrzucono`, 'error');
                            playNotificationSound();
                        }
                    }
                }
            } catch (error) {
                console.error('Auto-refresh error:', error);
            }
        }
        
        function playNotificationSound() {
            // Prosty dźwięk powiadomienia
            const audioContext = new (window.AudioContext || window.webkitAudioContext)();
            const oscillator = audioContext.createOscillator();
            const gainNode = audioContext.createGain();
            
            oscillator.connect(gainNode);
            gainNode.connect(audioContext.destination);
            
            oscillator.frequency.value = 800;
            oscillator.type = 'sine';
            
            gainNode.gain.setValueAtTime(0.3, audioContext.currentTime);
            gainNode.gain.exponentialRampToValueAtTime(0.01, audioContext.currentTime + 0.5);
            
            oscillator.start(audioContext.currentTime);
            oscillator.stop(audioContext.currentTime + 0.5);
        }
        
        // Włącz auto-odświeżanie co 15 sekund
        function startAutoRefresh() {
            if (autoRefreshInterval) clearInterval(autoRefreshInterval);
            autoRefreshInterval = setInterval(checkForUpdates, 15000); // 15 sekund
            document.getElementById('autoRefreshIndicator').classList.add('active');
        }
        
        function stopAutoRefresh() {
            if (autoRefreshInterval) {
                clearInterval(autoRefreshInterval);
                autoRefreshInterval = null;
            }
            document.getElementById('autoRefreshIndicator').classList.remove('active');
        }

    </script>
</body>
</html>
