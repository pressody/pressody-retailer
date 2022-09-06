<?php
/**
 * Flattened solutions repository.
 *
 * @since   0.8.0
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

namespace Pressody\Retailer\Repository;

use Pressody\Retailer\Package;
use Pressody\Retailer\SolutionFactory;
use Pressody\Retailer\SolutionManager;
use Pressody\Retailer\SolutionType\SolutionTypes;

/**
 * Flattened solutions repository class.
 *
 * @since 0.8.0
 */
class FlattenedSolutions extends AbstractRepository implements PackageRepository {

	/**
	 * Solutions repository to flatten.
	 *
	 * @var PackageRepository
	 */
	protected PackageRepository $repository_to_flatten;

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
	 * Create the repository.
	 *
	 * @since 0.8.0
	 *
	 * @param PackageRepository $repository_to_flatten Solutions repository to flatten.
	 * @param SolutionFactory   $factory               Solution factory.
	 * @param SolutionManager   $solution_manager
	 */
	public function __construct(
		PackageRepository $repository_to_flatten,
		SolutionFactory $factory,
		SolutionManager $solution_manager
	) {

		$this->repository_to_flatten = $repository_to_flatten;
		$this->factory               = $factory;
		$this->solution_manager      = $solution_manager;
	}

	/**
	 * Retrieve all solutions in the repository, flattened by including all their required solutions in the results.
	 *
	 * @since 0.8.0
	 *
	 * @return Package[]
	 */
	public function all(): array {
		$list = [];
		$this->flatten_solutions( $this->repository_to_flatten->all(), $list );

		ksort( $list );

		return $list;
	}

	/**
	 * Given a list of packages, accumulate them in a flat list.
	 *
	 * @since 0.8.0
	 *
	 * @param Package[] $packages
	 */
	protected function flatten_solutions( array $packages, array &$list ): array {
		if ( empty( $packages ) || ! is_array( $packages ) ) {
			return $list;
		}

		foreach ( $packages as $package ) {
			// If the package is not an object but some package data, build it if it's not already in the list.
			if ( is_array( $package ) && ! empty( $package['composer_package_name'] ) ) {
				if ( array_key_exists( $package['composer_package_name'], $list ) ) {
					continue;
				}

				if ( ! empty( $package['managed_post_id'] ) ) {
					$post = get_post( $package['managed_post_id'] );
					if ( empty( $post ) || $this->solution_manager::POST_TYPE !== $post->post_type ) {
						continue;
					}

					$package = $this->build( $post->ID );
				}
			}

			if ( ! $package instanceof Package || array_key_exists( $package->get_composer_package_name(), $list ) ) {
				continue;
			}

			$list[ $package->get_composer_package_name() ] = $package;

			if ( $package->has_required_solutions() ) {
				$this->flatten_solutions( $package->get_required_solutions(), $list );
			}
		}

		return $list;
	}

	/**
	 * Build a solution.
	 *
	 * @since 0.8.0
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
}
