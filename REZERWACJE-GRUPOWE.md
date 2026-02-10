# Rezerwacje Grupowe - Instrukcja Użytkowania

## Przegląd
System rezerwacji grupowych pozwala na utworzenie jednej rezerwacji obejmującej wiele łóżek jednocześnie. Jest to idealne rozwiązanie dla grup, rodzin lub innych sytuacji, gdzie jedna osoba rezerwuje kilka miejsc.

## Jak utworzyć rezerwację grupową?

### Krok 1: Wybierz termin
1. Otwórz widok **Kalendarz**
2. Kliknij na datę **przyjazdu** na wybranym łóżku
3. Kliknij na datę **wyjazdu** na tym samym łóżku
4. Zobaczysz podświetlony zakres dat

### Krok 2: Dodaj dodatkowe łóżka
1. Przytrzymaj klawisz **Ctrl** (lub **Cmd** na Mac)
2. Kliknij na inne łóżka, które chcesz dodać do rezerwacji
3. Możesz klikać na łóżka w różnych pokojach
4. System automatycznie sprawdzi dostępność każdego łóżka
5. Wybrane łóżka będą podświetlone na **niebiesko**

### Krok 3: Potwierdź wybór
1. Kliknij przycisk **"ZAREZERWUJ"** który pojawi się nad kalendarzem
2. W oknie rezerwacji zobaczysz:
   - Wybrany zakres dat
   - Liczbę wybranych łóżek
   - Listę wszystkich łóżek w rezerwacji
   - Automatycznie obliczoną cenę (suma za wszystkie łóżka)

### Krok 4: Wybierz gościa i zatwierdź
1. Wyszukaj lub dodaj nowego gościa
2. Uzupełnij liczbę dorosłych i dzieci
3. Dodaj ewentualne notatki
4. Kliknij **"Zatwierdź Rezerwację"**

## Ważne informacje

### Dostępność
- System sprawdza dostępność **każdego** łóżka osobno
- Jeśli którekolwiek łóżko jest zajęte w wybranym terminie, nie będzie można go dodać
- Otrzymasz komunikat: "Łóżko #X nie jest dostępne w wybranym terminie"

### Cena
- Cena jest automatycznie obliczana jako **suma** cen wszystkich wybranych łóżek
- Uwzględnia ceny weekendowe i bazowe dla każdego łóżka osobno
- Cena jest wyświetlana przed zatwierdzeniem rezerwacji

### Zarządzanie rezerwacją grupową
- Rezerwacja grupowa jest traktowana jako **jedna** rezerwacja
- Anulowanie rezerwacji zwalnia **wszystkie** łóżka jednocześnie
- Wszystkie łóżka mają ten sam termin przyjazdu i wyjazdu
- Wszystkie łóżka są przypisane do tego samego gościa

## Przykład użycia

**Scenariusz:** Rodzina 4-osobowa potrzebuje 2 łóżka na 3 noce

1. Wybierz termin: 10-13 marca na pierwszym łóżku
2. Przytrzymaj Ctrl i kliknij drugie łóżko
3. Kliknij "ZAREZERWUJ"
4. Wybierz gościa (np. Jan Kowalski)
5. Ustaw: 2 dorosłych, 2 dzieci
6. Zatwierdź

**Rezultat:** Jedna rezerwacja na nazwisko Jan Kowalski, obejmująca 2 łóżka, 10-13 marca, dla 4 osób.

## Wskazówki

✅ **Dobrze:**
- Wybierz najpierw termin, potem dodatkowe łóżka
- Sprawdź dostępność wszystkich łóżek przed potwierdzeniem
- Używaj rezerwacji grupowych dla rodzin i grup

❌ **Unikaj:**
- Tworzenia osobnych rezerwacji dla tej samej grupy
- Dodawania łóżek przed wybraniem terminu
- Mieszania różnych terminów w jednej rezerwacji

## Baza danych

### Migracja
Aby system rezerwacji grupowych działał poprawnie, musisz uruchomić migrację bazy danych:

1. Przejdź do **Booking → Migrations**
2. Kliknij **"Uruchom Oczekujące Migracje"**
3. Poczekaj na potwierdzenie

### Struktura
- Tabela `reservation_beds` przechowuje relacje między rezerwacjami a łóżkami
- Jedna rezerwacja może mieć wiele łóżek
- Jedno łóżko może być w wielu rezerwacjach (w różnych terminach)

## Rozwiązywanie problemów

### Nie mogę dodać łóżka (Ctrl+klik nie działa)
- Upewnij się, że najpierw wybrałeś zakres dat
- Sprawdź czy łóżko jest dostępne w tym terminie
- Spróbuj odświeżyć stronę (Ctrl+F5)

### Błąd podczas tworzenia rezerwacji
- Sprawdź czy wszystkie wybrane łóżka są nadal dostępne
- Upewnij się, że wybrałeś gościa
- Sprawdź logi błędów w pliku `debug-log.txt`

### Nie widzę opcji rezerwacji grupowej
- Upewnij się, że migracja bazy danych została wykonana
- Sprawdź czy frontend został poprawnie zbudowany (`npm run build`)
- Wyczyść cache przeglądarki

## Wsparcie techniczne

W razie problemów sprawdź:
1. **Logi błędów:** `wp-content/plugins/mikro-booking/debug-log.txt`
2. **Status migracji:** Booking → Migrations
3. **Konsola przeglądarki:** F12 → Console

---

*Dokumentacja wygenerowana dla MikroPlaneta Booking v1.0.0*
