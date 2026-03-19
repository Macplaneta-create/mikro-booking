<?php
/**
 * Frontend Controller
 *
 * Handles shortcodes and frontend assets
 *
 * @package MikroPlaneta\Booking
 * @since 1.0.0
 */

namespace MikroPlaneta\Booking\Core;

use MikroPlaneta\Booking\Core\Repositories\RoomRepository;
use MikroPlaneta\Booking\Core\Repositories\BedRepository;

if (!defined('ABSPATH')) {
    exit;
}

class Frontend {

    /**
     * Initialize frontend hooks
     */
    public function __construct() {
        add_shortcode('mikroplaneta_booking', [$this, 'render_booking_widget']);
        add_shortcode('mikroplaneta_room_card', [$this, 'render_booking_widget']);
        add_shortcode('mikroplaneta_availability_calendar', [$this, 'render_availability_calendar']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_assets']);
    }

    /**
     * Render the booking widget shortcode
     */
    public function render_booking_widget($atts): string {
        $atts = shortcode_atts([
            'room_id' => 0,
            'title' => '',
        ], $atts, 'mikroplaneta_booking');

        $room_id = max(0, (int) $atts['room_id']);
        $title = sanitize_text_field((string) $atts['title']);
        $widget_id = function_exists('wp_unique_id')
            ? wp_unique_id('mp-booking-widget-')
            : ('mp-booking-widget-' . uniqid());

        $captcha_provider = (string) get_option('mikroplaneta_booking_captcha_provider', 'recaptcha_v3');
        if ($captcha_provider === 'recaptcha_v3') {
            $site_key = trim((string) get_option('mikroplaneta_booking_recaptcha_site_key', ''));
            if ($site_key !== '') {
                wp_enqueue_script(
                    'google-recaptcha-v3',
                    'https://www.google.com/recaptcha/api.js?render=' . rawurlencode($site_key),
                    [],
                    null,
                    true
                );
            }
        } elseif ($captcha_provider === 'hcaptcha') {
            $site_key = trim((string) get_option('mikroplaneta_booking_hcaptcha_site_key', ''));
            if ($site_key !== '') {
                wp_enqueue_script(
                    'hcaptcha-js',
                    'https://js.hcaptcha.com/1/api.js',
                    [],
                    null,
                    true
                );
            }
        }

        // Enqueue simple widget (new version without bed selection)
        wp_enqueue_script('mikroplaneta-booking-widget');
        wp_enqueue_style('mikroplaneta-booking-widget');

        $widget_settings = [
            'roomId' => $room_id,
            'title' => $title,
            'hotelName' => get_option('mikroplaneta_booking_hotel_name', get_bloginfo('name')),
            'privacyPolicyUrl' => get_privacy_policy_url() ?: '#',
            'termsUrl' => get_option('mikroplaneta_booking_terms_url', '#'),
        ];

        return '<div id="' . esc_attr($widget_id) . '" class="mp-booking-widget-container" data-mp-settings="' . esc_attr(wp_json_encode($widget_settings)) . '"></div>';
    }

    /**
     * Render simplified public availability calendar/list widget.
     *
     * Usage: [mikroplaneta_availability_calendar]
     */
    public function render_availability_calendar($atts): string {
        $atts = shortcode_atts([
            'title' => __('Sprawdź dostępność pokoi i domków', 'mikroplaneta-booking'),
            'booking_title' => __('Rezerwacja', 'mikroplaneta-booking'),
        ], $atts, 'mikroplaneta_availability_calendar');

        $widget_id = function_exists('wp_unique_id')
            ? wp_unique_id('mp-availability-widget-')
            : ('mp-availability-widget-' . uniqid());

        wp_enqueue_script('mikroplaneta-booking-widget');
        wp_enqueue_style('mikroplaneta-booking-widget');
        wp_enqueue_script('mikroplaneta-availability-calendar');
        wp_enqueue_style('mikroplaneta-availability-calendar');

        $widget_settings = [
            'title' => sanitize_text_field((string) $atts['title']),
            'bookingTitle' => sanitize_text_field((string) $atts['booking_title']),
            'rooms' => $this->get_public_rooms_for_availability(),
        ];

        return '<div id="' . esc_attr($widget_id) . '" class="mp-availability-widget-container" data-mp-settings="' . esc_attr(wp_json_encode($widget_settings)) . '"></div>';
    }

    /**
     * Get active rooms with total places metadata for public availability widget.
     */
    private function get_public_rooms_for_availability(): array {
        $room_repo = new RoomRepository();
        $bed_repo = new BedRepository();
        $bed_place_repo = new \MikroPlaneta\Booking\Core\Repositories\BedPlaceRepository();

        $rooms = $room_repo->all([
            'status' => 'active',
            'limit' => 200,
        ]);

        $room_type_labels = [
            'standard' => __('Pokój standard', 'mikroplaneta-booking'),
            'deluxe' => __('Pokój deluxe', 'mikroplaneta-booking'),
            'suite' => __('Apartament', 'mikroplaneta-booking'),
            'cabin' => __('Domek', 'mikroplaneta-booking'),
            'dormitory' => __('Pokój wieloosobowy', 'mikroplaneta-booking'),
        ];

        $result = [];

        foreach ($rooms as $room) {
            if (!$room || empty($room->id)) {
                continue;
            }

            $beds = $bed_repo->findActiveByRoom((int) $room->id);
            $total_places = 0;
            $beds_count = 0;

            foreach ($beds as $bed) {
                $beds_count++;
                if ($bed_place_repo->exists()) {
                    $bed_place_repo->ensureDefaultPlacesForBed((int) $bed->id, (string) ($bed->bed_type ?? 'single'));
                    $bed_capacity = $bed_place_repo->getBedCapacity((int) $bed->id);
                    $total_places += $bed_capacity > 0 ? $bed_capacity : (((string) ($bed->bed_type ?? 'single') === 'bunk') ? 2 : 1);
                } else {
                    $total_places += ((string) ($bed->bed_type ?? 'single') === 'bunk') ? 2 : 1;
                }
            }

            $room_type = (string) ($room->room_type ?? 'standard');

            $result[] = [
                'id' => (int) $room->id,
                'name' => (string) ($room->name ?? ''),
                'room_type' => $room_type,
                'room_type_label' => $room_type_labels[$room_type] ?? __('Pokój', 'mikroplaneta-booking'),
                'total_places' => $total_places,
                'total_beds' => $beds_count,
            ];
        }

        return $result;
    }

    /**
     * Enqueue and register frontend assets
     */
    public function enqueue_assets(): void {
        $css_path = MIKROPLANETA_BOOKING_PLUGIN_DIR . 'public/css/widget.css';
        $js_path = MIKROPLANETA_BOOKING_PLUGIN_DIR . 'public/js/widget.js';
        $simple_js_path = MIKROPLANETA_BOOKING_PLUGIN_DIR . 'public/js/simple-widget.js';
        $card_css_path = MIKROPLANETA_BOOKING_PLUGIN_DIR . 'public/css/room-card.css';
        $card_js_path = MIKROPLANETA_BOOKING_PLUGIN_DIR . 'public/js/room-card-modal.js';
        $availability_css_path = MIKROPLANETA_BOOKING_PLUGIN_DIR . 'public/css/availability-calendar.css';
        $availability_js_path = MIKROPLANETA_BOOKING_PLUGIN_DIR . 'public/js/availability-calendar.js';
        
        $css_version = file_exists($css_path) ? (string) filemtime($css_path) : MIKROPLANETA_BOOKING_VERSION;
        $js_version = file_exists($js_path) ? (string) filemtime($js_path) : MIKROPLANETA_BOOKING_VERSION;
        $simple_js_version = file_exists($simple_js_path) ? (string) filemtime($simple_js_path) : MIKROPLANETA_BOOKING_VERSION;
        $card_css_version = file_exists($card_css_path) ? (string) filemtime($card_css_path) : MIKROPLANETA_BOOKING_VERSION;
        $card_js_version = file_exists($card_js_path) ? (string) filemtime($card_js_path) : MIKROPLANETA_BOOKING_VERSION;
        $availability_css_version = file_exists($availability_css_path) ? (string) filemtime($availability_css_path) : MIKROPLANETA_BOOKING_VERSION;
        $availability_js_version = file_exists($availability_js_path) ? (string) filemtime($availability_js_path) : MIKROPLANETA_BOOKING_VERSION;

        // Main widget
        wp_register_style(
            'mikroplaneta-booking-widget',
            MIKROPLANETA_BOOKING_PLUGIN_URL . 'public/css/widget.css',
            [],
            $css_version
        );

        wp_register_script(
            'mikroplaneta-booking-widget',
            MIKROPLANETA_BOOKING_PLUGIN_URL . 'public/js/simple-widget.js',
            [],
            $simple_js_version,
            true
        );

        // Simple widget (no bed selection) - STANDALONE, no dependencies
        wp_register_script(
            'mikroplaneta-simple-widget',
            MIKROPLANETA_BOOKING_PLUGIN_URL . 'public/js/simple-widget.js',
            [], // No dependencies - standalone script
            $simple_js_version,
            true
        );
        
        // Localize simple widget with same data
        wp_localize_script('mikroplaneta-simple-widget', 'mpBookingData', [
            'apiUrl' => esc_url_raw(rest_url('mikroplaneta/v1')),
            'nonce' => wp_create_nonce('wp_rest'),
            'debug' => (bool) (defined('WP_DEBUG') && WP_DEBUG),
            'captcha' => [
                'provider' => (string) get_option('mikroplaneta_booking_captcha_provider', 'recaptcha_v3'),
                'recaptchaSiteKey' => (string) get_option('mikroplaneta_booking_recaptcha_site_key', ''),
                'hcaptchaSiteKey' => (string) get_option('mikroplaneta_booking_hcaptcha_site_key', ''),
                'recaptchaAction' => 'booking_submit',
            ],
            'i18n' => [
                'loading' => __('Loading...', 'mikroplaneta-booking'),
                'checkIn' => __('Check-in', 'mikroplaneta-booking'),
                'checkOut' => __('Check-out', 'mikroplaneta-booking'),
                'adults' => __('Adults', 'mikroplaneta-booking'),
                'children' => __('Children', 'mikroplaneta-booking'),
                'suggestedBeds' => __('Beds have been automatically assigned to the number of guests.', 'mikroplaneta-booking'),
                'bedRequired' => __('Please select beds from the list.', 'mikroplaneta-booking'),
                'noBeds' => __('No available beds.', 'mikroplaneta-booking'),
                'summaryBase' => __('Selected', 'mikroplaneta-booking'),
                'summaryPlaces' => __('places', 'mikroplaneta-booking'),
                'summaryFor' => __('for', 'mikroplaneta-booking'),
                'summaryGuests' => __('guests', 'mikroplaneta-booking'),
                'summaryNone' => __('Select beds from the list.', 'mikroplaneta-booking'),
                'summaryMissing' => __('Missing places:', 'mikroplaneta-booking'),
                'summaryExtra' => __('Extra places:', 'mikroplaneta-booking'),
                'summaryPerfect' => __('Perfect match.', 'mikroplaneta-booking'),
                'submit' => __('Submit', 'mikroplaneta-booking'),
                'invalidDateRange' => __('Check-out date must be after check-in date.', 'mikroplaneta-booking'),
                'firstName' => __('First Name', 'mikroplaneta-booking'),
                'lastName' => __('Last Name', 'mikroplaneta-booking'),
                'email' => __('Email', 'mikroplaneta-booking'),
                'phone' => __('Phone', 'mikroplaneta-booking'),
                'notes' => __('Notes', 'mikroplaneta-booking'),
                'next' => __('Next', 'mikroplaneta-booking'),
                'back' => __('Back', 'mikroplaneta-booking'),
                'success' => __('Reservation submitted successfully.', 'mikroplaneta-booking'),
                'error' => __('An error occurred. Please try again.', 'mikroplaneta-booking'),
            ]
        ]);

        // Room card styles
        wp_register_style(
            'mikroplaneta-room-card',
            MIKROPLANETA_BOOKING_PLUGIN_URL . 'public/css/room-card.css',
            ['mikroplaneta-booking-widget'],
            $card_css_version
        );

        // Room card modal script
        wp_register_script(
            'mikroplaneta-room-card-modal',
            MIKROPLANETA_BOOKING_PLUGIN_URL . 'public/js/room-card-modal.js',
            ['mikroplaneta-simple-widget'],
            $card_js_version,
            true
        );

        wp_register_style(
            'mikroplaneta-availability-calendar',
            MIKROPLANETA_BOOKING_PLUGIN_URL . 'public/css/availability-calendar.css',
            ['mikroplaneta-booking-widget'],
            $availability_css_version
        );

        wp_register_script(
            'mikroplaneta-availability-calendar',
            MIKROPLANETA_BOOKING_PLUGIN_URL . 'public/js/availability-calendar.js',
            ['mikroplaneta-booking-widget'],
            $availability_js_version,
            true
        );

        // Localize for JS usage
        wp_localize_script('mikroplaneta-booking-widget', 'mpBookingData', [
            'apiUrl' => esc_url_raw(rest_url('mikroplaneta/v1')),
            'nonce' => wp_create_nonce('wp_rest'),
            'debug' => (bool) (defined('WP_DEBUG') && WP_DEBUG),
            'captcha' => [
                'provider' => (string) get_option('mikroplaneta_booking_captcha_provider', 'recaptcha_v3'),
                'recaptchaSiteKey' => (string) get_option('mikroplaneta_booking_recaptcha_site_key', ''),
                'hcaptchaSiteKey' => (string) get_option('mikroplaneta_booking_hcaptcha_site_key', ''),
                'recaptchaAction' => 'booking_submit',
            ],
            'i18n' => [
                'loading' => __('Loading...', 'mikroplaneta-booking'),
                'checkIn' => __('Check-in', 'mikroplaneta-booking'),
                'checkOut' => __('Check-out', 'mikroplaneta-booking'),
                'adults' => __('Adults', 'mikroplaneta-booking'),
                'children' => __('Children', 'mikroplaneta-booking'),
                'suggestedBeds' => __('Beds have been automatically assigned to the number of guests.', 'mikroplaneta-booking'),
                'bedRequired' => __('Please select beds from the list.', 'mikroplaneta-booking'),
                'noBeds' => __('No available beds.', 'mikroplaneta-booking'),
                'summaryBase' => __('Selected', 'mikroplaneta-booking'),
                'summaryPlaces' => __('places', 'mikroplaneta-booking'),
                'summaryFor' => __('for', 'mikroplaneta-booking'),
                'summaryGuests' => __('guests', 'mikroplaneta-booking'),
                'summaryNone' => __('Select beds from the list.', 'mikroplaneta-booking'),
                'summaryMissing' => __('Missing places:', 'mikroplaneta-booking'),
                'summaryExtra' => __('Extra places:', 'mikroplaneta-booking'),
                'summaryPerfect' => __('Perfect match.', 'mikroplaneta-booking'),
                'submit' => __('Submit', 'mikroplaneta-booking'),
                'invalidDateRange' => __('Check-out date must be after check-in date.', 'mikroplaneta-booking'),
                'firstName' => __('First Name', 'mikroplaneta-booking'),
                'lastName' => __('Last Name', 'mikroplaneta-booking'),
                'email' => __('Email', 'mikroplaneta-booking'),
                'phone' => __('Phone', 'mikroplaneta-booking'),
                'notes' => __('Notes', 'mikroplaneta-booking'),
                'next' => __('Next', 'mikroplaneta-booking'),
                'back' => __('Back', 'mikroplaneta-booking'),
                'success' => __('Reservation submitted successfully.', 'mikroplaneta-booking'),
                'error' => __('An error occurred. Please try again.', 'mikroplaneta-booking'),
            ]
        ]);
    }
}
