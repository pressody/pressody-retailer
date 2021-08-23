<?php
/**
 * Log Database Object Class.
 *
 * @since   0.14.0
 * @license GPL-2.0-or-later
 * @package PixelgradeLT
 */

declare ( strict_types=1 );

namespace PixelgradeLT\Retailer\Database\Rows;

use BerlinDB\Database\Row;

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
class Log extends Row {

	/**
	 * Log constructor.
	 *
	 * @since 0.14.0
	 *
	 * @param $item
	 */
	public function __construct( $item ) {
		parent::__construct( $item );

		// This is optional, but recommended. Set the type of each column, and prepare.
		$this->id             = (int) $this->id;
	}
}
