# 🔐 Bezpieczeństwo i Wydajność - Audyt Aplikacji
**MikroPlaneta Booking Plugin**
**Data audytu:** 2026-02-23
**Wersja:** 1.2.7

---

## 📊 PODSUMOWANIE

| Kategoria | Ocena | Status | Krytyczne | Wysokie | Średnie | Niskie |
|-----------|-------|--------|-----------|---------|---------|--------|
| **Bezpieczeństwo** | ⚠️ **B+** | Poprawne | 0 | 2 | 3 | 4 |
| **Wydajność** | ✅ **A-** | Bardzo dobra | 0 | 1 | 2 | 2 |
| **Kod jakośd** | ✅ **A-** | Bardzo dobra | 0 | 0 | 2 | 3 |
| **Gotowośd do produkcji** | ⚠️ **B** | Wymaga poprawek | 0 | 3 | 2 | 1 |

---

## 🔴 KRYTYCZNE (0)

✅ **Brak problemów krytycznych!**

---

## 🟠 WYSOKI PRIORYTET (6)

### 1. **Pliki debugowe na produkcji** 🔴 HIGH
**Lokalizacja:** `/*.php` (główny folder)

**Problem:**
```
debug-beds.php
debug-db-v2.php
debug-db.php
debug-pricing.php
test-api.php
test-create-pricing.php
test-get-pricing.php
test-group-pricing.php
test-insert.php
test-update.php
```

**Ryzyko:** 
- Bezpośredni dostęp do plików debugowych
- Możliwośd wykonania dowolnego kodu SQL
- Wyciek danych z bazy

**Rozwiązanie:**
```bash
# USUNĄD przed produkcją!
rm debug-*.php test-*.php

# LUB zabezpiecz .htaccess
# Deny from all
```

**Status:** ⚠️ **WYMAGA NAPRAWY**

---

### 2. **Brak prawdziwego CAPTCHA w publicznym endpoint** 🟠 HIGH
**Lokalizacja:** `rest-api/controllers/class-public-reservations-controller.php:122`

**Problem:**
```php
private function verify_captcha(string $token): bool {
    $simulate = (bool) apply_filters(
        'mikroplaneta_booking_recaptcha_simulate',
        defined('WP_DEBUG') && WP_DEBUG
    );
    if ($simulate) {
        return $token !== '';  // ZAWSZE PASS w debug mode!
    }
    return false;
}
```

**Ryzyko:**
- Spam botów w trybie debug
- Fake rezerwacje
- Atak DoS przez masowe rezerwacje

**Rozwiązanie:**
```php
// Wdrożyć prawdziwe Google reCAPTCHA lub hCaptcha
private function verify_captcha(string $token): bool {
    $secret_key = get_option('mikroplaneta_booking_recaptcha_secret');
    $response = wp_remote_post('https://www.google.com/recaptcha/api/siteverify', [
        'body' => [
            'secret' => $secret_key,
            'response' => $token,
            'remoteip' => $_SERVER['REMOTE_ADDR']
        ]
    ]);
    $result = json_decode(wp_remote_retrieve_body($response), true);
    return isset($result['success']) && $result['success'];
}
```

**Status:** ⚠️ **WYMAGA NAPRAWY PRZED PRODUKCJĄ**

---

### 3. **Logging do pliku tekstowego** 🟠 HIGH
**Lokalizacja:** 
- `rest-api/controllers/class-rooms-controller.php:197, 213`
- `core/repositories/class-room-repository.php:131, 194`

**Problem:**
```php
$log_file = MIKROPLANETA_BOOKING_PLUGIN_DIR . 'debug-log.txt';
file_put_contents($log_file, "[...] " . $e->getMessage() . "\n", FILE_APPEND);
```

**Ryzyko:**
- Plik może być publicznie dostępny
- Brak rotacji logów (może urosd do GB)
- Potencjalny wyciek danych (SQL errors, stack traces)
- Problemy z uprawnieniami na serwerze

**Rozwiązanie:**
```php
// Zamiast file_put_contents:
error_log('[MikroBooking] Room create failed: ' . $e->getMessage());

// Lub WordPress logging:
if (defined('WP_DEBUG') && WP_DEBUG) {
    error_log('[MikroBooking] ' . $e->getMessage());
}
```

**Status:** ⚠️ **WYMAGA NAPRAWY**

---

### 4. **Brak build artifacts w repo** 🟠 HIGH
**Lokalizacja:** `assets/admin/`

