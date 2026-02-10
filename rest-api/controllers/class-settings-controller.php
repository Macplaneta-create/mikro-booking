<?php
/**
 * Settings REST Controller
 *
 * Handles API requests for plugin settings
 *
 * @package MikroPlaneta\Booking
 * @since 1.0.0
 */

namespace MikroPlaneta\Booking\RestApi\Controllers;

use MikroPlaneta\Booking\RestApi\RestController;
use WP_REST_Request;
use WP_REST_Response;

if (!defined('ABSPATH')) {
    exit;
}

class SettingsController extends RestController {
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->rest_base = 'settings';
    }
    
    /**
     * Register routes
     */
    public function register_routes(): void {
        // Get all settings
        register_rest_route($this->namespace, '/' . $this->rest_base, [
            [
                'methods' => 'GET',
                'callback' => [$this, 'get_settings'],
                'permission_callback' => [$this, 'check_permission'],
            ],
            [
                'methods' => 'POST',
                'callback' => [$this, 'update_settings'],
                'permission_callback' => [$this, 'check_permission'],
                'args' => [
                    'pending_timeout_hours' => ['type' => 'integer', 'minimum' => 1],
                    'auto_expire_pending' => ['type' => 'boolean'],
                    'require_payment_confirmation' => ['type' => 'boolean'],
                ],
            ],
        ]);
    }
    
    /**
     * Get all settings
     */
    public function get_settings($request): WP_REST_Response {
        $settings = [
            'pending_timeout_hours' => (int) get_option(
                'mikroplaneta_booking_pending_timeout_hours',
                48
            ),
            'auto_expire_pending' => (bool) get_option(
                'mikroplaneta_booking_auto_expire_pending',
                true
            ),
            'require_payment_confirmation' => (bool) get_option(
                'mikroplaneta_booking_require_payment_confirmation',
                true
            ),
        ];
        
        return $this->success($settings);
    }
    
    /**
     * Update settings
     */
    public function update_settings($request): WP_REST_Response {
        $params = $request->get_params();
        
        if (isset($params['pending_timeout_hours'])) {
            $timeout = max(1, (int) $params['pending_timeout_hours']);
            update_option('mikroplaneta_booking_pending_timeout_hours', $timeout);
        }
        
        if (isset($params['auto_expire_pending'])) {
            update_option('mikroplaneta_booking_auto_expire_pending', (bool) $params['auto_expire_pending']);
        }
        
        if (isset($params['require_payment_confirmation'])) {
            update_option('mikroplaneta_booking_require_payment_confirmation', (bool) $params['require_payment_confirmation']);
        }
        
        return $this->get_settings($request);
    }
    
    /**
     * Check if user has permission to manage settings
     */
    public function check_permission(): bool {
        return current_user_can('manage_options');
    }
}
