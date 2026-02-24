<?php
/**
 * Pricing Repository
 *
 * @package MikroPlaneta\Booking
 * @since 1.0.0
 */

namespace MikroPlaneta\Booking\Core\Repositories;

use MikroPlaneta\Booking\Core\Models\Pricing;
use MikroPlaneta\Booking\Core\Database\Schema;

if (!defined('ABSPATH')) {
    exit;
}

class PricingRepository implements RepositoryInterface {
    
    private string $table;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->table = Schema::get_table_name('pricing');
    }
    
    /**
     * Find pricing by ID
     */
    public function find(int $id): ?Pricing {
        global $wpdb;
        
        $row = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$this->table} WHERE id = %d", $id),
            ARRAY_A
        );
        
        return $row ? Pricing::fromArray($row) : null;
    }
    
    /**
     * Get all pricing records
     */
    public function all(array $args = []): array {
        global $wpdb;
        
        $where = '1=1';
        $params = [];
        
        if (!empty($args['room_id'])) {
            $where .= ' AND room_id = %d';
            $params[] = $args['room_id'];
        }

        if (!empty($args['room_type'])) {
            $where .= ' AND room_type = %s';
            $params[] = $args['room_type'];
        }

        if (!empty($args['scope_type'])) {
            $where .= ' AND scope_type = %s';
            $params[] = $args['scope_type'];
        }

        if (!empty($args['pricing_mode'])) {
            $where .= ' AND (pricing_mode = %s OR pricing_mode IS NULL OR pricing_mode = \'\')';
            $params[] = $args['pricing_mode'];
        }
        
        $sql = "SELECT * FROM {$this->table} WHERE {$where} ORDER BY priority DESC, start_date ASC";
        
        if (!empty($params)) {
            $sql = $wpdb->prepare($sql, ...$params);
        }
        
        $rows = $wpdb->get_results($sql, ARRAY_A);
        
        return array_map(function($row) {
            return Pricing::fromArray($row);
        }, $rows);
    }
    
    /**
     * Create new pricing record
     */
    public function create(array $data): Pricing {
        global $wpdb;
        $scope_type = isset($data['scope_type']) ? (string) $data['scope_type'] : 'room_id';
        if (!in_array($scope_type, ['room_id', 'room_type'], true)) {
            throw new \Exception('Invalid scope_type. Allowed: room_id, room_type');
        }

        if ($scope_type === 'room_id' && empty($data['room_id'])) {
            throw new \Exception('room_id is required for scope_type=room_id');
        }

        if ($scope_type === 'room_type' && empty($data['room_type'])) {
            throw new \Exception('room_type is required for scope_type=room_type');
        }

        $weekend_from_day = isset($data['weekend_from_day']) ? (int) $data['weekend_from_day'] : 5;
        $weekend_to_day = isset($data['weekend_to_day']) ? (int) $data['weekend_to_day'] : 7;
        if ($weekend_from_day < 1 || $weekend_from_day > 7) {
            throw new \Exception('weekend_from_day must be in range 1-7');
        }
        if ($weekend_to_day < 1 || $weekend_to_day > 7) {
            throw new \Exception('weekend_to_day must be in range 1-7');
        }

        $insert_data = [
            'name' => isset($data['name']) && $data['name'] !== '' ? (string) $data['name'] : null,
            'room_id' => $scope_type === 'room_id' ? (int) $data['room_id'] : null,
            'scope_type' => $scope_type,
            'room_type' => $scope_type === 'room_type' ? (string) $data['room_type'] : null,
            'pricing_mode' => isset($data['pricing_mode']) ? (string) $data['pricing_mode'] : null,
            'priority' => isset($data['priority']) ? (int) $data['priority'] : 100,
            'start_date' => $data['start_date'],
            'end_date' => $data['end_date'],
            'base_price' => $data['base_price'],
            'weekend_price' => $data['weekend_price'],
            'weekend_from_day' => $weekend_from_day,
            'weekend_to_day' => $weekend_to_day,
        ];
        
        $wpdb->insert($this->table, $insert_data);
        
        $pricing = $this->find($wpdb->insert_id);
        
        if (!$pricing) {
            throw new \Exception('Failed to create pricing record');
        }
        
        return $pricing;
    }
    
    /**
     * Update pricing record
     */
    public function update(int $id, array $data): Pricing {
        global $wpdb;
        
        $update_data = [];
        $fields = ['name', 'room_id', 'scope_type', 'room_type', 'pricing_mode', 'priority', 'start_date', 'end_date', 'base_price', 'weekend_price', 'weekend_from_day', 'weekend_to_day'];
        
        foreach ($fields as $field) {
            if (isset($data[$field])) {
                $update_data[$field] = $data[$field];
            }
        }

        $next_scope = $update_data['scope_type'] ?? null;
        if ($next_scope !== null && !in_array((string) $next_scope, ['room_id', 'room_type'], true)) {
            throw new \Exception('Invalid scope_type. Allowed: room_id, room_type');
        }

        // Keep consistency when switching scope
        if (($next_scope ?? '') === 'room_id') {
            if (empty($update_data['room_id']) && !empty($update_data['room_type'])) {
                $update_data['room_type'] = null;
            }
        }
        if (($next_scope ?? '') === 'room_type') {
            if (empty($update_data['room_type'])) {
                throw new \Exception('room_type is required for scope_type=room_type');
            }
            $update_data['room_id'] = null;
        }

        if (isset($update_data['weekend_from_day'])) {
            $weekend_from_day = (int) $update_data['weekend_from_day'];
            if ($weekend_from_day < 1 || $weekend_from_day > 7) {
                throw new \Exception('weekend_from_day must be in range 1-7');
            }
            $update_data['weekend_from_day'] = $weekend_from_day;
        }

        if (isset($update_data['weekend_to_day'])) {
            $weekend_to_day = (int) $update_data['weekend_to_day'];
            if ($weekend_to_day < 1 || $weekend_to_day > 7) {
                throw new \Exception('weekend_to_day must be in range 1-7');
            }
            $update_data['weekend_to_day'] = $weekend_to_day;
        }
        
        if (empty($update_data)) {
            throw new \Exception('No data to update');
        }
        
        $wpdb->update(
            $this->table,
            $update_data,
            ['id' => $id]
        );
        
        $pricing = $this->find($id);
        
        if (!$pricing) {
            throw new \Exception('Pricing record not found after update');
        }
        
        return $pricing;
    }
    
    /**
     * Delete pricing record
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
     * Find pricing for a room on a specific date
     */
    public function findForDate(int $room_id, string $room_type, string $pricing_mode, string $date): ?Pricing {
        global $wpdb;

        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$this->table} 
                WHERE (
                    (scope_type = 'room_id' AND room_id = %d)
                    OR
                    (scope_type = 'room_type' AND room_type = %s)
                )
                AND %s >= start_date 
                AND %s <= end_date
                AND (
                    pricing_mode IS NULL
                    OR pricing_mode = ''
                    OR pricing_mode = %s
                )
                ORDER BY
                    priority DESC,
                    CASE WHEN scope_type = 'room_id' THEN 2 ELSE 1 END DESC,
                    id DESC
                LIMIT 1",
                $room_id,
                $room_type,
                $date,
                $date,
                $pricing_mode
            ),
            ARRAY_A
        );
        
        return $row ? Pricing::fromArray($row) : null;
    }
}
