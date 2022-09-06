<?php
/**
 * Solution repository with a filter callback.
 *
 * @package Pressody
 * @license GPL-2.0-or-later
 * @since 0.1.0
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

use Pressody\Retailer\Package;

/**
 * Filtered package repository class.
 *
 * @since 0.1.0
 */
class FilteredRepository extends AbstractRepository implements PackageRepository {
	/**
	 * Filter callback.
	 *
	 * @var callable
	 */
	protected $callback;

	/**
	 * Solution repository.
	 *
	 * @var PackageRepository
	 */
	protected PackageRepository $repository;

	/**
	 * Create the repository.
	 *
	 * @since 0.1.0
	 *
	 * @param PackageRepository $repository Solution repository.
	 * @param callable          $callback   Filter callback.
	 */
	public function __construct( PackageRepository $repository, callable $callback ) {
		$this->repository = $repository;
		$this->callback   = $callback;
	}

	/**
	 * Retrieve all packages in the repository, filtered by the provided callback.
	 *
	 * @since 0.1.0
	 *
	 * @return Package[]
	 */
	public function all(): array {
		$items = [];

		foreach ( $this->repository->all() as $package ) {
			if ( ( $this->callback )( $package ) ) {
				$items[ $package->get_composer_package_name() ] = $package;
			}
		}

		ksort( $items );

		return $items;
	}
}
