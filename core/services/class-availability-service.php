<?php
/**
 * Availability Service
 *
 * Business logic for checking bed availability
 *
 * @package MikroPlaneta\Booking
 * @since 1.0.0
 */

namespace MikroPlaneta\Booking\Core\Services;

use MikroPlaneta\Booking\Core\Repositories\BedRepository;
use MikroPlaneta\Booking\Core\Repositories\ReservationRepository;
use MikroPlaneta\Booking\Core\Models\Bed;
use MikroPlaneta\Booking\Core\Models\Reservation;
use MikroPlaneta\Booking\Core\Database\Schema;

if (!defined('ABSPATH')) {
    exit;
}

class AvailabilityService {
    
    private BedRepository $bed_repository;
    private ReservationRepository $reservation_repository;
    
    /**
     * Constructor
     */
    public function __construct(
        BedRepository $bed_repository,
        ReservationRepository $reservation_repository
    ) {
        $this->bed_repository = $bed_repository;
        $this->reservation_repository = $reservation_repository;
    }
    
    /**
     * Find available beds for date range
     */
    public function findAvailableBeds(string $check_in, string $check_out, array $filters = []): array {
        // Get all active beds
        $all_beds = $this->bed_repository->all(array_merge($filters, ['is_active' => true]));
        
        // Filter out unavailable beds
        $available_beds = array_filter($all_beds, function(Bed $bed) use ($check_in, $check_out) {
            return $this->reservation_repository->isBedAvailable($bed->id, $check_in, $check_out);
        });
        
        return array_values($available_beds);
    }
    
    /**
     * Find available beds by room
     */
    public function findAvailableBedsByRoom(
        int $room_id,
        string $check_in,
        string $check_out
    ): array {
        return $this->findAvailableBeds($check_in, $check_out, ['room_id' => $room_id]);
    }
    
    /**
     * Find available beds for group
     * Returns array of bed groups that can accommodate the group
     */
    public function findAvailableBedsForGroup(
        int $group_size,
        string $check_in,
        string $check_out,
        array $preferences = []
    ): array {
        $available_beds = $this->findAvailableBeds($check_in, $check_out);
        
        $total_capacity = 0;
        foreach ($available_beds as $bed) {
            $total_capacity += $this->getBedCapacity($bed);
        }

        if ($total_capacity < $group_size) {
            return [];
        }
        
        // Group beds by room
        $beds_by_room = [];
        foreach ($available_beds as $bed) {
            $beds_by_room[$bed->room_id][] = $bed;
        }
        
        // Find combinations that can fit the group
        $combinations = [];
        
        // Try to fit group in single room first
        foreach ($beds_by_room as $room_id => $beds) {
            $room_capacity = 0;
            foreach ($beds as $bed) {
                $room_capacity += $this->getBedCapacity($bed);
            }

            if ($room_capacity >= $group_size) {
                usort($beds, function(Bed $a, Bed $b) {
                    return $this->getBedCapacity($b) <=> $this->getBedCapacity($a);
                });

                $picked_beds = [];
                $capacity_left = $group_size;
                foreach ($beds as $bed) {
                    $picked_beds[] = $bed;
                    $capacity_left -= $this->getBedCapacity($bed);
                    if ($capacity_left <= 0) {
                        break;
                    }
                }

                $combinations[] = [
                    'type' => 'single_room',
                    'room_id' => $room_id,
                    'beds' => $picked_beds,
                    'score' => 100, // Highest score for single room
                ];
            }
        }
        
        // If no single room fits, try multiple rooms
        if (empty($combinations)) {
            // Simple algorithm: take beds with highest capacity from multiple rooms
            usort($available_beds, function(Bed $a, Bed $b) {
                return $this->getBedCapacity($b) <=> $this->getBedCapacity($a);
            });

            $selected_beds = [];
            $capacity_left = $group_size;
            foreach ($available_beds as $bed) {
                $selected_beds[] = $bed;
                $capacity_left -= $this->getBedCapacity($bed);
                if ($capacity_left <= 0) {
                    break;
                }
            }

            if ($capacity_left <= 0) {
                $combinations[] = [
                    'type' => 'multiple_rooms',
                    'beds' => $selected_beds,
                    'score' => 50, // Lower score for split
                ];
            }
        }
        
        // Sort by score
        usort($combinations, function($a, $b) {
            return $b['score'] <=> $a['score'];
        });
        
        return $combinations;
    }
    
