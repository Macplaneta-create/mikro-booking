<?php
/**
 * Dashboard Performance Optimization Script
 * 
 * Run this script once to add database indexes for better dashboard performance.
 * 
 * Usage: 
 * 1. Open browser: http://gorytajemnic/wp-content/plugins/mikro-booking/optimize-dashboard.php
 * 2. Or run from CLI: php wp-content/plugins/mikro-booking/optimize-dashboard.php
 */

// Load WordPress
require_once dirname(dirname(dirname(dirname(__FILE__)))) . '/wp-load.php';

if (!current_user_can('manage_options')) {
    // For CLI execution
    if (php_sapi_name() !== 'cli') {
        die('Access denied');
    }
}

echo "=== MikroPlaneta Booking - Dashboard Optimization ===\n\n";

global $wpdb;
$reservations_table = $wpdb->prefix . 'hotel_reservations';
$beds_table = $wpdb->prefix . 'hotel_beds';

echo "Checking database indexes...\n\n";

// Check if index exists
$indexes = $wpdb->get_results("SHOW INDEX FROM {$reservations_table}");
$has_created_at_index = false;

foreach ($indexes as $index) {
    if ($index->Column_name === 'created_at') {
        $has_created_at_index = true;
        break;
    }
}

if (!$has_created_at_index) {
    echo "Adding index on created_at to reservations table...\n";
    $result = $wpdb->query("ALTER TABLE {$reservations_table} ADD INDEX idx_created_at (created_at)");
    if ($result) {
        echo "✅ Index added successfully!\n";
    } else {
        echo "❌ Failed to add index: " . $wpdb->last_error . "\n";
    }
} else {
    echo "✅ Index on created_at already exists\n";
}

// Check beds table
$indexes = $wpdb->get_results("SHOW INDEX FROM {$beds_table}");
$has_room_id_index = false;

foreach ($indexes as $index) {
    if ($index->Column_name === 'room_id') {
        $has_room_id_index = true;
        break;
    }
}

if (!$has_room_id_index) {
    echo "\nAdding index on room_id to beds table...\n";
    $result = $wpdb->query("ALTER TABLE {$beds_table} ADD INDEX idx_room_id (room_id)");
    if ($result) {
        echo "✅ Index added successfully!\n";
    } else {
        echo "❌ Failed to add index: " . $wpdb->last_error . "\n";
    }
} else {
    echo "\n✅ Index on room_id already exists\n";
}

echo "\n=== Optimization Complete ===\n";
echo "\nDashboard should now load faster! 🚀\n";
