<?php
/**
 * Moduł WZ - Panel operatora (odbiorcy)
 * Operator potwierdza lub odrzuca dokumenty WZ przypisane do niego
 */

require_once __DIR__ . '/../../core/session.php';
require_once __DIR__ . '/../../core/auth.php';

$user = requireUser();

if (empty($user['is_operator'])) {
    http_response_code(403);
    echo 'Moduł dostępny tylko dla operatorów.';
    exit;
}

$pdo = require __DIR__ . '/../../core/db.php';

$stmt = $pdo->prepare("SELECT 
    w.id,
    w.document_number,
    w.material_type,
    w.material_quantity,
    w.status,
    w.notes,
    w.created_at,
    s.name AS site_name,
    COALESCE(
        CONCAT(e.first_name, ' ', e.last_name),
        CONCAT(m.first_name, ' ', m.last_name)
    ) AS creator_name
FROM wz_scans w
LEFT JOIN sites s ON w.site_id = s.id
LEFT JOIN employees e ON w.employee_id = e.id
LEFT JOIN managers m ON w.manager_id = m.id
WHERE w.operator_id = ?
ORDER BY w.created_at DESC");
$stmt->execute([$user['id']]);
$documents = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Dodatkowa sekcja skanowania WZ tylko dla specjalnego operatora (is_operator = 3)
$canScanWz = ((int)$user['is_operator'] === 3);

$sites = [];
$operators = [];
$nextDocumentNumber = '';

if ($canScanWz) {
    // Lista aktywnych budów tylko z aktywną sesją kierowcy (is_operator = 2), jak w scan.php
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

    // Operatorzy / odbiorcy – kierowcy z aktywną sesją na maszynie (is_operator = 2)
    // Pobierz wraz z site_id aby móc filtrować po budowie
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

    // Automatyczne nadawanie numeru WZ jak w scan.php
    $currentYear = date('Y');
    $documentPrefix = 'WZ/' . $currentYear . '/';
    $nextDocumentNumber = $documentPrefix . '001';

    try {
        $stmtNum = $pdo->prepare("SELECT document_number FROM wz_scans WHERE document_number LIKE ? ORDER BY id DESC LIMIT 1");
        $stmtNum->execute([$documentPrefix . '%']);
        $lastNumber = $stmtNum->fetchColumn();

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
}
?>
<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>WZ operatora</title>

    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        .container {
            max-width: 1100px;
            margin: 0 auto;
            background: rgba(255, 255, 255, 0.98);
            border-radius: 20px;
            padding: 30px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        }
        h1 {
            color: #333;
            margin-bottom: 10px;
            font-size: 28px;
            text-align: center;
        }
        .subtitle {
            text-align: center;
            color: #666;
            margin-bottom: 25px;
            font-size: 14px;
        }
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-block;
        }
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 15px rgba(102, 126, 234, 0.4);
        }
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        .btn-secondary:hover { background: #5a6268; }
        .btn-success { background: #28a745; color: white; }
        .btn-success:hover { background: #218838; }
        .btn-danger { background: #dc3545; color: white; }
        .btn-danger:hover { background: #c82333; }

        .top-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            flex-wrap: wrap;
            gap: 10px;
        }
        .list {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }
        .card {
            background: #f8f9fa;
            border-radius: 12px;
            padding: 15px 18px;
            border: 1px solid #e0e0e0;
            display: flex;
            flex-direction: column;
            gap: 6px;
        }
        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }
        .card-title {
            font-weight: 600;
            font-size: 16px;
        }
        .status-badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
        }
        .status-waiting_operator { background: #17a2b8; color: #fff; }
        .status-waiting_manager { background: #007bff; color: #fff; }
        .status-approved { background: #28a745; color: #fff; }
        .status-rejected { background: #dc3545; color: #fff; }
        .status-draft { background: #ffc107; color: #333; }
        .meta {
            font-size: 13px;
            color: #555;
        }
        .notes {
            font-size: 13px;
            color: #444;
            margin-top: 4px;
        }
        .actions {
            margin-top: 8px;
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }
        .empty {
            text-align: center;
            padding: 40px 20px;
            color: #666;
            font-size: 15px;
        }
        /* Styl sekcji skanowania WZ jak w scan.php */
        .form-section {
            background: #f8f9fa;
            padding: 20px;
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
            padding: 10px 12px;
            border: 2px solid #ddd;
            border-radius: 8px;
            font-size: 14px;
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
            min-height: 70px;
        }
        .button-row {
            display: flex;
            gap: 10px;
            justify-content: flex-start;
            flex-wrap: wrap;
            margin-top: 10px;
        }
        .result-op {
            padding: 10px 12px;
            border-radius: 10px;
            margin-top: 10px;
            text-align: center;
            font-weight: 500;
            display: none;
        }
        .result-op.success {
            background: #d4edda;
            color: #155724;
            border: 2px solid #c3e6cb;
            display: block;
        }
        .result-op.error {
            background: #f8d7da;
            color: #721c24;
            border: 2px solid #f5c6cb;
            display: block;
        }
        @media (max-width: 768px) {
            .container { padding: 20px; }
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>📄 Dokumenty WZ</h1>
        <div class="subtitle">Lista dokumentów WZ przypisanych do Ciebie jako operatora</div>

        <?php if ($canScanWz): ?>
            <div class="form-section" style="margin-bottom: 25px;">
                <h3>📷 Dodaj nowy dokument WZ</h3>
                <form id="wzFormOperator" enctype="multipart/form-data">
                    <div class="form-row">
                        <div>
                            <label for="opDocumentNumber">Numer dokumentu WZ *</label>
                            <input type="text" id="opDocumentNumber" name="document_number" required
                                   value="<?= htmlspecialchars($nextDocumentNumber) ?>" readonly>
                        </div>
                    </div>
                    <div class="form-row">
                        <div>
                            <label for="opSiteSelect">Budowa *</label>
                            <select id="opSiteSelect" name="site_id" required>
                                <option value="">— Wybierz budowę —</option>
                                <?php foreach ($sites as $site): ?>
                                    <option value="<?= $site['id'] ?>"><?= htmlspecialchars($site['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="form-row">
                        <div>
                            <label for="opOperatorSelect">Operator / odbiorca materiału *</label>
                            <select id="opOperatorSelect" name="operator_id" required>
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
                            <label for="opSourceSelect">Skąd ładowane / dokąd transportowane *</label>
                            <select id="opSourceSelect" name="material_source" required>
                                <option value="">— Wybierz miejsce —</option>
                                <option value="bg_construction">B.G Construction</option>
                                <option value="podwykonawstwo">Podwykonawstwo</option>
                                <option value="na_sprzedaz">Na sprzedaż</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-row">
                        <div>
                            <label for="opMaterialGroup">🏷️ Grupa materiału *</label>
                            <select id="opMaterialGroup" required>
                                <option value="">— Wybierz grupę —</option>
                                <?php foreach ($materialGroups as $group): ?>
                                    <option value="<?= $group['id'] ?>"><?= htmlspecialchars($group['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="form-row">
                        <div>
                            <label for="opMaterialType">🧱 Rodzaj materiału *</label>
                            <select id="opMaterialType" name="material_type" required disabled>
                                <option value="">— Najpierw wybierz grupę materiału —</option>
                            </select>
                        </div>
                        <div>
                            <label for="opMaterialQuantity">📦 Ilość *</label>
                            <input type="number" id="opMaterialQuantity" name="material_quantity" required min="0" step="0.01" placeholder="np. 12.5">
                        </div>
                    </div>
                    <div class="form-row">
                        <div>
                            <label for="opNotes">Uwagi</label>
                            <textarea id="opNotes" name="notes" placeholder="Dodatkowe informacje (opcjonalnie)"></textarea>
                        </div>
                    </div>
                    <div class="form-row">
                        <div>
                            <label for="opScanFile">Skan dokumentu (JPG, PNG, PDF, max 10MB) – opcjonalnie</label>
                            <input type="file" id="opScanFile" name="scan" accept="image/jpeg,image/png,application/pdf">
                        </div>
                    </div>
                    <div class="button-row">
                        <button type="submit" class="btn btn-primary">💾 Zapisz dokument WZ</button>
                    </div>
                    <div id="opResult" class="result-op"></div>
                </form>
            </div>
        <?php endif; ?>

        <div class="top-bar">
            <div>
                <strong>Operator:</strong>
                <?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?>
            </div>
            <div>
                <a href="../../panel.php" class="btn btn-secondary">⬅️ Powrót do panelu</a>
            </div>
        </div>

        <?php if (empty($documents)): ?>
            <div class="empty">
                📭 Nie masz aktualnie żadnych dokumentów WZ.
            </div>
        <?php else: ?>
            <div class="list">
                <?php
                $statusLabels = [
                    'draft' => 'Wersja robocza',
                    'waiting_operator' => 'Do potwierdzenia (kierowca)',
                    'waiting_manager' => 'Wysłane do kierownika',
                    'approved' => 'Zatwierdzone',
                    'rejected' => 'Odrzucone',
                ];
                ?>
                <?php foreach ($documents as $doc): ?>
                    <?php
                    $status = $doc['status'];
                    $statusClass = 'status-' . $status;
                    $statusLabel = $statusLabels[$status] ?? $status;
                    ?>
                    <div class="card" data-id="<?= (int)$doc['id'] ?>" data-status="<?= htmlspecialchars($status) ?>">
                        <div class="card-header">
                            <div class="card-title">
                                <?= htmlspecialchars($doc['document_number']) ?>
                            </div>
                            <span class="status-badge <?= $statusClass ?>"><?= $statusLabel ?></span>
                        </div>
                        <div class="meta">
                            📍 Budowa: <?= htmlspecialchars($doc['site_name'] ?? 'Brak') ?><br>
                            🧱 Materiał: <strong><?= htmlspecialchars($doc['material_type'] ?? '-') ?></strong><br>
                            📦 Ilość: <strong><?= $doc['material_quantity'] !== null ? number_format((float)$doc['material_quantity'], 2, ',', ' ') : '-' ?></strong><br>
                            👤 Utworzono przez: <?= htmlspecialchars($doc['creator_name'] ?? 'Nieznany') ?><br>
                            📅 Data: <?= date('d.m.Y H:i', strtotime($doc['created_at'])) ?>
                        </div>
                        <?php if (!empty($doc['notes'])): ?>
                            <div class="notes">
                                📝 Uwagi: <?= nl2br(htmlspecialchars($doc['notes'])) ?>
                            </div>
                        <?php endif; ?>

                        <?php if ($status === 'waiting_operator'): ?>
                            <div class="actions">
                                <button class="btn btn-success" onclick="handleAction(<?= (int)$doc['id'] ?>, 'approve')">✅ Potwierdź odbiór Towaru</button>
                                <button class="btn btn-danger" onclick="handleAction(<?= (int)$doc['id'] ?>, 'reject')">❌ Odrzuć</button>
                            </div>
                        <?php elseif ($status === 'waiting_manager'): ?>
                            <div class="notes">
                                ⏳ Dokument został potwierdzony i czeka na akceptację kierownika.
                            </div>
                        <?php elseif ($status === 'approved'): ?>
                            <div class="notes">
                                ✅ Dokument został zatwierdzony.
                            </div>
                        <?php elseif ($status === 'rejected'): ?>
                            <div class="notes">
                                ❌ Dokument został odrzucony.
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <script>
        <?php if ($canScanWz): ?>
        const wzFormOperator = document.getElementById('wzFormOperator');
        const opResult = document.getElementById('opResult');
        const opSourceSelect = document.getElementById('opSourceSelect');
        const opSiteSelect = document.getElementById('opSiteSelect');
        const opOperatorSelect = document.getElementById('opOperatorSelect');

        // Przechowaj wszystkie opcje operatorów
        const allOperatorOptions = opOperatorSelect ? Array.from(opOperatorSelect.options).slice(1) : [];

        // Filtrowanie operatorów na podstawie wybranej budowy
        if (opSiteSelect && opOperatorSelect) {
            opSiteSelect.addEventListener('change', function() {
                const selectedSiteId = this.value;
                
                // Usuń wszystkie opcje oprócz pierwszej (placeholder)
                while (opOperatorSelect.options.length > 1) {
                    opOperatorSelect.remove(1);
                }
                
                if (!selectedSiteId) {
                    // Jeśli nie wybrano budowy, pokazuj wszystkich operatorów
                    allOperatorOptions.forEach(opt => {
                        opOperatorSelect.appendChild(opt.cloneNode(true));
                    });
                } else {
                    // Filtruj operatorów pracujących na wybranej budowie
                    const filteredOptions = allOperatorOptions.filter(opt => {
                        return opt.getAttribute('data-site-id') === selectedSiteId;
                    });
                    
                    if (filteredOptions.length > 0) {
                        filteredOptions.forEach(opt => {
                            opOperatorSelect.appendChild(opt.cloneNode(true));
                        });
                    } else {
                        // Jeśli brak operatorów na tej budowie, pokaż komunikat
                        const noOperatorOption = document.createElement('option');
                        noOperatorOption.value = '';
                        noOperatorOption.textContent = '— Brak operatorów na tej budowie —';
                        noOperatorOption.disabled = true;
                        opOperatorSelect.appendChild(noOperatorOption);
                    }
                }
            });
        }

        // Dwupoziomowy wybór materiału: grupa → konkretny materiał
        const opMaterialGroup = document.getElementById('opMaterialGroup');
        const opMaterialType = document.getElementById('opMaterialType');

        // Dane materiałów z PHP (zamiast pobierania przez API)
        const materialsByGroup = <?= json_encode($materialsByGroup, JSON_UNESCAPED_UNICODE) ?>;

        // Event listener dla wyboru grupy
        if (opMaterialGroup && opMaterialType) {
            opMaterialGroup.addEventListener('change', function() {
                const selectedGroupId = this.value;
                
                // Usuń wszystkie opcje oprócz pierwszej
                while (opMaterialType.options.length > 1) {
                    opMaterialType.remove(1);
                }
                
                if (!selectedGroupId) {
                    // Brak wybranej grupy - wyłącz drugi dropdown
                    opMaterialType.disabled = true;
                    opMaterialType.options[0].textContent = '— Najpierw wybierz grupę materiału —';
                } else {
                    // Wypełnij drugi dropdown materiałami z wybranej grupy
                    opMaterialType.disabled = false;
                    opMaterialType.options[0].textContent = '— Wybierz rodzaj materiału —';
                    
                    const materials = materialsByGroup[selectedGroupId] || [];
                    materials.forEach(material => {
                        const option = document.createElement('option');
                        option.value = material.name;
                        option.textContent = material.name;
                        opMaterialType.appendChild(option);
                    });
                }
            });
        }

        if (wzFormOperator) {
            wzFormOperator.addEventListener('submit', async function(e) {
                e.preventDefault();

                const formData = new FormData(wzFormOperator);
                const sourceValue = (formData.get('material_source') || '').toString();
                const qtyRaw = (formData.get('material_quantity') || '').toString().trim();
                const qtyNum = parseFloat(qtyRaw.replace(',', '.'));

                // Walidacja źródła / destynacji materiału
                if (!sourceValue) {
                    opResult.className = 'result-op error';
                    opResult.textContent = '✗ Wybierz skąd ładowane / dokąd transportowane';
                    opResult.style.display = 'block';
                    return;
                }

                // Zbuduj opis źródła i dopisz do notatek
                let sourceLabel = '';
                if (sourceValue === 'site') {
                    const selectedIndex = opSiteSelect ? opSiteSelect.selectedIndex : -1;
                    const siteText = (selectedIndex >= 0 && opSiteSelect)
                        ? opSiteSelect.options[selectedIndex].text
                        : '';
                    sourceLabel = siteText ? 'Budowa: ' + siteText : 'Budowa';
                } else if (sourceValue === 'baza_horodyszcze') {
                    sourceLabel = 'Baza magazynowa Horodyszcze';
                } else if (sourceValue === 'baza_pawlow') {
                    sourceLabel = 'Baza magazynowa Pawłów';
                } else if (sourceValue === 'baza_towarowa') {
                    sourceLabel = 'Baza magazynowa Towarowa';
                }

                if (sourceLabel) {
                    const existingNotes = (formData.get('notes') || '').toString().trim();
                    const prefix = '[ŹRÓDŁO: ' + sourceLabel + ']';
                    const combined = existingNotes
                        ? prefix + ' ' + existingNotes
                        : prefix;
                    formData.set('notes', combined);
                }

                if (!qtyRaw || isNaN(qtyNum) || qtyNum <= 0) {
                    opResult.className = 'result-op error';
                    opResult.textContent = '✗ Podaj prawidłową, dodatnią ilość materiału';
                    opResult.style.display = 'block';
                    return;
                }

                opResult.className = 'result-op';
                opResult.textContent = '⏳ Zapisywanie...';
                opResult.style.display = 'block';

                try {
                    const response = await fetch('save_wz.php', {
                        method: 'POST',
                        body: formData
                    });

                    if (!response.ok) {
                        const data = await response.json();
                        opResult.className = 'result-op error';
                        opResult.textContent = `✗ ${data.message || 'Błąd serwera'}`;
                        return;
                    }

                    const data = await response.json();

                    if (data.success) {
                        opResult.className = 'result-op success';
                        opResult.textContent = `✓ ${data.message} (${data.document_number})`;

                        setTimeout(() => {
                            wzFormOperator.reset();
                            opResult.style.display = 'none';
                        }, 2000);
                    } else {
                        opResult.className = 'result-op error';
                        opResult.textContent = `✗ ${data.message}`;
                    }
                } catch (error) {
                    console.error('Błąd:', error);
                    opResult.className = 'result-op error';
                    opResult.textContent = '✗ Błąd połączenia z serwerem: ' + error.message;
                }
            });
        }
        <?php endif; ?>

        async function handleAction(id, action) {
            if (action === 'reject') {
                const reason = prompt('Podaj powód odrzucenia (wymagane):');
                
                if (reason === null) {
                    return; // Anulowano
                }
                
                const trimmedReason = (reason || '').trim();
                if (!trimmedReason) {
                    alert('❌ Powód odrzucenia jest wymagany!');
                    return;
                }
                
                await sendAction(id, action, trimmedReason);
            } else {
                if (!confirm('Potwierdzić odbiór Towaru z tego dokumentu WZ?')) return;
                await sendAction(id, action, '');
            }
        }

        async function sendAction(id, action, notes) {
            try {
                const res = await fetch('operator_action.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id, action, notes })
                });
                
                if (!res.ok) {
                    const data = await res.json();
                    alert(data.message || 'Wystąpił błąd');
                    return;
                }
                
                const data = await res.json();
                if (data.success) {
                    alert(data.message || 'Operacja zakończona sukcesem');
                    location.reload();
                } else {
                    alert(data.message || 'Wystąpił błąd');
                }
            } catch (e) {
                console.error('Błąd:', e);
                alert('Błąd połączenia z serwerem: ' + e.message);
            }
        }
    </script>
</body>
</html>
