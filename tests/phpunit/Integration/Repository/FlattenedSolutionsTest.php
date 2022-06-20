<?php
declare ( strict_types=1 );

namespace Pressody\Retailer\Tests\Integration\Repository;

use Pressody\Retailer\Repository\FlattenedSolutions;
use Pressody\Retailer\Repository\ProcessedSolutions;
use Pressody\Retailer\SolutionType\BaseSolution;
use Pressody\Retailer\SolutionType\SolutionTypes;
use Pressody\Retailer\Tests\Framework\PHPUnitUtil;
use Pressody\Retailer\Tests\Integration\TestCase;

use Psr\Container\ContainerInterface;
use function Pressody\Retailer\plugin;

class FlattenedSolutionsTest extends TestCase {
	protected static $posts_data;
	protected static $dep_posts_data;
	protected static $container;

	/**
	 * @param \WP_UnitTest_Factory $factory
	 */
	public static function wpSetUpBeforeClass( $factory ) {
		// We need to set a user with sufficient privileges to create packages and edit them.
		wp_set_current_user( 1 );

		/** @var ContainerInterface $container */
		self::$container = plugin()->get_container();

		// Register pdsolution post type
		$register_post_type = PHPUnitUtil::getProtectedMethod( self::$container['hooks.solution_post_type'], 'register_post_type' );
		$register_post_type->invoke( self::$container['hooks.solution_post_type'] );

		// Register and populate the taxonomies.
		$register_solution_type_taxonomy = PHPUnitUtil::getProtectedMethod( self::$container['hooks.solution_post_type'], 'register_solution_type_taxonomy' );
		$register_solution_type_taxonomy->invoke( self::$container['hooks.solution_post_type'] );
		$insert_solution_type_taxonomy_terms = PHPUnitUtil::getProtectedMethod( self::$container['hooks.solution_post_type'], 'insert_solution_type_taxonomy_terms' );
		$insert_solution_type_taxonomy_terms->invoke( self::$container['hooks.solution_post_type'] );

		$register_solution_category_taxonomy = PHPUnitUtil::getProtectedMethod( self::$container['hooks.solution_post_type'], 'register_solution_category_taxonomy' );
		$register_solution_category_taxonomy->invoke( self::$container['hooks.solution_post_type'] );

		$register_solution_keyword_taxonomy = PHPUnitUtil::getProtectedMethod( self::$container['hooks.solution_post_type'], 'register_solution_keyword_taxonomy' );
		$register_solution_keyword_taxonomy->invoke( self::$container['hooks.solution_post_type'] );

		// Set this package as a regular solution type.
		$package_type = get_term_by( 'slug', SolutionTypes::REGULAR, self::$container['solution.manager']::TYPE_TAXONOMY );

		// These are solutions that others depend upon.
		self::$dep_posts_data = [
			'blog' => [
				'post_title'  => 'Blog',
				'post_status' => 'publish',
				'post_name'   => 'blog',
				'post_type'   => self::$container['solution.manager']::POST_TYPE,
				'tax_input'   => [
					self::$container['solution.manager']::TYPE_TAXONOMY    => [ $package_type->term_id ],
					self::$container['solution.manager']::KEYWORD_TAXONOMY => 'keyword1, keyword2, keyword3',
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
				'post_type'   => self::$container['solution.manager']::POST_TYPE,
				'tax_input'   => [
					self::$container['solution.manager']::TYPE_TAXONOMY    => [ $package_type->term_id ],
					self::$container['solution.manager']::KEYWORD_TAXONOMY => 'keyword1, keyword2, keyword3',
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

		// Create the test pdsolutions posts that will be dependencies to other posts that we test.
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
			'post_type'   => self::$container['solution.manager']::POST_TYPE,
			'tax_input'   => [
				self::$container['solution.manager']::TYPE_TAXONOMY    => [ $package_type->term_id ],
				self::$container['solution.manager']::KEYWORD_TAXONOMY => 'keyword1, keyword2, keyword3',
			],
			'meta_input'  => [
				'_solution_details_description'                    => 'Package custom description (ecommerce).',
				'_solution_details_longdescription'                => '<h2>Awesome eCommerce solution</h2>
This is the <strong>long, rich-text description</strong> for this <em>solution.</em>

And here is a quote from a customer:
<blockquote>Pure bliss, man!</blockquote>',
				'_solution_details_homepage'                       => 'https://package.homepage',
				'_solution_required_parts|||0|value'               => '_',
				'_solution_required_parts|package_name|0|0|value'  => 'pressody-records/part_yet-another',
				'_solution_required_parts|version_range|0|0|value' => '1.2.9',
				'_solution_required_parts|stability|0|0|value'     => 'stable',
				'_solution_required_solutions|||0|value'           => '_',
				'_solution_required_solutions|pseudo_id|0|0|value' => 'edd #' . $dep_post_ids['edd'],
			],
		];

		$post_ids['ecommerce'] = $factory->post->create_object( self::$posts_data['ecommerce'] );

		// Requires the blog solution and excludes the ecommerce one.
		self::$posts_data['presentation'] = [
			'post_title'  => 'Presentation',
			'post_status' => 'publish',
			'post_name'   => 'presentation',
			'post_type'   => self::$container['solution.manager']::POST_TYPE,
			'tax_input'   => [
				self::$container['solution.manager']::TYPE_TAXONOMY    => [ $package_type->term_id ],
				self::$container['solution.manager']::KEYWORD_TAXONOMY => 'keyword9, keyword10, keyword11',
			],
			'meta_input'  => [
				'_solution_details_description'                    => 'Package custom description (presentation).',
				'_solution_details_longdescription'                => '<h2>Awesome presentation solution</h2>
This is the <strong>long, rich-text description</strong> for this <em>solution.</em>

And here is a quote from a customer:
<blockquote>Pure bliss, man!</blockquote>',
				'_solution_details_homepage'                       => 'https://package.homepage',
				'_solution_required_parts|||0|value'               => '_',
				'_solution_required_parts|package_name|0|0|value'  => 'pressody-records/part_yet-another',
				'_solution_required_parts|version_range|0|0|value' => '1.2.9',
				'_solution_required_parts|stability|0|0|value'     => 'stable',
				'_solution_required_solutions|||0|value'           => '_',
				'_solution_required_solutions|pseudo_id|0|0|value' => 'blog #' . $dep_post_ids['blog'],
			],
		];

		$post_ids['presentation'] = $factory->post->create_object( self::$posts_data['presentation'] );
	}

	public static function wpTearDownAfterClass() {
	}

	public function test_flattened_solutions() {
		/** @var \Pressody\Retailer\Repository\Solutions $repository */
		$repository = plugin()->get_container()['repository.solutions'];
		$repository->reinitialize();

		$post_slugs = [ 'presentation', 'ecommerce', ];

		$filtered_repo = $repository->with_filter(
			function ( $package ) use ( $post_slugs ) {
				if ( ! in_array( $package->get_slug(), $post_slugs ) ) {
					return false;
				}

				return true;
			}
		);

		$flattened_repository = new FlattenedSolutions( $filtered_repo, $repository->get_factory(), $repository->get_solution_manager() );
		$flattened_solutions  = $flattened_repository->all();

		$this->assertNotEmpty( $flattened_solutions['pressody-retailer/edd'] );
		$this->assertInstanceOf( BaseSolution::class, $flattened_solutions['pressody-retailer/edd'] );
		$this->assertNotEmpty( $flattened_solutions['pressody-retailer/ecommerce'] );
		$this->assertInstanceOf( BaseSolution::class, $flattened_solutions['pressody-retailer/ecommerce'] );
		$this->assertNotEmpty( $flattened_solutions['pressody-retailer/blog'] );
		$this->assertInstanceOf( BaseSolution::class, $flattened_solutions['pressody-retailer/blog'] );
		$this->assertNotEmpty( $flattened_solutions['pressody-retailer/presentation'] );
		$this->assertInstanceOf( BaseSolution::class, $flattened_solutions['pressody-retailer/presentation'] );

		// We also expect a certain order, alphabetically by the package name.
		$this->assertSame( [
			'pressody-retailer/blog',
			'pressody-retailer/ecommerce',
			'pressody-retailer/edd',
			'pressody-retailer/presentation',
		], array_keys( $flattened_solutions ) );
	}
}
