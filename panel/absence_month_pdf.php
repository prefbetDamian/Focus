<?php
require_once __DIR__ . '/../core/auth.php';

// Sprawdzenie uprawnień managera (rola 2+)
requireManager(2);

require(__DIR__ . '/../lib/tfpdf.php');

$config = require __DIR__.'/../config.php';

$pdo = new PDO(
    "mysql:host={$config['db_host']};port={$config['db_port']};dbname={$config['db_name']};charset=utf8mb4",
    $config['db_user'],
    $config['db_pass'],
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

$month = $_GET['month'] ?? null;
if (!$month) die("Brak miesiąca");

/* ===== JEDEN WIERSZ OD–DO ===== */
$sql = "
SELECT 
    e.first_name,
    e.last_name,
    ws.site_name,
    MIN(ws.start_time) AS date_from,
    MAX(ws.end_time)   AS date_to
FROM work_sessions ws
JOIN employees e ON e.id = ws.employee_id
WHERE ws.site_name IN ('URLOP','L4')
AND DATE_FORMAT(ws.start_time,'%Y-%m') = :m
GROUP BY e.id, ws.site_name
ORDER BY e.last_name
";

$stmt = $pdo->prepare($sql);
$stmt->execute(['m' => $month]);
$data = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* ===== PDF UTF-8 ===== */
$pdf = new tFPDF();
$pdf->AddPage();
$pdf->AddFont('DejaVu','', 'DejaVuSans.ttf', true);
$pdf->AddFont('DejaVu','B','DejaVuSans-Bold.ttf', true);

/* Tytuł */
$pdf->SetFont('DejaVu','B',14);
$pdf->Cell(0,10,"Lista nieobecności – $month",0,1,'C');
$pdf->Ln(5);

/* Nagłówki */
$pdf->SetFont('DejaVu','B',10);
$pdf->Cell(70, 8, 'Pracownik', 1);
$pdf->Cell(30, 8, 'Typ', 1);
$pdf->Cell(90, 8, 'Okres (od – do)', 1);
$pdf->Ln();

/* Dane */
$pdf->SetFont('DejaVu','',10);

if (!$data) {
    $pdf->Cell(0, 10, 'Brak nieobecności w wybranym miesiącu', 1, 1, 'C');
} else {
    foreach ($data as $r) {
        $employee = $r['last_name'].' '.$r['first_name'];
        $period   = substr($r['date_from'],0,10).' – '.substr($r['date_to'],0,10);

        $pdf->Cell(70, 8, $employee, 1);
        $pdf->Cell(30, 8, $r['site_name'], 1);
        $pdf->Cell(90, 8, $period, 1);
        $pdf->Ln();
    }
}

$pdf->Output('I', "nieobecnosci_$month.pdf");
