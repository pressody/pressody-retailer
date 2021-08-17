<?php
/**
 * WooCommerce plugin integration.
 *
 * @package PixelgradeLT
 * @license GPL-2.0-or-later
 * @since 0.14.0
 */

declare ( strict_types = 1 );

namespace PixelgradeLT\Retailer\Integration;

use Cedaro\WP\Plugin\AbstractHookProvider;
use function PixelgradeLT\Retailer\carbon_get_raw_post_meta;

/**
 * WooCommerce plugin integration provider class.
 *
 * @since 0.14.0
 */
class WooCommerce extends AbstractHookProvider {

	/**
	 * Register hooks.
	 *
	 * @since 0.14.0
	 */
	public function register_hooks() {
		$this->add_filter( 'pixelgradelt_retailer/solution_id_data', 'add_solution_data', 5, 2 );
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
		/**
		 * THE LINKED PRODUCT IDS.
		 */
		$solution_data['woocommerce_products'] = carbon_get_post_meta( $post_id, 'solution_woocommerce_products' );
		// Add the un-formatted DB data, for reference.
		// Differences may arise from invalid products that are no longer part of the options list (CarbonFields removes them automatically).
		$solution_data['woocommerce_products_raw'] = carbon_get_raw_post_meta( $post_id, 'solution_woocommerce_products' );

		return $solution_data;
	}
}
