# Następna Sesja - MikroPlaneta Booking

Data aktualizacji: 2026-03-29
Status: Plan modułu płatności jest gotowy i zatwierdzony. Następna sesja = Sesja 1 implementacji.

---

## 🎯 Priorytet Sesji: Moduł Płatności — Sesja 1 z 6

Pełny plan techniczny: `implementation_plan.md` (artifact w tej sesji, konwersacja 5860694c).

### Sesja 1 — Migracje + Modele + Repository (~2h)

Czysta praca backendowa, bez żadnych zewnętrznych zależności (nie potrzeba konta Przelewy24).

#### 1. Migracja 026 — tabela `payment_transactions`

Stworzyć plik `core/database/migrations/026-create-payment-transactions.php`:

```sql
id, reservation_id, gateway, gateway_transaction_id (UNIQUE),
amount, currency VARCHAR(3), status ENUM(...), payment_method,
idempotency_key (UNIQUE), raw_response, ip_address, created_at, updated_at
```

Szczegółowy schemat w `implementation_plan.md`.

#### 2. Migracja 027 — kolumna `payment_method` w `reservations`

Stworzyć `core/database/migrations/027-add-payment-method-to-reservations.php`:
- Dodaje kolumnę `payment_method ENUM('online', 'bank_transfer', 'none') DEFAULT 'none'`

#### 3. Model `PaymentTransaction`

Stworzyć `core/models/class-payment-transaction.php` — POPO zgodny z istniejącymi modelami (Room, Reservation, itp.).

#### 4. Rozszerzenie modelu `Reservation`

W `core/models/class-reservation.php`:
- Dodać pole `payment_method`
- Dodać stałe: `STATUS_PENDING_PAYMENT = 'pending_payment'`, `STATUS_PAID = 'paid'`

#### 5. Repository `PaymentTransactionRepository`

Stworzyć `core/repositories/class-payment-transaction-repository.php`:
- `create(array $data): ?PaymentTransaction`
- `findByReservationId(int $id): array`
- `findByGatewayTransactionId(string $id): ?PaymentTransaction`
- `updateStatus(int $id, string $status, array $extra = []): bool`
- Wyłącznie `$wpdb->prepare()` — zero raw SQL

---

## 📋 Decyzje podjęte w tej sesji (2026-03-29)

| Decyzja | Szczegół |
|---|---|
| Wielowalutowość | `currency VARCHAR(3)` w tabeli transakcji — gotowe na EUR/GBP/USD |
| Stripe | Osobna wtyczka add-on rejestrowana przez `mikroplaneta_payment_gateways` filter |
| Przelewy24 | Bundled w core jako MVP (rynek PL) |
| Model płatności | Dwa tory: online (auto-confirm przez webhook HMAC) + przelew ręczny (recepcja potwierdza ręcznie) |
| Dashboard UX | 🟢 Zapłacono online / 🟡 Czeka na przelew / ⚪ Bez zaliczki |
| Sandbox | Rejestracja na developers.przelewy24.pl + ngrok do testów lokalnych |
| Analityka | Dwufazowa: Faza 1 statystyki Q3, Faza 2 predykcja trendów (po 3 mies. danych) |
| Add-on model | `mikro-booking-stripe`, `mikro-booking-mollie` jako osobne wtyczki |

---

## ⏭️ Kolejne sesje po Sesji 1

| Sesja | Co |
|---|---|
| 2 | `PaymentSecurity`, `PaymentManager`, interfejs, `GatewayPrzelewy24` |
| 3 | `PaymentsController` — initiate + webhook + refund |
| 4 | Notyfikacje email + Settings UI (klucze API) |
| 5 | Dashboard UI — `PaymentStatusBadge` + sekcja „Do sprawdzenia" |
| 6 | Testy z sandbox Przelewy24 + ngrok |

---

## 🔧 Stare zadania (nadal otwarte, niższy priorytet)

Poniższe były priorytetem przed sesją 2026-03-29.
Po domknięciu Sesji 1 płatności — wróć do nich lub pomiń jeśli staging potwierdzi działanie:

- [ ] Ręczny test wyboru `place_ids` w kalendarzu (łóżko piętrowe)
- [ ] Ręczny test przycisku „Rezerwacje" w toolbarze kalendarza
- [ ] Ręczny test ceny w modalu tworzenia rezerwacji
- [ ] Ręczny test alokacji grupy 8 osób (groupSearch ranking)

