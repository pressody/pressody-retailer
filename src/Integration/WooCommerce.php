<?php
/**
 * WooCommerce plugin integration.
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

namespace Pressody\Retailer\Integration;

use Cedaro\WP\Plugin\AbstractHookProvider;
use Pressody\Retailer\Integration\WooCommerce\Order;
use Pressody\Retailer\PurchasedSolutionManager;
use Psr\Log\LoggerInterface;
use function Pressody\Retailer\carbon_get_raw_post_meta;

/**
 * WooCommerce plugin integration provider class.
 *
 * @since 0.14.0
 */
class WooCommerce extends AbstractHookProvider {

	/**
	 * @since 0.14.0
	 */
	const PRODUCT_LINKED_TO_PDSOLUTION_META_KEY = '_linked_to_pdsolution';

	/**
	 * The Purchased Solutions Manager.
	 *
	 * @since 0.14.0
	 *
	 * @var PurchasedSolutionManager
	 */
	protected PurchasedSolutionManager $ps_manager;

	/**
	 * Logger.
	 *
	 * @since 0.14.0
	 *
	 * @var LoggerInterface
	 */
	protected LoggerInterface $logger;

	/**
	 * Constructor.
	 *
	 * @since 0.14.0
	 *
	 * @param PurchasedSolutionManager $purchased_solution_manager Purchased Solutions Manager.
	 * @param LoggerInterface          $logger                     Logger.
	 */
	public function __construct(
		PurchasedSolutionManager $purchased_solution_manager,
		LoggerInterface $logger
	) {
		$this->ps_manager = $purchased_solution_manager;
		$this->logger     = $logger;
	}

	/**
	 * Register hooks.
	 *
	 * @since 0.14.0
	 */
	public function register_hooks() {
		// Handle PD Solutions.
		$this->add_filter( 'pressody_retailer/solution_id_data', 'add_solution_data', 5, 2 );
		$this->add_filter( 'pressody_retailer/solution_ids_by_query_args', 'handle_solution_query_args', 10, 2 );

		// Handle WooCommerce Products query custom query vars.
		add_filter( 'woocommerce_product_data_store_cpt_get_products_query', [
			$this,
			'handle_custom_query_vars',
		], 10, 2 );

		/**
		 * HANDLE ORDERS AND THE SYNC WITH PURCHASED SOLUTIONS.
		 *
		 * Yes, these hooks get triggered multiple times on a single order update since multiple entities (like metaboxes)
		 * trigger them.
		 * For reliability, we will accept firing the handlers multiple times given that we only update (write in the DB)
		 * when we need to.
		 *
		 * We use 100 as the priority because we want to leave others do their things first (probably more critical than ours).
		 */
		$this->add_action( 'woocommerce_new_order', 'handle_order_update', 100, 1 );
		$this->add_action( 'woocommerce_update_order', 'handle_order_update', 100, 1 );
		$this->add_action( 'woocommerce_delete_order', 'handle_order_delete', 100, 1 );
		$this->add_action( 'woocommerce_trash_order', 'handle_order_trash', 100, 1 );

		// These are most likely to be admin (AJAX) actions without the need for general order update.
		$this->add_action( 'woocommerce_order_refunded', 'handle_order_update', 100, 1 );

		$this->add_action( 'woocommerce_new_order_item', 'handle_order_item_update', 100, 1 );
		$this->add_action( 'woocommerce_update_order_item', 'handle_order_item_update', 100, 1 );
		$this->add_action( 'woocommerce_before_delete_order_item', 'handle_order_item_delete', 100, 1 );
	}

