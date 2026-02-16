# 📊 BAZA DANYCH - FOCUS RCP SYSTEM

## 🎯 Przygotowane pliki SQL

### 1. `database_schema.sql` - KOMPLETNA SCHEMA BAZY DANYCH
Plik zawiera pełną strukturę bazy danych ze wszystkimi tabelami, relacjami i indeksami.

**Zawiera:**
- ✅ 19 tabel systemu
- ✅ 25+ relacji (FOREIGN KEYS)
- ✅ 50+ indeksów wydajnościowych
- ✅ Komentarze do wszystkich tabel i kolumn
- ✅ Typy danych i ograniczenia

### 2. `database_sample_data.sql` - DANE TESTOWE
Plik z przykładowymi danymi do testowania systemu.

**Zawiera:**
- 👥 5 kierowników/administratorów
- 👷 5 pracowników
- 🏗️ 13 budów
- 🚜 10 maszyn budowlanych
- 📦 8 grup materiałów + 27 typów
- 📝 Przykładowe sesje pracy, tankowania, dokumenty WZ
- 📨 Wnioski urlopowe

---

## 🚀 INSTRUKCJA INSTALACJI

### Krok 1: Utwórz bazę danych
```sql
CREATE DATABASE rcp_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

### Krok 2: Załaduj schemę
```bash
# Windows (XAMPP)
cd C:\xampp\mysql\bin
mysql.exe -u root -p rcp_db < "C:\xampp\htdocs\Focus\database_schema.sql"

# Linux/Mac
mysql -u root -p rcp_db < /path/to/database_schema.sql
```

### Krok 3: Załaduj dane testowe (opcjonalnie)
```bash
# Windows (XAMPP)
mysql.exe -u root -p rcp_db < "C:\xampp\htdocs\Focus\database_sample_data.sql"

# Linux/Mac
mysql -u root -p rcp_db < /path/to/database_sample_data.sql
```

### Krok 4: Skonfiguruj połączenie
Skopiuj `config.example.php` jako `config.php` i wypełnij dane:

```php
<?php
return [
    'db_host' => 'localhost',
    'db_name' => 'rcp_db',
    'db_user' => 'root',
    'db_pass' => '',  // twoje hasło
    'db_charset' => 'utf8mb4',
    'db_port' => 3306,
];
```

### Krok 5: Zainstaluj słowniki materiałów
```bash
# Jeśli masz plik create_material_groups.sql
php install_materials.php
```

---

## 📋 STRUKTURA BAZY DANYCH

### 🔐 UŻYTKOWNICY I UPRAWNIENIA

#### 1. `employees` - Pracownicy
- Podstawowi użytkownicy systemu
- Logowanie przez PIN
- Przypisani do kierowników
- Śledzenie czasu pracy
- Dni urlopowe

#### 2. `managers` - Kierownicy/Administratorzy
- Różne poziomy uprawnień (role_level)
- Zarządzanie zespołami
- Akceptacja sesji i wniosków
- Dostęp do panelu zarządzania

**Role kierowników:**
- `role_level = 2` - Kierownik budowy
- `role_level = 3` - Wawryniuk (specjalna rola)
- `role_level = 4` - Kadry
- `role_level = 5` - Waga
- `role_level = 9` - Administrator systemu

### 🏗️ LOKALIZACJE I ZASOBY

#### 3. `sites` - Budowy
- Lista aktywnych i archiwalnych budów
- Przypisania kierowników przez `site_managers`

#### 4. `site_managers` - Przypisania kierowników
- RelacjaMany-to-Many
- Kierownik może zarządzać wieloma budowami
- Budowa może mieć wielu kierowników

#### 5. `machines` - Maszyny
- Koparki, ładowarki, walce, dźwigi
- Właściciele: PREFBET, BG, MARBUD, PUH, DRWAL, MERITUM, ZB
- Normy spalania dla analizy

### ⏱️ CZAS PRACY

#### 6. `work_sessions` - Sesje pracy
- Check-in / Check-out pracowników
- Geolokalizacja (GPS/IP)
- Przypisanie do budowy i maszyny
- Status: OK, PENDING, AUTO, MANUAL, REJECTED
- Grupy nieobecności (URLOP/L4)

**Statusy sesji:**
- `OK` - Prawidłowo zamknięta
- `PENDING` - Czeka na akceptację kierownika
- `AUTO` - Auto-zamknięta przez system (23:59)
- `MANUAL` - Zaakceptowana ręcznie przez kierownika
- `REJECTED` - Odrzucona przez kierownika

#### 7. `work_session_approvals` - Workflow akceptacji
- Dla budów z wieloma kierownikami
- Każdy kierownik musi zaakceptować
- Historia akceptacji

#### 8. `machine_sessions` - Praca maszyn
- Kto operował jaką maszyną
- Powiązane z work_sessions
- Czas pracy maszyny

### ⛽ TANKOWANIE

#### 9. `fuel_logs` - Logi tankowania
- Rejestracja każdego tankowania
- Stan licznika motogodzin
- Obliczanie spalania (l/mth)
- **Detekcja anomalii spalania**
- Średnia ruchoma z 3 ostatnich tankowań
- Wynik anomalii (0-1)

**Pola analizy:**
- `delta_mh` - Przepracowane motogodziny
- `avg_l_per_mh` - Średnie zużycie
- `rolling_avg` - Średnia ruchoma
- `anomaly_score` - Wynik anomalii (0=ok, >0.3=podejrzane)

### 📦 MATERIAŁY I DOKUMENTY

#### 10. `material_groups` - Grupy materiałów
- KRUSZYWA, BETONY, CERAMIKA, STAL, DREWNO, itp.
- Sortowanie przez display_order

#### 11. `material_types` - Typy materiałów
- Szczegółowe typy w grupach
- Piasek, żwir, beton, pręty zbrojeniowe, itp.

#### 12. `wz_scans` - Dokumenty WZ (Wydanie Zewnętrzne)
- Skanowanie dokumentów WZ
- **Workflow 3-stopniowy:**
  1. Skanowanie przez pracownika/kierownika
  2. `waiting_operator` - Operator potwierdza odbiór
  3. `waiting_manager` - Manager zatwierdza
  4. `approved` - Dokument zaakceptowany
- Podpis cyfrowy
- Generowanie PDF
- Powiązanie z materiałami i sesjami maszyn

**Workflow statusów WZ:**
```
waiting_operator → waiting_manager → approved
                ↓
              rejected
