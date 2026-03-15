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
use MikroPlaneta\Booking\Core\Services\NotificationService;
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
                    
                    // Payment settings
                    'deposit_enabled' => ['type' => 'boolean'],
                    'deposit_percent' => ['type' => 'integer', 'minimum' => 0, 'maximum' => 100],
                    'payment_account' => ['type' => 'string'],
                    'payment_bank_name' => ['type' => 'string'],
                    'payment_additional_info' => ['type' => 'string'],
                    
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

                    // Backup settings
                    'backup_email' => ['type' => 'string', 'format' => 'email'],
                    'backup_email_enabled' => ['type' => 'boolean'],
                    'backup_email_time' => ['type' => 'string', 'pattern' => '^([0-1]?[0-9]|2[0-3]):[0-5][0-9]$'],
                    'backup_retention_hours' => ['type' => 'integer', 'minimum' => 1],
                    'ical_retention_hours' => ['type' => 'integer', 'minimum' => 1],

                    // CSV Export settings
                    'csv_export_email' => ['type' => 'string', 'format' => 'email'],
                    'csv_export_enabled' => ['type' => 'boolean'],
                    'csv_export_time' => ['type' => 'string', 'pattern' => '^([0-1]?[0-9]|2[0-3]):[0-5][0-9]$'],
                    
                    // Onboarding
                    'onboarding_completed' => ['type' => 'boolean'],
                ],
            ],
        ]);

        // Trigger Cron manually (for testing)
        register_rest_route($this->namespace, '/' . $this->rest_base . '/trigger-cron', [
            [
                'methods' => 'POST',
                'callback' => [$this, 'trigger_cron'],
                'permission_callback' => [$this, 'check_permission'],
                'args' => [
                    'task' => ['type' => 'string'],
                ],
            ],
        ]);

        // Operational health check diagnostics
        register_rest_route($this->namespace, '/' . $this->rest_base . '/health-check', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'get_health_check'],
                'permission_callback' => [$this, 'check_permission'],
            ],
        ]);

        // Email templates settings
        register_rest_route($this->namespace, '/' . $this->rest_base . '/email-templates', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'get_email_templates'],
                'permission_callback' => [$this, 'check_permission'],
            ],
            [
                'methods' => 'POST',
                'callback' => [$this, 'update_email_templates'],
                'permission_callback' => [$this, 'check_permission'],
                'args' => [
                    'templates' => ['type' => 'array', 'required' => true],
                ],
            ],
        ]);


        // Notifications delivery log
        register_rest_route($this->namespace, '/' . $this->rest_base . '/notifications-log', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'get_notifications_log'],
                'permission_callback' => [$this, 'check_permission'],
                'args' => [
                    'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 500],
                ],
            ],
        ]);

        // Test email sending
        register_rest_route($this->namespace, '/' . $this->rest_base . '/test-email', [
            [
                'methods' => 'POST',
                'callback' => [$this, 'send_test_email'],
                'permission_callback' => [$this, 'check_permission'],
                'args' => [
                    'template_key' => ['type' => 'string', 'required' => true],
                    'to_email' => ['type' => 'string', 'required' => true],
                ],
            ],
        ]);
        
        // GDPR/RODO settings
        register_rest_route($this->namespace, '/' . $this->rest_base . '/gdpr', [
            [
                'methods' => 'POST',
                'callback' => [$this, 'update_gdpr_settings'],
                'permission_callback' => [$this, 'check_permission'],
                'args' => [
                    'privacy_policy_page_id' => ['type' => 'integer'],
                    'terms_page_id' => ['type' => 'integer'],
                ],
            ],
            [
                'methods' => 'GET',
                'callback' => [$this, 'get_gdpr_settings'],
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
            
            // Payment settings
            'deposit_enabled' => (bool) get_option('mikroplaneta_booking_deposit_enabled', false),
            'deposit_percent' => (int) get_option('mikroplaneta_booking_deposit_percent', 30),
            'payment_account' => (string) get_option('mikroplaneta_booking_payment_account', ''),
            'payment_bank_name' => (string) get_option('mikroplaneta_booking_payment_bank_name', ''),
            'payment_additional_info' => (string) get_option('mikroplaneta_booking_payment_additional_info', ''),
            
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

            // Backup settings
            'backup_email' => (string) get_option('mikroplaneta_backup_email', get_option('admin_email')),
            'backup_email_enabled' => (bool) get_option('mikroplaneta_backup_email_enabled', false),
            'backup_email_time' => (string) get_option('mikroplaneta_backup_email_time', '08:00'),
            'backup_retention_hours' => (int) get_option('mikroplaneta_booking_backup_retention_hours', 24),
            'ical_retention_hours' => (int) get_option('mikroplaneta_booking_ical_retention_hours', 24),

            // CSV Export settings
            'csv_export_email' => (string) get_option('mikroplaneta_csv_export_email', get_option('admin_email')),
            'csv_export_enabled' => (bool) get_option('mikroplaneta_csv_export_enabled', false),
            'csv_export_time' => (string) get_option('mikroplaneta_csv_export_time', '08:00'),
            
            // Onboarding
            'onboarding_completed' => (bool) get_option('mikroplaneta_onboarding_completed', false),
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

        // Payment settings
        if (isset($params['deposit_enabled'])) {
            update_option('mikroplaneta_booking_deposit_enabled', (bool) $params['deposit_enabled']);
        }

        if (isset($params['deposit_percent'])) {
            $percent = max(0, min(100, (int) $params['deposit_percent']));
            update_option('mikroplaneta_booking_deposit_percent', $percent);
        }

        if (isset($params['payment_account'])) {
            update_option('mikroplaneta_booking_payment_account', sanitize_text_field($params['payment_account']));
        }

        if (isset($params['payment_bank_name'])) {
            update_option('mikroplaneta_booking_payment_bank_name', sanitize_text_field($params['payment_bank_name']));
        }

        if (isset($params['payment_additional_info'])) {
            update_option('mikroplaneta_booking_payment_additional_info', sanitize_textarea_field($params['payment_additional_info']));
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

        // Backup settings
        if (isset($params['backup_email'])) {
            update_option('mikroplaneta_backup_email', sanitize_email($params['backup_email']));
        }
        if (isset($params['backup_email_enabled'])) {
            update_option('mikroplaneta_backup_email_enabled', (bool) $params['backup_email_enabled']);
        }
        if (isset($params['backup_email_time'])) {
            update_option('mikroplaneta_backup_email_time', sanitize_text_field($params['backup_email_time']));
        }
        if (isset($params['backup_retention_hours'])) {
            update_option('mikroplaneta_booking_backup_retention_hours', max(1, (int) $params['backup_retention_hours']));
        }
        if (isset($params['ical_retention_hours'])) {
            update_option('mikroplaneta_booking_ical_retention_hours', max(1, (int) $params['ical_retention_hours']));
        }

        // CSV Export settings
        if (isset($params['csv_export_email'])) {
            update_option('mikroplaneta_csv_export_email', sanitize_email($params['csv_export_email']));
        }
        if (isset($params['csv_export_enabled'])) {
            update_option('mikroplaneta_csv_export_enabled', (bool) $params['csv_export_enabled']);
        }
        if (isset($params['csv_export_time'])) {
            update_option('mikroplaneta_csv_export_time', sanitize_text_field($params['csv_export_time']));
        }
        
        // Onboarding
        if (isset($params['onboarding_completed'])) {
            update_option('mikroplaneta_onboarding_completed', (bool) $params['onboarding_completed']);
        }

        // Reschedule related cron events when delivery settings change
        if (class_exists('\\MikroPlaneta\\Booking\\Core\\CronHandler')) {
            \MikroPlaneta\Booking\Core\CronHandler::rescheduleScheduledEvents();
        }

        return $this->get_settings($request);
    }
    
    /**
     * Manually trigger cron expiry logic
     */
    public function trigger_cron($request): WP_REST_Response {
        try {
            $task = sanitize_key((string) $request->get_param('task'));
            if ($task === '') {
                $task = 'expiry';
            }

            if ($task === 'reminders') {
                do_action('mikroplaneta_booking_send_reminders');
                return $this->success([
                    'message' => __('Reminders sending triggered (check-in / check-out). Check notification log and email inbox.', 'mikroplaneta-booking'),
                    'task' => 'reminders',
                ]);
            }

            // Default task: expiry check
            do_action('mikroplaneta_booking_expire_reservations');

            return $this->success([
                'message' => __('Reservation expiry check triggered. Check logs or calendar.', 'mikroplaneta-booking'),
                'task' => 'expiry',
            ]);
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    /**
     * Get operational health-check diagnostics for admin settings page
     */
    public function get_health_check(WP_REST_Request $request): WP_REST_Response {
        $results = [];

        $results[] = $this->build_smtp_health_check();
        $results[] = $this->build_cron_health_check();
        $results[] = $this->build_rest_health_check();
        $results[] = $this->build_storage_health_check();

        $summary = [
            'ok' => 0,
            'warning' => 0,
            'error' => 0,
        ];

        foreach ($results as $row) {
            $status = (string) ($row['status'] ?? 'warning');
            if (!isset($summary[$status])) {
                $status = 'warning';
            }
            $summary[$status]++;
        }

        return $this->success([
            'checked_at' => current_time('mysql'),
            'summary' => $summary,
            'checks' => $results,
        ]);
    }

    /**
     * SMTP/mail transport health check
     */
    private function build_smtp_health_check(): array {
        $admin_email = (string) get_option('admin_email', '');
        $email_valid = is_email($admin_email);
        $has_wp_mail = function_exists('wp_mail');
        $has_phpmailer_hook = function_exists('has_action') && has_action('phpmailer_init');

        $status = 'ok';
        $message = __('Mail transport looks correct.', 'mikroplaneta-booking');

        if (!$has_wp_mail || !$email_valid) {
            $status = 'error';
            $message = __('No correct basic email configuration (wp_mail/admin_email).', 'mikroplaneta-booking');
        } elseif (!$has_phpmailer_hook) {
            $status = 'warning';
            $message = __('No custom SMTP transport detected. Check SMTP plugin configuration.', 'mikroplaneta-booking');
        }

        return [
            'key' => 'smtp',
            'label' => 'SMTP / Email transport',
            'status' => $status,
            'message' => $message,
            'details' => [
                'admin_email' => $admin_email,
                'admin_email_valid' => (bool) $email_valid,
                'wp_mail_available' => $has_wp_mail,
                'phpmailer_hook_detected' => (bool) $has_phpmailer_hook,
            ],
        ];
    }

    /**
     * WP-Cron scheduling health check
     */
    private function build_cron_health_check(): array {
        $is_disabled = defined('DISABLE_WP_CRON') && DISABLE_WP_CRON;
        $expiry_next = wp_next_scheduled('mikroplaneta_booking_expire_reservations');
        $reminders_next = wp_next_scheduled('mikroplaneta_booking_send_reminders');
        $cleanup_next = wp_next_scheduled('mikroplaneta_booking_cleanup_temp_files');

        $status = 'ok';
        $message = 'Zadania Cron są zaplanowane.';

        if ($is_disabled) {
            $status = 'warning';
            $message = 'WP-Cron jest wyłączony (DISABLE_WP_CRON=true). Upewnij się, że działa cron systemowy.';
        }

        if (!$expiry_next || !$reminders_next || !$cleanup_next) {
            $status = $is_disabled ? 'warning' : 'error';
            $message = 'Brakuje części zaplanowanych zadań Cron. Zapisz ustawienia lub aktywuj ponownie plugin.';
        }

        return [
            'key' => 'cron',
            'label' => 'WP-Cron',
            'status' => $status,
            'message' => $message,
            'details' => [
                'disabled' => $is_disabled,
                'next_expiry' => $expiry_next ? gmdate('Y-m-d H:i:s', (int) $expiry_next) : null,
                'next_reminders' => $reminders_next ? gmdate('Y-m-d H:i:s', (int) $reminders_next) : null,
                'next_cleanup' => $cleanup_next ? gmdate('Y-m-d H:i:s', (int) $cleanup_next) : null,
            ],
        ];
    }

    /**
     * REST API base diagnostics
     */
    private function build_rest_health_check(): array {
        $rest_base = function_exists('rest_url') ? rest_url('mikroplaneta/v1') : '';
        $nonce_available = function_exists('wp_create_nonce') ? (bool) wp_create_nonce('wp_rest') : false;

        $status = 'ok';
        $message = 'REST API wygląda poprawnie.';

        if ($rest_base === '') {
            $status = 'error';
            $message = 'Nie udało się wyznaczyć adresu REST API.';
        } elseif (!$nonce_available) {
            $status = 'warning';
            $message = 'Nonce REST może być niedostępny dla części żądań administracyjnych.';
        }

        return [
            'key' => 'rest',
            'label' => 'REST API',
            'status' => $status,
            'message' => $message,
            'details' => [
                'base_url' => $rest_base,
                'nonce_available' => $nonce_available,
            ],
        ];
    }

    /**
     * Writable directories diagnostics for temp exports / ical
     */
    private function build_storage_health_check(): array {
        $upload_dir = wp_upload_dir();
        $base_dir = trailingslashit((string) ($upload_dir['basedir'] ?? '')) . 'mikroplaneta-booking/';
        $upload_error = (string) ($upload_dir['error'] ?? '');

        $status = 'ok';
        $message = __('Temporary directories are ready for writing.', 'mikroplaneta-booking');

        if ($upload_error !== '') {
            $status = 'error';
            $message = __('WordPress reports an upload directory error.', 'mikroplaneta-booking');
        }

        $upload_writable = is_dir((string) ($upload_dir['basedir'] ?? '')) && is_writable((string) ($upload_dir['basedir'] ?? ''));
        $base_exists = is_dir($base_dir);
        $base_writable = $base_exists ? is_writable($base_dir) : $upload_writable;

        if ($status !== 'error' && (!$upload_writable || !$base_writable)) {
            $status = 'error';
            $message = __('No write permissions to plugin temporary directories.', 'mikroplaneta-booking');
        }

        return [
            'key' => 'storage',
            'label' => 'Pliki tymczasowe (backup / iCal)',
            'status' => $status,
            'message' => $message,
            'details' => [
                'upload_basedir' => (string) ($upload_dir['basedir'] ?? ''),
                'upload_writable' => $upload_writable,
                'plugin_temp_dir' => $base_dir,
                'plugin_temp_dir_exists' => $base_exists,
                'plugin_temp_dir_writable' => $base_writable,
                'upload_error' => $upload_error,
            ],
        ];
    }

    /**
     * Get email templates configuration
     */
    public function get_email_templates(WP_REST_Request $request): WP_REST_Response {
        $service = new NotificationService();
        return $this->success($service->getTemplateDefinitions());
    }

    /**
     * Save email templates configuration
     */
    public function update_email_templates(WP_REST_Request $request): WP_REST_Response {
        $templates = $request->get_param('templates');
        if (!is_array($templates)) {
            return $this->error(__('Invalid templates data', 'mikroplaneta-booking'), 400);
        }

        $service = new NotificationService();
        $service->saveTemplateDefinitions($templates);

        return $this->success([
            'message' => __('Email templates saved.', 'mikroplaneta-booking'),
            'templates' => $service->getTemplateDefinitions(),
        ]);
    }

    /**
     * Get notifications log entries
     */
    public function get_notifications_log(WP_REST_Request $request): WP_REST_Response {
        $limit = (int) ($request->get_param('limit') ?: 100);
        $service = new NotificationService();
        $rows = $service->getNotificationHistory($limit);
        return $this->success($rows);
    }

    /**
     * Send test email
     */
    public function send_test_email(WP_REST_Request $request): WP_REST_Response {
        $template_key = sanitize_key((string) $request->get_param('template_key'));
        $to_email = sanitize_email((string) $request->get_param('to_email'));

        if (!$to_email || !is_email($to_email)) {
            return $this->error(__('Invalid email address', 'mikroplaneta-booking'), 400);
        }

        $service = new NotificationService();
        $sent = $service->sendTestEmail($template_key, $to_email);

        if (!$sent) {
            return $this->error(__('Failed to send test email', 'mikroplaneta-booking'), 500);
        }

        return $this->success(['message' => __('Test email sent.', 'mikroplaneta-booking')]);
    }

    /**
     * Update GDPR settings
     */
    public function update_gdpr_settings(WP_REST_Request $request): WP_REST_Response {
        $privacy_policy_page_id = (int) $request->get_param('privacy_policy_page_id');
        $terms_page_id = (int) $request->get_param('terms_page_id');

        update_option('mikroplaneta_booking_privacy_policy_page_id', $privacy_policy_page_id);
        update_option('mikroplaneta_booking_terms_page_id', $terms_page_id);

        return $this->success(['message' => __('GDPR settings saved', 'mikroplaneta-booking')]);
    }

    /**
     * Get GDPR settings
     */
    public function get_gdpr_settings(WP_REST_Request $request): WP_REST_Response {
        $settings = [
            'privacy_policy_page_id' => (int) get_option('mikroplaneta_booking_privacy_policy_page_id', 0),
            'terms_page_id' => (int) get_option('mikroplaneta_booking_terms_page_id', 0),
        ];

        return $this->success($settings);
    }


    /**
     * Check if user has permission to manage settings
     */
    public function check_permission(): bool {
        return current_user_can('manage_options');
    }
}
