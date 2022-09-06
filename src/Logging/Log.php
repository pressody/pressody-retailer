<?php
/**
 * Logs API - Log Object
 *
 * @since       0.14.0
 * @license     GPL-2.0-or-later
 * @package     Pressody
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

namespace Pressody\Retailer\Logging;

use Pressody\Retailer\BaseObject;

/**
 * Log Class.
 *
 * @since 0.14.0
 *
 * @property int    $id
 * @property int    $level
 * @property string $source
 * @property string $message
 * @property string $context
 * @property string $date_created
 */
class Log extends BaseObject {

	/**
	 * Log ID.
	 *
	 * @since  0.14.0
	 * @access protected
	 * @var    int
	 */
	protected $id;

	/**
	 * Log level.
	 *
	 * @see \Pressody\Retailer\Logging\LogLevels
	 *
	 * @since  0.14.0
	 * @access protected
	 * @var    int
	 */
	protected $level;

	/**
	 * Log source.
	 *
	 * The place where this log was generated.
	 *
	 * @since  0.14.0
	 * @access protected
	 * @var    string
	 */
	protected $source;

	/**
	 * Log message.
	 *
	 * @since  0.14.0
	 * @access protected
	 * @var    string
	 */
	protected $message;

	/**
	 * Log context.
	 *
	 * Extra information related to the moment this log was generated (usually encoded JSON data).
	 *
	 * @since  0.14.0
	 * @access protected
	 * @var    string
	 */
	protected $context;

	/**
	 * Datetime the log was created.
	 *
	 * @since  0.14.0
	 * @access protected
	 * @var    string
	 */
	protected $date_created;
}
