<?php
require_once 'c:/laragon/www/gorytajemnic/wp-load.php';
global $wpdb;

$table = $wpdb->prefix . 'hotel_beds';
$results = $wpdb->get_results("SHOW COLUMNS FROM $table");

if (!$results) {
    echo "Table $table NOT FOUND!\n";
} else {
    echo "Table $table exists.\nColumns:\n";
    foreach ($results as $column) {
        echo "- {$column->Field}: {$column->Type}\n";
    }
}
