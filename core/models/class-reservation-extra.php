<?php
/**
 * Reservation Extra Model
 *
 * @package MikroPlaneta\Booking
 * @since 1.1.2
 */

namespace MikroPlaneta\Booking\Core\Models;

if (!defined('ABSPATH')) {
    exit;
}

class ReservationExtra {
    public ?int $id = null;
    public int $reservation_id;
    public int $service_id;
    public int $quantity;
    public float $unit_price;
    public float $total_price;
    public string $created_at;

    // Join data (optional)
    public ?string $service_name = null;

    public function __construct(array $data = []) {
        $this->id = isset($data['id']) ? (int)$data['id'] : null;
        $this->reservation_id = (int)($data['reservation_id'] ?? 0);
        $this->service_id = (int)($data['service_id'] ?? 0);
        $this->quantity = (int)($data['quantity'] ?? 1);
        $this->unit_price = (float)($data['unit_price'] ?? 0);
        $this->total_price = (float)($data['total_price'] ?? ($this->unit_price * $this->quantity));
        $this->created_at = $data['created_at'] ?? date('Y-m-d H:i:s');
        
        $this->service_name = $data['service_name'] ?? null;
    }

    public function toArray(): array {
        return [
            'id' => $this->id,
            'reservation_id' => $this->reservation_id,
            'service_id' => $this->service_id,
            'quantity' => $this->quantity,
            'unit_price' => $this->unit_price,
            'total_price' => $this->total_price,
            'created_at' => $this->created_at,
            'service_name' => $this->service_name
        ];
    }
}
