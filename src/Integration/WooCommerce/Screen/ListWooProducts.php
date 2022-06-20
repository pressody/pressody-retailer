<?php
/**
 * WooCommerce products list screen provider.
 *
 * @since   0.14.0
 * @license GPL-2.0-or-later
 * @package Pressody
 */

declare ( strict_types=1 );

namespace Pressody\Retailer\Integration\WooCommerce\Screen;

use Cedaro\WP\Plugin\AbstractHookProvider;
use Pressody\Retailer\Integration\WooCommerce;
use Pressody\Retailer\SolutionManager;
use Pressody\Retailer\Utils\ArrayHelpers;

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

		// Add custom filters to post list.
		$this->add_filter( 'woocommerce_products_admin_list_table_filters', 'add_custom_filters', 10, 1 );
		// Handle custom filtering.
		add_filter( 'request', [ $this, 'request_query' ] );
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

		wp_enqueue_script( 'pressody_retailer-admin' );
		wp_enqueue_style( 'pressody_retailer-admin' );
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
				'linked_pdsolution' => esc_html__( 'Linked PD Solution', 'pressody_retailer' ),
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
		if ( ! in_array( $column, [ 'linked_pdsolution', ] ) ) {
			return;
		}

		$output = '—';
		// Find the solution that is linked to this product.
		$solution_id = WooCommerce::get_product_linked_pdsolution( $post_id );
		if ( ! empty( $solution_id ) ) {
			$output      = '<a class="package-list_link" href="' . esc_url( get_edit_post_link( $solution_id ) ) . '" title="' . esc_attr__( 'Edit PD Solution', 'pressody_retailer' ) . '">' . get_the_title( $solution_id ) . ' #' . $solution_id . '</a>';
		}

		echo $output;
	}

	/**
	 * @since 0.14.0
	 *
	 * @param array $filters
	 *
	 * @return array
	 */
	protected function add_custom_filters( array $filters ) {
		$filters['linked_to_pdsolution'] = [ $this, 'render_products_linked_to_pdsolution_filter' ];

		return $filters;
	}

	/**
	 * Render the linked to pdsolution filter for the list table.
	 *
	 * @since 0.14.0
	 */
	public function render_products_linked_to_pdsolution_filter() {
		$current_filter = isset( $_REQUEST['linked_to_pdsolution'] ) ? wc_clean( wp_unslash( $_REQUEST['linked_to_pdsolution'] ) ) : false; // WPCS: input var ok, sanitization ok.
		$options        = [
			'linked'    => esc_html__( 'Linked to a PD Solution', 'pressody_retailer' ),
			'notlinked' => esc_html__( 'Not linked to a PD Solution', 'pressody_retailer' ),
		];
		$output         = '<select name="linked_to_pdsolution"><option value="">' . esc_html__( 'Filter by PD Solution link', 'pressody_retailer' ) . '</option>';

		foreach ( $options as $value => $label ) {
			$output .= '<option ' . selected( $value, $current_filter, false ) . ' value="' . esc_attr( $value ) . '">' . esc_html( $label ) . '</option>';
		}

		$output .= '</select>';
		echo $output; // WPCS: XSS ok.
	}

	/**
	 * Handle any filters.
	 *
	 * @since 0.14.0
	 *
	 * @param array $query_vars Query vars.
	 *
	 * @return array
	 */
	public function request_query( $query_vars ) {
		global $typenow;

		if ( isset( $typenow ) && 'product' === $typenow ) {
			return $this->query_filters( $query_vars );
		}

		return $query_vars;
	}

	/**
	 * Handle any custom filters.
	 *
	 * @since 0.14.0
	 *
	 * @param array $query_vars Query vars.
	 *
	 * @return array
	 */
	protected function query_filters( $query_vars ) {
		// Linked to PD Solution filter.
		if ( ! empty( $_GET['linked_to_pdsolution'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			add_filter( 'posts_clauses', [ $this, 'filter_linked_to_pdsolution_post_clauses' ] );
		}

		return $query_vars;
	}

	/**
	 * Filter by linked to PD Solution.
	 *
	 * @since 0.14.0
	 *
	 * @param array $args Query args.
	 *
	 * @return array
	 */
	public function filter_linked_to_pdsolution_post_clauses( array $args ): array {
		global $wpdb;
		if ( ! empty( $_GET['linked_to_pdsolution'] ) && in_array( $_GET['linked_to_pdsolution'], ['linked', 'notlinked'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$args['join']  .= " LEFT JOIN {$wpdb->postmeta} ltmeta ON $wpdb->posts.ID = ltmeta.post_id AND ltmeta.meta_key = WooCommerce::PRODUCT_LINKED_TO_PDSOLUTION_META_KEY ";
			if ( 'linked' === $_GET['linked_to_pdsolution'] ) {
				$args['where'] .= " AND ltmeta.meta_value ";
			} else {
				$args['where'] .= " AND NOT coalesce(ltmeta.meta_value, FALSE) ";
			}
		}

		return $args;
	}
}
