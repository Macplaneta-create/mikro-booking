# 📋 TODO - Naprawy i Zmiany

**Data:** 2026-02-28
**Status:** W toku

---

## 🚨 PILNE - Błędy do naprawy

### 1. **Backend - Kalendarz: Liczba łóżek ≠ liczba osób**

**Problem:**
- Rezerwacja: 4 dorosłych + 3 dzieci = 7 osób
- System pokazuje: **10 łóżek zajętych** ❌
- Powinno: **7 miejsc zajętych** ✅

**Gdzie:**
- Panel admina → Kalendarz → Edycja rezerwacji
- Modal pokazuje ostrzeżenie o 10 łóżkach dla 7 osób

**Przyczyna:**
- System liczy **łóżka** zamiast **miejsc**
- Łóżko piętrowe = 1 łóżko, ale 2 miejsca
- System błędnie mnoży liczbę łóżek

**Do zrobienia:**
1. Zmienić logikę w `class-reservation-service.php` - liczyć miejsca, nie łóżka
2. Zaktualizować UI w kalendarzu - pokazywać "miejsca" zamiast "łóżka"
3. Naprawić walidację - ostrzeżenie gdy miejsc > dostępnych miejsc, nie łóżek

**Pliki do zmiany:**
- `admin/src/components/calendar/ReservationDetailsModal.tsx`
- `admin/src/components/calendar/EditReservationModal.tsx`
- `core/services/class-reservation-service.php`

---

### 2. **Karty Pokoi (per_room) - Cena za POKÓJ, nie za osobę**

**Problem:**
- Karta pokoju z trybem `per_room` (np. "pokoj deluxe")
- Cena: 200 zł za POKÓJ
- System liczy: 2 osoby × 200 zł = 400 zł ❌
- Powinno: 200 zł za pokój (niezależnie od liczby osób) ❌

**Gdzie:**
- `[mikroplaneta_room_card room_id="X"]` gdzie room ma `pricing_mode = 'per_room'`

**Przyczyna:**
- System mnoży cenę przez liczbę osób
- Dla `per_room` cena jest STAŁA za cały pokój
- Liczba osób służy TYLKO do walidacji pojemności

**Do zrobienia:**
1. W `simple-widget.js` sprawdzić `pricing_mode` pokoju
2. Dla `per_room`: cena stała za pokój (nie mnożyć przez osoby)
3. Dla `per_bed`: cena za miejsce × liczba osób
4. Liczba osób tylko do walidacji (czy się zmieszczą)

**Przykład:**
```
Pokój deluxe (per_room): 200 zł/noc
- 1 dorosły = 200 zł (cały pokój)
- 2 dorosłych = 200 zł (cały pokój)
- 2 dorosłych + 1 dziecko = 200 zł (cały pokój, jeśli pojemność pozwala)

Pokój wieloosobowy (per_bed): 100 zł/osobę
- 1 osoba = 100 zł
- 2 osoby = 200 zł
- 4 osoby = 400 zł
```

**Pliki do zmiany:**
- `public/js/simple-widget.js` - logika calculatePrice
- `core/services/class-pricing-service.php` - calculateGroupPrice dla per_room

---

### 2. **Globalny Widget - Cena za miejsce**

**Status:** ✅ DZIAŁA

**Logika:**
- Łóżko piętrowe = 2 miejsca × 100 zł = 200 zł za łóżko
- 1 osoba = 100 zł (1 miejsce)
- 2 osoby = 200 zł (2 miejsca)
- Dziecko = 50 zł (0.5×)

**Test:**
- Globalny widget `[mikroplaneta_booking]`
- 2 dorosłych = 200 zł ✅
- 4 dorosłych + 1 dziecko = 450 zł ✅

---

### 3. **Karty Pokoi - Cena za miejsce**

**Status:** ✅ DZIAŁA

**Logika:**
- Cena za MIEJSCE (osobę), nie za łóżko
- Łóżka tylko informacyjnie (pojemność)

