<?php
/**
 * Main Plugin Class
 *
 * Singleton pattern - orchestrates plugin initialization
 * Registers hooks, loads dependencies, initializes services
 *
 * @package MikroPlaneta\Booking
 * @since 1.0.0
 */

namespace MikroPlaneta\Booking\Core;

if (!defined('ABSPATH')) {
    exit;
}

class Plugin {
    
    /**
     * Singleton instance
     */
    private static ?Plugin $instance = null;
    
    /**
     * Plugin version
     */
    private const VERSION = '1.0.0';
    
    /**
     * Get singleton instance
     */
    public static function get_instance(): Plugin {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Private constructor (singleton)
     */
    private function __construct() {
        $this->load_dependencies();
        $this->define_hooks();
    }
    
    /**
     * Load required dependencies
     */
    private function load_dependencies(): void {
        $dir = MIKROPLANETA_BOOKING_PLUGIN_DIR;
        
        // 1. Core Interfaces & Base Classes
        require_once $dir . 'core/repositories/interface-repository.php';
        require_once $dir . 'rest-api/class-rest-controller.php';
        
        // 1b. Database Classes (Required by Repositories)
        require_once $dir . 'core/database/class-schema.php';
        require_once $dir . 'core/database/class-database.php';
        
        // 2. Models
        require_once $dir . 'core/models/class-room.php';
        require_once $dir . 'core/models/class-bed.php';
        require_once $dir . 'core/models/class-guest.php';
        require_once $dir . 'core/models/class-reservation.php';
        
        // 3. Repositories
        require_once $dir . 'core/repositories/class-room-repository.php';
        require_once $dir . 'core/repositories/class-bed-repository.php';
        require_once $dir . 'core/repositories/class-guest-repository.php';
        require_once $dir . 'core/repositories/class-reservation-repository.php';
        
        // 4. Services
        require_once $dir . 'core/services/class-availability-service.php';
        require_once $dir . 'core/services/class-notification-service.php';
        require_once $dir . 'core/services/class-reservation-service.php';
        require_once $dir . 'core/services/class-guest-service.php';
        
        // 5. REST API Controllers
        require_once $dir . 'rest-api/controllers/class-rooms-controller.php';
        require_once $dir . 'rest-api/controllers/class-reservations-controller.php';
        require_once $dir . 'rest-api/controllers/class-guests-controller.php';
        require_once $dir . 'rest-api/controllers/class-availability-controller.php';
        
        // 6. Routes
        require_once $dir . 'rest-api/routes.php';
        
        // 7. Admin & Frontend
        require_once $dir . 'core/class-admin.php';
        require_once $dir . 'public/class-frontend.php';
    }
    
    /**
     * Register WordPress hooks
     */
    private function define_hooks(): void {
        // Initialize admin
        if (is_admin()) {
            new \MikroPlaneta\Booking\Core\Admin();
        }

        // Initialize frontend
        new \MikroPlaneta\Booking\Core\Frontend();
    }
    
    /**
     * Get plugin version
     */
    public function get_version(): string {
        return self::VERSION;
    }
}
