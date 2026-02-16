# Moduł Tankowania Maszyny

## Opis
Nowy moduł przeznaczony dla pracowników z uprawnieniami operatora (`is_operator = 1`), umożliwiający rejestrację tankowania maszyn w systemie RCP.

## Struktura plików
```
modules/fueling/
├── index.php           # Główny interfejs użytkownika
├── fuel_api.php        # API do zarządzania tankowaniem
└── check_operator.php  # Weryfikacja uprawnień operatora
```

## Funkcjonalność

### 1. System autoryzacji
Aplikacja wymaga weryfikacji:
- **Operator (tankujący)** - automatycznie z zalogowanej sesji, musi być operatorem (`is_operator = 1`)
- **PIN Managera** - manager z uprawnieniami `role_level = 5` musi zatwierdzić tankowanie

### 2. Rejestracja tankowania
- Wybór firmy (owner): PREF-BET, BG, PUH, MAR-BUD, DRWAL, MERITUM
- Automatyczne ładowanie maszyn przypisanych do wybranej firmy
- Wprowadzenie ilości litrów
- Wprowadzenie przejechanych motogodzin (m-h) lub przebiegu
- Walidacja PIN managera (role_level = 5)

### 3. Obliczenia automatyczne
System automatycznie oblicza:
- **delta_mh** - różnica motogodzin od ostatniego tankowania
- **avg_l_per_mh** - średnie zużycie paliwa na motogodzinę
- **rolling_avg_l_per_mh** - średnia ruchoma z 3 ostatnich tankowań
- **anomaly_score** - wskaźnik anomalii (0-100)
- **anomaly_flag** - flaga anomalii (1 = wykryto anomalię)

### 4. Wykrywanie anomalii
System wykrywa nieprawidłowości:
- Zbyt mała różnica m-h (<2)
- Odchylenie od średniej ruchomej (>200%)
- Odchylenie od normy paliwowej maszyny (±30%)

### 5. Zabezpieczenia
- **Brak pracy maszyny**: Jeśli m-h nie wzrosły od ostatniego tankowania, wpis jest odrzucany
- **Weryfikacja operatora**: Zalogowany użytkownik musi mieć status operatora (`is_operator = 1`)
- **Weryfikacja managera**: PIN managera musi być przypisany do konta z `role_level = 5`
- **Sesja użytkownika**: Dostęp tylko dla zalogowanych operatorów

## Integracja z panel.php

Przycisk "Tankowanie maszyny" pojawia się automatycznie w panelu pracownika tylko dla użytkowników z `is_operator = 1`.

```javascript
// Fragment kodu w panel.php
if (isOperator) {
    // Przycisk jest widoczny
    <button class="module-btn" onclick="go('modules/fueling/index.php')">
        <span class="icon">⛽</span>
        <span>Tankowanie maszyny</span>
    </button>
}
```

## Baza danych

### Tabela: fuel_logs
System wykorzystuje istniejącą tabelę `fuel_logs` z polami:
- `id` - klucz główny
- `machine_id` - ID maszyny
- `machine_name` - nazwa maszyny
- `owner` - firma właściciel
- `liters` - ilość zatankowanego paliwa
- `meter_mh` - stan licznika (motogodziny/przebieg)
- `supplier_id` - ID operatora (tankującego) - z sesji zalogowanego użytkownika
- `receiver_id` - NULL (manager nie jest w tabeli employees)
- `delta_mh` - różnica m-h od ostatniego tankowania
- `avg_l_per_mh` - średnie zużycie
- `rolling_avg_l_per_mh` - średnia ruchoma
- `anomaly_score` - wskaźnik anomalii
- `anomaly_flag` - flaga anomalii
- `ip` - adres IP
- `user_agent` - informacje o przeglądarce
- `created_at` - data utworzenia

### Tabela: employees
Wykorzystywane pole:
- `is_operator` - TINYINT(1), określa czy pracownik jest operatorem

### Tabela: managers
Wykorzystywane pola:
- `manager_id`, `manager_name`, `pin`, `role_level` - weryfikacja managera (tylko role_level = 5)

### Tabela: machines
Wykorzystywane pola:
- `id`, `machine_name`, `registry_number`, `owner`, `fuel_norm_l_per_mh`

## API Endpoints

### GET machines
```json
POST /modules/fueling/fuel_api.php
{
    "action": "get_machines",
    "owner": "PREF-BET"
}
```

### SAVE fuel
```json
POST /modules/fueling/fuel_api.php
{
    "action": "save_fuel",
    "manager_pin": "1234"
}
```
Uwaga: operator_pin nie jest wymagany - operator jest brany automatycznie z zalogowanej sesji ($_SESSION['user_id']) "meter_mh": 1234.5,
    "operator_pin": "1234",
    "owner_pin": "5678"
}
```

## Użycie

1. Pracownik z uprawnieniami operatora (`is_operator = 1`) loguje się do systemu
2. W panelu pracownika widzi przycisk "Tankowanie maszyny" (⛽)
3. Po kliknięciu otwiera się formularz tankowania
4. Operator wybiera firmę i maszynę
5. Wprowadza ilość litrów i stan licznika (m-h)
6. Prosi managera (role_level = 5) o wprowadzenie PIN-u zatwierdzającego
7. System waliduje:
   - Czy zalogowany użytkownik jest operatorem
   - Czy PIN należy do managera z role_level = 5
8. Zapisuje tankowanie z ID zalogowanego operatora
9. Automatycznie oblicza statystyki i wykrywa anomalie

- ✅ Weryfikacja sesji użytkownika (operator musi być zalogowany)
- ✅ Sprawdzanie uprawnień operatora (`is_operator = 1`)
- ✅ Walidacja PIN managera (tylko `role_level = 5`)
- ✅ Operator pobierany automatycznie z sesji (nie może się podszyć)
- ✅ Walidacja PIN-ów w bazie danych
- ✅ Sprawdzanie blokad kont pracowników
- ✅ Logowanie IP i User-Agent
- ✅ Sanityzacja danych wejściowych
- ✅ Prepared statements (ochrona przed SQL injection)

## Kompatybilność

Moduł jest w pełni kompatybilny z:
- Istniejącą strukturą bazy danych
- Systemem sesji aplikacji RCP
- Modułem pracy (`modules/work/`)
- Panelem kierownika (raporty tankowania)

## Uwagi techniczne

- Moduł używa tego samego stylu wizualnego co reszta aplikacji
- Responsywny design (działa na urządzeniach mobilnych)
- Walidacja po stronie klienta i serwera
- Automatyczne odświeżanie listy maszyn po wyborze firmy
- Czyszczenie formularza po zapisie
