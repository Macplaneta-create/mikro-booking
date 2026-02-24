<?php
/**
 * Pricing Model
 *
 * @package MikroPlaneta\Booking
 * @since 1.0.0
 */

namespace MikroPlaneta\Booking\Core\Models;

if (!defined('ABSPATH')) {
    exit;
}

class Pricing {
    
    public ?int $id;
    public ?string $name;
    public ?int $room_id;
    public string $scope_type;
    public ?string $room_type;
    public ?string $pricing_mode;
    public int $priority;
    public string $start_date;
    public string $end_date;
    public float $base_price;
    public float $weekend_price;
    public int $weekend_from_day;
    public int $weekend_to_day;
    public string $created_at;
    public string $updated_at;
    
    /**
     * Constructor
     */
    public function __construct(array $data = []) {
        $this->id = isset($data['id']) ? (int) $data['id'] : null;
        $this->name = isset($data['name']) && $data['name'] !== '' ? (string) $data['name'] : null;
        $this->room_id = isset($data['room_id']) ? (int) $data['room_id'] : null;
        $this->scope_type = (string) ($data['scope_type'] ?? 'room_id');
        $this->room_type = isset($data['room_type']) && $data['room_type'] !== '' ? (string) $data['room_type'] : null;
        $this->pricing_mode = isset($data['pricing_mode']) && $data['pricing_mode'] !== '' ? (string) $data['pricing_mode'] : null;
        $this->priority = (int) ($data['priority'] ?? 100);
        $this->start_date = $data['start_date'] ?? '';
        $this->end_date = $data['end_date'] ?? '';
        $this->base_price = (float) ($data['base_price'] ?? 0.0);
        $this->weekend_price = (float) ($data['weekend_price'] ?? 0.0);
        $this->weekend_from_day = (int) ($data['weekend_from_day'] ?? 5);
        $this->weekend_to_day = (int) ($data['weekend_to_day'] ?? 7);
        $this->created_at = $data['created_at'] ?? '';
        $this->updated_at = $data['updated_at'] ?? '';
    }
    
    /**
     * Create from array
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
            'name' => $this->name,
            'room_id' => $this->room_id,
            'scope_type' => $this->scope_type,
            'room_type' => $this->room_type,
            'pricing_mode' => $this->pricing_mode,
            'priority' => $this->priority,
            'start_date' => $this->start_date,
            'end_date' => $this->end_date,
            'base_price' => $this->base_price,
            'weekend_price' => $this->weekend_price,
            'weekend_from_day' => $this->weekend_from_day,
            'weekend_to_day' => $this->weekend_to_day,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
