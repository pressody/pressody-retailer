<?php
/**
 * Purchased Solutions Table.
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

namespace Pressody\Retailer\Database\Tables;

use BerlinDB\Database\Table;

/**
 * Set up the global "purchased_solutions" database table
 *
 * @since 0.14.0
 */
final class PurchasedSolutions extends Table {

	/**
	 * Table name.
	 *
	 * @access protected
	 * @since  0.14.0
	 * @var string
	 */
	protected $name = 'pd_retailer_purchased_solutions';

	/**
	 * Database version.
	 *
	 * @access protected
	 * @since  0.14.0
	 * @var int
	 */
	protected $version = 20220620;

	/**
	 * Array of upgrade versions and methods.
	 *
	 * The key is the version to which we upgrade, and the value is the callable to run.
	 * If the value is not a global callable, it will fallback to a class a method with the value as name, prefixed by `__`.
	 *
	 * @see BerlinDB\Database\Table::get_callable()
	 *
	 * @access protected
	 * @since  0.14.0
	 * @var    array
	 */
	protected $upgrades = array(
		'20210825' => 20210825,
	);

	/**
	 * Set up the database schema.
	 *
	 * @access protected
	 * @since  0.14.0
	 * @return void
	 */
	protected function set_schema() {
		$this->schema = "id bigint(20) unsigned NOT NULL auto_increment,
		status varchar(20) NOT NULL default 'ready',
		solution_id bigint(20) unsigned NOT NULL default '0',
		user_id bigint(20) unsigned NOT NULL default '0',
		order_id bigint(20) unsigned NOT NULL default '0',
		order_item_id bigint(20) unsigned NOT NULL default '0',
		composition_id bigint(20) unsigned NOT NULL default '0',
		date_created datetime NOT NULL default CURRENT_TIMESTAMP,
		date_modified datetime NOT NULL default CURRENT_TIMESTAMP,
		uuid varchar(100) NOT NULL default '',
		PRIMARY KEY (id),
		KEY status (status),
		KEY solution_id (solution_id),
        KEY user_id (user_id),
        KEY order_id (order_id),
        KEY order_item_id (order_item_id),
        KEY composition_id (composition_id)";
	}

	/**
	 * Upgrade to version 20210825
	 * 	- Add `status` column.
	 *
	 * @since 0.14.0
	 * @return bool
	 */
	protected function __20210825() {
		if ( ! $this->column_exists( 'status' ) ) {
			return $this->is_success(
				$this->get_db()->query(
					"ALTER TABLE {$this->table_name} ADD COLUMN status varchar(20) NOT NULL default 'ready' AFTER id"
				)
			);
		}

		return true;
	}
}
