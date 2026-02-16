<?php
/**
 * Skrypt do utworzenia tabel material_groups i material_types
 */

try {
    echo "Łączenie z bazą danych...\n";
    $pdo = require __DIR__ . '/core/db.php';
    
    echo "Czytanie pliku SQL...\n";
    $sql = file_get_contents(__DIR__ . '/create_material_groups.sql');
    
    if ($sql === false) {
        throw new Exception("Nie można odczytać pliku create_material_groups.sql");
    }
    
    echo "Wykonywanie SQL...\n";
    
    // Rozdziel wieloliniowe zapytania
    $statements = [];
    $currentStatement = '';
    
    foreach (explode("\n", $sql) as $line) {
        $line = trim($line);
        
        // Pomiń komentarze
        if (empty($line) || str_starts_with($line, '--')) {
            continue;
        }
        
        $currentStatement .= ' ' . $line;
        
        // Jeśli linia kończy się średnikiem, to jest to koniec zapytania
        if (str_ends_with($line, ';')) {
            $statements[] = trim($currentStatement);
            $currentStatement = '';
        }
    }
    
    // Wykonaj każde zapytanie
    foreach ($statements as $statement) {
        if (!empty($statement)) {
            try {
                $pdo->exec($statement);
                echo "✓ Wykonano: " . substr($statement, 0, 60) . "...\n";
            } catch (PDOException $e) {
                // Ignoruj błędy duplikatów przy INSERT ... ON DUPLICATE KEY
                if (strpos($e->getMessage(), 'Duplicate entry') === false) {
                    throw $e;
                }
                echo "⚠ Pominięto duplikat: " . substr($statement, 0, 60) . "...\n";
            }
        }
    }
    
    echo "\n✅ Tabele materiałów zostały utworzone pomyślnie!\n";
    
    // Sprawdź co zostało utworzone
    $stmt = $pdo->query("SELECT COUNT(*) FROM material_groups");
    $groupCount = $stmt->fetchColumn();
    
    $stmt = $pdo->query("SELECT COUNT(*) FROM material_types");
    $typeCount = $stmt->fetchColumn();
    
    echo "\n📊 Status:\n";
    echo "   - Grup materiałów: $groupCount\n";
    echo "   - Rodzajów materiałów: $typeCount\n";
    
} catch (Exception $e) {
    echo "\n❌ Błąd: " . $e->getMessage() . "\n";
    echo "   Plik: " . $e->getFile() . "\n";
    echo "   Linia: " . $e->getLine() . "\n";
    exit(1);
}
