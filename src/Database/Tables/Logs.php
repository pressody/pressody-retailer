<?php
/**
 * Logs Table.
 *
 * @since   0.14.0
 * @license GPL-2.0-or-later
 * @package PixelgradeLT
 */

declare ( strict_types=1 );

namespace PixelgradeLT\Retailer\Database\Tables;

use BerlinDB\Database\Table;

/**
 * Set up the global "logs" database table
 *
 * @since 0.14.0
 */
final class Logs extends Table {

	/**
	 * Table name.
	 *
	 * @access protected
	 * @since  0.14.0
	 * @var string
	 */
	protected string $name = 'lt_retailer_logs';

	/**
	 * Database version.
	 *
	 * @access protected
	 * @since  0.14.0
	 * @var int
	 */
	protected $version = 20210826;

	/**
	 * Set up the database schema.
	 *
	 * @access protected
	 * @since  0.14.0
	 * @return void
	 */
	protected function set_schema() {
		$this->schema = "id bigint(20) unsigned NOT NULL auto_increment,
        level smallint(4) NOT NULL default '0',
        source varchar(200) NOT NULL default '',
        message longtext NOT NULL default '',
        context longtext NULL default '',
        date_created datetime NOT NULL default CURRENT_TIMESTAMP,
		uuid varchar(100) NOT NULL default '',
		PRIMARY KEY (id),
		KEY level (level)";
	}
}
