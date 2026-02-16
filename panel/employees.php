<?php
require_once __DIR__ . '/../core/auth.php';

// Dostęp tylko dla kierownika (2+) – jak dashboard
$managerInfo = requireManagerPage(2);
?>

<!DOCTYPE html>
<html lang="pl">
<head>
<script>
    window.USER_ROLE = <?= (int)($_SESSION['role_level'] ?? 0) ?>;
</script>
<meta charset="UTF-8">
<title>Lista pracowników</title>

<style>
body {
    font-family: Arial;
    background:#f4f4f4;
    padding:30px;
}

.container {
    max-width:900px;
    margin:auto;
    background:#fff;
    padding:30px;
    border-radius:10px;
    box-shadow:0 0 15px rgba(0,0,0,0.1);
}

.employee {
    display:flex;
    justify-content:space-between;
    align-items:flex-start;
    padding:10px 0;
    border-bottom:1px solid #ddd;
    gap:12px;
}

.employee small {
    color:#555;
}

button {
    background:#dc3545;
    border:none;
    color:white;
    padding:6px 10px;
    border-radius:5px;
    cursor:pointer;
}

button:hover {
    background:#b02a37;
}

.back {
    margin-bottom:20px;
    display:inline-block;
    background:#6c757d;
    color:white;
    padding:8px 14px;
    border-radius:6px;
    text-decoration:none;
}

.status {
    font-size:18px;
    margin-right:6px;
}

