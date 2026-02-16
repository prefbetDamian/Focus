<?php
/**
 * Archiwum dokumentów WZ
 * Lista wszystkich dokumentów z możliwością filtrowania
 */

require_once __DIR__.'/../../core/session.php';
require_once __DIR__.'/../../core/auth.php';

// Wymagane konto managera (księgowy lub admin)
$manager = requireManager(2);

$pdo = require __DIR__.'/../../core/db.php';

// Filtry
$filterSite = $_GET['site'] ?? '';
$filterStatus = $_GET['status'] ?? '';
$filterMonth = $_GET['month'] ?? '';

// Budowa zapytania
$sql = "SELECT 
            w.id,
            w.document_number,
            w.material_type,
            w.material_quantity,
            w.scan_file,
            w.signature_file,
            w.pdf_file,
            w.status,
            w.notes,
            w.created_at,
            w.updated_at,
            s.name AS site_name,
            COALESCE(
                CONCAT(e.first_name, ' ', e.last_name),
                CONCAT(m.first_name, ' ', m.last_name)
            ) AS creator_name
        FROM wz_scans w
        LEFT JOIN sites s ON w.site_id = s.id
        LEFT JOIN employees e ON w.employee_id = e.id
        LEFT JOIN managers m ON w.manager_id = m.id
        WHERE 1=1";

$params = [];

if ($filterSite) {
    $sql .= " AND w.site_id = ?";
    $params[] = $filterSite;
}

if ($filterStatus) {
    $sql .= " AND w.status = ?";
    $params[] = $filterStatus;
}

if ($filterMonth) {
    $sql .= " AND DATE_FORMAT(w.created_at, '%Y-%m') = ?";
    $params[] = $filterMonth;
}

