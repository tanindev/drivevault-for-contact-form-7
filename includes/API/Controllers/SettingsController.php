<?php
namespace DriveVault\CF7\API\Controllers;

defined( 'ABSPATH' ) || exit;

/**
 * REST API Settings and System Status Controller.
 */
class SettingsController {

	/**
	 * Register REST routes.
	 *
	 * @param string $namespace
	 */
	public function register_routes( string $namespace ): void {
		register_rest_route(
			$namespace,
			'/settings',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_settings' ),
					'permission_callback' => array( $this, 'permissions_check' ),
				),
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'save_settings' ),
					'permission_callback' => array( $this, 'permissions_check' ),
				),
			)
		);

		register_rest_route(
			$namespace,
			'/status',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_system_status' ),
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
	 * Get current settings.
	 *
	 * @return \WP_REST_Response
	 */
	public function get_settings(): \WP_REST_Response {
		$settings = get_option( DRIVEVAULT_CF7_OPTION_SETTINGS, array() );

		return rest_ensure_response(
			array(
				'client_id'                  => ! empty( $settings['client_id'] ) ? $settings['client_id'] : '',
				'client_secret'              => ! empty( $settings['client_secret'] ) ? $settings['client_secret'] : '',
				'sync_trigger'               => ! empty( $settings['sync_trigger'] ) ? $settings['sync_trigger'] : 'mail_sent',
				'delete_local_uploads'       => ! empty( $settings['delete_local_uploads'] ),
				'anonymize_ip'               => ! empty( $settings['anonymize_ip'] ),
				'error_notification_email'   => ! empty( $settings['error_notification_email'] ) ? $settings['error_notification_email'] : get_option( 'admin_email' ),
				'enable_error_notifications' => isset( $settings['enable_error_notifications'] ) ? (bool) $settings['enable_error_notifications'] : true,
				'log_retention_days'         => ! empty( $settings['log_retention_days'] ) ? (int) $settings['log_retention_days'] : 60,
				'delete_data_on_uninstall'   => ! empty( $settings['delete_data_on_uninstall'] ),
			)
		);
	}

	/**
	 * Save settings.
	 *
	 * @param \WP_REST_Request $request
	 * @return \WP_REST_Response
	 */
	public function save_settings( \WP_REST_Request $request ): \WP_REST_Response {
		$params   = $request->get_json_params();
		$existing = get_option( DRIVEVAULT_CF7_OPTION_SETTINGS, array() );

		$updated = array(
			'client_id'                  => isset( $params['client_id'] ) ? trim( sanitize_text_field( $params['client_id'] ) ) : ( $existing['client_id'] ?? '' ),
			'client_secret'              => isset( $params['client_secret'] ) ? trim( sanitize_text_field( $params['client_secret'] ) ) : ( $existing['client_secret'] ?? '' ),
			'sync_trigger'               => isset( $params['sync_trigger'] ) && in_array( $params['sync_trigger'], array( 'mail_sent', 'submit' ), true ) ? $params['sync_trigger'] : ( $existing['sync_trigger'] ?? 'mail_sent' ),
			'delete_local_uploads'       => isset( $params['delete_local_uploads'] ) ? (bool) $params['delete_local_uploads'] : ( $existing['delete_local_uploads'] ?? false ),
			'anonymize_ip'               => isset( $params['anonymize_ip'] ) ? (bool) $params['anonymize_ip'] : ( $existing['anonymize_ip'] ?? false ),
			'error_notification_email'   => isset( $params['error_notification_email'] ) ? sanitize_email( $params['error_notification_email'] ) : ( $existing['error_notification_email'] ?? get_option( 'admin_email' ) ),
			'enable_error_notifications' => isset( $params['enable_error_notifications'] ) ? (bool) $params['enable_error_notifications'] : ( $existing['enable_error_notifications'] ?? true ),
			'log_retention_days'         => isset( $params['log_retention_days'] ) ? min( 365, max( 7, absint( $params['log_retention_days'] ) ) ) : ( $existing['log_retention_days'] ?? 60 ),
			'delete_data_on_uninstall'   => isset( $params['delete_data_on_uninstall'] ) ? (bool) $params['delete_data_on_uninstall'] : ( $existing['delete_data_on_uninstall'] ?? false ),
		);

		update_option( DRIVEVAULT_CF7_OPTION_SETTINGS, $updated );

		return rest_ensure_response(
			array(
				'success'  => true,
				'settings' => $updated,
				'message'  => __( 'Settings saved successfully.', 'drivevault-for-contact-form-7' ),
			)
		);
	}

	/**
	 * Get server environment diagnostics.
	 *
	 * @return \WP_REST_Response
	 */
	public function get_system_status(): \WP_REST_Response {
		global $wp_version;

		$cf7_version = defined( 'WPCF7_VERSION' ) ? WPCF7_VERSION : ( class_exists( 'WPCF7_ContactForm' ) ? 'Active' : 'Not Active' );

		return rest_ensure_response(
			array(
				'plugin_version' => DRIVEVAULT_CF7_VERSION,
				'php_version'    => PHP_VERSION,
				'wp_version'     => $wp_version,
				'cf7_version'    => $cf7_version,
				'curl_enabled'   => function_exists( 'curl_version' ),
				'openssl'        => extension_loaded( 'openssl' ) ? OPENSSL_VERSION_TEXT : 'Disabled',
				'memory_limit'   => ini_get( 'memory_limit' ),
				'max_upload'     => size_format( wp_max_upload_size() ),
				'server_time'    => current_time( 'mysql' ),
			)
		);
	}
}
