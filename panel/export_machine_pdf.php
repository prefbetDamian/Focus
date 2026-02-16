<?php
require_once __DIR__ . '/../core/auth.php';

// Sprawdzenie uprawnień managera (rola 2+)
requireManager(2);

error_reporting(E_ALL);
ini_set('display_errors', 1);

require(__DIR__ . '/../lib/fpdf.php');

$config = require __DIR__.'/../config.php';

/* ===== POLSKIE ZNAKI ===== */
function pl($text) {
    return iconv('UTF-8', 'ISO-8859-2//TRANSLIT', $text);
}

/* ===== FORMAT HH:MM Z SEKUND ===== */
function secToHM(int $sec): string {
    $h = floor($sec / 3600);
    $m = floor(($sec % 3600) / 60);
    return sprintf('%02d:%02d', $h, $m);
}

/* ===== BAZA ===== */
$pdo = new PDO(
    "mysql:host={$config['db_host']};port={$config['db_port']};dbname={$config['db_name']};charset=utf8mb4",
    $config['db_user'],
    $config['db_pass'],
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

/* ===== PARAMETRY ===== */
$machineId = (int)($_GET['machineId'] ?? 0);
$month     = $_GET['month'] ?? '';
$year      = $_GET['year'] ?? '';

if (!$machineId || !$month || !$year) {
    die('Brak danych do raportu maszyny');
}

/* ===== DANE ===== */
$stmt = $pdo->prepare("
    SELECT
        m.machine_name,
        ws.site_name,
        ws.start_time,
        ws.end_time,
        ws.duration_seconds,
        e.first_name,
        e.last_name
    FROM work_sessions ws
    JOIN machines m       ON m.id = ws.machine_id
    LEFT JOIN employees e ON e.id = ws.employee_id
        WHERE ws.machine_id = ?
            AND MONTH(ws.start_time) = ?
            AND YEAR(ws.start_time) = ?
            AND ws.status IN ('OK','MANUAL')
    ORDER BY ws.start_time
");
$stmt->execute([$machineId, $month, $year]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (!$rows) {
    die('Brak danych do raportu');
}

$machineName = $rows[0]['machine_name'];

/* ===== PDF ===== */
class PDF extends FPDF {

    public array $colW = [
        'B' => 90, // Budowa
        'O' => 40, // Operator
        'S' => 40, // Start
        'E' => 40, // Stop
        'C' => 35  // Czas
    ];

    public int $rowUnit = 6;

    function Header() {
        if (file_exists(__DIR__ . '/Logo.png')) {
            $this->Image(__DIR__ . '/Logo.png', 245, 5, 45);
        }

        $this->SetFont('DejaVu','B',14);
        $this->Cell(0,10,pl('Raport Czasu Pracy Maszyny'),0,1,'C');
        $this->Ln(2);
    }

    function Footer() {
        $this->SetY(-15);
        $this->SetFont('DejaVu','',8);
        $this->Cell(0,10,pl('Wygenerowano: ').date('Y-m-d H:i'),0,0,'C');
    }

    function TableHeader() {
        $this->SetFont('DejaVu','B',10);
        $this->Cell($this->colW['B'],8,pl('Budowa'),1);
        $this->Cell($this->colW['O'],8,pl('Operator'),1);
        $this->Cell($this->colW['S'],8,pl('Start'),1);
        $this->Cell($this->colW['E'],8,pl('Stop'),1);
        $this->Cell($this->colW['C'],8,pl('Czas'),1);
        $this->Ln();
        $this->SetFont('DejaVu','',9);
    }

    function CheckPageBreak($rowHeight) {
        if ($this->GetY() + $rowHeight > $this->PageBreakTrigger) {
            $this->AddPage('L');
            $this->TableHeader();
        }
    }
}

/* ===== START PDF ===== */
$pdf = new PDF('L');
$pdf->AddFont('DejaVu','', 'DejaVuSans.php');
$pdf->AddFont('DejaVu','B','DejaVuSans-Bold.php');
$pdf->AddPage();

/* ===== INFO ===== */
$pdf->SetFont('DejaVu','B',11);
$pdf->Cell(0,7,pl("Maszyna: $machineName"),0,1);
$pdf->SetFont('DejaVu','',10);
$pdf->Cell(0,6,pl("Miesiąc: $month/$year"),0,1);
$pdf->Ln(3);

$pdf->TableHeader();

$sumTotal = 0;
$bySite = [];

/* ===== WIERSZE ===== */
foreach ($rows as $r) {

    $sec = (int)($r['duration_seconds'] ?? 0);
    $sumTotal += $sec;
    $bySite[$r['site_name']] = ($bySite[$r['site_name']] ?? 0) + $sec;

    $operator = trim(($r['first_name'] ?? '') . ' ' . ($r['last_name'] ?? '')) ?: '—';

    $start = $r['start_time']
        ? date('d.m.y H:i', strtotime($r['start_time']))
        : '';

    $end = $r['end_time']
        ? date('d.m.y H:i', strtotime($r['end_time']))
        : '';

    $pdf->CheckPageBreak($pdf->rowUnit);

    $pdf->Cell($pdf->colW['B'],$pdf->rowUnit,pl($r['site_name']),1);
    $pdf->Cell($pdf->colW['O'],$pdf->rowUnit,pl($operator),1);
    $pdf->Cell($pdf->colW['S'],$pdf->rowUnit,$start,1);
    $pdf->Cell($pdf->colW['E'],$pdf->rowUnit,$end,1);
    $pdf->Cell($pdf->colW['C'],$pdf->rowUnit,secToHM($sec),1);
    $pdf->Ln();
}

/* ===== SUMA CZASU MASZYNY (BEZ ZAOKRĄGLEŃ) ===== */
$pdf->Ln(4);
$pdf->SetFont('DejaVu','B',10);
$pdf->Cell(
    $pdf->colW['B'] + $pdf->colW['O'] + $pdf->colW['S'] + $pdf->colW['E'],
    8,
    pl('SUMA CZASU MASZYNY'),
    1
);
$pdf->Cell($pdf->colW['C'],8,secToHM($sumTotal),1);

/* ===== SUMA CZASU NA BUDOWACH (BEZ ZAOKRĄGLEŃ) ===== */
$pdf->Ln(10);
$pdf->SetFont('DejaVu','B',10);
$pdf->Cell(0,8,pl('Suma czasu na budowach'),0,1);

$pdf->SetFont('DejaVu','',9);
foreach ($bySite as $site => $sec) {
    $pdf->Cell(120,7,pl($site),1);
    $pdf->Cell(40,7,secToHM($sec),1);
    $pdf->Ln();
}

/* ===== PODPIS ===== */
$pdf->Ln(12);
$pdf->Cell(190);
$pdf->Cell(80,6,pl('Podpis kierownika:'),0,1);
$pdf->Ln(8);
$pdf->Cell(190);
$pdf->Cell(80,0,'','T');

/* ===== OUTPUT ===== */
$filename = preg_replace(
    '/[^A-Za-z0-9._-]/',
    '_',
    iconv(
        'UTF-8',
        'ASCII//TRANSLIT',
        "Raport_maszyna_{$machineName}_{$month}_{$year}.pdf"
    )
);

$pdf->Output('D', $filename);
exit;
