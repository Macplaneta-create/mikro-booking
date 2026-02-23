<?php
/**
 * BedPlace Repository
 *
 * Data access layer for bed places
 *
 * @package MikroPlaneta\Booking
 * @since 1.2.8
 */

namespace MikroPlaneta\Booking\Core\Repositories;

use MikroPlaneta\Booking\Core\Models\BedPlace;
use MikroPlaneta\Booking\Core\Database\Schema;

if (!defined('ABSPATH')) {
    exit;
}

class BedPlaceRepository implements RepositoryInterface {

    private string $table;
    private string $beds_table;

    /**
     * Constructor
     */
    public function __construct() {
        $this->table = Schema::get_table_name('bed_places');
        $this->beds_table = Schema::get_table_name('beds');
    }

    /**
     * Find bed place by ID
     */
    public function find(int $id): ?BedPlace {
        global $wpdb;

        $sql = "SELECT p.*, b.room_id, b.bed_type, b.bed_number
                FROM {$this->table} p
                LEFT JOIN {$this->beds_table} b ON p.bed_id = b.id
                WHERE p.id = %d";

        $row = $wpdb->get_row(
            $wpdb->prepare($sql, $id),
            ARRAY_A
        );

        return $row ? BedPlace::fromArray($row) : null;
    }

    /**
     * Get all bed places
     */
    public function all(array $args = []): array {
        global $wpdb;

        $where = '1=1';
        $params = [];

        // Filter by bed
        if (!empty($args['bed_id'])) {
            $where .= ' AND p.bed_id = %d';
            $params[] = $args['bed_id'];
        }

        // Filter by room
        if (!empty($args['room_id'])) {
            $where .= ' AND b.room_id = %d';
            $params[] = $args['room_id'];
        }

        // Filter by active status
        if (isset($args['is_active'])) {
            $where .= ' AND p.is_active = %d';
            $params[] = $args['is_active'] ? 1 : 0;
        }

        // Filter by bed type
        if (!empty($args['bed_type'])) {
            $where .= ' AND b.bed_type = %s';
            $params[] = $args['bed_type'];
        }

        // Join with beds table
        $sql = "SELECT p.*, b.room_id, b.bed_type, b.bed_number
                FROM {$this->table} p
                LEFT JOIN {$this->beds_table} b ON p.bed_id = b.id
                WHERE {$where}
                ORDER BY b.room_id, b.bed_number, p.place_number";

        if (!empty($params)) {
            $sql = $wpdb->prepare($sql, $params);
        }

        $rows = $wpdb->get_results($sql, ARRAY_A);

        return array_map(function($row) {
            return BedPlace::fromArray($row);
        }, $rows);
    }

    /**
     * Create new bed place
     */
    public function create(array $data): BedPlace {
        global $wpdb;

        $insert_data = [
            'bed_id' => (int) $data['bed_id'],
            'place_number' => (int) ($data['place_number'] ?? 1),
            'place_label' => $data['place_label'] ?? '',
            'max_persons' => (int) ($data['max_persons'] ?? 1),
            'is_active' => $data['is_active'] ?? true,
        ];

        $wpdb->insert($this->table, $insert_data);

        $place = $this->find($wpdb->insert_id);

        if (!$place) {
            throw new \Exception('Failed to create bed place');
        }

        return $place;
    }

    /**
     * Update existing bed place
     */
    public function update(int $id, array $data): BedPlace {
        global $wpdb;

        $update_data = [];

        if (isset($data['place_number'])) {
            $update_data['place_number'] = (int) $data['place_number'];
        }

        if (isset($data['place_label'])) {
            $update_data['place_label'] = (string) $data['place_label'];
        }

        if (isset($data['max_persons'])) {
            $update_data['max_persons'] = (int) $data['max_persons'];
        }

        if (isset($data['is_active'])) {
            $update_data['is_active'] = (bool) $data['is_active'];
        }

        if (empty($update_data)) {
            throw new \Exception('No data to update');
        }

        $wpdb->update(
            $this->table,
            $update_data,
            ['id' => $id]
        );

        $place = $this->find($id);

        if (!$place) {
            throw new \Exception('Bed place not found after update');
        }

        return $place;
    }

