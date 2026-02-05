<?php
/**
 * Database Schema Definitions
 *
 * Contains SQL definitions for all tables
 * Used by migration files
 *
 * @package MikroPlaneta\Booking
 * @since 1.0.0
 */

namespace MikroPlaneta\Booking\Core\Database;

if (!defined('ABSPATH')) {
    exit;
}

class Schema {
    
    /**
     * Get table name with prefix
     */
    public static function get_table_name(string $table): string {
        global $wpdb;
        return $wpdb->prefix . 'hotel_' . $table;
    }
    
    /**
     * Get charset collate
     */
    public static function get_charset_collate(): string {
        global $wpdb;
        return $wpdb->get_charset_collate();
    }
    
    /**
     * Rooms table schema
     */
    public static function rooms_table(): string {
        $table = self::get_table_name('rooms');
        $charset = self::get_charset_collate();
        
        return "CREATE TABLE {$table} (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL,
            floor TINYINT DEFAULT 0,
            room_type ENUM('standard', 'deluxe', 'suite', 'dormitory') DEFAULT 'standard',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_room_type (room_type)
        ) {$charset};";
    }
    
    /**
     * Beds table schema
     */
    public static function beds_table(): string {
        $table = self::get_table_name('beds');
        $rooms_table = self::get_table_name('rooms');
        $charset = self::get_charset_collate();
        
        return "CREATE TABLE {$table} (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            room_id BIGINT UNSIGNED NOT NULL,
            bed_number TINYINT NOT NULL,
            bed_type ENUM('single', 'double', 'bunk') DEFAULT 'single',
            is_active BOOLEAN DEFAULT TRUE,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (room_id) REFERENCES {$rooms_table}(id) ON DELETE CASCADE,
            UNIQUE KEY unique_bed (room_id, bed_number),
            INDEX idx_active (is_active)
        ) {$charset};";
    }
    
    /**
     * Guests table schema
     */
    public static function guests_table(): string {
        $table = self::get_table_name('guests');
        $charset = self::get_charset_collate();
        
        return "CREATE TABLE {$table} (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            first_name VARCHAR(100) NOT NULL,
            last_name VARCHAR(100) NOT NULL,
            email VARCHAR(255) NOT NULL,
            phone VARCHAR(20),
            preferences JSON,
            total_stays INT DEFAULT 0,
            last_stay_date DATE,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY unique_email (email),
            INDEX idx_name (last_name, first_name)
        ) {$charset};";
    }
    
    /**
     * Reservations table schema
     */
    public static function reservations_table(): string {
        $table = self::get_table_name('reservations');
        $beds_table = self::get_table_name('beds');
        $guests_table = self::get_table_name('guests');
        $charset = self::get_charset_collate();
        
        return "CREATE TABLE {$table} (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            bed_id BIGINT UNSIGNED NOT NULL,
            guest_id BIGINT UNSIGNED NOT NULL,
            check_in DATE NOT NULL,
            check_out DATE NOT NULL,
            status ENUM('pending', 'confirmed', 'checked_in', 'checked_out', 'cancelled') DEFAULT 'pending',
            adults INT DEFAULT 1,
            children INT DEFAULT 0,
            total_price DECIMAL(10,2) DEFAULT 0.00,
            notes TEXT,
            created_by BIGINT UNSIGNED,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (bed_id) REFERENCES {$beds_table}(id) ON DELETE RESTRICT,
            FOREIGN KEY (guest_id) REFERENCES {$guests_table}(id) ON DELETE RESTRICT,
            INDEX idx_dates (check_in, check_out),
            INDEX idx_status (status),
            INDEX idx_bed_dates (bed_id, check_in, check_out)
        ) {$charset};";
    }
    
    /**
     * Allocations log table schema
     */
    public static function allocations_log_table(): string {
        $table = self::get_table_name('allocations_log');
        $reservations_table = self::get_table_name('reservations');
        $charset = self::get_charset_collate();
        
        return "CREATE TABLE {$table} (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            reservation_id BIGINT UNSIGNED NOT NULL,
            suggested_bed_id BIGINT UNSIGNED,
            actual_bed_id BIGINT UNSIGNED NOT NULL,
            ai_confidence DECIMAL(5,2),
            satisfaction_score TINYINT,
            feedback_notes TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (reservation_id) REFERENCES {$reservations_table}(id) ON DELETE CASCADE,
            INDEX idx_reservation (reservation_id)
        ) {$charset};";
    }
    
    /**
     * Notifications table schema
     */
    public static function notifications_table(): string {
        $table = self::get_table_name('notifications');
        $reservations_table = self::get_table_name('reservations');
        $guests_table = self::get_table_name('guests');
        $charset = self::get_charset_collate();
        
        return "CREATE TABLE {$table} (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            reservation_id BIGINT UNSIGNED,
            guest_id BIGINT UNSIGNED NOT NULL,
            channel ENUM('email', 'sms', 'push') NOT NULL,
            template_name VARCHAR(100) NOT NULL,
            status ENUM('pending', 'sent', 'failed') DEFAULT 'pending',
            sent_at DATETIME,
            opened_at DATETIME,
            clicked_at DATETIME,
            error_message TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (reservation_id) REFERENCES {$reservations_table}(id) ON DELETE SET NULL,
            FOREIGN KEY (guest_id) REFERENCES {$guests_table}(id) ON DELETE CASCADE,
            INDEX idx_status (status),
            INDEX idx_guest (guest_id)
        ) {$charset};";
    }
    
    /**
     * Changes log table schema
     */
    public static function changes_log_table(): string {
        $table = self::get_table_name('changes_log');
        $reservations_table = self::get_table_name('reservations');
        $charset = self::get_charset_collate();
        
        return "CREATE TABLE {$table} (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            reservation_id BIGINT UNSIGNED NOT NULL,
            changed_by BIGINT UNSIGNED,
            change_type ENUM('created', 'updated', 'cancelled', 'status_changed') NOT NULL,
            old_value JSON,
            new_value JSON,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (reservation_id) REFERENCES {$reservations_table}(id) ON DELETE CASCADE,
            INDEX idx_reservation (reservation_id),
            INDEX idx_created_at (created_at)
        ) {$charset};";
    }
    
    /**
     * AI suggestions table schema
     */
    public static function ai_suggestions_table(): string {
        $table = self::get_table_name('ai_suggestions');
        $charset = self::get_charset_collate();
        
        return "CREATE TABLE {$table} (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            request_params JSON NOT NULL,
            suggestion JSON NOT NULL,
            confidence_score DECIMAL(5,2),
            was_accepted BOOLEAN DEFAULT FALSE,
            feedback TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_created_at (created_at)
        ) {$charset};";
    }
}
