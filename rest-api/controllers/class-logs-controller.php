<?php
/**
 * Logs REST Controller
 *
 * Handles API requests for reservation logs
 *
 * @package MikroPlaneta\Booking
 * @since 1.0.0
 */

namespace MikroPlaneta\Booking\RestApi\Controllers;

use MikroPlaneta\Booking\RestApi\RestController;
use MikroPlaneta\Booking\Core\Services\LoggerService;
use WP_REST_Request;
use WP_REST_Response;

if (!defined('ABSPATH')) {
    exit;
}

class LogsController extends RestController {
    
    private LoggerService $logger_service;
    
    /**
     * Constructor
     */
    public function __construct(LoggerService $logger_service) {
        $this->rest_base = 'logs';
        $this->logger_service = $logger_service;
    }
    
    /**
     * Register routes
     */
    public function register_routes(): void {
        // Get logs for a reservation
        register_rest_route($this->namespace, '/' . $this->rest_base . '/(?P<id>[\d]+)', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'get_reservation_logs'],
                'permission_callback' => [$this, 'check_permission'],
                'args' => [
                    'id' => [
                        'validate_callback' => function($param) {
                            return is_numeric($param);
                        }
                    ],
                ],
            ],
        ]);
    }
    
    /**
     * Get logs for reservation
     */
    public function get_reservation_logs(WP_REST_Request $request): WP_REST_Response {
        $reservation_id = (int) $request->get_param('id');
        
        try {
            $logs = $this->logger_service->getLogs($reservation_id);
            
            // Enrich with user info if needed, but for now raw logs are likely fine.
            // Maybe fetch user display name?
            $enriched_logs = array_map(function($log) {
                if ($log['changed_by']) {
                    $user = get_userdata($log['changed_by']);
                    $log['user_name'] = $user ? $user->display_name : 'Unknown User';
                } else {
                    $log['user_name'] = 'System';
                }
                
                // Decode JSON fields if not decoded by WPDB (WPDB typically returns strings)
                if (is_string($log['old_value'])) {
                    $log['old_value'] = json_decode($log['old_value'], true);
                }
                if (is_string($log['new_value'])) {
                    $log['new_value'] = json_decode($log['new_value'], true);
                }
                
                return $log;
            }, $logs);
            
            return $this->success($enriched_logs);
            
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), 500);
        }
    }
    
    /**
     * Check permissions
     */
    public function check_permission(): bool {
        return current_user_can('manage_options');
    }
}
