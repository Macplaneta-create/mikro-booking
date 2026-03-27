<?php
/**
 * Reservation Place Repository
 *
 * @package MikroPlaneta\Booking
 * @since 1.2.8
 */

namespace MikroPlaneta\Booking\Core\Repositories;

use MikroPlaneta\Booking\Core\Models\ReservationPlace;
use MikroPlaneta\Booking\Core\Database\Schema;

if (!defined('ABSPATH')) {
    exit;
}

class ReservationPlaceRepository {

    private string $table;
    private string $places_table;
    private string $reservations_table;
    private static ?bool $table_exists = null;

    public function __construct() {
        $this->table = Schema::get_table_name('reservation_places');
        $this->places_table = Schema::get_table_name('bed_places');
        $this->reservations_table = Schema::get_table_name('reservations');
    }

    public function exists(): bool {
        if (self::$table_exists !== null) {
            return self::$table_exists;
        }

        global $wpdb;
        $result = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $this->table));
        self::$table_exists = $result === $this->table;

        return self::$table_exists;
    }

    public function getPlaceIdsForReservation(int $reservation_id): array {
        global $wpdb;

        if (!$this->exists()) {
            return [];
        }

        $results = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT place_id FROM {$this->table} WHERE reservation_id = %d ORDER BY place_id ASC",
                $reservation_id
            )
        );

        return array_map('intval', $results);
    }

    public function getPlacesForReservation(int $reservation_id): array {
        global $wpdb;

        if (!$this->exists()) {
            return [];
        }

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$this->table} WHERE reservation_id = %d ORDER BY place_id ASC",
                $reservation_id
            ),
            'ARRAY_A'
        );

        return array_map(static function($row) {
            return ReservationPlace::fromArray($row);
        }, $rows);
    }

    public function setPlacesForReservation(int $reservation_id, array $place_ids): void {
        global $wpdb;

        if (!$this->exists()) {
            return;
        }

        $normalized = array_values(array_unique(array_filter(array_map('intval', $place_ids), static function($id) {
            return $id > 0;
        })));

        $wpdb->delete($this->table, ['reservation_id' => $reservation_id], ['%d']);

        foreach ($normalized as $place_id) {
            $wpdb->insert($this->table, [
                'reservation_id' => $reservation_id,
                'place_id' => $place_id,
            ]);
        }
    }

    public function countOccupiedPlacesForBed(
        int $bed_id,
        string $check_in,
        string $check_out,
        ?int $exclude_reservation_id = null
    ): int {
        global $wpdb;

        if (!$this->exists()) {
            return 0;
        }

        $sql = "SELECT COUNT(*)
                FROM {$this->table} rp
                INNER JOIN {$this->places_table} p ON rp.place_id = p.id
                INNER JOIN {$this->reservations_table} r ON rp.reservation_id = r.id
                WHERE p.bed_id = %d
                AND r.status IN ('pending', 'confirmed', 'checked_in')
                AND r.check_in < %s
                AND r.check_out > %s";

        $params = [$bed_id, $check_out, $check_in];

        if ($exclude_reservation_id) {
            $sql .= ' AND r.id != %d';
            $params[] = $exclude_reservation_id;
        }

        return (int) $wpdb->get_var($wpdb->prepare($sql, $params));
    }

    public function isPlaceReserved(
        int $place_id,
        string $check_in,
        string $check_out,
        ?int $exclude_reservation_id = null
    ): bool {
        global $wpdb;

        if (!$this->exists()) {
            return false;
        }

        $sql = "SELECT COUNT(*)
                FROM {$this->table} rp
                INNER JOIN {$this->reservations_table} r ON rp.reservation_id = r.id
                WHERE rp.place_id = %d
                AND r.status IN ('pending', 'confirmed', 'checked_in')
                AND r.check_in < %s
                AND r.check_out > %s";

        $params = [$place_id, $check_out, $check_in];

        if ($exclude_reservation_id) {
            $sql .= ' AND r.id != %d';
            $params[] = $exclude_reservation_id;
        }

        return (int) $wpdb->get_var($wpdb->prepare($sql, $params)) > 0;
    }
}