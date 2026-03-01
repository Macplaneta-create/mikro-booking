<?php
/**
 * Force Add Payment Settings
 * 
 * Run once to add payment options to WordPress database
 * Access: http://gorytajemnic/wp-admin/admin.php?page=mikroplaneta-booking&run=force-add-payment-options
 * 
 * @package MikroPlaneta\Booking
 * @since 1.3.0
 */

// Prevent direct access - must be run through WordPress
if (!defined('ABSPATH')) {
    die('Access denied - run through WordPress admin');
}

// Check permissions
if (!current_user_can('manage_options')) {
    die('Unauthorized access');
}

// Check if already run
if (get_option('mikroplaneta_booking_payment_options_added')) {
    echo '<div class="notice notice-success"><p>✅ Ustawienia płatności zostały już dodane.</p></div>';
    return;
}

// Add payment options
add_option('mikroplaneta_booking_deposit_enabled', false);
add_option('mikroplaneta_booking_deposit_percent', 30);
add_option('mikroplaneta_booking_payment_account', '');
add_option('mikroplaneta_booking_payment_bank_name', '');
add_option('mikroplaneta_booking_payment_additional_info', '');

// Mark as added
update_option('mikroplaneta_booking_payment_options_added', true);

echo '<div class="notice notice-success"><p>';
echo '✅ <strong>Dodano ustawienia płatności!</strong><br><br>';
echo 'Deposit Enabled: ' . (get_option('mikroplaneta_booking_deposit_enabled') ? 'TAK' : 'NIE') . '<br>';
echo 'Deposit Percent: ' . get_option('mikroplaneta_booking_deposit_percent') . '%<br>';
echo 'Payment Account: ' . (get_option('mikroplaneta_booking_payment_account') ?: '(puste)') . '<br>';
echo 'Bank Name: ' . (get_option('mikroplaneta_booking_payment_bank_name') ?: '(puste)') . '<br>';
echo 'Additional Info: ' . (get_option('mikroplaneta_booking_payment_additional_info') ?: '(puste)');
echo '</p></div>';

echo '<p><a href="' . admin_url('admin.php?page=mikroplaneta-booking') . '" class="button">← Powrót do ustawień</a></p>';
