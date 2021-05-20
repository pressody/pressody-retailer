<?php
/**
 * Solutions repository.
 *
 * @package PixelgradeLT
 * @license GPL-2.0-or-later
 * @since 0.1.0
 */

declare ( strict_types = 1 );

namespace PixelgradeLT\Retailer\Repository;

use PixelgradeLT\Retailer\Package;
use PixelgradeLT\Retailer\SolutionFactory;
use PixelgradeLT\Retailer\SolutionManager;
use PixelgradeLT\Retailer\SolutionType\SolutionTypes;

/**
 * Solutions repository class.
 *
 * @since 0.1.0
 */
class Solutions extends AbstractRepository implements PackageRepository {
	/**
	 * Package factory.
	 *
	 * @var SolutionFactory
	 */
	protected SolutionFactory $factory;

	/**
	 * Solution manager.
	 *
	 * @var SolutionManager
	 */
	protected SolutionManager $solution_manager;

	/**
	 * Create a repository.
	 *
	 * @since 0.1.0
	 *
	 * @param SolutionFactory $factory Solution factory.
	 * @param SolutionManager $solution_manager
	 */
	public function __construct(
		SolutionFactory $factory,
		SolutionManager $solution_manager
	) {
		$this->factory          = $factory;
		$this->solution_manager = $solution_manager;
	}

	/**
	 * Retrieve all solutions.
	 *
	 * @since 0.1.0
	 *
	 * @return Package[]
	 */
	public function all(): array {
		$items = [];

		$args = [
			'package_type'        => [ SolutionTypes::BASIC ],
		];
		foreach ( $this->solution_manager->get_solution_ids_by( $args ) as $post_id ) {
			$post = get_post( $post_id );
			if ( empty( $post ) || $this->solution_manager::POST_TYPE !== $post->post_type ) {
				continue;
			}

			$package = $this->build( $post_id );
			$items[] = $package;
		}

		ksort( $items );

		return $items;
	}

	/**
	 * Build an external plugin.
	 *
	 * @since 0.1.0
	 *
	 * @param int    $post_id
	 *
	 * @return Package
	 */
	protected function build( int $post_id ): Package {
		return $this->factory->create( SolutionTypes::BASIC )
			// Then add managed data, if this plugin is managed.
			->from_manager( $post_id )
			->build();
	}
}
