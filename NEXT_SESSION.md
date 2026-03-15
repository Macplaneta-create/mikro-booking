# Następna Sesja - MikroPlaneta Booking

Data aktualizacji: 2026-03-15  
Status: po wdrożeniu poprawek i widgetu miesięcznego, gotowe do finalnej regresji + GO/NO-GO

## Top 3 działania

### 1. Regresja krytycznych flow (blok 1)

- [ ] potwierdzić, że opisy w Ustawieniach (Rate Limiting, SMTP Health Check) są zrozumiałe dla operatora,
- [ ] uruchomić test Cron reminders i sprawdzić nowe komunikaty + wpisy sent/failed w historii,
- [ ] potwierdzić aktualizację dashboardu po edycji dat rezerwacji,
- [ ] sprawdzić polski język wiadomości email do klienta (bez miksu PL/EN),
- [ ] ponowić sanity check Google Calendar po autoryzacji,
- [ ] sprawdzić mobilny i desktopowy widok shortcode `mikroplaneta_availability_calendar` (siatka + CTA `Rezerwuj`).

### 2. Decyzja release GO/NO-GO (blok 2)

- [ ] uruchomić `tools/release-go-nogo.ps1` interaktywnie na stagingu,
- [ ] uzupełnić brakujące punkty ręczne i zapisać raport decyzji,
- [ ] przy NO-GO dopisać blocker do `STATUS.md` i poprawić przed publikacją.

### 3. Następny sprint produktowy: płatności MVP (blok 3)

- [ ] jeden provider na start (Przelewy24),
- [ ] webhook statusów płatności,
- [ ] statusy i log zdarzeń płatności w panelu admina.
