<?php
/**
 * Google Calendar Service
 *
 * Integrates with Google Calendar API v3 using OAuth 2.0.
 * Bring Your Own Key (BYOK) model – each client provides their own
 * Google Cloud Console credentials (Client ID + Client Secret).
 *
 * @package MikroPlaneta\Booking
 * @since   1.4.0
 */

namespace MikroPlaneta\Booking\Core\Services;

use MikroPlaneta\Booking\Core\Models\Reservation;
use MikroPlaneta\Booking\Core\Models\Guest;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class GoogleCalendarService {

	/* ------------------------------------------------------------------ */
	/*  wp_options keys                                                     */
	/* ------------------------------------------------------------------ */

	const OPT_CLIENT_ID     = 'mikroplaneta_gcal_client_id';
	const OPT_CLIENT_SECRET = 'mikroplaneta_gcal_client_secret';
	const OPT_CALENDAR_ID   = 'mikroplaneta_gcal_calendar_id';
	const OPT_ACCESS_TOKEN  = 'mikroplaneta_gcal_access_token';
	const OPT_REFRESH_TOKEN = 'mikroplaneta_gcal_refresh_token';
	const OPT_ENABLED       = 'mikroplaneta_gcal_enabled';
	const OPT_OAUTH_STATE   = 'mikroplaneta_gcal_oauth_state';
	const OPT_EVENT_IDS     = 'mikroplaneta_gcal_event_ids';

	/* ------------------------------------------------------------------ */
	/*  Google API constants                                                */
	/* ------------------------------------------------------------------ */

	const GOOGLE_AUTH_URI    = 'https://accounts.google.com/o/oauth2/v2/auth';
	const GOOGLE_TOKEN_URI   = 'https://oauth2.googleapis.com/token';
	const GOOGLE_REVOKE_URI  = 'https://oauth2.googleapis.com/revoke';
	const GOOGLE_CAL_API     = 'https://www.googleapis.com/calendar/v3';
	const GOOGLE_SCOPE       = 'https://www.googleapis.com/auth/calendar';

	/* ------------------------------------------------------------------ */
	/*  Configuration (BYOK)                                                */
	/* ------------------------------------------------------------------ */

	/**
	 * Save client credentials (BYOK).
	 *
	 * @param string $client_id
	 * @param string $client_secret
	 */
	public function configure( string $client_id, string $client_secret ): void {
		update_option( self::OPT_CLIENT_ID, sanitize_text_field( $client_id ) );
		update_option( self::OPT_CLIENT_SECRET, sanitize_text_field( $client_secret ) );
	}

	public function getClientId(): string {
		return (string) get_option( self::OPT_CLIENT_ID, '' );
	}

	public function getClientSecret(): string {
		return (string) get_option( self::OPT_CLIENT_SECRET, '' );
	}

	public function hasCredentials(): bool {
		return $this->getClientId() !== '' && $this->getClientSecret() !== '';
	}

	/* ------------------------------------------------------------------ */
	/*  Connection state                                                     */
	/* ------------------------------------------------------------------ */

	public function isConnected(): bool {
		return $this->hasCredentials() && get_option( self::OPT_REFRESH_TOKEN, '' ) !== '';
	}

	public function isEnabled(): bool {
		return $this->isConnected() && (bool) get_option( self::OPT_ENABLED, false );
	}

	public function getConnectedEmail(): string {
		$token_data = $this->getStoredAccessToken();
		return $token_data['email'] ?? '';
	}

	public function setEnabled( bool $enabled ): void {
		update_option( self::OPT_ENABLED, $enabled );
	}

	public function setCalendarId( string $calendar_id ): void {
		update_option( self::OPT_CALENDAR_ID, sanitize_text_field( $calendar_id ) );
	}

	public function getCalendarId(): string {
		return (string) get_option( self::OPT_CALENDAR_ID, 'primary' );
	}

	/* ------------------------------------------------------------------ */
	/*  OAuth 2.0 flow                                                       */
	/* ------------------------------------------------------------------ */

	/**
	 * Generate redirect URI pointing to WP admin so Google redirects back.
	 */
	public function getRedirectUri(): string {
		return admin_url( 'admin.php?page=mikroplaneta-booking&gcal_callback=1' );
	}

	/**
	 * Build the Google OAuth consent URL.
	 */
	public function getAuthUrl(): string {
		$state = wp_generate_password( 24, false );
		update_option( self::OPT_OAUTH_STATE, $state );

		$params = [
			'client_id'     => $this->getClientId(),
			'redirect_uri'  => $this->getRedirectUri(),
			'response_type' => 'code',
			'scope'         => self::GOOGLE_SCOPE,
			'access_type'   => 'offline',
			'prompt'        => 'consent',
			'state'         => $state,
		];

		return self::GOOGLE_AUTH_URI . '?' . http_build_query( $params );
	}

	/**
	 * Exchange authorization code for access + refresh tokens.
	 *
	 * @throws \Exception on failure.
	 */
	public function exchangeCode( string $code, string $state ): void {
		$stored_state = (string) get_option( self::OPT_OAUTH_STATE, '' );
		if ( $stored_state === '' || ! hash_equals( $stored_state, $state ) ) {
			throw new \Exception( 'Invalid OAuth state – CSRF check failed.' );
		}
		delete_option( self::OPT_OAUTH_STATE );

		$response = wp_remote_post( self::GOOGLE_TOKEN_URI, [
			'body' => [
				'code'          => $code,
				'client_id'     => $this->getClientId(),
				'client_secret' => $this->getClientSecret(),
				'redirect_uri'  => $this->getRedirectUri(),
				'grant_type'    => 'authorization_code',
			],
		] );

		$token = $this->parseTokenResponse( $response );
		$this->storeTokens( $token );
	}

	/**
	 * Fetch current access token, refreshing if expired.
	 *
	 * @throws \Exception when unable to obtain a valid token.
	 */
	public function getAccessToken(): string {
		$token_data = $this->getStoredAccessToken();

		if ( ! empty( $token_data['token'] ) && isset( $token_data['expires_at'] ) ) {
			if ( time() < ( (int) $token_data['expires_at'] - 60 ) ) {
				return $token_data['token'];
			}
		}

		// Refresh
		$refresh_token = (string) get_option( self::OPT_REFRESH_TOKEN, '' );
		if ( $refresh_token === '' ) {
			throw new \Exception( 'No refresh token stored – please reconnect Google Calendar.' );
		}

		$response = wp_remote_post( self::GOOGLE_TOKEN_URI, [
			'body' => [
				'client_id'     => $this->getClientId(),
				'client_secret' => $this->getClientSecret(),
				'refresh_token' => $refresh_token,
				'grant_type'    => 'refresh_token',
			],
		] );

		$token = $this->parseTokenResponse( $response );
		$this->storeTokens( $token, $refresh_token ); // keep old refresh token if new not provided
		return $token['access_token'];
	}

	/* ------------------------------------------------------------------ */
	/*  Calendars                                                            */
	/* ------------------------------------------------------------------ */

	/**
	 * List user's Google Calendars.
	 *
	 * @return array<array{id: string, summary: string, primary: bool}>
	 * @throws \Exception
	 */
	public function listCalendars(): array {
		$response = wp_remote_get(
			self::GOOGLE_CAL_API . '/users/me/calendarList',
			[ 'headers' => $this->authHeaders() ]
		);

		$body = $this->parseApiResponse( $response );
		$calendars = [];
		foreach ( $body['items'] ?? [] as $item ) {
			$calendars[] = [
				'id'      => $item['id'] ?? '',
				'summary' => $item['summary'] ?? $item['id'] ?? '',
				'primary' => (bool) ( $item['primary'] ?? false ),
			];
		}
		return $calendars;
	}

	/* ------------------------------------------------------------------ */
	/*  Events CRUD                                                          */
	/* ------------------------------------------------------------------ */

	/**
	 * Create a Google Calendar event for a reservation.
	 *
	 * @throws \Exception
	 */
	public function createEvent( Reservation $reservation, Guest $guest ): string {
		$body     = wp_json_encode( $this->buildEventPayload( $reservation, $guest ) );
		$calendar = urlencode( $this->getCalendarId() );

		$response = wp_remote_post(
			self::GOOGLE_CAL_API . "/calendars/{$calendar}/events",
			array_merge( $this->authHeaders(), [
				'body'    => $body,
				'headers' => array_merge( $this->authHeaders()['headers'], [
					'Content-Type' => 'application/json',
				] ),
			] )
		);

		$data     = $this->parseApiResponse( $response );
		$event_id = $data['id'] ?? '';

		if ( $event_id !== '' ) {
			$this->saveEventId( (int) $reservation->id, $event_id );
		}

		return $event_id;
	}

	/**
	 * Update an existing Google Calendar event.
	 *
	 * @throws \Exception
	 */
	public function updateEvent( Reservation $reservation, Guest $guest ): void {
		$event_id = $this->getEventId( (int) $reservation->id );
		if ( $event_id === '' ) {
			// Event doesn't exist yet – create it
			$this->createEvent( $reservation, $guest );
			return;
		}

		$body     = wp_json_encode( $this->buildEventPayload( $reservation, $guest ) );
		$calendar = urlencode( $this->getCalendarId() );

		wp_remote_request(
			self::GOOGLE_CAL_API . "/calendars/{$calendar}/events/{$event_id}",
			array_merge( $this->authHeaders(), [
				'method'  => 'PUT',
				'body'    => $body,
				'headers' => array_merge( $this->authHeaders()['headers'], [
					'Content-Type' => 'application/json',
				] ),
			] )
		);
	}

	/**
	 * Delete a Google Calendar event.
	 */
	public function deleteEvent( int $reservation_id ): void {
		$event_id = $this->getEventId( $reservation_id );
		if ( $event_id === '' ) {
			return;
		}

		$calendar = urlencode( $this->getCalendarId() );

		wp_remote_request(
			self::GOOGLE_CAL_API . "/calendars/{$calendar}/events/{$event_id}",
			array_merge( $this->authHeaders(), [ 'method' => 'DELETE' ] )
		);

		$this->removeEventId( $reservation_id );
	}

	/* ------------------------------------------------------------------ */
	/*  Bulk sync                                                            */
	/* ------------------------------------------------------------------ */

	/**
	 * Sync all active reservations to Google Calendar.
	 *
	 * @return array{synced: int, failed: int}
	 */
	public function syncAll(): array {
		global $wpdb;

		$table  = \MikroPlaneta\Booking\Core\Database\Schema::get_table_name( 'reservations' );
		$gtable = \MikroPlaneta\Booking\Core\Database\Schema::get_table_name( 'guests' );

		$rows = $wpdb->get_results(
			"SELECT r.*, g.first_name, g.last_name, g.email
			 FROM {$table} r
			 LEFT JOIN {$gtable} g ON g.id = r.guest_id
			 WHERE r.status IN ('pending','confirmed','checked_in')",
			ARRAY_A
		);

		$synced = 0;
		$failed = 0;

		foreach ( $rows as $row ) {
			try {
				$reservation = new Reservation( $row );
				$guest       = new Guest( $row ); // has first_name, last_name, email
				$this->createEvent( $reservation, $guest );
				$synced++;
			} catch ( \Throwable $e ) {
				$failed++;
				error_log( '[MikroPlaneta Booking] GCal syncAll failed for reservation #' . ( $row['id'] ?? '?' ) . ': ' . $e->getMessage() );
			}
		}

		return [ 'synced' => $synced, 'failed' => $failed ];
	}

	/* ------------------------------------------------------------------ */
	/*  Disconnect                                                           */
	/* ------------------------------------------------------------------ */

	public function disconnect(): void {
		$refresh_token = get_option( self::OPT_REFRESH_TOKEN, '' );
		if ( $refresh_token ) {
			wp_remote_post( self::GOOGLE_REVOKE_URI, [ 'body' => [ 'token' => $refresh_token ] ] );
		}

		delete_option( self::OPT_ACCESS_TOKEN );
		delete_option( self::OPT_REFRESH_TOKEN );
		delete_option( self::OPT_CALENDAR_ID );
		delete_option( self::OPT_ENABLED );
		delete_option( self::OPT_EVENT_IDS );
	}

	/* ------------------------------------------------------------------ */
	/*  WordPress action hooks (thin wrappers)                               */
	/* ------------------------------------------------------------------ */

	/**
	 * @param Reservation $reservation
	 * @param int[]       $bed_ids
	 */
	public function onReservationCreated( Reservation $reservation, array $bed_ids ): void {
		if ( ! $this->isEnabled() ) {
			return;
		}
		try {
			$guest = $this->loadGuest( (int) $reservation->guest_id );
			if ( $guest ) {
				$this->createEvent( $reservation, $guest );
			}
		} catch ( \Throwable $e ) {
			error_log( '[MikroPlaneta Booking] GCal: failed to create event for reservation #' . $reservation->id . ': ' . $e->getMessage() );
		}
	}

	/**
	 * @param Reservation $reservation
	 * @param array       $old_data
	 */
	public function onReservationUpdated( Reservation $reservation, array $old_data ): void {
		if ( ! $this->isEnabled() ) {
			return;
		}
		try {
			$guest = $this->loadGuest( (int) $reservation->guest_id );
			if ( $guest ) {
				$this->updateEvent( $reservation, $guest );
			}
		} catch ( \Throwable $e ) {
			error_log( '[MikroPlaneta Booking] GCal: failed to update event for reservation #' . $reservation->id . ': ' . $e->getMessage() );
		}
	}

	/**
	 * @param Reservation $reservation
	 * @param string      $reason
	 */
	public function onReservationCancelled( Reservation $reservation, string $reason = '' ): void {
		if ( ! $this->isEnabled() ) {
			return;
		}
		try {
			$this->deleteEvent( (int) $reservation->id );
		} catch ( \Throwable $e ) {
			error_log( '[MikroPlaneta Booking] GCal: failed to delete event for reservation #' . $reservation->id . ': ' . $e->getMessage() );
		}
	}

	/* ------------------------------------------------------------------ */
	/*  Private helpers                                                      */
	/* ------------------------------------------------------------------ */

	private function buildEventPayload( Reservation $reservation, Guest $guest ): array {
		$hotel_name = get_option( 'mikroplaneta_booking_hotel_name', get_bloginfo( 'name' ) );
		$guest_name = trim( ( $guest->first_name ?? '' ) . ' ' . ( $guest->last_name ?? '' ) );
		$adults     = (int) ( $reservation->adults ?? 1 );
		$children   = (int) ( $reservation->children ?? 0 );
		$price      = number_format( (float) ( $reservation->total_price ?? 0 ), 2 ) . ' PLN';
		$status     = $reservation->status ?? 'pending';

		$description = implode( "\n", [
			"Gość: {$guest_name}",
			"Dorosłych: {$adults}" . ( $children ? ", Dzieci: {$children}" : '' ),
			"Cena: {$price}",
			"Status: {$status}",
			( $reservation->notes ? "Notatki: {$reservation->notes}" : '' ),
		] );

		// check_out date must be exclusive (Google uses exclusive end for all-day events)
		$check_in_dt  = new \DateTime( $reservation->check_in );
		$check_out_dt = new \DateTime( $reservation->check_out );
		// add 1 day so check_out day is included visually in multi-day display
		$check_out_dt->modify( '+1 day' );

		return [
			'summary'     => "Rezerwacja #{$reservation->id} – {$guest_name}",
			'description' => $description,
			'location'    => $hotel_name,
			'start'       => [ 'date' => $check_in_dt->format( 'Y-m-d' ) ],
			'end'         => [ 'date' => $check_out_dt->format( 'Y-m-d' ) ],
			'extendedProperties' => [
				'private' => [
					'mikroplaneta_reservation_id' => (string) $reservation->id,
				],
			],
		];
	}

	private function authHeaders(): array {
		return [
			'headers' => [
				'Authorization' => 'Bearer ' . $this->getAccessToken(),
			],
		];
	}

	/**
	 * @throws \Exception
	 */
	private function parseTokenResponse( $response ): array {
		if ( is_wp_error( $response ) ) {
			throw new \Exception( 'Google OAuth request failed: ' . $response->get_error_message() );
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( $code !== 200 || empty( $body['access_token'] ) ) {
			$error = $body['error_description'] ?? $body['error'] ?? 'Unknown error';
			throw new \Exception( "Google OAuth error ({$code}): {$error}" );
		}

		return $body;
	}

	/**
	 * @throws \Exception
	 */
	private function parseApiResponse( $response ): array {
		if ( is_wp_error( $response ) ) {
			throw new \Exception( 'Google Calendar API request failed: ' . $response->get_error_message() );
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( $code >= 400 ) {
			$error = $body['error']['message'] ?? "HTTP {$code}";
			throw new \Exception( "Google Calendar API error: {$error}" );
		}

		return $body ?? [];
	}

	private function storeTokens( array $token, string $existing_refresh_token = '' ): void {
		$access_data = [
			'token'      => $token['access_token'],
			'expires_at' => time() + ( (int) ( $token['expires_in'] ?? 3600 ) ),
			'email'      => $token['email'] ?? '',
		];
		update_option( self::OPT_ACCESS_TOKEN, wp_json_encode( $access_data ) );

		$refresh = $token['refresh_token'] ?? $existing_refresh_token;
		if ( $refresh !== '' ) {
			update_option( self::OPT_REFRESH_TOKEN, $refresh );
		}
	}

	private function getStoredAccessToken(): array {
		$raw = get_option( self::OPT_ACCESS_TOKEN, '' );
		if ( ! $raw ) {
			return [];
		}
		return json_decode( $raw, true ) ?? [];
	}

	/**
	 * Load guest email to get account info after token exchange.
	 */
	private function fetchGoogleUserEmail( string $access_token ): string {
		$response = wp_remote_get( 'https://www.googleapis.com/oauth2/v2/userinfo', [
			'headers' => [ 'Authorization' => "Bearer {$access_token}" ],
		] );

		if ( is_wp_error( $response ) ) {
			return '';
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		return $body['email'] ?? '';
	}

	private function saveEventId( int $reservation_id, string $event_id ): void {
		$map = $this->getEventIdsMap();
		$map[ $reservation_id ] = $event_id;
		update_option( self::OPT_EVENT_IDS, wp_json_encode( $map ) );
	}

	private function getEventId( int $reservation_id ): string {
		$map = $this->getEventIdsMap();
		return $map[ $reservation_id ] ?? '';
	}

	private function removeEventId( int $reservation_id ): void {
		$map = $this->getEventIdsMap();
		unset( $map[ $reservation_id ] );
		update_option( self::OPT_EVENT_IDS, wp_json_encode( $map ) );
	}

	private function getEventIdsMap(): array {
		$raw = get_option( self::OPT_EVENT_IDS, '' );
		if ( ! $raw ) {
			return [];
		}
		return json_decode( $raw, true ) ?? [];
	}

	private function loadGuest( int $guest_id ): ?Guest {
		global $wpdb;
		$table = \MikroPlaneta\Booking\Core\Database\Schema::get_table_name( 'guests' );
		$row   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $guest_id ), ARRAY_A );
		if ( ! $row ) {
			return null;
		}
		return new Guest( $row );
	}
}