```

### 📅 URLOPY I NIEOBECNOŚCI

#### 13. `absence_requests` - Wnioski urlopowe
- Składanie wniosków przez pracowników
- Akceptacja przez kierowników
- Automatyczne odejmowanie dni urlopowych
- Automatyczne tworzenie sesji w work_sessions (absence_group_id)
- Powiadomienia push

**Typy nieobecności:**
- `URLOP` - Urlop wypoczynkowy
- `L4` - Zwolnienie lekarskie
- Inne (do konfiguracji)

### 🔐 BEZPIECZEŃSTWO I AUDYT

#### 14. `login_attempts` - Próby logowania
- Śledzenie nieudanych prób
- Blokady po 5 próbach
- Context: rcp (aplikacja), panel (panel zarządzania)

#### 15. `login_audit` - Audyt logowań
- Historia wszystkich logowań
- Sukces/porażka
- IP, device_id, user_agent

### 🔔 POWIADOMIENIA

#### 16. `push_subscriptions` - Subskrypcje push
- Web Push API
- Powiadomienia dla pracowników i kierowników
- Endpoint, p256dh, auth keys

#### 17. `notification_log` - Historia powiadomień
- Kto wysłał, do kogo, kiedy
- Treść powiadomienia

### ⚙️ SYSTEM

#### 18. `scheduler_locks` - Blokady schedulera
- Zapobieganie równoczesnym uruchomieniom cron
- Blokady dla zadań: close_sessions, send_notifications

#### 19. `day_closures` - Zamknięte dniówki
- Zamykanie dnia roboczego
- Blokada edycji po zamknięciu

---

## 🔗 RELACJE MIĘDZY TABELAMI

```
employees
  ↓ (FK manager_id)
  → managers

employees
  ↓ (FK employee_id)
  → work_sessions
      ↓ (FK site_id)
      → sites
          ↓ (M:N przez site_managers)
          → managers
      ↓ (FK machine_id)
      → machines
      ↓ (FK manager_id - akceptacja)
      → managers
      
work_sessions
  ↓ (FK work_session_id)
  → work_session_approvals
      ↓ (FK manager_id)
      → managers

work_sessions
  ↓ (FK work_session_id)
  → machine_sessions
      ↓ (FK machine_id)
      → machines

machines
  ↓ (FK machine_id)
  → fuel_logs
      ↓ (FK supplier_id, receiver_id)
      → employees

material_groups
  ↓ (FK group_id)
  → material_types

sites
  ↓ (FK site_id)
  → wz_scans
      ↓ (FK employee_id, operator_id)
      → employees
      ↓ (FK manager_id, approving_manager_id)
      → managers
      ↓ (FK machine_session_id)
      → work_sessions

employees
  ↓ (FK employee_id)
  → absence_requests
      ↓ (FK reviewed_by, assigned_manager_id)
      → managers
```

---

## 🎨 NAJWAŻNIEJSZE ZAPYTANIA

### Sprawdź aktywne sesje pracy
```sql
SELECT 
    ws.id,
    ws.first_name,
    ws.last_name,
    ws.site_name,
    ws.start_time,
    TIMESTAMPDIFF(HOUR, ws.start_time, NOW()) AS hours_worked
FROM work_sessions ws
WHERE ws.end_time IS NULL
ORDER BY ws.start_time DESC;
```

### Sesje oczekujące na akceptację
```sql
SELECT 
    ws.*,
    COUNT(wsa.id) AS total_approvals,
    SUM(wsa.approved) AS approved_count
FROM work_sessions ws
LEFT JOIN work_session_approvals wsa ON wsa.work_session_id = ws.id
WHERE ws.status IN ('PENDING', 'AUTO')
  AND ws.end_time IS NOT NULL
