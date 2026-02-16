<?php
require_once __DIR__ . '/../core/auth.php';

// Panel dostępny dla wszystkich menedżerów (rola 1+)
$managerInfo = requireManagerPage(1);

// Kompatybilność z nowym formatem sesji
$managerName = is_array($_SESSION['manager']) 
    ? $_SESSION['manager']['first_name'] . ' ' . $_SESSION['manager']['last_name']
    : ($_SESSION['manager_name'] ?? $_SESSION['manager'] ?? 'Kierownik');
?>
<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel Kierownika - RCP System</title>

    <!-- Leaflet (mapa geolokalizacji) -->
    <link
        rel="stylesheet"
        href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
        integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY="
        crossorigin=""
    >
    <script
        src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"
        integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo="
        crossorigin=""
    ></script>
    <script>
        window.USER_ROLE = <?= (int)$_SESSION['role_level'] ?>;
    </script>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

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
            max-width: 1200px;
            margin: 0 auto;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 40px;
            padding-bottom: 20px;
            border-bottom: 2px solid rgba(102, 126, 234, 0.2);
        }

        .header h1 {
            color: #333;
            font-size: 32px;
        }

        .header .user-name {
            font-weight: bold;
            color: #667eea;
        }

        .absence-alert-header {
            margin-top: 8px;
            display: none;
            cursor: pointer;
        }

        .push-banner {
            margin-top: 10px;
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

        .absence-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: linear-gradient(135deg, #ffc107 0%, #ff5722 100%);
            color: #000;
            padding: 4px 14px;
            border-radius: 999px;
            font-weight: 700;
            font-size: 14px;
            box-shadow: 0 4px 10px rgba(0,0,0,0.2);
            animation: pulse-badge 1.5s infinite;
        }

        @keyframes pulse-badge {
            0% { transform: scale(1); box-shadow: 0 0 0 0 rgba(255, 193, 7, 0.7); }
            70% { transform: scale(1.05); box-shadow: 0 0 0 12px rgba(255, 193, 7, 0); }
            100% { transform: scale(1); box-shadow: 0 0 0 0 rgba(255, 193, 7, 0); }
        }

        .logout-btn {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            color: white;
            border: none;
            border-radius: 10px;
            padding: 12px 24px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
        }

        .logout-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(245, 87, 108, 0.4);
        }

        /* Sekcje */
        .dash-section {
            margin-bottom: 40px;
            padding: 25px;
            background: rgba(255, 255, 255, 0.5);
            border-radius: 15px;
            border: 1px solid rgba(102, 126, 234, 0.1);
        }
        
        .dash-section.clickable {
            cursor: pointer;
            transition: box-shadow 0.25s ease, transform 0.25s ease, background 0.25s ease;
        }
        
        .dash-section.clickable:hover {
            background: rgba(255, 255, 255, 0.9);
            box-shadow: 0 6px 20px rgba(0,0,0,0.12);
            transform: translateY(-2px);
        }

        .dash-section h3 {
            color: #333;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid rgba(102, 126, 234, 0.2);
            font-size: 20px;
        }

        /* Formularz */
        .form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 12px;
            margin-top: 15px;
        }

        input, select, textarea {
            padding: 12px;
            border: 2px solid #ddd;
            border-radius: 8px;
            font-size: 15px;
            font-family: inherit;
            transition: all 0.3s ease;
            background: white;
        }

        input:focus, select:focus, textarea:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        button {
            padding: 12px 20px;
            border: none;
            border-radius: 8px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            color: white;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }

        button:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }

        button.btn-success {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
        }

        button.btn-secondary {
            background: linear-gradient(135deg, #a8a8a8 0%, #6c757d 100%);
        }

        .result {
            margin-top: 15px;
            padding: 15px;
            border-radius: 8px;
            background: rgba(255, 255, 255, 0.8);
            display: none;
        }

        .result.show {
            display: block;
        }

        hr {
            margin: 30px 0;
            border: none;
            height: 1px;
            background: rgba(0,0,0,0.1);
        }

        /* Szybkie linki */
        .quick-links {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 30px;
        }

        .quick-link {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 12px;
            text-decoration: none;
            text-align: center;
            font-weight: 600;
            font-size: 16px;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }

        .quick-link:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.4);
        }

        .quick-link.secondary {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
        }

        .quick-link.success {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
        }

        @media (max-width: 768px) {
            .container {
                padding: 20px;
            }
            
            .header {
                flex-direction: column;
                gap: 15px;
                text-align: center;
            }

            .header h1 {
                font-size: 24px;
            }
        }

        /* Absence Requests Styles */
        .request-card {
            background: white;
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 15px;
            border-left: 4px solid #ffc107;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        .request-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 12px;
        }
        .request-employee {
            font-size: 18px;
            font-weight: 600;
            color: #333;
        }
        .request-dates {
            color: #667eea;
            font-weight: 600;
        }
        .request-info {
            color: #666;
            margin-bottom: 15px;
            line-height: 1.6;
        }
        .request-actions {
            display: flex;
            gap: 10px;
        }
        .btn-approve {
            background: #28a745;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s;
        }
        .btn-approve:hover {
            background: #218838;
            transform: translateY(-2px);
        }
        .btn-reject {
            background: #dc3545;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s;
        }
        .btn-reject:hover {
            background: #c82333;
            transform: translateY(-2px);
        }
        .empty-requests {
            text-align: center;
            padding: 40px;
            color: #999;
        }

        /* Work Sessions Approval Styles */
        .session-card {
            background: white;
            padding: 16px 18px;
            border-radius: 12px;
            margin-bottom: 12px;
            border-left: 4px solid #667eea;
            box-shadow: 0 2px 6px rgba(0,0,0,0.06);
        }
        .session-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 8px;
            gap: 8px;
        }
        .session-employee {
            font-weight: 600;
            color: #333;
        }
        .session-site {
            font-size: 13px;
            color: #555;
            background: #eef2ff;
            padding: 4px 10px;
            border-radius: 999px;
        }
        .session-meta {
            font-size: 13px;
            color: #666;
            margin-bottom: 8px;
        }
        .session-meta span {
            margin-right: 10px;
        }
        .session-comment {
            width: 100%;
            margin-top: 6px;
            margin-bottom: 8px;
            font-size: 13px;
            min-height: 40px;
            resize: vertical;
        }
        .session-actions {
            display: flex;
            gap: 8px;
        }
        .session-empty {
            text-align: center;
            padding: 24px;
            color: #999;
            font-size: 14px;
        }

        /* Geofencing – mapa START pracy */
        #geoMap {
            width: 100%;
            height: 400px;
            border-radius: 12px;
            margin-top: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            overflow: hidden;
        }

        #geoMapInfo {
            margin-top: 10px;
            font-size: 13px;
            color: #555;
        }

        /* Material Management Styles */
        .material-group-card {
            background: white;
            border: 2px solid #e0e0e0;
            border-radius: 12px;
            margin-bottom: 16px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }

        .material-group-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 14px 18px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }

        .material-group-header h4 {
            margin: 0;
            font-size: 16px;
            font-weight: 600;
        }

        .material-group-actions {
            display: flex;
            gap: 6px;
            flex-wrap: wrap;
        }

        .material-list {
            padding: 12px;
            background: #f8f9fa;
        }

        .material-item {
            background: white;
            padding: 10px 14px;
            margin-bottom: 8px;
            border-radius: 8px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border: 1px solid #e0e0e0;
            transition: all 0.2s;
        }

        .material-item:hover {
            border-color: #667eea;
            box-shadow: 0 2px 6px rgba(102, 126, 234, 0.15);
        }

        .material-item:last-child {
            margin-bottom: 0;
        }

        .material-name {
            font-size: 14px;
            color: #333;
            font-weight: 500;
        }

        .material-actions {
            display: flex;
            gap: 4px;
        }

        .btn-small {
            padding: 6px 12px;
            font-size: 13px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.2s;
        }

        .btn-tiny {
            padding: 4px 8px;
            font-size: 12px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.2s;
        }

        .btn-small.btn-primary,
        .btn-tiny.btn-primary {
            background: #667eea;
            color: white;
        }

        .btn-small.btn-primary:hover,
        .btn-tiny.btn-primary:hover {
            background: #5568d3;
        }

        .btn-small.btn-success,
        .btn-tiny.btn-success {
            background: #28a745;
            color: white;
        }

        .btn-small.btn-success:hover,
        .btn-tiny.btn-success:hover {
            background: #218838;
        }

        .btn-small.btn-danger,
        .btn-tiny.btn-danger {
            background: #dc3545;
            color: white;
        }

        .btn-small.btn-danger:hover,
        .btn-tiny.btn-danger:hover {
            background: #c82333;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div>
                <?php
                    $roleLevel = (int)($_SESSION['role_level'] ?? 0);
                    $roleNames = [
                        2 => 'Kierownik',
                        3 => 'Wawryniuk',
                        4 => 'Kadry',
                        5 => 'Waga Pawłów',
                        9 => 'Administrator',
                    ];
                    $roleLabel = $roleNames[$roleLevel] ?? ('Rola '.$roleLevel);
                ?>
                <h1>👔 Panel <?= htmlspecialchars($roleLabel) ?></h1>
                <div style="color: #666; margin-top: 5px;">
                    Witaj, <span class="user-name"><?= htmlspecialchars($managerName) ?></span>
                </div>
                <?php if ($roleLevel === 2 || $roleLevel === 9): ?>
                    <div id="absenceAlertHeader" class="absence-alert-header" data-roles="2,9">
                        <span class="absence-badge">
                            📬 Wnioski urlopowe: <span id="pendingCountHeader">0</span>
                        </span>
                    </div>
                <?php endif; ?>
                <?php if ($roleLevel === 2 || $roleLevel === 9): ?>
                    <div id="sessionsAlertHeader" class="absence-alert-header" style="margin-top:6px;" data-roles="2,9">
                        <span class="absence-badge">
                            ⏱ Sesje do akceptacji: <span id="pendingSessionsCountHeader">0</span>
                        </span>
                    </div>
                <?php endif; ?>
                <?php if ($roleLevel === 2 || $roleLevel === 9): ?>
                    <div id="wzAlertHeader" class="absence-alert-header" style="margin-top:6px;" data-roles="2,9">
                        <span class="absence-badge">
                            📄 WZ do akceptacji: <span id="pendingWzCountHeader">0</span>
                        </span>
                    </div>
                <?php endif; ?>
                <div id="pushBanner" class="push-banner">
                    🔔 Możesz włączyć powiadomienia (PUSH).<br>
                    <button id="pushButton" type="button">Włącz powiadomienia</button>
                </div>
            </div>
            <a href="logout.php" class="logout-btn">🚪 Wyloguj</a>
        </div>

        <!-- Szybkie linki (widoczność sterowana przez data-roles jak w sekcjach) -->
        <div class="quick-links">
            <a href="employees.php" class="quick-link" data-roles="2,4,9">
                👥 Pracownicy
            </a>
            <a href="sites.php" class="quick-link" data-roles="2,9">
                🏗️ Budowy
            </a>
            <a href="machines.php" class="quick-link" data-roles="2,9">
                🚜 Maszyny
            </a>
            <a href="../modules/wz/list_wz.php" class="quick-link" data-roles="2,9">
                📋 Archiwum WZ
            </a>
            <a href="absences.php" class="quick-link secondary" data-roles="2,4,9">
                🏖️ Nieobecności
            </a>
            <!-- Możesz dowolnie zmienić wartości data-roles powyżej -->
        </div>

        <?php if ($roleLevel === 9): ?>
        <section class="dash-section" data-roles="9">
            <h3>🗺️ Mapa START pracy (geofencing)</h3>
            <div class="form-row" style="margin-bottom:10px; align-items: center;">
                <div>
                    <label for="geoDate" style="font-size:14px;color:#555;display:block;margin-bottom:4px;">Data (domyślnie dzisiaj)</label>
                    <input type="date" id="geoDate">
                </div>
                <div>
                    <button type="button" onclick="reloadGeoMap()">🔄 Odśwież mapę</button>
                </div>
            </div>
            <div id="geoMap"></div>
            <div id="geoMapInfo">Punkty pokazują miejsca, w których pracownicy rozpoczęli pracę w wybranym dniu.</div>
        </section>
        <?php endif; ?>

        <section class="dash-section" data-roles="2,4,9">
            <h3>📊 Raport pracownika (MIESIĄC)</h3>
            <div class="form-row">
                <select id="employeeSelect"></select>
                <input type="month" id="monthPicker" placeholder="YYYY-MM">
                <button class="btn-success" onclick="exportPDF()">Eksport do PDF</button>
            </div>
            <div id="employeeResult" class="result"></div>
        </section>

        <!-- Sekcja Wnioski urlopowe (tylko dla role_level 4) -->
        <section class="dash-section clickable" data-roles="2,9" data-href="absence_requests.php" id="absence-requests-section">
            <h3>
                📬 Wnioski urlopowe 
                <span id="pendingCount" style="background: #ffc107; color: #000; padding: 4px 12px; border-radius: 20px; font-size: 14px; margin-left: 10px;">0</span>
            </h3>
            <div style="color:#666; margin-top:6px; font-size:14px;">
                Przejdź do listy wszystkich wniosków urlopowych oczekujących na akceptację.
            </div>
        </section>

        <!-- Szybki link: Sesje do akceptacji (kierownik budowy + admin) -->
        <?php if ($roleLevel === 2 || $roleLevel === 9): ?>
        <section class="dash-section clickable" data-roles="2,9" data-href="pending_sessions.php" id="pending-sessions-section">
            <h3>
                ⏱ Sesje do akceptacji
                <span id="pendingSessionsCount" style="background: #ffc107; color: #000; padding: 4px 12px; border-radius: 20px; font-size: 14px; margin-left: 10px;">0</span>
            </h3>
            <div style="color:#666; margin-top:6px; font-size:14px;">
                Przejdź do listy wszystkich sesji oczekujących na akceptację.
            </div>
        </section>
        <?php endif; ?>

        <?php if ($roleLevel === 4 || $roleLevel === 9): ?>
        <section class="dash-section" data-roles="4,9">
            <h3>🏖️ Urlopy / Zwolnienia (L4)</h3>
            <div class="form-row">
                <select id="absenceEmployee"></select>
                <div id="absenceVacationInfo" style="font-size:14px;color:#555;align-self:center;">
                    Dostępne dni urlopu: <strong id="absenceVacationDays">-</strong>
                </div>
                <select id="absenceType">
                    <option value="URLOP">Urlop</option>
                    <option value="L4">Zwolnienie (L4)</option>
                </select>
                <input type="date" id="absenceFrom">
                <input type="date" id="absenceTo">
                <button onclick="addAbsence()">Dodaj</button>
                <button class="btn-secondary" onclick="goToAbsences()">📋 Lista / Edycja</button>
            </div>
            <div id="absenceMsg" class="result"></div>
        </section>
        <?php endif; ?>

        <?php if (in_array($roleLevel, [2,3,9], true)): ?>
        <section class="dash-section" data-roles="2,3,9">
            <h3>🏗️ Raport budowy</h3>
            <div class="form-row">
                <select id="siteReport"></select>
                <input type="month" id="siteMonthPicker">
                <button class="btn-success" onclick="exportSitePDF()">Eksport PDF</button>
            </div>
        </section>
        <?php endif; ?>

        <?php if ($roleLevel === 9): ?>
        <section class="dash-section" data-roles="9">
            <h3>🏗️ Raport budowy – Kierownik</h3>
            <div class="form-row">
                <select id="siteReportk"></select>
                <input type="month" id="siteMonthPickerk">
                <button class="btn-success" onclick="exportSitePDF('k')">Eksport PDF</button>
            </div>
        </section>

        <?php endif; ?>

        <?php if ($roleLevel === 4 || $roleLevel === 9): ?>
        <section class="dash-section" data-roles="4,9">
            <h3>📄 Raport wszystkich pracowników</h3>
            <div class="form-row">
                <input type="month" id="allMonthPicker" placeholder="YYYY-MM">
                <button class="btn-success" onclick="exportAllEmployees()">Eksport PDF</button>
            </div>
        </section>
        <?php endif; ?>

        <?php if ($roleLevel === 9): ?>
        <section class="dash-section" data-roles="9">
            <h3>🏗️ Zarządzanie budowami</h3>
            <div class="form-row">
                <input id="newSite" placeholder="Numer - Nazwa budowy">
                <button onclick="addSite()">Dodaj budowę</button>
            </div>
            <div id="siteResult" class="result"></div>
        </section>
        <?php endif; ?>

        <section class="dash-section" data-roles="4,9">
            <h3>👷 Zarządzanie pracownikami</h3>
            <div class="form-row">
                <input id="empFirstName" placeholder="Imię">
                <input id="empLastName" placeholder="Nazwisko">
                <input id="empHourRate" type="number" step="0.01" placeholder="Stawka zł/h (np. 30.00)">
                <input id="empVacationDays" type="number" min="0" step="1" placeholder="Dni urlopu (np. 20)">
                <select id="empIsOperator">
                    <option value="0">Pracownik fizyczny</option>
                    <option value="1">Operator</option>
                    <option value="2">Kierowca</option>
                    <option value="3">Ładowarka</option>
                </select>
                <button onclick="addEmployee()">Dodaj pracownika</button>
            </div>
            <div id="empResult" class="result"></div>
        </section>

        <?php if ($roleLevel === 9): ?>
        <section class="dash-section" data-roles="9">
            <h3>🧱 Zarządzanie grupami i rodzajami materiałów</h3>
            <div style="margin-bottom: 15px;">
                <button onclick="loadMaterialGroups()" class="btn-primary">🔄 Odśwież dane</button>
                <button onclick="showAddGroupDialog()" class="btn-success">➕ Dodaj grupę materiałów</button>
            </div>
            <div id="materialGroupsContainer" style="margin-top: 15px;">
                <div style="text-align: center; color: #999; padding: 20px;">
                    Kliknij "Odśwież dane" aby załadować grupy materiałów
                </div>
            </div>
            <div id="materialResult" class="result"></div>
        </section>
        <?php endif; ?>

        <section class="dash-section" data-roles="2,9">
            <h3>📝 Ręczne dodanie sesji pracy</h3>
            <div class="form-row">
                <select id="manualEmployee" onchange="onManualEmployeeChange()">
                    <option value="">— Pracownik —</option>
                </select>
                <select id="manualSite">
                    <option value="">— Budowa —</option>
                </select>
                <select id="manualMachine" style="display:none">
                    <option value="">— Wybierz maszynę —</option>
                </select>
                <input type="date" id="manualDate">
                <button onclick="addManualSession()">Dodaj ręcznie sesję</button>
                <textarea id="manualComment" placeholder="Powód ręcznego dodania sesji" rows="2" style="grid-column:1/-1;resize:vertical"></textarea>
            </div>
            <div class="result" id="manualSessionMsg"></div>
        </section>

        <?php if ($roleLevel === 9): ?>
        <section class="dash-section" data-roles="9">
            <h3>⛽ Raport zużycia paliwa maszyny</h3>
            <div class="form-row">
                <select id="fuelMachineSelect"></select>
                <input type="month" id="fuelMonth">
                <button class="btn-success" onclick="exportFuelMachinePDF()">Eksport PDF</button>
            </div>
        </section>
        <?php endif; ?>

        <?php if (in_array($roleLevel, [2, 9], true)): ?>
        <section class="dash-section" data-roles="2,9">
            <h3>⛽ Ręczne tankowanie maszyny</h3>
            <div class="form-row">
                <select id="mfMachine">
                    <option value="">— Wybierz maszynę —</option>
                </select>
                <input type="number" id="mfLiters" step="0.01" placeholder="Litry zatankowane">
                <input type="number" id="mfMeterMh" step="0.01" placeholder="Stan licznika m-h / przebieg">
                <button class="btn-success" type="button" onclick="addManualFuel()">Zapisz tankowanie</button>
            </div>
            <div id="mfResult" class="result"></div>
        </section>
        <?php endif; ?>

        <?php if ($roleLevel === 9): ?>
        <section class="dash-section" data-roles="9">
            <h3>🚜 Raport pracy maszyny (MIESIĄC)</h3>
            <div class="form-row">
                <select id="machineSelect"></select>
                <input type="month" id="machineMonth">
                <button class="btn-success" onclick="exportMachinePDF()">Eksport PDF</button>
            </div>
        </section>

        <section class="dash-section" data-roles="9">
            <h3>🚜 Dodaj NOWĄ Maszynę</h3>

            <div class="form-row">
                <input id="mName" placeholder="Nazwa maszyny">

                <input id="mShort" placeholder="Nazwa skrócona (opcjonalnie)">

                <select id="mOwner">
                    <option value="">— właściciel —</option>
                    <option value="PREFBET">PREF-BET</option>
                    <option value="BG">BG Construction</option>
                    <option value="PUH">PUH</option>
                    <option value="MARBUD">MAR-BUD</option>
                    <option value="DRWAL">DRWAL</option>
                    <option value="MERITUM">MERITUM</option>
                    <option value="ZB">ZB</option>
                </select>

                <input id="mRenter" placeholder="Wynajmujący (opcjonalnie)">

                <input id="mHourRate" type="number" step="0.01" placeholder="Stawka [zł/h] (opcjonalnie)">

                <input id="mWorkshopTag" placeholder="Tag warsztatowy (opcjonalnie)">

                <input id="mFuelNorm" type="number" step="0.01" placeholder="Norma spalania [l/m-h] (opcjonalnie)">

                <input id="mRegistry" placeholder="Numer">

                <button onclick="addMachine()">Dodaj</button>

                <button onclick="goToMachines()" style="background:#6c757d">
                    Lista maszyn
                </button>
            </div>
            <div id="machineMsg" class="result"></div>

            <hr>
        </section>

        <section class="dash-section" data-roles="9" data-href="../admin/">
            <h3>🔐 Panel Administratora</h3>
            <div class="form-row">
                <button class="btn-secondary" onclick="go('../admin/')">🔐 Zarządzanie kierownikami</button>
            </div>
        </section>
        <?php endif; ?>

        <?php if (in_array($roleLevel, [2, 5, 9], true)): ?>
        <section class="dash-section" data-roles="2,5,9" data-href="../modules/wz/list_wz.php">
            <h3>📄 Moduł WZ</h3>
            <div class="form-row">
                <button class="btn-success" onclick="go('../modules/wz/scan.php')">📷 Nowy dokument WZ</button>
                <button class="btn-primary" onclick="go('../modules/wz/list_wz.php')">📂 Archiwum WZ</button>
            </div>
        </section>
        <?php endif; ?>
    </div>

    <script>
        const USER_ROLE = <?= (int)$_SESSION['role_level'] ?>;
        const MANAGER_ID = <?= isset($managerInfo['id']) ? (int)$managerInfo['id'] : 0 ?>;
        const BASE_TITLE = document.title;
        const VAPID_PUBLIC_KEY = 'BDttZpGMOcEb1OZNSg_eGC5FkMmPXMAl1pvlksZj923I2qWESy47AHVtHMRHCPJbxpVx9TnMQohVxAMsk1U5rhs';
        let pushInitialized = false;

        // Ukryj elementy (sekcje i szybkie linki) według roli
        document.querySelectorAll('[data-roles]').forEach(el => {
            const roles = el.dataset.roles
                ? el.dataset.roles.split(',').map(r => parseInt(r.trim(), 10)).filter(n => !Number.isNaN(n))
                : [];
            if (roles.length && !roles.includes(USER_ROLE)) {
                el.style.display = 'none';
            }
        });

        function go(url) {
            window.location.href = url;
        }

        function goToAbsences() {
            window.location.href = 'absences.php';
        }
        
        // Klikalne badge w nagłówku (skróty do list)
        const absenceHeader = document.getElementById('absenceAlertHeader');
        if (absenceHeader) {
            absenceHeader.addEventListener('click', () => {
                window.location.href = 'absence_requests.php';
            });
        }

        const sessionsHeader = document.getElementById('sessionsAlertHeader');
        if (sessionsHeader) {
            sessionsHeader.addEventListener('click', () => {
                window.location.href = 'pending_sessions.php';
            });
        }

        const wzHeader = document.getElementById('wzAlertHeader');
        if (wzHeader) {
            wzHeader.addEventListener('click', () => {
                window.location.href = '../modules/wz/list_wz.php';
            });
        }
        
        // Klikalny cały kafelek sekcji (jeśli ma data-href)
        document.querySelectorAll('.dash-section[data-href]').forEach(sec => {
            const url = sec.dataset.href;
            if (!url) return;

            sec.classList.add('clickable');

            sec.addEventListener('click', (e) => {
                // Nie przechwytuj kliknięć w przyciski, linki i pola formularzy
                if (e.target.closest('button, a, input, select, textarea')) {
                    return;
                }
                window.location.href = url;
            });
        });

        // Po załadowaniu strony odśwież licznik sesji do akceptacji
        if (typeof updatePendingSessionsHeader === 'function') {
            updatePendingSessionsHeader();
            // Częstsze odświeżanie: co 15 sekund
            setInterval(updatePendingSessionsHeader, 15000);
        }

        // Inicjalizacja banera PUSH dla kierownika
        if ((USER_ROLE === 2 || USER_ROLE === 9) && MANAGER_ID > 0) {
            setupManagerPushBanner(MANAGER_ID);
        }

        // ============ Absence Requests Management ============
        
        // Load pending absence requests counter (nagłówek + kafelek)
        async function loadAbsenceRequests() {
            // Tylko role 2 (kierownik), 4 (kadry) i 9 (admin)
            if (USER_ROLE !== 2 && USER_ROLE !== 4 && USER_ROLE !== 9) return;

            try {
                const response = await fetch('../absence_requests_api.php?action=count_pending');
                const result = await response.json();

                const countBadge = document.getElementById('pendingCount');
                const headerAlert = document.getElementById('absenceAlertHeader');
                const headerCount = document.getElementById('pendingCountHeader');

                const pendingCount = (result.success && typeof result.count === 'number') ? result.count : 0;

                if (countBadge) {
                    countBadge.textContent = pendingCount;
                }

                if (pendingCount > 0) {
                    if (headerAlert && headerCount) {
                        headerCount.textContent = pendingCount;
                        headerAlert.style.display = 'block';
                    }
                    document.title = `(${pendingCount}) ${BASE_TITLE}`;
                } else {
                    if (headerAlert && headerCount) {
                        headerAlert.style.display = 'none';
                    }
                    document.title = BASE_TITLE;
                }
            } catch (error) {
                console.error('Error loading absence requests:', error);
            }
        }

        // Approve absence request
        async function approveRequest(requestId) {
            const notes = prompt('Notatka dla pracownika (opcjonalnie):');
            if (notes === null) return; // Cancelled

            try {
                const response = await fetch('../absence_requests_api.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        action: 'approve',
                        request_id: requestId,
                        notes: notes
                    })
                });

                const result = await response.json();

                if (result.success) {
                    alert('✅ Wniosek zaakceptowany! Nieobecności zostały dodane do kalendarza.');
                    loadAbsenceRequests();
                } else {
                    alert('❌ ' + (result.message || 'Błąd podczas akceptacji'));
                }
            } catch (error) {
                alert('❌ Błąd połączenia');
                console.error(error);
            }
        }

        // Reject absence request
        async function rejectRequest(requestId) {
            const notes = prompt('Powód odrzucenia (wymagane):');
            if (!notes || notes.trim() === '') {
                alert('Musisz podać powód odrzucenia wniosku');
                return;
            }

            if (!confirm('Czy na pewno chcesz odrzucić ten wniosek?')) {
                return;
            }

            try {
                const response = await fetch('../absence_requests_api.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        action: 'reject',
                        request_id: requestId,
                        notes: notes
                    })
                });

                const result = await response.json();

                if (result.success) {
                    alert('Wniosek odrzucony');
                    loadAbsenceRequests();
                } else {
                    alert('❌ ' + (result.message || 'Błąd podczas odrzucania'));
                }
            } catch (error) {
                alert('❌ Błąd połączenia');
                console.error(error);
            }
        }

        // Helper functions
        function formatDate(dateStr) {
            const d = new Date(dateStr);
            return d.toLocaleDateString('pl-PL');
        }

        // ============ Work Sessions Pending Counter (header badge) ============

        async function updatePendingSessionsHeader() {
            // Tylko role 2 (kierownik) i 9 (admin)
            if (USER_ROLE !== 2 && USER_ROLE !== 9) return;

            try {
                const response = await fetch('get_pending_sessions.php');
                const data = await response.json();

                const headerAlert = document.getElementById('sessionsAlertHeader');
                const headerCount = document.getElementById('pendingSessionsCountHeader');
                const tileCount = document.getElementById('pendingSessionsCount');

                if (!headerAlert || !headerCount || !tileCount) return;

                if (data.success && Array.isArray(data.sessions) && data.sessions.length > 0) {
                    headerCount.textContent = data.sessions.length;
                    tileCount.textContent = data.sessions.length;
                    headerAlert.style.display = 'block';
                } else {
                    headerCount.textContent = '0';
                    tileCount.textContent = '0';
                    headerAlert.style.display = 'none';
                }
            } catch (e) {
                console.error('Błąd odświeżania licznika sesji do akceptacji:', e);
            }
        }

        // ============ WZ Pending Counter (header badge) ============

        async function updatePendingWzHeader() {
            // Tylko role 2 (kierownik) i 9 (admin)
            if (USER_ROLE !== 2 && USER_ROLE !== 9) return;

            try {
                const response = await fetch('get_pending_wz.php');
                const data = await response.json();

                const headerAlert = document.getElementById('wzAlertHeader');
                const headerCount = document.getElementById('pendingWzCountHeader');

                if (!headerAlert || !headerCount) return;

                const count = (data.success && typeof data.count === 'number') ? data.count : 0;

                if (count > 0) {
                    headerCount.textContent = count;
                    headerAlert.style.display = 'block';
                } else {
                    headerCount.textContent = '0';
                    headerAlert.style.display = 'none';
                }
            } catch (e) {
                console.error('Błąd odświeżania licznika WZ do akceptacji:', e);
            }
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

        function getTypeIcon(type) {
            const icons = {
                'urlop': '🏖️',
                'L4': '🏥',
                'inny': '📄'
            };
            return icons[type] || '📄';
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

        async function enableManagerPush(managerId) {
            if (!('Notification' in window) || !('serviceWorker' in navigator) || !('PushManager' in window)) {
                return;
            }

            if (!VAPID_PUBLIC_KEY) {
                return;
            }

            const permission = await Notification.requestPermission();
            if (permission !== 'granted') return;

            const reg = await navigator.serviceWorker.register('../service-worker.js');

            const sub = await reg.pushManager.subscribe({
                userVisibleOnly: true,
                applicationServerKey: urlBase64ToUint8Array(VAPID_PUBLIC_KEY)
            });

            await fetch('../api.php', {
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

        // Load absence requests counter on page load
        if (typeof loadAbsenceRequests === 'function') {
            loadAbsenceRequests();
            
            // Częstsze odświeżanie liczników wniosków urlopowych: co 15 sekund
            setInterval(loadAbsenceRequests, 15000);
        }

        // Load WZ pending counter on page load
        if (typeof updatePendingWzHeader === 'function') {
            updatePendingWzHeader();
            setInterval(updatePendingWzHeader, 15000);
        }

        // ==================== ZARZĄDZANIE MATERIAŁAMI ====================
        
        async function loadMaterialGroups() {
            const container = document.getElementById('materialGroupsContainer');
            const result = document.getElementById('materialResult');
            
            try {
                result.textContent = '⏳ Ładowanie...';
                result.className = 'result';
                result.style.display = 'block';
                
                const response = await fetch('material_api.php?action=get_all');
                const data = await response.json();
                
                if (!data.success) {
                    throw new Error(data.message);
                }
                
                if (!data.groups || data.groups.length === 0) {
                    container.innerHTML = '<div style="text-align: center; color: #999; padding: 20px;">Brak grup materiałów</div>';
                    result.style.display = 'none';
                    return;
                }
                
                let html = '';
                data.groups.forEach(group => {
                    html += `
                        <div class="material-group-card" data-group-id="${group.id}">
                            <div class="material-group-header">
                                <h4>📦 ${escapeHtml(group.name)}</h4>
                                <div class="material-group-actions">
                                    <button class="btn-small btn-primary" onclick="showEditGroupDialog(${group.id}, '${escapeHtml(group.name)}')">✏️ Edytuj</button>
                                    <button class="btn-small btn-success" onclick="showAddMaterialDialog(${group.id}, '${escapeHtml(group.name)}')">➕ Dodaj materiał</button>
                                    <button class="btn-small btn-danger" onclick="deleteGroup(${group.id}, '${escapeHtml(group.name)}')">🗑️ Usuń grupę</button>
                                </div>
                            </div>
                            <div class="material-list">
                    `;
                    
                    if (group.materials && group.materials.length > 0) {
                        group.materials.forEach(material => {
                            html += `
                                <div class="material-item" data-material-id="${material.id}">
                                    <span class="material-name">${escapeHtml(material.name)}</span>
                                    <div class="material-actions">
                                        <button class="btn-tiny btn-primary" onclick="showEditMaterialDialog(${material.id}, ${group.id}, '${escapeHtml(material.name)}')">✏️</button>
                                        <button class="btn-tiny btn-danger" onclick="deleteMaterial(${material.id}, '${escapeHtml(material.name)}')">🗑️</button>
                                    </div>
                                </div>
                            `;
                        });
                    } else {
                        html += '<div style="color: #999; padding: 10px; font-size: 13px;">Brak materiałów w tej grupie</div>';
                    }
                    
                    html += `
                            </div>
                        </div>
                    `;
                });
                
                container.innerHTML = html;
                result.textContent = '✓ Dane załadowane';
                result.className = 'result success';
                setTimeout(() => result.style.display = 'none', 2000);
                
            } catch (error) {
                console.error('Błąd:', error);
                result.textContent = '✗ ' + error.message;
                result.className = 'result error';
            }
        }
        
        function showAddGroupDialog() {
            const name = prompt('Podaj nazwę nowej grupy materiałów (np. "KRUSZYWA"):');
            if (!name || !name.trim()) return;
            addGroup(name.trim());
        }
        
        async function addGroup(name) {
            const result = document.getElementById('materialResult');
            try {
                result.textContent = '⏳ Dodawanie grupy...';
                result.className = 'result';
                result.style.display = 'block';
                
                const formData = new FormData();
                formData.append('action', 'add_group');
                formData.append('name', name);
                
                const response = await fetch('material_api.php', {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                
                if (!data.success) {
                    throw new Error(data.message);
                }
                
                result.textContent = '✓ ' + data.message;
                result.className = 'result success';
                
                // Przeładuj listę
                setTimeout(() => loadMaterialGroups(), 1000);
                
            } catch (error) {
                console.error('Błąd:', error);
                result.textContent = '✗ ' + error.message;
                result.className = 'result error';
            }
        }
        
        function showEditGroupDialog(groupId, currentName) {
            const name = prompt('Podaj nową nazwę grupy:', currentName);
            if (!name || !name.trim() || name.trim() === currentName) return;
            updateGroup(groupId, name.trim());
        }
        
        async function updateGroup(groupId, name) {
            const result = document.getElementById('materialResult');
            try {
                result.textContent = '⏳ Aktualizowanie grupy...';
                result.className = 'result';
                result.style.display = 'block';
                
                const formData = new FormData();
                formData.append('action', 'update_group');
                formData.append('id', groupId);
                formData.append('name', name);
                
                const response = await fetch('material_api.php', {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                
                if (!data.success) {
                    throw new Error(data.message);
                }
                
                result.textContent = '✓ ' + data.message;
                result.className = 'result success';
                
                setTimeout(() => loadMaterialGroups(), 1000);
                
            } catch (error) {
                console.error('Błąd:', error);
                result.textContent = '✗ ' + error.message;
                result.className = 'result error';
            }
        }
        
        async function deleteGroup(groupId, groupName) {
            if (!confirm(`Czy na pewno usunąć grupę "${groupName}"?\n\nUWAGA: Grupa musi być pusta (bez materiałów).`)) {
                return;
            }
            
            const result = document.getElementById('materialResult');
            try {
                result.textContent = '⏳ Usuwanie grupy...';
                result.className = 'result';
                result.style.display = 'block';
                
                const formData = new FormData();
                formData.append('action', 'delete_group');
                formData.append('id', groupId);
                
                const response = await fetch('material_api.php', {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                
                if (!data.success) {
                    throw new Error(data.message);
                }
                
                result.textContent = '✓ ' + data.message;
                result.className = 'result success';
                
                setTimeout(() => loadMaterialGroups(), 1000);
                
            } catch (error) {
                console.error('Błąd:', error);
                result.textContent = '✗ ' + error.message;
                result.className = 'result error';
            }
        }
        
        function showAddMaterialDialog(groupId, groupName) {
            const name = prompt(`Dodaj nowy materiał do grupy "${groupName}":\n\nPodaj nazwę materiału (np. "Kruszywo 0/22"):`);
            if (!name || !name.trim()) return;
            addMaterial(groupId, name.trim());
        }
        
        async function addMaterial(groupId, name) {
            const result = document.getElementById('materialResult');
            try {
                result.textContent = '⏳ Dodawanie materiału...';
                result.className = 'result';
                result.style.display = 'block';
                
                const formData = new FormData();
                formData.append('action', 'add_material');
                formData.append('group_id', groupId);
                formData.append('name', name);
                
                const response = await fetch('material_api.php', {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                
                if (!data.success) {
                    throw new Error(data.message);
                }
                
                result.textContent = '✓ ' + data.message;
                result.className = 'result success';
                
                setTimeout(() => loadMaterialGroups(), 1000);
                
            } catch (error) {
                console.error('Błąd:', error);
                result.textContent = '✗ ' + error.message;
                result.className = 'result error';
            }
        }
        
        function showEditMaterialDialog(materialId, groupId, currentName) {
            const name = prompt('Podaj nową nazwę materiału:', currentName);
            if (!name || !name.trim() || name.trim() === currentName) return;
            updateMaterial(materialId, name.trim());
        }
        
        async function updateMaterial(materialId, name) {
            const result = document.getElementById('materialResult');
            try {
                result.textContent = '⏳ Aktualizowanie materiału...';
                result.className = 'result';
                result.style.display = 'block';
                
                const formData = new FormData();
                formData.append('action', 'update_material');
                formData.append('id', materialId);
                formData.append('name', name);
                
                const response = await fetch('material_api.php', {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                
                if (!data.success) {
                    throw new Error(data.message);
                }
                
                result.textContent = '✓ ' + data.message;
                result.className = 'result success';
                
                setTimeout(() => loadMaterialGroups(), 1000);
                
            } catch (error) {
                console.error('Błąd:', error);
                result.textContent = '✗ ' + error.message;
                result.className = 'result error';
            }
        }
        
        async function deleteMaterial(materialId, materialName) {
            if (!confirm(`Czy na pewno usunąć materiał "${materialName}"?\n\nUWAGA: Nie można usunąć materiału używanego w dokumentach WZ.`)) {
                return;
            }
            
            const result = document.getElementById('materialResult');
            try {
                result.textContent = '⏳ Usuwanie materiału...';
                result.className = 'result';
                result.style.display = 'block';
                
                const formData = new FormData();
                formData.append('action', 'delete_material');
                formData.append('id', materialId);
                
                const response = await fetch('material_api.php', {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                
                if (!data.success) {
                    throw new Error(data.message);
                }
                
                result.textContent = '✓ ' + data.message;
                result.className = 'result success';
                
                setTimeout(() => loadMaterialGroups(), 1000);
                
            } catch (error) {
                console.error('Błąd:', error);
                result.textContent = '✗ ' + error.message;
                result.className = 'result error';
            }
        }
        
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML.replace(/'/g, '&#39;');
        }
    </script>
    <script src="dashboard.js"></script>
</body>
</html>
