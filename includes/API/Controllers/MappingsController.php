<?php
namespace DriveVault\CF7\API\Controllers;

defined( 'ABSPATH' ) || exit;

use DriveVault\CF7\Database\MappingRepository;
use DriveVault\CF7\Google\SheetsService;

/**
 * REST API Form Mappings Controller.
 */
class MappingsController {

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
			'/mappings',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_mappings' ),
					'permission_callback' => array( $this, 'permissions_check' ),
				),
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'save_mapping' ),
					'permission_callback' => array( $this, 'permissions_check' ),
				),
			)
		);

		register_rest_route(
			$namespace,
			'/mappings/(?P<form_id>\d+)',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_mapping_by_form' ),
				'permission_callback' => array( $this, 'permissions_check' ),
			)
		);

		register_rest_route(
			$namespace,
			'/mappings/(?P<id>\d+)',
			array(
				'methods'             => \WP_REST_Server::DELETABLE,
				'callback'            => array( $this, 'delete_mapping' ),
				'permission_callback' => array( $this, 'permissions_check' ),
			)
		);

		register_rest_route(
			$namespace,
			'/mappings/(?P<id>\d+)/toggle',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'toggle_mapping' ),
				'permission_callback' => array( $this, 'permissions_check' ),
			)
		);

		register_rest_route(
			$namespace,
			'/mappings/test',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'send_test_row' ),
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
	 * Get all mappings.
	 *
	 * @return \WP_REST_Response
	 */
	public function get_mappings(): \WP_REST_Response {
		$mappings = MappingRepository::get_all();
		return rest_ensure_response( $mappings );
	}

	/**
	 * Get mapping by form ID.
	 *
	 * @param \WP_REST_Request $request
	 * @return \WP_REST_Response
	 */
	public function get_mapping_by_form( \WP_REST_Request $request ): \WP_REST_Response {
		$form_id = absint( $request->get_param( 'form_id' ) );
		$mapping = MappingRepository::find_by_form( $form_id );

		return rest_ensure_response( $mapping ?: array( 'form_id' => $form_id ) );
	}

	/**
	 * Save or update form mapping.
	 *
	 * @param \WP_REST_Request $request
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function save_mapping( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$body = $request->get_json_params();
		if ( empty( $body['form_id'] ) ) {
			return new \WP_Error( 'missing_form_id', __( 'Form ID is required.', 'drivevault-for-contact-form-7' ) );
		}

		$result = MappingRepository::save( $body );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		// If column_map is provided and auto-create headers is requested, setup headers on the sheet.
		if ( ! empty( $body['auto_create_headers'] ) && ! empty( $body['sheet_id'] ) && ! empty( $body['column_map'] ) ) {
			$headers = array_values( (array) $body['column_map'] );
			$tab     = ! empty( $body['tab_name'] ) ? $body['tab_name'] : 'Sheet1';
			$this->sheets->create_or_update_headers( $body['sheet_id'], $tab, $headers );
		}

		$saved = MappingRepository::find_by_id( $result );
		return rest_ensure_response(
			array(
				'success' => true,
				'mapping' => $saved,
				'message' => __( 'Mapping saved successfully.', 'drivevault-for-contact-form-7' ),
			)
		);
	}

	/**
	 * Delete a mapping.
	 *
	 * @param \WP_REST_Request $request
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function delete_mapping( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$id      = absint( $request->get_param( 'id' ) );
		$deleted = MappingRepository::delete( $id );

		if ( ! $deleted ) {
			return new \WP_Error( 'delete_failed', __( 'Could not delete mapping.', 'drivevault-for-contact-form-7' ) );
		}

		return rest_ensure_response(
			array(
				'success' => true,
				'message' => __( 'Mapping deleted.', 'drivevault-for-contact-form-7' ),
			)
		);
	}

	/**
	 * Toggle active state.
	 *
	 * @param \WP_REST_Request $request
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function toggle_mapping( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$id     = absint( $request->get_param( 'id' ) );
		$status = MappingRepository::toggle_status( $id );

		if ( false === $status ) {
			return new \WP_Error( 'toggle_failed', __( 'Could not toggle mapping status.', 'drivevault-for-contact-form-7' ) );
		}

		return rest_ensure_response(
			array(
				'success'   => true,
				'is_active' => (bool) $status,
				'message'   => $status ? __( 'Feed activated.', 'drivevault-for-contact-form-7' ) : __( 'Feed paused.', 'drivevault-for-contact-form-7' ),
			)
		);
	}

	/**
	 * Send test row to sheet.
	 *
	 * @param \WP_REST_Request $request
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function send_test_row( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$body = $request->get_json_params();
		if ( empty( $body['sheet_id'] ) || empty( $body['column_map'] ) ) {
			return new \WP_Error( 'missing_data', __( 'Sheet ID and Column Mapping are required to send a test row.', 'drivevault-for-contact-form-7' ) );
		}

		$tab_name   = ! empty( $body['tab_name'] ) ? $body['tab_name'] : 'Sheet1';
		$column_map = (array) $body['column_map'];

		$test_row = array();
		foreach ( $column_map as $field_key => $column_name ) {
			$test_row[] = sprintf( '[Test] %s: Value', $column_name );
		}

		// Ensure headers exist.
		$existing = $this->sheets->get_headers( $body['sheet_id'], $tab_name );
		if ( is_wp_error( $existing ) || empty( $existing ) ) {
			$this->sheets->create_or_update_headers( $body['sheet_id'], $tab_name, array_values( $column_map ) );
		}

		$result = $this->sheets->append_row( $body['sheet_id'], $tab_name, $test_row );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return rest_ensure_response(
			array(
				'success' => true,
				'message' => __( 'Test row appended successfully to Google Sheet!', 'drivevault-for-contact-form-7' ),
			)
		);
	}
}
