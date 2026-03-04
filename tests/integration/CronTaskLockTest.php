<?php

declare(strict_types=1);

namespace Tests\Integration;

use MikroPlaneta\Booking\Core\CronHandler;
use PHPUnit\Framework\TestCase;

class CronTaskLockTest extends TestCase {
    protected function setUp(): void {
        $GLOBALS['__mb_transients'] = [];
    }

    public function testAcquireTaskLockBlocksSecondAcquireAndCanBeReleased(): void {
        $acquire = new \ReflectionMethod(CronHandler::class, 'acquireTaskLock');
        $acquire->setAccessible(true);

        $release = new \ReflectionMethod(CronHandler::class, 'releaseTaskLock');
        $release->setAccessible(true);

        $first = $acquire->invoke(null, 'send_reminders');
        $second = $acquire->invoke(null, 'send_reminders');

        $this->assertTrue($first);
        $this->assertFalse($second);

        $release->invoke(null, 'send_reminders');

        $third = $acquire->invoke(null, 'send_reminders');
        $this->assertTrue($third);
    }
}
