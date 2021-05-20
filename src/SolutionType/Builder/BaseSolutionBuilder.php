<?php
/**
 * Base solution builder.
 *
 * @since   0.1.0
 * @license GPL-2.0-or-later
 * @package PixelgradeLT
 */

declare ( strict_types=1 );

namespace PixelgradeLT\Retailer\SolutionType\Builder;

use PixelgradeLT\Retailer\Package;
use PixelgradeLT\Retailer\SolutionManager;
use PixelgradeLT\Retailer\Utils\ArrayHelpers;
use Psr\Log\LoggerInterface;
use ReflectionClass;

/**
 * Base package builder class.
 *
 * The base package is the simplest package we handle.
 *
 * @since 0.1.0
 */
class BaseSolutionBuilder {
	/**
	 * Reflection class instance.
	 *
	 * @var ReflectionClass
	 */
	protected ReflectionClass $class;

	/**
	 * Solution instance.
	 *
	 * @var Package
	 */
	protected Package $solution;

	/**
	 * Solution manager.
	 *
	 * @var SolutionManager
	 */
	protected SolutionManager $solution_manager;

	/**
	 * Logger.
	 *
	 * @var LoggerInterface
	 */
	protected LoggerInterface $logger;

	/**
	 * Create a builder for solutions.
	 *
	 * @since 0.1.0
	 *
	 * @param Package         $solution         Solution instance to build.
	 * @param SolutionManager $solution_manager Solutions manager.
	 * @param LoggerInterface $logger           Logger.
	 */
	public function __construct(
		Package $solution,
		SolutionManager $solution_manager,
		LoggerInterface $logger
	) {
		$this->solution = $solution;
		try {
			$this->class = new ReflectionClass( $solution );
			// phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
		} catch ( \ReflectionException $e ) {
			// noop.
		}

		$this->solution_manager = $solution_manager;
		$this->logger           = $logger;
	}

	/**
	 * Finalize the solution build.
	 *
	 * @since 0.1.0
	 *
	 * @return Package
	 */
	public function build(): Package {

		return $this->solution;
	}

	/**
	 * Set the name.
	 *
	 * @since 0.1.0
	 *
	 * @param string $name Package name.
	 *
	 * @return $this
	 */
	public function set_name( string $name ): self {
		return $this->set( 'name', $name );
	}

	/**
	 * Set the type.
	 *
	 * @since 0.1.0
	 *
	 * @param string $type Package type.
	 *
	 * @return $this
	 */
	public function set_type( string $type ): self {
		return $this->set( 'type', $type );
	}

	/**
	 * Set the source type.
	 *
	 * @since 0.1.0
	 *
	 * @param string $source_type Package source type.
	 *
	 * @return $this
	 */
	public function set_source_type( string $source_type ): self {
		return $this->set( 'source_type', $source_type );
	}

	/**
	 * Set the source name (in the form vendor/name).
	 *
	 * @since 0.1.0
	 *
	 * @param string $source_name Package source name.
	 *
	 * @return $this
	 */
	public function set_source_name( string $source_name ): self {
		return $this->set( 'source_name', $source_name );
	}

	/**
	 * Set the slug.
	 *
	 * @since 0.1.0
	 *
	 * @param string $slug Slug.
	 *
	 * @return $this
	 */
	public function set_slug( string $slug ): self {
		return $this->set( 'slug', $slug );
	}

	/**
	 * Set the description.
	 *
	 * @since 0.1.0
	 *
	 * @param string $description Description.
	 *
	 * @return $this
	 */
	public function set_description( string $description ): self {
		return $this->set( 'description', $description );
	}

	/**
	 * Set the keywords.
	 *
	 * @since 0.1.0
	 *
	 * @param string|string[] $keywords
	 *
	 * @return $this
	 */
	public function set_keywords( $keywords ): self {
		return $this->set( 'keywords', $this->normalize_keywords( $keywords ) );
	}

