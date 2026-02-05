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
        
        if (count($available_beds) < $group_size) {
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
            if (count($beds) >= $group_size) {
                $combinations[] = [
                    'type' => 'single_room',
                    'room_id' => $room_id,
                    'beds' => array_slice($beds, 0, $group_size),
                    'score' => 100, // Highest score for single room
                ];
            }
        }
        
        // If no single room fits, try multiple rooms
        if (empty($combinations)) {
            // Simple algorithm: take beds from multiple rooms
            $selected_beds = array_slice($available_beds, 0, $group_size);
            if (count($selected_beds) === $group_size) {
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
}
