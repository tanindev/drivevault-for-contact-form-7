<?php
namespace DriveVault\CF7\Google;

defined( 'ABSPATH' ) || exit;

/**
 * Google OAuth 2.0 Token & Authentication Manager.
 */
class OAuthManager {

	const AUTH_URL     = 'https://accounts.google.com/o/oauth2/v2/auth';
	const TOKEN_URL    = 'https://oauth2.googleapis.com/token';
	const REVOKE_URL   = 'https://oauth2.googleapis.com/revoke';
	const USERINFO_URL = 'https://www.googleapis.com/oauth2/v2/userinfo';

	const STATE_TRANSIENT = 'dvcf7_oauth_state';
	const STATE_TTL       = 900; // 15 minutes.

	const SCOPES = array(
		'https://www.googleapis.com/auth/spreadsheets',
		'https://www.googleapis.com/auth/drive.file',
		'https://www.googleapis.com/auth/userinfo.email',
		'https://www.googleapis.com/auth/userinfo.profile',
	);

	/**
	 * HTTP Client.
	 */
	protected HttpClient $http;

	/**
	 * Memoized in-memory token cache.
	 */
	protected ?array $cached_tokens = null;

	/**
	 * Constructor.
	 *
	 * @param HttpClient $http
	 */
	public function __construct( HttpClient $http ) {
		$this->http = $http;
		add_action( 'admin_init', array( $this, 'maybe_handle_oauth_redirect' ) );
		add_action( 'dvcf7_token_refresh', array( $this, 'cron_token_refresh' ) );

		if ( ! wp_next_scheduled( 'dvcf7_token_refresh' ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'hourly', 'dvcf7_token_refresh' );
		}
	}

	/**
	 * Get OAuth Redirect URI.
	 *
	 * @return string
	 */
	public function get_redirect_uri(): string {
		return admin_url( 'admin.php?page=' . DRIVEVAULT_CF7_PAGE_SLUG );
	}

	/**
	 * Get stored client credentials.
	 *
	 * @return array
	 */
	public function get_credentials(): array {
		$settings = get_option( DRIVEVAULT_CF7_OPTION_SETTINGS, array() );
		return array(
			'client_id'     => ! empty( $settings['client_id'] ) ? trim( $settings['client_id'] ) : '',
			'client_secret' => ! empty( $settings['client_secret'] ) ? trim( $settings['client_secret'] ) : '',
		);
	}

	/**
	 * Generate Google OAuth Authorization URL.
	 *
	 * @return string|\WP_Error
	 */
	public function get_auth_url(): string|\WP_Error {
		$creds = $this->get_credentials();
		if ( empty( $creds['client_id'] ) || empty( $creds['client_secret'] ) ) {
			return new \WP_Error(
				'missing_credentials',
				__( 'Please configure your Google Cloud Client ID and Client Secret first.', 'drivevault-for-contact-form-7' )
			);
		}

		$state = wp_generate_uuid4();
		set_transient( self::STATE_TRANSIENT, $state, self::STATE_TTL );

		$params = array(
			'client_id'              => $creds['client_id'],
			'redirect_uri'           => $this->get_redirect_uri(),
			'response_type'          => 'code',
			'scope'                  => implode( ' ', self::SCOPES ),
			'access_type'            => 'offline',
			'prompt'                 => 'consent',
			'include_granted_scopes' => 'true',
			'state'                  => $state,
		);

		return self::AUTH_URL . '?' . http_build_query( $params );
	}

