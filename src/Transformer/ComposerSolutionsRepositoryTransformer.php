<?php
/**
 * Composer solutions repository transformer.
 *
 * @since   0.1.0
 * @license GPL-2.0-or-later
 * @package PixelgradeLT
 */

declare ( strict_types=1 );

namespace PixelgradeLT\Retailer\Transformer;

use PixelgradeLT\Retailer\SolutionManager;
use Psr\Log\LoggerInterface;
use PixelgradeLT\Retailer\Capabilities;
use PixelgradeLT\Retailer\Package;
use PixelgradeLT\Retailer\Repository\PackageRepository;
use PixelgradeLT\Retailer\VersionParser;

/**
 * Composer solutions repository transformer class.
 *
 * @since 0.1.0
 */
class ComposerSolutionsRepositoryTransformer implements PackageRepositoryTransformer {

	/**
	 * Composer package transformer.
	 *
	 * @var ComposerPackageTransformer.
	 */
	protected ComposerPackageTransformer $composer_transformer;

	/**
	 * Package manager.
	 *
	 * @var SolutionManager
	 */
	protected SolutionManager $solution_manager;

	/**
	 * Version parser.
	 *
	 * @var VersionParser
	 */
	protected VersionParser $version_parser;

	/**
	 * Logger.
	 *
	 * @var LoggerInterface
	 */
	protected LoggerInterface $logger;

	/**
	 * Constructor.
	 *
	 * @since 0.1.0
	 *
	 * @param ComposerPackageTransformer $composer_transformer Composer package transformer.
	 * @param SolutionManager            $package_manager      Packages manager.
	 * @param VersionParser              $version_parser       Version parser.
	 * @param LoggerInterface            $logger               Logger.
	 */
	public function __construct(
		ComposerPackageTransformer $composer_transformer,
		SolutionManager $package_manager,
		VersionParser $version_parser,
		LoggerInterface $logger
	) {

		$this->composer_transformer = $composer_transformer;
		$this->solution_manager     = $package_manager;
		$this->version_parser       = $version_parser;
		$this->logger               = $logger;
	}

	/**
	 * Transform a repository of packages into the format used in packages.json.
	 *
	 * @since 0.1.0
	 *
	 * @param PackageRepository $repository Package repository.
	 *
	 * @return array
	 */
	public function transform( PackageRepository $repository ): array {
		$items = [];

		foreach ( $repository->all() as $package ) {
			// We will not include packages without releases or packages that are not public (except for admin users).
			if ( ! ( current_user_can( Capabilities::MANAGE_OPTIONS ) || $this->solution_manager->is_solution_public( $package ) ) ) {
				continue;
			}

			$package = $this->composer_transformer->transform( $package );
			$item    = $this->transform_item( $package );

			// Skip if there aren't any viewable releases.
			if ( empty( $item ) ) {
				continue;
			}

			$items[ $package->get_name() ] = $item;
		}

		return [ 'packages' => $items ];
	}

	/**
	 * Transform an item.
	 *
	 * @param Package $package Package instance.
	 *
	 * @return array
	 */
	protected function transform_item( Package $package ): array {
		$data = [];

		// Since solutions are Composer metapackages, they don't have releases. So we will simulate one.
		// @link https://getcomposer.org/doc/04-schema.md#type

		$version = '1.0.0';
		$meta    = [];

		// This order is important since we go from lower to higher importance. Each one overwrites the previous.
		// Start with the hard-coded requires, if any.
		$require = [];
		// Merge the package require, if any.
		if ( ! empty( $meta['require'] ) ) {
			$require = array_merge( $require, $meta['require'] );
		}
		// Merge the required solutions, if any.
		if ( ! empty( $package->get_required_solutions() ) ) {
			$require = array_merge( $require, $this->composer_transformer->transform_required_packages( $package->get_required_solutions() ) );
		}
		// Exclude the excluded solutions, if any.
		// We will actually use the Composer replace functionality since it does what we want:
		// tells the solver that the current package will replace those packages, thus they should not be installed.
		// @link https://getcomposer.org/doc/04-schema.md#replace
		$replace = [];
		if ( ! empty( $package->get_excluded_solutions() ) ) {
			$replace = array_merge( $replace, $this->composer_transformer->transform_required_packages( $package->get_excluded_solutions() ) );
		}

		// Merge the required LT Records parts, if any.
		if ( ! empty( $package->get_required_ltrecords_parts() ) ) {
			$require = array_merge( $require, $this->composer_transformer->transform_required_packages( $package->get_required_ltrecords_parts() ) );
		}

		// Finally, allow others to have a say.
		$require = apply_filters( 'pixelgradelt_retailer_composer_solution_require', $require, $package );

		$data[ $version ] = [
			'name'               => $package->get_name(),
			'version'            => $version,
			'version_normalized' => $this->version_parser->normalize( $version ),
			// No `dist` required.
			'require'            => $require,
			'replace'            => $replace,
			'type'               => $package->get_type(),
			'description'        => $package->get_description(),
			'keywords'           => $package->get_keywords(),
			'homepage'           => $package->get_homepage(),
			'license'            => $package->get_license(),
		];

		return $data;
	}
}
