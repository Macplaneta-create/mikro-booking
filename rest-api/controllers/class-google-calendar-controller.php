<?php
/**
 * Google Calendar REST Controller
 *
 * Handles all admin REST API endpoints for Google Calendar OAuth flow,
 * settings management, and synchronization.
 *
 * @package MikroPlaneta\Booking
 * @since   1.4.0
 */

namespace MikroPlaneta\Booking\RestApi\Controllers;

use MikroPlaneta\Booking\Core\Services\GoogleCalendarService;
use WP_REST_Controller;
use WP_REST_Request;
use WP_REST_Response;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class GoogleCalendarController extends WP_REST_Controller {

	private GoogleCalendarService $gcal;

	protected $namespace = 'mikroplaneta/v1';
	protected $rest_base = 'gcal';

	public function __construct( GoogleCalendarService $gcal ) {
		$this->gcal = $gcal;
	}

	public function register_routes(): void {
		// GET /gcal/auth-url  - Returns OAuth authorization URL
		register_rest_route( $this->namespace, '/' . $this->rest_base . '/auth-url', [
			'methods'             => \WP_REST_Server::READABLE,
			'callback'            => [ $this, 'get_auth_url' ],
			'permission_callback' => [ $this, 'admin_permissions_check' ],
		] );

		// GET /gcal/status  - Connection status + available calendars
		register_rest_route( $this->namespace, '/' . $this->rest_base . '/status', [
			'methods'             => \WP_REST_Server::READABLE,
			'callback'            => [ $this, 'get_status' ],
			'permission_callback' => [ $this, 'admin_permissions_check' ],
		] );

		// POST /gcal/settings  - Save Client ID, Secret, calendar_id, enabled
		register_rest_route( $this->namespace, '/' . $this->rest_base . '/settings', [
			'methods'             => \WP_REST_Server::CREATABLE,
			'callback'            => [ $this, 'update_settings' ],
			'permission_callback' => [ $this, 'admin_permissions_check' ],
		] );

		// POST /gcal/callback  - Exchange OAuth code for tokens
		register_rest_route( $this->namespace, '/' . $this->rest_base . '/callback', [
			'methods'             => \WP_REST_Server::CREATABLE,
			'callback'            => [ $this, 'handle_callback' ],
			'permission_callback' => [ $this, 'admin_permissions_check' ],
		] );

		// POST /gcal/disconnect  - Revoke tokens
		register_rest_route( $this->namespace, '/' . $this->rest_base . '/disconnect', [
			'methods'             => \WP_REST_Server::CREATABLE,
			'callback'            => [ $this, 'disconnect' ],
			'permission_callback' => [ $this, 'admin_permissions_check' ],
		] );

		// POST /gcal/sync-all  - Bulk sync all active reservations
		register_rest_route( $this->namespace, '/' . $this->rest_base . '/sync-all', [
			'methods'             => \WP_REST_Server::CREATABLE,
			'callback'            => [ $this, 'sync_all' ],
			'permission_callback' => [ $this, 'admin_permissions_check' ],
		] );
	}

	/* ------------------------------------------------------------------ */
	/*  Handlers                                                             */
	/* ------------------------------------------------------------------ */

	public function get_auth_url( WP_REST_Request $request ): WP_REST_Response {
		if ( ! $this->gcal->hasCredentials() ) {
			return $this->error( 'Brak Client ID lub Client Secret. Uzupełnij ustawienia Google Calendar.', 400 );
		}

		return $this->success( [
			'auth_url'     => $this->gcal->getAuthUrl(),
			'redirect_uri' => $this->gcal->getRedirectUri(),
		] );
	}

	public function get_status( WP_REST_Request $request ): WP_REST_Response {
		$connected = $this->gcal->isConnected();
		$calendars = [];

		if ( $connected ) {
			try {
				$calendars = $this->gcal->listCalendars();
			} catch ( \Throwable $e ) {
				// Token might be invalid – report as disconnected
				error_log( '[MikroPlaneta Booking] GCal status: failed to list calendars: ' . $e->getMessage() );
				$connected = false;
			}
		}

		return $this->success( [
			'connected'       => $connected,
			'enabled'         => $this->gcal->isEnabled(),
			'has_credentials' => $this->gcal->hasCredentials(),
			'email'           => $connected ? $this->gcal->getConnectedEmail() : '',
			'calendar_id'     => $this->gcal->getCalendarId(),
			'redirect_uri'    => $this->gcal->getRedirectUri(),
			'calendars'       => $calendars,
		] );
	}

	public function update_settings( WP_REST_Request $request ): WP_REST_Response {
		$params = $request->get_json_params();

		$client_id     = isset( $params['client_id'] )     ? sanitize_text_field( $params['client_id'] )     : null;
		$client_secret = isset( $params['client_secret'] ) ? sanitize_text_field( $params['client_secret'] ) : null;
		$calendar_id   = isset( $params['calendar_id'] )   ? sanitize_text_field( $params['calendar_id'] )   : null;
		$enabled       = isset( $params['enabled'] )       ? (bool) $params['enabled']                       : null;

		if ( $client_id !== null && $client_secret !== null ) {
			$this->gcal->configure( $client_id, $client_secret );
		}

		if ( $calendar_id !== null ) {
			$this->gcal->setCalendarId( $calendar_id );
		}

		if ( $enabled !== null ) {
			$this->gcal->setEnabled( $enabled );
		}

		return $this->success( [ 'message' => 'Ustawienia Google Calendar zapisane.' ] );
	}

	public function handle_callback( WP_REST_Request $request ): WP_REST_Response {
		$params = $request->get_json_params();
		$code   = sanitize_text_field( (string) ( $params['code']  ?? '' ) );
		$state  = sanitize_text_field( (string) ( $params['state'] ?? '' ) );

		if ( $code === '' || $state === '' ) {
			return $this->error( 'Brak wymaganych parametrów (code, state).', 400 );
		}

		try {
			$this->gcal->exchangeCode( $code, $state );
		} catch ( \Throwable $e ) {
			error_log( '[MikroPlaneta Booking] GCal callback error: ' . $e->getMessage() );
			return $this->error( 'Błąd autoryzacji: ' . $e->getMessage(), 400 );
		}

		// Load calendars immediately so the frontend can show the list
		try {
			$calendars = $this->gcal->listCalendars();
		} catch ( \Throwable $e ) {
			$calendars = [];
		}

		return $this->success( [
			'message'   => 'Połączono z Google Calendar pomyślnie!',
			'email'     => $this->gcal->getConnectedEmail(),
			'calendars' => $calendars,
		] );
	}

	public function disconnect( WP_REST_Request $request ): WP_REST_Response {
		$this->gcal->disconnect();
		return $this->success( [ 'message' => 'Rozłączono z Google Calendar.' ] );
	}

	public function sync_all( WP_REST_Request $request ): WP_REST_Response {
		if ( ! $this->gcal->isEnabled() ) {
			return $this->error( 'Integracja Google Calendar nie jest aktywna.', 400 );
		}

		try {
			$result = $this->gcal->syncAll();
		} catch ( \Throwable $e ) {
			return $this->error( 'Błąd synchronizacji: ' . $e->getMessage(), 500 );
		}

		return $this->success( [
			'message' => "Synchronizacja zakończona. Zsynchronizowano: {$result['synced']}, błędy: {$result['failed']}.",
			'synced'  => $result['synced'],
			'failed'  => $result['failed'],
		] );
	}

	/* ------------------------------------------------------------------ */
	/*  Permissions                                                          */
	/* ------------------------------------------------------------------ */

	public function admin_permissions_check(): bool {
		return current_user_can( 'manage_options' );
	}

	/* ------------------------------------------------------------------ */
	/*  Helpers                                                              */
	/* ------------------------------------------------------------------ */

	private function success( array $data ): WP_REST_Response {
		return new WP_REST_Response( [ 'success' => true, 'data' => $data ], 200 );
	}

	private function error( string $message, int $status = 400 ): WP_REST_Response {
		return new WP_REST_Response( [ 'success' => false, 'message' => $message ], $status );
	}
}
