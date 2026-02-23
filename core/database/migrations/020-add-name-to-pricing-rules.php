<?php
/**
 * Migration 020: Add name to pricing rules
 *
 * @package MikroPlaneta\Booking
 * @since 1.2.9
 */

namespace MikroPlaneta\Booking\Core\Database\Migrations;

use MikroPlaneta\Booking\Core\Database\Schema;

if (!defined('ABSPATH')) {
    exit;
}

class Migration_020_Add_Name_To_Pricing_Rules {

    public static function up(): void {
        global $wpdb;

        $table = Schema::get_table_name('pricing');
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
             AND TABLE_NAME = %s
             AND COLUMN_NAME = 'name'",
            $table
        ));

        if ((int) $exists === 0) {
            $wpdb->query("ALTER TABLE {$table} ADD COLUMN name VARCHAR(120) NULL AFTER id");
            $wpdb->query("CREATE INDEX idx_name ON {$table} (name)");
        }
    }

    public static function down(): void {
        global $wpdb;

        $table = Schema::get_table_name('pricing');
        $wpdb->query("DROP INDEX idx_name ON {$table}");
        $wpdb->query("ALTER TABLE {$table} DROP COLUMN name");
    }
}
