<?php
/**
 * Room Service: Handles CRUD for Rooms & Beds (Inventory)
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Mikroplaneta_Room_Service {

    private $table_rooms;
    private $table_beds;

    public function __construct() {
        global $wpdb;
        $this->table_rooms = $wpdb->prefix . 'mb_rooms';
        $this->table_beds = $wpdb->prefix . 'mb_beds';
    }

    /**
     * Create a new room with optional beds.
     */
    public function create_room( $data ) {
        global $wpdb;
        
        $name = sanitize_text_field( $data['name'] );
        $type = sanitize_text_field( $data['type'] ); // 'private' or 'dorm'
        $description = isset($data['description']) ? sanitize_textarea_field( $data['description'] ) : '';

        // Transaction start
        $wpdb->query( 'START TRANSACTION' );

        $inserted = $wpdb->insert(
            $this->table_rooms,
            array(
                'name' => $name,
                'type' => $type,
                'description' => $description,
                'status' => 'active'
            )
        );

        if ( ! $inserted ) {
            $wpdb->query( 'ROLLBACK' );
            return new WP_Error( 'db_error', 'Could not create room' );
        }

        $room_id = $wpdb->insert_id;

        // If beds are provided, insert them
        if ( ! empty( $data['beds'] ) && is_array( $data['beds'] ) ) {
            foreach ( $data['beds'] as $bed ) {
                $wpdb->insert(
                    $this->table_beds,
                    array(
                        'room_id' => $room_id,
                        'name' => sanitize_text_field( $bed['name'] ),
                        'type' => sanitize_text_field( $bed['type'] ),
                        'status' => 'active'
                    )
                );
            }
        }

        $wpdb->query( 'COMMIT' );

        return $this->get_room( $room_id );
    }

    /**
     * Get all rooms with their beds.
     */
    public function get_all_rooms() {
        global $wpdb;
        
        $rooms = $wpdb->get_results( "SELECT * FROM {$this->table_rooms} WHERE status != 'inactive'" );
        
        if ( empty( $rooms ) ) {
            return array();
        }

        // Fetch beds for these rooms efficiently
        $room_ids = wp_list_pluck( $rooms, 'id' );
        $ids_string = implode(',', array_map('intval', $room_ids));
        
        $beds = $wpdb->get_results( "SELECT * FROM {$this->table_beds} WHERE room_id IN ($ids_string) AND status != 'inactive'" );

        // Map beds to rooms
        $beds_by_room = array();
        foreach ( $beds as $bed ) {
            $beds_by_room[$bed->room_id][] = $bed;
        }

        foreach ( $rooms as $room ) {
            $room->beds = isset( $beds_by_room[$room->id] ) ? $beds_by_room[$room->id] : array();
        }

        return $rooms;
    }

    public function get_room( $id ) {
        global $wpdb;
        $room = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$this->table_rooms} WHERE id = %d", $id ) );
        if ( $room ) {
            $beds = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$this->table_beds} WHERE room_id = %d", $id ) );
            $room->beds = $beds;
        }
        return $room;
    }
}
