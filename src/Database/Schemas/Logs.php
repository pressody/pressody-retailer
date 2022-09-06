<?php
/**
 * Logs Schema Class.
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

declare ( strict_types = 1 );

namespace Pressody\Retailer\Database\Schemas;

use BerlinDB\Database\Schema;

/**
 * Logs Schema Class.
 *
 * @since 0.14.0
 */
class Logs extends Schema {

	/**
	 * Array of database column objects
	 *
	 * @since 0.14.0
	 * @access public
	 * @var array
	 */
	public $columns = array(

		// id
		array(
			'name'       => 'id',
			'type'       => 'bigint',
			'length'     => '20',
			'unsigned'   => true,
			'extra'      => 'auto_increment',
			'primary'    => true,
			'sortable'   => true
		),

		// level
		array(
			'name'       => 'level',
			'type'       => 'smallint',
			'length'     => '4',
			'unsigned'   => true,
			'default'    => '0',
			'sortable'   => true,
		),

		// source
		array(
			'name'       => 'source',
			'type'       => 'varchar',
			'length'     => '200',
			'default'    => '',
			'searchable' => true,
			'sortable'   => true,
			'in'         => false,
			'not_in'     => false
		),

		// message
		array(
			'name'       => 'message',
			'type'       => 'longtext',
			'default'    => '',
			'searchable' => true,
			'in'         => false,
			'not_in'     => false
		),

		// context
		array(
			'name'       => 'context',
			'type'       => 'longtext',
			'default'    => '',
			'searchable' => true,
			'in'         => false,
			'not_in'     => false
		),

		// date_created
		array(
			'name'       => 'date_created',
			'type'       => 'datetime',
			'default'    => '', // Defaults to current time in query class
			'created'    => true,
			'date_query' => true,
			'sortable'   => true
		),

		// uuid
		array(
			'uuid'       => true,
		)
	);
}