    /**
     * Get availability calendar for bed
     */
    public function getBedAvailabilityCalendar(int $bed_id, string $start_date, string $end_date): array {
        $reservations = $this->reservation_repository->findByBed($bed_id);
        
        $calendar = [];
        $current = new \DateTime($start_date);
        $end = new \DateTime($end_date);
        
        while ($current <= $end) {
            $date_str = $current->format('Y-m-d');
            $is_available = true;
            
            foreach ($reservations as $reservation) {
                if ($reservation->isCancelled()) {
                    continue;
                }
                
                $res_start = new \DateTime($reservation->check_in);
                $res_end = new \DateTime($reservation->check_out);
                
                if ($current >= $res_start && $current < $res_end) {
                    $is_available = false;
                    break;
                }
            }
            
            $calendar[$date_str] = [
                'date' => $date_str,
                'available' => $is_available,
            ];
            
            $current->modify('+1 day');
        }
        
        return $calendar;
    }
    
    /**
     * Get occupancy rate for date range
     */
    public function getOccupancyRate(string $start_date, string $end_date): array {
        $all_beds = $this->bed_repository->all(['is_active' => true]);
        $total_beds = count($all_beds);
        
        if ($total_beds === 0) {
            return [
                'rate' => 0,
                'occupied_beds' => 0,
                'total_beds' => 0,
            ];
        }
        
        $start = new \DateTime($start_date);
        $end = new \DateTime($end_date);
        $days = $start->diff($end)->days + 1;
        
        $total_bed_days = $total_beds * $days;
        $occupied_bed_days = 0;
        
        foreach ($all_beds as $bed) {
            $reservations = $this->reservation_repository->findByBed($bed->id);
            
            foreach ($reservations as $reservation) {
                if ($reservation->isCancelled()) {
                    continue;
                }
                
                $res_start = max($start_date, $reservation->check_in);
                $res_end = min($end_date, $reservation->check_out);
                
                if ($res_start < $res_end) {
                    $res_start_dt = new \DateTime($res_start);
                    $res_end_dt = new \DateTime($res_end);
                    $occupied_bed_days += $res_start_dt->diff($res_end_dt)->days;
                }
            }
        }
        
        $rate = $total_bed_days > 0 ? ($occupied_bed_days / $total_bed_days) * 100 : 0;
        
        return [
            'rate' => round($rate, 2),
            'occupied_bed_days' => $occupied_bed_days,
            'total_bed_days' => $total_bed_days,
            'total_beds' => $total_beds,
            'days' => $days,
        ];
    }
    
    /**
     * Check if specific bed is available
     */
    public function isBedAvailable(
        int $bed_id,
        string $check_in,
        string $check_out,
        ?int $exclude_reservation_id = null
    ): bool {
        $bed = $this->bed_repository->find($bed_id);
        if (!$bed || !$bed->is_active) {
            return false;
        }

        if ($this->isPlaceBasedRoom((int) $bed->room_id)) {
            return $this->isBedAvailableByCapacity($bed, $check_in, $check_out, $exclude_reservation_id);
        }

        return $this->reservation_repository->isBedAvailable(
            $bed_id,
            $check_in,
            $check_out,
            $exclude_reservation_id
        );
    }
    
