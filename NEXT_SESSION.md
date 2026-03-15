# 🎯 Następna Sesja - MikroPlaneta Booking

**Data aktualizacji:** 2026-03-15  
**Status:** gotowe do testów zewnętrznych i porządkowania przed kolejnym sprintem

## Aktualny stan

Domknięte i obecne w repo:

- backup i eksport CSV / SQL,
- iCal dla gości,
- health check i usprawnienia cron / maili,
- onboarding przy pierwszym uruchomieniu,
- fundament i18n,
- Google Calendar BYOK w wariancie eksportu.

## Priorytet na następną sesję

### 1. Testy zewnętrzne i regresja

- [ ] instalacja z wygenerowanej paczki ZIP na czystym WordPressie,
- [ ] przejście przez onboarding,
- [ ] weryfikacja tłumaczeń i tekstów interfejsu,
- [ ] test cronów, maili i logów po rezerwacji,
- [ ] sanity check Google Calendar po autoryzacji.

### 2. Domknięcie dokumentacji i release hygiene

- [ ] potwierdzić, które dokumenty są nadal potrzebne,
- [ ] zostawić tylko jeden plan krótkoterminowy (`NEXT_SESSION.md`),
- [ ] utrzymać `ROADMAP.md` jako backlog średnioterminowy,
- [ ] nie mieszać checklist release z roadmapą produktu.

### 3. Następny sprint produktowy

- [ ] płatności MVP: provider, webhook, statusy, log zdarzeń,
- [ ] iCal import / source tagging / anty-overbooking,
- [ ] dopiero potem AI FAQ w trybie read-only.

## Kolejność pracy

1. Testy na zewnętrznym środowisku.
2. Poprawki po testach i czyszczenie dokumentacji.
3. Płatności MVP.
4. Integracje OTA / iCal.

## Rytuał zamknięcia sesji

- [ ] po każdej większej sesji uruchomić prompt `/Update Project Docs`,
- [ ] po uruchomieniu promptu potwierdzić aktualność `STATUS.md` i `NEXT_SESSION.md`.

## Ważne pliki

- `README.md` - wejście do projektu
- `STATUS.md` - source of truth co działa, co wymaga potwierdzenia, co jest w planie
- `ROADMAP.md` - plan średnioterminowy
- `RELEASE_CHECKLIST.md` - główna checklista release / wdrożenia
- `QA_CHECKLIST.md` - aktywna checklista regresji przed releasem
- `docs/GOOGLE_CALENDAR_SETUP.md` - konfiguracja Google Calendar
- `docs/WPORG_RELEASE_CHECKLIST.md` - checklista release pod WordPress.org
- `docs/WP_REPO_REQUIREMENTS.md` - przygotowanie pod WordPress.org
