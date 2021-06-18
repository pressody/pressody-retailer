<?php
/**
 * Composer package transformer.
 *
 * @package PixelgradeLT
 * @license GPL-2.0-or-later
 * @since 0.1.0
 */

declare ( strict_types = 1 );

namespace PixelgradeLT\Retailer\Transformer;

use PixelgradeLT\Retailer\Package;
use PixelgradeLT\Retailer\SolutionFactory;
use function PixelgradeLT\Retailer\get_composer_vendor;

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
	 * @param array $required_ltpackages
	 * @return array
	 */
	public function transform_required_packages( array $required_ltpackages ): array {
		$composer_require = [];

		// Convert the managed required packages to the simple Composer format.
		foreach ( $required_ltpackages as $required_ltpackage ) {
			$composer_require[ $required_ltpackage['composer_package_name'] ] = $required_ltpackage['version_range'];

			if ( 'stable' !== $required_ltpackage['stability'] ) {
				$composer_require[ $required_ltpackage['composer_package_name'] ] .= '@' . $required_ltpackage['stability'];
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
