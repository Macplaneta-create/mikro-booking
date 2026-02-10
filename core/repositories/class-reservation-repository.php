<?php
/**
 * Reservation Repository
 *
 * Data access layer for reservations
 *
 * @package MikroPlaneta\Booking
 * @since 1.0.0
 */

namespace MikroPlaneta\Booking\Core\Repositories;

use MikroPlaneta\Booking\Core\Models\Reservation;
use MikroPlaneta\Booking\Core\Database\Schema;

if (!defined('ABSPATH')) {
    exit;
}

class ReservationRepository implements RepositoryInterface {
    
    private string $table;
    private string $guests_table;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->table = Schema::get_table_name('reservations');
        $this->guests_table = Schema::get_table_name('guests');
    }
    
    /**
     * Find reservation by ID
     */
    public function find(int $id): ?Reservation {
        global $wpdb;
        
        $sql = "SELECT r.*, g.first_name, g.last_name 
                FROM {$this->table} r 
                LEFT JOIN {$this->guests_table} g ON r.guest_id = g.id 
                WHERE r.id = %d";
        
        $row = $wpdb->get_row(
            $wpdb->prepare($sql, $id),
            ARRAY_A
        );
        
        if (!$row) {
            return null;
        }
        
        // Fetch bed IDs
        $reservation_beds_table = Schema::get_table_name('reservation_beds');
        $bed_ids = $wpdb->get_col($wpdb->prepare(
            "SELECT bed_id FROM {$reservation_beds_table} WHERE reservation_id = %d",
            $id
        ));
        
        $row['bed_ids'] = array_map('intval', $bed_ids);
        
        return Reservation::fromArray($row);
    }
    
    /**
     * Get all reservations
     * Returns a flattened list where each row represents a bed occupancy.
     * Group reservations will appear as multiple rows (one per bed).
     */
    public function all(array $args = []): array {
        global $wpdb;
        
        $reservation_beds_table = Schema::get_table_name('reservation_beds');
        
        // Base query - join with reservation_beds to get bed_id
        $sql = "SELECT r.*, rb.bed_id, g.first_name, g.last_name 
                FROM {$this->table} r 
                JOIN {$reservation_beds_table} rb ON r.id = rb.reservation_id
                LEFT JOIN {$this->guests_table} g ON r.guest_id = g.id 
                WHERE 1=1";
        
        $params = [];
        
        // Filter by bed
        if (!empty($args['bed_id'])) {
            $sql .= ' AND rb.bed_id = %d';
            $params[] = $args['bed_id'];
        }
        
        // Filter by guest
        if (!empty($args['guest_id'])) {
            $sql .= ' AND r.guest_id = %d';
            $params[] = $args['guest_id'];
        }
        
        // Filter by status
        if (!empty($args['status'])) {
            if (is_array($args['status'])) {
                $placeholders = implode(',', array_fill(0, count($args['status']), '%s'));
                $sql .= " AND r.status IN ({$placeholders})";
                $params = array_merge($params, $args['status']);
            } else {
                $sql .= ' AND r.status = %s';
                $params[] = $args['status'];
            }
        }
        
        // Filter by date range
        if (!empty($args['check_in_from'])) {
            $sql .= ' AND r.check_in >= %s';
            $params[] = $args['check_in_from'];
        }
        
        if (!empty($args['check_in_to'])) {
            $sql .= ' AND r.check_in <= %s';
            $params[] = $args['check_in_to'];
        }
        
        if (!empty($args['check_out_from'])) {
            $sql .= ' AND r.check_out >= %s';
            $params[] = $args['check_out_from'];
        }
        
        if (!empty($args['check_out_to'])) {
            $sql .= ' AND r.check_out <= %s';
            $params[] = $args['check_out_to'];
        }
        
        // Order by check-in date
        // Note: Simple ordering, we ignore complex args['order_by'] for now as this is mainly for calendar
        $sql .= ' ORDER BY r.check_in ASC';
        
        if (!empty($params)) {
            $rows = $wpdb->get_results($wpdb->prepare($sql, $params), ARRAY_A);
        } else {
            $rows = $wpdb->get_results($sql, ARRAY_A);
        }
        
        // Return Model objects
        return array_map(function($row) {
            $reservation = Reservation::fromArray($row);
            // We attach bed_id to the object dynamically for calendar usage
            $reservation->bed_id = (int) $row['bed_id'];
            return $reservation;
        }, $rows);
    }
    
    /**
     * Create new reservation
     */
    public function create(array $data): Reservation {
        global $wpdb;
        
        $insert_data = [
            'guest_id' => $data['guest_id'],
            'check_in' => $data['check_in'],
            'check_out' => $data['check_out'],
            'status' => $data['status'] ?? Reservation::STATUS_PENDING,
            'total_price' => $data['total_price'] ?? 0.0,
            'adults' => $data['adults'] ?? 1,
            'children' => $data['children'] ?? 0,
            'notes' => $data['notes'] ?? null,
            'created_by' => $data['created_by'] ?? get_current_user_id(),
        ];
        
        $wpdb->insert($this->table, $insert_data);
        
        $reservation = $this->find($wpdb->insert_id);
        
        if (!$reservation) {
            throw new \Exception('Failed to create reservation');
        }
        
        return $reservation;
    }
    
    /**
     * Update existing reservation
     */
    public function update(int $id, array $data): Reservation {
        global $wpdb;
        
        $update_data = [];
        $fields = ['check_in', 'check_out', 'status', 'total_price', 'adults', 'children', 'notes'];
        
        foreach ($fields as $field) {
            if (isset($data[$field])) {
                $update_data[$field] = $data[$field];
            }
        }
        
        if (isset($data['updated_at'])) {
            $update_data['updated_at'] = $data['updated_at'];
        }
        
        if (empty($update_data)) {
            throw new \Exception('No data to update');
        }
        
        $wpdb->update(
            $this->table,
            $update_data,
            ['id' => $id]
        );
        
        $reservation = $this->find($id);
        
        if (!$reservation) {
            throw new \Exception('Reservation not found after update');
        }
        
        return $reservation;
    }
    
    /**
     * Delete reservation
     */
    public function delete(int $id): bool {
        global $wpdb;
        
        $result = $wpdb->delete(
            $this->table,
            ['id' => $id],
            ['%d']
        );
        
        return $result !== false;
    }
    
    /**
     * Check if bed is available for given dates
     */
    public function isBedAvailable(int $bed_id, string $check_in, string $check_out, ?int $exclude_reservation_id = null): bool {
        global $wpdb;
        
        $reservation_beds_table = Schema::get_table_name('reservation_beds');
         
        // Join reservation_beds with reservations to check dates and status
        $sql = "SELECT COUNT(*) FROM {$this->table} r
                JOIN {$reservation_beds_table} rb ON r.id = rb.reservation_id
                WHERE rb.bed_id = %d
                AND r.status IN (%s, %s, %s)
                AND r.check_in < %s 
                AND r.check_out > %s";
                 
        $params = [
            $bed_id,
            Reservation::STATUS_CONFIRMED,
            Reservation::STATUS_CHECKED_IN,
            Reservation::STATUS_PENDING,
            $check_out,
            $check_in,
        ];
        
        if ($exclude_reservation_id) {
            $sql .= ' AND r.id != %d';
            $params[] = $exclude_reservation_id;
        }
        
        $count = $wpdb->get_var($wpdb->prepare($sql, $params));
        
        return $count == 0;
    }

    /**
     * Find reservations by bed
     */
    public function findByBed(int $bed_id): array {
        return $this->all(['bed_id' => $bed_id]);
    }

    /**
     * Find reservations by guest
     */
    public function findByGuest(int $guest_id): array {
        return $this->all(['guest_id' => $guest_id]);
    }
}
