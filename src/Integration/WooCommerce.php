<?php
/**
 * WooCommerce plugin integration.
 *
 * @since   0.14.0
 * @license GPL-2.0-or-later
 * @package PixelgradeLT
 */

declare ( strict_types=1 );

namespace PixelgradeLT\Retailer\Integration;

use BerlinDB\Database\Table;
use Cedaro\WP\Plugin\AbstractHookProvider;
use PixelgradeLT\Retailer\Integration\WooCommerce\Order;
use PixelgradeLT\Retailer\PurchasedSolutionManager;
use Psr\Log\LoggerInterface;
use function PixelgradeLT\Retailer\carbon_get_raw_post_meta;

/**
 * WooCommerce plugin integration provider class.
 *
 * @since 0.14.0
 */
class WooCommerce extends AbstractHookProvider {

	/**
	 * @since 0.14.0
	 */
	const PRODUCT_LINKED_TO_LTSOLUTION_META_KEY = '_linked_to_ltsolution';

	/**
	 * The Purchased Solutions Manager.
	 *
	 * @since 0.14.0
	 *
	 * @var PurchasedSolutionManager
	 */
	protected PurchasedSolutionManager $ps_manager;

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
	 * @param Table                    $db                         The instance handling the custom DB table.
	 * @param LoggerInterface          $logger                     Logger.
	 */
	public function __construct(
		PurchasedSolutionManager $purchased_solution_manager,
		Table $db,
		LoggerInterface $logger
	) {
		$this->ps_manager = $purchased_solution_manager;
		$this->db         = $db;
		$this->logger     = $logger;
	}

	/**
	 * Register hooks.
	 *
	 * @since 0.14.0
	 */
	public function register_hooks() {
		// Handle LT Solutions.
		$this->add_filter( 'pixelgradelt_retailer/solution_id_data', 'add_solution_data', 5, 2 );
		$this->add_filter( 'pixelgradelt_retailer/solution_ids_by_query_args', 'handle_solution_query_args', 10, 2 );

		// Handle WooCommerce Products query custom query vars.
		add_filter( 'woocommerce_product_data_store_cpt_get_products_query', [
			$this,
			'handle_custom_query_vars',
		], 10, 2 );

		/**
		 * HANDLE ORDERS AND THE SYNC WITH PURCHASED SOLUTIONS.
		 */
		$this->add_action( 'woocommerce_new_order', 'handle_order_update', 10, 2 );
		$this->add_action( 'woocommerce_update_order', 'handle_order_update', 10, 2 );
		$this->add_action( 'woocommerce_delete_order', 'handle_order_delete', 10, 1 );
		$this->add_action( 'woocommerce_trash_order', 'handle_order_trash', 10, 1 );
	}

