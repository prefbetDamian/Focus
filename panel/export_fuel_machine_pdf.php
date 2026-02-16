<?php
require_once __DIR__ . '/../core/auth.php';

// Sprawdzenie uprawnień managera (rola 2+)
requireManager(2);

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);


require(__DIR__ . '/../lib/fpdf.php');

$config = require __DIR__.'/../config.php';

/* ===== POLSKIE ZNAKI ===== */
function pl($text) {
    return iconv('UTF-8', 'ISO-8859-2//TRANSLIT', $text);
}

/* ===== BAZA ===== */
$pdo = new PDO(
    "mysql:host={$config['db_host']};port={$config['db_port']};dbname={$config['db_name']};charset=utf8mb4",
    $config['db_user'],
    $config['db_pass'],
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

/* ===== PARAMETRY ===== */
$machineId = (int)($_GET['machine_id'] ?? 0);
$monthVal  = $_GET['month'] ?? '';

if (!$machineId || !$monthVal) {
    die('Brak danych do raportu tankowania');
}

[$year, $month] = explode('-', $monthVal);

/* ===== POBIERZ PEŁNĄ NAZWĘ MASZYNY ===== */
$stmtMachine = $pdo->prepare("SELECT machine_name FROM machines WHERE id = ?");
$stmtMachine->execute([$machineId]);
$machine = $stmtMachine->fetch(PDO::FETCH_ASSOC);
$machineName = $machine['machine_name'] ?? 'Nieznana maszyna';

/* ===== PDF KLASA ===== */
class PDF extends FPDF {

    /* KOLEJNOŚĆ KOLUMN = LOGIKA RAPORTU */
    public array $colW = [
        'Dt' => 34, // Data
        'O' => 55, // Operator (odbiorca)
        'T' => 55, // Tankujący
        'L' => 20, // Litry
        'H' => 20, // m-h
        'R' => 25, // Δ m-h
        'A' => 24, // Śr. l/m-h
        'S' => 25  // Status
    ];

    public int $rowUnit = 6;

    function Header() {
        if (file_exists(__DIR__ . '/Logo.png')) {
            $this->Image(__DIR__ . '/Logo.png', 245, 5, 45);
        }

        $this->SetFont('DejaVu','B',14);
        $this->Cell(0,10,pl('Raport Tankowania Maszyny'),0,1,'C');
        $this->Ln(2);
    }

    function Footer() {
        $this->SetY(-15);
        $this->SetFont('DejaVu','',8);
        $this->Cell(0,10,pl('Wygenerowano: ') . date('Y-m-d H:i'),0,0,'C');
    }

    function TableHeader() {
        $this->SetFont('DejaVu','B',9);
        $this->Cell($this->colW['Dt'],8,pl('Data'),1);
        $this->Cell($this->colW['O'],8,pl('Operator'),1);
        $this->Cell($this->colW['T'],8,pl('Tankujący'),1);
        $this->Cell($this->colW['L'],8,pl('Litry'),1,0,'C');
        $this->Cell($this->colW['H'],8,pl('m-h'),1,0,'C');
        $this->Cell($this->colW['R'],8,pl('Różnica m-h'),1,0,'C');
        $this->Cell($this->colW['A'],8,pl('Śr. l/m-h'),1,0,'C');
        $this->Cell($this->colW['S'],8,pl('Status'),1,0,'C');
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

/* ===== FUNKCJA DLA PUSTEGO RAPORTU ===== */
function generateEmptyPDF($machineName, $month, $year) {
    // Używamy tej samej klasy PDF z obsługą polskich znaków
    $pdf = new PDF('L');
    $pdf->AddFont('DejaVu','', 'DejaVuSans.php');
    $pdf->AddFont('DejaVu','B','DejaVuSans-Bold.php');
    
    // Nadpisujemy Header i Footer dla uproszczonego raportu
    $pdf->AddPage();
    
    $pdf->SetFont('DejaVu', '', 12);
    $pdf->Cell(0, 10, pl("Maszyna: $machineName"), 0, 1);
    $pdf->Cell(0, 10, pl("Okres: $month/$year"), 0, 1);
    $pdf->Ln(10);
    
    $pdf->SetFont('DejaVu', 'B', 14);
    $pdf->Cell(0, 10, pl('Brak danych o tankowaniu w tym okresie'), 0, 1, 'C');
    $pdf->Ln(5);
    
    $pdf->SetFont('DejaVu', '', 10);
    $pdf->MultiCell(0, 6, pl("W tym miesiącu nie zostały wprowadzone żadne wpisy tankowania dla tej maszyny.\n\nAby dodać wpisy tankowania, należy uzupełnić tabelę fuel_logs w bazie danych."));
    
    $filename = preg_replace('/[^A-Za-z0-9._-]/', '_', iconv('UTF-8', 'ASCII//TRANSLIT', "Raport_tankowania_{$machineName}_{$month}_{$year}_brak_danych.pdf"));
    $pdf->Output('D', $filename);
}

/* ===== DANE ===== */
$stmt = $pdo->prepare("
    SELECT
        f.created_at,
        f.machine_name,
        f.liters,
        f.meter_mh,
        f.delta_mh,
        f.avg_l_per_mh,
        f.anomaly_score,
        CONCAT(e1.first_name,' ',e1.last_name) AS supplier,
        CONCAT(e2.first_name,' ',e2.last_name) AS receiver
    FROM fuel_logs f
    LEFT JOIN employees e1 ON e1.id = f.supplier_id
    LEFT JOIN employees e2 ON e2.id = f.receiver_id
    WHERE f.machine_id = ?
      AND MONTH(f.created_at) = ?
      AND YEAR(f.created_at) = ?
    ORDER BY f.created_at
");

$stmt->execute([$machineId, $month, $year]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (!$rows || count($rows) === 0) {
    // Generuj PDF z informacją o braku danych (nazwa maszyny już pobrana wyżej)
    generateEmptyPDF($machineName, $month, $year);
    exit;
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

/* ===== WIERSZE ===== */
foreach ($rows as $r) {

    $pdf->CheckPageBreak($pdf->rowUnit);

    $pdf->Cell(
        $pdf->colW['Dt'],
        $pdf->rowUnit,
        date('d.m.y H:i', strtotime($r['created_at'])),
        1
    );

    $pdf->Cell(
        $pdf->colW['O'],
        $pdf->rowUnit,
        pl($r['receiver'] ?: '—'),
        1
    );

    $pdf->Cell(
        $pdf->colW['T'],
        $pdf->rowUnit,
        pl($r['supplier'] ?: '—'),
        1
    );

    $pdf->Cell(
        $pdf->colW['L'],
        $pdf->rowUnit,
        number_format($r['liters'],1),
        1,
        0,
        'R'
    );

    $pdf->Cell(
        $pdf->colW['H'],
        $pdf->rowUnit,
        number_format($r['meter_mh'],2),
        1,
        0,
        'R'
    );

    $pdf->Cell(
        $pdf->colW['R'],
        $pdf->rowUnit,
        $r['delta_mh'] !== null ? number_format($r['delta_mh'],2) : '-',
        1,
        0,
        'R'
    );

    $pdf->Cell(
        $pdf->colW['A'],
        $pdf->rowUnit,
        $r['avg_l_per_mh'] ? number_format($r['avg_l_per_mh'],2) : '-',
        1,
        0,
        'R'
    );

    $pdf->Cell(
    $pdf->colW['S'],
    $pdf->rowUnit,
    ((int)($r['anomaly_score'] ?? 0) >= 60) ? pl('UWAGA') : 'OK',
    1,
    0,
    'C'
);


    $pdf->Ln();
}

/* ===== OUTPUT ===== */
$filename = preg_replace(
    '/[^A-Za-z0-9._-]/',
    '_',
    iconv(
        'UTF-8',
        'ASCII//TRANSLIT',
        "Raport_tankowania_{$machineName}_{$month}_{$year}.pdf"
    )
);

$pdf->Output('D', $filename);
exit;
