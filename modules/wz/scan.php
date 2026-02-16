<?php
/**
 * Moduł WZ - Skanowanie dokumentów
 */

require_once __DIR__.'/../../core/session.php';
require_once __DIR__.'/../../core/auth.php';

// Wymagane konto managera (księgowy lub admin)
$manager = requireManager(2);

// Pobranie listy budów i operatorów
$pdo = require __DIR__.'/../../core/db.php';

// Budowy tylko z aktywną sesją kierowcy (is_operator = 2), tak jak przy wyborze operatora
$sitesStmt = $pdo->query("SELECT DISTINCT s.id, s.name
    FROM sites s
    INNER JOIN work_sessions ws ON ws.site_id = s.id
    INNER JOIN employees e ON e.id = ws.employee_id
    WHERE s.active = 1
      AND s.name NOT IN ('URLOP', 'L4')
      AND e.is_operator = 2
      AND ws.end_time IS NULL
    ORDER BY s.name");
$sites = $sitesStmt->fetchAll(PDO::FETCH_ASSOC);

// Operatorzy / kierowcy (odbiorcy WZ) – pobierz wraz z site_id aby móc filtrować po budowie
$operatorsStmt = $pdo->query("SELECT DISTINCT e.id, e.first_name, e.last_name, ws.site_id
    FROM employees e
    INNER JOIN work_sessions ws ON ws.employee_id = e.id
    WHERE e.is_operator = 2
        AND ws.machine_id IS NOT NULL
        AND ws.end_time IS NULL
    ORDER BY e.last_name, e.first_name");
$operators = $operatorsStmt->fetchAll(PDO::FETCH_ASSOC);

// Pobierz grupy materiałów i ich rodzaje z bazy danych
$materialGroupsStmt = $pdo->query("SELECT id, name, display_order FROM material_groups ORDER BY display_order, name");
$materialGroups = $materialGroupsStmt->fetchAll(PDO::FETCH_ASSOC);

$materialsByGroup = [];
foreach ($materialGroups as $group) {
    $materialsStmt = $pdo->prepare("SELECT id, name, display_order FROM material_types WHERE group_id = ? ORDER BY display_order, name");
    $materialsStmt->execute([$group['id']]);
    $materialsByGroup[$group['id']] = $materialsStmt->fetchAll(PDO::FETCH_ASSOC);
}

// Automatyczne nadawanie numeru dokumentu WZ: WZ/ROK/NNN
$currentYear = date('Y');
$documentPrefix = 'WZ/' . $currentYear . '/';
$nextDocumentNumber = $documentPrefix . '001';

try {
    $stmt = $pdo->prepare("SELECT document_number FROM wz_scans WHERE document_number LIKE ? ORDER BY id DESC LIMIT 1");
    $stmt->execute([$documentPrefix . '%']);
    $lastNumber = $stmt->fetchColumn();

    if ($lastNumber) {
        $suffix = substr($lastNumber, strlen($documentPrefix));
        if (ctype_digit($suffix)) {
            $seq = (int)$suffix + 1;
            $nextDocumentNumber = $documentPrefix . sprintf('%03d', $seq);
        }
    }
} catch (Throwable $e) {
    // W razie błędu zostaw domyślne WZ/ROK/001
}
?>
<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Skanowanie WZ</title>

    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        .container {
            max-width: 900px;
            margin: 0 auto;
            background: rgba(255, 255, 255, 0.98);
            border-radius: 20px;
            padding: 40px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        }
        h1 {
            color: #333;
            margin-bottom: 10px;
            font-size: 32px;
            text-align: center;
        }
        .subtitle {
            text-align: center;
            color: #666;
            margin-bottom: 30px;
            font-size: 14px;
        }
        
        .form-section {
            background: #f8f9fa;
            padding: 25px;
            border-radius: 15px;
            margin-bottom: 20px;
        }
        .form-section h3 {
            color: #444;
            margin-bottom: 15px;
            font-size: 18px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .form-row {
            display: grid;
            gap: 15px;
            margin-bottom: 15px;
        }
        label {
            display: block;
            margin-bottom: 5px;
            color: #555;
            font-weight: 500;
            font-size: 14px;
        }
        input[type="text"],
        input[type="file"],
        select,
        textarea {
            width: 100%;
            padding: 12px;
            border: 2px solid #ddd;
            border-radius: 8px;
            font-size: 15px;
            font-family: inherit;
            transition: border-color 0.3s;
        }
        input:focus,
        select:focus,
        textarea:focus {
            outline: none;
            border-color: #667eea;
        }
        textarea {
            resize: vertical;
            min-height: 80px;
        }
        
        .file-upload-wrapper {
            position: relative;
            display: inline-block;
            width: 100%;
        }
        .file-upload-label {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            padding: 30px;
            border: 3px dashed #667eea;
            border-radius: 10px;
            background: white;
            cursor: pointer;
            transition: all 0.3s;
            color: #667eea;
            font-size: 16px;
        }
        .file-upload-label:hover {
            background: #f0f2ff;
            border-color: #5568d3;
        }
        input[type="file"] {
            position: absolute;
            opacity: 0;
            width: 0;
            height: 0;
        }
        .file-name {
            margin-top: 10px;
            color: #28a745;
            font-weight: 500;
            font-size: 14px;
        }
        
        .preview-section {
            margin-top: 15px;
            padding: 15px;
            background: white;
            border-radius: 8px;
            display: none;
        }
        .preview-section img {
            max-width: 100%;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        
        .btn {
            padding: 15px 30px;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(102, 126, 234, 0.4);
        }
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        .btn-secondary:hover {
            background: #5a6268;
        }
        .btn-danger {
            background: #dc3545;
            color: white;
        }
        .btn-danger:hover {
            background: #c82333;
        }
        .btn-success {
            background: #28a745;
            color: white;
        }
        .btn-success:hover {
            background: #218838;
        }
        
        .button-row {
            display: flex;
            gap: 15px;
            justify-content: center;
            margin-top: 30px;
            flex-wrap: wrap;
        }
        
        .result {
            padding: 15px;
            border-radius: 10px;
            margin-top: 20px;
            text-align: center;
            font-weight: 500;
            display: none;
        }
        .result.success {
            background: #d4edda;
            color: #155724;
            border: 2px solid #c3e6cb;
            display: block;
        }
        .result.error {
            background: #f8d7da;
            color: #721c24;
            border: 2px solid #f5c6cb;
            display: block;
        }

        .material-summary {
            margin-top: 10px;
            padding: 12px 14px;
            border-radius: 10px;
            background: #fff3cd;
            border-left: 4px solid #ffc107;
            font-size: 14px;
            color: #856404;
        }
        .material-summary strong {
            font-weight: 700;
        }
        
        @media (max-width: 768px) {
            .container {
                padding: 20px;
            }
            h1 {
                font-size: 24px;
            }
            .button-row {
                flex-direction: column;
            }
            .btn {
                width: 100%;
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>📄 Skanowanie WZ</h1>
        <div class="subtitle">Dodaj nowy dokument wydania zewnętrznego</div>
        
        <form id="wzForm" enctype="multipart/form-data">
            <!-- Podstawowe dane -->
            <div class="form-section">
                <h3>📋 Dane dokumentu</h3>
                <div class="form-row">
                    <div>
                           <label for="documentNumber">Numer dokumentu WZ *</label>
                           <input type="text" id="documentNumber" name="document_number" required
                               placeholder="np. WZ/2026/001"
                               value="<?= htmlspecialchars($nextDocumentNumber) ?>" readonly>
                    </div>
                </div>
                <div class="form-row">
                    <div>
                        <label for="siteSelect">Budowa *</label>
                        <select id="siteSelect" name="site_id" required>
                            <option value="">— Wybierz budowę —</option>
                            <?php foreach ($sites as $site): ?>
                                <option value="<?= $site['id'] ?>"><?= htmlspecialchars($site['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="form-row">
                    <div>
                        <label for="operatorSelect">Operator / odbiorca materiału *</label>
                        <select id="operatorSelect" name="operator_id" required>
                            <option value="">— Wybierz operatora —</option>
                            <?php foreach ($operators as $op): ?>
                                <option value="<?= $op['id'] ?>" data-site-id="<?= $op['site_id'] ?>">
                                    <?= htmlspecialchars($op['last_name'].' '.$op['first_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="form-row">
                    <div>
                        <label for="materialGroup">🏷️ Grupa materiału *</label>
                        <select id="materialGroup" required>
                            <option value="">— Wybierz grupę —</option>
                            <?php foreach ($materialGroups as $group): ?>
                                <option value="<?= $group['id'] ?>"><?= htmlspecialchars($group['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="form-row">
                    <div>
                        <label for="materialType">🧱 Rodzaj materiału *</label>
                        <select id="materialType" name="material_type" required disabled>
                            <option value="">— Najpierw wybierz grupę materiału —</option>
                        </select>
                    </div>
                    <div>
                        <label for="materialQuantity">📦 Ilość *</label>
                        <input type="number" id="materialQuantity" name="material_quantity" required min="0" step="0.01" placeholder="np. 12.5">
                    </div>
                </div>
                <div id="materialSummary" class="material-summary" style="display:none;">
                    🧱 Materiał: <strong id="materialSummaryType">-</strong><br>
                    📦 Ilość: <strong id="materialSummaryQty">-</strong>
                </div>
                <div class="form-row">
                    <div>
                        <label for="notes">Uwagi</label>
                        <textarea id="notes" name="notes" placeholder="Dodatkowe informacje (opcjonalnie)"></textarea>
                    </div>
                </div>
            </div>
            
            <!-- Upload skanu -->
            <div class="form-section">
                <h3>📷 Skan dokumentu</h3>
                <div class="file-upload-wrapper">
                    <label for="scanFile" class="file-upload-label">
                        <span style="font-size: 40px;">📁</span>
                        <div>
                            <strong>Kliknij aby wybrać plik</strong><br>
                            <small>JPG, PNG lub PDF (max 10MB)</small>
                        </div>
                    </label>
                    <input type="file" id="scanFile" name="scan" accept="image/jpeg,image/png,application/pdf">
                    <div class="file-name" id="fileName"></div>
                </div>
                <div class="preview-section" id="previewSection">
                    <img id="previewImage" alt="Podgląd skanu">
                </div>
            </div>
            
            <div id="result" class="result"></div>
            
            <div class="button-row">
                <button type="submit" class="btn btn-primary">💾 Zapisz dokument WZ</button>
                <button type="button" class="btn btn-secondary" onclick="viewArchive()">📂 Archiwum</button>
                <a href="#" onclick="goBackToEntry('../../panel/dashboard.php'); return false;" class="btn btn-secondary">⬅️ Powrót</a>
            </div>
        </form>
    </div>
    
    <script>
        // ==================== DANE MATERIAŁÓW Z PHP ====================
        const materialGroup = document.getElementById('materialGroup');
        const materialTypeSelect = document.getElementById('materialType');
        const siteSelect = document.getElementById('siteSelect');
        const operatorSelect = document.getElementById('operatorSelect');
        
        // Dane materiałów z PHP (zamiast pobierania przez API)
        const materialsByGroup = <?= json_encode($materialsByGroup, JSON_UNESCAPED_UNICODE) ?>;

        // Przechowuj wszystkie opcje operatorów dla filtrowania
        const allOperatorOptions = operatorSelect ? Array.from(operatorSelect.options).slice(1) : [];

        // Filtrowanie operatorów na podstawie wybranej budowy
        if (siteSelect && operatorSelect) {
            siteSelect.addEventListener('change', function() {
                const selectedSiteId = this.value;
                
                // Usuń wszystkie opcje oprócz pierwszej (placeholder)
                while (operatorSelect.options.length > 1) {
                    operatorSelect.remove(1);
                }
                
                if (!selectedSiteId) {
                    // Jeśli nie wybrano budowy, pokazuj wszystkich operatorów
                    allOperatorOptions.forEach(opt => {
                        operatorSelect.appendChild(opt.cloneNode(true));
                    });
                } else {
                    // Filtruj operatorów pracujących na wybranej budowie
                    const filteredOptions = allOperatorOptions.filter(opt => {
                        return opt.getAttribute('data-site-id') === selectedSiteId;
                    });
                    
                    if (filteredOptions.length > 0) {
                        filteredOptions.forEach(opt => {
                            operatorSelect.appendChild(opt.cloneNode(true));
                        });
                    } else {
                        // Jeśli brak operatorów na tej budowie, pokaż komunikat
                        const noOperatorOption = document.createElement('option');
                        noOperatorOption.value = '';
                        noOperatorOption.textContent = '— Brak operatorów na tej budowie —';
                        noOperatorOption.disabled = true;
                        operatorSelect.appendChild(noOperatorOption);
                    }
                }
            });
        }

        // Event listener dla wyboru grupy
        if (materialGroup && materialTypeSelect) {
            materialGroup.addEventListener('change', function() {
                const selectedGroupId = this.value;
                
                // Usuń wszystkie opcje oprócz pierwszej
                while (materialTypeSelect.options.length > 1) {
                    materialTypeSelect.remove(1);
                }
                
                if (!selectedGroupId) {
                    // Brak wybranej grupy - wyłącz drugi dropdown
                    materialTypeSelect.disabled = true;
                    materialTypeSelect.options[0].textContent = '— Najpierw wybierz grupę materiału —';
                } else {
                    // Wypełnij drugi dropdown materiałami z wybranej grupy
                    materialTypeSelect.disabled = false;
                    materialTypeSelect.options[0].textContent = '— Wybierz rodzaj materiału —';
                    
                    const materials = materialsByGroup[selectedGroupId] || [];
                    materials.forEach(material => {
                        const option = document.createElement('option');
                        option.value = material.name;
                        option.textContent = material.name;
                        materialTypeSelect.appendChild(option);
                    });
                }
                
                // Odśwież podsumowanie po zmianie
                refreshMaterialSummary();
            });
        }

        // ==================== RESZTA KODU ====================
        function goBackToEntry(fallbackUrl) {
            // Zawsze wróć do podanego adresu (np. panelu),
            // nie korzystaj z document.referrer, żeby nie wracać losowo do skanowania/archiwum
            window.location.href = fallbackUrl;
        }

        // Podgląd pliku
        const scanFile = document.getElementById('scanFile');
        const fileName = document.getElementById('fileName');
        const previewSection = document.getElementById('previewSection');
        const previewImage = document.getElementById('previewImage');
        
        scanFile.addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                fileName.textContent = `✓ ${file.name} (${(file.size / 1024 / 1024).toFixed(2)} MB)`;
                
                // Podgląd tylko dla obrazów
                if (file.type.startsWith('image/')) {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        previewImage.src = e.target.result;
                        previewSection.style.display = 'block';
                    };
                    reader.readAsDataURL(file);
                } else {
                    previewSection.style.display = 'none';
                }
            } else {
                fileName.textContent = '';
                previewSection.style.display = 'none';
            }
        });
        
        // Zapisywanie dokumentu
        const wzForm = document.getElementById('wzForm');
        const result = document.getElementById('result');
        // materialTypeSelect już zadeklarowany na początku skryptu
        const materialQuantityInput = document.getElementById('materialQuantity');
        const materialSummary = document.getElementById('materialSummary');
        const materialSummaryType = document.getElementById('materialSummaryType');
        const materialSummaryQty = document.getElementById('materialSummaryQty');

        function refreshMaterialSummary() {
            if (!materialSummary || !materialTypeSelect || !materialQuantityInput) return;

            const typeText = materialTypeSelect.options[materialTypeSelect.selectedIndex]?.text || '';
            const qtyRaw = materialQuantityInput.value.trim();

            if (!typeText && !qtyRaw) {
                materialSummary.style.display = 'none';
                return;
            }

            materialSummaryType.textContent = typeText || '-';
            materialSummaryQty.textContent = qtyRaw || '-';
            materialSummary.style.display = 'block';
        }

        if (materialTypeSelect) {
            materialTypeSelect.addEventListener('change', refreshMaterialSummary);
        }
        if (materialQuantityInput) {
            materialQuantityInput.addEventListener('input', refreshMaterialSummary);
        }
        
        wzForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const formData = new FormData(wzForm);
            const qtyRaw = (formData.get('material_quantity') || '').toString().trim();
            const qtyNum = parseFloat(qtyRaw.replace(',', '.'));

            if (!qtyRaw || isNaN(qtyNum) || qtyNum <= 0) {
                result.className = 'result error';
                result.textContent = '✗ Podaj prawidłową, dodatnią ilość materiału';
                result.style.display = 'block';
                return;
            }

            refreshMaterialSummary();
            
            result.className = 'result';
            result.textContent = '⏳ Zapisywanie...';
            result.style.display = 'block';
            
            try {
                const response = await fetch('save_wz.php', {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                
                if (data.success) {
                    result.className = 'result success';
                    result.textContent = `✓ ${data.message} (${data.document_number})`;
                    
                    // Reset formularza
                    setTimeout(() => {
                        wzForm.reset();
                        fileName.textContent = '';
                        previewSection.style.display = 'none';
                        
                        if (confirm('Dokument zapisany! Przejść do archiwum?')) {
                            viewArchive();
                        }
                    }, 2000);
                } else {
                    result.className = 'result error';
                    result.textContent = `✗ ${data.message}`;
                }
            } catch (error) {
                result.className = 'result error';
                result.textContent = '✗ Błąd połączenia z serwerem';
            }
        });
        
        function viewArchive() {
            window.location.href = 'list_wz.php';
        }
    </script>
</body>
</html>
