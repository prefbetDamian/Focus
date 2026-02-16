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
<title>Lista maszyn</title>

<style>
body {
    font-family: Arial;
    background:#f4f4f4;
    padding:30px;
}

.container {
    max-width:800px;
    margin:auto;
    background:#fff;
    padding:30px;
    border-radius:10px;
    box-shadow:0 0 15px rgba(0,0,0,0.1);
}

.machine {
    display:flex;
    justify-content:space-between;
    align-items:center;
    padding:10px;
    border-bottom:1px solid #ddd;
}

.machine small {
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

.active  { color:#28a745; }
.inactive { color:#6c757d; }

.owner {
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

.actions button {
    margin-left:6px;
}

.filter {
    margin-bottom:15px;
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
</style>
</head>
<body>

<div class="container">

<a href="#" class="back" onclick="goBackToEntry('dashboard.php'); return false;">⬅ Powrót</a>

<!-- Panel kontrolny admina (tylko rola 9) -->
<div class="admin-control-panel" data-roles="9" style="display:none;">
    <h3>⚙️ Panel sterowania modułami maszyn</h3>
    <div class="admin-control-grid">
        <div class="admin-control-item" onclick="toggleModuleCheckbox('module-machine-details')">
            <input type="checkbox" id="module-machine-details" onchange="updateModuleSettings()" onclick="event.stopPropagation();">
            <label for="module-machine-details" onclick="event.stopPropagation();">Szczegóły maszyny (stawka, skrót, wynajmujący)</label>
        </div>
        <div class="admin-control-item" onclick="toggleModuleCheckbox('module-delete-machine')">
            <input type="checkbox" id="module-delete-machine" onchange="updateModuleSettings()" onclick="event.stopPropagation();">
            <label for="module-delete-machine" onclick="event.stopPropagation();">Usuwanie maszyny</label>
        </div>
    </div>
</div>

<h2>🚜 Lista maszyn</h2>

<div style="display:flex;gap:20px;margin-bottom:15px;flex-wrap:wrap;">
    <div class="filter">
        🔍 Filtruj właściciela:
        <select id="ownerFilter" onchange="renderMachines()">
            <option value="">— wszyscy —</option>
            <option value="PREFBET">PREF-BET</option>
            <option value="BG">BG Construction</option>
            <option value="PUH">PUH</option>
            <option value="MARBUD">MAR-BUD</option>
            <option value="DRWAL">DRWAL</option>
            <option value="MERITUM">MERITUM</option>
            <option value="ZB">ZB</option>
        </select>
    </div>
    
    <div class="filter">
        📊 Status:
        <select id="statusFilter" onchange="renderMachines()">
            <option value="">— wszystkie —</option>
            <option value="active">✔ Aktywne</option>
            <option value="inactive">❌ Nieaktywne</option>
        </select>
    </div>
</div>

<div id="machineList"></div>

</div>

<script>
function goBackToEntry(fallbackUrl) {
    if (document.referrer) {
        window.location.href = document.referrer;
    } else {
        window.location.href = fallbackUrl;
    }
}

let machines = [];
let timers = {};

async function loadMachines() {
    const res = await fetch("./get_machines.php");
    const data = await res.json();

    if (!Array.isArray(data)) {
        console.error("Błędna odpowiedź API:", data);
        machines = [];
    } else {
        machines = data;
    }

    renderMachines();
}


function renderMachines() {
    const div = document.getElementById("machineList");
    div.innerHTML = "";

    const filter = document.getElementById("ownerFilter").value;
    const statusFilter = document.getElementById("statusFilter").value;

    machines.forEach(m => {
        if (filter && m.owner !== filter) return;
        
        // Filtruj po statusie
        if (statusFilter === 'active' && m.active != 1) return;
        if (statusFilter === 'inactive' && m.active == 1) return;

        const statusIcon = m.active == 1
            ? `<span class="status active">✔</span>`
            : `<span class="status inactive">❌</span>`;

        const timer = m.start_time
            ? `<span class="timer" id="timer-${m.id}"></span>`
            : ``;

        const rateHtml = `<small data-roles="9">Stawka: <b>${Number(m.hour_rate).toFixed(2)} zł/h</b></small>`;

        const shortHtml = m.short_name
            ? `<br data-roles="9"><span data-roles="9">🔹 Skrót: <b>${m.short_name}</b></span>`
            : ``;

        const renterHtml = m.renter
            ? `<br data-roles="9"><span data-roles="9">🤝 Wynajmujący: <b>${m.renter}</b></span>`
            : ``;

        div.innerHTML += `
            <div class="machine">
                <div>
                    ${statusIcon}
                    <b>${m.machine_name}</b>
                    ${shortHtml}
                    
                    ${timer}<br>
                    ${rateHtml}
                    <small>
    Nr ewid.: <b>${m.registry_number}</b>
    <br>🏢 ${m.owner}
    ${renterHtml}
    ${m.active && m.operator_first_name
        ? `<br>👷 ${m.operator_first_name} ${m.operator_last_name}`
        : ``}
</small>

                </div>
                <div class="actions">
                    <button data-roles="4,9" onclick="deleteMachine(${m.id})">Usuń</button>
                </div>
            </div>
        `;

        if (m.start_time) {
            startTimer(m.id, m.start_time);
        }
    });

    if (typeof applyRoleVisibility === 'function') {
        applyRoleVisibility();
    }
    
    // Zastosuj ustawienia modułów (tylko dla admina)
    if (window.USER_ROLE === 9 && typeof applyModuleSettings === 'function') {
        applyModuleSettings();
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

async function deleteMachine(id) {
    if (!confirm("Usunąć maszynę?")) return;

    const res = await fetch("delete_machine.php", {
        method:"POST",
        headers:{"Content-Type":"application/json"},
        body:JSON.stringify({id})
    });

    const data = await res.json();

    if (!data.success) {
        alert(data.message || "Nie można usunąć maszyny – ma aktywną sesję");
    }

    // ⬅️ ZAWSZE odśwież listę (jak w employees)
    loadMachines();
}

</script>

<script>
// ==== MODUŁ KONTROLI ADMINA ====
function loadModuleSettings() {
    // Inicjalizacja domyślnych wartości przy pierwszym użyciu
    const moduleKeys = [
        'module-machine-details',
        'module-delete-machine'
    ];
    
    // Ustaw domyślnie wszystkie jako włączone jeśli nie ma żadnych zapisanych ustawień
    const hasAnySettings = moduleKeys.some(key => localStorage.getItem(key) !== null);
    if (!hasAnySettings) {
        moduleKeys.forEach(key => localStorage.setItem(key, 'true'));
    }
    
    const settings = {
        'module-machine-details': localStorage.getItem('module-machine-details') !== 'false',
        'module-delete-machine': localStorage.getItem('module-delete-machine') !== 'false'
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
        'module-machine-details',
        'module-delete-machine'
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
        'module-machine-details': localStorage.getItem('module-machine-details') !== 'false',
        'module-delete-machine': localStorage.getItem('module-delete-machine') !== 'false'
    };

    // Szczegóły maszyny dla admina (stawka, skrót, wynajmujący)
    if (!settings['module-machine-details']) {
        document.querySelectorAll('small[data-roles="9"], span[data-roles="9"], br[data-roles="9"]').forEach(el => el.style.display = 'none');
    } else {
        document.querySelectorAll('small[data-roles="9"], span[data-roles="9"]').forEach(el => {
            if (window.USER_ROLE === 9) {
                el.style.display = '';
            }
        });
        document.querySelectorAll('br[data-roles="9"]').forEach(el => {
            if (window.USER_ROLE === 9) {
                el.style.display = '';
            }
        });
    }

    // Przycisk usuwania maszyny
    if (!settings['module-delete-machine']) {
        document.querySelectorAll('button[onclick*="deleteMachine"]').forEach(el => el.style.display = 'none');
    } else {
        document.querySelectorAll('button[onclick*="deleteMachine"]').forEach(el => {
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
    loadMachines();
    
    // Wczytaj i zastosuj ustawienia modułów
    if (window.USER_ROLE === 9) {
        loadModuleSettings();
        applyModuleSettings();
    }
});

</script>

</body>
</html>
