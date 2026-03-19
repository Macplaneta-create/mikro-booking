# QA Checklist

Aktualna checklista ręcznej regresji przed releasem lub testami zewnętrznymi.

## 0a) Ekspres 5 min (minimum przed publikacją)

- [ ] Strona z `mikroplaneta_availability_calendar` ładuje siatkę miesiąca bez błędu.
- [ ] `Rezerwuj` z kalendarza otwiera formularz i kończy się sukcesem testowej rezerwacji.
- [ ] Mail potwierdzający ma poprawną treść PL i załącznik `.ics`.
- [ ] `Testuj przypomnienia (Cron)` działa i nie generuje duplikatów po drugim kliknięciu.
- [ ] Brak nowych błędów krytycznych w `wp-content/debug.log`.

## 0b) Alokacja miejsc (nowy mechanizm) — szybki gate

- [ ] Na instalacji aktualizowanej uruchomiona migracja `025-create-reservation-places.php` (status migracji bez pozycji pending dla 025).
- [ ] Publiczne `/public/availability/beds` zwraca pola `capacity`, `available_places`, `occupied_places`.
- [ ] Adminowe `/availability/beds` i `/availability/group-search` zwracają te same pola dla łóżek.
- [ ] Dla łóżka piętrowego częściowo zajętego API pokazuje `available_places = 1` (zamiast blokowania całego łóżka).

## 0) Szybka regresja 20-30 min (smoke)

- [ ] W Ustawieniach sprawdź działanie wszystkich przycisków `Kopiuj` (shortcode główny, kalendarz dostępności, generator pokoju, Redirect URI).
- [ ] Na stronie z shortcode `mikroplaneta_availability_calendar` wybierz miesiąc i kliknij `Odśwież kalendarz`.
- [ ] Potwierdź, że siatka miesięczna pokazuje dostępność w formacie `X/Y` dla pokoi/domków.
- [ ] Kliknij `Rezerwuj` przy wybranym obiekcie i potwierdź otwarcie formularza z poprawnym pokojem.
- [ ] Dokończ rezerwację z frontu i potwierdź zapis + mail + wpis `sent` w historii notyfikacji.
- [ ] W adminie edytuj daty istniejącej rezerwacji i potwierdź poprawne odświeżenie dashboardu.
- [ ] W adminie wybierz `Ctrl+klik` kilka łóżek (np. 10), kliknij `Rezerwuj` i potwierdź: domyślna liczba osób = pojemność zaznaczonych łóżek oraz cena nie jest liczona jak dla 1 osoby.
- [ ] Uruchom `Testuj przypomnienia (Cron)` i potwierdź czytelny komunikat z detalami oraz brak duplikatów przy drugim uruchomieniu.

## 1) Dashboard: Backup & Export

- [ ] Klik `Eksport CSV` i potwierdź pobranie pliku bez błędu `rest_no_route`.
- [ ] Klik `Backup Bazy` i potwierdź pobranie pliku SQL.
- [ ] Klik `Test Email` i potwierdź czytelny komunikat bez `undefined`.
- [ ] Sprawdź, że widoczna jest notka o wymaganym SMTP / transporcie maili.

## 2) Ustawienia: Cron testy ręczne

- [ ] Klik `Testuj wygasanie (Cron)` i potwierdź komunikat sukcesu.
- [ ] Klik `Testuj przypomnienia (Cron)` i potwierdź komunikat sukcesu.
- [ ] Powtórz `Testuj przypomnienia (Cron)` drugi raz tego samego dnia i potwierdź brak duplikatów w logu notyfikacji.

## 3) Rezerwacja publiczna

- [ ] Utwórz nową rezerwację z frontu.
- [ ] Zweryfikuj poprawność walidacji dat i komunikatów formularza.
- [ ] Potwierdź, że CAPTCHA działa zgodnie z ustawionym providerem.
- [ ] Potwierdź, że email po utworzeniu rezerwacji zawiera załącznik `.ics`.
- [ ] Kliknij przycisk "Dodaj do kalendarza" w mailu i potwierdź pobranie `.ics` bez logowania do WP admina.
- [ ] Sprawdź `wp-content/debug.log` dla wpisu audytowego `iCal guest download`.

## 4) Rezerwacja w adminie

- [ ] Otwórz nową rezerwację i wykonaj `Potwierdź`.
- [ ] Potwierdź wysyłkę maila po potwierdzeniu rezerwacji.
- [ ] Potwierdź, że mail `confirmed` zawiera załącznik `.ics`.
- [ ] Sprawdź historię zmian rezerwacji i log notyfikacji.

## 4b) Rezerwacja miejsca (place-based) — scenariusze krytyczne

- [ ] Utwórz rezerwację 1 osoby na łóżku piętrowym i potwierdź, że druga osoba może zarezerwować to samo łóżko (drugie miejsce) w tym samym zakresie dat.
- [ ] Edytuj rezerwację grupową i zmniejsz liczbę osób: potwierdź, że zwolnione miejsca wracają do dostępności w API oraz w widoku publicznym.
- [ ] W check-in ustaw mniejszą liczbę gości i wybierz zwolnienie nadmiarowych łóżek: potwierdź poprawny przelicznik miejsc po zapisie.
- [ ] W widgetach publicznych i adminie pojemność/wybór bazuje na `capacity`/`available_places`, a nie na samym `bed_type`.

## 5) Przypomnienia mailowe

- [ ] Przygotuj rezerwację z check-in = jutro i status `confirmed` lub `paid`.
- [ ] Przygotuj rezerwację z check-out = jutro i status `checked_in`.
- [ ] Uruchom `Testuj przypomnienia (Cron)`.
- [ ] Potwierdź, że wysyłane są odpowiednie typy przypomnień.

## 6) Ustawienia i bezpieczeństwo

- [ ] Upewnij się, że `email_notifications` jest w oczekiwanym stanie.
- [ ] Sprawdź ustawienia limitu REST.
- [ ] Sprawdź retencję plików backup/iCal.

## 7) Regresja techniczna

- [ ] Build admina przechodzi.
- [ ] Testy integracyjne przechodzą.

## 8) Środowisko staging / produkcja

- [ ] SMTP działa testowo.
- [ ] WP-Cron działa lub skonfigurowano cron systemowy.
- [ ] Czas serwera i timezone WordPress są poprawne.

## 9) Go / No-Go

- [ ] Wszystkie krytyczne punkty zaliczone.
- [ ] Brak blockerów dla release.
- [ ] Decyzja: GO / NO-GO.