    /**
     * Delete bed place
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
     * Find places by bed ID
     */
    public function findByBed(int $bed_id): array {
        return $this->all(['bed_id' => $bed_id]);
    }

    /**
     * Find places by room ID
     */
    public function findByRoom(int $room_id): array {
        return $this->all(['room_id' => $room_id]);
    }

    /**
     * Get total capacity of a bed (sum of max_persons for all places)
     */
    public function getBedCapacity(int $bed_id): int {
        global $wpdb;

        $sql = "SELECT SUM(max_persons) as total_capacity
                FROM {$this->table}
                WHERE bed_id = %d AND is_active = 1";

        $result = $wpdb->get_var($wpdb->prepare($sql, $bed_id));

        return (int) ($result ?? 0);
    }

    /**
     * Get total capacity of a room (sum of max_persons for all places)
     */
    public function getRoomCapacity(int $room_id): int {
        global $wpdb;

        $sql = "SELECT SUM(p.max_persons) as total_capacity
                FROM {$this->table} p
                INNER JOIN {$this->beds_table} b ON p.bed_id = b.id
                WHERE b.room_id = %d AND p.is_active = 1";

        $result = $wpdb->get_var($wpdb->prepare($sql, $room_id));

        return (int) ($result ?? 0);
    }

    /**
     * Check if place is available for given dates
     */
    public function isPlaceAvailable(int $place_id, string $check_in, string $check_out, ?int $exclude_reservation_id = null): bool {
        global $wpdb;

        $reservation_places_table = Schema::get_table_name('reservation_places');
        $reservations_table = Schema::get_table_name('reservations');

        // Check if place is booked for overlapping dates
        $sql = "SELECT COUNT(*) FROM {$reservation_places_table} rp
                INNER JOIN {$reservations_table} r ON rp.reservation_id = r.id
                WHERE rp.place_id = %d
                AND r.status IN ('pending', 'confirmed', 'checked_in')
                AND r.check_in < %s
                AND r.check_out > %s";

        $params = [
            $place_id,
            $check_out,
            $check_in,
        ];

        if ($exclude_reservation_id) {
            $sql .= ' AND r.id != %d';
            $params[] = $exclude_reservation_id;
        }

        $count = $wpdb->get_var($wpdb->prepare($sql, $params));

        return $count == 0;
    }

    /**
     * Create default places for a bed based on bed type
     */
    public function createDefaultPlacesForBed(int $bed_id, string $bed_type): array {
        $places = [];

        switch ($bed_type) {
            case 'bunk':
                // Bunk bed: 2 places (Dół i Góra)
                $places[] = $this->create([
                    'bed_id' => $bed_id,
                    'place_number' => 1,
                    'place_label' => 'Dół',
                    'max_persons' => 1,
                    'is_active' => true,
                ]);

                $places[] = $this->create([
                    'bed_id' => $bed_id,
                    'place_number' => 2,
                    'place_label' => 'Góra',
                    'max_persons' => 1,
                    'is_active' => true,
                ]);
                break;

            case 'double':
                // Double bed: 1 place for 2 persons (couple)
                $places[] = $this->create([
                    'bed_id' => $bed_id,
                    'place_number' => 1,
                    'place_label' => 'Łóżko małżeńskie',
                    'max_persons' => 2,
                    'is_active' => true,
                ]);
                break;

            case 'single':
            default:
                // Single bed: 1 place for 1 person
                $places[] = $this->create([
                    'bed_id' => $bed_id,
                    'place_number' => 1,
                    'place_label' => 'Miejsce 1',
                    'max_persons' => 1,
                    'is_active' => true,
                ]);
                break;
        }

        return $places;
    }
}
