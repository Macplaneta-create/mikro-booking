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
        // Add payment options to WordPress
        add_option('mikroplaneta_booking_deposit_enabled', false);
        add_option('mikroplaneta_booking_deposit_percent', 30);
        add_option('mikroplaneta_booking_payment_account', '');
        add_option('mikroplaneta_booking_payment_bank_name', '');
        add_option('mikroplaneta_booking_payment_additional_info', '');
        
        // Mark migration as completed
        add_option('mikroplaneta_booking_migration_024_completed', true);
    }

    public static function down(): void {
        // Remove payment options
        delete_option('mikroplaneta_booking_deposit_enabled');
        delete_option('mikroplaneta_booking_deposit_percent');
        delete_option('mikroplaneta_booking_payment_account');
        delete_option('mikroplaneta_booking_payment_bank_name');
        delete_option('mikroplaneta_booking_payment_additional_info');
        delete_option('mikroplaneta_booking_migration_024_completed');
    }
}
