<?php
/**
 * Plugin Deactivator
 *
 * Handles plugin deactivation:
 * - Clears scheduled events
 * - Flushes cache
 * - Does NOT delete data (use uninstall.php for that)
 *
 * @package MikroPlaneta\Booking
 * @since 1.0.0
 */

namespace MikroPlaneta\Booking\Core;

if (!defined('ABSPATH')) {
    exit;
}

class Deactivator {
    
    /**
     * Run on plugin deactivation
     */
    public static function deactivate(): void {
        // Clear scheduled cron events (if any)
        wp_clear_scheduled_hook('mikroplaneta_booking_daily_cleanup');
        
        // Flush rewrite rules
        flush_rewrite_rules();
        
        // Clear transients
        self::clear_transients();
    }
    
    /**
     * Clear plugin transients
     */
    private static function clear_transients(): void {
        global $wpdb;
        
        $wpdb->query(
            "DELETE FROM {$wpdb->options} 
            WHERE option_name LIKE '_transient_mikroplaneta_booking_%' 
            OR option_name LIKE '_transient_timeout_mikroplaneta_booking_%'"
        );
    }
}
