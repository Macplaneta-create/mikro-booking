<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__ . '/../');
}

$GLOBALS['__mb_filters'] = [];
$GLOBALS['__mb_actions'] = [];
$GLOBALS['__mb_options'] = [];

if (!class_exists('WP_REST_Controller')) {
    class WP_REST_Controller {
        public string $rest_base = '';
    }
}

if (!class_exists('WP_REST_Response')) {
    class WP_REST_Response {
        private $data;
        private int $status;

        public function __construct($data = null, int $status = 200) {
            $this->data = $data;
            $this->status = $status;
        }

        public function get_data() {
            return $this->data;
        }

        public function get_status(): int {
            return $this->status;
        }
    }
}

if (!class_exists('WP_Error')) {
    class WP_Error {
        private string $message;
        private $data;

        public function __construct(string $code = '', string $message = '', $data = null) {
            $this->message = $message;
            $this->data = $data;
        }

        public function get_error_message(): string {
            return $this->message;
        }

        public function get_error_data() {
            return $this->data;
        }
    }
}

if (!function_exists('add_filter')) {
    function add_filter(string $hook, callable $callback): bool {
        $GLOBALS['__mb_filters'][$hook][] = $callback;
        return true;
    }
}

if (!function_exists('apply_filters')) {
    function apply_filters(string $hook, $value) {
        if (!isset($GLOBALS['__mb_filters'][$hook])) {
            return $value;
        }

        foreach ($GLOBALS['__mb_filters'][$hook] as $callback) {
            $value = $callback($value);
        }

        return $value;
    }
}

if (!function_exists('do_action')) {
    function do_action(string $hook, ...$args): void {
        $GLOBALS['__mb_actions'][] = [$hook, $args];
    }
}

if (!function_exists('sanitize_text_field')) {
    function sanitize_text_field($text): string {
        return trim((string) $text);
    }
}

if (!function_exists('sanitize_email')) {
    function sanitize_email($email): string {
        return trim((string) $email);
    }
}

if (!function_exists('wp_unslash')) {
    function wp_unslash($value) {
        return $value;
    }
}

if (!function_exists('get_option')) {
    function get_option(string $key, $default = false) {
        return $GLOBALS['__mb_options'][$key] ?? $default;
    }
}

if (!function_exists('update_option')) {
    function update_option(string $key, $value): bool {
        $GLOBALS['__mb_options'][$key] = $value;
        return true;
    }
}

if (!function_exists('is_wp_error')) {
    function is_wp_error($value): bool {
        return $value instanceof WP_Error;
    }
}

if (!function_exists('wp_remote_post')) {
    function wp_remote_post(string $url, array $args = []) {
        return $GLOBALS['__mb_remote_post_response'] ?? [
            'response' => ['code' => 200],
            'body' => '{"success":true,"score":0.9}',
        ];
    }
}

if (!function_exists('wp_remote_retrieve_response_code')) {
    function wp_remote_retrieve_response_code($response): int {
        return (int) ($response['response']['code'] ?? 0);
    }
}

if (!function_exists('wp_remote_retrieve_body')) {
    function wp_remote_retrieve_body($response): string {
        return (string) ($response['body'] ?? '');
    }
}

if (!function_exists('register_rest_route')) {
    function register_rest_route($namespace, $route, $args = [], $override = false): bool {
        return true;
    }
}

require_once __DIR__ . '/../core/repositories/interface-repository.php';
require_once __DIR__ . '/../core/models/class-reservation.php';
require_once __DIR__ . '/../core/models/class-guest.php';
require_once __DIR__ . '/../core/models/class-bed.php';
require_once __DIR__ . '/../rest-api/class-rest-controller.php';
require_once __DIR__ . '/../core/repositories/class-reservation-repository.php';
require_once __DIR__ . '/../core/repositories/class-guest-repository.php';
require_once __DIR__ . '/../core/repositories/class-bed-repository.php';
require_once __DIR__ . '/../core/repositories/class-reservation-bed-repository.php';
require_once __DIR__ . '/../core/services/class-availability-service.php';
require_once __DIR__ . '/../core/services/class-pricing-service.php';
require_once __DIR__ . '/../core/services/class-notification-service.php';
require_once __DIR__ . '/../core/services/class-reservation-service.php';
require_once __DIR__ . '/../core/services/class-guest-service.php';
require_once __DIR__ . '/../rest-api/controllers/class-public-reservations-controller.php';
require_once __DIR__ . '/../rest-api/controllers/class-reservations-controller.php';
