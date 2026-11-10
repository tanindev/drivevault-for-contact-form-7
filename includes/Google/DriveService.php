<?php
namespace DriveVault\CF7\Google;

defined( 'ABSPATH' ) || exit;

/**
 * Google Drive API v3 Service for File Uploads and Folder Management.
 */
class DriveService {

	const DRIVE_FILES_BASE  = 'https://www.googleapis.com/drive/v3/files';
	const DRIVE_UPLOAD_BASE = 'https://www.googleapis.com/upload/drive/v3/files?uploadType=multipart';

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
	 * List folders from Google Drive.
	 *
	 * @param string $parent_id
	 * @param bool   $force_refresh
	 * @return array|\WP_Error
	 */
	public function list_folders( string $parent_id = 'root', bool $force_refresh = false ): array|\WP_Error {
		$auth = $this->authenticate();
		if ( is_wp_error( $auth ) ) {
			return $auth;
		}

		$parent_id = ! empty( $parent_id ) ? sanitize_text_field( $parent_id ) : 'root';
		$cache_key = 'dvcf7_folders_' . md5( $parent_id );

		if ( ! $force_refresh ) {
			$cached = get_transient( $cache_key );
			if ( false !== $cached && is_array( $cached ) ) {
				return $cached;
			}
		}

		$query = sprintf(
			"mimeType = 'application/vnd.google-apps.folder' and trashed = false and '%s' in parents",
			addslashes( $parent_id )
		);

		$params = array(
			'q'        => $query,
			'fields'   => 'files(id, name, modifiedTime)',
			'pageSize' => 100,
			'orderBy'  => 'name asc',
		);

		$response = $this->http->get( self::DRIVE_FILES_BASE, $params );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		if ( empty( $response['status'] ) || $response['status'] < 200 || $response['status'] >= 300 ) {
			$msg = ! empty( $response['body']['error']['message'] ) ? $response['body']['error']['message'] : __( 'Failed to list folders.', 'drivevault-for-contact-form-7' );
			return new \WP_Error( 'folders_list_error', $msg );
		}

		$folders = ! empty( $response['body']['files'] ) ? $response['body']['files'] : array();
		set_transient( $cache_key, $folders, 10 * MINUTE_IN_SECONDS );

		return $folders;
	}

	/**
	 * Create a new folder in Google Drive.
	 *
	 * @param string $folder_name
	 * @param string $parent_id
	 * @return array|\WP_Error
	 */
	public function create_folder( string $folder_name, string $parent_id = 'root' ): array|\WP_Error {
		$auth = $this->authenticate();
		if ( is_wp_error( $auth ) ) {
			return $auth;
		}

		$parent_id = ! empty( $parent_id ) ? sanitize_text_field( $parent_id ) : 'root';
		$payload   = array(
			'name'     => sanitize_text_field( $folder_name ),
			'mimeType' => 'application/vnd.google-apps.folder',
		);

		if ( 'root' !== $parent_id ) {
			$payload['parents'] = array( $parent_id );
		}

		$response = $this->http->post( self::DRIVE_FILES_BASE, $payload );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		if ( empty( $response['status'] ) || $response['status'] < 200 || $response['status'] >= 300 ) {
			$msg = ! empty( $response['body']['error']['message'] ) ? $response['body']['error']['message'] : __( 'Failed to create folder.', 'drivevault-for-contact-form-7' );
			return new \WP_Error( 'folder_create_error', $msg );
		}

		delete_transient( 'dvcf7_folders_' . md5( $parent_id ) );

		$data = $response['body'];
		return array(
			'id'   => $data['id'] ?? '',
			'name' => $data['name'] ?? $folder_name,
		);
	}

