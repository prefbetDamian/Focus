<?php
/**
 * Podgląd dokumentu WZ
 */

require_once __DIR__.'/../../core/session.php';
require_once __DIR__.'/../../core/auth.php';

$manager = requireManager(2);
$pdo = require __DIR__.'/../../core/db.php';

$id = intval($_GET['id'] ?? 0);

if (!$id) {
    die('Brak ID dokumentu');
}

$stmt = $pdo->prepare("
    SELECT 
        w.*,
        s.name AS site_name,
        COALESCE(
            CONCAT(e.first_name, ' ', e.last_name),
            CONCAT(m.first_name, ' ', m.last_name)
        ) AS creator_name
    FROM wz_scans w
    LEFT JOIN sites s ON w.site_id = s.id
    LEFT JOIN employees e ON w.employee_id = e.id
    LEFT JOIN managers m ON w.manager_id = m.id
    WHERE w.id = ?
");
$stmt->execute([$id]);
$doc = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$doc) {
    die('Dokument nie istnieje');
}

$uploadUrl = '../../uploads/wz/';
?>
<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Podgląd WZ - <?= htmlspecialchars($doc['document_number']) ?></title>

    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            border-radius: 20px;
            padding: 40px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        }
        h1 {
            color: #333;
            margin-bottom: 30px;
            text-align: center;
        }
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .info-item {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 10px;
        }
        .info-item label {
            display: block;
            color: #666;
            font-size: 12px;
            text-transform: uppercase;
            margin-bottom: 5px;
        }
        .info-item .value {
            color: #333;
            font-size: 16px;
            font-weight: 600;
        }
        .files-section {
            margin-top: 30px;
        }
        .files-section h3 {
            margin-bottom: 15px;
            color: #444;
        }
        .file-preview {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            text-align: center;
        }
        .file-preview img {
            max-width: 100%;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            transition: all 0.3s;
        }
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        .btn-secondary:hover {
            background: #5a6268;
        }
        .status-badge {
            padding: 6px 16px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 600;
            text-transform: uppercase;
        }
        .status-draft { background: #ffc107; color: #333; }
        .status-approved { background: #28a745; color: white; }
        .status-rejected { background: #dc3545; color: white; }
        .status-waiting_operator { background: #17a2b8; color: #fff; }
        .status-waiting_manager { background: #007bff; color: #fff; }
    </style>
</head>
<body>
    <div class="container">
        <h1>📄 Dokument WZ - <?= htmlspecialchars($doc['document_number']) ?></h1>
        
        <div class="info-grid">
            <div class="info-item">
                <label>Numer dokumentu</label>
                <div class="value"><?= htmlspecialchars($doc['document_number']) ?></div>
            </div>
            <div class="info-item">
                <label>Budowa</label>
                <div class="value"><?= htmlspecialchars($doc['site_name'] ?? 'Brak') ?></div>
            </div>
            <div class="info-item">
                <label>Status</label>
                <div class="value">
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
                </div>
            </div>
            <div class="info-item">
                <label>Autor</label>
                <div class="value"><?= htmlspecialchars($doc['creator_name'] ?? 'Nieznany') ?></div>
            </div>
            <div class="info-item">
                <label>Data utworzenia</label>
                <div class="value"><?= date('d.m.Y H:i', strtotime($doc['created_at'])) ?></div>
            </div>
            <?php if ($doc['notes']): ?>
                <div class="info-item" style="grid-column: 1 / -1;">
                    <label>Uwagi</label>
                    <div class="value"><?= nl2br(htmlspecialchars($doc['notes'])) ?></div>
                </div>
            <?php endif; ?>
        </div>
        
        <div class="files-section">
            <?php if ($doc['scan_file']): ?>
                <h3>📄 Skan dokumentu</h3>
                <div class="file-preview">
                    <?php
                    $scanPath = $uploadUrl . $doc['scan_file'];
                    $ext = strtolower(pathinfo($doc['scan_file'], PATHINFO_EXTENSION));
                    ?>
                    <?php if (in_array($ext, ['jpg', 'jpeg', 'png'])): ?>
                        <img src="<?= $scanPath ?>" alt="Skan dokumentu">
                    <?php elseif ($ext === 'pdf'): ?>
                        <iframe src="<?= $scanPath ?>" width="100%" height="600px" style="border: none; border-radius: 8px;"></iframe>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            
            <?php if ($doc['signature_file']): ?>
                <h3>✍️ Podpis cyfrowy</h3>
                <div class="file-preview">
                    <img src="<?= $uploadUrl . $doc['signature_file'] ?>" alt="Podpis" style="max-width: 400px; background: white;">
                </div>
            <?php endif; ?>
            
            <?php if ($doc['pdf_file']): ?>
                <h3>📑 Wygenerowany PDF</h3>
                <div class="file-preview">
                    <iframe src="<?= $uploadUrl . $doc['pdf_file'] ?>" width="100%" height="600px" style="border: none; border-radius: 8px;"></iframe>
                </div>
            <?php endif; ?>
        </div>
        
        <div style="text-align: center; margin-top: 30px;">
            <button onclick="window.close()" class="btn btn-secondary">✖️ Zamknij</button>
        </div>
    </div>
</body>
</html>
