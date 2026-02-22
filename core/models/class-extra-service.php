<?php
/**
 * Extra Service Model
 *
 * @package MikroPlaneta\Booking
 * @since 1.1.2
 */

namespace MikroPlaneta\Booking\Core\Models;

if (!defined('ABSPATH')) {
    exit;
}

class ExtraService {
    public ?int $id = null;
    public string $name;
    public ?string $description;
    public float $price;
    public string $pricing_type; // 'per_stay' | 'per_unit' | 'per_person'
    public bool $auto_suggest_by_beds;
    public bool $is_active;
    public int $sort_order;
    public string $created_at;

    public function __construct(array $data = []) {
        $this->id = isset($data['id']) ? (int)$data['id'] : null;
        $this->name = $data['name'] ?? '';
        $this->description = $data['description'] ?? null;
        $this->price = isset($data['price']) ? (float)$data['price'] : 0.00;
        $this->pricing_type = $data['pricing_type'] ?? 'per_stay';
        $this->auto_suggest_by_beds = !empty($data['auto_suggest_by_beds']);
        $this->is_active = !isset($data['is_active']) || !empty($data['is_active']);
        $this->sort_order = isset($data['sort_order']) ? (int)$data['sort_order'] : 0;
        $this->created_at = $data['created_at'] ?? date('Y-m-d H:i:s');
    }

    public function toArray(): array {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'price' => $this->price,
            'pricing_type' => $this->pricing_type,
            'auto_suggest_by_beds' => $this->auto_suggest_by_beds,
            'is_active' => $this->is_active,
            'sort_order' => $this->sort_order,
            'created_at' => $this->created_at
        ];
    }
}
