<?php
/**
 * Force Database Update Script
 *
 * This script forces the execution of all migrations to ensure the database is up to date.
 * Access this via browser: /wp-admin/admin-post.php?action=mikroplaneta_force_update
 */

add_action('admin_post_mikroplaneta_force_update', function() {
    // Only available in debug/development mode
    if (!defined('WP_DEBUG') || !WP_DEBUG) {
        wp_die('This tool is only available in development mode (WP_DEBUG must be enabled).');
    }

    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized');
    }
    
    require_once MIKROPLANETA_BOOKING_PLUGIN_DIR . 'core/database/class-database.php';
    require_once MIKROPLANETA_BOOKING_PLUGIN_DIR . 'core/database/class-schema.php';
    
    $db = new \MikroPlaneta\Booking\Core\Database\Database();
    
    echo '<h1>Updating Database...</h1>';
    
    try {
        // Force run all migrations
        $migrations = glob(MIKROPLANETA_BOOKING_PLUGIN_DIR . 'core/database/migrations/*.php');
        sort($migrations);
        
        foreach ($migrations as $file) {
            $name = basename($file);
            echo "Running {$name}... ";
            
            require_once $file;
            
            // Extract class name
            $class_name = str_replace('.php', '', $name);
            $parts = explode('-', $class_name);
            $class_parts = array_map('ucfirst', $parts);
            $full_class_name = 'MikroPlaneta\\Booking\\Core\\Database\\Migrations\\Migration_' . implode('_', $class_parts);
            
            if (class_exists($full_class_name)) {
                $full_class_name::up();
                echo "<span style='color:green'>DONE</span><br>";
            } else {
                echo "<span style='color:red'>Class not found: {$full_class_name}</span><br>";
            }
        }
        
        echo '<h2>All Done!</h2>';
        
        // Debug: Show room table structure
        echo '<h3>Rooms Table Structure:</h3>';
        $rooms_table = \MikroPlaneta\Booking\Core\Database\Schema::get_table_name('rooms');
        $columns = $db->get_results("SHOW COLUMNS FROM {$rooms_table}");
        
        if ($columns) {
            echo '<table border="1" style="border-collapse: collapse; width: 100%;">';
            echo '<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>';
            foreach ($columns as $col) {
                echo "<tr>
                    <td>{$col->Field}</td>
                    <td>{$col->Type}</td>
                    <td>{$col->Null}</td>
                    <td>{$col->Key}</td>
                    <td>{$col->Default}</td>
                    <td>{$col->Extra}</td>
                </tr>";
            }
            echo '</table>';
        } else {
            echo "<p style='color:red'>Table {$rooms_table} not found!</p>";
        }

        // TEST ROOM CREATION
        echo '<h3>Test Room Creation:</h3>';
        try {
            require_once MIKROPLANETA_BOOKING_PLUGIN_DIR . 'core/models/class-room.php';
            require_once MIKROPLANETA_BOOKING_PLUGIN_DIR . 'core/repositories/interface-repository.php';
            require_once MIKROPLANETA_BOOKING_PLUGIN_DIR . 'core/repositories/class-room-repository.php';
            
            $repo = new \MikroPlaneta\Booking\Core\Repositories\RoomRepository();
            $test_data = [
                'name' => 'Test Room ' . time(),
                'floor' => 1,
                'room_type' => 'standard' // Valid enum
            ];
            
            echo "Attempting to create room: " . json_encode($test_data) . "<br>";
            $room = $repo->create($test_data);
            echo "<span style='color:green'>SUCCESS! Created room ID: {$room->id}</span><br>";
            
            // Clean up
            $repo->delete($room->id);
            echo "Cleaned up test room.<br>";
            
        } catch (Exception $e) {
            echo "<span style='color:red; font-weight:bold;'>FAILED: " . $e->getMessage() . "</span><br>";
            echo "<pre>" . $e->getTraceAsString() . "</pre>";
            
            // Show error log content if exists
            $log_file = MIKROPLANETA_BOOKING_PLUGIN_DIR . 'error_log.txt';
            if (file_exists($log_file)) {
                echo "<h4>Error Log Content:</h4><pre>" . file_get_contents($log_file) . "</pre>";
            }
        }

        // Explicitly fix pricing_type column in extra_services (dbDelta sometimes misses ENUM changes)
        $extras_table = \MikroPlaneta\Booking\Core\Database\Schema::get_table_name('extra_services');
        echo "Fixing pricing_type ENUM... ";
        $wpdb->query("ALTER TABLE {$extras_table} MODIFY COLUMN pricing_type ENUM('per_stay', 'per_unit', 'per_person') NOT NULL DEFAULT 'per_stay'");
        echo "<span style='color:green'>DONE</span><br>";

        // Fix rooms table columns
        $rooms_table = \MikroPlaneta\Booking\Core\Database\Schema::get_table_name('rooms');
        echo "Updating rooms table columns... ";
        $wpdb->query("ALTER TABLE {$rooms_table} MODIFY COLUMN status ENUM('active', 'inactive', 'maintenance') DEFAULT 'active'");
        echo "<span style='color:green'>DONE</span><br>";

        echo '<p><a href="' . admin_url('admin.php?page=mikroplaneta-booking') . '">Go back to Booking</a></p>';
        
    } catch (Exception $e) {
        echo "<h2 style='color:red'>Error: " . $e->getMessage() . "</h2>";
        echo '<pre>' . $e->getTraceAsString() . '</pre>';
    }
});
