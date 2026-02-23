<?php

declare(strict_types=1);

namespace Tests\Integration;

use MikroPlaneta\Booking\Core\Models\Bed;
use MikroPlaneta\Booking\Core\Models\Reservation;
use MikroPlaneta\Booking\Core\Repositories\BedRepository;
use MikroPlaneta\Booking\Core\Repositories\GuestRepository;
use MikroPlaneta\Booking\Core\Repositories\ReservationBedRepository;
use MikroPlaneta\Booking\Core\Repositories\ReservationRepository;
use MikroPlaneta\Booking\Core\Services\AvailabilityService;
use MikroPlaneta\Booking\Core\Services\NotificationService;
use MikroPlaneta\Booking\Core\Services\PricingService;
use MikroPlaneta\Booking\Core\Services\ReservationService;
use PHPUnit\Framework\TestCase;

class TestReservationCheckInAdjustments extends TestCase {
    protected function setUp(): void {
        $GLOBALS['__mb_actions'] = [];
    }

    public function testCheckInAppliesAdjustmentsAndMarksReservationCheckedIn(): void {
        $reservationRepository = $this->createMock(ReservationRepository::class);
        $guestRepository = $this->createMock(GuestRepository::class);
        $bedRepository = $this->createMock(BedRepository::class);
        $availabilityService = $this->createMock(AvailabilityService::class);
        $pricingService = $this->createMock(PricingService::class);
        $reservationBedRepository = $this->createMock(ReservationBedRepository::class);
        $notificationService = $this->createMock(NotificationService::class);

        $service = new ReservationService(
            $reservationRepository,
            $guestRepository,
            $bedRepository,
            $availabilityService,
            $pricingService,
            $reservationBedRepository,
            $notificationService
        );

        $reservation = Reservation::fromArray([
            'id' => 21,
            'guest_id' => 8,
            'bed_ids' => [1, 2],
            'check_in' => '2026-06-01',
            'check_out' => '2026-06-05',
            'status' => Reservation::STATUS_PENDING,
            'adults' => 2,
            'children' => 0,
        ]);

        $statusUpdated = Reservation::fromArray([
            'id' => 21,
            'guest_id' => 8,
            'bed_ids' => [3, 4],
            'check_in' => '2026-06-01',
            'check_out' => '2026-06-05',
            'status' => Reservation::STATUS_CHECKED_IN,
            'adults' => 2,
            'children' => 0,
        ]);

        $bed3 = Bed::fromArray(['id' => 3, 'is_active' => true]);
        $bed4 = Bed::fromArray(['id' => 4, 'is_active' => true]);

        $reservationRepository
            ->expects($this->once())
            ->method('find')
            ->with(21)
            ->willReturn($reservation);

        $bedRepository
            ->expects($this->atLeast(4))
            ->method('find')
            ->willReturnCallback(static function(int $bedId) use ($bed3, $bed4) {
                if ($bedId === 3) return $bed3;
                if ($bedId === 4) return $bed4;
                return null;
            });

        $availabilityService
            ->expects($this->exactly(2))
            ->method('isBedAvailable')
            ->withConsecutive(
                [3, '2026-06-01', '2026-06-05', 21],
                [4, '2026-06-01', '2026-06-05', 21]
            )
            ->willReturnOnConsecutiveCalls(true, true);

        $reservationRepository
            ->expects($this->exactly(2))
            ->method('update')
            ->withConsecutive(
                [21, ['adults' => 2, 'children' => 0]],
                [21, ['status' => Reservation::STATUS_CHECKED_IN]]
            )
            ->willReturnOnConsecutiveCalls($reservation, $statusUpdated);

        $reservationBedRepository
            ->expects($this->once())
            ->method('setBedsForReservation')
            ->with(21, [3, 4]);

        $result = $service->checkIn(21, [
            'adults' => 2,
            'children' => 0,
            'bed_ids' => [3, 4],
        ]);

        $this->assertSame(Reservation::STATUS_CHECKED_IN, $result->status);
    }

    public function testCheckInRejectsWhenGuestsExceedSelectedBeds(): void {
        $reservationRepository = $this->createMock(ReservationRepository::class);
        $guestRepository = $this->createMock(GuestRepository::class);
        $bedRepository = $this->createMock(BedRepository::class);
        $availabilityService = $this->createMock(AvailabilityService::class);
        $pricingService = $this->createMock(PricingService::class);
        $reservationBedRepository = $this->createMock(ReservationBedRepository::class);
        $notificationService = $this->createMock(NotificationService::class);

        $service = new ReservationService(
            $reservationRepository,
            $guestRepository,
            $bedRepository,
            $availabilityService,
            $pricingService,
            $reservationBedRepository,
            $notificationService
        );

        $reservation = Reservation::fromArray([
            'id' => 22,
            'guest_id' => 9,
            'bed_ids' => [1, 2],
            'check_in' => '2026-06-01',
            'check_out' => '2026-06-05',
            'status' => Reservation::STATUS_PENDING,
            'adults' => 2,
            'children' => 0,
        ]);

        $reservationRepository
            ->expects($this->once())
            ->method('find')
            ->with(22)
            ->willReturn($reservation);

        $reservationRepository
            ->expects($this->never())
            ->method('update');

        $reservationBedRepository
            ->expects($this->never())
            ->method('setBedsForReservation');

        $bedRepository
            ->expects($this->atLeast(1))
            ->method('find')
            ->willReturnCallback(static function(int $bedId) {
                if ($bedId === 3) return Bed::fromArray(['id' => 3, 'is_active' => true, 'bed_type' => 'single']);
                if ($bedId === 4) return Bed::fromArray(['id' => 4, 'is_active' => true, 'bed_type' => 'single']);
                return null;
            });

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Number of guests (3) exceeds selected beds capacity (2)');

        $service->checkIn(22, [
            'adults' => 3,
            'children' => 0,
            'bed_ids' => [3, 4],
        ]);
    }
}