.blocked { color:#dc3545; }
.active  { color:#28a745; }

.actions {
    display:flex;
    flex-direction:column;
    align-items:flex-end;
    gap:6px;
}

.actions button {
    margin-left:0;
}

.current-job {
    display:inline-block;
    margin-left:8px;
    padding:3px 8px;
    font-size:13px;
    border-radius:6px;
    background:#e9ecef;
}

.timer {
    display:inline-block;
    margin-left:6px;
    font-size:13px;
    color:#0d6efd;
    font-weight:bold;
}

.filter {
    display:flex;
    flex-direction:column;
    font-size:14px;
}

.filters {
    display:flex;
    flex-wrap:wrap;
    gap:10px 20px;
    margin-bottom:15px;
    align-items:flex-end;
}

.filter select,
.filter input[type="text"] {
    margin-top:5px;
    padding:6px 10px;
    border-radius:6px;
    border:1px solid #ccc;
    max-width:260px;
}

.note-input {
    width: 80%;
    margin-top: 8px;
    padding: 6px;
    border: 1px solid #ddd;
    border-radius: 5px;
    font-size: 13px;
    font-family: Arial;
}

.btn-save {
    background: #28a745;
    margin-top: 5px;
    font-size: 12px;
    padding: 4px 10px;
}

.btn-save:hover {
    background: #218838;
}

.note-display {
    margin-top: 8px;
    padding: 8px 10px;
    background: #ffeeba;
    border-radius: 6px;
    font-size: 14px;
    font-weight: 600;
    color: #856404;
    border-left: 4px solid #ff8800;
    box-shadow: 0 2px 6px rgba(0,0,0,0.08);
}

.admin-control-panel {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    padding: 20px;
    border-radius: 12px;
    margin-bottom: 25px;
    color: white;
    box-shadow: 0 4px 15px rgba(0,0,0,0.2);
}

.admin-control-panel h3 {
    margin: 0 0 15px 0;
    font-size: 18px;
    display: flex;
    align-items: center;
    gap: 8px;
}

.admin-control-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 12px;
}

.admin-control-item {
    background: rgba(255,255,255,0.15);
    padding: 10px 14px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    gap: 10px;
    cursor: pointer;
    transition: background 0.2s;
}

.admin-control-item:hover {
    background: rgba(255,255,255,0.25);
}

.admin-control-item input[type="checkbox"] {
    width: 18px;
    height: 18px;
    cursor: pointer;
}

.admin-control-item label {
    cursor: pointer;
    font-size: 14px;
    flex: 1;
}

.notification-panel {
    background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
    padding: 20px;
    border-radius: 12px;
    margin-bottom: 25px;
    color: white;
    box-shadow: 0 4px 15px rgba(0,0,0,0.2);
}

.notification-panel h3 {
    margin: 0 0 15px 0;
    font-size: 18px;
    display: flex;
    align-items: center;
    gap: 8px;
}

.notification-form {
    display: flex;
    flex-direction: column;
    gap: 15px;
}

.form-field {
    display: flex;
    flex-direction: column;
    gap: 6px;
}

.form-field label {
    font-size: 14px;
    font-weight: 600;
}

.form-field select,
.form-field textarea {
    padding: 10px;
    border: none;
    border-radius: 8px;
    font-size: 14px;
    font-family: inherit;
    background: white;
    color: #333;
}

.form-field textarea {
    resize: vertical;
    min-height: 80px;
}

.notification-actions {
    display: flex;
    gap: 10px;
}

.notification-actions button {
    flex: 1;
    padding: 12px;
    font-size: 15px;
    font-weight: 600;
}

.result-message {
    margin-top: 15px;
    padding: 12px;
    border-radius: 8px;
    font-weight: 600;
}

.result-message.success {
    background: rgba(255, 255, 255, 0.9);
    color: #155724;
}

.result-message.error {
    background: rgba(220, 53, 69, 0.9);
    color: white;
}
</style>
</head>
<body>

<div class="container">

<a href="#" class="back" onclick="goBackToEntry('dashboard.php'); return false;">⬅ Powrót</a>

<!-- Panel kontrolny admina (tylko rola 9) -->
<div class="admin-control-panel" data-roles="9" style="display:none;">
    <h3>⚙️ Panel sterowania modułami pracowników</h3>
    <div class="admin-control-grid">
        <div class="admin-control-item" onclick="toggleModuleCheckbox('module-edit-employee')">
            <input type="checkbox" id="module-edit-employee" onchange="updateModuleSettings()" onclick="event.stopPropagation();">
            <label for="module-edit-employee" onclick="event.stopPropagation();">Edycja danych pracownika</label>
        </div>
        <div class="admin-control-item" onclick="toggleModuleCheckbox('module-employee-details')">
            <input type="checkbox" id="module-employee-details" onchange="updateModuleSettings()" onclick="event.stopPropagation();">
            <label for="module-employee-details" onclick="event.stopPropagation();">Szczegóły pracownika (token, stawka)</label>
        </div>
        <div class="admin-control-item" onclick="toggleModuleCheckbox('module-session-comment')">
            <input type="checkbox" id="module-session-comment" onchange="updateModuleSettings()" onclick="event.stopPropagation();">
            <label for="module-session-comment" onclick="event.stopPropagation();">Komentarze do sesji pracy</label>
        </div>
        <div class="admin-control-item" onclick="toggleModuleCheckbox('module-print-token')">
            <input type="checkbox" id="module-print-token" onchange="updateModuleSettings()" onclick="event.stopPropagation();">
            <label for="module-print-token" onclick="event.stopPropagation();">Drukowanie tokena</label>
        </div>
        <div class="admin-control-item" onclick="toggleModuleCheckbox('module-unblock')">
            <input type="checkbox" id="module-unblock" onchange="updateModuleSettings()" onclick="event.stopPropagation();">
            <label for="module-unblock" onclick="event.stopPropagation();">Odblokowywanie pracownika</label>
        </div>
        <div class="admin-control-item" onclick="toggleModuleCheckbox('module-reset-device')">
            <input type="checkbox" id="module-reset-device" onchange="updateModuleSettings()" onclick="event.stopPropagation();">
            <label for="module-reset-device" onclick="event.stopPropagation();">Odpinanie urządzenia</label>
        </div>
        <div class="admin-control-item" onclick="toggleModuleCheckbox('module-delete-employee')">
            <input type="checkbox" id="module-delete-employee" onchange="updateModuleSettings()" onclick="event.stopPropagation();">
            <label for="module-delete-employee" onclick="event.stopPropagation();">Usuwanie pracownika</label>
        </div>
    </div>
</div>

<!-- Panel wysyłania powiadomień -->
<div class="notification-panel" data-roles="9" style="display:none;">
    <h3>💬 Wyślij powiadomienie do pracownika</h3>
    <div class="notification-form">
        <div class="form-field">
            <label for="notificationEmployee">Wybierz pracownika *</label>
            <select id="notificationEmployee" required>
                <option value="">— Wybierz pracownika —</option>
            </select>
        </div>
        <div class="form-field">
            <label for="notificationMessage">Wiadomość *</label>
            <textarea id="notificationMessage" placeholder="Wpisz wiadomość do pracownika..." rows="4" maxlength="500"></textarea>
            <small id="charCounter" style="color:#666;">0/500 znaków</small>
        </div>
        <div class="notification-actions">
            <button id="sendNotificationBtn" onclick="sendNotification()" style="background:#28a745;">
                📤 Wyślij powiadomienie
            </button>
        </div>
    </div>
    <div id="notificationResult" class="result-message" style="display:none;"></div>
</div>

<h2>👷 Lista pracowników</h2>

<div class="filters">
    <div class="filter">
        🔍 Filtruj Aktywne Miejsca Pracy:
        <select id="siteFilter" onchange="renderEmployees()">
            <option value="">— wszystkie —</option>
        </select>
    </div>

    <div class="filter">
        🔎 Szukaj pracownika:
        <input type="text" id="searchInput" placeholder="Imię, nazwisko, token, stanowisko..." oninput="renderEmployees()">
    </div>

    <div class="filter">
        📊 Liczba pracowników:
        <select id="limitSelect" onchange="renderEmployees()" style="margin-top:5px;padding:6px 10px;border-radius:6px;border:1px solid #ccc;">
            <option value="5" selected>5</option>
            <option value="10">10</option>
            <option value="20">20</option>
            <option value="50">50</option>
            <option value="999999">Wszyscy</option>
        </select>
    </div>

    <div class="filter">
        <button data-roles="9" onclick="printAllInstructions()" style="padding:10px 20px;background:#667eea;color:white;border:none;border-radius:6px;cursor:pointer;font-weight:600;">
            🖨️ Drukuj wszystkie instrukcje
        </button>
    </div>
</div>

<div id="employeeList"></div>

</div>

<script>
function goBackToEntry(fallbackUrl) {
    if (document.referrer) {
        window.location.href = document.referrer;
    } else {
        window.location.href = fallbackUrl;
    }
}

let employees = [];
let timers = {};

async function loadEmployeeList() {
    const res = await fetch("../get_employees.php");
    employees = await res.json();
    buildFilter();
    populateNotificationSelect();
    renderEmployees();
}

function buildFilter() {
    const select = document.getElementById("siteFilter");
    const sites = [...new Set(
        employees
            .filter(e => e.current_job)
            .map(e => e.current_job)
    )];

    select.innerHTML = `<option value="">— wszystkie —</option>`;
    sites.forEach(s => {
        select.innerHTML += `<option value="${s}">${s}</option>`;
    });
}

function populateNotificationSelect() {
    const select = document.getElementById("notificationEmployee");
    if (!select) return;
    
    // Sortuj alfabetycznie po nazwisku
    const sortedEmployees = [...employees].sort((a, b) => {
        const nameA = `${a.last_name} ${a.first_name}`.toLowerCase();
        const nameB = `${b.last_name} ${b.first_name}`.toLowerCase();
        return nameA.localeCompare(nameB);
    });
    
    select.innerHTML = '<option value="">— Wybierz pracownika —</option>';
    select.innerHTML += '<option value="all" style="font-weight: bold; color: #28a745;">📢 WSZYSCY PRACOWNICY</option>';
    
    sortedEmployees.forEach(emp => {
        const option = document.createElement('option');
        option.value = emp.id;
        
        // Dodaj emoji statusu
        let statusEmoji = '';
        if (emp.current_job) {
            statusEmoji = '🟢 '; // pracuje
        } else if (emp.blocked_until) {
            statusEmoji = '🔴 '; // zablokowany
        } else {
            statusEmoji = '⚪ '; // nieaktywny
        }
        
        option.textContent = `${statusEmoji}${emp.last_name} ${emp.first_name}`;
        
        // Dodaj info o pracy
        if (emp.current_job) {
            option.textContent += ` (${emp.current_job})`;
        }
        
        select.appendChild(option);
    });
}

async function sendNotification() {
    const employeeId = document.getElementById('notificationEmployee').value;
    const message = document.getElementById('notificationMessage').value.trim();
    const result = document.getElementById('notificationResult');
    const btn = document.getElementById('sendNotificationBtn');
    
    if (!employeeId) {
        result.textContent = '❌ Wybierz pracownika lub "Wszyscy"';
        result.className = 'result-message error';
        result.style.display = 'block';
        return;
    }
    
    if (!message) {
        result.textContent = '❌ Wpisz wiadomość';
        result.className = 'result-message error';
        result.style.display = 'block';
        return;
    }
    
    btn.disabled = true;
    btn.textContent = '⏳ Wysyłanie...';
    
    try {
        const response = await fetch('send_notification.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                employee_id: employeeId === 'all' ? 'all' : parseInt(employeeId, 10),
                message: message
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            result.textContent = '✅ ' + data.message;
            result.className = 'result-message success';
            result.style.display = 'block';
            
            // Wyczyść formularz
            document.getElementById('notificationMessage').value = '';
            document.getElementById('charCounter').textContent = '0/500 znaków';
        } else {
            result.textContent = '❌ ' + data.message;
            result.className = 'result-message error';
            result.style.display = 'block';
        }
    } catch (error) {
        result.textContent = '❌ Błąd połączenia z serwerem';
        result.className = 'result-message error';
        result.style.display = 'block';
    } finally {
        btn.disabled = false;
        btn.textContent = '📤 Wyślij powiadomienie';
        
        // Ukryj komunikat po 5 sekundach
        setTimeout(() => {
            result.style.display = 'none';
        }, 5000);
    }
}

function renderEmployees() {
    const div = document.getElementById("employeeList");
    div.innerHTML = "";

    const filter = document.getElementById("siteFilter").value;
    const search = (document.getElementById("searchInput").value || "").toLowerCase();
    const limit = parseInt(document.getElementById("limitSelect").value, 10) || 5;
    const now = Date.now();

    let count = 0; // Licznik wyrenderowanych pracowników

    for (const e of employees) {
        if (filter && e.current_job !== filter) continue;

        if (search) {
            const haystack = [
                e.first_name,
                e.last_name,
                e.pin_token,
                e.current_job,
                e.machine_name
            ]
                .filter(Boolean)
                .join(" ")
                .toLowerCase();

            if (!haystack.includes(search)) continue;
        }

        const blocked =
            e.blocked_until &&
            new Date(e.blocked_until).getTime() > now;

        // Ikona statusu:
        // - jeśli pracownik ZABLOKOWANY -> czerwone ❌
        // - w przeciwnym razie: zielone ✔ przy aktywnej sesji, czerwone ❌ gdy brak sesji
        const hasActiveSession = !!e.work_session_id;

        const statusIcon = blocked
            ? `<span class="status blocked">❌</span>`
            : (hasActiveSession
                ? `<span class="status active">✔</span>`
                : `<span class="status blocked">❌</span>`);

        const currentJob = e.current_job
    ? `<span class="current-job">
            🛠 ${e.current_job}
            ${e.machine_name
                ? ` | 🚜 ${e.machine_name} (${e.machine_registry_number})`
                : ``}
       </span>`
    : ``;

        let operatorLabel = '';
        const role = parseInt(e.is_operator ?? 0, 10) || 0;
        if (role === 1) {
            operatorLabel = `<span class="current-job">👷 Operator</span>`;
        } else if (role === 2) {
            operatorLabel = `<span class="current-job">🚚 Kierowca</span>`;
        } else if (role === 3) {
            operatorLabel = `<span class="current-job">🚜 Ładowarka</span>`;
        } else {
            operatorLabel = `<span class="current-job">🧱 Pracownik fizyczny</span>`;
        }


        const timer = e.start_time
            ? `<span class="timer" id="timer-${e.id}"></span>`
            : ``;

        // Komentarz do aktywnej sesji pracy
        const sessionCommentDisplay = e.work_session_id && e.manager_comment
            ? `<div class="note-display">💼 Komentarz kierownika: ${e.manager_comment}</div>`
            : ``;

        const sessionCommentInput = e.work_session_id
            ? `<div data-roles="4,9" style="margin-top: 8px;">
                    <textarea class="note-input" id="session-comment-${e.work_session_id}" placeholder="Dodaj komentarz do aktywnej sesji pracy...">${e.manager_comment || ''}</textarea>
                    <button class="btn-save" onclick="saveSessionComment(${e.work_session_id})">💼 Zapisz komentarz do sesji</button>
               </div>`
            : ``;

        div.innerHTML += `
            <div class="employee">
                <div style="flex: 1;">
                    ${statusIcon}
                    <b>${e.last_name} ${e.first_name}</b>
                    ${currentJob}
                    ${operatorLabel}
                    ${timer}<br>

                    ${sessionCommentDisplay}
                    ${sessionCommentInput}

                    <div data-roles="4,9">
                        <small>Token: <b>${e.pin_token}</b></small>
                        <small>Stawka: <b>${Number(e.hour_rate).toFixed(2)} zł/h</b></small>
                        <small>Urlop: <b>${e.vacation_days} dni</b></small><br>
                        <small>Urządzenie: <b>${e.device_id ? (e.device_id.substring(0, 12) + '…') : 'brak powiązania'}</b></small>
                        <small>IP: <b>${e.ip_address || '—'}</b></small>
                    </div>

                    <div data-roles="4,9" class="edit-block" style="margin-top:8px;padding:8px;border-radius:6px;border:1px solid #eee;background:#fafafa;">
                        <small><b>✏️ Edycja pracownika</b></small><br>
                        <input type="text" id="edit-first-${e.id}" value="${e.first_name}" placeholder="Imię" style="margin-top:4px;padding:4px 6px;border-radius:4px;border:1px solid #ccc;width:45%;">
                        <input type="text" id="edit-last-${e.id}" value="${e.last_name}" placeholder="Nazwisko" style="margin-top:4px;padding:4px 6px;border-radius:4px;border:1px solid #ccc;width:45%;margin-left:4px;">
                        <br>
                        <label style="font-size:13px;margin-top:6px;display:inline-block;">
                            Rola:
                            <select id="edit-role-${e.id}" style="margin-left:4px;padding:4px 6px;border-radius:4px;border:1px solid #ccc;">
                                <option value="0" ${role === 0 ? 'selected' : ''}>Pracownik fizyczny</option>
                                <option value="1" ${role === 1 ? 'selected' : ''}>Operator</option>
                                <option value="2" ${role === 2 ? 'selected' : ''}>Kierowca</option>
                                <option value="3" ${role === 3 ? 'selected' : ''}>Ładowarka</option>
                            </select>
                        </label>
                        <input type="number" step="0.01" id="edit-hour-${e.id}" value="${Number(e.hour_rate).toFixed(2)}" placeholder="Stawka [zł/h]" style="margin-top:4px;padding:4px 6px;border-radius:4px;border:1px solid #ccc;width:45%;margin-left:4px;">
                        <span style="margin-left:6px;font-size:13px;color:#555;">: Wynagrodzenie</span>
                        <br>
                        <input type="number" min="0" step="1" id="edit-vacation-${e.id}" value="${e.vacation_days}" placeholder="Dni urlopu" style="margin-top:4px;padding:4px 6px;border-radius:4px;border:1px solid #ccc;width:45%;">
                        
                        <span style="margin-left:6px;font-size:13px;color:#555;">: Dni urlopowe</span>
                        <br>
                        <button class="btn-save" style="margin-top:6px;" onclick="saveEmployee(${e.id})">💾 Zapisz zmiany</button>
                    </div>
                </div>
                <div class="actions">
                    <button data-roles="4,9" onclick="printToken('${e.pin_token}', '${e.first_name}', '${e.last_name}')" style="margin-top:15px;padding:10px 20px;background:#667eea;color:white;border:none;border-radius:6px;cursor:pointer;font-weight:600;">
                        🖨️ Drukuj token z instrukcją
                    </button>
                    ${blocked
                        ? `<button data-roles="4,9" onclick="unblockEmployee(${e.id})">Odblokuj</button>`
                        : ``
                    }
                    <button data-roles="9" onclick="resetDevice(${e.id})">Odepnij urządzenie</button>
                    <button data-roles="9" onclick="deleteEmployee(${e.id})">Usuń</button>
                </div>
            </div>
`;

        if (e.start_time) {
            startTimer(e.id, e.start_time);
        }

        count++;
        if (count >= limit) break; // Przerwij po osiągnięciu limitu
    }
    
    applyRoleVisibility();
    
    // Zastosuj ustawienia modułów (tylko dla admina)
    if (window.USER_ROLE === 9 && typeof applyModuleSettings === 'function') {
        applyModuleSettings();
    }
}

// Funkcja do wydruku instrukcji ustawienia PIN - identyczna treść jak w dashboard.js (printToken)
function printPinInstruction(firstName, lastName, token) {
    const printWindow = window.open('', '_blank', 'width=800,height=600');
    if (!printWindow) return;

    printWindow.document.write(`
<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <title>Token - ${firstName} ${lastName}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: Arial, sans-serif;
            padding: 40px;
            max-width: 800px;
            margin: 0 auto;
        }
        .header {
            text-align: center;
            margin-bottom: 40px;
            border-bottom: 3px solid #667eea;
            padding-bottom: 20px;
        }
        .header h1 {
            color: #333;
            font-size: 28px;
            margin-bottom: 10px;
        }
        .token-box {
            background: #f0f0f0;
            border: 3px solid #667eea;
            border-radius: 12px;
            padding: 30px;
            text-align: center;
            margin: 30px 0;
        }
        .token-label {
            font-size: 18px;
            color: #666;
            margin-bottom: 15px;
            font-weight: bold;
        }
        .token-value {
            font-size: 42px;
            font-weight: bold;
            color: #667eea;
            font-family: 'Courier New', monospace;
            letter-spacing: 3px;
        }
        .employee-info {
            background: #e7f3ff;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 30px;
        }
        .employee-info strong {
            color: #333;
            font-size: 18px;
        }
        .instructions {
            margin-top: 30px;
        }
        .instructions h2 {
            color: #667eea;
            font-size: 22px;
            margin-bottom: 20px;
        }
        .step {
            background: white;
            border-left: 4px solid #667eea;
            padding: 15px 20px;
            margin-bottom: 15px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .step-number {
            display: inline-block;
            width: 30px;
            height: 30px;
            background: #667eea;
            color: white;
            border-radius: 50%;
            text-align: center;
            line-height: 30px;
            font-weight: bold;
            margin-right: 10px;
        }
        .url {
            background: #f8f9fa;
            padding: 8px 12px;
            border-radius: 4px;
            font-family: monospace;
            color: #667eea;
            display: inline-block;
            margin-top: 5px;
        }
        .note {
            background: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 15px;
            margin-top: 20px;
            border-radius: 4px;
        }
        @media print {
            body { padding: 20px; }
            .no-print { display: none; }
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>🔑 TOKEN DOSTĘPU DO SYSTEMU RCP</h1>
        <p>Rejestr Czasu Pracy</p>
    </div>

    <div class="employee-info">
        <strong>👤 Pracownik:</strong> ${firstName} ${lastName}
    </div>

    <div class="token-box">
        <div class="token-label">Twój Token (10 znaków):</div>
        <div class="token-value">${token}</div>
    </div>

    <div class="instructions">
        <h2>📝 Instrukcja aktywacji konta</h2>
        
        <div class="step">
            <span class="step-number">1</span>
            <strong>Otwórz stronę aktywacyjną</strong><br>
            Wejdź na adres:<br>
            <span class="url">https://praca.pref-bet.com/set_pin.html</span>
        </div>

        <div class="step">
            <span class="step-number">2</span>
            <strong>Wpisz token</strong><br>
            Przepisz 10-znakowy token z ramki powyżej do pola "Token"
        </div>

        <div class="step">
            <span class="step-number">3</span>
            <strong>Ustaw swój PIN</strong><br>
            Wymyśl i wpisz 4-cyfrowy PIN (np. 1234)<br>
            <small style="color:#666;">⚠️ Zapamiętaj PIN - będzie potrzebny do logowania</small>
        </div>

        <div class="step">
            <span class="step-number">4</span>
            <strong>Zaloguj się</strong><br>
            Po ustawieniu PIN wróć na stronę główną:<br>
            <span class="url">https://praca.pref-bet.com</span><br>
            Zaloguj się swoim imieniem, nazwiskiem i PIN-em
        </div>

        <div class="step">
            <span class="step-number">5</span>
            <strong>Instrukcje dla użytkowników – powiadomienia PUSH i lokalizacja (wymagane)</strong><br>
            Aby system RCP działał poprawnie, wymagane jest włączenie powiadomień PUSH oraz zezwolenie na dostęp do lokalizacji (GPS) w telefonie.<br><br>
            Dla Androida: „Używaj Chrome/Edge/Firefox, nie wbudowanej przeglądarki producenta. Włącz powiadomienia w przeglądarce oraz zezwól na udostępnianie lokalizacji (GPS) dla strony z panelem RCP.”<br>
            Dla iOS: „iOS ≥ 16.4, otwórz Safari → nasz adres → Udostępnij → Dodaj do ekranu początkowego. Potem uruchom panel z ikony PWA, włącz powiadomienia oraz zezwól na dostęp do lokalizacji (GPS).”
        </div>
    </div>

    <div class="note">
        <strong>⚠️ WAŻNE:</strong><br>
        • Token możesz użyć wielokrotnie do zmiany PIN-u<br>
        • Zachowaj token w bezpiecznym miejscu<br>
        • W razie problemów skontaktuj się z kierownikiem
    </div>

    <div class="no-print" style="margin-top: 40px; text-align: center;">
        <button onclick="window.print()" style="padding: 15px 30px; background: #667eea; color: white; border: none; border-radius: 8px; font-size: 16px; cursor: pointer; font-weight: bold;">
            🖨️ Drukuj
        </button>
        <button onclick="window.close()" style="padding: 15px 30px; background: #6c757d; color: white; border: none; border-radius: 8px; font-size: 16px; cursor: pointer; font-weight: bold; margin-left: 10px;">
            ✖️ Zamknij
        </button>
    </div>

    <div style="margin-top: 40px; text-align: center; color: #999; font-size: 12px;">
        Wygenerowano: ${new Date().toLocaleString('pl-PL')}
    </div>
</body>
</html>
    `);
    printWindow.document.close();
}

// Zgodnie z przyciskiem: printToken('token', 'firstName', 'lastName')
function printToken(token, firstName, lastName) {
    printPinInstruction(firstName, lastName, token);
}

// Funkcja do drukowania wszystkich instrukcji pracowników
function printAllInstructions() {
    if (!employees || employees.length === 0) {
        alert('Brak pracowników do wydruku');
        return;
    }

    // Filtruj pracowników według aktualnych filtrów
    const filter = document.getElementById("siteFilter").value;
    const search = (document.getElementById("searchInput").value || "").toLowerCase();
    
    const filteredEmployees = employees.filter(e => {
        if (filter && e.current_job !== filter) return false;
        
        if (search) {
            const haystack = [
                e.first_name,
                e.last_name,
                e.pin_token,
                e.current_job,
                e.machine_name
            ]
                .filter(Boolean)
                .join(" ")
                .toLowerCase();
            
            if (!haystack.includes(search)) return false;
        }
        
        return true;
    });

    if (filteredEmployees.length === 0) {
        alert('Brak pracowników spełniających kryteria filtrowania');
        return;
    }

    const printWindow = window.open('', '_blank', 'width=800,height=600');
    if (!printWindow) {
        alert('Nie można otworzyć okna druku. Sprawdź blokadę wyskakujących okien.');
        return;
    }

    let htmlContent = `
<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <title>Instrukcje dla wszystkich pracowników</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: Arial, sans-serif;
            padding: 40px;
        }
        .page {
            page-break-after: always;
            max-width: 800px;
            margin: 0 auto 40px;
        }
        .page:last-child {
            page-break-after: auto;
        }
        .header {
            text-align: center;
            margin-bottom: 40px;
            border-bottom: 3px solid #667eea;
            padding-bottom: 20px;
        }
        .header h1 {
            color: #333;
            font-size: 28px;
            margin-bottom: 10px;
        }
        .token-box {
            background: #f0f0f0;
            border: 3px solid #667eea;
            border-radius: 12px;
            padding: 30px;
            text-align: center;
            margin: 30px 0;
        }
        .token-label {
            font-size: 18px;
            color: #666;
            margin-bottom: 15px;
            font-weight: bold;
        }
        .token-value {
            font-size: 42px;
            font-weight: bold;
            color: #667eea;
            font-family: 'Courier New', monospace;
            letter-spacing: 3px;
        }
        .employee-info {
            background: #e7f3ff;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 30px;
        }
        .employee-info strong {
            color: #333;
            font-size: 18px;
        }
        .instructions {
            margin-top: 30px;
        }
        .instructions h2 {
            color: #667eea;
            font-size: 22px;
            margin-bottom: 20px;
        }
        .step {
            background: white;
            border-left: 4px solid #667eea;
            padding: 15px 20px;
            margin-bottom: 15px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .step-number {
            display: inline-block;
            width: 30px;
            height: 30px;
            background: #667eea;
            color: white;
            border-radius: 50%;
            text-align: center;
            line-height: 30px;
            font-weight: bold;
            margin-right: 10px;
        }
        .url {
            background: #f8f9fa;
            padding: 8px 12px;
            border-radius: 4px;
            font-family: monospace;
            color: #667eea;
            display: inline-block;
            margin-top: 5px;
        }
        .note {
            background: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 15px;
            margin-top: 20px;
            border-radius: 4px;
        }
        @media print {
            body { padding: 20px; }
            .no-print { display: none; }
            .page {
                page-break-after: always;
                margin-bottom: 0;
            }
            .page:last-child {
                page-break-after: auto;
            }
        }
    </style>
</head>
<body>
`;

    filteredEmployees.forEach((employee, index) => {
        htmlContent += `
    <div class="page">
        <div class="header">
            <h1>🔑 TOKEN DOSTĘPU DO SYSTEMU RCP</h1>
            <p>Rejestr Czasu Pracy</p>
        </div>

        <div class="employee-info">
            <strong>👤 Pracownik:</strong> ${employee.first_name} ${employee.last_name}
        </div>

        <div class="token-box">
            <div class="token-label">Twój Token (10 znaków):</div>
            <div class="token-value">${employee.pin_token}</div>
        </div>

        <div class="instructions">
            <h2>📝 Instrukcja aktywacji konta</h2>
            
            <div class="step">
                <span class="step-number">1</span>
                <strong>Otwórz stronę aktywacyjną</strong><br>
                Wejdź na adres:<br>
                <span class="url">https://praca.pref-bet.com/set_pin.html</span>
            </div>

            <div class="step">
                <span class="step-number">2</span>
                <strong>Wpisz token</strong><br>
                Przepisz 10-znakowy token z ramki powyżej do pola "Token"
            </div>

            <div class="step">
                <span class="step-number">3</span>
                <strong>Ustaw swój PIN</strong><br>
                Wymyśl i wpisz 4-cyfrowy PIN (np. 1234)<br>
                <small style="color:#666;">⚠️ Zapamiętaj PIN - będzie potrzebny do logowania</small>
            </div>

            <div class="step">
                <span class="step-number">4</span>
                <strong>Zaloguj się</strong><br>
                Po ustawieniu PIN wróć na stronę główną:<br>
                <span class="url">https://praca.pref-bet.com</span><br>
                Zaloguj się swoim imieniem, nazwiskiem i PIN-em
            </div>

            <div class="step">
                <span class="step-number">5</span>
                <strong>Instrukcje dla użytkowników – powiadomienia PUSH i lokalizacja (wymagane)</strong><br>
                Aby system RCP działał poprawnie, wymagane jest włączenie powiadomień PUSH oraz zezwolenie na dostęp do lokalizacji (GPS) w telefonie.<br><br>
                Dla Androida: „Używaj Chrome/Edge/Firefox, nie wbudowanej przeglądarki producenta. Włącz powiadomienia w przeglądarce oraz zezwól na udostępnianie lokalizacji (GPS) dla strony z panelem RCP."<br>
                Dla iOS: „iOS ≥ 16.4, otwórz Safari → nasz adres → Udostępnij → Dodaj do ekranu początkowego. Potem uruchom panel z ikony PWA, włącz powiadomienia oraz zezwól na dostęp do lokalizacji (GPS)."
            </div>
        </div>

        <div class="note">
            <strong>⚠️ WAŻNE:</strong><br>
            • Token możesz użyć wielokrotnie do zmiany PIN-u<br>
            • Zachowaj token w bezpiecznym miejscu<br>
            • W razie problemów skontaktuj się z kierownikiem
        </div>

        <div style="margin-top: 40px; text-align: center; color: #999; font-size: 12px;">
            Pracownik ${index + 1} z ${filteredEmployees.length} | Wygenerowano: ${new Date().toLocaleString('pl-PL')}
        </div>
    </div>
`;
    });

    htmlContent += `
    <div class="no-print" style="margin-top: 40px; text-align: center;">
        <button onclick="window.print()" style="padding: 15px 30px; background: #667eea; color: white; border: none; border-radius: 8px; font-size: 16px; cursor: pointer; font-weight: bold;">
            🖨️ Drukuj wszystkie (${filteredEmployees.length} pracowników)
        </button>
        <button onclick="window.close()" style="padding: 15px 30px; background: #6c757d; color: white; border: none; border-radius: 8px; font-size: 16px; cursor: pointer; font-weight: bold; margin-left: 10px;">
            ✖️ Zamknij
        </button>
    </div>
</body>
</html>
    `;

    printWindow.document.write(htmlContent);
    printWindow.document.close();
}

async function saveEmployee(id) {
    const firstNameEl = document.getElementById(`edit-first-${id}`);
    const lastNameEl  = document.getElementById(`edit-last-${id}`);
    const roleEl      = document.getElementById(`edit-role-${id}`);
    const hourEl      = document.getElementById(`edit-hour-${id}`);
    const vacEl       = document.getElementById(`edit-vacation-${id}`);

    if (!firstNameEl || !lastNameEl || !roleEl || !hourEl || !vacEl) return;

    const first_name = firstNameEl.value.trim();
    const last_name  = lastNameEl.value.trim();

    let is_operator = parseInt(roleEl.value, 10);
    if (isNaN(is_operator) || is_operator < 0) {
        is_operator = 0;
    }
    const hour_rate   = parseFloat(hourEl.value.replace(',', '.'));
    const vacation_days = parseInt(vacEl.value, 10);

    if (!first_name || !last_name || isNaN(hour_rate) || isNaN(vacation_days) || vacation_days < 0) {
        alert('Uzupełnij poprawnie imię, nazwisko, stawkę i dni urlopu (>= 0).');
        return;
    }

    const res = await fetch('../update_employee.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id, first_name, last_name, is_operator, hour_rate, vacation_days })
    });

    const data = await res.json().catch(() => ({ success: false }));

    if (data.success) {
        alert('✅ Zapisano zmiany pracownika');
        loadEmployeeList();
    } else {
        alert('❌ Nie udało się zapisać zmian');
    }
}

