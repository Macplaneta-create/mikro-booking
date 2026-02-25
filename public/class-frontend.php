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
        add_shortcode('mikroplaneta_booking_card', [$this, 'render_booking_card']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_assets']);
    }

    /**
     * Render the booking widget shortcode
     * 
     * @param array $atts Shortcode attributes
     * @return string
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

        // Enqueue registered assets
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
     * Render a full room/cabin card with booking widget
     *
     * Usage: [mikroplaneta_booking_card room_id="12"]
     */
    public function render_booking_card($atts): string {
        $atts = shortcode_atts([
            'room_id' => 0,
            'show_widget' => 'yes',
            'button_label' => __('Rezerwuj', 'mikroplaneta-booking'),
        ], $atts, 'mikroplaneta_booking_card');

        $room_id = max(0, (int) $atts['room_id']);
        if ($room_id <= 0) {
            return '';
        }

        $room_repo = new RoomRepository();
        $bed_repo = new BedRepository();
        $room = $room_repo->find($room_id);
        if (!$room || $room->status !== 'active') {
            return '';
        }

        $beds = $bed_repo->findActiveByRoom($room_id);
        $places = 0;
        $bed_labels = [];
        foreach ($beds as $bed) {
            $capacity = $bed->bed_type === 'bunk' ? 2 : 1;
            $places += $capacity;
            $label = ucfirst((string) $bed->bed_type);
            $bed_labels[$label] = ($bed_labels[$label] ?? 0) + 1;
        }

        $room_data = $room->toArray();
        $image_url = !empty($room_data['image_url']) ? esc_url($room_data['image_url']) : '';
        $amenities = is_array($room_data['amenities']) ? $room_data['amenities'] : [];
        $bed_summary = [];
        foreach ($bed_labels as $label => $count) {
            $bed_summary[] = $count . ' x ' . $label;
        }

        $card  = '<div class="mp-booking-room-card" style="max-width:820px;margin:24px auto;padding:20px;border:1px solid #e5e7eb;border-radius:14px;background:#fff;box-shadow:0 8px 24px rgba(0,0,0,0.05);">';
        if ($image_url !== '') {
            $card .= '<img src="' . $image_url . '" alt="' . esc_attr($room->name) . '" style="width:100%;height:auto;max-height:320px;object-fit:cover;border-radius:12px;margin-bottom:14px;" />';
        }
        $card .= '<h3 style="margin:0 0 6px;font-size:1.35rem;color:#111827;">' . esc_html($room->name) . '</h3>';
        if (!empty($room->description)) {
            $card .= '<p style="margin:0 0 10px;color:#4b5563;line-height:1.45;">' . esc_html($room->description) . '</p>';
        }
        $card .= '<div style="display:flex;flex-wrap:wrap;gap:10px;margin-bottom:10px;">';
        $card .= '<span style="font-size:12px;padding:6px 10px;border-radius:999px;background:#eef2ff;color:#3730a3;">' . esc_html__('Type', 'mikroplaneta-booking') . ': ' . esc_html($room->room_type) . '</span>';
        $card .= '<span style="font-size:12px;padding:6px 10px;border-radius:999px;background:#ecfeff;color:#0e7490;">' . esc_html__('Places', 'mikroplaneta-booking') . ': ' . esc_html((string) $places) . '</span>';
        if (!empty($bed_summary)) {
            $card .= '<span style="font-size:12px;padding:6px 10px;border-radius:999px;background:#fef9c3;color:#854d0e;">' . esc_html__('Beds', 'mikroplaneta-booking') . ': ' . esc_html(implode(', ', $bed_summary)) . '</span>';
        }
        $card .= '</div>';
        if (!empty($amenities)) {
            $clean_amenities = array_map(static function($item) {
                return sanitize_text_field((string) $item);
            }, $amenities);
            $card .= '<p style="margin:0 0 12px;color:#374151;"><strong>' . esc_html__('Amenities', 'mikroplaneta-booking') . ':</strong> ' . esc_html(implode(', ', $clean_amenities)) . '</p>';
        }

        $card .= '<div style="margin-top:8px;">';
        $card .= '<button type="button" class="mp-booking-open-widget" data-room-id="' . esc_attr((string) $room_id) . '" style="background:#2563eb;color:#fff;border:none;padding:10px 14px;border-radius:8px;font-weight:600;cursor:pointer;">' . esc_html((string) $atts['button_label']) . '</button>';
        $card .= '</div>';
        $card .= '</div>';

        if ($atts['show_widget'] === 'no') {
            return $card;
        }

        $widget = $this->render_booking_widget([
            'room_id' => $room_id,
            'title' => sprintf(
                /* translators: %s room name */
                __('Reservation: %s', 'mikroplaneta-booking'),
                $room->name
            ),
        ]);

        return $card . $widget;
    }

    /**
     * Enqueue and register frontend assets
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
            ['jquery'],
            $js_version,
            true
        );

        // Localize for JS usage
        wp_localize_script('mikroplaneta-booking-widget', 'mpBookingData', [
            'apiUrl' => esc_url_raw(rest_url('mikroplaneta/v1')),
            'nonce' => wp_create_nonce('wp_rest'),
            'captcha' => [
                'provider' => (string) get_option('mikroplaneta_booking_captcha_provider', 'recaptcha_v3'),
                'recaptchaSiteKey' => (string) get_option('mikroplaneta_booking_recaptcha_site_key', ''),
                'hcaptchaSiteKey' => (string) get_option('mikroplaneta_booking_hcaptcha_site_key', ''),
                'recaptchaAction' => 'booking_submit',
            ],
            'i18n' => [
                'loading' => __('Loading...', 'mikroplaneta-booking'),
                'search' => __('Search', 'mikroplaneta-booking'),
                'checkIn' => __('Check-in', 'mikroplaneta-booking'),
                'checkOut' => __('Check-out', 'mikroplaneta-booking'),
                'firstName' => __('First name', 'mikroplaneta-booking'),
                'lastName' => __('Last name', 'mikroplaneta-booking'),
                'email' => __('Email', 'mikroplaneta-booking'),
                'phone' => __('Phone', 'mikroplaneta-booking'),
                'adults' => __('Adults', 'mikroplaneta-booking'),
                'children' => __('Children', 'mikroplaneta-booking'),
                'availableBeds' => __('Available beds', 'mikroplaneta-booking'),
                'findBeds' => __('Find available beds', 'mikroplaneta-booking'),
                'suggestBeds' => __('Auto-select beds', 'mikroplaneta-booking'),
                'suggestedBeds' => __('Beds were suggested automatically for current guest count.', 'mikroplaneta-booking'),
                'bedRequired' => __('Select at least one bed from availability list.', 'mikroplaneta-booking'),
                'noBeds' => __('No beds available for selected dates.', 'mikroplaneta-booking'),
                'summaryBase' => __('Selected', 'mikroplaneta-booking'),
                'summaryPlaces' => __('places', 'mikroplaneta-booking'),
                'summaryFor' => __('for', 'mikroplaneta-booking'),
                'summaryGuests' => __('guests', 'mikroplaneta-booking'),
                'summaryNone' => __('Choose beds from the list.', 'mikroplaneta-booking'),
                'summaryMissing' => __('Missing places:', 'mikroplaneta-booking'),
                'summaryExtra' => __('Extra places:', 'mikroplaneta-booking'),
                'summaryPerfect' => __('Perfect fit.', 'mikroplaneta-booking'),
                'notes' => __('Notes', 'mikroplaneta-booking'),
                'submit' => __('Send reservation request', 'mikroplaneta-booking'),
                'captchaMissing' => __('Captcha is not configured. Please contact reception.', 'mikroplaneta-booking'),
                'formInvalid' => __('Please fill all required fields.', 'mikroplaneta-booking'),
                'success' => __('Reservation request sent successfully.', 'mikroplaneta-booking'),
                'error' => __('Failed to send reservation request.', 'mikroplaneta-booking'),
            ]
        ]);
    }
}
