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

if (!defined('ABSPATH')) {
    exit;
}

class NotificationService {
    
    /**
     * Send reservation confirmation email
     */
    public function sendReservationConfirmation(Reservation $reservation, Guest $guest): bool {
        $subject = sprintf(
            __('Reservation Confirmation - %s', 'mikroplaneta-booking'),
            get_bloginfo('name')
        );
        
        $message = $this->getReservationConfirmationTemplate($reservation, $guest);
        
        $sent = wp_mail(
            $guest->email,
            $subject,
            $message,
            $this->getEmailHeaders()
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
        $subject = sprintf(
            __('Reservation Cancelled - %s', 'mikroplaneta-booking'),
            get_bloginfo('name')
        );
        
        $message = $this->getReservationCancellationTemplate($reservation, $guest, $reason);
        
        $sent = wp_mail(
            $guest->email,
            $subject,
            $message,
            $this->getEmailHeaders()
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
        $subject = sprintf(
            __('Check-in Reminder - %s', 'mikroplaneta-booking'),
            get_bloginfo('name')
        );
        
        $message = $this->getCheckInReminderTemplate($reservation, $guest);
        
        $sent = wp_mail(
            $guest->email,
            $subject,
            $message,
            $this->getEmailHeaders()
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
        $subject = sprintf(
            __('Check-out Reminder - %s', 'mikroplaneta-booking'),
            get_bloginfo('name')
        );
        
        $message = $this->getCheckOutReminderTemplate($reservation, $guest);
        
        $sent = wp_mail(
            $guest->email,
            $subject,
            $message,
            $this->getEmailHeaders()
        );
        
        if ($sent) {
            do_action('mikroplaneta_booking_notification_sent', 'checkout_reminder', $reservation, $guest);
        }
        
        return $sent;
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
