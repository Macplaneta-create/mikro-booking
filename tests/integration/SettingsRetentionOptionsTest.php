<?php

declare(strict_types=1);

namespace Tests\Integration;

use MikroPlaneta\Booking\RestApi\Controllers\SettingsController;
use PHPUnit\Framework\TestCase;
use Tests\Integration\Support\FakeRestRequest;

require_once __DIR__ . '/support/FakeRestRequest.php';
require_once __DIR__ . '/../../rest-api/controllers/class-settings-controller.php';

class SettingsRetentionOptionsTest extends TestCase {
    protected function setUp(): void {
        $GLOBALS['__mb_options'] = [];
    }

    public function testGetSettingsReturnsRetentionDefaults(): void {
        $controller = new SettingsController();

        $response = $controller->get_settings(new FakeRestRequest());
        $data = $response->get_data();

        $this->assertSame(200, $response->get_status());
        $this->assertTrue($data['success']);
        $this->assertSame(24, $data['data']['backup_retention_hours']);
        $this->assertSame(24, $data['data']['ical_retention_hours']);
    }

    public function testUpdateSettingsClampsRetentionToAtLeastOneHour(): void {
        $controller = new SettingsController();

        $request = new FakeRestRequest([
            'backup_retention_hours' => 0,
            'ical_retention_hours' => -5,
        ]);

        $response = $controller->update_settings($request);
        $data = $response->get_data();

        $this->assertSame(200, $response->get_status());
        $this->assertTrue($data['success']);

        $this->assertSame(1, (int) get_option('mikroplaneta_booking_backup_retention_hours', 0));
        $this->assertSame(1, (int) get_option('mikroplaneta_booking_ical_retention_hours', 0));
        $this->assertSame(1, $data['data']['backup_retention_hours']);
        $this->assertSame(1, $data['data']['ical_retention_hours']);
    }
}
