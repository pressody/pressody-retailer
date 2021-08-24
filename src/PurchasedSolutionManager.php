<?php
/**
 * Purchased Solution manager.
 *
 * @since   0.14.0
 * @license GPL-2.0-or-later
 * @package PixelgradeLT
 */

declare ( strict_types=1 );

namespace PixelgradeLT\Retailer;

use Psr\Log\LoggerInterface;

/**
 * Purchased Solution manager class.
 *
 * Handles the logic related to solutions purchased in some way and ready to be included in Compositions.
 *
 * @since 0.14.0
 */
class PurchasedSolutionManager {

	/**
	 * Used to create the pseudo IDs saved as values for a solution's required or excluded solutions.
	 * Don't change this without upgrading the data in the DB!
	 */
	const PSEUDO_ID_DELIMITER = ' #';

	/**
	 * The possible purchased solution statuses with details.
	 *
	 * The array keys are the status IDs.
	 *
	 * @since 0.14.0
	 *
	 * @var array
	 */
	public static array $STATUSES;

	/**
	 * Solution manager.
	 *
	 * @var SolutionManager
	 */
	protected SolutionManager $solution_manager;

	/**
	 * Logger.
	 *
	 * @var LoggerInterface
	 */
	protected LoggerInterface $logger;

	/**
	 * Constructor.
	 *
	 * @since 0.14.0
	 *
	 * @param SolutionManager $solution_manager Solutions manager.
	 * @param LoggerInterface $logger           Logger.
	 */
	public function __construct(
		SolutionManager $solution_manager,
		LoggerInterface $logger
	) {
		$this->solution_manager = $solution_manager;
		$this->logger           = $logger;

		self::$STATUSES = apply_filters( 'pixelgradelt_retailer/purchased_solution_statuses', [
			'ready'   => [
				'id'       => 'ready',
				'label'    => esc_html__( 'Ready', 'pixelgradelt_retailer' ),
				'desc'     => esc_html__( 'The purchased solution is ready to be used in compositions.', 'pixelgradelt_retailer' ),
				'internal' => false,
			],
			'active'  => [
				'id'       => 'active',
				'label'    => esc_html__( 'Active', 'pixelgradelt_retailer' ),
				'desc'     => esc_html__( 'The purchased solution is part of a composition. When it is no longer part of a composition it should be transitioned to `ready`.', 'pixelgradelt_retailer' ),
				'internal' => false,
			],
			'invalid' => [
				'id'       => 'invalid',
				'label'    => esc_html__( 'Invalid', 'pixelgradelt_retailer' ),
				'desc'     => esc_html__( 'The purchased solution can\'t be used in compositions. After certain changes it may become `ready`.', 'pixelgradelt_retailer' ),
				'internal' => false,
			],
			'retired' => [
				'id'       => 'retired',
				'label'    => esc_html__( 'Retired', 'pixelgradelt_retailer' ),
				'desc'     => esc_html__( 'The purchased solution has been retired and is no longer available for use. This status change should be definitive.', 'pixelgradelt_retailer' ),
				'internal' => false,
			],
		] );
	}

	/**
	 * Add a purchased solution entry.
	 *
	 * @since 0.14.0
	 *
	 * @param array $data           {
	 *                              Array of purchased solution data. Default empty.
	 *
	 *     The `date_created` and `date_modified` parameters do not need to be passed.
	 *     They will be automatically populated if empty.
	 *
	 * @type string $status         Optional. Purchased solution status. This is related to the solution, user, order and composition.
	 *                                        See PurchasedSolutionManager::$STATUSES for possible values.
	 *                                        Default `invalid`.
	 * @type int    $solution_id    Solution ID linked to the product being purchased. Default 0.
	 * @type int    $user_id        WordPress user ID linked to the customer of the order. Default 0.
	 * @type int    $order_id       ID of the order containing among its items the product linked to the solution. Default 0.
	 * @type int    $order_item_id  ID of the order item containing the product linked to the solution. Default 0.
	 * @type int    $composition_id The composition ID this purchased solution is currently part of. Default 0.
	 * @type string $date_created   Optional. Automatically calculated on add/edit.
	 *                                        The date & time the purchased solution was inserted.
	 *                                        Format: YYYY-MM-DD HH:MM:SS. Default empty.
	 * @type string $date_modified  Optional. Automatically calculated on add/edit.
	 *                                        The date & time the purchased solution was last modified.
	 *                                        Format: YYYY-MM-DD HH:MM:SS. Default empty.
	 * }
	 * @return int|false ID of newly created purchased solution, false on error.
	 */
	public function add_purchased_solution( array $data ) {
		$purchased_solutions = new Database\Queries\PurchasedSolution();

		return $purchased_solutions->add_item( $data );
	}

