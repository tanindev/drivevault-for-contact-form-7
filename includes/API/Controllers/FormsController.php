<?php
namespace DriveVault\CF7\API\Controllers;

defined( 'ABSPATH' ) || exit;

use DriveVault\CF7\Integration\FormManager;

/**
 * REST API Forms Controller.
 */
class FormsController {

	/**
	 * Form Manager.
	 */
	protected FormManager $forms;

	/**
	 * Constructor.
	 *
	 * @param FormManager $forms
	 */
	public function __construct( FormManager $forms ) {
		$this->forms = $forms;
	}

	/**
	 * Register REST routes.
	 *
	 * @param string $namespace
	 */
	public function register_routes( string $namespace ): void {
		register_rest_route(
			$namespace,
			'/forms',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_forms' ),
				'permission_callback' => array( $this, 'permissions_check' ),
			)
		);

		register_rest_route(
			$namespace,
			'/forms/(?P<id>\d+)/fields',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_fields' ),
				'permission_callback' => array( $this, 'permissions_check' ),
				'args'                => array(
					'id' => array(
						'validate_callback' => function ( $param ) {
							return is_numeric( $param );
						},
					),
				),
			)
		);
	}

	/**
	 * Permissions check callback.
	 *
	 * @return bool
	 */
	public function permissions_check(): bool {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Get list of all forms.
	 *
	 * @return \WP_REST_Response
	 */
	public function get_forms(): \WP_REST_Response {
		$list = $this->forms->get_forms();
		return rest_ensure_response( $list );
	}

	/**
	 * Get fields for specific form.
	 *
	 * @param \WP_REST_Request $request
	 * @return \WP_REST_Response
	 */
	public function get_fields( \WP_REST_Request $request ): \WP_REST_Response {
		$id     = absint( $request->get_param( 'id' ) );
		$fields = $this->forms->get_form_fields( $id );
		$meta   = $this->forms->get_meta_fields();

		return rest_ensure_response(
			array(
				'form_id' => $id,
				'fields'  => $fields,
				'meta'    => $meta,
			)
		);
	}
}