	/**
	 * Handle WooCommerce order update (or creation).
	 *
	 * We will extract any purchased solutions from the order and make sure that we update/create them all.
	 *
	 * @param int $order_id The order ID.
	 */
	protected function handle_order_update( int $order_id ) {
		$purchased_solutions = Order::get_purchased_solutions( $order_id );
		// Bail if this order doesn't contain purchased solutions.
		if ( empty( $purchased_solutions ) ) {
			return;
		}

		// Remember the purchased solutions we update, so we can track down those that may have become orphans.
		$encountered_ps_ids = [];
		// Remember the purchased solutions we update.
		$updated_ps_ids = [];

		// Handle each purchased solution details identified for the order's product items (line_item)
		// with products linked to an PD Solution.
		foreach ( $purchased_solutions as $purchased_solution ) {
			$this->handle_purchased_solution( $purchased_solution, $encountered_ps_ids, $updated_ps_ids );
		}

		/*
		 * One last thing to do: track down possibly orphaned purchased solutions.
		 *
		 * If we can find more purchased solutions related to this order ID (without the order item ID) than
		 * the number we've encountered, this means that are some orphan items that should be retired.
		 */
		$order_related_ps_count = $this->ps_manager->count_purchased_solutions( [
			'order_id' => $order_id,
		] );
		if ( $order_related_ps_count > count( $encountered_ps_ids ) ) {
			$orphan_ps = $this->ps_manager->get_purchased_solutions( [
				'id__not_in'     => $encountered_ps_ids,
				'order_id'       => $order_id,
				'status__not_in' => [ 'retired' ],
			] );
			foreach ( $orphan_ps as $ps ) {
				$this->ps_manager->retire_purchased_solution( $ps->id );
			}
		}

		// All done!
	}

	/**
	 * Handle WooCommerce order deletion.
	 *
	 * We will extract any purchased solutions from the order and make sure that we retire them all.
	 *
	 * @param int $order_id
	 */
	protected function handle_order_delete( int $order_id ) {
		$purchased_solutions = Order::get_purchased_solutions( $order_id );
		// Bail if this order doesn't contain purchased solutions.
		if ( empty( $purchased_solutions ) ) {
			return;
		}

		// Remember the purchased solutions we update.
		$updated_ps_ids = [];
		foreach ( $purchased_solutions as $purchased_solution ) {
			$counts = \wp_parse_args( $this->ps_manager->get_purchased_solution_counts( [
				'order_id'      => $purchased_solution['order_id'],
				'order_item_id' => $purchased_solution['order_item_id'],
			] ), [
				'total'   => 0,
				'ready'   => 0,
				'active'  => 0,
				'invalid' => 0,
				'retired' => 0,
			] );

			// Nothing to do if all purchased solutions have been retired.
			if ( $counts['total'] === $counts['retired'] ) {
				continue;
			}

			// Try to get existing purchased solutions by the unique combination of order_id and order_item_id.
			// Multiple results may be returned if the quantity is greater than 1.
			$found_ps = $this->ps_manager->get_purchased_solutions( [
				'order_id'      => $purchased_solution['order_id'],
				'order_item_id' => $purchased_solution['order_item_id'],
			] );
			foreach ( $found_ps as $ps ) {
				if ( 'retired' !== $ps->status ) {
					// We can retire this purchased solution.
					if ( $this->ps_manager->retire_purchased_solution( $ps->id ) ) {
						$updated_ps_ids[] = $ps->id;
					}
				}
			}
		}

		// All done!
	}

