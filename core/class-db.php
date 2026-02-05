<?php
/**
 * Database Migration System for Mikroplaneta Booking
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Mikroplaneta_Booking_DB {
    private $table_prefix;

    public function __construct() {
        global $wpdb;
        $this->table_prefix = $wpdb->prefix . 'mb_';
    }

    public function run_migrations() {
        $version = get_option( 'mikroplaneta_booking_db_version', '0.0.0' );

        if ( version_compare( $version, '0.1.1', '<' ) ) {
            $this->migrate_011();
            update_option( 'mikroplaneta_booking_db_version', '0.1.1' );
        }
    }

    private function migrate_011() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );

        $sql_bookings = "ALTER TABLE {$this->table_prefix}bookings 
            ADD COLUMN adults int DEFAULT 1,
            ADD COLUMN children int DEFAULT 0;";
        
        $sql_reservations = "ALTER TABLE {$this->table_prefix}reservations 
            ADD COLUMN adults int DEFAULT 1,
            ADD COLUMN children int DEFAULT 0;";

        $wpdb->query($sql_bookings);
        $wpdb->query($sql_reservations);
    }

    private function migrate_010() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );

        // 1. Rooms
        $sql1 = "CREATE TABLE {$this->table_prefix}rooms (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            type enum('private', 'dorm') DEFAULT 'private',
            description text,
            floor int DEFAULT 0,
            status enum('active', 'maintenance', 'inactive') DEFAULT 'active',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id)
        ) $charset_collate;";

        // 2. Beds (Sub-units)
        $sql2 = "CREATE TABLE {$this->table_prefix}beds (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            room_id bigint(20) NOT NULL,
            name varchar(100) NOT NULL,
            type enum('single', 'double', 'bunk_top', 'bunk_bottom') DEFAULT 'single',
            status enum('active', 'maintenance') DEFAULT 'active',
            PRIMARY KEY  (id),
            KEY room_id (room_id)
        ) $charset_collate;";

        // 3. Guests
        $sql3 = "CREATE TABLE {$this->table_prefix}guests (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            first_name varchar(100) NOT NULL,
            last_name varchar(100) NOT NULL,
            email varchar(255) NOT NULL,
            phone varchar(50),
            notes text,
            preferences JSON,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY email (email)
        ) $charset_collate;";

        // 4. Bookings (Orders/Groups)
        $sql4 = "CREATE TABLE {$this->table_prefix}bookings (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            guest_id bigint(20) NOT NULL,
            total_price decimal(10,2) NOT NULL,
            status enum('pending', 'confirmed', 'checked_in', 'checked_out', 'cancelled') DEFAULT 'pending',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY guest_id (guest_id)
        ) $charset_collate;";

        // 5. Reservations (Individual Bed-Nights)
        $sql5 = "CREATE TABLE {$this->table_prefix}reservations (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            booking_id bigint(20) NOT NULL,
            room_id bigint(20) NOT NULL,
            bed_id bigint(20),
            check_in date NOT NULL,
            check_out date NOT NULL,
            price_per_night decimal(10,2) NOT NULL,
            is_private_room_booking tinyint(1) DEFAULT 0,
            PRIMARY KEY  (id),
            KEY booking_id (booking_id),
            KEY room_id (room_id),
            KEY bed_id (bed_id),
            KEY check_in_out (check_in, check_out)
        ) $charset_collate;";

        // 6. Rates (Pricing Matrix)
        $sql6 = "CREATE TABLE {$this->table_prefix}rates (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            resource_type enum('room', 'bed') NOT NULL,
            resource_id bigint(20), -- If null, applies to all of type
            day_of_week tinyint(1), -- 0-6, null for all
            base_price decimal(10,2) NOT NULL,
            weekend_premium decimal(10,2) DEFAULT 0,
            PRIMARY KEY  (id)
        ) $charset_collate;";

        dbDelta( $sql1 );
        dbDelta( $sql2 );
        dbDelta( $sql3 );
        dbDelta( $sql4 );
        dbDelta( $sql5 );
        dbDelta( $sql6 );
    }
}
