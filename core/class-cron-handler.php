<?php
/**
 * Cron Handler
 *
 * Handles scheduled tasks for the plugin
 *
 * @package MikroPlaneta\Booking
 * @since 1.0.0
 */

namespace MikroPlaneta\Booking\Core;

use MikroPlaneta\Booking\Core\Services\ReservationExpiryService;
use MikroPlaneta\Booking\Core\Repositories\ReservationRepository;

if (!defined('ABSPATH')) {
    exit;
}

class CronHandler {
    
    /**
     * Initialize cron handlers
     */
    public static function init(): void {
        // Hook the cron callback
        add_action('mikroplaneta_booking_expire_reservations', [self::class, 'expire_reservations']);
    }
    
    /**
     * Handle reservation expiry cron job
     */
    public static function expire_reservations(): void {
        try {
            // Load required files
            require_once MIKROPLANETA_BOOKING_PLUGIN_DIR . 'core/repositories/class-reservation-repository.php';
            require_once MIKROPLANETA_BOOKING_PLUGIN_DIR . 'core/services/class-reservation-expiry-service.php';
            
            $repository = new ReservationRepository();
            $expiry_service = new ReservationExpiryService($repository);
            
            $expired_count = $expiry_service->expirePendingReservations();
            
            if ($expired_count > 0 && defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[MikroPlaneta Booking] Cron: Expired ' . intval($expired_count) . ' pending reservations');
            }
        } catch (\Exception $e) {
            error_log('[MikroPlaneta Booking] Cron Error: ' . wp_json_encode([
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]));
        }
    }
}

// Initialize cron handlers
CronHandler::init();
