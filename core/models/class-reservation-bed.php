<?php
/**
 * Reservation Bed Model
 *
 * Represents the many-to-many relationship between reservations and beds
 *
 * @package MikroPlaneta\Booking
 * @since 1.0.0
 */

namespace MikroPlaneta\Booking\Core\Models;

if (!defined('ABSPATH')) {
    exit;
}

class ReservationBed {
    
    public int $id;
    public int $reservation_id;
    public int $bed_id;
    public string $created_at;
    
    /**
     * Constructor
     */
    public function __construct(array $data = []) {
        if (!empty($data)) {
            $this->fill($data);
        }
    }
    
    /**
     * Fill model from array
     */
    public function fill(array $data): void {
        $this->id = (int) ($data['id'] ?? 0);
        $this->reservation_id = (int) ($data['reservation_id'] ?? 0);
        $this->bed_id = (int) ($data['bed_id'] ?? 0);
        $this->created_at = (string) ($data['created_at'] ?? '');
    }
    
    /**
     * Create instance from array
     */
    public static function fromArray(array $data): self {
        return new self($data);
    }
    
    /**
     * Convert to array
     */
    public function toArray(): array {
        return [
            'id' => $this->id,
            'reservation_id' => $this->reservation_id,
            'bed_id' => $this->bed_id,
            'created_at' => $this->created_at,
        ];
    }
}
