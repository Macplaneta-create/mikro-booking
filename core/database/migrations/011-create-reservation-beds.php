<?php
/**
 * Migration: Create Reservation Beds Table
 *
 * This table creates a many-to-many relationship between reservations and beds,
 * allowing a single reservation to span multiple beds (group bookings).
 *
 * @package MikroPlaneta\Booking
 * @since 1.0.0
 */

namespace MikroPlaneta\Booking\Core\Database\Migrations;

use MikroPlaneta\Booking\Core\Database\Schema;

if (!defined('ABSPATH')) {
    exit;
}

class Migration_011_Create_Reservation_Beds {
    
    /**
     * Run migration
     */
    public static function up(): void {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        
        $table = Schema::get_table_name('reservation_beds');
        $reservations_table = Schema::get_table_name('reservations');
        $beds_table = Schema::get_table_name('beds');
        $charset = Schema::get_charset_collate();
        
        $sql = "CREATE TABLE {$table} (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            reservation_id BIGINT UNSIGNED NOT NULL,
            bed_id BIGINT UNSIGNED NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (reservation_id) REFERENCES {$reservations_table}(id) ON DELETE CASCADE,
            FOREIGN KEY (bed_id) REFERENCES {$beds_table}(id) ON DELETE RESTRICT,
            UNIQUE KEY unique_reservation_bed (reservation_id, bed_id),
            INDEX idx_reservation (reservation_id),
            INDEX idx_bed (bed_id)
        ) {$charset};";
        
        dbDelta($sql);
        
        // Migrate existing reservations to use the new table
        // Only run if legacy bed_id column exists (older installs).
        $has_bed_id = $wpdb->get_var("SHOW COLUMNS FROM {$reservations_table} LIKE 'bed_id'");
        if ($has_bed_id) {
            $wpdb->query("
                INSERT INTO {$table} (reservation_id, bed_id, created_at)
                SELECT id, bed_id, created_at
                FROM {$reservations_table}
                WHERE bed_id IS NOT NULL
                ON DUPLICATE KEY UPDATE bed_id = bed_id
            ");
        }
    }
    
    /**
     * Rollback migration
     */
    public static function down(): void {
        global $wpdb;
        $table = Schema::get_table_name('reservation_beds');
        $wpdb->query("DROP TABLE IF EXISTS {$table}");
    }
}
