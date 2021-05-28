<?php
/**
 * Solution repository interface.
 *
 * @package PixelgradeLT
 * @license GPL-2.0-or-later
 * @since 0.8.0
 */

declare ( strict_types = 1 );

namespace PixelgradeLT\Retailer\Repository;

use PixelgradeLT\Retailer\SolutionFactory;
use PixelgradeLT\Retailer\SolutionManager;

/**
 * Solution repository interface.
 *
 * @since 0.8.0
 */
interface SolutionRepository extends PackageRepository {
	/**
	 * Retrieve the repository solution factory.
	 *
	 * @since 0.8.0
	 *
	 * @return SolutionFactory
	 */
	public function get_factory(): SolutionFactory;

	/**
	 * Retrieve the repository solutions manager.
	 *
	 * @since 0.8.0
	 *
	 * @return SolutionManager
	 */
	public function get_solution_manager(): SolutionManager;
}
