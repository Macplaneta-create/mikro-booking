<?php
/**
 * Logging Handler
 *
 * Listens to plugin hooks and logs changes
 *
 * @package MikroPlaneta\Booking
 * @since 1.0.0
 */

namespace MikroPlaneta\Booking\Core;

use MikroPlaneta\Booking\Core\Services\LoggerService;
use MikroPlaneta\Booking\Core\Repositories\ChangesLogRepository;

if (!defined('ABSPATH')) {
    exit;
}

class LoggingHandler {
    
    private LoggerService $logger;
    
    /**
     * Constructor
     */
    public function __construct() {
        $repository = new ChangesLogRepository();
        $this->logger = new LoggerService($repository);
    }
    
    /**
     * Initialize logging handlers
     */
    public static function init(): void {
        $handler = new self();
        
        // Hook into reservation actions
        add_action('mikroplaneta_booking_reservation_created', [$handler, 'log_created'], 10, 2);
        add_action('mikroplaneta_booking_reservation_updated', [$handler, 'log_updated'], 10, 2);
        add_action('mikroplaneta_booking_reservation_cancelled', [$handler, 'log_cancelled'], 10, 2);
        add_action('mikroplaneta_booking_reservation_confirmed', [$handler, 'log_confirmed'], 10, 1);
        add_action('mikroplaneta_booking_reservation_checked_in', [$handler, 'log_checked_in'], 10, 1);
        add_action('mikroplaneta_booking_reservation_checked_out', [$handler, 'log_checked_out'], 10, 1);
    }
    
    /**
     * Log reservation creation
     */
    public function log_created($reservation, $bed_ids): void {
        $this->logger->log(
            $reservation->id,
            'created',
            [],
            [
                'reservation' => $reservation->toArray(),
                'bed_ids' => $bed_ids
            ]
        );
    }
    
    /**
     * Log reservation update
     */
    public function log_updated($reservation, $changes): void {
        $this->logger->log(
            $reservation->id,
            'updated',
            [], // Currently we don't have access to old state here easily without refactoring
            $changes
        );
    }
    
    /**
     * Log reservation cancellation
     */
    public function log_cancelled($reservation, $reason): void {
        $this->logger->log(
            $reservation->id,
            'cancelled',
            ['status' => 'active'], // Assumption
            ['status' => 'cancelled', 'reason' => $reason]
        );
    }
    
    /**
     * Log confirmation
     */
    public function log_confirmed($reservation): void {
        $this->logger->log(
            $reservation->id,
            'status_changed',
            ['status' => 'pending'],
            ['status' => 'confirmed']
        );
    }
    
    /**
     * Log check-in
     */
    public function log_checked_in($reservation): void {
        $this->logger->log(
            $reservation->id,
            'status_changed',
            ['status' => 'confirmed'],
            ['status' => 'checked_in']
        );
    }
    
    /**
     * Log check-out
     */
    public function log_checked_out($reservation): void {
        $this->logger->log(
            $reservation->id,
            'status_changed',
            ['status' => 'checked_in'],
            ['status' => 'checked_out']
        );
    }
}

// Initialize handler
LoggingHandler::init();