**Problem:**
- React app musi byd zbudowany przed produkcją
- `index.js` (366 KB) i `index.css` (38 KB) muszą istnied

**Rozwiązanie:**
```bash
cd admin
npm install
npm run build
```

**Status:** ✅ **NAPRAWIONE** - pliki istnieją

---

### 5. **Brak rate limitingu na REST API** 🟠 HIGH
**Lokalizacja:** Globalnie

**Problem:**
- Brak ochrony przed brute-force
- Brak ochrony przed DoS
- Możliwośd masowego odpytywania API

**Rozwiązanie:**
```php
// Dodać middleware:
add_filter('rest_pre_dispatch', function($result, $server, $request) {
    $ip = $_SERVER['REMOTE_ADDR'];
    $transient = 'rate_limit_' . md5($ip);
    $count = (int) get_transient($transient);
    
    if ($count > 100) { // 100 requests per minute
        return new WP_Error('rate_limit_exceeded', 'Too many requests', ['status' => 429]);
    }
    
    set_transient($transient, $count + 1, 60);
    return $result;
}, 10, 3);
```

**Status:** ❌ **BRAK**

---

### 6. **TODO w kodzie produkcyjnym** 🟠 HIGH
**Lokalizacja:** `core/services/class-reservation-service.php:397`

**Problem:**
```php
// TODO: Implement changes logging to wp_hotel_changes_log table
```

**Ryzyko:**
- Niekompletna funkcjonalnośd
- Brak audit trail dla zmian w rezerwacjach

**Rozwiązanie:**
- Zaimplementowad logging lub usunąd TODO

**Status:** ⚠️ **DO NAPRAWY**

---

## 🟡 ŚREDNI PRIORYTET (7)

### 7. **Hardcoded fallback prices** 🟡 MEDIUM
**Lokalizacja:** `core/services/class-pricing-service.php:47`

**Problem:**
```php
$base_fallback = (float) get_option('mikroplaneta_booking_default_price', 100.00);
```

**Ryzyko:**
- Niepoprawne ceny jeśli admin nie skonfiguruje cennika
- Potencjalne straty finansowe

**Rozwiązanie:**
- Dodać walidację przy zapisie
- Dodać ostrzeżenie w admin UI jeśli brak cennika

**Status:** ⚠️ **DO POPRAWY**

---

### 8. **Brak indeksów w bazie** 🟡 MEDIUM
**Lokalizacja:** Migracje

**Problem:**
- Nie wszystkie kolumny używane w WHERE mają indeksy
- Powolne zapytania przy dużej liczbie rezerwacji

**Rozwiązanie:**
```sql
-- Dodać indeksy:
CREATE INDEX idx_check_in ON wp_hotel_reservations(check_in);
CREATE INDEX idx_check_out ON wp_hotel_reservations(check_out);
CREATE INDEX idx_status ON wp_hotel_reservations(status);
CREATE INDEX idx_room_id ON wp_hotel_beds(room_id);
```

**Status:** ✅ **CZĘŚCIOWO** - niektóre indeksy istnieją

---

### 9. **N+1 queries w REST API** 🟡 MEDIUM
**Lokalizacja:** `rest-api/controllers/class-rooms-controller.php`

**Problem:**
```php
$rooms = $this->room_repository->all($args);
$data = array_map(function($room) {
    $room_data = $room->toArray();
    $beds = $this->bed_repository->findByRoom($id); // Query per room!
    $room_data['beds'] = $beds;
    return $room_data;
}, $rooms);
```

**Rozwiązanie:**
- Eager loading beds w jednym zapytaniu
- Użyj JOIN lub WHERE IN

**Status:** ⚠️ **DO OPTYMALIZACJI**

---

### 10. **Brak cache dla często używanych danych** 🟡 MEDIUM
**Lokalizacja:** Globalnie

**Problem:**
- Każde zapytanie do bazy jest wykonywane od nowa
- Brak transients dla statystyk, cenników

**Rozwiązanie:**
```php
// Przykład: cache dla cen
$cache_key = 'pricing_' . $room_id . '_' . $date;
$price = get_transient($cache_key);
if (false === $price) {
    $price = $this->pricing_repository->findForDate($room_id, $date);
    set_transient($cache_key, $price, HOUR_IN_SECONDS);
}
```

**Status:** ❌ **BRAK**

---

### 11. **Publiczny .git folder** 🟡 MEDIUM
**Lokalizacja:** `/.git/`

**Ryzyko:**
- Historia commitów dostępna publicznie
- Możliwośd odtworzenia kodu źródłowego
- Wyciek kluczy API z historii

