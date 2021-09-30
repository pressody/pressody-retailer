<?php
/**
 * Capabilities provider.
 *
 * @since   0.1.0
 * @license GPL-2.0-or-later
 * @package PixelgradeLT
 */

declare ( strict_types=1 );

namespace PixelgradeLT\Retailer\Provider;

use Cedaro\WP\Plugin\AbstractHookProvider;
use PixelgradeLT\Retailer\Capabilities as Caps;
use PixelgradeLT\Retailer\CompositionManager;

/**
 * Capabilities provider class.
 *
 * @since 0.1.0
 */
class Capabilities extends AbstractHookProvider {

	/**
	 * Composition manager.
	 *
	 * @var CompositionManager
	 */
	protected CompositionManager $composition_manager;

	/**
	 * Constructor.
	 *
	 * @since 0.11.0
	 *
	 * @param CompositionManager $composition_manager Composition manager.
	 */
	public function __construct(
		CompositionManager $composition_manager
	) {
		$this->composition_manager = $composition_manager;
	}

	/**
	 * Register hooks.
	 *
	 * @since 0.1.0
	 */
	public function register_hooks() {
		add_filter( 'map_meta_cap', [ $this, 'map_meta_cap' ], 10, 4 );
	}

	/**
	 * Map meta capabilities to primitive capabilities.
	 *
	 * @since 0.1.0
	 *
	 * @param array  $caps    Primitive capabilities required of the user.
	 * @param string $cap     Capability being checked.
	 * @param int    $user_id The user ID.
	 * @param array  $args    Adds context to the capability check, typically
	 *                        starting with an object ID.
	 *
	 * @return array
	 */
	public function map_meta_cap( array $caps, string $cap, int $user_id, array $args ): array {
		switch ( $cap ) {
			case Caps::VIEW_SOLUTION :
				$caps = [ Caps::VIEW_SOLUTIONS ]; // VIEW_SOLUTION maps to VIEW_SOLUTIONS.
				break;
			case Caps::VIEW_COMPOSITION :
				// Besides post IDs, we also use hashids to identify compositions (mainly via REST API).
				if ( is_numeric( $args[0] ) ) {
					$post = get_post( $args[0] );
				} else {
					$post = get_post( $this->composition_manager->get_composition_post_id_by( [ 'hashid' => $args[0] ] ) );
				}

				if ( empty( $post ) ) {
					$caps[] = 'do_not_allow';
					break;
				}

				if ( 'revision' === $post->post_type ) {
					$post = get_post( $post->post_parent );
					if ( empty( $post ) ) {
						$caps[] = 'do_not_allow';
						break;
					}
				}

				// If the user can manage compositions, he or she is good to go.
				if ( user_can( $user_id, Caps::MANAGE_COMPOSITIONS ) ) {
					$caps = [ Caps::VIEW_COMPOSITIONS ]; // VIEW_COMPOSITION maps to VIEW_COMPOSITIONS.
					break;
				}

				// Get the composition owners.
				$composition_user_ids = $this->composition_manager->get_post_composition_user_ids( $post->ID );
				if ( ! in_array( $user_id, $composition_user_ids ) ) {
					$caps = [ 'do_not_allow' ];
					break;
				}

				$caps = [ Caps::VIEW_COMPOSITIONS ]; // VIEW_COMPOSITION maps to VIEW_COMPOSITIONS.
				break;
			case Caps::EDIT_COMPOSITION :
				// Besides post IDs, we also use hashids to identify compositions (mainly via REST API).
				if ( is_numeric( $args[0] ) ) {
					$post = get_post( $args[0] );
				} else {
					$post = get_post( $this->composition_manager->get_composition_post_id_by( [ 'hashid' => $args[0] ] ) );
				}

				if ( empty( $post ) ) {
					$caps[] = 'do_not_allow';
					break;
				}

				if ( 'revision' === $post->post_type ) {
					$post = get_post( $post->post_parent );
					if ( empty( $post ) ) {
						$caps[] = 'do_not_allow';
						break;
					}
				}

				// If the user can manage compositions, he or she is good to go.
				if ( user_can( $user_id, Caps::MANAGE_COMPOSITIONS ) ) {
					$caps = [ Caps::EDIT_COMPOSITIONS ]; // EDIT_COMPOSITION maps to EDIT_COMPOSITIONS.
					break;
				}

				// Get the composition owners.
				$composition_user_ids = $this->composition_manager->get_post_composition_user_ids( $post->ID );
				if ( ! in_array( $user_id, $composition_user_ids ) ) {
					$caps = [ 'do_not_allow' ];
					break;
				}

				$caps = [ Caps::EDIT_COMPOSITIONS ];
				break;
		}

		return $caps;
	}
}