GROUP BY ws.id
HAVING approved_count < total_approvals OR total_approvals = 0
ORDER BY ws.end_time DESC;
```

### Raport spalania maszyn z anomaliami
```sql
SELECT 
    m.machine_name,
    m.registry_number,
    fl.meter_mh,
    fl.liters,
    fl.delta_mh,
    fl.avg_l_per_mh,
    fl.rolling_avg,
    fl.anomaly_score,
    m.fuel_norm_l_per_mh,
    fl.created_at
FROM fuel_logs fl
JOIN machines m ON m.id = fl.machine_id
WHERE fl.anomaly_score > 0.3  -- Podejrzane spalanie
ORDER BY fl.anomaly_score DESC, fl.created_at DESC
LIMIT 20;
```

### Oczekujące wnioski urlopowe
```sql
SELECT 
    ar.*,
    e.first_name,
    e.last_name,
    e.vacation_days,
    DATEDIFF(ar.end_date, ar.start_date) + 1 AS days_requested
FROM absence_requests ar
JOIN employees e ON e.id = ar.employee_id
WHERE ar.status = 'pending'
ORDER BY ar.requested_at DESC;
```

### Oczekujące dokumenty WZ
```sql
SELECT 
    wz.*,
    s.name AS site_name,
    CONCAT(e.first_name, ' ', e.last_name) AS operator_name
FROM wz_scans wz
JOIN sites s ON s.id = wz.site_id
LEFT JOIN employees e ON e.id = wz.operator_id
WHERE wz.status IN ('waiting_operator', 'waiting_manager')
ORDER BY wz.created_at ASC;
```

---

## 📊 STATYSTYKI BAZY DANYCH

**Rozmiar:**
- 19 tabel
- 25+ relacji (FOREIGN KEYS)
- 50+ indeksów
- 150+ kolumn w sumie

**Wydajność:**
- Indeksy na wszystkich FK
- Indeksy na często używanych polach (daty, statusy)
- Indeksy kompozytowe dla złożonych zapytań
- Optymalizacja dla operacji JOIN

**Bezpieczeństwo:**
- Hasła PIN zahashowane (bcrypt)
- Audit trail dla logowań
- Soft delete gdzie potrzebne (archive)
- ON DELETE CASCADE/SET NULL odpowiednio skonfigurowane

---

## 🔧 KONSERWACJA

### Backup bazy
```bash
# Full backup
mysqldump -u root -p rcp_db > backup_$(date +%Y%m%d).sql

# Tylko schema
mysqldump -u root -p --no-data rcp_db > schema_backup.sql

# Tylko dane
mysqldump -u root -p --no-create-info rcp_db > data_backup.sql
```

### Restore
```bash
mysql -u root -p rcp_db < backup_20260216.sql
```

### Optymalizacja
```sql
-- Optymalizuj wszystkie tabele
OPTIMIZE TABLE employees, managers, sites, work_sessions, machines, fuel_logs;

-- Przebuduj indeksy
ANALYZE TABLE work_sessions, fuel_logs, wz_scans;
```

---

## 🎓 DANE TESTOWE (jeśli załadowałeś database_sample_data.sql)

### Konta managerów
```
PIN dla wszystkich: 1234

Administrator:
- Jan Kowalski (role_level=9)
- Email: j.kowalski@prefbet.pl

Kierownik:
- Adam Nowak (role_level=2)
- Email: a.nowak@prefbet.pl

Wawryniuk:
- Piotr Wawryniuk (role_level=3)
- Email: p.wawryniuk@prefbet.pl
```

### Konta pracowników
```
PIN dla wszystkich: 1234

- Marek Pracownik (zwykły pracownik)
- Paweł Operator (może tankować)
- Krzysztof Kierowca (kierowca)
- Andrzej Budowlaniec (zwykły pracownik)
- Zbigniew Operator (operator WZ)
```

---

## 📞 WSPARCIE

W razie problemów sprawdź:
1. Logi błędów PHP: `error_log`
2. Logi MySQL: `/var/log/mysql/error.log` lub `C:\xampp\mysql\data\*.err`
3. Uprawnienia do folderów: uploads/, scans/, signatures/
4. Konfiguracja PHP: `php.ini` (upload_max_filesize, post_max_size)

---

## ✅ CHECKLIST INSTALACJI

- [ ] Utworzono bazę danych `rcp_db`
- [ ] Załadowano `database_schema.sql`
- [ ] Załadowano `database_sample_data.sql` (opcjonalnie)
- [ ] Skopiowano `config.example.php` → `config.php`
- [ ] Wypełniono dane dostępowe w `config.php`
- [ ] Uruchomiono `install_materials.php` (jeśli potrzebne)
- [ ] Utworzono foldery: uploads/, scans/, signatures/, pdfs/
- [ ] Ustawiono uprawnienia 755 dla folderów
- [ ] Przetestowano logowanie managera
- [ ] Przetestowano logowanie pracownika
- [ ] Sprawdzono połączenie z bazą

---

**Data wygenerowania:** 2026-02-16  
**Wersja systemu:** FOCUS RCP v2.0  
**Baza danych:** MySQL 5.7+ / MariaDB 10.3+  
**Encoding:** UTF8MB4
