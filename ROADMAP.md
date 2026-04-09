# 🗺️ MikroPlaneta Booking - Roadmap 2026

**Stan dokumentu:** kwiecień 2026  
**Status produktu:** aktywnie rozwijany, gotowy do testów zewnętrznych  
**Cel dokumentu:** plan średnioterminowy, bez duplikowania checklist release i notatek sesyjnych

---

## 📊 Stan Obecny (v1.2.8)

### ✅ Działające funkcjonalności:

| Obszar | Funkcja | Status |
|--------|---------|--------|
| **Core** | Zarządzanie pokojami | ✅ |
| **Core** | Zarządzanie łóżkami (w tym piętrowymi) | ✅ |
| **Core** | Rezerwacje indywidualne | ✅ |
| **Core** | Rezerwacje grupowe (Ctrl+klik) | ✅ |
| **Pricing** | Cena za pokój (per_room) | ✅ |
| **Pricing** | Cena za miejsce (per_bed/per_place) | ✅ |
| **Pricing** | Ceny weekendowe | ✅ |
| **Pricing** | Mnożnik dla dzieci | ✅ |
| **Widgety** | Globalny widget `[mikroplaneta_booking]` | ✅ |
| **Widgety** | Karta pokoju z modalem | ✅ |
| **Płatności** | System zaliczek (konfigurowalny %) | ✅ |
| **Płatności** | Dane do przelewu | ✅ |
| **GDPR** | Zgody RODO | ✅ |
| **GDPR** | Akceptacja regulaminu | ✅ |
| **Email** | Powiadomienia email | ✅ |
| **Admin** | Kalendarz rezerwacji | ✅ |
| **Admin** | Dashboard ze statystykami | ✅ |
| **Admin** | Zarządzanie gośćmi | ✅ |
| **Admin** | Wiadomość do gościa z recepcji (✉️ + modal + log) | ✅ |
| **Admin** | Ustawienia systemu | ✅ |
| **Integracje** | Google Calendar - Eksport (BYOK) | ✅ |

---

## 🎯 ROADMAP - Plan Rozwoju

### **Strategia wykonania**

**Założenie:** Najpierw mały fundament operacyjny, potem szybkie MVP płatności i AI jako moduły niezależne.

#### Etap A (zrealizowany): Stabilność operacyjna
- [x] Health Check w panelu admina (SMTP, WP-Cron, REST, uprawnienia zapisu)
- [x] Retry + backoff dla wysyłki email (awarie transportu)
- [x] Idempotencja Cron (blokady i ochrona przed duplikatami)
- [x] Smoke testy krytycznych flow w warstwie integracyjnej

**Cel:** Domknięty fundament pod dalszy rozwój biznesowy.

#### Etap B (w toku): Płatności MVP
- [x] Infrastruktura płatności: migracje 026+027, `PaymentTransaction` model, `PaymentTransactionRepository`
- [x] `payment_method` auto-ustawiany przy tworzeniu rezerwacji (bank_transfer gdy depozyt włączony)
- [x] Badge 🟡 Przelew bankowy w `ReservationDetailsModal`
- [ ] `PaymentManager` + `GatewayInterface` + filter `mikroplaneta_payment_gateways` (Sesja 3)
- [ ] Dashboard: sekcja „Do sprawdzenia" + przycisk „Potwierdź przelew"
- [ ] Bramki online (Przelewy24, Stripe) → osobna wtyczka `mikro-booking-payments`

**Architektura:** przelew ręczny w core `mikro-booking`; bramki online w osobnej wtyczce add-on.

**Cel:** Domknąć realny proces pobrania zaliczki — ręczny przelew najpierw, online przez add-on.

#### Etap C (1 tydzień): AI MVP (bez ryzyka dla danych)
- [ ] Asystent FAQ dla recepcji i gościa (RAG na treści lokalnej)
- [ ] Kontekst odpowiedzi: status rezerwacji, polityki, godziny check-in/out
- [ ] Tryb tylko „read-only” (AI nie modyfikuje danych rezerwacji)
- [ ] Log pytań i odpowiedzi do poprawy jakości promptów

