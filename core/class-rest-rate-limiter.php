<?php
/**
 * REST API Rate Limiter
 *
 * Global throttling for plugin REST API namespace.
 *
 * @package MikroPlaneta\Booking
 * @since 1.2.8
 */

namespace MikroPlaneta\Booking\Core;

use WP_Error;
use WP_REST_Request;

if (!defined('ABSPATH')) {
    exit;
}

class RestRateLimiter {
    public function register(): void {
        add_filter('rest_pre_dispatch', [$this, 'enforce'], 10, 3);
    }

    /**
     * @param mixed $result
     * @param mixed $server
     * @param mixed $request
     * @return mixed
     */
    public function enforce($result, $server, $request) {
        if (!($request instanceof WP_REST_Request)) {
            return $result;
        }

        if ($result !== null) {
            return $result;
        }

        $route = (string) $request->get_route();
        if (strpos($route, '/mikroplaneta/v1/') !== 0) {
            return $result;
        }

        $enabled = (bool) get_option('mikroplaneta_booking_rate_limit_enabled', true);
        if (!$enabled) {
            return $result;
        }

        if (function_exists('current_user_can') && current_user_can('manage_options')) {
            return $result;
        }

        $window_seconds = (int) get_option('mikroplaneta_booking_rate_limit_window_seconds', 60);
        $max_requests = (int) get_option('mikroplaneta_booking_rate_limit_max_requests', 120);
        $window_seconds = max(10, $window_seconds);
        $max_requests = max(1, $max_requests);

        $identity = $this->resolve_client_identity();
        if ($identity === '') {
            return $result;
        }

        $key = 'mikroplaneta_booking_rl_' . md5($identity);
        $count = (int) get_transient($key);

        if ($count >= $max_requests) {
            return new WP_Error(
                'mikroplaneta_booking_rate_limit',
                __('Too many API requests. Please try again shortly.', 'mikroplaneta-booking'),
                ['status' => 429]
            );
        }

        set_transient($key, $count + 1, $window_seconds);
        return $result;
    }

    private function resolve_client_identity(): string {
        if (function_exists('get_current_user_id')) {
            $user_id = (int) get_current_user_id();
            if ($user_id > 0) {
                return 'u:' . $user_id;
            }
        }

        if (!empty($_SERVER['REMOTE_ADDR'])) {
            return 'ip:' . sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR']));
        }

        return '';
    }
}

