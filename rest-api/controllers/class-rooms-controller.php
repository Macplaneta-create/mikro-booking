<?php
/**
 * Rooms REST Controller
 *
 * Handles API requests for Rooms and Beds
 *
 * @package MikroPlaneta\Booking
 * @since 1.0.0
 */

namespace MikroPlaneta\Booking\RestApi\Controllers;

use MikroPlaneta\Booking\RestApi\RestController;
use MikroPlaneta\Booking\Core\Repositories\RoomRepository;
use MikroPlaneta\Booking\Core\Repositories\BedRepository;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

if (!defined('ABSPATH')) {
    exit;
}

class RoomsController extends RestController {
    
    private RoomRepository $room_repository;
    private BedRepository $bed_repository;
    
    /**
     * Constructor
     */
    public function __construct(
        RoomRepository $room_repository,
        BedRepository $bed_repository
    ) {
        $this->room_repository = $room_repository;
        $this->bed_repository = $bed_repository;
        $this->rest_base = 'rooms';
    }
    
    /**
     * Register routes
     */
    public function register_routes(): void {
        // Rooms Routes
        register_rest_route($this->namespace, '/' . $this->rest_base, [
            [
                'methods' => 'GET',
                'callback' => [$this, 'get_items'],
                'permission_callback' => [$this, 'check_permission'],
                'args' => [
                    'floor' => ['type' => 'integer'],
                    'room_type' => ['type' => 'string'],
                ],
            ],
            [
                'methods' => 'POST',
                'callback' => [$this, 'create_item'],
                'permission_callback' => [$this, 'check_permission'],
                'args' => [
                    'name' => ['required' => true, 'type' => 'string'],
                    'description' => ['type' => 'string'],
                    'image_id' => ['type' => 'integer'],
                    'amenities' => ['type' => 'array'],
                    'floor' => ['type' => 'integer'],
                    'room_type' => ['type' => 'string'],
                    'pricing_mode' => ['type' => 'string'],
                    'status' => ['type' => 'string'],
                ],
            ],
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
                'args' => [
                    'name' => ['type' => 'string'],
                    'description' => ['type' => 'string'],
                    'image_id' => ['type' => 'integer'],
                    'amenities' => ['type' => 'array'],
                    'floor' => ['type' => 'integer'],
                    'room_type' => ['type' => 'string'],
                    'pricing_mode' => ['type' => 'string'],
                    'status' => ['type' => 'string'],
                ],
            ],
            [
                'methods' => 'DELETE',
                'callback' => [$this, 'delete_item'],
                'permission_callback' => [$this, 'check_permission'],
            ],
        ]);
        
