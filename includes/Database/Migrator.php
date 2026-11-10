<?php
namespace DriveVault\CF7\Database;

defined( 'ABSPATH' ) || exit;

/**
 * Database Table Migrator.
 */
class Migrator {

	/**
	 * Run migrations for plugin custom database tables.
	 */
	public static function migrate(): void {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset_collate = $wpdb->get_charset_collate();

		// 1. Form Feeds & Mappings Table.
		$mappings_table = $wpdb->prefix . 'dvcf7_form_mappings';
		$sql_mappings   = "CREATE TABLE {$mappings_table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			form_id BIGINT UNSIGNED NOT NULL,
			sheet_id VARCHAR(255) NOT NULL DEFAULT '',
			sheet_name VARCHAR(255) NOT NULL DEFAULT '',
			tab_name VARCHAR(255) NOT NULL DEFAULT 'Sheet1',
			drive_folder_id VARCHAR(255) NOT NULL DEFAULT '',
			drive_folder_name VARCHAR(255) NOT NULL DEFAULT '',
			column_map LONGTEXT NULL,
			include_meta TINYINT(1) NOT NULL DEFAULT 1,
			upload_files_to_drive TINYINT(1) NOT NULL DEFAULT 1,
			delete_local_files TINYINT(1) NOT NULL DEFAULT 0,
			is_active TINYINT(1) NOT NULL DEFAULT 1,
			last_submitted_at DATETIME NULL DEFAULT NULL,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			UNIQUE KEY form_id (form_id),
			KEY is_active (is_active)
		) {$charset_collate};";

		dbDelta( $sql_mappings );

		// 2. Submission Audit Log Table.
		$logs_table = $wpdb->prefix . 'dvcf7_submission_log';
		$sql_logs   = "CREATE TABLE {$logs_table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			form_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			submission_id VARCHAR(64) NOT NULL DEFAULT '',
			sheet_id VARCHAR(255) NOT NULL DEFAULT '',
			mapped_data LONGTEXT NULL,
			drive_files LONGTEXT NULL,
			result VARCHAR(32) NOT NULL DEFAULT 'success',
			error_message TEXT NULL,
			duration_ms INT UNSIGNED NOT NULL DEFAULT 0,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY form_id (form_id),
			KEY result (result),
			KEY created_at (created_at)
		) {$charset_collate};";

		dbDelta( $sql_logs );

		update_option( 'dvcf7_db_version', DRIVEVAULT_CF7_VERSION );
	}
}
