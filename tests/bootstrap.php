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

if (!function_exists('sanitize_textarea_field')) {
    function sanitize_textarea_field($text): string {
        return trim((string) $text);
    }
}

if (!function_exists('__')) {
    function __($text, $domain = null): string {
        return (string) $text;
    }
}

if (!function_exists('_e')) {
    function _e($text, $domain = null): void {
        echo (string) $text;
    }
}

if (!function_exists('esc_html')) {
    function esc_html($text): string {
        return (string) $text;
    }
}

if (!function_exists('esc_url')) {
    function esc_url($url): string {
        return (string) $url;
    }
}

if (!function_exists('wp_kses_post')) {
    function wp_kses_post($content): string {
        return (string) $content;
    }
}

if (!function_exists('get_bloginfo')) {
    function get_bloginfo($show = ''): string {
        if ($show === 'name') {
            return 'MikroPlaneta Test';
        }
        if ($show === 'version') {
            return '6.0';
        }
        return 'MikroPlaneta Test';
    }
}

if (!function_exists('home_url')) {
    function home_url($path = '', $scheme = null): string {
        $base = 'https://example.test';
        $path = ltrim((string) $path, '/');
        return $path === '' ? $base : $base . '/' . $path;
    }
}

if (!function_exists('get_privacy_policy_url')) {
    function get_privacy_policy_url(): string {
        return 'https://example.test/privacy-policy';
    }
}

if (!function_exists('date_i18n')) {
    function date_i18n($format, $timestamp = false, $gmt = false): string {
        $ts = $timestamp ? (int) $timestamp : (int) ($GLOBALS['__mb_current_timestamp'] ?? time());
        return date((string) $format, $ts);
    }
}

if (!function_exists('is_email')) {
    function is_email($email): bool {
        return (bool) filter_var((string) $email, FILTER_VALIDATE_EMAIL);
    }
}

if (!function_exists('trailingslashit')) {
    function trailingslashit($value): string {
        return rtrim((string) $value, '/\\') . '/';
    }
}

if (!function_exists('wp_upload_dir')) {
    function wp_upload_dir($time = null, $create_dir = true, $refresh_cache = false): array {
        $basedir = rtrim(sys_get_temp_dir(), '/\\') . DIRECTORY_SEPARATOR . 'mikro-booking-tests-uploads';
        if (!is_dir($basedir)) {
            @mkdir($basedir, 0777, true);
        }

        return [
            'path' => $basedir,
            'url' => 'https://example.test/wp-content/uploads',
            'subdir' => '',
            'basedir' => $basedir,
            'baseurl' => 'https://example.test/wp-content/uploads',
            'error' => '',
        ];
    }
}

if (!function_exists('wp_mail')) {
    function wp_mail($to, $subject, $message, $headers = '', $attachments = []): bool {
        $GLOBALS['__mb_wp_mail_calls'][] = [
            'to' => $to,
            'subject' => $subject,
            'message' => $message,
            'headers' => $headers,
            'attachments' => $attachments,
        ];

        if (!empty($GLOBALS['__mb_wp_mail_results']) && is_array($GLOBALS['__mb_wp_mail_results'])) {
            $result = array_shift($GLOBALS['__mb_wp_mail_results']);
            return (bool) $result;
        }

        return (bool) ($GLOBALS['__mb_wp_mail_result'] ?? true);
    }
}

if (!function_exists('has_action')) {
    function has_action(string $hook, $callback = false) {
        $actions = $GLOBALS['__mb_actions_registered'] ?? [];
        $count = 0;
        foreach ($actions as $action) {
            if (($action['hook'] ?? null) === $hook) {
                $count++;
            }
        }

        return $count > 0 ? $count : false;
    }
}

if (!function_exists('rest_url')) {
    function rest_url($path = '', $scheme = 'rest'): string {
        $path = ltrim((string) $path, '/');
        return 'https://example.test/wp-json/' . $path;
    }
}

if (!function_exists('wp_create_nonce')) {
    function wp_create_nonce($action = -1): string {
        return 'test-nonce';
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

if (!function_exists('delete_transient')) {
    function delete_transient(string $key): bool {
        if (isset($GLOBALS['__mb_transients'][$key])) {
            unset($GLOBALS['__mb_transients'][$key]);
        }
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

if (!isset($GLOBALS['wpdb'])) {
    $GLOBALS['wpdb'] = new class {
        public string $prefix = 'wp_';

        public function prepare($query, ...$args): string {
            $flatArgs = [];
            foreach ($args as $arg) {
                if (is_array($arg)) {
                    foreach ($arg as $nested) {
                        $flatArgs[] = $nested;
                    }
                } else {
                    $flatArgs[] = $arg;
                }
            }

            if (empty($flatArgs)) {
                return (string) $query;
            }

            return @vsprintf(str_replace(['%d', '%f', '%s'], ['%u', '%F', "'%s'"], (string) $query), $flatArgs) ?: (string) $query;
        }

        public function get_var($query) {
            $queryText = (string) $query;
            if (stripos($queryText, 'GET_LOCK(') !== false) {
                return '1';
            }
            if (stripos($queryText, 'RELEASE_LOCK(') !== false) {
                return '1';
            }
            return null;
        }

        public function query($query): bool {
            return true;
        }
    };
}

if (!function_exists('wp_json_encode')) {
    function wp_json_encode($data, int $options = 0): string {
        return json_encode($data, $options) ?: '';
    }
}

if (!function_exists('get_current_user_id')) {
    function get_current_user_id(): int {
        return $GLOBALS['__mb_current_user_id'] ?? 0;
    }
}

if (!function_exists('nl2br')) {
    function nl2br(string $text): string {
        return str_replace("\n", "<br />\n", $text);
    }
}

require_once __DIR__ . '/../core/database/class-schema.php';

require_once __DIR__ . '/../core/repositories/interface-repository.php';
require_once __DIR__ . '/../core/models/class-reservation.php';
require_once __DIR__ . '/../core/models/class-guest.php';
require_once __DIR__ . '/../core/models/class-bed.php';
require_once __DIR__ . '/../core/models/class-bed-place.php';
require_once __DIR__ . '/../core/models/class-room.php';
require_once __DIR__ . '/../core/models/class-reservation-place.php';
require_once __DIR__ . '/../rest-api/class-rest-controller.php';
require_once __DIR__ . '/../core/repositories/class-reservation-repository.php';
require_once __DIR__ . '/../core/repositories/class-guest-repository.php';
require_once __DIR__ . '/../core/repositories/class-bed-repository.php';
require_once __DIR__ . '/../core/repositories/class-bed-place-repository.php';
require_once __DIR__ . '/../core/repositories/class-room-repository.php';
require_once __DIR__ . '/../core/repositories/class-reservation-bed-repository.php';
require_once __DIR__ . '/../core/repositories/class-reservation-place-repository.php';
require_once __DIR__ . '/../core/services/class-availability-service.php';
require_once __DIR__ . '/../core/services/class-pricing-service.php';
require_once __DIR__ . '/../core/services/class-notification-service.php';
require_once __DIR__ . '/../core/services/class-reservation-service.php';
require_once __DIR__ . '/../core/services/class-guest-service.php';
require_once __DIR__ . '/../core/class-cron-handler.php';
require_once __DIR__ . '/../rest-api/controllers/class-public-reservations-controller.php';
require_once __DIR__ . '/../rest-api/controllers/class-reservations-controller.php';
require_once __DIR__ . '/../rest-api/controllers/class-settings-controller.php';
