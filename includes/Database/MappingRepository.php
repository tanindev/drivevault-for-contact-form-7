<?php
namespace DriveVault\CF7\Database;

defined( 'ABSPATH' ) || exit;

/**
 * Form Mapping Data Repository.
 */
class MappingRepository {

	/**
	 * Table name without prefix.
	 */
	const TABLE_NAME = 'dvcf7_form_mappings';

	/**
	 * Get full table name with prefix.
	 *
	 * @return string
	 */
	public static function get_table_name(): string {
		global $wpdb;
		return $wpdb->prefix . self::TABLE_NAME;
	}

	/**
	 * Retrieve all mappings.
	 *
	 * @return array
	 */
	public static function get_all(): array {
		global $wpdb;
		$table = self::get_table_name();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
		$results = $wpdb->get_results( "SELECT * FROM {$table} ORDER BY id DESC", ARRAY_A );

		if ( ! is_array( $results ) ) {
			return array();
		}

		return array_map( array( __CLASS__, 'format_row' ), $results );
	}

	/**
	 * Find a mapping by its form ID.
	 *
	 * @param int $form_id
	 * @return array|null
	 */
	public static function find_by_form( int $form_id ): ?array {
		global $wpdb;
		$table = self::get_table_name();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery, PluginCheck.Security.DirectDB.UnescapedDBParameter
		$row = $wpdb->get_row(
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->prepare( "SELECT * FROM {$table} WHERE form_id = %d LIMIT 1", absint( $form_id ) ),
			ARRAY_A
		);

		return $row ? self::format_row( $row ) : null;
	}

	/**
	 * Find a mapping by its primary ID.
	 *
	 * @param int $id
	 * @return array|null
	 */
	public static function find_by_id( int $id ): ?array {
		global $wpdb;
		$table = self::get_table_name();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery, PluginCheck.Security.DirectDB.UnescapedDBParameter
		$row = $wpdb->get_row(
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d LIMIT 1", absint( $id ) ),
			ARRAY_A
		);

		return $row ? self::format_row( $row ) : null;
	}

	/**
	 * Create or update a form mapping.
	 *
	 * @param array $data
	 * @return int|\WP_Error Primary row ID on success, WP_Error on failure.
	 */
	public static function save( array $data ): int|\WP_Error {
		global $wpdb;
		$table = self::get_table_name();

		if ( empty( $data['form_id'] ) ) {
			return new \WP_Error( 'missing_form_id', __( 'A Contact Form ID is required.', 'drivevault-for-contact-form-7' ) );
		}

		$form_id = absint( $data['form_id'] );
		$existing = self::find_by_form( $form_id );

		$column_map = '';
		if ( isset( $data['column_map'] ) ) {
			$column_map = is_string( $data['column_map'] ) ? $data['column_map'] : wp_json_encode( $data['column_map'] );
		}

		$payload = array(
			'form_id'               => $form_id,
			'sheet_id'              => ! empty( $data['sheet_id'] ) ? sanitize_text_field( $data['sheet_id'] ) : '',
			'sheet_name'            => ! empty( $data['sheet_name'] ) ? sanitize_text_field( $data['sheet_name'] ) : '',
			'tab_name'              => ! empty( $data['tab_name'] ) ? sanitize_text_field( $data['tab_name'] ) : 'Sheet1',
			'drive_folder_id'       => ! empty( $data['drive_folder_id'] ) ? sanitize_text_field( $data['drive_folder_id'] ) : '',
			'drive_folder_name'     => ! empty( $data['drive_folder_name'] ) ? sanitize_text_field( $data['drive_folder_name'] ) : '',
			'column_map'            => $column_map,
			'include_meta'          => isset( $data['include_meta'] ) ? ( $data['include_meta'] ? 1 : 0 ) : 1,
			'upload_files_to_drive' => isset( $data['upload_files_to_drive'] ) ? ( $data['upload_files_to_drive'] ? 1 : 0 ) : 1,
			'delete_local_files'    => isset( $data['delete_local_files'] ) ? ( $data['delete_local_files'] ? 1 : 0 ) : 0,
			'is_active'             => isset( $data['is_active'] ) ? ( $data['is_active'] ? 1 : 0 ) : 1,
		);

		if ( $existing ) {
			// Update.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery
			$updated = $wpdb->update(
				$table,
				$payload,
				array( 'id' => $existing['id'] ),
				array( '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%d', '%d' ),
				array( '%d' )
			);

			if ( false === $updated ) {
				return new \WP_Error( 'db_update_error', $wpdb->last_error ?: __( 'Failed to update mapping.', 'drivevault-for-contact-form-7' ) );
			}

			return (int) $existing['id'];
		} else {
			// Insert.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery
			$inserted = $wpdb->insert(
				$table,
				$payload,
				array( '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%d', '%d' )
			);

			if ( false === $inserted ) {
				return new \WP_Error( 'db_insert_error', $wpdb->last_error ?: __( 'Failed to save mapping.', 'drivevault-for-contact-form-7' ) );
			}

			return (int) $wpdb->insert_id;
		}
	}

