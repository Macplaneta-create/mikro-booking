<?php
require_once 'c:/laragon/www/gorytajemnic/wp-load.php';
global $wpdb;

$table = $wpdb->prefix . 'hotel_rooms';

// 1. Check schema again
$columns = $wpdb->get_results("SHOW COLUMNS FROM $table");
echo "Current Columns in $table:\n";
foreach ($columns as $col) {
    echo "- {$col->Field} ({$col->Type})\n";
}

// 2. Attempt a raw insert
echo "\nAttempting raw insert...\n";
$insert_data = [
    'name' => 'Debug Room ' . time(),
    'description' => 'Test description',
    'image_id' => null,
    'amenities' => '[]',
    'floor' => 1,
    'room_type' => 'standard',
    'status' => 'active'
];

$result = $wpdb->insert($table, $insert_data);

if ($result === false) {
    echo "ERROR: " . $wpdb->last_error . "\n";
} else {
    echo "SUCCESS: Inserted ID " . $wpdb->insert_id . "\n";
    $wpdb->delete($table, ['id' => $wpdb->insert_id]);
    echo "Cleaned up test record.\n";
}
