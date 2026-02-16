<?php
/**
 * Skrypt aktualizacji tabeli wz_scans
 * Dodaje kolumnę manager_id dla obsługi dokumentów tworzonych przez managerów
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
    
    // 1. Usuń stary constraint
    echo "1. Usuwanie starego foreign key constraint...\n";
    try {
        $pdo->exec("ALTER TABLE `wz_scans` DROP FOREIGN KEY `fk_wz_scans_employee`");
        echo "   ✓ Usunięto fk_wz_scans_employee\n";
    } catch (PDOException $e) {
        echo "   ⚠ Constraint już nie istnieje lub błąd: " . $e->getMessage() . "\n";
    }
    
    // 2. Zmień employee_id na nullable
    echo "\n2. Zmiana employee_id na nullable...\n";
    $pdo->exec("ALTER TABLE `wz_scans` MODIFY `employee_id` INT(11) NULL COMMENT 'Kto skanował (z employees) - opcjonalne'");
    echo "   ✓ employee_id jest teraz nullable\n";
    
    // 3. Dodaj kolumnę manager_id
    echo "\n3. Dodawanie kolumny manager_id...\n";
    try {
        $pdo->exec("ALTER TABLE `wz_scans` ADD COLUMN `manager_id` INT(11) NULL COMMENT 'Manager który skanował (z managers)' AFTER `employee_id`");
        echo "   ✓ Dodano kolumnę manager_id\n";
    } catch (PDOException $e) {
        echo "   ⚠ Kolumna już istnieje lub błąd: " . $e->getMessage() . "\n";
    }
    
    // 4. Dodaj index dla manager_id
    echo "\n4. Dodawanie indexu dla manager_id...\n";
    try {
        $pdo->exec("ALTER TABLE `wz_scans` ADD KEY `idx_manager` (`manager_id`)");
        echo "   ✓ Dodano index idx_manager\n";
    } catch (PDOException $e) {
        echo "   ⚠ Index już istnieje lub błąd: " . $e->getMessage() . "\n";
    }
    
    // 5. Dodaj nowe constraints
    echo "\n5. Dodawanie nowych foreign key constraints...\n";
    try {
        $pdo->exec("ALTER TABLE `wz_scans` ADD CONSTRAINT `fk_wz_scans_employee` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE");
        echo "   ✓ Dodano fk_wz_scans_employee\n";
    } catch (PDOException $e) {
        echo "   ⚠ Constraint już istnieje lub błąd: " . $e->getMessage() . "\n";
    }
    
    try {
        $pdo->exec("ALTER TABLE `wz_scans` ADD CONSTRAINT `fk_wz_scans_manager` FOREIGN KEY (`manager_id`) REFERENCES `managers` (`id`) ON DELETE CASCADE");
        echo "   ✓ Dodano fk_wz_scans_manager\n";
    } catch (PDOException $e) {
        echo "   ⚠ Constraint już istnieje lub błąd: " . $e->getMessage() . "\n";
    }
    
    echo "\n✅ Aktualizacja zakończona pomyślnie!\n\n";
    echo "Sprawdzenie struktury tabeli:\n";
    
    $stmt = $pdo->query("DESCRIBE wz_scans");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($columns as $col) {
        echo "  - {$col['Field']}: {$col['Type']} " . ($col['Null'] === 'YES' ? 'NULL' : 'NOT NULL') . "\n";
    }
    
} catch (PDOException $e) {
    echo "❌ Błąd: " . $e->getMessage() . "\n";
    exit(1);
}
