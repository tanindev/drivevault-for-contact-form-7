<?php
namespace DriveVault\CF7\Integration;

defined( 'ABSPATH' ) || exit;

use DriveVault\CF7\Database\MappingRepository;
use DriveVault\CF7\Database\LogRepository;
use DriveVault\CF7\Google\SheetsService;

/**
 * Intercepts Contact Form 7 submissions and dispatches to Google Sheets and Drive.
 */
class SubmissionInterceptor {

	/**
	 * Sheets Service.
	 */
	protected SheetsService $sheets;

	/**
	 * Drive Upload Handler.
	 */
	protected DriveUploadHandler $uploader;

	/**
	 * Constructor.
	 *
	 * @param SheetsService      $sheets
	 * @param DriveUploadHandler $uploader
	 */
	public function __construct( SheetsService $sheets, DriveUploadHandler $uploader ) {
		$this->sheets   = $sheets;
		$this->uploader = $uploader;

		add_action( 'wpcf7_submit', array( $this, 'handle_submission' ), 10, 2 );
	}

	/**
	 * Handle CF7 submission hook.
	 *
	 * @param \WPCF7_ContactForm $contact_form
	 * @param array              $result
	 */
	public function handle_submission( \WPCF7_ContactForm $contact_form, array $result ): void {
		$form_id = $contact_form->id();
		if ( ! $form_id ) {
			return;
		}

		$settings = get_option( DRIVEVAULT_CF7_OPTION_SETTINGS, array() );
		$trigger  = ! empty( $settings['sync_trigger'] ) ? $settings['sync_trigger'] : 'mail_sent';

		if ( 'mail_sent' === $trigger && ( ! isset( $result['status'] ) || 'mail_sent' !== $result['status'] ) ) {
			return;
		}

		$mapping = MappingRepository::find_by_form( $form_id );
		if ( ! $mapping || empty( $mapping['is_active'] ) || empty( $mapping['sheet_id'] ) ) {
			return;
		}

		$start_time = microtime( true );

		$submission = \WPCF7_Submission::get_instance();
		if ( ! $submission ) {
			return;
		}

		$submission_id = wp_generate_uuid4();
		$posted_data   = $submission->get_posted_data();

		// Retrieve uploaded files from CF7 submission.
		$uploaded_files = array();
		if ( method_exists( $submission, 'uploaded_files' ) ) {
			$uploaded_files = (array) $submission->uploaded_files();
		} elseif ( method_exists( $submission, 'get_uploaded_files' ) ) {
			$uploaded_files = (array) $submission->get_uploaded_files();
		} elseif ( ! empty( $submission->get_meta( 'uploaded_files' ) ) ) {
			$uploaded_files = (array) $submission->get_meta( 'uploaded_files' );
		}

		// 1. Process Google Drive File Uploads if any.
		$drive_links   = array();
		$drive_records = array();

		if ( ! empty( $mapping['upload_files_to_drive'] ) && ! empty( $uploaded_files ) ) {
			$upload_result = $this->uploader->process_uploads(
				$uploaded_files,
				$mapping,
				$contact_form->title(),
				$submission_id
			);
			$drive_links   = $upload_result['links'];
			$drive_records = $upload_result['uploaded_files'];
		}

		// 2. Build row values according to column map.
		$column_map = is_array( $mapping['column_map'] ) ? $mapping['column_map'] : array();
		$row_data   = array();

		// Fetch existing headers on sheet to maintain exact column alignment if available.
		$existing_headers = $this->sheets->get_headers( $mapping['sheet_id'], $mapping['tab_name'] );
		$headers          = ( ! is_wp_error( $existing_headers ) && ! empty( $existing_headers ) )
			? $existing_headers
			: array_values( $column_map );

		// Map fields to values.
		$mapped_payload = array();
		foreach ( $column_map as $field_key => $column_header ) {
			$val = '';

			// Check file upload fields first.
			if ( isset( $drive_links[ $field_key ] ) && ! empty( $drive_links[ $field_key ] ) ) {
				$val = $drive_links[ $field_key ];
			} elseif ( isset( $uploaded_files[ $field_key ] ) && ! empty( $uploaded_files[ $field_key ] ) ) {
				$files = is_array( $uploaded_files[ $field_key ] ) ? $uploaded_files[ $field_key ] : array( $uploaded_files[ $field_key ] );
				$urls  = array();
				foreach ( $files as $f ) {
					$urls[] = $this->file_path_to_url( $f );
				}
				$val = implode( ', ', array_filter( $urls ) );
			} elseif ( isset( $posted_data[ $field_key ] ) ) {
				$raw_val = $posted_data[ $field_key ];
				if ( is_array( $raw_val ) ) {
					$val = implode( ', ', array_map( 'strval', $raw_val ) );
				} else {
					$val = (string) $raw_val;
				}

				// If CF7 stored an internal SHA256 upload hash and no file was successfully linked,
				// check if this field was a file tag to avoid writing an un-openable raw hash into the sheet.
				if ( 64 === strlen( $val ) && ctype_xdigit( $val ) ) {
					$tags = $contact_form->scan_form_tags( array( 'name' => $field_key ) );
					if ( ! empty( $tags ) && in_array( $tags[0]->basetype, array( 'file', 'file*' ), true ) ) {
						// phpcs:ignore WordPress.Security.NonceVerification.Missing
						$val = ! empty( $_FILES[ $field_key ]['name'] ) ? sanitize_text_field( wp_unslash( $_FILES[ $field_key ]['name'] ) ) : '';
					}
				}
			}

			// Check if it's a special meta field.
			if ( str_starts_with( $field_key, '_meta_' ) ) {
				$meta_type = substr( $field_key, 6 );
				$val       = $this->resolve_meta_field( $meta_type, $submission, $contact_form, $submission_id, $settings );
			}

			$mapped_payload[ $column_header ] = $val;
		}

		// Build row array in correct column order.
		foreach ( $headers as $header ) {
			$row_data[] = isset( $mapped_payload[ $header ] ) ? $mapped_payload[ $header ] : '';
		}

		// If headers were empty (new sheet), ensure headers are created.
		if ( is_wp_error( $existing_headers ) || empty( $existing_headers ) ) {
			$this->sheets->create_or_update_headers( $mapping['sheet_id'], $mapping['tab_name'], array_keys( $mapped_payload ) );
			$row_data = array_values( $mapped_payload );
		}

		// 3. Dispatch to Google Sheets.
		$sync_result = $this->sheets->append_row( $mapping['sheet_id'], $mapping['tab_name'], $row_data );
		$duration    = (int) ( ( microtime( true ) - $start_time ) * 1000 );

		if ( is_wp_error( $sync_result ) ) {
			// Record error log.
			LogRepository::add_log(
				array(
					'form_id'       => $form_id,
					'submission_id' => $submission_id,
					'sheet_id'      => $mapping['sheet_id'],
					'mapped_data'   => $mapped_payload,
					'drive_files'   => $drive_records,
					'result'        => 'error',
					'error_message' => $sync_result->get_error_message(),
					'duration_ms'   => $duration,
				)
			);

			$this->maybe_send_error_email( $contact_form->title(), $sync_result->get_error_message() );
		} else {
			// Record success log.
			LogRepository::add_log(
				array(
					'form_id'       => $form_id,
					'submission_id' => $submission_id,
					'sheet_id'      => $mapping['sheet_id'],
					'mapped_data'   => $mapped_payload,
					'drive_files'   => $drive_records,
					'result'        => 'success',
					'error_message' => null,
					'duration_ms'   => $duration,
				)
			);

			MappingRepository::update_last_submitted( $form_id );
		}
	}

