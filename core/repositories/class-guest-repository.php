<?php
/**
 * Guest Repository
 *
 * Data access layer for guests
 *
 * @package MikroPlaneta\Booking
 * @since 1.0.0
 */

namespace MikroPlaneta\Booking\Core\Repositories;

use MikroPlaneta\Booking\Core\Models\Guest;
use MikroPlaneta\Booking\Core\Database\Schema;

if (!defined('ABSPATH')) {
    exit;
}

class GuestRepository implements RepositoryInterface {
    
    private string $table;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->table = Schema::get_table_name('guests');
    }
    
    /**
     * Find guest by ID
     */
    public function find(int $id): ?Guest {
        global $wpdb;
        
        $row = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$this->table} WHERE id = %d", $id),
            ARRAY_A
        );
        
        return $row ? Guest::fromArray($row) : null;
    }
    
    /**
     * Get all guests
     */
    public function all(array $args = []): array {
        global $wpdb;
        
        $where = '1=1';
        $params = [];
        
        // Search by name or email
        if (!empty($args['search'])) {
            $where .= ' AND (first_name LIKE %s OR last_name LIKE %s OR email LIKE %s)';
            $search = '%' . $wpdb->esc_like($args['search']) . '%';
            $params[] = $search;
            $params[] = $search;
            $params[] = $search;
        }
        
        // Order by
        $order_by = $args['order_by'] ?? 'last_name, first_name';
        $order = $args['order'] ?? 'ASC';
        
        // Limit
        $limit = isset($args['limit']) ? (int) $args['limit'] : null;
        $offset = isset($args['offset']) ? (int) $args['offset'] : 0;
        
        $sql = "SELECT * FROM {$this->table} WHERE {$where} ORDER BY {$order_by} {$order}";
        
        if ($limit) {
            $sql .= " LIMIT {$offset}, {$limit}";
        }
        
        if (!empty($params)) {
            $sql = $wpdb->prepare($sql, ...$params);
        }
        
        $rows = $wpdb->get_results($sql, ARRAY_A);
        
        return array_map(function($row) {
            return Guest::fromArray($row);
        }, $rows);
    }
    
    /**
     * Create new guest
     */
    public function create(array $data): Guest {
        global $wpdb;
        
        $insert_data = [
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'preferences' => isset($data['preferences']) ? json_encode($data['preferences']) : null,
            'total_stays' => $data['total_stays'] ?? 0,
            'last_stay_date' => $data['last_stay_date'] ?? null,
        ];
        
        $wpdb->insert($this->table, $insert_data);
        
        $guest = $this->find($wpdb->insert_id);
        
        if (!$guest) {
            throw new \Exception('Failed to create guest');
        }
        
        return $guest;
    }
    
    /**
     * Update existing guest
     */
    public function update(int $id, array $data): Guest {
        global $wpdb;
        
        $update_data = [];
        
        if (isset($data['first_name'])) {
            $update_data['first_name'] = $data['first_name'];
        }
        
        if (isset($data['last_name'])) {
            $update_data['last_name'] = $data['last_name'];
        }
        
        if (isset($data['email'])) {
            $update_data['email'] = $data['email'];
        }
        
        if (isset($data['phone'])) {
            $update_data['phone'] = $data['phone'];
        }
        
        if (isset($data['preferences'])) {
            $update_data['preferences'] = json_encode($data['preferences']);
        }
        
        if (isset($data['total_stays'])) {
            $update_data['total_stays'] = $data['total_stays'];
        }
        
        if (isset($data['last_stay_date'])) {
            $update_data['last_stay_date'] = $data['last_stay_date'];
        }
        
        if (empty($update_data)) {
            throw new \Exception('No data to update');
        }
        
        $wpdb->update(
            $this->table,
            $update_data,
            ['id' => $id]
        );
        
        $guest = $this->find($id);
        
        if (!$guest) {
            throw new \Exception('Guest not found after update');
        }
        
        return $guest;
    }
    
    /**
     * Delete guest
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
     * Find guest by email
     */
    public function findByEmail(string $email): ?Guest {
        global $wpdb;
        
        $row = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$this->table} WHERE email = %s", $email),
            ARRAY_A
        );
        
        return $row ? Guest::fromArray($row) : null;
    }
    
    /**
     * Search guests
     */
    public function search(string $query): array {
        return $this->all(['search' => $query]);
    }
    
    /**
     * Get returning guests
     */
    public function getReturningGuests(): array {
        global $wpdb;
        
        $rows = $wpdb->get_results(
            "SELECT * FROM {$this->table} WHERE total_stays > 0 ORDER BY total_stays DESC",
            ARRAY_A
        );
        
        return array_map(function($row) {
            return Guest::fromArray($row);
        }, $rows);
    }
}
