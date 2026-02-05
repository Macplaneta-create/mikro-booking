<?php
/**
 * Guest Model
 *
 * Plain Old PHP Object representing a guest entity
 *
 * @package MikroPlaneta\Booking
 * @since 1.0.0
 */

namespace MikroPlaneta\Booking\Core\Models;

if (!defined('ABSPATH')) {
    exit;
}

class Guest {
    
    public int $id;
    public string $first_name;
    public string $last_name;
    public string $email;
    public ?string $phone;
    public array $preferences;
    public int $total_stays;
    public ?string $last_stay_date;
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
        $this->first_name = (string) ($data['first_name'] ?? '');
        $this->last_name = (string) ($data['last_name'] ?? '');
        $this->email = (string) ($data['email'] ?? '');
        $this->phone = $data['phone'] ?? null;
        
        // Decode JSON preferences
        if (isset($data['preferences'])) {
            $this->preferences = is_string($data['preferences']) 
                ? json_decode($data['preferences'], true) ?? []
                : (array) $data['preferences'];
        } else {
            $this->preferences = [];
        }
        
        $this->total_stays = (int) ($data['total_stays'] ?? 0);
        $this->last_stay_date = $data['last_stay_date'] ?? null;
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
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'email' => $this->email,
            'phone' => $this->phone,
            'preferences' => $this->preferences,
            'total_stays' => $this->total_stays,
            'last_stay_date' => $this->last_stay_date,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
    
    /**
     * Get full name
     */
    public function getFullName(): string {
        return trim($this->first_name . ' ' . $this->last_name);
    }
    
    /**
     * Check if guest is returning
     */
    public function isReturning(): bool {
        return $this->total_stays > 0;
    }
    
    /**
     * Get preference value
     */
    public function getPreference(string $key, $default = null) {
        return $this->preferences[$key] ?? $default;
    }
    
    /**
     * Set preference value
     */
    public function setPreference(string $key, $value): void {
        $this->preferences[$key] = $value;
    }
    
    /**
     * Increment stay counter
     */
    public function incrementStays(): void {
        $this->total_stays++;
        $this->last_stay_date = current_time('mysql');
    }
}
