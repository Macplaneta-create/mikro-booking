# Mikroplaneta Booking - Plan Rozwoju (Sprints)

Poniżej znajduje się podział prac na 4 główne etapy (Sprinty). Skupiamy się na dowiezieniu wartościowej funkcjonalności w każdym kroku.

## ✅ Aktualizacja statusu (2026-02-27)
- [x] Kalendarz: poprawa czytelności i wyróżnień dni/weekendów.
- [x] Shortcode generator (room/cabin booking + card).
- [x] CAPTCHA provider switch + global rate limiting.
- [x] Email notifications lifecycle (potwierdzenie/anulowanie/przypomnienia cron).
- [x] Panel administracyjny email: edycja szablonów, test email, historia wysyłek.

## 🏁 Sprint 1: Core & Security (Fundamenty)
**Cel**: System jest bezpieczny, ma strukturę bazy danych i chronione API.
- [x] Inicjalizacja wtyczki i React Dashboard.
- [ ] **License Manager**: Implementacja klasy `License_Manager` z obsługą `MIKROPLANETA_DEV_MODE` (obejście licencji na localhost).
- [x] **API Protection**: Zabezpieczenie endpointów REST API przed nieautoryzowanym dostępem.
- [ ] **Room Service (Backend)**: Logika PHP do tworzenia/czytania pokoi i łóżek z bazy danych.

## 🏨 Sprint 2: Inventory & Ceny (Zasoby)
**Cel**: Recepcjonista może skonfigurować hotel (pokoje, łóżka) i cennik.
- [ ] **API Endpoints**: Endpointy `/rooms` i `/rates` (GET, POST, PUT, DELETE).
- [ ] **Room Manager (React)**: Interfejs do dodawania pokoi ("Kreator Pokoju" - wybór Private/Dorm).
- [ ] **Price Matrix**: Tabela cen w bazie i logika ich pobierania (Cena Bazowa + Weekend).

## 📅 Sprint 3: Kalendarz & Rezerwacje (Serce Systemu)
**Cel**: Działa rezerwowanie miejsc i widok kalendarza.
- [ ] **Booking Engine**: Kluczowy algorytm sprawdzający dostępność (`check_availability`) uwzględniający logikę hybrydową.
- [ ] **Timeline Component**: Wizualny kalendarz w React z osią czasu.
- [ ] **Drag & Drop**: Możliwość przesuwania rezerwacji myszką.
- [ ] **Booking Form**: Modal do tworzenia nowej rezerwacji z poziomu kalendarza.

## 🚀 Sprint 4: Zaawansowane Funkcje & Release
**Cel**: Wtyczka gotowa do wdrożenia produkcyjnego.
- [ ] **Staff Override**: Tryb wymuszania rezerwacji przez administratora.
- [x] **Powiadomienia**: Wysyłka e-maili + przypomnienia + log i edycja szablonów.
- [ ] **Dashboard Data**: Podpięcie widgetów dashboardu pod prawdziwe dane z bazy.
- [ ] **Final Build**: Optymalizacja i przygotowanie paczki `.zip`.

## 🎯 Sprint 5: Payments + WordPress.org Readiness (Nowy)
**Cel**: Domknięcie monetyzacji i przygotowanie do oficjalnej publikacji.
- [ ] **Payments Core**: model płatności + statusy + historia wpłat.
- [ ] **Payments UI**: sekcja płatności w modalu rezerwacji + częściowe wpłaty.
- [ ] **Email + Payments**: maile potwierdzające wpłatę / przypomnienia.
- [ ] **WP.org Compliance Docs**:
  - [ ] `readme.txt` (WordPress.org format)
  - [ ] `CHANGELOG.md`
  - [ ] `LICENSE` (GPLv2+)
  - [ ] `assets/` (ikony, bannery, screenshoty)
