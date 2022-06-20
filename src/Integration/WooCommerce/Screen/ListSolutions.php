<?php
/**
 * List Solutions (All Solutions) screen provider.
 *
 * @since   0.14.0
 * @license GPL-2.0-or-later
 * @package Pressody
 */

declare ( strict_types=1 );

namespace Pressody\Retailer\Integration\WooCommerce\Screen;

use Cedaro\WP\Plugin\AbstractHookProvider;
use Pressody\Retailer\SolutionManager;
use Pressody\Retailer\Utils\ArrayHelpers;

/**
 * List Solutions screen provider class.
 *
 * @since 0.14.0
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
		// Add custom columns to post list.
		$this->add_action( 'manage_' . $this->solution_manager::POST_TYPE . '_posts_columns', 'add_custom_columns', 20 );
		$this->add_action( 'manage_' . $this->solution_manager::POST_TYPE . '_posts_custom_column', 'populate_custom_columns', 10, 2 );
	}

	protected function add_custom_columns( array $columns ): array {
		$screen = get_current_screen();
		if ( $this->solution_manager::POST_TYPE !== $screen->post_type ) {
			return $columns;
		}

		// Insert after the title columns for dependencies.
		$columns = ArrayHelpers::insertAfterKey( $columns, 'title',
			[
				'solution_linked_products' => esc_html__( 'Linked Products', 'pressody_retailer' ),
			]
		);

		return $columns;
	}

	protected function populate_custom_columns( string $column, int $post_id ): void {
		if ( ! in_array( $column, [
			'solution_linked_products',
		] ) ) {
			return;
		}

		$output = '—';

		$solution_data = $this->solution_manager->get_solution_id_data( $post_id );
		if ( 'solution_linked_products' === $column && ! empty( $solution_data['woocommerce_products'] ) ) {
			$list = [];
			foreach ( $solution_data['woocommerce_products'] as $product_id ) {
				$list[] = '<a class="package-list_link" href="' . get_edit_post_link( $product_id ) . '" title="' . esc_attr__( 'Edit WooCommerce Product', 'pressody_retailer' ) . '">' . get_the_title( $product_id ) . '</a>';
			}

			$output = implode( '<br>' . PHP_EOL, $list );
		}

		echo $output;
	}
}
