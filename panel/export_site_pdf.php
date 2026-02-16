<?php
require_once __DIR__ . '/../core/auth.php';

// Sprawdzenie uprawnień managera (rola 2+)
requireManager(2);

$config = require __DIR__.'/../config.php';
require(__DIR__ . '/../lib/fpdf.php');

/* ===== POLSKIE ZNAKI ===== */
function pl($t){ return iconv('UTF-8','ISO-8859-2//TRANSLIT',$t); }

/* ===== INTERWAŁY 15 / 45 ===== */
function roundToIntervals(int $s): int {
    if ($s <= 0) return 0;
    $h = floor($s / 3600);
    $m = floor(($s % 3600) / 60);
    if ($m < 30) return $h * 3600;
    return ($h * 3600) + 1800;
}

function secToHM(int $s): string {
    return sprintf('%02d:%02d', floor($s/3600), floor(($s%3600)/60));
}

/* ===== DB ===== */
$pdo = new PDO(
    "mysql:host={$config['db_host']};port={$config['db_port']};dbname={$config['db_name']};charset=utf8mb4",
    $config['db_user'],
    $config['db_pass'],
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

/* ===== PARAMETRY ===== */
$site  = $_GET['site']  ?? '';
$month = (int)($_GET['month'] ?? 0);
$year  = (int)($_GET['year'] ?? 0);

if (!$site || !$month || !$year) die('Brak danych');

/* ===== DANE ===== */
$stmt = $pdo->prepare("
    SELECT
        ws.first_name,
        ws.last_name,
        ws.start_time,
        ws.end_time,
        ws.duration_seconds,
        e.hour_rate,
        m.short_name AS machine_short
    FROM work_sessions ws
    LEFT JOIN employees e
        ON e.first_name = ws.first_name
       AND e.last_name  = ws.last_name
    LEFT JOIN machines m
    ON m.id = ws.machine_id
        WHERE ws.site_name = ?
            AND MONTH(ws.start_time) = ?
            AND YEAR(ws.start_time) = ?
            AND ws.status IN ('OK','MANUAL')
    ORDER BY ws.last_name, ws.first_name, ws.start_time
");
$stmt->execute([$site, $month, $year]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* ===== GRUPOWANIE PO PRACOWNIKU ===== */
$employees = [];
foreach ($rows as $r) {
    $key = $r['last_name'].' '.$r['first_name'];
    $employees[$key][] = $r;
}

/* ===== PDF ===== */
class PDF extends FPDF {
    function Header(){
        if (file_exists(__DIR__.'/Logo.png')) {
            $this->Image(__DIR__.'/Logo.png',245,5,45);
        }
        $this->AddFont('DejaVu','B','DejaVuSans-Bold.php');
        $this->SetFont('DejaVu','B',14);
        $this->Cell(0,10,pl('Raport budowy'),0,1,'C');
        $this->Ln(4);
    }
}

$pdf = new PDF('L');
$pdf->AddPage();
$pdf->AddFont('DejaVu','','DejaVuSans.php');
$pdf->AddFont('DejaVu','B','DejaVuSans-Bold.php');

/* INFO */
$pdf->SetFont('DejaVu','',10);
$pdf->Cell(0,6,pl("Budowa: $site"),0,1);
$pdf->Cell(0,6,pl("Okres: $month/$year"),0,1);
$pdf->Ln(4);

/* KOLUMNY */
$wDate=28; $wIn=22; $wOut=22; $wPB=28; $wBG=28;$wMach = 60;


/* ===== ZESTAWIENIA ===== */
$finalHours = [];
$finalCosts = [];

$sumSalaryPB = 0;
$sumSalaryBG = 0;
$sumZusPB    = 0;
$sumZusBG    = 0;
$sumTotalPB  = 0;
$sumTotalBG  = 0;

$ZUS = 0.67;

/* ===== PRACOWNICY ===== */
foreach ($employees as $emp => $sessions) {

    $rate = (float)($sessions[0]['hour_rate'] ?? 0);

    $dailyWorked = [];
    $sumPB = 0;
    $sumBG = 0;

    $pdf->SetFont('DejaVu','B',11);
    $pdf->Cell(0,8,pl("Pracownik: $emp | Stawka: $rate zł"),0,1);

    $pdf->SetFont('DejaVu','B',9);
    $pdf->Cell($wDate,7,pl('Data'),1);
    $pdf->Cell($wIn,7,pl('Wejście'),1);
    $pdf->Cell($wOut,7,pl('Zejście'),1);
    $pdf->Cell($wMach,7,pl('Maszyna'),1);
    $pdf->Cell($wPB,7,pl('Pref-Bet'),1,0,'C');
    $pdf->Cell($wBG,7,pl('BG'),1,0,'C');
    $pdf->Ln();

    $pdf->SetFont('DejaVu','',9);

    foreach ($sessions as $s) {

        $date = date('Y-m-d', strtotime($s['start_time']));
        $sec  = (int)$s['duration_seconds'];

        $dailyWorked[$date] ??= 0;
        $remaining = max(0, (8*3600) - $dailyWorked[$date]);
        $pb = min($sec, $remaining);
        $bg = max(0, $sec - $pb);

        $dailyWorked[$date] += $sec;
        $sumPB += $pb;
        $sumBG += $bg;

        $pdf->Cell($wDate,7,$date,1);
        $pdf->Cell($wIn,7,date('H:i',strtotime($s['start_time'])),1);
        $pdf->Cell($wOut,7,$s['end_time']?date('H:i',strtotime($s['end_time'])):'',1);
        $pdf->Cell($wMach,7,pl($s['machine_short'] ?? ''),1);
        $pdf->Cell($wPB,7,secToHM(roundToIntervals($pb)),1,0,'C');
        $pdf->Cell($wBG,7,$bg?secToHM(roundToIntervals($bg)):'',1,0,'C');
        $pdf->Ln();
    }

    $sumPB = roundToIntervals($sumPB);
    $sumBG = roundToIntervals($sumBG);

    $hPB = $sumPB / 3600;
    $hBG = $sumBG / 3600;

    $salaryPB = round($hPB * $rate, 2);
    $salaryBG = round($hBG * $rate, 2);

    $zusPB = round($salaryPB * $ZUS, 2);
    $zusBG = round($salaryBG * $ZUS, 2);

    $totalPB = round($salaryPB + $zusPB, 2);
    $totalBG = round($salaryBG + $zusBG, 2);

    /* ===== SUMY PRACOWNIKA ===== */
    $pdf->Ln(2);
    $pdf->SetFont('DejaVu','B',9);
    $pdf->Cell($wDate+$wIn+$wOut+$wMach,7,pl('Suma godzin:'),1,0,'R');
    $pdf->Cell($wPB,7,secToHM($sumPB),1,0,'C');
    $pdf->Cell($wBG,7,secToHM($sumBG),1,0,'C');
    $pdf->Ln();

    $pdf->Cell($wDate+$wIn+$wOut+$wMach,7,pl('Podstawa:'),1,0,'R');
    $pdf->Cell($wPB,7,number_format($salaryPB,2,',',' '),1,0,'C');
    $pdf->Cell($wBG,7,number_format($salaryBG,2,',',' '),1,0,'C');
    $pdf->Ln();

    $pdf->Cell($wDate+$wIn+$wOut+$wMach,7,pl('ZUS:'),1,0,'R');
    $pdf->Cell($wPB,7,number_format($zusPB,2,',',' '),1,0,'C');
    $pdf->Cell($wBG,7,number_format($zusBG,2,',',' '),1,0,'C');
    $pdf->Ln();

    $pdf->Cell($wDate+$wIn+$wOut+$wMach,7,pl('Suma kosztu:'),1,0,'R');
    $pdf->Cell($wPB,7,number_format($totalPB,2,',',' '),1,0,'C');
    $pdf->Cell($wBG,7,number_format($totalBG,2,',',' '),1,0,'C');
    $pdf->Ln(6);

    /* ===== ZBIERANIE GLOBALNE ===== */
    $finalHours[$emp] = [$hPB,$hBG];

    $sumSalaryPB += $salaryPB;
    $sumSalaryBG += $salaryBG;
    $sumZusPB    += $zusPB;
    $sumZusBG    += $zusBG;
    $sumTotalPB  += $totalPB;
    $sumTotalBG  += $totalBG;
}

/* ===== ZESTAWIENIE KOŃCOWE ===== */
$pdf->AddPage();
$pdf->SetFont('DejaVu','B',12);
$pdf->Cell(0,10,pl('Zestawienie końcowe Pacownik'),0,1,'C');

/* ===== SUMY GODZIN ===== */
$sumPrefBet = 0.0;
$sumBG      = 0.0;

foreach ($finalHours as $h) {
    $sumPrefBet += (float)$h[0];
    $sumBG      += (float)$h[1];
}

/* ===== TABELA ===== */
$pdf->SetFont('DejaVu','B',9);
$pdf->Cell(80,7,pl('Pracownik'),1);
$pdf->Cell(30,7,pl('Pref-Bet (h)'),1,0,'C');
$pdf->Cell(30,7,pl('BG (h)'),1,0,'C');
$pdf->Ln();

$pdf->SetFont('DejaVu','',9);
foreach ($finalHours as $e => $h){
    $pdf->Cell(80,7,pl($e),1);
    $pdf->Cell(30,7,number_format($h[0],2,',',' '),1,0,'C');
    $pdf->Cell(30,7,number_format($h[1],2,',',' '),1,0,'C');
    $pdf->Ln();
}

/* ===== WIERSZ SUMA ===== */
$pdf->SetFont('DejaVu','B',9);
$pdf->Cell(80,7,pl('SUMA'),1);
$pdf->Cell(30,7,number_format($sumPrefBet,2,',',' '),1,0,'C');
$pdf->Cell(30,7,number_format($sumBG,2,',',' '),1,0,'C');
$pdf->Ln();

/* ===== OUTPUT ===== */
$fn = preg_replace(
    '/[^A-Za-z0-9._-]/','_',
    iconv('UTF-8','ASCII//TRANSLIT',"Raport_{$site}_{$month}_{$year}.pdf")
);

$pdf->Output('I',$fn);