	/**
	 * Handle WooCommerce order trashing.
	 *
	 * We will extract any purchased solutions from the order and make sure that we invalidate them all.
	 *
	 * @param int $order_id
	 */
	protected function handle_order_trash( int $order_id ) {
		$purchased_solutions = Order::get_purchased_solutions( $order_id );
		// Bail if this order doesn't contain purchased solutions.
		if ( empty( $purchased_solutions ) ) {
			return;
		}

		// Remember the purchased solutions we update.
		$updated_ps_ids = [];
		foreach ( $purchased_solutions as $purchased_solution ) {
			$counts = \wp_parse_args( $this->ps_manager->get_purchased_solution_counts( [
				'order_id'      => $purchased_solution['order_id'],
				'order_item_id' => $purchased_solution['order_item_id'],
			] ), [
				'total'   => 0,
				'ready'   => 0,
				'active'  => 0,
				'invalid' => 0,
				'retired' => 0,
			] );

			// Nothing to do if all purchased solutions have been invalidated or retired.
			if ( $counts['total'] === ( $counts['retired'] + $counts['invalid'] ) ) {
				continue;
			}

			// Try to get existing purchased solutions by the unique combination of order_id and order_item_id.
			// Multiple results may be returned if the quantity is greater than 1.
			$found_ps = $this->ps_manager->get_purchased_solutions( [
				'order_id'      => $purchased_solution['order_id'],
				'order_item_id' => $purchased_solution['order_item_id'],
			] );
			foreach ( $found_ps as $ps ) {
				if ( ! in_array( $ps->status, [ 'invalid', 'retired', ] ) ) {
					// We can invalidate this purchased solution.
					if ( $this->ps_manager->invalidate_purchased_solution( $ps->id ) ) {
						$updated_ps_ids[] = $ps->id;
					}
				}
			}
		}

		// All done!
	}

	/**
	 * Handle WooCommerce order item update (or creation).
	 *
	 * We will extract any purchased solutions from the order item and make sure that we update/create them all.
	 *
	 * @param int $order_item_id
	 *
	 * @return bool True on success, false on failure.
	 */
	protected function handle_order_item_update( int $order_item_id ): bool {
		$purchased_solution = Order::get_item_purchased_solution( $order_item_id );
		// Bail if this order item doesn't generate a purchased solution.
		if ( empty( $purchased_solution ) ) {
			return false;
		}

		// Remember the purchased solutions we update, so we can track down those that may have become orphans.
		$encountered_ps_ids = [];
		// Remember the purchased solutions we update.
		$updated_ps_ids = [];

		return $this->handle_purchased_solution( $purchased_solution, $encountered_ps_ids, $updated_ps_ids );
	}

	/**
	 * Handle WooCommerce order item delete.
	 *
	 * We will extract any purchased solutions from the order item and make sure that we retire them all.
	 *
	 * @param int $order_item_id
	 *
	 * @return bool True on success, false on failure.
	 */
	protected function handle_order_item_delete( int $order_item_id ): bool {
		$purchased_solution = Order::get_item_purchased_solution( $order_item_id );
		// Bail if this order item doesn't generate a purchased solution.
		if ( empty( $purchased_solution ) ) {
			return false;
		}

		// Remember the purchased solutions we update, so we can track down those that may have become orphans.
		$encountered_ps_ids = [];
		// Remember the purchased solutions we update.
		$updated_ps_ids = [];

		// Mark the purchased solution as invalid.
		$purchased_solution['status'] = 'invalid';

		return $this->handle_purchased_solution( $purchased_solution, $encountered_ps_ids, $updated_ps_ids );
	}

