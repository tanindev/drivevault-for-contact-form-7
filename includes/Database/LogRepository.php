<?php
namespace DriveVault\CF7\Database;

defined( 'ABSPATH' ) || exit;

/**
 * Submission Audit Log Data Repository.
 */
class LogRepository {

	/**
	 * Table name without prefix.
	 */
	const TABLE_NAME = 'dvcf7_submission_log';

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
	 * Record a new submission attempt log.
	 *
	 * @param array $data
	 * @return int Log ID on success.
	 */
	public static function add_log( array $data ): int {
		global $wpdb;
		$table = self::get_table_name();

		$mapped_data = '';
		if ( isset( $data['mapped_data'] ) ) {
			$mapped_data = is_string( $data['mapped_data'] ) ? $data['mapped_data'] : wp_json_encode( $data['mapped_data'] );
		}

		$drive_files = '';
		if ( isset( $data['drive_files'] ) ) {
			$drive_files = is_string( $data['drive_files'] ) ? $data['drive_files'] : wp_json_encode( $data['drive_files'] );
		}

		$payload = array(
			'form_id'       => ! empty( $data['form_id'] ) ? absint( $data['form_id'] ) : 0,
			'submission_id' => ! empty( $data['submission_id'] ) ? sanitize_text_field( $data['submission_id'] ) : '',
			'sheet_id'      => ! empty( $data['sheet_id'] ) ? sanitize_text_field( $data['sheet_id'] ) : '',
			'mapped_data'   => $mapped_data,
			'drive_files'   => $drive_files,
			'result'        => ! empty( $data['result'] ) ? sanitize_text_field( $data['result'] ) : 'success',
			'error_message' => ! empty( $data['error_message'] ) ? sanitize_text_field( $data['error_message'] ) : null,
			'duration_ms'   => ! empty( $data['duration_ms'] ) ? absint( $data['duration_ms'] ) : 0,
			'created_at'    => current_time( 'mysql' ),
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery
		$inserted = $wpdb->insert(
			$table,
			$payload,
			array( '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s' )
		);

		return false !== $inserted ? (int) $wpdb->insert_id : 0;
	}

	/**
	 * Get paginated logs list.
	 *
	 * @param array $args
	 * @return array Array with 'items', 'total', 'pages', 'current_page'.
	 */
	public static function get_logs( array $args = array() ): array {
		global $wpdb;
		$table = self::get_table_name();

		$page     = isset( $args['page'] ) ? max( 1, absint( $args['page'] ) ) : 1;
		$per_page = isset( $args['per_page'] ) ? min( 100, max( 5, absint( $args['per_page'] ) ) ) : 20;
		$offset   = ( $page - 1 ) * $per_page;

		$where_clauses = array( '1=1' );
		$params        = array();

		// Status filter.
		if ( ! empty( $args['result'] ) && in_array( $args['result'], array( 'success', 'error', 'skipped' ), true ) ) {
			$where_clauses[] = 'result = %s';
			$params[]        = $args['result'];
		}

		// Form ID filter.
		if ( ! empty( $args['form_id'] ) ) {
			$where_clauses[] = 'form_id = %d';
			$params[]        = absint( $args['form_id'] );
		}

		// Search term.
		if ( ! empty( $args['search'] ) ) {
			$where_clauses[] = '(submission_id LIKE %s OR sheet_id LIKE %s OR error_message LIKE %s)';
			$like            = '%' . $wpdb->esc_like( sanitize_text_field( $args['search'] ) ) . '%';
			$params[]        = $like;
			$params[]        = $like;
			$params[]        = $like;
		}

		$where_sql = implode( ' AND ', $where_clauses );

		// Count total matching.
		$count_sql = "SELECT COUNT(*) FROM {$table} WHERE {$where_sql}";
		if ( ! empty( $params ) ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
			$total = (int) $wpdb->get_var( $wpdb->prepare( $count_sql, $params ) );
		} else {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
			$total = (int) $wpdb->get_var( $count_sql );
		}

		// Fetch items.
		$items_sql = "SELECT * FROM {$table} WHERE {$where_sql} ORDER BY id DESC LIMIT %d OFFSET %d";
		$items_params = array_merge( $params, array( $per_page, $offset ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
		$rows = $wpdb->get_results( $wpdb->prepare( $items_sql, $items_params ), ARRAY_A );

		// Preload mappings to enrich logs with sheet & tab names.
		$mappings_by_form = array();
		$all_mappings     = MappingRepository::get_all();
		if ( is_array( $all_mappings ) ) {
			foreach ( $all_mappings as $m ) {
				$mappings_by_form[ (int) $m['form_id'] ] = $m;
			}
		}

		$items = array();
		if ( is_array( $rows ) ) {
			foreach ( $rows as $row ) {
				$form_id    = (int) $row['form_id'];
				$form_title = '';
				if ( ! empty( $form_id ) ) {
					$form_title = get_the_title( $form_id );
				}

				$mapping    = isset( $mappings_by_form[ $form_id ] ) ? $mappings_by_form[ $form_id ] : null;
				$sheet_name = ( $mapping && ! empty( $mapping['sheet_name'] ) )
					? $mapping['sheet_name']
					: ( ! empty( $row['sheet_id'] ) ? substr( $row['sheet_id'], 0, 16 ) . '...' : '' );
				$tab_name   = ( $mapping && ! empty( $mapping['tab_name'] ) ) ? $mapping['tab_name'] : 'Sheet1';

				$mapped_data = ! empty( $row['mapped_data'] ) ? json_decode( $row['mapped_data'], true ) : array();
				$drive_files = ! empty( $row['drive_files'] ) ? json_decode( $row['drive_files'], true ) : array();

				$items[] = array(
					'id'                => (int) $row['id'],
					'form_id'           => $form_id,
					/* translators: %d: Form ID */
					'form_title'        => $form_title ?: sprintf( __( 'Form #%d', 'drivevault-for-contact-form-7' ), $form_id ),
					'submission_id'     => $row['submission_id'],
					'sheet_id'          => $row['sheet_id'],
					'sheet_name'        => $sheet_name,
					'spreadsheet_title' => $sheet_name,
					'tab_name'          => $tab_name,
					'mapped_data'       => $mapped_data,
					'form_data'         => $mapped_data,
					'payload'           => $mapped_data,
					'drive_files'       => $drive_files,
					'uploaded_files'    => $drive_files,
					'result'            => $row['result'],
					'status'            => $row['result'],
					'error_message'     => $row['error_message'],
					'duration_ms'       => (int) $row['duration_ms'],
					'execution_time_ms' => (int) $row['duration_ms'],
					'created_at'        => $row['created_at'],
				);
			}
		}

		return array(
			'items'        => $items,
			'total'        => $total,
			'pages'        => ceil( $total / $per_page ),
			'current_page' => $page,
			'per_page'     => $per_page,
		);
	}

	/**
	 * Find a log entry by primary ID.
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

		if ( ! $row ) {
			return null;
		}

		$form_id    = (int) $row['form_id'];
		$mapping    = MappingRepository::find_by_form( $form_id );
		$sheet_name = ( $mapping && ! empty( $mapping['sheet_name'] ) )
			? $mapping['sheet_name']
			: ( ! empty( $row['sheet_id'] ) ? substr( $row['sheet_id'], 0, 16 ) . '...' : '' );
		$tab_name   = ( $mapping && ! empty( $mapping['tab_name'] ) ) ? $mapping['tab_name'] : 'Sheet1';

		$mapped_data = ! empty( $row['mapped_data'] ) ? json_decode( $row['mapped_data'], true ) : array();
		$drive_files = ! empty( $row['drive_files'] ) ? json_decode( $row['drive_files'], true ) : array();

		return array(
			'id'                => (int) $row['id'],
			'form_id'           => $form_id,
			'submission_id'     => $row['submission_id'],
			'sheet_id'          => $row['sheet_id'],
			'sheet_name'        => $sheet_name,
			'spreadsheet_title' => $sheet_name,
			'tab_name'          => $tab_name,
			'mapped_data'       => $mapped_data,
			'form_data'         => $mapped_data,
			'payload'           => $mapped_data,
			'drive_files'       => $drive_files,
			'uploaded_files'    => $drive_files,
			'result'            => $row['result'],
			'status'            => $row['result'],
			'error_message'     => $row['error_message'],
			'duration_ms'       => (int) $row['duration_ms'],
			'execution_time_ms' => (int) $row['duration_ms'],
			'created_at'        => $row['created_at'],
		);
	}

	/**
	 * Get aggregated statistics for the dashboard.
	 *
	 * @return array
	 */
	public static function get_stats(): array {
		global $wpdb;
		$table = self::get_table_name();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
		$total_syncs = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
		$successful = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE result = 'success'" );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
		$failed = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE result = 'error'" );

		$success_rate = $total_syncs > 0 ? round( ( $successful / $total_syncs ) * 100, 1 ) : 100;

		// Count active form mappings.
		$mappings_table = MappingRepository::get_table_name();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
		$active_forms = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$mappings_table} WHERE is_active = 1" );

		return array(
			'total_syncs'   => $total_syncs,
			'successful'    => $successful,
			'failed'        => $failed,
			'success_rate'  => $success_rate,
			'active_forms'  => $active_forms,
		);
	}

	/**
	 * Delete a single log row.
	 *
	 * @param int $id
	 * @return bool
	 */
	public static function delete_log( int $id ): bool {
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
	 * Clear all logs.
	 *
	 * @return bool
	 */
	public static function clear_all(): bool {
		global $wpdb;
		$table = self::get_table_name();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
		return false !== $wpdb->query( "TRUNCATE TABLE {$table}" );
	}

	/**
	 * Automatically delete logs older than retention days.
	 *
	 * @param int $days
	 * @return int Deleted count.
	 */
	public static function cleanup_old_logs( int $days = 60 ): int {
		global $wpdb;
		$table = self::get_table_name();
		$days  = max( 1, absint( $days ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery, PluginCheck.Security.DirectDB.UnescapedDBParameter
		return (int) $wpdb->query(
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->prepare( "DELETE FROM {$table} WHERE created_at < DATE_SUB(NOW(), INTERVAL %d DAY)", $days ) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		);
	}
}
