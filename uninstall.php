<?php
/**
 * Uninstall Script
 *
 * Fired when the plugin is uninstalled.
 * Removes all plugin data from the database.
 *
 * @package MikroPlaneta\Booking
 * @since 1.0.0
 */

// If uninstall not called from WordPress, exit
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

global $wpdb;

// Define table names
$tables = [
    $wpdb->prefix . 'hotel_ai_suggestions',
    $wpdb->prefix . 'hotel_changes_log',
    $wpdb->prefix . 'hotel_notifications',
    $wpdb->prefix . 'hotel_allocations_log',
    $wpdb->prefix . 'hotel_reservations',
    $wpdb->prefix . 'hotel_beds',
    $wpdb->prefix . 'hotel_rooms',
    $wpdb->prefix . 'hotel_guests',
];

// Drop tables in reverse order (to respect foreign keys)
foreach ($tables as $table) {
    $wpdb->query("DROP TABLE IF EXISTS {$table}");
}

// Delete plugin options
$options = [
    'mikroplaneta_booking_version',
    'mikroplaneta_booking_installed_at',
    'mikroplaneta_booking_migrations',
];

foreach ($options as $option) {
    delete_option($option);
}

// Clear transients
$wpdb->query(
    "DELETE FROM {$wpdb->options} 
    WHERE option_name LIKE '_transient_mikroplaneta_booking_%' 
    OR option_name LIKE '_transient_timeout_mikroplaneta_booking_%'"
);

// Clear any cached data
wp_cache_flush();
