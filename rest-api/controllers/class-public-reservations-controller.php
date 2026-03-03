<?php
/**
 * Public Reservations REST Controller
 *
 * Handles frontend reservation requests (pending status)
 *
 * @package MikroPlaneta\Booking
 * @since 1.0.0
 */

namespace MikroPlaneta\Booking\RestApi\Controllers;

use MikroPlaneta\Booking\RestApi\RestController;
use MikroPlaneta\Booking\Core\Services\ReservationService;
use MikroPlaneta\Booking\Core\Services\GuestService;
use MikroPlaneta\Booking\Core\Services\AvailabilityService;
use MikroPlaneta\Booking\Core\Models\Reservation;
use WP_REST_Response;

if (!defined('ABSPATH')) {
    exit;
}

class PublicReservationsController extends RestController {

    private ReservationService $reservation_service;
    private GuestService $guest_service;
    private ?AvailabilityService $availability_service;

    /**
     * Constructor
     */
    public function __construct(
        ReservationService $reservation_service,
        GuestService $guest_service,
        ?AvailabilityService $availability_service = null
    ) {
        $this->reservation_service = $reservation_service;
        $this->guest_service = $guest_service;
        $this->availability_service = $availability_service;
        $this->rest_base = 'public/reservations';
    }

    /**
     * Register routes
     */
    public function register_routes(): void {
        register_rest_route($this->namespace, '/' . $this->rest_base, [
            [
                'methods' => 'POST',
                'callback' => [$this, 'create_request'],
                'permission_callback' => '__return_true',
                'args' => [
                    'guest' => ['required' => true, 'type' => 'object'],
                    'bed_ids' => ['required' => true, 'type' => 'array', 'items' => ['type' => 'integer']],
                    'check_in' => ['required' => true, 'type' => 'string', 'format' => 'date'],
                    'check_out' => ['required' => true, 'type' => 'string', 'format' => 'date'],
                    'adults' => ['type' => 'integer', 'default' => 1],
                    'children' => ['type' => 'integer', 'default' => 0],
                    'notes' => ['type' => 'string'],
                    'captcha_token' => ['required' => false, 'type' => 'string'],
                ],
            ],
        ]);

        register_rest_route($this->namespace, '/public/availability/beds', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'public_available_beds'],
                'permission_callback' => '__return_true',
                'args' => [
                    'check_in' => ['required' => true, 'type' => 'string', 'format' => 'date'],
                    'check_out' => ['required' => true, 'type' => 'string', 'format' => 'date'],
                    'room_id' => ['required' => false, 'type' => 'integer'],
                ],
            ],
        ]);
    }

    /**
     * Create reservation request (pending)
     */
    public function create_request($request): WP_REST_Response {
        $params = $request->get_params();

        if (!$this->enforce_rate_limit()) {
            return $this->error('Too many reservation attempts. Please try again in a few minutes.', 429);
        }

        $captcha_token = isset($params['captcha_token']) ? (string) $params['captcha_token'] : '';
        if (!$this->verify_captcha($captcha_token)) {
            return $this->error('Captcha verification failed', 400);
        }

        $guest_input = isset($params['guest']) && is_array($params['guest']) ? $params['guest'] : [];
        $guest_data = [
            'first_name' => sanitize_text_field($guest_input['first_name'] ?? ''),
            'last_name' => sanitize_text_field($guest_input['last_name'] ?? ''),
            'email' => sanitize_email($guest_input['email'] ?? ''),
            'phone' => sanitize_text_field($guest_input['phone'] ?? ''),
        ];

        if (empty($guest_data['first_name']) || empty($guest_data['last_name']) || empty($guest_data['email'])) {
            return $this->error('Guest first name, last name, and email are required', 400);
        }

        $bed_ids = isset($params['bed_ids']) && is_array($params['bed_ids']) ? array_map('intval', $params['bed_ids']) : [];
        $bed_ids = array_values(array_filter($bed_ids, fn($id) => $id > 0));

        if (empty($bed_ids)) {
            return $this->error('At least one bed must be selected', 400);
        }

        $data = [
            'guest_id' => 0,
            'bed_ids' => $bed_ids,
            'check_in' => sanitize_text_field($params['check_in'] ?? ''),
            'check_out' => sanitize_text_field($params['check_out'] ?? ''),
            'adults' => max(1, (int) ($params['adults'] ?? 1)),
            'children' => max(0, (int) ($params['children'] ?? 0)),
            'notes' => isset($params['notes']) ? sanitize_text_field($params['notes']) : null,
            'status' => Reservation::STATUS_PENDING,
        ];
        
        // Handle GDPR consents
        $consents = isset($params['consents']) && is_array($params['consents']) ? $params['consents'] : [];
        if (!empty($consents)) {
            $data['consents'] = [
                'data_processing' => !empty($consents['data_processing']),
                'terms_accepted' => !empty($consents['terms_accepted']),
                'marketing' => !empty($consents['marketing']),
                'timestamp' => sanitize_text_field($consents['timestamp'] ?? current_time('mysql')),
                'ip_address' => sanitize_text_field($_SERVER['REMOTE_ADDR'] ?? ''),
                'user_agent' => sanitize_text_field($_SERVER['HTTP_USER_AGENT'] ?? ''),
            ];
        }

        try {
            // For public widget: allow multiple reservations per email
            // First try to find existing guest by email
            $existing_guest = $this->guest_service->findByEmail($guest_data['email']);

            if ($existing_guest) {
                // Use existing guest - allow multiple reservations
                $data['guest_id'] = $existing_guest->id;
                $guest = $existing_guest;
            } else {
                // Create new guest
                $guest = $this->guest_service->createGuest($guest_data);
                $data['guest_id'] = $guest->id;
            }

            $reservation = $this->reservation_service->createReservation($data);

            // Log consents after reservation is created
            if (!empty($data['consents'])) {
                do_action('mikroplaneta_booking_consents_given', $reservation->id, $data['consents'], $guest->email);
            }

            // Calculate deposit information
            $deposit_enabled = (bool) get_option('mikroplaneta_booking_deposit_enabled', false);
            $deposit_percent = (int) get_option('mikroplaneta_booking_deposit_percent', 30);
            $timeout_hours = (int) get_option('mikroplaneta_booking_pending_timeout_hours', 48);
            
            // Get total price from reservation
            $total_price = (float) $reservation->total_price;
            $deposit_amount = $deposit_enabled ? ($total_price * $deposit_percent / 100) : 0;
            $payment_deadline = date('Y-m-d H:i:s', strtotime("+{$timeout_hours} hours"));

            // Prepare payment info
            $payment_info = null;
            if ($deposit_enabled) {
                $payment_info = [
                    'account_number' => (string) get_option('mikroplaneta_booking_payment_account', ''),
                    'bank_name' => (string) get_option('mikroplaneta_booking_payment_bank_name', ''),
                    'additional_info' => (string) get_option('mikroplaneta_booking_payment_additional_info', ''),
                    'title' => 'Rezerwacja #' . $reservation->id,
                ];
            }

            return $this->success([
                'reservation_id' => $reservation->id,
                'status' => $reservation->status,
                'total_price' => $total_price,
                'deposit_required' => $deposit_enabled,
                'deposit_amount' => $deposit_amount,
                'deposit_percent' => $deposit_percent,
                'payment_deadline' => $payment_deadline,
                'payment_info' => $payment_info,
                'message' => 'Rezerwacja została utworzona pomyślnie.',
            ], 201);
        } catch (\Exception $e) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[MikroBooking] Public reservation error: ' . $e->getMessage());
            }
            return $this->error('Nie udało się utworzyć rezerwacji. Spróbuj ponownie lub skontaktuj się z recepcją.', 400);
        }
    }

    /**
     * Simulated captcha verification during development
     */
    private function verify_captcha(string $token): bool {
        $provider = (string) get_option('mikroplaneta_booking_captcha_provider', 'recaptcha_v3');
        if ($provider === 'none') {
            return true;
        }

        if ($token === '') {
            return false;
        }

        $environment = function_exists('wp_get_environment_type') ? wp_get_environment_type() : 'production';
        $is_dev_environment = in_array($environment, ['local', 'development'], true);
        $simulate = (bool) apply_filters('mikroplaneta_booking_recaptcha_simulate', false);

        if ($simulate && $is_dev_environment) {
            return true;
        }

        if ($provider === 'hcaptcha') {
            return $this->verify_hcaptcha($token);
        }

        return $this->verify_recaptcha($token);
    }

    private function verify_recaptcha(string $token): bool {
        $secret_key = trim((string) get_option('mikroplaneta_booking_recaptcha_secret_key', ''));
        if ($secret_key === '') {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[MikroBooking] CAPTCHA verification failed: missing recaptcha secret key.');
            }
            return false;
        }

        $body = [
            'secret' => $secret_key,
            'response' => $token,
        ];

        if (!empty($_SERVER['REMOTE_ADDR'])) {
            $body['remoteip'] = sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR']));
        }

        $response = wp_remote_post('https://www.google.com/recaptcha/api/siteverify', [
            'timeout' => 10,
            'body' => $body,
        ]);

        if (is_wp_error($response)) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[MikroBooking] CAPTCHA verification HTTP error: ' . $response->get_error_message());
            }
            return false;
        }

        $status_code = (int) wp_remote_retrieve_response_code($response);
        if ($status_code < 200 || $status_code >= 300) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[MikroBooking] CAPTCHA verification HTTP status: ' . $status_code);
            }
            return false;
        }

        $payload = json_decode(wp_remote_retrieve_body($response), true);
        if (!is_array($payload) || empty($payload['success'])) {
            return false;
        }

        // Optional score check for reCAPTCHA v3
        if (isset($payload['score'])) {
            $min_score = (float) get_option('mikroplaneta_booking_recaptcha_min_score', 0.5);
            $min_score = (float) apply_filters('mikroplaneta_booking_recaptcha_min_score', $min_score);
            if ((float) $payload['score'] < $min_score) {
                return false;
            }
        }

        return true;
    }

    private function verify_hcaptcha(string $token): bool {
        $secret_key = trim((string) get_option('mikroplaneta_booking_hcaptcha_secret_key', ''));
        if ($secret_key === '') {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[MikroBooking] CAPTCHA verification failed: missing hCaptcha secret key.');
            }
            return false;
        }

        $body = [
            'secret' => $secret_key,
            'response' => $token,
        ];

        if (!empty($_SERVER['REMOTE_ADDR'])) {
            $body['remoteip'] = sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR']));
        }

        $response = wp_remote_post('https://hcaptcha.com/siteverify', [
            'timeout' => 10,
            'body' => $body,
        ]);

        if (is_wp_error($response)) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[MikroBooking] hCaptcha verification HTTP error: ' . $response->get_error_message());
            }
            return false;
        }

        $status_code = (int) wp_remote_retrieve_response_code($response);
        if ($status_code < 200 || $status_code >= 300) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[MikroBooking] hCaptcha verification HTTP status: ' . $status_code);
            }
            return false;
        }

        $payload = json_decode(wp_remote_retrieve_body($response), true);
        return is_array($payload) && !empty($payload['success']);
    }

    /**
     * Basic IP-based throttling for public reservation endpoint.
     */
    private function enforce_rate_limit(): bool {
        $ip = $this->get_client_ip();
        if ($ip === '') {
            return true;
        }

        $window_seconds = (int) apply_filters('mikroplaneta_booking_public_rate_limit_window', 10 * MINUTE_IN_SECONDS);
        $max_attempts = (int) apply_filters('mikroplaneta_booking_public_rate_limit_max_attempts', 20);

        $window_seconds = max(60, $window_seconds);
        $max_attempts = max(1, $max_attempts);

        $key = 'mb_public_res_rate_' . md5($ip);
        $attempts = (int) get_transient($key);

        if ($attempts >= $max_attempts) {
            return false;
        }

        set_transient($key, $attempts + 1, $window_seconds);
        return true;
    }

    private function get_client_ip(): string {
        if (!empty($_SERVER['REMOTE_ADDR'])) {
            return sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR']));
        }

        return '';
    }

    public function public_available_beds($request): WP_REST_Response {
        if (!$this->availability_service) {
            return $this->error('Availability service unavailable', 503);
        }

        $check_in = sanitize_text_field((string) $request->get_param('check_in'));
        $check_out = sanitize_text_field((string) $request->get_param('check_out'));
        $room_id = max(0, (int) $request->get_param('room_id'));

        if ($check_in === '' || $check_out === '') {
            return $this->error('check_in and check_out are required', 400);
        }

        try {
            $beds = $room_id > 0
                ? $this->availability_service->findAvailableBedsByRoom($room_id, $check_in, $check_out)
                : $this->availability_service->findAvailableBeds($check_in, $check_out);
            $payload = array_map(static function($bed) {
                return [
                    'id' => (int) ($bed->id ?? 0),
                    'room_id' => (int) ($bed->room_id ?? 0),
                    'bed_number' => (int) ($bed->bed_number ?? 0),
                    'bed_type' => (string) ($bed->bed_type ?? 'single'),
                    'is_active' => (bool) ($bed->is_active ?? true),
                ];
            }, $beds);

            return $this->success($payload);
        } catch (\Throwable $e) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[MikroBooking] Public availability error: ' . $e->getMessage());
            }
            return $this->error('Unable to fetch available beds', 400);
        }
    }
}
