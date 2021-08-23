<?php
/**
 * Purchased Solutions Query Class.
 *
 * @since   0.14.0
 * @license GPL-2.0-or-later
 * @package PixelgradeLT
 */

declare ( strict_types=1 );

namespace PixelgradeLT\Retailer\Database\Queries;

use BerlinDB\Database\Query;

/**
 * Class used for querying purchased solutions.
 *
 * @since 0.14.0
 *
 * @see   \PixelgradeLT\Retailer\Database\Queries\PurchasedSolution::__construct() for accepted arguments.
 */
class PurchasedSolution extends Query {

	/** Table Properties ******************************************************/

	/**
	 * Name of the database table to query.
	 *
	 * @since  0.14.0
	 * @access public
	 * @var string
	 */
	protected $table_name = 'lt_retailer_purchased_solutions';

	/**
	 * String used to alias the database table in MySQL statement.
	 *
	 * @since  0.14.0
	 * @access public
	 * @var string
	 */
	protected $table_alias = 'ltrtps';

	/**
	 * Name of class used to set up the database schema
	 *
	 * @since  0.14.0
	 * @access public
	 * @var string
	 */
	protected $table_schema = '\\PixelgradeLT\\Retailer\\Database\\Schemas\\PurchasedSolutions';

	/** Item ******************************************************************/

	/**
	 * Name for a single item
	 *
	 * @since  0.14.0
	 * @access public
	 * @var string
	 */
	protected $item_name = 'purchased_solution';

	/**
	 * Plural version for a group of items.
	 *
	 * @since  0.14.0
	 * @access public
	 * @var string
	 */
	protected $item_name_plural = 'purchased_solutions';

	/**
	 * Callback function for turning IDs into objects
	 *
	 * @since  0.14.0
	 * @access public
	 * @var mixed
	 */
	protected $item_shape = '\\PixelgradeLT\\Retailer\\PurchasedSolution';

	/** Cache *****************************************************************/

	/**
	 * Group to cache queries and queried items in.
	 *
	 * @since  0.14.0
	 * @access public
	 * @var string
	 */
	protected $cache_group = 'lt_rt_purchased_solutions';

	/** Methods ***************************************************************/

	/**
	 * Sets up the query, based on the query vars passed.
	 *
	 * @since  0.14.0
	 * @access public
	 *
	 * @param string|array $query                  {
	 *                                             Optional. Array or query string of query parameters. Default empty.
	 *
	 * @type int           $id                     A purchased solution ID to only return that purchased solution. Default empty.
	 * @type array         $id__in                 Array of purchased solution IDs to include. Default empty.
	 * @type array         $id__not_in             Array of purchased solution IDs to exclude. Default empty.
	 * @type string        $solution_id            A solution ID to only return those purchased solutions associated with it. Default empty.
	 * @type array         $solution_id__in        Array of solution IDs to include only those purchased solutions associated with them. Default empty.
	 * @type array         $solution_id__not_in    Array of solution IDs to exclude those purchased solutions associated with them. Default empty.
	 * @type string        $user_id                A user ID to only return those users. Default empty.
	 * @type array         $user_id__in            Array of user IDs to include. Default empty.
	 * @type array         $user_id__not_in        Array of user IDs to exclude. Default empty.
	 * @type string        $order_id               An order ID to only return those purchased solutions associated with it. Default empty.
	 * @type array         $order_id__in           Array of order IDs to include only those purchased solutions associated with them. Default empty.
	 * @type array         $order_id__not_in       Array of order IDs to exclude those purchased solutions associated with them. Default empty.
	 * @type string        $order_item_id          An order item ID to only return those purchased solutions associated with it. Default empty.
	 * @type array         $order_item_id__in      Array of order item IDs to include only those purchased solutions associated with them. Default empty.
	 * @type array         $order_item_id__not_in  Array of order item IDs to exclude those purchased solutions associated with them. Default empty.
	 * @type string        $composition_id         A composition ID to only return those purchased solutions associated with it. Default empty.
	 * @type array         $composition_id__in     Array of composition IDs to include only those purchased solutions associated with them. Default empty.
	 * @type array         $composition_id__not_in Array of composition IDs to exclude those purchased solutions associated with them. Default empty.
	 * @type array         $date_query             Query all datetime columns together. See WP_Date_Query.
	 * @type array         $date_created_query     Date query clauses to limit by. See WP_Date_Query.
	 *                                              Default null.
	 * @type array         $date_modified_query    Date query clauses to limit by. See WP_Date_Query.
	 *                                              Default null.
	 * @type bool          $count                  Whether to return a count (true) or array of objects.
	 *                                              Default false.
	 * @type string        $fields                 Item fields to return. Accepts any column known names
	 *                                              or empty (returns an array of complete objects). Default empty.
	 * @type int           $number                 Limit number of logs to retrieve. Default 100.
	 * @type int           $offset                 Number of logs to offset the query. Used to build LIMIT clause.
	 *                                              Default 0.
	 * @type bool          $no_found_rows          Whether to disable the `SQL_CALC_FOUND_ROWS` query. Default true.
	 * @type string|array  $orderby                Accepts 'id', 'solution_id', 'user_id', 'order_id', 'order_item_id',
	 *                                              'composition_id', 'date_created', and 'date_modified'. Also accepts false,
	 *                                              an empty array, or 'none' to disable `ORDER BY` clause.
	 *                                              Default 'id'.
	 * @type string        $order                  How to order results. Accepts 'ASC', 'DESC'. Default 'DESC'.
	 * @type string        $search                 Search term(s) to retrieve matching purchased solutions for. Default empty.
	 * @type bool          $update_cache           Whether to prime the cache for found purchased solutions. Default false.
	 * }
	 */
	public function __construct( $query = array() ) {
		parent::__construct( $query );
	}
}