	/**
	 * Normalize a given set of keywords.
	 *
	 * @since 0.1.0
	 *
	 * @param string|string[] $keywords
	 *
	 * @return array
	 */
	protected function normalize_keywords( $keywords ): array {
		$delimiter = ',';
		// If by any chance we are given an array, sanitize and return it.
		if ( is_array( $keywords ) ) {
			foreach ( $keywords as $key => $keyword ) {
				// Reject non-string or empty entries.
				if ( ! is_string( $keyword ) ) {
					unset( $keywords[ $key ] );
					continue;
				}

				$keywords[ $key ] = trim( \sanitize_text_field( $keyword ) );
			}

			// We don't keep the array keys.
			$keywords = array_values( $keywords );

			// We don't keep the falsy keywords.
			$keywords = array_filter( $keywords );

			// We don't keep duplicates.
			$keywords = array_unique( $keywords );

			// Sort the keywords alphabetically.
			sort( $keywords );

			return $keywords;
		}

		// Anything else we coerce to a string.
		if ( ! is_string( $keywords ) ) {
			$keywords = (string) $keywords;
		}

		// Make sure we trim it.
		$keywords = trim( $keywords );

		// Bail on empty string.
		if ( empty( $keywords ) ) {
			return [];
		}

		// Return the whole string as an element if the delimiter is missing.
		if ( false === strpos( $keywords, $delimiter ) ) {
			return [ trim( \sanitize_text_field( $keywords ) ) ];
		}

		$keywords = explode( $delimiter, $keywords );
		foreach ( $keywords as $key => $keyword ) {
			$keywords[ $key ] = trim( \sanitize_text_field( $keyword ) );

			if ( empty( $keywords[ $key ] ) ) {
				unset( $keywords[ $key ] );
			}
		}

		// We don't keep the array keys.
		$keywords = array_values( $keywords );

		// We don't keep the falsy keywords.
		$keywords = array_filter( $keywords );

		// We don't keep duplicates.
		$keywords = array_unique( $keywords );

		// Sort the keywords alphabetically.
		sort( $keywords );

		return $keywords;
	}

	/**
	 * Set the homepage URL.
	 *
	 * @since 0.1.0
	 *
	 * @param string $url URL.
	 *
	 * @return $this
	 */
	public function set_homepage( string $url ): self {
		return $this->set( 'homepage', $url );
	}

	/**
	 * Set if this package is managed by us.
	 *
	 * @since 0.1.0
	 *
	 * @param bool $is_managed
	 *
	 * @return $this
	 */
	public function set_is_managed( bool $is_managed ): self {
		return $this->set( 'is_managed', $is_managed );
	}

	/**
	 * Set the managed post ID if this package is managed by us.
	 *
	 * @since 0.1.0
	 *
	 * @param int $managed_post_id
	 *
	 * @return $this
	 */
	public function set_managed_post_id( int $managed_post_id ): self {
		return $this->set( 'managed_post_id', $managed_post_id );
	}

	/**
	 * Set the package visibility.
	 *
	 * @since 0.1.0
	 *
	 * @param string $visibility
	 *
	 * @return $this
	 */
	public function set_visibility( string $visibility ): self {
		return $this->set( 'visibility', $visibility );
	}

	/**
	 * Set the (Composer) require list if this package is managed by us.
	 *
	 * This will be merged with the required packages and other hard-coded packages to generate the final require config.
	 *
	 * @since 0.1.0
	 *
	 * @param array $composer_require
	 *
	 * @return $this
	 */
	public function set_composer_require( array $composer_require ): self {
		return $this->set( 'composer_require', $composer_require );
	}

	/**
	 * Set the managed required packages if this package is managed by us.
	 *
	 * @since 0.1.0
	 *
	 * @param array $required_packages
	 *
	 * @return $this
	 */
	public function set_required_packages( array $required_packages ): self {
		return $this->set( 'required_packages', $this->normalize_required_packages( $required_packages ) );
	}

