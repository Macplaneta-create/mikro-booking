<?php
/**
 * Plugin Name: MikroPlaneta Booking
 * Plugin URI: https://mikroplaneta.pl/booking
 * Description: Advanced hotel booking system with AI-powered bed allocation
 * Version: 1.2.3
 * Requires at least: 6.0
 * Requires PHP: 8.0
 * Author: MikroPlaneta
 * Author URI: https://mikroplaneta.pl
 * License: GPL v3 or later
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain: mikroplaneta-booking
 * Domain Path: /languages
 *
 * @package MikroPlaneta\Booking
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// Plugin constants
define('MIKROPLANETA_BOOKING_VERSION', '1.2.3');
define('MIKROPLANETA_BOOKING_PLUGIN_FILE', __FILE__);
define('MIKROPLANETA_BOOKING_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('MIKROPLANETA_BOOKING_PLUGIN_URL', plugin_dir_url(__FILE__));

// Composer autoloader
if (file_exists(MIKROPLANETA_BOOKING_PLUGIN_DIR . 'vendor/autoload.php')) {
    require_once MIKROPLANETA_BOOKING_PLUGIN_DIR . 'vendor/autoload.php';
}

// Core classes
require_once MIKROPLANETA_BOOKING_PLUGIN_DIR . 'core/class-plugin.php';
require_once MIKROPLANETA_BOOKING_PLUGIN_DIR . 'core/class-activator.php';
require_once MIKROPLANETA_BOOKING_PLUGIN_DIR . 'core/class-deactivator.php';

use MikroPlaneta\Booking\Core\Plugin;
use MikroPlaneta\Booking\Core\Activator;
use MikroPlaneta\Booking\Core\Deactivator;

/**
 * Activation hook
 */
register_activation_hook(__FILE__, function() {
    Activator::activate();
});

/**
 * Deactivation hook
 */
register_deactivation_hook(__FILE__, function() {
    Deactivator::deactivate();
});

/**
 * Initialize the plugin
 */
function mikroplaneta_booking_init() {
    return Plugin::get_instance();
}

// Start the plugin
mikroplaneta_booking_init();
