<?php
declare ( strict_types=1 );

namespace PixelgradeLT\Retailer\Tests\Integration\Repository;

use PixelgradeLT\Retailer\Repository\ProcessedSolutions;
use PixelgradeLT\Retailer\SolutionType\BaseSolution;
use PixelgradeLT\Retailer\SolutionType\SolutionTypes;
use PixelgradeLT\Retailer\Tests\Framework\PHPUnitUtil;
use PixelgradeLT\Retailer\Tests\Integration\TestCase;

use Psr\Container\ContainerInterface;
use function PixelgradeLT\Retailer\plugin;

class ProcessedSolutionsTest extends TestCase {
	protected static $posts_data;
	protected static $dep_posts_data;
	protected static $old_container;

	/**
	 * @param \WP_UnitTest_Factory $factory
	 */
	public static function wpSetUpBeforeClass( $factory ) {
		// We need to set a user with sufficient privileges to create packages and edit them.
		wp_set_current_user( 1 );

		/** @var ContainerInterface $old_container */
		self::$old_container = plugin()->get_container();

		// Register ltsolution post type
		$register_post_type = PHPUnitUtil::getProtectedMethod( self::$old_container['hooks.solution_post_type'], 'register_post_type' );
		$register_post_type->invoke( self::$old_container['hooks.solution_post_type'] );

		// Register and populate the taxonomies.
		$register_solution_type_taxonomy = PHPUnitUtil::getProtectedMethod( self::$old_container['hooks.solution_post_type'], 'register_solution_type_taxonomy' );
		$register_solution_type_taxonomy->invoke( self::$old_container['hooks.solution_post_type'] );
		$insert_solution_type_taxonomy_terms = PHPUnitUtil::getProtectedMethod( self::$old_container['hooks.solution_post_type'], 'insert_solution_type_taxonomy_terms' );
		$insert_solution_type_taxonomy_terms->invoke( self::$old_container['hooks.solution_post_type'] );

		$register_solution_category_taxonomy = PHPUnitUtil::getProtectedMethod( self::$old_container['hooks.solution_post_type'], 'register_solution_category_taxonomy' );
		$register_solution_category_taxonomy->invoke( self::$old_container['hooks.solution_post_type'] );

		$register_solution_keyword_taxonomy = PHPUnitUtil::getProtectedMethod( self::$old_container['hooks.solution_post_type'], 'register_solution_keyword_taxonomy' );
		$register_solution_keyword_taxonomy->invoke( self::$old_container['hooks.solution_post_type'] );

		// Set this package as a regular solution type.
		$package_type = get_term_by( 'slug', SolutionTypes::REGULAR, self::$old_container['solution.manager']::TYPE_TAXONOMY );

		// These are solutions that others depend upon.
		self::$dep_posts_data = [
			'blog' => [
				'post_title'  => 'Blog',
				'post_status' => 'publish',
				'post_name'   => 'blog',
				'post_type'   => self::$old_container['solution.manager']::POST_TYPE,
				'tax_input'   => [
					self::$old_container['solution.manager']::TYPE_TAXONOMY    => [ $package_type->term_id ],
					self::$old_container['solution.manager']::KEYWORD_TAXONOMY => 'keyword1, keyword2, keyword3',
				],
				'meta_input'  => [
					'_solution_details_description'     => 'Package custom description (blog).',
					'_solution_details_longdescription' => '<h2>Awesome blog solution</h2>
This is the <strong>long, rich-text description</strong> for this <em>solution.</em>

And here is a quote from a customer:
<blockquote>Pure bliss, man!</blockquote>',
					'_solution_details_homepage'        => 'https://package.homepage',
				],
			],
			'edd'  => [
				'post_title'  => 'EDD',
				'post_status' => 'publish',
				'post_name'   => 'edd',
				'post_type'   => self::$old_container['solution.manager']::POST_TYPE,
				'tax_input'   => [
					self::$old_container['solution.manager']::TYPE_TAXONOMY    => [ $package_type->term_id ],
					self::$old_container['solution.manager']::KEYWORD_TAXONOMY => 'keyword1, keyword2, keyword3',
				],
				'meta_input'  => [
					'_solution_details_description'     => 'Package custom description (edd).',
					'_solution_details_longdescription' => '<h2>Awesome EDD solution</h2>
This is the <strong>long, rich-text description</strong> for this <em>solution.</em>

And here is a quote from a customer:
<blockquote>Pure bliss, man!</blockquote>',
					'_solution_details_homepage'        => 'https://package.homepage',
				],
			],
		];

		// Create the test ltsolutions posts that will be dependencies to other posts that we test.
		$dep_post_ids = [];
		foreach ( self::$dep_posts_data as $key => $data ) {
			$dep_post_ids[ $key ] = $factory->post->create_object( $data );
		}

		$post_ids = [];

		// Requires the edd solution and excludes the blog one.
		self::$posts_data              = [];
		self::$posts_data['ecommerce'] = [
			'post_title'  => 'Ecommerce',
			'post_status' => 'publish',
			'post_name'   => 'ecommerce',
			'post_type'   => self::$old_container['solution.manager']::POST_TYPE,
			'tax_input'   => [
				self::$old_container['solution.manager']::TYPE_TAXONOMY    => [ $package_type->term_id ],
				self::$old_container['solution.manager']::KEYWORD_TAXONOMY => 'keyword1, keyword2, keyword3',
			],
			'meta_input'  => [
				'_solution_details_description'                    => 'Package custom description (ecommerce).',
				'_solution_details_longdescription'                => '<h2>Awesome eCommerce solution</h2>
This is the <strong>long, rich-text description</strong> for this <em>solution.</em>

And here is a quote from a customer:
<blockquote>Pure bliss, man!</blockquote>',
				'_solution_details_homepage'                       => 'https://package.homepage',
				'_solution_required_parts|||0|value'               => '_',
				'_solution_required_parts|package_name|0|0|value'  => 'pixelgradelt-records/part_yet-another',
				'_solution_required_parts|version_range|0|0|value' => '1.2.9',
				'_solution_required_parts|stability|0|0|value'     => 'stable',
				'_solution_required_solutions|||0|value'           => '_',
				'_solution_required_solutions|pseudo_id|0|0|value' => 'edd #' . $dep_post_ids['edd'],
				'_solution_excluded_solutions|||0|value'           => '_',
				'_solution_excluded_solutions|pseudo_id|0|0|value' => 'blog #' . $dep_post_ids['blog'],
			],
		];

		$post_ids['ecommerce'] = $factory->post->create_object( self::$posts_data['ecommerce'] );

		// Requires the blog solution and excludes the ecommerce one.
		self::$posts_data['presentation'] = [
			'post_title'  => 'Presentation',
			'post_status' => 'publish',
			'post_name'   => 'presentation',
			'post_type'   => self::$old_container['solution.manager']::POST_TYPE,
			'tax_input'   => [
				self::$old_container['solution.manager']::TYPE_TAXONOMY    => [ $package_type->term_id ],
				self::$old_container['solution.manager']::KEYWORD_TAXONOMY => 'keyword9, keyword10, keyword11',
			],
			'meta_input'  => [
				'_solution_details_description'                    => 'Package custom description (presentation).',
				'_solution_details_longdescription'                => '<h2>Awesome presentation solution</h2>
This is the <strong>long, rich-text description</strong> for this <em>solution.</em>

And here is a quote from a customer:
<blockquote>Pure bliss, man!</blockquote>',
				'_solution_details_homepage'                       => 'https://package.homepage',
				'_solution_required_parts|||0|value'               => '_',
				'_solution_required_parts|package_name|0|0|value'  => 'pixelgradelt-records/part_yet-another',
				'_solution_required_parts|version_range|0|0|value' => '1.2.9',
				'_solution_required_parts|stability|0|0|value'     => 'stable',
				'_solution_required_solutions|||0|value'           => '_',
				'_solution_required_solutions|pseudo_id|0|0|value' => 'blog #' . $dep_post_ids['blog'],
				'_solution_excluded_solutions|||0|value'           => '_',
				'_solution_excluded_solutions|pseudo_id|0|0|value' => 'ecommerce #' . $post_ids['ecommerce'],
			],
		];

		$post_ids['presentation'] = $factory->post->create_object( self::$posts_data['presentation'] );
	}