**Test:**
- `[mikroplaneta_room_card room_id="1"]`
- 4 osoby w dormitory = 400 zł ✅

---

## 📝 USTALENIA - Model Cen

### **Zasady:**

1. **Cena za MIEJSCE (osobę), nie za łóżko**
   - 1 osoba = 1 miejsce × cena
   - Łóżko piętrowe = 2 miejsca (informacja o pojemności)

2. **Mnożniki łóżek USUNIĘTE**
   - Nie mają sensu biznesowego
   - Łóżko podwójne nie jest dla 2 obcych osób

3. **Dwa tryby cen:**

   **A. Dormitory (per_bed):**
   - Cena: X zł za OSOBĘ/MIEJSCE
   - Przykład: 100 zł/osobę
   - 4 osoby = 400 zł

   **B. Pokoje standard (per_room):**
   - Cena: X zł za POKÓJ
   - Przykład: 200 zł/pokój
   - 1-2 osoby = 200 zł (cały pokój)

---

## 🔧 DO ZROBIENIA - Backend Kalendarz

### **Krok 1: Zmienić logikę liczenia**

**Plik:** `core/services/class-reservation-service.php`

**Aktualnie:**
```php
// Liczy łóżka
$beds_count = count($reservation->bed_ids);
```

**Powinno:**
```php
// Liczy miejsca
$places_count = 0;
foreach ($beds as $bed) {
    $places_count += ($bed->bed_type === 'bunk') ? 2 : 1;
}
```

---

### **Krok 2: Zaktualizować UI**

**Plik:** `admin/src/components/calendar/EditReservationModal.tsx`

**Zmienić:**
- "Łóżka: 10" → "Miejsca: 7"
- "Zajęte łóżka" → "Zajęte miejsca"
- Walidacja: miejsca ≤ dostępnych miejsc

---

### **Krok 3: Testować**

1. Otwórz kalendarz
2. Kliknij rezerwację (4 dorosłych + 3 dzieci)
3. Sprawdź czy pokazuje "7 miejsc" nie "10 łóżek"
4. Edytuj liczbę osób → miejsca powinny się aktualizować

---

## ✅ ZROBIONE

### **1. Globalny Widget - Cena**
- ✅ Liczy cenę za miejsce
- ✅ Uwzględnia dzieci (0.5×)
- ✅ Łóżka piętrowe = 2 miejsca

### **2. Karty Pokoi - Cena**
- ✅ Liczy cenę za miejsce
- ✅ Dormitory: cena per_bed
- ✅ Pokoje: cena per_room

### **3. Mnożniki Łóżek**
- ✅ Usunięte mnożniki typów łóżek
- ✅ Cena bazowa × mnożnik dziecka

### **4. UI Łóżek**
- ✅ Pokazuje pojemność łóżka (np. "2 miejsca")
- ✅ Gość widzi ile miejsc na łóżku

---

## 📊 PRIORYTETY

| Zadanie | Priorytet | Status |
|---------|-----------|--------|
| **Naprawić liczenie miejsc w kalendarzu** | 🔴 WYSOKI | ❌ DO ZROBENIA |
| **Przetestować globalny widget** | 🟢 ŚREDNI | ✅ ZROBIONE |
| **Przetestować karty pokoi** | 🟢 ŚREDNI | ✅ ZROBIONE |
| **Dodać informacje o miejscach w UI** | 🟡 NISKI | ✅ ZROBIONE |

---

## 🎯 NASTĘPNE KROKI

1. **Naprawić kalendarz** - liczenie miejsc zamiast łóżek
2. **Przetestować** wszystkie scenariusze rezerwacji
3. **Zaktualizować dokumentację** dla użytkownika

---

**Notatki:**
- Łóżko piętrowe to 2 miejsca, nie 1 łóżko
- Cena jest za miejsce (osobę), nie za łóżko
- Mnożniki łóżek nie mają sensu - usunięte
- Gość musi widzieć ile miejsc rezerwuje
