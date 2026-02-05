<?php
/**
 * Migration: Create Allocations Log Table
 *
 * @package MikroPlaneta\Booking
 * @since 1.0.0
 */

namespace MikroPlaneta\Booking\Core\Database\Migrations;

use MikroPlaneta\Booking\Core\Database\Schema;

if (!defined('ABSPATH')) {
    exit;
}

class Migration_005_Create_Allocations_Log {
    
    /**
     * Run migration
     */
    public static function up(): void {
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        
        $sql = Schema::allocations_log_table();
        dbDelta($sql);
    }
    
    /**
     * Rollback migration
     */
    public static function down(): void {
        global $wpdb;
        $table = Schema::get_table_name('allocations_log');
        $wpdb->query("DROP TABLE IF EXISTS {$table}");
    }
}
