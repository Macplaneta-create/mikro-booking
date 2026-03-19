# Następna Sesja - MikroPlaneta Booking

Data aktualizacji: 2026-03-15  
Status: kod i testy integracyjne dla alokacji miejsc są gotowe; następny krok to stagingowy test ręczny recepcjonisty i końcowa decyzja release

## Top 3 działania

### 1. Test recepcjonisty na stagingu

- [ ] uruchomić migrację `025-create-reservation-places.php` na stagingu / aktualizowanej instalacji,
- [ ] przejść klikany test 15 min: częściowo zajęte łóżko piętrowe, edycja, check-in z mniejszą liczbą gości, widget publiczny,
- [ ] potwierdzić payload `capacity` / `available_places` w adminie i na froncie po aktualizacji danych.

### 2. Regresja krytycznych flow

- [ ] potwierdzić, że opisy w Ustawieniach (Rate Limiting, SMTP Health Check) są zrozumiałe dla operatora,
- [ ] uruchomić test Cron reminders i sprawdzić nowe komunikaty + wpisy sent/failed w historii,
- [ ] potwierdzić aktualizację dashboardu po edycji dat rezerwacji,
- [ ] potwierdzić scenariusz admin „Ctrl+klik wiele łóżek -> Rezerwuj”: domyślna liczba osób = pojemność zaznaczonych miejsc i poprawna cena startowa,
- [ ] sprawdzić polski język wiadomości email do klienta (bez miksu PL/EN),
- [ ] ponowić sanity check Google Calendar po autoryzacji,
- [ ] sprawdzić mobilny i desktopowy widok shortcode `mikroplaneta_availability_calendar` (siatka + CTA `Rezerwuj`).

### 3. Decyzja release GO/NO-GO

- [ ] uruchomić `tools/release-go-nogo.ps1` interaktywnie na stagingu,
- [ ] uzupełnić brakujące punkty ręczne i zapisać raport decyzji,
- [ ] przy NO-GO dopisać blocker do `STATUS.md` i poprawić przed publikacją.
