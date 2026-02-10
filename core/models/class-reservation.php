<?php
/**
 * Reservation Model
 *
 * Plain Old PHP Object representing a reservation entity
 *
 * @package MikroPlaneta\Booking
 * @since 1.0.0
 */

namespace MikroPlaneta\Booking\Core\Models;

if (!defined('ABSPATH')) {
    exit;
}

class Reservation {
    
    public int $id;
    public array $bed_ids = [];
    public int $guest_id;
    public string $check_in;
    public string $check_out;
    public string $status;
    public float $total_price;
    public int $adults;
    public int $children;
    public ?string $notes;
    public ?int $created_by;
    public string $created_at;
    public string $updated_at;
    
    // Add joined guest fields
    public ?string $first_name = null;
    public ?string $last_name = null;
    
    /**
     * Valid statuses
     */
    public const STATUS_PENDING = 'pending';
    public const STATUS_CONFIRMED = 'confirmed';
    public const STATUS_CHECKED_IN = 'checked_in';
    public const STATUS_CHECKED_OUT = 'checked_out';
    public const STATUS_CANCELLED = 'cancelled';
    
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
        $this->guest_id = (int) ($data['guest_id'] ?? 0);
        $this->bed_ids = isset($data['bed_ids']) && is_array($data['bed_ids']) ? $data['bed_ids'] : [];
        $this->check_in = (string) ($data['check_in'] ?? '');
        $this->check_out = (string) ($data['check_out'] ?? '');
        $this->status = (string) ($data['status'] ?? self::STATUS_PENDING);
        $this->total_price = (float) ($data['total_price'] ?? 0.0);
        $this->adults = (int) ($data['adults'] ?? 1);
        $this->children = (int) ($data['children'] ?? 0);
        $this->notes = $data['notes'] ?? null;
        $this->created_by = isset($data['created_by']) ? (int) $data['created_by'] : null;
        $this->created_at = (string) ($data['created_at'] ?? '');
        $this->updated_at = (string) ($data['updated_at'] ?? '');
        
        // Joined guest fields
        $this->first_name = $data['first_name'] ?? null;
        $this->last_name = $data['last_name'] ?? null;
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
            'bed_ids' => $this->bed_ids,
            'guest_id' => $this->guest_id,
            'check_in' => $this->check_in,
            'check_out' => $this->check_out,
            'status' => $this->status,
            'total_price' => $this->total_price,
            'adults' => $this->adults,
            'children' => $this->children,
            'notes' => $this->notes,
            'created_by' => $this->created_by,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
        ];
    }
    
    /**
     * Get number of nights
     */
    public function getNights(): int {
        $check_in = new \DateTime($this->check_in);
        $check_out = new \DateTime($this->check_out);
        return $check_in->diff($check_out)->days;
    }
    
    /**
     * Check if reservation is active
     */
    public function isActive(): bool {
        return in_array($this->status, [
            self::STATUS_CONFIRMED,
            self::STATUS_CHECKED_IN,
        ], true);
    }
    
    /**
     * Check if reservation is cancelled
     */
    public function isCancelled(): bool {
        return $this->status === self::STATUS_CANCELLED;
    }
    
    /**
     * Check if reservation is in the past
     */
    public function isPast(): bool {
        return strtotime($this->check_out) < time();
    }
    
    /**
     * Check if reservation is current
     */
    public function isCurrent(): bool {
        $now = time();
        return strtotime($this->check_in) <= $now && strtotime($this->check_out) >= $now;
    }
    
    /**
     * Check if reservation is future
     */
    public function isFuture(): bool {
        return strtotime($this->check_in) > time();
    }
    
    /**
     * Update status
     */
    public function updateStatus(string $status): void {
        $valid_statuses = [
            self::STATUS_PENDING,
            self::STATUS_CONFIRMED,
            self::STATUS_CHECKED_IN,
            self::STATUS_CHECKED_OUT,
            self::STATUS_CANCELLED,
        ];
        
        if (in_array($status, $valid_statuses, true)) {
            $this->status = $status;
        }
    }
    
    /**
     * Confirm reservation
     */
    public function confirm(): void {
        $this->status = self::STATUS_CONFIRMED;
    }
    
    /**
     * Check in
     */
    public function checkIn(): void {
        $this->status = self::STATUS_CHECKED_IN;
    }
    
    /**
     * Check out
     */
    public function checkOut(): void {
        $this->status = self::STATUS_CHECKED_OUT;
    }
    
    /**
     * Cancel reservation
     */
    public function cancel(): void {
        $this->status = self::STATUS_CANCELLED;
    }
}
