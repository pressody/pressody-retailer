<?php
/**
 * Solution repository interface.
 *
 * @package PixelgradeLT
 * @license GPL-2.0-or-later
 * @since 0.1.0
 */

declare ( strict_types = 1 );

namespace PixelgradeLT\Retailer\Repository;

use PixelgradeLT\Retailer\Package;

/**
 * Solution repository interface.
 *
 * @since 0.1.0
 */
interface PackageRepository {
	/**
	 * Retrieve all.
	 *
	 * @since 0.1.0
	 *
	 * @return Package[]
	 */
	public function all(): array;

	/**
	 * Whether an item with the supplied criteria exists.
	 *
	 * @since 0.1.0
	 *
	 * @param array $args Map of key/value pairs.
	 * @return bool
	 */
	public function contains( array $args ): bool;

	/**
	 * Retrieve items that match a list of key/value pairs.
	 *
	 * @since 0.1.0
	 *
	 * @param array $args Map of key/value pairs.
	 * @return array
	 */
	public function where( array $args ): array;

	/**
	 * Retrieve the first item to match a list of key/value pairs.
	 *
	 * @since 0.1.0
	 *
	 * @param array $args Map of key/value pairs.
	 *
	 * @return Package|null
	 */
	public function first_where( array $args ): ?Package;

	/**
	 * Apply a callback to a repository to filter items.
	 *
	 * @since 0.1.0
	 *
	 * @param callable $callback Filter callback.
	 *
	 * @return PackageRepository
	 */
	public function with_filter( callable $callback ): PackageRepository;

	/**
	 * Reinitialize all items in the repository.
	 *
	 * @since 0.1.0
	 */
	public function reinitialize();
}
