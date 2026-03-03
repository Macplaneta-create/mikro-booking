<?php
/**
 * iCalendar Service
 *
 * Generates iCalendar (.ics) files for reservations
 *
 * @package MikroPlaneta\Booking
 * @since 1.3.1
 */

namespace MikroPlaneta\Booking\Core\Services;

use MikroPlaneta\Booking\Core\Models\Reservation;
use MikroPlaneta\Booking\Core\Models\Guest;
use MikroPlaneta\Booking\Core\Database\Schema;

if (!defined('ABSPATH')) {
    exit;
}

class IcalService {

    /**
     * Directory for temporary iCal files
     */
    private function getTempDir(): string {
        $upload_dir = wp_upload_dir();
        return trailingslashit($upload_dir['basedir']) . 'mikroplaneta-booking/ical/';
    }

    /**
     * Retention period in hours for iCal files
     */
    private function getRetentionHours(): int {
        $hours = (int) get_option('mikroplaneta_booking_ical_retention_hours', 24);
        return max(1, $hours);
    }

    /**
     * Cleanup old iCal files
     *
     * @return int Number of deleted files
     */
    public function cleanupOldFiles(): int {
        $temp_dir = $this->getTempDir();
        if (!is_dir($temp_dir)) {
            return 0;
        }

        $cutoff = time() - ($this->getRetentionHours() * HOUR_IN_SECONDS);
        $deleted = 0;

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($temp_dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::LEAVES_ONLY
        );

        foreach ($iterator as $file_info) {
            if (!$file_info->isFile()) {
                continue;
            }

            $file = $file_info->getPathname();
            $basename = basename($file);
            if (in_array($basename, ['.htaccess', 'index.php', 'web.config', 'nginx.conf'], true)) {
                continue;
            }

            $mtime = @filemtime($file);
            if ($mtime === false || $mtime >= $cutoff) {
                continue;
            }

            if (@unlink($file)) {
                $deleted++;
            }
        }

        return $deleted;
    }

    /**
     * Generate iCalendar content for a reservation
     *
     * @param Reservation $reservation Reservation model
     * @param Guest $guest Guest model
     * @return string iCalendar content
     */
    public function generateIcs(Reservation $reservation, Guest $guest): string {
        $hotel_name = get_bloginfo('name');
        $hotel_address = get_option('mikroplaneta_booking_hotel_address', '');
        
        // Create unique ID
        $uid = 'reservation-' . $reservation->id . '@' . parse_url(home_url(), PHP_URL_HOST);
        
        // Format dates (all-day events: DTSTART is check-in, DTEND is check-out)
        $dtstart = date('Ymd', strtotime($reservation->check_in));
        $dtend = date('Ymd', strtotime($reservation->check_out));
        
        // Create timestamp
        $dtstamp = date('Ymd\THis\Z');
        $created = date('Ymd\THis\Z', strtotime($reservation->created_at));
        
        // Event summary and description
        $summary = 'Rezerwacja #' . $reservation->id . ' - ' . $guest->first_name . ' ' . $guest->last_name;
        $description = sprintf(
            "Gość: %s %s\n" .
            "Email: %s\n" .
            "Telefon: %s\n" .
            "Liczba gości: %d dorosłych + %d dzieci\n" .
            "Status: %s\n" .
            "Cena: %.2f zł",
            $guest->first_name,
            $guest->last_name,
            $guest->email,
            $guest->phone ?: 'N/A',
            $reservation->adults,
            $reservation->children,
            $reservation->status,
            $reservation->total_price
        );
        
        // Location
        $location = $hotel_name . ($hotel_address ? ', ' . $hotel_address : '');
        
        // Build iCalendar content
        $ics = [];
        $ics[] = 'BEGIN:VCALENDAR';
        $ics[] = 'VERSION:2.0';
        $ics[] = 'PRODID:-//MikroPlaneta Booking//NONSGML v1.0//PL';
        $ics[] = 'CALSCALE:GREGORIAN';
        $ics[] = 'METHOD:PUBLISH';
        $ics[] = 'X-WR-CALNAME:' . $this->escapeIcs($hotel_name);
        $ics[] = 'X-WR-TIMEZONE:' . date_default_timezone_get();
        $ics[] = 'BEGIN:VEVENT';
        $ics[] = 'UID:' . $this->escapeIcs($uid);
        $ics[] = 'DTSTAMP:' . $dtstamp;
        $ics[] = 'DTSTART;VALUE=DATE:' . $dtstart;
        $ics[] = 'DTEND;VALUE=DATE:' . $dtend;
        $ics[] = 'SUMMARY:' . $this->escapeIcs($summary);
        $ics[] = 'DESCRIPTION:' . $this->escapeIcs($description);
        $ics[] = 'LOCATION:' . $this->escapeIcs($location);
        $ics[] = 'STATUS:CONFIRMED';
        $ics[] = 'SEQUENCE:0';
        $ics[] = 'CREATED:' . $created;
        $ics[] = 'LAST-MODIFIED:' . $dtstamp;
        $ics[] = 'URL:' . home_url();
        $ics[] = 'END:VEVENT';
        $ics[] = 'END:VCALENDAR';
        
        return implode("\r\n", $ics);
    }

