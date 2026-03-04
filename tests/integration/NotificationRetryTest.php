<?php

declare(strict_types=1);

namespace Tests\Integration;

use MikroPlaneta\Booking\Core\Services\NotificationService;
use PHPUnit\Framework\TestCase;

class NotificationRetryTest extends TestCase {
    protected function setUp(): void {
        $GLOBALS['__mb_wp_mail_calls'] = [];
        $GLOBALS['__mb_wp_mail_results'] = [];
        $GLOBALS['__mb_wp_mail_result'] = true;
    }

    public function testSendTestEmailRetriesUntilSuccess(): void {
        $service = new NotificationService();

        $GLOBALS['__mb_wp_mail_results'] = [false, false, true];

        $sent = $service->sendTestEmail('reservation_confirmation', 'guest@example.com');

        $this->assertTrue($sent);
        $this->assertCount(3, $GLOBALS['__mb_wp_mail_calls']);
    }

    public function testSendTestEmailFailsAfterAllRetries(): void {
        $service = new NotificationService();

        $GLOBALS['__mb_wp_mail_results'] = [false, false, false];

        $sent = $service->sendTestEmail('reservation_confirmation', 'guest@example.com');

        $this->assertFalse($sent);
        $this->assertCount(3, $GLOBALS['__mb_wp_mail_calls']);
    }
}
