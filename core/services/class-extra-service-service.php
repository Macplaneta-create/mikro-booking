<?php
/**
 * Extra Service Service
 *
 * @package MikroPlaneta\Booking
 * @since 1.1.2
 */

namespace MikroPlaneta\Booking\Core\Services;

use MikroPlaneta\Booking\Core\Repositories\ExtraServiceRepository;
use MikroPlaneta\Booking\Core\Repositories\ReservationExtraRepository;
use MikroPlaneta\Booking\Core\Repositories\ReservationRepository;
use MikroPlaneta\Booking\Core\Models\ReservationExtra;

if (!defined('ABSPATH')) {
    exit;
}

class ExtraServiceService {
    
    private ExtraServiceRepository $service_repo;
    private ReservationExtraRepository $reservation_extra_repo;
    private ReservationRepository $reservation_repo;

    public function __construct(
        ExtraServiceRepository $service_repo,
        ReservationExtraRepository $reservation_extra_repo,
        ReservationRepository $reservation_repo
    ) {
        $this->service_repo = $service_repo;
        $this->reservation_extra_repo = $reservation_extra_repo;
        $this->reservation_repo = $reservation_repo;
    }

    /**
     * Set extras for a reservation
     * Expects array of ['service_id' => X, 'quantity' => Y]
     */
    public function setExtrasForReservation(int $reservation_id, array $extras): array {
        $reservation = $this->reservation_repo->find($reservation_id);
        if (!$reservation) {
            throw new \Exception("Reservation not found");
        }

        // First, clear existing extras to avoid duplicates/messy state
        $this->reservation_extra_repo->deleteByReservation($reservation_id);

        $results = [];
        $extra_total_price = 0;

        foreach ($extras as $extra_data) {
            $service = $this->service_repo->find($extra_data['service_id']);
            if (!$service || !$service->is_active) {
                continue;
            }

            $quantity = max(1, (int)($extra_data['quantity'] ?? 1));
            $unit_price = $service->price;
            $total_price = $unit_price * $quantity;

            $reservation_extra = $this->reservation_extra_repo->create([
                'reservation_id' => $reservation_id,
                'service_id' => $service->id,
                'quantity' => $quantity,
                'unit_price' => $unit_price,
                'total_price' => $total_price
            ]);

            $results[] = $reservation_extra;
            $extra_total_price += $total_price;
        }

        // We don't update the reservation's total_price here anymore.
        // The total_price in the `reservations` table should probably be the SUM of (nights price + extras).
        // Let's check how PricingService handle things.
        
        return $results;
    }

    /**
     * Get extras for reservation
     */
    public function getExtrasForReservation(int $reservation_id): array {
        return $this->reservation_extra_repo->all(['reservation_id' => $reservation_id]);
    }

    /**
     * Calculate total price of extras for a reservation
     */
    public function calculateExtrasTotal(int $reservation_id): float {
        $extras = $this->getExtrasForReservation($reservation_id);
        return array_reduce($extras, fn($carry, $item) => $carry + $item->total_price, 0.0);
    }
}
