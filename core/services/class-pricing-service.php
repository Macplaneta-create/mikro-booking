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

if (!defined('ABSPATH')) {
    exit;
}

class PricingService {
    
    private PricingRepository $pricing_repository;
    private BedRepository $bed_repository;
    
    /**
     * Constructor
     */
    public function __construct(
        PricingRepository $pricing_repository,
        BedRepository $bed_repository
    ) {
        $this->pricing_repository = $pricing_repository;
        $this->bed_repository = $bed_repository;
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
            $total += $price;
            
            $details[] = [
                'date' => $date_str,
                'price' => (float) $price,
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
