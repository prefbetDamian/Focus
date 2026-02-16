<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel Administratora - Logowanie</title>

    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: Arial, sans-serif;
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }
        .login-container {
            background: white;
            border-radius: 12px;
            padding: 40px;
            max-width: 400px;
            width: 100%;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        }
        .logo {
            text-align: center;
            margin-bottom: 30px;
        }
        .logo::before {
            content: "🔐";
            font-size: 60px;
            display: block;
            margin-bottom: 10px;
        }
        h1 {
            text-align: center;
            color: #1e3c72;
            margin-bottom: 10px;
            font-size: 24px;
        }
        .subtitle {
            text-align: center;
            color: #666;
            margin-bottom: 30px;
            font-size: 14px;
        }
        .warning {
            background: #fff3cd;
            border: 1px solid #ffc107;
            color: #856404;
            padding: 12px;
            border-radius: 6px;
            margin-bottom: 20px;
            font-size: 13px;
            text-align: center;
        }
        .form-group {
            margin-bottom: 20px;
        }
        label {
            display: block;
            color: #333;
            margin-bottom: 8px;
            font-weight: bold;
            font-size: 14px;
        }
        input[type="password"] {
            width: 100%;
            padding: 14px;
            border: 2px solid #ddd;
            border-radius: 8px;
            font-size: 16px;
            transition: all 0.3s;
        }
        input[type="password"]:focus {
            outline: none;
            border-color: #1e3c72;
            box-shadow: 0 0 0 3px rgba(30, 60, 114, 0.1);
        }
        button {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s;
        }
        button:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(30, 60, 114, 0.4);
        }
        button:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }
        .message {
            margin-top: 15px;
            padding: 12px;
            border-radius: 6px;
            font-size: 14px;
            display: none;
        }
        .message.error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
            display: block;
        }
        .back-link {
            text-align: center;
            margin-top: 20px;
        }
        .back-link a {
            color: #1e3c72;
            text-decoration: none;
            font-size: 14px;
        }
        .back-link a:hover {
            text-decoration: underline;
        }
        .attempts {
            text-align: center;
            margin-top: 15px;
            font-size: 13px;
            color: #666;
        }
        .locked {
            background: #f8d7da;
            border: 2px solid #dc3545;
            color: #721c24;
            padding: 20px;
            border-radius: 8px;
            text-align: center;
        }
        .locked h2 {
            color: #dc3545;
            margin-bottom: 10px;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="logo"></div>
        <h1>Panel Administratora</h1>
        <p class="subtitle">Dostęp tylko dla uprawnionych</p>
        
        <div class="warning">
            ⚠️ Wszystkie próby logowania są rejestrowane
        </div>

        <div id="loginArea">
            <form id="loginForm">
                <div class="form-group">
                    <label for="password">Hasło administratora:</label>
                    <input type="password" id="password" required autofocus placeholder="Wprowadź hasło">
                </div>
                
                <button type="submit" id="btnLogin">🔓 Zaloguj się</button>
            </form>

            <div class="message" id="message"></div>
            <div class="attempts" id="attempts"></div>
        </div>

        <div class="back-link">
            <a href="../index.html">← Powrót do strony głównej</a>
        </div>
    </div>

    <script>
        const form = document.getElementById('loginForm');
        const password = document.getElementById('password');
        const btnLogin = document.getElementById('btnLogin');
        const message = document.getElementById('message');
        const attempts = document.getElementById('attempts');
        const loginArea = document.getElementById('loginArea');

        // Sprawdź czy konto jest zablokowane
        checkLockout();

        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            
            if (!password.value) {
                showMessage('Wprowadź hasło', 'error');
                return;
            }

            btnLogin.disabled = true;
            btnLogin.textContent = '⏳ Sprawdzanie...';

            try {
                const response = await fetch('admin_auth.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        password: password.value
                    })
                });

                const data = await response.json();

                if (data.success) {
                    showMessage('✅ Autoryzacja pomyślna! Przekierowuję...', 'success');
                    setTimeout(() => {
                        window.location.href = 'index.php';
                    }, 1000);
                } else {
                    showMessage('❌ ' + data.message, 'error');
                    
                    if (data.locked) {
                        showLockout(data.lockout_time);
                    } else if (data.attempts_left !== undefined) {
                        attempts.textContent = `Pozostało prób: ${data.attempts_left}`;
                    }
                    
                    password.value = '';
                    password.focus();
                    btnLogin.disabled = false;
                    btnLogin.textContent = '🔓 Zaloguj się';
                }
            } catch (error) {
                showMessage('❌ Błąd połączenia', 'error');
                btnLogin.disabled = false;
                btnLogin.textContent = '🔓 Zaloguj się';
            }
        });

        async function checkLockout() {
            try {
                const response = await fetch('admin_auth.php?check_lockout=1');
                const data = await response.json();
                
                if (data.locked) {
                    showLockout(data.lockout_time);
                }
            } catch (error) {
                console.error('Błąd sprawdzania blokady:', error);
            }
        }

        function showLockout(seconds) {
            const minutes = Math.ceil(seconds / 60);
            loginArea.innerHTML = `
                <div class="locked">
                    <h2>🔒 Konto zablokowane</h2>
                    <p>Przekroczono limit prób logowania.</p>
                    <p><strong>Dostęp zostanie odblokowany za:</strong></p>
                    <p style="font-size: 24px; margin: 15px 0;">${minutes} minut</p>
                    <p style="font-size: 12px; color: #666;">Skontaktuj się z administratorem systemu.</p>
                </div>
            `;
        }

        function showMessage(text, type) {
            message.textContent = text;
            message.className = 'message ' + type;
        }
    </script>
</body>
</html>
