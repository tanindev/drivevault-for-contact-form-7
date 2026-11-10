<?php
namespace DriveVault\CF7\Google;

defined( 'ABSPATH' ) || exit;

/**
 * Google Sheets API v4 Service.
 */
class SheetsService {

	const SHEETS_BASE = 'https://sheets.googleapis.com/v4/spreadsheets';
	const DRIVE_BASE  = 'https://www.googleapis.com/drive/v3/files';

	/**
	 * HTTP Client.
	 */
	protected HttpClient $http;

	/**
	 * OAuth Manager.
	 */
	protected OAuthManager $oauth;

	/**
	 * Constructor.
	 *
	 * @param HttpClient   $http
	 * @param OAuthManager $oauth
	 */
	public function __construct( HttpClient $http, OAuthManager $oauth ) {
		$this->http  = $http;
		$this->oauth = $oauth;
	}

	/**
	 * Prepare authenticated HTTP client.
	 *
	 * @return true|\WP_Error
	 */
	protected function authenticate(): true|\WP_Error {
		$token = $this->oauth->get_valid_access_token();
		if ( is_wp_error( $token ) ) {
			return $token;
		}

		$this->http->set_access_token( $token );
		return true;
	}

	/**
	 * List user spreadsheets from Google Drive.
	 *
	 * @param int    $max_results
	 * @param string $search
	 * @param bool   $force_refresh
	 * @return array|\WP_Error
	 */
	public function list_spreadsheets( int $max_results = 50, string $search = '', bool $force_refresh = false ): array|\WP_Error {
		$auth = $this->authenticate();
		if ( is_wp_error( $auth ) ) {
			return $auth;
		}

		$cache_key = 'dvcf7_sheets_' . md5( $search . '_' . $max_results );
		if ( ! $force_refresh ) {
			$cached = get_transient( $cache_key );
			if ( false !== $cached && is_array( $cached ) ) {
				return $cached;
			}
		}

		$query_parts = array(
			"mimeType = 'application/vnd.google-apps.spreadsheet'",
			'trashed = false',
		);

		if ( ! empty( $search ) ) {
			$query_parts[] = sprintf( "name contains '%s'", addslashes( sanitize_text_field( $search ) ) );
		}

		$params = array(
			'q'        => implode( ' and ', $query_parts ),
			'fields'   => 'files(id, name, modifiedTime, webViewLink)',
			'pageSize' => min( 100, max( 10, absint( $max_results ) ) ),
			'orderBy'  => 'modifiedTime desc',
		);

		$response = $this->http->get( self::DRIVE_BASE, $params );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		if ( empty( $response['status'] ) || $response['status'] < 200 || $response['status'] >= 300 ) {
			$msg = ! empty( $response['body']['error']['message'] ) ? $response['body']['error']['message'] : __( 'Failed to list spreadsheets.', 'drivevault-for-contact-form-7' );
			return new \WP_Error( 'sheets_list_error', $msg );
		}

		$files = ! empty( $response['body']['files'] ) ? $response['body']['files'] : array();
		set_transient( $cache_key, $files, 5 * MINUTE_IN_SECONDS );

		return $files;
	}

	/**
	 * Create a new spreadsheet in Google Sheets.
	 *
	 * @param string $title
	 * @return array|\WP_Error
	 */
	public function create_spreadsheet( string $title ): array|\WP_Error {
		$auth = $this->authenticate();
		if ( is_wp_error( $auth ) ) {
			return $auth;
		}

		$payload = array(
			'properties' => array(
				'title' => sanitize_text_field( $title ),
			),
			'sheets'     => array(
				array(
					'properties' => array(
						'title'          => 'Sheet1',
						'gridProperties' => array(
							'rowCount'    => 1000,
							'columnCount' => 26,
						),
					),
				),
			),
		);

		$response = $this->http->post( self::SHEETS_BASE, $payload );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		if ( empty( $response['status'] ) || $response['status'] < 200 || $response['status'] >= 300 ) {
			$msg = ! empty( $response['body']['error']['message'] ) ? $response['body']['error']['message'] : __( 'Failed to create spreadsheet.', 'drivevault-for-contact-form-7' );
			return new \WP_Error( 'sheets_create_error', $msg );
		}

		delete_transient( 'dvcf7_sheets_' );

		$data = $response['body'];
		return array(
			'id'          => ! empty( $data['spreadsheetId'] ) ? $data['spreadsheetId'] : '',
			'name'        => ! empty( $data['properties']['title'] ) ? $data['properties']['title'] : $title,
			'webViewLink' => ! empty( $data['spreadsheetUrl'] ) ? $data['spreadsheetUrl'] : '',
		);
	}

