<?php

declare(strict_types=1);

namespace Tests\Integration;

use MikroPlaneta\Booking\RestApi\Controllers\SettingsController;
use PHPUnit\Framework\TestCase;
use Tests\Integration\Support\FakeRestRequest;

require_once __DIR__ . '/support/FakeRestRequest.php';

class SettingsCronRescheduleTest extends TestCase {
    protected function setUp(): void {
        $GLOBALS['__mb_options'] = [];
        $GLOBALS['__mb_cleared_hooks'] = [];
        $GLOBALS['__mb_scheduled_events'] = [];
        $GLOBALS['__mb_next_scheduled'] = [];
        $GLOBALS['__mb_current_timestamp'] = strtotime('2026-03-03 10:00:00');
    }

    public function testUpdateSettingsReschedulesBackupAndCsvCrons(): void {
        $controller = new SettingsController();

        $request = new FakeRestRequest([
            'backup_email_enabled' => true,
            'backup_email_time' => '07:30',
            'csv_export_enabled' => true,
            'csv_export_time' => '09:45',
        ]);

        $response = $controller->update_settings($request);

        $this->assertSame(200, $response->get_status());

        $this->assertContains('mikroplaneta_booking_daily_backup', $GLOBALS['__mb_cleared_hooks']);
        $this->assertContains('mikroplaneta_booking_daily_csv_export', $GLOBALS['__mb_cleared_hooks']);

        $hooks = array_map(static fn(array $event): string => $event['hook'], $GLOBALS['__mb_scheduled_events']);
        $this->assertContains('mikroplaneta_booking_daily_backup', $hooks);
        $this->assertContains('mikroplaneta_booking_daily_csv_export', $hooks);
    }
}
