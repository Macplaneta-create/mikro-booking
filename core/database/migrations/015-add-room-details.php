<?php
/**
 * Migration: Add details to Rooms table
 *
 * @package MikroPlaneta\Booking
 * @since 1.1.3
 */

namespace MikroPlaneta\Booking\Core\Database\Migrations;

use MikroPlaneta\Booking\Core\Database\Schema;

if (!defined('ABSPATH')) {
    exit;
}

class Migration_015_Add_Room_Details {
    
    public static function up(): void {
        global $wpdb;
        $table = Schema::get_table_name('rooms');
        
        $sql = "ALTER TABLE {$table} 
                ADD COLUMN description TEXT AFTER name,
                ADD COLUMN image_id BIGINT UNSIGNED AFTER description,
                ADD COLUMN amenities JSON AFTER image_id,
                ADD COLUMN status ENUM('active', 'inactive', 'maintenance') DEFAULT 'active' AFTER room_type,
                ADD INDEX idx_status (status)";
        
        $wpdb->query($sql);
    }
    
    public static function down(): void {
        global $wpdb;
        $table = Schema::get_table_name('rooms');
        
        $sql = "ALTER TABLE {$table} 
                DROP COLUMN description,
                DROP COLUMN image_id,
                DROP COLUMN amenities,
                DROP COLUMN status,
                DROP INDEX idx_status";
        
        $wpdb->query($sql);
    }
}
