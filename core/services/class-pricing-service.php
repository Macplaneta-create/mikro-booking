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

        $room = $this->room_repository->find((int) $bed->room_id);
        if (!$room) {
            throw new \Exception('Room not found for selected bed');
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
            $pricing = $this->pricing_repository->findForDate(
                (int) $bed->room_id,
                (string) $room->room_type,
                (string) $room->pricing_mode,
                $date_str
            );
            
            $base_price = $pricing ? $pricing->base_price : $base_fallback;
            $weekend_price = $pricing ? $pricing->weekend_price : $base_price;

            // Definition of weekend: Friday and Saturday nights (standard in hotel industry)
            // N: 1 (Mon) to 7 (Sun)
            $day_of_week = (int) $date->format('N');
            $is_weekend = in_array($day_of_week, [5, 6]); 
            
            $price = $is_weekend ? $weekend_price : $base_price;
            
            // Apply bed type multiplier
            $bed_type = $bed->bed_type ?: 'single';
            $multiplier_key = 'mikroplaneta_booking_multiplier_' . $bed_type;
            $multiplier = (float) get_option($multiplier_key, ($bed_type === 'single' ? 1.0 : 2.0));
            
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
    public function calculateGroupPrice(array $bed_ids, string $check_in, string $check_out, int $adults = 0, int $children = 0, int $room_id = 0): array {
        $total_guests = max(1, $adults + $children);

        // Fallback: If no beds are selected but we have a room_id, suggest "placeholder" beds for calculation
        if (empty($bed_ids) && $room_id > 0) {
            $room_beds = $this->bed_repository->findByRoom($room_id);
            if (!empty($room_beds)) {
                // Take up to $total_guests beds
                $count = 0;
                foreach ($room_beds as $rb) {
                    if ($count < $total_guests) {
                        $bed_ids[] = (int)$rb->id;
                        $count++;
                    }
                }
            }
        }

        if (empty($bed_ids)) {
            // Still no beds? At least calculate nights correctly
            try {
                $dt1 = new \DateTime($check_in);
                $dt2 = new \DateTime($check_out);
                $nights = (int)$dt1->diff($dt2)->days;
                return ['total' => 0.0, 'nights' => $nights, 'details' => []];
            } catch (\Exception $e) {
                return ['total' => 0.0, 'nights' => 0, 'details' => []];
            }
        }

        // Group beds by room_id
        $beds_objects = [];
        foreach ($bed_ids as $bid) {
            $bed = $this->bed_repository->find((int)$bid);
            if ($bed) {
                $beds_objects[] = $bed;
            }
        }

        $grand_total = 0.0;
        $all_details = [];
        
        // Calculate nights count from dates for consistency
        try {
            $dt1 = new \DateTime($check_in);
            $dt2 = new \DateTime($check_out);
            $total_nights = (int)$dt1->diff($dt2)->days;
        } catch (\Exception $e) {
            $total_nights = 0;
        }

        // Group by rooms for pricing mode check
        $rooms_beds = [];
        foreach ($beds_objects as $bed) {
            $rooms_beds[$bed->room_id][] = $bed;
        }

        // Calculate individual bed prices first
        $individual_prices = [];
        foreach ($beds_objects as $bed) {
            $room = $this->room_repository->find($bed->room_id);
            if ($room && $room->pricing_mode === 'per_room') {
                // If it's per room, we handle it later
                continue;
            }
            $price_data = $this->calculateTotalPrice($bed->id, $check_in, $check_out);
            $individual_prices[] = [
                'bed' => $bed,
                'total' => $price_data['total'],
                'details' => $price_data['details'],
                'nights' => $price_data['nights']
            ];
        }

        // Handle Per-Room pricing first
        foreach ($rooms_beds as $rid => $r_beds) {
            $room = $this->room_repository->find($rid);
            if ($room && $room->pricing_mode === 'per_room') {
                $room_price_data = $this->calculateRoomPrice($rid, $check_in, $check_out);
                $grand_total += $room_price_data['total'];
                $all_details = array_merge($all_details, $room_price_data['details']);
            }
        }

        // Handle Per-Bed pricing with child discount
        if (!empty($individual_prices)) {
            $child_multiplier = (float) get_option('mikroplaneta_booking_multiplier_children', 0.5);
            
            // Sort by price descending so children take the cheapest beds
            usort($individual_prices, fn($a, $b) => $a['total'] <=> $b['total']);
            
            foreach ($individual_prices as $index => $item) {
                $is_child = $index < $children;
                $multiplier = $is_child ? $child_multiplier : 1.0;
                
                $final_item_price = $item['total'] * $multiplier;
                $grand_total += $final_item_price;

                // Update details with child info if applicable
                $details = $item['details'];
                if ($is_child) {
                    foreach ($details as &$d) {
                        $d['price'] *= $child_multiplier;
                        $d['is_child_rate'] = true;
                    }
                }
                $all_details = array_merge($all_details, $details);
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
        $room = $this->room_repository->find($room_id);
        if (!$room) {
            throw new \Exception('Room not found');
        }

        $check_in_dt = new \DateTime($check_in);
        $check_out_dt = new \DateTime($check_out);
        $interval = new \DateInterval('P1D');
        $period = new \DatePeriod($check_in_dt, $interval, $check_out_dt);

        $total = 0.0;
        $details = [];
        $base_fallback = (float) get_option('mikroplaneta_booking_default_price', 100.00);

        foreach ($period as $date) {
            $date_str = $date->format('Y-m-d');
            $pricing = $this->pricing_repository->findForDate(
                $room_id,
                (string) $room->room_type,
                (string) $room->pricing_mode,
                $date_str
            );

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
