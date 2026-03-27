<?php
/**
 * REST API Routes Registration
 *
 * Registers all REST API routes
 *
 * @package MikroPlaneta\Booking
 * @since 1.0.0
 */

namespace MikroPlaneta\Booking\RestApi;

// Repositories
use MikroPlaneta\Booking\Core\Repositories\RoomRepository;
use MikroPlaneta\Booking\Core\Repositories\BedRepository;
use MikroPlaneta\Booking\Core\Repositories\ReservationRepository;
use MikroPlaneta\Booking\Core\Repositories\GuestRepository;
use MikroPlaneta\Booking\Core\Repositories\PricingRepository;
use MikroPlaneta\Booking\Core\Repositories\ReservationBedRepository;
use MikroPlaneta\Booking\Core\Repositories\ReservationPlaceRepository;
use MikroPlaneta\Booking\Core\Repositories\BedPlaceRepository;

// Services
use MikroPlaneta\Booking\Core\Services\AvailabilityService;
use MikroPlaneta\Booking\Core\Services\ReservationService;
use MikroPlaneta\Booking\Core\Services\GuestService;
use MikroPlaneta\Booking\Core\Services\NotificationService;
use MikroPlaneta\Booking\Core\Services\PricingService;

// Controllers
use MikroPlaneta\Booking\RestApi\Controllers\RoomsController;
use MikroPlaneta\Booking\RestApi\Controllers\ReservationsController;
use MikroPlaneta\Booking\RestApi\Controllers\PublicReservationsController;
use MikroPlaneta\Booking\RestApi\Controllers\GuestsController;
use MikroPlaneta\Booking\RestApi\Controllers\AvailabilityController;
use MikroPlaneta\Booking\RestApi\Controllers\PricingController;
use MikroPlaneta\Booking\RestApi\Controllers\DashboardController;
use MikroPlaneta\Booking\RestApi\Controllers\SettingsController;
use MikroPlaneta\Booking\RestApi\Controllers\ExtrasController;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Register all REST routes
 */
function register_routes(): void {
    
    // 1. Initialize Repositories
    $room_repo = new RoomRepository();
    $bed_repo = new BedRepository();
    $reservation_repo = new ReservationRepository();
    $guest_repo = new GuestRepository();
    $pricing_repo = new PricingRepository();
    $res_bed_repo = new ReservationBedRepository();
    $res_place_repo = new ReservationPlaceRepository();
    $bed_place_repo = new BedPlaceRepository();
    $logs_repo = new \MikroPlaneta\Booking\Core\Repositories\ChangesLogRepository();
    $extra_service_repo = new \MikroPlaneta\Booking\Core\Repositories\ExtraServiceRepository();
    $res_extra_repo = new \MikroPlaneta\Booking\Core\Repositories\ReservationExtraRepository();
    
    // 2. Initialize Services
    // Availability Service needs BedRepo & ReservationRepo
    $availability_service = new AvailabilityService($bed_repo, $reservation_repo, $bed_place_repo, $res_place_repo, $room_repo);
    
    // Pricing Service needs PricingRepo, BedRepo & RoomRepo
    $pricing_service = new PricingService($pricing_repo, $bed_repo, $room_repo);
    
    // Guest Service needs GuestRepo, ReservationRepo & BedRepo
    $guest_service = new GuestService($guest_repo, $reservation_repo, $bed_repo);
    
    // Notification Service (no deps)
    $notification_service = new NotificationService();
    
    // Changes logger service
    $logger_service = new \MikroPlaneta\Booking\Core\Services\LoggerService($logs_repo);
    
    // Extra Service Service
    $extra_service_service = new \MikroPlaneta\Booking\Core\Services\ExtraServiceService(
        $extra_service_repo,
        $res_extra_repo,
        $reservation_repo
    );
    
    // Reservation Service needs ReservationRepo, GuestRepo, BedRepo, AvailabilityService, PricingService, ReservationBedRepo, NotificationService
    $reservation_service = new ReservationService(
        $reservation_repo,
        $guest_repo,
        $bed_repo,
        $availability_service,
        $pricing_service,
        $res_bed_repo,
        $res_place_repo,
        $bed_place_repo,
        $notification_service,
        $room_repo,
        $logger_service
    );
    
    // 3. Initialize Controllers & Register Routes
    
    // Rooms Controller
    $rooms_controller = new RoomsController($room_repo, $bed_repo, $bed_place_repo);
    $rooms_controller->register_routes();
    
    // Reservations Controller
    $reservations_controller = new ReservationsController($reservation_service, $reservation_repo);
    $reservations_controller->register_routes();

    // Public Reservations Controller
    $public_reservations_controller = new PublicReservationsController($reservation_service, $guest_service, $availability_service);
    $public_reservations_controller->register_routes();
    
    // Guests Controller
    $guests_controller = new GuestsController($guest_service, $guest_repo);
    $guests_controller->register_routes();
    
    // Availability Controller
    $availability_controller = new AvailabilityController($availability_service);
    $availability_controller->register_routes();
    
    // Pricing Controller
    $pricing_controller = new PricingController($pricing_repo, $pricing_service);
    $pricing_controller->register_routes();
    
    // Dashboard Controller
    $dashboard_controller = new DashboardController($room_repo, $bed_repo, $reservation_repo);
    $dashboard_controller->register_routes();
    
    // Settings Controller
    $settings_controller = new SettingsController();
    $settings_controller->register_routes();
    
    // Logs Controller
    $logs_controller = new \MikroPlaneta\Booking\RestApi\Controllers\LogsController($logger_service);
    $logs_controller->register_routes();

    // Extras Controller
    $extras_controller = new ExtrasController($extra_service_repo, $extra_service_service);
    $extras_controller->register_routes();

    // Backup Controller
    $backup_controller = new \MikroPlaneta\Booking\RestApi\Controllers\BackupController();
    $backup_controller->register_routes();

    // Google Calendar Controller (BYOK OAuth + sync)
    $gcal_service    = new \MikroPlaneta\Booking\Core\Services\GoogleCalendarService();
    $gcal_controller = new \MikroPlaneta\Booking\RestApi\Controllers\GoogleCalendarController($gcal_service);
    $gcal_controller->register_routes();
}

add_action('rest_api_init', __NAMESPACE__ . '\\register_routes');
