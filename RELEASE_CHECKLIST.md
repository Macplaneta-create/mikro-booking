# Release Checklist

Jedna operacyjna checklista przed testami zewnętrznymi, release candidate i wdrożeniem.

## 0. 5 minut przed publikacją (GO/NO-GO)

Skrót: uruchom `tools/release-go-nogo.ps1` (albo `tools/release-go-nogo.ps1 -NonInteractive`) i zapisz decyzję z raportu.

- [ ] Na froncie działa `mikroplaneta_availability_calendar` (siatka ładuje się bez błędów).
- [ ] Przycisk `Rezerwuj` z kalendarza otwiera formularz i pozwala utworzyć testową rezerwację.
- [ ] Mail po rezerwacji dochodzi i ma spójną treść PL + załącznik `.ics`.
- [ ] W adminie `Testuj przypomnienia (Cron)` zwraca czytelny komunikat i nie tworzy duplikatów przy drugim uruchomieniu.
- [ ] W Ustawieniach działają przyciski `Kopiuj` (minimum: shortcode główny i kalendarz dostępności).
- [ ] `wp-content/debug.log` nie pokazuje nowych błędów krytycznych po smoke teście.

## 1. Preflight

- [ ] `git status` jest zrozumiały i wiadomo, które zmiany wchodzą do releasu.
- [ ] Wersja w `mikroplaneta-booking.php` zgadza się z `readme.txt`.
- [ ] Decyzja: build testowy, release candidate czy final release.

## 2. Build i paczka

- [ ] `composer install --no-dev`
- [ ] build panelu admina i obecność assetów w `assets/admin/`
- [ ] release package nie zawiera `node_modules`, zbędnych skryptów lokalnych i plików debugowych
- [ ] `readme.txt` ma poprawny `Stable tag` i format pod WordPress.org

## 3. Instalacja i aktywacja

- [ ] instalacja z wygenerowanej paczki ZIP na świeżym WordPressie
- [ ] aktywacja wtyczki bez błędów PHP
- [ ] migracje wykonane poprawnie
- [ ] onboarding przechodzi bez blockerów

## 4. Regresja funkcjonalna

- [ ] dashboard: eksport CSV, backup SQL, test email
- [ ] cron: test wygasania i test przypomnień bez duplikatów
- [ ] frontend: rezerwacja, walidacja dat, CAPTCHA, `.ics`
- [ ] admin: potwierdzenie rezerwacji, email, historia zmian, log notyfikacji
- [ ] Google Calendar: autoryzacja, wybór kalendarza, synchronizacja podstawowa

Źródło szczegółowych kroków: `QA_CHECKLIST.md`

## 5. Regresja techniczna

- [ ] build admina przechodzi
- [ ] testy integracyjne przechodzą
- [ ] aktywacja / deaktywacja / uninstall nie powodują regresji
- [ ] sprawdzenie `wp-content/debug.log`

## 6. Środowisko i operacje

- [ ] SMTP działa testowo
- [ ] WP-Cron działa lub skonfigurowano cron systemowy
- [ ] czas serwera i timezone WordPress są poprawne
- [ ] retencja plików backup / iCal jest ustawiona sensownie

## 7. WordPress.org / bezpieczeństwo

- [ ] maintenance tools i debug endpoints nie są publicznie odsłonięte w release
- [ ] publiczne endpointy mają właściwe zabezpieczenia i limity
- [ ] wszystkie akcje uprzywilejowane mają capability checks i nonce verification
- [ ] pakiet spełnia wymagania repozytorium WordPress.org

Źródło szczegółowych kroków: `docs/WPORG_RELEASE_CHECKLIST.md`

## 8. Go / No-Go

- [ ] brak blockerów krytycznych
- [ ] wszystkie krytyczne punkty QA zaliczone
- [ ] decyzja: GO / NO-GO