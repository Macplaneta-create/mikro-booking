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
        
        $sql = "SELECT * FROM {$this->table} WHERE {$where} ORDER BY start_date ASC";
        
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
        
        $insert_data = [
            'room_id' => $data['room_id'],
            'start_date' => $data['start_date'],
            'end_date' => $data['end_date'],
            'base_price' => $data['base_price'],
            'weekend_price' => $data['weekend_price'],
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
        $fields = ['room_id', 'start_date', 'end_date', 'base_price', 'weekend_price'];
        
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
    public function findForDate(int $room_id, string $date): ?Pricing {
        global $wpdb;
        
        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$this->table} 
                WHERE room_id = %d 
                AND %s >= start_date 
                AND %s <= end_date 
                LIMIT 1",
                $room_id,
                $date,
                $date
            ),
            ARRAY_A
        );
        
        return $row ? Pricing::fromArray($row) : null;
    }
}
