<?php
namespace DriveVault\CF7\API;

defined( 'ABSPATH' ) || exit;

use DriveVault\CF7\Google\OAuthManager;
use DriveVault\CF7\Google\SheetsService;
use DriveVault\CF7\Google\DriveService;
use DriveVault\CF7\Integration\FormManager;

/**
 * REST API Server Coordinator.
 */
class RestServer {

	const NAMESPACE = 'drivevault-cf7/v1';

	/**
	 * Controllers list.
	 */
	protected array $controllers = array();

	/**
	 * Constructor.
	 *
	 * @param OAuthManager  $oauth
	 * @param SheetsService $sheets
	 * @param DriveService  $drive
	 * @param FormManager   $forms
	 */
	public function __construct( OAuthManager $oauth, SheetsService $sheets, DriveService $drive, FormManager $forms ) {
		$this->controllers = array(
			new Controllers\AuthController( $oauth ),
			new Controllers\FormsController( $forms ),
			new Controllers\GoogleController( $sheets, $drive ),
			new Controllers\MappingsController( $sheets ),
			new Controllers\LogsController( $sheets ),
			new Controllers\SettingsController(),
		);

		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Register all REST API endpoints.
	 */
	public function register_routes(): void {
		foreach ( $this->controllers as $controller ) {
			$controller->register_routes( self::NAMESPACE );
		}
	}
}
