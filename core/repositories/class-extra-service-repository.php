<?php
/**
 * Extra Service Repository
 *
 * @package MikroPlaneta\Booking
 * @since 1.1.2
 */

namespace MikroPlaneta\Booking\Core\Repositories;

use MikroPlaneta\Booking\Core\Database\Schema;
use MikroPlaneta\Booking\Core\Models\ExtraService;

if (!defined('ABSPATH')) {
    exit;
}

class ExtraServiceRepository implements RepositoryInterface {
    
    private string $table;

    public function __construct() {
        $this->table = Schema::get_table_name('extra_services');
    }

    public function find(int $id): ?ExtraService {
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->table} WHERE id = %d", $id), ARRAY_A);
        return $row ? new ExtraService($row) : null;
    }

    public function all(array $filters = []): array {
        global $wpdb;
        $query = "SELECT * FROM {$this->table}";
        $conditions = [];
        $params = [];

        if (isset($filters['is_active'])) {
            $conditions[] = "is_active = %d";
            $params[] = (int)$filters['is_active'];
        }

        if (!empty($conditions)) {
            $query .= " WHERE " . implode(' AND ', $conditions);
        }

        $query .= " ORDER BY sort_order ASC, name ASC";

        $rows = $wpdb->get_results($wpdb->prepare($query, $params), ARRAY_A);
        return array_map(fn($row) => new ExtraService($row), $rows);
    }

    public function create(array $data): ExtraService {
        global $wpdb;
        $result = $wpdb->insert($this->table, $data);
        if ($result === false) {
            throw new \Exception("Błąd bazy danych przy tworzeniu usługi: " . $wpdb->last_error);
        }
        $data['id'] = $wpdb->insert_id;
        return new ExtraService($data);
    }

    public function update(int $id, array $data): ExtraService {
        global $wpdb;
        $result = $wpdb->update($this->table, $data, ['id' => $id]);
        if ($result === false) {
            throw new \Exception("Błąd bazy danych przy aktualizacji usługi: " . $wpdb->last_error);
        }
        return $this->find($id) ?: new ExtraService($data + ['id' => $id]);
    }

    public function delete(int $id): bool {
        global $wpdb;
        return (bool)$wpdb->delete($this->table, ['id' => $id]);
    }
}
