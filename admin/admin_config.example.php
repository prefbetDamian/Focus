<?php
/**
 * Przykładowa konfiguracja dostępu do panelu administratora
 * 
 * INSTRUKCJA:
 * 1. Skopiuj ten plik jako admin_config.php
 * 2. Wygeneruj swoje hasło: echo hash('sha256', 'TwojeHaslo123!');
 * 3. Wklej wygenerowany hash poniżej
 * 4. NIE commituj pliku admin_config.php do repozytorium!
 * 
 * WAŻNE: Zmień hasło przed uruchomieniem w produkcji!
 */

return [
    // Hasło dostępu do panelu admina (SHA-256)
    // Domyślne hasło: "Admin2026!" - ZMIEŃ TO!
    // Wygeneruj: echo hash('sha256', 'TwojeNoweHaslo');
    'admin_password_hash' => '84a78a9f54bf57d7d309d659e73526d70eb0e78de6fb88f1e1508f64ffad2879',
    
    // Dozwolone adresy IP (puste = wszystkie)
    // W produkcji wpisz swoje IP dla dodatkowego bezpieczeństwa
    // Przykład: ['127.0.0.1', '192.168.1.100']
    'allowed_ips' => [],
    
    // Maksymalna liczba nieudanych prób logowania
    'max_login_attempts' => 5,
    
    // Czas blokady po przekroczeniu limitu (w sekundach)
    'lockout_duration' => 1800, // 30 minut
    
    // Czas ważności sesji admina (w sekundach)
    'session_timeout' => 3600, // 60 minut
    
    // Wymuszenie HTTPS (ustaw true w produkcji!)
    'force_https' => false,
    
    // Logowanie prób dostępu
    'log_access' => true,
];
