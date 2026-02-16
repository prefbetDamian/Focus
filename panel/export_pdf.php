<?php
require_once __DIR__ . '/../core/auth.php';

// Sprawdzenie uprawnień managera (rola 2+)
requireManager(2);

error_reporting(E_ALL);
ini_set('display_errors', 1);

require(__DIR__ . '/../lib/tfpdf.php');

$config = require __DIR__.'/../config.php';

/* ===== BAZA ===== */
$pdo = new PDO(
    "mysql:host={$config['db_host']};port={$config['db_port']};dbname={$config['db_name']};charset=utf8mb4",
    $config['db_user'],
    $config['db_pass'],
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

/* ===== PARAMETRY ===== */
$firstName = $_GET['firstName'] ?? '';
$lastName  = $_GET['lastName'] ?? '';
$month     = (int)($_GET['month'] ?? 0);
$year      = (int)($_GET['year'] ?? 0);

if (!$firstName || !$lastName || !$month || !$year) {
    die('Brak danych do raportu');
}

/* ===== STAWKA ===== */
$stmtRate = $pdo->prepare("
    SELECT hour_rate
    FROM employees
    WHERE first_name = ? AND last_name = ?
    LIMIT 1
");
$stmtRate->execute([$firstName, $lastName]);
$hourRate = (float)($stmtRate->fetchColumn() ?? 0);

/* ===== DANE ===== */
$stmt = $pdo->prepare("
    SELECT
        ws.site_name,
        ws.start_time,
        ws.end_time,
        ws.duration_seconds,
        ws.manager_comment,
        ws.manager_id,
        ws.status,
        m.registry_number,
        m.short_name,
        mgr.first_name AS manager_first_name,
        mgr.last_name  AS manager_last_name
    FROM work_sessions ws
    LEFT JOIN machines m ON m.id = ws.machine_id
    LEFT JOIN managers mgr ON mgr.id = ws.manager_id
    WHERE ws.first_name = ?
      AND ws.last_name  = ?
      AND MONTH(ws.start_time) = ?
      AND YEAR(ws.start_time)  = ?
    ORDER BY ws.start_time
");
$stmt->execute([$firstName, $lastName, $month, $year]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* ===== SESJE PO DNIACH ===== */
$sessionsByDay = [];
foreach ($rows as $r) {
    $day = date('Y-m-d', strtotime($r['start_time']));
    $sessionsByDay[$day][] = $r;
}

/* ===== FUNKCJE ===== */
function roundToIntervals(int $seconds): int {
    if ($seconds <= 0) return 0;
    $h = floor($seconds / 3600);
    $m = floor(($seconds % 3600) / 60);
    if ($m < 30) return $h * 3600;
    return $h * 3600 + 1800;
}

function formatFullHours(int $seconds): string {
    if ($seconds <= 0) return '00.00';
    return sprintf('%02d.%02d', floor($seconds / 3600), floor(($seconds % 3600) / 60));
}

function isSaturdayOrHoliday(string $dateYmd): bool {
    $ts = strtotime($dateYmd);
    if (!$ts) {
        return false;
    }

    $dow = (int)date('N', $ts); // 6 = sobota
    if ($dow === 6) {
        return true;
    }

    $md = date('m-d', $ts);
    $fixedHolidays = [
        '01-01', // Nowy Rok
        '01-06', // Trzech Króli
        '05-01', // Święto Pracy
        '05-03', // Święto Konstytucji 3 Maja
        '08-15', // Wniebowzięcie NMP
        '11-01', // Wszystkich Świętych
        '11-11', // Święto Niepodległości
        '12-25', // Boże Narodzenie (pierwszy dzień)
        '12-26', // Boże Narodzenie (drugi dzień)
    ];

    return in_array($md, $fixedHolidays, true);
}

/* ===== PDF ===== */
class PDF extends tFPDF {

    public array $colW = [
        'D'=>20,'B'=>60,'M'=>80,'S'=>15,'E'=>15,'C'=>20,'N'=>20,'K'=>50
    ];
    public int $rowUnit = 6;

    function Header() {
        if (file_exists(__DIR__.'/Logo.png')) {
            $this->Image(__DIR__.'/Logo.png', 245, 5, 45);
        }
        $this->Ln(6);
        $this->SetFont('DejaVu','B',14);
        $this->Cell(0,10,'Raport Czasu Pracy',0,1,'C');
        $this->Ln(6);
    }

    function Footer() {
        $this->SetY(-15);
        $this->SetFont('DejaVu','',8);
        $this->Cell(0,10,'Wygenerowano: '.date('Y-m-d H:i'),0,0,'C');
    }

    function TableHeader() {
        $this->SetFont('DejaVu','B',8);
        foreach (['D'=>'Data','B'=>'Budowa','M'=>'Maszyna','S'=>'Start','E'=>'Stop','C'=>'Pref-Bet','N'=>'BG','K'=>'Komentarz'] as $k=>$v) {
            $this->Cell($this->colW[$k],8,$v,1,0,'C');
        }
        $this->Ln();
        $this->SetFont('DejaVu','',8);
    }

    function CheckPageBreak($h) {
        if ($this->GetY()+$h > $this->PageBreakTrigger) {
            $this->AddPage('L');
            $this->TableHeader();
        }
    }
}

/* ===== START ===== */
$pdf = new PDF('L');
$pdf->AddFont('DejaVu','', 'DejaVuSans.ttf', true);
$pdf->AddFont('DejaVu','B','DejaVuSans-Bold.ttf', true);

$pdf->AddPage();

/* INFO */
$pdf->SetFont('DejaVu','B',11);
$pdf->Cell(0,7,"Pracownik: $firstName $lastName",0,1);
$pdf->Cell(0,7,"Wynagrodzenie: $hourRate zł",0,1);
$pdf->SetFont('DejaVu','',10);
$pdf->Cell(0,6,"Miesiąc: $month/$year",0,1);
$pdf->Ln(3);
$pdf->TableHeader();

/* ===== DNI ===== */
$sumWork = $sumOver = 0;
$weekendWorkSec = 0;
$weekendOverSec = 0;
$dailyWorked = [];
$daysInMonth = cal_days_in_month(CAL_GREGORIAN, $month, $year);

for ($d=1;$d<=$daysInMonth;$d++) {
    $dateKey = sprintf('%04d-%02d-%02d',$year,$month,$d);
    foreach ($sessionsByDay[$dateKey] ?? [[]] as $r) {

        if (!$r) {
            foreach ($pdf->colW as $w) $pdf->Cell($w,6,'',1);
            $pdf->Ln();
            continue;
        }

        $sec = (int)$r['duration_seconds'];
        $dailyWorked[$dateKey] ??= 0;

        $work = min($sec, max(0,28800-$dailyWorked[$dateKey]));
        $over = max(0,$sec-$work);

        $dailyWorked[$dateKey]+=$sec;

        $status    = $r['status'] ?? 'OK';
        $isPayable = in_array($status, ['OK','MANUAL'], true);

        if ($isPayable) {
            $sumWork += $work;
            $sumOver += $over;

            if (isSaturdayOrHoliday($dateKey)) {
                $weekendWorkSec += $work;
                $weekendOverSec += $over;
            }
        }

        $commentText = '';
if (!empty($r['manager_comment'])) {
    $commentText = 'SESJA RECZNA: '.$r['manager_comment'];
    if (!empty($r['manager_first_name']) && !empty($r['manager_last_name'])) {
        $commentText .= "\nDodane przez: ".$r['manager_first_name'].' '.$r['manager_last_name'];
    }
}


        $pdf->CheckPageBreak(6);
        $pdf->Cell($pdf->colW['D'],6,$dateKey,1);
        $pdf->Cell($pdf->colW['B'],6,$r['site_name'],1);
        $pdf->Cell($pdf->colW['M'],6,trim(($r['registry_number']??'').' '.$r['short_name']),1);
        $pdf->Cell($pdf->colW['S'],6,date('H.i',strtotime($r['start_time'])),1);
        $pdf->Cell($pdf->colW['E'],6,$r['end_time']?date('H.i',strtotime($r['end_time'])):'',1);
        $pdf->Cell($pdf->colW['C'],6,formatFullHours($work),1,0,'C');
        $pdf->Cell($pdf->colW['N'],6,$over?formatFullHours($over):'',1,0,'C');
        $pdf->MultiCell(    $pdf->colW['K'],    $pdf->rowUnit,    ($commentText !== '' ? $commentText : ''),    1,    'C');
        $pdf->Ln();
    }
}

/* ===== SUMY ===== */
$sumWork = roundToIntervals($sumWork);
$sumOver = roundToIntervals($sumOver);

// Założenie: cała sesja przypisana do daty startu (brak rozbijania przez północ)
$weekendWorkSec = min($weekendWorkSec, $sumWork);
$weekendOverSec = min($weekendOverSec, $sumOver);

/* ===== WYNAGRODZENIE ===== */
$ZUS_FACTOR = 0.67;

$hoursWork = $sumWork / 3600;
$hoursOver = $sumOver / 3600;

// Godziny w soboty/święta liczone jako 2× stawka
$weekendWorkHours = $weekendWorkSec / 3600;
$weekendOverHours = $weekendOverSec / 3600;

$baseSalaryWork = $hoursWork * $hourRate;
$baseSalaryOver = $hoursOver * $hourRate;

// Premia 100% za soboty/święta (druga stawka)
$bonusWork = $weekendWorkHours * $hourRate;
$bonusOver = $weekendOverHours * $hourRate;

$salaryWork = round($baseSalaryWork + $bonusWork, 2);
$salaryOver = round($baseSalaryOver + $bonusOver, 2);

$salaryWork1 = round($salaryWork * $ZUS_FACTOR, 2);
$salaryOver1 = round($salaryOver * $ZUS_FACTOR, 2);
$salaryWork2 = round($salaryWork + $salaryWork1, 2);
$salaryOver2 = round($salaryOver + $salaryOver1, 2);

$pdf->Ln(4);
$pdf->SetFont('DejaVu','B',10);

$labelWidth = $pdf->colW['D'] + $pdf->colW['B'] + $pdf->colW['M'] + $pdf->colW['S'] + $pdf->colW['E'];

$pdf->Cell($labelWidth,8,'Suma godzin:',1,0,'R');
$pdf->Cell($pdf->colW['C'],8,formatFullHours($sumWork),1,0,'C');
$pdf->Cell($pdf->colW['N'],8,formatFullHours($sumOver),1,1,'C');

$pdf->Ln(1);
$pdf->Cell($labelWidth,8,'Podstawa:',1,0,'R');
$pdf->Cell($pdf->colW['C'],8,number_format($salaryWork,2,',',' '),1,0,'C');
$pdf->Cell($pdf->colW['N'],8,number_format($salaryOver,2,',',' '),1,1,'C');

$pdf->Ln(1);
$pdf->Cell($labelWidth,8,'ZUS:',1,0,'R');
$pdf->Cell($pdf->colW['C'],8,number_format($salaryWork1,2,',',' '),1,0,'C');
$pdf->Cell($pdf->colW['N'],8,number_format($salaryOver1,2,',',' '),1,1,'C');

$pdf->Ln(1);
$pdf->Cell($labelWidth,8,'Suma wynagrodzenia:',1,0,'R');
$pdf->Cell($pdf->colW['C'],8,number_format($salaryWork2,2,',',' '),1,0,'C');
$pdf->Cell($pdf->colW['N'],8,number_format($salaryOver2,2,',',' '),1,1,'C');

/* ===== PODPIS ===== */
$pdf->Ln(20);
$pdf->Cell(190);
$pdf->Cell(80,6,'Podpis kierownika:',0,1);
$pdf->Ln(8);
$pdf->Cell(190);
$pdf->Cell(80,0,'','T');

/* ===== OUTPUT ===== */
$pdf->Output('I',"Raport_{$firstName}_{$lastName}_{$month}_{$year}.pdf");

