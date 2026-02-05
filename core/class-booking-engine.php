<?php
/**
 * Booking Engine: The Heart of the System
 * Handles Availability Checks & Reservation Creation (ACID)
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Mikroplaneta_Booking_Engine {

    private $table_reservations;
    private $table_bookings;
    private $table_rooms;
    private $table_beds;
    private $table_guests;

    public function __construct() {
        global $wpdb;
        $this->table_reservations = $wpdb->prefix . 'mb_reservations';
        $this->table_bookings = $wpdb->prefix . 'mb_bookings';
        $this->table_rooms = $wpdb->prefix . 'mb_rooms';
        $this->table_beds = $wpdb->prefix . 'mb_beds';
        $this->table_guests = $wpdb->prefix . 'mb_guests';
    }

    /**
     * Search for available rooms/beds
     */
    public function check_availability( $check_in, $check_out, $guests = 1 ) {
        global $wpdb;

        // 1. Get all active rooms and their beds
        $room_service = new Mikroplaneta_Room_Service();
        $all_rooms = $room_service->get_all_rooms();
        
        // 2. Get all overlapping reservations
        // Logic: (StartA <= EndB) and (EndA >= StartB)
        // Implementation: NOT (EndA < StartB OR StartA > EndB) -> Overlap
        $query = $wpdb->prepare(
            "SELECT room_id, bed_id, is_private_room_booking 
             FROM {$this->table_reservations} 
             WHERE NOT (check_out <= %s OR check_in >= %s)",
            $check_in, $check_out
        );
        $occupied = $wpdb->get_results( $query );

        // 3. Map Occupancy
        $blocked_rooms = array(); // Room IDs explicitly blocked (Private Booking)
        $blocked_beds = array();  // Bed IDs occupied

        foreach ( $occupied as $res ) {
            if ( $res->is_private_room_booking ) {
                $blocked_rooms[ $res->room_id ] = true;
            }
            if ( $res->bed_id ) {
                $blocked_beds[ $res->bed_id ] = true;
            }
        }

        // 4. Filter Available Inventory
        $available_options = array();

        foreach ( $all_rooms as $room ) {
            // Rule A: If room is fully blocked via 'Private Room Booking', skip
            if ( isset( $blocked_rooms[ $room->id ] ) ) {
                continue;
            }

            if ( $room->type === 'private' ) {
                // Private Room Logic: Available ONLY if NO beds are taken
                // (We assume if a private room has a reservation, it's either fully blocked or beds are used)
                // Actually, for private rooms we normally book the whole thing.
                // But if data integrity failed and a bed is booked, we should treat room as unavailable.
                
                $is_any_bed_taken = false;
                if ( ! empty( $room->beds ) ) {
                    foreach ( $room->beds as $bed ) {
                        if ( isset( $blocked_beds[ $bed->id ] ) ) {
                            $is_any_bed_taken = true;
                            break;
                        }
                    }
                }

                if ( ! $is_any_bed_taken ) {
                    // Check capacity (number of beds vs requested guests)
                    $capacity = count( $room->beds );
                     // Simplification: if guests fit in capacity, show room
                    if ( $capacity >= $guests ) {
                        $available_options[] = array(
                            'type' => 'room',
                            'room_id' => $room->id,
                            'name' => $room->name,
                            'capacity' => $capacity,
                            'price_estimate' => 100 * $guests // @TODO: Use Price Matrix
                        );
                    }
                }

            } elseif ( $room->type === 'dorm' ) {
                // Dorm Logic: Count free beds
                $free_beds = array();
                foreach ( $room->beds as $bed ) {
                    if ( ! isset( $blocked_beds[ $bed->id ] ) ) {
                        $free_beds[] = $bed;
                    }
                }

                // If we have enough free beds for the group (or at least 1 if we allow splitting)
                // For now, let's list individual beds or the group option
                if ( count( $free_beds ) > 0 ) {
                    $available_options[] = array(
                        'type' => 'bed',
                        'room_id' => $room->id,
                        'name' => "Miejsca w: " . $room->name,
                        'available_count' => count( $free_beds ),
                        'price_estimate' => 50 // @TODO: Use Price Matrix
                    );
                }
            }
        }

        return $available_options;
    }

    /**
     * Create a Booking (Transaction)
     */
    public function create_booking( $data ) {
        global $wpdb;
        
        // Validate Data
        if ( empty($data['guest']) || empty($data['check_in']) || empty($data['check_out']) || empty($data['items']) ) {
            return new WP_Error('invalid_data', 'Missing required fields');
        }

        $check_in = $data['check_in'];
        $check_out = $data['check_out'];
        $items = $data['items']; // Array of { room_id, bed_id (optional), is_private }

        try {
            $wpdb->query( 'START TRANSACTION' );

            // 1. Create/Find Guest
            $guest_id = $this->get_or_create_guest( $data['guest'] );
            if ( is_wp_error($guest_id) ) throw new Exception($guest_id->get_error_message());

            // 2. Create Master Booking (Order)
            $total_price = 0; // Calculate sum
            $wpdb->insert( $this->table_bookings, array(
                'guest_id' => $guest_id,
                'total_price' => 0, // Update later
                'status' => 'confirmed'
            ));
            $booking_id = $wpdb->insert_id;

            // 3. Create Reservations (Atomic Items)
            foreach ( $items as $item ) {
                // Double check availability (Race condition protection)
                if ( ! $this->is_slot_free( $item['room_id'], $item['bed_id'] ?? null, $check_in, $check_out ) ) {
                    throw new Exception('Wybrane miejsce zostało w międzyczasie zajęte. Spróbuj ponownie.');
                }

                $price = 100; // @TODO: Real pricing
                $total_price += $price;

                $wpdb->insert( $this->table_reservations, array(
                    'booking_id' => $booking_id,
                    'room_id' => $item['room_id'],
                    'bed_id' => $item['bed_id'] ?? null,
                    'check_in' => $check_in,
                    'check_out' => $check_out,
                    'price_per_night' => $price,
                    'is_private_room_booking' => !empty($item['is_private']) ? 1 : 0
                ));
            }

            // Update total price
            $wpdb->update( $this->table_bookings, array( 'total_price' => $total_price ), array( 'id' => $booking_id ) );

            $wpdb->query( 'COMMIT' );
            return array( 'booking_id' => $booking_id, 'status' => 'success' );

        } catch ( Exception $e ) {
            $wpdb->query( 'ROLLBACK' );
            return new WP_Error( 'booking_failed', $e->getMessage() );
        }
    }

    private function get_or_create_guest( $guest_data ) {
        global $wpdb;
        $email = sanitize_email( $guest_data['email'] );
        
        // Find existing
        $exists = $wpdb->get_var( $wpdb->prepare("SELECT id FROM {$this->table_guests} WHERE email = %s", $email) );
        if ( $exists ) return $exists;

        // Create new
        $wpdb->insert( $this->table_guests, array(
            'first_name' => sanitize_text_field( $guest_data['first_name'] ),
            'last_name' => sanitize_text_field( $guest_data['last_name'] ),
            'email' => $email,
            'phone' => sanitize_text_field( $guest_data['phone'] ?? '' )
        ));
        
        return $wpdb->insert_id;
    }

    private function is_slot_free( $room_id, $bed_id, $start, $end ) {
        global $wpdb;
        // Check strict overlap for specific resource
        
        $query = "SELECT id FROM {$this->table_reservations} 
                  WHERE room_id = %d 
                  AND NOT (check_out <= %s OR check_in >= %s)";
        $params = array( $room_id, $start, $end );

        if ( $bed_id ) {
            $query .= " AND bed_id = %d";
            $params[] = $bed_id;
        } else {
            // If checking room (private), check if ANY reservation exists in room
            // (either private room block OR individual bed booking)
             // Already covered by room_id check above
        }

        $result = $wpdb->get_var( $wpdb->prepare( $query, $params ) );
        return is_null($result);
    }
}
