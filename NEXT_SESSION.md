# 🎯 Następna Sesja - MikroPlaneta Booking

**Data ostatniej aktualizacji:** 2026-03-03
**Status:** ✅ **PRODUKCYJNY - v1.3.0**
**Ostatnie zmiany:** Powiadomienia Real-Time na Dashboardie

---

## ✅ Co zostało zrobione (ostatnia sesja - 2026-03-03)

### 1. **Powiadomienia Real-Time - Dashboard** ⭐
- [x] Backend: `/dashboard/stats` zwraca `pending_reservations` count
- [x] Backend: `/dashboard/stats` zwraca `recent_reservations` (5 ostatnich)
- [x] Frontend: Polling co 30 sekund (auto-refresh)
- [x] Frontend: Badge "Oczekujące" z licznikiem pending (czerwony gdy > 0)
- [x] Frontend: Tabela "Ostatnie Rezerwacje" z pełnymi danymi
- [x] Frontend: Timestamp ostatniej aktualizacji
- [x] Fix: Usunięto unused variable w ReservationModal.tsx

**Pliki zmienione:**
- `rest-api/controllers/class-dashboard-controller.php` - nowe pole w API
- `admin/src/components/DashboardContent.tsx` - polling + UI
- `admin/src/components/ReservationModal.tsx` - fix TypeScript error

**Testy:**
1. Otwórz dashboard
2. Wyślij rezerwację z widgeta
3. Poczekaj 30 sekund
4. ✅ Dashboard pokazuje nową rezerwację w tabeli "Ostatnie Rezerwacje"
5. ✅ Licznik "Oczekujące" aktualizuje się na czerwono

---

## 🚀 Priorytety na NASTĘPNĄ sesję

### **OPCJA B: Google Calendar + iCalendar** 📅 (ZALECANE)

**Dlaczego:** Bezpieczeństwo danych + wygoda dla klienta.

**Zakres prac (8-10h):**

#### 1. iCalendar (.ics) dla klienta
```php
// core/services/class-ical-service.php
public function generateIcs(Reservation $reservation): string {
    $ics = "BEGIN:VCALENDAR\r\n";
    $ics .= "VERSION:2.0\r\n";
    $ics .= "BEGIN:VEVENT\r\n";
    $ics .= "UID:reservation-{$reservation->id}@mikroplaneta.pl\r\n";
    $ics .= "DTSTART:" . date('Ymd', strtotime($reservation->check_in)) . "\r\n";
    $ics .= "DTEND:" . date('Ymd', strtotime($reservation->check_out)) . "\r\n";
    $ics .= "SUMMARY:Rezerwacja #{$reservation->id}\r\n";
    $ics .= "DESCRIPTION:Gość: {$guest->first_name} {$guest->last_name}\r\n";
    $ics .= "LOCATION:{$hotel_name}\r\n";
    $ics .= "END:VEVENT\r\n";
    $ics .= "END:VCALENDAR\r\n";
    return $ics;
}
```

#### 2. Załącznik w emailu
- [ ] Dodaj `.ics` jako załącznik
- [ ] Link "Dodaj do kalendarza" w emailu

#### 3. Google Calendar - OAuth
- [ ] Rejestracja w Google Cloud Console
- [ ] OAuth 2.0 flow
- [ ] Zapisz token w options

**Pliki do zmiany:**
- `core/services/class-email-service.php`
- Nowy: `core/services/class-ical-service.php`
- `admin/src/components/Settings.tsx` (konfiguracja Google)

---

### **OPCJA C: Licznik Pending w Menu** 🔴

**Dlaczego:** Szybki podgląd ile rezerwacji czeka na akcję.

**Zakres prac (2-3h):**

```php
// core/class-admin.php
add_filter('plugin_action_links', function($links) {
    $pending = $this->db->get_var("SELECT COUNT(*) FROM wp_mikroplaneta_reservations WHERE status = 'pending'");
    if ($pending > 0) {
        $links[] = "<span class='update-plugins count-{$pending}'><span class='plugin-count'>{$pending}</span></span>";
    }
    return $links;
});
```

**Pliki do zmiany:**
- `core/class-admin.php`
- `admin/src/App.tsx` (jeśli chcemy też w React)

---

## 📋 Checklista - Przed Wdrożeniem

### Testy funkcjonalne:
- [ ] Rezerwacja z widgeta → email → .ics
- [ ] Rezerwacja z admina → Google Calendar
- [ ] Powiadomienie na dashboardzie (✅ zrobione)
- [ ] Licznik pending w menu
- [ ] Ceny łóżek piętrowych (18 miejsc = 1800 zł)

### Testy wydajnościowe:
- [ ] Dashboard z 100+ rezerwacjami
- [ ] Kalendarz z 50+ pokojami
- [ ] Widget przy 1000+ odwiedzających/mc

### Bezpieczeństwo:
- [ ] Sanityzacja danych z widgeta
- [ ] Rate limiting na /public/reservations
- [ ] Backup bazy przed wdrożeniem

---

## 🎯 Długoterminowe Cele (z ROADMAP.md)

### Q2 2026:
- 💳 Płatności online (Przelewy24, BLIK)
- 📄 Faktury VAT PDF
- 📱 Powiadomienia SMS

### Q3 2026:
- 📊 Dashboard z wykresami
- 📈 Export CSV/Excel
- 🤖 AI Chatbot

### Q4 2026:
- 🏨 Channel Manager (Booking.com, Airbnb)
- 💹 Dynamic Pricing
- 📱 Mobile App (React Native)

---

## 📁 Ważne Pliki

### Dokumentacja:
- `ROADMAP.md` - Pełny plan rozwoju
- `NEXT_SESSION.md` - Konkretne zadania na następną sesję
- `ARCHITECTURE.md` - Struktura systemu
- `DEVELOPMENT.md` - Setup środowiska

### Kod:
- `core/services/class-pricing-service.php` - Naprawione ceny (per-place)
- `admin/src/components/DashboardContent.tsx` - **NOWY** Real-time polling + recent reservations
- `rest-api/controllers/class-dashboard-controller.php` - **NOWY** Pending count + recent reservations

---

## 🔧 Szybki Start - Następna Sesja

```bash
# 1. Otwórz projekt
cd c:\laragon\www\gorytajemnic\wp-content\plugins\mikro-booking

# 2. Sprawdź status
git status

# 3. Wybierz zadanie z listy powyżej
# np. Google Calendar (Opcja B)

# 4. Stwórz branch
git checkout -b feature/google-calendar

# 5. Koduj, testuj, commituj
git add .
git commit -m "feat: iCalendar (.ics) export for reservations"

# 6. Zbuduj React (jeśli zmiany w admin)
cd admin
npm run build

# 7. Testuj w WordPress
```

---

## 📞 Support

**W razie problemów:**
1. Sprawdź `wp-content/debug.log`
2. Konsola przeglądarki (F12)
3. ROADMAP.md sekcja "Dług Techniczny"

---

**Gotowy do działania! 🚀**

*Następna sesja: Google Calendar + iCalendar (Opcja B) lub Licznik Pending w Menu (Opcja C)*