	/**
	 * @param array $purchased_solution The purchased solution details as extracted from an order item.
	 * @param array $encountered_ps_ids Accumulator for encountered purchased solutions IDs.
	 * @param array $updated_ps_ids     Accumulator for updated (or created) purchased solutions IDs.
	 *
	 * @return bool True if we have successfully handled or false otherwise.
	 */
	protected function handle_purchased_solution(
		array $purchased_solution,
		array &$encountered_ps_ids = [],
		array &$updated_ps_ids = []
	): bool {

		if ( empty( $purchased_solution['order_id'] )
		     || empty( $purchased_solution['order_item_id'] )
		     || empty( $purchased_solution['order'] )
		     // Reject WC_Order_Refund.
		     || ! $purchased_solution['order'] instanceof \WC_Order
		) {

			return false;
		}

		$counts = \wp_parse_args( $this->ps_manager->get_purchased_solution_counts( [
			'order_id'      => $purchased_solution['order_id'],
			'order_item_id' => $purchased_solution['order_item_id'],
		] ), [
			'total'   => 0,
			'ready'   => 0,
			'active'  => 0,
			'invalid' => 0,
			'retired' => 0,
		] );

		// Try to get existing purchased solutions by the unique combination of order_id and order_item_id.
		// Multiple results may be returned if the quantity is greater than 1.
		$found_ps = $this->ps_manager->get_purchased_solutions( [
			'order_id'      => $purchased_solution['order_id'],
			'order_item_id' => $purchased_solution['order_item_id'],
			// Since the id is auto-incremented, it is safe to use it as a marker for recency.
			'order_by'      => 'id',
			// The latest purchased solutions, first.
			'order'         => 'DESC',
		] );

		$encountered_ps_ids = array_unique( array_merge( $encountered_ps_ids, array_map( function ( $item ) {
			return $item->id;
		}, $found_ps ) ) );

		/*
		 * Handle updating purchased solutions details like solution ID, user ID.
		 *
		 * Do this first, so we can safely stop as early as possible after this.
		 */
		foreach ( $found_ps as $ps ) {
			$to_update = [];
			if ( $ps->solution_id !== $purchased_solution['solution_id'] ) {
				$to_update['solution_id'] = $purchased_solution['solution_id'];
			}
			if ( $ps->user_id !== $purchased_solution['customer_id'] ) {
				$to_update['user_id'] = $purchased_solution['customer_id'];
			}
			if ( ! empty( $to_update ) ) {
				if ( $this->ps_manager->update_purchased_solution( $ps->id, $to_update ) ) {
					$updated_ps_ids[] = $ps->id;
				}
			}
		}

		/*
		 * Handle cancelled order item (aka the entire order was cancelled).
		 */

		// Invalid order items will lead to the `retired` status.
		if ( 'invalid' === $purchased_solution['status'] ) {
			// We need to retire all purchased solutions.
			foreach ( $found_ps as $ps ) {
				if ( 'retired' !== $ps->status ) {
					// We can retire this purchased solution.
					if ( $this->ps_manager->retire_purchased_solution( $ps->id ) ) {
						$counts[ $ps->status ] --;
						$counts['retired'] ++;
						$updated_ps_ids[] = $ps->id;
					}
				}
			}

			// If this order item has been cancelled/deleted, there is no need to do anything more.
			return true;
		}

		/*
		 * Handle fully or partial refunded order item (aka the entire quantity, or only some of it).
		 */

		// Refunded order items will lead to the `retired` status.
		if ( $purchased_solution['refunded_qty'] > 0 && $counts['retired'] < $purchased_solution['refunded_qty'] ) {
			// We need to retire some purchased solutions due to refunded order items.
			$need_to_retire = $purchased_solution['refunded_qty'] - $counts['retired'];
			// First, the invalid purchased solutions.
			if ( $need_to_retire > 0 && $counts['invalid'] > 0 ) {
				foreach ( $found_ps as $ps ) {
					if ( 'invalid' === $ps->status ) {
						// We can retire this purchased solution.
						if ( $this->ps_manager->retire_purchased_solution( $ps->id ) ) {
							$need_to_retire --;
							$counts[ $ps->status ] --;
							$counts['retired'] ++;
							$updated_ps_ids[] = $ps->id;
						}
					}

					if ( $need_to_retire === 0 ) {
						break;
					}
				}
			}
			// Second, the ready purchased solutions.
			if ( $need_to_retire > 0 && $counts['ready'] > 0 ) {
				foreach ( $found_ps as $ps ) {
					if ( 'ready' === $ps->status ) {
						// We can retire this purchased solution.
						if ( $this->ps_manager->retire_purchased_solution( $ps->id ) ) {
							$need_to_retire --;
							$counts[ $ps->status ] --;
							$counts['retired'] ++;
							$updated_ps_ids[] = $ps->id;
						}
					}

					if ( $need_to_retire === 0 ) {
						break;
					}
				}
			}

			// Third, sadly, the active purchased solutions.
			if ( $need_to_retire > 0 && $counts['active'] > 0 ) {
				foreach ( $found_ps as $ps ) {
					if ( 'active' === $ps->status ) {
						// We can retire this purchased solution.
						if ( $this->ps_manager->retire_purchased_solution( $ps->id ) ) {
							$need_to_retire --;
							$counts[ $ps->status ] --;
							$counts['retired'] ++;
							$updated_ps_ids[] = $ps->id;
						}
					}

					if ( $need_to_retire === 0 ) {
						break;
					}
				}
			}
		}
		// If this order item has been fully refunded, there is no need to do anything more.
		if ( 'refunded' === $purchased_solution['status'] ) {
			return true;
		}

		/*
		 * Handle creating new purchased solutions for this order item.
		 */
		if ( $counts['total'] < $purchased_solution['qty'] ) {
			// We are lacking some purchased solutions for this order item.
			while ( $counts['total'] < $purchased_solution['qty'] ) {
				$new_ps_args = [
					'status'        => 'invalid',
					'solution_id'   => $purchased_solution['solution_id'],
					'user_id'       => $purchased_solution['customer_id'],
					'order_id'      => $purchased_solution['order_id'],
					'order_item_id' => $purchased_solution['order_item_id'],
				];
				$new_ps_id   = $this->ps_manager->add_purchased_solution( $new_ps_args );
				if ( false === $new_ps_id ) {
					// We have failed to create a new purchased solution.
					// Log and bail completely.
					$this->logger->error(
						'Error inserting a new purchased solution for order #{order_id}, item #{order_item_id}.',
						[
							'order_id'      => $purchased_solution['order_id'],
							'order_item_id' => $purchased_solution['order_item_id'],
							'new_args'      => $new_ps_args,
							'logCategory'   => 'woocommerce',
						]
					);

					return false;
				}

				$updated_ps_ids[] = $new_ps_id;
				$counts['total'] ++;
				$counts['invalid'] ++;
			}

			// Refresh the corresponding purchased solutions since we have introduced new items.
			$found_ps = $this->ps_manager->get_purchased_solutions( [
				'order_id'      => $purchased_solution['order_id'],
				'order_item_id' => $purchased_solution['order_item_id'],
				// Since the id is auto-incremented, it is safe to use it as a marker for recency.
				'order_by'      => 'id',
				// The latest purchased solutions, first.
				'order'         => 'DESC',
			] );

			$encountered_ps_ids = array_unique( array_merge( $encountered_ps_ids, array_map( function ( $item ) {
				return $item->id;
			}, $found_ps ) ) );

			// Refresh the counts also.
			$counts = \wp_parse_args( $this->ps_manager->get_purchased_solution_counts( [
				'order_id'      => $purchased_solution['order_id'],
				'order_item_id' => $purchased_solution['order_item_id'],
			] ), [
				'total'   => 0,
				'ready'   => 0,
				'active'  => 0,
				'invalid' => 0,
				'retired' => 0,
			] );
		}

		/*
		 * Handle paid orders, meaning all purchased solutions related to them should NOT be invalid.
		 */
		if ( $purchased_solution['order']->is_paid() && $counts['invalid'] > 0 ) {
			foreach ( $found_ps as $ps ) {
				if ( 'invalid' === $ps->status ) {
					if ( ! empty( $ps->composition_id ) ) {
						// We can make this purchased solution directly active.
						if ( $this->ps_manager->activate_purchased_solution( $ps->id, $ps->composition_id ) ) {
							$counts[ $ps->status ] --;
							$counts['active'] ++;
							$updated_ps_ids[] = $ps->id;
						}
					} else {
						// We can make this purchased solution ready.
						if ( $this->ps_manager->ready_purchased_solution( $ps->id ) ) {
							$counts[ $ps->status ] --;
							$counts['ready'] ++;
							$updated_ps_ids[] = $ps->id;
						}
					}
				}

				if ( $counts['invalid'] === 0 ) {
					break;
				}
			}
		}
		// Since an order might be manually "resurrected" from a refunded or cancelled status (though this is a bad thing),
		// "resurrect" retired purchased solutions, but break their composition ID relation.
		if ( $purchased_solution['order']->is_paid() && $counts['retired'] > 0 ) {
			foreach ( $found_ps as $ps ) {
				if ( 'retired' === $ps->status ) {
					// We can make this purchased solution ready.
					if ( $this->ps_manager->update_purchased_solution( $ps->id, [
						'status'         => 'ready',
						'composition_id' => 0,
					] ) ) {
						$counts[ $ps->status ] --;
						$counts['ready'] ++;
						$updated_ps_ids[] = $ps->id;
					}
				}

				if ( $counts['retired'] === 0 ) {
					break;
				}
			}
		}

		/*
		 * Handle orders that need payment.
		 * This is a little sensible because this process may be destructive:
		 * - ready purchased solutions should be transitioned to invalid; this is safe;
		 * - active purchased solutions should also be transitioned to invalid; this has direct user consequences.
		 *   For active purchased solution we will leave a 7 days grace period from the order creation date.
		 *
		 * This is different from refunds since payments may fail for a variety of valid reasons.
		 */
		if ( $purchased_solution['order']->needs_payment() && $counts['ready'] > 0 ) {
			foreach ( $found_ps as $ps ) {
				if ( 'ready' === $ps->status ) {
					// We can make this purchased solution invalid.
					if ( $this->ps_manager->invalidate_purchased_solution( $ps->id ) ) {
						$counts[ $ps->status ] --;
						$counts['invalid'] ++;
						$updated_ps_ids[] = $ps->id;
					}
				}

				if ( $counts['ready'] === 0 ) {
					break;
				}
			}
		}
		if ( $purchased_solution['order']->needs_payment()
		     && $counts['active'] > 0
		     && ( time() - $purchased_solution['order']->get_date_created()->getTimestamp() ) > \DAY_IN_SECONDS * 7 ) {

			// After 7 days from order creation, we can safely tackle the active purchased solutions.
			// By this time, enough communication should have happened.
			foreach ( $found_ps as $ps ) {
				if ( 'active' === $ps->status ) {
					// We can make this purchased solution invalid.
					if ( $this->ps_manager->invalidate_purchased_solution( $ps->id ) ) {
						$counts[ $ps->status ] --;
						$counts['invalid'] ++;
						$updated_ps_ids[] = $ps->id;
					}
				}

				if ( $counts['active'] === 0 ) {
					break;
				}
			}
		}

		$updated_ps_ids = array_unique( $updated_ps_ids );

		return true;
	}

