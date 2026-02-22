<?php
/**
 * Migration: Create Extra Services Table
 *
 * @package MikroPlaneta\Booking
 * @since 1.1.2
 */

namespace MikroPlaneta\Booking\Core\Database\Migrations;

use MikroPlaneta\Booking\Core\Database\Schema;

if (!defined('ABSPATH')) {
    exit;
}

class Migration_013_Create_Extra_Services {
    
    public static function up(): void {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        
        $table = Schema::get_table_name('extra_services');
        $charset = Schema::get_charset_collate();
        
        $sql = "CREATE TABLE {$table} (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            description TEXT,
            price DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
            pricing_type ENUM('per_stay', 'per_unit', 'per_person') NOT NULL DEFAULT 'per_stay',
            auto_suggest_by_beds TINYINT(1) NOT NULL DEFAULT 0,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            sort_order INT NOT NULL DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_active (is_active),
            INDEX idx_sort (sort_order)
        ) {$charset};";
        
        dbDelta($sql);

        // Add some default services
        $wpdb->insert($table, [
            'name' => 'Pościel dodatkowa',
            'description' => 'Komplet pościeli i poszewek',
            'price' => 15.00,
            'pricing_type' => 'per_unit',
            'auto_suggest_by_beds' => 1,
            'sort_order' => 1
        ]);

        $wpdb->insert($table, [
            'name' => 'Opłata za zwierzaka',
            'description' => 'Pies lub kot (jednorazowo)',
            'price' => 30.00,
            'pricing_type' => 'per_stay',
            'auto_suggest_by_beds' => 0,
            'sort_order' => 2
        ]);
    }
    
    public static function down(): void {
        global $wpdb;
        $table = Schema::get_table_name('extra_services');
        $wpdb->query("DROP TABLE IF EXISTS {$table}");
    }
}
