# 🗺️ MikroPlaneta Booking - Roadmap Rozwoju

Ten dokument przedstawia plan dalszego rozwoju systemu po ustabilizowaniu podstawowej logiki kalendarza (v1.0.6).

## 🚀 Najbliższe cele (Short-term)

### 1. Rozbudowa Dashboardu (Analityka i Operacje)
Dashboard powinien przestać być placeholderem i stać się centrum dowodzenia.
- **Widżet "Dzisiaj":** Lista dzisiejszych przyjazdów i wyjazdów z możliwością szybkiego oznaczenia statusu Check-in/Check-out.
- **Karty Statystyk:** Aktualne obłożenie obiektu (%), prognozowany przychód na obecny tydzień.
- **Alert "Zaległości":** Lista rezerwacji o statusie "Pending", które wymagają uwagi.

### 2. Silnik AI (Inteligentne Przypisywanie Łóżek)
Zaimplementowanie algorytmu optymalizacyjnego (Bin Packing) dla rezerwacji grupowych.
- **Zadanie:** System automatycznie sugeruje łóżka tak, aby zostawić jak najwięcej wolnych "bloków" dla przyszłych gości.
- **UI:** Przycisk "Automatyczny przydział" w oknie nowej rezerwacji.

## 📈 Średnioterminowe cele (Mid-term)

### 3. System Testów Automatycznych
Zapewnienie stabilności przy wprowadzaniu nowych funkcji.
- **Backend:** PHPUnit dla logiki dostępności i cennika.
- **Frontend:** Vitest dla komponentów React (szczególnie CalendarView).
- **CI/CD:** Automatyczne uruchamianie testów przy każdym pushu na GitHub.

### 4. Zaawansowane Zarządzanie Grupami
Zwiększenie elastyczności rezerwacji obejmujących wiele łóżek.
- **Indywidualne edycje:** Możliwość zmiany daty wyjazdu tylko dla jednego łóżka w ramach grupy bez rozbijania całej rezerwacji.
- **Split Payment:** Rejestrowanie wpłat dla poszczególnych osób w grupie.

## 🌐 Wizja długoterminowa (Long-term)

### 5. Integracje i Notyfikacje (Bramki SMS/Voucher)
- **Twilio/SMS API:** Automatyczne wysyłanie kodów do drzwi w dniu przyjazdu.
- **Channel Manager:** Synchronizacja z Booking.com, Airbnb via iCal lub bezpośrednie API.
- **Self Check-in Kiosk:** Interfejs dla gości do samodzielnego zameldowania na tablecie.

---

**Status bieżący:** ✅ Podstawowa logika kalendarza (turnovers, group booking, pricing) - **USTABILIZOWANA** (v1.0.6).
