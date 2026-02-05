<?php
/**
 * Bed Repository
 *
 * Data access layer for beds
 *
 * @package MikroPlaneta\Booking
 * @since 1.0.0
 */

namespace MikroPlaneta\Booking\Core\Repositories;

use MikroPlaneta\Booking\Core\Models\Bed;
use MikroPlaneta\Booking\Core\Database\Schema;

if (!defined('ABSPATH')) {
    exit;
}

class BedRepository implements RepositoryInterface {
    
    private string $table;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->table = Schema::get_table_name('beds');
    }
    
    /**
     * Find bed by ID
     */
    public function find(int $id): ?Bed {
        global $wpdb;
        
        $row = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$this->table} WHERE id = %d", $id),
            ARRAY_A
        );
        
        return $row ? Bed::fromArray($row) : null;
    }
    
    /**
     * Get all beds
     */
    public function all(array $args = []): array {
        global $wpdb;
        
        $where = '1=1';
        $params = [];
        
        // Filter by room
        if (!empty($args['room_id'])) {
            $where .= ' AND room_id = %d';
            $params[] = $args['room_id'];
        }
        
        // Filter by bed type
        if (!empty($args['bed_type'])) {
            $where .= ' AND bed_type = %s';
            $params[] = $args['bed_type'];
        }
        
        // Filter by active status
        if (isset($args['is_active'])) {
            $where .= ' AND is_active = %d';
            $params[] = $args['is_active'] ? 1 : 0;
        }
        
        // Order by
        $order_by = $args['order_by'] ?? 'room_id, bed_number';
        $order = $args['order'] ?? 'ASC';
        
        $sql = "SELECT * FROM {$this->table} WHERE {$where} ORDER BY {$order_by} {$order}";
        
        if (!empty($params)) {
            $sql = $wpdb->prepare($sql, ...$params);
        }
        
        $rows = $wpdb->get_results($sql, ARRAY_A);
        
        return array_map(function($row) {
            return Bed::fromArray($row);
        }, $rows);
    }
    
    /**
     * Create new bed
     */
    public function create(array $data): Bed {
        global $wpdb;
        
        $insert_data = [
            'room_id' => $data['room_id'],
            'bed_number' => $data['bed_number'],
            'bed_type' => $data['bed_type'] ?? 'single',
            'is_active' => $data['is_active'] ?? true,
        ];
        
        $wpdb->insert($this->table, $insert_data);
        
        $bed = $this->find($wpdb->insert_id);
        
        if (!$bed) {
            throw new \Exception('Failed to create bed');
        }
        
        return $bed;
    }
    
    /**
     * Update existing bed
     */
    public function update(int $id, array $data): Bed {
        global $wpdb;
        
        $update_data = [];
        
        if (isset($data['bed_number'])) {
            $update_data['bed_number'] = $data['bed_number'];
        }
        
        if (isset($data['bed_type'])) {
            $update_data['bed_type'] = $data['bed_type'];
        }
        
        if (isset($data['is_active'])) {
            $update_data['is_active'] = $data['is_active'];
        }
        
        if (empty($update_data)) {
            throw new \Exception('No data to update');
        }
        
        $wpdb->update(
            $this->table,
            $update_data,
            ['id' => $id]
        );
        
        $bed = $this->find($id);
        
        if (!$bed) {
            throw new \Exception('Bed not found after update');
        }
        
        return $bed;
    }
    
    /**
     * Delete bed
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
     * Get beds by room ID
     */
    public function findByRoom(int $room_id): array {
        return $this->all(['room_id' => $room_id]);
    }
    
    /**
     * Get active beds by room ID
     */
    public function findActiveByRoom(int $room_id): array {
        return $this->all([
            'room_id' => $room_id,
            'is_active' => true,
        ]);
    }
    
    /**
     * Count beds in room
     */
    public function countByRoom(int $room_id): int {
        global $wpdb;
        
        return (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->table} WHERE room_id = %d",
                $room_id
            )
        );
    }
}
