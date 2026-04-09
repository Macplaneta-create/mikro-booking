<?php

declare(strict_types=1);

namespace Tests\Integration;

use MikroPlaneta\Booking\Core\Models\Guest;
use MikroPlaneta\Booking\Core\Models\Reservation;
use MikroPlaneta\Booking\Core\Repositories\ChangesLogRepository;
use MikroPlaneta\Booking\Core\Repositories\GuestRepository;
use MikroPlaneta\Booking\Core\Repositories\ReservationRepository;
use MikroPlaneta\Booking\Core\Services\GuestService;
use MikroPlaneta\Booking\Core\Services\NotificationService;
use MikroPlaneta\Booking\RestApi\Controllers\GuestsController;
use PHPUnit\Framework\TestCase;
use Tests\Integration\Support\FakeRestRequest;

require_once __DIR__ . '/support/FakeRestRequest.php';
require_once __DIR__ . '/../../core/repositories/class-changes-log-repository.php';
require_once __DIR__ . '/../../rest-api/controllers/class-guests-controller.php';

class GuestsControllerMessageTest extends TestCase {

    protected function setUp(): void {
        $GLOBALS['__mb_options'] = [];
        $GLOBALS['__mb_current_user_id'] = 1;
    }

    private function makeController(
        ?GuestRepository $guestRepo = null,
        ?NotificationService $notification = null,
        ?ReservationRepository $reservationRepo = null,
        ?ChangesLogRepository $changesLog = null
    ): GuestsController {
        return new GuestsController(
            $this->createMock(GuestService::class),
            $guestRepo       ?? $this->createMock(GuestRepository::class),
            $notification    ?? $this->createMock(NotificationService::class),
            $reservationRepo ?? $this->createMock(ReservationRepository::class),
            $changesLog      ?? $this->createMock(ChangesLogRepository::class)
        );
    }

    public function testReturns404WhenGuestNotFound(): void {
        $guestRepo = $this->createMock(GuestRepository::class);
        $guestRepo->method('find')->with(99)->willReturn(null);

        $controller = $this->makeController(guestRepo: $guestRepo);
        $request = new FakeRestRequest(
            ['subject' => 'Temat', 'body' => 'Treść'],
            ['id' => 99]
        );

        $response = $controller->send_message($request);

        $this->assertSame(404, $response->get_status());
    }

    public function testReturns400WhenSubjectIsEmpty(): void {
        $guest = Guest::fromArray([
            'id' => 1, 'first_name' => 'Jan', 'last_name' => 'Nowak', 'email' => 'jan@example.com',
        ]);

        $guestRepo = $this->createMock(GuestRepository::class);
        $guestRepo->method('find')->with(1)->willReturn($guest);

        $controller = $this->makeController(guestRepo: $guestRepo);
        $request = new FakeRestRequest(
            ['subject' => '', 'body' => 'Treść'],
            ['id' => 1]
        );

        $response = $controller->send_message($request);

        $this->assertSame(400, $response->get_status());
    }

    public function testReturns400WhenReservationBelongsToDifferentGuest(): void {
        $guest = Guest::fromArray([
            'id' => 1, 'first_name' => 'Jan', 'last_name' => 'Nowak', 'email' => 'jan@example.com',
        ]);

        $reservation = Reservation::fromArray([
            'id' => 55, 'guest_id' => 99, 'status' => 'confirmed',
            'check_in' => '2026-04-10', 'check_out' => '2026-04-12', 'bed_ids' => [1],
        ]);

        $guestRepo = $this->createMock(GuestRepository::class);
        $guestRepo->method('find')->with(1)->willReturn($guest);

        $reservationRepo = $this->createMock(ReservationRepository::class);
        $reservationRepo->method('find')->with(55)->willReturn($reservation);

        $controller = $this->makeController(
            guestRepo: $guestRepo,
            reservationRepo: $reservationRepo
        );

        $request = new FakeRestRequest(
            ['subject' => 'Zmiana pokoju', 'body' => 'Przenieśliśmy Cię do pokoju 5.', 'reservation_id' => 55],
            ['id' => 1]
        );

        $response = $controller->send_message($request);

        $this->assertSame(400, $response->get_status());
    }