**Rozwiązanie:**
```bash
# Na serwerze:
rm -rf .git

# Lub zablokuj .htaccess:
<IfModule mod_alias.c>
    RedirectMatch 403 /\.git
</IfModule>
```

**Status:** ⚠️ **DO ZABEZPIECZENIA**

---

### 12. **Skomplikowane zapytania SQL** 🟡 MEDIUM
**Lokalizacja:** `core/repositories/class-reservation-repository.php`

**Problem:**
```php
$sql = "SELECT r.*, rb.bed_id, g.first_name, g.last_name
        FROM {$this->table} r
        JOIN {$reservation_beds_table} rb ON r.id = rb.reservation_id
        LEFT JOIN {$this->guests_table} g ON r.guest_id = g.id
        WHERE 1=1";
// + wiele warunków WHERE
```

**Ryzyko:**
- Powolne przy dużej liczbie rezerwacji
- Brak EXPLAIN na zapytaniach

**Rozwiązanie:**
- Użyj EXPLAIN do analizy
- Rozważ denormalizację dla często używanych danych

**Status:** ⚠️ **DO MONITOROWANIA**

---

### 13. **Brak walidacji inputów w REST API** 🟡 MEDIUM
**Lokalizacja:** Niektóre controllery

**Problem:**
```php
public function create_item($request): WP_REST_Response {
    $params = $request->get_params(); // Bez walidacji!
    // ...
}
```

**Rozwiązanie:**
```php
// Walidacja w register_rest_route:
'args' => [
    'name' => [
        'required' => true,
        'type' => 'string',
        'minLength' => 3,
        'maxLength' => 100,
        'pattern' => '^[\w\s-]+$',
    ],
],
```

**Status:** ⚠️ **CZĘŚCIOWO**

---

## 🟢 NISKI PRIORYTET (12)

### 14. **Brak TypeScript strict mode** 🟢 LOW
**Lokalizacja:** `admin/tsconfig.json`

**Problem:**
```json
{
    "strict": true,  // ✅ Jest włączone!
}
```

**Status:** ✅ **DOBRZE** - strict mode włączony

---

### 15. **Duży bundle JS** 🟢 LOW
**Lokalizacja:** `assets/admin/index.js` (366 KB)

**Problem:**
- Powolne ładowanie na słabym łączu
- Brak code splittingu

**Rozwiązanie:**
- Code splitting po routes
- Lazy loading komponentów
- Tree shaking

**Status:** ⚠️ **DO OPTYMALIZACJI**

---

### 16. **Brak source maps na produkcji** 🟢 LOW
**Lokalizacja:** `admin/vite.config.ts`

**Problem:**
- Trudne debugowanie błędów na produkcji

**Rozwiązanie:**
```ts
build: {
    sourcemap: true, // Generuj .map files
}
```

**Status:** ❌ **BRAK**

---

### 17. **Console.log w kodzie** 🟢 LOW
**Lokalizacja:** `admin/src/`

**Status:** ✅ **CZYSTE** - brak console.log w kodzie

---

### 18. ** eval() lub new Function()** 🟢 LOW
**Lokalizacja:** Globalnie

**Status:** ✅ **BEZPIECZNIE** - brak eval()

---

### 19. **Direct $_GET/$_POST usage** 🟢 LOW
**Lokalizacja:** Globalnie

**Status:** ✅ **BEZPIECZNIE** - brak bezpośredniego użycia

---

### 20. **SQL Injection** 🟢 LOW
**Lokalizacja:** Wszystkie zapytania SQL

**Status:** ✅ **BEZPIECZNIE** - wszystkie zapytania z `prepare()`

---

### 21. **XSS Protection** 🟢 LOW
**Lokalizacja:** Output w PHP

**Status:** ✅ **BEZPIECZNIE** - `esc_html()`, `esc_url_raw()`, `sanitize_*()`

---

### 22. **CSRF Protection** 🟢 LOW
**Lokalizacja:** REST API

**Status:** ✅ **BEZPIECZNIE** - nonce + `permission_callback`

---

### 23. **ABSPATH checks** 🟢 LOW
**Lokalizacja:** Wszystkie pliki PHP

**Status:** ✅ **BEZPIECZNIE** - `if (!defined('ABSPATH')) { exit; }`

---

## 📈 WYDAJNOŚD - SZCZEGÓŁY

### Czas ładowania (szacunkowy):

