<?php
require_once 'c:/laragon/www/gorytajemnic/wp-load.php';
global $wpdb;

$table = $wpdb->prefix . 'hotel_rooms';
$results = $wpdb->get_results("SHOW COLUMNS FROM $table");

$output = "Table: $table\nColumns:\n";
foreach ($results as $column) {
    $output .= "- {$column->Field}: {$column->Type}\n";
}

file_put_contents(__DIR__ . '/db_status.txt', $output);
echo "Status saved to db_status.txt\n";
