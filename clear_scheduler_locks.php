<?php
/**
 * Czyści pliki lock schedulera (dla testowania)
 * 
 * ZABEZPIECZENIE: Wymagaj logowania administratora
 */

declare(strict_types=1);

// WYMAGANE zabezpieczenie - tylko admin może czyścić locki
require_once __DIR__ . '/admin/check_admin.php';

header('Content-Type: application/json');

$lockDir = __DIR__ . '/cron/locks';

if (!is_dir($lockDir)) {
    echo json_encode(['success' => false, 'error' => 'Katalog locks nie istnieje']);
    exit;
}

$files = glob($lockDir . '/*.lock');
$cleared = 0;

foreach ($files as $file) {
    if (unlink($file)) {
        $cleared++;
    }
}

echo json_encode([
    'success' => true,
    'cleared_files' => $cleared,
    'message' => "Wyczyszczono $cleared plików lock"
]);
