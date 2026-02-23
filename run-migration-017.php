<?php
/**
 * Run Migration 017 - Bed Places
 * 
 * Access this file once to run the migration:
 * http://gorytajemnic.test/wp-content/plugins/mikro-booking/run-migration-017.php
 */

require_once __DIR__ . '/../../../wp-load.php';

if (!defined('WP_DEBUG') || !WP_DEBUG) {
    status_header(403);
    exit('To narzędzie działa tylko w środowisku deweloperskim (WP_DEBUG=true).');
}

if (!current_user_can('manage_options')) {
    die('Brak uprawnień. Tylko administrator może uruchomić migrację.');
}

$nonce = isset($_GET['_wpnonce']) ? sanitize_text_field(wp_unslash($_GET['_wpnonce'])) : '';
if (!wp_verify_nonce($nonce, 'mikroplaneta_run_migration_017')) {
    status_header(403);
    exit('Nieprawidłowy token bezpieczeństwa.');
}

echo "<h1>Migracja 017 - Bed Places</h1>";
echo "<p>Tworzenie tabeli miejsc w łóżkach...</p>";

try {
    // Load migration class
    require_once __DIR__ . '/core/database/migrations/017-create-bed-places.php';
    
    // Run migration
    MikroPlaneta\Booking\Core\Database\Migrations\Migration_017_Create_Bed_Places::up();
    
    echo "<h2 style='color: green;'>✅ Migracja zakończona sukcesem!</h2>";
    echo "<h3>Co zostało zrobione:</h3>";
    echo "<ul>";
    echo "<li>✅ Utworzono tabelę wp_hotel_bed_places</li>";
    echo "<li>✅ Zmigrowano istniejące łóżka do miejsc</li>";
    echo "<ul>";
    echo "<li>Łóżka pojedyncze → 1 miejsce</li>";
    echo "<li>Łóżka piętrowe → 2 miejsca (Dół, Góra)</li>";
    echo "<li>Łóżka podwójne → 1 miejsce dla 2 osób</li>";
    echo "</ul>";
    echo "</ul>";
    
    echo "<h3>Następne kroki:</h3>";
    echo "<ol>";
    echo "<li>Przejdź do <strong>Booking → Rooms & Beds</strong></li>";
    echo "<li>Edytuj pokoje z łóżkami piętrowymi</li>";
    echo "<li>Sprawdź czy miejsca są poprawnie przypisane</li>";
    echo "<li>Usuń ten plik (run-migration-017.php) po zakończeniu</li>";
    echo "</ol>";
    
    echo "<p><a href='" . admin_url('admin.php?page=mikroplaneta-booking-migrations') . "'>→ Przejdź do panelu migracji</a></p>";
    
} catch (Exception $e) {
    echo "<h2 style='color: red;'>❌ Błąd migracji</h2>";
    echo "<pre>" . esc_html($e->getMessage()) . "</pre>";
    echo "<pre>" . esc_html($e->getTraceAsString()) . "</pre>";
}

?>
