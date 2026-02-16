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
<title>Lista budów</title>

<style>
body {
    font-family: Arial;
    background:#f4f4f4;
    padding:30px;
}

.container {
    max-width:700px;
    margin:auto;
    background:#fff;
    padding:30px;
    border-radius:10px;
    box-shadow:0 0 15px rgba(0,0,0,0.1);
}

.site {
    display:flex;
    justify-content:space-between;
    align-items:flex-start;
    padding:12px 14px;
    margin-bottom:8px;
    border-radius:8px;
    border:1px solid #e0e0e0;
    background:#fafafa;
}

.toolbar {
    display:flex;
    justify-content:space-between;
    align-items:center;
    margin-bottom:15px;
}

.view-switch {
    display:flex;
    gap:8px;
}

.view-btn {
    padding:6px 12px;
    border-radius:6px;
    border:1px solid #007bff;
    background:#fff;
    color:#007bff;
    cursor:pointer;
    font-size:14px;
}

.view-btn.active {
    background:#007bff;
    color:#fff;
}

.bulk-actions {
    display:flex;
    gap:8px;
}

.site-main label {
    font-weight:bold;
}

.manager-row {
    margin-top:4px;
    display:flex;
    flex-wrap:wrap;
    gap:6px 10px;
    align-items:flex-start;
}

.manager-tags {
    display:flex;
    flex-wrap:wrap;
    gap:6px;
}

.manager-badge {
    display:inline-flex;
    align-items:center;
    gap:4px;
    padding:3px 8px;
    border-radius:999px;
    background:#e3f2fd;
    color:#0d47a1;
    font-size:12px;
    cursor:pointer;
}

.manager-badge:hover {
    background:#bbdefb;
}

.manager-remove {
    font-weight:bold;
    color:#b71c1c;
}

.manager-empty {
    font-size:12px;
    color:#777;
}

