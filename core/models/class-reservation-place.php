<?php
/**
 * Reservation Place Model
 *
 * @package MikroPlaneta\Booking
 * @since 1.2.8
 */

namespace MikroPlaneta\Booking\Core\Models;

if (!defined('ABSPATH')) {
    exit;
}

class ReservationPlace {

    public int $id;
    public int $reservation_id;
    public int $place_id;
    public string $created_at;

    public function __construct(array $data = []) {
        if (!empty($data)) {
            $this->fill($data);
        }
    }

    public function fill(array $data): void {
        $this->id = (int) ($data['id'] ?? 0);
        $this->reservation_id = (int) ($data['reservation_id'] ?? 0);
        $this->place_id = (int) ($data['place_id'] ?? 0);
        $this->created_at = (string) ($data['created_at'] ?? '');
    }

    public static function fromArray(array $data): self {
        return new self($data);
    }

    public function toArray(): array {
        return [
            'id' => $this->id,
            'reservation_id' => $this->reservation_id,
            'place_id' => $this->place_id,
            'created_at' => $this->created_at,
        ];
    }
}