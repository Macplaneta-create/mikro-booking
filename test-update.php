<?php
require_once 'c:/laragon/www/gorytajemnic/wp-load.php';
global $wpdb;

$table = $wpdb->prefix . 'hotel_rooms';

// Get a room to update
$room_id = $wpdb->get_var("SELECT id FROM $table LIMIT 1");

if (!$room_id) {
    echo "No rooms found to update. Creating one first.\n";
    $wpdb->insert($table, ['name' => 'Initial Room', 'room_type' => 'standard']);
    $room_id = $wpdb->insert_id;
}

echo "Updating room ID: $room_id\n";

require_once MIKROPLANETA_BOOKING_PLUGIN_DIR . 'core/models/class-room.php';
require_once MIKROPLANETA_BOOKING_PLUGIN_DIR . 'core/repositories/interface-repository.php';
require_once MIKROPLANETA_BOOKING_PLUGIN_DIR . 'core/repositories/class-room-repository.php';

$repo = new \MikroPlaneta\Booking\Core\Repositories\RoomRepository();

try {
    // Try update with new fields
    $data = [
        'description' => 'Updated at ' . date('H:i:s'),
        'image_id' => 0, // Simulate clearing image
        'amenities' => ['wifi', 'tv'],
        'status' => 'active'
    ];
    
    $updated = $repo->update($room_id, $data);
    echo "SUCCESS: Room updated. Description: " . $updated->description . "\n";
    
} catch (\Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
