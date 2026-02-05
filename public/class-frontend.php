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

if (!defined('ABSPATH')) {
    exit;
}

class Frontend {

    /**
     * Initialize frontend hooks
     */
    public function __construct() {
        add_shortcode('mikroplaneta_booking', [$this, 'render_booking_widget']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_assets']);
    }

    /**
     * Render the booking widget shortcode
     * 
     * @param array $atts Shortcode attributes
     * @return string
     */
    public function render_booking_widget($atts): string {
        // Enqueue registered assets
        wp_enqueue_script('mikroplaneta-booking-widget');
        wp_enqueue_style('mikroplaneta-booking-widget');

        return '<div id="mikroplaneta-booking-widget" class="mp-booking-widget-container">
            <div class="mp-loader">' . __('Loading booking system...', 'mikroplaneta-booking') . '</div>
        </div>';
    }

    /**
     * Enqueue and register frontend assets
     */
    public function enqueue_assets(): void {
        wp_register_style(
            'mikroplaneta-booking-widget',
            MIKROPLANETA_BOOKING_PLUGIN_URL . 'public/css/widget.css',
            [],
            MIKROPLANETA_BOOKING_VERSION
        );

        wp_register_script(
            'mikroplaneta-booking-widget',
            MIKROPLANETA_BOOKING_PLUGIN_URL . 'public/js/widget.js',
            ['jquery'],
            MIKROPLANETA_BOOKING_VERSION,
            true
        );

        // Localize for JS usage
        wp_localize_script('mikroplaneta-booking-widget', 'mpBookingData', [
            'apiUrl' => esc_url_raw(rest_url('mikroplaneta/v1')),
            'nonce' => wp_create_nonce('wp_rest'),
            'i18n' => [
                'loading' => __('Loading...', 'mikroplaneta-booking'),
                'search' => __('Search', 'mikroplaneta-booking'),
                'checkIn' => __('Check-in', 'mikroplaneta-booking'),
                'checkOut' => __('Check-out', 'mikroplaneta-booking'),
            ]
        ]);
    }
}
