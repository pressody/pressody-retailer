<?php
/**
 * The Composition custom post type.
 *
 * @since   0.11.0
 * @license GPL-2.0-or-later
 * @package PixelgradeLT
 */

declare ( strict_types=1 );

namespace PixelgradeLT\Retailer\PostType;

use Cedaro\WP\Plugin\AbstractHookProvider;
use Pixelgrade\WPPostNotes\PostNotes;
use PixelgradeLT\Retailer\CompositionManager;

/**
 * The Composition custom post type provider: provides the interface for and stores the information about each composition.
 *
 * @since 0.11.0
 */
class CompositionPostType extends AbstractHookProvider {

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

		$this->post_notes = new PostNotes( $this->composition_manager::POST_TYPE );
	}

	public function register_hooks() {
		/*
		 * HANDLE THE CUSTOM POST TYPE LOGIC.
		 */
		$this->add_action( 'init', 'register_post_type' );

		/*
		 * HANDLE THE CUSTOM TAXONOMY LOGIC.
		 */
		$this->add_action( 'init', 'register_composition_keyword_taxonomy', 12 );
	}

	/**
	 * Register the custom package post type as defined by the package manager.
	 *
	 * @since 0.11.0
	 */
	protected function register_post_type() {
		register_post_type( $this->composition_manager::POST_TYPE, $this->composition_manager->get_composition_post_type_args() );
	}

	/**
	 * Register the taxonomy for the solution keywords as defined by the solution manager.
	 *
	 * @since 0.11.0
	 */
	protected function register_composition_keyword_taxonomy() {
		if ( taxonomy_exists( $this->composition_manager::KEYWORD_TAXONOMY ) ) {
			register_taxonomy_for_object_type(
					$this->composition_manager::KEYWORD_TAXONOMY,
					$this->composition_manager::POST_TYPE
			);
		} else {
			register_taxonomy(
					$this->composition_manager::KEYWORD_TAXONOMY,
					[ $this->composition_manager::POST_TYPE ],
					$this->composition_manager->get_solution_keyword_taxonomy_args()
			);
		}
	}
}
