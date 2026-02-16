<?php
/**
 * Skrypt aktualizacji tabeli wz_scans pod nowy workflow WZ
 * Dodaje kolumny operator_id, approving_manager_id, machine_session_id
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

    // 1. Dodanie kolumny operator_id
    echo "1. Dodawanie kolumny operator_id...\n";
    try {
        $pdo->exec("ALTER TABLE `wz_scans` ADD COLUMN `operator_id` INT(11) NULL COMMENT 'Operator / odbiorca (z employees)' AFTER `manager_id`");
        echo "   ✓ Dodano kolumnę operator_id\n";
    } catch (PDOException $e) {
        echo "   ⚠ Kolumna operator_id już istnieje lub błąd: " . $e->getMessage() . "\n";
    }

    // 2. Index i FK dla operator_id
    echo "\n2. Dodawanie indeksu i klucza obcego dla operator_id...\n";
    try {
        $pdo->exec("ALTER TABLE `wz_scans` ADD KEY `idx_operator` (`operator_id`)");
        echo "   ✓ Dodano index idx_operator\n";
    } catch (PDOException $e) {
        echo "   ⚠ Index idx_operator już istnieje lub błąd: " . $e->getMessage() . "\n";
    }

    try {
        $pdo->exec("ALTER TABLE `wz_scans` ADD CONSTRAINT `fk_wz_scans_operator` FOREIGN KEY (`operator_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE");
        echo "   ✓ Dodano fk_wz_scans_operator\n";
    } catch (PDOException $e) {
        echo "   ⚠ Constraint fk_wz_scans_operator już istnieje lub błąd: " . $e->getMessage() . "\n";
    }

    // 3. Dodanie kolumny approving_manager_id
    echo "\n3. Dodawanie kolumny approving_manager_id...\n";
    try {
        $pdo->exec("ALTER TABLE `wz_scans` ADD COLUMN `approving_manager_id` INT(11) NULL COMMENT 'Manager, który ostatecznie zatwierdził WZ' AFTER `operator_id`");
        echo "   ✓ Dodano kolumnę approving_manager_id\n";
    } catch (PDOException $e) {
        echo "   ⚠ Kolumna approving_manager_id już istnieje lub błąd: " . $e->getMessage() . "\n";
    }

    // 4. Index i FK dla approving_manager_id
    echo "\n4. Dodawanie indeksu i klucza obcego dla approving_manager_id...\n";
    try {
        $pdo->exec("ALTER TABLE `wz_scans` ADD KEY `idx_approving_manager` (`approving_manager_id`)");
        echo "   ✓ Dodano index idx_approving_manager\n";
    } catch (PDOException $e) {
        echo "   ⚠ Index idx_approving_manager już istnieje lub błąd: " . $e->getMessage() . "\n";
    }

    try {
        $pdo->exec("ALTER TABLE `wz_scans` ADD CONSTRAINT `fk_wz_scans_approving_manager` FOREIGN KEY (`approving_manager_id`) REFERENCES `managers` (`id`) ON DELETE SET NULL");
        echo "   ✓ Dodano fk_wz_scans_approving_manager\n";
    } catch (PDOException $e) {
        echo "   ⚠ Constraint fk_wz_scans_approving_manager już istnieje lub błąd: " . $e->getMessage() . "\n";
    }

    // 5. Aktualizacja kolumny status pod nowy workflow
    echo "\n5. Aktualizacja kolumny status pod nowy workflow...\n";
    try {
        $pdo->exec("ALTER TABLE `wz_scans` MODIFY `status` VARCHAR(32) NOT NULL DEFAULT 'waiting_operator' COMMENT 'Status workflow WZ'");
        echo "   ✓ Zmieniono kolumnę status na VARCHAR(32) z domyślną wartością waiting_operator\n";
    } catch (PDOException $e) {
        echo "   ⚠ Nie udało się zmodyfikować kolumny status lub już zmodyfikowana: " . $e->getMessage() . "\n";
    }

    // 6. Dodanie kolumny machine_session_id (opcjonalne powiązanie z pracą maszyny)
    echo "\n6. Dodawanie kolumny machine_session_id...\n";
    try {
        $pdo->exec("ALTER TABLE `wz_scans` ADD COLUMN `machine_session_id` INT(11) NULL COMMENT 'Powiązana sesja maszyny (z work_sessions)' AFTER `approving_manager_id`");
        echo "   ✓ Dodano kolumnę machine_session_id\n";
    } catch (PDOException $e) {
        echo "   ⚠ Kolumna machine_session_id już istnieje lub błąd: " . $e->getMessage() . "\n";
    }

    // 7. Index i FK dla machine_session_id
    echo "\n7. Dodawanie indeksu i klucza obcego dla machine_session_id...\n";
    try {
        $pdo->exec("ALTER TABLE `wz_scans` ADD KEY `idx_machine_session` (`machine_session_id`)");
        echo "   ✓ Dodano index idx_machine_session\n";
    } catch (PDOException $e) {
        echo "   ⚠ Index idx_machine_session już istnieje lub błąd: " . $e->getMessage() . "\n";
    }

    try {
        $pdo->exec("ALTER TABLE `wz_scans` ADD CONSTRAINT `fk_wz_scans_machine_session` FOREIGN KEY (`machine_session_id`) REFERENCES `work_sessions` (`id`) ON DELETE SET NULL");
        echo "   ✓ Dodano fk_wz_scans_machine_session\n";
    } catch (PDOException $e) {
        echo "   ⚠ Constraint fk_wz_scans_machine_session już istnieje lub błąd: " . $e->getMessage() . "\n";
    }

    echo "\n✅ Aktualizacja workflow WZ zakończona pomyślnie!\n\n";
    echo "Sprawdzenie struktury tabeli wz_scans:\n";

    $stmt = $pdo->query("DESCRIBE wz_scans");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($columns as $col) {
        echo "  - {$col['Field']}: {$col['Type']} " . ($col['Null'] === 'YES' ? 'NULL' : 'NOT NULL') . "\n";
    }

} catch (PDOException $e) {
    echo "❌ Błąd: " . $e->getMessage() . "\n";
    exit(1);
}
