<?php
/**
 * BedPlace Model
 *
 * Plain Old PHP Object representing a bed place entity
 * A place is a sleeping spot within a bed (e.g., bunk bed has 2 places: bottom and top)
 *
 * @package MikroPlaneta\Booking
 * @since 1.2.8
 */

namespace MikroPlaneta\Booking\Core\Models;

if (!defined('ABSPATH')) {
    exit;
}

class BedPlace {

    public int $id;
    public int $bed_id;
    public int $place_number;
    public string $place_label;
    public int $max_persons; // usually 1; kept flexible for custom place capacities
    public bool $is_active;
    public string $created_at;

    // Joined bed data
    public ?int $room_id = null;
    public ?string $bed_type = null;
    public ?int $bed_number = null;

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
        $this->bed_id = (int) ($data['bed_id'] ?? 0);
        $this->place_number = (int) ($data['place_number'] ?? 1);
        $this->place_label = (string) ($data['place_label'] ?? '');
        $this->max_persons = (int) ($data['max_persons'] ?? 1);
        $this->is_active = (bool) ($data['is_active'] ?? true);
        $this->created_at = (string) ($data['created_at'] ?? '');

        // Joined bed data
        $this->room_id = isset($data['room_id']) ? (int) $data['room_id'] : null;
        $this->bed_type = $data['bed_type'] ?? null;
        $this->bed_number = isset($data['bed_number']) ? (int) $data['bed_number'] : null;
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
            'bed_id' => $this->bed_id,
            'place_number' => $this->place_number,
            'place_label' => $this->place_label,
            'max_persons' => $this->max_persons,
            'is_active' => $this->is_active,
            'created_at' => $this->created_at,
            'room_id' => $this->room_id,
            'bed_type' => $this->bed_type,
            'bed_number' => $this->bed_number,
        ];
    }

    /**
     * Get full label (e.g., "Pokój 101 - Łóżko #1 - Góra")
     */
    public function getFullLabel(): string {
        $parts = [];
        
        if ($this->room_id) {
            $parts[] = "Pokój #{$this->room_id}";
        }
        
        if ($this->bed_number) {
            $parts[] = "Łóżko #{$this->bed_number}";
        }
        
        if ($this->place_label) {
            $parts[] = $this->place_label;
        }
        
        return implode(' - ', $parts);
    }

    /**
     * Check if place is for couples (double bed)
     */
    public function isForCouples(): bool {
        return $this->max_persons >= 2;
    }

    /**
     * Check if place is in bunk bed
     */
    public function isBunkPlace(): bool {
        return $this->bed_type === 'bunk';
    }
}