	/**
	 * Listen for OAuth redirect on the plugin settings page.
	 */
	public function maybe_handle_oauth_redirect(): void {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		if ( ! isset( $_GET['page'] ) || DRIVEVAULT_CF7_PAGE_SLUG !== $_GET['page'] ) {
			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		if ( isset( $_GET['error'] ) ) {
			$error = sanitize_text_field( wp_unslash( $_GET['error'] ) );
			/* translators: %s: Error message from Google */
			set_transient( 'dvcf7_auth_notice', array( 'type' => 'error', 'msg' => sprintf( __( 'Google Authorization denied: %s', 'drivevault-for-contact-form-7' ), $error ) ), 60 );
			wp_safe_redirect( $this->get_redirect_uri() );
			exit;
		}

		if ( isset( $_GET['code'] ) && isset( $_GET['state'] ) ) {
			$code  = sanitize_text_field( wp_unslash( $_GET['code'] ) );
			$state = sanitize_text_field( wp_unslash( $_GET['state'] ) );

			$result = $this->exchange_code( $code, $state );

			if ( is_wp_error( $result ) ) {
				set_transient( 'dvcf7_auth_notice', array( 'type' => 'error', 'msg' => $result->get_error_message() ), 60 );
			} else {
				set_transient( 'dvcf7_auth_notice', array( 'type' => 'success', 'msg' => __( 'Google Account connected successfully!', 'drivevault-for-contact-form-7' ) ), 60 );
			}

			wp_safe_redirect( $this->get_redirect_uri() );
			exit;
		}
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
	}

	/**
	 * Exchange authorization code for tokens.
	 *
	 * @param string $code
	 * @param string $state
	 * @return bool|\WP_Error
	 */
	public function exchange_code( string $code, string $state ): bool|\WP_Error {
		$saved_state = get_transient( self::STATE_TRANSIENT );
		delete_transient( self::STATE_TRANSIENT );

		if ( empty( $saved_state ) || ! hash_equals( $saved_state, $state ) ) {
			return new \WP_Error( 'state_mismatch', __( 'Security validation failed: OAuth state mismatch.', 'drivevault-for-contact-form-7' ) );
		}

		$creds = $this->get_credentials();
		if ( empty( $creds['client_id'] ) || empty( $creds['client_secret'] ) ) {
			return new \WP_Error( 'missing_credentials', __( 'Missing OAuth credentials.', 'drivevault-for-contact-form-7' ) );
		}

		$body = array(
			'code'          => $code,
			'client_id'     => $creds['client_id'],
			'client_secret' => $creds['client_secret'],
			'redirect_uri'  => $this->get_redirect_uri(),
			'grant_type'    => 'authorization_code',
		);

		$response = $this->http->post(
			self::TOKEN_URL,
			http_build_query( $body ),
			array( 'Content-Type' => 'application/x-www-form-urlencoded' )
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		if ( empty( $response['status'] ) || $response['status'] < 200 || $response['status'] >= 300 ) {
			$msg = ! empty( $response['body']['error_description'] ) ? $response['body']['error_description'] : __( 'Failed to exchange authorization code.', 'drivevault-for-contact-form-7' );
			return new \WP_Error( 'token_exchange_failed', $msg );
		}

		$data = $response['body'];
		if ( empty( $data['access_token'] ) ) {
			return new \WP_Error( 'invalid_token_response', __( 'Invalid response received from Google token server.', 'drivevault-for-contact-form-7' ) );
		}

		$tokens = array(
			'access_token'  => $data['access_token'],
			'refresh_token' => ! empty( $data['refresh_token'] ) ? $data['refresh_token'] : '',
			'expires_at'    => time() + ( isset( $data['expires_in'] ) ? (int) $data['expires_in'] : 3600 ),
			'scope'         => ! empty( $data['scope'] ) ? $data['scope'] : '',
			'created_at'    => time(),
		);

		// If refresh_token was not returned (re-authorization), preserve existing refresh token.
		if ( empty( $tokens['refresh_token'] ) ) {
			$existing = $this->get_stored_tokens();
			if ( ! empty( $existing['refresh_token'] ) ) {
				$tokens['refresh_token'] = $existing['refresh_token'];
			}
		}

		$this->save_tokens( $tokens );

		// Warm userinfo cache.
		delete_transient( 'dvcf7_google_userinfo' );
		$this->get_account_info( true );

		return true;
	}

	/**
	 * Get a valid access token, auto-refreshing if expired or close to expiring.
	 *
	 * @return string|\WP_Error
	 */
	public function get_valid_access_token(): string|\WP_Error {
		$tokens = $this->get_stored_tokens();
		if ( empty( $tokens['access_token'] ) ) {
			return new \WP_Error( 'not_connected', __( 'Google account is not connected.', 'drivevault-for-contact-form-7' ), array( 'status' => 400 ) );
		}

		// If token expires within 90 seconds, refresh it.
		if ( ! empty( $tokens['expires_at'] ) && ( $tokens['expires_at'] - time() ) < 90 ) {
			$refreshed = $this->refresh_token();
			if ( is_wp_error( $refreshed ) ) {
				return $refreshed;
			}
			$tokens = $this->get_stored_tokens();
		}

		return $tokens['access_token'];
	}

	/**
	 * Refresh access token using refresh_token.
	 *
	 * @return bool|\WP_Error
	 */
	public function refresh_token(): bool|\WP_Error {
		$tokens = $this->get_stored_tokens();
		if ( empty( $tokens['refresh_token'] ) ) {
			return new \WP_Error( 'no_refresh_token', __( 'No refresh token available. Please reconnect your Google account.', 'drivevault-for-contact-form-7' ) );
		}

		$creds = $this->get_credentials();
		if ( empty( $creds['client_id'] ) || empty( $creds['client_secret'] ) ) {
			return new \WP_Error( 'missing_credentials', __( 'Missing OAuth credentials.', 'drivevault-for-contact-form-7' ) );
		}

		$body = array(
			'client_id'     => $creds['client_id'],
			'client_secret' => $creds['client_secret'],
			'refresh_token' => $tokens['refresh_token'],
			'grant_type'    => 'refresh_token',
		);

		$response = $this->http->post(
			self::TOKEN_URL,
			http_build_query( $body ),
			array( 'Content-Type' => 'application/x-www-form-urlencoded' )
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		if ( empty( $response['status'] ) || $response['status'] < 200 || $response['status'] >= 300 ) {
			return new \WP_Error( 'refresh_failed', __( 'Could not refresh Google access token. Please re-authorize.', 'drivevault-for-contact-form-7' ) );
		}

		$data = $response['body'];
		if ( empty( $data['access_token'] ) ) {
			return new \WP_Error( 'invalid_refresh_response', __( 'Invalid refresh token response.', 'drivevault-for-contact-form-7' ) );
		}

		$tokens['access_token'] = $data['access_token'];
		$tokens['expires_at']   = time() + ( isset( $data['expires_in'] ) ? (int) $data['expires_in'] : 3600 );
		if ( ! empty( $data['refresh_token'] ) ) {
			$tokens['refresh_token'] = $data['refresh_token'];
		}

		$this->save_tokens( $tokens );

		return true;
	}

	/**
	 * WP-Cron hourly token refresh callback.
	 */
	public function cron_token_refresh(): void {
		$tokens = $this->get_stored_tokens();
		if ( ! empty( $tokens['refresh_token'] ) ) {
			$this->refresh_token();
		}
	}

	/**
	 * Disconnect Google Account and revoke tokens.
	 *
	 * @return bool
	 */
	public function disconnect(): bool {
		$tokens = $this->get_stored_tokens();

		if ( ! empty( $tokens['access_token'] ) ) {
			$this->http->post(
				self::REVOKE_URL . '?token=' . urlencode( $tokens['access_token'] ),
				null,
				array( 'Content-Type' => 'application/x-www-form-urlencoded' )
			);
		}

		delete_option( DRIVEVAULT_CF7_OPTION_TOKENS );
		delete_transient( 'dvcf7_google_userinfo' );
		delete_transient( 'dvcf7_sheets_cache' );
		delete_transient( 'dvcf7_folders_cache' );

		$this->cached_tokens = null;

		return true;
	}

	/**
	 * Fetch Google Account profile info.
	 *
	 * @param bool $force_refresh
	 * @return array|\WP_Error
	 */
	public function get_account_info( bool $force_refresh = false ): array|\WP_Error {
		if ( ! $force_refresh ) {
			$cached = get_transient( 'dvcf7_google_userinfo' );
			if ( false !== $cached && is_array( $cached ) ) {
				return $cached;
			}
		}

		$token = $this->get_valid_access_token();
		if ( is_wp_error( $token ) ) {
			return $token;
		}

		$this->http->set_access_token( $token );
		$response = $this->http->get( self::USERINFO_URL );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		if ( empty( $response['status'] ) || 200 !== $response['status'] ) {
			return new \WP_Error( 'userinfo_error', __( 'Failed to retrieve Google profile info.', 'drivevault-for-contact-form-7' ) );
		}

		$data = $response['body'];
		$info = array(
			'email'   => ! empty( $data['email'] ) ? sanitize_email( $data['email'] ) : '',
			'name'    => ! empty( $data['name'] ) ? sanitize_text_field( $data['name'] ) : '',
			'picture' => ! empty( $data['picture'] ) ? esc_url_raw( $data['picture'] ) : '',
			'hd'      => ! empty( $data['hd'] ) ? sanitize_text_field( $data['hd'] ) : '',
		);

		set_transient( 'dvcf7_google_userinfo', $info, 12 * HOUR_IN_SECONDS );

		return $info;
	}

	/**
	 * Check if currently connected and token is valid.
	 *
	 * @return bool
	 */
	public function is_connected(): bool {
		$tokens = $this->get_stored_tokens();
		return ! empty( $tokens['access_token'] ) && ! empty( $tokens['refresh_token'] );
	}

	/**
	 * Save tokens encrypted into WordPress options.
	 *
	 * @param array $tokens
	 */
	protected function save_tokens( array $tokens ): void {
		$this->cached_tokens = $tokens;
		$encrypted           = self::encrypt_payload( $tokens );
		update_option( DRIVEVAULT_CF7_OPTION_TOKENS, $encrypted );
	}

	/**
	 * Retrieve tokens from option and decrypt.
	 *
	 * @return array
	 */
	public function get_stored_tokens(): array {
		if ( null !== $this->cached_tokens ) {
			return $this->cached_tokens;
		}

		$raw = get_option( DRIVEVAULT_CF7_OPTION_TOKENS, array() );
		if ( empty( $raw ) ) {
			$this->cached_tokens = array();
			return array();
		}

		$decrypted = self::decrypt_payload( $raw );
		if ( is_array( $decrypted ) ) {
			$this->cached_tokens = $decrypted;
			return $decrypted;
		}

		$this->cached_tokens = array();
		return array();
	}

	/**
	 * Encrypt payload using AES-256-CBC and WordPress auth salts.
	 *
	 * @param mixed $data
	 * @return mixed
	 */
	public static function encrypt_payload( mixed $data ): mixed {
		if ( ! extension_loaded( 'openssl' ) || ! defined( 'AUTH_KEY' ) ) {
			return $data;
		}

		$json = wp_json_encode( $data );
		if ( false === $json ) {
			return $data;
		}

		$salt = defined( 'SECURE_AUTH_KEY' ) ? SECURE_AUTH_KEY : ( defined( 'AUTH_SALT' ) ? AUTH_SALT : 'dvcf7_salt' );
		$key  = hash( 'sha256', AUTH_KEY . $salt, true );
		$iv   = function_exists( 'random_bytes' ) ? random_bytes( 16 ) : openssl_random_pseudo_bytes( 16 );

		$ciphertext = openssl_encrypt( $json, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv );
		if ( false === $ciphertext ) {
			return $data;
		}

		return 'dvcf7_enc:' . base64_encode( $iv . $ciphertext );
	}

	/**
	 * Decrypt payload.
	 *
	 * @param mixed $data
	 * @return mixed
	 */
	public static function decrypt_payload( mixed $data ): mixed {
		if ( ! is_string( $data ) || ! str_starts_with( $data, 'dvcf7_enc:' ) ) {
			return $data;
		}

		if ( ! extension_loaded( 'openssl' ) || ! defined( 'AUTH_KEY' ) ) {
			return $data;
		}

		$raw = base64_decode( substr( $data, 10 ) );
		if ( ! is_string( $raw ) || strlen( $raw ) < 17 ) {
			return $data;
		}

		$iv         = substr( $raw, 0, 16 );
		$ciphertext = substr( $raw, 16 );

		$salt = defined( 'SECURE_AUTH_KEY' ) ? SECURE_AUTH_KEY : ( defined( 'AUTH_SALT' ) ? AUTH_SALT : 'dvcf7_salt' );
		$key  = hash( 'sha256', AUTH_KEY . $salt, true );

		$decrypted = openssl_decrypt( $ciphertext, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv );
		if ( false === $decrypted ) {
			return $data;
		}

		$parsed = json_decode( $decrypted, true );
		return null !== $parsed ? $parsed : $data;
	}
}