**Cel:** Szybka wartość biznesowa bez ingerencji AI w krytyczne operacje.

### **Priorytet 1: Powiadomienia i Alerty (Q1 2026)**

#### 1.1 Dashboard - Powiadomienia w czasie rzeczywistym 🔴 NOWE
**Opis:** Recepcjonista widzi nowe rezerwacje na dashboardzie bez odświeżania strony.

**Wymagania:**
- [ ] WebSocket lub polling co 30 sekund
- [ ] Licznik nowych rezerwacji (badge na ikonie)
- [ ] Dźwięk powiadomienia (opcjonalny)
- [ ] Lista ostatnich rezerwacji na dashboardzie
- [ ] Filtr: „Oczekujące na potwierdzenie"

**Wymagania UX — Status płatności zaliczki (decyzja 2026-03-29, częściowo zaimplementowane):**

Recepcjonista musi na pierwszy rzut oka widzieć, które rezerwacje wymagają jego uwagi w kwestii płatności. Każda rezerwacja na dashboardzie i liście rezerwacji pokazuje badge:

- [x] 🟡 `Przelew bankowy` — badge w `ReservationDetailsModal` ✅ (2026-04-09)
- [ ] 🟢 `Zapłacono online` — po wdrożeniu bramki online w `mikro-booking-payments`
- [ ] ⚪ `Bez zaliczki` — gdy depozyt nie jest wymagany

- [ ] Sekcja „Do sprawdzenia" na dashboardzie: lista rezerwacji ze statusem `pending` i metodą `bank_transfer`, posortowana wg terminu wygaśnięcia
- [ ] Przycisk „Potwierdź przelew" przy każdej takiej rezerwacji — jednym kliknięciem zmienia status na `confirmed` bez wchodzenia w szczegóły
- [ ] Tooltip/szczegóły: metoda płatności, kwota zaliczki, termin płatności, ile czasu zostało

**Tech Stack:**
- WordPress REST API + polling (prostsze)
- Lub: Pusher/Ably (prawdziwy real-time)

**Pliki do zmiany:**
- `admin/src/components/DashboardContent.tsx`
- `admin/src/components/PaymentStatusBadge.tsx` (nowy)
- `rest-api/controllers/class-dashboard-controller.php`

**Estimacja:** 6-8 godzin (rozszerzone o UX płatności)

---

#### 1.2 Licznik rezerwacji oczekujących 🔴 NOWE
**Opis:** Widget w menu admina pokazujący ile rezerwacji czeka na potwierdzenie.

**Wymagania:**
- [ ] Badge przy menu "Booking" z liczbą pending
- [ ] Kliknięcie przenosi do filtra rezerwacji pending
- [ ] Odświeżanie co 60 sekund

**Pliki do zmiany:**
- `core/class-admin.php`
- `admin/src/App.tsx`

**Estimacja:** 2-3 godziny

---

#### 1.3 Powiadomienia push w przeglądarce 🔴 NOWE
**Opis:** Recepcjonista dostaje powiadomienie systemowe nawet jak ma otwartą inną kartę.

**Wymagania:**
- [ ] Permission prompt przy pierwszym wejściu
- [ ] Service Worker do obsługi powiadomień
- [ ] Powiadomienie: "Nowa rezerwacja #123 od Jan Kowalski"
- [ ] Kliknięcie otwiera rezerwację

**Tech Stack:**
- Web Push API
- Service Worker

**Estimacja:** 6-8 godzin

---

### **Priorytet 2: Integracje (Q2 2026)**

#### 2.0 Channel Manager MVP (Booking.com + Integracje) 🔴 NOWE
**Opis:** Integracja kanałów etapami, zaczynając od bezpiecznej synchronizacji dostępności i cen.

**Etap 1 (Zrealizowano):**
- [x] Google Calendar (Eksport) z użyciem modelu BYOK

**Etap 2 (najbezpieczniejszy, iCal):**
- [ ] iCal import/export dla Booking.com i Airbnb (synchronizacja kalendarza)
- [ ] Oznaczanie rezerwacji źródłem (`direct`, `booking`, `airbnb`)
- [ ] Anty-overbooking: blokady miejsc natychmiast po imporcie

