<?php
/**
 * Migration 021: Add weekend range fields to pricing rules
 *
 * @package MikroPlaneta\Booking
 * @since 1.3.0
 */

namespace MikroPlaneta\Booking\Core\Database\Migrations;

use MikroPlaneta\Booking\Core\Database\Schema;

if (!defined('ABSPATH')) {
    exit;
}

class Migration_021_Add_Weekend_Range_To_Pricing_Rules {

    public static function up(): void {
        global $wpdb;

        $table = Schema::get_table_name('pricing');

        self::add_column_if_missing($table, 'weekend_from_day', "TINYINT UNSIGNED NOT NULL DEFAULT 5 AFTER weekend_price");
        self::add_column_if_missing($table, 'weekend_to_day', "TINYINT UNSIGNED NOT NULL DEFAULT 7 AFTER weekend_from_day");

        $wpdb->query("UPDATE {$table} SET weekend_from_day = 5 WHERE weekend_from_day IS NULL OR weekend_from_day = 0");
        $wpdb->query("UPDATE {$table} SET weekend_to_day = 7 WHERE weekend_to_day IS NULL OR weekend_to_day = 0");
    }

    public static function down(): void {
        global $wpdb;

        $table = Schema::get_table_name('pricing');
        $wpdb->query("ALTER TABLE {$table} DROP COLUMN weekend_to_day");
        $wpdb->query("ALTER TABLE {$table} DROP COLUMN weekend_from_day");
    }

    private static function add_column_if_missing(string $table, string $column, string $definition): void {
        global $wpdb;

        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
             AND TABLE_NAME = %s
             AND COLUMN_NAME = %s",
            $table,
            $column
        ));

        if ((int) $exists === 0) {
            $wpdb->query("ALTER TABLE {$table} ADD COLUMN {$column} {$definition}");
        }
    }
}