$sql .= " ORDER BY w.created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$documents = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Lista budów dla filtra
$sites = $pdo->query("SELECT id, name FROM sites WHERE active = 1 AND name NOT IN ('URLOP', 'L4') ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

// Pobierz nazwę wybranej budowy, jeśli jest filtr
$filterSiteName = '';
if ($filterSite) {
    foreach ($sites as $site) {
        if ($site['id'] == $filterSite) {
            $filterSiteName = $site['name'];
            break;
        }
    }
}

// Statystyki materiałów na budowach (tylko zatwierdzone dokumenty)
$statsQuery = "
    SELECT 
        s.name AS site_name,
        w.material_type,
        SUM(CAST(w.material_quantity AS DECIMAL(10,2))) AS total_quantity
    FROM wz_scans w
    LEFT JOIN sites s ON w.site_id = s.id
    WHERE w.status = 'approved'";

// Jeśli wybrano filtr budowy, zastosuj go również do statystyk
if ($filterSite) {
    $statsQuery .= " AND w.site_id = " . (int)$filterSite;
}

$statsQuery .= "
    GROUP BY s.name, w.material_type
    ORDER BY s.name, w.material_type
";
$statsStmt = $pdo->query($statsQuery);
$materialStats = $statsStmt->fetchAll(PDO::FETCH_ASSOC);

// Grupuj statystyki po budowach
$statsBySite = [];
foreach ($materialStats as $stat) {
    $siteName = $stat['site_name'] ?? 'Nieznana budowa';
    if (!isset($statsBySite[$siteName])) {
        $statsBySite[$siteName] = [];
    }
    $statsBySite[$siteName][] = $stat;
}
?>
<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Archiwum WZ</title>

    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        .container {
            max-width: 1400px;
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
        
        .filter-section {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 15px;
            margin-bottom: 30px;
        }
        .filter-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            align-items: end;
        }
        label {
            display: block;
            margin-bottom: 5px;
            color: #555;
            font-weight: 500;
            font-size: 14px;
        }
        select,
        input[type="month"] {
            width: 100%;
            padding: 10px;
            border: 2px solid #ddd;
            border-radius: 8px;
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
        .btn-secondary:hover {
            background: #5a6268;
        }
        .btn-success {
            background: #28a745;
            color: white;
        }
        .btn-success:hover {
            background: #218838;
        }
        .btn-danger {
            background: #dc3545;
            color: white;
            font-size: 12px;
            padding: 6px 12px;
        }
        .btn-danger:hover {
            background: #c82333;
        }
        .btn-info {
            background: #17a2b8;
            color: white;
            font-size: 12px;
            padding: 6px 12px;
        }
        .btn-info:hover {
            background: #138496;
        }
        
        .top-buttons {
            display: flex;
            gap: 15px;
            justify-content: center;
            margin-bottom: 30px;
            flex-wrap: wrap;
        }
        
        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin-bottom: 30px;
        }
        .stat-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 15px;
            text-align: center;
        }
        .stat-card .number {
            font-size: 32px;
            font-weight: bold;
            margin-bottom: 5px;
        }
        .stat-card .label {
            font-size: 14px;
            opacity: 0.9;
        }
        
        .table-container {
            overflow-x: auto;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            background: white;
            border-radius: 10px;
            overflow: hidden;
        }
        thead {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        th, td {
            padding: 12px;
            text-align: left;
            font-size: 14px;
        }
        th {
            font-weight: 600;
            text-transform: uppercase;
            font-size: 12px;
            letter-spacing: 0.5px;
        }
        tbody tr {
            border-bottom: 1px solid #eee;
            transition: background 0.2s;
        }
        tbody tr:hover {
            background: #f8f9fa;
        }
        
        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
        }
        .status-draft {
            background: #ffc107;
            color: #333;
        }
        .status-approved {
            background: #28a745;
            color: white;
        }
        .status-rejected {
            background: #dc3545;
            color: white;
        }
        .status-waiting_operator {
            background: #17a2b8;
            color: white;
        }
        .status-waiting_manager {
            background: #007bff;
            color: white;
        }
        
        .action-buttons {
            display: flex;
            gap: 5px;
            flex-wrap: wrap;
        }
        
        .no-data {
            text-align: center;
            padding: 60px 20px;
            color: #666;
            font-size: 16px;
        }
        
        .alert {
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            font-weight: 500;
        }
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 2px solid #c3e6cb;
        }
        
        .material-stats {
            margin: 30px 0;
            background: rgba(102, 126, 234, 0.05);
            border-radius: 15px;
            padding: 25px;
            border: 2px solid rgba(102, 126, 234, 0.1);
        }
        .material-stats h2 {
            color: #667eea;
            margin-bottom: 20px;
            font-size: 24px;
            user-select: none;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 15px;
        }
        .material-stats h2:hover {
            color: #5568d3;
        }
        .stats-header-left {
            cursor: pointer;
            flex: 1;
        }
        .stats-header-right {
            display: flex;
            gap: 10px;
            align-items: center;
        }
        .btn-pdf {
            background: #28a745;
            color: white;
            padding: 8px 16px;
            font-size: 14px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            transition: all 0.3s;
            white-space: nowrap;
        }
        .btn-pdf:hover {
            background: #218838;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(40, 167, 69, 0.4);
        }
        .toggle-icon {
            font-size: 20px;
            transition: transform 0.3s;
        }
        .toggle-icon.collapsed {
            transform: rotate(-90deg);
        }
        .site-stats {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 15px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        .site-stats h3 {
            color: #333;
            margin-bottom: 15px;
            font-size: 18px;
            padding-bottom: 10px;
            border-bottom: 2px solid #667eea;
        }
        .material-item {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid #eee;
        }
        .material-item:last-child {
            border-bottom: none;
        }
        .material-name {
            font-weight: 600;
            color: #555;
        }
        .material-quantity {
            font-weight: 700;
            color: #667eea;
            font-size: 16px;
        }
        .no-stats {
            text-align: center;
            padding: 20px;
            color: #999;
            font-style: italic;
        }
        
        @media (max-width: 768px) {
            .container {
                padding: 20px;
            }
            h1 {
                font-size: 24px;
            }
            .material-stats h2 {
                flex-direction: column;
                align-items: flex-start;
            }
            .stats-header-right {
                width: 100%;
                justify-content: space-between;
            }
            .btn-pdf {
                font-size: 12px;
                padding: 6px 12px;
            }
            .table-container {
                font-size: 12px;
            }
            th, td {
                padding: 8px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>📂 Archiwum WZ</h1>
        <div class="subtitle">Wszystkie dokumenty wydania zewnętrznego</div>
        
        <?php if (isset($_GET['generated'])): ?>
            <div class="alert alert-success">
                ✅ PDF został wygenerowany pomyślnie i dokument został zatwierdzony!
            </div>
        <?php endif; ?>
        
        <div class="top-buttons">
            <a href="scan.php" class="btn btn-success">➕ Nowy dokument WZ</a>
            <a href="#" onclick="goBackToEntry('../../panel/dashboard.php'); return false;" class="btn btn-secondary">⬅️ Powrót do panelu</a>
        </div>
        
        <!-- Statystyki -->
        <div class="stats">
            <div class="stat-card">
                <div class="number"><?= count($documents) ?></div>
                <div class="label">Wszystkie dokumenty</div>
            </div>
            <div class="stat-card">
                <div class="number"><?= count(array_filter($documents, fn($d) => $d['status'] === 'waiting_operator')) ?></div>
                <div class="label">Do potwierdzenia (kierowca)</div>
            </div>
            <div class="stat-card">
                <div class="number"><?= count(array_filter($documents, fn($d) => $d['status'] === 'waiting_manager')) ?></div>
                <div class="label">Do akceptacji (kierownik)</div>
            </div>
            <div class="stat-card">
                <div class="number"><?= count(array_filter($documents, fn($d) => $d['status'] === 'approved')) ?></div>
                <div class="label">Zatwierdzone</div>
            </div>
            <div class="stat-card">
                <div class="number"><?= count(array_filter($documents, fn($d) => $d['status'] === 'rejected')) ?></div>
                <div class="label">Odrzucone</div>
            </div>
        </div>
        
        <!-- Filtry -->
        <form method="GET" class="filter-section">
            <div class="filter-row">
                <div>
                    <label>Budowa</label>
                    <select name="site">
                        <option value="">— Wszystkie —</option>
                        <?php foreach ($sites as $site): ?>
                            <option value="<?= $site['id'] ?>" <?= $filterSite == $site['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($site['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label>Status</label>
                    <select name="status">
                        <option value="">— Wszystkie —</option>
                        <option value="waiting_operator" <?= $filterStatus === 'waiting_operator' ? 'selected' : '' ?>>Do potwierdzenia (kierowca)</option>
                        <option value="waiting_manager" <?= $filterStatus === 'waiting_manager' ? 'selected' : '' ?>>Do akceptacji (kierownik)</option>
                        <option value="approved" <?= $filterStatus === 'approved' ? 'selected' : '' ?>>Zatwierdzone</option>
                        <option value="rejected" <?= $filterStatus === 'rejected' ? 'selected' : '' ?>>Odrzucone</option>
                    </select>
                </div>
                <div>
                    <label>Miesiąc</label>
                    <input type="month" name="month" value="<?= htmlspecialchars($filterMonth) ?>">
                </div>
                <div>
                    <button type="submit" class="btn btn-primary">🔍 Filtruj</button>
                    <a href="list_wz.php" class="btn btn-secondary">✖️ Wyczyść</a>
                </div>
            </div>
        </form>
        
        <!-- Statystyki materiałów na budowach -->
        <?php if (!empty($statsBySite)): ?>
        <div class="material-stats">
            <h2>
                <span class="stats-header-left" onclick="toggleStats()">📊 Statystyki materiałów wbudowanych na budowach<?= $filterSiteName ? ' - ' . htmlspecialchars($filterSiteName) : '' ?></span>
                <div class="stats-header-right">
                    <a href="generate_materials_report.php<?= $filterSite ? '?site=' . (int)$filterSite : '' ?>" class="btn-pdf">📄 Generuj raport PDF</a>
                    <span class="toggle-icon" id="statsToggle" onclick="toggleStats()" style="cursor: pointer;">▼</span>
                </div>
            </h2>
            <div class="stats-content" id="statsContent">
                <div style="font-size: 13px; color: #666; margin-bottom: 15px;">
                    ℹ️ Zestawienie uwzględnia tylko zatwierdzone dokumenty WZ<?= $filterSiteName ? ' dla budowy: ' . htmlspecialchars($filterSiteName) : '' ?>
                </div>
                
                <?php foreach ($statsBySite as $siteName => $materials): ?>
                    <div class="site-stats">
                        <h3>🏗️ <?= htmlspecialchars($siteName) ?></h3>
                        <?php foreach ($materials as $material): ?>
                            <div class="material-item">
                                <span class="material-name"><?= htmlspecialchars($material['material_type']) ?></span>
                                <span class="material-quantity"><?= number_format($material['total_quantity'], 2, ',', ' ') ?> jednostek</span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php else: ?>
        <div class="material-stats">
            <h2>📊 Statystyki materiałów wbudowanych na budowach</h2>
            <div class="no-stats">Brak zatwierdzonych dokumentów WZ z materiałami</div>
        </div>
        <?php endif; ?>
        
        <!-- Tabela dokumentów -->
        <div class="table-container">
            <?php if (empty($documents)): ?>
                <div class="no-data">
                    📭 Brak dokumentów WZ.<br>
                    <a href="scan.php" class="btn btn-primary" style="margin-top: 20px;">Dodaj pierwszy dokument</a>
                </div>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>Numer WZ</th>
                            <th>Budowa</th>
                            <th>Materiał</th>
                            <th>Ilość</th>
                            <th>Status</th>
                            <th>Autor</th>
                            <th>Data utworzenia</th>
                            <th>Pliki</th>
                            <th>Akcje</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($documents as $doc): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($doc['document_number']) ?></strong></td>
                                <td><?= htmlspecialchars($doc['site_name'] ?? 'Brak') ?></td>
                                <td><strong><?= htmlspecialchars($doc['material_type'] ?? '-') ?></strong></td>
                                <td><?= $doc['material_quantity'] !== null ? number_format((float)$doc['material_quantity'], 2, ',', ' ') : '-' ?></td>
                                <td>
                                    <?php
                                    $statusLabels = [
                                        'draft' => 'Wersja robocza',
                                        'waiting_operator' => 'Do potwierdzenia (kierowca)',
                                        'waiting_manager' => 'Do akceptacji (kierownik)',
                                        'approved' => 'Zatwierdzone',
                                        'rejected' => 'Odrzucone'
                                    ];
                                    $statusClass = 'status-' . $doc['status'];
                                    $statusLabel = $statusLabels[$doc['status']] ?? $doc['status'];
                                    ?>
                                    <span class="status-badge <?= $statusClass ?>"><?= $statusLabel ?></span>
                                </td>
                                <td><?= htmlspecialchars($doc['creator_name'] ?? 'Nieznany') ?></td>
                                <td><?= date('d.m.Y H:i', strtotime($doc['created_at'])) ?></td>
                                <td>
                                    <?php if ($doc['scan_file']): ?>
                                        📄 Skan
                                    <?php endif; ?>
                                    <?php if ($doc['signature_file']): ?>
                                        ✍️ Podpis
                                    <?php endif; ?>
                                    <?php if ($doc['pdf_file']): ?>
                                        📑 PDF
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <button class="btn btn-info" onclick="viewDocument(<?= $doc['id'] ?>)">👁️ Podgląd</button>
                                        <?php if (!$doc['pdf_file'] && in_array($doc['status'], ['waiting_manager', 'draft'], true)): ?>
                                            <button class="btn btn-success" onclick="generatePDF(<?= $doc['id'] ?>)">📑 Akceptacja WZ</button>
                                        <?php endif; ?>
                                        <?php if ($doc['status'] === 'waiting_manager'): ?>
                                            <button class="btn btn-danger" onclick="rejectDocument(<?= $doc['id'] ?>, '<?= htmlspecialchars($doc['document_number']) ?>')">❌ Odrzuć</button>
                                        <?php endif; ?>
                                        <button class="btn btn-danger" data-roles="9" onclick="deleteDocument(<?= $doc['id'] ?>, '<?= htmlspecialchars($doc['document_number']) ?>')">🗑️ Usuń</button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
    
    <script>
        window.USER_ROLE = <?= (int)($manager['role_level'] ?? 0) ?>;

        function goBackToEntry(fallbackUrl) {
            // Zawsze wróć do podanego adresu (np. panelu),
            // nie korzystaj z document.referrer, żeby uniknąć pętli
            window.location.href = fallbackUrl;
        }

        function viewDocument(id) {
            window.open(`view_wz.php?id=${id}`, '_blank');
        }
        
        function generatePDF(id) {
            if (confirm('Wygenerować PDF z tego dokumentu?')) {
                window.location.href = `generate_wz_pdf.php?id=${id}`;
            }
        }
        
        async function rejectDocument(id, docNumber) {
            const reason = prompt(`Odrzucanie dokumentu ${docNumber}\n\nPodaj powód odrzucenia (wymagane):`);
            
            if (reason === null) {
                return; // Anulowano
            }
            
            const trimmedReason = (reason || '').trim();
            if (!trimmedReason) {
                alert('❌ Powód odrzucenia jest wymagany!');
                return;
            }
            
            try {
                const response = await fetch('manager_action.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id, action: 'reject', notes: trimmedReason })
                });
                
                if (!response.ok) {
                    const data = await response.json();
                    alert('✗ ' + (data.message || 'Błąd serwera'));
                    return;
                }
                
                const data = await response.json();
                
                if (data.success) {
                    alert('✓ ' + data.message);
                    location.reload();
                } else {
                    alert('✗ ' + data.message);
                }
            } catch (error) {
                console.error('Błąd:', error);
                alert('✗ Błąd połączenia z serwerem: ' + error.message);
            }
        }
        
        async function deleteDocument(id, docNumber) {
            if (!confirm(`Czy na pewno usunąć dokument ${docNumber}?\n\nTa operacja jest nieodwracalna!`)) {
                return;
            }
            
            try {
                const response = await fetch('delete_wz.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id })
                });
                
                if (!response.ok) {
                    const data = await response.json();
                    alert('✗ ' + (data.message || 'Błąd serwera'));
                    return;
                }
                
                const data = await response.json();
                
                if (data.success) {
                    alert('✓ Dokument usunięty');
                    location.reload();
                } else {
                    alert('✗ ' + data.message);
                }
            } catch (error) {
                console.error('Błąd:', error);
                alert('✗ Błąd połączenia z serwerem: ' + error.message);
            }
        }

        function applyRoleVisibility() {
            document.querySelectorAll('[data-roles]').forEach(el => {
                const allowed = el.dataset.roles
                    .split(',')
                    .map(r => parseInt(r.trim(), 10));

                if (!allowed.includes(window.USER_ROLE)) {
                    el.style.display = 'none';
                } else {
                    el.style.display = '';
                }
            });
        }
        
        function toggleStats() {
            const content = document.getElementById('statsContent');
            const icon = document.getElementById('statsToggle');
            
            if (content.style.display === 'none') {
                content.style.display = 'block';
                icon.classList.remove('collapsed');
            } else {
                content.style.display = 'none';
                icon.classList.add('collapsed');
            }
        }

        // Zastosuj widoczność po załadowaniu strony
        document.addEventListener('DOMContentLoaded', applyRoleVisibility);
    </script>
</body>
</html>
