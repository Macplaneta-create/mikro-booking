# Status Projektu

Ostatnia aktualizacja: 2026-03-27

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
| Rezerwacje podstawowe | Zaimplementowane, do potwierdzenia | średni | potwierdzić manualnie flow kalendarza (CTA w siatce + toolbar), alokację grup oraz przeliczanie ceny w modalu |
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
| Płatności online | Planowane | wysoki | rozpocząć MVP od jednego providera |
| iCal import / OTA | Planowane | wysoki | po testach zewnętrznych |
| AI FAQ | Planowane | wysoki | dopiero po płatnościach i integracjach |

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
- sprawdzić algorytm alokacji grup: przy grupie 8 osób system rozrzucił gości po kilku pokojach mimo dostępnego pokoju wieloosobowego.
- potwierdzić ręcznie, że modal tworzenia rezerwacji po wyborze terminu w kalendarzu pokazuje poprawną cenę noclegu także przy automatycznym doborze łóżek.
- potwierdzić ręcznie w realnym UI, że kalendarz zachowuje wybrane `place_ids` przy tworzeniu rezerwacji i nie wpada ponownie w automatyczną alokację dla ręcznie wskazanego miejsca.
## Plan najbliższy

1. Potwierdzić ręcznie na stagingu / w realnym UI WordPressa, że wybór konkretnego miejsca w kalendarzu zapisuje te same `place_ids` i działa dla częściowo zajętego łóżka piętrowego.
2. Potwierdzić ręcznie działanie CTA „Rezerwacje" w widoku kalendarza (siatka + toolbar) oraz poprawną cenę w modalu tworzenia rezerwacji.
3. Zweryfikować i poprawić alokację grup, żeby preferowała pokój wieloosobowy przed rozrzucaniem grupy po kilku pokojach.

## Zasada aktualizacji po każdej sesji

- zaktualizować ten dokument w 2 minuty,
- przenieść rzeczy ukończone z planu do sekcji zweryfikowane albo do sekcji wymagających potwierdzenia,
- dopisać maksymalnie 3 najważniejsze następne kroki,
- jeśli pojawił się problem, wpisać go do sekcji do dopracowania zamiast chować go w logach albo pojedynczym pliku tekstowym.