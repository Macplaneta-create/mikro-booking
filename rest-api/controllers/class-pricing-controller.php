<?php
/**
 * Pricing REST Controller
 *
 * Handles API requests for Pricing
 *
 * @package MikroPlaneta\Booking
 * @since 1.0.0
 */

namespace MikroPlaneta\Booking\RestApi\Controllers;

use MikroPlaneta\Booking\RestApi\RestController;
use MikroPlaneta\Booking\Core\Repositories\PricingRepository;
use MikroPlaneta\Booking\Core\Services\PricingService;
use WP_REST_Request;
use WP_REST_Response;

if (!defined('ABSPATH')) {
    exit;
}

class PricingController extends RestController {
    
    private PricingRepository $pricing_repository;
    private PricingService $pricing_service;
    
    /**
     * Constructor
     */
    public function __construct(
        PricingRepository $pricing_repository,
        PricingService $pricing_service
    ) {
        $this->pricing_repository = $pricing_repository;
        $this->pricing_service = $pricing_service;
        $this->rest_base = 'pricing';
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
                    'room_id' => ['type' => 'integer'],
                ],
            ],
            [
                'methods' => 'POST',
                'callback' => [$this, 'create_item'],
                'permission_callback' => [$this, 'check_permission'],
                'args' => [
                    'room_id' => ['required' => true, 'type' => 'integer'],
                    'start_date' => ['required' => true, 'type' => 'string'],
                    'end_date' => ['required' => true, 'type' => 'string'],
                    'base_price' => ['required' => true, 'type' => 'number'],
                    'weekend_price' => ['required' => true, 'type' => 'number'],
                ],
            ],
        ]);

        register_rest_route($this->namespace, '/' . $this->rest_base . '/calculate', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'calculate_price'],
                'permission_callback' => [$this, 'check_permission'],
                'args' => [
                    'bed_id' => ['required' => true, 'type' => 'integer'],
                    'check_in' => ['required' => true, 'type' => 'string'],
                    'check_out' => ['required' => true, 'type' => 'string'],
                ],
            ],
        ]);

        register_rest_route($this->namespace, '/' . $this->rest_base . '/(?P<id>\d+)', [
            [
                'methods' => 'DELETE',
                'callback' => [$this, 'delete_item'],
                'permission_callback' => [$this, 'check_permission'],
            ],
        ]);
    }
    
    /**
     * Get all pricing records
     */
    public function get_items($request): WP_REST_Response {
        $args = [];
        if ($request->has_param('room_id')) {
            $args['room_id'] = $request->get_param('room_id');
        }
        $items = $this->pricing_repository->all($args);
        return $this->success(array_map(fn($item) => $item->toArray(), $items));
    }
    
    /**
     * Create pricing record
     */
    public function create_item($request): WP_REST_Response {
        try {
            $item = $this->pricing_repository->create($request->get_params());
            return $this->success($item->toArray(), 201);
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
    }
    
    /**
     * Calculate price for given parameters
     */
    public function calculate_price($request): WP_REST_Response {
        try {
            $bed_id = (int) $request->get_param('bed_id');
            $check_in = $request->get_param('check_in');
            $check_out = $request->get_param('check_out');

            $result = $this->pricing_service->calculateTotalPrice($bed_id, $check_in, $check_out);
            return $this->success($result);
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
    }
    
    /**
     * Delete pricing record
     */
    public function delete_item($request): WP_REST_Response {
        $id = (int) $request['id'];
        
        if ($this->pricing_repository->delete($id)) {
            return $this->success(null, 204);
        }
        
        return $this->error('Failed to delete pricing record');
    }
}
