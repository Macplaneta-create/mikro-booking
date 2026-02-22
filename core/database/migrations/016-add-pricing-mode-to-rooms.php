<?php
/**
 * Migration: Add pricing_mode to Rooms table
 *
 * @package MikroPlaneta\Booking
 * @since 1.2.1
 */

namespace MikroPlaneta\Booking\Core\Database\Migrations;

use MikroPlaneta\Booking\Core\Database\Schema;

if (!defined('ABSPATH')) {
    exit;
}

class Migration_016_Add_Pricing_Mode_To_Rooms {
    
    public static function up(): void {
        global $wpdb;
        $table = Schema::get_table_name('rooms');
        
        $sql = "ALTER TABLE {$table} 
                ADD COLUMN pricing_mode ENUM('per_room', 'per_bed') DEFAULT 'per_room' AFTER room_type";
        
        $wpdb->query($sql);

        // Update dormitories to per_bed by default
        $wpdb->update($table, ['pricing_mode' => 'per_bed'], ['room_type' => 'dormitory']);
    }
    
    public static function down(): void {
        global $wpdb;
        $table = Schema::get_table_name('rooms');
        
        $sql = "ALTER TABLE {$table} 
                DROP COLUMN pricing_mode";
        
        $wpdb->query($sql);
    }
}
