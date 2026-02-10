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
use MikroPlaneta\Booking\Core\Services\PricingService;
use MikroPlaneta\Booking\Core\Repositories\ReservationBedRepository;

if (!defined('ABSPATH')) {
    exit;
}

class ReservationService {
    
    private ReservationRepository $reservation_repository;
    private GuestRepository $guest_repository;
    private BedRepository $bed_repository;
    private AvailabilityService $availability_service;
    private PricingService $pricing_service;
    private ReservationBedRepository $reservation_bed_repository;
    
    /**
     * Constructor
     */
    public function __construct(
        ReservationRepository $reservation_repository,
        GuestRepository $guest_repository,
        BedRepository $bed_repository,
        AvailabilityService $availability_service,
        PricingService $pricing_service,
        ReservationBedRepository $reservation_bed_repository
    ) {
        $this->reservation_repository = $reservation_repository;
        $this->guest_repository = $guest_repository;
        $this->bed_repository = $bed_repository;
        $this->availability_service = $availability_service;
        $this->pricing_service = $pricing_service;
        $this->reservation_bed_repository = $reservation_bed_repository;
    }
    
    /**
     * Create new reservation with validation
     * Always expects bed_ids array for single or group reservations
     */
    public function createReservation(array $data): Reservation {
        // Validate required fields
        $this->validateReservationData($data);
        
        // Ensure bed_ids is always an array
        if (!isset($data['bed_ids']) || !is_array($data['bed_ids']) || empty($data['bed_ids'])) {
            throw new \Exception('At least one bed must be selected (bed_ids required)');
        }
        
        $bed_ids = $data['bed_ids'];
        
        // Validate dates
        $this->validateDates($data['check_in'], $data['check_out']);
        
        // Verify guest exists
        $guest = $this->guest_repository->find($data['guest_id']);
        if (!$guest) {
            throw new \Exception('Guest not found');
        }
        
        // Validate all beds and check availability
        $total_price = 0;
        foreach ($bed_ids as $bed_id) {
            // Verify bed exists and is active
            $bed = $this->bed_repository->find($bed_id);
            if (!$bed || !$bed->is_active) {
                throw new \Exception("Bed #{$bed_id} not found or inactive");
            }
            
            // Check bed availability
            if (!$this->availability_service->isBedAvailable(
                $bed_id,
                $data['check_in'],
                $data['check_out']
            )) {
                throw new \Exception("Bed #{$bed_id} is not available for selected dates");
            }
            
            // Calculate price for this bed
            $total_price += $this->calculatePrice(
                $bed_id,
                $data['check_in'],
                $data['check_out']
            );
        }
        
        // Use calculated price if not provided
        if (!isset($data['total_price'])) {
            $data['total_price'] = $total_price;
        }
        
        // CRITICAL: Force all reservations to start as PENDING
        // This ensures they must be explicitly confirmed before finalization
        $data['status'] = Reservation::STATUS_PENDING;
        
        // Remove bed_ids from data before creating reservation
        unset($data['bed_ids']);
        
        // Create reservation
        $reservation = $this->reservation_repository->create($data);
        
        // Link all beds to this reservation
        $this->reservation_bed_repository->setBedsForReservation(
            $reservation->id,
            $bed_ids
        );
        
        // Re-fetch to get beds and updated data
        $reservation = $this->reservation_repository->find($reservation->id);
        
        // Fire WordPress action
        do_action('mikroplaneta_booking_reservation_created', $reservation, $bed_ids);
        
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
        
        // If dates or beds are being changed, check availability
        if (isset($data['check_in']) || isset($data['check_out']) || isset($data['bed_ids'])) {
            $check_in = $data['check_in'] ?? $reservation->check_in;
            $check_out = $data['check_out'] ?? $reservation->check_out;
            $bed_ids = $data['bed_ids'] ?? $reservation->bed_ids;
            
            $this->validateDates($check_in, $check_out);
            
            foreach ($bed_ids as $bed_id) {
                if (!$this->availability_service->isBedAvailable(
                    $bed_id,
                    $check_in,
                    $check_out,
                    $id // Exclude current reservation
                )) {
                    throw new \Exception("Bed #{$bed_id} is not available for selected dates");
                }
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
        $required = ['bed_ids', 'guest_id', 'check_in', 'check_out'];
        
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
        $result = $this->pricing_service->calculateTotalPrice($bed_id, $check_in, $check_out);
        return (float) $result['total'];
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
