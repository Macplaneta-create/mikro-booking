<?php
/**
 * Migration: Create Pricing Table
 *
 * @package MikroPlaneta\Booking
 * @since 1.0.0
 */

namespace MikroPlaneta\Booking\Core\Database\Migrations;

use MikroPlaneta\Booking\Core\Database\Schema;

if (!defined('ABSPATH')) {
    exit;
}

class Migration_010_Create_Pricing {
    
    /**
     * Run migration
     */
    public static function up(): void {
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        
        $sql = Schema::pricing_table();
        dbDelta($sql);
    }
    
    /**
     * Rollback migration
     */
    public static function down(): void {
        global $wpdb;
        $table = Schema::get_table_name('pricing');
        $wpdb->query("DROP TABLE IF EXISTS {$table}");
    }
}
