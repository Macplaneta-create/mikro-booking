# Następna Sesja - MikroPlaneta Booking

Data aktualizacji: 2026-03-27  
Status: ręczny wybór miejsca przez `place_ids`, podgląd ceny noclegu, fallback przycisku „Rezerwacje” i ranking alokacji grup (`groupSearch`) są już dopięte w kodzie, ale przed domknięciem stagingu trzeba je kliknąć i potwierdzić manualnie w realnym UI

## Top 3 działania

### 1. Potwierdzenie ręcznego wyboru miejsca w kalendarzu

- [ ] kliknąć manualnie scenariusz: wybrać konkretne wolne miejsce w łóżku piętrowym z kalendarza -> zapisać rezerwację -> sprawdzić w szczegółach, że zapisane zostały te same `place_ids`,
- [ ] sprawdzić regresję dla częściowego obłożenia: nowa rezerwacja, edycja oraz check-in z mniejszą liczbą gości,
- [ ] jeśli staging pokaże rozjazd między wyborem a zapisem, dopisać dokładny przypadek do `STATUS.md` jako blocker.

### 2. Widok kalendarza i modal rezerwacji

- [ ] potwierdzić ręcznie działanie przycisku `Rezerwacje` zarówno z CTA w siatce, jak i z fallbacku w toolbarze,
- [ ] potwierdzić manualnie, że modal tworzenia rezerwacji pokazuje cenę noclegu także przy automatycznym doborze łóżek,
- [ ] potwierdzić po poprawce pełny flow: wybór terminu w kalendarzu -> klik `Rezerwacje` -> poprawna cena w modalu.

### 3. Alokacja grup

- [ ] potwierdzić ręcznie scenariusz grupy 8 osób po zmianie rankingu `groupSearch`,
- [ ] sprawdzić, czy system wybiera wariant `single_room` (dorm), jeśli cały skład mieści się w jednym pokoju,
- [ ] sprawdzić wynikową cenę i brak regresji względem mniejszych grup.
