<?php
namespace DriveVault\CF7\API\Controllers;

defined( 'ABSPATH' ) || exit;

use DriveVault\CF7\Google\OAuthManager;
use DriveVault\CF7\Google\HttpClient;

/**
 * REST API Auth Controller.
 */
class AuthController {

	/**
	 * OAuth Manager.
	 */
	protected OAuthManager $oauth;

	/**
	 * Constructor.
	 *
	 * @param OAuthManager $oauth
	 */
	public function __construct( OAuthManager $oauth ) {
		$this->oauth = $oauth;
	}

	/**
	 * Register REST routes.
	 *
	 * @param string $namespace
	 */
	public function register_routes( string $namespace ): void {
		register_rest_route(
			$namespace,
			'/auth/status',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_status' ),
				'permission_callback' => array( $this, 'permissions_check' ),
			)
		);

		register_rest_route(
			$namespace,
			'/auth/url',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_auth_url' ),
				'permission_callback' => array( $this, 'permissions_check' ),
			)
		);

		register_rest_route(
			$namespace,
			'/auth/disconnect',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'disconnect' ),
				'permission_callback' => array( $this, 'permissions_check' ),
			)
		);

		register_rest_route(
			$namespace,
			'/auth/test',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'test_connection' ),
				'permission_callback' => array( $this, 'permissions_check' ),
			)
		);
	}

	/**
	 * Permissions check callback.
	 *
	 * @return bool
	 */
	public function permissions_check(): bool {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Get current Google authentication status.
	 *
	 * @return \WP_REST_Response
	 */
	public function get_status(): \WP_REST_Response {
		$is_connected = $this->oauth->is_connected();
		$account_info = null;

		if ( $is_connected ) {
			$info = $this->oauth->get_account_info();
			if ( ! is_wp_error( $info ) ) {
				$account_info = $info;
			}
		}

		return rest_ensure_response(
			array(
				'isConnected' => $is_connected,
				'account'     => $account_info,
				'redirectUri' => $this->oauth->get_redirect_uri(),
			)
		);
	}

	/**
	 * Get Google Auth URL.
	 *
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function get_auth_url(): \WP_REST_Response|\WP_Error {
		$url = $this->oauth->get_auth_url();
		if ( is_wp_error( $url ) ) {
			return $url;
		}

		return rest_ensure_response( array( 'url' => $url ) );
	}

	/**
	 * Disconnect Google account.
	 *
	 * @return \WP_REST_Response
	 */
	public function disconnect(): \WP_REST_Response {
		$this->oauth->disconnect();
		return rest_ensure_response(
			array(
				'success' => true,
				'message' => __( 'Disconnected successfully.', 'drivevault-for-contact-form-7' ),
			)
		);
	}

	/**
	 * Test Google connection.
	 *
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function test_connection(): \WP_REST_Response|\WP_Error {
		$info = $this->oauth->get_account_info( true );
		if ( is_wp_error( $info ) ) {
			return $info;
		}

		return rest_ensure_response(
			array(
				'success' => true,
				'account' => $info,
				'message' => __( 'Connection verified successfully.', 'drivevault-for-contact-form-7' ),
			)
		);
	}
}
