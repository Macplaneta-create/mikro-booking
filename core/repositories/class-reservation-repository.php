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
        
        return $row ? Reservation::fromArray($row) : null;
    }
    
    /**
     * Get all reservations
     */
    public function all(array $args = []): array {
        global $wpdb;
        
        $where = '1=1';
        $params = [];
        
        // Filter by bed
        if (!empty($args['bed_id'])) {
            $where .= ' AND r.bed_id = %d';
            $params[] = $args['bed_id'];
        }
        
        // Filter by guest
        if (!empty($args['guest_id'])) {
            $where .= ' AND r.guest_id = %d';
            $params[] = $args['guest_id'];
        }
        
        // Filter by status
        if (!empty($args['status'])) {
            if (is_array($args['status'])) {
                $placeholders = implode(',', array_fill(0, count($args['status']), '%s'));
                $where .= " AND r.status IN ({$placeholders})";
                $params = array_merge($params, $args['status']);
            } else {
                $where .= ' AND r.status = %s';
                $params[] = $args['status'];
            }
        }
        
        // Filter by date range
        if (!empty($args['check_in_from'])) {
            $where .= ' AND r.check_in >= %s';
            $params[] = $args['check_in_from'];
        }
        
        if (!empty($args['check_in_to'])) {
            $where .= ' AND r.check_in <= %s';
            $params[] = $args['check_in_to'];
        }
        
        if (!empty($args['check_out_from'])) {
            $where .= ' AND r.check_out >= %s';
            $params[] = $args['check_out_from'];
        }
        
        if (!empty($args['check_out_to'])) {
            $where .= ' AND r.check_out <= %s';
            $params[] = $args['check_out_to'];
        }
        
        // Order by
        $order_field = $args['order_by'] ?? 'check_in';
        $order_by = "r.{$order_field}";
        $order = $args['order'] ?? 'DESC';
        
        // Limit
        $limit = isset($args['limit']) ? (int) $args['limit'] : null;
        $offset = isset($args['offset']) ? (int) $args['offset'] : 0;
        
        $sql = "SELECT r.*, g.first_name, g.last_name 
                FROM {$this->table} r 
                LEFT JOIN {$this->guests_table} g ON r.guest_id = g.id 
                WHERE {$where} ORDER BY {$order_by} {$order}";
        
        if ($limit) {
            $sql .= " LIMIT {$offset}, {$limit}";
        }
        
        if (!empty($params)) {
            $sql = $wpdb->prepare($sql, ...$params);
        }
        
        $rows = $wpdb->get_results($sql, ARRAY_A);
        
        return array_map(function($row) {
            return Reservation::fromArray($row);
        }, $rows);
    }
    
    /**
     * Create new reservation
     */
    public function create(array $data): Reservation {
        global $wpdb;
        
        $insert_data = [
            'bed_id' => $data['bed_id'],
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
        $fields = ['bed_id', 'check_in', 'check_out', 'status', 'total_price', 'adults', 'children', 'notes'];
        
        foreach ($fields as $field) {
            if (isset($data[$field])) {
                $update_data[$field] = $data[$field];
            }
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
     * Find reservations by guest
     */
    public function findByGuest(int $guest_id): array {
        return $this->all(['guest_id' => $guest_id]);
    }
    
    /**
     * Find reservations by bed
     */
    public function findByBed(int $bed_id): array {
        return $this->all(['bed_id' => $bed_id]);
    }
    
    /**
     * Find active reservations
     */
    public function findActive(): array {
        return $this->all([
            'status' => [
                Reservation::STATUS_CONFIRMED,
                Reservation::STATUS_CHECKED_IN,
            ],
        ]);
    }
    
    /**
     * Find upcoming reservations
     */
    public function findUpcoming(int $days = 7): array {
        $today = current_time('Y-m-d');
        $future = date('Y-m-d', strtotime("+{$days} days"));
        
        return $this->all([
            'check_in_from' => $today,
            'check_in_to' => $future,
            'status' => Reservation::STATUS_CONFIRMED,
        ]);
    }
    
    /**
     * Check if bed is available for dates
     */
    public function isBedAvailable(int $bed_id, string $check_in, string $check_out, ?int $exclude_reservation_id = null): bool {
        global $wpdb;
        
        $where = 'bed_id = %d AND status IN (%s, %s, %s) AND (
            (check_in <= %s AND check_out > %s) OR
            (check_in < %s AND check_out >= %s) OR
            (check_in >= %s AND check_out <= %s)
        )';
        
        $params = [
            $bed_id,
            Reservation::STATUS_CONFIRMED,
            Reservation::STATUS_CHECKED_IN,
            Reservation::STATUS_PENDING,
            $check_in, $check_in,
            $check_out, $check_out,
            $check_in, $check_out,
        ];
        
        if ($exclude_reservation_id) {
            $where .= ' AND id != %d';
            $params[] = $exclude_reservation_id;
        }
        
        $sql = $wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->table} WHERE {$where}",
            ...$params
        );
        
        $count = (int) $wpdb->get_var($sql);
        
        return $count === 0;
    }
}
