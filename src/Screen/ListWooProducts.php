<?php
/**
 * WooCommerce products list screen provider.
 *
 * @package PixelgradeLT
 * @license GPL-2.0-or-later
 * @since 0.14.0
 */

declare ( strict_types = 1 );

namespace PixelgradeLT\Retailer\Screen;

use Cedaro\WP\Plugin\AbstractHookProvider;
use PixelgradeLT\Retailer\SolutionManager;
use PixelgradeLT\Retailer\Utils\ArrayHelpers;

/**
 * WooCommerce products list screen provider class.
 *
 * @since 0.14.0
 */
class ListWooProducts extends AbstractHookProvider {
	/**
	 * Solution manager.
	 *
	 * @var SolutionManager
	 */
	protected SolutionManager $solution_manager;

	/**
	 * Constructor.
	 *
	 * @since 0.14.0
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
	 * @since 0.14.0
	 */
	public function register_hooks() {
		// Assets.
		add_action( 'load-edit.php', [ $this, 'load_screen' ] );

		// Add custom columns to post list.
		$this->add_action( 'manage_product_posts_columns', 'add_custom_columns' );
		$this->add_action( 'manage_product_posts_custom_column', 'populate_custom_columns', 10, 2 );
	}

	/**
	 * Set up the screen.
	 *
	 * @since 0.14.0
	 */
	public function load_screen() {
		$screen = get_current_screen();
		if ( 'product' !== $screen->post_type ) {
			return;
		}

		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
	}

	/**
	 * Enqueue assets for the screen.
	 *
	 * @since 0.14.0
	 */
	public function enqueue_assets() {
		$screen = get_current_screen();
		if ( 'product' !== $screen->post_type ) {
			return;
		}

		wp_enqueue_script( 'pixelgradelt_retailer-admin' );
		wp_enqueue_style( 'pixelgradelt_retailer-admin' );
	}

	/**
	 * @since 0.14.0
	 *
	 * @param array $columns
	 *
	 * @return array
	 */
	protected function add_custom_columns( array $columns ): array {
		$screen = get_current_screen();
		if ( 'product' !== $screen->post_type ) {
			return $columns;
		}

		// Insert after the tags column.
		$columns = ArrayHelpers::insertAfterKey( $columns, 'product_tag',
			[
				'linked_ltsolution' => esc_html__( 'Linked LT Solution', 'pixelgradelt_retailer' ),
			]
		);

		return $columns;
	}

	/**
	 * @since 0.14.0
	 *
	 * @param string $column
	 * @param int    $post_id
	 */
	protected function populate_custom_columns( string $column, int $post_id ): void {
		if ( ! in_array( $column, [ 'linked_ltsolution', ] ) ) {
			return;
		}

		$output = '—';
		// Find a solution that is linked to this product. Since a product can only be linked to only one solution, we are safe.
		$solution_id = $this->solution_manager->get_solution_ids_by( ['woocommerce_product_id' => $post_id ] );
		if ( ! empty( $solution_id ) ) {
			$solution_id = reset( $solution_id );
			$output = '<a class="package-list_link" href="' . esc_url( get_edit_post_link( $solution_id ) ) . '" title="Edit LT Solution">' . get_the_title( $solution_id ) . ' #' . $solution_id . '</a>';
		}

		echo $output;
	}
}
