<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__ . '/../');
}
if (!defined('MINUTE_IN_SECONDS')) {
    define('MINUTE_IN_SECONDS', 60);
}
if (!defined('HOUR_IN_SECONDS')) {
    define('HOUR_IN_SECONDS', 3600);
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

if (!function_exists('add_action')) {
    function add_action(string $hook, callable $callback, int $priority = 10, int $accepted_args = 1): bool {
        $GLOBALS['__mb_actions_registered'][] = [
            'hook' => $hook,
            'callback' => $callback,
            'priority' => $priority,
            'accepted_args' => $accepted_args,
        ];
        return true;
    }
}

if (!function_exists('sanitize_text_field')) {
    function sanitize_text_field($text): string {
        return trim((string) $text);
    }
}

if (!function_exists('sanitize_key')) {
    function sanitize_key($key): string {
        $key = strtolower((string) $key);
        return preg_replace('/[^a-z0-9_\-]/', '', $key) ?? '';
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

if (!function_exists('get_transient')) {
    function get_transient(string $key) {
        return $GLOBALS['__mb_transients'][$key] ?? false;
    }
}

if (!function_exists('set_transient')) {
    function set_transient(string $key, $value, int $expiration = 0): bool {
        $GLOBALS['__mb_transients'][$key] = $value;
        return true;
    }
}

if (!function_exists('wp_get_environment_type')) {
    function wp_get_environment_type(): string {
        return $GLOBALS['__mb_environment_type'] ?? 'development';
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

if (!function_exists('wp_clear_scheduled_hook')) {
    function wp_clear_scheduled_hook(string $hook): bool {
        $GLOBALS['__mb_cleared_hooks'][] = $hook;
        return true;
    }
}

if (!function_exists('wp_next_scheduled')) {
    function wp_next_scheduled(string $hook) {
        return $GLOBALS['__mb_next_scheduled'][$hook] ?? false;
    }
}

if (!function_exists('wp_schedule_event')) {
    function wp_schedule_event(int $timestamp, string $recurrence, string $hook): bool {
        $GLOBALS['__mb_scheduled_events'][] = [
            'timestamp' => $timestamp,
            'recurrence' => $recurrence,
            'hook' => $hook,
        ];
        $GLOBALS['__mb_next_scheduled'][$hook] = $timestamp;
        return true;
    }
}

if (!function_exists('current_time')) {
    function current_time($type, $gmt = 0) {
        if ($type === 'timestamp') {
            return $GLOBALS['__mb_current_timestamp'] ?? time();
        }
        if ($type === 'mysql') {
            return date('Y-m-d H:i:s', $GLOBALS['__mb_current_timestamp'] ?? time());
        }

        return time();
    }
}

require_once __DIR__ . '/../core/repositories/interface-repository.php';
require_once __DIR__ . '/../core/models/class-reservation.php';
require_once __DIR__ . '/../core/models/class-guest.php';
require_once __DIR__ . '/../core/models/class-bed.php';
require_once __DIR__ . '/../core/models/class-room.php';
require_once __DIR__ . '/../rest-api/class-rest-controller.php';
require_once __DIR__ . '/../core/repositories/class-reservation-repository.php';
require_once __DIR__ . '/../core/repositories/class-guest-repository.php';
require_once __DIR__ . '/../core/repositories/class-bed-repository.php';
require_once __DIR__ . '/../core/repositories/class-room-repository.php';
require_once __DIR__ . '/../core/repositories/class-reservation-bed-repository.php';
require_once __DIR__ . '/../core/services/class-availability-service.php';
require_once __DIR__ . '/../core/services/class-pricing-service.php';
require_once __DIR__ . '/../core/services/class-notification-service.php';
require_once __DIR__ . '/../core/services/class-reservation-service.php';
require_once __DIR__ . '/../core/services/class-guest-service.php';
require_once __DIR__ . '/../core/class-cron-handler.php';
require_once __DIR__ . '/../rest-api/controllers/class-public-reservations-controller.php';
require_once __DIR__ . '/../rest-api/controllers/class-reservations-controller.php';
require_once __DIR__ . '/../rest-api/controllers/class-settings-controller.php';
