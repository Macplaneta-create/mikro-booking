<?php
/**
 * Pricing Service
 *
 * Business logic for calculating prices
 *
 * @package MikroPlaneta\Booking
 * @since 1.0.0
 */

namespace MikroPlaneta\Booking\Core\Services;

use MikroPlaneta\Booking\Core\Repositories\PricingRepository;
use MikroPlaneta\Booking\Core\Repositories\BedRepository;
use MikroPlaneta\Booking\Core\Repositories\RoomRepository;

if (!defined('ABSPATH')) {
    exit;
}

class PricingService {
    
    private PricingRepository $pricing_repository;
    private BedRepository $bed_repository;
    private RoomRepository $room_repository;
    
    /**
     * Constructor
     */
    public function __construct(
        PricingRepository $pricing_repository,
        BedRepository $bed_repository,
        RoomRepository $room_repository
    ) {
        $this->pricing_repository = $pricing_repository;
        $this->bed_repository = $bed_repository;
        $this->room_repository = $room_repository;
    }
    
    /**
     * Calculate total price for a date range
     *
     * @param int $bed_id Bed ID
     * @param string $check_in Check-in date (YYYY-MM-DD)
     * @param string $check_out Check-out date (YYYY-MM-DD)
     * @return array Calculation result
     */
    public function calculateTotalPrice(int $bed_id, string $check_in, string $check_out): array {
        $bed = $this->bed_repository->find($bed_id);
        if (!$bed) {
            throw new \Exception('Bed not found');
        }

        $check_in_dt = new \DateTime($check_in);
        $check_out_dt = new \DateTime($check_out);
        
        if ($check_in_dt >= $check_out_dt) {
            throw new \Exception('Check-out date must be after check-in date');
        }

        $interval = new \DateInterval('P1D');
        $period = new \DatePeriod($check_in_dt, $interval, $check_out_dt);

        $total = 0.0;
        $details = [];
        $base_fallback = (float) get_option('mikroplaneta_booking_default_price', 100.00);

        foreach ($period as $date) {
            $date_str = $date->format('Y-m-d');
            $pricing = $this->pricing_repository->findForDate($bed->room_id, $date_str);
            
            $base_price = $pricing ? $pricing->base_price : $base_fallback;
            $weekend_price = $pricing ? $pricing->weekend_price : $base_price;

            // Definition of weekend: Friday and Saturday nights (standard in hotel industry)
            // N: 1 (Mon) to 7 (Sun)
            $day_of_week = (int) $date->format('N');
            $is_weekend = in_array($day_of_week, [5, 6]); 
            
            $price = $is_weekend ? $weekend_price : $base_price;
            
            // Apply bed type multiplier
            $multiplier_key = 'mikroplaneta_booking_multiplier_' . ($bed->bed_type ?: 'single');
            $multiplier = (float) get_option($multiplier_key, ($bed->bed_type === 'single' ? 1.0 : 2.0));
            
            $final_price = $price * $multiplier;
            $total += $final_price;
            
            $details[] = [
                'date' => $date_str,
                'price' => (float) $final_price,
                'base_price_at_date' => (float) $price,
                'multiplier' => $multiplier,
                'is_weekend' => $is_weekend
            ];
        }

        return [
            'total' => $total,
            'nights' => count($details),
            'details' => $details
        ];
    }

    /**
     * Calculate total price for multiple beds (potentially from different rooms)
     * Handles Room-based vs Bed-based pricing modes.
     */
    public function calculateGroupPrice(array $bed_ids, string $check_in, string $check_out): array {
        if (empty($bed_ids)) {
            return ['total' => 0.0, 'nights' => 0, 'details' => []];
        }

        // Group beds by room_id
        $beds = [];
        foreach ($bed_ids as $bid) {
            $bed = $this->bed_repository->find($bid);
            if ($bed) {
                $beds[$bed->room_id][] = $bed;
            }
        }

        $grand_total = 0.0;
        $all_details = [];
        $total_nights = 0;

        foreach ($beds as $room_id => $room_beds) {
            $room = $this->room_repository->find($room_id);
            if (!$room) continue;

            if ($room->pricing_mode === 'per_room') {
                // Calculate once for the room
                $room_price_data = $this->calculateRoomPrice($room_id, $check_in, $check_out);
                $grand_total += $room_price_data['total'];
                $total_nights = $room_price_data['nights'];
                $all_details = array_merge($all_details, $room_price_data['details']);
            } else {
                // Per-bed logic (sum each bed)
                foreach ($room_beds as $bed) {
                    $bed_price_data = $this->calculateTotalPrice($bed->id, $check_in, $check_out);
                    $grand_total += $bed_price_data['total'];
                    $total_nights = $bed_price_data['nights'];
                    $all_details = array_merge($all_details, $bed_price_data['details']);
                }
            }
        }

        return [
            'total' => $grand_total,
            'nights' => $total_nights,
            'details' => $all_details
        ];
    }

    /**
     * Internal helper to calculate base room price (ignoring beds)
     */
    private function calculateRoomPrice(int $room_id, string $check_in, string $check_out): array {
        $check_in_dt = new \DateTime($check_in);
        $check_out_dt = new \DateTime($check_out);
        $interval = new \DateInterval('P1D');
        $period = new \DatePeriod($check_in_dt, $interval, $check_out_dt);

        $total = 0.0;
        $details = [];
        $base_fallback = (float) get_option('mikroplaneta_booking_default_price', 100.00);

        foreach ($period as $date) {
            $date_str = $date->format('Y-m-d');
            $pricing = $this->pricing_repository->findForDate($room_id, $date_str);

            $base_price = $pricing ? $pricing->base_price : $base_fallback;
            $weekend_price = $pricing ? $pricing->weekend_price : $base_price;

            $day_of_week = (int) $date->format('N');
            $is_weekend = in_array($day_of_week, [5, 6]);

            $price = $is_weekend ? $weekend_price : $base_price;
            $total += $price;

            $details[] = [
                'date' => $date_str,
                'price' => (float) $price,
                'is_room_total' => true,
                'is_weekend' => $is_weekend
            ];
        }

        return [
            'total' => $total,
            'nights' => count($details),
            'details' => $details
        ];
    }
}
