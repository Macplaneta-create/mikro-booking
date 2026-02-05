<?php
/**
 * Guests REST Controller
 *
 * Handles API requests for Guests
 *
 * @package MikroPlaneta\Booking
 * @since 1.0.0
 */

namespace MikroPlaneta\Booking\RestApi\Controllers;

use MikroPlaneta\Booking\RestApi\RestController;
use MikroPlaneta\Booking\Core\Services\GuestService;
use MikroPlaneta\Booking\Core\Repositories\GuestRepository;
use WP_REST_Request;
use WP_REST_Response;

if (!defined('ABSPATH')) {
    exit;
}

class GuestsController extends RestController {
    
    private GuestService $guest_service;
    private GuestRepository $guest_repository;
    
    /**
     * Constructor
     */
    public function __construct(
        GuestService $guest_service,
        GuestRepository $guest_repository
    ) {
        $this->guest_service = $guest_service;
        $this->guest_repository = $guest_repository;
        $this->rest_base = 'guests';
    }
    
    /**
     * Register routes
     */
    public function register_routes(): void {
        register_rest_route($this->namespace, '/' . $this->rest_base, [
            [
                'methods' => 'GET',
                'callback' => [$this, 'get_items'],
                'permission_callback' => [$this, 'check_permission'],
                'args' => [
                    'search' => ['type' => 'string'],
                    'email' => ['type' => 'string'],
                    'limit' => ['type' => 'integer', 'default' => 20],
                    'offset' => ['type' => 'integer', 'default' => 0],
                ]
            ],
            [
                'methods' => 'POST',
                'callback' => [$this, 'create_item'],
                'permission_callback' => [$this, 'check_permission'],
                'args' => [
                    'first_name' => ['required' => true, 'type' => 'string'],
                    'last_name' => ['required' => true, 'type' => 'string'],
                    'email' => ['required' => true, 'type' => 'string', 'format' => 'email'],
                ]
            ]
        ]);
        
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
            [
                'methods' => 'DELETE',
                'callback' => [$this, 'delete_item'],
                'permission_callback' => [$this, 'check_permission'],
            ],
        ]);
        
        // Stats
        register_rest_route($this->namespace, '/' . $this->rest_base . '/(?P<id>\d+)/stats', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'get_stats'],
                'permission_callback' => [$this, 'check_permission'],
            ],
        ]);
    }
    
    /**
     * Get guests
     */
    public function get_items($request): WP_REST_Response {
        // Handle search
        if ($request->has_param('search')) {
            $guests = $this->guest_repository->search($request->get_param('search'));
            return $this->success(array_map(fn($g) => $g->toArray(), $guests));
        }
        
        // Handle email lookup
        if ($request->has_param('email')) {
            $guest = $this->guest_repository->findByEmail($request->get_param('email'));
            return $this->success($guest ? [$guest->toArray()] : []);
        }
        
        // List all
        $args = [
            'limit' => $request->get_param('limit'),
            'offset' => $request->get_param('offset'),
        ];
        
        $guests = $this->guest_repository->all($args);
        return $this->success(array_map(fn($g) => $g->toArray(), $guests));
    }
    
    /**
     * Get single guest
     */
    public function get_item($request): WP_REST_Response {
        $id = (int) $request['id'];
        $guest = $this->guest_repository->find($id);
        
        if (!$guest) {
            return $this->error('Guest not found', 404);
        }
        
        return $this->success($guest->toArray());
    }
    
    /**
     * Create guest
     */
    public function create_item($request): WP_REST_Response {
        try {
            $guest = $this->guest_service->createGuest($request->get_params());
            return $this->success($guest->toArray(), 201);
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
    }
    
    /**
     * Update guest
     */
    public function update_item($request): WP_REST_Response {
        $id = (int) $request['id'];
        try {
            $guest = $this->guest_service->updateGuest($id, $request->get_params());
            return $this->success($guest->toArray());
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
    }
    
    /**
     * Delete guest
     */
    public function delete_item($request): WP_REST_Response {
        $id = (int) $request['id'];
        try {
            if ($this->guest_service->deleteGuest($id)) {
                return $this->success(null, 204);
            }
            return $this->error('Failed to delete guest');
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
    }
    
    /**
     * Get guest stats
     */
    public function get_stats($request): WP_REST_Response {
        $id = (int) $request['id'];
        try {
            $stats = $this->guest_service->getGuestStatistics($id);
            return $this->success($stats);
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
    }
}