	/**
	 * Add WooCommerce specific data to a solution.
	 *
	 * @since 0.14.0
	 *
	 * @param array $solution_data The solution data.
	 * @param int   $post_id       The solution post ID.
	 *
	 * @return array The modified solution ID data.
	 */
	protected function add_solution_data( array $solution_data, int $post_id ): array {
		/**
		 * THE LINKED PRODUCT IDS.
		 */
		$solution_data['woocommerce_products'] = \carbon_get_post_meta( $post_id, 'solution_woocommerce_products' );
		// Add the un-formatted DB data, for reference.
		// Differences may arise from invalid products that are no longer part of the options list (CarbonFields removes them automatically).
		$solution_data['woocommerce_products_raw'] = carbon_get_raw_post_meta( $post_id, 'solution_woocommerce_products' );

		return $solution_data;
	}

	/**
	 * Handle WooCommerce specific solution query args.
	 *
	 * @since 0.14.0
	 *
	 * @param array $query_args The query args.
	 * @param array $args       The received args.
	 *
	 * @return array The modified solution ID data.
	 */
	protected function handle_solution_query_args( array $query_args, array $args ): array {
		if ( ! empty( $args['woocommerce_product_id'] ) && 'any' !== $args['woocommerce_product_id'] && is_numeric( $args['woocommerce_product_id'] ) ) {

			$args['woocommerce_product_id'] = \absint( $args['woocommerce_product_id'] );

			$query_args['meta_query'][] = [
				'key'   => 'solution_woocommerce_products',
				'compare' => 'IN',
				'value' => $args['woocommerce_product_id'],
			];
		}

		return $query_args;
	}

