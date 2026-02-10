<?php
/**
 * Migration: Remove bed_id from reservations table
 *
 * This migration removes the bed_id column from reservations table
 * since we now use the reservation_beds junction table exclusively.
 *
 * @package MikroPlaneta\Booking
 * @since 1.0.0
 */

namespace MikroPlaneta\Booking\Core\Database\Migrations;

use MikroPlaneta\Booking\Core\Database\Schema;

if (!defined('ABSPATH')) {
    exit;
}

class Migration_012_Remove_Bed_Id_From_Reservations {
    
    /**
     * Run migration
     */
    public static function up(): void {
        global $wpdb;
        
        $table = Schema::get_table_name('reservations');
        
        // Remove foreign key constraint first (if exists)
        $fk_name = $wpdb->get_var($wpdb->prepare(
            "SELECT CONSTRAINT_NAME FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
             WHERE TABLE_SCHEMA = DATABASE()
             AND TABLE_NAME = %s
             AND COLUMN_NAME = 'bed_id'
             AND REFERENCED_TABLE_NAME IS NOT NULL
             LIMIT 1",
            $table
        ));
        if ($fk_name) {
            $wpdb->query("ALTER TABLE {$table} DROP FOREIGN KEY {$fk_name}");
        }

        // Remove the bed_id column if it exists
        $has_bed_id = $wpdb->get_var("SHOW COLUMNS FROM {$table} LIKE 'bed_id'");
        if ($has_bed_id) {
            $wpdb->query("ALTER TABLE {$table} DROP COLUMN bed_id");
        }

        // Remove the index that included bed_id if it exists
        $has_index = $wpdb->get_var($wpdb->prepare(
            "SHOW INDEX FROM {$table} WHERE Key_name = %s",
            'idx_bed_dates'
        ));
        if ($has_index) {
            $wpdb->query("ALTER TABLE {$table} DROP INDEX idx_bed_dates");
        }
    }
    
    /**
     * Rollback migration
     */
    public static function down(): void {
        global $wpdb;
        
        $table = Schema::get_table_name('reservations');
        $beds_table = Schema::get_table_name('beds');
        
        // Add bed_id column back
        $wpdb->query("ALTER TABLE {$table} ADD COLUMN bed_id BIGINT UNSIGNED NOT NULL AFTER id");
        
        // Add foreign key back
        $wpdb->query("ALTER TABLE {$table} ADD FOREIGN KEY (bed_id) REFERENCES {$beds_table}(id) ON DELETE RESTRICT");
        
        // Add index back
        $wpdb->query("ALTER TABLE {$table} ADD INDEX idx_bed_dates (bed_id, check_in, check_out)");
    }
}
