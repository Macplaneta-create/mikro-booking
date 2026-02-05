<?php
/**
 * License Manager for Mikroplaneta Booking
 * Handles activation, verification, and Dev Mode bypass.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Mikroplaneta_Booking_License {

    private static $option_name = 'mikroplaneta_booking_license';
    private static $api_url = 'https://api.mikroplaneta.pl/verify'; // Placeholder for future API

    /**
     * Check if the license is active.
     * 
     * @return boolean
     */
    public static function is_active() {
        // 1. Dev Mode Bypass
        if ( defined( 'MIKROPLANETA_DEV_MODE' ) && MIKROPLANETA_DEV_MODE === true ) {
            return true;
        }

        // 2. Check Database
        $license = get_option( self::$option_name );
        
        if ( ! $license || empty( $license['key'] ) || empty( $license['status'] ) ) {
            return false;
        }

        if ( $license['status'] !== 'valid' ) {
            return false;
        }

        // 3. Optional: Check expiration logic here
        // if ( time() > $license['expires'] ) return false;

        return true;
    }

    /**
     * Activate a license key.
     */
    public static function activate( $key ) {
        // Mock remote request for now
        $response = self::remote_activation_request( $key );

        if ( $response['success'] ) {
            update_option( self::$option_name, array(
                'key' => $key,
                'status' => 'valid',
                'activated_at' => time()
            ));
            return array( 'success' => true, 'message' => 'License activated successfully.' );
        }

        return array( 'success' => false, 'message' => 'Invalid license key.' );
    }

    /**
     * Mock function to simulate remote API call.
     * Replace with wp_remote_post() in production.
     */
    private static function remote_activation_request( $key ) {
        // In real world, this calls api.mikroplaneta.pl
        if ( strpos( $key, 'mikro-' ) === 0 ) {
            return array( 'success' => true );
        }
        return array( 'success' => false );
    }

    public static function get_license_data() {
        if ( defined( 'MIKROPLANETA_DEV_MODE' ) && MIKROPLANETA_DEV_MODE === true ) {
            return array( 'status' => 'valid', 'type' => 'developer_override' );
        }
        return get_option( self::$option_name, array( 'status' => 'inactive' ) );
    }
}
