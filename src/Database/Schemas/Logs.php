<?php
/**
 * Logs Schema Class.
 *
 * @since   0.14.0
 * @license GPL-2.0-or-later
 * @package PixelgradeLT
 */

declare ( strict_types = 1 );

namespace PixelgradeLT\Retailer\Database\Schemas;

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
	public array $columns = array(

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
