# Status Projektu

Ostatnia aktualizacja: 2026-03-15

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
| Rezerwacje podstawowe | Zaimplementowane, do potwierdzenia | średni | potwierdzić pełny flow na świeżym WordPressie |
| Backup CSV / SQL | Zaimplementowane, do potwierdzenia | średni | test z paczki ZIP i test cronów |
| iCal dla gości | Zaimplementowane, do potwierdzenia | średni | potwierdzić link CTA i logi audytowe |
| Health Check | Zaimplementowane, do potwierdzenia | średni | test w środowisku zewnętrznym |
| Retry maili i locki cron | Zaimplementowane, do potwierdzenia | średni | regresja notyfikacji i brak duplikatów |
| Onboarding | Zaimplementowane, do potwierdzenia | niski | przejść wizard na czystej instalacji |
| i18n / WordPress.org | W trakcie | niski | sprawdzić tłumaczenia i paczkę release |
| Google Calendar BYOK eksport | Zaimplementowane, do potwierdzenia | niski | autoryzacja, sync i test callbacku |
| Kalendarz dostępności (publiczny shortcode) | Zaimplementowane, do potwierdzenia | średni | regresja frontu: siatka miesięczna + CTA Rezerwuj + responsywność |
| Ustawienia admina (czytelność + kopiowanie) | Zaimplementowane, do potwierdzenia | średni | potwierdzić komunikaty i działanie wszystkich przycisków Kopiuj na różnych przeglądarkach |
| Płatności online | Planowane | wysoki | rozpocząć MVP od jednego providera |
| iCal import / OTA | Planowane | wysoki | po testach zewnętrznych |
| AI FAQ | Planowane | wysoki | dopiero po płatnościach i integracjach |

## Zweryfikowane ostatnio

- uporządkowano dokumentację i rozdzielono: status, plan, QA, release,
- zarchiwizowano snapshoty wersji 1.2.7,
- główne checklisty są aktywne i mają jasne role.
- wykonano testy na żywej instalacji zewnętrznej i zebrano listę problemów UX/komunikatów.
- dodano skrócony proces GO/NO-GO i runbook `tools/release-go-nogo.ps1` z raportem decyzji.

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

## Plan najbliższy

1. Regresja po wdrożonych poprawkach UX/mail/dashboard/widget oraz wyceny grupowej w adminie.
2. Decyzja GO/NO-GO na stagingu z użyciem runbooka release.
3. Płatności MVP.

## Zasada aktualizacji po każdej sesji

- zaktualizować ten dokument w 2 minuty,
- przenieść rzeczy ukończone z planu do sekcji zweryfikowane albo do sekcji wymagających potwierdzenia,
- dopisać maksymalnie 3 najważniejsze następne kroki,
- jeśli pojawił się problem, wpisać go do sekcji do dopracowania zamiast chować go w logach albo pojedynczym pliku tekstowym.