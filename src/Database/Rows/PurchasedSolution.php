<?php
/**
 * Purchased Solution Database Object Class.
 *
 * @since   0.14.0
 * @license GPL-2.0-or-later
 * @package Pressody
 */

/*
 * This file is part of a Pressody module.
 *
 * This Pressody module is free software: you can redistribute it and/or modify it under the terms of the
 * GNU General Public License as published by the Free Software Foundation, either version 2 of the License,
 * or (at your option) any later version.
 *
 * This Pressody module is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY;
 * without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along with this Pressody module.
 * If not, see <https://www.gnu.org/licenses/>.
 *
 * Copyright (c) 2021, 2022 Vlad Olaru (vlad@thinkwritecode.com)
 */

declare ( strict_types=1 );

namespace Pressody\Retailer\Database\Rows;

use BerlinDB\Database\Row;
use Pressody\Retailer\PurchasedSolutionManager;

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
