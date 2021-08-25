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
		$purchased_solutions = [];

		if ( ! $order instanceof \WC_Abstract_Order ) {
			$order = \WC_Order_Factory::get_order( $order );
		}

		if ( empty( $order ) ) {
			return $purchased_solutions;
		}

		foreach ( $order->get_items() as $order_item ) {
			$purchased_solutions[] = self::get_item_purchased_solution( $order_item, $order );
		}

		// Remove any null entries (meaning failure to get a purchased solution for a given order item).
		return array_filter( $purchased_solutions );
	}

	/**
	 * Given a WooCommerce order item extract the corresponding purchased solution's details.
	 *
	 * IMPORTANT: Right now we work under the assumption that
	 *            a WooCommerce product leads to the purchase of a single solution!
	 *            See WooCommerce\Screen\EditSolution::get_available_products().
	 *
	 * @since 0.14.0
	 *
	 * @param mixed          $order_item \WC_Order_Item object, order_item ID.
	 * @param \WC_Order|null $order      Optional. The order object the order item is part of.
	 *
	 * @return array|null The purchased solutions details or null on failure.
	 */
	public static function get_item_purchased_solution( $order_item, \WC_Order $order = null ): ?array {

		if ( ! $order_item instanceof \WC_Order_Item ) {
			$order_item = \WC_Order_Factory::get_order_item( $order_item );
		}

		// We are only interested in Product order items (line_items), not taxes, totals or the like.
		if ( empty( $order_item ) || empty( $order_item->get_id() ) || ! $order_item instanceof \WC_Order_Item_Product ) {
			return null;
		}

		// We want the base product, not variations.
		$product = \wc_get_product( $order_item->get_product_id() );
		if ( ! $product instanceof \WC_Product ) {
			return null;
		}

		// Determine if this product is linked to an LT Solution.
		$linked_solution_id = WooCommerce::get_product_linked_ltsolution( $product );
		if ( empty( $linked_solution_id ) ) {
			// No linked solution.
			return null;
		}

		if ( empty( $order ) ) {
			$order = $order_item->get_order();
		}
		// We need a valid order (not to deal with refund orders like \WC_Order_Refund).
		if ( empty( $order ) || ! $order instanceof \WC_Order ) {
			return null;
		}


		// If the order this order item is part of is fully refunded, all items are refunded even if WooCommerce will not show it on a per-item basis.
		$refunded_qty = ( 'refunded' === $order->get_status() ) ? $order_item->get_quantity() : \absint( $order->get_qty_refunded_for_item( $order_item->get_id() ) );
		$status       = ( $order_item->get_quantity() === $refunded_qty ) ? 'refunded' : ( in_array( $order->get_status(), [
			'cancelled',
			'failed',
		] ) ? 'invalid' : 'active' );

		return [
			'status'        => $status,
			'solution_id'   => $linked_solution_id,
			'product_id'    => $order_item->get_product_id(),
			'variation_id'  => $order_item->get_variation_id(),
			'order_id'      => $order->get_id(),
			'order'         => $order,
			'order_item_id' => $order_item->get_id(),
			'qty'           => $order_item->get_quantity(),
			'refunded_qty'  => $refunded_qty,
			'customer_id'   => $order->get_customer_id(),
		];
	}
}
