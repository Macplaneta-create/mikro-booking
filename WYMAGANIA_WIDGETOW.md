# 📋 Wymagania - Widgety Rezerwacji

**Data:** 2026-02-28
**Wersja:** 1.0

---

## 🎯 Typy Widgetów

### **1. Globalny Widget `[mikroplaneta_booking]`**

**Przeznaczenie:** Ogólne zapytanie o dostępność w całym obiekcie

**Funkcje:**
- ✅ Pokazuje wszystkie dostępne łóżka w obiekcie
- ✅ Użytkownik wybiera daty i liczbę osób
- ✅ System sprawdza dostępność
- ✅ System liczy cenę za miejsca (nie za łóżka)
- ✅ Formularz z danymi gościa
- ✅ Wysyłanie rezerwacji

**Czego NIE robi:**
- ❌ Nie przypisuje do konkretnego pokoju
- ❌ Nie pokazuje wyboru łóżek (tylko dostępność)

**Oczekiwane zachowanie:**
1. **Krok 1:** Daty + liczba osób → sprawdzanie dostępności
2. **Krok 2:** Dane gościa → podsumowanie z ceną
3. **Wysyłanie:** Blokada formularza po sukcesie
4. **Komunikat:** Wyraźne potwierdzenie lub błąd

---

### **2. Karta Pokoju `[mikroplaneta_room_card room_id="X"]`**

**Przeznaczenie:** Rezerwacja konkretnego pokoju

**Funkcje:**
- ✅ Pokazuje zdjęcie i informacje o pokoju
- ✅ Pokazuje pojemność (max osób)
- ✅ Pokazuje udogodnienia
- ✅ Formularz z datami i liczbą osób
- ✅ Przycisk "Rezerwuj" → otwiera modal
- ✅ Modal z danymi gościa
- ✅ Walidacja: liczba osób ≤ pojemność pokoju

**Czego NIE robi:**
- ❌ Nie pokazuje wyboru łóżek (tylko informacyjnie)
- ❌ Nie pozwala rezerwować więcej niż pojemność

**Oczekiwane zachowanie:**
1. **Karta:** Zdjęcie + info + przycisk
2. **Modal:** Daty + osoby → dane gościa → wysłanie
3. **Blokada:** Formularz zablokowany po wysłaniu
4. **Komunikat:** Wyraźne potwierdzenie

---

### **3. Tryby Cen**

#### **A. per_room (pokoje standard/deluxe)**
- Cena: **X zł za POKÓJ** (nie za osobę)
- 1 osoba = X zł
- 2 osoby = X zł (cały pokój)
- Liczba osób: tylko do walidacji pojemności

**Przykład:**
```
Pokój deluxe: 200 zł/noc
- 1 dorosły = 200 zł
- 2 dorosłych = 200 zł
- 2 dorosłych + 1 dziecko = 200 zł (jeśli pojemność pozwala)
```

#### **B. per_bed (pokoje wieloosobowe/dormitory)**
- Cena: **X zł za MIEJSCE/OSOBĘ**
- 1 osoba = X zł
- 2 osoby = 2X zł
- Dziecko = X zł × 0.5

**Przykład:**
```
Pokój wieloosobowy: 100 zł/osobę
- 1 dorosły = 100 zł
- 2 dorosłych = 200 zł
- 2 dorosłych + 2 dzieci = 300 zł
```

---

## 🐛 Znane Błędy

### **1. Globalny Widget - "undefined" przy łóżkach**

**Objaw:** W kroku 1 widoczne "undefined" zamiast informacji o łóżkach

**Przyczyna:** Renderowanie listy łóżek gdy nie jest potrzebna

**Naprawa:** Ukryć sekcję wyboru łóżek w globalnym widżecie

---

### **2. Brak Blokady po Wysyłce**

**Objaw:** Można wysłać rezerwację wielokrotnie

**Przyczyna:** Brak blokady formularza po sukcesie

**Naprawa:** 
- Zablokować wszystkie inputy
- Wyszarzyć formularz (opacity: 0.7)
- Wyłączyć przyciski

---

### **3. Komunikat o Sukcesie**

**Objaw:** Mały, niewyraźny komunikat

**Przyczyna:** Zły styl CSS

