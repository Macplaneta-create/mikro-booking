<?php
/**
 * Reservations REST Controller
 *
 * Handles API requests for Reservations
 *
 * @package MikroPlaneta\Booking
 * @since 1.0.0
 */

namespace MikroPlaneta\Booking\RestApi\Controllers;

use MikroPlaneta\Booking\RestApi\RestController;
use MikroPlaneta\Booking\Core\Services\ReservationService;
use MikroPlaneta\Booking\Core\Repositories\ReservationRepository;
use WP_REST_Request;
use WP_REST_Response;

if (!defined('ABSPATH')) {
    exit;
}

class ReservationsController extends RestController {
    
    private ReservationService $reservation_service;
    private ReservationRepository $reservation_repository;
    
    /**
     * Constructor
     */
    public function __construct(
        ReservationService $reservation_service,
        ReservationRepository $reservation_repository
    ) {
        $this->reservation_service = $reservation_service;
        $this->reservation_repository = $reservation_repository;
        $this->rest_base = 'reservations';
    }
    
    /**
     * Register routes
     */
    public function register_routes(): void {
        // List & Create
        register_rest_route($this->namespace, '/' . $this->rest_base, [
            [
                'methods' => 'GET',
                'callback' => [$this, 'get_items'],
                'permission_callback' => [$this, 'check_permission'],
                'args' => [
                    'status' => ['type' => 'string'], // or array
                    'check_in_from' => ['type' => 'string', 'format' => 'date'],
                    'check_in_to' => ['type' => 'string', 'format' => 'date'],
                    'check_out_from' => ['type' => 'string', 'format' => 'date'],
                    'check_out_to' => ['type' => 'string', 'format' => 'date'],
                    'guest_id' => ['type' => 'integer'],
                    'bed_id' => ['type' => 'integer'],
                    'limit' => ['type' => 'integer', 'default' => 20],
                    'offset' => ['type' => 'integer', 'default' => 0],
                ],
            ],
            [
                'methods' => 'POST',
                'callback' => [$this, 'create_item'],
                'permission_callback' => [$this, 'check_permission'],
                // Validation handled by Service, but basic types here
                'args' => [
                    'guest_id' => ['required' => true, 'type' => 'integer'],
                    'bed_id' => ['required' => true, 'type' => 'integer'],
                    'check_in' => ['required' => true, 'type' => 'string', 'format' => 'date'],
                    'check_out' => ['required' => true, 'type' => 'string', 'format' => 'date'],
                    'adults' => ['type' => 'integer', 'default' => 1],
                    'children' => ['type' => 'integer', 'default' => 0],
                ],
            ],
        ]);
        
        // Single Item Operations
        register_rest_route($this->namespace, '/' . $this->rest_base . '/(?P<id>\d+)', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'get_item'],
                'permission_callback' => [$this, 'check_permission'],
            ],
            [
                'methods' => 'PUT',
                'callback' => [$this, 'update_item'],
                'permission_callback' => [$this, 'check_permission'],
            ],
        ]);
        
        // Actions
        $this->register_action_route('cancel');
        $this->register_action_route('confirm');
        $this->register_action_route('checkin');
        $this->register_action_route('checkout');
    }
    
    /**
     * Helper to register action routes
     */
    private function register_action_route(string $action): void {
        register_rest_route($this->namespace, '/' . $this->rest_base . '/(?P<id>\d+)/' . $action, [
            'methods' => 'POST',
            'callback' => [$this, $action . '_item'],
            'permission_callback' => [$this, 'check_permission'],
        ]);
    }
    
    /**
     * Get reservations
     */
    public function get_items($request): WP_REST_Response {
        $params = $request->get_params();
        
        // Clean up params for repository
        $args = array_intersect_key($params, array_flip([
            'status', 'check_in_from', 'check_in_to', 
            'check_out_from', 'check_out_to', 
            'guest_id', 'bed_id', 'limit', 'offset', 'order_by', 'order'
        ]));
        
        $reservations = $this->reservation_repository->all($args);
        
        $data = array_map(fn($res) => $res->toArray(), $reservations);
        
        return $this->success($data);
    }
    
    /**
     * Get single reservation
     */
    public function get_item($request): WP_REST_Response {
        $id = (int) $request['id'];
        $reservation = $this->reservation_repository->find($id);
        
        if (!$reservation) {
            return $this->error('Reservation not found', 404);
        }
        
        return $this->success($reservation->toArray());
    }
    
    /**
     * Create reservation
     */
    public function create_item($request): WP_REST_Response {
        try {
            $reservation = $this->reservation_service->createReservation($request->get_params());
            return $this->success($reservation->toArray(), 201);
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
    }
    
    /**
     * Update reservation
     */
    public function update_item($request): WP_REST_Response {
        $id = (int) $request['id'];
        try {
            $reservation = $this->reservation_service->updateReservation($id, $request->get_params());
            return $this->success($reservation->toArray());
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
    }
    
    /**
     * Cancel reservation
     */
    public function cancel_item($request): WP_REST_Response {
        $id = (int) $request['id'];
        $reason = $request->get_param('reason') ?? '';
        
        try {
            $reservation = $this->reservation_service->cancelReservation($id, $reason);
            return $this->success($reservation->toArray());
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
    }
    
    /**
     * Confirm reservation
     */
    public function confirm_item($request): WP_REST_Response {
        $id = (int) $request['id'];
        try {
            $reservation = $this->reservation_service->confirmReservation($id);
            return $this->success($reservation->toArray());
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
    }
    
    /**
     * Check-in
     */
    public function checkin_item($request): WP_REST_Response {
        $id = (int) $request['id'];
        try {
            $reservation = $this->reservation_service->checkIn($id);
            return $this->success($reservation->toArray());
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
    }
    
    /**
     * Check-out
     */
    public function checkout_item($request): WP_REST_Response {
        $id = (int) $request['id'];
        try {
            $reservation = $this->reservation_service->checkOut($id);
            return $this->success($reservation->toArray());
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
    }
}
