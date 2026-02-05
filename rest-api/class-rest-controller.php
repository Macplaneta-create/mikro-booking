<?php
/**
 * Base REST Controller
 *
 * Parent class for all REST API controllers
 * Provides common functionality and response formatting
 *
 * @package MikroPlaneta\Booking
 * @since 1.0.0
 */

namespace MikroPlaneta\Booking\RestApi;

use WP_REST_Controller;
use WP_REST_Response;
use WP_Error;

if (!defined('ABSPATH')) {
    exit;
}

abstract class RestController extends WP_REST_Controller {
    
    /**
     * API namespace
     */
    protected $namespace = 'mikroplaneta/v1';
    
    /**
     * Send success response
     */
    protected function success($data, int $status = 200): WP_REST_Response {
        return new WP_REST_Response([
            'success' => true,
            'data' => $data,
        ], $status);
    }
    
    /**
     * Send error response
     */
    protected function error(string $message, int $status = 400, array $data = []): WP_REST_Response {
        return new WP_REST_Response([
            'success' => false,
            'message' => $message,
            'data' => $data,
        ], $status);
    }
    
    /**
     * Convert WP_Error to REST response
     */
    protected function wp_error_to_response(WP_Error $error): WP_REST_Response {
        return $this->error(
            $error->get_error_message(),
            400,
            $error->get_error_data()
        );
    }
    
    /**
     * Check if user has permission
     */
    public function check_permission(): bool {
        return current_user_can('manage_options');
    }
}
