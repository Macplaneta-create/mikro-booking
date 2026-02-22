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
    public ?string $description;
    public ?int $image_id;
    public array $amenities;
    public int $floor;
    public string $room_type;
    public string $pricing_mode;
    public string $status;
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
        $this->description = isset($data['description']) ? (string) $data['description'] : null;
        $this->image_id = isset($data['image_id']) ? (int) $data['image_id'] : null;
        
        if (isset($data['amenities'])) {
            $this->amenities = is_string($data['amenities']) ? json_decode($data['amenities'], true) : (array)$data['amenities'];
        } else {
            $this->amenities = [];
        }

        $this->floor = (int) ($data['floor'] ?? 0);
        $this->room_type = (string) ($data['room_type'] ?? 'standard');
        $this->pricing_mode = (string) ($data['pricing_mode'] ?? 'per_room');
        $this->status = (string) ($data['status'] ?? 'active');
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
            'description' => $this->description,
            'image_id' => $this->image_id,
            'image_url' => $this->image_id ? wp_get_attachment_url($this->image_id) : null,
            'amenities' => $this->amenities,
            'floor' => $this->floor,
            'room_type' => $this->room_type,
            'pricing_mode' => $this->pricing_mode,
            'status' => $this->status,
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
