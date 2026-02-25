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
                    'multiplier_single' => ['type' => 'number'],
                    'multiplier_double' => ['type' => 'number'],
                    'multiplier_bunk' => ['type' => 'number'],
                    'multiplier_children' => ['type' => 'number'],
                    'captcha_provider' => ['type' => 'string'],
                    'recaptcha_site_key' => ['type' => 'string'],
                    'recaptcha_secret_key' => ['type' => 'string'],
                    'recaptcha_min_score' => ['type' => 'number'],
                    'hcaptcha_site_key' => ['type' => 'string'],
                    'hcaptcha_secret_key' => ['type' => 'string'],
                    'rate_limit_enabled' => ['type' => 'boolean'],
                    'rate_limit_window_seconds' => ['type' => 'integer', 'minimum' => 10],
                    'rate_limit_max_requests' => ['type' => 'integer', 'minimum' => 1],
                ],
            ],
        ]);

        // Trigger Cron manually (for testing)
        register_rest_route($this->namespace, '/' . $this->rest_base . '/trigger-cron', [
            [
                'methods' => 'POST',
                'callback' => [$this, 'trigger_cron'],
                'permission_callback' => [$this, 'check_permission'],
            ],
        ]);
    }
    
    /**
     * Get all settings
     */
    public function get_settings($request): WP_REST_Response {
        $settings = [
            // Hotel Basic Info
            'hotel_name' => get_option('mikroplaneta_booking_hotel_name', 'Mój Hotel'),
            'check_in_time' => get_option('mikroplaneta_booking_check_in_time', '14:00'),
            'check_out_time' => get_option('mikroplaneta_booking_check_out_time', '11:00'),
            'currency' => get_option('mikroplaneta_booking_currency', 'PLN'),
            'timezone' => get_option('mikroplaneta_booking_timezone', 'Europe/Warsaw'),
            
            // Notifications
            'email_notifications' => (bool) get_option('mikroplaneta_booking_email_notifications', true),
            
            // Workflow Settings
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
            'multiplier_single' => (float) get_option('mikroplaneta_booking_multiplier_single', 1.0),
            'multiplier_double' => (float) get_option('mikroplaneta_booking_multiplier_double', 1.8),
            'multiplier_bunk' => (float) get_option('mikroplaneta_booking_multiplier_bunk', 1.0),
            'multiplier_children' => (float) get_option('mikroplaneta_booking_multiplier_children', 0.5),
            'captcha_provider' => (string) get_option('mikroplaneta_booking_captcha_provider', 'recaptcha_v3'),
            'recaptcha_site_key' => (string) get_option('mikroplaneta_booking_recaptcha_site_key', ''),
            'recaptcha_secret_key' => (string) get_option('mikroplaneta_booking_recaptcha_secret_key', ''),
            'recaptcha_min_score' => (float) get_option('mikroplaneta_booking_recaptcha_min_score', 0.5),
            'hcaptcha_site_key' => (string) get_option('mikroplaneta_booking_hcaptcha_site_key', ''),
            'hcaptcha_secret_key' => (string) get_option('mikroplaneta_booking_hcaptcha_secret_key', ''),
            'rate_limit_enabled' => (bool) get_option('mikroplaneta_booking_rate_limit_enabled', true),
            'rate_limit_window_seconds' => (int) get_option('mikroplaneta_booking_rate_limit_window_seconds', 60),
            'rate_limit_max_requests' => (int) get_option('mikroplaneta_booking_rate_limit_max_requests', 120),
        ];
        
        return $this->success($settings);
    }
    
    /**
     * Update settings
     */
    public function update_settings($request): WP_REST_Response {
        $params = $request->get_params();
        
        // Hotel Basic Info
        if (isset($params['hotel_name'])) {
            update_option('mikroplaneta_booking_hotel_name', sanitize_text_field($params['hotel_name']));
        }
        
        if (isset($params['check_in_time'])) {
            update_option('mikroplaneta_booking_check_in_time', sanitize_text_field($params['check_in_time']));
        }
        
        if (isset($params['check_out_time'])) {
            update_option('mikroplaneta_booking_check_out_time', sanitize_text_field($params['check_out_time']));
        }
        
        if (isset($params['currency'])) {
            update_option('mikroplaneta_booking_currency', sanitize_text_field($params['currency']));
        }
        
        if (isset($params['timezone'])) {
            update_option('mikroplaneta_booking_timezone', sanitize_text_field($params['timezone']));
        }
        
        // Notifications
        if (isset($params['email_notifications'])) {
            update_option('mikroplaneta_booking_email_notifications', (bool) $params['email_notifications']);
        }
        
        // Workflow Settings
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

        if (isset($params['multiplier_single'])) {
            update_option('mikroplaneta_booking_multiplier_single', (float) $params['multiplier_single']);
        }
        if (isset($params['multiplier_double'])) {
            update_option('mikroplaneta_booking_multiplier_double', (float) $params['multiplier_double']);
        }
        if (isset($params['multiplier_bunk'])) {
            update_option('mikroplaneta_booking_multiplier_bunk', (float) $params['multiplier_bunk']);
        }
        if (isset($params['multiplier_children'])) {
            update_option('mikroplaneta_booking_multiplier_children', (float) $params['multiplier_children']);
        }
        
        // CAPTCHA
        if (isset($params['captcha_provider'])) {
            $provider = sanitize_text_field((string) $params['captcha_provider']);
            $allowed = ['none', 'recaptcha_v3', 'hcaptcha'];
            if (!in_array($provider, $allowed, true)) {
                $provider = 'recaptcha_v3';
            }
            update_option('mikroplaneta_booking_captcha_provider', $provider);
        }
        if (isset($params['recaptcha_site_key'])) {
            update_option('mikroplaneta_booking_recaptcha_site_key', sanitize_text_field((string) $params['recaptcha_site_key']));
        }
        if (isset($params['recaptcha_secret_key'])) {
            update_option('mikroplaneta_booking_recaptcha_secret_key', sanitize_text_field((string) $params['recaptcha_secret_key']));
        }
        if (isset($params['recaptcha_min_score'])) {
            $score = (float) $params['recaptcha_min_score'];
            $score = max(0.0, min(1.0, $score));
            update_option('mikroplaneta_booking_recaptcha_min_score', $score);
        }
        if (isset($params['hcaptcha_site_key'])) {
            update_option('mikroplaneta_booking_hcaptcha_site_key', sanitize_text_field((string) $params['hcaptcha_site_key']));
        }
        if (isset($params['hcaptcha_secret_key'])) {
            update_option('mikroplaneta_booking_hcaptcha_secret_key', sanitize_text_field((string) $params['hcaptcha_secret_key']));
        }

        // Global REST API Rate Limiting
        if (isset($params['rate_limit_enabled'])) {
            update_option('mikroplaneta_booking_rate_limit_enabled', (bool) $params['rate_limit_enabled']);
        }
        if (isset($params['rate_limit_window_seconds'])) {
            $window = max(10, (int) $params['rate_limit_window_seconds']);
            update_option('mikroplaneta_booking_rate_limit_window_seconds', $window);
        }
        if (isset($params['rate_limit_max_requests'])) {
            $max_requests = max(1, (int) $params['rate_limit_max_requests']);
            update_option('mikroplaneta_booking_rate_limit_max_requests', $max_requests);
        }
        
        return $this->get_settings($request);
    }
    
    /**
     * Manually trigger cron expiry logic
     */
    public function trigger_cron($request): WP_REST_Response {
        try {
            // Include cron handler if not already loaded
            if (!class_exists('\MikroPlaneta\Booking\Core\CronHandler')) {
                require_once MIKROPLANETA_BOOKING_PLUGIN_DIR . 'core/class-cron-handler.php';
            }
            
            // Re-fetch count by executing the static method from CronHandler
            \MikroPlaneta\Booking\Core\CronHandler::expire_reservations();
            
            return $this->success(['message' => 'Uruchomiono sprawdzanie wygasania rezerwacji. Sprawdź logi lub kalendarz.']);
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    /**
     * Check if user has permission to manage settings
     */
    public function check_permission(): bool {
        return current_user_can('manage_options');
    }
}
