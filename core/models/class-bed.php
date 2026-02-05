<?php
/**
 * Bed Model
 *
 * Plain Old PHP Object representing a bed entity
 *
 * @package MikroPlaneta\Booking
 * @since 1.0.0
 */

namespace MikroPlaneta\Booking\Core\Models;

if (!defined('ABSPATH')) {
    exit;
}

class Bed {
    
    public int $id;
    public int $room_id;
    public int $bed_number;
    public string $bed_type;
    public bool $is_active;
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
        $this->room_id = (int) ($data['room_id'] ?? 0);
        $this->bed_number = (int) ($data['bed_number'] ?? 0);
        $this->bed_type = (string) ($data['bed_type'] ?? 'single');
        $this->is_active = (bool) ($data['is_active'] ?? true);
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
            'room_id' => $this->room_id,
            'bed_number' => $this->bed_number,
            'bed_type' => $this->bed_type,
            'is_active' => $this->is_active,
            'created_at' => $this->created_at,
        ];
    }
    
    /**
     * Get display name
     */
    public function getDisplayName(): string {
        return sprintf('Bed #%d (%s)', $this->bed_number, ucfirst($this->bed_type));
    }
    
    /**
     * Check if bed is available
     */
    public function isAvailable(): bool {
        return $this->is_active;
    }
    
    /**
     * Activate bed
     */
    public function activate(): void {
        $this->is_active = true;
    }
    
    /**
     * Deactivate bed
     */
    public function deactivate(): void {
        $this->is_active = false;
    }
}