function startTimer(id, startTime) {
    if (timers[id]) return;

    timers[id] = setInterval(() => {
        const start = new Date(startTime).getTime();
        const diff = Math.floor((Date.now() - start) / 1000);

        const h = String(Math.floor(diff / 3600)).padStart(2, "0");
        const m = String(Math.floor((diff % 3600) / 60)).padStart(2, "0");
        const s = String(diff % 60).padStart(2, "0");

        const el = document.getElementById(`timer-${id}`);
        if (el) el.textContent = `⏱ ${h}:${m}:${s}`;
    }, 1000);
}

async function deleteEmployee(id) {
    if (!confirm("Usunąć pracownika?")) return;

    const res = await fetch("../delete_employee.php", {
        method:"POST",
        headers:{"Content-Type":"application/json"},
        body:JSON.stringify({id})
    });

    const data = await res.json();
    if (data.success) loadEmployeeList();
}

// Inicjalizacja licznika znaków dla wiadomości
const messageTextarea = document.getElementById('notificationMessage');
const charCounter = document.getElementById('charCounter');

if (messageTextarea && charCounter) {
    messageTextarea.addEventListener('input', function() {
        const length = this.value.length;
        charCounter.textContent = `${length}/500 znaków`;
        
        if (length > 450) {
            charCounter.style.color = '#dc3545';
        } else {
            charCounter.style.color = '#666';
        }
    });
}

