<?php
/**
 * Site compositions logic provider.
 *
 * @since   1.0.0
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

namespace Pressody\Retailer\Provider;

use Cedaro\WP\Plugin\AbstractHookProvider;
use Pressody\Retailer\CompositionManager;
use Pressody\Retailer\SolutionManager;
use Psr\Log\LoggerInterface;
use function Pressody\Retailer\local_rest_call;

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
		$this->add_filter( 'pressody_retailer/validate_composition_pddetails', 'validate_composition_pddetails', 10, 2 );
		$this->add_filter( 'pressody_retailer/instructions_to_update_composition', 'instructions_to_update_composition', 10, 3 );
	}

	/**
	 * Validate the received composition details.
	 *
	 * @param bool|\WP_Error $valid   Whether the composition PD details are valid.
	 * @param array          $details The composition PD details as decrypted from the composition data.
	 *
	 * @return bool|\WP_Error True on valid. False or WP_Error on invalid.
	 */
	protected function validate_composition_pddetails( $valid, array $details ) {
		// Do nothing if the composition details have already been marked as invalid.
		if ( is_wp_error( $valid ) || true !== $valid ) {
			return $valid;
		}

		// Prepare an errors holder.
		$errors = new \WP_Error();

		if ( ! empty( $details['userids'] ) ) {
			// Check that AT LEAST a user ID actually belongs to a valid user.
			$valid_user_ids = array_filter( $details['userids'], function( $userid ) {
				return false !== get_user_by( 'id', $userid );
			} );
			if ( empty( $valid_user_ids ) ) {
				$errors->add( 'not_found', esc_html__( 'Couldn\'t find at least a valid user with the provided user IDs.', 'pressody_retailer' ) );
			}
		} else {
			$errors->add( 'missing_or_empty', esc_html__( 'Missing or empty user IDs.', 'pressody_retailer' ) );
		}

		if ( empty( $composition_hashid = $details['compositionid'] ) ) {
			$errors->add( 'missing_or_empty', esc_html__( 'Missing or empty composition ID.', 'pressody_retailer' ) );
		}

		// Check if the composition hashid represents a valid composition.
		$composition_data = $this->composition_manager->get_composition_data_by( [ 'hashid' => $composition_hashid, ] );
		if ( empty( $composition_data ) ) {
			$errors->add( 'not_found', esc_html__( 'Couldn\'t find a composition with the provided composition hashid.', 'pressody_retailer' ) );
		} else if ( ! empty( $valid_user_ids ) ) {
			// Check if at least a provided, valid user is among the current composition owners.
			$composition_user_ids = array_filter( array_keys( $composition_data['users'] ) );
			sort( $composition_user_ids );
			if ( ! empty( $composition_user_ids ) && empty( array_intersect( $valid_user_ids, $composition_user_ids ) ) ) {
				$errors->add( 'invalid', esc_html__( 'None of the provided users is among the composition\'s current owners.', 'pressody_retailer' ) );
			}
		}

		// Check the composition status. We want to let through only ready or active compositions.
		if ( ! empty( $composition_data ) && ! in_array( $composition_data['status'], [ 'ready', 'active', ] ) ) {
			$errors->add( 'status',
				sprintf( esc_html__( 'According to the composition\'s status (%1$s), the composition is not fit for use.', 'pressody_retailer' ), $composition_data['status'] )
			);
		}

		if ( $errors->has_errors() ) {
			return $errors;
		}

		return true;
	}

	/**
	 * Generate the instructions to update a composition by.
	 *
	 * @param bool|array $instructions_to_update The instructions to update the composition by.
	 * @param array      $composition_pddetails  The decrypted composition PD details, already checked.
	 * @param array      $composer               The full composition data.
	 *
	 * @return bool|\WP_Error|array false or WP_Error if there is a reason to reject the attempt. Empty array if there is nothing to update.
	 */
	protected function instructions_to_update_composition( $instructions_to_update, array $composition_pddetails, array $composer ) {
		// Do nothing if we should already reject.
		if ( false === $instructions_to_update || is_wp_error( $instructions_to_update ) ) {
			return $instructions_to_update;
		}

		if ( empty( $composition_pddetails['compositionid'] ) ) {
			return new \WP_Error(
				'missing_compositionid',
				'The composition\'s PD details are missing the "compositionid".',
			);
		}

		// Get all data about the composition.
		$composition_data = $this->composition_manager->get_composition_data_by( [ 'hashid' => $composition_pddetails['compositionid'], ], true );
		if ( empty( $composition_data ) ) {
			return new \WP_Error(
				'composition_not_found',
				'The composition with the PD details "compositionid" could not be found.',
			);
		}

		// Get the solutions IDs and context.
		$solutionsIds     = $this->composition_manager->extract_required_solutions_post_ids( $composition_data['required_solutions'] );
		$solutionsContext = $this->composition_manager->extract_required_solutions_context( $composition_data['required_solutions'] );

		// We have a conundrum when it comes to updating the required PD Parts: what happens with the removed PD Parts?
		// Sure, we can easily add, but what do we do with leftover PD Parts?
		// Current answer: remove all PD Parts before adding the current ones.
		if ( ! empty( $composer['require'] ) ) {
			if ( empty( $instructions_to_update['remove'] ) ) {
				$instructions_to_update['remove'] = [];
			}
			// To keep things simple, get all the PD Parts available from PD Records and remove any that we find.
			$all_pdparts = $this->solution_manager->get_pdrecords_parts();
			if ( is_wp_error( $all_pdparts ) ) {
				// We have failed to get the PD Parts from PD Records. Log and move on.
				$this->logger->error(
					'Error fetching all the PD Parts from PD Records for the composition with post ID #{post_id} and hashid "{hashid}": {message}',
					[
						'post_id' => $composition_data['id'],
						'hashid'  => $composition_data['hashid'],
						'message' => $all_pdparts->get_error_message(),
					]
				);
			} else {
				foreach ( $all_pdparts as $pdpart_package_name ) {
					if ( isset( $composer['require'][ $pdpart_package_name ] ) ) {
						$instructions_to_update['remove'][ $pdpart_package_name ] = [
							'name' => $pdpart_package_name,
						];
					}
				}
			}
		}

		// Get the PD parts the composition solutions require.
		// By default, we don't require any PD Part.
		$instructions_to_update['require'] = [];
		if ( ! empty( $solutionsIds ) ) {
			$required_parts = local_rest_call( '/pressody_retailer/v1/solutions/parts', 'GET', [
				'postId'           => $solutionsIds,
				'solutionsContext' => $solutionsContext,
			] );

			if ( isset( $required_parts['code'] ) || isset( $required_parts['message'] ) ) {
				// We have failed to get the solutions' PD Parts. Log and move on.
				$this->logger->error(
					'Error fetching the composition solutions\' PD Parts for the composition with post ID #{post_id} and hashid "{hashid}".',
					[
						'post_id'  => $composition_data['id'],
						'hashid'   => $composition_data['hashid'],
						'response' => $required_parts,
					]
				);
			} elseif ( ! empty( $required_parts ) ) {
				$instructions_to_update['require'] = $required_parts;
			}
		}

		// If the PD details are different, add them.
		if ( $this->should_update_pddetails( $composition_data, $composition_pddetails ) ) {
			// Get the encrypted form of the composition's PD details.
			$instructions_to_update['pddetails'] = $this->composition_manager->get_post_composition_encrypted_pddetails( $composition_data );
		}

		// @todo Maybe handle other composer.json entries by passing them in the 'composer' entry of $instructions_to_update.

		return $instructions_to_update;
	}

	/**
	 * Check if a composition's details need to be updated.
	 *
	 * @param array $composition_data The full composition data.
	 * @param array $old_pddetails The old composition PD details, already checked.
	 *
	 * @return bool
	 */
	protected function should_update_pddetails( array $composition_data, array $old_pddetails ): bool {
		// If there is a change in the composition owners/users list, we need to update.
		$old_userids = $old_pddetails['userids'];
		$new_userids = array_filter( array_keys( $composition_data['users'] ) );
		sort( $old_userids );
		sort( $new_userids );
		if ( $old_userids != $new_userids ) {
			return true;
		}

		// If the hashid has changed, we need to update.
		if ( $composition_data['hashid'] != $old_pddetails['compositionid'] ) {
			return true;
		}

		return false;
	}
}
