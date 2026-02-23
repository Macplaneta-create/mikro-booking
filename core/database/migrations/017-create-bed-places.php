<?php
/**
 * Migration 017: Create Bed Places Table
 * 
 * Adds support for multiple places per bed (e.g., bunk beds have 2 places)
 * Allows proper capacity management for different bed types
 * 
 * @package MikroPlaneta\Booking
 * @since 1.2.8
 */

namespace MikroPlaneta\Booking\Core\Database\Migrations;

use MikroPlaneta\Booking\Core\Database\Schema;

if (!defined('ABSPATH')) {
    exit;
}

class Migration_017_Create_Bed_Places {

    /**
     * Run migration (UP)
     */
    public static function up(): void {
        global $wpdb;

        $places_table = Schema::get_table_name('bed_places');
        $beds_table = Schema::get_table_name('beds');
        $charset = Schema::get_charset_collate();

        // Create bed_places table
        $sql = "CREATE TABLE {$places_table} (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            bed_id BIGINT UNSIGNED NOT NULL,
            place_number TINYINT UNSIGNED NOT NULL,
            place_label VARCHAR(50) DEFAULT '',
            max_persons TINYINT UNSIGNED DEFAULT 1,
            is_active BOOLEAN DEFAULT TRUE,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (bed_id) REFERENCES {$beds_table}(id) ON DELETE CASCADE,
            UNIQUE KEY unique_place (bed_id, place_number),
            INDEX idx_bed (bed_id),
            INDEX idx_active (is_active)
        ) {$charset};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);

        // Migrate existing beds to places
        self::migrate_existing_beds();
    }

    /**
     * Rollback migration (DOWN)
     */
    public static function down(): void {
        global $wpdb;

        $places_table = Schema::get_table_name('bed_places');
        
        // Drop table (CASCADE will handle dependent data)
        $wpdb->query("DROP TABLE IF EXISTS {$places_table}");
    }

    /**
     * Migrate existing beds to bed_places
     * - Single beds: 1 place with max_persons = 1
     * - Bunk beds: 2 places (Dół/Góra) with max_persons = 1 each
     * - Double beds: 1 place with max_persons = 2
     */
    private static function migrate_existing_beds(): void {
        global $wpdb;

        $places_table = Schema::get_table_name('bed_places');
        $beds_table = Schema::get_table_name('beds');

        // Get all existing beds
        $beds = $wpdb->get_results("SELECT id, bed_type FROM {$beds_table}", ARRAY_A);

        if (empty($beds)) {
            return;
        }

        foreach ($beds as $bed) {
            $bed_id = (int) $bed['id'];
            $bed_type = $bed['bed_type'] ?? 'single';

            switch ($bed_type) {
                case 'bunk':
                    // Bunk bed: 2 places (Dół i Góra)
                    $wpdb->insert($places_table, [
                        'bed_id' => $bed_id,
                        'place_number' => 1,
                        'place_label' => 'Dół',
                        'max_persons' => 1,
                        'is_active' => true,
                    ]);

                    $wpdb->insert($places_table, [
                        'bed_id' => $bed_id,
                        'place_number' => 2,
                        'place_label' => 'Góra',
                        'max_persons' => 1,
                        'is_active' => true,
                    ]);
                    break;

                case 'double':
                    // Double bed: 1 place for 2 persons (couple)
                    $wpdb->insert($places_table, [
                        'bed_id' => $bed_id,
                        'place_number' => 1,
                        'place_label' => 'Łóżko małżeńskie',
                        'max_persons' => 2,
                        'is_active' => true,
                    ]);
                    break;

                case 'single':
                default:
                    // Single bed: 1 place for 1 person
                    $wpdb->insert($places_table, [
                        'bed_id' => $bed_id,
                        'place_number' => 1,
                        'place_label' => 'Miejsce 1',
                        'max_persons' => 1,
                        'is_active' => true,
                    ]);
                    break;
            }
        }
    }
}
