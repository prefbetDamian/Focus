<?php
/**
 * CORE: Połączenie z bazą danych
 * Używane przez wszystkie moduły systemu
 */

$config = require __DIR__.'/../config.php';


$pdo = new PDO(
    "mysql:host={$config['db_host']};port={$config['db_port']};dbname={$config['db_name']};charset=utf8mb4",
    $config['db_user'],
    $config['db_pass'],
    [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]
);

return $pdo;
