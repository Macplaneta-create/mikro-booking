<?php
/**
 * Migration 024: Add Payment Settings
 * 
 * Adds new payment/deposit options to WordPress database
 * 
 * @package MikroPlaneta\Booking
 * @since 1.3.0
 */

namespace MikroPlaneta\Booking\Core\Database\Migrations;

use MikroPlaneta\Booking\Core\Database\Schema;

if (!defined('ABSPATH')) {
    exit;
}

class Migration_024_Add_Payment_Settings {

    public static function up(): void {
        global $wpdb;
        
        // Direct SQL insert - more reliable than add_option() in migrations
        $wpdb->query("
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
        
        // Clear WordPress cache
        wp_cache_delete('mikroplaneta_booking_deposit_enabled', 'options');
        wp_cache_delete('mikroplaneta_booking_deposit_percent', 'options');
        wp_cache_delete('mikroplaneta_booking_payment_account', 'options');
        wp_cache_delete('mikroplaneta_booking_payment_bank_name', 'options');
        wp_cache_delete('mikroplaneta_booking_payment_additional_info', 'options');
    }

    public static function down(): void {
        global $wpdb;
        
        $wpdb->query("
            DELETE FROM {$wpdb->options} 
            WHERE option_name IN (
                'mikroplaneta_booking_deposit_enabled',
                'mikroplaneta_booking_deposit_percent',
                'mikroplaneta_booking_payment_account',
                'mikroplaneta_booking_payment_bank_name',
                'mikroplaneta_booking_payment_additional_info',
                'mikroplaneta_booking_payment_options_added'
            )
        ");
    }
}
