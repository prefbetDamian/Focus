<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tankowanie Maszyny - RCP System</title>

    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Arial, sans-serif;
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            overflow-x: hidden;
            padding: 20px 0;
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
            padding: 40px;
            max-width: 600px;
            width: 90%;
            border-radius: 25px;
            box-shadow: 0 15px 50px rgba(0, 0, 0, 0.4);
        }

        .header {
            text-align: center;
            margin-bottom: 30px;
        }

        .header h1 {
            color: #333;
            font-size: 28px;
            margin-bottom: 10px;
        }

        .header .subtitle {
            color: #667eea;
            font-size: 16px;
            font-weight: 600;
        }

        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 18px;
            margin-bottom: 20px;
        }

        .form-full {
            grid-column: 1/-1;
        }

        input, select {
            width: 100%;
            padding: 16px;
            font-size: 16px;
            font-weight: 600;
            border: 3px solid #667eea;
            border-radius: 12px;
            transition: all 0.3s ease;
            background: white;
            text-align: center;
        }

        input:focus, select:focus {
            outline: none;
            border-color: #764ba2;
            box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.2);
            transform: scale(1.02);
        }

        select {
            cursor: pointer;
        }

        .pin-section {
            margin-top: 25px;
            padding: 20px;
            background: rgba(102, 126, 234, 0.05);
            border-radius: 15px;
            border: 2px solid rgba(102, 126, 234, 0.1);
        }

        .pin-section h2 {
            color: #333;
            font-size: 16px;
            margin-bottom: 15px;
            text-align: center;
        }

        .pin-section h2 span {
            color: #667eea;
            font-weight: 700;
        }

        .pin-box {
            display: flex;
            justify-content: center;
            gap: 10px;
            margin-bottom: 10px;
        }

        .pin-input {
            width: 60px;
            height: 60px;
            font-size: 28px;
            font-weight: bold;
            text-align: center;
            border: 3px solid #667eea;
            border-radius: 12px;
            transition: all 0.3s ease;
            background: white;
            padding: 0;
        }

        .pin-input:focus {
            outline: none;
            border-color: #764ba2;
            box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.3);
            transform: scale(1.1);
        }

        button {
            width: 100%;
            padding: 18px;
            font-size: 18px;
            font-weight: 700;
            color: white;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            border-radius: 12px;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-top: 25px;
        }

        button:hover:not(:disabled) {
            transform: translateY(-3px);
            box-shadow: 0 10px 25px rgba(102, 126, 234, 0.4);
        }

        button:active {
            transform: translateY(0);
        }

        button:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }

        .back-btn {
            background: linear-gradient(135deg, #6c757d 0%, #5a6268 100%);
            margin-top: 15px;
        }

        .back-btn:hover {
            box-shadow: 0 10px 25px rgba(108, 117, 125, 0.4);
        }

        #log {
            margin-top: 20px;
            padding: 15px;
            border-radius: 10px;
            font-size: 15px;
            font-weight: 600;
            text-align: center;
            display: none;
        }

        #log.show {
            display: block;
        }

        #log.success {
            background: #d4edda;
            color: #155724;
            border: 2px solid #c3e6cb;
        }

        #log.error {
            background: #f8d7da;
            color: #721c24;
            border: 2px solid #f5c6cb;
        }

        .info-box {
            background: rgba(102, 126, 234, 0.1);
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            text-align: center;
        }

        .info-box strong {
            color: #667eea;
        }

        .mode-switch {
            display: flex;
            gap: 20px;
            margin-bottom: 25px;
        }

        /* Kafelki jak w panelu głównym */
        .module-btn {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 15px;
            padding: 24px 16px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 8px;
            flex: 1;
        }

        .module-btn .icon {
            font-size: 32px;
        }

        .module-btn:hover {
            transform: translateY(-4px);
            box-shadow: 0 10px 25px rgba(102, 126, 234, 0.4);
        }

        /* Wyraźne podświetlenie wybranego trybu tankowania */
        .mode-switch .module-btn {
            opacity: 0.7;
        }

        .mode-switch .module-btn.active {
            opacity: 1;
            transform: translateY(-2px) scale(1.02);
            box-shadow: 0 0 0 3px rgba(255, 255, 255, 0.9), 0 14px 30px rgba(102, 126, 234, 0.75);
        }

        .module-btn.secondary {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
        }

        /* Dropdown jak w wyborze budowy (step_building) */
        .site-dropdown {
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
            padding: 12px 15px;
            cursor: pointer;
            border-bottom: 1px solid #eee;
            transition: background 0.2s;
            font-size: 15px;
        }
        .dropdown-item:hover {
            background: #f8f9fa;
        }
        .dropdown-item:last-child {
            border-bottom: none;
        }
        #ownerDisplay,
        #machineDisplay {
            padding: 12px 15px;
            background: #e7f3ff;
            border-radius: 10px;
            font-weight: 600;
            text-align: center;
            color: #667eea;
            cursor: pointer;
            margin-top: 6px;
        }

        @media (max-width: 600px) {
            .container {
                padding: 30px 20px;
            }

            .header h1 {
                font-size: 24px;
            }

            .pin-input {
                width: 55px;
                height: 55px;
                font-size: 24px;
            }

            input, select {
                font-size: 15px;
                padding: 14px;
            }

            .form-grid {
                gap: 14px;
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
        <div class="header">
            <h1>⛽ Tankowanie Maszyny</h1>
            <div class="subtitle">Panel operatora</div>
        </div>

        <div class="mode-switch">
            <button type="button" id="modeExternal" class="module-btn active" onclick="setMode('external')">
                <div class="icon">⛽</div>
                <div>TANKOWANIE ZEWNĘTRZNE</div>
                <small>Stacja paliw / wyjazd</small>
            </button>
            <button type="button" id="modeInternal" class="module-btn secondary" onclick="setMode('internal')">
                <div class="icon">🏭</div>
                <div>TANKOWANIE WEWNĘTRZNE</div>
                <small>Dystrybutor na bazie</small>
            </button>
        </div>

        <div class="info-box" id="operatorInfo">
            <strong>Operator:</strong> <span id="operatorName">Ładowanie...</span>
        </div>

        <div class="form-grid">
            <input type="number" id="liters" placeholder="Tankowane Litry" step="0.01" required>
            <input type="number" id="mh" placeholder="m-h / Przebieg" step="0.01" required>

            <div id="machineSelector" class="form-full">
                <!-- Ukryte oryginalne pola dla logiki JS -->
                <select id="owner" class="form-full" required style="display:none;">
                    <option value="">— Wybierz Firmę —</option>
                    <option value="ALL">WSZYSTKIE MASZYNY</option>
                    <option value="PREF-BET">PREF-BET</option>
                    <option value="BG">BG</option>
                    <option value="PUH">PUH</option>
                    <option value="MAR-BUD">MAR-BUD</option>
                    <option value="DRWAL">DRWAL</option>
                    <option value="MERITUM">MERITUM</option>
                </select>

                <input
                    type="text"
                    id="machineInput"
                    class="form-full"
                    list="machinesList"
                    placeholder="— Wybierz Maszynę —"
                    autocomplete="off"
                    required
                    style="display:none;"
                >
                <datalist id="machinesList"></datalist>

                <!-- Nowy widok dropdown jak w wyborze budowy -->
                <div style="margin-top:8px;">
                    <label style="display:block;margin-bottom:6px;font-weight:600;color:#333;">Właściciel maszyny:</label>
                    <div class="site-dropdown">
                        <div id="ownerDisplay" onclick="toggleOwnerDropdown()">
                            Wybierz firmę ▼
                        </div>
                        <div id="ownerDropdown" class="dropdown-menu">
                            <div class="dropdown-item" data-value="ALL" onclick="selectOwner('ALL')"><strong>💡 WSZYSTKIE MASZYNY</strong></div>
                            <div class="dropdown-item" data-value="PREF-BET" onclick="selectOwner('PREF-BET')">PREF-BET</div>
                            <div class="dropdown-item" data-value="BG" onclick="selectOwner('BG')">BG</div>
                            <div class="dropdown-item" data-value="PUH" onclick="selectOwner('PUH')">PUH</div>
                            <div class="dropdown-item" data-value="MAR-BUD" onclick="selectOwner('MAR-BUD')">MAR-BUD</div>
                            <div class="dropdown-item" data-value="DRWAL" onclick="selectOwner('DRWAL')">DRWAL</div>
                            <div class="dropdown-item" data-value="MERITUM" onclick="selectOwner('MERITUM')">MERITUM</div>
                        </div>
                    </div>
                </div>

                <div style="margin-top:12px;">
                    <label style="display:block;margin-bottom:6px;font-weight:600;color:#333;">Maszyna:</label>
                    <div class="site-dropdown">
                        <div id="machineDisplay" onclick="toggleMachineDropdown()">
                            Wybierz maszynę ▼
                        </div>
                        <div id="machineDropdown" class="dropdown-menu"></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="pin-section">
            <h2><span>PIN ZATWIERDZAJĄCY</span></h2>
            <p id="pinHint" style="text-align: center; color: #666; font-size: 14px; margin-bottom: 10px;">
                Tryb ZEWNĘTRZNY: wymagany PIN operatora 
            </p>
            <div class="pin-box" id="pinManager"></div>
            <input type="hidden" id="managerPin">
        </div>

        <button onclick="saveFuel()" id="saveBtn">💾 ZAPISZ TANKOWANIE</button>
        <button class="back-btn" onclick="goBackToEntry('../../panel.php')">← Powrót do panelu</button>

        <div id="log"></div>
    </div>

    <script>
        let authorized = false;
        const machinesMap = {};
        let fuelMode = 'external';
        let activeMachine = null;
        let preselectMachineId = null;
        let selectedOwner = '';

        function goBackToEntry(fallbackUrl) {
            if (document.referrer) {
                window.location.href = document.referrer;
            } else {
                window.location.href = fallbackUrl;
            }
        }

        // Sprawdź autoryzację
        async function checkAuth() {
            try {
                const res = await fetch('check_operator.php');
                const data = await res.json();

                if (!data.authorized) {
                    alert(data.message || 'Brak dostępu');
                    window.location.href = '../../panel.php';
                    return;
                }

                authorized = true;
                document.getElementById('operatorName').textContent = 
                    data.first_name + ' ' + data.last_name;

                // Zapamiętaj aktywną maszynę z sesji (jeśli jest) i użyj do preselekcji
                if (data.active_machine_id) {
                    activeMachine = {
                        id: data.active_machine_id,
                        name: data.active_machine_name || '',
                        registry: data.active_machine_registry || '',
                        owner: data.active_machine_owner || ''
                    };

                    // Ustaw preselekcję maszyny (zostanie wybrana gdy załadujemy listę)
                    preselectMachineId = activeMachine.id;

                    // Automatycznie ustaw "WSZYSTKIE MASZYNY"
                    selectOwner('ALL');
                } else {
                    // Jeśli brak aktywnej maszyny, również ustaw "WSZYSTKIE MASZYNY"
                    selectOwner('ALL');
                }
            } catch (err) {
                console.error('Błąd autoryzacji:', err);
                alert('Błąd sprawdzania uprawnień');
                window.location.href = '../../panel.php';
            }
        }

        // Dropdown właściciela (firma)
        function toggleOwnerDropdown() {
            const menu = document.getElementById('ownerDropdown');
            if (!menu) return;

            if (menu.classList.contains('show')) {
                menu.classList.remove('show');
                menu.style.maxHeight = '';
                return;
            }

            // Oblicz dostępną wysokość odnosnie kontenera
            const container = document.querySelector('.container');
            const display = document.getElementById('ownerDisplay');
            
            if (container && display) {
                const containerRect = container.getBoundingClientRect();
                const displayRect = display.getBoundingClientRect();
                
                // Dostępne miejsce od display do góry kontenera (minus margines)
                const availableSpace = displayRect.top - containerRect.top - 20;
                const minHeight = 150;
                const maxHeight = Math.max(minHeight, Math.min(availableSpace, 400));
                
                menu.style.maxHeight = maxHeight + 'px';
            }

            menu.classList.add('show');
        }

        function selectOwner(value) {
            const ownerSelect = document.getElementById('owner');
            const ownerDisplay = document.getElementById('ownerDisplay');
            const menu = document.getElementById('ownerDropdown');

            selectedOwner = value;

            if (ownerSelect) {
                ownerSelect.value = value;
                loadMachines();
            }

            if (ownerDisplay) {
                if (value === 'ALL') {
                    ownerDisplay.innerHTML = '💡 WSZYSTKIE MASZYNY ▼';
                } else {
                    ownerDisplay.innerHTML = value + ' ▼';
                }
                ownerDisplay.style.background = 'linear-gradient(135deg, #667eea 0%, #764ba2 100%)';
                ownerDisplay.style.color = 'white';
            }

            if (menu) {
                menu.classList.remove('show');
                menu.style.maxHeight = '';
            }

            // Po zmianie firmy wyczyść wybór maszyny
            const machineDisplay = document.getElementById('machineDisplay');
            if (machineDisplay) {
                machineDisplay.innerHTML = 'Wybierz maszynę ▼';
                machineDisplay.style.background = '#e7f3ff';
                machineDisplay.style.color = '#667eea';
            }

            const machineInput = document.getElementById('machineInput');
            if (machineInput) {
                machineInput.value = '';
            }
        }

        // Dropdown maszyn
        function toggleMachineDropdown() {
            const menu = document.getElementById('machineDropdown');
            if (!menu) return;

            if (!selectedOwner) {
                alert('Najpierw wybierz właściciela maszyny.');
                return;
            }

            if (menu.classList.contains('show')) {
                menu.classList.remove('show');
                menu.style.maxHeight = '';
                return;
            }

            // Oblicz dostępną wysokość odnoście kontenera
            const container = document.querySelector('.container');
            const display = document.getElementById('machineDisplay');
            
            if (container && display) {
                const containerRect = container.getBoundingClientRect();
                const displayRect = display.getBoundingClientRect();
                
                // Dostępne miejsce od display do góry kontenera (minus margines)
                const availableSpace = displayRect.top - containerRect.top - 20;
                const minHeight = 150;
                const maxHeight = Math.max(minHeight, Math.min(availableSpace, 400));
                
                menu.style.maxHeight = maxHeight + 'px';
            }

            menu.classList.add('show');
        }

        function selectMachineFromDropdown(label, machineOwner) {
            const machineInput = document.getElementById('machineInput');
            const machineDisplay = document.getElementById('machineDisplay');
            const menu = document.getElementById('machineDropdown');

            if (machineInput) {
                machineInput.value = label;
            }

            if (machineDisplay) {
                machineDisplay.innerHTML = label + ' ▼';
                machineDisplay.style.background = 'linear-gradient(135deg, #667eea 0%, #764ba2 100%)';
                machineDisplay.style.color = 'white';
            }

            if (menu) {
                menu.classList.remove('show');
                menu.style.maxHeight = '';
            }

            // Automatycznie ustaw właściciela tej maszyny (jeśli była lista WSZYSTKIE)
            if (machineOwner && selectedOwner === 'ALL') {
                const ownerSelect = document.getElementById('owner');
                const ownerDisplay = document.getElementById('ownerDisplay');
                
                // Zaktualizuj selectedOwner na konkretną firmę
                selectedOwner = machineOwner;
                
                if (ownerSelect) {
                    ownerSelect.value = machineOwner;
                }
                
                if (ownerDisplay) {
                    ownerDisplay.innerHTML = machineOwner + ' (✓ auto) ▼';
                    ownerDisplay.style.background = 'linear-gradient(135deg, #28a745 0%, #20c997 100%)';
                    ownerDisplay.style.color = 'white';
                }
            }
        }

        // Zamknięcie dropdownów po kliknięciu poza
        document.addEventListener('click', function(e) {
            if (!e.target.closest('.site-dropdown')) {
                const ownerMenu = document.getElementById('ownerDropdown');
                const machineMenu = document.getElementById('machineDropdown');
                if (ownerMenu) {
                    ownerMenu.classList.remove('show');
                    ownerMenu.style.maxHeight = '';
                }
                if (machineMenu) {
                    machineMenu.classList.remove('show');
                    machineMenu.style.maxHeight = '';
                }
            }
        });

        // Inicjalizacja PIN
        function initPin(containerId, hiddenId) {
            const box = document.getElementById(containerId);
            for (let i = 0; i < 4; i++) {
                const input = document.createElement("input");
                input.type = "password";
                input.maxLength = 1;
                input.inputMode = "numeric";
                input.className = "pin-input";
                input.oninput = () => {
                    input.value = input.value.replace(/\D/g, '');
                    document.getElementById(hiddenId).value =
                        [...box.querySelectorAll("input")].map(x => x.value).join('');
                    if (input.value && input.nextSibling) input.nextSibling.focus();
                };
                input.onkeydown = (e) => {
                    if (e.key === 'Backspace' && !input.value && input.previousSibling) {
                        input.previousSibling.focus();
                    }
                };
                box.appendChild(input);
            }
        }

        initPin("pinManager", "managerPin");

        // Ładowanie maszyn
        const owner = document.getElementById("owner");
        const machineInput = document.getElementById("machineInput");
        const machinesList = document.getElementById("machinesList");

        owner.addEventListener("change", loadMachines);

        function loadMachines() {
            if (!owner) return;

            machineInput.value = "";
            if (machinesList) {
                machinesList.innerHTML = "";
            }
            Object.keys(machinesMap).forEach(k => delete machinesMap[k]);

            const machineDropdown = document.getElementById('machineDropdown');
            if (machineDropdown) {
                machineDropdown.innerHTML = '';
            }

            if (!owner.value) return;

            const requestOwner = owner.value === 'ALL' ? '' : owner.value;

            fetch("fuel_api.php", {
                method: "POST",
                headers: { "Content-Type": "application/json" },
                body: JSON.stringify({ action: "get_machines", owner: requestOwner, get_all: owner.value === 'ALL' })
            })
            .then(r => r.json())
            .then(data => {
                if (!Array.isArray(data)) return;

                data.forEach(m => {
                    const label = `${m.registry_number} - ${m.machine_name}`;

                    // dla starej logiki (mapowanie label -> id)
                    if (machinesList) {
                        const o = document.createElement("option");
                        o.value = label;
                        machinesList.appendChild(o);
                    }
                    machinesMap[label] = m.id;

                    // nowy dropdown z maszynami
                    if (machineDropdown) {
                        const div = document.createElement('div');
                        div.className = 'dropdown-item';
                        
                        // Jeśli pokazujemy WSZYSTKIE maszyny, dodaj oznaczenie właściciela
                        if (selectedOwner === 'ALL') {
                            div.textContent = `${label} (${m.owner})`;
                        } else {
                            div.textContent = label;
                        }
                        
                        div.onclick = () => selectMachineFromDropdown(label, m.owner);
                        machineDropdown.appendChild(div);
                    }

                    if (preselectMachineId && m.id == preselectMachineId) {
                        machineInput.value = label;
                        const machineDisplay = document.getElementById('machineDisplay');
                        if (machineDisplay) {
                            machineDisplay.innerHTML = label + ' ▼';
                            machineDisplay.style.background = 'linear-gradient(135deg, #667eea 0%, #764ba2 100%)';
                            machineDisplay.style.color = 'white';
                        }
                    }
                });
            })
            .catch(err => {
                console.error('Błąd ładowania maszyn:', err);
            });
        }

        function setMode(mode) {
            fuelMode = mode === 'internal' ? 'internal' : 'external';

            const btnExt = document.getElementById('modeExternal');
            const btnInt = document.getElementById('modeInternal');
            const pinHint = document.getElementById('pinHint');

            if (fuelMode === 'external') {
                btnExt.classList.add('active');
                btnInt.classList.remove('active');
                if (pinHint) {
                    pinHint.innerHTML = 'Tryb ZEWNĘTRZNY: wymagany PIN operatora ';
                }
            } else {
                btnInt.classList.add('active');
                btnExt.classList.remove('active');
                if (pinHint) {
                    pinHint.innerHTML = 'Tryb WEWNĘTRZNY: wymagany PIN Tankującego';
                }
            }
        }

        // Zapis tankowania
        async function saveFuel() {
            // Zawsze używaj wyboru użytkownika z dropdowna
            const machineLabel = machineInput.value.trim();
            let machineId = machinesMap[machineLabel] || null;
            
            const liters = document.getElementById('liters').value;
            const mh = document.getElementById('mh').value;
            const managerPinValue = document.getElementById('managerPin').value;

            // Firma musi być wybrana z dropdowna
            let ownerValue = owner.value;

            // Walidacja
            if (!machineId) {
                showLog('❌ Wybierz maszynę z listy', 'error');
                machineInput.value = '';
                return;
            }

            if (!liters || isNaN(liters) || parseFloat(liters) <= 0) {
                showLog('❌ Podaj poprawną ilość litrów (tylko liczby)', 'error');
                return;
            }

            if (!mh || isNaN(mh) || parseFloat(mh) <= 0) {
                showLog('❌ Podaj poprawny stan licznika m-h (tylko liczby)', 'error');
                return;
            }

            if (!managerPinValue || managerPinValue.length !== 4 || !/^\d{4}$/.test(managerPinValue)) {
                showLog('❌ Wprowadź 4-cyfrowy PIN', 'error');
                return;
            }

            // Wyłącz przycisk podczas zapisu
            const saveBtn = document.getElementById('saveBtn');
            saveBtn.disabled = true;
            saveBtn.textContent = '⏳ Zapisywanie...';

            try {
                const res = await fetch("fuel_api.php", {
                    method: "POST",
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        action: "save_fuel",
                        mode: fuelMode,
                        owner: ownerValue,
                        machine_id: machineId,
                        liters: liters,
                        meter_mh: mh,
                        manager_pin: managerPinValue
                    })
                });

                const data = await res.json();

                if (data.status === 'ok') {
                    showLog(data.message, 'success');
                    
                    // Wyczyść formularz
                    document.getElementById('liters').value = '';
                    document.getElementById('mh').value = '';
                    document.querySelectorAll('.pin-input').forEach(inp => inp.value = '');
                    document.getElementById('managerPin').value = '';
                } else {
                    showLog(data.message || '❌ Błąd zapisu', 'error');
                }
            } catch (err) {
                console.error('Błąd:', err);
                showLog('❌ Błąd połączenia z serwerem', 'error');
            } finally {
                saveBtn.disabled = false;
                saveBtn.textContent = '💾 ZAPISZ TANKOWANIE';
            }
        }

        function showLog(message, type) {
            const log = document.getElementById('log');
            log.textContent = message;
            log.className = 'show ' + type;
            
            setTimeout(() => {
                log.classList.remove('show');
            }, 5000);
        }

        function goBack() {
            window.location.href = '../../panel.php';
        }

        // Inicjalizacja
        checkAuth();
    </script>
</body>
</html>
