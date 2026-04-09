# Status Projektu

Ostatnia aktualizacja: 2026-04-09

Ten dokument jest głównym źródłem prawdy o stanie projektu między sesjami.

## Jak czytać status

- Zweryfikowane: funkcja działa i była sprawdzona manualnie albo testami.
- Zaimplementowane, do potwierdzenia: kod jest w repo, ale wymaga regresji lub testu na czystym środowisku.
- W trakcie: temat jest rozpoczęty, ale nie należy go uznawać za gotowy.
- Planowane: backlog, bez deklaracji gotowości.
- Do dopracowania / naprawy: znane ryzyka, braki UX, regresje albo obszary niepotwierdzone.

## Stan bieżący

| Obszar | Status | Poziom pewności | Następny krok |
|---|---|---|---|
| Rezerwacje podstawowe | Zaimplementowane, do potwierdzenia | średni | potwierdzić manualnie flow kalendarza (CTA w siatce + toolbar), ranking alokacji grup oraz przeliczanie ceny w modalu |
| Backup CSV / SQL | Zaimplementowane, do potwierdzenia | średni | test z paczki ZIP i test cronów |
| iCal dla gości | Zaimplementowane, do potwierdzenia | średni | potwierdzić link CTA i logi audytowe |
| Health Check | Zaimplementowane, do potwierdzenia | średni | test w środowisku zewnętrznym |
| Retry maili i locki cron | Zaimplementowane, do potwierdzenia | średni | regresja notyfikacji i brak duplikatów |
| Onboarding | Zaimplementowane, do potwierdzenia | niski | przejść wizard na czystej instalacji |
| i18n / WordPress.org | W trakcie | niski | sprawdzić tłumaczenia i paczkę release |
| Google Calendar BYOK eksport | Zaimplementowane, do potwierdzenia | niski | autoryzacja, sync i test callbacku |
| Kalendarz dostępności (publiczny shortcode) | Zaimplementowane, do potwierdzenia | średni | regresja frontu: siatka miesięczna + CTA Rezerwuj + responsywność |
| Ustawienia admina (czytelność + kopiowanie) | Zaimplementowane, do potwierdzenia | średni | potwierdzić komunikaty i działanie wszystkich przycisków Kopiuj na różnych przeglądarkach |
| Alokacja miejsc w łóżkach piętrowych | Zaimplementowane, do potwierdzenia | średni | uruchomić migrację `reservation_places` i zrobić regresję create/edit/check-in/widget dla częściowo zajętych łóżek |
| Płatności online — Moduł (architektura) | Zaimplementowane, do potwierdzenia | wysoki | Sesja 1+2 gotowe: migracje, model, repository, auto payment_method, badge. Następny krok: PaymentManager + gateway interface (Sesja 3) |
| Wiadomość do gościa z recepcji | Zaimplementowane, do potwierdzenia | średni | test wysyłki w środowisku zewnętrznym, potwierdzenie zapisu w changes_log |
| iCal import / OTA | Planowane | wysoki | po testach zewnętrznych |
| AI FAQ | Planowane | wysoki | dopiero po płatnościach i integracjach |
| Analityka — Faza 1 (wykresy, CSV, PDF) | Planowane | średni | Q3 2026; wymaga danych produkcyjnych z pierwszych instalacji |
| Analityka — Faza 2 (predykcja trendów) | Planowane | niski | po Fazie 1 i zebraniu min. 3 mies. danych; decyzja produktowa: wyróżnik rynkowy |
| Smart Tips dla recepcji | Planowane | niski | po ukończeniu predykcji trendów (5.4 → 5.5) |

## Zweryfikowane ostatnio

