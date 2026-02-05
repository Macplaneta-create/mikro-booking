<?php
/**
 * Room Repository
 *
 * Data access layer for rooms
 *
 * @package MikroPlaneta\Booking
 * @since 1.0.0
 */

namespace MikroPlaneta\Booking\Core\Repositories;

use MikroPlaneta\Booking\Core\Models\Room;
use MikroPlaneta\Booking\Core\Database\Schema;

if (!defined('ABSPATH')) {
    exit;
}

class RoomRepository implements RepositoryInterface {
    
    private string $table;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->table = Schema::get_table_name('rooms');
    }
    
    /**
     * Find room by ID
     */
    public function find(int $id): ?Room {
        global $wpdb;
        
        $row = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$this->table} WHERE id = %d", $id),
            ARRAY_A
        );
        
        return $row ? Room::fromArray($row) : null;
    }
    
    /**
     * Get all rooms
     */
    public function all(array $args = []): array {
        global $wpdb;
        
        $where = '1=1';
        $params = [];
        
        // Filter by room type
        if (!empty($args['room_type'])) {
            $where .= ' AND room_type = %s';
            $params[] = $args['room_type'];
        }
        
        // Filter by floor
        if (isset($args['floor'])) {
            $where .= ' AND floor = %d';
            $params[] = $args['floor'];
        }
        
        // Order by
        $order_by = $args['order_by'] ?? 'name';
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
            return Room::fromArray($row);
        }, $rows);
    }
    
    /**
     * Create new room
     */
    public function create(array $data): Room {
        global $wpdb;
        
        $insert_data = [
            'name' => $data['name'],
            'floor' => $data['floor'] ?? 0,
            'room_type' => $data['room_type'] ?? 'standard',
        ];
        
        $wpdb->insert($this->table, $insert_data);
        
        $room = $this->find($wpdb->insert_id);
        
        if (!$room) {
            throw new \Exception('Failed to create room');
        }
        
        return $room;
    }
    
    /**
     * Update existing room
     */
    public function update(int $id, array $data): Room {
        global $wpdb;
        
        $update_data = [];
        
        if (isset($data['name'])) {
            $update_data['name'] = $data['name'];
        }
        
        if (isset($data['floor'])) {
            $update_data['floor'] = $data['floor'];
        }
        
        if (isset($data['room_type'])) {
            $update_data['room_type'] = $data['room_type'];
        }
        
        if (empty($update_data)) {
            throw new \Exception('No data to update');
        }
        
        $wpdb->update(
            $this->table,
            $update_data,
            ['id' => $id]
        );
        
        $room = $this->find($id);
        
        if (!$room) {
            throw new \Exception('Room not found after update');
        }
        
        return $room;
    }
    
    /**
     * Delete room
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
     * Count total rooms
     */
    public function count(array $args = []): int {
        global $wpdb;
        
        $where = '1=1';
        $params = [];
        
        if (!empty($args['room_type'])) {
            $where .= ' AND room_type = %s';
            $params[] = $args['room_type'];
        }
        
        if (isset($args['floor'])) {
            $where .= ' AND floor = %d';
            $params[] = $args['floor'];
        }
        
        $sql = "SELECT COUNT(*) FROM {$this->table} WHERE {$where}";
        
        if (!empty($params)) {
            $sql = $wpdb->prepare($sql, ...$params);
        }
        
        return (int) $wpdb->get_var($sql);
    }
    
    /**
     * Find room by name
     */
    public function findByName(string $name): ?Room {
        global $wpdb;
        
        $row = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$this->table} WHERE name = %s", $name),
            ARRAY_A
        );
        
        return $row ? Room::fromArray($row) : null;
    }
}
