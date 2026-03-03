<?php
/**
 * Backup & Export Service
 *
 * Handles CSV export, database backup, and email summaries
 *
 * @package MikroPlaneta\Booking
 * @since 1.3.1
 */

namespace MikroPlaneta\Booking\Core\Services;

use MikroPlaneta\Booking\Core\Database\Schema;

if (!defined('ABSPATH')) {
    exit;
}

class BackupService {

    /**
     * Export reservations to CSV
     *
     * @param array $filters Date range, status, etc.
     * @return string CSV content
     */
    public function exportReservationsToCsv(array $filters = []): string {
        global $wpdb;
        
        $reservations_table = Schema::get_table_name('reservations');
        $guests_table = Schema::get_table_name('guests');
        $reservation_beds_table = Schema::get_table_name('reservation_beds');
        $rooms_table = Schema::get_table_name('rooms');
        $beds_table = Schema::get_table_name('beds');
        
        // Build query
        $where = [];
        $params = [];
        
        // Date range
        if (!empty($filters['date_from'])) {
            $where[] = "r.check_in >= %s";
            $params[] = $filters['date_from'];
        }
        
        if (!empty($filters['date_to'])) {
            $where[] = "r.check_out <= %s";
            $params[] = $filters['date_to'];
        }
        
        // Status
        if (!empty($filters['status']) && $filters['status'] !== 'all') {
            $where[] = "r.status = %s";
            $params[] = $filters['status'];
        }
        
        $where_sql = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
        
        // Query
        $sql = "SELECT 
                    r.id,
                    r.check_in,
                    r.check_out,
                    r.status,
                    r.adults,
                    r.children,
                    r.total_price,
                    r.created_at,
                    g.first_name,
                    g.last_name,
                    g.email,
                    g.phone,
                    GROUP_CONCAT(DISTINCT rm.name SEPARATOR ', ') as rooms,
                    GROUP_CONCAT(DISTINCT rb.bed_id SEPARATOR ', ') as bed_ids
                FROM {$reservations_table} r
                LEFT JOIN {$guests_table} g ON r.guest_id = g.id
                LEFT JOIN {$reservation_beds_table} rb ON r.id = rb.reservation_id
                LEFT JOIN {$beds_table} b ON rb.bed_id = b.id
                LEFT JOIN {$rooms_table} rm ON b.room_id = rm.id
                {$where_sql}
                GROUP BY r.id
                ORDER BY r.check_in DESC";
        
        if (!empty($params)) {
            $sql = $wpdb->prepare($sql, $params);
        }
        
        $results = $wpdb->get_results($sql, ARRAY_A);
        
        // Generate CSV
        $output = fopen('php://temp', 'r+');
        
        // Header
        fputcsv($output, [
            'ID',
            'Gość',
            'Email',
            'Telefon',
            'Check-in',
            'Check-out',
            'Status',
            'Dorośli',
            'Dzieci',
            'Pokoje',
            'Łóżka',
            'Cena (zł)',
            'Utworzono'
        ]);
        
        // Data
        foreach ($results as $row) {
            fputcsv($output, [
                $row['id'],
                $row['first_name'] . ' ' . $row['last_name'],
                $row['email'],
                $row['phone'] ?: 'N/A',
                $row['check_in'],
                $row['check_out'],
                $row['status'],
                $row['adults'],
                $row['children'],
                $row['rooms'] ?: 'N/A',
                $row['bed_ids'] ?: 'N/A',
                number_format($row['total_price'], 2),
                $row['created_at']
            ]);
        }
        
        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);
        
