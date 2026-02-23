<?php
/**
 * Admin Menu Handler
 *
 * Registers admin menu and pages
 *
 * @package MikroPlaneta\Booking
 * @since 1.0.0
 */

namespace MikroPlaneta\Booking\Core;

if (!defined('ABSPATH')) {
    exit;
}

class Admin {
    
    /**
     * Initialize admin
     */
    public function __construct() {
        add_action('admin_menu', [$this, 'register_menu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
    }
    
    /**
     * Register admin menu
     */
    public function register_menu(): void {
        add_menu_page(
            __('MikroPlaneta Booking', 'mikroplaneta-booking'),
            __('Booking', 'mikroplaneta-booking'),
            'manage_options',
            'mikroplaneta-booking',
            [$this, 'render_admin_page'],
            'dashicons-calendar-alt',
            30
        );
        
        // Submenu pages
        add_submenu_page(
            'mikroplaneta-booking',
            __('Dashboard', 'mikroplaneta-booking'),
            __('Dashboard', 'mikroplaneta-booking'),
            'manage_options',
            'mikroplaneta-booking',
            [$this, 'render_admin_page']
        );
        
        add_submenu_page(
            'mikroplaneta-booking',
            __('Reservations', 'mikroplaneta-booking'),
            __('Reservations', 'mikroplaneta-booking'),
            'manage_options',
            'mikroplaneta-booking-reservations',
            [$this, 'render_admin_page']
        );
        
        add_submenu_page(
            'mikroplaneta-booking',
            __('Rooms & Beds', 'mikroplaneta-booking'),
            __('Rooms & Beds', 'mikroplaneta-booking'),
            'manage_options',
            'mikroplaneta-booking-rooms',
            [$this, 'render_admin_page']
        );
        
        add_submenu_page(
            'mikroplaneta-booking',
            __('Guests', 'mikroplaneta-booking'),
            __('Guests', 'mikroplaneta-booking'),
            'manage_options',
            'mikroplaneta-booking-guests',
            [$this, 'render_admin_page']
        );
        
        add_submenu_page(
            'mikroplaneta-booking',
            __('Pricing', 'mikroplaneta-booking'),
            __('Pricing', 'mikroplaneta-booking'),
            'manage_options',
            'mikroplaneta-booking-pricing',
            [$this, 'render_admin_page']
        );

        add_submenu_page(
            'mikroplaneta-booking',
            __('Extra Services', 'mikroplaneta-booking'),
            __('Services', 'mikroplaneta-booking'),
            'manage_options',
            'mikroplaneta-booking-services',
            [$this, 'render_admin_page']
        );
        
        add_submenu_page(
            'mikroplaneta-booking',
            __('Settings', 'mikroplaneta-booking'),
            __('Settings', 'mikroplaneta-booking'),
            'manage_options',
            'mikroplaneta-booking-settings',
            [$this, 'render_admin_page']
        );
        
        add_submenu_page(
            'mikroplaneta-booking',
            __('Database Migrations', 'mikroplaneta-booking'),
            __('Migrations', 'mikroplaneta-booking'),
            'manage_options',
            'mikroplaneta-booking-migrations',
            [$this, 'render_migrations_page']
        );
    }
    
    /**
     * Render admin page (React app)
     */
    public function render_admin_page(): void {
        $js_file = MIKROPLANETA_BOOKING_PLUGIN_DIR . 'assets/admin/index.js';
        
        if (!file_exists($js_file)) {
            ?>
            <div class="wrap">
                <h1><?php _e('MikroPlaneta Booking', 'mikroplaneta-booking'); ?></h1>
                <div class="notice notice-error">
                    <p>
                        <strong><?php _e('Frontend Build Missing', 'mikroplaneta-booking'); ?></strong><br>
                        <?php _e('The React frontend application has not been built properly.', 'mikroplaneta-booking'); ?><br>
                        <?php _e('Please run the following commands in the plugin directory to build the frontend:', 'mikroplaneta-booking'); ?>
                    </p>
                    <pre>cd admin && npm install && npm run build</pre>
                    <p><em>(Error code: Build artifacts not found in assets/admin/)</em></p>
                </div>
            </div>
            <?php
            return;
        }
        
        echo '<div id="mikroplaneta-booking-root"></div>';
    }
    
    /**
     * Render settings page
     */
    public function render_settings_page(): void {
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            
            <div class="notice notice-info">
                <p>
                    <strong><?php _e('MikroPlaneta Booking System', 'mikroplaneta-booking'); ?></strong><br>
                    <?php _e('Version:', 'mikroplaneta-booking'); ?> <?php echo esc_html(MIKROPLANETA_BOOKING_VERSION); ?><br>
                    <?php _e('Developer:', 'mikroplaneta-booking'); ?> <a href="https://mikroplaneta.pl" target="_blank">MikroPlaneta.pl</a>
                </p>
            </div>
            
            <div class="card">
                <h2><?php _e('Database Status', 'mikroplaneta-booking'); ?></h2>
                <?php $this->render_database_status(); ?>
            </div>
            
            <div class="card" style="margin-top: 20px;">
                <h2><?php _e('System Information', 'mikroplaneta-booking'); ?></h2>
                <table class="widefat">
                    <tbody>
                        <tr>
                            <td><strong><?php _e('Plugin Version:', 'mikroplaneta-booking'); ?></strong></td>
                            <td><?php echo esc_html(MIKROPLANETA_BOOKING_VERSION); ?></td>
                        </tr>
                        <tr>
                            <td><strong><?php _e('WordPress Version:', 'mikroplaneta-booking'); ?></strong></td>
                            <td><?php echo esc_html(get_bloginfo('version')); ?></td>
                        </tr>
                        <tr>
                            <td><strong><?php _e('PHP Version:', 'mikroplaneta-booking'); ?></strong></td>
                            <td><?php echo esc_html(PHP_VERSION); ?></td>
                        </tr>
                        <tr>
                            <td><strong><?php _e('Installed At:', 'mikroplaneta-booking'); ?></strong></td>
                            <td><?php echo esc_html(get_option('mikroplaneta_booking_installed_at', 'N/A')); ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render migrations page
     */
    public function render_migrations_page(): void {
        require_once MIKROPLANETA_BOOKING_PLUGIN_DIR . 'admin/pages/migrations.php';
    }
    
    /**
     * Render database status
     */
    private function render_database_status(): void {
        require_once MIKROPLANETA_BOOKING_PLUGIN_DIR . 'core/database/class-database.php';
        require_once MIKROPLANETA_BOOKING_PLUGIN_DIR . 'core/database/class-schema.php';
        
        $database = new \MikroPlaneta\Booking\Core\Database\Database();
        $status = $database->get_status();
        
        ?>
        <table class="widefat">
            <thead>
                <tr>
                    <th><?php _e('Migration', 'mikroplaneta-booking'); ?></th>
                    <th><?php _e('Status', 'mikroplaneta-booking'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($status['migrations'] as $migration): ?>
                <tr>
                    <td><?php echo esc_html($migration['name']); ?></td>
                    <td>
                        <?php if ($migration['executed']): ?>
                            <span style="color: green;">✓ <?php _e('Executed', 'mikroplaneta-booking'); ?></span>
                        <?php else: ?>
                            <span style="color: orange;">⏳ <?php _e('Pending', 'mikroplaneta-booking'); ?></span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr>
                    <td><strong><?php _e('Total:', 'mikroplaneta-booking'); ?></strong></td>
                    <td>
                        <strong>
                            <?php echo esc_html($status['executed']); ?> / <?php echo esc_html($status['total']); ?>
                            <?php _e('executed', 'mikroplaneta-booking'); ?>
                        </strong>
                    </td>
                </tr>
            </tfoot>
        </table>
        <?php
    }
    
    /**
     * Enqueue admin assets
     */
    public function enqueue_assets($hook): void {
        // Only load on our plugin pages
        if (strpos($hook, 'mikroplaneta-booking') === false) {
            return;
        }

        wp_enqueue_media();
        
        // Check if React app is built
        $js_file = MIKROPLANETA_BOOKING_PLUGIN_DIR . 'assets/admin/index.js';
        $css_file = MIKROPLANETA_BOOKING_PLUGIN_DIR . 'assets/admin/index.css';

        // Use file modification time for cache busting in development
        $version = MIKROPLANETA_BOOKING_VERSION;
        if (defined('WP_DEBUG') && WP_DEBUG) {
            $js_version = file_exists($js_file) ? filemtime($js_file) : $version;
            $css_version = file_exists($css_file) ? filemtime($css_file) : $version;
        } else {
            $js_version = $version;
            $css_version = $version;
        }

        if (file_exists($js_file)) {
            wp_enqueue_script(
                'mikroplaneta-booking-admin',
                MIKROPLANETA_BOOKING_PLUGIN_URL . 'assets/admin/index.js',
                [],
                $js_version,
                true
            );
        }

        if (file_exists($css_file)) {
            wp_enqueue_style(
                'mikroplaneta-booking-admin',
                MIKROPLANETA_BOOKING_PLUGIN_URL . 'assets/admin/index.css',
                [],
                $css_version
            );
        }
        
        // Pass data to React app
        wp_localize_script('mikroplaneta-booking-admin', 'mikroplanetaBooking', [
            'apiUrl' => rest_url('mikroplaneta/v1'),
            'nonce' => wp_create_nonce('wp_rest'),
            'currentPage' => isset($_GET['page']) ? sanitize_text_field($_GET['page']) : '',
            'version' => MIKROPLANETA_BOOKING_VERSION,
        ]);
    }
}
