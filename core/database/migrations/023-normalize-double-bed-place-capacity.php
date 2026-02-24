<?php
/**
 * Migration 023: Normalize double bed places capacity
 *
 * Double beds are treated as one operational place in inventory planning.
 *
 * @package MikroPlaneta\Booking
 * @since 1.3.0
 */

namespace MikroPlaneta\Booking\Core\Database\Migrations;

use MikroPlaneta\Booking\Core\Database\Schema;

if (!defined('ABSPATH')) {
    exit;
}

class Migration_023_Normalize_Double_Bed_Place_Capacity {

    public static function up(): void {
        global $wpdb;

        $places_table = Schema::get_table_name('bed_places');
        $beds_table = Schema::get_table_name('beds');

        $wpdb->query(
            "UPDATE {$places_table} p
             INNER JOIN {$beds_table} b ON p.bed_id = b.id
             SET p.max_persons = 1
             WHERE b.bed_type = 'double'"
        );
    }

    public static function down(): void {
        global $wpdb;

        $places_table = Schema::get_table_name('bed_places');
        $beds_table = Schema::get_table_name('beds');

        $wpdb->query(
            "UPDATE {$places_table} p
             INNER JOIN {$beds_table} b ON p.bed_id = b.id
             SET p.max_persons = 2
             WHERE b.bed_type = 'double'"
        );
    }
}
