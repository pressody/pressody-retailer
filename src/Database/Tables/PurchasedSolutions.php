<?php
/**
 * Purchased Solutions Table.
 *
 * @since   0.14.0
 * @license GPL-2.0-or-later
 * @package PixelgradeLT
 */

declare ( strict_types=1 );

namespace PixelgradeLT\Retailer\Database\Tables;

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
	protected string $name = 'lt_retailer_purchased_solutions';

	/**
	 * Database version.
	 *
	 * @access protected
	 * @since  0.14.0
	 * @var int
	 */
	protected $version = 20210824;

	/**
	 * Set up the database schema.
	 *
	 * @access protected
	 * @since  0.14.0
	 * @return void
	 */
	protected function set_schema() {
		$this->schema = "id bigint(20) unsigned NOT NULL auto_increment,
		solution_id bigint(20) unsigned NOT NULL default '0',
		user_id bigint(20) unsigned NOT NULL default '0',
		order_id bigint(20) unsigned NOT NULL default '0',
		order_item_id bigint(20) unsigned NOT NULL default '0',
		composition_id bigint(20) unsigned NOT NULL default '0',
		date_created datetime NOT NULL default CURRENT_TIMESTAMP,
		date_modified datetime NOT NULL default CURRENT_TIMESTAMP,
		uuid varchar(100) NOT NULL default '',
		PRIMARY KEY (id),
		KEY solution_id (solution_id),
        KEY user_id (user_id),
        KEY order_id (order_id),
        KEY order_item_id (order_item_id),
        KEY composition_id (composition_id)";
	}
}
