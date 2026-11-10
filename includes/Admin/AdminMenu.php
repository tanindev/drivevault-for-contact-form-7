<?php
namespace DriveVault\CF7\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Admin Menu and Page Registration.
 */
class AdminMenu {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'register_menu' ), 50 );
		add_filter( 'plugin_action_links_' . plugin_basename( DRIVEVAULT_CF7_FILE ), array( $this, 'add_action_links' ) );
	}

	/**
	 * Add Settings action link on the plugins list page.
	 *
	 * @param array $links Current plugin action links.
	 * @return array
	 */
	public function add_action_links( array $links ): array {
		$settings_link = sprintf(
			'<a href="%s">%s</a>',
			esc_url( admin_url( 'admin.php?page=' . DRIVEVAULT_CF7_PAGE_SLUG ) ),
			esc_html__( 'Settings', 'drivevault-for-contact-form-7' )
		);
		array_unshift( $links, $settings_link );
		return $links;
	}

	/**
	 * Register Contact Form 7 submenu page for DriveVault.
	 */
	public function register_menu(): void {
		$parent_slug = '';

		// Detect Contact Form 7 top-level parent menu slug.
		// CF7 6.0+ uses 'wpcf7-dashboard', earlier versions use 'wpcf7'.
		if ( isset( $GLOBALS['admin_page_hooks']['wpcf7-dashboard'] ) ) {
			$parent_slug = 'wpcf7-dashboard';
		} elseif ( isset( $GLOBALS['admin_page_hooks']['wpcf7'] ) ) {
			$parent_slug = 'wpcf7';
		} elseif ( defined( 'WPCF7_VERSION' ) ) {
			$parent_slug = version_compare( WPCF7_VERSION, '6.0', '>=' ) ? 'wpcf7-dashboard' : 'wpcf7';
		}

		if ( ! empty( $parent_slug ) ) {
			add_submenu_page(
				$parent_slug,
				__( 'DriveVault', 'drivevault-for-contact-form-7' ),
				__( 'DriveVault', 'drivevault-for-contact-form-7' ),
				'manage_options',
				DRIVEVAULT_CF7_PAGE_SLUG,
				array( $this, 'render_page' )
			);
		} else {
			// Fallback top-level menu when Contact Form 7 is not active.
			add_menu_page(
				__( 'DriveVault CF7', 'drivevault-for-contact-form-7' ),
				__( 'DriveVault CF7', 'drivevault-for-contact-form-7' ),
				'manage_options',
				DRIVEVAULT_CF7_PAGE_SLUG,
				array( $this, 'render_page' ),
				'dashicons-cloud',
				30
			);
		}
	}

	/**
	 * Render React root container and DriveVault frame.
	 */
	public function render_page(): void {
		?>
		<div class="wrap drivevault-cf7-admin-wrap">
			<div id="drivevault-cf7-app"></div>
		</div>
		<?php
	}
}
