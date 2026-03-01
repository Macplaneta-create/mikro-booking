<?php
/**
 * Migration: Add Payment Settings
 * 
 * Adds new payment/deposit options to WordPress database
 * 
 * @package MikroPlaneta\Booking
 * @since 1.3.0
 */

if (!defined('ABSPATH')) {
    exit;
}

// Add payment options
add_option('mikroplaneta_booking_deposit_enabled', false);
add_option('mikroplaneta_booking_deposit_percent', 30);
add_option('mikroplaneta_booking_payment_account', '');
add_option('mikroplaneta_booking_payment_bank_name', '');
add_option('mikroplaneta_booking_payment_additional_info', '');

return [
    'success' => true,
    'message' => 'Dodano ustawienia płatności: zaliczka, konto bankowe, informacje dodatkowe',
    'options' => [
        'deposit_enabled' => get_option('mikroplaneta_booking_deposit_enabled'),
        'deposit_percent' => get_option('mikroplaneta_booking_deposit_percent'),
        'payment_account' => get_option('mikroplaneta_booking_payment_account'),
        'payment_bank_name' => get_option('mikroplaneta_booking_payment_bank_name'),
        'payment_additional_info' => get_option('mikroplaneta_booking_payment_additional_info'),
    ]
];
