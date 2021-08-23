<?php
/**
 * Purchased Solutions API - PurchasedSolution Object
 *
 * @since   0.14.0
 * @license GPL-2.0-or-later
 * @package PixelgradeLT
 */

declare ( strict_types = 1 );

namespace PixelgradeLT\Retailer;

/**
 * PurchasedSolution Class.
 *
 * @since 0.14.0
 *
 * @property int $id
 * @property int $solution_id
 * @property int $user_id
 * @property int $order_id
 * @property int $order_item_id
 * @property int $composition_id
 * @property string $date_created
 * @property string $date_modified
 */
class PurchasedSolution extends Database\Rows\PurchasedSolution {

	/**
	 * Purchased Solution ID.
	 *
	 * @since  0.14.0
	 * @access protected
	 * @var    int
	 */
	protected int $id;

	/**
	 * Solution ID.
	 *
	 * @since  0.14.0
	 * @access protected
	 * @var    int
	 */
	protected int $solution_id;

	/**
	 * User ID.
	 *
	 * @since  0.14.0
	 * @access protected
	 * @var    int
	 */
	protected int $user_id;

	/**
	 * Order ID.
	 *
	 * @since  0.14.0
	 * @access protected
	 * @var    int
	 */
	protected int $order_id;

	/**
	 * Order Item ID.
	 *
	 * @since  0.14.0
	 * @access protected
	 * @var    int
	 */
	protected int $order_item_id;

	/**
	 * Composition ID.
	 *
	 * @since  0.14.0
	 * @access protected
	 * @var    int
	 */
	protected int $composition_id;

	/**
	 * Date purchased solution was created.
	 *
	 * @since  0.14.0
	 * @access protected
	 * @var    string
	 */
	protected string $date_created;

	/**
	 * Date purchased solution was last modified.
	 *
	 * @since  0.14.0
	 * @access protected
	 * @var    string
	 */
	protected string $date_modified;
}
