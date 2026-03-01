# 📝 TODO - MikroPlaneta Booking UI Improvements

## ✅ Zrobione (2026-02-27)

### Modal z formularzem rezerwacji
- [x] Zmiana domyślnego zachowania karty (`show_widget="no"`)
- [x] Przycisk "Sprawdź dostępność" otwiera modal
- [x] Dane z mini-formularza przekazywane do modala (daty, liczba gości)
- [x] Automatyczne wyszukiwanie dostępnych łóżek po otwarciu modala
- [x] Filtrowanie łóżek tylko dla wybranego pokoju (room_id)
- [x] Auto-sugestia łóżek dla liczby gości
- [x] Zamykanie modala: X, kliknięcie w backdrop, ESC
- [x] Zablokowanie dat przeszłych (min="today")
- [x] Naprawa ładowania skryptu JS (wp_enqueue w render_booking_card)
- [x] Usunięcie jQuery z dependencji
- [x] Font dziedziczony z szablonu (nie własny)

### Pliki zmienione:
- `public/class-frontend.php` - enqueue assets, data-min atrybuty
- `public/js/widget.js` - obsługa modala, prefill danych, debug logi
- `public/css/widget.css` - style modala

---

## 🎨 Do poprawy - UI/UX (Priorytet na jutro)

### 1. Wygląd karty pokoju (`render_booking_card`)
- [ ] **Nowoczesny design** - obecny jest "brzydki" (inline style z lat 2000)
- [ ] **Lepsza typografia** - fonty, odstępy, hierarchy
- [ ] **Zdjęcie pokoju** - lepsze proporcje, lazy loading, hover effects
- [ ] **Pola formularza** - nowocześniejszy wygląd (cienie, border-radius)
- [ ] **Przycisk** - hover/active states, transition effects
- [ ] **Responsywność** - mobile-first, stackowanie na małych ekranach
- [ ] **Ikony** - dodać ikony (kalendarz, użytkownicy) do pól

### 2. Wygląd modala
- [ ] **Nagłówek** - dodać nazwę pokoju, może miniaturkę zdjęcia
- [ ] **Tło modala** - lepszy backdrop (gradient, blur)
- [ ] **Animacja otwierania** - płynniejsza (current: slide + scale)
- [ ] **Formularz w środku** - mniej "ściśnięty", więcej powietrza
- [ ] **Lista łóżek** - ładniejsza prezentacja (kafelki zamiast checkboxów)
- [ ] **Podsumowanie** - lepsza wizualizacja wybranych miejsc
- [ ] **Przycisk wyślij** - bardziej widoczny, CTA style

### 3. Widget CSS - ogólne
- [ ] **Przenieść inline style do CSS** - obecnie wszystko w PHP (inline)
- [ ] **Dodać zmienne CSS** - kolory, spacing (łatwiejsza customizacja)
- [ ] **Dark mode support** - opcjonalnie
- [ ] **Animacje ładowania** - spinner podczas fetchowania łóżek
- [ ] **Error states** - ładniejsze komunikaty błędów

### 4. UX ulepszenia
- [ ] **Tooltipy** - wyjaśnienia (np. co to "łóżko piętrowe")
- [ ] **Walidacja na żywo** - podświetlanie błędnych pól
- [ ] **Success animation** - konfetti po udanej rezerwacji
- [ ] **Loading states** - skeleton screens zamiast "Loading..."
- [ ] **Mobile improvements** - większe pola dotykowe

---

## 🛠️ Tech Debt

### Do usunięcia:
- [ ] Console.logi z widget.js (dodane dla debugowania)
- [ ] Inline style w PHP (przenieść do CSS)

### Do refaktoru:
- [ ] `render_booking_card()` - zbyt dużo inline HTML, rozbić na mniejsze funkcje
- [ ] `widget.js` - funkcje helper w osobnych plikach
- [ ] CSS - użyć BEM lub innej metodologii

---

## 📋 Shortcode'y - dokumentacja

```php
// Karta pokoju z modalem (domyślnie)
[mikroplaneta_booking_card room_id="5"]

// Karta + formularz pod spodem (stare zachowanie)
[mikroplaneta_booking_card room_id="5" show_widget="yes"]

// Własny tekst przycisku
[mikroplaneta_booking_card room_id="5" button_label="Zarezerwuj"]

// Sam widget (bez karty)
[mikroplaneta_booking room_id="5"]
```

---

## 🎯 Plan na jutro

1. **Przygotować mockup** w Figma/Photoshop (jak ma wyglądać)
2. **Nowy CSS dla karty** - nowoczesny design
3. **Nowy CSS dla modala** - lepszy UX
4. **Przenieść inline style** do pliku CSS
5. **Dodać animacje** (hover, loading, success)
6. **Przetestować na mobile**

---

*Ostatnia aktualizacja: 2026-02-27*
