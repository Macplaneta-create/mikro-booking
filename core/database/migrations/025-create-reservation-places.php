<?php
/**
 * Migration 025: Create Reservation Places Table
 *
 * @package MikroPlaneta\Booking
 * @since 1.2.8
 */

namespace MikroPlaneta\Booking\Core\Database\Migrations;

use MikroPlaneta\Booking\Core\Database\Schema;

if (!defined('ABSPATH')) {
    exit;
}

class Migration_025_Create_Reservation_Places {

    public static function up(): void {
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        call_user_func('dbDelta', Schema::reservation_places_table());
    }

    public static function down(): void {
        global $wpdb;

        $table = Schema::get_table_name('reservation_places');
        $wpdb->query("DROP TABLE IF EXISTS {$table}");
    }
}