	/**
	 * Get spreadsheet details and list of worksheet tabs.
	 *
	 * @param string $spreadsheet_id
	 * @return array|\WP_Error
	 */
	public function get_spreadsheet( string $spreadsheet_id ): array|\WP_Error {
		$auth = $this->authenticate();
		if ( is_wp_error( $auth ) ) {
			return $auth;
		}

		$url      = self::SHEETS_BASE . '/' . rawurlencode( $spreadsheet_id );
		$response = $this->http->get( $url, array( 'fields' => 'spreadsheetId,properties.title,sheets.properties' ) );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		if ( empty( $response['status'] ) || $response['status'] < 200 || $response['status'] >= 300 ) {
			$msg = ! empty( $response['body']['error']['message'] ) ? $response['body']['error']['message'] : __( 'Failed to fetch spreadsheet details.', 'drivevault-for-contact-form-7' );
			return new \WP_Error( 'sheets_fetch_error', $msg );
		}

		$data  = $response['body'];
		$tabs  = array();
		if ( ! empty( $data['sheets'] ) && is_array( $data['sheets'] ) ) {
			foreach ( $data['sheets'] as $sheet ) {
				if ( ! empty( $sheet['properties']['title'] ) ) {
					$tabs[] = array(
						'sheetId' => $sheet['properties']['sheetId'] ?? 0,
						'title'   => $sheet['properties']['title'],
						'index'   => $sheet['properties']['index'] ?? 0,
					);
				}
			}
		}

		return array(
			'id'    => $data['spreadsheetId'] ?? $spreadsheet_id,
			'title' => $data['properties']['title'] ?? '',
			'tabs'  => $tabs,
		);
	}

	/**
	 * Fetch existing column headers from Row 1.
	 *
	 * @param string $spreadsheet_id
	 * @param string $tab_name
	 * @return array|\WP_Error
	 */
	public function get_headers( string $spreadsheet_id, string $tab_name = 'Sheet1' ): array|\WP_Error {
		$auth = $this->authenticate();
		if ( is_wp_error( $auth ) ) {
			return $auth;
		}

		$range    = rawurlencode( $tab_name ) . '!1:1';
		$url      = self::SHEETS_BASE . '/' . rawurlencode( $spreadsheet_id ) . '/values/' . $range;
		$response = $this->http->get( $url );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		if ( empty( $response['status'] ) || $response['status'] < 200 || $response['status'] >= 300 ) {
			return new \WP_Error( 'headers_fetch_error', __( 'Failed to fetch row headers.', 'drivevault-for-contact-form-7' ) );
		}

		$values = ! empty( $response['body']['values'][0] ) ? $response['body']['values'][0] : array();
		return $values;
	}

	/**
	 * Setup or overwrite headers in Row 1 and format nicely.
	 *
	 * @param string $spreadsheet_id
	 * @param string $tab_name
	 * @param array  $headers Array of string headers.
	 * @return bool|\WP_Error
	 */
	public function create_or_update_headers( string $spreadsheet_id, string $tab_name, array $headers ): bool|\WP_Error {
		if ( empty( $headers ) || ! is_array( $headers ) ) {
			return false;
		}

		$auth = $this->authenticate();
		if ( is_wp_error( $auth ) ) {
			return $auth;
		}

		$tab_name = ! empty( $tab_name ) ? $tab_name : 'Sheet1';
		$range    = $tab_name . '!A1';

		$url = self::SHEETS_BASE . '/' . rawurlencode( $spreadsheet_id ) . '/values/' . rawurlencode( $range ) . '?valueInputOption=USER_ENTERED';

		$payload = array(
			'range'          => $range,
			'majorDimension' => 'ROWS',
			'values'         => array( array_values( $headers ) ),
		);

		$response = $this->http->put( $url, $payload );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		if ( empty( $response['status'] ) || $response['status'] < 200 || $response['status'] >= 300 ) {
			return new \WP_Error( 'headers_update_error', __( 'Could not write sheet headers.', 'drivevault-for-contact-form-7' ) );
		}

		return true;
	}

	/**
	 * Append a new submission row to the designated sheet/tab.
	 *
	 * @param string $spreadsheet_id
	 * @param string $tab_name
	 * @param array  $row_values Ordered array of string values.
	 * @return array|bool|\WP_Error
	 */
	public function append_row( string $spreadsheet_id, string $tab_name, array $row_values ): array|bool|\WP_Error {
		$auth = $this->authenticate();
		if ( is_wp_error( $auth ) ) {
			return $auth;
		}

		$tab_name = ! empty( $tab_name ) ? $tab_name : 'Sheet1';
		$range    = rawurlencode( $tab_name ) . '!A:A';

		$url = self::SHEETS_BASE . '/' . rawurlencode( $spreadsheet_id ) . '/values/' . $range . ':append?valueInputOption=USER_ENTERED&insertDataOption=INSERT_ROWS';

		$payload = array(
			'range'          => $tab_name . '!A:A',
			'majorDimension' => 'ROWS',
			'values'         => array( array_values( $row_values ) ),
		);

		$response = $this->http->post( $url, $payload );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		if ( empty( $response['status'] ) || $response['status'] < 200 || $response['status'] >= 300 ) {
			$msg = ! empty( $response['body']['error']['message'] ) ? $response['body']['error']['message'] : __( 'Failed to append row to Google Sheet.', 'drivevault-for-contact-form-7' );
			return new \WP_Error( 'append_row_error', $msg );
		}

		return ! empty( $response['body']['updates'] ) ? $response['body']['updates'] : true;
	}
}
