<?php
/**
 * Purchased Solution manager.
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

namespace Pressody\Retailer;

use BerlinDB\Database\Query;
use BerlinDB\Database\Table;
use Psr\Log\LoggerInterface;

/**
 * Purchased Solution manager class.
 *
 * Handles the logic related to solutions purchased in some way and ready to be included in Compositions.
 *
 * @since 0.14.0
 */
class PurchasedSolutionManager implements Manager {

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
	 * The custom DB table.
	 *
	 * @since 0.14.0
	 *
	 * @var Table
	 */
	protected Table $db;

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
	 * @param Table           $db               The instance handling the custom DB table.
	 * @param LoggerInterface $logger           Logger.
	 */
	public function __construct(
		SolutionManager $solution_manager,
		Table $db,
		LoggerInterface $logger
	) {
		$this->solution_manager = $solution_manager;
		$this->db               = $db;
		$this->logger           = $logger;

		self::$STATUSES = \apply_filters( 'pressody_retailer/purchased_solution_statuses', [
			'ready'   => [
				'id'       => 'ready',
				'label'    => esc_html__( 'Ready', 'pressody_retailer' ),
				'desc'     => esc_html__( 'The purchased solution is ready to be used in compositions.', 'pressody_retailer' ),
				'internal' => false,
			],
			'active'  => [
				'id'       => 'active',
				'label'    => esc_html__( 'Active', 'pressody_retailer' ),
				'desc'     => esc_html__( 'The purchased solution is part of a composition. When it is no longer part of a composition it should be transitioned to `ready`.', 'pressody_retailer' ),
				'internal' => false,
			],
			'invalid' => [
				'id'       => 'invalid',
				'label'    => esc_html__( 'Invalid', 'pressody_retailer' ),
				'desc'     => esc_html__( 'The purchased solution can\'t be used in compositions. After certain changes it may become `ready` or `active`.', 'pressody_retailer' ),
				'internal' => false,
			],
			'retired' => [
				'id'       => 'retired',
				'label'    => esc_html__( 'Retired', 'pressody_retailer' ),
				'desc'     => esc_html__( 'The purchased solution has been retired and is no longer available for use. This status change should be definitive.', 'pressody_retailer' ),
				'internal' => false,
			],
		] );
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
	public function get_purchased_solution( int $id = 0 ) {
		$purchased_solutions = new Database\Queries\PurchasedSolution();

		return $purchased_solutions->get_item( $id );
	}

	/**
	 * Get a purchased solution by a specific field value.
	 *
	 * @since 0.14.0
	 *
	 * @param string     $field Database table field.
	 * @param string|int $value Value of the row.
	 *
	 * @return PurchasedSolution|false Purchased solution object if successful, false otherwise.
	 */
	public function get_purchased_solution_by( string $field = '', $value = '' ) {
		$purchased_solutions = new Database\Queries\PurchasedSolution();

		return $purchased_solutions->get_item_by( $field, $value );
	}

	/**
	 * Query for purchased solutions.
	 *
	 * @since 0.14.0
	 *
	 * @see   Database\Queries\PurchasedSolution::__construct()
	 *
	 * @param array $args Arguments. See `\Pressody\Retailer\Database\Queries\PurchasedSolution` for
	 *                    accepted arguments.
	 *
	 * @return PurchasedSolution[] Array of `PurchasedSolution` objects.
	 */
	public function get_purchased_solutions( array $args = [] ): array {
		$purchased_solutions = new Database\Queries\PurchasedSolution();

		// Parse args.
		$args = \wp_parse_args( $args, array(
			'number' => 50,
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
	 * @param array $args Arguments. See `\Pressody\Retailer\Database\Queries\PurchasedSolution` for
	 *                    accepted arguments.
	 *
	 * @return int Number of purchased solutions returned based on query arguments passed.
	 */
	public function count_purchased_solutions( array $args = [] ): int {
		// Parse args.
		$args = \wp_parse_args( $args, array(
			'count' => true,
		) );

		// Query for count(s).
		$purchased_solutions = new Database\Queries\PurchasedSolution( $args );

		// Return count(s).
		return \absint( $purchased_solutions->found_items );
	}

	/**
	 * Query for and return array of purchased solution counts, keyed by status.
	 *
	 * @since 0.14.0
	 *
	 * @see   Database\Queries\PurchasedSolution::__construct()
	 *
	 * @param array $args Arguments. See `\Pressody\Retailer\Database\Queries\PurchasedSolution` for
	 *                    accepted arguments.
	 *
	 * @return array Purchased solution counts keyed by status.
	 */
	public function get_purchased_solution_counts( array $args = [] ): array {

		// Parse args
		$args = \wp_parse_args( $args, array(
			'count'   => true,
			'groupby' => 'status',
		) );

		// Query for counts.
		$counts = new Database\Queries\PurchasedSolution( $args );

		// Format & return
		return $this->format_counts( $counts, $args['groupby'] );
	}

	/**
	 * Format an array of count objects, using the $groupby key.
	 *
	 * @since 0.14.0
	 *
	 * @param Query  $counts
	 * @param string $groupby
	 *
	 * @return array
	 */
	protected function format_counts( Query $counts, string $groupby = '' ): array {

		// Default array
		$c = array(
			'total' => 0,
		);

		// Loop through counts and shape return value
		if ( ! empty( $counts->items ) ) {
			// Loop through statuses
			foreach ( $counts->items as $count ) {
				$c[ $count[ $groupby ] ] = \absint( $count['count'] );
				$c['total']              += $c[ $count[ $groupby ] ];
			}

		}

		// Return array of counts
		return $c;
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
	 * @param int   $id             Purchased solution ID.
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

		$result = $purchased_solutions->update_item( $id, $data );

		return $result !== false;
	}

	/**
	 * Move a purchased solution to the `invalid` status.
	 *
	 * @since 0.14.0
	 *
	 * @param int $id The purchased solution ID.
	 *
	 * @return bool true if the purchased solution was invalidated successfully, false if not.
	 */
	public function invalidate_purchased_solution( int $id ): bool {
		$purchased_solutions = new Database\Queries\PurchasedSolution();

		$result = $purchased_solutions->update_item( $id, [
			'status' => 'invalid',
		] );

		return $result !== false;
	}

	/**
	 * Move a purchased solution to the `ready` status.
	 *
	 * @since 0.14.0
	 *
	 * @param int $id The purchased solution ID.
	 *
	 * @return bool true if the purchased solution was readied successfully, false if not.
	 */
	public function ready_purchased_solution( int $id ): bool {
		$purchased_solutions = new Database\Queries\PurchasedSolution();

		$result = $purchased_solutions->update_item( $id, [
			'status' => 'ready',
		] );

		return $result !== false;
	}

	/**
	 * Move a purchased solution to the `active` status.
	 *
	 * A composition ID must be provided to be able to activate a purchased solution.
	 * But only if the purchased solution is ready.
	 *
	 * @since 0.14.0
	 *
	 * @param int $id             The purchased solution ID.
	 * @param int $composition_id The composition ID this purchased solution is active in.
	 *
	 * @return bool true if the purchased solution was activated successfully, false if not.
	 */
	public function activate_purchased_solution( int $id, int $composition_id ): bool {
		$purchased_solutions = new Database\Queries\PurchasedSolution();

		// We can only activate ready purchased solutions. Any other status, and we will not activate.
		/** @var PurchasedSolution $current */
		$current = $purchased_solutions->get_item( $id );
		if ( 'ready' !== $current->status ) {
			return false;
		}

		$result = $purchased_solutions->update_item( $id, [
			'status'         => 'active',
			'composition_id' => $composition_id,
		] );

		return $result !== false;
	}

	/**
	 * Deactivate a previously attached purchased solution to a certain composition.
	 *
	 * Moves the purchased solution to the `ready` status and sets the composition ID to 0.
	 * But only if the purchased solution is active.
	 *
	 * @since 0.15.0
	 *
	 * @param int $id             The purchased solution ID.
	 * @param int $composition_id Optional. The composition ID the purchased solution should be attached to prior to deactivation.
	 *                            Set to 0 to not check for a match prior to detaching.
	 *
	 * @return bool true if the purchased solution was deactivated successfully, false if not.
	 */
	public function deactivate_purchased_solution( int $id, int $composition_id = 0 ): bool {
		$purchased_solutions = new Database\Queries\PurchasedSolution();

		/** @var PurchasedSolution $current */
		$current = $purchased_solutions->get_item( $id );

		// We can only deactivate active purchased solutions.
		if ( 'active' !== $current->status ) {
			return false;
		}

		// If provided with a composition ID we will only deactivate if the purchased solution is currently attached to that composition ID.
		if ( $composition_id > 0 ) {
			if ( ! empty( $current->composition_id ) && $composition_id !== $current->composition_id ) {
				return false;
			}
		}

		$result = $purchased_solutions->update_item( $id, [
			'status'         => 'ready',
			'composition_id' => 0,
		] );

		return $result !== false;
	}

	/**
	 * Move a purchased solution to the `retired` status.
	 *
	 * @since 0.14.0
	 *
	 * @param int $id The purchased solution ID.
	 *
	 * @return bool true if the purchased solution was retired successfully, false if not.
	 */
	public function retire_purchased_solution( int $id ): bool {
		$purchased_solutions = new Database\Queries\PurchasedSolution();

		$result = $purchased_solutions->update_item( $id, [
			'status' => 'retired',
		] );

		return $result !== false;
	}

	/**
	 * Delete a purchased solution.
	 *
	 * @since 0.14.0
	 *
	 * @param int $id The purchased solution ID.
	 *
	 * @return int|false `1` if the purchased solution was deleted successfully, false on error.
	 */
	public function delete_purchased_solution( int $id ): bool {
		$purchased_solutions = new Database\Queries\PurchasedSolution();

		$result = $purchased_solutions->delete_item( $id );

		return $result !== false;
	}
}
