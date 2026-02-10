<?php
/**
 * REST API Security and Registration
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Mikroplaneta_Booking_API {
    private $namespace = 'mikroplaneta-booking/v1';

    public function register_routes() {
        // Legacy API disabled by default for security. Opt-in with a filter if needed.
        if (!apply_filters('mikroplaneta_booking_enable_legacy_api', false)) {
            return;
        }

        register_rest_route( $this->namespace, '/dashboard/stats', array(
            'methods'  => 'GET',
            'callback' => array( $this, 'get_dashboard_stats' ),
            'permission_callback' => array( $this, 'check_permission' ),
        ) );

        register_rest_route( $this->namespace, '/rooms', array(
            array(
                'methods'  => 'GET',
                'callback' => array( $this, 'get_rooms' ),
                'permission_callback' => array( $this, 'check_permission' ),
            ),
            array(
                'methods'  => 'POST',
                'callback' => array( $this, 'create_room' ),
                'permission_callback' => array( $this, 'check_permission' ),
            )
        ) );

        register_rest_route( $this->namespace, '/bookings/check', array(
            'methods'  => 'POST',
            'callback' => array( $this, 'api_check_availability' ),
            'permission_callback' => array( $this, 'check_permission' ),
        ) );

        register_rest_route( $this->namespace, '/bookings', array(
            'methods'  => 'POST',
            'callback' => array( $this, 'api_create_booking' ),
            'permission_callback' => array( $this, 'check_permission' ),
        ) );
    }

    public function api_check_availability( $request ) {
        $params = $request->get_json_params();
        $check_in = sanitize_text_field( $params['check_in'] ?? '' );
        $check_out = sanitize_text_field( $params['check_out'] ?? '' );
        $guests = intval( $params['guests'] ?? 1 );

        if ( ! $check_in || ! $check_out ) {
            return new WP_Error( 'missing_dates', 'Dates are required', array( 'status' => 400 ) );
        }

        $engine = new Mikroplaneta_Booking_Engine();
        $results = $engine->check_availability( $check_in, $check_out, $guests );

        return new WP_REST_Response( $results, 200 );
    }

    public function api_create_booking( $request ) {
        $data = $request->get_json_params();
        $engine = new Mikroplaneta_Booking_Engine();
        $result = $engine->create_booking( $data );

        if ( is_wp_error( $result ) ) return $result;

        return new WP_REST_Response( $result, 201 );
    }

    public function check_permission() {
        // 1. Basic Security: Nonce Verification (handled by WP usually, but good to be explicit for custom auth)
        // Note: For cookie based auth, 'manage_options' cap check is sufficient.
        
        // 2. Capability Check
        if ( ! current_user_can( 'manage_options' ) ) {
            return new WP_Error( 'rest_forbidden', 'Sorry, you are not allowed to do that.', array( 'status' => 403 ) );
        }

        // 3. License Check
        if ( ! Mikroplaneta_Booking_License::is_active() ) {
            return new WP_Error( 'rest_forbidden', 'License is inactive. Please activate Mikroplaneta Booking.', array( 'status' => 403 ) );
        }

        return true;
    }

    public function get_dashboard_stats( $request ) {
        // Placeholder for stats
        return new WP_REST_Response( array(
            'arrivals_today'   => 5,
            'departures_today' => 3,
            'occupancy_rate'   => 75,
            'active_bookings'  => 12
        ), 200 );
    }

    public function get_rooms( $request ) {
        $service = new Mikroplaneta_Room_Service();
        $rooms = $service->get_all_rooms();
        return new WP_REST_Response( $rooms, 200 );
    }

    public function create_room( $request ) {
        $data = $request->get_json_params();
        
        if ( empty( $data['name'] ) ) {
            return new WP_Error( 'missing_param', 'Room name is required', array( 'status' => 400 ) );
        }

        $service = new Mikroplaneta_Room_Service();
        $result = $service->create_room( $data );

        if ( is_wp_error( $result ) ) {
            return $result;
        }

        return new WP_REST_Response( $result, 201 );
    }
}
