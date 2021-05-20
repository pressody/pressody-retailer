<?php
/**
 * The Solution custom post type.
 *
 * @since   0.1.0
 * @license GPL-2.0-or-later
 * @package PixelgradeLT
 */

declare ( strict_types=1 );

namespace PixelgradeLT\Retailer\PostType;

use Cedaro\WP\Plugin\AbstractHookProvider;
use PixelgradeLT\Retailer\SolutionType\SolutionTypes;
use PixelgradeLT\Retailer\SolutionManager;

/**
 * The Solution custom post type provider: provides the interface for and stores the information about each solution.
 *
 * @since 0.1.0
 */
class SolutionPostType extends AbstractHookProvider {

	/**
	 * Solution manager.
	 *
	 * @var SolutionManager
	 */
	protected SolutionManager $solution_manager;

	/**
	 * Constructor.
	 *
	 * @since 0.1.0
	 *
	 * @param SolutionManager $solution_manager Solution manager.
	 */
	public function __construct(
			SolutionManager $solution_manager
	) {
		$this->solution_manager = $solution_manager;
	}

	public function register_hooks() {
		/*
		 * HANDLE THE CUSTOM POST TYPE LOGIC.
		 */
		$this->add_action( 'init', 'register_post_type' );

		/*
		 * HANDLE THE CUSTOM TAXONOMY LOGIC.
		 */
		$this->add_action( 'init', 'register_solution_category_taxonomy', 12 );
		$this->add_action( 'init', 'register_solution_type_taxonomy', 14 );
		$this->add_action( 'init', 'insert_solution_type_taxonomy_terms', 16 );
		$this->add_action( 'init', 'register_package_keyword_taxonomy', 18 );

		$this->add_action( 'save_post_' . $this->solution_manager::POST_TYPE, 'save_solution_type_meta_box' );
	}

	/**
	 * Register the custom package post type as defined by the package manager.
	 *
	 * @since 0.1.0
	 */
	protected function register_post_type() {
		register_post_type( $this->solution_manager::POST_TYPE, $this->solution_manager->get_solution_post_type_args() );
	}

	/**
	 * Register the taxonomy for the solution category as defined by the solution manager.
	 *
	 * @since 0.1.0
	 */
	protected function register_solution_category_taxonomy() {
		if ( taxonomy_exists( $this->solution_manager::CATEGORY_TAXONOMY ) ) {
			register_taxonomy_for_object_type(
					$this->solution_manager::CATEGORY_TAXONOMY,
					$this->solution_manager::POST_TYPE
			);
		} else {
			register_taxonomy(
					$this->solution_manager::CATEGORY_TAXONOMY,
					[ $this->solution_manager::POST_TYPE ],
					$this->solution_manager->get_solution_category_taxonomy_args()
			);
		}
	}

	/**
	 * Register the taxonomy for the solution type as defined by the solution manager.
	 *
	 * @since 0.1.0
	 */
	protected function register_solution_type_taxonomy() {
		if ( taxonomy_exists( $this->solution_manager::TYPE_TAXONOMY ) ) {
			register_taxonomy_for_object_type(
					$this->solution_manager::TYPE_TAXONOMY,
					$this->solution_manager::POST_TYPE
			);
		} else {
			register_taxonomy(
					$this->solution_manager::TYPE_TAXONOMY,
					[ $this->solution_manager::POST_TYPE ],
					$this->solution_manager->get_solution_type_taxonomy_args( [
							'meta_box_cb' => [
									$this,
									'solution_type_meta_box',
							],
					] )
			);
		}
	}

	/**
	 * Insert the terms for the solution type taxonomy defined by the solution manager.
	 *
	 * @since 0.1.0
	 */
	protected function insert_solution_type_taxonomy_terms() {
		// Force the insertion of needed terms matching the SOLUTION TYPES.
		foreach ( SolutionTypes::DETAILS as $term_slug => $term_details ) {
			if ( ! term_exists( $term_slug, $this->solution_manager::TYPE_TAXONOMY ) ) {
				wp_insert_term( $term_details['name'], $this->solution_manager::TYPE_TAXONOMY, [
						'slug'        => $term_slug,
						'description' => $term_details['description'],
				] );
			}
		}
	}

	/**
	 * Register the taxonomy for the solution keywords as defined by the solution manager.
	 *
	 * @since 0.1.0
	 */
	protected function register_package_keyword_taxonomy() {
		if ( taxonomy_exists( $this->solution_manager::KEYWORD_TAXONOMY ) ) {
			register_taxonomy_for_object_type(
					$this->solution_manager::KEYWORD_TAXONOMY,
					$this->solution_manager::POST_TYPE
			);
		} else {
			register_taxonomy(
					$this->solution_manager::KEYWORD_TAXONOMY,
					[ $this->solution_manager::POST_TYPE ],
					$this->solution_manager->get_solution_keyword_taxonomy_args()
			);
		}
	}

	/**
	 * Display Solution Type meta box
	 *
	 * @param \WP_Post $post
	 */
	public function solution_type_meta_box( \WP_Post $post ) {
		$terms = get_terms( $this->solution_manager::TYPE_TAXONOMY, array(
				'hide_empty' => false,
				'orderby'    => 'term_id',
				'order'      => 'ASC',
		) );

		$solution_type      = wp_get_object_terms( $post->ID, $this->solution_manager::TYPE_TAXONOMY, array(
				'orderby' => 'term_id',
				'order'   => 'ASC',
		) );
		$current_solution_type_name = '';

		if ( ! is_wp_error( $solution_type ) ) {
			if ( isset( $solution_type[0] ) && isset( $solution_type[0]->name ) ) {
				$current_solution_type_name = $solution_type[0]->name;
			}
		}

		// Use the default type, if nothing else.
		if ( empty( $current_solution_type_name ) ) {
			$current_solution_type_name = SolutionTypes::BASIC;
		}

		foreach ( $terms as $term ) { ?>
			<label title="<?php esc_attr_e( $term->name ); ?>">
				<input type="radio"
				       name="<?php esc_attr_e( $this->solution_manager::TYPE_TAXONOMY_SINGULAR ); ?>"
				       value="<?php esc_attr_e( $term->name ); ?>" <?php checked( $term->name, $current_solution_type_name ); ?>>
				<span><?php esc_html_e( $term->name ); ?></span>
			</label><br>
			<?php
		}
	}

	/**
	 * Save the solution type box results.
	 *
	 * @param int $post_id The ID of the post that's being saved.
	 */
	protected function save_solution_type_meta_box( int $post_id ) {
		$post = get_post( $post_id );

		if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE )
		     || ( defined( 'DOING_AJAX' ) && DOING_AJAX )
		     || ! current_user_can( 'edit_post', $post_id )
		     || false !== wp_is_post_revision( $post_id )
		     || 'trash' == get_post_status( $post_id )
		     || isset( $post->post_status ) && 'auto-draft' == $post->post_status ) {
			return;
		}

		$solution_type = isset( $_POST[ $this->solution_manager::TYPE_TAXONOMY_SINGULAR ] ) ? sanitize_text_field( $_POST[ $this->solution_manager::TYPE_TAXONOMY_SINGULAR ] ) : '';

		// A valid type is required, so force the default one.
		if ( empty( $solution_type ) ) {
			$solution_type = SolutionTypes::BASIC;
		}

		$term = get_term_by( 'name', $solution_type, $this->solution_manager::TYPE_TAXONOMY );
		if ( ! empty( $term ) && ! is_wp_error( $term ) ) {
			wp_set_object_terms( $post_id, $term->term_id, $this->solution_manager::TYPE_TAXONOMY, false );
		}
	}
}
