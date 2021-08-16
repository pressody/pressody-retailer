<?php
declare ( strict_types=1 );

namespace PixelgradeLT\Retailer\Tests\Integration\Repository;

use PixelgradeLT\Retailer\SolutionType\BaseSolution;
use PixelgradeLT\Retailer\SolutionType\SolutionTypes;
use PixelgradeLT\Retailer\Tests\Framework\PHPUnitUtil;
use PixelgradeLT\Retailer\Tests\Integration\TestCase;

use Psr\Container\ContainerInterface;
use function PixelgradeLT\Retailer\plugin;

class SolutionsTest extends TestCase {
	protected static $posts_data;
	protected static $dep_posts_data;
	protected static $old_container;

	/**
	 * @param \WP_UnitTest_Factory $factory
	 */
	public static function wpSetUpBeforeClass( $factory ) {
		// We need to set a user with sufficient privileges to create packages and edit them.
		set_current_user( 1 );

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

		// Set this package as a basic solution type.
		$package_type = get_term_by( 'slug', SolutionTypes::REGULAR, self::$old_container['solution.manager']::TYPE_TAXONOMY );

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
					'_solution_details_description'     => 'Package custom description.',
					'_solution_details_longdescription' => '<h2>Awesome solution</h2>
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
					'_solution_details_description'     => 'Package custom description.',
					'_solution_details_longdescription' => '<h2>Awesome solution</h2>
This is the <strong>long, rich-text description</strong> for this <em>solution.</em>

And here is a quote from a customer:
<blockquote>Pure bliss, man!</blockquote>',
					'_solution_details_homepage'        => 'https://package.homepage',
				],
			],
		];

		// First, create the test ltsolutions posts that will be dependencies to other posts that we test.
		$dep_post_ids = [];
		foreach ( self::$dep_posts_data as $key => $data ) {
			$dep_post_ids[ $key] = $factory->post->create_object( $data );
		}

		self::$posts_data = [
			'ecommerce' => [
				'post_title'  => 'Ecommerce',
				'post_status' => 'publish',
				'post_name'   => 'ecommerce',
				'post_type'   => self::$old_container['solution.manager']::POST_TYPE,
				'tax_input'   => [
					self::$old_container['solution.manager']::TYPE_TAXONOMY    => [ $package_type->term_id ],
					self::$old_container['solution.manager']::KEYWORD_TAXONOMY => 'keyword1, keyword2, keyword3',
				],
				'meta_input'  => [
					'_solution_details_description'                    => 'Package custom description.',
					'_solution_details_longdescription'                => '<h2>Awesome solution</h2>
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
					'_solution_excluded_solutions|pseudo_id|0|0|value' => 'edd #' . $dep_post_ids['edd'],
				],
			],
		];

		$post_ids = [];
		foreach ( self::$posts_data as $key => $data ) {
			$post_ids[ $key] = $factory->post->create_object( $data );
		}
	}

	public static function wpTearDownAfterClass() {
	}

	public function test_get_non_existent_solution() {
		/** @var \PixelgradeLT\Retailer\Repository\Solutions $repository */
		$repository = plugin()->get_container()['repository.solutions'];
		$repository->reinitialize();

		$package = $repository->first_where( [ 'slug' => 'something-not-here' ] );
		$this->assertNull( $package );
	}

	public function test_get_solution_with_required_parts() {
		/** @var \PixelgradeLT\Retailer\Repository\Solutions $repository */
		$repository = plugin()->get_container()['repository.solutions'];
		$repository->reinitialize();

		$package = $repository->first_where( [ 'slug' => 'ecommerce', ] );

		$this->assertInstanceOf( BaseSolution::class, $package );

		$this->assertCount( 0, $package->get_authors() );
		$this->assertSame( 'Package custom description.', $package->get_description() );
		$this->assertSame( 'https://package.homepage', $package->get_homepage() );
		$this->assertSame( SolutionTypes::REGULAR, $package->get_type() );
		$this->assertCount( 3, $package->get_keywords() );
		$this->assertTrue( $package->has_required_ltrecords_parts() );
		$this->assertCount( 1, $package->get_required_ltrecords_parts() );
		$this->assertTrue( $package->has_required_solutions() );
		$this->assertCount( 1, $package->get_required_solutions() );
		$this->assertTrue( $package->has_excluded_solutions() );
		$this->assertCount( 1, $package->get_excluded_solutions() );
	}
}
