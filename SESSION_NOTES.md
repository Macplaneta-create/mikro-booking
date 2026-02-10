# Session Notes - 10.02.2026

## Zrobione ✅

### 1. Backend - Wymuszenie Pending Status
- Zmodyfikowano `ReservationService::createReservation()` aby WSZYSTKIE rezerwacje (zarówno admin, jak i frontend) zaczynały się w stanie `pending`
- Rezerwacje wymagają teraz jawnego potwierdzenia przed finalizacją
- Zmiana w: `core/services/class-reservation-service.php` (linia ~106)

### 2. Frontend - Domyślny Status
- `ReservationModal.tsx` zmieniony aby domyślnie ustawiać `status: 'pending'` (zamiast `'confirmed'`)
- Dotyczy rezerwacji tworzonych z panelu admina

### 3. Admin Settings Panel
Utworzono kompletny system konfiguracji rezerwacji:
- **Endpoint**: `POST/GET /microplaneta/v1/settings`
- **Nowy kontroler**: `rest-api/controllers/class-settings-controller.php`
- **UI**: `admin/src/components/Settings.tsx` - możliwość konfiguracji:
  - `pending_timeout_hours` (domyślnie 48) - czas oczekiwania na potwierdzenie
  - `auto_expire_pending` (bool, domyślnie true) - auto-anulowanie po timeout'u
  - `require_payment_confirmation` (bool, domyślnie true) - wymóg potwierdzenia płatności
- **API Client**: Dodano `SettingsAPI` w `admin/src/services/api.ts`

### 4. Cron Auto-Expiry System
Zautomatyzowana expiracja rezerwacji zawieszonych:
- **Serwis**: `core/services/class-reservation-expiry-service.php` - logika wygaszania
- **Handler**: `core/class-cron-handler.php` - hook do WordPress cron
- **Rejestracja**: `core/class-activator.php` - scheduling cron job (co godzinę)
- **Opcje domyślne**: Ustawiane w `Activator::set_default_options()`

### 5. Status-Based Coloring (Calendar View)
Zmieniono kolorowanie rezerwacji z guest-based na status-based:
- 🟢 **Confirmed** - `bg-green-500`
- 🟠 **Pending** - `bg-amber-500` + pulse animation
- 🟢 **Checked In** - `bg-emerald-600`
- ⚫ **Checked Out** - `bg-gray-500`
- ⚫ **Cancelled** - `bg-gray-400`

Zmiany w `admin/src/components/CalendarView.tsx`:
- Zastąpiono `getGuestColor()` przez `getStatusColor()` i `getStatusLabel()`
- Legendę zmieniono z listy gości na statystykę statusów
- Dodano zmienne `statusColors` i `statusLabels`
- Aktualizacja tooltips (umożliwia podgląd statusu na hover)

### 6. Confirmation Button in Reservation Details
Dodano przycisk "Potwierdź" w modalu szczegółów rezerwacji:
- Widoczny **tylko gdy status = 'pending'**
- Zielony przycisk z ikoną czeku
- Wywołuje `ReservationsAPI.confirm(id)` endpoint
- Po potwierdzeniu odświeża kalendarz

Zmiana w `admin/src/components/CalendarView.tsx` (sekcja Details Modal footer)

### 7. Frontend Build
- Naprawiono TypeScript errors (undefined status)
- Zbudowano bundle: `npm run build`
- Assets output: `assets/admin/index.js`, `index.css`

## ⚠️ Odkryta Usterka

**Problem**: Rezerwacja 3 osób na jednym łóżku
- Rezerwacja przez modal w kalendarzu pozwala wybrać rezerwację "na 3 osoby" ale bez wielołóżek
- Brak nałożenia walidacji że każda osoba wymaga osobnego łóżka
- **Źródło**: `ReservationModal.tsx` nie sprawdza czy liczba osób > liczba wybranych łóżek

**Ścieżka problemu**:
1. Otwórz modal (+ Rezerwacja)
2. Wpisz gościa
3. Wybierz datę
4. Ustaw 3 osoby dorosłe
5. Kliknij 1 łóżko (bez wielolóżka)
6. Rezerwacja się zapisuje na 1 łóżku dla 3 osób

## Plan na Następną Sesję 🚀

### Priorytet 1: Walidacja Rezerwacji (CRITICAL)
**CEL**: Uniemożliwić rezerwacje o liczbie osób > liczba wybranych łóżek

**Zadania**:
- [ ] Dodać walidację w `ReservationModal.tsx` 
  - Weryfikacja: `totalGuests <= selectedBeds.length`
  - Alert: "Wybierz co najmniej jedno łóżko na osobę"
  - Disable submit button jeśli nie spełnione
  
- [ ] Backend validation w `ReservationService::validateReservationData()`
  - Sprawdzić czy adults + children <= count(bed_ids)
  - Throw exception jeśli nie spełnione
  - `"Number of guests exceeds number of selected beds"`

- [ ] Frontend auto-assign logic
  - Jeśli user nie wybrał łóżek przez Ctrl+Click, system powinien:
    - Automatycznie przydzielić `group_size` łóżek
    - Jeśli ostatnio łóżko było wyłączone ręcznie, nie dodawać go
    - Lub: zmienić flow - wymagać jawnego wyboru łóżek

