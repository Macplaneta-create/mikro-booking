<?php
require_once 'c:/laragon/www/gorytajemnic/wp-load.php';
global $wpdb;

$table = $wpdb->prefix . 'hotel_rooms';
$columns = $wpdb->get_col("DESCRIBE $table");

$missing = [];
$target_columns = ['description', 'image_id', 'amenities', 'status'];
foreach ($target_columns as $col) {
    if (!in_array($col, $columns)) {
        $missing[] = $col;
    }
}

echo "Missing columns: " . implode(', ', $missing) . "\n";

if (in_array('description', $missing)) {
    $wpdb->query("ALTER TABLE $table ADD COLUMN description TEXT AFTER name");
    echo "Added description\n";
}
if (in_array('image_id', $missing)) {
    $wpdb->query("ALTER TABLE $table ADD COLUMN image_id BIGINT UNSIGNED AFTER description");
    echo "Added image_id\n";
}
if (in_array('amenities', $missing)) {
    // Check MySQL version for JSON support
    $ver = $wpdb->db_version();
    $type = (version_compare($ver, '5.7.8', '>=') || strpos($ver, 'MariaDB') !== false) ? 'JSON' : 'LONGTEXT';
    $wpdb->query("ALTER TABLE $table ADD COLUMN amenities $type AFTER image_id");
    echo "Added amenities ($type)\n";
}
if (in_array('status', $missing)) {
    $wpdb->query("ALTER TABLE $table ADD COLUMN status ENUM('active', 'inactive', 'maintenance') DEFAULT 'active' AFTER room_type");
    $wpdb->query("CREATE INDEX idx_status ON $table (status)");
    echo "Added status and index\n";
}

echo "Repair finished.\n";
