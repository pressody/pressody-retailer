<?php
/**
 * List Solutions (All Solutions) screen provider.
 *
 * @since   0.1.0
 * @license GPL-2.0-or-later
 * @package Pressody
 */

declare ( strict_types=1 );

namespace Pressody\Retailer\Screen;

use Cedaro\WP\Plugin\AbstractHookProvider;
use Pressody\Retailer\SolutionManager;
use Pressody\Retailer\Utils\ArrayHelpers;

/**
 * List Solutions screen provider class.
 *
 * @since 0.1.0
 */
class ListSolutions extends AbstractHookProvider {

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
	 * @param SolutionManager $solution_manager Solutions manager.
	 */
	public function __construct(
		SolutionManager $solution_manager
	) {
		$this->solution_manager = $solution_manager;
	}

	/**
	 * Register hooks.
	 *
	 * @since 0.1.0
	 */
	public function register_hooks() {
		// Assets.
		add_action( 'load-edit.php', [ $this, 'load_screen' ] );

		// Logic.
		// Show a dropdown to filter the posts list by the custom taxonomy.
		$this->add_action( 'restrict_manage_posts', 'output_admin_list_filters' );

		// Add custom columns to post list.
		$this->add_action( 'manage_' . $this->solution_manager::POST_TYPE . '_posts_columns', 'add_custom_columns' );
		$this->add_action( 'manage_' . $this->solution_manager::POST_TYPE . '_posts_custom_column', 'populate_custom_columns', 10, 2 );
	}

	/**
	 * Output filters in the "All Posts" screen.
	 *
	 * @param string $post_type The current post type.
	 */
	protected function output_admin_list_filters( string $post_type ) {
		if ( $this->solution_manager::POST_TYPE !== $post_type ) {
			return;
		}

		$type_taxonomy = get_taxonomy( $this->solution_manager::TYPE_TAXONOMY );
		wp_dropdown_categories( array(
			'show_option_all' => sprintf( __( 'All %s', 'pressody_retailer' ), $type_taxonomy->label ),
			'orderby'         => 'term_id',
			'order'           => 'ASC',
			'hide_empty'      => false,
			'hide_if_empty'   => true,
			'selected'        => filter_input( INPUT_GET, $type_taxonomy->query_var, FIPDER_SANITIZE_STRING ),
			'hierarchical'    => false,
			'name'            => $type_taxonomy->query_var,
			'taxonomy'        => $type_taxonomy->name,
			'value_field'     => 'slug',
		) );

		$category_taxonomy = get_taxonomy( $this->solution_manager::CATEGORY_TAXONOMY );
		wp_dropdown_categories( array(
			'show_option_all' => sprintf( __( 'All %s', 'pressody_retailer' ), $category_taxonomy->label ),
			'orderby'         => 'term_id',
			'order'           => 'ASC',
			'hide_empty'      => false,
			'hide_if_empty'   => true,
			'selected'        => filter_input( INPUT_GET, $category_taxonomy->query_var, FIPDER_SANITIZE_STRING ),
			'hierarchical'    => true,
			'name'            => $category_taxonomy->query_var,
			'taxonomy'        => $category_taxonomy->name,
			'value_field'     => 'slug',
		) );
	}

	/**
	 * Set up the screen.
	 *
	 * @since 0.1.0
	 */
	public function load_screen() {
		$screen = get_current_screen();
		if ( $this->solution_manager::POST_TYPE !== $screen->post_type ) {
			return;
		}

		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
	}

	/**
	 * Enqueue assets.
	 *
	 * @since 0.1.0
	 */
	public function enqueue_assets() {
		wp_enqueue_script( 'pressody_retailer-admin' );
		wp_enqueue_style( 'pressody_retailer-admin' );
	}

	protected function add_custom_columns( array $columns ): array {
		$screen = get_current_screen();
		if ( $this->solution_manager::POST_TYPE !== $screen->post_type ) {
			return $columns;
		}

		// Insert after the title columns for dependencies.
		$columns = ArrayHelpers::insertAfterKey( $columns, 'title',
			[
				'solution_required_parts' => esc_html__( 'Required Parts', 'pressody_retailer' ),
				'solution_required_solutions' => esc_html__( 'Required Solutions', 'pressody_retailer' ),
				'solution_excluded_solutions' => esc_html__( 'Excluded Solutions', 'pressody_retailer' ),
			]
		);

		return $columns;
	}

	protected function populate_custom_columns( string $column, int $post_id ): void {
		if ( ! in_array( $column, [
			'solution_required_parts',
			'solution_required_solutions',
			'solution_excluded_solutions',
		] ) ) {
			return;
		}

		$output = '—';

		$solution_data = $this->solution_manager->get_solution_id_data( $post_id );
		if ( 'solution_required_parts' === $column && ! empty( $solution_data['required_pdrecords_parts'] ) ) {
			$list = [];
			foreach ( $solution_data['required_pdrecords_parts'] as $part_details ) {
				$list[] = $part_details['package_name'] . ':' . $part_details['version_range'];
			}

			$output = implode( '<br>' . PHP_EOL, $list );
		}

		if ( 'solution_required_solutions' === $column && ! empty( $solution_data['required_solutions'] ) ) {
			$list = [];
			foreach ( $solution_data['required_solutions'] as $solution_details ) {
				$list[] = '<a class="package-list_link" href="' . esc_url( get_edit_post_link( $solution_details['managed_post_id'] ) ) . '" title="Edit Required PD Solution">' . get_the_title( $solution_details['managed_post_id'] ) . ' (' . $solution_details['pseudo_id'] . ')</a>';
			}

			$output = implode( '<br>' . PHP_EOL, $list );
		}

		if ( 'solution_excluded_solutions' === $column && ! empty( $solution_data['excluded_solutions'] ) ) {
			$list = [];
			foreach ( $solution_data['excluded_solutions'] as $solution_details ) {
				$list[] = '<a class="package-list_link" href="' . esc_url( get_edit_post_link( $solution_details['managed_post_id'] ) ) . '" title="Edit Excluded PD Solution">' . get_the_title( $solution_details['managed_post_id'] ) . ' (' . $solution_details['pseudo_id'] . ')</a>';
			}

			$output = implode( '<br>' . PHP_EOL, $list );
		}

		echo $output;
	}
}
