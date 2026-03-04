<?php
/**
 * Notification Service
 *
 * Business logic for sending notifications
 *
 * @package MikroPlaneta\Booking
 * @since 1.0.0
 */

namespace MikroPlaneta\Booking\Core\Services;

use MikroPlaneta\Booking\Core\Models\Reservation;
use MikroPlaneta\Booking\Core\Models\Guest;
use MikroPlaneta\Booking\Core\Database\Schema;
use MikroPlaneta\Booking\Core\Services\IcalService;

if (!defined('ABSPATH')) {
    exit;
}

class NotificationService {
    private const TEMPLATE_DEFINITIONS = [
        'reservation_confirmation' => 'Potwierdzenie rezerwacji',
        'reservation_pending' => 'Rezerwacja oczekująca (zaliczka)',
        'reservation_cancellation' => 'Anulowanie rezerwacji',
        'checkin_reminder' => 'Przypomnienie o zameldowaniu',
        'checkout_reminder' => 'Przypomnienie o wymeldowaniu',
    ];

    private ?bool $notifications_table_available = null;
    private ?IcalService $ical_service = null;

    private const EMAIL_RETRY_ATTEMPTS = 3;
    private const EMAIL_RETRY_BACKOFF_MS = [0, 300, 900];

    /**
     * Get iCalendar service
     */
    private function getIcalService(): IcalService {
        if ($this->ical_service === null) {
            $this->ical_service = new IcalService();
        }
        return $this->ical_service;
    }

    /**
     * Send reservation confirmation email
     */
    public function sendReservationConfirmation(Reservation $reservation, Guest $guest, array $context = []): bool {
        // Use different template based on reservation status
        $template = ($reservation->status === 'pending')
            ? 'reservation_pending'
            : 'reservation_confirmation';

        [$subject, $message] = $this->resolveTemplate($template, $reservation, $guest, $context);

        // Generate iCalendar attachment
        $attachments = [];
        $ical_service = $this->getIcalService();
        $ics_content = $ical_service->generateIcs($reservation, $guest);
        $ics_filepath = $ical_service->saveIcsFile($ics_content, $reservation->id);
        
        if ($ics_filepath) {
            $attachments[] = $ics_filepath;
        }

        $result = $this->sendEmailWithRetry(
            $guest->email,
            $subject,
            $message,
            $this->getEmailHeaders(),
            $attachments
        );

        $sent = (bool) $result['sent'];
        $attempts = (int) $result['attempts'];

        $this->logNotification(
            $template,
            $reservation,
            $guest,
            $sent,
            $sent ? '' : sprintf('wp_mail() returned false after %d attempts', $attempts)
        );

        if ($sent) {
            do_action('mikroplaneta_booking_notification_sent', 'reservation_confirmation', $reservation, $guest);
        }

        return $sent;
    }
    
    /**
     * Send reservation cancellation email
     */
    public function sendReservationCancellation(Reservation $reservation, Guest $guest, string $reason = ''): bool {
        [$subject, $message] = $this->resolveTemplate('reservation_cancellation', $reservation, $guest, [
            'reason' => $reason,
        ]);
        
        $result = $this->sendEmailWithRetry(
            $guest->email,
            $subject,
            $message,
            $this->getEmailHeaders()
        );

        $sent = (bool) $result['sent'];
        $attempts = (int) $result['attempts'];

        $this->logNotification(
            'reservation_cancellation',
            $reservation,
            $guest,
            $sent,
            $sent ? '' : sprintf('wp_mail() returned false after %d attempts', $attempts)
        );
        
        if ($sent) {
            do_action('mikroplaneta_booking_notification_sent', 'reservation_cancellation', $reservation, $guest);
        }
        
        return $sent;
    }
    
    /**
     * Send check-in reminder
     */
    public function sendCheckInReminder(Reservation $reservation, Guest $guest): bool {
        if ($this->wasNotificationSentToday('checkin_reminder', (int) $reservation->id, (int) $guest->id)) {
            return true;
        }

        [$subject, $message] = $this->resolveTemplate('checkin_reminder', $reservation, $guest);
        
        $result = $this->sendEmailWithRetry(
            $guest->email,
            $subject,
            $message,
            $this->getEmailHeaders()
        );

        $sent = (bool) $result['sent'];
        $attempts = (int) $result['attempts'];

        $this->logNotification(
            'checkin_reminder',
            $reservation,
            $guest,
            $sent,
            $sent ? '' : sprintf('wp_mail() returned false after %d attempts', $attempts)
        );
        
        if ($sent) {
            do_action('mikroplaneta_booking_notification_sent', 'checkin_reminder', $reservation, $guest);
        }
        
        return $sent;
    }
    