        return $csv;
    }

    /**
     * Export database to SQL
     *
     * @param bool $only_hotel_tables Export only hotel_* tables
     * @return string SQL content
     */
    public function exportDatabaseToSql(bool $only_hotel_tables = true): string {
        global $wpdb;
        
        $tables = $wpdb->get_col('SHOW TABLES');
        
        if ($only_hotel_tables) {
            $tables = array_filter($tables, function($table) {
                return strpos($table, $wpdb->prefix . 'hotel_') !== false;
            });
        }
        
        $sql = "-- MikroPlaneta Booking Database Backup\n";
        $sql .= "-- Generated: " . date('Y-m-d H:i:s') . "\n";
        $sql .= "-- WordPress URL: " . home_url() . "\n\n";
        
        foreach ($tables as $table) {
            $sql .= "--\n";
            $sql .= "-- Table structure for table `{$table}`\n";
            $sql .= "--\n\n";
            
            // CREATE TABLE
            $create = $wpdb->get_row("SHOW CREATE TABLE {$table}", ARRAY_N);
            $sql .= "DROP TABLE IF EXISTS `{$create[0]}`;\n";
            $sql .= $create[1] . ";\n\n";
            
            // INSERT data
            $sql .= "--\n";
            $sql .= "-- Dumping data for table `{$table}`\n";
            $sql .= "--\n\n";
            
            $rows = $wpdb->get_results("SELECT * FROM {$table}", ARRAY_A);
            
            if (!empty($rows)) {
                foreach ($rows as $row) {
                    $values = array_map(function($value) {
                        if ($value === null) {
                            return 'NULL';
                        }
                        return "'" . esc_sql($value) . "'";
                    }, array_values($row));
                    
                    $sql .= "INSERT INTO `{$table}` VALUES (" . implode(', ', $values) . ");\n";
                }
                $sql .= "\n";
            }
        }
        
        return $sql;
    }

    /**
     * Send daily backup email
     *
     * @param array $settings Email settings
     * @return bool Success
     */
    public function sendDailyBackupEmail(array $settings): bool {
        global $wpdb;
        
        $reservations_table = Schema::get_table_name('reservations');
        
        // Get date range
        $date_from = date('Y-m-d', strtotime('-1 day'));
        $date_to = date('Y-m-d');
        
        // Get statistics
        $stats = [
            'total_reservations' => 0,
            'total_revenue' => 0,
            'pending' => 0,
            'confirmed' => 0,
            'checked_in' => 0,
            'cancelled' => 0
        ];
        
        // Total reservations in range
        $stats['total_reservations'] = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$reservations_table} WHERE DATE(created_at) = %s",
            $date_from
        ));
        
        // Total revenue
        $stats['total_revenue'] = (float) $wpdb->get_var($wpdb->prepare(
            "SELECT SUM(total_price) FROM {$reservations_table} WHERE DATE(created_at) = %s",
            $date_from
        ));
        
        // By status
        $status_counts = $wpdb->get_results($wpdb->prepare(
            "SELECT status, COUNT(*) as count, SUM(total_price) as revenue
             FROM {$reservations_table}
             WHERE DATE(created_at) = %s
             GROUP BY status",
            $date_from
        ), ARRAY_A);
        
        foreach ($status_counts as $status) {
            if (isset($stats[$status['status']])) {
                $stats[$status['status']] = (int) $status['count'];
                $stats[$status['status'] . '_revenue'] = (float) $status['revenue'];
            }
        }
        
        // Build email
        $subject = sprintf(
            '[%s] Podsumowanie rezerwacji - %s',
            get_bloginfo('name'),
            date('d.m.Y', strtotime($date_from))
        );
        
        $message = $this->generateDailyEmailHtml($stats, $date_from);
        
        // Headers
        $headers = [
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . get_bloginfo('name') . ' <' . get_option('admin_email') . '>'
        ];
        
        // Send to recipients
        $recipients = is_array($settings['email']) 
            ? implode(',', $settings['email']) 
            : $settings['email'];
        
        $sent = wp_mail($recipients, $subject, $message, $headers);
        
        return $sent;
    }

    /**
     * Send daily CSV export with reservations
     *
     * @param array $settings Email settings
     * @return bool Success
     */
    public function sendDailyCsvExport(array $settings): bool {
        // Generate CSV
        $filters = [
            'date_from' => date('Y-m-d', strtotime('-1 day')),
            'date_to' => date('Y-m-d'),
            'status' => 'all'
        ];
        
        $csv = $this->exportReservationsToCsv($filters);
        
        if (empty($csv)) {
            return false;
        }
        
        // Save CSV to temp file
        $filename = 'rezerwacje-' . date('Y-m-d') . '.csv';
        $filepath = $this->saveFile($csv, $filename, 'backup/csv');
        
        if (!$filepath) {
            return false;
        }
        
        // Build email
        $subject = sprintf(
            '[%s] Eksport rezerwacji CSV - %s',
            get_bloginfo('name'),
            date('d.m.Y', strtotime('-1 day'))
        );
        
        $message = $this->generateCsvEmailHtml($filters['date_from']);
        
        // Headers
        $headers = [
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . get_bloginfo('name') . ' <' . get_option('admin_email') . '>'
        ];
        
        // Attachments
        $attachments = [$filepath];
        
        // Send to recipients
        $recipients = is_array($settings['csv_email']) 
            ? implode(',', $settings['csv_email']) 
            : $settings['csv_email'];
        
        if (empty($recipients)) {
            $recipients = get_option('admin_email');
        }
        
        $sent = wp_mail($recipients, $subject, $message, $headers, $attachments);
        
        // Clean up temp file after sending
        if (file_exists($filepath)) {
            @unlink($filepath);
        }
        
        return $sent;
    }

    /**
     * Generate CSV email HTML
     *
     * @param string $date Date
     * @return string HTML message
     */
    private function generateCsvEmailHtml(string $date): string {
        $hotel_name = get_bloginfo('name');
        $hotel_url = home_url();
        
        ob_start();
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: #007cba; color: white; padding: 20px; text-align: center; }
                .content { margin: 20px 0; padding: 20px; background: #f5f5f5; border-radius: 5px; }
                .footer { margin-top: 30px; padding-top: 20px; border-top: 1px solid #ddd; font-size: 12px; color: #999; }
                .button { display: inline-block; padding: 10px 20px; background: #007cba; color: white; text-decoration: none; border-radius: 5px; }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <h1><?php echo esc_html($hotel_name); ?></h1>
                    <p>Eksport rezerwacji CSV</p>
                </div>
                
                <div class="content">
                    <p>Dzień dobry,</p>
                    <p>W załączniku znajdziesz eksport rezerwacji z dnia <strong><?php echo date('d.m.Y', strtotime($date)); ?></strong>.</p>
                    <p>Plik CSV można otworzyć w programie Excel lub innym arkuszu kalkulacyjnym.</p>
                    
                    <p style="margin-top: 20px;">
                        <a href="<?php echo esc_url($hotel_url); ?>/wp-admin/admin.php?page=mikroplaneta-booking" class="button">
                            Przejdź do Dashboardu
                        </a>
                    </p>
                </div>
                
                <div class="footer">
                    <p>Automatyczna wiadomość z systemu MikroPlaneta Booking</p>
                    <p><a href="<?php echo esc_url($hotel_url); ?>"><?php echo esc_html($hotel_url); ?></a></p>
                </div>
            </div>
        </body>
        </html>
        <?php
        return ob_get_clean();
    }

    /**
     * Generate daily email HTML
     *
     * @param array $stats Statistics
     * @param string $date Date
     * @return string HTML message
     */
    private function generateDailyEmailHtml(array $stats, string $date): string {
        $hotel_name = get_bloginfo('name');
        $hotel_url = home_url();
        
        ob_start();
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: #007cba; color: white; padding: 20px; text-align: center; }
                .stats { margin: 20px 0; }
                .stat-box { background: #f5f5f5; padding: 15px; margin: 10px 0; border-radius: 5px; }
                .stat-number { font-size: 24px; font-weight: bold; color: #007cba; }
                .stat-label { font-size: 14px; color: #666; }
                .footer { margin-top: 30px; padding-top: 20px; border-top: 1px solid #ddd; font-size: 12px; color: #999; }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <h1><?php echo esc_html($hotel_name); ?></h1>
                    <p>Podsumowanie rezerwacji</p>
                </div>
                
                <div class="stats">
                    <h2>Podsumowanie za dzień <?php echo date('d.m.Y', strtotime($date)); ?></h2>
                    
                    <div class="stat-box">
                        <div class="stat-number"><?php echo esc_html($stats['total_reservations']); ?></div>
                        <div class="stat-label">Nowych rezerwacji</div>
                    </div>
                    
                    <div class="stat-box">
                        <div class="stat-number"><?php echo number_format($stats['total_revenue'], 2); ?> zł</div>
                        <div class="stat-label">Przychód</div>
                    </div>
                    
                    <h3>Statusy:</h3>
                    <ul>
                        <li><strong>Oczekujące:</strong> <?php echo esc_html($stats['pending']); ?></li>
                        <li><strong>Potwierdzone:</strong> <?php echo esc_html($stats['confirmed']); ?></li>
                        <li><strong>Zameldowane:</strong> <?php echo esc_html($stats['checked_in']); ?></li>
                        <li><strong>Anulowane:</strong> <?php echo esc_html($stats['cancelled']); ?></li>
                    </ul>
                </div>
                
                <div class="footer">
                    <p>Automatyczna wiadomość z systemu MikroPlaneta Booking</p>
                    <p><a href="<?php echo esc_url($hotel_url); ?>"><?php echo esc_html($hotel_url); ?></a></p>
                </div>
            </div>
        </body>
        </html>
        <?php
        return ob_get_clean();
    }

    /**
     * Save file to uploads directory
     *
     * @param string $content File content
     * @param string $filename Filename
     * @param string $subdir Subdirectory
     * @return string|false File path or false
     */
    public function saveFile(string $content, string $filename, string $subdir = 'backup') {
        $upload_dir = wp_upload_dir();
        $target_dir = $upload_dir['basedir'] . '/mikroplaneta-booking/' . $subdir . '/';
        
        // Create directory
        if (!file_exists($target_dir)) {
            wp_mkdir_p($target_dir);
            
            // Add .htaccess to prevent direct access
            file_put_contents($target_dir . '.htaccess', 'deny from all');
        }
        
        $filepath = $target_dir . $filename;
        
        if (file_put_contents($filepath, $content) !== false) {
            return $filepath;
        }
        
        return false;
    }

    /**
     * Get file for download
     *
     * @param string $filepath File path
     * @param string $filename Download filename
     */
    public function sendFile(string $filepath, string $filename): void {
        if (!file_exists($filepath)) {
            wp_die('File not found', 'Error', ['response' => 404]);
        }

        if (ob_get_level()) {
            ob_end_clean();
        }

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . filesize($filepath));
        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');

        readfile($filepath);
        exit;
    }
}
