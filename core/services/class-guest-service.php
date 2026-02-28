<?php
/**
 * Guest Service
 *
 * Business logic for managing guests
 *
 * @package MikroPlaneta\Booking
 * @since 1.0.0
 */

namespace MikroPlaneta\Booking\Core\Services;

use MikroPlaneta\Booking\Core\Repositories\GuestRepository;
use MikroPlaneta\Booking\Core\Repositories\ReservationRepository;
use MikroPlaneta\Booking\Core\Repositories\BedRepository;
use MikroPlaneta\Booking\Core\Models\Guest;

if (!defined('ABSPATH')) {
    exit;
}

class GuestService {
    
    private GuestRepository $guest_repository;
    private ReservationRepository $reservation_repository;
    private BedRepository $bed_repository;
    
    /**
     * Constructor
     */
    public function __construct(
        GuestRepository $guest_repository,
        ReservationRepository $reservation_repository,
        BedRepository $bed_repository
    ) {
        $this->guest_repository = $guest_repository;
        $this->reservation_repository = $reservation_repository;
        $this->bed_repository = $bed_repository;
    }
    
    /**
     * Create new guest with validation
     */
    public function createGuest(array $data): Guest {
        // Validate required fields
        $this->validateGuestData($data);

        // Validate email format
        if (!is_email($data['email'])) {
            throw new \Exception('Invalid email format');
        }

        // Create guest
        $guest = $this->guest_repository->create($data);

        // Fire WordPress action
        do_action('mikroplaneta_booking_guest_created', $guest);

        return $guest;
    }

    /**
     * Find guest by email
     */
    public function findByEmail(string $email): ?Guest {
        return $this->guest_repository->findByEmail($email);
    }
    
    /**
     * Update guest with validation
     */
    public function updateGuest(int $id, array $data): Guest {
        $guest = $this->guest_repository->find($id);
        
        if (!$guest) {
            throw new \Exception('Guest not found');
        }
        
        // If email is being changed, check for duplicates
        if (isset($data['email']) && $data['email'] !== $guest->email) {
            if (!is_email($data['email'])) {
                throw new \Exception('Invalid email format');
            }
            
            $existing = $this->guest_repository->findByEmail($data['email']);
            if ($existing && $existing->id !== $id) {
                throw new \Exception('Another guest with this email already exists');
            }
        }
        
        // Update guest
        $updated_guest = $this->guest_repository->update($id, $data);
        
        // Fire WordPress action
        do_action('mikroplaneta_booking_guest_updated', $updated_guest);
        
        return $updated_guest;
    }
    
    /**
     * Delete guest (only if no active reservations)
     */
    public function deleteGuest(int $id): bool {
        $guest = $this->guest_repository->find($id);
        
        if (!$guest) {
            throw new \Exception('Guest not found');
        }
        
        // Check for active reservations
        $reservations = $this->reservation_repository->findByGuest($id);
        $active_reservations = array_filter($reservations, function($reservation) {
            return $reservation->isActive();
        });
        
        if (!empty($active_reservations)) {
            throw new \Exception('Cannot delete guest with active reservations');
        }
        
        // Delete guest
        $result = $this->guest_repository->delete($id);
        
        if ($result) {
            do_action('mikroplaneta_booking_guest_deleted', $id);
        }
        
        return $result;
    }
    
    /**
     * Find or create guest by email
     */
    public function findOrCreateGuest(array $data): Guest {
        // Try to find existing guest
        if (isset($data['email'])) {
            $existing = $this->guest_repository->findByEmail($data['email']);
            if ($existing) {
                return $existing;
            }
        }
        
        // Create new guest
        return $this->createGuest($data);
    }
    
    /**
     * Merge duplicate guests
     */
    public function mergeGuests(int $keep_id, int $merge_id): Guest {
        $keep_guest = $this->guest_repository->find($keep_id);
        $merge_guest = $this->guest_repository->find($merge_id);
        
        if (!$keep_guest || !$merge_guest) {
            throw new \Exception('One or both guests not found');
        }
        
        if ($keep_id === $merge_id) {
            throw new \Exception('Cannot merge guest with itself');
        }
        
        // Transfer all reservations from merge_guest to keep_guest
        $reservations = $this->reservation_repository->findByGuest($merge_id);
        foreach ($reservations as $reservation) {
            $this->reservation_repository->update($reservation->id, [
                'guest_id' => $keep_id,
            ]);
        }
        
        // Merge preferences
        $merged_preferences = array_merge(
            $merge_guest->preferences,
            $keep_guest->preferences
        );
        
        // Update keep_guest with merged data
        $this->guest_repository->update($keep_id, [
            'preferences' => $merged_preferences,
            'total_stays' => $keep_guest->total_stays + $merge_guest->total_stays,
        ]);
        
        // Delete merge_guest
        $this->guest_repository->delete($merge_id);
        
        // Get updated guest
        $updated_guest = $this->guest_repository->find($keep_id);
        
        // Fire WordPress action
        do_action('mikroplaneta_booking_guests_merged', $keep_id, $merge_id);
        
        return $updated_guest;
    }
    
    /**
     * Get guest statistics
     */
    public function getGuestStatistics(int $id): array {
        $guest = $this->guest_repository->find($id);
        
        if (!$guest) {
            throw new \Exception('Guest not found');
        }
        
        $reservations = $this->reservation_repository->findByGuest($id);
        
        $stats = [
            'total_reservations' => count($reservations),
            'completed_stays' => 0,
            'cancelled_reservations' => 0,
            'upcoming_reservations' => 0,
            'total_nights' => 0,
            'total_spent' => 0.0,
            'favorite_bed_type' => null,
        ];
        
        $bed_types = [];
        
        foreach ($reservations as $reservation) {
            if ($reservation->isCancelled()) {
                $stats['cancelled_reservations']++;
                continue;
            }
            
            if ($reservation->status === 'checked_out') {
                $stats['completed_stays']++;
            }
            
            if ($reservation->isFuture()) {
                $stats['upcoming_reservations']++;
            }
            
            $stats['total_nights'] += $reservation->getNights();
            $stats['total_spent'] += $reservation->total_price;
            
            // Track bed types from reservation_beds relation.
            $reservation_bed_ids = is_array($reservation->bed_ids)
                ? array_values(array_unique(array_map('intval', $reservation->bed_ids)))
                : [];

            foreach ($reservation_bed_ids as $bed_id) {
                $bed = $this->bed_repository->find($bed_id);
                if ($bed) {
                    $bed_types[$bed->bed_type] = ($bed_types[$bed->bed_type] ?? 0) + 1;
                }
            }
        }
        
        // Find favorite bed type
        if (!empty($bed_types)) {
            arsort($bed_types);
            $stats['favorite_bed_type'] = array_key_first($bed_types);
        }
        
        return $stats;
    }
    
    /**
     * Search guests
     */
    public function searchGuests(string $query): array {
        return $this->guest_repository->search($query);
    }
    
    /**
     * Get returning guests
     */
    public function getReturningGuests(): array {
        return $this->guest_repository->getReturningGuests();
    }
    
    /**
     * Validate guest data
     */
    private function validateGuestData(array $data): void {
        $required = ['first_name', 'last_name', 'email'];
        
        foreach ($required as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                throw new \Exception("Field '{$field}' is required");
            }
        }
    }
}

