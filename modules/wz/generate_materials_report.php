<?php
/**
 * Generowanie raportu PDF - Statystyki materiałów wbudowanych na budowach
 */

require_once __DIR__.'/../../core/session.php';
require_once __DIR__.'/../../core/auth.php';
require_once __DIR__.'/../../lib/tfpdf.php';

ini_set('display_errors', 0);
error_reporting(E_ALL);

$manager = requireManager(2);

try {
    $pdo = require __DIR__.'/../../core/db.php';
} catch (Exception $e) {
    die('Błąd połączenia z bazą danych: ' . $e->getMessage());
}

// Parametry filtrowania
$filterSiteId = isset($_GET['site']) ? (int)$_GET['site'] : 0;
$siteName = '';

// Jeśli wybrano konkretną budowę, pobierz jej nazwę
if ($filterSiteId) {
    $siteStmt = $pdo->prepare("SELECT name FROM sites WHERE id = ?");
    $siteStmt->execute([$filterSiteId]);
    $siteName = $siteStmt->fetchColumn() ?: '';
}

// Pobierz statystyki materiałów (tylko zatwierdzone dokumenty)
$statsQuery = "
    SELECT 
        s.name AS site_name,
        w.material_type,
        SUM(CAST(w.material_quantity AS DECIMAL(10,2))) AS total_quantity,
        COUNT(w.id) AS document_count,
        MIN(w.created_at) AS first_date,
        MAX(w.created_at) AS last_date
    FROM wz_scans w
    LEFT JOIN sites s ON w.site_id = s.id
    WHERE w.status = 'approved'";

