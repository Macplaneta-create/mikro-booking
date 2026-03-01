<?php
/**
 * Room Card Shortcode
 * Simple, clean room card with booking modal
 *
 * Usage: [mikroplaneta_room_card room_id="12"]
 *
 * @package MikroPlaneta\Booking
 */

namespace MikroPlaneta\Booking\Core;

use MikroPlaneta\Booking\Core\Repositories\RoomRepository;
use MikroPlaneta\Booking\Core\Repositories\BedRepository;

if (!defined('ABSPATH')) {
    exit;
}

class RoomCardShortcode {

    public function __construct() {
        add_shortcode('mikroplaneta_room_card', [$this, 'render']);
    }

    public function render($atts): string {
        $atts = shortcode_atts([
            'room_id' => 0,
            'button_label' => __('Rezerwuj', 'mikroplaneta-booking'),
        ], $atts, 'mikroplaneta_room_card');

        $room_id = max(0, (int) $atts['room_id']);
        if ($room_id <= 0) {
            return '';
        }

        // Debug: log enqueue
        error_log('[RoomCard] Enqueue scripts for room_id: ' . $room_id);

        // Enqueue assets - ONLY simple widget and modal (no old widget.js)
        wp_enqueue_style('mikroplaneta-room-card');
        wp_enqueue_style('mikroplaneta-booking-widget'); // CSS only
        wp_enqueue_script('mikroplaneta-simple-widget'); // This loads widget.js as dependency
        wp_enqueue_script('mikroplaneta-room-card-modal');

        // Get room data
        $room_repo = new RoomRepository();
        $bed_repo = new BedRepository();
        $room = $room_repo->find($room_id);
        
        if (!$room || $room->status !== 'active') {
            return '';
        }

        $beds = $bed_repo->findActiveByRoom($room_id);
        $places = 0;
        foreach ($beds as $bed) {
            $places += ($bed->bed_type === 'bunk' ? 2 : 1);
        }

        $room_data = $room->toArray();
        $image_url = !empty($room_data['image_url']) ? esc_url($room_data['image_url']) : '';
        $amenities = is_array($room_data['amenities']) ? array_slice($room_data['amenities'], 0, 5) : [];

        // Room type labels
        $room_type_labels = [
            'standard' => __('Standard', 'mikroplaneta-booking'),
            'deluxe' => __('Deluxe', 'mikroplaneta-booking'),
            'cabin' => __('Domek', 'mikroplaneta-booking'),
            'suite' => __('Apartament', 'mikroplaneta-booking'),
            'dormitory' => __('Pokój wieloosobowy', 'mikroplaneta-booking'),
        ];
        $room_type = $room_type_labels[(string) $room->room_type] ?? __('Pokój', 'mikroplaneta-booking');

        ob_start();
        ?>
        <div class="mp-room-card" data-room-id="<?php echo esc_attr($room_id); ?>">
            <!-- Image -->
            <div class="mp-room-card__image-wrapper">
                <?php if ($image_url): ?>
                    <img src="<?php echo $image_url; ?>" alt="<?php echo esc_attr($room->name); ?>" loading="lazy" />
                <?php else: ?>
                    <div class="mp-room-card__image-placeholder">
                        <svg xmlns="http://www.w3.org/2000/svg" width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1">
                            <rect width="18" height="18" x="3" y="3" rx="2"/>
                            <circle cx="9" cy="9" r="2"/>
                            <path d="m21 15-3.086-3.086a2 2 0 0 0-2.828 0L6 21"/>
                        </svg>
                    </div>
                <?php endif; ?>
                <span class="mp-room-card__badge"><?php echo esc_html($room_type); ?></span>
            </div>

            <!-- Content -->
            <div class="mp-room-card__content">
                <h3 class="mp-room-card__title"><?php echo esc_html($room->name); ?></h3>
                
                <?php if (!empty($room->description)): ?>
                    <p class="mp-room-card__description"><?php echo esc_html(mb_substr($room->description, 0, 120)); ?><?php echo strlen($room->description) > 120 ? '...' : ''; ?></p>
                <?php endif; ?>

                <!-- Info -->
                <div class="mp-room-card__info">
                    <div class="mp-room-card__info-item">
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                            <circle cx="9" cy="7" r="4"/>
                            <path d="M23 21v-2a4 4 0 0 0-3-3.87"/>
                            <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
                        </svg>
                        <span><?php echo esc_html($places); ?> <?php echo _n('osoba', 'osoby', $places, 'mikroplaneta-booking'); ?></span>
                    </div>
                    
                    <?php if (!empty($beds)): ?>
                        <div class="mp-room-card__info-item">
                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M2 4v16"/>
                                <path d="M2 8h18a2 2 0 0 1 2 2v10"/>
                                <path d="M2 17h20"/>
                                <path d="M6 8v9"/>
                            </svg>
                            <span><?php echo esc_html(count($beds)); ?> <?php echo _n('łóżko', 'łóżka', count($beds), 'mikroplaneta-booking'); ?></span>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Amenities -->
                <?php if (!empty($amenities)): ?>
                    <div class="mp-room-card__amenities">
                        <?php foreach ($amenities as $amenity): ?>
                            <span class="mp-room-card__amenity"><?php echo esc_html($amenity); ?></span>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <!-- Button -->
                <button type="button" class="mp-room-card__btn" data-room-id="<?php echo esc_attr($room_id); ?>" data-room-name="<?php echo esc_attr($room->name); ?>">
                    <?php echo esc_html($atts['button_label']); ?>
                </button>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
}