loadEmployeeList();

async function unblockEmployee(id) {
    if (!confirm("Odblokować pracownika?")) return;

    const res = await fetch("./unlock_employee.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ employee_id: id })
    });

    const data = await res.json();

    if (data.success) {
        loadEmployeeList();
    } else {
        alert(data.message || "Nie udało się odblokować pracownika");
    }
}

async function resetDevice(id) {
    if (!confirm("Odepnąć przypisane urządzenie dla tego pracownika?")) return;

    const res = await fetch("../update_employee.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ id, reset_device: true })
    });

    const data = await res.json().catch(() => ({ success: false }));

    if (data.success) {
        alert("✅ Urządzenie zostało odpięte. Pracownik będzie mógł przypiąć nowe przy kolejnym logowaniu.");
        loadEmployeeList();
    } else {
        alert("❌ Nie udało się odpiąć urządzenia");
    }
}

async function saveSessionComment(sessionId) {
    const commentText = document.getElementById(`session-comment-${sessionId}`).value;

    const res = await fetch("./update_session_comment.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ session_id: sessionId, comment: commentText })
    });

    const data = await res.json();

    if (data.success) {
        alert("✅ Komentarz do sesji zapisany");
        loadEmployeeList();
    } else {
        alert("❌ " + (data.message || "Nie udało się zapisać"));
    }
}

