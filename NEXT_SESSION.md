# Następna Sesja - MikroPlaneta Booking

Data aktualizacji: 2026-03-19  
Status: przed domknięciem stagingu trzeba naprawić problemy wykryte w realnym UI admina: przycisk „Rezerwacje”, alokację grup i przeliczanie ceny w modalu

## Top 4 działania

### 1. Ręczny wybór miejsca z kalendarza jest nadpisywany przez optymalizator (BLOKER)

- [ ] znaleźć w backendzie (`ReservationService` lub `AvailabilityService`) miejsce, gdzie `place_ids` przekazane przez frontend są ignorowane lub ponownie alokowane,
- [ ] rozdzielić ścieżkę: jeśli request zawiera `place_ids` → zachować je bez zmian; jeśli brak `place_ids` → uruchomić algorytm optymalizacji (tryb automatyczny),
- [ ] potwierdzić manualnie: wybrać konkretne „Dół" w łóżku piętrowym z kalendarza → zapis rezerwacji → ta sama alokacja widoczna w szczegółach.

### 2. Widok kalendarza i modal rezerwacji

- [ ] sprawdzić, dlaczego przycisk `Rezerwacje` w widoku kalendarza nie działa poprawnie,
- [ ] naprawić modal tworzenia rezerwacji, aby podliczał cenę noclegu zamiast tylko pościeli / usług dodatkowych,
- [ ] potwierdzić po poprawce pełny flow: wybór terminu w kalendarzu -> klik `Rezerwacje` -> poprawna cena w modalu.

### 3. Alokacja grup

- [ ] prześledzić algorytm doboru łóżek / pokoi dla grupy, bo przy grupie 8 osób system rozrzucił gości po kilku pokojach,
- [ ] dodać lub poprawić regułę preferencji dla pokoju wieloosobowego, jeśli cały skład mieści się w jednym dormie,
- [ ] potwierdzić to ręcznie scenariuszem grupy 8 osób i sprawdzić wynikową cenę.

### 4. Powrót do stagingu

- [ ] wrócić do ręcznego testu recepcjonisty po poprawkach z punktów 1-3,
- [ ] uruchomić `tools/release-go-nogo.ps1` interaktywnie na stagingu,
- [ ] przy NO-GO dopisać blocker do `STATUS.md` i poprawić przed publikacją.
