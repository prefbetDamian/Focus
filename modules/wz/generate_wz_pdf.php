<?php
/**
 * Generowanie PDF z dokumentu WZ
 * Łączy skan dokumentu z podpisem cyfrowym
 */

require_once __DIR__.'/../../core/session.php';
require_once __DIR__.'/../../core/auth.php';
require_once __DIR__.'/../../lib/fpdf.php';

// Włącz raportowanie błędów
error_reporting(E_ALL);
ini_set('display_errors', 0);

$manager = requireManager(2);

$id = intval($_GET['id'] ?? 0);

if (!$id) {
    die('Brak ID dokumentu');
}

try {
    $pdo = require __DIR__.'/../../core/db.php';
} catch (Exception $e) {
    die('Błąd połączenia z bazą danych: ' . $e->getMessage());
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

$uploadDir = __DIR__.'/../../uploads/wz/';

// Funkcja dla polskich znaków
function pl($text) {
    return iconv('UTF-8', 'ISO-8859-2//TRANSLIT', $text);
}

// Tworzenie PDF
class WZ_PDF extends FPDF {
    
    private $docNumber;
    private $siteName;
    private $employeeName;
    private $createdAt;
    
    function __construct($docNumber, $siteName, $employeeName, $createdAt) {
        parent::__construct();
        $this->docNumber = $docNumber;
        $this->siteName = $siteName;
        $this->employeeName = $employeeName;
        $this->createdAt = $createdAt;
    }
    
    function Header() {
        if (file_exists(__DIR__ . '/../../panel/Logo.png')) {
            $this->Image(__DIR__ . '/../../panel/Logo.png', 10, 6, 40);
        }
        
        $this->SetFont('Arial', 'B', 20);
        $this->Cell(0, 10, pl('Dokument WZ'), 0, 1, 'C');
        $this->Ln(5);
        
        $this->SetFont('Arial', '', 10);
        $this->Cell(0, 6, pl("Numer: {$this->docNumber}"), 0, 1);
        $this->Cell(0, 6, pl("Budowa: {$this->siteName}"), 0, 1);
        $this->Cell(0, 6, pl("Wystawil: {$this->employeeName}"), 0, 1);
        $this->Cell(0, 6, pl("Data: {$this->createdAt}"), 0, 1);
        $this->Ln(10);
    }
    
    function Footer() {
        $this->SetY(-15);
        $this->SetFont('Arial', 'I', 8);
        $this->Cell(0, 10, pl('Strona ') . $this->PageNo(), 0, 0, 'C');
    }
}

$pdf = new WZ_PDF(
    $doc['document_number'],
    $doc['site_name'] ?? 'Brak',
    $doc['creator_name'] ?? 'Nieznany',
    date('d.m.Y H:i', strtotime($doc['created_at']))
);

$pdf->AddPage();

// Dodanie skanu dokumentu
if ($doc['scan_file']) {
    $scanPath = $uploadDir . $doc['scan_file'];
    $ext = strtolower(pathinfo($doc['scan_file'], PATHINFO_EXTENSION));
    
    if (file_exists($scanPath)) {
        $pdf->SetFont('Arial', 'B', 12);
        $pdf->Cell(0, 8, pl('Skan dokumentu:'), 0, 1);
        $pdf->Ln(2);
        
        // Wstaw obraz skanu
        if (in_array($ext, ['jpg', 'jpeg', 'png'])) {
            // Oblicz wymiary obrazu aby zmieścił się na stronie
            $maxWidth = 180;
            $maxHeight = 180;
            
            if (!file_exists($scanPath)) {
                $pdf->Cell(0, 8, pl('[Plik skanu nie istnieje]'), 0, 1);
            } else {
                list($width, $height) = getimagesize($scanPath);
                $ratio = min($maxWidth / $width, $maxHeight / $height);
                $newWidth = $width * $ratio;
                $newHeight = $height * $ratio;
                
                $pdf->Image($scanPath, 15, $pdf->GetY(), $newWidth);
                $pdf->Ln($newHeight + 10);
            }
        } elseif ($ext === 'pdf') {
            $pdf->Cell(0, 8, pl('[Dokument PDF zalaczony - patrz oryginalny plik]'), 0, 1);
            $pdf->Ln(5);
        }
    }
} else {
    $pdf->SetFont('Arial', '', 10);
    $pdf->Cell(0, 8, pl('[Brak skanu dokumentu]'), 0, 1);
    $pdf->Ln(5);
}

// Dodanie uwag
if ($doc['notes']) {
    $pdf->SetFont('Arial', 'B', 12);
    $pdf->Cell(0, 8, pl('Uwagi:'), 0, 1);
    $pdf->SetFont('Arial', '', 10);
    $pdf->MultiCell(0, 6, pl($doc['notes']));
    $pdf->Ln(5);
}

// Dodanie podpisu
if ($doc['signature_file']) {
    $signaturePath = $uploadDir . $doc['signature_file'];
    
    if (file_exists($signaturePath)) {
        $pdf->SetFont('Arial', 'B', 12);
        $pdf->Cell(0, 8, pl('Podpis cyfrowy:'), 0, 1);
        $pdf->Ln(2);
        
        // Wstaw podpis (mniejszy rozmiar)
        list($width, $height) = getimagesize($signaturePath);
        $maxWidth = 100;
        $ratio = $maxWidth / $width;
        $newWidth = $width * $ratio;
        $newHeight = $height * $ratio;
        
        $pdf->Image($signaturePath, 15, $pdf->GetY(), $newWidth);
        $pdf->Ln($newHeight + 5);
    } else {
        $pdf->SetFont('Arial', '', 10);
        $pdf->Cell(0, 8, pl('[Plik podpisu nie istnieje]'), 0, 1);
        $pdf->Ln(5);
    }
} else {
    $pdf->SetFont('Arial', '', 10);
    $pdf->Cell(0, 8, pl('[Brak podpisu cyfrowego]'), 0, 1);
    $pdf->Ln(5);
}

// Zapisz PDF
$pdfFileName = 'wz_' . preg_replace('/[^A-Za-z0-9._-]/', '_', $doc['document_number']) . '_' . time() . '.pdf';
$pdfPath = $uploadDir . $pdfFileName;

try {
    // Sprawdź czy katalog istnieje
    if (!is_dir($uploadDir)) {
        if (!mkdir($uploadDir, 0755, true)) {
            throw new Exception('Nie można utworzyć katalogu: ' . $uploadDir);
        }
    }
    
    // Sprawdź uprawnienia do zapisu
    if (!is_writable($uploadDir)) {
        throw new Exception('Brak uprawnień do zapisu w katalogu: ' . $uploadDir);
    }
    
    // Generuj PDF
    $pdf->Output('F', $pdfPath);
    
    // Sprawdź czy plik został utworzony
    if (!file_exists($pdfPath)) {
        throw new Exception('Nie udało się utworzyć pliku PDF w: ' . $pdfPath);
    }
    
    // Aktualizuj bazę danych
    $stmt = $pdo->prepare("UPDATE wz_scans SET pdf_file = ?, status = 'approved', updated_at = NOW() WHERE id = ?");
    $success = $stmt->execute([$pdfFileName, $id]);
    
    if (!$success) {
        throw new Exception('Nie udało się zaktualizować bazy danych');
    }
    
    // Przekieruj do listy z komunikatem
    header('Location: list_wz.php?generated=1');
    exit;
    
} catch (Exception $e) {
    // Loguj błąd do pliku
    error_log('WZ PDF Generation Error: ' . $e->getMessage() . ' | File: ' . $e->getFile() . ' | Line: ' . $e->getLine());
    die('<h1>Błąd podczas generowania PDF</h1><p>' . htmlspecialchars($e->getMessage()) . '</p><p><a href="list_wz.php">Powrót do listy</a></p>');
}