.manager-actions select {
    padding:4px 6px;
    font-size:12px;
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
    <h3>⚙️ Panel sterowania modułami</h3>
    <div class="admin-control-grid">
        <div class="admin-control-item" onclick="toggleModuleCheckbox('module-checkboxes')">
            <input type="checkbox" id="module-checkboxes" onchange="updateModuleSettings()" onclick="event.stopPropagation();">
            <label for="module-checkboxes" onclick="event.stopPropagation();">Checkboxy zaznaczania budów</label>
        </div>
        <div class="admin-control-item" onclick="toggleModuleCheckbox('module-archive-buttons')">
            <input type="checkbox" id="module-archive-buttons" onchange="updateModuleSettings()" onclick="event.stopPropagation();">
            <label for="module-archive-buttons" onclick="event.stopPropagation();">Przyciski archiwizacji</label>
        </div>
        <div class="admin-control-item" onclick="toggleModuleCheckbox('module-add-manager')">
            <input type="checkbox" id="module-add-manager" onchange="updateModuleSettings()" onclick="event.stopPropagation();">
            <label for="module-add-manager" onclick="event.stopPropagation();">Dodawanie kierowników do budów</label>
        </div>
        <div class="admin-control-item" onclick="toggleModuleCheckbox('module-remove-manager')">
            <input type="checkbox" id="module-remove-manager" onchange="updateModuleSettings()" onclick="event.stopPropagation();">
            <label for="module-remove-manager" onclick="event.stopPropagation();">Usuwanie kierowników z budów</label>
        </div>
        <div class="admin-control-item" onclick="toggleModuleCheckbox('module-delete-site')">
            <input type="checkbox" id="module-delete-site" onchange="updateModuleSettings()" onclick="event.stopPropagation();">
            <label for="module-delete-site" onclick="event.stopPropagation();">Usuwanie budów</label>
        </div>
    </div>
</div>

<h2>🏗️ Lista budów</h2>

<div class="toolbar">
    <div class="view-switch">
        <button type="button" class="view-btn active" id="btnViewActive" onclick="setView('active')">Aktywne</button>
        <button type="button" class="view-btn" id="btnViewArchived" onclick="setView('archived')">Archiwum</button>
    </div>
    <div class="bulk-actions">
        <button type="button" id="archiveBtn" onclick="archiveSelected()" data-roles="9">Archiwizuj zaznaczone</button>
        <button type="button" id="restoreBtn" onclick="restoreSelected()" style="display:none;" data-roles="9">Przywróć zaznaczone</button>
    </div>
</div>

<div id="siteList"></div>

</div>

<script>
function goBackToEntry(fallbackUrl) {
    if (document.referrer) {
        window.location.href = document.referrer;
    } else {
        window.location.href = fallbackUrl;
    }
}

let currentView = 'active';
let managers = [];

async function loadManagers() {
    try {
        const res = await fetch('../get_managers.php');
        const data = await res.json();
        // Jeśli endpoint zwraca {success:false,...}, zabezpiecz się
        managers = Array.isArray(data) ? data : [];
    } catch (e) {
        console.error('Błąd ładowania listy kierowników:', e);
        managers = [];
    }
}

async function loadSites() {
    const res = await fetch(`../get_sites.php?status=${currentView}`);
    const data = await res.json();

    const div = document.getElementById("siteList");
    div.innerHTML = "";

    data.forEach(s => {
        const assignOptions = managers.length
            ? managers.map(m => `<option value="${m.id}">${m.first_name} ${m.last_name}</option>`).join('')
            : '<option value="">Brak dostępnych kierowników</option>';

        let assignedBadges = '<span class="manager-empty">Brak przypisanych kierowników</span>';
        if (s.manager_ids && s.manager_ids.length && managers.length) {
            const assignedIds = s.manager_ids.split(',').map(id => parseInt(id, 10)).filter(id => !isNaN(id));
            const assignedManagers = managers.filter(m => assignedIds.includes(parseInt(m.id, 10)));
            if (assignedManagers.length) {
                assignedBadges = assignedManagers.map(m => {
                    // Dla roli 9: klikalny badge z możliwością usuwania
                    const removableBadge = `<span class="manager-badge" data-roles="9" onclick="unassignManager(${s.id}, ${m.id})">${m.first_name} ${m.last_name}<span class="manager-remove">×</span></span>`;
                    // Dla pozostałych ról: tylko do podglądu (bez onclick)
                    const readonlyBadge = `<span class="manager-badge" data-roles="1,2,4,5" style="cursor: default;">${m.first_name} ${m.last_name}</span>`;
                    return removableBadge + readonlyBadge;
                }).join(' ');
            }
        }

        div.innerHTML += `
            <div class="site">
                <div class="site-main">
                    <label>
                        <input type="checkbox" class="site-checkbox" data-id="${s.id}" data-roles="9">
                        ${s.name}
                    </label>
                    <div class="manager-row">
                        <div class="manager-tags">${assignedBadges}</div>
                        <div class="manager-actions">
                            <select data-roles="9" onchange="assignManager(${s.id}, this.value); this.value='';">
                                <option value="">+ Dodaj kierownika</option>
                                ${assignOptions}
                            </select>
                        </div>
                    </div>
                </div>
                <button data-roles="4,9" onclick="deleteSite(${s.id})">Usuń</button>
            </div>
        `;
    });

    // Przełącz widoczność przycisków masowej akcji
    document.getElementById('archiveBtn').style.display = (currentView === 'active') ? 'inline-block' : 'none';
    document.getElementById('restoreBtn').style.display = (currentView === 'archived') ? 'inline-block' : 'none';

    if (typeof applyRoleVisibility === 'function') {
        applyRoleVisibility();
    }
    
    // Zastosuj ustawienia modułów (tylko dla admina)
    if (window.USER_ROLE === 9 && typeof applyModuleSettings === 'function') {
        applyModuleSettings();
    }
}

function setView(view) {
    currentView = view === 'archived' ? 'archived' : 'active';

    const btnActive = document.getElementById('btnViewActive');
    const btnArchived = document.getElementById('btnViewArchived');

    if (currentView === 'active') {
        btnActive.classList.add('active');
        btnArchived.classList.remove('active');
    } else {
        btnActive.classList.remove('active');
        btnArchived.classList.add('active');
    }

    loadSites();
}

async function changeStatusSelected(targetActive) {
    const checkboxes = Array.from(document.querySelectorAll('.site-checkbox:checked'));
    if (checkboxes.length === 0) {
        alert('Zaznacz co najmniej jedną budowę');
        return;
    }

    const confirmMessage = targetActive === 0
        ? 'Czy na pewno zarchiwizować zaznaczone budowy?'
        : 'Czy na pewno przywrócić zaznaczone budowy?';

    if (!confirm(confirmMessage)) return;

    for (const cb of checkboxes) {
        const id = parseInt(cb.dataset.id, 10);
        if (!id) continue;

        const res = await fetch("archive_site.php", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({
                id: id,
                active: targetActive
            })
        });

        const data = await res.json();
        if (!data.success) {
            alert("Błąd zmiany statusu dla budowy ID " + id);
        }
    }

    loadSites();
}

function archiveSelected() {
    changeStatusSelected(0);
}

function restoreSelected() {
    changeStatusSelected(1);
}

async function deleteSite(id) {
    if (!confirm("USUNĄĆ NA STAŁE tę budowę?")) return;

    const res = await fetch("delete_site.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ id })
    });

    const data = await res.json();
    if (data.success) loadSites();
}

async function assignManager(siteId, managerId) {
    if (!managerId) return;

    try {
        const res = await fetch('assign_manager.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ site_id: siteId, manager_id: managerId })
        });

        const data = await res.json();
        if (data.success) {
            await loadSites();
        } else {
            alert(data.error || 'Błąd przypisywania kierownika');
        }
    } catch (e) {
        console.error('Błąd assignManager:', e);
        alert('Błąd przypisywania kierownika');
    }
}

async function unassignManager(siteId, managerId) {
    if (!managerId) return;

    try {
        const res = await fetch('unassign_manager.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ site_id: siteId, manager_id: managerId })
        });

        const data = await res.json();
        if (data.success) {
            await loadSites();
        } else {
            alert(data.error || 'Błąd usuwania kierownika');
        }
    } catch (e) {
        console.error('Błąd unassignManager:', e);
        alert('Błąd usuwania kierownika');
    }
}