	/**
	 * Update a purchased solution entry.
	 *
	 * @since 0.14.0
	 *
	 * @param int   $id Purchased solution ID.
	 * @param array $data           {
	 *                              Array of purchased solution data. Default empty.
	 *
	 *     The `date_created` and `date_modified` parameters do not need to be passed.
	 *     They will be automatically populated if empty.
	 *
	 * @type string $status         Purchased solution status. This is related to the solution, user, order and composition.
	 *                              See PurchasedSolutionManager::$STATUSES for possible values.
	 *                              Default `invalid`.
	 * @type int    $solution_id    Solution ID linked to the product being purchased. Default 0.
	 * @type int    $user_id        WordPress user ID linked to the customer of the order. Default 0.
	 * @type int    $order_id       ID of the order containing among its items the product linked to the solution. Default 0.
	 * @type int    $order_item_id  ID of the order item containing the product linked to the solution. Default 0.
	 * @type int    $composition_id The composition ID this purchased solution is currently part of. Default 0.
	 * @type string $date_created   Optional. Automatically calculated on add/edit.
	 *                                        The date & time the purchased solution was inserted.
	 *                                        Format: YYYY-MM-DD HH:MM:SS. Default empty.
	 * @type string $date_modified  Optional. Automatically calculated on add/edit.
	 *                                        The date & time the purchased solution was last modified.
	 *                                        Format: YYYY-MM-DD HH:MM:SS. Default empty.
	 * }
	 * @return bool Whether the purchased solution was updated.
	 */
	public function update_purchased_solution( int $id, array $data ): bool {
		$purchased_solutions = new Database\Queries\PurchasedSolution();

		return $purchased_solutions->update_item( $id, $data );
	}

	/**
	 * Move a purchased solution to the retired status.
	 *
	 * @since 0.14.0
	 *
	 * @param int $id
	 *
	 * @return bool      true if the purchased order was retired successfully, false if not.
	 */
	public function retire_purchased_solution( int $id ): bool {
		$purchased_solutions = new Database\Queries\PurchasedSolution();

		return $purchased_solutions->update_item( $id, [
			'status' => 'retired',
		] );
	}

	/**
	 * Delete a purchased solution.
	 *
	 * @since 0.14.0
	 *
	 * @param int $id
	 *
	 * @return int|false `1` if the purchased solution was deleted successfully, false on error.
	 */
	public function delete_purchased_solution( int $id ): bool {
		$purchased_solutions = new Database\Queries\PurchasedSolution();

		return $purchased_solutions->delete_item( $id );
	}

	/**
	 * Get a purchased solution by ID.
	 *
	 * @since 0.14.0
	 *
	 * @param int $id Purchased solution ID.
	 *
	 * @return PurchasedSolution|false Purchased solution object if successful, false otherwise.
	 */
	function get_purchased_solution( int $id = 0 ) {
		$purchased_solutions = new Database\Queries\PurchasedSolution();

		// Return purchased solution
		return $purchased_solutions->get_item( $id );
	}

	/**
	 * Get a purchased solution by a specific field value.
	 *
	 * @since 0.14.0
	 *
	 * @param string $field Database table field.
	 * @param string|int  $value Value of the row.
	 *
	 * @return PurchasedSolution|false Purchased solution object if successful, false otherwise.
	 */
	function get_purchased_solution_by( string $field = '', $value = '' ) {
		$purchased_solutions = new Database\Queries\PurchasedSolution();

		// Return purchased solution
		return $purchased_solutions->get_item_by( $field, $value );
	}

	/**
	 * Query for purchased solutions.
	 *
	 * @since 0.14.0
	 *
	 * @see   Database\Queries\PurchasedSolution::__construct()
	 *
	 * @param array $args Arguments. See `\PixelgradeLT\Retailer\Database\Queries\PurchasedSolution` for
	 *                    accepted arguments.
	 *
	 * @return PurchasedSolution[] Array of `PurchasedSolution` objects.
	 */
	function get_purchased_solutions( array $args = [] ): array {
		$purchased_solutions = new Database\Queries\PurchasedSolution();

		// Parse args.
		$args = wp_parse_args( $args, array(
			'number' => 30,
		) );

		// Return purchased solutions.
		return $purchased_solutions->query( $args );
	}

	/**
	 * Count purchased solutions.
	 *
	 * @since 0.14.0
	 *
	 * @see   Database\Queries\PurchasedSolution::__construct()
	 *
	 * @param array $args Arguments. See `\PixelgradeLT\Retailer\Database\Queries\PurchasedSolution` for
	 *                    accepted arguments.
	 *
	 * @return int Number of purchased solutions returned based on query arguments passed.
	 */
	function count_purchased_solutions( array $args = [] ): int {
		// Parse args.
		$args = wp_parse_args( $args, array(
			'count' => true,
		) );

		// Query for count(s).
		$purchased_solutions = new Database\Queries\PurchasedSolution( $args );

		// Return count(s).
		return absint( $purchased_solutions->found_items );
	}
}
