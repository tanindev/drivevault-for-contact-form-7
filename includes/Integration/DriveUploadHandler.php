<?php
namespace DriveVault\CF7\Integration;

defined( 'ABSPATH' ) || exit;

use DriveVault\CF7\Google\DriveService;

/**
 * Handles uploading Contact Form 7 file attachments to Google Drive.
 */
class DriveUploadHandler {

	/**
	 * Google Drive Service.
	 */
	protected DriveService $drive;

	/**
	 * Constructor.
	 *
	 * @param DriveService $drive
	 */
	public function __construct( DriveService $drive ) {
		$this->drive = $drive;
	}

	/**
	 * Process file uploads for a submission.
	 *
	 * @param array  $uploaded_files Array of field_name => file_path or array of paths.
	 * @param array  $mapping Form mapping configuration.
	 * @param string $form_title Contact form title.
	 * @param string $submission_id Unique submission identifier.
	 * @return array Array with 'links' (field_name => string URL) and 'uploaded_files' (list of metadata).
	 */
	public function process_uploads( array $uploaded_files, array $mapping, string $form_title, string $submission_id ): array {
		if ( empty( $uploaded_files ) || ! is_array( $uploaded_files ) ) {
			return array(
				'links'          => array(),
				'uploaded_files' => array(),
			);
		}

		// Determine target Drive folder.
		$target_folder_id = ! empty( $mapping['drive_folder_id'] ) ? $mapping['drive_folder_id'] : '';
		if ( empty( $target_folder_id ) ) {
			// Check if we already cached an auto-created folder for this form to avoid duplicates.
			$cache_key        = 'dvcf7_auto_folder_' . absint( $mapping['form_id'] );
			$cached_folder_id = get_transient( $cache_key );

			if ( ! empty( $cached_folder_id ) ) {
				$target_folder_id = $cached_folder_id;
			} else {
				// Create folder named after form.
				$folder_name = sanitize_text_field( $form_title ?: 'CF7 Uploads' );
				$folder      = $this->drive->create_folder( $folder_name );
				if ( ! is_wp_error( $folder ) && ! empty( $folder['id'] ) ) {
					$target_folder_id = $folder['id'];
					set_transient( $cache_key, $target_folder_id, 30 * DAY_IN_SECONDS );
				}
			}
		}

		$links                 = array();
		$uploaded_file_records = array();
		$should_delete_local   = ! empty( $mapping['delete_local_files'] );

		foreach ( $uploaded_files as $field_name => $file_paths ) {
			$paths = is_array( $file_paths ) ? $file_paths : array( $file_paths );
			$urls  = array();

			foreach ( $paths as $path ) {
				if ( ! file_exists( $path ) || ! is_readable( $path ) ) {
					continue;
				}

				$original_name = basename( $path );
				// Prefix submission ID to avoid collisions in Drive folder.
				$drive_file_name = sprintf( '%s_%s', substr( $submission_id, 0, 8 ), $original_name );

				$upload_result = $this->drive->upload_file( $path, $drive_file_name, '', $target_folder_id );

				if ( ! is_wp_error( $upload_result ) && ! empty( $upload_result['webViewLink'] ) ) {
					$urls[]                  = $upload_result['webViewLink'];
					$uploaded_file_records[] = array(
						'field'        => $field_name,
						'name'         => $original_name,
						'drive_id'     => $upload_result['id'],
						'webViewLink'  => $upload_result['webViewLink'],
					);

					// Optionally delete local server file.
					if ( $should_delete_local ) {
						wp_delete_file( $path );
					}
				}
			}

			if ( ! empty( $urls ) ) {
				$links[ $field_name ] = implode( ', ', $urls );
			}
		}

		return array(
			'links'          => $links,
			'uploaded_files' => $uploaded_file_records,
		);
	}
}
