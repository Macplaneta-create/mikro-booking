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
        
        // Order by (whitelist to avoid SQL injection)
        $allowed_order_by = ['name', 'floor', 'room_type', 'created_at', 'updated_at', 'id'];
        $order_by = $args['order_by'] ?? 'name';
        if (!in_array($order_by, $allowed_order_by, true)) {
            $order_by = 'name';
        }

        $order = strtoupper($args['order'] ?? 'ASC');
        if (!in_array($order, ['ASC', 'DESC'], true)) {
            $order = 'ASC';
        }
        
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
    /**
     * Helper to normalize room type
     */
    private function normalize_room_type(string $type): string {
        $map = [
            'private' => 'standard',
            'dorm' => 'dormitory',
            'standard' => 'standard',
            'deluxe' => 'deluxe',
            'suite' => 'suite',
            'dormitory' => 'dormitory',
        ];
        
        return $map[$type] ?? 'standard';
    }

    /**
     * Create new room
     */
    public function create(array $data): Room {
        global $wpdb;
        
        // Debug-only logging to server error log (avoid file writes in plugin dir)
        $log = function($msg) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[MikroBooking] ' . $msg);
            }
        };
        
        $log('Create room initiated.');
        
        $room_type = $this->normalize_room_type($data['room_type'] ?? 'standard');
        $log("Normalized room type: {$room_type}");
        
        $insert_data = [
            'name' => $data['name'],
            'floor' => $data['floor'] ?? 0,
            'room_type' => $room_type,
        ];
        
        // Try insert
        $result = $wpdb->insert($this->table, $insert_data);
        
        // If failed, try to repair table and retry
        if ($result === false) {
            $original_error = $wpdb->last_error;
            $log("Insert failed. Error: {$original_error}. Attempting repair...");
            
            // Attempt auto-repair
            require_once ABSPATH . 'wp-admin/includes/upgrade.php';
            $sql = \MikroPlaneta\Booking\Core\Database\Schema::rooms_table();
            dbDelta($sql);
            
            // Retry insert
            $result = $wpdb->insert($this->table, $insert_data);
            
            if ($result === false) {
                $final_error = $wpdb->last_error ?: $original_error;
                $log("FATAL: Insert failed after repair. Error: {$final_error}");
                error_log("MikroBooking Error: Failed to insert room after repair. DB Error: " . $final_error);
                throw new \Exception("Database error: " . $final_error);
            }
        }
        
        $log('Room inserted successfully. ID: ' . $wpdb->insert_id);
        
        $room = $this->find($wpdb->insert_id);
        
        if (!$room) {
            $log('FATAL: Failed to retrieve created room ID: ' . $wpdb->insert_id);
            throw new \Exception('Failed to retrieve created room');
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
            $update_data['room_type'] = $this->normalize_room_type($data['room_type']);
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
