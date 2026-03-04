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

    private const HOOK_SEND_REMINDERS = 'mikroplaneta_booking_send_reminders';
    private const HOOK_DAILY_BACKUP = 'mikroplaneta_booking_daily_backup';
    private const HOOK_DAILY_CSV_EXPORT = 'mikroplaneta_booking_daily_csv_export';
    private const HOOK_CLEANUP_TEMP_FILES = 'mikroplaneta_booking_cleanup_temp_files';
    private const TASK_LOCK_TTL_SECONDS = 300;
    
    /**
     * Initialize cron handlers
     */
    public static function init(): void {
        // Hook the cron callback
        add_action('mikroplaneta_booking_expire_reservations', [self::class, 'expire_reservations']);
        add_action(self::HOOK_SEND_REMINDERS, [self::class, 'send_reminders']);
        add_action(self::HOOK_DAILY_BACKUP, [self::class, 'send_daily_backup']);
        add_action(self::HOOK_DAILY_CSV_EXPORT, [self::class, 'send_daily_csv_export']);
        add_action(self::HOOK_CLEANUP_TEMP_FILES, [self::class, 'cleanup_temp_files']);
        
        // Schedule daily reminders event if not scheduled
        if (!wp_next_scheduled(self::HOOK_SEND_REMINDERS)) {
            wp_schedule_event(time(), 'daily', self::HOOK_SEND_REMINDERS);
        }

        // Schedule daily backup email if enabled
        self::rescheduleScheduledEvents();
    }

    /**
     * Reschedule daily backup and CSV cron events according to current settings
     */
    public static function rescheduleScheduledEvents(): void {
        // Daily backup email
        wp_clear_scheduled_hook(self::HOOK_DAILY_BACKUP);
        if (get_option('mikroplaneta_backup_email_enabled', false)) {
            $backup_time = (string) get_option('mikroplaneta_backup_email_time', '08:00');
            $timestamp = self::get_timestamp_for_time($backup_time);
            wp_schedule_event($timestamp, 'daily', self::HOOK_DAILY_BACKUP);
        }

        // Daily CSV export
        wp_clear_scheduled_hook(self::HOOK_DAILY_CSV_EXPORT);
        if (get_option('mikroplaneta_csv_export_enabled', false)) {
            $csv_time = (string) get_option('mikroplaneta_csv_export_time', '08:00');
            $timestamp = self::get_timestamp_for_time($csv_time);
            wp_schedule_event($timestamp, 'daily', self::HOOK_DAILY_CSV_EXPORT);
        }

        // Daily cleanup of temp files
        if (!wp_next_scheduled(self::HOOK_CLEANUP_TEMP_FILES)) {
            wp_schedule_event(time(), 'daily', self::HOOK_CLEANUP_TEMP_FILES);
        }
    }

    /**
     * Calculate next timestamp for HH:MM daily schedule
     */
    public static function get_timestamp_for_time(string $time): int {
        $time = trim($time);
        if (!preg_match('/^([0-1]?\d|2[0-3]):([0-5]\d)$/', $time, $matches)) {
            $time = '08:00';
            preg_match('/^([0-1]?\d|2[0-3]):([0-5]\d)$/', $time, $matches);
        }

        $hour = (int) $matches[1];
        $minute = (int) $matches[2];

        $now = current_time('timestamp');
        $target = mktime($hour, $minute, 0, (int) date('n', $now), (int) date('j', $now), (int) date('Y', $now));

        if ($target <= $now) {
            $target = strtotime('+1 day', $target);
        }

        return (int) $target;
    }
    
    /**
     * Handle reservation expiry cron job
     */
    public static function expire_reservations(): void {
        if (!self::acquireTaskLock('expire_reservations')) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[MikroPlaneta Booking] Cron: Skipping expiry task (already running)');
            }
            return;
        }

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
        } finally {
            self::releaseTaskLock('expire_reservations');
        }
    }

    /**
     * Handle daily reminders (check-in / check-out)
     */
    public static function send_reminders(): void {
        if (!self::acquireTaskLock('send_reminders')) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[MikroPlaneta Booking] Cron: Skipping reminders task (already running)');
            }
            return;
        }

        // Check if notifications are enabled
        if (!get_option('mikroplaneta_booking_email_notifications', true)) {
            self::releaseTaskLock('send_reminders');
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
        } finally {
            self::releaseTaskLock('send_reminders');
        }
    }

    /**
     * Send daily backup email
     */
    public static function send_daily_backup(): void {
        if (!self::acquireTaskLock('send_daily_backup')) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[MikroPlaneta Booking] Cron: Skipping daily backup task (already running)');
            }
            return;
        }

        // Check if enabled
        if (!get_option('mikroplaneta_backup_email_enabled', false)) {
            self::releaseTaskLock('send_daily_backup');
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
        } finally {
            self::releaseTaskLock('send_daily_backup');
        }
    }

    /**
     * Send daily CSV export
     */
    public static function send_daily_csv_export(): void {
        if (!self::acquireTaskLock('send_daily_csv_export')) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[MikroPlaneta Booking] Cron: Skipping daily CSV task (already running)');
            }
            return;
        }

        // Check if enabled
        if (!get_option('mikroplaneta_csv_export_enabled', false)) {
            self::releaseTaskLock('send_daily_csv_export');
            return;
        }

        try {
            require_once MIKROPLANETA_BOOKING_PLUGIN_DIR . 'core/services/class-backup-service.php';

            $settings = [
                'csv_email' => get_option('mikroplaneta_csv_export_email', get_option('admin_email')),
                'enabled' => true
            ];

            $backup_service = new \MikroPlaneta\Booking\Core\Services\BackupService();
            $sent = $backup_service->sendDailyCsvExport($settings);

            if ($sent && defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[MikroPlaneta Booking] Cron: Daily CSV export sent');
            }
        } catch (\Exception $e) {
            error_log('[MikroPlaneta Booking] Cron Error (CSV Export): ' . wp_json_encode([
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]));
        } finally {
            self::releaseTaskLock('send_daily_csv_export');
        }
    }

    /**
     * Cleanup temporary files (backup exports and iCal files)
     */
    public static function cleanup_temp_files(): void {
        if (!self::acquireTaskLock('cleanup_temp_files')) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[MikroPlaneta Booking] Cron: Skipping cleanup task (already running)');
            }
            return;
        }

        try {
            require_once MIKROPLANETA_BOOKING_PLUGIN_DIR . 'core/services/class-backup-service.php';
            require_once MIKROPLANETA_BOOKING_PLUGIN_DIR . 'core/services/class-ical-service.php';

            $backup_service = new \MikroPlaneta\Booking\Core\Services\BackupService();
            $ical_service = new \MikroPlaneta\Booking\Core\Services\IcalService();

            $deleted_backup = $backup_service->cleanupOldFiles();
            $deleted_ical = $ical_service->cleanupOldFiles();

            if (($deleted_backup > 0 || $deleted_ical > 0) && defined('WP_DEBUG') && WP_DEBUG) {
                error_log(sprintf(
                    '[MikroPlaneta Booking] Cron: Cleaned temp files (backup: %d, ical: %d)',
                    intval($deleted_backup),
                    intval($deleted_ical)
                ));
            }
        } catch (\Exception $e) {
            error_log('[MikroPlaneta Booking] Cron Error (Cleanup): ' . wp_json_encode([
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]));
        } finally {
            self::releaseTaskLock('cleanup_temp_files');
        }
    }

    /**
     * Acquire in-process task lock to avoid concurrent cron execution.
     */
    private static function acquireTaskLock(string $task): bool {
        $key = self::getTaskLockKey($task);
        if ((bool) get_transient($key)) {
            return false;
        }

        set_transient($key, 1, self::TASK_LOCK_TTL_SECONDS);
        return true;
    }

    /**
     * Release task lock.
     */
    private static function releaseTaskLock(string $task): void {
        $key = self::getTaskLockKey($task);

        if (function_exists('delete_transient')) {
            delete_transient($key);
            return;
        }

        set_transient($key, 0, 1);
    }

    /**
     * Build lock key name for cron task.
     */
    private static function getTaskLockKey(string $task): string {
        return 'mikroplaneta_booking_lock_' . sanitize_key($task);
    }
}

// Initialize cron handlers
CronHandler::init();