</script>

<script>
// ==== MODUŁ KONTROLI ADMINA ====
function loadModuleSettings() {
    // Inicjalizacja domyślnych wartości przy pierwszym użyciu
    const moduleKeys = [
        'module-edit-employee',
        'module-employee-details',
        'module-session-comment',
        'module-print-token',
        'module-unblock',
        'module-reset-device',
        'module-delete-employee'
    ];
    
    // Ustaw domyślnie wszystkie jako włączone jeśli nie ma żadnych zapisanych ustawień
    const hasAnySettings = moduleKeys.some(key => localStorage.getItem(key) !== null);
    if (!hasAnySettings) {
        moduleKeys.forEach(key => localStorage.setItem(key, 'true'));
    }
    
    const settings = {
        'module-edit-employee': localStorage.getItem('module-edit-employee') !== 'false',
        'module-employee-details': localStorage.getItem('module-employee-details') !== 'false',
        'module-session-comment': localStorage.getItem('module-session-comment') !== 'false',
        'module-print-token': localStorage.getItem('module-print-token') !== 'false',
        'module-unblock': localStorage.getItem('module-unblock') !== 'false',
        'module-reset-device': localStorage.getItem('module-reset-device') !== 'false',
        'module-delete-employee': localStorage.getItem('module-delete-employee') !== 'false'
    };

    // Ustaw checkboxy w panelu kontrolnym
    Object.keys(settings).forEach(key => {
        const checkbox = document.getElementById(key);
        if (checkbox) {
            checkbox.checked = settings[key];
        }
    });

    return settings;
}

