<?php
/**
 * Migration 019: Add studio room type
 *
 * @package MikroPlaneta\Booking
 * @since 1.2.9
 */

namespace MikroPlaneta\Booking\Core\Database\Migrations;

use MikroPlaneta\Booking\Core\Database\Schema;

if (!defined('ABSPATH')) {
    exit;
}

class Migration_019_Add_Studio_Room_Type {

    public static function up(): void {
        global $wpdb;

        $table = Schema::get_table_name('rooms');
        $wpdb->query("ALTER TABLE {$table} MODIFY COLUMN room_type ENUM('standard', 'deluxe', 'studio', 'suite', 'dormitory') DEFAULT 'standard'");
    }

    public static function down(): void {
        global $wpdb;

        $table = Schema::get_table_name('rooms');
        // Map studio back to standard on rollback to keep enum valid.
        $wpdb->query("UPDATE {$table} SET room_type = 'standard' WHERE room_type = 'studio'");
        $wpdb->query("ALTER TABLE {$table} MODIFY COLUMN room_type ENUM('standard', 'deluxe', 'suite', 'dormitory') DEFAULT 'standard'");
    }
}
