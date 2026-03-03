# 🗺️ MikroPlaneta Booking - Roadmap 2026

**Wersja:** 1.2.7  
**Data ostatniej aktualizacji:** 2026-03-02  
**Status:** ✅ **PRODUKCYJNY**

---

## 📊 Stan Obecny (v1.2.7)

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
| **Admin** | Ustawienia systemu | ✅ |

---

## 🎯 ROADMAP - Plan Rozwoju

### **Priorytet 1: Powiadomienia i Alerty (Q1 2026)**

#### 1.1 Dashboard - Powiadomienia w czasie rzeczywistym 🔴 NOWE
**Opis:** Recepcjonista widzi nowe rezerwacje na dashboardzie bez odświeżania strony.

**Wymagania:**
- [ ] WebSocket lub polling co 30 sekund
- [ ] Licznik nowych rezerwacji (badge na ikonie)
- [ ] Dźwięk powiadomienia (opcjonalny)
- [ ] Lista ostatnich rezerwacji na dashboardzie
- [ ] Filtr: "Oczekujące na potwierdzenie"

**Tech Stack:**
- WordPress REST API + polling (prostsze)
- Lub: Pusher/Ably (prawdziwy real-time)

**Pliki do zmiany:**
- `admin/src/components/DashboardContent.tsx`
- `rest-api/controllers/class-dashboard-controller.php`

**Estimacja:** 4-6 godzin

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

#### 2.1 Google Calendar - Eksport rezerwacji 🔴 NOWE
**Opis:** Każda rezerwacja automatycznie trafia do kalendarza Google.

**Wymagania:**
- [ ] Autoryzacja OAuth 2.0 z Google
- [ ] Wybór kalendarza (może być więcej obiektów)
- [ ] Event: "Rezerwacja #123 - Jan Kowalski"
- [ ] Data: check-in → check-out (całodniowe)
- [ ] Opis: Gość, łóżka, cena, status
- [ ] Opcja: wyłącz dla konkretnych rezerwacji

**Tech Stack:**
- Google Calendar API v3
- PHP Google API Client

**Pliki do zmiany:**
- `core/services/class-reservation-service.php` (hook po utworzeniu)
- `admin/src/components/Settings.tsx` (konfiguracja)
- Nowy: `integrations/class-google-calendar.php`

**Estimacja:** 8-12 godzin

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

#### 2.3 iCalendar (.ics) dla klienta 🔴 NOWE
**Opis:** Klient dostaje plik .ics do zapisania w kalendarzu (Google, Apple, Outlook).

**Wymagania:**
- [ ] Generowanie pliku .ics po rezerwacji
- [ ] Załącznik w emailu potwierdzającym
- [ ] Link do pobrania: "Dodaj do kalendarza"
- [ ] Event: check-in → check-out
- [ ] Lokalizacja: nazwa obiektu + adres

**Tech Stack:**
- PHP: `eluceo/ical` lub ręczna generacja

**Pliki do zmiany:**
- `core/services/class-email-service.php`
- Nowy: `core/services/class-ical-service.php`

**Estimacja:** 4-6 godzin

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
**Opis:** Klient płaci zaliczkę online od razu.

**Wymagania:**
- [ ] Integracja z Przelewy24 API
- [ ] Płatność przy rezerwacji (widget)
- [ ] Status: "paid" po pozytywnej płatności
- [ ] Powiadomienie email z potwierdzeniem
- [ ] Zwrot płatności przy anulowaniu

**Tech Stack:**
- Przelewy24 REST API
- Webhook do obsługi statusów

**Estimacja:** 10-15 godzin

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

#### 5.1 Dashboard - Statystyki 🔴 NOWE
**Opis:** Rozbudowany dashboard z wykresami.

**Wymagania:**
- [ ] Wykres obłożenia (miesiąc/rok)
- [ ] Przychód (miesiąc/rok)
- [ ] Średnia cena za noc
- [ ] Top 10 gości
- [ ] Źródła rezerwacji (widget, Booking.com, itp.)

**Tech Stack:**
- Chart.js lub Recharts

**Estimacja:** 8-12 godzin

---

#### 5.2 Export CSV / Excel 🔴 NOWE
**Opis:** Eksport rezerwacji do arkusza kalkulacyjnego.

**Wymagania:**
- [ ] Export wszystkich rezerwacji
- [ ] Filtry: data, status, pokój
- [ ] Kolumny: gość, data, łóżka, cena, status

**Estimacja:** 4-6 godzin

---

#### 5.3 Raport miesięczny PDF 🔴 NOWE
**Opis:** Automatyczny raport na email właściciela.

**Wymagania:**
- [ ] Generowanie 1-go dnia miesiąca
- [ ] Podsumowanie: przychód, obłożenie, liczba gości
- [ ] Porównanie z poprzednim miesiącem
- [ ] PDF załącznik

**Estimacja:** 6-8 godzin

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
- [ ] Dashboard - statystyki i wykresy
- [ ] Export CSV / Excel
- [ ] Raporty miesięczne PDF
- [ ] WhatsApp Business API

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

**Ostatnia aktualizacja:** 2026-03-02  
**Wersja roadmap:** 1.0

**Zatwierdzone przez:** MikroPlaneta Team
