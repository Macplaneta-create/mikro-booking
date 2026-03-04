# QA Checklist 1.2.7

Data: 2026-03-04
Commit checkpoint: e192711

## 1) Dashboard: Backup & Export

- [ ] Klik `Eksport CSV` i potwierdź pobranie pliku bez błędu `rest_no_route`.
- [ ] Klik `Backup Bazy` i potwierdź pobranie pliku SQL.
- [ ] Klik `Test Email` i potwierdź czytelny komunikat (bez `undefined`).
- [ ] Sprawdź, że widoczna jest notka o wymaganym SMTP/transporcie maili.

## 2) Ustawienia: Cron testy ręczne

- [ ] Klik `Testuj wygasanie (Cron)` i potwierdź komunikat sukcesu.
- [ ] Klik `Testuj przypomnienia (Cron)` i potwierdź komunikat sukcesu.
- [ ] Powtórz `Testuj przypomnienia (Cron)` drugi raz tego samego dnia i potwierdź brak duplikatów w logu notyfikacji.

## 3) Rezerwacja publiczna (flow użytkownika)

- [ ] Utwórz nową rezerwację z frontu (status początkowy: pending/confirmed wg konfiguracji).
- [ ] Zweryfikuj poprawność walidacji dat i komunikatów formularza.
- [ ] Potwierdź, że CAPTCHA działa zgodnie z ustawionym providerem (`none` / `recaptcha_v3` / `hcaptcha`).

## 4) Rezerwacja w adminie

- [ ] Otwórz nową rezerwację i wykonaj `Potwierdź`.
- [ ] Potwierdź wysyłkę maila po potwierdzeniu rezerwacji.
- [ ] Sprawdź wpisy w logu notyfikacji (`sent/failed`, template_name, guest, czas).

## 5) Przypomnienia mailowe

- [ ] Przygotuj rezerwację z check-in = jutro (status confirmed/paid).
- [ ] Przygotuj rezerwację z check-out = jutro (status checked_in).
- [ ] Uruchom `Testuj przypomnienia (Cron)`.
- [ ] Potwierdź, że wysyłane są odpowiednie typy: `checkin_reminder`, `checkout_reminder`.

## 6) Ustawienia i bezpieczeństwo (sanity)

- [ ] Upewnij się, że `email_notifications` jest w oczekiwanym stanie.
- [ ] Sprawdź ustawienia limitu REST (`rate_limit_enabled`, window, max requests).
- [ ] Sprawdź retencję plików backup/iCal (wartości > 0).

## 7) Regresja techniczna

- [ ] Build admina przechodzi (`npm run -s build` w `admin/`).
- [ ] Testy integracyjne przechodzą (`php vendor/bin/phpunit tests/integration`).

## 8) Staging/produkcyjne warunki

- [ ] SMTP działa testowo (osobny test pluginu SMTP).
- [ ] WP-Cron działa (lub skonfigurowany cron systemowy do wywołania `wp-cron.php`).
- [ ] Czas serwera i timezone WordPress są poprawne.

## 9) Go/No-Go

- [ ] Wszystkie krytyczne punkty (1, 2, 4, 5) zaliczone.
- [ ] Brak blockerów dla release.
- [ ] Decyzja: GO / NO-GO
