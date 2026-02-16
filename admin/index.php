<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
/**
 * Strona administratora - zarządzanie kierownikami
 * Dostęp: http://localhost/Rcp/admin/
 * UWAGA: Wymaga autoryzacji administratora
 */

// SPRAWDŹ AUTENTYKACJĘ ADMINA
require_once __DIR__ . '/check_admin.php';

$config = require __DIR__.'/../config.php';
$emailConfig = @include __DIR__.'/../email_config.php';

try {
    $pdo = new PDO(
        "mysql:host={$config['db_host']};dbname={$config['db_name']};charset=utf8mb4",
        $config['db_user'],
        $config['db_pass'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch (PDOException $e) {
    die("Błąd połączenia: " . $e->getMessage());
}

$message = '';
$error = '';

// Obsługa dodawania/aktualizacji kierownika
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $firstName = trim($_POST['first_name'] ?? '');
    $lastName = trim($_POST['last_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $role = (int)($_POST['role'] ?? 2);
    $pin = trim($_POST['pin'] ?? '');
    $canBeManager = isset($_POST['can_be_manager']) && $_POST['can_be_manager'] === '1' ? 1 : 0;

    if ($action === 'add') {
        if (!$firstName || !$lastName) {
            $error = 'Imię i nazwisko wymagane';
        } elseif (!preg_match('/^\d{4}$/', $pin)) {
            $error = 'PIN musi mieć 4 cyfry';
        } else {
            $pinHash = password_hash($pin, PASSWORD_DEFAULT);
            
            try {
                $token = bin2hex(random_bytes(5)); // 10-znakowy token
                $stmt = $pdo->prepare("
                    INSERT INTO managers (first_name, last_name, email, phone, pin_token, role_level, can_be_manager)
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([$firstName, $lastName, $email, $phone, $token, $role, $canBeManager]);
                
                // Wysyłka powiadomień
                $emailSent = false;
                $notifications = [];
                
                // Wyślij email z tokenem jeśli podano adres
                if (!empty($email) && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    require_once __DIR__ . '/../core/email.php';
                    
                    $subject = "🔑 Token dostępu do systemu RCP";
                    $body = "<html><body style='font-family: Arial, sans-serif;'>";
                    $body .= "<h2>Witaj $firstName $lastName!</h2>";
                    $body .= "<p>Otrzymujesz dostęp do systemu RCP (Rejestr Czasu Pracy).</p>";
                    $body .= "<p><strong>Twój token aktywacyjny:</strong></p>";
                    $body .= "<div style='background: #f0f0f0; padding: 15px; border-radius: 8px; font-size: 18px; font-family: monospace;'>";
                    $body .= "<strong>$token</strong>";
                    $body .= "</div>";
                    $body .= "<p><strong>Jak ustawić PIN:</strong></p>";
                    $body .= "<ol>";
                    $body .= "<li>Przejdź do: <a href='https://praca.pref-bet.com/set_pin.html'>https://praca.pref-bet.com/set_pin.html</a></li>";
                    $body .= "<li>Wpisz powyższy token</li>";
                    $body .= "<li>Ustaw swój 4-cyfrowy PIN</li>";
                    $body .= "</ol>";
                    $body .= "<p>Po ustawieniu PIN-u zaloguj się na: <a href='https://praca.pref-bet.com'>https://praca.pref-bet.com</a></p>";
                    $body .= "<hr><small style='color: #666;'>System RCP - Rejestracja Czasu Pracy</small>";
                    $body .= "</body></html>";
                    
                    $result = sendEmail($email, $subject, $body);
                    $emailSent = $result['success'];
                    
                    if ($emailSent) {
                        $notifications[] = "📧 Email wysłany na: <strong>$email</strong>";
                    } else {
                        $notifications[] = "⚠️ Błąd wysyłania emaila: " . $result['message'];
                    }
                }
                
                // Komunikat sukcesu
                if (!empty($notifications)) {
                    $message = "✅ Dodano kierownika: <strong>$firstName $lastName</strong><br>";
                    $message .= implode("<br>", $notifications);
                    $message .= "<br><br>🔑 <strong>TOKEN:</strong> <code style='background:#f0f0f0;padding:5px;'>$token</code>";
                } else {
                    $message = "✅ Dodano kierownika: $firstName $lastName<br>🔑 <strong>TOKEN: $token</strong><br><small>Wyślij kierownikowi token. Ustawi PIN na: <a href='set_pin.html' target='_blank'>set_pin.html</a></small>";
                }
            } catch (PDOException $e) {
                // Obsługa duplikatu
                if ($e->getCode() == 23000 && strpos($e->getMessage(), 'Duplicate entry') !== false) {
                    $error = "❌ Kierownik <strong>$firstName $lastName</strong> już istnieje w systemie. Użyj innego imienia/nazwiska lub usuń istniejący wpis.";
                } else {
                    $error = 'Błąd bazy danych: ' . $e->getMessage();
                }
            }
        }
    } elseif ($action === 'reset') {
        $id = (int)($_POST['id'] ?? 0);
        
        if (!$id) {
            $error = 'ID wymagane';
        } else {
            try {
                $token = bin2hex(random_bytes(5)); // Nowy token (10 znaków)
                $stmt = $pdo->prepare("
                    UPDATE managers
                    SET pin_token = ?, pin_hash = NULL, device_id = NULL, ip_address = NULL
                    WHERE id = ?
                ");
                $stmt->execute([$token, $id]);
                $message = "✅ Zresetowano dostęp<br>🔑 <strong>TOKEN: $token</strong><br><small>Wyślij token kierownikowi. Ustawi PIN na: <a href='set_pin.html' target='_blank'>set_pin.html</a></small>";
            } catch (PDOException $e) {
                $error = 'Błąd: ' . $e->getMessage();
            }
        }
    } elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        
        if (!$id) {
            $error = 'ID wymagane';
        } else {
            try {
                $stmt = $pdo->prepare("DELETE FROM managers WHERE id = ?");
                $stmt->execute([$id]);
                $message = "✅ Usunięto kierownika";
            } catch (PDOException $e) {
                $error = 'Błąd: ' . $e->getMessage();
            }
        }
    } elseif ($action === 'toggle_can_be_manager') {
        $id = (int)($_POST['id'] ?? 0);
        
        if (!$id) {
            $error = 'ID wymagane';
        } else {
            try {
                // Pobierz aktualną wartość
                $stmt = $pdo->prepare("SELECT can_be_manager, first_name, last_name FROM managers WHERE id = ?");
                $stmt->execute([$id]);
                $mgr = $stmt->fetch();
                
                if ($mgr) {
                    // Przełącz wartość
                    $newValue = $mgr['can_be_manager'] ? 0 : 1;
                    $stmt = $pdo->prepare("UPDATE managers SET can_be_manager = ? WHERE id = ?");
                    $stmt->execute([$newValue, $id]);
                    
                    $statusText = $newValue ? 'może być kierownikiem' : 'nie może być kierownikiem';
                    $message = "✅ Zaktualizowano: <strong>{$mgr['first_name']} {$mgr['last_name']}</strong> - $statusText";
                } else {
                    $error = 'Nie znaleziono managera';
                }
            } catch (PDOException $e) {
                $error = 'Błąd: ' . $e->getMessage();
            }
        }
    }
}

// Pobierz listę kierowników
$stmt = $pdo->query("
    SELECT id, first_name, last_name, email, phone, role_level, device_id, ip_address, created_at, pin_token, pin_hash, can_be_manager
    FROM managers
    ORDER BY id
");
$managers = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Zarządzanie kierownikami</title>

    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            border-radius: 12px;
            padding: 30px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
        }
        h1 {
            text-align: center;
            color: #333;
            margin-bottom: 30px;
        }
        .message {
            padding: 12px;
            border-radius: 6px;
            margin-bottom: 20px;
            font-weight: bold;
        }
        .success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .section {
            margin-bottom: 40px;
        }
        .section h2 {
            color: #667eea;
            margin-bottom: 15px;
            padding-bottom: 8px;
            border-bottom: 2px solid #667eea;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }
        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        th {
            background: #667eea;
            color: white;
            font-weight: bold;
        }
        tr:hover {
            background: #f5f5f5;
        }
        form {
            background: #f9f9f9;
            padding: 20px;
            border-radius: 8px;
            margin-top: 15px;
        }
        .form-group {
            margin-bottom: 15px;
        }
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }
        label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
            color: #333;
        }
        input, select {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
        }
        button {
            background: #667eea;
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 6px;
            font-size: 16px;
            cursor: pointer;
            font-weight: bold;
            transition: all 0.3s;
        }
        button:hover {
            background: #5568d3;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(102,126,234,0.4);
        }
        .btn-danger {
            background: #dc3545;
        }
        .btn-danger:hover {
            background: #c82333;
        }
        .btn-warning {
            background: #ffc107;
            color: #000;
        }
        .btn-warning:hover {
            background: #e0a800;
        }
        .inline-form {
            display: inline-block;
            margin-left: 10px;
        }
        .badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: bold;
        }
        .badge-admin {
            background: #dc3545;
            color: white;
        }
        .badge-manager {
            background: #ffc107;
            color: #000;
        }
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        .btn-logout {
            background: #dc3545;
            padding: 8px 16px;
            font-size: 14px;
        }
        .btn-logout:hover {
            background: #c82333;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🔐 Zarządzanie kierownikami</h1>
            <a href="logout.php" class="btn-logout" style="text-decoration: none; display: inline-block;">🚪 Wyloguj</a>
        </div>

        <!-- Status powiadomień -->
        <div style="background: #f8f9fa; padding: 15px; border-radius: 8px; margin-bottom: 20px; border-left: 4px solid #667eea;">
            <strong>📬 Status automatycznych powiadomień:</strong><br>
            <div style="margin-top: 10px;">
        <!-- Status powiadomień -->
        <div style="background: #f8f9fa; padding: 15px; border-radius: 8px; margin-bottom: 20px; border-left: 4px solid #667eea;">
            <strong>📬 Status automatycznych powiadomień:</strong><br>
            <div style="margin-top: 10px;">
                <?php if ($emailConfig && isset($emailConfig['method'])): ?>
                    <span style="color: #28a745; font-weight: bold;">✅ Email: SKONFIGUROWANE</span>
                    <small>(<?= htmlspecialchars($emailConfig['method']) ?>)</small>
                <?php else: ?>
                    <span style="color: #ffc107; font-weight: bold;">⚠️ Email: Domyślne ustawienia</span>
                    <small><a href="../EMAIL_SETUP.md" target="_blank">→ Jak skonfigurować?</a></small>
                <?php endif; ?>
            </div>
            <small style="color: #666; display: block; margin-top: 10px;">
                💡 Token zawsze wyświetli się na ekranie, niezależnie od statusu powiadomień.
            </small>
        </div>

        <?php if ($message): ?>
            <div class="message success"><?= $message ?></div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="message error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <!-- Lista kierowników -->
        <div class="section">
            <h2>📋 Istniejący kierownicy</h2>
            <?php if (count($managers) > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Imię i nazwisko</th>
                            <th>Email</th>
                            <th>Telefon</th>
                            <th>Poziom</th>
                            <th>Status</th>
                            <th>Może być kierownikiem</th>
                            <th>Urządzenie</th>
                            <th>IP</th>
                            <th>Utworzono</th>
                            <th>Akcje</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($managers as $mgr): ?>
                            <tr>
                                <td><?= $mgr['id'] ?></td>
                                <td><?= htmlspecialchars($mgr['first_name'] . ' ' . $mgr['last_name']) ?></td>
                                <td><?= htmlspecialchars($mgr['email'] ?: '—') ?></td>
                                <td><?= htmlspecialchars($mgr['phone'] ?: '—') ?></td>
                                <td>
                                    <?php if ($mgr['role_level'] >= 9): ?>
                                        <span class="badge badge-admin">Admin (<?= $mgr['role_level'] ?>)</span>
                                    <?php else: ?>
                                        <span class="badge badge-manager">Kierownik (<?= $mgr['role_level'] ?>)</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!empty($mgr['pin_hash'])): ?>
                                        <span style="color:#28a745; font-weight:bold;">✅ Aktywny</span>
                                    <?php elseif (!empty($mgr['pin_token'])): ?>
                                        <span style="color:#ffc107; font-weight:bold;">⏳ Czeka na aktywację</span><br>
                                        <small style="color:#666;">Token: <code><?= htmlspecialchars($mgr['pin_token']) ?></code></small>
                                    <?php else: ?>
                                        <span style="color:#dc3545; font-weight:bold;">❌ Nieaktywny</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($mgr['can_be_manager']): ?>
                                        <span style="color:#28a745; font-weight:bold;">✓ Tak</span>
                                    <?php else: ?>
                                        <span style="color:#dc3545; font-weight:bold;">✗ Nie</span>
                                    <?php endif; ?>
                                    <form method="POST" class="inline-form" style="margin-top: 5px;">
                                        <input type="hidden" name="action" value="toggle_can_be_manager">
                                        <input type="hidden" name="id" value="<?= $mgr['id'] ?>">
                                        <button type="submit" style="padding:4px 8px; font-size:12px; background:#6c757d;" title="Przełącz możliwość wyboru jako kierownik">Przełącz</button>
                                    </form>
                                </td>
                                <td><?= $mgr['device_id'] ? substr($mgr['device_id'], 0, 12) . '...' : '—' ?></td>
                                <td><?= $mgr['ip_address'] ?: '—' ?></td>
                                <td><?= $mgr['created_at'] ?></td>
                                <td>
                                    <form method="POST" class="inline-form" onsubmit="return confirm('Wygenerować nowy token?')">
                                        <input type="hidden" name="action" value="reset">
                                        <input type="hidden" name="id" value="<?= $mgr['id'] ?>">
                                        <button type="submit" class="btn-warning" style="padding:8px 12px;">Reset (nowy token)</button>
                                    </form>
                                    
                                    <form method="POST" class="inline-form" onsubmit="return confirm('Na pewno usunąć?')">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?= $mgr['id'] ?>">
                                        <button type="submit" class="btn-danger" style="padding:8px 12px;">Usuń</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p>Brak kierowników w systemie.</p>
            <?php endif; ?>
        </div>

        <!-- Dodaj nowego kierownika -->
        <div class="section">
            <h2>➕ Dodaj nowego kierownika</h2>
            <form method="POST">
                <input type="hidden" name="action" value="add">
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Imię:</label>
                        <input type="text" name="first_name" required placeholder="np. Jan">
                    </div>
                    
                    <div class="form-group">
                        <label>Nazwisko:</label>
                        <input type="text" name="last_name" required placeholder="np. Kowalski">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Email (opcjonalnie - do wysyłki tokenu):</label>
                        <input type="email" name="email" placeholder="kierownik@firma.pl">
                    </div>
                </div>

                <div class="form-group">
                    <label>Poziom uprawnień:</label>
                    <select name="role" required>
                        <option value="2" selected>Kierownik (2)</option>
                        <option value="3">Wawryniuk (3)</option>
                        <option value="4">Kadry Gośka (4)</option>
                        <option value="5">Waga Pawłów (5)</option>
                        <option value="9">Administrator (9)</option>
                    </select>
                </div>

                <div class="form-group">
                    <label style="display: flex; align-items: center; cursor: pointer;">
                        <input type="checkbox" name="can_be_manager" value="1" checked style="width: auto; margin-right: 10px;">
                        <span>Może być wybieralny jako kierownik (do przypisywania pracowników/budów)</span>
                    </label>
                    <small style="color:#666; display: block; margin-top: 5px;">
                        ℹ️ Jeśli zaznaczone, ta osoba będzie dostępna na liście kierowników przy przypisywaniu.
                    </small>
                </div>

                <input type="hidden" name="pin" value="0000">

                <button type="submit">Dodaj kierownika (wygeneruj token)</button>
                
                <p style="margin-top:10px; color:#666; font-size:13px;">
                    ℹ️ <strong>Token zostanie wysłany:</strong><br>
                    📧 Email (jeśli podano adres)<br>
                    💻 Wyświetli się na ekranie (zawsze)
                </p>
            </form>
        </div>
    </div>
</body>
</html>
