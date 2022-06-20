<?php
/**
 * Multi repository.
 *
 * @package Pressody
 * @license GPL-2.0-or-later
 * @since 0.1.0
 */

declare ( strict_types = 1 );

namespace Pressody\Retailer\Repository;

use Pressody\Retailer\Package;

/**
 * Multi repository class.
 *
 * @since 0.1.0
 */
class MultiRepository extends AbstractRepository implements PackageRepository {
	/**
	 * Array of package repositories.
	 *
	 * @var PackageRepository[]
	 */
	protected array $repositories = [];

	/**
	 * Create a multi repository.
	 *
	 * @since 0.1.0
	 *
	 * @param array $repositories Array of package repositories.
	 */
	public function __construct( array $repositories ) {
		$this->repositories = $repositories;
	}

	/**
	 * Retrieve all packages in the repository.
	 *
	 * @since 0.1.0
	 *
	 * @return Package[]
	 */
	public function all(): array {
		$items = [];

		foreach ( $this->repositories as $repository ) {
			$items = array_merge( $items, $repository->all() );
		}

		ksort( $items );

		return $items;
	}

	/**
	 * Reinitialize all packages in the repository.
	 *
	 * @since 0.1.0
	 */
	public function reinitialize() {
		foreach ( $this->repositories as $repository ) {
			if ( method_exists( $repository, 'reinitialize' ) ) {
				$repository->reinitialize();
			}
		}
	}
}
