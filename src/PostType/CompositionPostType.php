<?php
/**
 * The Composition custom post type.
 *
 * @since   0.11.0
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

namespace Pressody\Retailer\PostType;

use Cedaro\WP\Plugin\AbstractHookProvider;
use Pixelgrade\WPPostNotes\PostNotes;
use Pressody\Retailer\CompositionManager;

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
	 * Post notes functionality.
	 *
	 * @var PostNotes
	 */
	protected PostNotes $post_notes;

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