function updateModuleSettings() {
    const modules = [
        'module-edit-employee',
        'module-employee-details',
        'module-session-comment',
        'module-print-token',
        'module-unblock',
        'module-reset-device',
        'module-delete-employee'
    ];
    
    modules.forEach(moduleId => {
        const checkbox = document.getElementById(moduleId);
        if (checkbox) {
            localStorage.setItem(moduleId, checkbox.checked);
        }
    });

    applyModuleSettings();
}

function applyModuleSettings() {
    const settings = {
        'module-edit-employee': localStorage.getItem('module-edit-employee') !== 'false',
        'module-employee-details': localStorage.getItem('module-employee-details') !== 'false',
        'module-session-comment': localStorage.getItem('module-session-comment') !== 'false',
        'module-print-token': localStorage.getItem('module-print-token') !== 'false',
        'module-unblock': localStorage.getItem('module-unblock') !== 'false',
        'module-reset-device': localStorage.getItem('module-reset-device') !== 'false',
        'module-delete-employee': localStorage.getItem('module-delete-employee') !== 'false'
    };

    // Edycja danych pracownika (.edit-block)
    if (!settings['module-edit-employee']) {
        document.querySelectorAll('.edit-block').forEach(el => el.style.display = 'none');
    } else {
        document.querySelectorAll('.edit-block').forEach(el => {
            const roles = el.dataset.roles ? el.dataset.roles.split(',').map(r => parseInt(r.trim(), 10)) : [];
            if (roles.length === 0 || roles.includes(window.USER_ROLE)) {
                el.style.display = '';
            }
        });
    }

    // Szczegóły pracownika (token, stawka, urlop, device_id, IP) - wszystkie div bez klasy specjalnej z data-roles="4,9"
    document.querySelectorAll('[data-roles="4,9"]').forEach(el => {
        // Sprawdź czy to nie edit-block (ten jest obsługiwany osobno)
        if (el.classList.contains('edit-block')) return;
        
        // Szczegóły pracownika (pierwszy div z data-roles="4,9")
        if (!el.querySelector('textarea') && !el.classList.contains('actions')) {
            if (!settings['module-employee-details']) {
                el.style.display = 'none';
            } else if ([4, 9].includes(window.USER_ROLE)) {
                el.style.display = '';
            }
        }
        
        // Komentarz do sesji
        if (el.querySelector('textarea[id^="session-comment-"]')) {
            if (!settings['module-session-comment']) {
                el.style.display = 'none';
            } else if ([4, 9].includes(window.USER_ROLE)) {
                el.style.display = '';
            }
        }
    });

    // Przycisk drukowania tokena
    if (!settings['module-print-token']) {
        document.querySelectorAll('button[onclick*="printToken"]').forEach(el => el.style.display = 'none');
    } else {
        document.querySelectorAll('button[onclick*="printToken"]').forEach(el => {
            const roles = el.dataset.roles ? el.dataset.roles.split(',').map(r => parseInt(r.trim(), 10)) : [];
            if (roles.length === 0 || roles.includes(window.USER_ROLE)) {
                el.style.display = '';
            }
        });
    }

    // Przycisk odblokowania
    if (!settings['module-unblock']) {
        document.querySelectorAll('button[onclick*="unblockEmployee"]').forEach(el => el.style.display = 'none');
    } else {
        document.querySelectorAll('button[onclick*="unblockEmployee"]').forEach(el => {
            const roles = el.dataset.roles ? el.dataset.roles.split(',').map(r => parseInt(r.trim(), 10)) : [];
            if (roles.length === 0 || roles.includes(window.USER_ROLE)) {
                el.style.display = '';
            }
        });
    }

    // Przycisk odpinania urządzenia
    if (!settings['module-reset-device']) {
        document.querySelectorAll('button[onclick*="resetDevice"]').forEach(el => el.style.display = 'none');
    } else {
        document.querySelectorAll('button[onclick*="resetDevice"]').forEach(el => {
            const roles = el.dataset.roles ? el.dataset.roles.split(',').map(r => parseInt(r.trim(), 10)) : [];
            if (roles.length === 0 || roles.includes(window.USER_ROLE)) {
                el.style.display = '';
            }
        });
    }

    // Przycisk usuwania pracownika
    if (!settings['module-delete-employee']) {
        document.querySelectorAll('button[onclick*="deleteEmployee"]').forEach(el => el.style.display = 'none');
    } else {
        document.querySelectorAll('button[onclick*="deleteEmployee"]').forEach(el => {
            const roles = el.dataset.roles ? el.dataset.roles.split(',').map(r => parseInt(r.trim(), 10)) : [];
            if (roles.length === 0 || roles.includes(window.USER_ROLE)) {
                el.style.display = '';
            }
        });
    }
}

function toggleModuleCheckbox(checkboxId) {
    const checkbox = document.getElementById(checkboxId);
    if (checkbox) {
        checkbox.checked = !checkbox.checked;
        updateModuleSettings();
    }
}
</script>

<script>
function applyRoleVisibility() {
    document.querySelectorAll('[data-roles]').forEach(el => {
        const allowed = el.dataset.roles
            .split(',')
            .map(r => parseInt(r.trim(), 10));

        if (!allowed.includes(window.USER_ROLE)) {
            el.style.display = 'none';
        } else {
            // Pokaż element jeśli użytkownik ma odpowiednią rolę
            el.style.display = '';
        }
    });
}

document.addEventListener('DOMContentLoaded', () => {
    // Wczytaj i zastosuj ustawienia modułów
    if (window.USER_ROLE === 9) {
        loadModuleSettings();
        applyModuleSettings();
    }
});
</script>

</body>
</html>
