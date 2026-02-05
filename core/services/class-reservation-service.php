<?php
/**
 * Reservation Service
 *
 * Business logic for managing reservations
 *
 * @package MikroPlaneta\Booking
 * @since 1.0.0
 */

namespace MikroPlaneta\Booking\Core\Services;

use MikroPlaneta\Booking\Core\Repositories\ReservationRepository;
use MikroPlaneta\Booking\Core\Repositories\GuestRepository;
use MikroPlaneta\Booking\Core\Repositories\BedRepository;
use MikroPlaneta\Booking\Core\Models\Reservation;

if (!defined('ABSPATH')) {
    exit;
}

class ReservationService {
    
    private ReservationRepository $reservation_repository;
    private GuestRepository $guest_repository;
    private BedRepository $bed_repository;
    private AvailabilityService $availability_service;
    
    /**
     * Constructor
     */
    public function __construct(
        ReservationRepository $reservation_repository,
        GuestRepository $guest_repository,
        BedRepository $bed_repository,
        AvailabilityService $availability_service
    ) {
        $this->reservation_repository = $reservation_repository;
        $this->guest_repository = $guest_repository;
        $this->bed_repository = $bed_repository;
        $this->availability_service = $availability_service;
    }
    
    /**
     * Create new reservation with validation
     */
    public function createReservation(array $data): Reservation {
        // Validate required fields
        $this->validateReservationData($data);
        
        // Check bed availability
        if (!$this->availability_service->isBedAvailable(
            $data['bed_id'],
            $data['check_in'],
            $data['check_out']
        )) {
            throw new \Exception('Bed is not available for selected dates');
        }
        
        // Validate dates
        $this->validateDates($data['check_in'], $data['check_out']);
        
        // Verify bed exists and is active
        $bed = $this->bed_repository->find($data['bed_id']);
        if (!$bed || !$bed->is_active) {
            throw new \Exception('Bed not found or inactive');
        }
        
        // Verify guest exists
        $guest = $this->guest_repository->find($data['guest_id']);
        if (!$guest) {
            throw new \Exception('Guest not found');
        }
        
        // Calculate price if not provided
        if (!isset($data['total_price'])) {
            $data['total_price'] = $this->calculatePrice(
                $data['bed_id'],
                $data['check_in'],
                $data['check_out']
            );
        }
        
        // Create reservation
        $reservation = $this->reservation_repository->create($data);
        
        // Fire WordPress action
        do_action('mikroplaneta_booking_reservation_created', $reservation);
        
        return $reservation;
    }
    
    /**
     * Update reservation with validation
     */
    public function updateReservation(int $id, array $data): Reservation {
        $reservation = $this->reservation_repository->find($id);
        
        if (!$reservation) {
            throw new \Exception('Reservation not found');
        }
        
        // If dates are being changed, check availability
        if (isset($data['check_in']) || isset($data['check_out']) || isset($data['bed_id'])) {
            $check_in = $data['check_in'] ?? $reservation->check_in;
            $check_out = $data['check_out'] ?? $reservation->check_out;
            $bed_id = $data['bed_id'] ?? $reservation->bed_id;
            
            $this->validateDates($check_in, $check_out);
            
            if (!$this->availability_service->isBedAvailable(
                $bed_id,
                $check_in,
                $check_out,
                $id // Exclude current reservation
            )) {
                throw new \Exception('Bed is not available for selected dates');
            }
        }
        
        // Store old values for logging
        $old_data = $reservation->toArray();
        
        // Update reservation
        $updated_reservation = $this->reservation_repository->update($id, $data);
        
        // Log changes
        $this->logChanges($id, $old_data, $updated_reservation->toArray());
        
        // Fire WordPress action
        do_action('mikroplaneta_booking_reservation_updated', $updated_reservation, $old_data);
        
        return $updated_reservation;
    }
    
