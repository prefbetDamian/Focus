<?php
require_once __DIR__ . '/../core/auth.php';

// Sprawdzenie uprawnień managera (rola 2+)
requireManager(2);

require(__DIR__ . '/../lib/fpdf.php');

$config = require __DIR__.'/../config.php';

/* ====== POLSKIE ZNAKI ====== */
function pl($text) {
    return iconv('UTF-8', 'ISO-8859-2//TRANSLIT', $text);
}

/* ===== ZAOKRĄGLANIE WG INTERWAŁÓW 20 / 40 ===== */
function roundWorkTime(int $seconds): int {
    if ($seconds <= 0) return 0;

    $hours = floor($seconds / 3600);
    $mins  = floor(($seconds % 3600) / 60);

    if ($mins < 30) return $hours * 3600;
    return ($hours * 3600) + (30 * 60);
}

/* ====== DB ====== */
$pdo = new PDO(
    "mysql:host={$config['db_host']};port={$config['db_port']};dbname={$config['db_name']};charset=utf8mb4",
    $config['db_user'],
    $config['db_pass'],
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

/* ====== PARAMETRY ====== */
$month = $_GET['month'] ?? '';
$year  = $_GET['year'] ?? '';

if (!$month || !$year) {
    die('Brak miesiąca lub roku');
}

/* ====== DANE ====== */
$stmt = $pdo->prepare("
    SELECT first_name, last_name, site_name,
           SEC_TO_TIME(SUM(duration_seconds)) AS time
        FROM work_sessions
        WHERE MONTH(start_time) = ?
            AND YEAR(start_time) = ?
            AND status IN ('OK','MANUAL')
    GROUP BY first_name, last_name, site_name
    ORDER BY last_name, first_name, site_name
");
$stmt->execute([$month, $year]);
$data = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* ====== GRUPOWANIE ====== */
$employees = [];
foreach ($data as $row) {
    $key = $row['first_name'] . ' ' . $row['last_name'];
    $employees[$key][] = $row;
}

/* ====== PDF ====== */
class PDF extends FPDF {

    function Header() {
        if (file_exists(__DIR__ . '/Logo.png')) {
            $this->Image(__DIR__ . '/Logo.png', 230, 5, 50);
        }

        $this->AddFont('DejaVu','B','DejaVuSans-Bold.php');
        $this->SetFont('DejaVu','B',14);
        $this->Cell(0,10, pl('Raport wszystkich pracowników'),0,1,'C');
        $this->Ln(5);
    }

    function Footer() {
        $this->SetY(-15);
        $this->AddFont('DejaVu','', 'DejaVuSans.php');
        $this->SetFont('DejaVu','',8);
        $this->Cell(0,10, pl('Wygenerowano: ') . date('Y-m-d H:i'),0,0,'C');
    }
}

/* ====== START PDF (LANDSCAPE) ====== */
$pdf = new PDF('L');
$pdf->AddPage();

$pdf->AddFont('DejaVu','', 'DejaVuSans.php');
$pdf->AddFont('DejaVu','B','DejaVuSans-Bold.php');

/* ====== OKRES ====== */
$pdf->SetFont('DejaVu','',10);
$pdf->Cell(0,6, pl("Okres: $month/$year"),0,1);
$pdf->Ln(3);

/* ====== SZEROKOŚCI KOLUMN ====== */
$colSite = 200;
$colTime = 50;

$globalSeconds = 0;

/* ====== DANE PRACOWNIKÓW ====== */
foreach ($employees as $employee => $rows) {

    if ($pdf->GetY() > 180) {
        $pdf->AddPage();
    }

    $pdf->SetFont('DejaVu','B',11);
    $pdf->Cell(0,8, pl($employee),0,1);

    /* NAGŁÓWEK */
    $pdf->SetFont('DejaVu','B',9);
    $pdf->Cell($colSite,7, pl('Budowa'),1);
    $pdf->Cell($colTime,7, pl('Czas'),1,0,'C');
    $pdf->Ln();

    $pdf->SetFont('DejaVu','',9);
    $empSeconds = 0;

    foreach ($rows as $r) {

        $pdf->Cell($colSite,7, pl($r['site_name']),1);

        [$h,$m] = array_pad(explode(':', $r['time'] ?? '00:00'), 2, 0);
        $pdf->Cell(
            $colTime,
            7,
            sprintf('%02d:%02d', $h, $m),
            1,
            0,
            'C'
        );
        $pdf->Ln();

        [$h,$m,$s] = array_pad(
            explode(':', $r['time'] ?? '00:00'), 3, 0
        );
        $empSeconds += ($h*3600)+($m*60);
    }

    /* ===== RAZEM ===== */
    $empSecondsRounded = roundWorkTime($empSeconds);

    $empTotal = sprintf(
        "%02d:%02d",
        floor($empSecondsRounded/3600),
        floor(($empSecondsRounded%3600)/60)
    );

    $pdf->SetFont('DejaVu','B',9);
    $pdf->Cell($colSite,7, pl('RAZEM'),1);
    $pdf->Cell($colTime,7, $empTotal,1,0,'C');
    $pdf->Ln(6);

    $globalSeconds += $empSecondsRounded;
}

/* ====== SUMA GLOBALNA ====== */
$globalSecondsRounded = roundWorkTime($globalSeconds);

$pdf->Ln(4);
$pdf->SetFont('DejaVu','B',11);
$pdf->Cell($colSite,8, pl('ŁĄCZNIE WSZYSCY'),1);
$pdf->Cell(
    $colTime,
    8,
    sprintf(
        "%02d:%02d",
        floor($globalSecondsRounded/3600),
        floor(($globalSecondsRounded%3600)/60)
    ),
    1,
    0,
    'C'
);

/* ====== PODPIS ====== */
if ($pdf->GetY() > 180) {
    $pdf->AddPage();
}

$pdf->Ln(20);
$pdf->SetFont('DejaVu','',9);
$pdf->Cell(190);
$pdf->Cell(80,6, pl('Podpis kierownika:'),0,1);
$pdf->Ln(10);
$pdf->Cell(190);
$pdf->Cell(80,0,'','T');

/* ====== NAZWA PLIKU ====== */
function filenameSafe($text) {
    $text = iconv('UTF-8','ASCII//TRANSLIT',$text);
    return preg_replace('/[^A-Za-z0-9._-]/','_',$text);
}

$filename = filenameSafe("Raport_wszyscy_{$month}_{$year}.pdf");

/* ====== OUTPUT ====== */
$pdf->Output('I', $filename);