        // Beds Sub-Routes
        register_rest_route($this->namespace, '/' . $this->rest_base . '/(?P<room_id>\d+)/beds', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'get_beds'],
                'permission_callback' => [$this, 'check_permission'],
            ],
            [
                'methods' => 'POST',
                'callback' => [$this, 'create_bed'],
                'permission_callback' => [$this, 'check_permission'],
                'args' => [
                    'bed_number' => ['required' => true, 'type' => 'integer'],
                    'bed_type' => ['type' => 'string'],
                    'is_active' => ['type' => 'boolean'],
                ],
            ],
        ]);
        
        // Single Bed Routes (for update/delete)
        register_rest_route($this->namespace, '/beds/(?P<id>\d+)', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'get_bed'],
                'permission_callback' => [$this, 'check_permission'],
            ],
            [
                'methods' => 'PUT',
                'callback' => [$this, 'update_bed'],
                'permission_callback' => [$this, 'check_permission'],
            ],
            [
                'methods' => 'DELETE',
                'callback' => [$this, 'delete_bed'],
                'permission_callback' => [$this, 'check_permission'],
            ],
        ]);
    }
    
    /**
     * Get all rooms
     */
    public function get_items($request): WP_REST_Response {
        $args = [];
        
        if ($request->has_param('floor')) {
            $args['floor'] = $request->get_param('floor');
        }
        
        if ($request->has_param('room_type')) {
            $args['room_type'] = $request->get_param('room_type');
        }

        if ($request->has_param('status')) {
            $args['status'] = $request->get_param('status');
        }
        
        $rooms = $this->room_repository->all($args);
        
        $data = array_map(function($room) {
            $room_data = $room->toArray();
            // Attach beds count? Or beds? Let's verify standard practices. 
            // For listing, simple data is better.
            return $room_data;
        }, $rooms);
        
        return $this->success($data);
    }
    
    /**
     * Get single room
     */
    public function get_item($request): WP_REST_Response {
        $id = (int) $request['id'];
        $room = $this->room_repository->find($id);
        
        if (!$room) {
            return $this->error('Room not found', 404);
        }
        
        $data = $room->toArray();
        // Add beds to detail view
        $beds = $this->bed_repository->findByRoom($id);
        $data['beds'] = array_map(fn($bed) => $bed->toArray(), $beds);
        
        return $this->success($data);
    }
    
    /**
     * Create room
     */
    public function create_item($request): WP_REST_Response {
        try {
            $room = $this->room_repository->create($request->get_params());
            return $this->success($room->toArray(), 201);
        } catch (\Exception $e) {
            error_log('[MikroBooking] Room create failed: ' . $e->getMessage());
            return $this->error($e->getMessage());
        }
    }

    /**
     * Update room
     */
    public function update_item($request): WP_REST_Response {
        $id = (int) $request['id'];

        try {
            $room = $this->room_repository->update($id, $request->get_params());
            return $this->success($room->toArray());
        } catch (\Exception $e) {
            error_log('[MikroBooking] Room update failed: ' . $e->getMessage());
            return $this->error($e->getMessage());
        }
    }
    
    /**
     * Delete room
     */
    public function delete_item($request): WP_REST_Response {
        $id = (int) $request['id'];
        
        // Should verify if room is empty/has no active stuff? 
        // Repository or Service normally handles this, but let's assume direct repo call for simple CRUD.
        // Wait, deleting a room would delete beds?
        
        try {
            // Check for beds if needed, but SQL might handle cascade or restrict.
            // Let's rely on repo returning false if it fails (e.g. FK constraint)
            if ($this->room_repository->delete($id)) {
                return $this->success(null, 204);
            }
            return $this->error('Failed to delete room');
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
    }
    
    // --- Bed Methods ---
    
    /**
     * Get beds for a room
     */
    public function get_beds($request): WP_REST_Response {
        $room_id = (int) $request['room_id'];
        $beds = $this->bed_repository->findByRoom($room_id);
        
        $data = array_map(fn($bed) => $bed->toArray(), $beds);
        return $this->success($data);
    }
    
    /**
     * Get single bed
     */
    public function get_bed($request): WP_REST_Response {
        $id = (int) $request['id'];
        $bed = $this->bed_repository->find($id);
        
        if (!$bed) {
            return $this->error('Bed not found', 404);
        }
        
        return $this->success($bed->toArray());
    }
    
    /**
     * Create bed in room
     */
    public function create_bed($request): WP_REST_Response {
        $room_id = (int) $request['room_id'];
        
        // Verify room exists
        $room = $this->room_repository->find($room_id);
        if (!$room) {
            return $this->error('Room not found', 404);
        }
        
        $params = $request->get_params();
        $params['room_id'] = $room_id;
        
        try {
            $bed = $this->bed_repository->create($params);
            return $this->success($bed->toArray(), 201);
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
    }
    
    /**
     * Update bed
     */
    public function update_bed($request): WP_REST_Response {
        $id = (int) $request['id'];
        
        try {
            $bed = $this->bed_repository->update($id, $request->get_params());
            return $this->success($bed->toArray());
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
    }
    
    /**
     * Delete bed
     */
    public function delete_bed($request): WP_REST_Response {
        $id = (int) $request['id'];
        
        try {
            if ($this->bed_repository->delete($id)) {
                return $this->success(null, 204);
            }
            return $this->error('Failed to delete bed');
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
    }
}
