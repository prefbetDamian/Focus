<?php
require_once __DIR__ . '/../core/auth.php';

// Strona dla kierowników (rola >= 2) i admina (9)
$managerInfo = requireManagerPage(2);

$managerName = is_array($_SESSION['manager'])
    ? $_SESSION['manager']['first_name'] . ' ' . $_SESSION['manager']['last_name']
    : ($_SESSION['manager_name'] ?? $_SESSION['manager'] ?? 'Kierownik');
?>
<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Wnioski urlopowe - RCP System</title>

    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

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
            color: #333;
            font-size: 26px;
        }

        .header .user-name {
            font-weight: bold;
            color: #667eea;
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

        .dash-section {
            margin-top: 10px;
            padding: 20px;
            background: rgba(255, 255, 255, 0.5);
            border-radius: 15px;
            border: 1px solid rgba(102, 126, 234, 0.1);
        }

        .dash-section h3 {
            color: #333;
            margin-bottom: 16px;
            padding-bottom: 8px;
            border-bottom: 2px solid rgba(102, 126, 234, 0.2);
            font-size: 20px;
        }

        .request-card {
            background: white;
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 15px;
            border-left: 4px solid #ffc107;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        .request-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 12px;
        }
        .request-employee {
            font-size: 18px;
            font-weight: 600;
            color: #333;
        }
        .request-dates {
            color: #667eea;
            font-weight: 600;
        }
        .request-info {
            color: #666;
            margin-bottom: 15px;
            line-height: 1.6;
        }
        .request-actions {
            display: flex;
            gap: 10px;
        }
        .btn-approve {
            background: #28a745;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s;
        }
        .btn-approve:hover {
            background: #218838;
            transform: translateY(-2px);
        }
        .btn-reject {
            background: #dc3545;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s;
        }
        .btn-reject:hover {
            background: #c82333;
            transform: translateY(-2px);
        }
        .empty-requests {
            text-align: center;
            padding: 40px;
            color: #999;
        }

        .counter-badge {
            background: #ffc107;
            color: #000;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 14px;
            margin-left: 10px;
        }

        @media (max-width: 768px) {
            .container {
                padding: 20px;
            }

            .header {
                flex-direction: column;
                gap: 10px;
                text-align: center;
            }

            .header h1 {
                font-size: 22px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div>
                <h1>📬 Wnioski urlopowe do akceptacji</h1>
                <div style="color:#666; margin-top:5px;">
                    Witaj, <span class="user-name"><?php echo htmlspecialchars($managerName); ?></span>
                </div>
            </div>
            <a href="dashboard.php" class="back-link">⬅ Powrót do panelu</a>
        </div>

        <section class="dash-section">
            <h3>
                Oczekujące wnioski
                <span id="pendingCount" class="counter-badge">0</span>
            </h3>
            <div id="absence-requests-list"></div>
        </section>
    </div>

    <script>
        async function loadAbsenceRequests() {
            try {
                const response = await fetch('../absence_requests_api.php?action=list&status=pending');
                const result = await response.json();

                const container = document.getElementById('absence-requests-list');
                const countBadge = document.getElementById('pendingCount');

                if (!container || !countBadge) return;

                if (result.success && Array.isArray(result.requests) && result.requests.length > 0) {
                    countBadge.textContent = result.requests.length;
                    container.innerHTML = result.requests.map(req => `
                        <div class="request-card" id="request-${req.id}">
                            <div class="request-header">
                                <div class="request-employee">
                                    👤 ${req.first_name} ${req.last_name}
                                </div>
                                <div class="request-dates">
                                    ${formatDate(req.start_date)} - ${formatDate(req.end_date)}
                                    <span style="color: #999; font-size: 14px;">(${calculateDays(req.start_date, req.end_date)} dni)</span>
                                </div>
                            </div>
                            <div class="request-info">
                                <strong>Typ:</strong> ${getTypeIcon(req.type)} ${req.type}<br>
                                ${req.reason ? `<strong>Powód:</strong> ${req.reason}<br>` : ''}
                                <strong>Złożono:</strong> ${formatDateTime(req.requested_at)}
                            </div>
                            <div class="request-actions">
                                <button class="btn-approve" onclick="approveRequest(${req.id})">
                                    ✅ Akceptuj
                                </button>
                                <button class="btn-reject" onclick="rejectRequest(${req.id})">
                                    ❌ Odrzuć
                                </button>
                            </div>
                        </div>
                    `).join('');
                } else {
                    countBadge.textContent = '0';
                    container.innerHTML = '<div class="empty-requests">✅ Brak oczekujących wniosków</div>';
                }
            } catch (error) {
                console.error('Error loading absence requests:', error);
                const container = document.getElementById('absence-requests-list');
                if (container) {
                    container.innerHTML = '<div class="empty-requests">❌ Błąd ładowania wniosków</div>';
                }
            }
        }

        async function approveRequest(requestId) {
            const notes = prompt('Notatka dla pracownika (opcjonalnie):');
            if (notes === null) return; // Cancelled

            try {
                const response = await fetch('../absence_requests_api.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        action: 'approve',
                        request_id: requestId,
                        notes: notes
                    })
                });

                const result = await response.json();

                if (result.success) {
                    alert('✅ Wniosek zaakceptowany! Nieobecności zostały dodane do kalendarza.');
                    loadAbsenceRequests();
                } else {
                    alert('❌ ' + (result.message || 'Błąd podczas akceptacji'));
                }
            } catch (error) {
                alert('❌ Błąd połączenia');
                console.error(error);
            }
        }

        async function rejectRequest(requestId) {
            const notes = prompt('Powód odrzucenia (wymagane):');
            if (!notes || notes.trim() === '') {
                alert('Musisz podać powód odrzucenia wniosku');
                return;
            }

            if (!confirm('Czy na pewno chcesz odrzucić ten wniosek?')) {
                return;
            }

            try {
                const response = await fetch('../absence_requests_api.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        action: 'reject',
                        request_id: requestId,
                        notes: notes
                    })
                });

                const result = await response.json();

                if (result.success) {
                    alert('Wniosek odrzucony');
                    loadAbsenceRequests();
                } else {
                    alert('❌ ' + (result.message || 'Błąd podczas odrzucania'));
                }
            } catch (error) {
                alert('❌ Błąd połączenia');
                console.error(error);
            }
        }

        function formatDate(dateStr) {
            const d = new Date(dateStr);
            return d.toLocaleDateString('pl-PL');
        }

        function formatDateTime(dateStr) {
            const d = new Date(dateStr);
            return d.toLocaleString('pl-PL');
        }

        function calculateDays(start, end) {
            const s = new Date(start);
            const e = new Date(end);
            return Math.ceil((e - s) / (1000 * 60 * 60 * 24)) + 1;
        }

        function getTypeIcon(type) {
            const icons = {
                'urlop': '🏖️',
                'L4': '🏥',
                'inny': '📄'
            };
            return icons[type] || '📄';
        }

        // Start
        loadAbsenceRequests();
        // Częstsze odświeżanie listy wniosków urlopowych: co 15 sekund
        setInterval(loadAbsenceRequests, 15000);
    </script>
</body>
</html>
