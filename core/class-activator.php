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
        ];
        
        foreach ($defaults as $key => $value) {
            if (get_option($key) === false) {
                add_option($key, $value);
            }
        }
    }
}
