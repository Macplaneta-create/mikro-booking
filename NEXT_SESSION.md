# Następna Sesja - MikroPlaneta Booking

Data aktualizacji: 2026-03-27  
Status: ręczny wybór miejsca przez `place_ids`, podgląd ceny noclegu i fallback przycisku „Rezerwacje” w toolbarze kalendarza są już dopięte w kodzie, ale przed domknięciem stagingu trzeba je kliknąć w realnym UI i zamknąć jeszcze alokację grup

## Top 3 działania

### 1. Potwierdzenie ręcznego wyboru miejsca w kalendarzu

- [ ] kliknąć manualnie scenariusz: wybrać konkretne wolne miejsce w łóżku piętrowym z kalendarza -> zapisać rezerwację -> sprawdzić w szczegółach, że zapisane zostały te same `place_ids`,
- [ ] sprawdzić regresję dla częściowego obłożenia: nowa rezerwacja, edycja oraz check-in z mniejszą liczbą gości,
- [ ] jeśli staging pokaże rozjazd między wyborem a zapisem, dopisać dokładny przypadek do `STATUS.md` jako blocker.

### 2. Widok kalendarza i modal rezerwacji

- [ ] sprawdzić, dlaczego przycisk `Rezerwacje` w widoku kalendarza nie działa poprawnie,
- [ ] potwierdzić manualnie, że modal tworzenia rezerwacji pokazuje cenę noclegu także przy automatycznym doborze łóżek,
- [ ] potwierdzić po poprawce pełny flow: wybór terminu w kalendarzu -> klik `Rezerwacje` -> poprawna cena w modalu.

### 3. Alokacja grup

- [ ] prześledzić algorytm doboru łóżek / pokoi dla grupy, bo przy grupie 8 osób system rozrzucił gości po kilku pokojach,
- [ ] dodać lub poprawić regułę preferencji dla pokoju wieloosobowego, jeśli cały skład mieści się w jednym dormie,
- [ ] potwierdzić to ręcznie scenariuszem grupy 8 osób i sprawdzić wynikową cenę.

- [ ] po domknięciu punktów 1-3 wrócić do `tools/release-go-nogo.ps1` na stagingu.
