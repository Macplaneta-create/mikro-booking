<?php

declare(strict_types=1);

namespace Tests\Integration;

use MikroPlaneta\Booking\Core\Models\Reservation;
use MikroPlaneta\Booking\Core\Repositories\ReservationRepository;
use MikroPlaneta\Booking\Core\Services\ReservationExpiryService;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../core/services/class-reservation-expiry-service.php';

class ReservationExpiryServiceTest extends TestCase {
    protected function setUp(): void {
        $GLOBALS['__mb_options'] = [];
    }

    public function testDoesNotExpireWhenAutoExpireIsDisabled(): void {
        $GLOBALS['__mb_options']['mikroplaneta_booking_auto_expire_pending'] = false;
        $GLOBALS['__mb_options']['mikroplaneta_booking_pending_timeout_hours'] = 48;

        $repository = $this->createMock(ReservationRepository::class);
        $repository->expects($this->never())->method('all');
        $repository->expects($this->never())->method('update');

        $service = new ReservationExpiryService($repository);
        $expired = $service->expirePendingReservations();

        $this->assertSame(0, $expired);
    }

    public function testExpiresOnlyOldPendingReservationsWhenEnabled(): void {
        $GLOBALS['__mb_options']['mikroplaneta_booking_auto_expire_pending'] = true;
        $GLOBALS['__mb_options']['mikroplaneta_booking_pending_timeout_hours'] = 48;

        $old = Reservation::fromArray([
            'id' => 101,
            'status' => Reservation::STATUS_PENDING,
            'created_at' => date('Y-m-d H:i:s', time() - (49 * 3600)),
        ]);

        $recent = Reservation::fromArray([
            'id' => 102,
            'status' => Reservation::STATUS_PENDING,
            'created_at' => date('Y-m-d H:i:s', time() - (2 * 3600)),
        ]);

        $repository = $this->createMock(ReservationRepository::class);
        $repository
            ->expects($this->once())
            ->method('all')
            ->with(['status' => Reservation::STATUS_PENDING])
            ->willReturn([$old, $recent]);

        $repository
            ->expects($this->once())
            ->method('update')
            ->with(101, ['status' => Reservation::STATUS_CANCELLED])
            ->willReturn($old);

        $service = new ReservationExpiryService($repository);
        $expired = $service->expirePendingReservations();

        $this->assertSame(1, $expired);
    }
}