    /**
     * Save iCalendar file to temp directory
     *
     * @param string $icsContent iCalendar content
     * @param int $reservationId Reservation ID
     * @return string|false Path to saved file or false on failure
     */
    public function saveIcsFile(string $icsContent, int $reservationId) {
        // Opportunistic cleanup for environments where WP-Cron may be disabled
        $this->cleanupOldFiles();

        $temp_dir = $this->getTempDir();
        
        // Create directory if it doesn't exist
        if (!file_exists($temp_dir)) {
            wp_mkdir_p($temp_dir);
            
            // Add .htaccess to prevent direct access
            file_put_contents($temp_dir . '.htaccess', 'deny from all');

            // Add index.php to prevent directory listing
            file_put_contents($temp_dir . 'index.php', "<?php\n// Silence is golden.\n");

            // Add web.config for IIS
            file_put_contents(
                $temp_dir . 'web.config',
                "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<configuration>\n  <system.webServer>\n    <security>\n      <authorization>\n        <remove users=\"*\" roles=\"\" verbs=\"\" />\n        <add accessType=\"Deny\" users=\"*\" />\n      </authorization>\n    </security>\n  </system.webServer>\n</configuration>\n"
            );

            // Add Nginx hint file
            file_put_contents(
                $temp_dir . 'nginx.conf',
                "location ^~ / {\n    deny all;\n    return 403;\n}\n"
            );
        }
        
        $filename = 'reservation-' . $reservationId . '-' . time() . '.ics';
        $filepath = $temp_dir . $filename;
        
        if (file_put_contents($filepath, $icsContent) !== false) {
            return $filepath;
        }
        
        return false;
    }

    /**
     * Get download URL for iCalendar file
     *
     * @param int $reservationId Reservation ID
     * @return string Download URL
     */
    public function getDownloadUrl(int $reservationId): string {
        return wp_nonce_url(
            admin_url('admin-ajax.php?action=mikroplaneta_download_ical&reservation_id=' . $reservationId),
            'download_ical_' . $reservationId
        );
    }

    /**
     * Escape special characters for iCalendar format
     *
     * @param string $text Text to escape
     * @return string Escaped text
     */
    private function escapeIcs(string $text): string {
        // Escape backslashes first
        $text = str_replace('\\', '\\\\', $text);
        // Escape commas
        $text = str_replace(',', '\,', $text);
        // Escape semicolons
        $text = str_replace(';', '\;', $text);
        // Escape new lines
        $text = str_replace("\n", '\n', $text);
        // Escape carriage returns
        $text = str_replace("\r", '', $text);
        
        return $text;
    }

    /**
     * Get MIME type for .ics file
     *
     * @return string MIME type
     */
    public function getMimeType(): string {
        return 'text/calendar; charset=utf-8';
    }

    /**
     * Send iCalendar file as download
     *
     * @param string $filepath Path to iCalendar file
     * @param string $filename Filename for download
     */
    public function sendDownload(string $filepath, string $filename): void {
        if (!file_exists($filepath)) {
            wp_die('File not found', 'Error', ['response' => 404]);
        }

        // Clear output buffer
        if (ob_get_level()) {
            ob_end_clean();
        }

        // Set headers
        header('Content-Type: ' . $this->getMimeType());
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . filesize($filepath));
        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');

        // Send file
        readfile($filepath);
        exit;
    }
}