**Etap 2 (API):**
- [ ] Integracja przez API Channel Manager / OTA API (availability + rates + reservations)
- [ ] Obsługa mapowania: pokoje/łóżka ↔ jednostki OTA
- [ ] Webhooki/pull sync + retry + deduplikacja

**Wymagania architektoniczne:**
- [ ] Jedna warstwa integracyjna `core/services/class-channel-manager-service.php`
- [ ] Adaptery kanałów (`integrations/class-booking-com.php`, `integrations/class-airbnb.php`)
- [ ] Centralny log synchronizacji (sukcesy, konflikty, błędy)

**Uwaga biznesowa:**
Dostęp do pełnego API Booking.com zależy od warunków partnerstwa i środowiska dostawcy, dlatego MVP zaczynamy od iCal + modelu adapterów.

**Estimacja:**
- Etap 1 (iCal): 8-12 godzin
- Etap 2 (API): 20-35 godzin (zależnie od zakresu i dostępu API)

#### 2.1 Google Calendar - Eksport rezerwacji
**Opis:** Każda rezerwacja automatycznie trafia do kalendarza Google.

**Wymagania:**
- [x] Autoryzacja OAuth 2.0 z Google
- [x] Wybór kalendarza docelowego
- [x] Event: "Rezerwacja #123 - Jan Kowalski"
- [x] Data: check-in → check-out
- [x] Opis: gość, cena, status
- [ ] Opcja: wyłącz dla konkretnych rezerwacji

**Tech Stack:**
- Google Calendar API v3
- PHP Google API Client

**Stan:** eksport BYOK wdrożony, dalsze prace dotyczą dopracowania UX i rozszerzeń.

**Główne pliki:**
- `core/services/class-google-calendar-service.php`
- `rest-api/controllers/class-google-calendar-controller.php`
- `admin/src/components/Settings.tsx`

**Następny krok:** doprecyzowanie logów, mapowania danych i ewentualnego sync dwukierunkowego.

---

#### 2.2 Google Calendar - Sync dwukierunkowy 🔴 NOWE
**Opis:** Blokada w kalendarzu Google = brak dostępności w systemie.

**Wymagania:**
- [ ] Import eventów z Google Calendar
- [ ] Event z tagiem "blocked" = niedostępne łóżka
- [ ] Periodic sync (co godzinę)
- [ ] Manualny sync button

**Estimacja:** 10-15 godzin

---

#### 2.3 iCalendar (.ics) dla klienta
**Opis:** Klient dostaje plik .ics do zapisania w kalendarzu (Google, Apple, Outlook).

**Stan:** podstawowy flow iCal dla gościa jest wdrożony. Dalsze prace dotyczą rozszerzeń i integracji OTA.

**Wdrożone:**
- [x] Generowanie pliku .ics po rezerwacji
- [x] Załącznik w emailu potwierdzającym
- [x] Link CTA do pobrania iCal
- [x] Event: check-in → check-out

**Następny krok:**
- [ ] dopracowanie mapowania danych i logów pod integracje iCal import / OTA

**Główne pliki:**
- `core/services/class-ical-service.php`
- `core/services/class-notification-service.php`

---

#### 2.4 Apple Wallet / Google Pay Pass 🔴 NOWE
**Opis:** Klient dostaje pass do portfela w smartfonie (jak bilet lotniczy).

**Wymagania:**
- [ ] Generowanie .pkpass (Apple Wallet)
- [ ] QR code z numerem rezerwacji
- [ ] Dane: gość, obiekt, data, łóżko
- [ ] Aktualizacja passa przy zmianie rezerwacji

**Tech Stack:**
- PKPass library (np. `mobinetic/pkpass`)

**Estimacja:** 12-16 godzin (skomplikowane)

---

### **Priorytet 3: Płatności Online (Q2 2026)**

#### 3.1 Przelewy24 / BLIK 🔴 NOWE
**Opis:** Klient płaci zaliczkę online bezpośrednio po złożeniu rezerwacji. System automatycznie wykrywa płatność przez webhook i zmienia status rezerwacji — bez udziału recepcji.