	/**
	 * Convert local filesystem path to public URL.
	 *
	 * @param string $path
	 * @return string
	 */
	protected function file_path_to_url( string $path ): string {
		$uploads = wp_upload_dir();
		if ( str_starts_with( $path, $uploads['basedir'] ) ) {
			return str_replace( $uploads['basedir'], $uploads['baseurl'], $path );
		}
		return basename( $path );
	}

	/**
	 * Resolve meta field value.
	 *
	 * @param string              $key
	 * @param \WPCF7_Submission   $submission
	 * @param \WPCF7_ContactForm  $contact_form
	 * @param string              $submission_id
	 * @param array               $settings
	 * @return string
	 */
	protected function resolve_meta_field( string $key, \WPCF7_Submission $submission, \WPCF7_ContactForm $contact_form, string $submission_id, array $settings ): string {
		return match ( $key ) {
			'submission_time' => current_time( 'Y-m-d H:i:s' ),
			'form_title'      => (string) $contact_form->title(),
			'form_id'         => (string) $contact_form->id(),
			'submission_id'   => $submission_id,
			'ip_address'      => ! empty( $settings['anonymize_ip'] ) ? 'Anonymized' : (string) $submission->get_meta( 'remote_ip' ),
			'page_url'        => (string) ( $submission->get_meta( 'url' ) ?: ( wp_get_referer() ?: '' ) ),
			'user_agent'      => (string) $submission->get_meta( 'user_agent' ),
			default           => '',
		};
	}

	/**
	 * Send alert email on sync failure.
	 *
	 * @param string $form_title
	 * @param string $error_message
	 */
	protected function maybe_send_error_email( string $form_title, string $error_message ): void {
		$settings = get_option( DRIVEVAULT_CF7_OPTION_SETTINGS, array() );
		if ( empty( $settings['enable_error_notifications'] ) || empty( $settings['error_notification_email'] ) ) {
			return;
		}

		$to      = sanitize_email( $settings['error_notification_email'] );
		$subject = sprintf( '[DriveVault] Google Sync Alert: %s', $form_title );
		$body    = sprintf(
			"Hello,\n\nA Contact Form 7 submission failed to sync with Google Sheets.\n\nForm: %s\nTime: %s\nError: %s\n\nPlease check your DriveVault Submission Logs in WordPress admin for details.\n\nBest regards,\nDriveVault for Contact Form 7",
			$form_title,
			current_time( 'mysql' ),
			$error_message
		);

		wp_mail( $to, $subject, $body );
	}
}
