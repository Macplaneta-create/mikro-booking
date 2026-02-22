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

        // Filter by status
        if (!empty($args['status'])) {
            $where .= ' AND status = %s';
            $params[] = $args['status'];
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
        
        $log = function($msg) {
                $log_file = MIKROPLANETA_BOOKING_PLUGIN_DIR . 'debug-log.txt';
                $time = date('Y-m-d H:i:s');
                file_put_contents($log_file, "[$time] [REPO] $msg\n", FILE_APPEND);
        };
        
        $log('Create room initiated. Data: ' . json_encode($data));
        
        $room_type = $this->normalize_room_type($data['room_type'] ?? 'standard');
        $log("Normalized room type: {$room_type}");
        
        $insert_data = [
            'name' => (string)$data['name'],
            'description' => isset($data['description']) ? (string)$data['description'] : null,
            'image_id' => isset($data['image_id']) ? (int)$data['image_id'] : null,
            'amenities' => isset($data['amenities']) ? json_encode($data['amenities']) : json_encode([]),
            'floor' => (int)($data['floor'] ?? 0),
            'room_type' => $room_type,
            'pricing_mode' => (string)($data['pricing_mode'] ?? 'per_room'),
            'status' => (string)($data['status'] ?? 'active'),
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
        
        $log = function($msg) {
                $log_file = MIKROPLANETA_BOOKING_PLUGIN_DIR . 'debug-log.txt';
                $time = date('Y-m-d H:i:s');
                file_put_contents($log_file, "[$time] [REPO_UPDATE] $msg\n", FILE_APPEND);
        };

        $log("Update room $id initiated. Data: " . json_encode($data));

        $update_data = [];
        
        if (isset($data['name'])) {
            $update_data['name'] = (string)$data['name'];
        }
        
        if (isset($data['description'])) {
            $update_data['description'] = (string)$data['description'];
        }

        if (isset($data['image_id'])) {
            $update_data['image_id'] = (int)$data['image_id'];
        }

        if (isset($data['amenities'])) {
            $update_data['amenities'] = json_encode($data['amenities']);
        }

        if (isset($data['floor'])) {
            $update_data['floor'] = (int)$data['floor'];
        }
        
        if (isset($data['room_type'])) {
            $update_data['room_type'] = $this->normalize_room_type($data['room_type']);
        }

        if (isset($data['status'])) {
            $update_data['status'] = (string)$data['status'];
        }

        if (isset($data['pricing_mode'])) {
            $update_data['pricing_mode'] = (string)$data['pricing_mode'];
        }
        
        if (empty($update_data)) {
            throw new \Exception('No data to update');
        }
        
        $result = $wpdb->update(
            $this->table,
            $update_data,
            ['id' => $id]
        );

        // If failed, try to repair table and retry
        if ($result === false) {
            $log("Update failed. Error: " . $wpdb->last_error . ". Attempting repair...");
            
            // Attempt auto-repair
            require_once ABSPATH . 'wp-admin/includes/upgrade.php';
            $sql = \MikroPlaneta\Booking\Core\Database\Schema::rooms_table();
            dbDelta($sql);
            
            // Retry update
            $result = $wpdb->update(
                $this->table,
                $update_data,
                ['id' => $id]
            );
            
            if ($result === false) {
                $log("FATAL: Update failed after repair. Error: " . $wpdb->last_error);
                throw new \Exception("Database error: " . $wpdb->last_error);
            }
        }
        
        $log("Update successful for room $id");
        
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
