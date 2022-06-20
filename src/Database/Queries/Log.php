<?php
/**
 * Logs Query Class.
 *
 * @since   0.14.0
 * @license GPL-2.0-or-later
 * @package Pressody
 */

declare ( strict_types = 1 );

namespace Pressody\Retailer\Database\Queries;

use BerlinDB\Database\Query;

/**
 * Class used for querying logs.
 *
 * @since 0.14.0
 *
 * @see \Pressody\Retailer\Database\Queries\Log::__construct() for accepted arguments.
 */
class Log extends Query {

	/** Table Properties ******************************************************/

	/**
	 * Name of the database table to query.
	 *
	 * @since 0.14.0
	 * @access public
	 * @var string
	 */
	protected $table_name = 'pd_retailer_logs';

	/**
	 * String used to alias the database table in MySQL statement.
	 *
	 * @since 0.14.0
	 * @access public
	 * @var string
	 */
	protected $table_alias = 'ltrtl';

	/**
	 * Name of class used to set up the database schema
	 *
	 * @since 0.14.0
	 * @access public
	 * @var string
	 */
	protected $table_schema = '\\Pressody\\Retailer\\Database\\Schemas\\Logs';

	/** Item ******************************************************************/

	/**
	 * Name for a single item
	 *
	 * @since 0.14.0
	 * @access public
	 * @var string
	 */
	protected $item_name = 'log';

	/**
	 * Plural version for a group of items.
	 *
	 * @since 0.14.0
	 * @access public
	 * @var string
	 */
	protected $item_name_plural = 'logs';

	/**
	 * Callback function for turning IDs into objects
	 *
	 * @since 0.14.0
	 * @access public
	 * @var mixed
	 */
	protected $item_shape = '\\Pressody\\Retailer\\Logging\\Log';

	/** Cache *****************************************************************/

	/**
	 * Group to cache queries and queried items in.
	 *
	 * @since 0.14.0
	 * @access public
	 * @var string
	 */
	protected $cache_group = 'lt_rt_logs';

	/** Methods ***************************************************************/

	/**
	 * Sets up the query, based on the query vars passed.
	 *
	 * @since 0.14.0
	 * @access public
	 *
	 * @param string|array $query {
	 *     Optional. Array or query string of query parameters. Default empty.
	 *
	 *     @type int          $id                   An log ID to only return that log. Default empty.
	 *     @type array        $id__in               Array of log IDs to include. Default empty.
	 *     @type array        $id__not_in           Array of log IDs to exclude. Default empty.
	 *     @type string       $level                 A level to only return those levels. Default empty.
	 *     @type array        $level__in             Array of levels to include. Default empty.
	 *     @type array        $level__not_in         Array of levels to exclude. Default empty.
	 *     @type string       $source                Source to search by. Default empty.
	 *     @type string       $message              Message to search by. Default empty.
	 *     @type array        $date_created_query   Date query clauses to limit by. See WP_Date_Query.
	 *                                              Default null.
	 *     @type bool         $count                Whether to return a count (true) or array of objects.
	 *                                              Default false.
	 *     @type string       $fields               Item fields to return. Accepts any column known names
	 *                                              or empty (returns an array of complete objects). Default empty.
	 *     @type int          $number               Limit number of logs to retrieve. Default 100.
	 *     @type int          $offset               Number of logs to offset the query. Used to build LIMIT clause.
	 *                                              Default 0.
	 *     @type bool         $no_found_rows        Whether to disable the `SQL_CALC_FOUND_ROWS` query. Default true.
	 *     @type string|array $orderby              Accepts 'id', 'object_id', 'object_type', 'user_id', 'type',
	 *                                              'title', 'date_created', and 'date_modified'. Also accepts false,
	 *                                              an empty array, or 'none' to disable `ORDER BY` clause.
	 *                                              Default 'id'.
	 *     @type string       $order                How to order results. Accepts 'ASC', 'DESC'. Default 'DESC'.
	 *     @type string       $search               Search term(s) to retrieve matching logs for. Default empty.
	 *     @type bool         $update_cache         Whether to prime the cache for found logs. Default false.
	 * }
	 */
	public function __construct( $query = array() ) {
		parent::__construct( $query );
	}
}
