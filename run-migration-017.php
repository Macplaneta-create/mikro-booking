<?php
/**
 * Run migration 017 manually (development/maintenance only).
 */

if (!defined('MIKROPLANETA_BOOKING_ENABLE_MAINTENANCE_TOOLS') || MIKROPLANETA_BOOKING_ENABLE_MAINTENANCE_TOOLS !== true) {
    if (php_sapi_name() !== 'cli') {
        http_response_code(403);
        exit('Maintenance tool disabled.');
    }
}

require_once __DIR__ . '/../../../wp-load.php';

if (!defined('WP_DEBUG') || !WP_DEBUG) {
    if (PHP_SAPI !== 'cli') {
        status_header(403);
        exit('This tool is available only in debug mode.');
    }
}

if (PHP_SAPI !== 'cli') {
    if (!current_user_can('manage_options')) {
        status_header(403);
        exit('Access denied.');
    }

    $nonce = isset($_REQUEST['_wpnonce']) ? sanitize_text_field(wp_unslash($_REQUEST['_wpnonce'])) : '';
    if (!wp_verify_nonce($nonce, 'mikroplaneta_run_migration_017')) {
        status_header(403);
        exit('Invalid security token.');
    }
}

require_once __DIR__ . '/core/database/migrations/017_add_payment_options.php';

$ok = \MikroPlaneta\Booking\Core\Database\Migrations\Migration_017_Add_Payment_Options::up();

if (PHP_SAPI === 'cli') {
    echo $ok ? "Migration 017 completed.\n" : "Migration 017 failed.\n";
    exit($ok ? 0 : 1);
}

echo $ok ? 'Migration 017 completed.' : 'Migration 017 failed.';
