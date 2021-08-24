<?php
/**
 * Purchased Solution Database Object Class.
 *
 * @since   0.14.0
 * @license GPL-2.0-or-later
 * @package PixelgradeLT
 */

declare ( strict_types=1 );

namespace PixelgradeLT\Retailer\Database\Rows;

use BerlinDB\Database\Row;
use PixelgradeLT\Retailer\PurchasedSolutionManager;

/**
 * Purchased Solution database row class.
 *
 * This class exists solely to encapsulate database schema changes, to help
 * separate the needs of the application layer from the requirements of the
 * database layer.
 *
 * For example, if a database column is renamed or a return value needs to be
 * formatted differently, this class will make sure old values are still
 * supported and new values do not conflict.
 *
 * @since 0.14.0
 */
class PurchasedSolution extends Row {

	/**
	 * Purchased Solution constructor.
	 *
	 * @since 0.14.0
	 *
	 * @param $item
	 */
	public function __construct( $item ) {
		parent::__construct( $item );

		// This is optional, but recommended. Set the type of each column, and prepare.
		$this->id             = (int) $this->id;
		$this->status         = in_array( $this->status, array_keys( PurchasedSolutionManager::$STATUSES ) ) ? $this->status : 'invalid';
		$this->solution_id    = (int) $this->solution_id;
		$this->user_id        = (int) $this->user_id;
		$this->order_id       = (int) $this->order_id;
		$this->order_item_id  = (int) $this->order_item_id;
		$this->composition_id = (int) $this->composition_id;
		//		$this->date_created   = ( false === $this->date_created ) ? 0 : strtotime( $this->date_created );
		//		$this->date_modified  = ( false === $this->date_modified ) ? 0 : strtotime( $this->date_modified );
	}
}
