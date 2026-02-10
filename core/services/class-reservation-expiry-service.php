<?php
/**
 * Reservation Expiry Service
 *
 * Handles automatic expiry of pending reservations after timeout
 *
 * @package MikroPlaneta\Booking
 * @since 1.0.0
 */

namespace MikroPlaneta\Booking\Core\Services;

use MikroPlaneta\Booking\Core\Repositories\ReservationRepository;
use MikroPlaneta\Booking\Core\Models\Reservation;

if (!defined('ABSPATH')) {
    exit;
}

class ReservationExpiryService {
    
    private ReservationRepository $reservation_repository;
    
    /**
     * Constructor
     */
    public function __construct(ReservationRepository $reservation_repository) {
        $this->reservation_repository = $reservation_repository;
    }
    
    /**
     * Check and expire pending reservations that exceeded timeout
     *
     * @return int Number of reservations expired
     */
    public function expirePendingReservations(): int {
        // Check if auto-expire is enabled
        $auto_expire = (bool) get_option('mikroplaneta_booking_auto_expire_pending', true);
        
        if (!$auto_expire) {
            return 0;
        }
        
        // Get timeout setting (in hours)
        $timeout_hours = (int) get_option('mikroplaneta_booking_pending_timeout_hours', 48);
        
        // Calculate cutoff time
        $cutoff_time = date('Y-m-d H:i:s', time() - ($timeout_hours * 3600));
        
        // Get all pending reservations older than cutoff
        $pending_reservations = $this->reservation_repository->all([
            'status' => Reservation::STATUS_PENDING,
        ]);
        
        $expired_count = 0;
        
        foreach ($pending_reservations as $reservation) {
            // Check if created_at is older than cutoff
            if (strtotime($reservation->created_at) < strtotime($cutoff_time)) {
                try {
                    // Update status to cancelled
                    $this->reservation_repository->update($reservation->id, [
                        'status' => Reservation::STATUS_CANCELLED,
                    ]);
                    
                    // Log the expiry action
                    $this->log_expiry($reservation->id, $reservation->created_at);
                    
                    // Fire WordPress action for hooks
                    do_action('mikroplaneta_booking_reservation_expired', $reservation, $timeout_hours);
                    
                    $expired_count++;
                } catch (\Exception $e) {
                    error_log('[MikroBooking] Expiry Error for Reservation #' . intval($reservation->id) . ': ' . wp_json_encode([
                        'error' => $e->getMessage(),
                        'created_at' => $reservation->created_at,
                        'cutoff_time' => $cutoff_time,
                    ]));
                }
            }
        }
        
        if ($expired_count > 0) {
            error_log('[MikroBooking] Expired ' . intval($expired_count) . ' pending reservations (timeout: ' . intval($timeout_hours) . 'h, cutoff: ' . $cutoff_time . ')');
        }
        
        return $expired_count;
    }
    
    /**
     * Log reservation expiry
     */
    private function log_expiry(int $reservation_id, string $created_at): void {
        $timeout_hours = (int) get_option('mikroplaneta_booking_pending_timeout_hours', 48);
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[MikroBooking] Reservation #' . intval($reservation_id) . ' expired (pending for ' . intval($timeout_hours) . 'h, created: ' . $created_at . ')');
        }
    }
}
