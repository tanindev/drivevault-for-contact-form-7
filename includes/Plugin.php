<?php
namespace DriveVault\CF7;

defined( 'ABSPATH' ) || exit;

/**
 * Main Singleton Plugin Orchestrator.
 */
class Plugin {

	/**
	 * Singleton instance.
	 */
	private static ?Plugin $instance = null;

	/**
	 * Services.
	 */
	public Google\HttpClient $http;
	public Google\OAuthManager $oauth;
	public Google\SheetsService $sheets;
	public Google\DriveService $drive;
	public Integration\FormManager $form_manager;
	public Integration\DriveUploadHandler $upload_handler;
	public Integration\SubmissionInterceptor $interceptor;
	public Admin\AdminMenu $admin_menu;
	public Admin\Assets $assets;
	public API\RestServer $rest_server;

	/**
	 * Get singleton instance.
	 *
	 * @return Plugin
	 */
	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		$this->init_services();
		$this->init_hooks();
	}

	/**
	 * Initialize all plugin services.
	 */
	private function init_services(): void {
		// 1. Google HTTP & OAuth layer.
		$this->http  = new Google\HttpClient();
		$this->oauth = new Google\OAuthManager( $this->http );

		// 2. Google Sheets & Drive API services.
		$this->sheets = new Google\SheetsService( $this->http, $this->oauth );
		$this->drive  = new Google\DriveService( $this->http, $this->oauth );

		// 3. Contact Form 7 integration services.
		$this->form_manager   = new Integration\FormManager();
		$this->upload_handler = new Integration\DriveUploadHandler( $this->drive );
		$this->interceptor    = new Integration\SubmissionInterceptor( $this->sheets, $this->upload_handler );

		// 4. Admin UI & Assets.
		$this->admin_menu = new Admin\AdminMenu();
		$this->assets     = new Admin\Assets( $this->oauth );

		// 5. REST API Server.
		$this->rest_server = new API\RestServer( $this->oauth, $this->sheets, $this->drive, $this->form_manager );
	}

	/**
	 * Initialize hooks and cron schedules.
	 */
	private function init_hooks(): void {
		// Schedule log cleanup daily if not scheduled.
		add_action( 'dvcf7_cleanup_logs', array( $this, 'cron_cleanup_logs' ) );
		if ( ! wp_next_scheduled( 'dvcf7_cleanup_logs' ) ) {
			wp_schedule_event( time() + DAY_IN_SECONDS, 'daily', 'dvcf7_cleanup_logs' );
		}
	}

	/**
	 * Cron job to cleanup old logs based on retention setting.
	 */
	public function cron_cleanup_logs(): void {
		$settings = get_option( DRIVEVAULT_CF7_OPTION_SETTINGS, array() );
		$days     = ! empty( $settings['log_retention_days'] ) ? (int) $settings['log_retention_days'] : 60;
		Database\LogRepository::cleanup_old_logs( $days );
	}
}
