<?php
/**
 * Processed solutions repository.
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

use Composer\Repository\FilterRepository;
use Pressody\Retailer\Package;
use Pressody\Retailer\SolutionFactory;
use Pressody\Retailer\SolutionManager;

/**
 * Processed solutions repository class.
 *
 * @since 0.8.0
 */
class ProcessedSolutions extends AbstractRepository implements PackageRepository {

	/**
	 * Solution repository.
	 *
	 * @var SolutionRepository
	 */
	protected PackageRepository $repository;

	/**
	 * Solutions context details.
	 *
	 * @var array
	 */
	protected array $solutions_context;

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
	 * @param PackageRepository $repository        Solution repository.
	 * @param array             $solutions_context Solutions context details.
	 * @param SolutionFactory   $factory
	 * @param SolutionManager   $solution_manager
	 */
	public function __construct(
		PackageRepository $repository,
		array $solutions_context,
		SolutionFactory $factory,
		SolutionManager $solution_manager
	) {

		$this->repository        = $repository;
		$this->solutions_context = $solutions_context;
		$this->factory           = $factory;
		$this->solution_manager  = $solution_manager;
	}

	/**
	 * Retrieve all solutions in the repository,
	 * processed by the business logic (mainly flatten the require tree and apply the exclude solutions logic).
	 *
	 * @since 0.8.0
	 *
	 * @return Package[]
	 */
	public function all(): array {
		// We run a processing only on the received repository to make sure that excluded solutions don't leave their dependencies behind.
		$first_pass_processed_package_list = $this->process_solutions( $this->repository->all() );
		// Make a list of composer package names.
		$first_pass_processed_package_name_list = [];
		foreach ( $first_pass_processed_package_list as $package ) {
			$first_pass_processed_package_name_list[] = $package->get_composer_package_name();
		}

		// Filter the initial repo to only leave the solutions left after the first pass.
		$first_pass_processed_repo = $this->repository->with_filter(
			function ( $package ) use ( $first_pass_processed_package_name_list ) {
				if ( ! in_array( $package->get_composer_package_name(), $first_pass_processed_package_name_list ) ) {
					return false;
				}

				return true;
			}
		);

		// Flatten and run processing again.
		$flattened_repository = new FlattenedSolutions( $first_pass_processed_repo, $this->factory, $this->solution_manager );
		return $this->process_solutions( $flattened_repository->all() );
	}

	/**
	 * Given a flat list of solutions, process them and return the resulting list.
	 *
	 * This is the logic that we currently apply:
	 *  - Apply the solutions' exclusion logic for each solution in the list;
	 *
	 * @param Package[] $solutions A flat list of solutions with their Composer package name as keys.
	 *
	 * @return Package[] The processed flat list of solutions.
	 */
	protected function process_solutions( array $solutions ): array {
		if ( empty( $solutions ) || ! is_array( $solutions ) ) {
			return [];
		}

		// Before we begin, we want to reverse order the flat list of solutions by their timestamp of when they were added to a site's composition.
		// This way latter added solutions will get to exclude first.
		$solutions = $this->maybe_preorder( $solutions );

		while ( $solution = current( $solutions ) ) {

			if ( ! $solution instanceof Package ) {
				// Save the current key, jump to the next list item, and them unset.
				$key = key( $solutions );
				next( $solutions );
				unset( $solutions[ $key ] );

				continue;
			}

			$did_exclude = false;
			if ( $solution->has_excluded_solutions() ) {
				foreach ( $solution->get_excluded_solutions() as $excluded_solution ) {
					if ( is_array( $excluded_solution )
					     && ! empty( $excluded_solution['composer_package_name'] )
					     && isset( $solutions[ $excluded_solution['composer_package_name'] ] ) ) {

						unset( $solutions[ $excluded_solution['composer_package_name'] ] );

						$did_exclude = true;
					}
				}
			}

			if ( $did_exclude ) {
				// Since we don't know where in the list the excluded solutions were located, we start over.
				// We are not dealing with humongous lists, so it's a worthy compromise.
				reset( $solutions );
			} else {
				next( $solutions );
			}
		}

		ksort( $solutions );

		return $solutions;
	}

	/**
	 * Order a flattened list of solutions keyed by their Composer package name.
	 *
	 * @param array $solutions
	 *
	 * @return array
	 */
	protected function maybe_preorder( array $solutions ): array {
		ksort( $solutions );

		if ( empty( $this->solutions_context ) || ! is_array( $this->solutions_context ) ) {
			return $solutions;
		}

		// We will sort the solutions by their context timestamp, in reverse order (so the most recent timestamp first).
		uasort( $solutions,
			/**
			 * @param Package $a
			 * @param Package $b
			 */
			function ( $a, $b ) {
				if ( ! $a instanceof Package || ! $b instanceof Package ) {
					return 0;
				}

				if ( ! isset( $this->solutions_context[ $a->get_composer_package_name() ]['timestamp'] )
				     || ! isset( $this->solutions_context[ $b->get_composer_package_name() ]['timestamp'] ) ) {

					return 0;
				}

				if ( \absint( $this->solutions_context[ $a->get_composer_package_name() ]['timestamp'] ) > \absint( $this->solutions_context[ $b->get_composer_package_name() ]['timestamp'] ) ) {
					return -1;
				}

				if ( \absint( $this->solutions_context[ $a->get_composer_package_name() ]['timestamp'] ) < \absint( $this->solutions_context[ $b->get_composer_package_name() ]['timestamp'] ) ) {
					return 1;
				}

				return 0;
			}
		);

		return $solutions;
	}
}
