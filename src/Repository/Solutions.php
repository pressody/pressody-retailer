<?php
/**
 * Solutions repository.
 *
 * @since   0.1.0
 * @license GPL-2.0-or-later
 * @package PixelgradeLT
 */

declare ( strict_types=1 );

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
class Solutions extends AbstractRepository implements SolutionRepository {
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
			'solution_type' => 'all',
		];
		foreach ( $this->solution_manager->get_solution_ids_by( $args ) as $post_id ) {
			$post = get_post( $post_id );
			if ( empty( $post ) || $this->solution_manager::POST_TYPE !== $post->post_type ) {
				continue;
			}

			$package = $this->build( $post->ID );
			$items[ $package->get_composer_package_name() ] = $package;
		}

		ksort( $items );

		return $items;
	}

	/**
	 * Build a solution.
	 *
	 * @since 0.1.0
	 *
	 * @param int $post_id
	 *
	 * @return Package
	 */
	protected function build( int $post_id ): Package {
		return $this->factory->create( SolutionTypes::REGULAR )
		                     ->from_manager( $post_id )
		                     ->build();
	}

	/**
	 * Retrieve the repository solution factory.
	 *
	 * @since 0.8.0
	 *
	 * @return SolutionFactory
	 */
	public function get_factory(): SolutionFactory {
		return $this->factory;
	}

	/**
	 * Retrieve the repository solutions manager.
	 *
	 * @since 0.8.0
	 *
	 * @return SolutionManager
	 */
	public function get_solution_manager(): SolutionManager {
		return $this->solution_manager;
	}
}