// Filtruj po budowie, jeśli wybrano
if ($filterSiteId) {
    $statsQuery .= " AND w.site_id = " . (int)$filterSiteId;
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

// Pobierz statystyki ogólne
$totalQuery = "SELECT COUNT(*) as total_approved FROM wz_scans WHERE status = 'approved'";
if ($filterSiteId) {
    $totalQuery .= " AND site_id = " . (int)$filterSiteId;
}
$totalApproved = (int)$pdo->query($totalQuery)->fetchColumn();

// Rozpocznij generowanie PDF
class MaterialsReportPDF extends tFPDF
{
    private $reportDate;
    private $siteName;
    
    public function __construct($reportDate, $siteName = '')
    {
        parent::__construct();
        $this->reportDate = $reportDate;
        $this->siteName = $siteName;
    }
    
    function Header()
    {
        // Logo lub nazwa firmy
        $this->SetFont('DejaVu', 'B', 16);
        $this->SetTextColor(102, 126, 234);
        $title = 'Raport: Materiały wbudowane na budowach';
        if ($this->siteName) {
            $title = 'Raport: Materiały - ' . $this->siteName;
        }
        $this->Cell(0, 10, $title, 0, 1, 'C');
        
        $this->SetFont('DejaVu', '', 10);
        $this->SetTextColor(100, 100, 100);
        $this->Cell(0, 6, 'Data wygenerowania: ' . $this->reportDate, 0, 1, 'C');
        $this->Cell(0, 6, 'Dokument zawiera sumaryczne zestawienie materiałów z zatwierdzonych WZ', 0, 1, 'C');
        
        $this->Ln(5);
    }
    
    function Footer()
    {
        $this->SetY(-15);
        $this->SetFont('DejaVu', '', 8);
        $this->SetTextColor(128, 128, 128);
        $this->Cell(0, 10, 'Strona ' . $this->PageNo() . ' | System RCP', 0, 0, 'C');
    }
    
    function SiteSection($siteName, $materials)
    {
        // Nazwa budowy
        $this->SetFont('DejaVu', 'B', 14);
        $this->SetFillColor(102, 126, 234);
        $this->SetTextColor(255, 255, 255);
        $this->Cell(0, 10, 'Budowa: ' . $siteName, 0, 1, 'L', true);
        $this->Ln(2);
        
        // Nagłówki tabeli
        $this->SetFont('DejaVu', 'B', 10);
        $this->SetFillColor(230, 230, 250);
        $this->SetTextColor(0, 0, 0);
        $this->Cell(90, 8, 'Materiał', 1, 0, 'L', true);
        $this->Cell(35, 8, 'Ilość', 1, 0, 'C', true);
        $this->Cell(30, 8, 'Dokumentów', 1, 0, 'C', true);
        $this->Cell(35, 8, 'Zakres dat', 1, 1, 'C', true);
        
        // Dane materiałów
        $this->SetFont('DejaVu', '', 9);
        foreach ($materials as $material) {
            $this->Cell(90, 7, $material['material_type'], 1, 0, 'L');
            $this->Cell(35, 7, number_format($material['total_quantity'], 2, ',', ' '), 1, 0, 'C');
            $this->Cell(30, 7, (string)$material['document_count'], 1, 0, 'C');
            
            // Zakres dat
            $firstDate = date('d.m.Y', strtotime($material['first_date']));
            $lastDate = date('d.m.Y', strtotime($material['last_date']));
            $dateRange = ($firstDate === $lastDate) ? $firstDate : $firstDate . ' - ' . $lastDate;
            
            $this->Cell(35, 7, $dateRange, 1, 1, 'C');
        }
        
        $this->Ln(8);
    }
    
    function SummarySection($totalDocuments, $totalSites, $totalMaterials)
    {
        $this->SetFont('DejaVu', 'B', 12);
        $this->SetFillColor(40, 167, 69);
        $this->SetTextColor(255, 255, 255);
        $this->Cell(0, 10, 'Podsumowanie', 0, 1, 'L', true);
        $this->Ln(2);
        
        $this->SetFont('DejaVu', '', 10);
        $this->SetTextColor(0, 0, 0);
        $this->Cell(100, 7, 'Łączna liczba zatwierdzonych dokumentów WZ:', 0, 0);
        $this->SetFont('DejaVu', 'B', 10);
        $this->Cell(0, 7, (string)$totalDocuments, 0, 1);
        
        $this->SetFont('DejaVu', '', 10);
        $this->Cell(100, 7, 'Liczba budów:', 0, 0);
        $this->SetFont('DejaVu', 'B', 10);
        $this->Cell(0, 7, (string)$totalSites, 0, 1);
        
        $this->SetFont('DejaVu', '', 10);
        $this->Cell(100, 7, 'Łączna liczba typów materiałów:', 0, 0);
        $this->SetFont('DejaVu', 'B', 10);
        $this->Cell(0, 7, (string)$totalMaterials, 0, 1);
    }
}

// Tworzenie PDF
$pdf = new MaterialsReportPDF(date('d.m.Y H:i'), $siteName);
$pdf->AddFont('DejaVu', '', 'DejaVuSans.ttf', true);
$pdf->AddFont('DejaVu', 'B', 'DejaVuSans-Bold.ttf', true);

$pdf->AddPage();
$pdf->SetAutoPageBreak(true, 20);

// Jeśli brak danych
if (empty($statsBySite)) {
    $pdf->SetFont('DejaVu', '', 12);
    $pdf->SetTextColor(150, 150, 150);
    $pdf->Cell(0, 20, 'Brak zatwierdzonych dokumentów WZ z materiałami', 0, 1, 'C');
} else {
    // Generuj sekcje dla każdej budowy
    foreach ($statsBySite as $siteName => $materials) {
        $pdf->SiteSection($siteName, $materials);
    }
    
    // Podsumowanie
    $totalSites = count($statsBySite);
    $totalMaterials = count($materialStats);
    $pdf->SummarySection($totalApproved, $totalSites, $totalMaterials);
}

// Wyślij PDF do przeglądarki
$filename = 'Raport_Materialy';
if ($siteName) {
    // Usuń znaki specjalne z nazwy budowy dla nazwy pliku
    $safeSiteName = preg_replace('/[^a-zA-Z0-9_-]/', '_', $siteName);
    $filename .= '_' . $safeSiteName;
}
$filename .= '_' . date('Y-m-d_His') . '.pdf';
$pdf->Output('I', $filename);
