<?php
/**
 * Migration: Add Adults and Children Counts
 *
 * @package MikroPlaneta\Booking
 * @since 1.0.0
 */

namespace MikroPlaneta\Booking\Core\Database\Migrations;

use MikroPlaneta\Booking\Core\Database\Schema;

if (!defined('ABSPATH')) {
    exit;
}

class Migration_009_Add_Adults_Children {
    
    /**
     * Run migration
     */
    public static function up(): void {
        global $wpdb;
        $table = Schema::get_table_name('reservations');
        
        // Add columns if they don't exist
        $row = $wpdb->get_results("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = '{$table}' AND COLUMN_NAME = 'adults'");
        if(empty($row)) {
            $wpdb->query("ALTER TABLE {$table} ADD COLUMN adults INT DEFAULT 1");
        }
        
        $row = $wpdb->get_results("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = '{$table}' AND COLUMN_NAME = 'children'");
        if(empty($row)) {
            $wpdb->query("ALTER TABLE {$table} ADD COLUMN children INT DEFAULT 0");
        }
    }
    
    /**
     * Rollback migration
     */
    public static function down(): void {
        global $wpdb;
        $table = Schema::get_table_name('reservations');
        $wpdb->query("ALTER TABLE {$table} DROP COLUMN IF EXISTS adults");
        $wpdb->query("ALTER TABLE {$table} DROP COLUMN IF EXISTS children");
    }
}
