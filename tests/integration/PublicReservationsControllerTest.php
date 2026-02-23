<?php

declare(strict_types=1);

namespace Tests\Integration;

use MikroPlaneta\Booking\Core\Models\Guest;
use MikroPlaneta\Booking\Core\Models\Reservation;
use MikroPlaneta\Booking\Core\Services\GuestService;
use MikroPlaneta\Booking\Core\Services\ReservationService;
use MikroPlaneta\Booking\RestApi\Controllers\PublicReservationsController;
use PHPUnit\Framework\TestCase;
use Tests\Integration\Support\FakeRestRequest;

require_once __DIR__ . '/support/FakeRestRequest.php';

class TestPublicReservationsController extends TestCase {
    protected function setUp(): void {
        $GLOBALS['__mb_filters'] = [];
        $GLOBALS['__mb_actions'] = [];
        $GLOBALS['__mb_options'] = [];
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
            ->method('findOrCreateGuest')
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
}
