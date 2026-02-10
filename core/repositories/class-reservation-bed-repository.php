<?php
/**
 * Reservation Bed Repository
 *
 * Data access layer for reservation-bed relationships
 *
 * @package MikroPlaneta\Booking
 * @since 1.0.0
 */

namespace MikroPlaneta\Booking\Core\Repositories;

use MikroPlaneta\Booking\Core\Models\ReservationBed;
use MikroPlaneta\Booking\Core\Database\Schema;

if (!defined('ABSPATH')) {
    exit;
}

class ReservationBedRepository {
    
    private string $table;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->table = Schema::get_table_name('reservation_beds');
    }
    
    /**
     * Get beds for a reservation
     */
    public function getBedsForReservation(int $reservation_id): array {
        global $wpdb;
        
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$this->table} WHERE reservation_id = %d",
                $reservation_id
            ),
            ARRAY_A
        );
        
        return array_map(function($row) {
            return ReservationBed::fromArray($row);
        }, $rows);
    }
    
    /**
     * Get bed IDs for a reservation
     */
    public function getBedIdsForReservation(int $reservation_id): array {
        global $wpdb;
        
        $results = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT bed_id FROM {$this->table} WHERE reservation_id = %d",
                $reservation_id
            )
        );
        
        return array_map('intval', $results);
    }
    
    /**
     * Add bed to reservation
     */
    public function addBedToReservation(int $reservation_id, int $bed_id): ReservationBed {
        global $wpdb;
        
        $wpdb->insert($this->table, [
            'reservation_id' => $reservation_id,
            'bed_id' => $bed_id,
        ]);
        
        $id = $wpdb->insert_id;
        
        $row = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$this->table} WHERE id = %d", $id),
            ARRAY_A
        );
        
        return ReservationBed::fromArray($row);
    }
    
    /**
     * Remove bed from reservation
     */
    public function removeBedFromReservation(int $reservation_id, int $bed_id): bool {
        global $wpdb;
        
        $result = $wpdb->delete(
            $this->table,
            [
                'reservation_id' => $reservation_id,
                'bed_id' => $bed_id,
            ],
            ['%d', '%d']
        );
        
        return $result !== false;
    }
    
    /**
     * Set beds for reservation (replaces all existing)
     */
    public function setBedsForReservation(int $reservation_id, array $bed_ids): void {
        global $wpdb;
        
        // Remove all existing beds
        $wpdb->delete(
            $this->table,
            ['reservation_id' => $reservation_id],
            ['%d']
        );
        
        // Add new beds
        foreach ($bed_ids as $bed_id) {
            $this->addBedToReservation($reservation_id, $bed_id);
        }
    }
    
    /**
     * Delete all beds for reservation
     */
    public function deleteBedsForReservation(int $reservation_id): bool {
        global $wpdb;
        
        $result = $wpdb->delete(
            $this->table,
            ['reservation_id' => $reservation_id],
            ['%d']
        );
        
        return $result !== false;
    }
}