- uporządkowano dokumentację i rozdzielono: status, plan, QA, release,
- zarchiwizowano snapshoty wersji 1.2.7,
- główne checklisty są aktywne i mają jasne role.
- wykonano testy na żywej instalacji zewnętrznej i zebrano listę problemów UX/komunikatów.
- dodano skrócony proces GO/NO-GO i runbook `tools/release-go-nogo.ps1` z raportem decyzji.
- build admina przechodzi po zmianach place-based (`capacity` / `available_places`).
- paczka release `1.2.8` została wygenerowana i przeszła walidację archiwum.
- uaktualniono `ARCHITECTURE.md` i `QA_CHECKLIST.md` o model `reservation_places` oraz scenariusze regresji częściowo zajętych łóżek piętrowych.
- przeszły testy integracyjne najbliższe pracy recepcji: update rezerwacji, check-in z korektami, endpoint update, public reservations, trigger cron i reschedule cron.
- build admina oraz `php -l` dla zmienionych plików przeszły po dopięciu obsługi `place_ids` w kalendarzu i backendzie rezerwacji.
- **2026-03-29:** podjęto decyzję produktową o module Analityki w dwóch fazach (szczegóły w `ROADMAP.md` Priorytet 5). Faza 1 — czyste statystyki i wykresy (Q3 2026). Faza 2 — predykcja trendów obłożenia i Smart Tips dla recepcji (po zebraniu min. 3 mies. danych). Zidentyfikowane jako wyróżnik względem Amelii / MotoPress / Booking Calendar.
- **2026-03-29:** zaprojektowano kompletny moduł płatności. Decyzja: przelew ręczny w core, bramki online jako osobna wtyczka `mikro-booking-payments` przez filter `mikroplaneta_payment_gateways`.
- **2026-04-09:** Sesja 1+2 modułu płatności: migracje 026+027, `PaymentTransaction` model + repository, `Reservation` rozszerzona o `payment_method`; auto-ustawianie `payment_method = 'bank_transfer'` przy tworzeniu rezerwacji gdy depozyt włączony + konto skonfigurowane; badge 🟡 Przelew bankowy w `ReservationDetailsModal`. Wszystkie `php -l` i build ✅.
- **2026-04-09:** Naprawiono błąd `failed to create reservation` — nowe migracje (026+027) nie uruchamiały się na aktywnej wtyczce; dodano `maybe_run_pending_migrations()` na `plugins_loaded` prio 5 w `class-plugin.php`. Zweryfikowane na żywej instalacji.
- **2026-04-09:** Wiadomość do gościa z recepcji: `NotificationService::sendCustomMessage()`, endpoint `POST /guests/{id}/message`, logi w `changes_log`. Przycisk ✉️ w `GuestsView` + modal z dropdownem rezerwacji gościa. `php -l` ✅, build ✅.
- **2026-04-09:** Suite testów integracyjnych 43/43 zielone. Naprawiono pre-existing failures: ścieżki do `tools/` w AdminToolsSecurityGuardsTest, brakujące `require_once IcalService` + stuby `wp_salt`/`add_query_arg`/`admin_url`/`wp_nonce_url`/`wp_die` w bootstrap, izolacja `$GLOBALS['wpdb']` w PaymentTransactionRepositoryTest (setUp/tearDown), brakujące 2 argumenty konstruktora w ReservationConfirmNotificationTest.

## Zaimplementowane, ale wymagają potwierdzenia

- instalacja z paczki ZIP,
- onboarding po pierwszej aktywacji,
- tłumaczenia w interfejsie admina i frontendu,
- cron i notyfikacje mailowe w środowisku zewnętrznym,
- Google Calendar OAuth i synchronizacja podstawowa.
- doprecyzowane opisy ustawień API rate limiting i Health Check SMTP,
- rozszerzone komunikaty po ręcznym teście Cron (więcej danych operacyjnych),
- ukrycie menu Migrations w produkcji (domyślnie widoczne tylko przy WP_DEBUG lub filtrze),
- polonizacja domyślnych tematów i głównych treści wiadomości email,
- dashboard: korekta liczników przyjazdów/wyjazdów (status pending + confirmed + checked_in) i wyłączenie cache odpowiedzi statystyk.
- nowy shortcode/widget frontendu z kalendarzem/listą dostępności pokoi i domków oraz CTA „Rezerwuj”, bazujący na tych samych publicznych endpointach co istniejący widget.
- widoczna sekcja shortcode dostępności w Ustawieniach + poprawki przycisków `Kopiuj` (clipboard + fallback).
- poprawka modala rezerwacji grupowej w adminie: domyślna liczba gości startuje od pojemności zaznaczonych łóżek (zamiast 1), aby wycena startowa była spójna z wyborem wielu łóżek.
- dodano trwały model `reservation_places` oraz migrację tworzącą tabelę mapującą rezerwację na konkretne miejsca.
- `ReservationService` i `AvailabilityService` alokują oraz liczą realnie zajęte miejsca; endpointy availability zwracają teraz `capacity` i `available_places`.
- publiczny widget, kalendarz miesięczny i kluczowe modale admina przestały zgadywać po `bed_type` i czytają pojemność / wolne miejsca z backendu.
- tworzenie i odczyt łóżek inicjalizuje `bed_places`, a frontendowy shortcode dostępności liczy `total_places` z repozytorium miejsc.
- kalendarz admina przekazuje do modala rezerwacji wyliczone `place_ids`, a `ReservationService` respektuje ręczny wybór miejsca, jeśli request zawiera poprawne i dostępne `place_ids`; tryb automatyczny nadal używa optymalizatora tylko przy ich braku.
- modal tworzenia rezerwacji liczy podgląd ceny noclegu tą samą logiką doboru łóżek co finalny zapis, więc przy trybie automatycznym nie powinien już pokazywać samych usług dodatkowych bez ceny pobytu.
- dodano dodatkowy przycisk akcji „Rezerwacje” w toolbarze kalendarza admina, podpięty pod ten sam flow co przycisk `ZAREZERWUJ` w siatce, aby domknąć przypadki problematycznego kliknięcia CTA w wierszu łóżka.
- poprawiono ranking alokacji grup w `AvailabilityService`, aby preferował najlepiej dopasowany wariant `single_room` (w tym dorm) przed rozdzielaniem grupy na wiele pokoi; frontend dodatkowo sortuje odpowiedzi `groupSearch` deterministycznie przed wyborem pierwszej opcji.

