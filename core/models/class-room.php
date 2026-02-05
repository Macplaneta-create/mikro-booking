<?php
/**
 * Room Model
 *
 * Plain Old PHP Object representing a room entity
 *
 * @package MikroPlaneta\Booking
 * @since 1.0.0
 */

namespace MikroPlaneta\Booking\Core\Models;

if (!defined('ABSPATH')) {
    exit;
}

class Room {
    
    public int $id;
    public string $name;
    public int $floor;
    public string $room_type;
    public string $created_at;
    public string $updated_at;
    
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
        $this->name = (string) ($data['name'] ?? '');
        $this->floor = (int) ($data['floor'] ?? 0);
        $this->room_type = (string) ($data['room_type'] ?? 'standard');
        $this->created_at = (string) ($data['created_at'] ?? '');
        $this->updated_at = (string) ($data['updated_at'] ?? '');
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
            'name' => $this->name,
            'floor' => $this->floor,
            'room_type' => $this->room_type,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
    
    /**
     * Get display name
     */
    public function getDisplayName(): string {
        return sprintf('%s (Floor %d)', $this->name, $this->floor);
    }
    
    /**
     * Check if room is of specific type
     */
    public function isType(string $type): bool {
        return $this->room_type === $type;
    }
}
