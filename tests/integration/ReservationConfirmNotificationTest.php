<?php

declare(strict_types=1);

namespace Tests\Integration;

use MikroPlaneta\Booking\Core\Models\Guest;
use MikroPlaneta\Booking\Core\Models\Reservation;
use MikroPlaneta\Booking\Core\Repositories\BedPlaceRepository;
use MikroPlaneta\Booking\Core\Repositories\BedRepository;
use MikroPlaneta\Booking\Core\Repositories\GuestRepository;
use MikroPlaneta\Booking\Core\Repositories\ReservationBedRepository;
use MikroPlaneta\Booking\Core\Repositories\ReservationPlaceRepository;
use MikroPlaneta\Booking\Core\Repositories\ReservationRepository;
use MikroPlaneta\Booking\Core\Repositories\RoomRepository;
use MikroPlaneta\Booking\Core\Services\AvailabilityService;
use MikroPlaneta\Booking\Core\Services\NotificationService;
use MikroPlaneta\Booking\Core\Services\PricingService;
use MikroPlaneta\Booking\Core\Services\ReservationService;
use PHPUnit\Framework\TestCase;

class ReservationConfirmNotificationTest extends TestCase {
    protected function setUp(): void {
        $GLOBALS['__mb_options'] = [];
    }

    public function testConfirmReservationSendsNotificationWhenEnabled(): void {
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

        update_option('mikroplaneta_booking_email_notifications', true);

        $existing = Reservation::fromArray([
            'id' => 101,
            'guest_id' => 15,
            'status' => Reservation::STATUS_PENDING,
            'check_in' => '2026-04-10',
            'check_out' => '2026-04-12',
            'bed_ids' => [3],
        ]);

        $confirmed = Reservation::fromArray([
            'id' => 101,
            'guest_id' => 15,
            'status' => Reservation::STATUS_CONFIRMED,
            'check_in' => '2026-04-10',
            'check_out' => '2026-04-12',
            'bed_ids' => [3],
        ]);

        $guest = Guest::fromArray([
            'id' => 15,
            'first_name' => 'Jan',
            'last_name' => 'Nowak',
            'email' => 'jan.nowak@example.com',
        ]);

        $reservationRepository
            ->expects($this->once())
            ->method('find')
            ->with(101)
            ->willReturn($existing);

        $reservationRepository
            ->expects($this->once())
            ->method('update')
            ->with(101, ['status' => Reservation::STATUS_CONFIRMED, 'notes' => "\n\nConfirmed: Ręczne potwierdzenie"])
            ->willReturn($confirmed);

        $guestRepository
            ->expects($this->once())
            ->method('find')
            ->with(15)
            ->willReturn($guest);

        $notificationService
            ->expects($this->once())
            ->method('sendReservationConfirmation')
            ->with(
                $confirmed,
                $guest,
                $this->callback(static function(array $context): bool {
                    return isset($context['reason']) && $context['reason'] === 'Ręczne potwierdzenie';
                })
            )
            ->willReturn(true);

        $result = $service->confirmReservation(101, 'Ręczne potwierdzenie');

        $this->assertSame(Reservation::STATUS_CONFIRMED, $result->status);
    }
}