	/**
	 * Handle custom query vars to get products.
	 *
	 * @since 0.14.0
	 *
	 * @param array $query      Args for WP_Query.
	 * @param array $query_vars Query vars from WC_Product_Query.
	 *
	 * @return array The modified $query
	 */
	public function handle_custom_query_vars( array $query, array $query_vars ): array {
		/*
		 * Handle the 'linked_to_pdsolution' custom query var behavior:
		 * - true : Only products that are linked to a solution;
		 * - false : Only products that are not linked to a solution;
		 * - int : Only products that are linked to a certain solution post ID.
		 */
		if ( isset( $query_vars['linked_to_pdsolution'] ) ) {
			if ( true === $query_vars['linked_to_pdsolution'] ) {
				$query['meta_query'][] = [
					'key'     => self::PRODUCT_LINKED_TO_PDSOLUTION_META_KEY,
					'compare' => 'EXISTS',
				];
				$query['meta_query'][] = [
					'key'     => self::PRODUCT_LINKED_TO_PDSOLUTION_META_KEY,
					'value'   => 0,
					'compare' => '>',
				];
			} else if ( false === $query_vars['linked_to_pdsolution'] ) {
				$query['meta_query'][] = [
					'relation' => 'OR',
					[
						'key'     => self::PRODUCT_LINKED_TO_PDSOLUTION_META_KEY,
						'compare' => 'NOT EXISTS',
					],
					[
						'key'     => self::PRODUCT_LINKED_TO_PDSOLUTION_META_KEY,
						'value'   => 0,
						'compare' => '=',
					],
				];
			} else if ( absint( $query_vars['linked_to_pdsolution'] ) > 0 ) {
				$query['meta_query'][] = [
					'key'     => self::PRODUCT_LINKED_TO_PDSOLUTION_META_KEY,
					'compare' => 'EXISTS',
				];
				$query['meta_query'][] = [
					'key'     => self::PRODUCT_LINKED_TO_PDSOLUTION_META_KEY,
					'value'   => \absint( $query_vars['linked_to_pdsolution'] ),
					'compare' => '=',
				];
			}
		}

		/*
		 * Handle the 'not_linked_to_pdsolution' custom query var behavior:
		 * - int : Only products that are NOT linked to a certain solution post ID.
		 */
		if ( ! empty( $query_vars['not_linked_to_pdsolution'] ) && absint( $query_vars['not_linked_to_pdsolution'] ) > 0 ) {
			$query['meta_query'][] = [
				'relation' => 'OR',
				[
					'key'     => self::PRODUCT_LINKED_TO_PDSOLUTION_META_KEY,
					'compare' => 'NOT EXISTS',
				],
				[
					'key'     => self::PRODUCT_LINKED_TO_PDSOLUTION_META_KEY,
					'value'   => 0,
					'compare' => '=',
				],
				[
					'key'     => self::PRODUCT_LINKED_TO_PDSOLUTION_META_KEY,
					'value'   => \absint( $query_vars['not_linked_to_pdsolution'] ),
					'compare' => '!=',
				],
			];
		}

		return $query;
	}

	/**
	 * Given a product, return its linked PD Solution.
	 *
	 * @since 0.14.0
	 *
	 * @param mixed $product WC_Product|WP_Post|int|bool $product Product instance, post instance, numeric or false to use global $post.
	 *
	 * @return int|false The linked PD Solution post ID or false on no linked PD Solution or failure.
	 */
	public static function get_product_linked_pdsolution( $product = false ) {
		$product = \wc_get_product( $product );
		if ( empty( $product ) ) {
			return false;
		}

		$solution_id = \get_post_meta( $product->get_id(), WooCommerce::PRODUCT_LINKED_TO_PDSOLUTION_META_KEY, true );
		if ( empty( $solution_id ) ) {
			return false;
		}

		return \absint( $solution_id );
	}
}
