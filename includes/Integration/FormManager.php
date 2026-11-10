<?php
namespace DriveVault\CF7\Integration;

defined( 'ABSPATH' ) || exit;

/**
 * Contact Form 7 Forms & Fields Manager.
 */
class FormManager {

	/**
	 * Meta field labels dictionary.
	 */
	const META_FIELDS = array(
		'submission_time' => 'Submission Timestamp',
		'form_title'      => 'Form Title',
		'form_id'         => 'Form ID',
		'submission_id'   => 'Submission ID',
		'ip_address'      => 'Submitter IP',
		'page_url'        => 'Submitted Page URL',
		'user_agent'      => 'User Agent',
	);

	/**
	 * Get list of all Contact Form 7 forms.
	 *
	 * @return array
	 */
	public function get_forms(): array {
		if ( ! class_exists( 'WPCF7_ContactForm' ) && ! defined( 'WPCF7_VERSION' ) ) {
			return array();
		}

		$posts = get_posts(
			array(
				'post_type'      => 'wpcf7_contact_form',
				'posts_per_page' => -1,
				'orderby'        => 'title',
				'order'          => 'ASC',
				'post_status'    => 'publish',
			)
		);

		$forms = array();
		if ( is_array( $posts ) ) {
			foreach ( $posts as $post ) {
				$mapping = \DriveVault\CF7\Database\MappingRepository::find_by_form( $post->ID );

				$forms[] = array(
					'id'                 => (int) $post->ID,
					'title'              => $post->post_title,
					'has_mapping'        => ! empty( $mapping ),
					'is_active'          => ! empty( $mapping['is_active'] ),
					'sheet_name'         => ! empty( $mapping['sheet_name'] ) ? $mapping['sheet_name'] : '',
					'tab_name'           => ! empty( $mapping['tab_name'] ) ? $mapping['tab_name'] : '',
					'drive_folder_name'  => ! empty( $mapping['drive_folder_name'] ) ? $mapping['drive_folder_name'] : '',
					'last_submitted_at'  => ! empty( $mapping['last_submitted_at'] ) ? $mapping['last_submitted_at'] : null,
				);
			}
		}

		return $forms;
	}

	/**
	 * Get fields scanned from a CF7 form template.
	 *
	 * @param int $form_id
	 * @return array
	 */
	public function get_form_fields( int $form_id ): array {
		if ( ! function_exists( 'wpcf7_contact_form' ) ) {
			return array();
		}

		$form = wpcf7_contact_form( absint( $form_id ) );
		if ( ! $form ) {
			return array();
		}

		$tags   = $form->scan_form_tags();
		$fields = array();

		foreach ( $tags as $tag ) {
			if ( empty( $tag->name ) ) {
				continue;
			}

			// Skip submit buttons and captcha response fields if not useful.
			if ( in_array( $tag->basetype, array( 'submit', 'captchac' ), true ) ) {
				continue;
			}

			$is_file     = 'file' === $tag->basetype;
			$is_required = str_contains( $tag->type, '*' );

			$label = ! empty( $tag->labels ) && ! empty( $tag->labels[0] ) ? $tag->labels[0] : '';
			if ( empty( $label ) ) {
				$label = ucwords( str_replace( array( '-', '_' ), ' ', $tag->name ) );
			}

			$fields[] = array(
				'name'        => $tag->name,
				'type'        => $tag->basetype,
				'label'       => $label,
				'is_required' => $is_required,
				'is_file'     => $is_file,
				'options'     => ! empty( $tag->values ) ? $tag->values : array(),
			);
		}

		return $fields;
	}

	/**
	 * Get standard meta field definitions.
	 *
	 * @return array
	 */
	public function get_meta_fields(): array {
		$meta = array();
		foreach ( self::META_FIELDS as $key => $label ) {
			$meta[] = array(
				'key'   => $key,
				'label' => $label,
			);
		}
		return $meta;
	}
}