## Zweryfikowane testami, ale nadal wymagają regresji ręcznej

- flow recepcji na poziomie integracyjnym: update rezerwacji, check-in z korektą liczby gości, endpoint update rezerwacji,
- publiczny flow rezerwacji i availability na poziomie testów integracyjnych,
- akcje recepcji w Ustawieniach: trigger cron i reschedule cron.

## Do dopracowania / naprawy

- nie traktować samego istnienia kodu jako potwierdzenia gotowości,
- nie mieszać backlogu produktu z checklistą release,
- każdą większą funkcję oznaczać osobno jako zaimplementowaną i osobno jako zweryfikowaną,
- utrzymać aktualność dokumentów technicznych po większych zmianach w architekturze,
- po każdej sesji dopisać co faktycznie sprawdzono, a nie tylko co zmieniono.
- ponownie potwierdzić odświeżanie dashboardu po edycji dat rezerwacji na środowisku zewnętrznym,
- doprecyzować UX konfiguracji adresu odbiorcy dla recepcji i czytelny status dostarczenia emaila (sent/failed + kontekst).
- potwierdzić stabilność miesięcznej siatki dostępności przy większej liczbie pokoi i w widoku mobilnym.
- potwierdzić regresyjnie scenariusz „Ctrl+klik wiele łóżek” w adminie: zgodność domyślnej liczby osób i ceny startowej.
- potwierdzić wykonanie migracji `025-create-reservation-places.php` na aktualizowanej instalacji oraz zachowanie fallbacku dla starszych danych.
- sprawdzić ręcznie scenariusze częściowego obłożenia łóżka piętrowego: nowa rezerwacja, edycja, check-in z mniejszą liczbą gości i widget publiczny.
- potwierdzić klikany test recepcjonisty w realnym UI WordPressa, bo obecna weryfikacja tej części była integracyjna, nie manualna.
- potwierdzić ręcznie działanie CTA „Rezerwacje” w kalendarzu admina (przycisk w siatce i fallback w toolbarze).
- potwierdzić ręcznie scenariusz grupy 8 osób po zmianie rankingu `groupSearch` (preferencja `single_room`) i sprawdzić, czy system nie rozrzuca grupy mimo dostępnego dormu.
- potwierdzić ręcznie, że modal tworzenia rezerwacji po wyborze terminu w kalendarzu pokazuje poprawną cenę noclegu także przy automatycznym doborze łóżek.
- potwierdzić ręcznie w realnym UI, że kalendarz zachowuje wybrane `place_ids` przy tworzeniu rezerwacji i nie wpada ponownie w automatyczną alokację dla ręcznie wskazanego miejsca.
## Plan najbliższy

1. Zaimplementować `PaymentManager` + `GatewayInterface` (Sesja 3 modułu płatności).
2. Dodać sekcję „Do sprawdzenia" na dashboardzie dla rezerwacji `payment_method = bank_transfer` + przycisk „Potwierdź przelew".
3. Potwierdzić ręcznie na stagingu: CTA kalendarza, alokację grupy, cenę w modalu, wybór miejsca w łóżku piętrowym.

## Zasada aktualizacji po każdej sesji

- zaktualizować ten dokument w 2 minuty,
- przenieść rzeczy ukończone z planu do sekcji zweryfikowane albo do sekcji wymagających potwierdzenia,
- dopisać maksymalnie 3 najważniejsze następne kroki,
- jeśli pojawił się problem, wpisać go do sekcji do dopracowania zamiast chować go w logach albo pojedynczym pliku tekstowym.