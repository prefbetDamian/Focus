<?php
require_once __DIR__ . '/../core/auth.php';

// Wymagany zalogowany kierownik (rola 2) lub administrator (rola 9)
$managerInfo = requireManagerPage(2);
?>
<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sesje do akceptacji - RCP System</title>

    <script>
        window.USER_ROLE = <?= (int)($_SESSION['role_level'] ?? 0) ?>;
    </script>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        .container {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 20px;
            padding: 30px;
            max-width: 900px;
            margin: 0 auto;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        }
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 2px solid rgba(102, 126, 234, 0.2);
        }
        .header h1 {
            font-size: 24px;
            color: #333;
        }
        .back-link {
            text-decoration: none;
            padding: 10px 18px;
            border-radius: 10px;
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
            color: #fff;
            font-weight: 600;
            font-size: 14px;
        }
        .session-card {
            background: white;
            padding: 16px 18px;
            border-radius: 12px;
            margin-bottom: 12px;
            border-left: 4px solid #667eea;
            box-shadow: 0 2px 6px rgba(0,0,0,0.06);
        }
        .session-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 8px;
            gap: 8px;
        }
        .session-employee {
            font-weight: 600;
            color: #333;
        }
        .session-site {
            font-size: 13px;
            color: #555;
            background: #eef2ff;
            padding: 4px 10px;
            border-radius: 999px;
        }
        .session-meta {
            font-size: 13px;
            color: #666;
            margin-bottom: 8px;
        }
        .session-meta span {
            margin-right: 10px;
        }
        .session-comment {
            width: 100%;
            margin-top: 6px;
            margin-bottom: 8px;
            font-size: 13px;
            min-height: 40px;
            resize: vertical;
        }
        .session-actions {
            display: flex;
            gap: 8px;
        }
        .session-empty {
            text-align: center;
            padding: 24px;
            color: #999;
            font-size: 14px;
        }
        .btn-approve {
            background: #28a745;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
        }
        .btn-reject {
            background: #dc3545;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>⏱ Sesje do akceptacji</h1>
            <a href="#" class="back-link" onclick="goBackToEntry('dashboard.php'); return false;">← Wróć do panelu</a>
        </div>
        <div id="pendingSessionsList"></div>
    </div>

    <script src="dashboard.js"></script>
    <script>
        function goBackToEntry(fallbackUrl) {
            if (document.referrer) {
                window.location.href = document.referrer;
            } else {
                window.location.href = fallbackUrl;
            }
        }

        // Załaduj listę sesji, jeśli funkcja jest dostępna
        if (typeof loadPendingSessions === 'function') {
            loadPendingSessions();
        }
    </script>
</body>
</html>