    /**
     * Send check-out reminder
     */
    public function sendCheckOutReminder(Reservation $reservation, Guest $guest): bool {
        if ($this->wasNotificationSentToday('checkout_reminder', (int) $reservation->id, (int) $guest->id)) {
            return true;
        }

        [$subject, $message] = $this->resolveTemplate('checkout_reminder', $reservation, $guest);
        
        $result = $this->sendEmailWithRetry(
            $guest->email,
            $subject,
            $message,
            $this->getEmailHeaders()
        );

        $sent = (bool) $result['sent'];
        $attempts = (int) $result['attempts'];

        $this->logNotification(
            'checkout_reminder',
            $reservation,
            $guest,
            $sent,
            $sent ? '' : sprintf('wp_mail() returned false after %d attempts', $attempts)
        );
        
        if ($sent) {
            do_action('mikroplaneta_booking_notification_sent', 'checkout_reminder', $reservation, $guest);
        }
        
        return $sent;
    }

    /**
     * Get editable template definitions for admin UI
     */
    public function getTemplateDefinitions(): array {
        $guest = new Guest([
            'id' => 1,
            'first_name' => 'Jan',
            'last_name' => 'Kowalski',
            'email' => 'jan@example.com',
        ]);
        $reservation = new Reservation([
            'id' => 1001,
            'guest_id' => 1,
            'check_in' => date('Y-m-d', strtotime('+7 days')),
            'check_out' => date('Y-m-d', strtotime('+10 days')),
            'total_price' => 999.99,
            'adults' => 2,
            'children' => 1,
            'notes' => 'Pokój z widokiem.',
            'status' => Reservation::STATUS_CONFIRMED,
        ]);

        $templates = [];
        foreach (self::TEMPLATE_DEFINITIONS as $key => $label) {
            $default_subject = $this->getDefaultSubject($key);
            $default_body = $this->getDefaultBody($key, $reservation, $guest, [
                'reason' => 'Przykładowy powód anulowania.',
            ]);

            $subject = (string) get_option(
                "mikroplaneta_booking_email_subject_{$key}",
                $default_subject
            );
            $body = (string) get_option(
                "mikroplaneta_booking_email_body_{$key}",
                $default_body
            );

            $templates[] = [
                'key' => $key,
                'label' => $label,
                'subject' => $subject,
                'body' => $body,
                'default_subject' => $default_subject,
                'default_body' => $default_body,
            ];
        }

        return [
            'templates' => $templates,
            'placeholders' => array_keys($this->buildPlaceholders($reservation, $guest, ['reason' => 'Powód'])),
        ];
    }

    /**
     * Persist templates from admin UI
     */
    public function saveTemplateDefinitions(array $templates): void {
        foreach ($templates as $template) {
            $key = sanitize_key((string) ($template['key'] ?? ''));
            if (!array_key_exists($key, self::TEMPLATE_DEFINITIONS)) {
                continue;
            }

            $subject = sanitize_text_field((string) ($template['subject'] ?? ''));
            $body = wp_kses_post((string) ($template['body'] ?? ''));

            $default_subject = $this->getDefaultSubject($key);
            $default_body = $this->getDefaultBody(
                $key,
                new Reservation([
                    'id' => 1,
                    'guest_id' => 1,
                    'check_in' => date('Y-m-d', strtotime('+1 day')),
                    'check_out' => date('Y-m-d', strtotime('+2 days')),
                    'total_price' => 100,
                ]),
                new Guest([
                    'id' => 1,
                    'first_name' => 'Jan',
                    'last_name' => 'Kowalski',
                    'email' => 'jan@example.com',
                ]),
                ['reason' => '']
            );

            if ($subject === '' || $subject === $default_subject) {
                delete_option("mikroplaneta_booking_email_subject_{$key}");
            } else {
                update_option("mikroplaneta_booking_email_subject_{$key}", $subject);
            }

            if ($body === '' || $body === $default_body) {
                delete_option("mikroplaneta_booking_email_body_{$key}");
            } else {
                update_option("mikroplaneta_booking_email_body_{$key}", $body);
            }
        }
    }

