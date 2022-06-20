<?php
declare ( strict_types=1 );

namespace Pressody\Retailer\Tests\Integration;

use Pressody\Retailer\Capabilities;
use Pressody\Retailer\Repository\Solutions;
use Pressody\Retailer\SolutionManager;
use Pressody\Retailer\SolutionType\BaseSolution;
use Pressody\Retailer\SolutionType\SolutionTypes;
use Pressody\Retailer\Tests\Framework\PHPUnitUtil;

use Psr\Container\ContainerInterface;
use function Pressody\Retailer\get_composer_vendor;
use function Pressody\Retailer\plugin;

class SolutionManagerTest extends TestCase {
	protected static $posts_data;
	protected static $dep_posts_data;
	protected static $post_ids;
	protected static $container;

	/**
	 * @param \WP_UnitTest_Factory $factory
	 */
	public static function wpSetUpBeforeClass( $factory ) {
		// Make sure that the administrator role has the needed capabilities.
		Capabilities::register();
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

		self::$post_ids = [];

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
		foreach ( self::$dep_posts_data as $key => $data ) {
			self::$post_ids[ $key ] = $factory->post->create_object( $data );
		}

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
				'_solution_required_solutions|pseudo_id|0|0|value' => 'edd #' . self::$post_ids['edd'],
				'_solution_excluded_solutions|||0|value'           => '_',
				'_solution_excluded_solutions|pseudo_id|0|0|value' => 'blog #' . self::$post_ids['blog'],
			],
		];

		self::$post_ids['ecommerce'] = $factory->post->create_object( self::$posts_data['ecommerce'] );

		// Requires the blog solution and excludes the ecommerce and portfolio ones.
		// Presentation and portfolio are mutually exclusive.
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
				'_solution_required_parts|version_range|0|0|value' => '^1',
				'_solution_required_parts|stability|0|0|value'     => 'stable',
				'_solution_required_solutions|||0|value'           => '_',
				'_solution_required_solutions|pseudo_id|0|0|value' => 'blog #' . self::$post_ids['blog'],
				'_solution_excluded_solutions|||0|value'           => '_',
				'_solution_excluded_solutions|pseudo_id|0|0|value' => 'ecommerce #' . self::$post_ids['ecommerce'],
			],
		];

		self::$post_ids['presentation'] = $factory->post->create_object( self::$posts_data['presentation'] );

		// Requires the blog solution and excludes the presentation one.
		// Presentation and portfolio are mutually exclusive.
		self::$posts_data['portfolio'] = [
			'post_title'  => 'Portfolio',
			'post_status' => 'publish',
			'post_name'   => 'portfolio',
			'post_type'   => self::$container['solution.manager']::POST_TYPE,
			'tax_input'   => [
				self::$container['solution.manager']::TYPE_TAXONOMY    => [ $package_type->term_id ],
				self::$container['solution.manager']::KEYWORD_TAXONOMY => 'keyword4, keyword7, keyword9',
			],
			'meta_input'  => [
				'_solution_details_description'                    => 'Package custom description (portfolio).',
				'_solution_details_longdescription'                => '<h2>Awesome portfolio solution</h2>
This is the <strong>long, rich-text description</strong> for this <em>solution.</em>

And here is a quote from a customer:
<blockquote>Pure bliss, man!</blockquote>',
				'_solution_details_homepage'                       => 'https://package.homepage',
				'_solution_required_parts|||0|value'               => '_',
				'_solution_required_parts|||1|value'               => '_',
				'_solution_required_parts|package_name|0|0|value'  => 'pressody-records/part_yet-another',
				'_solution_required_parts|version_range|0|0|value' => '^2',
				'_solution_required_parts|stability|0|0|value'     => 'stable',
				'_solution_required_parts|package_name|1|0|value'  => 'pressody-records/part_test-test',
				'_solution_required_parts|version_range|1|0|value' => '^1.0',
				'_solution_required_parts|stability|1|0|value'     => 'stable',
				'_solution_required_solutions|||0|value'           => '_',
				'_solution_required_solutions|pseudo_id|0|0|value' => 'blog #' . self::$post_ids['blog'],
				'_solution_excluded_solutions|||0|value'           => '_',
				'_solution_excluded_solutions|pseudo_id|0|0|value' => 'presentation #' . self::$post_ids['presentation'],
			],
		];

		self::$post_ids['portfolio'] = $factory->post->create_object( self::$posts_data['portfolio'] );
		// Now that we have the portfolio post ID, add some more meta-data to the presentation solution to make them mutually exclusive.
		update_post_meta( self::$post_ids['presentation'], '_solution_excluded_solutions|||1|value', '_' );
		update_post_meta( self::$post_ids['presentation'], '_solution_excluded_solutions|pseudo_id|1|0|value', 'portfolio #' . self::$post_ids['portfolio'] );
	}

	public static function wpTearDownAfterClass() {
	}

	public function test_get_solution_type_ids() {
		/** @var SolutionManager $solution_manager */
		$solution_manager = self::$container['solution.manager'];

		$this->assertEqualSets( array_values( self::$post_ids ), $solution_manager->get_solution_type_ids() );
		$this->assertEqualSets( array_values( self::$post_ids ), $solution_manager->get_solution_type_ids('all') );
		$this->assertEqualSets( array_values( self::$post_ids ), $solution_manager->get_solution_type_ids(SolutionTypes::REGULAR ) );
	}

	public function test_get_solution_ids_by() {
		/** @var SolutionManager $solution_manager */
		$solution_manager = self::$container['solution.manager'];

		$this->assertEqualSets( array_values( self::$post_ids ), $solution_manager->get_solution_ids_by( [] ) );
		$this->assertEqualSets( array_values( self::$post_ids ), $solution_manager->get_solution_ids_by( [
			'solution_type' => SolutionTypes::REGULAR,
		] ) );
		$this->assertEqualSets( [ self::$post_ids['blog'], ], $solution_manager->get_solution_ids_by( [
			'post_ids' => self::$post_ids['blog'],
		] ) );
		$this->assertEqualSets( [ self::$post_ids['blog'], ], $solution_manager->get_solution_ids_by( [
			'post_ids' => [ self::$post_ids['blog'], ],
		] ) );
		$expected = self::$post_ids;
		unset( $expected['blog'] );
		$this->assertEqualSets( $expected, $solution_manager->get_solution_ids_by( [
			'exclude_post_ids' => self::$post_ids['blog'],
		] ) );
		$this->assertEqualSets( [ self::$post_ids['blog'], ], $solution_manager->get_solution_ids_by( [
			'slug' => 'blog',
		] ) );
		$this->assertEqualSets( array_values( self::$post_ids ), $solution_manager->get_solution_ids_by( [
			'post_status' => 'publish',
		] ) );
		$this->assertEqualSets( [], $solution_manager->get_solution_ids_by( [
			'post_status' => 'private',
		] ) );
	}

	public function test_get_solution_id_data() {
		/** @var SolutionManager $solution_manager */
		$solution_manager = self::$container['solution.manager'];

		$solution_data = $solution_manager->get_solution_id_data( self::$post_ids['portfolio'] );

		$this->assertTrue( $solution_data['is_managed'] );
		$this->assertSame( self::$post_ids['portfolio'], $solution_data['managed_post_id'] );
		$this->assertSame( 'public', $solution_data['visibility'] );
		$this->assertSame( self::$posts_data['portfolio']['post_title'], $solution_data['name'] );
		$this->assertSame( SolutionTypes::REGULAR, $solution_data['type'] );
		$this->assertSame( self::$posts_data['portfolio']['post_name'], $solution_data['slug'] );
		$this->assertSame( [], $solution_data['categories'] );
		$this->assertEqualSets( ['keyword4', 'keyword7', 'keyword9', ], $solution_data['keywords'] );
		$this->assertSame( self::$posts_data['portfolio']['meta_input']['_solution_details_description'], $solution_data['description'] );
		$this->assertSame( self::$posts_data['portfolio']['meta_input']['_solution_details_homepage'], $solution_data['homepage'] );
		$this->assertCount( 2, $solution_data['required_pdrecords_parts'] );
		$this->assertEqualSets( [ 'pressody-records/part_yet-another', 'pressody-records/part_test-test', ], \wp_list_pluck( $solution_data['required_pdrecords_parts'], 'package_name' ) );
		$this->assertCount( 1, $solution_data['required_solutions'] );
		$this->assertEqualSets( [ self::$post_ids['blog'], ], \wp_list_pluck( $solution_data['required_solutions'], 'managed_post_id' ) );
		$this->assertCount( 1, $solution_data['excluded_solutions'] );
		$this->assertEqualSets( [ self::$post_ids['presentation'], ], \wp_list_pluck( $solution_data['excluded_solutions'], 'managed_post_id' ) );
		$this->assertSame( [], $solution_data['composer_require'] );
	}

	public function test_get_solution_data_by() {
		/** @var SolutionManager $solution_manager */
		$solution_manager = self::$container['solution.manager'];

		$solution_data = $solution_manager->get_solution_data_by( ['post_ids' => [ self::$post_ids['portfolio'], ], ] );

		$this->assertTrue( $solution_data['is_managed'] );
		$this->assertSame( self::$post_ids['portfolio'], $solution_data['managed_post_id'] );
		$this->assertSame( 'public', $solution_data['visibility'] );
		$this->assertSame( self::$posts_data['portfolio']['post_title'], $solution_data['name'] );
		$this->assertSame( SolutionTypes::REGULAR, $solution_data['type'] );
		$this->assertSame( self::$posts_data['portfolio']['post_name'], $solution_data['slug'] );
		$this->assertSame( [], $solution_data['categories'] );
		$this->assertEqualSets( ['keyword4', 'keyword7', 'keyword9', ], $solution_data['keywords'] );
		$this->assertSame( self::$posts_data['portfolio']['meta_input']['_solution_details_description'], $solution_data['description'] );
		$this->assertSame( self::$posts_data['portfolio']['meta_input']['_solution_details_homepage'], $solution_data['homepage'] );
		$this->assertCount( 2, $solution_data['required_pdrecords_parts'] );
		$this->assertEqualSets( [ 'pressody-records/part_yet-another', 'pressody-records/part_test-test', ], \wp_list_pluck( $solution_data['required_pdrecords_parts'], 'package_name' ) );
		$this->assertCount( 1, $solution_data['required_solutions'] );
		$this->assertEqualSets( [ self::$post_ids['blog'], ], \wp_list_pluck( $solution_data['required_solutions'], 'managed_post_id' ) );
		$this->assertCount( 1, $solution_data['excluded_solutions'] );
		$this->assertEqualSets( [ self::$post_ids['presentation'], ], \wp_list_pluck( $solution_data['excluded_solutions'], 'managed_post_id' ) );
		$this->assertSame( [], $solution_data['composer_require'] );
	}

	public function test_get_post_solution_visibility() {
		/** @var SolutionManager $solution_manager */
		$solution_manager = self::$container['solution.manager'];

		$this->assertSame( 'public', $solution_manager->get_post_solution_visibility( self::$post_ids['blog'] ) );
	}

	public function test_get_post_solution_name() {
		/** @var SolutionManager $solution_manager */
		$solution_manager = self::$container['solution.manager'];

		$this->assertSame( self::$posts_data['ecommerce']['post_title'], $solution_manager->get_post_solution_name( self::$post_ids['ecommerce'] ) );
	}

	public function test_get_post_solution_type() {
		/** @var SolutionManager $solution_manager */
		$solution_manager = self::$container['solution.manager'];

		$this->assertSame( SolutionTypes::REGULAR, $solution_manager->get_post_solution_type( self::$post_ids['ecommerce'] ) );
	}

	public function test_get_post_solution_slug() {
		/** @var SolutionManager $solution_manager */
		$solution_manager = self::$container['solution.manager'];

		$this->assertSame( self::$posts_data['ecommerce']['post_name'], $solution_manager->get_post_solution_slug( self::$post_ids['ecommerce'] ) );
	}

	public function test_get_post_solution_categories() {
		/** @var SolutionManager $solution_manager */
		$solution_manager = self::$container['solution.manager'];

		$this->assertSame( [], $solution_manager->get_post_solution_categories( self::$post_ids['ecommerce'] ) );
	}

	public function test_get_post_solution_keywords() {
		/** @var SolutionManager $solution_manager */
		$solution_manager = self::$container['solution.manager'];

		$this->assertEqualSets( ['keyword4', 'keyword7', 'keyword9', ], $solution_manager->get_post_solution_keywords( self::$post_ids['portfolio'] ) );
	}

	public function test_get_post_solution_required_solutions() {
		/** @var SolutionManager $solution_manager */
		$solution_manager = self::$container['solution.manager'];

		$this->assertEqualSets( [ self::$post_ids['blog'], ], \wp_list_pluck( $solution_manager->get_post_solution_required_solutions( self::$post_ids['portfolio'] ), 'managed_post_id' ) );
	}

	public function test_get_post_solution_excluded_solutions() {
		/** @var SolutionManager $solution_manager */
		$solution_manager = self::$container['solution.manager'];

		$this->assertEqualSets( [ self::$post_ids['presentation'], ], \wp_list_pluck( $solution_manager->get_post_solution_excluded_solutions( self::$post_ids['portfolio'] ), 'managed_post_id' ) );
	}

	public function test_get_post_solution_required_parts() {
		/** @var SolutionManager $solution_manager */
		$solution_manager = self::$container['solution.manager'];

		$this->assertEqualSets( [ 'pressody-records/part_yet-another', 'pressody-records/part_test-test', ], \wp_list_pluck( $solution_manager->get_post_solution_required_parts( self::$post_ids['portfolio'] ), 'package_name' ) );
	}

	public function test_get_post_solution_composer_require() {
		/** @var SolutionManager $solution_manager */
		$solution_manager = self::$container['solution.manager'];

		$this->assertSame( [], $solution_manager->get_post_solution_composer_require( self::$post_ids['ecommerce'] ) );
	}

	public function test_is_solution_public() {
		/** @var SolutionManager $solution_manager */
		$solution_manager = self::$container['solution.manager'];

		/** @var Solutions $solutions_repository */
		$solutions_repository = self::$container['repository.solutions'];

		$solution_package = $solutions_repository->first_where( [ 'managed_post_id' => self::$post_ids['ecommerce'] ] );

		$this->assertTrue( $solution_manager->is_solution_public( $solution_package ) );
	}

	public function test_get_solution_visibility() {
		/** @var SolutionManager $solution_manager */
		$solution_manager = self::$container['solution.manager'];

		/** @var Solutions $solutions_repository */
		$solutions_repository = self::$container['repository.solutions'];

		$solution_package = $solutions_repository->first_where( [ 'managed_post_id' => self::$post_ids['ecommerce'] ] );

		$this->assertSame( 'public', $solution_manager->get_solution_visibility( $solution_package ) );

		// Non-managed solutions are always public.
		$this->assertSame( 'public', $solution_manager->get_solution_visibility( new BaseSolution() ) );
	}

	public function test_solution_name_to_composer_package_name() {
		/** @var SolutionManager $solution_manager */
		$solution_manager = self::$container['solution.manager'];

		$vendor = get_composer_vendor();

		$this->assertSame( $vendor . '/name', $solution_manager->solution_name_to_composer_package_name( 'name' ) );
	}
}