(async () => {
    await loadManagers();
    await loadSites();
})();
</script>

<script>
// ==== MODUŁ KONTROLI ADMINA ====
function loadModuleSettings() {
    // Inicjalizacja domyślnych wartości przy pierwszym użyciu
    const moduleKeys = [
        'module-checkboxes',
        'module-archive-buttons',
        'module-add-manager',
        'module-remove-manager',
        'module-delete-site'
    ];
    
    // Ustaw domyślnie wszystkie jako włączone jeśli nie ma żadnych zapisanych ustawień
    const hasAnySettings = moduleKeys.some(key => localStorage.getItem(key) !== null);
    if (!hasAnySettings) {
        moduleKeys.forEach(key => localStorage.setItem(key, 'true'));
    }
    
    const settings = {
        'module-checkboxes': localStorage.getItem('module-checkboxes') !== 'false',
        'module-archive-buttons': localStorage.getItem('module-archive-buttons') !== 'false',
        'module-add-manager': localStorage.getItem('module-add-manager') !== 'false',
        'module-remove-manager': localStorage.getItem('module-remove-manager') !== 'false',
        'module-delete-site': localStorage.getItem('module-delete-site') !== 'false'
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
    const modules = ['module-checkboxes', 'module-archive-buttons', 'module-add-manager', 'module-remove-manager', 'module-delete-site'];
    
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
        'module-checkboxes': localStorage.getItem('module-checkboxes') !== 'false',
        'module-archive-buttons': localStorage.getItem('module-archive-buttons') !== 'false',
        'module-add-manager': localStorage.getItem('module-add-manager') !== 'false',
        'module-remove-manager': localStorage.getItem('module-remove-manager') !== 'false',
        'module-delete-site': localStorage.getItem('module-delete-site') !== 'false'
    };

    // Checkboxy zaznaczania budów
    if (!settings['module-checkboxes']) {
        document.querySelectorAll('.site-checkbox').forEach(el => el.style.display = 'none');
    } else {
        document.querySelectorAll('.site-checkbox').forEach(el => {
            const roles = el.dataset.roles ? el.dataset.roles.split(',').map(r => parseInt(r.trim(), 10)) : [];
            if (roles.length === 0 || roles.includes(window.USER_ROLE)) {
                el.style.display = '';
            }
        });
    }

    // Przyciski archiwizacji/przywracania
    if (!settings['module-archive-buttons']) {
        const archiveBtn = document.getElementById('archiveBtn');
        const restoreBtn = document.getElementById('restoreBtn');
        if (archiveBtn) archiveBtn.style.display = 'none';
        if (restoreBtn) restoreBtn.style.display = 'none';
    } else {
        const archiveBtn = document.getElementById('archiveBtn');
        const restoreBtn = document.getElementById('restoreBtn');
        
        if (archiveBtn) {
            const roles = archiveBtn.dataset.roles ? archiveBtn.dataset.roles.split(',').map(r => parseInt(r.trim(), 10)) : [];
            if (roles.length === 0 || roles.includes(window.USER_ROLE)) {
                // Uwzględnij też logikę widoczności z loadSites()
                if (currentView === 'active') {
                    archiveBtn.style.display = 'inline-block';
                }
            }
        }
        
        if (restoreBtn) {
            const roles = restoreBtn.dataset.roles ? restoreBtn.dataset.roles.split(',').map(r => parseInt(r.trim(), 10)) : [];
            if (roles.length === 0 || roles.includes(window.USER_ROLE)) {
                // Uwzględnij też logikę widoczności z loadSites()
                if (currentView === 'archived') {
                    restoreBtn.style.display = 'inline-block';
                }
            }
        }
    }

    // Selecty dodawania kierowników
    if (!settings['module-add-manager']) {
        document.querySelectorAll('select[data-roles="9"]').forEach(el => el.style.display = 'none');
    } else {
        document.querySelectorAll('select[data-roles="9"]').forEach(el => {
            if (window.USER_ROLE === 9) {
                el.style.display = '';
            }
        });
    }

    // Badge kierowników z możliwością usuwania (z data-roles="9")
    if (!settings['module-remove-manager']) {
        document.querySelectorAll('.manager-badge[data-roles="9"]').forEach(el => el.style.display = 'none');
    } else {
        document.querySelectorAll('.manager-badge[data-roles="9"]').forEach(el => {
            if (window.USER_ROLE === 9) {
                el.style.display = '';
            }
        });
    }

    // Przyciski usuwania budów
    if (!settings['module-delete-site']) {
        document.querySelectorAll('button[data-roles*="9"][onclick*="deleteSite"]').forEach(el => el.style.display = 'none');
    } else {
        document.querySelectorAll('button[data-roles*="9"][onclick*="deleteSite"]').forEach(el => {
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
    if (typeof applyRoleVisibility === 'function') {
        applyRoleVisibility();
    }
    
    // Wczytaj i zastosuj ustawienia modułów
    if (window.USER_ROLE === 9) {
        loadModuleSettings();
        applyModuleSettings();
    }
});
</script>

</body>
</html>