    /**
     * Send test email for selected template
     */
    public function sendTestEmail(string $template_key, string $to_email): bool {
        if (!array_key_exists($template_key, self::TEMPLATE_DEFINITIONS)) {
            return false;
        }

        $guest = new Guest([
            'id' => 0,
            'first_name' => 'Test',
            'last_name' => 'Klient',
            'email' => $to_email,
        ]);
        $reservation = new Reservation([
            'id' => 9999,
            'guest_id' => 0,
            'check_in' => date('Y-m-d', strtotime('+5 days')),
            'check_out' => date('Y-m-d', strtotime('+7 days')),
            'total_price' => 555.0,
            'adults' => 2,
            'children' => 0,
            'status' => Reservation::STATUS_CONFIRMED,
        ]);

        [$subject, $message] = $this->resolveTemplate($template_key, $reservation, $guest, [
            'reason' => 'To jest testowa wiadomość.',
        ]);

        $result = $this->sendEmailWithRetry($to_email, $subject, $message, $this->getEmailHeaders());
        return (bool) $result['sent'];
    }

    /**
     * Send email with retry + incremental backoff.
     *
     * @return array{sent:bool,attempts:int,error:string}
     */
    private function sendEmailWithRetry(
        string $to,
        string $subject,
        string $message,
        array $headers,
        array $attachments = []
    ): array {
        $max_attempts = (int) apply_filters(
            'mikroplaneta_booking_email_retry_attempts',
            self::EMAIL_RETRY_ATTEMPTS
        );
        $max_attempts = max(1, $max_attempts);

        $backoff_ms = apply_filters(
            'mikroplaneta_booking_email_retry_backoff_ms',
            self::EMAIL_RETRY_BACKOFF_MS
        );

        if (!is_array($backoff_ms) || $backoff_ms === []) {
            $backoff_ms = self::EMAIL_RETRY_BACKOFF_MS;
        }

        $last_error = '';

        for ($attempt = 1; $attempt <= $max_attempts; $attempt++) {
            if ($attempt > 1) {
                $delay_ms = $this->getBackoffDelayMs($backoff_ms, $attempt - 1);
                if ($delay_ms > 0) {
                    usleep($delay_ms * 1000);
                }
            }

            $sent = wp_mail($to, $subject, $message, $headers, $attachments);
            if ($sent) {
                return [
                    'sent' => true,
                    'attempts' => $attempt,
                    'error' => '',
                ];
            }

            $last_error = 'wp_mail() returned false';
        }

        return [
            'sent' => false,
            'attempts' => $max_attempts,
            'error' => $last_error,
        ];
    }

    /**
     * Resolve backoff delay for a retry attempt (1-based retry index).
     */
    private function getBackoffDelayMs(array $backoff_ms, int $retry_index): int {
        $index = max(0, $retry_index - 1);
        $last = (int) end($backoff_ms);
        $value = $backoff_ms[$index] ?? $last;
        return max(0, (int) $value);
    }

    /**
     * Read recent notifications history
     */
    public function getNotificationHistory(int $limit = 100): array {
        if (!$this->isNotificationsTableAvailable()) {
            return [];
        }

        global $wpdb;

        $limit = max(1, min(500, $limit));
        $table = Schema::get_table_name('notifications');
        $guests_table = Schema::get_table_name('guests');

        $sql = $wpdb->prepare(
            "SELECT n.id, n.template_name, n.status, n.sent_at, n.created_at, n.error_message, n.reservation_id, n.guest_id,
                    g.first_name, g.last_name, g.email
             FROM {$table} n
             LEFT JOIN {$guests_table} g ON g.id = n.guest_id
             ORDER BY n.created_at DESC
             LIMIT %d",
            $limit
        );

        return $wpdb->get_results($sql, ARRAY_A) ?: [];
    }

    /**
     * Resolve template subject/body, with option overrides and placeholders
     *
     * @return array{0:string,1:string}
     */
    private function resolveTemplate(string $template_key, Reservation $reservation, Guest $guest, array $context = []): array {
        $default_subject = $this->getDefaultSubject($template_key);
        $default_body = $this->getDefaultBody($template_key, $reservation, $guest, $context);

        $subject = (string) get_option(
            "mikroplaneta_booking_email_subject_{$template_key}",
            $default_subject
        );
        $body = (string) get_option(
            "mikroplaneta_booking_email_body_{$template_key}",
            $default_body
        );

        $placeholders = $this->buildPlaceholders($reservation, $guest, $context);

        return [
            strtr($subject, $placeholders),
            strtr($body, $placeholders),
        ];
    }