**Dwa tryby płatności zaliczki (równolegle):**

- **Online** → klient wybiera „Zapłać kartą/BLIKIEM" → system auto-potwierdza po webhookу → dashboard: 🟢 `Zapłacono online`
- **Przelew ręczny** → klient wybiera „Przelew tradycyjny" → dostaje dane konta → recepcja sprawdza ręcznie → dashboard: 🟡 `Czeka na przelew`

**Wymagania:**
- [ ] Integracja z Przelewy24 REST API v3 (sandbox + produkcja)
- [ ] BLIK jako metoda płatności w Przelewy24
- [ ] Publiczny endpoint webhook: `POST /wp-json/mikroplaneta/v1/payments/webhook`
- [ ] Weryfikacja podpisu webhooka (HMAC — ochrona przed fałszywymi wywołaniami)
- [ ] Nowe pole w rezerwacji: `payment_method` (`online` | `bank_transfer` | `none`)
- [ ] Nowe statusy rezerwacji: `pending_payment` → `confirmed` (auto, po webhookу) lub `failed` / `refunded`
- [ ] Nowa tabela `payment_transactions` — audit trail każdej transakcji (kwota, status, timestamp, odpowiedź bramki)
- [ ] Email do klienta: „Płatność przyjęta — rezerwacja potwierdzona" (automatyczny po webhookу)
- [ ] Email do recepcji: „Nowa opłacona rezerwacja #ID"
- [ ] Zwrot zaliczki przy anulowaniu (refund przez API bramki)

**Dashboard recepcji (powiązane z sekcją 1.1):**
- [ ] Badge statusu płatności przy każdej rezerwacji: 🟢 / 🟡 / ⚪
- [ ] Sekcja „Do sprawdzenia" — rezerwacje czekające na przelew ręczny, posortowane wg terminu wygaśnięcia
- [ ] Przycisk „Potwierdź przelew" — jednym kliknięciem, bez wchodzenia w szczegóły rezerwacji

**Tech Stack:**
- Przelewy24 REST API v3
- Webhook endpoint zabezpieczony podpisem HMAC
- Nowa migracja `026-create-payment-transactions.php`

**Główne pliki (nowe):**
- `core/payments/interface-payment-gateway.php` — abstrakcyjny kontrakt bramki
- `core/payments/class-payment-manager.php` — rejestr aktywnych bramek
- `core/payments/class-payment-transaction.php` — value object transakcji
- `integrations/payments/class-gateway-przelewy24.php` — implementacja
- `rest-api/controllers/class-payments-controller.php` — webhook + inicjacja płatności
- `admin/src/components/PaymentStatusBadge.tsx` — komponent UI badge'a

**Estimacja:** 14-18 godzin

---

#### 3.2 Stripe 🔴 NOWE
**Opis:** Alternatywa dla Przelewy24 (dla klientów zagranicznych).

**Wymagania:**
- [ ] Stripe Checkout
- [ ] Obsługa kart
- [ ] Subskrypcje (dla stałych gości)

**Estimacja:** 8-12 godzin

---

#### 3.3 Faktury VAT 🔴 NOWE
**Opis:** Automatyczne generowanie faktur PDF.

**Wymagania:**
- [ ] Dane firmy z Settings
- [ ] Dane gościa z rezerwacji
- [ ] PDF z fakturą
- [ ] Wysyłka emailem
- [ ] Numeracja faktur

**Tech Stack:**
- TCPDF lub Dompdf

**Estimacja:** 10-15 godzin

---

### **Priorytet 4: SMS i Komunikacja (Q3 2026)**

#### 4.1 Powiadomienia SMS 🔴 NOWE
**Opis:** Klient dostaje SMS z potwierdzeniem i przypomnieniem.

**Wymagania:**
- [ ] Integracja z SMS API (np. SMSAPI, Twilio)
- [ ] SMS po rezerwacji
- [ ] SMS 24h przed check-in (przypomnienie)
- [ ] SMS z kodem dostępu (opcjonalnie)

**Estimacja:** 6-8 godzin

---