| Strona | Czas | Rozmiar | Zapytania DB |
|--------|------|---------|--------------|
| Dashboard | ~2s | 400 KB | ~15 |
| Kalendarz | ~3s | 450 KB | ~25 |
| Rooms & Beds | ~1.5s | 380 KB | ~10 |
| Settings | ~1s | 350 KB | ~5 |

### Optymalizacje:

✅ **Zrobione:**
- PSR-4 autoloading
- Repository pattern
- Prepared statements
- Indeksy na kluczowych kolumnach

❌ **Do zrobienia:**
- Object cache (Redis/Memcached)
- Query cache (transients)
- Code splitting
- Lazy loading

---

## 🔐 BEZPIECZEŃSTWO - CHECKLISTA

### ✅ Zrobione:
- [x] SQL Injection Protection (`prepare()`)
- [x] XSS Protection (`esc_html()`, `sanitize_*()`)
- [x] CSRF Protection (nonce, `permission_callback`)
- [x] File Security (`ABSPATH` checks)
- [x] Version Checks (PHP 8.0+, WP 6.0+)
- [x] Input Sanitization
- [x] Output Escaping
- [x] Foreign Keys w bazie
- [x] Type hints w PHP

### ⚠️ Do zrobienia:
- [ ] Usunąd pliki debugowe (`debug-*.php`, `test-*.php`)
- [ ] Wdrożyć prawdziwe CAPTCHA
- [ ] Zmienić logging z pliku na `error_log()`
- [ ] Dodać rate limiting
- [ ] Zablokowad dostęp do `.git`
- [ ] Dodać HTTPS enforcement
- [ ] Dodać security headers (HSTS, CSP)
- [ ] Encrypt wrażliwe dane w bazie

---

## 🎯 REKOMENDACJE

### Natychmiast (przed produkcją):

1. **Usuń pliki debugowe:**
   ```bash
   rm debug-*.php test-*.php
   ```

2. **Zbuduj React app:**
   ```bash
   cd admin && npm run build
   ```

3. **Zablokuj .git:**
   ```bash
   # .htaccess
   RedirectMatch 403 /\.git
   ```

4. **Wdróż CAPTCHA:**
   - Google reCAPTCHA v3 lub
   - hCaptcha

### Krótkoterminowo (1-2 tygodnie):

5. **Zmień logging:**
   ```php
   // Zamiast file_put_contents:
   error_log('[MikroBooking] ' . $e->getMessage());
   ```

6. **Dodaj rate limiting:**
   ```php
   add_filter('rest_pre_dispatch', 'rate_limit_callback', 10, 3);
   ```

7. **Dodaj cache:**
   ```php
   $data = get_transient('key');
   if (false === $data) { /* query */ set_transient('key', $data); }
   ```

### Długoterminowo (1-2 miesiące):

8. **Optymalizacja zapytań:**
   - EXPLAIN na wszystkich zapytaniach
   - Eager loading relacji
   - Denormalizacja dla często używanych danych

9. **Code splitting:**
   - Podział bundle'a na chunks
   - Lazy loading komponentów

10. **Redis/Memcached:**
    - Object cache
    - Query cache

---

## 📊 OCENA KOŃCOWA

| Aspekt | Ocena | Komentarz |
|--------|-------|-----------|
| **Bezpieczeostwo** | 7.5/10 | Dobre podstawy, brak rate limitingu |
| **Wydajnośd** | 8/10 | Dobra architektura, brak cache |
| **Jakośd kodu** | 8.5/10 | Nowoczesny PHP, TypeScript |
| **Produkcja** | 6.5/10 | Wymaga cleanupu przed deploy |

### **ŚREDNIA: 7.6/10 - DOBRA APLIKACJA, WYMAGA POPRAWEK PRZED PRODUKCJĄ**

---

## ✅ AKCJE NAPRAWCZE - CHECKLISTA

```bash
# 1. Usuń pliki debugowe
rm debug-*.php test-*.php

# 2. Zbuduj React
cd admin && npm run build

# 3. Zablokuj .git
echo "RedirectMatch 403 /\.git" >> .htaccess

# 4. Wdróż CAPTCHA (TODO w kodzie)

# 5. Zmień logging (TODO w kodzie)

# 6. Dodaj rate limiting (TODO w kodzie)

# 7. Na serwerze:
composer install --no-dev
chmod -R 755 .
find . -name '*.php' -exec chmod 644 {} \;
```

---

**Data audytu:** 2026-02-23  
**Audytor:** AI Assistant  
**Status:** ⚠️ **WYMAGA POPRAWEK PRZED PRODUKCJĄ**
