<?php
/**
 * Migration: Create Notifications Table
 *
 * @package MikroPlaneta\Booking
 * @since 1.0.0
 */

namespace MikroPlaneta\Booking\Core\Database\Migrations;

use MikroPlaneta\Booking\Core\Database\Schema;

if (!defined('ABSPATH')) {
    exit;
}

class Migration_006_Create_Notifications {
    
    /**
     * Run migration
     */
    public static function up(): void {
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        
        $sql = Schema::notifications_table();
        dbDelta($sql);
    }
    
    /**
     * Rollback migration
     */
    public static function down(): void {
        global $wpdb;
        $table = Schema::get_table_name('notifications');
        $wpdb->query("DROP TABLE IF EXISTS {$table}");
    }
}
