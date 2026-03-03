<?php
/**
 * Cron Handler
 *
 * Handles scheduled tasks for the plugin
 *
 * @package MikroPlaneta\Booking
 * @since 1.0.0
 */

namespace MikroPlaneta\Booking\Core;

use MikroPlaneta\Booking\Core\Services\ReservationExpiryService;
use MikroPlaneta\Booking\Core\Repositories\ReservationRepository;

if (!defined('ABSPATH')) {
    exit;
}

class CronHandler {
    
    /**
     * Initialize cron handlers
     */
    public static function init(): void {
        // Hook the cron callback
        add_action('mikroplaneta_booking_expire_reservations', [self::class, 'expire_reservations']);
        add_action('mikroplaneta_booking_send_reminders', [self::class, 'send_reminders']);
        
        // Schedule daily reminders event if not scheduled
        if (!wp_next_scheduled('mikroplaneta_booking_send_reminders')) {
            wp_schedule_event(time(), 'daily', 'mikroplaneta_booking_send_reminders');
        }
    }
    
    /**
     * Handle reservation expiry cron job
     */
    public static function expire_reservations(): void {
        try {
            // Load required files
            require_once MIKROPLANETA_BOOKING_PLUGIN_DIR . 'core/repositories/class-reservation-repository.php';
            require_once MIKROPLANETA_BOOKING_PLUGIN_DIR . 'core/services/class-reservation-expiry-service.php';
            
            $repository = new ReservationRepository();
            $expiry_service = new ReservationExpiryService($repository);
            
            $expired_count = $expiry_service->expirePendingReservations();
            
            if ($expired_count > 0 && defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[MikroPlaneta Booking] Cron: Expired ' . intval($expired_count) . ' pending reservations');
            }
        } catch (\Exception $e) {
            error_log('[MikroPlaneta Booking] Cron Error (Expiry): ' . wp_json_encode([
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]));
        }
    }

    /**
     * Handle daily reminders (check-in / check-out)
     */
    public static function send_reminders(): void {
        // Check if notifications are enabled
        if (!get_option('mikroplaneta_booking_email_notifications', true)) {
            return;
        }

        try {
            require_once MIKROPLANETA_BOOKING_PLUGIN_DIR . 'core/repositories/class-reservation-repository.php';
            require_once MIKROPLANETA_BOOKING_PLUGIN_DIR . 'core/repositories/class-guest-repository.php';
            require_once MIKROPLANETA_BOOKING_PLUGIN_DIR . 'core/services/class-notification-service.php';
            
            $reservation_repo = new \MikroPlaneta\Booking\Core\Repositories\ReservationRepository();
            $guest_repo = new \MikroPlaneta\Booking\Core\Repositories\GuestRepository();
            $notification_service = new \MikroPlaneta\Booking\Core\Services\NotificationService();
            
            // 1. Send Check-in Reminders (for tomorrow)
            $check_in_date = date('Y-m-d', strtotime('+1 day'));
            $incoming = $reservation_repo->findByDate('check_in', $check_in_date, ['confirmed', 'paid']);
            
            foreach ($incoming as $reservation) {
                $guest = $guest_repo->find($reservation->guest_id);
                if ($guest && $guest->email) {
                    $notification_service->sendCheckInReminder($reservation, $guest);
                }
            }
            
            // 2. Send Check-out Reminders (for check-out tomorrow)
            $check_out_date = date('Y-m-d', strtotime('+1 day'));
            $outgoing = $reservation_repo->findByDate('check_out', $check_out_date, ['checked_in']);
            
            foreach ($outgoing as $reservation) {
                $guest = $guest_repo->find($reservation->guest_id);
                if ($guest && $guest->email) {
                    $notification_service->sendCheckOutReminder($reservation, $guest);
                }
            }
            
            if ((count($incoming) > 0 || count($outgoing) > 0) && defined('WP_DEBUG') && WP_DEBUG) {
                error_log(sprintf(
                    '[MikroPlaneta Booking] Cron: Sent %d check-in reminders and %d check-out reminders',
                    count($incoming),
                    count($outgoing)
                ));
            }

        } catch (\Exception $e) {
            error_log('[MikroPlaneta Booking] Cron Error (Reminders): ' . $e->getMessage());
        }
    }

    /**
     * Send daily backup email
     */
    public static function send_daily_backup(): void {
        // Check if enabled
        if (!get_option('mikroplaneta_backup_email_enabled', false)) {
            return;
        }

        try {
            require_once MIKROPLANETA_BOOKING_PLUGIN_DIR . 'core/services/class-backup-service.php';

            $settings = [
                'email' => get_option('mikroplaneta_backup_email', get_option('admin_email')),
                'enabled' => true
            ];

            $backup_service = new \MikroPlaneta\Booking\Core\Services\BackupService();
            $sent = $backup_service->sendDailyBackupEmail($settings);

            if ($sent && defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[MikroPlaneta Booking] Cron: Daily backup email sent');
            }
        } catch (\Exception $e) {
            error_log('[MikroPlaneta Booking] Cron Error (Daily Backup): ' . wp_json_encode([
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]));
        }
    }
}

// Initialize cron handlers
CronHandler::init();
