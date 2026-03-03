<?php

declare(strict_types=1);

namespace {
    if (!function_exists('add_action')) {
        function add_action(string $hook, callable $callback): bool {
            $GLOBALS['__mb_registered_actions'][] = $hook;
            return true;
        }
    }

    if (!function_exists('wp_next_scheduled')) {
        function wp_next_scheduled(string $hook) {
            return false;
        }
    }

    if (!function_exists('wp_schedule_event')) {
        function wp_schedule_event(int $timestamp, string $recurrence, string $hook): bool {
            $GLOBALS['__mb_scheduled_events'][] = $hook;
            return true;
        }
    }

    if (!function_exists('wp_clear_scheduled_hook')) {
        function wp_clear_scheduled_hook(string $hook): bool {
            $GLOBALS['__mb_cleared_hooks'][] = $hook;
            return true;
        }
    }

    if (!function_exists('flush_rewrite_rules')) {
        function flush_rewrite_rules(): bool {
            $GLOBALS['__mb_flush_rewrite_called'] = true;
            return true;
        }
    }
}

namespace Tests\Integration {

use MikroPlaneta\Booking\Core\Deactivator;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../core/class-cron-handler.php';
require_once __DIR__ . '/../../core/class-deactivator.php';

class CronLifecycleHooksTest extends TestCase {
    private $previousWpdb;

    protected function setUp(): void {
        $this->previousWpdb = $GLOBALS['wpdb'] ?? null;

        $GLOBALS['__mb_registered_actions'] = [];
        $GLOBALS['__mb_actions_registered'] = [];
        $GLOBALS['__mb_scheduled_events'] = [];
        $GLOBALS['__mb_cleared_hooks'] = [];
        $GLOBALS['__mb_flush_rewrite_called'] = false;
        $GLOBALS['__mb_options'] = [];

        $GLOBALS['wpdb'] = new class {
            public string $options = 'wp_options';

            public function query(string $sql): bool {
                $GLOBALS['__mb_wpdb_last_query'] = $sql;
                return true;
            }
        };
    }

    protected function tearDown(): void {
        if ($this->previousWpdb !== null) {
            $GLOBALS['wpdb'] = $this->previousWpdb;
            return;
        }

        unset($GLOBALS['wpdb']);
    }

    public function testCronHandlerRegistersCleanupTempFilesHook(): void {
        // Re-init to register hooks into fresh globals
        \MikroPlaneta\Booking\Core\CronHandler::init();

        $registeredHooks = array_map(
            static fn(array $item): string => $item['hook'] ?? '',
            $GLOBALS['__mb_actions_registered']
        );

        $this->assertContains('mikroplaneta_booking_cleanup_temp_files', $registeredHooks);
    }

    public function testDeactivatorClearsAllPluginCronHooks(): void {
        Deactivator::deactivate();

        $expected = [
            'mikroplaneta_booking_expire_reservations',
            'mikroplaneta_booking_send_reminders',
            'mikroplaneta_booking_daily_backup',
            'mikroplaneta_booking_daily_csv_export',
            'mikroplaneta_booking_cleanup_temp_files',
        ];

        foreach ($expected as $hook) {
            $this->assertContains($hook, $GLOBALS['__mb_cleared_hooks']);
        }

        $this->assertTrue($GLOBALS['__mb_flush_rewrite_called']);
    }
}

}