    public function testSendsMessageAndLogsSuccessfully(): void {
        $guest = Guest::fromArray([
            'id' => 2, 'first_name' => 'Anna', 'last_name' => 'Kowal', 'email' => 'anna@example.com',
        ]);

        $reservation = Reservation::fromArray([
            'id' => 10, 'guest_id' => 2, 'status' => 'confirmed',
            'check_in' => '2026-04-10', 'check_out' => '2026-04-12', 'bed_ids' => [1],
        ]);

        $guestRepo = $this->createMock(GuestRepository::class);
        $guestRepo->method('find')->with(2)->willReturn($guest);

        $notification = $this->createMock(NotificationService::class);
        $notification
            ->expects($this->once())
            ->method('sendCustomMessage')
            ->with($guest, 'Informacja o zmianie', 'Zmieniliśmy Twój pokój.', 10)
            ->willReturn(true);

        $reservationRepo = $this->createMock(ReservationRepository::class);
        $reservationRepo->method('find')->with(10)->willReturn($reservation);

        $changesLog = $this->createMock(ChangesLogRepository::class);
        $changesLog
            ->expects($this->once())
            ->method('create')
            ->with($this->callback(static function(array $data): bool {
                return $data['change_type'] === 'custom_message'
                    && $data['reservation_id'] === 10;
            }))
            ->willReturn((object) ['id' => 1]);

        $controller = $this->makeController(
            guestRepo: $guestRepo,
            notification: $notification,
            reservationRepo: $reservationRepo,
            changesLog: $changesLog
        );

        $request = new FakeRestRequest(
            ['subject' => 'Informacja o zmianie', 'body' => 'Zmieniliśmy Twój pokój.', 'reservation_id' => 10],
            ['id' => 2]
        );

        $response = $controller->send_message($request);

        $this->assertSame(200, $response->get_status());
        $this->assertTrue($response->get_data()['data']['sent']);
    }

    public function testReturns500WhenMailFails(): void {
        $guest = Guest::fromArray([
            'id' => 3, 'first_name' => 'Piotr', 'last_name' => 'Zając', 'email' => 'piotr@example.com',
        ]);

        $guestRepo = $this->createMock(GuestRepository::class);
        $guestRepo->method('find')->with(3)->willReturn($guest);

        $notification = $this->createMock(NotificationService::class);
        $notification->method('sendCustomMessage')->willReturn(false);

        $controller = $this->makeController(
            guestRepo: $guestRepo,
            notification: $notification
        );

        $request = new FakeRestRequest(
            ['subject' => 'Problem', 'body' => 'Treść wiadomości'],
            ['id' => 3]
        );

        $response = $controller->send_message($request);

        $this->assertSame(500, $response->get_status());
    }

    public function testSendsMessageWithoutReservationLink(): void {
        $guest = Guest::fromArray([
            'id' => 4, 'first_name' => 'Ewa', 'last_name' => 'Lis', 'email' => 'ewa@example.com',
        ]);

        $guestRepo = $this->createMock(GuestRepository::class);
        $guestRepo->method('find')->with(4)->willReturn($guest);

        $notification = $this->createMock(NotificationService::class);
        $notification
            ->expects($this->once())
            ->method('sendCustomMessage')
            ->with($guest, 'Bez rezerwacji', 'Ogólna wiadomość.', null)
            ->willReturn(true);

        $changesLog = $this->createMock(ChangesLogRepository::class);
        $changesLog->method('create')->willReturn((object) ['id' => 1]);

        $controller = $this->makeController(
            guestRepo: $guestRepo,
            notification: $notification,
            changesLog: $changesLog
        );

        $request = new FakeRestRequest(
            ['subject' => 'Bez rezerwacji', 'body' => 'Ogólna wiadomość.'],
            ['id' => 4]
        );

        $response = $controller->send_message($request);

        $this->assertSame(200, $response->get_status());
    }
}
