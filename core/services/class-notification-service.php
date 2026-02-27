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

if (!defined('ABSPATH')) {
    exit;
}

class NotificationService {
    private const TEMPLATE_DEFINITIONS = [
        'reservation_confirmation' => 'Potwierdzenie rezerwacji',
        'reservation_cancellation' => 'Anulowanie rezerwacji',
        'checkin_reminder' => 'Przypomnienie o zameldowaniu',
        'checkout_reminder' => 'Przypomnienie o wymeldowaniu',
    ];

    private ?bool $notifications_table_available = null;
    
    /**
     * Send reservation confirmation email
     */
    public function sendReservationConfirmation(Reservation $reservation, Guest $guest): bool {
        [$subject, $message] = $this->resolveTemplate('reservation_confirmation', $reservation, $guest);
        
        $sent = wp_mail(
            $guest->email,
            $subject,
            $message,
            $this->getEmailHeaders()
        );

        $this->logNotification(
            'reservation_confirmation',
            $reservation,
            $guest,
            $sent,
            $sent ? '' : 'wp_mail() returned false'
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
        
        $sent = wp_mail(
            $guest->email,
            $subject,
            $message,
            $this->getEmailHeaders()
        );

        $this->logNotification(
            'reservation_cancellation',
            $reservation,
            $guest,
            $sent,
            $sent ? '' : 'wp_mail() returned false'
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
        [$subject, $message] = $this->resolveTemplate('checkin_reminder', $reservation, $guest);
        
        $sent = wp_mail(
            $guest->email,
            $subject,
            $message,
            $this->getEmailHeaders()
        );

        $this->logNotification(
            'checkin_reminder',
            $reservation,
            $guest,
            $sent,
            $sent ? '' : 'wp_mail() returned false'
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
        [$subject, $message] = $this->resolveTemplate('checkout_reminder', $reservation, $guest);
        
        $sent = wp_mail(
            $guest->email,
            $subject,
            $message,
            $this->getEmailHeaders()
        );

        $this->logNotification(
            'checkout_reminder',
            $reservation,
            $guest,
            $sent,
            $sent ? '' : 'wp_mail() returned false'
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

        return wp_mail($to_email, $subject, $message, $this->getEmailHeaders());
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
                return sprintf(__('Reservation Confirmation - %s', 'mikroplaneta-booking'), get_bloginfo('name'));
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
