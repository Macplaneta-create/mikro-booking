<?php
/**
 * Main Plugin Class
 *
 * Singleton pattern - orchestrates plugin initialization
 * Registers hooks, loads dependencies, initializes services
 *
 * @package MikroPlaneta\Booking
 * @since 1.0.0
 */

namespace MikroPlaneta\Booking\Core;

if (!defined('ABSPATH')) {
    exit;
}

class Plugin {
    
    /**
     * Singleton instance
     */
    private static ?Plugin $instance = null;
    
    /**
     * Get singleton instance
     */
    public static function get_instance(): Plugin {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Private constructor (singleton)
     */
    private function __construct() {
        $this->load_dependencies();
        $this->define_hooks();
    }
    
    /**
     * Load required dependencies
     */
    private function load_dependencies(): void {
        $dir = MIKROPLANETA_BOOKING_PLUGIN_DIR;
        
        // 1. Core Interfaces & Base Classes
        require_once $dir . 'core/repositories/interface-repository.php';
        require_once $dir . 'rest-api/class-rest-controller.php';
        
        // 1b. Database Classes (Required by Repositories)
        require_once $dir . 'core/database/class-schema.php';
        require_once $dir . 'core/database/class-database.php';
        
        // 2. Models
        require_once $dir . 'core/models/class-room.php';
        require_once $dir . 'core/models/class-bed.php';
        require_once $dir . 'core/models/class-guest.php';
        require_once $dir . 'core/models/class-reservation.php';
        require_once $dir . 'core/models/class-reservation-bed.php';
        require_once $dir . 'core/models/class-pricing.php';
        require_once $dir . 'core/models/class-extra-service.php';
        require_once $dir . 'core/models/class-reservation-extra.php';
        require_once $dir . 'core/models/class-bed-place.php';
        
        // 3. Repositories
        require_once $dir . 'core/repositories/class-room-repository.php';
        require_once $dir . 'core/repositories/class-bed-repository.php';
        require_once $dir . 'core/repositories/class-guest-repository.php';
        require_once $dir . 'core/repositories/class-reservation-repository.php';
        require_once $dir . 'core/repositories/class-reservation-bed-repository.php';
        require_once $dir . 'core/repositories/class-pricing-repository.php';
        require_once $dir . 'core/repositories/class-changes-log-repository.php';
        require_once $dir . 'core/repositories/class-extra-service-repository.php';
        require_once $dir . 'core/repositories/class-reservation-extra-repository.php';
        require_once $dir . 'core/repositories/class-bed-place-repository.php';
        
        // 4. Services
        require_once $dir . 'core/services/class-availability-service.php';
        require_once $dir . 'core/services/class-notification-service.php';
        require_once $dir . 'core/services/class-reservation-service.php';
        require_once $dir . 'core/services/class-guest-service.php';
        require_once $dir . 'core/services/class-pricing-service.php';
        require_once $dir . 'core/services/class-reservation-expiry-service.php';
        require_once $dir . 'core/services/class-logger-service.php';
        require_once $dir . 'core/services/class-extra-service-service.php';
        require_once $dir . 'core/services/class-ical-service.php';
        require_once $dir . 'core/services/class-backup-service.php';
        require_once $dir . 'core/services/class-google-calendar-service.php';
        
        // 5. REST API Controllers
        require_once $dir . 'rest-api/controllers/class-rooms-controller.php';
        require_once $dir . 'rest-api/controllers/class-reservations-controller.php';
        require_once $dir . 'rest-api/controllers/class-public-reservations-controller.php';
        require_once $dir . 'rest-api/controllers/class-guests-controller.php';
        require_once $dir . 'rest-api/controllers/class-availability-controller.php';
        require_once $dir . 'rest-api/controllers/class-pricing-controller.php';
        require_once $dir . 'rest-api/controllers/class-dashboard-controller.php';
        require_once $dir . 'rest-api/controllers/class-settings-controller.php';
        require_once $dir . 'rest-api/controllers/class-logs-controller.php';
        require_once $dir . 'rest-api/controllers/class-extras-controller.php';
        require_once $dir . 'rest-api/controllers/class-backup-controller.php';
        require_once $dir . 'rest-api/controllers/class-google-calendar-controller.php';
        
        // 6. Routes
        require_once $dir . 'rest-api/routes.php';
        
        // 7. Admin & Frontend
        require_once $dir . 'core/class-admin.php';
        require_once $dir . 'core/class-rest-rate-limiter.php';
        require_once $dir . 'public/class-frontend.php';
        require_once $dir . 'public/class-room-card-shortcode.php';
        require_once $dir . 'core/class-cron-handler.php';
        require_once $dir . 'core/class-logging-handler.php';
        require_once $dir . 'core/class-consent-handler.php';

        // 8. Utilities intentionally not auto-loaded in production runtime
    }
    
    /**
     * Register WordPress hooks
     */
    private function define_hooks(): void {
        // Load text domain for i18n
        add_action('plugins_loaded', [$this, 'load_textdomain']);

        // Initialize admin
        if (is_admin()) {
            new \MikroPlaneta\Booking\Core\Admin();
        }

        // Initialize frontend
        new \MikroPlaneta\Booking\Core\Frontend();

        // Initialize room card shortcode
        new \MikroPlaneta\Booking\Core\RoomCardShortcode();

        // Initialize consent handler (GDPR)
        new \MikroPlaneta\Booking\Core\Consent_Handler();

        // Global REST API throttling
        (new \MikroPlaneta\Booking\Core\RestRateLimiter())->register();

        // AJAX handlers for iCalendar download
        add_action('wp_ajax_mikroplaneta_download_ical', [$this, 'handle_ical_download']);
        add_action('wp_ajax_mikroplaneta_download_ical_guest', [$this, 'handle_ical_download_guest']);
        add_action('wp_ajax_nopriv_mikroplaneta_download_ical_guest', [$this, 'handle_ical_download_guest']);

        // AJAX handlers for Backup & Export
        add_action('wp_ajax_mikroplaneta_export_csv', [$this, 'handle_export_csv']);
        add_action('wp_ajax_mikroplaneta_export_sql', [$this, 'handle_export_sql']);
        add_action('wp_ajax_mikroplaneta_send_daily_backup', [$this, 'handle_send_daily_backup']);

        // Google Calendar integration (BYOK) – hooks into reservation lifecycle
        $gcal = new \MikroPlaneta\Booking\Core\Services\GoogleCalendarService();
        add_action('mikroplaneta_booking_reservation_created',   [$gcal, 'onReservationCreated'],   10, 2);
        add_action('mikroplaneta_booking_reservation_updated',   [$gcal, 'onReservationUpdated'],   10, 2);
        add_action('mikroplaneta_booking_reservation_cancelled', [$gcal, 'onReservationCancelled'], 10, 2);
    }

    /**
     * Load plugin text domain for translations.
     */
    public function load_textdomain(): void {
        load_plugin_textdomain(
            'mikroplaneta-booking',
            false,
            dirname(plugin_basename(MIKROPLANETA_BOOKING_PLUGIN_FILE)) . '/languages/'
        );
    }

    /**
     * Handle CSV export
     */
    public function handle_export_csv(): void {
        check_admin_referer('mikroplaneta_export_csv');
        
        if (!current_user_can('manage_options')) {
            wp_die('Access denied', 'Error', ['response' => 403]);
        }

        $filters = [
            'date_from' => isset($_GET['date_from']) ? sanitize_text_field($_GET['date_from']) : '',
            'date_to' => isset($_GET['date_to']) ? sanitize_text_field($_GET['date_to']) : '',
            'status' => isset($_GET['status']) ? sanitize_text_field($_GET['status']) : 'all'
        ];

        $backup_service = new \MikroPlaneta\Booking\Core\Services\BackupService();
        $csv = $backup_service->exportReservationsToCsv($filters);
        
        $filename = 'rezerwacje-' . date('Y-m-d') . '.csv';
        
        if (ob_get_level()) {
            ob_end_clean();
        }

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . strlen($csv));
        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');

        echo $csv;
        exit;
    }

