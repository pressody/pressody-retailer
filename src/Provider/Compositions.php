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
		$this->add_filter( 'pixelgradelt_retailer/validate_user_details', 'validate_user_details', 10, 2 );
		$this->add_filter( 'pixelgradelt_retailer/details_to_update_composition', 'details_to_update_composition', 10, 3 );
	}

	/**
	 * @param bool|\WP_Error $valid        Whether the user details are valid.
	 * @param array          $user_details The user details as decrypted from the composition details.
	 *
	 * @return bool|\WP_Error
	 */
	protected function validate_user_details( $valid, array $user_details ) {
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

		if ( empty( $composition_id = $user_details['compositionid'] ) ) {
			$errors->add( 'missing_or_empty', esc_html__( 'Missing or empty composition ID.', 'pixelgradelt_retailer' ) );
		}
		// @todo Check that the composition ID is valid.

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
	 * @return bool|\WP_Error|array false or WP_Error if there is a reason to reject to attempt. Empty array if there is nothing to update.
	 */
	protected function details_to_update_composition( $details_to_update, array $user_details, array $composer ) {
		// Do nothing if we should already reject.
		if ( false === $details_to_update || is_wp_error( $details_to_update ) ) {
			return $details_to_update;
		}

		// @todo Get the site ID details and maybe update them (site name, etc).

		// @todo Maybe determine all the orders that are attached to the site ID.

		// @todo Go through all the orders (and related ones) in $user_details and redetermine the parts to be present. Return ALL the parts that should be used.

		// @todo Make sure that we remove other LT parts left around with the `remove` entry in $details_to_update.

		return $details_to_update;
	}
}
