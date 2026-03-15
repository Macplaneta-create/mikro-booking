# Production Readiness

## Status

Wtyczka jest funkcjonalna i nadaje się do testów zewnętrznych oraz wdrożeń kontrolowanych, ale dokument nie powinien udawać finalnego certyfikatu jakości. To jest praktyczna checklista gotowości, nie marketingowy raport.

**Aktualizacja:** 2026-03-15

## Co jest gotowe

- podstawowy flow rezerwacji,
- panel admina z kalendarzem, dashboardem i ustawieniami,
- eksport CSV / SQL,
- iCal dla gości,
- health check i mechanizmy cron / notyfikacji,
- Google Calendar eksport w modelu BYOK,
- przygotowanie pod i18n i WordPress.org.

## Co nadal wymaga potwierdzenia przed szerokim wdrożeniem

- test instalacji z paczki ZIP na świeżym WordPressie,
- pełna ręczna regresja po onboarding i tłumaczeniach,
- walidacja cronów i wysyłki maili w środowisku zewnętrznym,
- weryfikacja release package oraz dokumentacji pod WordPress.org,
- decyzja czy bieżący branch jest już kandydatem do release, czy nadal beta.

## Operacyjnie

Pełna checklista wdrożenia i release znajduje się w `RELEASE_CHECKLIST.md`.

## Dokumenty referencyjne

- `README.md`
- `SZYBKI_START.md`
- `RELEASE_CHECKLIST.md`
- `ROADMAP.md`
- `NEXT_SESSION.md`
- `docs/WP_REPO_REQUIREMENTS.md`

## Uwagi

Jeżeli celem jest publikacja do WordPress.org, finalna decyzja o gotowości powinna wynikać z testów paczki release i zgodności repo, a nie z samego faktu, że lokalnie wszystko działa.
