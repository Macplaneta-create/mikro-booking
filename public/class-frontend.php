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
            'hotelName' => get_option('mikroplaneta_booking_hotel_name', get_bloginfo('name')),
            'privacyPolicyUrl' => get_privacy_policy_url() ?: '#',
            'termsUrl' => get_option('mikroplaneta_booking_terms_url', '#'),
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
            'show_widget' => 'no', // Changed default to 'no' - modal mode
            'button_label' => __('Sprawdź dostępność', 'mikroplaneta-booking'),
        ], $atts, 'mikroplaneta_booking_card');

        $room_id = max(0, (int) $atts['room_id']);
        if ($room_id <= 0) {
            return '';
        }

        // Enqueue frontend assets
        wp_enqueue_script('mikroplaneta-booking-widget');
        wp_enqueue_style('mikroplaneta-booking-widget');

        $room_repo = new RoomRepository();
        $bed_repo = new BedRepository();
        $room = $room_repo->find($room_id);
        if (!$room || $room->status !== 'active') {
            return '';
        }

        $beds = $bed_repo->findActiveByRoom($room_id);
        $places = 0;
        $bed_labels = [];
        $bed_type_labels = [
            'single' => __('Pojedyncze', 'mikroplaneta-booking'),
            'double' => __('Podwójne', 'mikroplaneta-booking'),
            'bunk' => __('Piętrowe', 'mikroplaneta-booking'),
        ];
        $room_type_labels = [
            'dormitory' => __('Zbiorowy (dormitory)', 'mikroplaneta-booking'),
            'standard' => __('Standard', 'mikroplaneta-booking'),
            'deluxe' => __('Deluxe', 'mikroplaneta-booking'),
            'studio' => __('Studio', 'mikroplaneta-booking'),
            'cabin' => __('Domek', 'mikroplaneta-booking'),
            'suite' => __('Suite', 'mikroplaneta-booking'),
        ];
        foreach ($beds as $bed) {
            $capacity = $bed->bed_type === 'bunk' ? 2 : 1;
            $places += $capacity;
            $label = $bed_type_labels[(string) $bed->bed_type] ?? (string) $bed->bed_type;
            $bed_labels[$label] = ($bed_labels[$label] ?? 0) + 1;
        }

        $room_data = $room->toArray();
        $image_url = !empty($room_data['image_url']) ? esc_url($room_data['image_url']) : '';
        $amenities = is_array($room_data['amenities']) ? $room_data['amenities'] : [];
        $bed_summary = [];
        foreach ($bed_labels as $label => $count) {
            $bed_summary[] = $count . ' x ' . $label;
        }
        $room_type_value = $room_type_labels[(string) $room->room_type] ?? (string) $room->room_type;
        $today = date('Y-m-d');

        ob_start();
        ?>
        <div class="mp-booking-room-card">
            <div class="mp-booking-room-card__image-container">
                <?php if ($image_url !== ''): ?>
                    <img src="<?php echo $image_url; ?>" alt="<?php echo esc_attr($room->name); ?>" class="mp-booking-room-card__img" />
                <?php else: ?>
                    <div class="mp-booking-room-card__img-placeholder">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-image"><rect width="18" height="18" x="3" y="3" rx="2" ry="2"/><circle cx="9" cy="9" r="2"/><path d="m21 15-3.086-3.086a2 2 0 0 0-2.828 0L6 21"/></svg>
                    </div>
                <?php endif; ?>
                <div class="mp-booking-room-card__type-badge">
                    <?php echo esc_html($room_type_value); ?>
                </div>
            </div>

            <div class="mp-booking-room-card__content">
                <div class="mp-booking-room-card__header">
                    <h3 class="mp-booking-room-card__title"><?php echo esc_html($room->name); ?></h3>
                    <div class="mp-booking-room-card__capacity">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-users"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                        <span>Max <?php echo esc_html((string) $places); ?> osób</span>
                    </div>
                </div>
                
                <?php if (!empty($room->description)): ?>
                    <p class="mp-booking-room-card__desc"><?php echo esc_html($room->description); ?></p>
                <?php endif; ?>

                <div class="mp-booking-room-card__details">
                    <?php if (!empty($bed_summary)): ?>
                        <div class="mp-booking-room-card__detail-item">
                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 4v16"/><path d="M2 8h18a2 2 0 0 1 2 2v10"/><path d="M2 17h20"/><path d="M6 8v9"/></svg>
                            <span><?php echo esc_html(implode(', ', $bed_summary)); ?></span>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($amenities)): ?>
                        <div class="mp-booking-room-card__detail-item">
                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2v4"/><path d="m16.2 7.8 2.9-2.9"/><path d="M18 12h4"/><path d="m16.2 16.2 2.9 2.9"/><path d="M12 18v4"/><path d="m4.9 19.1 2.9-2.9"/><path d="M2 12h4"/><path d="m4.9 4.9 2.9 2.9"/></svg>
                            <ul class="mp-booking-room-card__amenities-list">
                                <?php foreach ($amenities as $amenity): ?>
                                    <li>
                                        <?php
                                        // Map amenities to icons
                                        $amenity_icons = [
                                            'wifi' => '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12.55a11 11 0 0 1 14.08 0"/><path d="M1.42 9a16 16 0 0 1 21.16 0"/><path d="M8.53 16.11a6 6 0 0 1 6.95 0"/><line x1="12" y1="20" x2="12.01" y2="20"/></svg>',
                                            'tv' => '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect width="20" height="15" x="2" y="7" rx="2" ry="2"/><polyline points="17 2 12 7 7 2"/></svg>',
                                            'bathroom' => '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 6 6.5 3.5a1.5 1.5 0 0 0-1-1.5C4.01 2 3 2.5 3 5a1.5 1.5 0 0 0 3 0c0-1 .5-1.5 1-2"/><line x1="3" y1="11" x2="21" y2="11"/><path d="M12 21v-6"/><path d="M12 15a2 2 0 1 0 0-4 2 2 0 0 0 0 4Z"/><path d="M21 11v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-7"/><path d="M12 11V6"/></svg>',
                                            'ac' => '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9.59 4.59A2 2 0 1 1 11 8H2m10.59 11.41A2 2 0 1 0 14 16H2m10 4V8m0 12 4-4"/><circle cx="12" cy="12" r="10"/><path d="M12 2v2"/></svg>',
                                            'coffee' => '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 8h1a4 4 0 1 1 0 8h-1"/><path d="M3 8h14v9a4 4 0 0 1-4 4H7a4 4 0 0 1-4-4Z"/><line x1="6" y1="2" x2="6" y2="4"/><line x1="10" y1="2" x2="10" y2="4"/><line x1="14" y1="2" x2="14" y2="4"/></svg>',
                                            'heating' => '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 9a4 4 0 0 0-2 7.5"/><path d="M12 3v2"/><path d="m6.6 18.4-1.4 1.4"/><path d="M20 4v10.54a4 4 0 1 1-4 0V4a2 2 0 0 1 4 0Z"/><path d="M4 13H2"/><path d="M6.34 7.34 4.93 5.93"/></svg>',
                                        ];
                                        $amenity_key = strtolower((string) $amenity);
                                        $icon = $amenity_icons[$amenity_key] ?? $amenity_icons['wifi'];
                                        ?>
                                        <span class="mp-booking-room-card__amenity"><?php echo $icon . ' ' . esc_html((string) $amenity); ?></span>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="mp-booking-room-card__action">
                    <button
                        type="button"
                        class="mp-booking-open-modal mp-booking-room-card__btn"
                        data-room-id="<?php echo esc_attr((string) $room_id); ?>"
                        data-room-name="<?php echo esc_attr($room->name); ?>"
                    >
                        <span><?php echo esc_html((string) $atts['button_label']); ?></span>
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-arrow-right"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg>
                    </button>
                </div>

                <div class="mp-booking-room-card__booking-form">
                    <div class="mp-booking-room-card__form-title">Wybierz termin i liczbę gości</div>
                    <div class="mp-booking-room-card__form-grid">
                        <div class="mp-booking-room-card__form-group">
                            <label class="mp-booking-room-card__label">Przyjazd</label>
                            <input type="date" class="mp-card-check-in mp-booking-room-card__input" data-room-id="<?php echo esc_attr((string) $room_id); ?>" min="<?php echo esc_attr($today); ?>" />
                        </div>
                        <div class="mp-booking-room-card__form-group">
                            <label class="mp-booking-room-card__label">Wyjazd</label>
                            <input type="date" class="mp-card-check-out mp-booking-room-card__input" data-room-id="<?php echo esc_attr((string) $room_id); ?>" min="<?php echo esc_attr($today); ?>" />
                        </div>
                        <div class="mp-booking-room-card__form-group">
                            <label class="mp-booking-room-card__label">Dorośli</label>
                            <input type="number" min="1" value="1" class="mp-card-adults mp-booking-room-card__number-input" data-room-id="<?php echo esc_attr((string) $room_id); ?>" />
                        </div>
                        <div class="mp-booking-room-card__form-group">
                            <label class="mp-booking-room-card__label">Dzieci</label>
                            <input type="number" min="0" value="0" class="mp-card-children mp-booking-room-card__number-input" data-room-id="<?php echo esc_attr((string) $room_id); ?>" />
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php
        $card = ob_get_clean();

        if ($atts['show_widget'] === 'no') {
            // Modal mode - add hidden widget container for modal content
            return $card . '<div class="mp-booking-modal-container mp-hidden" data-room-id="' . esc_attr((string) $room_id) . '" data-room-name="' . esc_attr($room->name) . '"></div>';
        }

        $widget = $this->render_booking_widget([
            'room_id' => $room_id,
            'title' => sprintf(
                /* translators: %s room name */
                __('Reservation: %s', 'mikroplaneta-booking'),
                $room->name
            ),
        ]);

        return $card . '<div class="mp-booking-card-widget">' . $widget . '</div>';
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
            [], // Removed jQuery dependency
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