#### 4.2 WhatsApp Business API 🔴 NOWE
**Opis:** Komunikacja z klientem przez WhatsApp.

**Wymagania:**
- [ ] Szablony wiadomości
- [ ] Potwierdzenie rezerwacji
- [ ] Odpowiedzi na pytania

**Estimacja:** 8-12 godzin

---

### **Priorytet 5: Analityka i Raporty (Q3 2026)**

> **Decyzja produktowa (2026-03-29):** Moduł analityki budujemy dwufazowo.
> - **Faza 1** — czyste statystyki historyczne (wykresy, eksporty, raporty PDF).
> - **Faza 2** — predykcja trendów obłożenia i podpowiedzi dla recepcji (Smart Tips), aktywowana po zebraniu min. 3 miesięcy danych produkcyjnych.
>
> To **wyróżnik na rynku** — żadna wiodąca wtyczka booking (Amelia, MotoPress, Booking Calendar) nie oferuje predykcji trendów. Naturalnie współgra z Priorytetem 7 (AI/Chatbot).

---

#### 5.1 Dashboard - Statystyki 🔴 FAZA 1
**Opis:** Rozbudowany dashboard z wykresami historycznego obłożenia i przychodów.

**Wymagania:**
- [ ] Wykres obłożenia (miesiąc/rok) — per pokój i łącznie
- [ ] Wykres przychodów (miesiąc/rok)
- [ ] Średnia cena za noc
- [ ] Top 10 gości (wg liczby noclegów i przychodu)
- [ ] Źródła rezerwacji (`direct`, `booking`, `airbnb`, itp.)
- [ ] Widok porównawczy: ten miesiąc vs poprzedni
- [ ] Obsługa stanu „za mało danych" — informacja UX dla nowych instalacji

**Tech Stack:**
- Recharts (już w zależnościach React/Vite projektu)
- PHP: agregacje na tabelach `reservations`, `reservation_beds`, `reservation_places`

**Główne pliki:**
- `admin/src/components/DashboardContent.tsx`
- `rest-api/controllers/class-dashboard-controller.php`

**Estimacja:** 10-14 godzin

---

#### 5.2 Export CSV / Excel 🔴 FAZA 1
**Opis:** Eksport rezerwacji do arkusza kalkulacyjnego.

**Wymagania:**
- [ ] Eksport wszystkich rezerwacji z filtrami: data, status, pokój
- [ ] Kolumny: gość, daty, łóżka, cena, status, źródło
- [ ] Eksport danych gości (RODO: tylko za zgodą)

**Estimacja:** 4-6 godzin

---

#### 5.3 Raport miesięczny PDF 🔴 FAZA 1
**Opis:** Automatyczny raport na email właściciela, generowany pierwszego dnia każdego miesiąca.

**Wymagania:**
- [ ] Generowanie przez WP-Cron 1-go dnia miesiąca
- [ ] Podsumowanie: przychód, obłożenie %, liczba gości
- [ ] Porównanie z poprzednim miesiącem i analogicznym miesiącem rok wcześniej
- [ ] PDF jako załącznik (biblioteka: Dompdf)

**Estimacja:** 6-8 godzin

---

#### 5.4 Predykcja Trendów Obłożenia 🔴 FAZA 2 (NOWE)
**Opis:** System przewiduje przyszłe obłożenie na podstawie historii rezerwacji z poprzednich lat i miesięcy. Właściciel widzi prognozę na kolejne 4–8 tygodni.

**Wymagania:**
- [ ] Algorytm sezonowości: porównanie tego samego okresu rok temu (rok-do-roku)
- [ ] Prognoza obłożenia % na kolejne 4 tygodnie — per pokój i łącznie
- [ ] Wykres: dane historyczne + linia predykcji (wyróżniona wizualnie)
- [ ] Próg aktywacji: min. 3 miesiące danych historycznych — poniżej progu: informacja UX zamiast pustego wykresu
- [ ] (Faza 2b) Opcjonalna integracja z OpenAI API dla zaawansowanych wzorców sezonowych — powiązana z Priorytetem 7

