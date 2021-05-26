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
	 * Set the authors.
	 *
	 * @since 0.1.0
	 *
	 * @param array $authors Authors.
	 *
	 * @return $this
	 */
	public function set_authors( array $authors ): self {
		return $this->set( 'authors', $this->normalize_authors( $authors ) );
	}

	protected function normalize_authors( array $authors ): array {
		$authors = array_map( function ( $author ) {
			if ( is_string( $author ) && ! empty( $author ) ) {
				return [ 'name' => trim( $author ) ];
			}

			if ( is_array( $author ) ) {
				// Make sure only the fields we are interested in are left.
				$accepted_keys = array_fill_keys( [ 'name', 'email', 'homepage', 'role' ], '' );
				$author        = array_replace( $accepted_keys, array_intersect_key( $author, $accepted_keys ) );

				// Remove falsy author entries.
				$author = array_filter( $author );

				// We need the name not to be empty.
				if ( empty( $author['name'] ) ) {
					return false;
				}

				return $author;
			}

			// We have an invalid author.
			return false;

		}, $authors );

		// Filter out falsy authors.
		$authors = array_filter( $authors );

		// We don't keep the array keys.
		$authors = array_values( $authors );

		return $authors;
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
	 * Set the package license.
	 *
	 * @since 0.1.0
	 *
	 * @param string $license
	 *
	 * @return $this
	 */
	public function set_license( string $license ): self {
		return $this->set( 'license', $this->normalize_license( $license ) );
	}

	/**
	 * We want to try and normalize the license to the SPDX format.
	 *
	 * @link https://spdx.org/licenses/
	 *
	 * @param string $license
	 *
	 * @return string
	 */
	protected function normalize_license( string $license ): string {
		$license = trim( $license );

		$tmp_license = strtolower( $license );

		if ( empty( $tmp_license ) ) {
			// Default to the WordPress license.
			return 'GPL-2.0-or-later';
		}

		// Handle the `GPL-2.0-or-later` license.
		if ( preg_match( '#(GNU\s*-?)?(General Public License|GPL)(\s*[-_v]*\s*)(2[.-]?0?\s*-?)(or\s*-?later|\+)#i', $tmp_license ) ) {
			return 'GPL-2.0-or-later';
		}

		// Handle the `GPL-2.0-only` license.
		if ( preg_match( '#(GNU\s*-?)?(General Public License|GPL)(\s*[-_v]*\s*)(2[.-]?0?\s*-?)(only)?#i', $tmp_license ) ) {
			return 'GPL-2.0-only';
		}

		// Handle the `GPL-3.0-or-later` license.
		if ( preg_match( '#(GNU\s*-?)?(General Public License|GPL)(\s*[-_v]*\s*)(3[.-]?0?\s*-?)(or\s*-?later|\+)#i', $tmp_license ) ) {
			return 'GPL-3.0-or-later';
		}

		// Handle the `GPL-3.0-only` license.
		if ( preg_match( '#(GNU\s*-?)?(General Public License|GPL)(\s*[-_v]*\s*)(3[.-]?0?\s*-?)(only)?#i', $tmp_license ) ) {
			return 'GPL-3.0-only';
		}

		// Handle the `MIT` license.
		if ( preg_match( '#(The\s*)?(MIT\s*)(License)?#i', $tmp_license ) ) {
			return 'MIT';
		}

		return $license;
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
	 * Set the required LT Records Parts.
	 *
	 * @since 0.1.0
	 *
	 * @param array $required_ltrecords_parts
	 *
	 * @return $this
	 */
	public function set_required_ltrecords_parts( array $required_ltrecords_parts ): self {
		return $this->set( 'required_ltrecords_parts', $this->normalize_required_ltrecords_parts( $required_ltrecords_parts ) );
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
	 * Set the required solutions.
	 *
	 * @since 0.1.0
	 *
	 * @param array $required_solutions
	 *
	 * @return $this
	 */
	public function set_required_solutions( array $required_solutions ): self {
		return $this->set( 'required_solutions', $this->normalize_required_solutions( $required_solutions ) );
	}

	/**
	 * Set the excluded solutions.
	 *
	 * @since 0.1.0
	 *
	 * @param array $excluded_solutions
	 *
	 * @return $this
	 */
	public function set_excluded_solutions( array $excluded_solutions ): self {
		return $this->set( 'excluded_solutions', $this->normalize_required_solutions( $excluded_solutions ) );
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

		// Since we have data, it is a managed package (all solutions are managed, yep)
		$this->set_is_managed( true );

		// Set other package details.
		$this->set_license( 'GPL-2.0-or-later' );
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

		if ( empty( $this->solution->get_authors() ) && ! empty( $package_data['authors'] ) ) {
			$this->set_authors( $package_data['authors'] );
		}

		if ( empty( $this->solution->get_homepage() ) && ! empty( $package_data['homepage'] ) ) {
			$this->set_homepage( $package_data['homepage'] );
		}

		if ( empty( $this->solution->get_description() ) && ! empty( $package_data['description'] ) ) {
			$this->set_description( $package_data['description'] );
		}

		if ( empty( $this->solution->get_license() ) && ! empty( $package_data['license'] ) ) {
			// Make sure that the license is a single string, not an array of strings.
			// Packagist.org offers a list of license in case a project is dual or triple licensed.
			if ( is_array( $package_data['license'] ) ) {
				$package_data['license'] = reset( $package_data['license'] );
			}
			$this->set_license( $package_data['license'] );
		}

		if ( empty( $this->solution->get_keywords() ) && ! empty( $package_data['keywords'] ) ) {
			$this->set_keywords( $package_data['keywords'] );
		}

		if ( isset( $package_data['is_managed'] ) ) {
			$this->set_is_managed( $package_data['is_managed'] );
		}

		if ( empty( $this->solution->get_managed_post_id() ) && ! empty( $package_data['managed_post_id'] ) ) {
			$this->set_managed_post_id( $package_data['managed_post_id'] );
		}

		if ( empty( $this->solution->get_visibility() ) && ! empty( $package_data['visibility'] ) ) {
			$this->set_visibility( $package_data['visibility'] );
		}

		if ( empty( $this->solution->get_composer_require() ) && ! empty( $package_data['composer_require'] ) ) {
			$this->set_composer_require( $package_data['composer_require'] );
		}

		if ( ! empty( $package_data['required_solutions'] ) ) {
			// We need to normalize before the merge since we need the keys to be in the same format.
			// A bit inefficient, I know.
			$package_data['required_solutions'] = $this->normalize_required_solutions( $package_data['required_solutions'] );
			// We will merge the required solutions into the existing ones.
			$this->set_required_solutions(
				ArrayHelpers::array_merge_recursive_distinct(
					$this->solution->get_required_solutions(),
					$package_data['required_solutions']
				)
			);
		}

		if ( ! empty( $package_data['excluded_solutions'] ) ) {
			// We need to normalize before the merge since we need the keys to be in the same format.
			// A bit inefficient, I know.
			$package_data['excluded_solutions'] = $this->normalize_required_solutions( $package_data['excluded_solutions'] );
			// We will merge the excluded solutions into the existing ones.
			$this->set_excluded_solutions(
				ArrayHelpers::array_merge_recursive_distinct(
					$this->solution->get_excluded_solutions(),
					$package_data['excluded_solutions']
				)
			);
		}

		if ( ! empty( $package_data['required_ltrecords_parts'] ) ) {
			// We need to normalize before the merge since we need the keys to be in the same format.
			// A bit inefficient, I know.
			$package_data['required_ltrecords_parts'] = $this->normalize_required_ltrecords_parts( $package_data['required_ltrecords_parts'] );
			// We will merge the required solutions into the existing ones.
			$this->set_required_ltrecords_parts(
				ArrayHelpers::array_merge_recursive_distinct(
					$this->solution->get_required_ltrecords_parts(),
					$package_data['required_ltrecords_parts']
				)
			);
		}

		return $this;
	}

	/**
	 * Make sure that the required solutions are in a format expected by BaseSolution.
	 *
	 * @since 0.1.0
	 *
	 * @param array $required_solutions
	 *
	 * @return array
	 */
	protected function normalize_required_solutions( array $required_solutions ): array {
		if ( empty( $required_solutions ) ) {
			return [];
		}

		$normalized = [];
		// The pseudo_id is completely unique to a solution since it encloses the title and the post ID. Totally unique.
		// We will rely on this uniqueness to make sure the only one required package remains of each entity.
		// Subsequent required solution data referring to the same solution post will overwrite previous ones.
		foreach ( $required_solutions as $required_solution ) {
			if ( empty( $required_solution['pseudo_id'] )
			     || empty( $required_solution['managed_post_id'] )
			) {
				$this->logger->error(
					'Invalid required package details for package "{package}".',
					[
						'package'          => $this->solution->get_name(),
						'required_package' => $required_solution,
					]
				);

				continue;
			}

			$normalized[ $required_solution['pseudo_id'] ] = [
				'composer_package_name' => ! empty( $required_solution['composer_package_name'] ) ? $required_solution['composer_package_name'] : false,
				'version_range'         => ! empty( $required_solution['version_range'] ) ? $required_solution['version_range'] : '*',
				'stability'             => ! empty( $required_solution['stability'] ) ? $required_solution['stability'] : 'stable',
				'managed_post_id'       => $required_solution['managed_post_id'],
				'pseudo_id'             => $required_solution['pseudo_id'],
			];

			if ( ! empty( $required_solution['composer_package_name'] ) ) {
				continue;
			}

			$package_data = $this->solution_manager->get_solution_id_data( $required_solution['managed_post_id'] );
			if ( empty( $package_data ) ) {
				// Something is wrong. We will not include this required package.
				$this->logger->error(
					'Error getting required package data with post ID #{managed_post_id} for package "{package}".',
					[
						'managed_post_id' => $required_solution['managed_post_id'],
						'package'         => $this->solution->get_name(),
					]
				);

				unset( $normalized[ $required_solution['pseudo_id'] ] );
				continue;
			}

			/**
			 * Construct the Composer-like package name (the same way @see ComposerSolutionTransformer::transform() does it).
			 */
			$vendor = apply_filters( 'pixelgradelt_retailer_vendor', 'pixelgradelt-retailer', $required_solution, $package_data );
			$name   = $this->normalize_package_name( $package_data['slug'] );

			$normalized[ $required_solution['pseudo_id'] ]['composer_package_name'] = $vendor . '/' . $name;
		}

		return $normalized;
	}

	/**
	 * Make sure that the required LT Records Parts are in a format expected by BaseSolution.
	 *
	 * @since 0.1.0
	 *
	 * @param array $required_parts
	 *
	 * @return array
	 */
	protected function normalize_required_ltrecords_parts( array $required_parts ): array {
		if ( empty( $required_parts ) ) {
			return [];
		}

		$normalized = [];
		// The pseudo_id is completely unique to a solution since it encloses the title and the post ID. Totally unique.
		// We will rely on this uniqueness to make sure the only one required package remains of each entity.
		// Subsequent required solution data referring to the same solution post will overwrite previous ones.
		foreach ( $required_parts as $required_part ) {
			if ( empty( $required_part['package_name'] ) ) {
				$this->logger->error(
					'Invalid required LT Records Part details for solution "{solution}" #{solution_post_id}.',
					[
						'solution'         => $this->solution->get_name(),
						'solution_post_id' => $this->solution->get_managed_post_id(),
						'required_part'    => $required_part,
					]
				);

				continue;
			}

			// Since we deal with the Composer package name from the start,
			// the `package_name` is the same as `composer_package_name`, but we need both to keep the logic humming.
			$normalized[ $required_part['package_name'] ] = [
				'package_name'          => $required_part['package_name'],
				'composer_package_name' => $required_part['package_name'],
				'version_range'         => ! empty( $required_part['version_range'] ) ? $required_part['version_range'] : '*',
				'stability'             => ! empty( $required_part['stability'] ) ? $required_part['stability'] : 'stable',
			];
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
			->set_authors( $solution->get_authors() )
			->set_homepage( $solution->get_homepage() )
			->set_description( $solution->get_description() )
			->set_keywords( $solution->get_keywords() )
			->set_license( $solution->get_license() )
			->set_is_managed( $solution->is_managed() )
			->set_managed_post_id( $solution->get_managed_post_id() )
			->set_visibility( $solution->get_visibility() )
			->set_composer_require( $solution->get_composer_require() )
			->set_required_solutions( $solution->get_required_solutions() )
			->set_excluded_solutions( $solution->get_excluded_solutions() )
			->set_required_ltrecords_parts( $solution->get_required_ltrecords_parts() );

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
