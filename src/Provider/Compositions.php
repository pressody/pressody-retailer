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
use PixelgradeLT\Retailer\CompositionManager;
use PixelgradeLT\Retailer\SolutionManager;
use Psr\Log\LoggerInterface;
use function PixelgradeLT\Retailer\local_rest_call;

/**
 * Site compositions logic provider class.
 *
 * @since 1.0.0
 */
class Compositions extends AbstractHookProvider {

	/**
	 * Composition manager.
	 *
	 * @var CompositionManager
	 */
	protected CompositionManager $composition_manager;

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
	 * @since 0.11.0
	 *
	 * @param CompositionManager $composition_manager Composition manager.
	 * @param SolutionManager    $solution_manager    Solutions manager.
	 * @param LoggerInterface    $logger              Logger.
	 */
	public function __construct(
		CompositionManager $composition_manager,
		SolutionManager $solution_manager,
		LoggerInterface $logger
	) {
		$this->composition_manager = $composition_manager;
		$this->solution_manager    = $solution_manager;
		$this->logger              = $logger;
	}

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

		if ( empty( $composition_hashid = $user_details['compositionid'] ) ) {
			$errors->add( 'missing_or_empty', esc_html__( 'Missing or empty composition ID.', 'pixelgradelt_retailer' ) );
		}

		// Check if the composition hashid represents a valid composition.
		$composition_data = $this->composition_manager->get_composition_data_by( [ 'hashid' => $composition_hashid, ] );
		if ( empty( $composition_data ) ) {
			$errors->add( 'not_found', esc_html__( 'Couldn\'t find a composition with the provided composition hashid.', 'pixelgradelt_retailer' ) );
		}
		// Check if the user is the same user that owns the composition.
		else if ( ! empty( $user_id ) && $user_id !== absint( $composition_data['user']['id'] ) ) {
			$errors->add( 'invalid', esc_html__( 'The user that owns the composition is not the same as the provided user.', 'pixelgradelt_retailer' ) );
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
	 * @return bool|\WP_Error|array false or WP_Error if there is a reason to reject to attempt. Empty array if there is nothing to update.
	 */
	protected function details_to_update_composition( $details_to_update, array $user_details, array $composer ) {
		// Do nothing if we should already reject.
		if ( false === $details_to_update || is_wp_error( $details_to_update ) ) {
			return $details_to_update;
		}

		// Get all data about the composition.
		$composition_data = $this->composition_manager->get_composition_data_by( [ 'hashid' => $user_details['compositionid'], ], true );

		// Get the solutions IDs and context.
		$solutionsIds     = $this->composition_manager->get_post_composition_required_solutions_ids( $composition_data['required_solutions'] );
		$solutionsContext = $this->composition_manager->get_post_composition_required_solutions_context( $composition_data['required_solutions'] );

		// We have a conundrum when it comes to updating the required LT Parts: what happens with the removed LT Parts?
		// Sure, we can easily add, but what do we do with leftover LT Parts?
		// Current answer: remove all LT Parts before adding the current ones.
		if ( ! empty( $composer['require'] ) ) {
			if ( empty( $details_to_update['remove'] ) ) {
				$details_to_update['remove'] = [];
			}
			// To keep things simple, get all the LT Parts available from LT Records and remove any that we find.
			$all_ltparts = $this->solution_manager->get_ltrecords_parts();
			if ( is_wp_error( $all_ltparts ) ) {
				// We have failed to get the LT Parts from LT Records. Log and move on.
				$this->logger->error(
					'Error fetching the all the LT Parts from LT Records for the composition with post ID #{post_id} and hashid "{hashid}": {message}',
					[
						'post_id' => $composition_data['id'],
						'hashid'  => $composition_data['hashid'],
						'message'   => $all_ltparts->get_error_message(),
					]
				);
			} else {
				foreach ( $all_ltparts as $ltpart_package_name ) {
					if ( isset( $composer['require'][ $ltpart_package_name ] ) ) {
						$details_to_update['remove'][ $ltpart_package_name ] = [
							'name' => $ltpart_package_name,
						];
					}
				}
			}
		}

		// Get the LT parts the composition solutions require.
		// By default we don't require any LT Part.
		$details_to_update['require'] = [];
		if ( ! empty( $solutionsIds ) ) {
			$required_parts = local_rest_call( '/pixelgradelt_retailer/v1/solutions/parts', 'GET', [], [
				'postId'           => $solutionsIds,
				'solutionsContext' => $solutionsContext,
			] );

			if ( isset( $required_parts['code'] ) || isset( $required_parts['message'] ) ) {
				// We have failed to get the solutions' LT Parts. Log and move on.
				$this->logger->error(
					'Error fetching the composition solutions\' LT Parts for the composition with post ID #{post_id} and hashid "{hashid}".',
					[
						'post_id'  => $composition_data['id'],
						'hashid'   => $composition_data['hashid'],
						'response' => $required_parts,
					]
				);
			} elseif ( ! empty( $required_parts ) ) {
				$details_to_update['require'] = $required_parts;
			}
		}

		// If the user details are different, add them.
		if ( $this->should_update_user( $composition_data, $user_details ) ) {
			// Get the encrypted form of the composition user details.
			$details_to_update['user'] = $this->composition_manager->get_post_composition_encrypted_user_details( $composition_data );
		}

		// @todo Maybe handle other composer.json entries by passing them in the 'composer' entry of $details_to_update.

		return $details_to_update;
	}

	protected function should_update_user( array $composition_data, array $old_user_details ): bool {
		if ( $composition_data['user']['id'] != $old_user_details['userid'] ) {
			return true;
		}

		if ( $composition_data['hashid'] != $old_user_details['compositionid'] ) {
			return true;
		}

		if ( isset( $composition_data['user']['email'] )
		     && ! isset( $old_user_details['extra']['email'] ) ) {
			return true;
		}
		if ( $composition_data['user']['email'] != $old_user_details['extra']['email'] ) {
			return true;
		}

		if ( isset( $composition_data['user']['username'] )
		     && ! isset( $old_user_details['extra']['username'] ) ) {
			return true;
		}
		if ( $composition_data['user']['username'] != $old_user_details['extra']['username'] ) {
			return true;
		}

		return false;
	}
}
