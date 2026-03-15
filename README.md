# MikroPlaneta Booking

WordPress plugin do obsługi rezerwacji pokoi i miejsc noclegowych, z panelem admina w React, kalendarzem dostępności, iCal oraz integracją Google Calendar w modelu BYOK.

## Najważniejsze funkcje

- Zarządzanie pokojami i łóżkami, w tym rezerwacje grupowe.
- Cennik per room i per bed, ceny weekendowe oraz mnożniki dla dzieci.
- Publiczny widget rezerwacji i karta pokoju z modalem.
- Publiczny kalendarz dostępności miesięcznej: `mikroplaneta_availability_calendar`.
- Powiadomienia email, pliki `.ics`, cron i logi notyfikacji.
- Backup i eksport CSV/SQL z panelu administratora.
- Health Check, onboarding i przygotowanie pod i18n / WordPress.org.
- Google Calendar eksport rezerwacji przez OAuth 2.0 BYOK.

## Wymagania

- PHP 8.0+
- WordPress 6.0+
- Node.js 18+
- Composer

## Szybki start

1. Zainstaluj zależności PHP:
   ```bash
   composer install
   ```
2. Zainstaluj zależności panelu admina:
   ```bash
   cd admin
   npm install
   ```
3. Aktywuj wtyczkę w WordPressie.
4. W trybie developerskim uruchom frontend admina:
   ```bash
   cd admin
   npm run dev
   ```

## Dokumentacja

- [SZYBKI_START.md](SZYBKI_START.md) - skrócony start po polsku
- [DEVELOPMENT.md](DEVELOPMENT.md) - workflow developerski
- [ARCHITECTURE.md](ARCHITECTURE.md) - architektura i warstwy systemu
- [ROADMAP.md](ROADMAP.md) - plan produktu i priorytety
- [STATUS.md](STATUS.md) - główny stan projektu między sesjami
- [NEXT_SESSION.md](NEXT_SESSION.md) - najbliższe konkretne zadania
- [DOCUMENTATION_WORKFLOW.md](DOCUMENTATION_WORKFLOW.md) - proces utrzymania dokumentacji między sesjami
- [RELEASE_CHECKLIST.md](RELEASE_CHECKLIST.md) - główna checklista operacyjna release / wdrożenia
- [QA_CHECKLIST.md](QA_CHECKLIST.md) - aktywna checklista ręcznych testów
- [docs/API.md](docs/API.md) - REST API
- [docs/DATABASE.md](docs/DATABASE.md) - baza danych
- [docs/GOOGLE_CALENDAR_SETUP.md](docs/GOOGLE_CALENDAR_SETUP.md) - konfiguracja Google Calendar
- [docs/WPORG_RELEASE_CHECKLIST.md](docs/WPORG_RELEASE_CHECKLIST.md) - checklista release pod WordPress.org
- [docs/WP_REPO_REQUIREMENTS.md](docs/WP_REPO_REQUIREMENTS.md) - zgodność z WordPress.org

## Struktura repozytorium

```text
mikro-booking/
├── core/          # logika backendowa, serwisy, repozytoria, migracje
├── rest-api/      # kontrolery i trasy REST API
├── public/        # frontend publiczny widgetów
├── admin/         # źródła React/TypeScript dla panelu admina
├── assets/        # zbudowane assety frontendu i admina
├── docs/          # dokumentacja techniczna
├── tests/         # testy integracyjne
└── tools/         # narzędzia serwisowe i maintenance
```

## Status

Stan repozytorium po marcowych zmianach 2026:

- stabilizacja operacyjna jest domknięta,
- Google Calendar eksport jest w gałęzi main (wymaga testu zewnętrznego),
- kolejne priorytety to testy zewnętrzne, płatności MVP i synchronizacja iCal.

## Licencja

GPL v3 or later
