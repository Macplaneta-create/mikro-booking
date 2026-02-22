<?php
/**
 * REST API: Extras Controller
 *
 * @package MikroPlaneta\Booking
 * @since 1.1.2
 */

namespace MikroPlaneta\Booking\RestApi\Controllers;

use WP_REST_Request;
use WP_REST_Response;
use MikroPlaneta\Booking\Core\Repositories\ExtraServiceRepository;
use MikroPlaneta\Booking\Core\Services\ExtraServiceService;
use MikroPlaneta\Booking\RestApi\RestController;

if (!defined('ABSPATH')) {
    exit;
}

class ExtrasController extends RestController {
    
    private ExtraServiceRepository $service_repo;
    private ExtraServiceService $service;

    public function __construct(ExtraServiceRepository $service_repo, ExtraServiceService $service) {
        $this->service_repo = $service_repo;
        $this->service = $service;
    }

    public function register_routes(): void {
        register_rest_route($this->namespace, '/extras/services', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'get_services'],
                'permission_callback' => [$this, 'check_permission'],
            ],
            [
                'methods' => 'POST',
                'callback' => [$this, 'create_service'],
                'permission_callback' => [$this, 'check_permission'],
            ],
        ]);

        register_rest_route($this->namespace, '/extras/services/(?P<id>\d+)', [
            [
                'methods' => 'PUT',
                'callback' => [$this, 'update_service'],
                'permission_callback' => [$this, 'check_permission'],
            ],
            [
                'methods' => 'DELETE',
                'callback' => [$this, 'delete_service'],
                'permission_callback' => [$this, 'check_permission'],
            ],
        ]);

        register_rest_route($this->namespace, '/reservations/(?P<id>\d+)/extras', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'get_reservation_extras'],
                'permission_callback' => [$this, 'check_permission'],
            ],
            [
                'methods' => 'POST',
                'callback' => [$this, 'set_reservation_extras'],
                'permission_callback' => [$this, 'check_permission'],
            ],
        ]);
    }

    public function get_services(WP_REST_Request $request): WP_REST_Response {
        $filters = $request->get_params();
        $services = $this->service_repo->all($filters);
        return $this->success(array_map(fn($s) => $s->toArray(), $services));
    }

    public function create_service(WP_REST_Request $request): WP_REST_Response {
        try {
            $service = $this->service_repo->create($request->get_params());
            return $this->success($service->toArray());
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    public function update_service(WP_REST_Request $request): WP_REST_Response {
        $id = (int)$request['id'];
        try {
            $service = $this->service_repo->update($id, $request->get_params());
            return $this->success($service->toArray());
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    public function delete_service(WP_REST_Request $request): WP_REST_Response {
        $id = (int)$request['id'];
        $this->service_repo->delete($id);
        return $this->success(true);
    }

    public function get_reservation_extras(WP_REST_Request $request): WP_REST_Response {
        $id = (int)$request['id'];
        $extras = $this->service->getExtrasForReservation($id);
        return $this->success(array_map(fn($e) => $e->toArray(), $extras));
    }

    public function set_reservation_extras(WP_REST_Request $request): WP_REST_Response {
        $id = (int)$request['id'];
        $extras_data = $request->get_param('extras'); // Array of {service_id, quantity}

        if (!is_array($extras_data)) {
            return $this->error('Extras must be an array');
        }

        try {
            $results = $this->service->setExtrasForReservation($id, $extras_data);
            return $this->success(array_map(fn($e) => $e->toArray(), $results));
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
    }
}
