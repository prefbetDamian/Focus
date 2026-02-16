<?php
/**
 * Sprawdzenie logów błędów PHP
 */

echo "<h1>Sprawdzanie logów błędów PHP</h1>";

// Możliwe lokalizacje logów w XAMPP
$logPaths = [
    'C:\\xampp\\php\\logs\\php_error_log',
    'C:\\xampp\\apache\\logs\\error.log',
    'C:\\xampp\\apache\\logs\\php_error_log',
    ini_get('error_log'),
];

echo "<h2>Konfiguracja PHP:</h2>";
echo "error_log: " . ini_get('error_log') . "<br>";
echo "display_errors: " . ini_get('display_errors') . "<br>";
echo "log_errors: " . ini_get('log_errors') . "<br>";

echo "<h2>Ostatnie błędy z logów:</h2>";

foreach ($logPaths as $path) {
    if (empty($path)) continue;
    
    echo "<h3>$path</h3>";
    
    if (file_exists($path)) {
        $lines = file($path);
        $lastLines = array_slice($lines, -50); // ostatnie 50 linii
        
        echo "<pre style='background:#f5f5f5;padding:10px;border:1px solid #ccc;max-height:400px;overflow:auto;'>";
        foreach ($lastLines as $line) {
            // Podświetl błędy
            if (stripos($line, 'error') !== false || stripos($line, 'fatal') !== false) {
                echo "<span style='color:red;font-weight:bold;'>" . htmlspecialchars($line) . "</span>";
            } elseif (stripos($line, 'warning') !== false) {
                echo "<span style='color:orange;'>" . htmlspecialchars($line) . "</span>";
            } else {
                echo htmlspecialchars($line);
            }
        }
        echo "</pre>";
    } else {
        echo "<p style='color:gray;'>Plik nie istnieje</p>";
    }
}

echo "<h2>Test wywołania API z error handling:</h2>";

// Włącz wszystkie błędy
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Symuluj request
$_SERVER['REQUEST_METHOD'] = 'GET';
$_GET['action'] = 'count_pending';

// Start sesji
session_start();
$_SESSION['manager'] = ['id' => 1, 'role_level' => 9];

echo "<p>Wywołuję API...</p>";

// Przechwytuj output
ob_start();

try {
    include __DIR__ . '/absence_requests_api.php';
    $output = ob_get_clean();
    
    echo "<h3>✅ API zwróciło:</h3>";
    echo "<pre style='background:#e8f5e9;padding:10px;border:1px solid #4caf50;'>";
    echo htmlspecialchars($output);
    echo "</pre>";
    
    // Sprawdź czy to poprawny JSON
    $json = json_decode($output, true);
    if ($json !== null) {
        echo "<h3>✅ Poprawny JSON:</h3>";
        echo "<pre>" . print_r($json, true) . "</pre>";
    } else {
        echo "<h3>❌ To nie jest poprawny JSON!</h3>";
        echo "<p>Błąd JSON: " . json_last_error_msg() . "</p>";
    }
    
} catch (Throwable $e) {
    $output = ob_get_clean();
    
    echo "<h3>❌ Wystąpił błąd:</h3>";
    echo "<pre style='background:#ffebee;padding:10px;border:1px solid #f44336;'>";
    echo "Typ: " . get_class($e) . "\n";
    echo "Komunikat: " . $e->getMessage() . "\n";
    echo "Plik: " . $e->getFile() . "\n";
    echo "Linia: " . $e->getLine() . "\n\n";
    echo "Stack trace:\n" . $e->getTraceAsString();
    echo "</pre>";
    
    if (!empty($output)) {
        echo "<h3>Output przed błędem:</h3>";
        echo "<pre>" . htmlspecialchars($output) . "</pre>";
    }
}
?>
