<?php
/**
 * Site compositions logic provider.
 *
 * @since   1.0.0
 * @license GPL-2.0-or-later
 * @package PixelgradeLT
 */

declare ( strict_types=1 );

namespace PixelgradeLT\Retailer\Provider;

use Cedaro\WP\Plugin\AbstractHookProvider;

/**
 * Site compositions logic provider class.
 *
 * @since 1.0.0
 */
class Compositions extends AbstractHookProvider {

	/**
	 * Register hooks.
	 *
	 * @since 1.0.0
	 */
	public function register_hooks() {
		$this->add_filter( 'pixelgradelt_retailer/check_user_details', 'check_user_details', 10, 3 );
		$this->add_filter( 'pixelgradelt_retailer/details_to_update_composition', 'details_to_update_composition', 10, 3 );
	}

	/**
	 * @param bool|\WP_Error $valid        Whether the user details are valid.
	 * @param array          $user_details The user details as decrypted from the composition details.
	 * @param array          $composer     The full composition details.
	 *
	 * @return bool|\WP_Error
	 */
	protected function check_user_details( $valid, array $user_details, array $composer ) {
		// Do nothing if the user details have already been marked as invalid.
		if ( is_wp_error( $valid ) || true !== $valid ) {
			return $valid;
		}

		// Prepare an errors holder.
		$errors = new \WP_Error();

		if ( ! empty( $user_details['userid'] ) && ! empty( $user_id = absint( $user_details['userid'] ) ) ) {
			$user = get_user_by( 'id', $user_id );
			if ( false === $user ) {
				$errors->add( 'not_found', esc_html__( 'Couldn\'t find a user with the provided user ID.', 'pixelgradelt_retailer' ) );
			}
		} else {
			$errors->add( 'missing_or_empty', esc_html__( 'Missing or empty user ID.', 'pixelgradelt_retailer' ) );
		}

		if ( empty( $site_id = $user_details['siteid'] ) ) {
			$errors->add( 'missing_or_empty', esc_html__( 'Missing or empty site ID.', 'pixelgradelt_retailer' ) );
		}
		// @todo Check that the provided site ID is valid (belongs to an existing site). This should probably be checked with LT Deck.

		if ( ! empty( $user_details['orderid'] ) && function_exists( '\wc_get_order' ) && class_exists( '\WC_Order' ) ) {
			if ( ! is_array( $user_details['orderid'] ) ) {
				$errors->add( 'malformed', esc_html__( '"orderid" must be a list of WooCommerce order ids (integers).', 'pixelgradelt_retailer' ) );
			} else {
				// We will allow the check if at least one of the orders are active (not refunded, etc).
				$has_active_orders = 0;
				foreach ( $user_details['orderid'] as $order_id ) {
					$order = \wc_get_order( $order_id );
					if ( ! $order instanceof \WC_Order ) {
						$errors->add( 'not_found', esc_html__( 'Couldn\'t find at least one of the provided order IDs.', 'pixelgradelt_retailer' ) );
						break;
					}

					if ( ! empty( $user ) && $user->ID !== $order->get_user_id( 'edit' ) ) {
						$errors->add( 'mismatch', esc_html__( 'At least one of the provided order IDs doesn\'t belong to the provided user ID.', 'pixelgradelt_retailer' ) );
						break;
					}

					// We exclude on-hold orders as they are still pending payment.
					if ( 'refunded' !== $order->get_status() && in_array( $order->get_status(), [
							'completed',
							'processing',
							'refunded',
						] ) ) {
						$has_active_orders ++;
					}

					// @todo We should check for active or inactive subscriptions.
				}

				if ( ! $has_active_orders ) {
					$errors->add( 'ecommerce', esc_html__( 'Couldn\'t find at least one of the provided order IDs that is active.', 'pixelgradelt_retailer' ) );
				}
			}
		}

		if ( $errors->has_errors() ) {
			return $errors;
		}

		return true;
	}

	/**
	 * @param bool|array $details_to_update The new composition details.
	 * @param array      $user_details      The received user details, already checked.
	 * @param array      $composer          The full composition details.
	 *
	 * @return bool|array false if there is a reason to reject to attempt. Empty array if there is nothing to update.
	 */
	protected function details_to_update_composition( $details_to_update, array $user_details, array $composer ) {
		// Do nothing if we should already reject.
		if ( false === $details_to_update ) {
			return $details_to_update;
		}

		// @todo Get the site ID details and maybe update them (site name, etc).

		// @todo Maybe determine all the orders that are attached to the site ID.

		// @todo Go through all the orders (and related ones) in $user_details and redetermine the parts to be present. Return ALL the parts that should be used.

		// @todo Make sure that we remove other LT parts left around with the `remove` entry in $details_to_update.

		return $details_to_update;
	}
}
