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
use MikroPlaneta\Booking\Core\Repositories\RoomRepository;

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
    private RoomRepository $room_repository;
    private ?LoggerService $logger_service;
    
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
        NotificationService $notification_service,
        RoomRepository $room_repository,
        ?LoggerService $logger_service = null
    ) {
        $this->reservation_repository = $reservation_repository;
        $this->guest_repository = $guest_repository;
        $this->bed_repository = $bed_repository;
        $this->availability_service = $availability_service;
        $this->pricing_service = $pricing_service;
        $this->reservation_bed_repository = $reservation_bed_repository;
        $this->notification_service = $notification_service;
        $this->room_repository = $room_repository;
        $this->logger_service = $logger_service;
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
        
        $bed_ids = $this->expandBedsForExclusiveRooms($this->normalizeBedIds($data['bed_ids']));
        if (empty($bed_ids)) {
            throw new \Exception('At least one bed must be selected');
        }
        
        // Validate dates
        $this->validateDates($data['check_in'], $data['check_out']);
        
        // Verify guest exists
        $guest = $this->guest_repository->find($data['guest_id']);
        if (!$guest) {
            throw new \Exception('Guest not found');
        }

        $reservation = $this->withBedLocks($bed_ids, function() use ($bed_ids, $data): Reservation {
            // Validate all beds and check availability under lock
            $total_price = 0;
            foreach ($bed_ids as $bed_id) {
                $bed = $this->bed_repository->find($bed_id);
                if (!$bed || !$bed->is_active) {
                    throw new \Exception("Bed #{$bed_id} not found or inactive");
                }

                if (!$this->availability_service->isBedAvailable(
                    $bed_id,
                    $data['check_in'],
                    $data['check_out']
                )) {
                    throw new \Exception("Bed #{$bed_id} is not available for selected dates");
                }

                $total_price += $this->calculatePrice(
                    $bed_id,
                    $data['check_in'],
                    $data['check_out']
                );
            }

            $reservation_data = $data;
            if (!isset($reservation_data['total_price'])) {
                $reservation_data['total_price'] = $total_price;
            }
            $reservation_data['status'] = Reservation::STATUS_PENDING;
            unset($reservation_data['bed_ids']);

            $reservation = $this->reservation_repository->create($reservation_data);
            $this->reservation_bed_repository->setBedsForReservation($reservation->id, $bed_ids);

            $reloaded = $this->reservation_repository->find($reservation->id);
            if (!$reloaded) {
                throw new \Exception('Reservation not found after create');
            }

            return $reloaded;
        });
        
        // Send confirmation email if enabled in settings
        $email_notifications = (bool) get_option('mikroplaneta_booking_email_notifications', true);
        if ($email_notifications && $guest->email) {
            try {
                // Pass consents in context if available
                $context = [];
                if (isset($data['consents']) && !empty($data['consents'])) {
                    $context['consents'] = $data['consents'];
                }
                
                $this->notification_service->sendReservationConfirmation($reservation, $guest, $context);
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

        $current_bed_ids = $this->expandBedsForExclusiveRooms($this->normalizeBedIds($reservation->bed_ids));
        $candidate_bed_ids = isset($data['bed_ids'])
            ? $this->expandBedsForExclusiveRooms($this->normalizeBedIds($data['bed_ids']))
            : $current_bed_ids;
        $lock_bed_ids = array_values(array_unique(array_merge($current_bed_ids, $candidate_bed_ids)));

        return $this->withBedLocks($lock_bed_ids, function() use ($id, $data): Reservation {
            $reservation = $this->reservation_repository->find($id);
            if (!$reservation) {
                throw new \Exception('Reservation not found');
            }

            $normalized_bed_ids = null;

            if (isset($data['check_in']) || isset($data['check_out']) || isset($data['bed_ids'])) {
                $check_in = $data['check_in'] ?? $reservation->check_in;
                $check_out = $data['check_out'] ?? $reservation->check_out;
                $bed_ids = $this->expandBedsForExclusiveRooms($this->normalizeBedIds($data['bed_ids'] ?? $reservation->bed_ids));

                if (isset($data['bed_ids'])) {
                    $bed_ids = $this->expandBedsForExclusiveRooms($this->normalizeBedIds($data['bed_ids']));
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
                        $id
                    )) {
                        throw new \Exception("Bed #{$bed_id} is not available for selected dates");
                    }
                }
            }

            if (isset($data['adults']) || isset($data['children']) || isset($data['bed_ids'])) {
                $adults = isset($data['adults']) ? max(1, (int) $data['adults']) : (int) $reservation->adults;
                $children = isset($data['children']) ? max(0, (int) $data['children']) : (int) $reservation->children;
                $effective_bed_ids = $normalized_bed_ids ?? $this->expandBedsForExclusiveRooms($this->normalizeBedIds($reservation->bed_ids));
                $this->assertGuestCountFitsCapacity($adults, $children, $effective_bed_ids);
            }

            $old_data = $reservation->toArray();
            $updated_reservation = $this->reservation_repository->update($id, $data);

            if ($normalized_bed_ids !== null) {
                $this->reservation_bed_repository->setBedsForReservation($id, $normalized_bed_ids);
                $updated_reservation = $this->reservation_repository->find($id);
                if (!$updated_reservation) {
                    throw new \Exception('Reservation not found after bed update');
                }
            }

            $this->logChanges($id, $old_data, $updated_reservation->toArray());
            do_action('mikroplaneta_booking_reservation_updated', $updated_reservation, $old_data);

            return $updated_reservation;
        });
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

        $current_bed_ids = $this->expandBedsForExclusiveRooms($this->normalizeBedIds($reservation->bed_ids));
        $candidate_bed_ids = isset($adjustment['bed_ids'])
            ? $this->expandBedsForExclusiveRooms($this->normalizeBedIds($adjustment['bed_ids']))
            : $current_bed_ids;
        $lock_bed_ids = array_values(array_unique(array_merge($current_bed_ids, $candidate_bed_ids)));

        return $this->withBedLocks($lock_bed_ids, function() use ($id, $adjustment): Reservation {
            $reservation = $this->reservation_repository->find($id);
            if (!$reservation) {
                throw new \Exception('Reservation not found');
            }

            if ($reservation->status !== Reservation::STATUS_CONFIRMED && $reservation->status !== Reservation::STATUS_PENDING) {
                throw new \Exception('Only pending or confirmed reservations can be checked in');
            }

            if (!empty($adjustment)) {
                $update_data = [];
                if (isset($adjustment['adults'])) {
                    $update_data['adults'] = max(1, (int) $adjustment['adults']);
                }
                if (isset($adjustment['children'])) {
                    $update_data['children'] = max(0, (int) $adjustment['children']);
                }

                $effective_bed_ids = $this->expandBedsForExclusiveRooms($this->normalizeBedIds($reservation->bed_ids));
                if (isset($adjustment['bed_ids'])) {
                    $effective_bed_ids = $this->expandBedsForExclusiveRooms($this->normalizeBedIds($adjustment['bed_ids']));
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

            do_action('mikroplaneta_booking_guest_checked_in', $updated);

            return $updated;
        });
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
        $bed_ids = $this->expandBedsForExclusiveRooms($this->normalizeBedIds($data['bed_ids'] ?? []));
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
     * Calculate total places capacity from bed types.
     * single=1, double=1, bunk=2
     */
    private function calculateBedsCapacity(array $bed_ids): int {
        $capacity = 0;

        foreach ($bed_ids as $bed_id) {
            $bed = $this->bed_repository->find((int) $bed_id);
            if (!$bed) {
                continue;
            }

            switch ((string) $bed->bed_type) {
                case 'bunk':
                    $capacity += 2;
                    break;
                case 'double':
                case 'single':
                default:
                    $capacity += 1;
                    break;
            }
        }

        return $capacity;
    }

    /**
     * Private rooms and cabins are exclusive (whole-unit booking).
     * If at least one bed from such room is selected, include all active beds from that room.
     */
    private function expandBedsForExclusiveRooms(array $bed_ids): array {
        if (empty($bed_ids)) {
            return [];
        }

        $expanded = [];
        $processed_room_ids = [];

        foreach ($bed_ids as $bed_id) {
            $bed = $this->bed_repository->find((int) $bed_id);
            if (!$bed || !$bed->is_active) {
                continue;
            }

            $room = $this->room_repository->find((int) $bed->room_id);
            if (!$room) {
                $expanded[] = (int) $bed->id;
                continue;
            }

            if ($room->room_type !== 'dormitory') {
                if (isset($processed_room_ids[(int) $room->id])) {
                    continue;
                }

                $processed_room_ids[(int) $room->id] = true;
                $room_beds = $this->bed_repository->findActiveByRoom((int) $room->id);
                if (empty($room_beds)) {
                    $expanded[] = (int) $bed->id;
                    continue;
                }
                foreach ($room_beds as $room_bed) {
                    $expanded[] = (int) $room_bed->id;
                }
            } else {
                $expanded[] = (int) $bed->id;
            }
        }

        return array_values(array_unique(array_filter($expanded, static function($id) {
            return $id > 0;
        })));
    }

    /**
     * Execute reservation critical section with advisory bed locks and DB transaction.
     */
    private function withBedLocks(array $bed_ids, callable $callback) {
        global $wpdb;

        if (!isset($wpdb) || !is_object($wpdb)) {
            return $callback();
        }

        $normalized = $this->normalizeBedIds($bed_ids);
        sort($normalized);

        $acquired = [];

        try {
            foreach ($normalized as $bed_id) {
                $lock_key = 'mikro_booking_bed_' . (int) $bed_id;
                $locked = (string) $wpdb->get_var(
                    $wpdb->prepare('SELECT GET_LOCK(%s, %d)', $lock_key, 10)
                );

                if ($locked !== '1') {
                    throw new \Exception('Failed to acquire booking lock for bed #' . (int) $bed_id);
                }

                $acquired[] = $lock_key;
            }

            $wpdb->query('START TRANSACTION');

            try {
                $result = $callback();
                $wpdb->query('COMMIT');
                return $result;
            } catch (\Throwable $e) {
                $wpdb->query('ROLLBACK');
                throw $e;
            }
        } finally {
            for ($i = count($acquired) - 1; $i >= 0; $i--) {
                $wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)', $acquired[$i]));
            }
        }
    }
    
    /**
     * Log changes to reservation
     */
    private function logChanges(int $reservation_id, array $old_data, array $new_data): void {
        if ($this->logger_service) {
            try {
                $this->logger_service->log($reservation_id, 'updated', $old_data, $new_data);
            } catch (\Throwable $e) {
                error_log('[MikroBooking] Failed to write reservation change log: ' . $e->getMessage());
            }
        }

        do_action('mikroplaneta_booking_reservation_changed', $reservation_id, $old_data, $new_data);
    }
}
