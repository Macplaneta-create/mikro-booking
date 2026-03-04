<?php

declare(strict_types=1);

namespace Tests\Integration;

use MikroPlaneta\Booking\RestApi\Controllers\SettingsController;
use PHPUnit\Framework\TestCase;
use Tests\Integration\Support\FakeRestRequest;

require_once __DIR__ . '/support/FakeRestRequest.php';

class SettingsTriggerCronTasksTest extends TestCase {
    protected function setUp(): void {
        $GLOBALS['__mb_actions'] = [];
    }

    public function testTriggerCronDefaultsToExpiryTask(): void {
        $controller = new SettingsController();

        $response = $controller->trigger_cron(new FakeRestRequest());
        $payload = $response->get_data();

        $this->assertSame(200, $response->get_status());
        $this->assertTrue($payload['success']);
        $this->assertSame('expiry', $payload['data']['task']);

        $hooks = array_map(static fn(array $action): string => $action[0], $GLOBALS['__mb_actions']);
        $this->assertContains('mikroplaneta_booking_expire_reservations', $hooks);
    }

    public function testTriggerCronRunsRemindersTask(): void {
        $controller = new SettingsController();

        $response = $controller->trigger_cron(new FakeRestRequest([
            'task' => 'reminders',
        ]));
        $payload = $response->get_data();

        $this->assertSame(200, $response->get_status());
        $this->assertTrue($payload['success']);
        $this->assertSame('reminders', $payload['data']['task']);

        $hooks = array_map(static fn(array $action): string => $action[0], $GLOBALS['__mb_actions']);
        $this->assertContains('mikroplaneta_booking_send_reminders', $hooks);
    }
}
