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

        wp_enqueue_script('mikroplaneta-booking-widget');
        wp_enqueue_style('mikroplaneta-booking-widget');

        $widget_settings = [
            'roomId' => $room_id,
            'title' => $title,
        ];

        return '<div id="' . esc_attr($widget_id) . '" class="mp-booking-widget-container" data-mp-settings="' . esc_attr(wp_json_encode($widget_settings)) . '">
            <div class="mp-loader">' . __('Loading booking system...', 'mikroplaneta-booking') . '</div>
        </div>';
    }

    /**
     * Enqueue frontend assets
     */
    public function enqueue_assets(): void {
        $css_path = MIKROPLANETA_BOOKING_PLUGIN_DIR . 'public/css/widget.css';
        $js_path = MIKROPLANETA_BOOKING_PLUGIN_DIR . 'public/js/widget.js';
        $css_version = file_exists($css_path) ? (string) filemtime($css_path) : MIKROPLANETA_BOOKING_VERSION;
        $js_version = file_exists($js_path) ? (string) filemtime($js_path) : MIKROPLANETA_BOOKING_VERSION;

        wp_register_style(
            'mikroplaneta-booking-widget',
            MIKROPLANETA_BOOKING_PLUGIN_URL . 'public/css/widget.css',
            [],
            $css_version
        );

        wp_register_script(
            'mikroplaneta-booking-widget',
            MIKROPLANETA_BOOKING_PLUGIN_URL . 'public/js/widget.js',
            [],
            $js_version,
            true
        );

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
                'loading' => __('Ładowanie...', 'mikroplaneta-booking'),
                'checkIn' => __('Przyjazd', 'mikroplaneta-booking'),
                'checkOut' => __('Wyjazd', 'mikroplaneta-booking'),
                'adults' => __('Dorośli', 'mikroplaneta-booking'),
                'children' => __('Dzieci', 'mikroplaneta-booking'),
                'suggestedBeds' => __('Łóżka zostały automatycznie dobrane do liczby gości.', 'mikroplaneta-booking'),
                'bedRequired' => __('Wybierz łóżka z listy.', 'mikroplaneta-booking'),
                'noBeds' => __('Brak dostępnych łóżek.', 'mikroplaneta-booking'),
                'summaryBase' => __('Wybrano', 'mikroplaneta-booking'),
                'summaryPlaces' => __('miejsc', 'mikroplaneta-booking'),
                'summaryFor' => __('dla', 'mikroplaneta-booking'),
                'summaryGuests' => __('gości', 'mikroplaneta-booking'),
                'summaryNone' => __('Wybierz łóżka z listy.', 'mikroplaneta-booking'),
                'summaryMissing' => __('Brakuje miejsc:', 'mikroplaneta-booking'),
                'summaryExtra' => __('Nadmiar miejsc:', 'mikroplaneta-booking'),
                'summaryPerfect' => __('Dobór idealny.', 'mikroplaneta-booking'),
                'invalidDateRange' => __('Data wyjazdu musi być późniejsza niż data przyjazdu.', 'mikroplaneta-booking'),
                'submit' => __('Wyślij', 'mikroplaneta-booking'),
            ]
        ]);
    }
}