	public static function wpTearDownAfterClass() {
	}

	public function test_exclusion() {
		/** @var \PixelgradeLT\Retailer\Repository\Solutions $repository */
		$repository = plugin()->get_container()['repository.solutions'];
		$repository->reinitialize();

		$post_slugs = [ 'blog', 'ecommerce', ];

		$filtered_repo = $repository->with_filter(
			function ( $package ) use ( $post_slugs ) {
				if ( ! in_array( $package->get_slug(), $post_slugs ) ) {
					return false;
				}

				return true;
			}
		);

		// Make a processed repository out of the filtered solutions repository.
		$processed_repository = new ProcessedSolutions( $filtered_repo, [], $repository->get_factory(), $repository->get_solution_manager() );
		$processed_solutions  = $processed_repository->all();

		// We expect to have the ecommerce and edd solutions, but not the blog one since it is excluded by ecommerce.
		$this->assertNotEmpty( $processed_solutions['pixelgradelt-retailer/edd'] );
		$this->assertInstanceOf( BaseSolution::class, $processed_solutions['pixelgradelt-retailer/edd'] );
		$this->assertNotEmpty( $processed_solutions['pixelgradelt-retailer/ecommerce'] );
		$this->assertInstanceOf( BaseSolution::class, $processed_solutions['pixelgradelt-retailer/ecommerce'] );
		$this->assertNotContains( 'pixelgradelt-retailer/blog', array_keys( $processed_solutions ) );
	}

