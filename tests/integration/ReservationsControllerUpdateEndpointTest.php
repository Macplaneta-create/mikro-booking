<?php

declare(strict_types=1);

namespace Tests\Integration;

use MikroPlaneta\Booking\Core\Models\Reservation;
use MikroPlaneta\Booking\Core\Repositories\ReservationRepository;
use MikroPlaneta\Booking\Core\Services\ReservationService;
use MikroPlaneta\Booking\RestApi\Controllers\ReservationsController;
use PHPUnit\Framework\TestCase;
use Tests\Integration\Support\FakeRestRequest;

require_once __DIR__ . '/support/FakeRestRequest.php';

class ReservationsControllerUpdateEndpointTest extends TestCase {
    public function testUpdateEndpointForwardsBedIdsToService(): void {
        $service = $this->createMock(ReservationService::class);
        $repository = $this->createMock(ReservationRepository::class);
        $controller = new ReservationsController($service, $repository);

        $updated = Reservation::fromArray([
            'id' => 12,
            'guest_id' => 5,
            'bed_ids' => [3, 4],
            'check_in' => '2026-07-10',
            'check_out' => '2026-07-12',
            'status' => Reservation::STATUS_CONFIRMED,
        ]);

        $params = ['bed_ids' => [3, 4]];

        $service
            ->expects($this->once())
            ->method('updateReservation')
            ->with(12, $params)
            ->willReturn($updated);

        $request = new FakeRestRequest($params, ['id' => 12]);
        $response = $controller->update_item($request);
        $data = $response->get_data();

        $this->assertSame(200, $response->get_status());
        $this->assertTrue($data['success']);
        $this->assertSame([3, 4], $data['data']['bed_ids']);
    }
}
