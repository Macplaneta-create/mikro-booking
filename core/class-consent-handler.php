<?php
/**
 * Consent Handler
 *
 * Handles GDPR consent logging and management
 *
 * @package MikroPlaneta\Booking
 * @since 1.0.0
 */

namespace MikroPlaneta\Booking\Core;

if (!defined('ABSPATH')) {
    exit;
}

class Consent_Handler {

    /**
     * Initialize consent handler
     */
    public function __construct() {
        add_action('mikroplaneta_booking_consents_given', [$this, 'log_consents'], 10, 3);
        add_filter('mikroplaneta_booking_email_confirmation_data', [$this, 'add_consent_to_email'], 10, 2);
    }

    /**
     * Log consents to database
     *
     * @param int $reservation_id Reservation ID
     * @param array $consents Consent data
     * @param string $email Guest email
     */
    public function log_consents(int $reservation_id, array $consents, string $email): void {
        global $wpdb;
        
        $table = $wpdb->prefix . 'booking_consents';
        
        // Create table if doesn't exist
        $this->create_table_if_not_exists();
        
        $data = [
            'reservation_id' => $reservation_id,
            'guest_email' => $email,
            'data_processing' => !empty($consents['data_processing']) ? 1 : 0,
            'terms_accepted' => !empty($consents['terms_accepted']) ? 1 : 0,
            'marketing' => !empty($consents['marketing']) ? 1 : 0,
            'ip_address' => $consents['ip_address'] ?? '',
            'user_agent' => $consents['user_agent'] ?? '',
            'consent_timestamp' => $consents['timestamp'] ?? current_time('mysql'),
            'created_at' => current_time('mysql'),
        ];
        
        $result = $wpdb->insert($table, $data);
        
        if ($result === false) {
            error_log('[MikroBooking] Failed to log consents: ' . $wpdb->last_error);
        } else {
            error_log('[MikroBooking] Consents logged for reservation ' . $reservation_id . ' by ' . $email);
        }
    }

    /**
     * Add consent info to confirmation email
     *
     * @param array $data Email data
     * @param int $reservation_id Reservation ID
     * @return array
     */
    public function add_consent_to_email(array $data, int $reservation_id): array {
        global $wpdb;
        
        $table = $wpdb->prefix . 'booking_consents';
        $consent = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table} WHERE reservation_id = %d",
            $reservation_id
        ), ARRAY_A);
        
        if ($consent) {
            $data['consents'] = [
                'data_processing' => (bool) $consent['data_processing'],
                'terms_accepted' => (bool) $consent['terms_accepted'],
                'marketing' => (bool) $consent['marketing'],
                'timestamp' => $consent['consent_timestamp'],
                'ip' => $consent['ip_address'],
            ];
        }
        
        return $data;
    }

    /**
     * Create consents table if not exists
     */
    private function create_table_if_not_exists(): void {
        global $wpdb;
        
        $table = $wpdb->prefix . 'booking_consents';
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS {$table} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            reservation_id BIGINT(20) UNSIGNED NOT NULL,
            guest_email VARCHAR(255) NOT NULL,
            data_processing TINYINT(1) NOT NULL DEFAULT 0,
            terms_accepted TINYINT(1) NOT NULL DEFAULT 0,
            marketing TINYINT(1) NOT NULL DEFAULT 0,
            ip_address VARCHAR(45) NOT NULL DEFAULT '',
            user_agent TEXT NOT NULL,
            consent_timestamp DATETIME NOT NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY reservation_id (reservation_id),
            KEY guest_email (guest_email),
            KEY consent_timestamp (consent_timestamp)
        ) {$charset_collate};";
        
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }

    /**
     * Get consents for reservation
     *
     * @param int $reservation_id Reservation ID
     * @return array|null
     */
    public static function get_consents(int $reservation_id): ?array {
        global $wpdb;
        
        $table = $wpdb->prefix . 'booking_consents';
        $consent = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table} WHERE reservation_id = %d",
            $reservation_id
        ), ARRAY_A);
        
        return $consent ?: null;
    }
}
