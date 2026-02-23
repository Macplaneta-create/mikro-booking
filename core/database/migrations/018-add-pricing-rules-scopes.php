<?php
/**
 * Migration 018: Extend pricing rules with scopes and priority
 *
 * Adds support for:
 * - scope_type: room-specific vs room-type rules
 * - room_type targeting
 * - pricing_mode targeting
 * - priority-based rule resolution
 *
 * @package MikroPlaneta\Booking
 * @since 1.2.9
 */

namespace MikroPlaneta\Booking\Core\Database\Migrations;

use MikroPlaneta\Booking\Core\Database\Schema;

if (!defined('ABSPATH')) {
    exit;
}

class Migration_018_Add_Pricing_Rules_Scopes {

    public static function up(): void {
        global $wpdb;

        $table = Schema::get_table_name('pricing');

        self::add_column_if_missing($table, 'scope_type', "VARCHAR(20) NOT NULL DEFAULT 'room_id' AFTER room_id");
        self::add_column_if_missing($table, 'room_type', "VARCHAR(20) NULL AFTER scope_type");
        self::add_column_if_missing($table, 'pricing_mode', "VARCHAR(20) NULL AFTER room_type");
        self::add_column_if_missing($table, 'priority', "INT NOT NULL DEFAULT 100 AFTER pricing_mode");

        // room_id must allow NULL for room_type scoped rules
        $wpdb->query("ALTER TABLE {$table} MODIFY COLUMN room_id BIGINT UNSIGNED NULL");

        // Backfill existing rows (legacy room-level rules)
        $wpdb->query("UPDATE {$table} SET scope_type = 'room_id' WHERE scope_type IS NULL OR scope_type = ''");
        $wpdb->query("UPDATE {$table} SET priority = 100 WHERE priority IS NULL");

        // Add indexes (ignore failures when index already exists)
        $wpdb->query("CREATE INDEX idx_scope_priority ON {$table} (scope_type, priority)");
        $wpdb->query("CREATE INDEX idx_room_type_dates ON {$table} (room_type, start_date, end_date)");
        $wpdb->query("CREATE INDEX idx_pricing_mode ON {$table} (pricing_mode)");
    }

    public static function down(): void {
        global $wpdb;

        $table = Schema::get_table_name('pricing');

        $wpdb->query("DROP INDEX idx_scope_priority ON {$table}");
        $wpdb->query("DROP INDEX idx_room_type_dates ON {$table}");
        $wpdb->query("DROP INDEX idx_pricing_mode ON {$table}");

        $wpdb->query("ALTER TABLE {$table} DROP COLUMN priority");
        $wpdb->query("ALTER TABLE {$table} DROP COLUMN pricing_mode");
        $wpdb->query("ALTER TABLE {$table} DROP COLUMN room_type");
        $wpdb->query("ALTER TABLE {$table} DROP COLUMN scope_type");
        $wpdb->query("ALTER TABLE {$table} MODIFY COLUMN room_id BIGINT UNSIGNED NOT NULL");
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
