<?php
/**
 * Migration: Create AI Suggestions Table
 *
 * @package MikroPlaneta\Booking
 * @since 1.0.0
 */

namespace MikroPlaneta\Booking\Core\Database\Migrations;

use MikroPlaneta\Booking\Core\Database\Schema;

if (!defined('ABSPATH')) {
    exit;
}

class Migration_008_Create_AI_Suggestions {
    
    /**
     * Run migration
     */
    public static function up(): void {
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        
        $sql = Schema::ai_suggestions_table();
        dbDelta($sql);
    }
    
    /**
     * Rollback migration
     */
    public static function down(): void {
        global $wpdb;
        $table = Schema::get_table_name('ai_suggestions');
        $wpdb->query("DROP TABLE IF EXISTS {$table}");
    }
}
