<?php
/**
 * Migration: Create Reservation Extras Table
 *
 * @package MikroPlaneta\Booking
 * @since 1.1.2
 */

namespace MikroPlaneta\Booking\Core\Database\Migrations;

use MikroPlaneta\Booking\Core\Database\Schema;

if (!defined('ABSPATH')) {
    exit;
}

class Migration_014_Create_Reservation_Extras {
    
    public static function up(): void {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        
        $table = Schema::get_table_name('reservation_extras');
        $reservations_table = Schema::get_table_name('reservations');
        $services_table = Schema::get_table_name('extra_services');
        $charset = Schema::get_charset_collate();
        
        $sql = "CREATE TABLE {$table} (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            reservation_id BIGINT UNSIGNED NOT NULL,
            service_id BIGINT UNSIGNED NOT NULL,
            quantity INT NOT NULL DEFAULT 1,
            unit_price DECIMAL(10, 2) NOT NULL,
            total_price DECIMAL(10, 2) NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (reservation_id) REFERENCES {$reservations_table}(id) ON DELETE CASCADE,
            FOREIGN KEY (service_id) REFERENCES {$services_table}(id) ON DELETE RESTRICT,
            INDEX idx_reservation (reservation_id),
            INDEX idx_service (service_id)
        ) {$charset};";
        
        dbDelta($sql);
    }
    
    public static function down(): void {
        global $wpdb;
        $table = Schema::get_table_name('reservation_extras');
        $wpdb->query("DROP TABLE IF EXISTS {$table}");
    }
}
