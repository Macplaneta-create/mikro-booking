# Następna Sesja - MikroPlaneta Booking

Data aktualizacji: 2026-05-25
Status: Testy 47/47 zielone, API płatności zweryfikowane testami PHPUnit, przed końcem sesji zostaje manualne potwierdzenie w realnym WordPressie.

---

## 🎯 Top 3 priorytety następnej sesji

### 1. Potwierdzenie manualne endpointu `/payments/gateways`

- sprawdzić odpowiedź w działającej instalacji WordPress
- potwierdzić brak błędów w realnym środowisku i zgodność kontraktu `gateways` / `total`

### 2. Dokończenie flow dashboardu „Do sprawdzenia"

- dodać sekcję dla rezerwacji `payment_method = bank_transfer`
- podpiąć akcję „Potwierdź przelew" i zweryfikować działania w UI

### 3. Regresja manualna UI

- ręczny test przycisku „Rezerwuj zaznaczone" / „ZAREZERWUJ" w kalendarzu
- ręczny test wyboru miejsca w łóżku piętrowym i zapis `place_ids`
- ręczny test ceny w modalu tworzenia rezerwacji przy automatycznym doborze łóżek

---

## 📋 Decyzje architektoniczne (aktualne)

| Decyzja | Szczegół |
|---|---|
| Przelew ręczny | W core `mikro-booking` — zaimplementowany ✅ |
| Bramki online | Osobna wtyczka `mikro-booking-payments` przez filter |
| Filter | `mikroplaneta_payment_gateways` — model WooCommerce |
| Przelewy24 | Będzie w `mikro-booking-payments`, nie w core |