**Naprawa:** 
- Większy font
- Zielone tło
- Ikona sukcesu
- Wyraźny tekst

---

## ✅ Checklista Testowa

### **Globalny Widget:**
- [ ] Wybór dat (przeszłe zablokowane)
- [ ] Wybór liczby osób
- [ ] Sprawdzenie dostępności
- [ ] Cena liczy się poprawnie (per_bed)
- [ ] Brak wyboru łóżek (tylko informacja)
- [ ] Formularz z danymi gościa
- [ ] Walidacja pól
- [ ] Blokada po wysłaniu
- [ ] Komunikat sukcesu
- [ ] Komunikat błędu

### **Karta Pokoju:**
- [ ] Zdjęcie pokoju
- [ ] Nazwa i opis
- [ ] Pojemność (max osób)
- [ ] Udogodnienia
- [ ] Przycisk "Rezerwuj"
- [ ] Modal z formularzem
- [ ] Walidacja: osoby ≤ pojemność
- [ ] Cena (per_room lub per_bed)
- [ ] Blokada po wysłaniu
- [ ] Komunikat sukcesu

---

## 📝 Scenariusze Testowe

### **Scenariusz 1: Dormitory (per_bed)**
```
Widget: Globalny lub Karta dormitory
Cena: 100 zł/osobę
Mnożniki: dziecko 0.5×

Test:
- 1 dorosły = 100 zł ✅
- 2 dorosłych = 200 zł ✅
- 2 dorosłych + 2 dzieci = 300 zł ✅
```

### **Scenariusz 2: Pokój Deluxe (per_room)**
```
Widget: Karta pokoju
Cena: 200 zł/pokój
Tryb: per_room

Test:
- 1 dorosły = 200 zł ✅
- 2 dorosłych = 200 zł ✅
- 2 dorosłych + 1 dziecko = 200 zł ✅
```

### **Scenariusz 3: Walidacja Pojemności**
```
Widget: Karta pokoju (max 4 osoby)

Test:
- 4 osoby = można zarezerwować ✅
- 5 osób = błąd walidacji ✅
```

---

## 🎨 UI/UX Wymagania

### **Komunikaty:**
- **Sukces:** Zielone tło, ikona ✓, duży tekst
- **Błąd:** Czerwone tło, ikona ✗, konkretny powód
- **Ładowanie:** Niebieskie tło, animacja

### **Blokada Formularza:**
- Wszystkie inputy: `disabled`
- Tło formularza: `opacity: 0.7`
- Przyciski: `disabled`
- Kursor: `not-allowed`

### **Responsywność:**
- Mobile: 1 kolumna
- Tablet: 2 kolumny
- Desktop: zgodnie z projektem

---

## 🔧 Do Naprawy (Priorytety)

| Błąd | Priorytet | Status |
|------|-----------|--------|
| **"undefined" przy łóżkach** | 🔴 Wysoki | ❌ DO NAPRAWY |
| **Brak blokady po wysyłce** | 🔴 Wysoki | ❌ DO NAPRAWY |
| **Niewyraźny komunikat** | 🟡 Średni | ❌ DO NAPRAWY |
| **Kalendarz: łóżka ≠ miejsca** | 🟡 Średni | ❌ DO NAPRAWY |

---

## 📊 Status Implementacji

| Funkcja | Globalny | Karta | Status |
|---------|----------|-------|--------|
| **Cena per_bed** | ✅ | ✅ | DZIAŁA |
| **Cena per_room** | N/A | ✅ | DZIAŁA |
| **Walidacja dat** | ✅ | ✅ | DZIAŁA |
| **Walidacja osób** | ✅ | ✅ | DZIAŁA |
| **Blokada formularza** | ❌ | ❌ | DO NAPRAWY |
| **Komunikat sukcesu** | ❌ | ❌ | DO NAPRAWY |
| **Brak wyboru łóżek** | ❌ | ✅ | CZĘŚCIOWO |

---

**Notatki:**
- Łóżko piętrowe = 2 miejsca (informacja o pojemności)
- Cena za miejsce (osobę), nie za łóżko
- Mnożniki łóżek nie używane
- per_room = cena stała za pokój
- per_bed = cena za osobę