**Tech Stack:**
- **Faza 2a (PHP):** prosta agregacja SQL (mean, trend liniowy, sezonowość tygodniowa / miesięczna)
- **Faza 2b (AI):** OpenAI API lub lokalny model — po weryfikacji Priorytetu 7
- **Frontend:** Recharts — linia predykcji jako `<ReferenceLine>` z `strokeDasharray`

**Główne pliki (nowe):**
- `core/services/class-analytics-service.php`
- `rest-api/controllers/class-analytics-controller.php`
- `admin/src/components/AnalyticsView.tsx`

**Estimacja:**
- Faza 2a (algorytm PHP): 12-18 godzin
- Faza 2b (AI): 8-12 godzin — po wdrożeniu Priorytetu 7

**Warunek startu fazy 2:** zebrane min. 3 miesiące danych od pierwszych klientów produkcyjnych.

---

#### 5.5 Podpowiedzi dla Recepcji (Smart Tips) 🔴 FAZA 2 (NOWE)
**Opis:** Na podstawie trendów i historii system generuje krótkie, kontekstowe podpowiedzi widoczne w panelu admina na dashboardzie.

**Przykłady podpowiedzi:**
- *„Ten weekend historycznie bywa zapełniony w 90% — rozważ podwyżkę ceny."*
- *„Mało rezerwacji na przyszły wtorek — aktywuj promocję."*
- *„Gość Jan Kowalski odwiedzał obiekt co roku w tym terminie — nie ma jeszcze rezerwacji."*

