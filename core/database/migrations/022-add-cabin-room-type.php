<?php
/**
 * Migration 022: Add cabin room type
 *
 * @package MikroPlaneta\Booking
 * @since 1.3.0
 */

namespace MikroPlaneta\Booking\Core\Database\Migrations;

use MikroPlaneta\Booking\Core\Database\Schema;

if (!defined('ABSPATH')) {
    exit;
}

class Migration_022_Add_Cabin_Room_Type {

    public static function up(): void {
        global $wpdb;

        $table = Schema::get_table_name('rooms');
        $wpdb->query("ALTER TABLE {$table} MODIFY COLUMN room_type ENUM('standard', 'deluxe', 'studio', 'suite', 'dormitory', 'cabin') DEFAULT 'standard'");
    }

    public static function down(): void {
        global $wpdb;

        $table = Schema::get_table_name('rooms');
        $wpdb->query("UPDATE {$table} SET room_type = 'standard' WHERE room_type = 'cabin'");
        $wpdb->query("ALTER TABLE {$table} MODIFY COLUMN room_type ENUM('standard', 'deluxe', 'studio', 'suite', 'dormitory') DEFAULT 'standard'");
    }
}
