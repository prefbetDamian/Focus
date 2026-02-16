<?php
/**
 * Skrypt aktualizacji tabeli wz_scans
 * Dodaje kolumny material_type i material_quantity na potrzeby dokumentów WZ
 */

$config = require __DIR__ . '/config.php';

try {
        $pdo = new PDO(
		"mysql:host={$config['db_host']};port={$config['db_port']};dbname={$config['db_name']};charset=utf8mb4",
        $config['db_user'],
        $config['db_pass'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    echo "Połączono z bazą danych.\n\n";

    echo "1. Dodawanie kolumny material_type...\n";
    try {
        $pdo->exec("ALTER TABLE `wz_scans` ADD COLUMN `material_type` VARCHAR(255) NULL COMMENT 'Rodzaj materiału' AFTER `document_number`");
        echo "   ✓ Dodano kolumnę material_type\n";
    } catch (PDOException $e) {
        echo "   ⚠ Kolumna material_type już istnieje lub błąd: " . $e->getMessage() . "\n";
    }

    echo "\n2. Dodawanie kolumny material_quantity...\n";
    try {
        $pdo->exec("ALTER TABLE `wz_scans` ADD COLUMN `material_quantity` DECIMAL(10,2) NULL COMMENT 'Ilość materiału' AFTER `material_type`");
        echo "   ✓ Dodano kolumnę material_quantity\n";
    } catch (PDOException $e) {
        echo "   ⚠ Kolumna material_quantity już istnieje lub błąd: " . $e->getMessage() . "\n";
    }

    echo "\nStruktura tabeli wz_scans po zmianach:\n";
    $stmt = $pdo->query("DESCRIBE wz_scans");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($columns as $col) {
        echo "  - {$col['Field']}: {$col['Type']} " . ($col['Null'] === 'YES' ? 'NULL' : 'NOT NULL') . "\n";
    }

    echo "\n✅ Aktualizacja zakończona.\n";
} catch (PDOException $e) {
    echo "❌ Błąd: " . $e->getMessage() . "\n";
    exit(1);
}
