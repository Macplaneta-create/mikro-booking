<?php
/**
 * Logger Service
 *
 * Centralized logging for tracking changes
 *
 * @package MikroPlaneta\Booking
 * @since 1.0.0
 */

namespace MikroPlaneta\Booking\Core\Services;

use MikroPlaneta\Booking\Core\Repositories\ChangesLogRepository;

if (!defined('ABSPATH')) {
    exit;
}

class LoggerService {
    
    private ChangesLogRepository $repository;
    
    /**
     * Constructor
     */
    public function __construct(ChangesLogRepository $repository) {
        $this->repository = $repository;
    }
    
    /**
     * Log a change
     */
    public function log(int $reservation_id, string $action, array $old = [], array $new = []): void {
        $user_id = get_current_user_id();
        
        $this->repository->create([
            'reservation_id' => $reservation_id,
            'changed_by' => $user_id,
            'change_type' => $action, // created, updated, cancelled, status_changed
            'old_value' => !empty($old) ? wp_json_encode($old) : null,
            'new_value' => !empty($new) ? wp_json_encode($new) : null,
        ]);
    }
    
    /**
     * Get logs for a reservation
     */
    public function getLogs(int $reservation_id): array {
        return $this->repository->findByReservation($reservation_id);
    }
}
