<?php
/**
 * Funkcje wysyłania emaili przez SMTP z użyciem PHPMailer
 */

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Obsługa różnych struktur folderów (lokalne vs hosting)
$phpmailerPath = __DIR__ . '/../vendor/phpmailer/phpmailer/src';
if (!is_dir($phpmailerPath)) {
    // Próbuj różnych wariantów nazw (duże/małe litery)
    $alternatives = [
        __DIR__ . '/../vendor/PHPMailer-6.9.1/src',  // Duże M
        __DIR__ . '/../vendor/PHPmailer-6.9.1/src',  // Małe m
        __DIR__ . '/../vendor/phpmailer-6.9.1/src',  // Małe p i m
    ];
    
    foreach ($alternatives as $alt) {
        if (is_dir($alt)) {
            $phpmailerPath = $alt;
            break;
        }
    }
}

require_once $phpmailerPath . '/Exception.php';
require_once $phpmailerPath . '/PHPMailer.php';
require_once $phpmailerPath . '/SMTP.php';

/**
 * Wysyła email przez SMTP
 * 
 * @param string $to Email odbiorcy
 * @param string $subject Temat wiadomości
 * @param string $body Treść HTML
 * @return array ['success' => bool, 'message' => string]
 */
if (!function_exists('sendEmail')) {
    function sendEmail($to, $subject, $body) {
    $config = require __DIR__ . '/../email_config.php';
    
    // Sprawdź czy wysyłanie jest włączone
    if (empty($config['method']) || $config['method'] !== 'smtp') {
        error_log("Email config issue - method: " . ($config['method'] ?? 'NOT SET'));
        return ['success' => false, 'message' => 'Wysyłanie emaili wymaga ustawienia method=smtp w email_config.php'];
    }
    
    $mail = new PHPMailer(true);
    
    try {
        // Konfiguracja serwera SMTP
        if ($config['debug']) {
            $mail->SMTPDebug = 2; // Szczegółowe logi
        }
        
        $mail->isSMTP();
        $mail->Host       = $config['smtp']['host'];
        $mail->SMTPAuth   = $config['smtp']['auth'];
        $mail->Username   = $config['smtp']['username'];
        $mail->Password   = $config['smtp']['password'];
        $mail->SMTPSecure = $config['smtp']['encryption'];
        $mail->Port       = $config['smtp']['port'];
        $mail->CharSet    = 'UTF-8';
        
        // Nadawca
        $mail->setFrom($config['from_email'], $config['from_name']);
        
        // Odbiorca
        $mail->addAddress($to);
        
        // Treść
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $body;
        $mail->AltBody = strip_tags($body);
        
        $mail->send();
        
        return ['success' => true, 'message' => 'Email wysłany pomyślnie'];
        
    } catch (Exception $e) {
        error_log("Email error: {$mail->ErrorInfo}");
        return ['success' => false, 'message' => "Błąd wysyłania: {$mail->ErrorInfo}"];
    }
    }
}

/**
 * Wysyła dane logowania do nowego kierownika
 * 
 * @param string $email Email kierownika
 * @param string $name Imię i nazwisko
 * @param string $login Login do panelu
 * @param string $password Hasło (tylko przy pierwszym wysyłaniu!)
 * @return array ['success' => bool, 'message' => string]
 */
if (!function_exists('sendManagerCredentials')) {
    function sendManagerCredentials($email, $name, $login, $password) {
    $subject = "Dostęp do panelu RCP - Dane logowania";
    
    $body = "
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; background: #f9f9f9; }
            .header { background: #667eea; color: white; padding: 20px; text-align: center; border-radius: 10px 10px 0 0; }
            .content { background: white; padding: 30px; border-radius: 0 0 10px 10px; }
            .credentials { background: #f0f2ff; padding: 15px; border-left: 4px solid #667eea; margin: 20px 0; }
            .credentials strong { color: #667eea; }
            .footer { text-align: center; margin-top: 20px; font-size: 12px; color: #666; }
            .warning { background: #fff3cd; padding: 10px; border-left: 4px solid #ffc107; margin: 15px 0; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>System RCP</h1>
                <p>Rejestr Czasu Pracy</p>
            </div>
            <div class='content'>
                <h2>Witaj, {$name}!</h2>
                <p>Zostałeś dodany jako kierownik w systemie RCP. Poniżej znajdują się Twoje dane logowania:</p>
                
                <div class='credentials'>
                    <p><strong>Adres panelu:</strong> https://praca.pref-bet.com/panel/login.html</p>
                    <p><strong>Login:</strong> {$login}</p>
                    <p><strong>Hasło:</strong> {$password}</p>
                </div>
                
                <div class='warning'>
                    <strong>⚠️ Ważne:</strong> Zmień hasło po pierwszym logowaniu w Ustawieniach panelu.
                </div>
                
                <p>Jeśli masz pytania, skontaktuj się z administratorem systemu.</p>
            </div>
            <div class='footer'>
                <p>© 2026 System RCP - Automatyczna wiadomość, nie odpowiadaj na tego maila.</p>
            </div>
        </div>
    </body>
    </html>
    ";
    
    return sendEmail($email, $subject, $body);
    }
}
