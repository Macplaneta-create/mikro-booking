<?php
/**
 * Reservation Extra Repository
 *
 * @package MikroPlaneta\Booking
 * @since 1.1.2
 */

namespace MikroPlaneta\Booking\Core\Repositories;

use MikroPlaneta\Booking\Core\Database\Schema;
use MikroPlaneta\Booking\Core\Models\ReservationExtra;

if (!defined('ABSPATH')) {
    exit;
}

class ReservationExtraRepository implements RepositoryInterface {
    
    private string $table;
    private string $services_table;

    public function __construct() {
        $this->table = Schema::get_table_name('reservation_extras');
        $this->services_table = Schema::get_table_name('extra_services');
    }

    public function find(int $id): ?ReservationExtra {
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT re.*, s.name as service_name 
             FROM {$this->table} re
             LEFT JOIN {$this->services_table} s ON re.service_id = s.id
             WHERE re.id = %d", 
            $id
        ), ARRAY_A);
        return $row ? new ReservationExtra($row) : null;
    }

    public function all(array $filters = []): array {
        global $wpdb;
        $query = "SELECT re.*, s.name as service_name 
                  FROM {$this->table} re
                  LEFT JOIN {$this->services_table} s ON re.service_id = s.id";
        $conditions = [];
        $params = [];

        if (isset($filters['reservation_id'])) {
            $conditions[] = "re.reservation_id = %d";
            $params[] = (int)$filters['reservation_id'];
        }

        if (!empty($conditions)) {
            $query .= " WHERE " . implode(' AND ', $conditions);
        }

        $rows = $wpdb->get_results($wpdb->prepare($query, $params), ARRAY_A);
        return array_map(fn($row) => new ReservationExtra($row), $rows);
    }

    public function create(array $data): ReservationExtra {
        global $wpdb;
        $wpdb->insert($this->table, $data);
        $data['id'] = $wpdb->insert_id;
        return $this->find($data['id']);
    }

    public function update(int $id, array $data): ReservationExtra {
        global $wpdb;
        $wpdb->update($this->table, $data, ['id' => $id]);
        return $this->find($id);
    }

    public function delete(int $id): bool {
        global $wpdb;
        return (bool)$wpdb->delete($this->table, ['id' => $id]);
    }

    /**
     * Delete all extras for a specific reservation (useful for updates)
     */
    public function deleteByReservation(int $reservation_id): bool {
        global $wpdb;
        return (bool)$wpdb->delete($this->table, ['reservation_id' => $reservation_id]);
    }
}
