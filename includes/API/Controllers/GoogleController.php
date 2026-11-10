<?php
namespace DriveVault\CF7\API\Controllers;

defined( 'ABSPATH' ) || exit;

use DriveVault\CF7\Google\SheetsService;
use DriveVault\CF7\Google\DriveService;

/**
 * REST API Google Services Controller.
 */
class GoogleController {

	/**
	 * Sheets Service.
	 */
	protected SheetsService $sheets;

	/**
	 * Drive Service.
	 */
	protected DriveService $drive;

	/**
	 * Constructor.
	 *
	 * @param SheetsService $sheets
	 * @param DriveService  $drive
	 */
	public function __construct( SheetsService $sheets, DriveService $drive ) {
		$this->sheets = $sheets;
		$this->drive  = $drive;
	}

	/**
	 * Register REST routes.
	 *
	 * @param string $namespace
	 */
	public function register_routes( string $namespace ): void {
		// Sheets routes.
		register_rest_route(
			$namespace,
			'/google/sheets',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'list_sheets' ),
					'permission_callback' => array( $this, 'permissions_check' ),
				),
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_sheet' ),
					'permission_callback' => array( $this, 'permissions_check' ),
				),
			)
		);

		register_rest_route(
			$namespace,
			'/google/sheets/tabs',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_sheet_tabs' ),
				'permission_callback' => array( $this, 'permissions_check' ),
			)
		);

		register_rest_route(
			$namespace,
			'/google/sheets/headers',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_sheet_headers' ),
				'permission_callback' => array( $this, 'permissions_check' ),
			)
		);

		// Drive folders routes.
		register_rest_route(
			$namespace,
			'/google/drive/folders',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'list_folders' ),
					'permission_callback' => array( $this, 'permissions_check' ),
				),
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_folder' ),
					'permission_callback' => array( $this, 'permissions_check' ),
				),
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
	 * List spreadsheets.
	 *
	 * @param \WP_REST_Request $request
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function list_sheets( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$search  = sanitize_text_field( $request->get_param( 'search' ) ?: '' );
		$refresh = (bool) $request->get_param( 'refresh' );

		$sheets = $this->sheets->list_spreadsheets( 60, $search, $refresh );
		if ( is_wp_error( $sheets ) ) {
			return $sheets;
		}

		return rest_ensure_response( $sheets );
	}

	/**
	 * Create new spreadsheet.
	 *
	 * @param \WP_REST_Request $request
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function create_sheet( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$title = sanitize_text_field( $request->get_param( 'title' ) ?: '' );
		if ( empty( $title ) ) {
			return new \WP_Error( 'missing_title', __( 'Spreadsheet title is required.', 'drivevault-for-contact-form-7' ) );
		}

		$sheet = $this->sheets->create_spreadsheet( $title );
		if ( is_wp_error( $sheet ) ) {
			return $sheet;
		}

		return rest_ensure_response( $sheet );
	}

	/**
	 * Get spreadsheet details and tabs.
	 *
	 * @param \WP_REST_Request $request
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function get_sheet_tabs( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$sheet_id = sanitize_text_field( $request->get_param( 'sheet_id' ) ?: '' );
		if ( empty( $sheet_id ) ) {
			return new \WP_Error( 'missing_sheet_id', __( 'Sheet ID is required.', 'drivevault-for-contact-form-7' ) );
		}

		$details = $this->sheets->get_spreadsheet( $sheet_id );
		if ( is_wp_error( $details ) ) {
			return $details;
		}

		return rest_ensure_response( $details );
	}

	/**
	 * Get sheet row 1 headers.
	 *
	 * @param \WP_REST_Request $request
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function get_sheet_headers( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$sheet_id = sanitize_text_field( $request->get_param( 'sheet_id' ) ?: '' );
		$tab_name = sanitize_text_field( $request->get_param( 'tab_name' ) ?: 'Sheet1' );

		if ( empty( $sheet_id ) ) {
			return new \WP_Error( 'missing_sheet_id', __( 'Sheet ID is required.', 'drivevault-for-contact-form-7' ) );
		}

		$headers = $this->sheets->get_headers( $sheet_id, $tab_name );
		if ( is_wp_error( $headers ) ) {
			return $headers;
		}

		return rest_ensure_response( $headers );
	}

	/**
	 * List Google Drive folders.
	 *
	 * @param \WP_REST_Request $request
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function list_folders( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$parent_id = sanitize_text_field( $request->get_param( 'parent_id' ) ?: 'root' );
		$refresh   = (bool) $request->get_param( 'refresh' );

		$folders = $this->drive->list_folders( $parent_id, $refresh );
		if ( is_wp_error( $folders ) ) {
			return $folders;
		}

		return rest_ensure_response( $folders );
	}

	/**
	 * Create folder in Google Drive.
	 *
	 * @param \WP_REST_Request $request
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function create_folder( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$name      = sanitize_text_field( $request->get_param( 'name' ) ?: '' );
		$parent_id = sanitize_text_field( $request->get_param( 'parent_id' ) ?: 'root' );

		if ( empty( $name ) ) {
			return new \WP_Error( 'missing_name', __( 'Folder name is required.', 'drivevault-for-contact-form-7' ) );
		}

		$folder = $this->drive->create_folder( $name, $parent_id );
		if ( is_wp_error( $folder ) ) {
			return $folder;
		}

		return rest_ensure_response( $folder );
	}
}
