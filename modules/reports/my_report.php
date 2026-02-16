<?php
/**
 * Moduł Raporty - Mój raport
 * (W przygotowaniu)
 */

require_once __DIR__.'/../../core/session.php';
require_once __DIR__.'/../../core/auth.php';

$user = requireUser();
?>
<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mój raport czasu pracy</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }
        .container {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 20px;
            padding: 40px;
            max-width: 700px;
            width: 100%;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            text-align: center;
        }
        h1 {
            color: #333;
            margin-bottom: 20px;
            font-size: 28px;
        }
        .icon {
            font-size: 80px;
            margin-bottom: 20px;
        }
        .info {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            line-height: 1.6;
            color: #555;
            text-align: left;
        }
        .stats-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
            gap: 12px;
            margin: 10px 0 5px;
        }
        .stats-card {
            background: #f3f6ff;
            padding: 12px;
            border-radius: 10px;
            font-size: 13px;
            text-align: left;
        }
        .stats-card b {
            display: block;
            font-size: 15px;
            margin-top: 4px;
        }
        .filters {
            margin: 20px 0;
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            align-items: center;
            justify-content: center;
        }
        .filters label {
            font-size: 14px;
            color: #555;
        }
        .filters input[type="month"] {
            padding: 8px 10px;
            border-radius: 8px;
            border: 1px solid #ccc;
            font-size: 14px;
        }
        .filters button {
            padding: 8px 18px;
            border-radius: 999px;
            border: none;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: #fff;
            font-weight: 600;
            cursor: pointer;
        }
        .summary-box {
            margin: 15px 0 10px;
            padding: 12px 16px;
            border-radius: 10px;
            background: #e7f3ff;
            color: #155724;
            font-weight: 600;
            text-align: left;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
            font-size: 14px;
        }
        th, td {
            padding: 8px 10px;
            border-bottom: 1px solid #ddd;
            text-align: left;
        }
        th {
            background: #f1f3ff;
            font-weight: 600;
        }
        tbody tr:nth-child(even) {
            background: #fafafa;
        }
        .empty {
            text-align: center;
            padding: 20px 10px;
            color: #888;
        }
        .btn-back {
            background: #6c757d;
            color: white;
            border: none;
            border-radius: 10px;
            padding: 15px 30px;
            font-size: 16px;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
        }
        .btn-back:hover {
            background: #5a6268;
        }
        .calendar-grid {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 6px;
            margin-top: 20px;
        }
        .day {
            padding: 10px;
            border-radius: 8px;
            text-align: center;
            font-size: 13px;
            background: #f3f4f6;
        }
        .day-header {
            font-weight: 600;
            background: transparent;
        }
        .day[data-hours="0"] {
            background: #e5e7eb;
            color: #6b7280;
        }
        .day[data-level="mid"] {
            background: #fde68a;
        }
        .day[data-level="high"] {
            background: #bbf7d0;
        }
        .day[data-level="absence"] {
            background: #bfdbfe;
            color: #1e40af;
            font-weight: 600;
        }
        .day[data-level="absence-pending"] {
            background: #fef3c7;
            color: #92400e;
            font-weight: 600;
            border: 2px dashed #f59e0b;
        }
        .day[data-level="absence-rejected"] {
            background: #fecaca;
            color: #991b1b;
            font-weight: 600;
            text-decoration: line-through;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="icon">📊</div>
        <h1>Mój raport czasu pracy</h1>
        
        <div class="info">
            <strong>Twoje statystyki miesięczne</strong><br><br>
            W tym miejscu możesz sprawdzić, ile łącznie czasu przepracowałeś w wybranym miesiącu,
            z podziałem na budowy. Zestawienie obejmuje tylko sesje zatwierdzone przez kierownika
            (status <em>OK</em> lub <em>MANUAL</em>), dzięki czemu widzisz realnie rozliczone godziny.
        </div>

        <div class="filters">
            <label for="monthPicker">Wybierz miesiąc:</label>
            <input type="month" id="monthPicker">
            <button type="button" onclick="loadStats()">🔍 Pokaż statystyki</button>
        </div>

        <div id="summaryCards" class="stats-cards" style="display:none;">
            <div class="stats-card">
                Łącznie w miesiącu
                <b id="cardTotalTime">–</b>
            </div>
            <div class="stats-card">
                Dni z pracą
                <b id="cardWorkDays">–</b>
            </div>
            <div class="stats-card">
                Dni urlopowe
                <b id="cardAbsenceDays">–</b>
            </div>
            <div class="stats-card">
                Liczba sesji
                <b id="cardSessions">–</b>
            </div>
            <div class="stats-card">
                Średnio dziennie
                <b id="cardAvgDaily">–</b>
            </div>
            <div class="stats-card">
                Najdłuższy dzień
                <b id="cardLongestDay">–</b>
            </div>
            <div class="stats-card">
                Najdłuższa sesja
                <b id="cardLongestSession">–</b>
            </div>
        </div>

        <div id="summary" class="summary-box" style="display:none;"></div>

        <div id="calendar" class="calendar-grid" style="display:none;"></div>

        <table id="statsTable" style="display:none;">
            <thead>
                <tr>
                    <th>Budowa</th>
                    <th>Liczba sesji</th>
                    <th>Łączny czas</th>
                </tr>
            </thead>
            <tbody id="statsBody"></tbody>
        </table>

        <div id="emptyInfo" class="empty" style="display:none;">
            Brak zatwierdzonych sesji w wybranym miesiącu.
        </div>

        <a href="#" class="btn-back" onclick="goBackToEntry('../../panel.php'); return false;">
            ⬅️ Powrót do panelu
        </a>
    </div>
    <script>
        function goBackToEntry(fallbackUrl) {
            if (document.referrer) {
                window.location.href = document.referrer;
            } else {
                window.location.href = fallbackUrl;
            }
        }

        function setDefaultMonth() {
            const input = document.getElementById('monthPicker');
            if (!input) return;
            const now = new Date();
            const y = now.getFullYear();
            const m = String(now.getMonth() + 1).padStart(2, '0');
            input.value = `${y}-${m}`;
        }

        async function loadStats() {
            const monthInput = document.getElementById('monthPicker');
            const summary    = document.getElementById('summary');
            const summaryCards = document.getElementById('summaryCards');
            const calendarEl = document.getElementById('calendar');
            const table      = document.getElementById('statsTable');
            const tbody      = document.getElementById('statsBody');
            const emptyInfo  = document.getElementById('emptyInfo');

            if (!monthInput) return;
            const month = monthInput.value;

            if (!month) {
                alert('Wybierz miesiąc.');
                return;
            }

            summary.style.display   = 'none';
            if (summaryCards) summaryCards.style.display = 'none';
            if (calendarEl) {
                calendarEl.style.display = 'none';
                calendarEl.innerHTML = '';
            }
            table.style.display     = 'none';
            emptyInfo.style.display = 'none';
            tbody.innerHTML         = '';

            try {
                const res = await fetch(`my_report_api.php?month=${encodeURIComponent(month)}`);
                const data = await res.json();

                if (!data.success) {
                    alert(data.message || 'Nie udało się pobrać statystyk.');
                    return;
                }

                const stats      = Array.isArray(data.stats) ? data.stats : [];
                const daysData   = Array.isArray(data.days) ? data.days : [];
                const summaryData = data.summary || null;

                // Sprawdź czy są jakiekolwiek dane (praca lub urlop)
                const hasWorkDays = stats.length > 0;
                const hasAbsenceDays = summaryData && summaryData.absence_days > 0;

                if (!hasWorkDays && !hasAbsenceDays) {
                    emptyInfo.style.display = 'block';
                    return;
                }

                // Kafelki podsumowania (Etap 1)
                if (summaryData && summaryCards) {
                    document.getElementById('cardTotalTime').textContent      = summaryData.total_time       || data.overall_time || '00:00';
                    document.getElementById('cardWorkDays').textContent      = summaryData.work_days        ?? '0';
                    document.getElementById('cardAbsenceDays').textContent   = summaryData.absence_days     ?? '0';
                    document.getElementById('cardSessions').textContent      = summaryData.sessions         ?? '0';
                    document.getElementById('cardAvgDaily').textContent      = summaryData.avg_daily_time   || '00:00';
                    document.getElementById('cardLongestDay').textContent    = summaryData.longest_day_time || '00:00';
                    document.getElementById('cardLongestSession').textContent= summaryData.longest_session  || '00:00';

                    summaryCards.style.display = 'grid';
                }

                // Stare podsumowanie tekstowe – zostawiamy jako skrót
                summary.textContent = `Łącznie w miesiącu: ${(summaryData && summaryData.total_time) || data.overall_time} (rozliczone godziny)`;
                summary.style.display = 'block';

                // Kalendarz miesiąca - pokaż jeśli są dni pracy LUB urlopy
                if (calendarEl && (daysData.length > 0 || hasAbsenceDays)) {
                    renderCalendar(calendarEl, data.year, data.monthNum, daysData, data.absence_requests || []);
                }

                // Tabela - pokaż tylko jeśli są sesje pracy
                if (stats.length > 0) {
                    stats.forEach(row => {
                        const tr = document.createElement('tr');
                        const site = row.site_name || '—';
                        const count = row.sessions_count || 0;
                        const total = row.total_time || '00:00:00';

                        tr.innerHTML = `
                            <td>${site}</td>
                            <td>${count}</td>
                            <td>${total}</td>
                        `;
                        tbody.appendChild(tr);
                    });

                    table.style.display = 'table';
                }
            } catch (e) {
                console.error(e);
                alert('Błąd połączenia z serwerem.');
            }
        }

        function renderCalendar(container, year, monthNum, daysData, absenceRequests) {
            if (!container || !year || !monthNum) return;

            container.innerHTML = '';

            const headers = ['Pn', 'Wt', 'Śr', 'Cz', 'Pt', 'So', 'Nd'];
            headers.forEach(h => {
                const el = document.createElement('div');
                el.className = 'day day-header';
                el.textContent = h;
                container.appendChild(el);
            });

            const hoursMap = {};
            const typeMap = {};
            const absenceTypeMap = {};
            daysData.forEach(d => {
                if (!d.date) return;
                hoursMap[d.date] = Number(d.hours || 0);
                typeMap[d.date] = d.type || 'work';
                if (d.absence_type) {
                    absenceTypeMap[d.date] = d.absence_type;
                }
            });

            // Mapowanie wnioskiów urlopowych na dni
            const requestsMap = {};
            (absenceRequests || []).forEach(req => {
                if (!req.start_date || !req.end_date) return;
                const start = new Date(req.start_date);
                const end = new Date(req.end_date);
                
                for (let d = new Date(start); d <= end; d.setDate(d.getDate() + 1)) {
                    const dateStr = d.toISOString().split('T')[0];
                    if (!requestsMap[dateStr]) {
                        requestsMap[dateStr] = [];
                    }
                    requestsMap[dateStr].push({
                        type: req.type,
                        status: req.status
                    });
                }
            });

            const firstDay = new Date(year, monthNum - 1, 1);
            const lastDay = new Date(year, monthNum, 0).getDate();

            // JS: 0=Nd ... 6=So; chcemy poniedziałek jako pierwszy
            let dow = firstDay.getDay();
            if (dow === 0) dow = 7;

            // Puste pola przed pierwszym dniem
            for (let i = 1; i < dow; i++) {
                const empty = document.createElement('div');
                empty.className = 'day';
                empty.textContent = '';
                container.appendChild(empty);
            }

            for (let day = 1; day <= lastDay; day++) {
                const d = String(day).padStart(2, '0');
                const dateStr = `${year}-${String(monthNum).padStart(2, '0')}-${d}`;
                const hours = hoursMap[dateStr] || 0;
                const type = typeMap[dateStr] || '';
                const absenceType = absenceTypeMap[dateStr] || '';
                const requests = requestsMap[dateStr] || [];

                const el = document.createElement('div');
                el.className = 'day';
                el.dataset.hours = String(hours);

                let level = '';
                let displayText = '';

                // Priorytet: zaakceptowany urlop z work_sessions
                if (type === 'absence') {
                    level = 'absence';
                    const icon = absenceType === 'L4' ? '🏥' : '🏖️';
                    displayText = `<strong>${day}</strong><br>${icon} ${absenceType}`;
                }
                // Jeśli nie ma zaakceptowanego, sprawdź wnioski urlopowe
                else if (requests.length > 0) {
                    const req = requests[0]; // wez pierwszy wniosek dla tego dnia
                    const icon = req.type === 'L4' ? '🏥' : '🏖️';
                    
                    if (req.status === 'pending') {
                        level = 'absence-pending';
                        displayText = `<strong>${day}</strong><br>${icon} ${req.type}<br><small>(oczekuje)</small>`;
                    } else if (req.status === 'rejected') {
                        level = 'absence-rejected';
                        displayText = `<strong>${day}</strong><br>${icon} ${req.type}<br><small>(odrzucony)</small>`;
                    } else if (req.status === 'approved') {
                        level = 'absence';
                        displayText = `<strong>${day}</strong><br>${icon} ${req.type}`;
                    }
                }
                else if (hours === 0) {
                    level = '';
                    displayText = `<strong>${day}</strong><br>&nbsp;`;
                } else if (hours <= 8) {
                    level = 'mid';
                    displayText = `<strong>${day}</strong><br>${hours.toFixed(1)}h`;
                } else {
                    level = 'high';
                    displayText = `<strong>${day}</strong><br>${hours.toFixed(1)}h`;
                }

                if (level) {
                    el.dataset.level = level;
                }

                el.innerHTML = displayText;
                container.appendChild(el);
            }

            container.style.display = 'grid';
        }

        // Ustaw domyślnie bieżący miesiąc i od razu załaduj statystyki
        setDefaultMonth();
        loadStats();
    </script>
</body>
</html>
