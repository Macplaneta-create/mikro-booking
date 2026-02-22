<?php
require_once 'c:/laragon/www/gorytajemnic/wp-load.php';
global $wpdb;

$table = $wpdb->prefix . 'hotel_rooms';
$results = $wpdb->get_results("SHOW COLUMNS FROM $table");

echo "Database: " . DB_NAME . "\n";
echo "MySQL Version: " . $wpdb->db_version() . "\n";
echo "Table: $table\n";
echo "Columns:\n";

foreach ($results as $column) {
    echo "- {$column->Field}: {$column->Type}\n";
}

$room_count = $wpdb->get_var("SELECT COUNT(*) FROM $table");
echo "Total Rooms: $room_count\n";
