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
use MikroPlaneta\Booking\Core\Services\NotificationService;
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
    private NotificationService $notification_service;
    
    /**
     * Constructor
     */
    public function __construct(
        ReservationRepository $reservation_repository,
        GuestRepository $guest_repository,
        BedRepository $bed_repository,
        AvailabilityService $availability_service,
        PricingService $pricing_service,
        ReservationBedRepository $reservation_bed_repository,
        NotificationService $notification_service
    ) {
        $this->reservation_repository = $reservation_repository;
        $this->guest_repository = $guest_repository;
        $this->bed_repository = $bed_repository;
        $this->availability_service = $availability_service;
        $this->pricing_service = $pricing_service;
        $this->reservation_bed_repository = $reservation_bed_repository;
        $this->notification_service = $notification_service;
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
        
        // Send confirmation email if enabled in settings
        $email_notifications = (bool) get_option('mikroplaneta_booking_email_notifications', true);
        if ($email_notifications && $guest->email) {
            try {
                $this->notification_service->sendReservationConfirmation($reservation, $guest);
            } catch (\Exception $e) {
                // Log error but don't fail the reservation
                error_log('Failed to send reservation confirmation email: ' . $e->getMessage());
            }
        }
        
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
        
        $normalized_bed_ids = null;

        // If dates or beds are being changed, check availability
        if (isset($data['check_in']) || isset($data['check_out']) || isset($data['bed_ids'])) {
            $check_in = $data['check_in'] ?? $reservation->check_in;
            $check_out = $data['check_out'] ?? $reservation->check_out;
            $bed_ids = $data['bed_ids'] ?? $reservation->bed_ids;

            if (isset($data['bed_ids'])) {
                $bed_ids = $this->normalizeBedIds($data['bed_ids']);
                if (empty($bed_ids)) {
                    throw new \Exception('At least one bed must be selected');
                }
                $normalized_bed_ids = $bed_ids;
            }
            
            $this->validateDates($check_in, $check_out);
            
            foreach ($bed_ids as $bed_id) {
                $bed = $this->bed_repository->find((int) $bed_id);
                if (!$bed || !$bed->is_active) {
                    throw new \Exception("Bed #{$bed_id} not found or inactive");
                }

                if (!$this->availability_service->isBedAvailable(
                    (int) $bed_id,
                    $check_in,
                    $check_out,
                    $id // Exclude current reservation
                )) {
                    throw new \Exception("Bed #{$bed_id} is not available for selected dates");
                }
            }
        }

        // Validate guests vs effective capacity when any of these fields change.
        if (isset($data['adults']) || isset($data['children']) || isset($data['bed_ids'])) {
            $adults = isset($data['adults']) ? max(1, (int) $data['adults']) : (int) $reservation->adults;
            $children = isset($data['children']) ? max(0, (int) $data['children']) : (int) $reservation->children;
            $effective_bed_ids = $normalized_bed_ids ?? $reservation->bed_ids;
            $this->assertGuestCountFitsCapacity($adults, $children, $effective_bed_ids);
        }
        
        // Store old values for logging
        $old_data = $reservation->toArray();
        
        // Update reservation
        $updated_reservation = $this->reservation_repository->update($id, $data);

        // Persist bed relation updates when bed_ids were provided
        if ($normalized_bed_ids !== null) {
            $this->reservation_bed_repository->setBedsForReservation($id, $normalized_bed_ids);
            $updated_reservation = $this->reservation_repository->find($id);
            if (!$updated_reservation) {
                throw new \Exception('Reservation not found after bed update');
            }
        }
        
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
        
        // Send cancellation email if enabled
        $email_notifications = (bool) get_option('mikroplaneta_booking_email_notifications', true);
        if ($email_notifications) {
            $guest = $this->guest_repository->find($updated->guest_id);
            if ($guest && $guest->email) {
                try {
                    $this->notification_service->sendReservationCancellation($updated, $guest, $reason);
                } catch (\Exception $e) {
                    error_log('Failed to send cancellation email: ' . $e->getMessage());
                }
            }
        }
        
        // Fire WordPress action
        do_action('mikroplaneta_booking_reservation_cancelled', $updated, $reason);
        
        return $updated;
    }
    
    public function confirmReservation(int $id, string $reason = ''): Reservation {
        $reservation = $this->reservation_repository->find($id);
        
        if (!$reservation) {
            throw new \Exception('Reservation not found');
        }
        
        $reservation->confirm();
        
        $update_data = [
            'status' => $reservation->status,
        ];
        
        if (!empty($reason)) {
            $update_data['notes'] = $reservation->notes . "\n\nConfirmed: " . $reason;
        }
        
        $updated = $this->reservation_repository->update($id, $update_data);
        
        // Fire WordPress action
        do_action('mikroplaneta_booking_reservation_confirmed', $updated, $reason);
        
        return $updated;
    }
    
    /**
     * Check in guest with optional adjustments (guest count, beds)
     */
    public function checkIn(int $id, array $adjustment = []): Reservation {
        $reservation = $this->reservation_repository->find($id);
        
        if (!$reservation) {
            throw new \Exception('Reservation not found');
        }
        
        if ($reservation->status !== Reservation::STATUS_CONFIRMED && $reservation->status !== Reservation::STATUS_PENDING) {
            throw new \Exception('Only pending or confirmed reservations can be checked in');
        }

        // Apply adjustments if provided (e.g., when fewer guests arrived)
        if (!empty($adjustment)) {
            $update_data = [];
            if (isset($adjustment['adults'])) {
                $update_data['adults'] = max(1, (int) $adjustment['adults']);
            }
            if (isset($adjustment['children'])) {
                $update_data['children'] = max(0, (int) $adjustment['children']);
            }

            $effective_bed_ids = $reservation->bed_ids;
            if (isset($adjustment['bed_ids'])) {
                $effective_bed_ids = $this->normalizeBedIds($adjustment['bed_ids']);
                if (empty($effective_bed_ids)) {
                    throw new \Exception('At least one bed must be selected');
                }
            }

            $effective_adults = $update_data['adults'] ?? $reservation->adults;
            $effective_children = $update_data['children'] ?? $reservation->children;
            $this->assertGuestCountFitsCapacity($effective_adults, $effective_children, $effective_bed_ids);
            
            if (!empty($update_data)) {
                $this->reservation_repository->update($id, $update_data);
            }
            
            if (isset($adjustment['bed_ids'])) {
                foreach ($effective_bed_ids as $bed_id) {
                    $bed = $this->bed_repository->find($bed_id);
                    if (!$bed || !$bed->is_active) {
                        throw new \Exception("Bed #{$bed_id} not found or inactive");
                    }

                    if (!$this->availability_service->isBedAvailable(
                        $bed_id,
                        $reservation->check_in,
                        $reservation->check_out,
                        $id
                    )) {
                        throw new \Exception("Bed #{$bed_id} is not available for selected dates");
                    }
                }

                $this->reservation_bed_repository->setBedsForReservation($id, $effective_bed_ids);
            }

            do_action(
                'mikroplaneta_booking_reservation_adjusted_during_checkin',
                $id,
                [
                    'adults' => $effective_adults,
                    'children' => $effective_children,
                    'bed_ids' => $effective_bed_ids,
                ]
            );
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

        // Validate guest count vs beds capacity
        $adults = isset($data['adults']) ? (int) $data['adults'] : 1;
        $children = isset($data['children']) ? (int) $data['children'] : 0;
        $bed_ids = is_array($data['bed_ids']) ? $data['bed_ids'] : [];
        $this->assertGuestCountFitsCapacity($adults, $children, $bed_ids);
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
     * Normalize bed IDs into a unique list of positive integers
     */
    private function normalizeBedIds($bed_ids): array {
        if (!is_array($bed_ids)) {
            return [];
        }

        $normalized = array_map('intval', $bed_ids);
        $normalized = array_filter($normalized, static function($id) {
            return $id > 0;
        });

        return array_values(array_unique($normalized));
    }

    /**
     * Ensure selected beds can host total guests (capacity-aware validation)
     */
    private function assertGuestCountFitsCapacity(int $adults, int $children, array $bed_ids): void {
        $total_guests = max(1, $adults + $children);
        $capacity = $this->calculateBedsCapacity($bed_ids);

        if ($total_guests > $capacity) {
            throw new \Exception("Number of guests ({$total_guests}) exceeds selected beds capacity ({$capacity})");
        }
    }

    /**
     * Calculate total beds capacity from bed types.
     * single=1, double=2, bunk=2
     */
    private function calculateBedsCapacity(array $bed_ids): int {
        $capacity = 0;

        foreach ($bed_ids as $bed_id) {
            $bed = $this->bed_repository->find((int) $bed_id);
            if (!$bed) {
                continue;
            }

            switch ((string) $bed->bed_type) {
                case 'double':
                case 'bunk':
                    $capacity += 2;
                    break;
                case 'single':
                default:
                    $capacity += 1;
                    break;
            }
        }

        return $capacity;
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
