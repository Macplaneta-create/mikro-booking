<?php
/**
 * Dashboard REST Controller
 *
 * Handles API requests for Dashboard Stats
 *
 * @package MikroPlaneta\Booking
 * @since 1.0.0
 */

namespace MikroPlaneta\Booking\RestApi\Controllers;

use MikroPlaneta\Booking\RestApi\RestController;
use MikroPlaneta\Booking\Core\Repositories\RoomRepository;
use MikroPlaneta\Booking\Core\Repositories\BedRepository;
use MikroPlaneta\Booking\Core\Repositories\ReservationRepository;
use WP_REST_Request;
use WP_REST_Response;

if (!defined('ABSPATH')) {
    exit;
}

class DashboardController extends RestController {
    
    private RoomRepository $room_repository;
    private BedRepository $bed_repository;
    private ReservationRepository $reservation_repository;
    
    /**
     * Constructor
     */
    public function __construct(
        RoomRepository $room_repository,
        BedRepository $bed_repository,
        ReservationRepository $reservation_repository
    ) {
        $this->room_repository = $room_repository;
        $this->bed_repository = $bed_repository;
        $this->reservation_repository = $reservation_repository;
        $this->rest_base = 'dashboard';
    }
    
    /**
     * Register routes
     */
    public function register_routes(): void {
        // Dashboard Stats
        register_rest_route($this->namespace, '/' . $this->rest_base . '/stats', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'get_stats'],
                'permission_callback' => [$this, 'check_permission'],
            ]
        ]);
    }
    
    /**
     * Get dashboard stats
     */
    public function get_stats($request): WP_REST_Response {
        global $wpdb;

        $stats = [
            'total_rooms' => 0,
            'total_beds' => 0,
            'occupancy_rate' => 0,
            'arrivals_today' => 0,
            'departures_today' => 0,
            'active_bookings' => 0,
            'checked_in_guests' => 0,
            'pending_reservations' => 0,
            'recent_reservations' => []
        ];

        // 1. Total Rooms & Beds
        // Note: Repositories might not have count methods, so using raw queries or fetching all might be needed
        // Ideally repos should have count() methods. Let's check or implement basic ones.
        // Assuming repos have all(), we can count. For large datasets this is bad, but for a mikro-hotel it's fine.
        
        // Optimize: Use DB Schema directly for counts to avoid hydrating objects
        $rooms_table = \MikroPlaneta\Booking\Core\Database\Schema::get_table_name('rooms');
        $beds_table = \MikroPlaneta\Booking\Core\Database\Schema::get_table_name('beds');
        $reservations_table = \MikroPlaneta\Booking\Core\Database\Schema::get_table_name('reservations');
        $reservation_beds_table = \MikroPlaneta\Booking\Core\Database\Schema::get_table_name('reservation_beds');
        $guests_table = \MikroPlaneta\Booking\Core\Database\Schema::get_table_name('guests');

        $stats['total_rooms'] = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$rooms_table}");
        $stats['total_beds'] = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$beds_table} WHERE is_active = 1");

        // 2. Today's date
        $today = current_time('Y-m-d');

        // 3. Arrivals Today
        $stats['arrivals_today'] = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$reservations_table} WHERE check_in = %s AND status IN ('pending', 'confirmed', 'checked_in')",
            $today
        ));

        // 4. Departures Today
        $stats['departures_today'] = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$reservations_table} WHERE check_out = %s AND status IN ('pending', 'confirmed', 'checked_in')",
            $today
        ));

        // 5. Active Bookings (Occupied Beds Today)
        // We need to count how many BEDS are occupied today.
        // A bed is occupied if a reservation exists where check_in <= today AND check_out > today
        // AND status is confirmed or checked_in.
        
        $sql_occupied = "SELECT COUNT(DISTINCT rb.bed_id) 
                         FROM {$reservations_table} r
                         JOIN {$reservation_beds_table} rb ON r.id = rb.reservation_id
                         WHERE r.check_in <= %s 
                         AND r.check_out > %s
                         AND r.status IN ('confirmed', 'checked_in')";
                         
        $occupied_beds = (int) $wpdb->get_var($wpdb->prepare($sql_occupied, $today, $today));
        
        $stats['active_bookings'] = $occupied_beds; // Or we can call this 'occupied_beds'

        // 6. Currently Checked-in Guests (Sum of adults + children)
        $stats['checked_in_guests'] = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT SUM(adults + children) FROM {$reservations_table} WHERE status = %s",
            \MikroPlaneta\Booking\Core\Models\Reservation::STATUS_CHECKED_IN
        ));

        // 7. Occupancy Rate
        if ($stats['total_beds'] > 0) {
            $stats['occupancy_rate'] = round(($occupied_beds / $stats['total_beds']) * 100);
        }

        // 8. Pending Reservations Count
        $stats['pending_reservations'] = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$reservations_table} WHERE status = %s",
            \MikroPlaneta\Booking\Core\Models\Reservation::STATUS_PENDING
        ));

        // 9. Recent Reservations (last 5, any status) - OPTIMIZED
        // Using indexed query with proper JOIN
        $recent = $wpdb->get_results(
            "SELECT r.id, g.first_name, g.last_name, r.check_in, r.check_out,
                    r.adults, r.children, r.status, r.total_price, r.created_at,
                    GROUP_CONCAT(rb.bed_id ORDER BY rb.bed_id SEPARATOR ',') as bed_ids
             FROM {$reservations_table} r
             LEFT JOIN {$guests_table} g ON r.guest_id = g.id
             LEFT JOIN {$reservation_beds_table} rb ON r.id = rb.reservation_id
             GROUP BY r.id, g.first_name, g.last_name, r.check_in, r.check_out, r.adults, r.children, r.status, r.total_price, r.created_at
             ORDER BY r.created_at DESC
             LIMIT 5",
            ARRAY_A
        );

        $stats['recent_reservations'] = array_map(function($res) {
            return [
                'id' => (int) $res['id'],
                'first_name' => $res['first_name'],
                'last_name' => $res['last_name'],
                'check_in' => $res['check_in'],
                'check_out' => $res['check_out'],
                'adults' => (int) $res['adults'],
                'children' => (int) $res['children'],
                'status' => $res['status'],
                'bed_ids' => $res['bed_ids'] ? array_map('intval', explode(',', $res['bed_ids'])) : [],
                'total_price' => (float) $res['total_price'],
                'created_at' => $res['created_at']
            ];
        }, $recent ?: []);

        if (function_exists('nocache_headers')) {
            nocache_headers();
        }

        return $this->success($stats);
    }
}