	/**
	 * Fill (missing) package details from the PackageManager if this is a managed package (via CPT).
	 *
	 * @since 0.1.0
	 *
	 * @param int   $post_id Optional. The package post ID to retrieve data for. Leave empty and provide $args to query.
	 * @param array $args    Optional. Args used to query for a managed package if the post ID failed to retrieve data.
	 *
	 * @return $this
	 */
	public function from_manager( int $post_id = 0, array $args = [] ): self {
		$this->set_managed_post_id( $post_id );

		$package_data = $this->solution_manager->get_solution_id_data( $post_id );
		// If we couldn't fetch package data by the post ID, try via the args.
		if ( empty( $package_data ) ) {
			$package_data = $this->solution_manager->get_solution_data_by( $args );
		}
		// No data, no play.
		if ( empty( $package_data ) ) {
			// Mark this package as not being managed by us, yet.
			$this->set_is_managed( false );

			return $this;
		}

		// Since we have data, it is a managed package.
		$this->set_is_managed( true );
		$this->set_visibility( $this->solution_manager->get_solution_visibility( $this->solution ) );

		$this->from_package_data( $package_data );

		return $this;
	}

	/**
	 * Set properties from a package data array.
	 *
	 * @since 0.1.0
	 *
	 * @param array $package_data Package data.
	 *
	 * @return $this
	 */
	public function from_package_data( array $package_data ): self {
		if ( empty( $this->solution->get_name() ) && ! empty( $package_data['name'] ) ) {
			$this->set_name( $package_data['name'] );
		}

		if ( empty( $this->solution->get_slug() ) && ! empty( $package_data['slug'] ) ) {
			$this->set_slug( $package_data['slug'] );
		}

		if ( empty( $this->solution->get_type() ) && ! empty( $package_data['type'] ) ) {
			$this->set_type( $package_data['type'] );
		}

		if ( empty( $this->solution->get_homepage() ) && ! empty( $package_data['homepage'] ) ) {
			$this->set_homepage( $package_data['homepage'] );
		}

		if ( empty( $this->solution->get_description() ) && ! empty( $package_data['description'] ) ) {
			$this->set_description( $package_data['description'] );
		}

		if ( empty( $this->solution->get_keywords() ) && ! empty( $package_data['keywords'] ) ) {
			$this->set_keywords( $package_data['keywords'] );
		}

		if ( isset( $package_data['is_managed'] ) ) {
			$this->set_is_managed( $package_data['is_managed'] );
		}

		if ( empty( $this->solution->get_visibility() ) && ! empty( $package_data['visibility'] ) ) {
			$this->set_visibility( $package_data['visibility'] );
		}

		if ( empty( $this->solution->get_composer_require() ) && ! empty( $package_data['composer_require'] ) ) {
			$this->set_composer_require( $package_data['composer_require'] );
		}

		if ( ! empty( $package_data['required_packages'] ) ) {
			// We need to normalize before the merge since we need the keys to be in the same format.
			// A bit inefficient, I know.
			$package_data['required_packages'] = $this->normalize_required_packages( $package_data['required_packages'] );
			// We will merge the required packages into the existing ones.
			$this->set_required_packages(
				ArrayHelpers::array_merge_recursive_distinct(
					$this->solution->get_required_packages(),
					$package_data['required_packages']
				)
			);
		}

		return $this;
	}