### Priorytet 2: Poprawy UX w Modal (HIGH)
- [ ] Wyjaśnić flow rezerwacji dla użytkownika
  - Tekst: "Każda osoba wymaga osobnego łóżka"
  - Podpowiedź jak używać Ctrl+Click dla grupy
  
- [ ] Dodać visual feedback
  - Pod polem adults/children: "Wymagane X łóżek"
  - Jeśli bed_ids.length < potrzeba: podświetlić kolorowo
  
- [ ] Zmieniany adults/children -> reset bed selection
  - Jeśli user zmieni liczbę osób, bed_ids powinien się wyzerować
  - Wymusza ponowny wybór właściwej liczby łóżek

### Priorytet 3: Admin Confirmation Workflow (MEDIUM)
- [ ] Test flow: pending → confirm → checked_in
  - Weryfikacja że przycisk "Potwierdź" pracuje
  - Sprawdzić czy status się zmienia w kalendarzu
  
- [ ] Dodać opcjonalny "Reason" field do confirm action
  - np: "Potwierdzam płatność - transakcja XXXXX"
  
- [ ] Notyfikacja email do gościa przy potwierdzeniu
  - Integracja z `NotificationService`
  - Template: "Twoja rezerwacja została potwierdzona"

### Priorytet 4: Cron Testing (MEDIUM)
- [ ] Zweryfikować czy cron auto-expire pracuje
  - Ustawić test timeout na 5 minut
  - Stworzyć rezerwację, czekać, sprawdzić czy przejdzie do cancelled
  - Logs czekać w `wp_debug.log`
  
- [ ] Dodać manual trigger do Settings
  - Przycisk "Test Expiry Now" w admin panel
  - Natychmiast uruchamia expiry logic bez czekania na cron

### Priorytet 5: Payment Confirmation Integration (FUTURE)
- [ ] Integracja z webhook'ami płatności
  - Stripe/PayPal callback → auto-confirm rezerwacji
  - Lub: admin manual po potwierdzeniu wpłaty w banku
  
### Priorytet 6: Frontend Public Endpoint (FUTURE)
- [ ] Test rezerwacji z frontendu (shortcode)
  - Czy już tworzy rezerwacje w stanie pending
  - Czy funkcjonujący kod + CAPTCHA

## Dokumentacja (do Zaktualizowania)
- [ ] Zaktualizować `ARCHITECTURE.md` z nowym flow pending→confirmed
- [ ] Zaktualizować `README.md` sekcję "Workflow Rezerwacji"
- [ ] Dodać informacje o Settings i opcjach timeout'u

## Commita na GitHub
```bash
git add .
git commit -m "feat: Implement pending reservation workflow with timeout and admin settings

- Force all reservations (admin & frontend) to start in PENDING status
- Add configurable timeout (default 48h) in Settings panel
- Implement hourly cron job for auto-expiring pending reservations
- Change calendar coloring from guest-based to status-based
- Add confirmation button in reservation details modal
- Fix TypeScript errors in CalendarView component

KNOWN ISSUE:
- Validation missing: allows creating reservation for 3 people on 1 bed
- Need to add frontend & backend validation: people_count <= beds_count
- See SESSION_NOTES.md for detailed plan on fixing this"
```

## Przydatne Komendy do Testowania

```bash
# Sprawdzić czy cron jest zarejestrowany
wp cron test

# Uruchomić ręcznie (w WP CLI):
wp eval 'do_action("mikroplaneta_booking_expire_reservations");'

# Obejrzeć debug log:
tail -f wp-content/debug.log | grep MikroPlaneta
```

## Notatki Techniczne

### Reservation Flow
```
CREATE /reservations (admin)
  → status: pending (forced by service)
  → listeners: do_action("mikroplaneta_booking_reservation_created")

CREATE /public/reservations (frontend)
  → status: pending (hardcoded, no user input)
  → listeners: do_action("mikroplaneta_booking_reservation_created")

POST /reservations/{id}/confirm (admin)
  → status: pending → confirmed
  → listeners: do_action("mikroplaneta_booking_reservation_updated")

[HOURLY CRON]
  → Check all pending reservations
  → if (created_at < now - timeout_hours) → status: cancelled
  → listeners: do_action("mikroplaneta_booking_reservation_expired")
```

### Settings Storage
Wszystkie opcje w `wp_options`:
- `mikroplaneta_booking_pending_timeout_hours` (int, default 48)
- `mikroplaneta_booking_auto_expire_pending` (bool, default true)
- `mikroplaneta_booking_require_payment_confirmation` (bool, default true)

### Files Modified
```
✅ Backend:
  - core/services/class-reservation-service.php (force pending)
  - core/services/class-reservation-expiry-service.php (NEW)
  - core/class-cron-handler.php (NEW)
  - core/class-activator.php (schedule cron)
  - core/class-plugin.php (load expiry service)
  - rest-api/controllers/class-settings-controller.php (NEW)
  - rest-api/routes.php (register settings endpoint)

✅ Frontend:
  - admin/src/components/ReservationModal.tsx (status: pending)
  - admin/src/components/CalendarView.tsx (status colors, confirm button)
  - admin/src/components/Settings.tsx (NEW - UI for settings)
  - admin/src/services/api.ts (SettingsAPI)

✅ Built:
  - assets/admin/index.js (compiled)
  - assets/admin/index.css (compiled)
```

---
**Status**: Ready for testing | Next session focus: Bed count validation
**Estimated effort for Priority 1**: 2-3 hours (frontend + backend + testing)