	/**
	 * Upload a local file to Google Drive using multipart upload.
	 *
	 * @param string $file_path Absolute path to local file.
	 * @param string $file_name Target file name in Drive.
	 * @param string $mime_type MIME type.
	 * @param string $parent_folder_id Parent folder ID in Drive.
	 * @return array|\WP_Error
	 */
	public function upload_file( string $file_path, string $file_name, string $mime_type = '', string $parent_folder_id = '' ): array|\WP_Error {
		if ( ! file_exists( $file_path ) || ! is_readable( $file_path ) ) {
			/* translators: %s: Local file path */
			return new \WP_Error( 'file_not_found', sprintf( __( 'Local file not found or unreadable: %s', 'drivevault-for-contact-form-7' ), $file_path ) );
		}

		$auth = $this->authenticate();
		if ( is_wp_error( $auth ) ) {
			return $auth;
		}

		if ( empty( $mime_type ) ) {
			$wp_filetype = wp_check_filetype( $file_name );
			if ( ! empty( $wp_filetype['type'] ) ) {
				$mime_type = $wp_filetype['type'];
			} elseif ( function_exists( 'finfo_open' ) ) {
				$finfo     = finfo_open( FILEINFO_MIME_TYPE );
				$mime_type = $finfo ? finfo_file( $finfo, $file_path ) : 'application/octet-stream';
				if ( $finfo ) {
					finfo_close( $finfo );
				}
			} elseif ( function_exists( 'mime_content_type' ) ) {
				$mime_type = mime_content_type( $file_path );
			} else {
				$mime_type = 'application/octet-stream';
			}
		}

		global $wp_filesystem;
		if ( empty( $wp_filesystem ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
			WP_Filesystem();
		}

		$file_content = $wp_filesystem ? $wp_filesystem->get_contents( $file_path ) : false;
		if ( false === $file_content && function_exists( 'file_get_contents' ) ) {
			$file_content = file_get_contents( $file_path );
		}
		if ( false === $file_content ) {
			return new \WP_Error( 'file_read_error', __( 'Could not read local file contents.', 'drivevault-for-contact-form-7' ) );
		}

		$boundary = '-------' . md5( microtime() );

		// Metadata part.
		$metadata = array(
			'name' => sanitize_file_name( $file_name ),
		);
		if ( ! empty( $parent_folder_id ) && 'root' !== $parent_folder_id ) {
			$metadata['parents'] = array( sanitize_text_field( $parent_folder_id ) );
		}

		// Multipart payload.
		$body  = "--{$boundary}\r\n";
		$body .= "Content-Type: application/json; charset=UTF-8\r\n\r\n";
		$body .= wp_json_encode( $metadata ) . "\r\n";
		$body .= "--{$boundary}\r\n";
		$body .= "Content-Type: {$mime_type}\r\n\r\n";
		$body .= $file_content . "\r\n";
		$body .= "--{$boundary}--";

		$headers = array(
			'Content-Type' => "multipart/related; boundary={$boundary}",
		);

		$token = $this->oauth->get_valid_access_token();
		$args  = array(
			'method'  => 'POST',
			'headers' => array_merge(
				$headers,
				array(
					'Authorization' => 'Bearer ' . $token,
					'Accept'        => 'application/json',
				)
			),
			'body'    => $body,
			'timeout' => 60,
		);

		$response = wp_remote_request( self::DRIVE_UPLOAD_BASE, $args );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$data = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( $code < 200 || $code >= 300 ) {
			/* translators: %d: HTTP response status code */
			$msg = ! empty( $data['error']['message'] ) ? $data['error']['message'] : sprintf( __( 'Upload failed with status %d', 'drivevault-for-contact-form-7' ), $code );
			return new \WP_Error( 'upload_failed', $msg );
		}

		$file_id = $data['id'] ?? '';
		if ( empty( $file_id ) ) {
			return new \WP_Error( 'invalid_upload_response', __( 'Drive upload succeeded but no file ID was returned.', 'drivevault-for-contact-form-7' ) );
		}

		// Make file readable by anyone with the link so it can be viewed from Sheets & Dashboard.
		$this->make_file_viewable_by_anyone( $file_id );

		// Fetch file link.
		$file_info = $this->get_file_info( $file_id );
		$view_link = ! is_wp_error( $file_info ) && ! empty( $file_info['webViewLink'] )
			? $file_info['webViewLink']
			: sprintf( 'https://drive.google.com/file/d/%s/view', $file_id );

		return array(
			'id'          => $file_id,
			'name'        => $data['name'] ?? $file_name,
			'webViewLink' => $view_link,
			'mimeType'    => $mime_type,
		);
	}

	/**
	 * Set file permission to readable by anyone with the link.
	 *
	 * @param string $file_id
	 * @return bool|\WP_Error
	 */
	public function make_file_viewable_by_anyone( string $file_id ): bool|\WP_Error {
		$auth = $this->authenticate();
		if ( is_wp_error( $auth ) ) {
			return $auth;
		}

		$url     = self::DRIVE_FILES_BASE . '/' . rawurlencode( $file_id ) . '/permissions';
		$payload = array(
			'role'               => 'reader',
			'type'               => 'anyone',
			'allowFileDiscovery' => false,
		);

		$response = $this->http->post( $url, $payload );
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		return true;
	}

	/**
	 * Get file metadata from Google Drive.
	 *
	 * @param string $file_id
	 * @return array|\WP_Error
	 */
	public function get_file_info( string $file_id ): array|\WP_Error {
		$auth = $this->authenticate();
		if ( is_wp_error( $auth ) ) {
			return $auth;
		}

		$url      = self::DRIVE_FILES_BASE . '/' . rawurlencode( $file_id );
		$params   = array( 'fields' => 'id,name,webViewLink,webContentLink,size,mimeType' );
		$response = $this->http->get( $url, $params );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		if ( empty( $response['status'] ) || 200 !== $response['status'] ) {
			return new \WP_Error( 'file_info_error', __( 'Failed to fetch file metadata.', 'drivevault-for-contact-form-7' ) );
		}

		return $response['body'];
	}
}
