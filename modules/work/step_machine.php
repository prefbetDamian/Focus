<?php
/**
 * STEP 2: Wybór maszyny (tylko dla operatorów)
 */

require_once __DIR__.'/../../core/session.php';
require_once __DIR__.'/../../core/auth.php';

$user = requireUser();
$pdo = require __DIR__.'/../../core/db.php';

// Sprawdź czy operator
if (!$user['is_operator']) {
    header('Location: step_confirm.php');
    exit;
}

// Pobierz dostępne maszyny (unikalne, bez duplikatów)
// Grupujemy po m.id aby każda maszyna pojawiła się tylko raz
// Maszyny z registry_number = '1' mogą być używane przez wielu pracowników jednocześnie
$stmt = $pdo->query("
    SELECT 
        m.id,
        m.machine_name,
        m.registry_number,
        m.owner,
        (CASE WHEN m.registry_number = '1' THEN NULL ELSE MAX(ws.id) END) as active_session
    FROM machines m
    LEFT JOIN work_sessions ws ON ws.machine_id = m.id AND ws.end_time IS NULL
    GROUP BY m.id, m.machine_name, m.registry_number, m.owner
    ORDER BY CAST(m.registry_number AS UNSIGNED), m.machine_name
");
$machines = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Wybierz maszynę</title>

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
            max-width: 800px;
            width: 100%;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            max-height: none;
            overflow: visible;
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
            margin-bottom: 10px;
        }
        .site-info {
            background: #e7f3ff;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            text-align: center;
        }
        .machine-list {
            display: flex;
            flex-direction: column;
            gap: 10px;
            margin-bottom: 20px;
        }
        .machine-btn {
            background: #f8f9fa;
            border: 2px solid #e9ecef;
            border-radius: 10px;
            padding: 15px;
            cursor: pointer;
            transition: all 0.3s ease;
            text-align: left;
        }
        .machine-btn:hover:not(.disabled) {
            background: #e9ecef;
            border-color: #667eea;
            transform: translateX(5px);
        }
        .machine-btn.selected {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-color: #667eea;
        }
        .machine-btn.disabled {
            opacity: 0.5;
            cursor: not-allowed;
            background: #f8d7da;
        }
        .machine-name {
            font-weight: bold;
            font-size: 16px;
            margin-bottom: 5px;
        }
        .machine-details {
            font-size: 14px;
            opacity: 0.8;
        }
        .badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 5px;
            font-size: 12px;
            margin-left: 10px;
        }
        .badge-active {
            background: #dc3545;
            color: white;
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
        .btn-next {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        .btn-next:hover {
            opacity: 0.9;
        }
        .btn-next:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        .machine-dropdown {
            position: relative;
            flex: 1;
        }
        .dropdown-menu {
            display: none;
            position: absolute;
            bottom: 100%;
            left: 0;
            right: 0;
            background: white;
            border: 2px solid #667eea;
            border-radius: 10px;
            max-height: 60vh;
            overflow-y: auto;
            -webkit-overflow-scrolling: touch;
            z-index: 1000;
            box-shadow: 0 -10px 30px rgba(0,0,0,0.2);
            margin-bottom: 5px;
        }
        .dropdown-menu.show {
            display: block;
        }
        .dropdown-item {
            padding: 15px;
            cursor: pointer;
            border-bottom: 1px solid #eee;
            transition: background 0.2s;
        }
        .dropdown-item:hover {
            background: #f8f9fa;
        }
        .dropdown-item.disabled {
            opacity: 0.5;
            cursor: not-allowed;
            background: #f8d7da;
        }
        .dropdown-item .machine-name {
            font-weight: 600;
            margin-bottom: 4px;
            font-size: 15px;
        }
        .dropdown-item .machine-details {
            font-size: 13px;
            color: #666;
        }
        #machineDisplay {
            padding: 15px;
            background: #e7f3ff;
            border-radius: 10px;
            font-weight: 600;
            text-align: center;
            color: #667eea;
            cursor: pointer;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .machine-input-group {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        @media (min-width: 768px) {
            .machine-input-group {
                flex-direction: row;
                align-items: stretch;
            }
            .dropdown-menu {
                max-width: 600px;
                left: auto;
                right: 0;
            }
        }
    </style>
</head>
<body>
    <video class="bg-video" autoplay loop muted playsinline>
        <source src="../../background.mp4" type="video/mp4">
    </video>
    <div class="video-overlay"></div>
    <div class="container">
        <h1>🚜 Wybierz maszynę</h1>
        <div class="subtitle">Krok 2 z 3</div>

        <div class="site-info" id="siteInfo">
            <strong>Budowa:</strong> <span id="siteName">-</span>
        </div>

        <!-- Pole wyboru maszyny -->
        <div style="margin-bottom: 20px;">
            <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #333;">
                Wyszukaj maszynę:
            </label>
            <input 
                type="text" 
                id="machineFilter" 
                placeholder="Wpisz numer ewidencyjny..." 
                inputmode="numeric"
                pattern="\d*"
                style="width: 100%; padding: 12px; border: 2px solid #ddd; border-radius: 10px; font-size: 15px; margin-bottom: 10px;">
            
            <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #333;">
                Wybierz maszynę:
            </label>
            <div class="machine-input-group">
                <div class="machine-dropdown">
                    <div id="machineDisplay" onclick="toggleMachineDropdown()">
                        Kliknij aby wybrać maszynę ▼
                    </div>
                    <div id="machineDropdownMenu" class="dropdown-menu">
                        <?php foreach ($machines as $machine): ?>
                            <?php 
                                $searchString = strtolower(($machine['machine_name'] ?? '') . ' ' . ($machine['registry_number'] ?? ''));
                                $isSpecialMachine = (string)($machine['registry_number'] ?? '') === '1';
                                // Maszyna o nr ewidencyjnym 1 nigdy nie jest blokowana jako "zajęta"
                                $isActive = !$isSpecialMachine && !empty($machine['active_session']);
                            ?>
                            <div
                                class="dropdown-item<?= $isActive ? ' disabled' : '' ?>"
                                data-id="<?= $machine['id'] ?>"
                                data-name="<?= htmlspecialchars($machine['machine_name']) ?>"
                                data-registry="<?= htmlspecialchars($machine['registry_number']) ?>"
                                data-owner="<?= htmlspecialchars($machine['owner']) ?>"
                                data-search="<?= htmlspecialchars($searchString) ?>"
                            >
                                <div class="machine-name">
                                    <?= htmlspecialchars($machine['machine_name']) ?> - <?= htmlspecialchars($machine['registry_number']) ?>
                                    <?php if ($isActive): ?>
                                        <span class="badge badge-active">ZAJĘTA</span>
                                    <?php endif; ?>
                                </div>
                                <div class="machine-details">
                                    Właściciel: <?= htmlspecialchars($machine['owner']) ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <div id="filterInfo" style="margin-top: 5px; font-size: 13px; color: #666;"></div>
            
            <div id="machineInfo" style="margin-top: 10px; padding: 12px; background: #f8f9fa; border-radius: 8px; display: none;">
                <div style="font-size: 14px; color: #666;">
                    <strong>Maszyna:</strong> <span id="infoName">-</span><br>
                    <strong>Nr rej:</strong> <span id="infoRegistry">-</span><br>
                    <strong>Właściciel:</strong> <span id="infoOwner">-</span>
                </div>
            </div>
        </div>

        <div class="actions">
            <button class="btn btn-back" onclick="back()">⬅️ Wstecz</button>
            <button class="btn btn-next" id="btnNext" disabled onclick="next()">Dalej ➡️</button>
        </div>
    </div>

    <script>
        // Wczytaj dane z sessionStorage
        const siteName = sessionStorage.getItem('selected_site_name');
        if (!siteName) {
            window.location.href = 'step_building.php';
        } else {
            document.getElementById('siteName').textContent = siteName;
        }

        let selectedMachineId = null;
        let selectedMachineName = null;
        let selectedMachineRegistry = null;
        let selectedMachineOwner = null;

        const machineFilter = document.getElementById('machineFilter');
        const filterInfo = document.getElementById('filterInfo');
        const machineInfo = document.getElementById('machineInfo');
        const btnNext = document.getElementById('btnNext');
        const machineDisplay = document.getElementById('machineDisplay');
        const machineDropdownMenu = document.getElementById('machineDropdownMenu');
        const machineItems = Array.from(machineDropdownMenu.querySelectorAll('.dropdown-item'));

        // Wczytaj zapisany wybór przy starcie
        window.addEventListener('load', function() {
            const savedId = sessionStorage.getItem('selected_machine_id');
            const savedName = sessionStorage.getItem('selected_machine_name');
            const savedRegistry = sessionStorage.getItem('selected_machine_registry');
            const savedOwner = sessionStorage.getItem('selected_machine_owner');

            if (savedId && savedName && savedRegistry) {
                selectedMachineId = parseInt(savedId, 10);
                selectedMachineName = savedName;
                selectedMachineRegistry = savedRegistry;
                selectedMachineOwner = savedOwner || '';

                updateMachineDisplay(savedName, savedRegistry);
                updateMachineInfo(savedName, savedRegistry, selectedMachineOwner);
                btnNext.disabled = false;
            }
        });

        // Inicjalizacja kliknięć na elementach listy
        machineItems.forEach(item => {
            if (item.classList.contains('disabled')) {
                return;
            }
            item.addEventListener('click', function() {
                const id = parseInt(item.dataset.id, 10);
                const name = item.dataset.name;
                const registry = item.dataset.registry;
                const owner = item.dataset.owner || '';
                selectMachine(id, name, registry, owner);
            });
        });

        // Filtrowanie listy w dropdownie
        machineFilter.addEventListener('input', function() {
            // Pozwól tylko na cyfry
            this.value = this.value.replace(/\D/g, '');

            const searchText = this.value.toLowerCase().trim();
            let visibleCount = 0;

            machineItems.forEach(item => {
                const search = (item.dataset.search || '').toLowerCase();
                if (!searchText || search.includes(searchText)) {
                    item.style.display = '';
                    visibleCount++;
                } else {
                    item.style.display = 'none';
                }
            });

            // Automatycznie podstaw pierwszą pasującą maszynę
            if (searchText && visibleCount > 0) {
                const firstVisible = machineItems.find(it => it.style.display !== 'none' && !it.classList.contains('disabled'));
                if (firstVisible) {
                    const id = parseInt(firstVisible.dataset.id, 10);
                    const name = firstVisible.dataset.name;
                    const registry = firstVisible.dataset.registry;
                    const owner = firstVisible.dataset.owner || '';
                    selectMachine(id, name, registry, owner);
                }
            }

            if (searchText) {
                filterInfo.textContent = `Znaleziono: ${visibleCount} maszyn(y)`;
                filterInfo.style.color = visibleCount > 0 ? '#28a745' : '#dc3545';
            } else {
                filterInfo.textContent = '';
            }
        });

        function toggleMachineDropdown() {
            if (machineDropdownMenu.classList.contains('show')) {
                machineDropdownMenu.classList.remove('show');
                machineDropdownMenu.style.maxHeight = '';
                return;
            }

            const rect = machineDisplay.getBoundingClientRect();
            const viewportHeight = window.innerHeight || document.documentElement.clientHeight || 800;
            const availableAbove = rect.top - 10; // miejsce od górnej krawędzi do pola
            const minHeight = 120;

            // Wysokość listy nie większa niż dostępne miejsce nad przyciskiem
            // i nie większa niż 80% wysokości okna
            const hardCap = viewportHeight * 0.8;
            const maxHeight = Math.min(Math.max(minHeight, availableAbove), hardCap);
            machineDropdownMenu.style.maxHeight = maxHeight + 'px';

            machineDropdownMenu.classList.add('show');
        }

        // Zamknij dropdown po kliknięciu poza nim
        document.addEventListener('click', function(e) {
            if (!e.target.closest('.machine-dropdown')) {
                machineDropdownMenu.classList.remove('show');
                machineDropdownMenu.style.maxHeight = '';
            }
        });

        function updateMachineDisplay(name, registry) {
            machineDisplay.textContent = `${name} - ${registry} ▼`;
            machineDisplay.style.background = 'linear-gradient(135deg, #667eea 0%, #764ba2 100%)';
            machineDisplay.style.color = 'white';
        }

        function updateMachineInfo(name, registry, owner) {
            document.getElementById('infoName').textContent = name;
            document.getElementById('infoRegistry').textContent = registry;
            document.getElementById('infoOwner').textContent = owner || '-';
            machineInfo.style.display = 'block';
            machineInfo.style.background = '#d4edda';
            machineInfo.style.border = '2px solid #28a745';
        }

        function selectMachine(id, name, registry, owner) {
            selectedMachineId = id;
            selectedMachineName = name;
            selectedMachineRegistry = registry;
            selectedMachineOwner = owner || '';

            updateMachineDisplay(name, registry);
            updateMachineInfo(name, registry, selectedMachineOwner);
            machineDropdownMenu.classList.remove('show');
            btnNext.disabled = false;

            sessionStorage.setItem('selected_machine_id', selectedMachineId);
            sessionStorage.setItem('selected_machine_name', selectedMachineName);
            sessionStorage.setItem('selected_machine_registry', selectedMachineRegistry);
            sessionStorage.setItem('selected_machine_owner', selectedMachineOwner);
        }

        function next() {
            if (!selectedMachineId) return;

            // Zapisz w sessionStorage
            sessionStorage.setItem('selected_machine_id', selectedMachineId);
            sessionStorage.setItem('selected_machine_name', selectedMachineName);
            sessionStorage.setItem('selected_machine_registry', selectedMachineRegistry);
            sessionStorage.setItem('selected_machine_owner', selectedMachineOwner || '');

            window.location.href = 'step_confirm.php';
        }

        function back() {
            window.location.href = 'step_building.php';
        }
    </script>
</body>
</html>
