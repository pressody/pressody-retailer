<?php
/**
 * Logs Table.
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
	protected $name = 'pd_retailer_logs';

	/**
	 * Database version.
	 *
	 * @access protected
	 * @since  0.14.0
	 * @var int
	 */
	protected $version = 20220620;

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
