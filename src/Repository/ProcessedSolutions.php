<?php
/**
 * Processed solutions repository.
 *
 * @since   0.8.0
 * @license GPL-2.0-or-later
 * @package PixelgradeLT
 */

declare ( strict_types=1 );

namespace PixelgradeLT\Retailer\Repository;

use PixelgradeLT\Retailer\Package;
use PixelgradeLT\Retailer\SolutionFactory;
use PixelgradeLT\Retailer\SolutionManager;

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
	 * @param PackageRepository $repository Solution repository.
	 */
	public function __construct(
		PackageRepository $repository,
		SolutionFactory $factory,
		SolutionManager $solution_manager
	) {

		$this->repository       = $repository;
		$this->factory          = $factory;
		$this->solution_manager = $solution_manager;
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
		$flattened_repository = new FlattenedSolutions( $this->repository, $this->factory, $this->solution_manager );

		return $this->process_solutions( $flattened_repository->all() );
	}

	/**
	 * Given a flat list of solutions, process them and return the resulting list.
	 *
	 * These is the logic that we currently apply:
	 *  - Apply the solutions exclusion logic for each solution in the list;
	 *
	 * @param Package[] $solutions A flat list of solutions with their Composer package name as keys.
	 *
	 * @return Package[] The processed flat list of solutions.
	 */
	protected function process_solutions( array $solutions ): array {
		if ( empty( $solutions ) || ! is_array( $solutions ) ) {
			return [];
		}

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

		return $solutions;
	}
}
