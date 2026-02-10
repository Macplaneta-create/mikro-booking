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
use MikroPlaneta\Booking\Core\Models\Reservation;
use WP_REST_Response;

if (!defined('ABSPATH')) {
    exit;
}

class PublicReservationsController extends RestController {

    private ReservationService $reservation_service;
    private GuestService $guest_service;

    /**
     * Constructor
     */
    public function __construct(
        ReservationService $reservation_service,
        GuestService $guest_service
    ) {
        $this->reservation_service = $reservation_service;
        $this->guest_service = $guest_service;
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
                    'captcha_token' => ['required' => true, 'type' => 'string'],
                ],
            ],
        ]);
    }

    /**
     * Create reservation request (pending)
     */
    public function create_request($request): WP_REST_Response {
        $params = $request->get_params();

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

        try {
            $guest = $this->guest_service->findOrCreateGuest($guest_data);
            $data['guest_id'] = $guest->id;

            $reservation = $this->reservation_service->createReservation($data);

            return $this->success([
                'reservation_id' => $reservation->id,
                'status' => $reservation->status,
            ], 201);
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), 400);
        }
    }

    /**
     * Simulated captcha verification during development
     */
    private function verify_captcha(string $token): bool {
        $simulate = (bool) apply_filters(
            'mikroplaneta_booking_recaptcha_simulate',
            defined('WP_DEBUG') && WP_DEBUG
        );

        if ($simulate) {
            return $token !== '';
        }

        // Real verification should be implemented before production use.
        return false;
    }
}
