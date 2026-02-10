<?php
/**
 * Availability REST Controller
 *
 * Handles API requests for Availability checking
 *
 * @package MikroPlaneta\Booking
 * @since 1.0.0
 */

namespace MikroPlaneta\Booking\RestApi\Controllers;

use MikroPlaneta\Booking\RestApi\RestController;
use MikroPlaneta\Booking\Core\Services\AvailabilityService;
use WP_REST_Request;
use WP_REST_Response;

if (!defined('ABSPATH')) {
    exit;
}

class AvailabilityController extends RestController {
    
    private AvailabilityService $availability_service;
    
    /**
     * Constructor
     */
    public function __construct(AvailabilityService $availability_service) {
        $this->availability_service = $availability_service;
        $this->rest_base = 'availability';
    }
    
    /**
     * Register routes
     */
    public function register_routes(): void {
        // Find available beds
        register_rest_route($this->namespace, '/' . $this->rest_base . '/beds', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'get_available_beds'],
                'permission_callback' => [$this, 'check_permission'],
                'args' => [
                    'check_in' => ['required' => true, 'type' => 'string', 'format' => 'date'],
                    'check_out' => ['required' => true, 'type' => 'string', 'format' => 'date'],
                    'room_id' => ['type' => 'integer'],
                ]
            ],
        ]);
        
        // Group Search
        register_rest_route($this->namespace, '/' . $this->rest_base . '/group-search', [
             [
                'methods' => 'GET',
                'callback' => [$this, 'search_group_availability'],
                'permission_callback' => [$this, 'check_permission'],
                'args' => [
                    'group_size' => ['required' => true, 'type' => 'integer'],
                    'check_in' => ['required' => true, 'type' => 'string', 'format' => 'date'],
                    'check_out' => ['required' => true, 'type' => 'string', 'format' => 'date'],
                ]
            ],
        ]);
        
        // Calendar
        register_rest_route($this->namespace, '/' . $this->rest_base . '/calendar/(?P<bed_id>\d+)', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'get_calendar'],
                'permission_callback' => [$this, 'check_permission'],
                'args' => [
                    'start_date' => ['required' => true, 'type' => 'string', 'format' => 'date'],
                    'end_date' => ['required' => true, 'type' => 'string', 'format' => 'date'],
                ]
            ],
        ]);
        
        // Occupancy
        register_rest_route($this->namespace, '/' . $this->rest_base . '/occupancy', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'get_occupancy'],
                'permission_callback' => [$this, 'check_permission'],
                'args' => [
                    'start_date' => ['required' => true, 'type' => 'string', 'format' => 'date'],
                    'end_date' => ['required' => true, 'type' => 'string', 'format' => 'date'],
                ]
            ],
        ]);
    }
    
    /**
     * Get available beds
     */
    public function get_available_beds($request): WP_REST_Response {
        $check_in = $request->get_param('check_in');
        $check_out = $request->get_param('check_out');
        $room_id = $request->get_param('room_id');
        
        if ($room_id) {
            $beds = $this->availability_service->findAvailableBedsByRoom((int)$room_id, $check_in, $check_out);
        } else {
            $beds = $this->availability_service->findAvailableBeds($check_in, $check_out);
        }
        
        $data = array_map(fn($bed) => $bed->toArray(), $beds);
        return $this->success($data);
    }
    
    /**
     * Search group availability
     */
    public function search_group_availability($request): WP_REST_Response {
        $group_size = (int) $request->get_param('group_size');
        $check_in = $request->get_param('check_in');
        $check_out = $request->get_param('check_out');
        
        $results = $this->availability_service->findAvailableBedsForGroup(
            $group_size,
            $check_in,
            $check_out
        );
        
        // Need to serialize Bed objects inside results
        $data = array_map(function($option) {
            $option['beds'] = array_map(fn($bed) => $bed->toArray(), $option['beds']);
            return $option;
        }, $results);
        
        return $this->success($data);
    }
    
    /**
     * Get calendar
     */
    public function get_calendar($request): WP_REST_Response {
        $bed_id = (int) $request['bed_id'];
        $start_date = $request->get_param('start_date');
        $end_date = $request->get_param('end_date');
        
        $calendar = $this->availability_service->getBedAvailabilityCalendar($bed_id, $start_date, $end_date);
        
        // Keep associative array (dates as keys) or convert to indexed?
        // JS usually prefers lists, but map is fine too.
        return $this->success($calendar);
    }
    
    /**
     * Get occupancy
     */
    public function get_occupancy($request): WP_REST_Response {
        $start_date = $request->get_param('start_date');
        $end_date = $request->get_param('end_date');
        
        $stats = $this->availability_service->getOccupancyRate($start_date, $end_date);
        return $this->success($stats);
    }
}
