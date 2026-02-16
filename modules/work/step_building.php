<?php
/**
 * STEP 1: Wybór budowy
 * Pracownik wybiera na jakiej budowie będzie pracować
 */

require_once __DIR__.'/../../core/session.php';
require_once __DIR__.'/../../core/auth.php';

$user = requireUser();
$pdo = require __DIR__.'/../../core/db.php';

// Pobierz listę aktywnych budów
$stmt = $pdo->query("
    SELECT id, name 
    FROM sites 
    WHERE active = 1 
    ORDER BY name
");
$sites = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Wybierz budowę</title>

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
            margin-bottom: 30px;
        }
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
            padding: 15px;
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
        #siteDisplay {
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
        .site-input-group {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        @media (max-width: 767px) {
            body {
                align-items: flex-start;
                padding-top: 50px;
            }
            .container {
                max-width: 900px;
                width: 95vw;
                padding: 30px 20px;
                overflow: visible;
            }
            .dropdown-menu {
                max-width: 100%;
            }
        }
        @media (min-width: 768px) {
            .site-input-group {
                flex-direction: row;
                align-items: stretch;
            }
            .dropdown-menu {
                max-width: 600px;
                left: auto;
                right: 0;
            }
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
    </style>
</head>
<body>
    <video class="bg-video" autoplay loop muted playsinline>
        <source src="../../background.mp4" type="video/mp4">
    </video>
    <div class="video-overlay"></div>
    <div class="container">
        <h1>🏗️ Wybierz budowę</h1>
        <div class="subtitle">Krok 1 z <?= $user['is_operator'] ? '3' : '2' ?></div>

        <div class="actions" style="margin-bottom: 20px;">
            <button class="btn btn-back" onclick="goBackToEntry('../../panel.php')">⬅️ Powrót</button>
            <button class="btn btn-next" id="btnNext" disabled onclick="next()">Dalej ➡️</button>
        </div>

        <!-- Pole wyboru budowy -->
        <div style="margin-bottom: 20px;">
            <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #333;">
                Wybrana budowa:
            </label>
            <div class="site-input-group">
                <input 
                    type="text" 
                    id="siteInput" 
                    placeholder="Wpisz nazwę budowy..." 
                    style="flex: 1; padding: 15px; border: 2px solid #667eea; border-radius: 10px; font-size: 16px; font-weight: 600;">
                <div class="site-dropdown">
                    <div id="siteDisplay" onclick="toggleDropdown()">
                        Wciśnij aby wybierać budowę ▼
                    </div>
                    <div id="dropdownMenu" class="dropdown-menu">
                        <?php foreach ($sites as $site): ?>
                            <div class="dropdown-item" onclick="selectFromDropdown(<?= $site['id'] ?>, '<?= htmlspecialchars($site['name']) ?>')">
                                <?= htmlspecialchars($site['name']) ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <div id="matchInfo" style="margin-top: 8px; font-size: 13px; color: #666;"></div>
        </div>
    </div>

    <script>
        function goBackToEntry(fallbackUrl) {
            const ref = document.referrer || '';

            // Jeżeli przyszliśmy z innego modułu niż kroki pracy, wracamy do poprzedniej strony.
            // Jeśli referrer to któryś z kroków pracy (step_building / step_machine / step_confirm),
            // wracamy bezpośrednio do głównego panelu, żeby uniknąć pętli między krokami.
            if (ref && !ref.includes('modules/work/step_')) {
                window.location.href = ref;
            } else {
                window.location.href = fallbackUrl;
            }
        }

        let selectedSiteId = null;
        let selectedSiteName = null;
        const isOperator = <?= $user['is_operator'] ?>;
        
        // Lista budów z PHP
        const sites = <?= json_encode($sites) ?>;
        
        const siteInput = document.getElementById('siteInput');
        const siteDisplay = document.getElementById('siteDisplay');
        const matchInfo = document.getElementById('matchInfo');
        const btnNext = document.getElementById('btnNext');
        const dropdownMenu = document.getElementById('dropdownMenu');

        // Wczytaj zapisany wybór przy starcie
        window.addEventListener('load', function() {
            const savedId = sessionStorage.getItem('selected_site_id');
            const savedName = sessionStorage.getItem('selected_site_name');
            
            if (savedId && savedName) {
                const site = sites.find(s => s.id == savedId);
                if (site) {
                    selectedSiteId = parseInt(savedId);
                    selectedSiteName = savedName;
                    siteInput.value = savedName;
                    updateDisplay(savedName);
                }
            }
        });

        // Toggle dropdown z dynamiczną wysokością na mobile
        function toggleDropdown() {
            const isMobile = window.innerWidth <= 767;

            if (dropdownMenu.classList.contains('show')) {
                dropdownMenu.classList.remove('show');
                if (isMobile) {
                    dropdownMenu.style.maxHeight = '';
                }
                return;
            }

            if (isMobile) {
                const rect = siteDisplay.getBoundingClientRect();
                const available = rect.top - 10; // miejsce od górnej krawędzi do pola
                const minHeight = 120;
                const maxHeight = Math.max(minHeight, available);
                dropdownMenu.style.maxHeight = maxHeight + 'px';
            }

            dropdownMenu.classList.add('show');
        }

        // Zamknij dropdown po kliknięciu poza nim
        document.addEventListener('click', function(e) {
            if (!e.target.closest('.site-dropdown')) {
                dropdownMenu.classList.remove('show');
                if (window.innerWidth <= 767) {
                    dropdownMenu.style.maxHeight = '';
                }
            }
        });

        // Wybór z dropdown
        function selectFromDropdown(id, name) {
            selectedSiteId = id;
            selectedSiteName = name;

            siteInput.value = name;
            updateDisplay(name);
            dropdownMenu.classList.remove('show');
            
            // Zapisz w sessionStorage
            sessionStorage.setItem('selected_site_id', id);
            sessionStorage.setItem('selected_site_name', name);
        }

        // Funkcja aktualizująca display
        function updateDisplay(name) {
            siteDisplay.innerHTML = name + ' ▼';
            siteDisplay.style.background = 'linear-gradient(135deg, #667eea 0%, #764ba2 100%)';
            siteDisplay.style.color = 'white';
            matchInfo.textContent = '✓ Budowa wybrana';
            matchInfo.style.color = '#28a745';
            btnNext.disabled = false;
        }

        // Live update przy wpisywaniu
        siteInput.addEventListener('input', function() {
            const searchText = this.value.toLowerCase().trim();
            
            if (!searchText) {
                siteDisplay.innerHTML = 'Wciśnij aby wybierać budowę ▼';
                siteDisplay.style.background = '#e7f3ff';
                siteDisplay.style.color = '#667eea';
                matchInfo.textContent = '';
                btnNext.disabled = true;
                selectedSiteId = null;
                return;
            }

            // Szukaj dopasowania
            const match = sites.find(s => 
                s.name.toLowerCase().includes(searchText)
            );

            if (match) {
                selectedSiteId = match.id;
                selectedSiteName = match.name;
                
                updateDisplay(match.name);
                
                // Zapisz w sessionStorage
                sessionStorage.setItem('selected_site_id', match.id);
                sessionStorage.setItem('selected_site_name', match.name);
            } else {
                siteDisplay.innerHTML = 'Brak budowy ▼';
                siteDisplay.style.background = '#f8d7da';
                siteDisplay.style.color = '#721c24';
                matchInfo.textContent = '✗ Nie znaleziono budowy';
                matchInfo.style.color = '#dc3545';
                btnNext.disabled = true;
                selectedSiteId = null;
            }
        });

        function selectSite(id, name) {
            selectedSiteId = id;
            selectedSiteName = name;

            // Zaznacz wybraną budowę
            document.querySelectorAll('.site-btn').forEach(btn => {
                btn.classList.remove('selected');
            });
            event.target.classList.add('selected');

            // Włącz przycisk Dalej
            document.getElementById('btnNext').disabled = false;
        }

        function next() {
            if (!selectedSiteId) return;

            // Zapisz w sessionStorage
            sessionStorage.setItem('selected_site_id', selectedSiteId);
            sessionStorage.setItem('selected_site_name', selectedSiteName);

            // Operator → wybór maszyny, Pracownik → podsumowanie
            if (isOperator) {
                window.location.href = 'step_machine.php';
            } else {
                window.location.href = 'step_confirm.php';
            }
        }

        function back() {
            window.location.href = '../../panel.php';
        }
    </script>
</body>
</html>
