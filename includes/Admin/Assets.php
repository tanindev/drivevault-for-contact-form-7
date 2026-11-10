<?php
namespace DriveVault\CF7\Admin;

defined( 'ABSPATH' ) || exit;

use DriveVault\CF7\Google\OAuthManager;

/**
 * Admin Assets Enqueue Manager.
 */
class Assets {

	/**
	 * OAuth Manager.
	 */
	protected OAuthManager $oauth;

	/**
	 * Constructor.
	 *
	 * @param OAuthManager $oauth
	 */
	public function __construct( OAuthManager $oauth ) {
		$this->oauth = $oauth;
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	/**
	 * Enqueue scripts and styles on plugin settings screen.
	 *
	 * @param string $hook
	 */
	public function enqueue_assets( string $hook ): void {
		// Only enqueue on our plugin screen.
		if ( ! str_contains( $hook, DRIVEVAULT_CF7_PAGE_SLUG ) ) {
			return;
		}

		$asset_file = DRIVEVAULT_CF7_PATH . 'assets/build/index.asset.php';
		$asset_info = file_exists( $asset_file )
			? require $asset_file
			: array(
				'dependencies' => array( 'wp-element', 'wp-components', 'wp-data', 'wp-i18n', 'wp-api-fetch' ),
				'version'      => DRIVEVAULT_CF7_VERSION,
			);

		// Enqueue built CSS.
		if ( file_exists( DRIVEVAULT_CF7_PATH . 'assets/build/index.css' ) ) {
			wp_enqueue_style(
				'drivevault-cf7-admin',
				DRIVEVAULT_CF7_ASSETS_URL . 'build/index.css',
				array( 'wp-components' ),
				$asset_info['version']
			);
		}

		// Enqueue built JS.
		if ( file_exists( DRIVEVAULT_CF7_PATH . 'assets/build/index.js' ) ) {
			wp_enqueue_script(
				'drivevault-cf7-admin',
				DRIVEVAULT_CF7_ASSETS_URL . 'build/index.js',
				$asset_info['dependencies'],
				$asset_info['version'],
				true
			);

			// Retrieve one-time auth notice if set.
			$auth_notice = get_transient( 'dvcf7_auth_notice' );
			if ( $auth_notice ) {
				delete_transient( 'dvcf7_auth_notice' );
			}

			// Localize settings and credentials data.
			$settings = get_option( DRIVEVAULT_CF7_OPTION_SETTINGS, array() );

			$localized_data = array(
				'apiUrl'       => esc_url_raw( rest_url( 'drivevault-cf7/v1' ) ),
				'nonce'        => wp_create_nonce( 'wp_rest' ),
				'adminUrl'     => esc_url_raw( admin_url() ),
				'redirectUri'  => $this->oauth->get_redirect_uri(),
				'version'      => DRIVEVAULT_CF7_VERSION,
				'assetsUrl'    => DRIVEVAULT_CF7_ASSETS_URL,
				'isConnected'  => $this->oauth->is_connected(),
				'authNotice'   => $auth_notice,
				'settings'     => array(
					'client_id'                  => ! empty( $settings['client_id'] ) ? $settings['client_id'] : '',
					'client_secret'              => ! empty( $settings['client_secret'] ) ? $settings['client_secret'] : '',
					'sync_trigger'               => ! empty( $settings['sync_trigger'] ) ? $settings['sync_trigger'] : 'mail_sent',
					'delete_local_uploads'       => ! empty( $settings['delete_local_uploads'] ),
					'anonymize_ip'               => ! empty( $settings['anonymize_ip'] ),
					'error_notification_email'   => ! empty( $settings['error_notification_email'] ) ? $settings['error_notification_email'] : get_option( 'admin_email' ),
					'enable_error_notifications' => isset( $settings['enable_error_notifications'] ) ? (bool) $settings['enable_error_notifications'] : true,
					'log_retention_days'         => ! empty( $settings['log_retention_days'] ) ? (int) $settings['log_retention_days'] : 60,
					'delete_data_on_uninstall'   => ! empty( $settings['delete_data_on_uninstall'] ),
				),
			);

			wp_localize_script( 'drivevault-cf7-admin', 'drivevaultCf7Data', $localized_data );

			if ( function_exists( 'wp_set_script_translations' ) ) {
				wp_set_script_translations( 'drivevault-cf7-admin', 'drivevault-for-contact-form-7', DRIVEVAULT_CF7_PATH . 'languages' );
			}
		}
	}
}
