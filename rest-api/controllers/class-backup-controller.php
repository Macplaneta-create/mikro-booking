<?php
/**
 * Backup REST Controller
 *
 * Handles backup and export API requests
 *
 * @package MikroPlaneta\Booking
 * @since 1.3.1
 */

namespace MikroPlaneta\Booking\RestApi\Controllers;

use MikroPlaneta\Booking\RestApi\RestController;
use MikroPlaneta\Booking\Core\Services\BackupService;
use WP_REST_Request;
use WP_REST_Response;

if (!defined('ABSPATH')) {
    exit;
}

class BackupController extends RestController {

    private BackupService $backup_service;

    /**
     * Constructor
     */
    public function __construct() {
        $this->backup_service = new BackupService();
        $this->rest_base = 'backup';
    }

    /**
     * Register routes
     */
    public function register_routes(): void {
        // Export CSV
        register_rest_route($this->namespace, '/' . $this->rest_base . '/export/csv', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'export_csv'],
                'permission_callback' => [$this, 'check_permission'],
            ]
        ]);

        // Export SQL
        register_rest_route($this->namespace, '/' . $this->rest_base . '/export/sql', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'export_sql'],
                'permission_callback' => [$this, 'check_permission'],
            ]
        ]);

        // Send Daily Backup Email (test)
        register_rest_route($this->namespace, '/' . $this->rest_base . '/send-daily', [
            [
                'methods' => 'POST',
                'callback' => [$this, 'send_daily'],
                'permission_callback' => [$this, 'check_permission'],
            ]
        ]);
    }

    /**
     * Export reservations to CSV
     */
    public function export_csv($request): WP_REST_Response {
        $filters = [
            'date_from' => $request->get_param('date_from') ?: '',
            'date_to' => $request->get_param('date_to') ?: '',
            'status' => $request->get_param('status') ?: 'all'
        ];

        $csv = $this->backup_service->exportReservationsToCsv($filters);
        
        return new WP_REST_Response($csv, 200, [
            'Content-Type' => 'text/csv; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="rezerwacje-' . date('Y-m-d') . '.csv"'
        ]);
    }

    /**
     * Export database to SQL
     */
    public function export_sql($request): WP_REST_Response {
        $only_hotel = $request->get_param('only_hotel') ?? true;
        
        $sql = $this->backup_service->exportDatabaseToSql((bool) $only_hotel);
        
        return new WP_REST_Response($sql, 200, [
            'Content-Type' => 'text/plain; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="backup-bazy-' . date('Y-m-d-H-i') . '.sql"'
        ]);
    }

    /**
     * Send daily backup email (test)
     */
    public function send_daily($request): WP_REST_Response {
        $recipient = sanitize_email((string) get_option('mikroplaneta_backup_email', get_option('admin_email')));

        if (empty($recipient) || !is_email($recipient)) {
            return $this->error('Ustaw poprawny adres email dla backupu w Ustawieniach.', 400);
        }

        $settings = [
            'email' => $recipient,
            'enabled' => true
        ];

        $sent = $this->backup_service->sendDailyBackupEmail($settings);

        if ($sent) {
            return $this->success([
                'message' => 'Wiadomość została przekazana do wysyłki. Sprawdź skrzynkę odbiorczą oraz logi SMTP/serwera poczty.',
                'recipient' => $recipient,
            ]);
        } else {
            return $this->error('Nie udało się wysłać emaila', 500);
        }
    }

    /**
     * Check permission
     */
    public function check_permission(): bool {
        return current_user_can('manage_options');
    }
}
