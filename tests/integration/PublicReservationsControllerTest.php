<?php

declare(strict_types=1);

namespace Tests\Integration;

use MikroPlaneta\Booking\Core\Models\Guest;
use MikroPlaneta\Booking\Core\Models\Bed;
use MikroPlaneta\Booking\Core\Models\Reservation;
use MikroPlaneta\Booking\Core\Services\AvailabilityService;
use MikroPlaneta\Booking\Core\Services\GuestService;
use MikroPlaneta\Booking\Core\Services\ReservationService;
use MikroPlaneta\Booking\RestApi\Controllers\PublicReservationsController;
use PHPUnit\Framework\TestCase;
use Tests\Integration\Support\FakeRestRequest;

require_once __DIR__ . '/support/FakeRestRequest.php';

class PublicReservationsControllerTest extends TestCase {
    protected function setUp(): void {
        $GLOBALS['__mb_filters'] = [];
        $GLOBALS['__mb_actions'] = [];
        $GLOBALS['__mb_options'] = [];
        $GLOBALS['__mb_transients'] = [];
        $GLOBALS['__mb_environment_type'] = 'development';
        unset($GLOBALS['__mb_remote_post_response']);
    }

    public function testRejectsRequestWhenCaptchaTokenIsEmpty(): void {
        $reservationService = $this->createMock(ReservationService::class);
        $guestService = $this->createMock(GuestService::class);
        $controller = new PublicReservationsController($reservationService, $guestService);

        $request = new FakeRestRequest([
            'guest' => ['first_name' => 'Jan', 'last_name' => 'Kowalski', 'email' => 'jan@example.com'],
            'bed_ids' => [1],
            'check_in' => '2026-04-10',
            'check_out' => '2026-04-12',
            'captcha_token' => '',
        ]);

        $response = $controller->create_request($request);

        $this->assertSame(400, $response->get_status());
        $this->assertSame('Captcha verification failed', $response->get_data()['message']);
    }

    public function testCreatesReservationWhenCaptchaProviderIsNone(): void {
        $GLOBALS['__mb_options']['mikroplaneta_booking_captcha_provider'] = 'none';

        $guest = Guest::fromArray([
            'id' => 101,
            'first_name' => 'Anna',
            'last_name' => 'Nowak',
            'email' => 'anna@example.com',
        ]);

        $createdReservation = Reservation::fromArray([
            'id' => 1001,
            'guest_id' => 101,
            'bed_ids' => [3],
            'check_in' => '2026-04-10',
            'check_out' => '2026-04-12',
            'status' => Reservation::STATUS_PENDING,
        ]);

        $reservationService = $this->createMock(ReservationService::class);
        $guestService = $this->createMock(GuestService::class);

        $guestService
            ->expects($this->once())
            ->method('createGuest')
            ->willReturn($guest);

        $reservationService
            ->expects($this->once())
            ->method('createReservation')
            ->willReturn($createdReservation);

        $controller = new PublicReservationsController($reservationService, $guestService);

        $request = new FakeRestRequest([
            'guest' => ['first_name' => 'Anna', 'last_name' => 'Nowak', 'email' => 'anna@example.com'],
            'bed_ids' => [3],
            'check_in' => '2026-04-10',
            'check_out' => '2026-04-12',
            'captcha_token' => '',
        ]);

        $response = $controller->create_request($request);
        $data = $response->get_data();

        $this->assertSame(201, $response->get_status());
        $this->assertTrue($data['success']);
        $this->assertSame(1001, $data['data']['reservation_id']);
    }

    public function testCreatesPendingReservationWhenCaptchaSimulationIsEnabled(): void {
        add_filter('mikroplaneta_booking_recaptcha_simulate', static function() {
            return true;
        });

        $guest = Guest::fromArray([
            'id' => 77,
            'first_name' => 'Jan',
            'last_name' => 'Kowalski',
            'email' => 'jan@example.com',
        ]);

        $createdReservation = Reservation::fromArray([
            'id' => 900,
            'guest_id' => 77,
            'bed_ids' => [10],
            'check_in' => '2026-04-10',
            'check_out' => '2026-04-12',
            'status' => Reservation::STATUS_PENDING,
        ]);

        $reservationService = $this->createMock(ReservationService::class);
        $guestService = $this->createMock(GuestService::class);

        $guestService
            ->expects($this->once())
            ->method('createGuest')
            ->willReturn($guest);

        $reservationService
            ->expects($this->once())
            ->method('createReservation')
            ->with($this->callback(static function(array $payload): bool {
                return $payload['guest_id'] === 77
                    && $payload['bed_ids'] === [10]
                    && $payload['status'] === Reservation::STATUS_PENDING;
            }))
            ->willReturn($createdReservation);

        $controller = new PublicReservationsController($reservationService, $guestService);

        $request = new FakeRestRequest([
            'guest' => ['first_name' => 'Jan', 'last_name' => 'Kowalski', 'email' => 'jan@example.com'],
            'bed_ids' => [10],
            'check_in' => '2026-04-10',
            'check_out' => '2026-04-12',
            'captcha_token' => 'token-ok',
        ]);

        $response = $controller->create_request($request);
        $data = $response->get_data();

        $this->assertSame(201, $response->get_status());
        $this->assertTrue($data['success']);
        $this->assertSame(900, $data['data']['reservation_id']);
        $this->assertSame(Reservation::STATUS_PENDING, $data['data']['status']);
    }