    private function getDefaultSubject(string $template_key): string {
        switch ($template_key) {
            case 'reservation_confirmation':
                return sprintf(__('Reservation Confirmed - %s', 'mikroplaneta-booking'), get_bloginfo('name'));
            case 'reservation_pending':
                return sprintf(__('Reservation Received - Waiting for Confirmation - %s', 'mikroplaneta-booking'), get_bloginfo('name'));
            case 'reservation_cancellation':
                return sprintf(__('Reservation Cancelled - %s', 'mikroplaneta-booking'), get_bloginfo('name'));
            case 'checkin_reminder':
                return sprintf(__('Check-in Reminder - %s', 'mikroplaneta-booking'), get_bloginfo('name'));
            case 'checkout_reminder':
                return sprintf(__('Check-out Reminder - %s', 'mikroplaneta-booking'), get_bloginfo('name'));
            default:
                return sprintf(__('Reservation Message - %s', 'mikroplaneta-booking'), get_bloginfo('name'));
        }
    }

    private function getDefaultBody(string $template_key, Reservation $reservation, Guest $guest, array $context = []): string {
        switch ($template_key) {
            case 'reservation_confirmation':
                return $this->getReservationConfirmationTemplate($reservation, $guest);
            case 'reservation_pending':
                return $this->getReservationPendingTemplate($reservation, $guest);
            case 'reservation_cancellation':
                return $this->getReservationCancellationTemplate(
                    $reservation,
                    $guest,
                    (string) ($context['reason'] ?? '')
                );
            case 'checkin_reminder':
                return $this->getCheckInReminderTemplate($reservation, $guest);
            case 'checkout_reminder':
                return $this->getCheckOutReminderTemplate($reservation, $guest);
            default:
                return $this->getReservationConfirmationTemplate($reservation, $guest);
        }
    }

    private function buildPlaceholders(Reservation $reservation, Guest $guest, array $context = []): array {
        $reason = (string) ($context['reason'] ?? '');
        
        // Check if consents were passed in context (from reservation data)
        $consents = $context['consents'] ?? null;
        
        $consent_text = '';
        if ($consents) {
            $consent_items = [];
            if (!empty($consents['data_processing'])) {
                $consent_items[] = '✓ Wyraziłem zgodę na przetwarzanie danych osobowych';
            }
            if (!empty($consents['terms_accepted'])) {
                $consent_items[] = '✓ Zapoznałem się i akceptuję regulamin';
            }
            if (!empty($consents['marketing'])) {
                $consent_items[] = '✓ Chcę otrzymywać newsletter';
            }
            $consent_text = !empty($consent_items) 
                ? "\n\n---\n" . __('Zgody RODO:', 'mikroplaneta-booking') . "\n" . implode("\n", $consent_items) 
                    . "\n" . __('Data wyrażenia:', 'mikroplaneta-booking') . ' ' . date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($consents['timestamp'] ?? current_time('mysql')))
                    . "\n" . __('IP:', 'mikroplaneta-booking') . ' ' . ($consents['ip_address'] ?? '')
                : '';
        }
        
