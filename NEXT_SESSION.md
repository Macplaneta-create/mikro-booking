# Następna Sesja - MikroPlaneta Booking

Data aktualizacji: 2026-04-09
Status: Sesja 1+2 płatności zakończona + auto-migracje naprawione + wiadomość do gościa z recepcji. Następna sesja = Sesja 3 (PaymentManager + gateway interface).

---

## 🎯 Top 3 priorytety następnej sesji

### 1. PaymentManager + gateway interface (Sesja 3, ~2h)

Stworzyć `core/payments/interface-gateway.php`:
```php
interface GatewayInterface {
    public function getName(): string;
    public function initiate(Reservation $reservation, array $options): array;
    public function handleWebhook(array $payload, string $signature): bool;
    public function refund(int $transaction_id, float $amount): bool;
}
```

Stworzyć `core/payments/class-payment-manager.php`:
- `registerGateway(GatewayInterface $gateway): void`
- `getGateway(string $name): ?GatewayInterface`
- `getAvailableGateways(): array`
- Aplikuje filter `mikroplaneta_payment_gateways` w konstruktorze

Zarejestrować oba pliki w `class-plugin.php`.

### 2. Dashboard — sekcja „Do sprawdzenia" (powiązane z Sesją 3)

W `DashboardContent.tsx` dodać sekcję z rezerwacjami gdzie `payment_method = 'bank_transfer'` i status `pending`:
- Lista posortowana wg terminu wygaśnięcia
- Przycisk „Potwierdź przelew" → zmienia status na `confirmed`

### 3. Regresja manualna UI

- Ręczny test przycisku „Rezerwuj zaznaczone" / „ZAREZERWUJ" w kalendarzu
- Ręczny test wyboru miejsca w łóżku piętrowym i zapis `place_ids`
- Ręczny test ceny w modalu tworzenia rezerwacji przy automatycznym doborze łóżek

---

## 📋 Decyzje architektoniczne (aktualne)

| Decyzja | Szczegół |
|---|---|
| Przelew ręczny | W core `mikro-booking` — zaimplementowany ✅ |
| Bramki online | Osobna wtyczka `mikro-booking-payments` przez filter |
| Filter | `mikroplaneta_payment_gateways` — model WooCommerce |
| Przelewy24 | Będzie w `mikro-booking-payments`, nie w core |

