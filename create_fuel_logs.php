<?php
/**
 * Skrypt tworzenia tabeli fuel_logs
 */

$config = require __DIR__.'/config.php';

try {
        $pdo = new PDO(
		"mysql:host={$config['db_host']};port={$config['db_port']};dbname={$config['db_name']};charset=utf8mb4",
        $config['db_user'],
        $config['db_pass'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    
    echo "Połączono z bazą danych.\n\n";
    
    $sql = file_get_contents(__DIR__ . '/database_fuel_logs.sql');
    
    // Usuń komentarze z przykładowymi danymi
    $sql = preg_replace('/-- INSERT INTO.*$/m', '', $sql);
    
    // Wykonaj SQL
    $pdo->exec($sql);
    
    echo "✅ Tabela fuel_logs została utworzona pomyślnie!\n\n";
    
    // Sprawdź strukturę
    $stmt = $pdo->query("DESCRIBE fuel_logs");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Struktura tabeli fuel_logs:\n";
    foreach ($columns as $col) {
        echo "  - {$col['Field']}: {$col['Type']} " . ($col['Null'] === 'YES' ? 'NULL' : 'NOT NULL') . "\n";
    }
    
} catch (PDOException $e) {
    echo "❌ Błąd: " . $e->getMessage() . "\n";
    exit(1);
}