	public function test_exclusion_with_context_order() {
		/** @var \PixelgradeLT\Retailer\Repository\Solutions $repository */
		$repository = plugin()->get_container()['repository.solutions'];
		$repository->reinitialize();

		$post_slugs = [ 'edd', 'blog', 'ecommerce', ];

		$filtered_repo = $repository->with_filter(
			function ( $package ) use ( $post_slugs ) {
				if ( ! in_array( $package->get_slug(), $post_slugs ) ) {
					return false;
				}

				return true;
			}
		);

		$solutions_context = [
			'pixelgradelt-retailer/ecommerce' => [
				'timestamp' => 100,
			],
			'pixelgradelt-retailer/edd'       => [
				'timestamp' => 200,
			],
			'pixelgradelt-retailer/blog'      => [
				'timestamp' => 300,
			],
		];

		// Make a processed repository out of the filtered solutions repository.
		$processed_repository = new ProcessedSolutions( $filtered_repo, $solutions_context, $repository->get_factory(), $repository->get_solution_manager() );
		$processed_solutions  = $processed_repository->all();

		// We expect to have the ecommerce and edd solutions, but not the blog one since it is excluded by ecommerce.
		$this->assertNotEmpty( $processed_solutions['pixelgradelt-retailer/edd'] );
		$this->assertInstanceOf( BaseSolution::class, $processed_solutions['pixelgradelt-retailer/edd'] );
		$this->assertNotEmpty( $processed_solutions['pixelgradelt-retailer/ecommerce'] );
		$this->assertInstanceOf( BaseSolution::class, $processed_solutions['pixelgradelt-retailer/ecommerce'] );
		$this->assertNotContains( 'pixelgradelt-retailer/blog', array_keys( $processed_solutions ) );

		// We also expect a certain order, alphabetically by the package name.
		$this->assertSame( [
			'pixelgradelt-retailer/ecommerce',
			'pixelgradelt-retailer/edd',
		], array_keys( $processed_solutions ) );
	}

	public function test_multiple_exclusion_with_context_order() {
		/** @var \PixelgradeLT\Retailer\Repository\Solutions $repository */
		$repository = plugin()->get_container()['repository.solutions'];
		$repository->reinitialize();

		$post_slugs = [ 'blog', 'presentation', 'ecommerce', ];

		$filtered_repo = $repository->with_filter(
			function ( $package ) use ( $post_slugs ) {
				if ( ! in_array( $package->get_slug(), $post_slugs ) ) {
					return false;
				}

				return true;
			}
		);

		// The presentation solution should be the first to exclude, thus excluding the ecommerce solution.
		$solutions_context = [
			'pixelgradelt-retailer/presentation' => [
				'timestamp' => 200,
			],
			'pixelgradelt-retailer/ecommerce'    => [
				'timestamp' => 100,
			],
			'pixelgradelt-retailer/blog'         => [
				'timestamp' => 300,
			],
		];

		// Make a processed repository out of the filtered solutions repository.
		$processed_repository = new ProcessedSolutions( $filtered_repo, $solutions_context, $repository->get_factory(), $repository->get_solution_manager() );
		$processed_solutions  = $processed_repository->all();

		$this->assertNotEmpty( $processed_solutions['pixelgradelt-retailer/blog'] );
		$this->assertInstanceOf( BaseSolution::class, $processed_solutions['pixelgradelt-retailer/blog'] );
		$this->assertNotEmpty( $processed_solutions['pixelgradelt-retailer/presentation'] );
		$this->assertInstanceOf( BaseSolution::class, $processed_solutions['pixelgradelt-retailer/presentation'] );
		$this->assertNotContains( 'pixelgradelt-retailer/ecommerce', array_keys( $processed_solutions ) );
		$this->assertNotContains( 'pixelgradelt-retailer/edd', array_keys( $processed_solutions ) );

		// We also expect a certain order, alphabetically by the package name.
		$this->assertSame( [
			'pixelgradelt-retailer/blog',
			'pixelgradelt-retailer/presentation',
		], array_keys( $processed_solutions ) );
	}
}
