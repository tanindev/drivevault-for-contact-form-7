<?php
/**
 * Plugin Name:       DriveVault for Contact Form 7 – Google Sheets & Drive Sync
 * Plugin URI:        https://github.com/tanindev/drivevault-for-contact-form-7
 * Description:       Connect Contact Form 7 to Google Sheets & Google Drive. Sync form submissions to spreadsheets in real time and securely store file uploads in Google Drive.
 * Version:           1.0.0
 * Author:            DriveVault
 * Author URI:        https://profiles.wordpress.org/tanindev/
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       drivevault-for-contact-form-7
 * Domain Path:       /languages
 * Requires at least: 6.2
 * Requires PHP:      8.3
 * Requires Plugins:  contact-form-7
 *
 * @package DriveVaultCF7
 */

defined( 'ABSPATH' ) || exit;

define( 'DRIVEVAULT_CF7_VERSION', '1.0.0' );
define( 'DRIVEVAULT_CF7_FILE', __FILE__ );
define( 'DRIVEVAULT_CF7_PATH', plugin_dir_path( __FILE__ ) );
define( 'DRIVEVAULT_CF7_URL', plugin_dir_url( __FILE__ ) );
define( 'DRIVEVAULT_CF7_ASSETS_URL', DRIVEVAULT_CF7_URL . 'assets/' );
define( 'DRIVEVAULT_CF7_PAGE_SLUG', 'drivevault-cf7' );

if ( ! defined( 'DRIVEVAULT_CF7_OPTION_SETTINGS' ) ) {
	define( 'DRIVEVAULT_CF7_OPTION_SETTINGS', 'dvcf7_settings' );
}
if ( ! defined( 'DRIVEVAULT_CF7_OPTION_TOKENS' ) ) {
	define( 'DRIVEVAULT_CF7_OPTION_TOKENS', 'dvcf7_google_tokens' );
}

define( 'DRIVEVAULT_CF7_MIN_PHP', '8.3' );
define( 'DRIVEVAULT_CF7_MIN_WP', '6.2' );

/**
 * Autoloader for Plugin Classes.
 */
spl_autoload_register( function ( string $class ): void {
	$prefix   = 'DriveVault\\CF7\\';
	$base_dir = DRIVEVAULT_CF7_PATH . 'includes/';

	if ( ! str_starts_with( $class, $prefix ) ) {
		return;
	}

	$relative_class = substr( $class, strlen( $prefix ) );
	$file           = $base_dir . str_replace( '\\', '/', $relative_class ) . '.php';

	if ( file_exists( $file ) ) {
		require_once $file;
	}
} );

/**
 * Check dependencies and initialize plugin.
 */
function drivevault_cf7_init_plugin(): void {
	// Check PHP version.
	if ( version_compare( PHP_VERSION, DRIVEVAULT_CF7_MIN_PHP, '<' ) ) {
		add_action( 'admin_notices', function () {
			?>
			<div class="notice notice-error is-dismissible">
				<p>
					<strong><?php esc_html_e( 'DriveVault for Contact Form 7', 'drivevault-for-contact-form-7' ); ?></strong>
					<?php
					printf(
						/* translators: 1: Minimum PHP version, 2: Current PHP version */
						esc_html__( 'requires PHP %1$s or higher. You are currently running PHP %2$s.', 'drivevault-for-contact-form-7' ),
						esc_html( DRIVEVAULT_CF7_MIN_PHP ),
						esc_html( PHP_VERSION )
					);
					?>
				</p>
			</div>
			<?php
		} );
		return;
	}

	// Check WordPress version.
	global $wp_version;
	if ( version_compare( $wp_version, DRIVEVAULT_CF7_MIN_WP, '<' ) ) {
		add_action( 'admin_notices', function () {
			?>
			<div class="notice notice-error is-dismissible">
				<p>
					<strong><?php esc_html_e( 'DriveVault for Contact Form 7', 'drivevault-for-contact-form-7' ); ?></strong>
					<?php
					printf(
						/* translators: 1: Minimum WordPress version, 2: Current WordPress version */
						esc_html__( 'requires WordPress %1$s or higher. You are currently running WordPress %2$s.', 'drivevault-for-contact-form-7' ),
						esc_html( DRIVEVAULT_CF7_MIN_WP ),
						esc_html( $GLOBALS['wp_version'] ?? '' )
					);
					?>
				</p>
			</div>
			<?php
		} );
		return;
	}

	// Check Contact Form 7 is active.
	if ( ! defined( 'WPCF7_VERSION' ) && ! class_exists( 'WPCF7_ContactForm' ) ) {
		add_action( 'admin_notices', function () {
			?>
			<div class="notice notice-warning is-dismissible">
				<p>
					<strong><?php esc_html_e( 'DriveVault for Contact Form 7', 'drivevault-for-contact-form-7' ); ?></strong>
					<?php esc_html_e( 'requires Contact Form 7 to be installed and active.', 'drivevault-for-contact-form-7' ); ?>
				</p>
			</div>
			<?php
		} );
		return;
	}

	// Initialize main plugin singleton.
	\DriveVault\CF7\Plugin::instance();
}
add_action( 'init', 'drivevault_cf7_init_plugin' );

/**
 * Activation Hook.
 */
register_activation_hook( __FILE__, function () {
	// Run DB migrations.
	require_once DRIVEVAULT_CF7_PATH . 'includes/Database/Migrator.php';
	\DriveVault\CF7\Database\Migrator::migrate();

	// Setup default options if not exists.
	$default_settings = array(
		'client_id'                  => '',
		'client_secret'              => '',
		'sync_trigger'               => 'mail_sent', // 'mail_sent' (standard) or 'submit'
		'delete_local_uploads'       => false,
		'anonymize_ip'               => false,
		'error_notification_email'   => get_option( 'admin_email' ),
		'enable_error_notifications' => true,
		'log_retention_days'         => 60,
		'delete_data_on_uninstall'   => false,
	);

	if ( false === get_option( DRIVEVAULT_CF7_OPTION_SETTINGS ) ) {
		update_option( DRIVEVAULT_CF7_OPTION_SETTINGS, $default_settings );
	}
} );

/**
 * Deactivation Hook.
 */
register_deactivation_hook( __FILE__, function () {
	wp_clear_scheduled_hook( 'dvcf7_token_refresh' );
	wp_clear_scheduled_hook( 'dvcf7_cleanup_logs' );
} );