    public function testRejectsRequestWhenCaptchaScoreIsTooLowInRealVerificationMode(): void {
        add_filter('mikroplaneta_booking_recaptcha_simulate', static function() {
            return false;
        });

        $GLOBALS['__mb_options']['mikroplaneta_booking_recaptcha_secret_key'] = 'secret-key';
        $GLOBALS['__mb_remote_post_response'] = [
            'response' => ['code' => 200],
            'body' => '{"success":true,"score":0.1}',
        ];

        $reservationService = $this->createMock(ReservationService::class);
        $guestService = $this->createMock(GuestService::class);

        $reservationService
            ->expects($this->never())
            ->method('createReservation');

        $controller = new PublicReservationsController($reservationService, $guestService);

        $request = new FakeRestRequest([
            'guest' => ['first_name' => 'Jan', 'last_name' => 'Kowalski', 'email' => 'jan@example.com'],
            'bed_ids' => [1],
            'check_in' => '2026-04-10',
            'check_out' => '2026-04-12',
            'captcha_token' => 'token-ok',
        ]);

        $response = $controller->create_request($request);

        $this->assertSame(400, $response->get_status());
        $this->assertSame('Captcha verification failed', $response->get_data()['message']);
    }

    public function testCreatesReservationWhenHcaptchaIsEnabledAndValid(): void {
        $GLOBALS['__mb_options']['mikroplaneta_booking_captcha_provider'] = 'hcaptcha';
        $GLOBALS['__mb_options']['mikroplaneta_booking_hcaptcha_secret_key'] = 'hcaptcha-secret';
        $GLOBALS['__mb_remote_post_response'] = [
            'response' => ['code' => 200],
            'body' => '{"success":true}',
        ];

        $guest = Guest::fromArray([
            'id' => 202,
            'first_name' => 'Piotr',
            'last_name' => 'Kowal',
            'email' => 'piotr@example.com',
        ]);

        $createdReservation = Reservation::fromArray([
            'id' => 2002,
            'guest_id' => 202,
            'bed_ids' => [4],
            'check_in' => '2026-06-10',
            'check_out' => '2026-06-12',
            'status' => Reservation::STATUS_PENDING,
        ]);

        $reservationService = $this->createMock(ReservationService::class);
        $guestService = $this->createMock(GuestService::class);

        $guestService
            ->expects($this->once())
            ->method('createGuest')
            ->willReturn($guest);

        $reservationService
            ->expects($this->once())
            ->method('createReservation')
            ->willReturn($createdReservation);

        $controller = new PublicReservationsController($reservationService, $guestService);

        $request = new FakeRestRequest([
            'guest' => ['first_name' => 'Piotr', 'last_name' => 'Kowal', 'email' => 'piotr@example.com'],
            'bed_ids' => [4],
            'check_in' => '2026-06-10',
            'check_out' => '2026-06-12',
            'captcha_token' => 'hcaptcha-token-ok',
        ]);

        $response = $controller->create_request($request);
        $data = $response->get_data();

        $this->assertSame(201, $response->get_status());
        $this->assertTrue($data['success']);
        $this->assertSame(2002, $data['data']['reservation_id']);
    }

    public function testReturnsAvailableBedsForPublicEndpoint(): void {
        $reservationService = $this->createMock(ReservationService::class);
        $guestService = $this->createMock(GuestService::class);
        $availabilityService = $this->createMock(AvailabilityService::class);

        $availabilityService
            ->expects($this->once())
            ->method('findAvailableBeds')
            ->with('2026-05-10', '2026-05-12')
            ->willReturn([
                Bed::fromArray(['id' => 1, 'room_id' => 2, 'bed_number' => 1, 'bed_type' => 'single', 'is_active' => true]),
                Bed::fromArray(['id' => 2, 'room_id' => 2, 'bed_number' => 2, 'bed_type' => 'bunk', 'is_active' => true]),
            ]);

        $controller = new PublicReservationsController($reservationService, $guestService, $availabilityService);

        $request = new FakeRestRequest([
            'check_in' => '2026-05-10',
            'check_out' => '2026-05-12',
        ]);

        $response = $controller->public_available_beds($request);
        $data = $response->get_data();

        $this->assertSame(200, $response->get_status());
        $this->assertTrue($data['success']);
        $this->assertCount(2, $data['data']);
        $this->assertSame(1, $data['data'][0]['id']);
    }

    public function testReturnsAvailableBedsByRoomWhenRoomIdProvided(): void {
        $reservationService = $this->createMock(ReservationService::class);
        $guestService = $this->createMock(GuestService::class);
        $availabilityService = $this->createMock(AvailabilityService::class);

        $availabilityService
            ->expects($this->once())
            ->method('findAvailableBedsByRoom')
            ->with(5, '2026-05-10', '2026-05-12')
            ->willReturn([
                Bed::fromArray(['id' => 7, 'room_id' => 5, 'bed_number' => 1, 'bed_type' => 'double', 'is_active' => true]),
            ]);

        $availabilityService
            ->expects($this->never())
            ->method('findAvailableBeds');

        $controller = new PublicReservationsController($reservationService, $guestService, $availabilityService);

        $request = new FakeRestRequest([
            'check_in' => '2026-05-10',
            'check_out' => '2026-05-12',
            'room_id' => 5,
        ]);

        $response = $controller->public_available_beds($request);
        $data = $response->get_data();

        $this->assertSame(200, $response->get_status());
        $this->assertTrue($data['success']);
        $this->assertCount(1, $data['data']);
        $this->assertSame(7, $data['data'][0]['id']);
    }
}
