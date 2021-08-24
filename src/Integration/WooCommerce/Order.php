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

use PixelgradeLT\Retailer\Integration\WooCommerce;

/**
 * WooCommerce Order utility wrapping class.
 *
 * @since 0.14.0
 */
final class Order {

	/**
	 * Given a WooCommerce order extract the corresponding purchased solutions details.
	 *
	 * @since 0.14.0
	 *
	 * @param mixed $order WP_Post object, WC_Order object, order ID.
	 *
	 * @return array List with each purchased solutions details.
	 */
	public static function get_purchased_solutions( $order ): array {
		$details = [];

		if ( ! $order instanceof \WC_Abstract_Order ) {
			$order = \WC_Order_Factory::get_order( $order );
		}

		if ( empty( $order ) ) {
			return $details;
		}

		foreach ( $order->get_items() as $item ) {
			// Be extra sure.
			if ( ! $item instanceof \WC_Order_Item_Product ) {
				continue;
			}

			// We want the base product, not variations.
			$product = \wc_get_product( $item->get_product_id() );
			if ( ! $product instanceof \WC_Product ) {
				continue;
			}

			// Determine if this product is linked to an LT Solution.
			$linked_solution_id = WooCommerce::get_product_linked_ltsolution( $product );
			if ( empty( $linked_solution_id ) ) {
				// No linked solution.
				continue;
			}

			$refunded_qty = \absint( $order->get_qty_refunded_for_item( $item->get_id() ) );
			$details[]    = [
				'status'        => ( $item->get_quantity() === $refunded_qty ) ? 'refunded' : 'active',
				'solution_id'   => $linked_solution_id,
				'product_id'    => $item->get_product_id(),
				'variation_id'  => $item->get_variation_id(),
				'order_id'      => $order->get_id(),
				'order_item_id' => $item->get_id(),
				'qty'           => $item->get_quantity(),
				'refunded_qty'  => $refunded_qty,
				'customer_id'   => $order->get_customer_id(),
			];
		}

		return $details;
	}
}