    /**
     * Handle SQL database export
     */
    public function handle_export_sql(): void {
        check_admin_referer('mikroplaneta_export_sql');

        if (!current_user_can('manage_options')) {
            wp_die('Access denied', 'Error', ['response' => 403]);
        }

        $only_hotel = isset($_GET['only_hotel']) ? (bool) $_GET['only_hotel'] : true;
        
        $backup_service = new \MikroPlaneta\Booking\Core\Services\BackupService();
        $sql = $backup_service->exportDatabaseToSql($only_hotel);
        
        $filename = 'backup-bazy-' . date('Y-m-d-H-i') . '.sql';
        
        if (ob_get_level()) {
            ob_end_clean();
        }

        header('Content-Type: text/plain; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . strlen($sql));
        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');

        echo $sql;
        exit;
    }

    /**
     * Handle manual daily backup email send
     */
    public function handle_send_daily_backup(): void {
        check_admin_referer('mikroplaneta_send_daily_backup');
        
        if (!current_user_can('manage_options')) {
            wp_die('Access denied', 'Error', ['response' => 403]);
        }

        $settings = [
            'email' => get_option('mikroplaneta_backup_email', get_option('admin_email')),
            'enabled' => get_option('mikroplaneta_backup_email_enabled', false)
        ];

        $backup_service = new \MikroPlaneta\Booking\Core\Services\BackupService();
        $sent = $backup_service->sendDailyBackupEmail($settings);

        if ($sent) {
            wp_send_json_success(['message' => 'Email wysłany pomyślnie']);
        } else {
            wp_send_json_error(['message' => 'Nie udało się wysłać emaila']);
        }
    }

    /**
     * Handle iCalendar file download
     */
    public function handle_ical_download(): void {
        $reservation_id = isset($_GET['reservation_id']) ? intval($_GET['reservation_id']) : 0;
        
        if (!$reservation_id) {
            wp_die('Invalid reservation ID', 'Error', ['response' => 400]);
        }
        
        // Verify nonce
        check_admin_referer('download_ical_' . $reservation_id);
        
        $ical_service = new \MikroPlaneta\Booking\Core\Services\IcalService();
        
        // Generate iCalendar content
        global $wpdb;
        $reservations_table = \MikroPlaneta\Booking\Core\Database\Schema::get_table_name('reservations');
        $guests_table = \MikroPlaneta\Booking\Core\Database\Schema::get_table_name('guests');
        
        $reservation_data = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$reservations_table} WHERE id = %d",
            $reservation_id
        ), ARRAY_A);
        
        if (!$reservation_data) {
            wp_die('Reservation not found', 'Error', ['response' => 404]);
        }
        
        $guest_data = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$guests_table} WHERE id = %d",
            $reservation_data['guest_id']
        ), ARRAY_A);
        
        if (!$guest_data) {
            wp_die('Guest not found', 'Error', ['response' => 404]);
        }
        
        // Create model objects
        $reservation = new \MikroPlaneta\Booking\Core\Models\Reservation($reservation_data);
        $guest = new \MikroPlaneta\Booking\Core\Models\Guest($guest_data);
        
        // Generate and send file
        $ics_content = $ical_service->generateIcs($reservation, $guest);
        $filename = 'rezerwacja-' . $reservation_id . '.ics';
        
        // Save to temp and send
        $filepath = $ical_service->saveIcsFile($ics_content, $reservation_id);
        if ($filepath) {
            $ical_service->sendDownload($filepath, $filename);
        } else {
            wp_die('Failed to generate iCalendar file', 'Error', ['response' => 500]);
        }
    }

    /**
     * Handle public iCalendar download for guests via signed URL
     */
    public function handle_ical_download_guest(): void {
        $reservation_id = isset($_GET['reservation_id']) ? intval($_GET['reservation_id']) : 0;
        $guest_id = isset($_GET['guest_id']) ? intval($_GET['guest_id']) : 0;
        $expires = isset($_GET['expires']) ? intval($_GET['expires']) : 0;
        $token = isset($_GET['token']) ? sanitize_text_field((string) $_GET['token']) : '';

        if ($reservation_id <= 0 || $guest_id <= 0 || $expires <= 0 || $token === '') {
            $this->log_ical_guest_download_event('rejected', [
                'reservation_id' => $reservation_id,
                'guest_id' => $guest_id,
                'reason' => 'invalid_params',
            ]);
            wp_die('Invalid iCalendar request', 'Error', ['response' => 400]);
        }

        $ical_service = new \MikroPlaneta\Booking\Core\Services\IcalService();
        if (!$ical_service->isGuestDownloadRequestValid($reservation_id, $guest_id, $expires, $token)) {
            $this->log_ical_guest_download_event('rejected', [
                'reservation_id' => $reservation_id,
                'guest_id' => $guest_id,
                'reason' => 'invalid_or_expired_signature',
            ]);
            wp_die('Invalid or expired iCalendar link', 'Error', ['response' => 403]);
        }

        global $wpdb;
        $reservations_table = \MikroPlaneta\Booking\Core\Database\Schema::get_table_name('reservations');
        $guests_table = \MikroPlaneta\Booking\Core\Database\Schema::get_table_name('guests');

        $reservation_data = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$reservations_table} WHERE id = %d",
            $reservation_id
        ), ARRAY_A);

        if (!$reservation_data) {
            $this->log_ical_guest_download_event('rejected', [
                'reservation_id' => $reservation_id,
                'guest_id' => $guest_id,
                'reason' => 'reservation_not_found',
            ]);
            wp_die('Reservation not found', 'Error', ['response' => 404]);
        }

        if (intval($reservation_data['guest_id']) !== $guest_id) {
            $this->log_ical_guest_download_event('rejected', [
                'reservation_id' => $reservation_id,
                'guest_id' => $guest_id,
                'reason' => 'guest_mismatch',
            ]);
            wp_die('Invalid reservation guest mapping', 'Error', ['response' => 403]);
        }

        $guest_data = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$guests_table} WHERE id = %d",
            $guest_id
        ), ARRAY_A);

        if (!$guest_data) {
            $this->log_ical_guest_download_event('rejected', [
                'reservation_id' => $reservation_id,
                'guest_id' => $guest_id,
                'reason' => 'guest_not_found',
            ]);
            wp_die('Guest not found', 'Error', ['response' => 404]);
        }

        $reservation = new \MikroPlaneta\Booking\Core\Models\Reservation($reservation_data);
        $guest = new \MikroPlaneta\Booking\Core\Models\Guest($guest_data);

        $ics_content = $ical_service->generateIcs($reservation, $guest);
        $filename = 'rezerwacja-' . $reservation_id . '.ics';

        $filepath = $ical_service->saveIcsFile($ics_content, $reservation_id);
        if ($filepath) {
            $this->log_ical_guest_download_event('success', [
                'reservation_id' => $reservation_id,
                'guest_id' => $guest_id,
                'reason' => '',
            ]);
            $ical_service->sendDownload($filepath, $filename);
        }

        $this->log_ical_guest_download_event('failed', [
            'reservation_id' => $reservation_id,
            'guest_id' => $guest_id,
            'reason' => 'file_generation_failed',
        ]);

        wp_die('Failed to generate iCalendar file', 'Error', ['response' => 500]);
    }

    /**
     * Lightweight audit log for guest iCal link usage.
     */
    private function log_ical_guest_download_event(string $status, array $context): void {
        $payload = [
            'status' => $status,
            'reservation_id' => intval($context['reservation_id'] ?? 0),
            'guest_id' => intval($context['guest_id'] ?? 0),
            'reason' => sanitize_text_field((string) ($context['reason'] ?? '')),
            'ip' => sanitize_text_field((string) ($_SERVER['REMOTE_ADDR'] ?? '')),
            'user_agent' => sanitize_text_field((string) ($_SERVER['HTTP_USER_AGENT'] ?? '')),
        ];

        // Mirror CTA usage in reservation changes log so it is visible in admin history panel.
        $this->log_ical_guest_download_to_changes_log(
            (int) $payload['reservation_id'],
            (string) $payload['status'],
            (string) $payload['reason'],
            (int) $payload['guest_id']
        );

        error_log('[MikroPlaneta Booking] iCal guest download: ' . wp_json_encode($payload));
    }

    /**
     * Write iCal CTA events to reservation changes log for admin visibility.
     */
    private function log_ical_guest_download_to_changes_log(
        int $reservation_id,
        string $status,
        string $reason,
        int $guest_id
    ): void {
        if ($reservation_id <= 0) {
            return;
        }

        try {
            $repository = new \MikroPlaneta\Booking\Core\Repositories\ChangesLogRepository();
            $logger = new \MikroPlaneta\Booking\Core\Services\LoggerService($repository);

            $logger->log($reservation_id, 'updated', [], [
                'event' => 'ical_guest_download',
                'status' => $status,
                'reason' => $reason,
                'guest_id' => $guest_id,
                'source' => 'email_cta',
                'logged_at' => current_time('mysql'),
            ]);
        } catch (\Throwable $e) {
            error_log('[MikroPlaneta Booking] Failed to persist iCal CTA log to changes_log: ' . $e->getMessage());
        }
    }
    
    /**
     * Get plugin version
     */
    public function get_version(): string {
        return MIKROPLANETA_BOOKING_VERSION;
    }
}
