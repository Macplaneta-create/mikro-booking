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
                    'room_type' => ['type' => 'string'],
                    'scope_type' => ['type' => 'string'],
                    'pricing_mode' => ['type' => 'string'],
                ],
            ],
            [
                'methods' => 'POST',
                'callback' => [$this, 'create_item'],
                'permission_callback' => [$this, 'check_permission'],
                'args' => [
                    'name' => ['type' => 'string'],
                    'scope_type' => ['type' => 'string'],
                    'room_id' => ['type' => 'integer'],
                    'room_type' => ['type' => 'string'],
                    'pricing_mode' => ['type' => 'string'],
                    'priority' => ['type' => 'integer'],
                    'start_date' => ['required' => true, 'type' => 'string'],
                    'end_date' => ['required' => true, 'type' => 'string'],
                    'base_price' => ['required' => true, 'type' => 'number'],
                    'weekend_price' => ['required' => true, 'type' => 'number'],
                    'weekend_from_day' => ['type' => 'integer'],
                    'weekend_to_day' => ['type' => 'integer'],
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

        register_rest_route($this->namespace, '/' . $this->rest_base . '/calculate-group', [
            [
                'methods' => 'POST',
                'callback' => [$this, 'calculate_group_price'],
                'permission_callback' => [$this, 'check_permission'],
                'args' => [
                    'bed_ids' => ['required' => true, 'type' => 'array'],
                    'check_in' => ['required' => true, 'type' => 'string'],
                    'check_out' => ['required' => true, 'type' => 'string'],
                    'adults' => ['type' => 'integer'],
                    'children' => ['type' => 'integer'],
                    'room_id' => ['type' => 'integer'],
                ],
            ],
        ]);

        register_rest_route($this->namespace, '/' . $this->rest_base . '/(?P<id>\d+)', [
            [
                'methods' => 'PUT',
                'callback' => [$this, 'update_item'],
                'permission_callback' => [$this, 'check_permission'],
                'args' => [
                    'name' => ['type' => 'string'],
                    'scope_type' => ['type' => 'string'],
                    'room_id' => ['type' => 'integer'],
                    'room_type' => ['type' => 'string'],
                    'pricing_mode' => ['type' => 'string'],
                    'priority' => ['type' => 'integer'],
                    'start_date' => ['type' => 'string'],
                    'end_date' => ['type' => 'string'],
                    'base_price' => ['type' => 'number'],
                    'weekend_price' => ['type' => 'number'],
                    'weekend_from_day' => ['type' => 'integer'],
                    'weekend_to_day' => ['type' => 'integer'],
                ],
            ],
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
        if ($request->has_param('room_type')) {
            $args['room_type'] = $request->get_param('room_type');
        }
        if ($request->has_param('scope_type')) {
            $args['scope_type'] = $request->get_param('scope_type');
        }
        if ($request->has_param('pricing_mode')) {
            $args['pricing_mode'] = $request->get_param('pricing_mode');
        }
        $items = $this->pricing_repository->all($args);
        return $this->success(array_map(fn($item) => $item->toArray(), $items));
    }
    
    /**
     * Create pricing record
     */
    public function create_item($request): WP_REST_Response {
        try {
            $params = $request->get_params();
            $params['scope_type'] = $params['scope_type'] ?? 'room_id';
            $item = $this->pricing_repository->create($params);
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
     * Calculate price for multiple beds at once
     */
    public function calculate_group_price($request): WP_REST_Response {
        try {
            $bed_ids = $request->get_param('bed_ids');
            $check_in = $request->get_param('check_in');
            $check_out = $request->get_param('check_out');
            $adults = (int) $request->get_param('adults');
            $children = (int) $request->get_param('children');
            $room_id = (int) $request->get_param('room_id');

            $result = $this->pricing_service->calculateGroupPrice($bed_ids, $check_in, $check_out, $adults, $children, $room_id);
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

    /**
     * Update pricing record
     */
    public function update_item($request): WP_REST_Response {
        $id = (int) $request['id'];
        $params = $request->get_params();

        try {
            $updated = $this->pricing_repository->update($id, $params);
            return $this->success($updated->toArray());
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
    }
}
