<?php
/**
 * Changes Log Repository
 *
 * Data access layer for changes log
 *
 * @package MikroPlaneta\Booking
 * @since 1.0.0
 */

namespace MikroPlaneta\Booking\Core\Repositories;

use MikroPlaneta\Booking\Core\Database\Schema;

if (!defined('ABSPATH')) {
    exit;
}

class ChangesLogRepository implements RepositoryInterface {
    
    private string $table;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->table = Schema::get_table_name('changes_log');
    }
    
    /**
     * Find log by ID
     */
    public function find(int $id): ?object {
        global $wpdb;
        
        $row = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$this->table} WHERE id = %d", $id),
            OBJECT
        ); // OBJECT is default, but explicit for clarity
        
        return $row ?: null;
    }
    
    /**
     * Create new log entry
     */
    public function create(array $data): object {
        global $wpdb;
        
        $wpdb->insert($this->table, $data);
        
        $id = $wpdb->insert_id;
        $obj = $this->find($id);
        
        // Safety fallback: ensure an object is returned even if DB read fails slightly
        // This prevents "Return value must be of type object, null returned" error
        return $obj ?: (object) array_merge(['id' => (int)$id], $data);
    }
    
    /**
     * Get logs for a reservation
     * 
     * Returns array of associative arrays for easier JSON encoding and frontend consumption
     */
    public function findByReservation(int $reservation_id): array {
        global $wpdb;
        
        $sql = "SELECT * FROM {$this->table} WHERE reservation_id = %d ORDER BY created_at DESC";
        
        return $wpdb->get_results(
            $wpdb->prepare($sql, $reservation_id),
            ARRAY_A // Keep associative array for LogsController compatibility
        );
    }
    
    /**
     * Update (not supported for logs)
     * Throws exception because logs are immutable
     */
    public function update(int $id, array $data): object {
        throw new \BadMethodCallException('Updating logs is not supported.');
    }
    
    /**
     * Delete (not supported for logs)
     */
    public function delete(int $id): bool {
        return false;
    }

    /**
     * All (not typically used for logs, but implemented for interface)
     */
    public function all(array $args = []): array {
        return [];
    }
}
