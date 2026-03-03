<?php
/**
 * Plugin Activator
 *
 * Handles plugin activation:
 * - Runs database migrations
 * - Sets default options
 * - Creates necessary directories
 *
 * @package MikroPlaneta\Booking
 * @since 1.0.0
 */

namespace MikroPlaneta\Booking\Core;

if (!defined('ABSPATH')) {
    exit;
}

class Activator {
    
    /**
     * Run on plugin activation
     */
    public static function activate(): void {
        // Check PHP version
        if (version_compare(PHP_VERSION, '8.0', '<')) {
            wp_die(
                esc_html__('MikroPlaneta Booking requires PHP 8.0 or higher.', 'mikroplaneta-booking'),
                esc_html__('Plugin Activation Error', 'mikroplaneta-booking'),
                ['back_link' => true]
            );
        }
        
        // Check WordPress version
        global $wp_version;
        if (version_compare($wp_version, '6.0', '<')) {
            wp_die(
                esc_html__('MikroPlaneta Booking requires WordPress 6.0 or higher.', 'mikroplaneta-booking'),
                esc_html__('Plugin Activation Error', 'mikroplaneta-booking'),
                ['back_link' => true]
            );
        }
        
        // Load database class
        require_once MIKROPLANETA_BOOKING_PLUGIN_DIR . 'core/database/class-database.php';
        require_once MIKROPLANETA_BOOKING_PLUGIN_DIR . 'core/database/class-schema.php';
        
        // Run database migrations
        $database = new \MikroPlaneta\Booking\Core\Database\Database();
        
        try {
            $database->migrate();
        } catch (\Exception $e) {
            wp_die(
                esc_html__('Database migration failed: ', 'mikroplaneta-booking') . esc_html($e->getMessage()),
                esc_html__('Plugin Activation Error', 'mikroplaneta-booking'),
                ['back_link' => true]
            );
        }
        
        // Set default plugin options
        self::set_default_options();
        
        // Schedule cron events
        self::schedule_cron_events();
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }
    
    /**
     * Set default plugin options
     */
    private static function set_default_options(): void {
        $defaults = [
            'mikroplaneta_booking_version' => MIKROPLANETA_BOOKING_VERSION,
            'mikroplaneta_booking_installed_at' => current_time('mysql'),
            'mikroplaneta_booking_pending_timeout_hours' => 48,
            'mikroplaneta_booking_auto_expire_pending' => true,
            'mikroplaneta_booking_require_payment_confirmation' => true,
            
            // Payment settings
            'mikroplaneta_booking_deposit_enabled' => false,
            'mikroplaneta_booking_deposit_percent' => 30,
            'mikroplaneta_booking_payment_account' => '',
            'mikroplaneta_booking_payment_bank_name' => '',
            'mikroplaneta_booking_payment_additional_info' => '',
            
            'mikroplaneta_booking_captcha_provider' => 'recaptcha_v3',
            'mikroplaneta_booking_recaptcha_site_key' => '',
            'mikroplaneta_booking_recaptcha_secret_key' => '',
            'mikroplaneta_booking_recaptcha_min_score' => 0.5,
            'mikroplaneta_booking_hcaptcha_site_key' => '',
            'mikroplaneta_booking_hcaptcha_secret_key' => '',
            'mikroplaneta_booking_rate_limit_enabled' => true,
            'mikroplaneta_booking_rate_limit_window_seconds' => 60,
            'mikroplaneta_booking_rate_limit_max_requests' => 120,

            // File retention settings (hours)
            'mikroplaneta_booking_backup_retention_hours' => 24,
            'mikroplaneta_booking_ical_retention_hours' => 24,
        ];
        
        foreach ($defaults as $key => $value) {
            if (get_option($key) === false) {
                add_option($key, $value);
            }
        }
    }
    
    /**
     * Schedule cron events
     */
    private static function schedule_cron_events(): void {
        // Schedule hourly cron for expiring pending reservations
        if (!wp_next_scheduled('mikroplaneta_booking_expire_reservations')) {
            wp_schedule_event(time(), 'hourly', 'mikroplaneta_booking_expire_reservations');
        }

        // Schedule daily cleanup for temporary export and iCal files
        if (!wp_next_scheduled('mikroplaneta_booking_cleanup_temp_files')) {
            wp_schedule_event(time(), 'daily', 'mikroplaneta_booking_cleanup_temp_files');
        }
    }
}
