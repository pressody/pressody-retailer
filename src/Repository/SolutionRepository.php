<?php
/**
 * Solution repository interface.
 *
 * @package Pressody
 * @license GPL-2.0-or-later
 * @since 0.8.0
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

declare ( strict_types = 1 );

namespace Pressody\Retailer\Repository;

use Pressody\Retailer\SolutionFactory;
use Pressody\Retailer\SolutionManager;

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