	/**
	 * Delete a mapping.
	 *
	 * @param int $id
	 * @return bool
	 */
	public static function delete( int $id ): bool {
		global $wpdb;
		$table = self::get_table_name();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery
		$deleted = $wpdb->delete(
			$table,
			array( 'id' => absint( $id ) ),
			array( '%d' )
		);

		return false !== $deleted;
	}

	/**
	 * Toggle active status of a mapping.
	 *
	 * @param int $id
	 * @return bool|int New status or false on failure.
	 */
	public static function toggle_status( int $id ): int|bool {
		$mapping = self::find_by_id( $id );
		if ( ! $mapping ) {
			return false;
		}

		$new_status = $mapping['is_active'] ? 0 : 1;

		global $wpdb;
		$table = self::get_table_name();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery
		$updated = $wpdb->update(
			$table,
			array( 'is_active' => $new_status ),
			array( 'id' => absint( $id ) ),
			array( '%d' ),
			array( '%d' )
		);

		return false !== $updated ? $new_status : false;
	}

	/**
	 * Touch last submitted at timestamp.
	 *
	 * @param int $form_id
	 * @return bool
	 */
	public static function update_last_submitted( int $form_id ): bool {
		global $wpdb;
		$table = self::get_table_name();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery
		$result = $wpdb->update(
			$table,
			array( 'last_submitted_at' => current_time( 'mysql' ) ),
			array( 'form_id' => absint( $form_id ) ),
			array( '%s' ),
			array( '%d' )
		);

		return false !== $result;
	}

	/**
	 * Format raw DB row into standardized structure.
	 *
	 * @param array $row
	 * @return array
	 */
	public static function format_row( array $row ): array {
		$column_map = array();
		if ( ! empty( $row['column_map'] ) ) {
			$decoded = json_decode( $row['column_map'], true );
			if ( is_array( $decoded ) ) {
				$column_map = $decoded;
			}
		}

		return array(
			'id'                    => (int) $row['id'],
			'form_id'               => (int) $row['form_id'],
			'sheet_id'              => (string) $row['sheet_id'],
			'sheet_name'            => (string) $row['sheet_name'],
			'tab_name'              => (string) $row['tab_name'],
			'drive_folder_id'       => (string) $row['drive_folder_id'],
			'drive_folder_name'     => (string) $row['drive_folder_name'],
			'column_map'            => $column_map,
			'include_meta'          => (bool) $row['include_meta'],
			'upload_files_to_drive' => (bool) $row['upload_files_to_drive'],
			'delete_local_files'    => (bool) $row['delete_local_files'],
			'is_active'             => (bool) $row['is_active'],
			'last_submitted_at'     => $row['last_submitted_at'],
			'created_at'            => $row['created_at'],
			'updated_at'            => $row['updated_at'],
		);
	}
}
