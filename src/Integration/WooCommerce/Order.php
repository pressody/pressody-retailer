<?php
/**
 * WooCommerce Order utility wrapping.
 *
 * @since   0.14.0
 * @license GPL-2.0-or-later
 * @package PixelgradeLT
 */

declare ( strict_types=1 );

namespace PixelgradeLT\Retailer\Integration\WooCommerce;

use Cedaro\WP\Plugin\AbstractHookProvider;
use function PixelgradeLT\Retailer\carbon_get_raw_post_meta;

/**
 * WooCommerce Order utility wrapping provider class.
 *
 * @since 0.14.0
 */
class Order extends AbstractHookProvider {

	/**
	 * Register hooks.
	 *
	 * @since 0.14.0
	 */
	public function register_hooks() {
		$this->add_filter( 'pixelgradelt_retailer/solution_id_data', 'add_solution_data', 5, 2 );
		$this->add_filter( 'pixelgradelt_retailer/solution_ids_by_query_args', 'handle_solution_query_args', 10, 2 );

		add_filter( 'woocommerce_product_data_store_cpt_get_products_query', [
			$this,
			'handle_custom_query_vars',
		], 10, 2 );
	}

	/**
	 * Add WooCommerce specific data to a solution.
	 *
	 * @since 0.14.0
	 *
	 * @param array $solution_data The solution data.
	 * @param int   $post_id       The solution post ID.
	 *
	 * @return array The modified solution ID data.
	 */
	protected function add_solution_data( array $solution_data, int $post_id ): array {

	}
}
