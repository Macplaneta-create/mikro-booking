<?php

declare(strict_types=1);

namespace Tests\Integration;

use MikroPlaneta\Booking\Core\Models\Bed;
use MikroPlaneta\Booking\Core\Models\Reservation;
use MikroPlaneta\Booking\Core\Repositories\BedRepository;
use MikroPlaneta\Booking\Core\Repositories\GuestRepository;
use MikroPlaneta\Booking\Core\Repositories\RoomRepository;
use MikroPlaneta\Booking\Core\Repositories\ReservationBedRepository;
use MikroPlaneta\Booking\Core\Repositories\ReservationPlaceRepository;
use MikroPlaneta\Booking\Core\Repositories\BedPlaceRepository;
use MikroPlaneta\Booking\Core\Repositories\ReservationRepository;
use MikroPlaneta\Booking\Core\Services\AvailabilityService;
use MikroPlaneta\Booking\Core\Services\NotificationService;
use MikroPlaneta\Booking\Core\Services\PricingService;
use MikroPlaneta\Booking\Core\Services\ReservationService;
use PHPUnit\Framework\TestCase;

class ReservationUpdateBedIdsTest extends TestCase {
    protected function setUp(): void {
        $GLOBALS['__mb_actions'] = [];
    }

    public function testUpdateReservationPersistsBedRelationChanges(): void {
        $reservationRepository = $this->createMock(ReservationRepository::class);
        $guestRepository = $this->createMock(GuestRepository::class);
        $bedRepository = $this->createMock(BedRepository::class);
        $availabilityService = $this->createMock(AvailabilityService::class);
        $pricingService = $this->createMock(PricingService::class);
        $reservationBedRepository = $this->createMock(ReservationBedRepository::class);
        $reservationPlaceRepository = $this->createMock(ReservationPlaceRepository::class);
        $bedPlaceRepository = $this->createMock(BedPlaceRepository::class);
        $notificationService = $this->createMock(NotificationService::class);
        $roomRepository = $this->createMock(RoomRepository::class);

        $service = new ReservationService(
            $reservationRepository,
            $guestRepository,
            $bedRepository,
            $availabilityService,
            $pricingService,
            $reservationBedRepository,
            $reservationPlaceRepository,
            $bedPlaceRepository,
            $notificationService,
            $roomRepository
        );

        $existing = Reservation::fromArray([
            'id' => 10,
            'guest_id' => 5,
            'bed_ids' => [1, 2],
            'check_in' => '2026-05-01',
            'check_out' => '2026-05-03',
            'status' => Reservation::STATUS_CONFIRMED,
            'adults' => 2,
            'children' => 0,
        ]);

        $updated = Reservation::fromArray([
            'id' => 10,
            'guest_id' => 5,
            'bed_ids' => [3, 4],
            'check_in' => '2026-05-01',
            'check_out' => '2026-05-03',
            'status' => Reservation::STATUS_CONFIRMED,
            'adults' => 2,
            'children' => 0,
        ]);

        $activeBed3 = Bed::fromArray(['id' => 3, 'is_active' => true]);
        $activeBed4 = Bed::fromArray(['id' => 4, 'is_active' => true]);
        $place31 = (object) ['id' => 31, 'is_active' => true];
        $place41 = (object) ['id' => 41, 'is_active' => true];

        $reservationRepository
            ->expects($this->exactly(3))
            ->method('find')
            ->withConsecutive([10], [10], [10])
            ->willReturnOnConsecutiveCalls($existing, $existing, $updated);

        $bedRepository
            ->expects($this->atLeast(4))
            ->method('find')
            ->willReturnCallback(static function(int $bedId) use ($activeBed3, $activeBed4) {
                if ($bedId === 3) return $activeBed3;
                if ($bedId === 4) return $activeBed4;
                return null;
            });

        $availabilityService
            ->expects($this->exactly(2))
            ->method('isBedAvailable')
            ->withConsecutive(
                [3, '2026-05-01', '2026-05-03', 10],
                [4, '2026-05-01', '2026-05-03', 10]
            )
            ->willReturnOnConsecutiveCalls(true, true);

        $reservationRepository
            ->expects($this->once())
            ->method('update')
            ->with(10, ['bed_ids' => [3, 4]])
            ->willReturn($updated);

        $reservationBedRepository
            ->expects($this->once())
            ->method('setBedsForReservation')
            ->with(10, [3, 4]);

        $bedPlaceRepository
            ->expects($this->atLeast(1))
            ->method('ensureDefaultPlacesForBed');

        $bedPlaceRepository
            ->expects($this->atLeast(2))
            ->method('findByBed')
            ->willReturnCallback(static function(int $bedId) use ($place31, $place41) {
                if ($bedId === 3) {
                    return [$place31];
                }
                if ($bedId === 4) {
                    return [$place41];
                }
                return [];
            });

        $bedPlaceRepository
            ->expects($this->atLeast(2))
            ->method('isPlaceAvailable')
            ->willReturn(true);

        $bedPlaceRepository
            ->method('getBedCapacity')
            ->willReturn(0);

        $reservationPlaceRepository
            ->expects($this->once())
            ->method('setPlacesForReservation')
            ->with(10, [31, 41]);

        $result = $service->updateReservation(10, ['bed_ids' => [3, 4]]);

        $this->assertSame(10, $result->id);
        $this->assertSame([3, 4], $result->bed_ids);
    }
}
