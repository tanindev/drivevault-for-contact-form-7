<?php
namespace DriveVault\CF7\API\Controllers;

defined( 'ABSPATH' ) || exit;

use DriveVault\CF7\Database\LogRepository;
use DriveVault\CF7\Database\MappingRepository;
use DriveVault\CF7\Google\SheetsService;

/**
 * REST API Logs Controller.
 */
class LogsController {

	/**
	 * Sheets Service.
	 */
	protected SheetsService $sheets;

	/**
	 * Constructor.
	 *
	 * @param SheetsService $sheets
	 */
	public function __construct( SheetsService $sheets ) {
		$this->sheets = $sheets;
	}

	/**
	 * Register REST routes.
	 *
	 * @param string $namespace
	 */
	public function register_routes( string $namespace ): void {
		register_rest_route(
			$namespace,
			'/logs',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_logs' ),
					'permission_callback' => array( $this, 'permissions_check' ),
				),
				array(
					'methods'             => \WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'clear_logs' ),
					'permission_callback' => array( $this, 'permissions_check' ),
				),
			)
		);

		register_rest_route(
			$namespace,
			'/logs/stats',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_stats' ),
				'permission_callback' => array( $this, 'permissions_check' ),
			)
		);

		register_rest_route(
			$namespace,
			'/logs/(?P<id>\d+)',
			array(
				'methods'             => \WP_REST_Server::DELETABLE,
				'callback'            => array( $this, 'delete_log' ),
				'permission_callback' => array( $this, 'permissions_check' ),
			)
		);

		register_rest_route(
			$namespace,
			'/logs/(?P<id>\d+)/retry',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'retry_submission' ),
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
	 * Get paginated logs.
	 *
	 * @param \WP_REST_Request $request
	 * @return \WP_REST_Response
	 */
	public function get_logs( \WP_REST_Request $request ): \WP_REST_Response {
		$args = array(
			'page'     => absint( $request->get_param( 'page' ) ?: 1 ),
			'per_page' => absint( $request->get_param( 'per_page' ) ?: 20 ),
			'result'   => sanitize_text_field( $request->get_param( 'result' ) ?: '' ),
			'form_id'  => absint( $request->get_param( 'form_id' ) ?: 0 ),
			'search'   => sanitize_text_field( $request->get_param( 'search' ) ?: '' ),
		);

		$data = LogRepository::get_logs( $args );
		return rest_ensure_response( $data );
	}

	/**
	 * Get aggregated stats for the dashboard.
	 *
	 * @return \WP_REST_Response
	 */
	public function get_stats(): \WP_REST_Response {
		$stats = LogRepository::get_stats();
		return rest_ensure_response( $stats );
	}

	/**
	 * Delete single log entry.
	 *
	 * @param \WP_REST_Request $request
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function delete_log( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$id      = absint( $request->get_param( 'id' ) );
		$deleted = LogRepository::delete_log( $id );

		if ( ! $deleted ) {
			return new \WP_Error( 'delete_failed', __( 'Could not delete log item.', 'drivevault-for-contact-form-7' ) );
		}

		return rest_ensure_response(
			array(
				'success' => true,
				'message' => __( 'Log item removed.', 'drivevault-for-contact-form-7' ),
			)
		);
	}

	/**
	 * Clear all submission logs.
	 *
	 * @return \WP_REST_Response
	 */
	public function clear_logs(): \WP_REST_Response {
		LogRepository::clear_all();
		return rest_ensure_response(
			array(
				'success' => true,
				'message' => __( 'All submission logs have been cleared.', 'drivevault-for-contact-form-7' ),
			)
		);
	}

	/**
	 * Retry a failed submission.
	 *
	 * @param \WP_REST_Request $request
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function retry_submission( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$id  = absint( $request->get_param( 'id' ) );
		$log = LogRepository::find_by_id( $id );

		if ( ! $log ) {
			return new \WP_Error( 'not_found', __( 'Log item not found.', 'drivevault-for-contact-form-7' ) );
		}

		if ( empty( $log['mapped_data'] ) ) {
			return new \WP_Error( 'empty_data', __( 'No mapped payload found in this log.', 'drivevault-for-contact-form-7' ) );
		}

		$mapping = MappingRepository::find_by_form( $log['form_id'] );
		if ( ! $mapping || empty( $mapping['sheet_id'] ) ) {
			return new \WP_Error( 'missing_mapping', __( 'No active sheet mapping found for this form.', 'drivevault-for-contact-form-7' ) );
		}

		$row_data = array_values( (array) $log['mapped_data'] );
		$result   = $this->sheets->append_row( $mapping['sheet_id'], $mapping['tab_name'], $row_data );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		// Update log row status to success.
		global $wpdb;
		$table = LogRepository::get_table_name();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery
		$wpdb->update(
			$table,
			array(
				'result'        => 'success',
				'error_message' => null,
			),
			array( 'id' => $id ),
			array( '%s', '%s' ),
			array( '%d' )
		);

		return rest_ensure_response(
			array(
				'success' => true,
				'message' => __( 'Submission successfully re-synced to Google Sheets!', 'drivevault-for-contact-form-7' ),
			)
		);
	}
}