	/**
	 * Handle WooCommerce order update (or creation).
	 *
	 * We will extract any purchased solutions from the order and make sure that we update/create them all.
	 *
	 * @param int       $order_id
	 * @param \WC_Order $order
	 */
	protected function handle_order_update( int $order_id, \WC_Order $order ) {
		$purchased_solutions_details = Order::get_purchased_solutions( $order );
		// Bail if this order doesn't contain purchased solutions.
		if ( empty( $purchased_solutions_details ) ) {
			return;
		}

		// Remember the purchased solutions we update, so we can track down those that may have become orphans.
		$encountered_purchased_solutions = [];
		// Remember the purchased solutions we update.
		$updated_purchased_solutions = [];
		foreach ( $purchased_solutions_details as $current_details ) {
			$counts = \wp_parse_args( $this->ps_manager->get_purchased_solution_counts( [
				'order_id'      => $current_details['order_id'],
				'order_item_id' => $current_details['order_item_id'],
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
				'order_id'      => $current_details['order_id'],
				'order_item_id' => $current_details['order_item_id'],
				// Since the id is auto-incremented, it is safe to use it as a marker for recency.
				'order_by'      => 'id',
				// The latest purchased solutions, first.
				'order'         => 'DESC',
			] );

			$encountered_purchased_solutions = array_unique( array_merge( $encountered_purchased_solutions, array_map( function ( $item ) {
				return $item->id;
			}, $found_ps ) ) );

			/*
			 * Handle fully or partial refunded order item (aka the entire quantity, or only some of it).
			 */

			// Refunded order items will lead to the `retired` status.
			if ( $current_details['refunded_qty'] > 0 && $counts['retired'] < $current_details['refunded_qty'] ) {
				// We need to retire some purchased solutions due to refunded order items.
				$need_to_retire = $current_details['refunded_qty'] - $counts['retired'];
				// First, the invalid purchased solutions.
				if ( $need_to_retire > 0 && $counts['invalid'] > 0 ) {
					foreach ( $found_ps as $ps ) {
						if ( 'invalid' === $ps->status ) {
							// We can retire this purchased solution.
							if ( $this->ps_manager->retire_purchased_solution( $ps->id ) ) {
								$need_to_retire --;
								$counts['invalid'] --;
								$counts['retired'] ++;
								$updated_purchased_solutions[] = $ps->id;
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
								$counts['ready'] --;
								$counts['retired'] ++;
								$updated_purchased_solutions[] = $ps->id;
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
								$counts['active'] --;
								$counts['retired'] ++;
								$updated_purchased_solutions[] = $ps->id;
							}
						}

						if ( $need_to_retire === 0 ) {
							break;
						}
					}
				}
			}
			// If this order item has been fully refunded, there is no need to do anything more.
			if ( 'refunded' === $current_details['status'] ) {
				continue;
			}

			/*
			 * Handle creating new purchased solutions for this order item.
			 */
			if ( $counts['total'] < $current_details['qty'] ) {
				// We are lacking some purchased solutions for this order item.
				while ( $counts['total'] < $current_details['qty'] ) {
					$new_ps_args = [
						'status'        => 'invalid',
						'solution_id'   => $current_details['solution_id'],
						'user_id'       => $current_details['customer_id'],
						'order_id'      => $current_details['order_id'],
						'order_item_id' => $current_details['order_item_id'],
					];
					$new_ps_id   = $this->ps_manager->add_purchased_solution( $new_ps_args );
					if ( false === $new_ps_id ) {
						// We have failed to create a new purchased solution.
						// Log and bail completely.
						$this->logger->error(
							'Error inserting a new purchased solution for order #{order_id}, item #{order_item_id}.',
							[
								'order_id'      => $current_details['order_id'],
								'order_item_id' => $current_details['order_item_id'],
								'new_args'      => $new_ps_args,
								'logCategory'   => 'woocommerce',
							]
						);

						return;
					}

					$updated_purchased_solutions[] = $new_ps_id;
					$counts['total'] ++;
					$counts['invalid'] ++;
				}

				// Refresh the corresponding purchased solutions since we have introduced new items.
				$found_ps = $this->ps_manager->get_purchased_solutions( [
					'order_id'      => $current_details['order_id'],
					'order_item_id' => $current_details['order_item_id'],
					// Since the id is auto-incremented, it is safe to use it as a marker for recency.
					'order_by'      => 'id',
					// The latest purchased solutions, first.
					'order'         => 'DESC',
				] );

				$encountered_purchased_solutions = array_unique( array_merge( $encountered_purchased_solutions, array_map( function ( $item ) {
					return $item->id;
				}, $found_ps ) ) );

				// Refresh the counts also.
				$counts = \wp_parse_args( $this->ps_manager->get_purchased_solution_counts( [
					'order_id'      => $current_details['order_id'],
					'order_item_id' => $current_details['order_item_id'],
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
			if ( $order->is_paid() && $counts['invalid'] > 0 ) {
				foreach ( $found_ps as $ps ) {
					if ( 'invalid' === $ps->status ) {
						if ( ! empty( $ps->composition_id ) ) {
							// We can make this purchased solution directly active.
							if ( $this->ps_manager->activate_purchased_solution( $ps->id, $ps->composition_id ) ) {
								$counts['invalid'] --;
								$counts['active'] ++;
								$updated_purchased_solutions[] = $ps->id;
							}
						} else {
							// We can make this purchased solution ready.
							if ( $this->ps_manager->ready_purchased_solution( $ps->id ) ) {
								$counts['invalid'] --;
								$counts['ready'] ++;
								$updated_purchased_solutions[] = $ps->id;
							}
						}
					}

					if ( $counts['invalid'] === 0 ) {
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
			if ( $order->needs_payment() && $counts['ready'] > 0 ) {
				foreach ( $found_ps as $ps ) {
					if ( 'ready' === $ps->status ) {
						// We can make this purchased solution invalid.
						if ( $this->ps_manager->invalidate_purchased_solution( $ps->id ) ) {
							$counts['invalid'] ++;
							$counts['ready'] --;
							$updated_purchased_solutions[] = $ps->id;
						}
					}

					if ( $counts['ready'] === 0 ) {
						break;
					}
				}
			}
			if ( $order->needs_payment()
			     && $counts['active'] > 0
			     && ( time() - $order->get_date_created()->getTimestamp() ) > \DAY_IN_SECONDS * 7 ) {

				// After 7 days from order creation, we can safely tackle the active purchased solutions.
				// By this time, enough communication should have happened.
				foreach ( $found_ps as $ps ) {
					if ( 'active' === $ps->status ) {
						// We can make this purchased solution invalid.
						if ( $this->ps_manager->invalidate_purchased_solution( $ps->id ) ) {
							$counts['invalid'] ++;
							$counts['active'] --;
							$updated_purchased_solutions[] = $ps->id;
						}
					}

					if ( $counts['active'] === 0 ) {
						break;
					}
				}
			}

			// Pretty much done with this purchased solution matching an order item. On to the next one.
		}

		$updated_purchased_solutions = array_unique( $updated_purchased_solutions );

		/*
		 * One last thing to do: track down possibly orphaned purchased solutions.
		 *
		 * If we can find more purchased solutions related to this order ID (without the order item ID) than
		 * the number we've encountered, this means that are some orphan items that should be retired.
		 */
		$order_related_ps_count = $this->ps_manager->count_purchased_solutions( [
			'order_id' => $order_id,
		] );
		if ( $order_related_ps_count > count( $encountered_purchased_solutions ) ) {
			$orphan_ps = $this->ps_manager->get_purchased_solutions( [
				'id__not_in' => $encountered_purchased_solutions,
				'order_id' => $order_id,
				'status__not_in' => ['retired'],
			] );
			foreach ( $orphan_ps as $ps ) {
				$this->ps_manager->retire_purchased_solution( $ps->id );
			}
		}

		// All done!
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
		 * Handle the 'linked_to_ltsolution' custom query var behavior:
		 * - true : Only products that are linked to a solution;
		 * - false : Only products that are not linked to a solution;
		 * - int : Only products that are linked to a certain solution post ID.
		 */
		if ( isset( $query_vars['linked_to_ltsolution'] ) ) {
			if ( true === $query_vars['linked_to_ltsolution'] ) {
				$query['meta_query'][] = [
					'key'     => self::PRODUCT_LINKED_TO_LTSOLUTION_META_KEY,
					'compare' => 'EXISTS',
				];
				$query['meta_query'][] = [
					'key'     => self::PRODUCT_LINKED_TO_LTSOLUTION_META_KEY,
					'value'   => 0,
					'compare' => '>',
				];
			} else if ( false === $query_vars['linked_to_ltsolution'] ) {
				$query['meta_query'][] = [
					'relation' => 'OR',
					[
						'key'     => self::PRODUCT_LINKED_TO_LTSOLUTION_META_KEY,
						'compare' => 'NOT EXISTS',
					],
					[
						'key'     => self::PRODUCT_LINKED_TO_LTSOLUTION_META_KEY,
						'value'   => 0,
						'compare' => '=',
					],
				];
			} else if ( absint( $query_vars['linked_to_ltsolution'] ) > 0 ) {
				$query['meta_query'][] = [
					'key'     => self::PRODUCT_LINKED_TO_LTSOLUTION_META_KEY,
					'compare' => 'EXISTS',
				];
				$query['meta_query'][] = [
					'key'     => self::PRODUCT_LINKED_TO_LTSOLUTION_META_KEY,
					'value'   => \absint( $query_vars['linked_to_ltsolution'] ),
					'compare' => '=',
				];
			}
		}

		/*
		 * Handle the 'not_linked_to_ltsolution' custom query var behavior:
		 * - int : Only products that are NOT linked to a certain solution post ID.
		 */
		if ( ! empty( $query_vars['not_linked_to_ltsolution'] ) && absint( $query_vars['not_linked_to_ltsolution'] ) > 0 ) {
			$query['meta_query'][] = [
				'relation' => 'OR',
				[
					'key'     => self::PRODUCT_LINKED_TO_LTSOLUTION_META_KEY,
					'compare' => 'NOT EXISTS',
				],
				[
					'key'     => self::PRODUCT_LINKED_TO_LTSOLUTION_META_KEY,
					'value'   => 0,
					'compare' => '=',
				],
				[
					'key'     => self::PRODUCT_LINKED_TO_LTSOLUTION_META_KEY,
					'value'   => \absint( $query_vars['not_linked_to_ltsolution'] ),
					'compare' => '!=',
				],
			];
		}

		return $query;
	}

	/**
	 * Given a product, return its linked LT Solution.
	 *
	 * @since 0.14.0
	 *
	 * @param mixed $product WC_Product|WP_Post|int|bool $product Product instance, post instance, numeric or false to use global $post.
	 *
	 * @return int|false The linked LT Solution post ID or false on no linked LT Solution or failure.
	 */
	public static function get_product_linked_ltsolution( $product = false ) {
		$product = \wc_get_product( $product );
		if ( empty( $product ) ) {
			return false;
		}

		$solution_id = \get_post_meta( $product->get_id(), WooCommerce::PRODUCT_LINKED_TO_LTSOLUTION_META_KEY, true );
		if ( empty( $solution_id ) ) {
			return false;
		}

		return \absint( $solution_id );
	}
}
