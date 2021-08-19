<?php
/**
 * WooCommerce plugin integration.
 *
 * @since   0.14.0
 * @license GPL-2.0-or-later
 * @package PixelgradeLT
 */

declare ( strict_types=1 );

namespace PixelgradeLT\Retailer\Integration;

use Cedaro\WP\Plugin\AbstractHookProvider;
use function PixelgradeLT\Retailer\carbon_get_raw_post_meta;

/**
 * WooCommerce plugin integration provider class.
 *
 * @since 0.14.0
 */
class WooCommerce extends AbstractHookProvider {

	const PRODUCT_LINKED_TO_LTSOLUTION_META_KEY = '_linked_to_ltsolution';

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
		/**
		 * THE LINKED PRODUCT IDS.
		 */
		$solution_data['woocommerce_products'] = carbon_get_post_meta( $post_id, 'solution_woocommerce_products' );
		// Add the un-formatted DB data, for reference.
		// Differences may arise from invalid products that are no longer part of the options list (CarbonFields removes them automatically).
		$solution_data['woocommerce_products_raw'] = carbon_get_raw_post_meta( $post_id, 'solution_woocommerce_products' );

		return $solution_data;
	}

	/**
	 * Handle WooCommerce specific solution query args.
	 *
	 * @since 0.14.0
	 *
	 * @param array $query_args The query args.
	 * @param array $args       The received args.
	 *
	 * @return array The modified solution ID data.
	 */
	protected function handle_solution_query_args( array $query_args, array $args ): array {
		if ( ! empty( $args['woocommerce_product_id'] ) && 'any' !== $args['woocommerce_product_id'] && is_numeric( $args['woocommerce_product_id'] ) ) {

			$args['woocommerce_product_id'] = absint( $args['woocommerce_product_id'] );

			$query_args['meta_query'][] = [
				'key'   => 'solution_woocommerce_products',
				'value' => $args['woocommerce_product_id'],
			];
		}

		return $query_args;
	}

	/**
	 * Handle custom query vars to get products.
	 *
	 * @since 0.14.0
	 *
	 * @param array $query      Args for WP_Query.
	 * @param array $query_vars Query vars from WC_Product_Query.
	 *
	 * @return array The modified $query
	 */
	public function handle_custom_query_vars( array $query, array $query_vars ): array {
		/*
		 * Handle the 'linked_to_ltsolution' custom query var behavior:
		 * - true : Only products that are linked to a solution;
		 * - false : Only products that are not linked to a solution;
		 * - int : Only products that are linked to a certain solution post ID.
		 */
		if ( isset( $query_vars['linked_to_ltsolution'] ) ) {
			if ( true === $query_vars['linked_to_ltsolution'] ) {
				$query['meta_query'][] = [
					'key'     => self::PRODUCT_LINKED_TO_LTSOLUTION_META_KEY,
					'compare' => 'EXISTS',
				];
				$query['meta_query'][] = [
					'key'     => self::PRODUCT_LINKED_TO_LTSOLUTION_META_KEY,
					'value'   => 0,
					'compare' => '>',
				];
			} else if ( false === $query_vars['linked_to_ltsolution'] ) {
				$query['meta_query'][] = [
					'relation' => 'OR',
					[
						'key'     => self::PRODUCT_LINKED_TO_LTSOLUTION_META_KEY,
						'compare' => 'NOT EXISTS',
					],
					[
						'key'     => self::PRODUCT_LINKED_TO_LTSOLUTION_META_KEY,
						'value'   => 0,
						'compare' => '=',
					],
				];
			} else if ( absint( $query_vars['linked_to_ltsolution'] ) > 0 ) {
				$query['meta_query'][] = [
					'key'     => self::PRODUCT_LINKED_TO_LTSOLUTION_META_KEY,
					'compare' => 'EXISTS',
				];
				$query['meta_query'][] = [
					'key'     => self::PRODUCT_LINKED_TO_LTSOLUTION_META_KEY,
					'value'   => absint( $query_vars['linked_to_ltsolution'] ),
					'compare' => '=',
				];
			}
		}

		/*
		 * Handle the 'not_linked_to_ltsolution' custom query var behavior:
		 * - int : Only products that are NOT linked to a certain solution post ID.
		 */
		if ( ! empty( $query_vars['not_linked_to_ltsolution'] ) && absint( $query_vars['not_linked_to_ltsolution'] ) > 0 ) {
			$query['meta_query'][] = [
				'relation' => 'OR',
				[
					'key'     => self::PRODUCT_LINKED_TO_LTSOLUTION_META_KEY,
					'compare' => 'NOT EXISTS',
				],
				[
					'key'     => self::PRODUCT_LINKED_TO_LTSOLUTION_META_KEY,
					'value'   => 0,
					'compare' => '=',
				],
				[
					'key'     => self::PRODUCT_LINKED_TO_LTSOLUTION_META_KEY,
					'value'   => absint( $query_vars['not_linked_to_ltsolution'] ),
					'compare' => '!=',
				],
			];
		}

		return $query;
	}

	/**
	 * Given a product, return its linked LT Solution
	 *
	 * @since 0.14.0
	 *
	 * @param mixed $product WC_Product|WP_Post|int|bool $product Product instance, post instance, numeric or false to use global $post.
	 *
	 * @return int|false The linked LT Solution post ID or false on no linked LT Solution or failure.
	 */
	public static function get_product_linked_ltsolution( $product = false ) {
		$product = wc_get_product( $product );
		if ( empty( $product ) ) {
			return false;
		}

		$solution_id = get_post_meta( $product->get_id(), WooCommerce::PRODUCT_LINKED_TO_LTSOLUTION_META_KEY, true );
		if ( empty( $solution_id ) ) {
			return false;
		}

		return absint( $solution_id );
	}
}