    /**
     * Get next available date for bed
     */
    public function getNextAvailableDate(int $bed_id, string $from_date, int $nights = 1): ?string {
        $current = new \DateTime($from_date);
        $max_days = 365; // Search up to 1 year ahead
        
        for ($i = 0; $i < $max_days; $i++) {
            $check_in = $current->format('Y-m-d');
            $check_out = (clone $current)->modify("+{$nights} days")->format('Y-m-d');
            
            if ($this->isBedAvailable($bed_id, $check_in, $check_out)) {
                return $check_in;
            }
            
            $current->modify('+1 day');
        }
        
        return null;
    }

    /**
     * In dormitory rooms, availability is place-based (capacity), not binary bed lock.
     */
    private function isBedAvailableByCapacity(
        Bed $bed,
        string $check_in,
        string $check_out,
        ?int $exclude_reservation_id = null
    ): bool {
        $capacity = $this->getBedCapacity($bed);
        if ($capacity <= 0) {
            return false;
        }

        $overlapping = $this->reservation_repository->findByBed((int) $bed->id);
        $occupied_places = 0;

        foreach ($overlapping as $reservation) {
            if (!$this->isActiveBlockingStatus((string) $reservation->status)) {
                continue;
            }
            if ($exclude_reservation_id && (int) $reservation->id === $exclude_reservation_id) {
                continue;
            }
            if (!$this->datesOverlap($reservation->check_in, $reservation->check_out, $check_in, $check_out)) {
                continue;
            }

            $occupied_places += $this->getOccupiedPlacesForReservationOnBed($reservation, (int) $bed->id);
            if ($occupied_places >= $capacity) {
                return false;
            }
        }

        return $occupied_places < $capacity;
    }

    /**
     * Estimate how many places of target bed are used by this reservation.
     * Guests are greedily assigned to selected beds by capacity (largest first).
     */
    private function getOccupiedPlacesForReservationOnBed(Reservation $reservation, int $target_bed_id): int {
        $guest_count = max(1, (int) $reservation->adults + (int) $reservation->children);
        $bed_ids = is_array($reservation->bed_ids) ? array_values(array_unique(array_map('intval', $reservation->bed_ids))) : [];
        if (empty($bed_ids)) {
            return 0;
        }

        $beds = [];
        foreach ($bed_ids as $bed_id) {
            $bed = $this->bed_repository->find($bed_id);
            if ($bed && $bed->is_active) {
                $beds[] = $bed;
            }
        }
        if (empty($beds)) {
            return 0;
        }

        usort($beds, function(Bed $a, Bed $b) {
            return $this->getBedCapacity($b) <=> $this->getBedCapacity($a);
        });

        $remaining = $guest_count;
        foreach ($beds as $bed) {
            $bed_capacity = $this->getBedCapacity($bed);
            if ($bed_capacity <= 0) {
                continue;
            }

            $assigned = min($remaining, $bed_capacity);
            if ((int) $bed->id === $target_bed_id) {
                return $assigned;
            }

            $remaining -= $assigned;
            if ($remaining <= 0) {
                break;
            }
        }

        return 0;
    }

    private function getBedCapacity(Bed $bed): int {
        switch ((string) $bed->bed_type) {
            case 'double':
            case 'bunk':
                return 2;
            case 'single':
            default:
                return 1;
        }
    }

    private function isPlaceBasedRoom(int $room_id): bool {
        global $wpdb;

        if (!isset($wpdb) || !is_object($wpdb)) {
            return false;
        }

        $rooms_table = Schema::get_table_name('rooms');
        $room_type = $wpdb->get_var($wpdb->prepare(
            "SELECT room_type FROM {$rooms_table} WHERE id = %d",
            $room_id
        ));

        return $room_type === 'dormitory';
    }

    private function datesOverlap(string $a_start, string $a_end, string $b_start, string $b_end): bool {
        return $a_start < $b_end && $a_end > $b_start;
    }

    private function isActiveBlockingStatus(string $status): bool {
        return in_array($status, [
            Reservation::STATUS_PENDING,
            Reservation::STATUS_CONFIRMED,
            Reservation::STATUS_CHECKED_IN,
        ], true);
    }
}