	/**
	 * Make sure that the managed required packages are in a format expected by BasePackage.
	 *
	 * @since 0.1.0
	 *
	 * @param array $required_packages
	 *
	 * @return array
	 */
	protected function normalize_required_packages( array $required_packages ): array {
		if ( empty( $required_packages ) ) {
			return [];
		}

		$normalized = [];
		// The pseudo_id is completely unique to a package since it encloses the source_name (source_type or vendor and package name/slug),
		// and the post ID. Totally unique.
		// We will rely on this uniqueness to make sure the only one required package remains of each entity.
		// Subsequent required package data referring to the same managed package post will overwrite previous ones.
		foreach ( $required_packages as $required_package ) {
			if ( empty( $required_package['pseudo_id'] )
			     || empty( $required_package['source_name'] )
			     || empty( $required_package['managed_post_id'] )
			) {
				$this->logger->error(
					'Invalid required package details for package "{package}".',
					[
						'package'          => $this->solution->get_name(),
						'required_package' => $required_package,
					]
				);

				continue;
			}

			$normalized[ $required_package['pseudo_id'] ] = [
				'composer_package_name' => ! empty( $required_package['composer_package_name'] ) ? $required_package['composer_package_name'] : false,
				'version_range'         => ! empty( $required_package['version_range'] ) ? $required_package['version_range'] : '*',
				'stability'             => ! empty( $required_package['stability'] ) ? $required_package['stability'] : 'stable',
				'source_name'           => $required_package['source_name'],
				'managed_post_id'       => $required_package['managed_post_id'],
				'pseudo_id'             => $required_package['pseudo_id'],
			];

			if ( ! empty( $required_package['composer_package_name'] ) ) {
				continue;
			}

			$package_data = $this->solution_manager->get_solution_id_data( $required_package['managed_post_id'] );
			if ( empty( $package_data ) ) {
				// Something is wrong. We will not include this required package.
				$this->logger->error(
					'Error getting managed required package data with post ID #{managed_post_id} for package "{package}".',
					[
						'managed_post_id' => $required_package['managed_post_id'],
						'package'         => $this->solution->get_name(),
					]
				);

				unset( $normalized[ $required_package['pseudo_id'] ] );
				continue;
			}

			/**
			 * Construct the Composer-like package name (the same way @see ComposerSolutionTransformer::transform() does it).
			 */
			$vendor = apply_filters( 'pixelgradelt_retailer_vendor', 'pixelgradelt_retailer', $required_package, $package_data );
			$name   = $this->normalize_package_name( $package_data['slug'] );

			$normalized[ $required_package['pseudo_id'] ]['composer_package_name'] = $vendor . '/' . $name;
		}

		return $normalized;
	}

	/**
	 * Normalize a package name for packages.json.
	 *
	 * @since 0.1.0
	 *
	 * @link  https://github.com/composer/composer/blob/79af9d45afb6bcaac8b73ae6a8ae24414ddf8b4b/src/Composer/Package/Loader/ValidatingArrayLoader.php#L339-L369
	 *
	 * @param string $name Package name.
	 *
	 * @return string
	 */
	protected function normalize_package_name( $name ): string {
		$name = strtolower( $name );

		return preg_replace( '/[^a-z0-9_\-\.]+/i', '', $name );
	}

	/**
	 * Set properties from an existing solution.
	 *
	 * @since 0.1.0
	 *
	 * @param Package $solution Solution.
	 *
	 * @return $this
	 */
	public function with_package( Package $solution ): self {
		$this
			->set_name( $solution->get_name() )
			->set_slug( $solution->get_slug() )
			->set_type( $solution->get_type() )
			->set_homepage( $solution->get_homepage() )
			->set_description( $solution->get_description() )
			->set_keywords( $solution->get_keywords() )
			->set_is_managed( $solution->is_managed() )
			->set_managed_post_id( $solution->get_managed_post_id() )
			->set_visibility( $solution->get_visibility() )
			->set_composer_require( $solution->get_composer_require() )
			->set_required_packages( $solution->get_required_packages() );

		return $this;
	}

	/**
	 * Normalize a given package version string.
	 *
	 * @since 0.1.0
	 *
	 * @param string $version
	 *
	 * @return string|null The normalized version string or null if invalid.
	 */
	protected function normalize_version( string $version ): ?string {
		try {
			$normalized_version = $this->solution_manager->normalize_version( $version );
		} catch ( \Exception $e ) {
			// If there was an exception it means that something is wrong with this version.
			$this->logger->error(
				'Error normalizing version: {version} for package "{package}".',
				[
					'exception' => $e,
					'version'   => $version,
					'package'   => $this->solution->get_name(),
				]
			);

			return null;
		}

		return $normalized_version;
	}

	/**
	 * Set a property on the package instance.
	 *
	 * Uses the reflection API to assign values to protected properties of the
	 * package instance to make the returned instance immutable.
	 *
	 * @since 0.1.0
	 *
	 * @param string $name  Property name.
	 * @param mixed  $value Property value.
	 *
	 * @return $this
	 */
	protected function set( string $name, $value ): self {
		try {
			$property = $this->class->getProperty( $name );
			$property->setAccessible( true );
			$property->setValue( $this->solution, $value );
		} catch ( \ReflectionException $e ) {
			// Nothing right now. We should really make sure that we are setting properties that exist.
		}

		return $this;
	}
}