        return [
            '{{guest_name}}' => esc_html($guest->getFullName()),
            '{{guest_email}}' => esc_html($guest->email),
            '{{reservation_id}}' => (string) intval($reservation->id),
            '{{check_in}}' => esc_html(date_i18n(get_option('date_format'), strtotime($reservation->check_in))),
            '{{check_out}}' => esc_html(date_i18n(get_option('date_format'), strtotime($reservation->check_out))),
            '{{nights}}' => (string) intval($reservation->getNights()),
            '{{total_price}}' => esc_html(number_format((float) $reservation->total_price, 2)),
            '{{hotel_name}}' => esc_html(get_bloginfo('name')),
            '{{home_url}}' => esc_url(home_url()),
            '{{reason}}' => nl2br(esc_html($reason)),
            '{{consents}}' => nl2br(esc_html($consent_text)),
        ];
    }

    private function logNotification(
        string $template_name,
        Reservation $reservation,
        Guest $guest,
        bool $sent,
        string $error_message = ''
    ): void {
        if (!$this->isNotificationsTableAvailable()) {
            return;
        }

        if ((int) $guest->id <= 0) {
            return;
        }

        global $wpdb;
        $table = Schema::get_table_name('notifications');

        $wpdb->insert($table, [
            'reservation_id' => ($reservation->id > 0 ? (int) $reservation->id : null),
            'guest_id' => (int) $guest->id,
            'channel' => 'email',
            'template_name' => $template_name,
            'status' => $sent ? 'sent' : 'failed',
            'sent_at' => $sent ? current_time('mysql') : null,
            'error_message' => $sent ? null : sanitize_text_field($error_message),
            'created_at' => current_time('mysql'),
        ]);
    }

    private function isNotificationsTableAvailable(): bool {
        if ($this->notifications_table_available !== null) {
            return $this->notifications_table_available;
        }

        global $wpdb;
        $table = Schema::get_table_name('notifications');
        $found = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table));
        $this->notifications_table_available = ($found === $table);

        return $this->notifications_table_available;
    }

    /**
     * Check whether reminder notification was already sent today for reservation/guest.
     */
    private function wasNotificationSentToday(string $template_name, int $reservation_id, int $guest_id): bool {
        if (!$this->isNotificationsTableAvailable() || $reservation_id <= 0 || $guest_id <= 0) {
            return false;
        }

        global $wpdb;
        $table = Schema::get_table_name('notifications');
        $today = (string) current_time('Y-m-d');

        $count = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table}
             WHERE template_name = %s
               AND reservation_id = %d
               AND guest_id = %d
               AND status = %s
               AND DATE(sent_at) = %s",
            $template_name,
            $reservation_id,
            $guest_id,
            'sent',
            $today
        ));

        return $count > 0;
    }
    
    /**
     * Get email headers
     */
    private function getEmailHeaders(): array {
        $from_email = get_option('admin_email');
        $from_name = get_bloginfo('name');
        
        return [
            'Content-Type: text/html; charset=UTF-8',
            "From: {$from_name} <{$from_email}>",
        ];
    }
    
    /**
     * Get reservation confirmation template
     */
    private function getReservationConfirmationTemplate(Reservation $reservation, Guest $guest): string {
        ob_start();
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: #0073aa; color: white; padding: 20px; text-align: center; }
                .content { padding: 20px; background: #f9f9f9; }
                .details { background: white; padding: 15px; margin: 15px 0; border-left: 4px solid #0073aa; }
                .footer { text-align: center; padding: 20px; font-size: 12px; color: #666; }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <h1><?php _e('Reservation Confirmed', 'mikroplaneta-booking'); ?></h1>
                </div>
                
                <div class="content">
                    <p><?php printf(__('Dear %s,', 'mikroplaneta-booking'), esc_html($guest->getFullName())); ?></p>
                    
                    <p><?php _e('Your reservation has been confirmed. Here are the details:', 'mikroplaneta-booking'); ?></p>
                    
                    <div class="details">
                        <p><strong><?php _e('Reservation ID:', 'mikroplaneta-booking'); ?></strong> #<?php echo esc_html($reservation->id); ?></p>
                        <p><strong><?php _e('Check-in:', 'mikroplaneta-booking'); ?></strong> <?php echo esc_html(date_i18n(get_option('date_format'), strtotime($reservation->check_in))); ?></p>
                        <p><strong><?php _e('Check-out:', 'mikroplaneta-booking'); ?></strong> <?php echo esc_html(date_i18n(get_option('date_format'), strtotime($reservation->check_out))); ?></p>
                        <p><strong><?php _e('Nights:', 'mikroplaneta-booking'); ?></strong> <?php echo esc_html($reservation->getNights()); ?></p>
                        <p><strong><?php _e('Total Price:', 'mikroplaneta-booking'); ?></strong> <?php echo esc_html(number_format($reservation->total_price, 2)); ?> PLN</p>
                    </div>
                    
                    <?php if ($reservation->notes): ?>
                    <div class="details">
                        <p><strong><?php _e('Notes:', 'mikroplaneta-booking'); ?></strong></p>
                        <p><?php echo nl2br(esc_html($reservation->notes)); ?></p>
                    </div>
                    <?php endif; ?>

                    <div class="details" style="border-left-color: #28a745;">
                        <p><strong><?php _e('GDPR Consents:', 'mikroplaneta-booking'); ?></strong></p>
                        <p style="font-size: 13px;">{{consents}}</p>
                        <p style="font-size: 11px; color: #666; margin-top: 10px;">
                            <?php _e('By making this reservation, you have agreed to our', 'mikroplaneta-booking'); ?> 
                            <a href="<?php echo esc_url(get_privacy_policy_url()); ?>"><?php _e('Privacy Policy', 'mikroplaneta-booking'); ?></a>.
                        </p>
                    </div>

                    <p><?php _e('We look forward to welcoming you!', 'mikroplaneta-booking'); ?></p>
                </div>
                
                <div class="footer">
                    <p><?php echo esc_html(get_bloginfo('name')); ?></p>
                    <p><a href="<?php echo esc_url(home_url()); ?>"><?php echo esc_url(home_url()); ?></a></p>
                </div>
            </div>
        </body>
        </html>
        <?php
        return ob_get_clean();
    }

    /**
     * Get reservation pending template (with deposit info)
     */
    private function getReservationPendingTemplate(Reservation $reservation, Guest $guest): string {
        // Get deposit settings
        $deposit_enabled = (bool) get_option('mikroplaneta_booking_deposit_enabled', false);
        $deposit_percent = (int) get_option('mikroplaneta_booking_deposit_percent', 30);
        $payment_account = (string) get_option('mikroplaneta_booking_payment_account', '');
        $payment_bank_name = (string) get_option('mikroplaneta_booking_payment_bank_name', '');
        $payment_additional_info = (string) get_option('mikroplaneta_booking_payment_additional_info', '');
        $timeout_hours = (int) get_option('mikroplaneta_booking_pending_timeout_hours', 48);
        
        // Calculate deposit amount
        $deposit_amount = $deposit_enabled ? ($reservation->total_price * $deposit_percent / 100) : 0;
        
        // Calculate deadline
        $deadline = date_i18n(
            get_option('date_format') . ' ' . get_option('time_format'),
            strtotime("+{$timeout_hours} hours")
        );

        ob_start();
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: #f59e0b; color: white; padding: 20px; text-align: center; }
                .content { padding: 20px; background: #f9f9f9; }
                .details { background: white; padding: 15px; margin: 15px 0; border-left: 4px solid #f59e0b; }
                .payment-info { background: #fef3c7; padding: 15px; margin: 15px 0; border-left: 4px solid #f59e0b; }
                .footer { text-align: center; padding: 20px; font-size: 12px; color: #666; }
                .highlight { font-weight: bold; color: #d97706; }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <h1><?php _e('Reservation Received', 'mikroplaneta-booking'); ?></h1>
                    <p style="margin: 10px 0 0; font-size: 14px;"><?php _e('Waiting for confirmation', 'mikroplaneta-booking'); ?></p>
                </div>

                <div class="content">
                    <p><?php printf(__('Dear %s,', 'mikroplaneta-booking'), esc_html($guest->getFullName())); ?></p>

                    <p><?php _e('Thank you for your reservation request. We have received your booking and it is waiting for confirmation.', 'mikroplaneta-booking'); ?></p>

                    <div class="details">
                        <p><strong><?php _e('Reservation ID:', 'mikroplaneta-booking'); ?></strong> #<?php echo esc_html($reservation->id); ?></p>
                        <p><strong><?php _e('Status:', 'mikroplaneta-booking'); ?></strong> <span class="highlight"><?php _e('Pending (waiting for confirmation)', 'mikroplaneta-booking'); ?></span></p>
                        <p><strong><?php _e('Check-in:', 'mikroplaneta-booking'); ?></strong> <?php echo esc_html(date_i18n(get_option('date_format'), strtotime($reservation->check_in))); ?></p>
                        <p><strong><?php _e('Check-out:', 'mikroplaneta-booking'); ?></strong> <?php echo esc_html(date_i18n(get_option('date_format'), strtotime($reservation->check_out))); ?></p>
                        <p><strong><?php _e('Nights:', 'mikroplaneta-booking'); ?></strong> <?php echo esc_html($reservation->getNights()); ?></p>
                        <p><strong><?php _e('Guests:', 'mikroplaneta-booking'); ?></strong> <?php echo esc_html($reservation->adults + $reservation->children); ?></p>
                        <p><strong><?php _e('Total Price:', 'mikroplaneta-booking'); ?></strong> <?php echo esc_html(number_format($reservation->total_price, 2)); ?> PLN</p>
                    </div>

                    <?php if ($deposit_enabled && $deposit_amount > 0): ?>
                    <div class="payment-info">
                        <h2 style="margin: 0 0 15px; color: #d97706;"><?php _e('Deposit Required', 'mikroplaneta-booking'); ?></h2>
                        
                        <p><?php _e('To confirm your reservation, please make a deposit payment:', 'mikroplaneta-booking'); ?></p>
                        
                        <p style="font-size: 18px; text-align: center; margin: 20px 0;">
                            <strong><?php _e('Deposit Amount:', 'mikroplaneta-booking'); ?></strong><br>
                            <span class="highlight" style="font-size: 24px;"><?php echo esc_html(number_format($deposit_amount, 2)); ?> PLN</span>
                            <span style="font-size: 14px; color: #666;">(<?php echo esc_html($deposit_percent); ?>%)</span>
                        </p>

                        <div style="background: white; padding: 15px; margin: 15px 0; border-radius: 8px;">
                            <p style="margin: 10px 0;"><strong><?php _e('Bank Account Number:', 'mikroplaneta-booking'); ?></strong></p>
                            <p style="font-size: 18px; font-family: monospace; background: #f9f9f9; padding: 10px; text-align: center; letter-spacing: 2px;">
                                <?php echo esc_html($payment_account ?: '---'); ?>
                            </p>
                            
                            <?php if ($payment_bank_name): ?>
                            <p style="margin: 10px 0;"><strong><?php _e('Bank:', 'mikroplaneta-booking'); ?></strong> <?php echo esc_html($payment_bank_name); ?></p>
                            <?php endif; ?>
                            
                            <p style="margin: 10px 0;"><strong><?php _e('Payment Title:', 'mikroplaneta-booking'); ?></strong> <?php printf(__('Reservation #%d', 'mikroplaneta-booking'), intval($reservation->id)); ?></p>
                            
                            <?php if ($payment_additional_info): ?>
                            <p style="margin: 10px 0;"><strong><?php _e('Additional Information:', 'mikroplaneta-booking'); ?></strong></p>
                            <p style="font-size: 13px; color: #666;"><?php echo nl2br(esc_html($payment_additional_info)); ?></p>
                            <?php endif; ?>
                        </div>

                        <p style="background: #fef3c7; padding: 10px; border-radius: 6px;">
                            <strong><?php _e('Payment Deadline:', 'mikroplaneta-booking'); ?></strong><br>
                            <?php printf(
                                __('Please make the payment within %d hours (before %s).', 'mikroplaneta-booking'),
                                intval($timeout_hours),
                                esc_html($deadline)
                            ); ?>
                        </p>

                        <p style="font-size: 13px; color: #666;">
                            <?php _e('Your reservation will be confirmed automatically after the deposit is received. If we do not receive the payment within the deadline, your reservation will be automatically cancelled.', 'mikroplaneta-booking'); ?>
                        </p>
                    </div>
                    <?php else: ?>
                    <div class="payment-info" style="border-left-color: #10b981; background: #ecfdf5;">
                        <h2 style="margin: 0 0 15px; color: #059669;"><?php _e('Waiting for Confirmation', 'mikroplaneta-booking'); ?></h2>
                        <p><?php _e('Our team will review your reservation request and confirm it shortly. You will receive a confirmation email with further instructions.', 'mikroplaneta-booking'); ?></p>
                    </div>
                    <?php endif; ?>

                    <?php if ($reservation->notes): ?>
                    <div class="details">
                        <p><strong><?php _e('Your Notes:', 'mikroplaneta-booking'); ?></strong></p>
                        <p><?php echo nl2br(esc_html($reservation->notes)); ?></p>
                    </div>
                    <?php endif; ?>

                    <div class="details" style="border-left-color: #28a745;">
                        <p><strong><?php _e('GDPR Consents:', 'mikroplaneta-booking'); ?></strong></p>
                        <p style="font-size: 13px;">{{consents}}</p>
                        <p style="font-size: 11px; color: #666; margin-top: 10px;">
                            <?php _e('By making this reservation, you have agreed to our', 'mikroplaneta-booking'); ?>
                            <a href="<?php echo esc_url(get_privacy_policy_url()); ?>"><?php _e('Privacy Policy', 'mikroplaneta-booking'); ?></a>.
                        </p>
                    </div>

                    <p><?php _e('If you have any questions, please contact us.', 'mikroplaneta-booking'); ?></p>
                </div>

                <div class="footer">
                    <p><?php echo esc_html(get_bloginfo('name')); ?></p>
                    <p><a href="<?php echo esc_url(home_url()); ?>"><?php echo esc_url(home_url()); ?></a></p>
                </div>
            </div>
        </body>
        </html>
        <?php
        return ob_get_clean();
    }

    /**
     * Get reservation cancellation template
     */
    private function getReservationCancellationTemplate(Reservation $reservation, Guest $guest, string $reason): string {
        ob_start();
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: #dc3232; color: white; padding: 20px; text-align: center; }
                .content { padding: 20px; background: #f9f9f9; }
                .details { background: white; padding: 15px; margin: 15px 0; border-left: 4px solid #dc3232; }
                .footer { text-align: center; padding: 20px; font-size: 12px; color: #666; }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <h1><?php _e('Reservation Cancelled', 'mikroplaneta-booking'); ?></h1>
                </div>
                
                <div class="content">
                    <p><?php printf(__('Dear %s,', 'mikroplaneta-booking'), esc_html($guest->getFullName())); ?></p>
                    
                    <p><?php _e('Your reservation has been cancelled.', 'mikroplaneta-booking'); ?></p>
                    
                    <div class="details">
                        <p><strong><?php _e('Reservation ID:', 'mikroplaneta-booking'); ?></strong> #<?php echo esc_html($reservation->id); ?></p>
                        <p><strong><?php _e('Check-in:', 'mikroplaneta-booking'); ?></strong> <?php echo esc_html(date_i18n(get_option('date_format'), strtotime($reservation->check_in))); ?></p>
                        <p><strong><?php _e('Check-out:', 'mikroplaneta-booking'); ?></strong> <?php echo esc_html(date_i18n(get_option('date_format'), strtotime($reservation->check_out))); ?></p>
                    </div>
                    
                    <?php if ($reason): ?>
                    <div class="details">
                        <p><strong><?php _e('Reason:', 'mikroplaneta-booking'); ?></strong></p>
                        <p><?php echo nl2br(esc_html($reason)); ?></p>
                    </div>
                    <?php endif; ?>
                    
                    <p><?php _e('If you have any questions, please contact us.', 'mikroplaneta-booking'); ?></p>
                </div>
                
                <div class="footer">
                    <p><?php echo esc_html(get_bloginfo('name')); ?></p>
                    <p><a href="<?php echo esc_url(home_url()); ?>"><?php echo esc_url(home_url()); ?></a></p>
                </div>
            </div>
        </body>
        </html>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Get check-in reminder template
     */
    private function getCheckInReminderTemplate(Reservation $reservation, Guest $guest): string {
        ob_start();
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: #46b450; color: white; padding: 20px; text-align: center; }
                .content { padding: 20px; background: #f9f9f9; }
                .details { background: white; padding: 15px; margin: 15px 0; border-left: 4px solid #46b450; }
                .footer { text-align: center; padding: 20px; font-size: 12px; color: #666; }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <h1><?php _e('Check-in Reminder', 'mikroplaneta-booking'); ?></h1>
                </div>
                
                <div class="content">
                    <p><?php printf(__('Dear %s,', 'mikroplaneta-booking'), esc_html($guest->getFullName())); ?></p>
                    
                    <p><?php _e('This is a reminder that your check-in is coming up soon!', 'mikroplaneta-booking'); ?></p>
                    
                    <div class="details">
                        <p><strong><?php _e('Check-in Date:', 'mikroplaneta-booking'); ?></strong> <?php echo esc_html(date_i18n(get_option('date_format'), strtotime($reservation->check_in))); ?></p>
                        <p><strong><?php _e('Check-out Date:', 'mikroplaneta-booking'); ?></strong> <?php echo esc_html(date_i18n(get_option('date_format'), strtotime($reservation->check_out))); ?></p>
                    </div>
                    
                    <p><?php _e('We look forward to seeing you!', 'mikroplaneta-booking'); ?></p>
                </div>
                
                <div class="footer">
                    <p><?php echo esc_html(get_bloginfo('name')); ?></p>
                </div>
            </div>
        </body>
        </html>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Get check-out reminder template
     */
    private function getCheckOutReminderTemplate(Reservation $reservation, Guest $guest): string {
        ob_start();
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: #ffb900; color: white; padding: 20px; text-align: center; }
                .content { padding: 20px; background: #f9f9f9; }
                .details { background: white; padding: 15px; margin: 15px 0; border-left: 4px solid #ffb900; }
                .footer { text-align: center; padding: 20px; font-size: 12px; color: #666; }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <h1><?php _e('Check-out Reminder', 'mikroplaneta-booking'); ?></h1>
                </div>
                
                <div class="content">
                    <p><?php printf(__('Dear %s,', 'mikroplaneta-booking'), esc_html($guest->getFullName())); ?></p>
                    
                    <p><?php _e('This is a reminder that your check-out is tomorrow.', 'mikroplaneta-booking'); ?></p>
                    
                    <div class="details">
                        <p><strong><?php _e('Check-out Date:', 'mikroplaneta-booking'); ?></strong> <?php echo esc_html(date_i18n(get_option('date_format'), strtotime($reservation->check_out))); ?></p>
                    </div>
                    
                    <p><?php _e('Thank you for staying with us!', 'mikroplaneta-booking'); ?></p>
                </div>
                
                <div class="footer">
                    <p><?php echo esc_html(get_bloginfo('name')); ?></p>
                </div>
            </div>
        </body>
        </html>
        <?php
        return ob_get_clean();
    }
}