    /**
     * Cancel reservation
     */
    public function cancelReservation(int $id, string $reason = ''): Reservation {
        $reservation = $this->reservation_repository->find($id);
        
        if (!$reservation) {
            throw new \Exception('Reservation not found');
        }
        
        if ($reservation->isCancelled()) {
            throw new \Exception('Reservation is already cancelled');
        }
        
        // Update status
        $reservation->cancel();
        $updated = $this->reservation_repository->update($id, [
            'status' => $reservation->status,
            'notes' => $reservation->notes . "\n\nCancelled: " . $reason,
        ]);
        
        // Fire WordPress action
        do_action('mikroplaneta_booking_reservation_cancelled', $updated, $reason);
        
        return $updated;
    }
    
    /**
     * Confirm reservation
     */
    public function confirmReservation(int $id): Reservation {
        $reservation = $this->reservation_repository->find($id);
        
        if (!$reservation) {
            throw new \Exception('Reservation not found');
        }
        
        $reservation->confirm();
        $updated = $this->reservation_repository->update($id, [
            'status' => $reservation->status,
        ]);
        
        // Fire WordPress action
        do_action('mikroplaneta_booking_reservation_confirmed', $updated);
        
        return $updated;
    }
    
    /**
     * Check in guest
     */
    public function checkIn(int $id): Reservation {
        $reservation = $this->reservation_repository->find($id);
        
        if (!$reservation) {
            throw new \Exception('Reservation not found');
        }
        
        if ($reservation->status !== Reservation::STATUS_CONFIRMED) {
            throw new \Exception('Only confirmed reservations can be checked in');
        }
        
        $reservation->checkIn();
        $updated = $this->reservation_repository->update($id, [
            'status' => $reservation->status,
        ]);
        
        // Fire WordPress action
        do_action('mikroplaneta_booking_guest_checked_in', $updated);
        
        return $updated;
    }
    
    /**
     * Check out guest
     */
    public function checkOut(int $id): Reservation {
        $reservation = $this->reservation_repository->find($id);
        
        if (!$reservation) {
            throw new \Exception('Reservation not found');
        }
        
        if ($reservation->status !== Reservation::STATUS_CHECKED_IN) {
            throw new \Exception('Only checked-in reservations can be checked out');
        }
        
        $reservation->checkOut();
        $updated = $this->reservation_repository->update($id, [
            'status' => $reservation->status,
        ]);
        
        // Update guest statistics
        $guest = $this->guest_repository->find($reservation->guest_id);
        if ($guest) {
            $guest->incrementStays();
            $this->guest_repository->update($guest->id, [
                'total_stays' => $guest->total_stays,
                'last_stay_date' => $guest->last_stay_date,
            ]);
        }
        
        // Fire WordPress action
        do_action('mikroplaneta_booking_guest_checked_out', $updated);
        
        return $updated;
    }
    
    /**
     * Validate reservation data
     */
    private function validateReservationData(array $data): void {
        $required = ['bed_id', 'guest_id', 'check_in', 'check_out'];
        
        foreach ($required as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                throw new \Exception("Field '{$field}' is required");
            }
        }
    }
    
    /**
     * Validate dates
     */
    private function validateDates(string $check_in, string $check_out): void {
        $check_in_dt = new \DateTime($check_in);
        $check_out_dt = new \DateTime($check_out);
        
        if ($check_in_dt >= $check_out_dt) {
            throw new \Exception('Check-out date must be after check-in date');
        }
        
        $today = new \DateTime('today');
        if ($check_in_dt < $today) {
            throw new \Exception('Check-in date cannot be in the past');
        }
    }
    
    /**
     * Calculate price for reservation
     */
    private function calculatePrice(int $bed_id, string $check_in, string $check_out): float {
        $check_in_dt = new \DateTime($check_in);
        $check_out_dt = new \DateTime($check_out);
        $nights = $check_in_dt->diff($check_out_dt)->days;
        
        // TODO: Implement dynamic pricing based on bed type, season, etc.
        $price_per_night = 100.00;
        
        return $nights * $price_per_night;
    }
    
    /**
     * Log changes to reservation
     */
    private function logChanges(int $reservation_id, array $old_data, array $new_data): void {
        // TODO: Implement changes logging to wp_hotel_changes_log table
        // For now, just fire an action
        do_action('mikroplaneta_booking_reservation_changed', $reservation_id, $old_data, $new_data);
    }
}
