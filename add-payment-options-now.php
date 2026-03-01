<?php
/**
 * Add Payment Options - One Click
 * 
 * Run this file once to add payment settings to WordPress database
 * Access: http://gorytajemnic/wp-content/plugins/mikro-booking/add-payment-options-now.php
 * 
 * @package MikroPlaneta\Booking
 * @since 1.3.0
 */

// Load WordPress
require_once dirname(__DIR__, 3) . '/wp-load.php';

// Check permissions
if (!current_user_can('manage_options')) {
    die('❌ Access denied. You must be an administrator to run this script.');
}

// Check if already added
if (get_option('mikroplaneta_booking_payment_options_added')) {
    echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Payment Options</title>';
    echo '<style>body{font-family:Arial,sans-serif;padding:40px;max-width:800px;margin:0 auto;background:#f5f5f5}.card{background:#fff;padding:30px;border-radius:12px;box-shadow:0 2px 10px rgba(0,0,0,0.1)}h1{color:#10b981;margin:0 0 20px}p{color:#666;line-height:1.6}.success{background:#ecfdf5;border:2px solid #10b981;color:#065f46;padding:20px;border-radius:8px;margin:20px 0}.option{background:#f9fafb;padding:10px;margin:5px 0;border-radius:6px;border-left:4px solid #10b981}a{display:inline-block;margin-top:20px;padding:12px 24px;background:#6366f1;color:#fff;text-decoration:none;border-radius:8px;font-weight:600}</style></head><body>';
    echo '<div class="card">';
    echo '<h1>✅ Ustawienia płatności zostały już dodane!</h1>';
    echo '<div class="success">';
    echo '<p><strong>Aktualne wartości:</strong></p>';
    echo '<div class="option"><strong>Deposit Enabled:</strong> ' . (get_option('mikroplaneta_booking_deposit_enabled') ? 'TAK' : 'NIE') . '</div>';
    echo '<div class="option"><strong>Deposit Percent:</strong> ' . get_option('mikroplaneta_booking_deposit_percent') . '%</div>';
    echo '<div class="option"><strong>Payment Account:</strong> ' . (get_option('mikroplaneta_booking_payment_account') ?: '(puste)') . '</div>';
    echo '<div class="option"><strong>Bank Name:</strong> ' . (get_option('mikroplaneta_booking_payment_bank_name') ?: '(puste)') . '</div>';
    echo '<div class="option"><strong>Additional Info:</strong> ' . (get_option('mikroplaneta_booking_payment_additional_info') ?: '(puste)') . '</div>';
    echo '</div>';
    echo '<a href="' . admin_url('admin.php?page=mikroplaneta-booking') . '">← Powrót do ustawień</a>';
    echo '</div></body></html>';
    exit;
}

// Add payment options using direct SQL (more reliable)
global $wpdb;

$result = $wpdb->query("
    INSERT INTO {$wpdb->options} (option_name, option_value, autoload) 
    VALUES 
    ('mikroplaneta_booking_deposit_enabled', '0', 'yes'),
    ('mikroplaneta_booking_deposit_percent', '30', 'yes'),
    ('mikroplaneta_booking_payment_account', '', 'yes'),
    ('mikroplaneta_booking_payment_bank_name', '', 'yes'),
    ('mikroplaneta_booking_payment_additional_info', '', 'yes'),
    ('mikroplaneta_booking_payment_options_added', '1', 'yes')
    ON DUPLICATE KEY UPDATE option_value = VALUES(option_value)
");

// Clear cache
wp_cache_delete('mikroplaneta_booking_deposit_enabled', 'options');
wp_cache_delete('mikroplaneta_booking_deposit_percent', 'options');
wp_cache_delete('mikroplaneta_booking_payment_account', 'options');
wp_cache_delete('mikroplaneta_booking_payment_bank_name', 'options');
wp_cache_delete('mikroplaneta_booking_payment_additional_info', 'options');

// Verify
$all_added = (
    get_option('mikroplaneta_booking_deposit_enabled') !== false &&
    get_option('mikroplaneta_booking_deposit_percent') !== false &&
    get_option('mikroplaneta_booking_payment_account') !== false &&
    get_option('mikroplaneta_booking_payment_bank_name') !== false &&
    get_option('mikroplaneta_booking_payment_additional_info') !== false
);

echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Payment Options Added</title>';
echo '<style>body{font-family:Arial,sans-serif;padding:40px;max-width:800px;margin:0 auto;background:#f5f5f5}.card{background:#fff;padding:30px;border-radius:12px;box-shadow:0 2px 10px rgba(0,0,0,0.1)}h1{color:#10b981;margin:0 0 20px}p{color:#666;line-height:1.6}.success{background:#ecfdf5;border:2px solid #10b981;color:#065f46;padding:20px;border-radius:8px;margin:20px 0}.error{background:#fef2f2;border:2px solid #ef4444;color:#991b1b;padding:20px;border-radius:8px;margin:20px 0}.option{background:#f9fafb;padding:10px;margin:5px 0;border-radius:6px;border-left:4px solid #10b981}a{display:inline-block;margin-top:20px;padding:12px 24px;background:#6366f1;color:#fff;text-decoration:none;border-radius:8px;font-weight:600}</style></head><body>';
echo '<div class="card">';

if ($result && $all_added) {
    echo '<h1>✅ Sukces! Dodano ustawienia płatności</h1>';
    echo '<div class="success">';
    echo '<p><strong>Dodane opcje:</strong></p>';
    echo '<div class="option"><strong>Deposit Enabled:</strong> ' . (get_option('mikroplaneta_booking_deposit_enabled') ? 'TAK' : 'NIE') . ' (domyślnie: NIE)</div>';
    echo '<div class="option"><strong>Deposit Percent:</strong> ' . get_option('mikroplaneta_booking_deposit_percent') . '% (domyślnie: 30%)</div>';
    echo '<div class="option"><strong>Payment Account:</strong> ' . (get_option('mikroplaneta_booking_payment_account') ?: '(puste - uzupełnij w ustawieniach)') . '</div>';
    echo '<div class="option"><strong>Bank Name:</strong> ' . (get_option('mikroplaneta_booking_payment_bank_name') ?: '(puste - uzupełnij w ustawieniach)') . '</div>';
    echo '<div class="option"><strong>Additional Info:</strong> ' . (get_option('mikroplaneta_booking_payment_additional_info') ?: '(puste - opcjonalne)') . '</div>';
    echo '</div>';
    echo '<p><strong>Co dalej?</strong></p>';
    echo '<ol>';
    echo '<li>Wejdź w <strong>Booking → Settings</strong></li>';
    echo '<li>Skonfiguruj dane płatności (konto, bank)</li>';
    echo '<li>Włącz zaliczkę jeśli chcesz ją wymagać</li>';
    echo '<li>Przetestuj rezerwację - powinien pojawić się komunikat o zaliczce</li>';
    echo '</ol>';
    echo '<a href="' . admin_url('admin.php?page=mikroplaneta-booking') . '">→ Przejdź do ustawień</a>';
} else {
    echo '<h1>❌ Błąd!</h1>';
    echo '<div class="error">';
    echo '<p>Nie udało się dodać opcji do bazy danych.</p>';
    echo '<p>Sprawdź czy WordPress ma dostęp do bazy danych.</p>';
    echo '</div>';
    echo '<a href="javascript:location.reload()">Spróbuj ponownie</a>';
}

echo '</div></body></html>';

// Delete this file after use for security
// Uncomment the line below to auto-delete this file after running
// unlink(__FILE__);
