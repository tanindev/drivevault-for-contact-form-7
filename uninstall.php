<?php
/**
 * DriveVault for Contact Form 7 Uninstall Handler.
 *
 * @package DriveVaultCF7
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Clean up plugin data upon uninstall if enabled by administrator.
 */
function drivevault_cf7_uninstall_plugin(): void {
	// Check if user chose to delete data upon uninstall.
	$dvcf7_settings = get_option( 'dvcf7_settings', array() );
	if ( empty( $dvcf7_settings['delete_data_on_uninstall'] ) ) {
		return;
	}

	global $wpdb;

	// 1. Drop plugin custom tables.
	$dvcf7_mappings_table = $wpdb->prefix . 'dvcf7_form_mappings';
	$dvcf7_logs_table     = $wpdb->prefix . 'dvcf7_submission_log';

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
	$wpdb->query( "DROP TABLE IF EXISTS {$dvcf7_mappings_table}" );
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
	$wpdb->query( "DROP TABLE IF EXISTS {$dvcf7_logs_table}" );

	// 2. Delete plugin options.
	delete_option( 'dvcf7_settings' );
	delete_option( 'dvcf7_google_tokens' );
	delete_option( 'dvcf7_version' );
	delete_option( 'dvcf7_db_version' );

	// 3. Clear transient caches.
	delete_transient( 'dvcf7_oauth_state' );

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery
	$wpdb->query(
		$wpdb->prepare(
			"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
			'_transient_dvcf7_%'
		)
	);
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery
	$wpdb->query(
		$wpdb->prepare(
			"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
			'_transient_timeout_dvcf7_%'
		)
	);

	// 4. Clear scheduled hooks.
	wp_clear_scheduled_hook( 'dvcf7_token_refresh' );
	wp_clear_scheduled_hook( 'dvcf7_cleanup_logs' );
}

drivevault_cf7_uninstall_plugin();
