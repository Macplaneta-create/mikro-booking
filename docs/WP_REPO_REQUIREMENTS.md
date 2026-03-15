# Zgodność Wtyczki z Wymaganiami Repozytorium WordPress.org

Ten dokument opisuje stan wtyczki w kontekście oficjalnych [Wymagań Repozytorium Wtyczek WordPress.org](https://developer.wordpress.org/plugins/wordpress-org/detailed-plugin-guidelines/). 

Został przygotowany dla zespołu audytującego oraz jako lista kontrolna (`checklist`) przed finalnym submitem.

## 1. Architektura i Przestrzenie Nazw (Namespaces)
Kod wtyczki jest zorganizowany zgodnie ze standardami programowania zorientowanego obiektowo (OOP).
*   **Namespaces:** Wszystkie klasy PHP używają unikalnej przestrzeni nazw `MikroPlaneta\Booking`, co zapobiega konfliktom z innymi wtyczkami.
*   **Katalogi:** Oddzielono warstwę kontrolerów (`rest-api/controllers`), logiki biznesowej (`core/services`), oraz zapytań do bazy (`core/repositories`).
*   **Brak zależności zewnętrznych:** Usunięto i uniknięto katalogu `vendor/` - cała komunikacja HTTP (np. z Google Calendar API) odbywa się poprzez wbudowane API WordPressa (`wp_remote_post()`, `wp_remote_get()`).
*   **Zarządzanie zasobami (Assets):** Skrypty i style dołączane są poprawnie przez `wp_enqueue_script` i `wp_enqueue_style` za pośrednictwem klasy `MikroPlaneta\Booking\Core\Plugin`.

## 2. Bezpieczeństwo i Walidacja (Security & Data Integrity)
Spełnione są najważniejsze punkty dotyczące bezpieczeństwa danych użytkowników (Security in WordPress):
*   **Sanityzacja danych wejściowych:** Każdy parametr przyjmowany do bazy lub funkcji jest poddawany sanityzacji (`sanitize_text_field`, `sanitize_email`, `absint`, `floatval`). W kontrolerach REST wszystkie odbierane dane z obiektu `WP_REST_Request` są walidowane i czyszczone.
*   **Ucieczka danych wyjściowych (Escaping):** Parametry wyświetlane na frontendzie w HTMLu są zabezpieczone klasycznym `esc_html()`, `esc_attr()`, `esc_url()`. Większość UI admina korzysta z Reacta (Vite), który domyślnie posiada własną silną izolację XSS.
*   **Zabezpieczenie zapytań SQL:** Odpytywanie autorskich tabel odbywa się zawsze za pomocą `$wpdb->prepare()`. Przykłady w `class-reservation-repository.php` dowodzą użycia placeholderów (`%d`, `%s`).

## 3. Zabezpieczenie Opcji Administracyjnych (Permissions & Nonces)
*   **Uprawnienia REST API:** Wszystkie endpointy modyfikujące stan (Settings, Reservations) rejestrowane autorsko poprzez klasę dziedziczącą po `WP_REST_Controller` posiadają pole `permission_callback`, sprawdzające czy użytkownik to przynajmniej `manage_options`.  
*   **Ochrona metod publicznych (CSRF / Rate limiting):** Formularz "Nowa Rezerwacja" z poziomu frontendu i modale gościa nie wymagają logowania, ale wtyczka używa rygorystycznej filtracji i walidacji każdego pola wejściowego przed procesowaniem leadów kalendarza. Callbacki wp-admin (Ajax i REST) przesyłają unikalny nagłówek weryfikacyjny (Dedykowany Endpoint: `X-WP-Nonce`).

## 4. Baza Danych i Czysta Dezinstalacja
*   Wtyczka tworzy kilka zagnieżdżonych, autorskich tabel ze zunifikowanym prefixem `{prefix}_mikroplaneta_booking_*`. 
*   **Mechanizm Migracji:** Tabele konfigurowane i patchowane są bezpiecznie z mechanizmu wersjonowania w kodzie (katalog `/migrations`). Brak bezpośredniego używania db_delta w miejscach, które paraliżowałyby główny hook.
*   **Brak skrótów:** Zapytania SQL nie używają limitów na zasobach rdzenia WordPressa bez potrzeby - polegano na dedykowanym repozytorium. Skrypty `force-repair-db.php` czy `force-update.php`, będące w głównym katalogu, zostały podczas optymalizacji przeniesione lub zarchiwizowane, aby nie były odsłonięte w głównym korzeniu wtyczki (root).

## 5. Tłumaczenia, Języki i i18n
*   Wszystkie napisy kodowane "na sztywno" w kodzie rdzenia są modyfikowane do formy wspieranej lokalizacją. 
*   *Text domain:* `mikroplaneta-booking`
*   Skrypty frontendu i plugin reactowy są obecnie przygotowywane na przyjęcie pełnego modułu i18n/JED (do odpytań i renderu tłumaczeń po stronie przeglądarki klienta, w celu spełnienia wymogów dystrybucji globalnej).

## 6. Wymagania wobec Licencji i Treści
*   Wtyczka oparta jest w 100% o licencję typu (GPL v2 or later) — bez dołączania restrykcyjnych klas. 
*   Ikony pochodzą od dystrybutora "Lucide" (Licencja ISC zgodna). Zależności reactowe wbudowane w pakiet `.JS` na licencji MIT.

*(Dokument aktualizowany: Marzec 2026. Status: Gotowy do rozpoczęcia rewizji repozytorium po finalnym zamrożeniu kodu React i18n).*
