<?php
/**
 * Composer package transformer.
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

namespace Pressody\Retailer\Transformer;

use Pressody\Retailer\Package;
use Pressody\Retailer\SolutionFactory;
use function Pressody\Retailer\get_composer_vendor;

/**
 * Composer package transformer class.
 *
 * @since 0.1.0
 */
class ComposerSolutionTransformer implements ComposerPackageTransformer {

	/**
	 * Package factory.
	 *
	 * @var SolutionFactory
	 */
	protected SolutionFactory $factory;

	/**
	 * Create a Composer solution transformer.
	 *
	 * @since 0.1.0
	 *
	 * @param SolutionFactory $factory Solution factory.
	 */
	public function __construct( SolutionFactory $factory ) {
		$this->factory = $factory;
	}

	/**
	 * Transform a package into a Composer package.
	 *
	 * @since 0.1.0
	 *
	 * @param Package $package Solution.
	 *
	 * @return Package
	 */
	public function transform( Package $package ): Package {
		$builder = $this->factory->create( 'composer' )->with_package( $package );

		// For solution packages used in a Composer packages.json, we use the `metapackage` type.
		// @link https://getcomposer.org/doc/04-schema.md#type
		$builder->set_type( 'metapackage' );

		$vendor = get_composer_vendor();
		$name   = $this->normalize_package_name( $package->get_slug() );
		$builder->set_name( $vendor . '/' . $name );

		return $builder->build();
	}

	/**
	 * Transform a solution's required packages (other solutions, parts) into a Composer require list.
	 *
	 * @since 0.1.0
	 *
	 * @param array $required_pdpackages
	 * @return array
	 */
	public function transform_required_packages( array $required_pdpackages ): array {
		$composer_require = [];

		// Convert the managed required packages to the simple Composer format.
		foreach ( $required_pdpackages as $required_pdpackage ) {
			$composer_require[ $required_pdpackage['composer_package_name'] ] = $required_pdpackage['version_range'];

			if ( 'stable' !== $required_pdpackage['stability'] ) {
				$composer_require[ $required_pdpackage['composer_package_name'] ] .= '@' . $required_pdpackage['stability'];
			}
		}

		return $composer_require;
	}

	/**
	 * Normalize a package name for packages.json.
	 *
	 * @since 0.1.0
	 *
	 * @link https://github.com/composer/composer/blob/79af9d45afb6bcaac8b73ae6a8ae24414ddf8b4b/src/Composer/Package/Loader/ValidatingArrayLoader.php#L339-L369
	 *
	 * @param string $name Package name.
	 *
	 * @return string
	 */
	protected function normalize_package_name( string $name ): string {
		$name = strtolower( $name );
		return preg_replace( '/[^a-z0-9_\-\.]+/i', '', $name );
	}
}