**Wymagania:**
- [ ] Min. 3 podpowiedzi kontekstowe na dashboardzie (rotujące)
- [ ] Typy: wysokie obłożenie prognozowane, niskie obłożenie, powtarzający się gość, brak rezerwacji w historycznie dobrym terminie
- [ ] Linki akcji z poziomu podpowiedzi (np. „Zmień cenę" → PricingView)
- [ ] Możliwość odrzucenia / ukrycia konkretnej podpowiedzi

**Główne pliki:**
- `core/services/class-analytics-service.php` (rozszerzenie)
- `admin/src/components/DashboardContent.tsx` (sekcja Smart Tips)

**Estimacja:** 6-10 godzin (po ukończeniu 5.4)

---

### **Priorytet 6: Booking Channels (Q4 2026)**

#### 6.1 Channel Manager - Booking.com 🔴 NOWE
**Opis:** Synchronizacja dostępności z Booking.com.

**Wymagania:**
- [ ] Booking.com XML API
- [ ] Export dostępności
- [ ] Import rezerwacji
- [ ] Zapobieganie overbookingu

**Estimacja:** 20-30 godzin (bardzo skomplikowane)

---

#### 6.2 Channel Manager - Airbnb 🔴 NOWE
**Opis:** Synchronizacja z Airbnb.

**Wymagania:**
- [ ] Airbnb API
- [ ] iCalendar sync (prostsze)
- [ ] Lub pełna integracja API

**Estimacja:** 15-25 godzin

---

### **Priorytet 7: AI i Automatyzacja (Q4 2026)**

#### 7.1 AI Bed Allocation - Ulepszenia 🔴 NOWE
**Opis:** Lepszy algorytm przydzielania łóżek.

**Wymagania:**
- [ ] Uczenie się preferencji (które pokoje lubi gość)
- [ ] Preferencje: okno, dół/góra łóżka piętrowego
- [ ] Automatyczne upgrade'y (jak brak miejsc)

**Estimacja:** 10-15 godzin

---

#### 7.2 Dynamic Pricing 🔴 NOWE
**Opis:** Automatyczne dostosowanie cen do popytu.

**Wymagania:**
- [ ] Wyższe ceny w weekendy
- [ ] Wyższe ceny w sezonie
- [ ] Niższe ceny przy niskim obłożeniu
- [ ] Reguły: "jeśli obłożenie < 30%, obniż cenę o 20%"

**Estimacja:** 12-18 godzin

---

#### 7.3 Chatbot dla gości 🔴 NOWE
**Opis:** Automatyczne odpowiedzi na pytania.

**Wymagania:**
- [ ] Integracja z OpenAI API
- [ ] Baza wiedzy o obiekcie
- [ ] FAQ: check-in, parking, śniadania

**Estimacja:** 15-20 godzin

---

### **Priorytet 8: Mobile App (2027)**

#### 8.1 Aplikacja dla Recepcji 🔴 NOWE
**Opis:** Native app dla recepcjonistów.

**Wymagania:**
- [ ] React Native lub Flutter
- [ ] Dashboard
- [ ] Check-in / Check-out
- [ ] Powiadomienia push

**Estimacja:** 80-120 godzin

---

#### 8.2 Aplikacja dla Gościa 🔴 NOWE
**Opis:** App dla stałych gości.

**Wymagania:**
- [ ] Rezerwacje
- [ ] Historia pobytów
- [ ] Check-in online
- [ ] Keyless entry (kod na telefonie)

**Estimacja:** 100-150 godzin

---

## 📅 Harmonogram

### **Q1 2026 (Sty-Mar)**
- [x] Naprawa cen łóżek piętrowych
- [ ] Powiadomienia real-time na dashboardzie
- [ ] Licznik rezerwacji pending
- [ ] Google Calendar eksport
- [ ] iCalendar (.ics) dla klienta

### **Q2 2026 (Kwi-Cze)**
- [ ] Przelewy24 / BLIK
- [ ] Stripe
- [ ] Faktury VAT PDF
- [ ] Powiadomienia SMS

### **Q3 2026 (Lip-Wrz)**
- [ ] Dashboard - statystyki i wykresy (5.1) — Faza 1
- [ ] Export CSV / Excel (5.2) — Faza 1
- [ ] Raporty miesięczne PDF (5.3) — Faza 1
- [ ] WhatsApp Business API (4.2)
- [ ] Predykcja trendów obłożenia (5.4) — Faza 2a, po zebraniu min. 3 mies. danych
- [ ] Smart Tips dla recepcji (5.5) — po ukończeniu 5.4

### **Q4 2026 (Paź-Gru)**
- [ ] Channel Manager (Booking.com, Airbnb)
- [ ] Dynamic Pricing
- [ ] AI Chatbot

### **2027**
- [ ] Mobile App (React Native)
- [ ] Keyless Entry
- [ ] Pełna automatyzacja

---

## 🔧 Dług Techniczny

### Do refaktoryzacji:
- [ ] Ujednolicenie endpointów dostępności (zrobione częściowo)
- [ ] Testy jednostkowe (brak)
- [ ] Dokumentacja API (brak)
- [ ] Error handling w widgetach

### Do optymalizacji:
- [ ] Cache zapytań do bazy (duże obłożenie)
- [ ] Lazy loading łóżek w kalendarzu
- [ ] Debounce przy wyszukiwaniu gości

---

## 📈 Metryki Sukcesu

### Cel na Q2 2026:
- ⏱️ Czas rezerwacji: < 2 minuty
- 📧 95% rezerwacji z potwierdzeniem email
- 📅 80% rezerwacji zsynchronizowanych z Google Calendar
- 💳 50% płatności online (jak wdrożyć)

### Cel na Q4 2026:
- 🏨 10 obiektów na platformie
- 📊 90% obłożenia (średnio)
- ⭐ 4.8/5 satysfakcji klientów

---

## 🎯 Następna Sesja - Konkretne Zadania

### Sesja #1: Powiadomienia (4-6h)
1. Dashboard polling co 30s
2. Badge z liczbą pending rezerwacji
3. Lista ostatnich rezerwacji

### Sesja #2: Google Calendar (6-8h)
1. OAuth 2.0 konfiguracja
2. Eksport rezerwacji do kalendarza
3. Testy

### Sesja #3: iCalendar (3-4h)
1. Generowanie pliku .ics
2. Załącznik w emailu
3. Testy na iOS/Android

---

## 📞 Support i Kontakt

**GitHub Issues:** [link]  
**Email:** support@mikroplaneta.pl  
**Dokumentacja:** `/docs` folder

---

**Ostatnia aktualizacja:** 2026-03-29  
**Wersja roadmap:** 1.1

**Zatwierdzone przez:** MikroPlaneta Team
