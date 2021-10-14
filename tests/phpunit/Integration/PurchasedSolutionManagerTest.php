<?php
declare ( strict_types=1 );

namespace PixelgradeLT\Retailer\Tests\Integration;

use PixelgradeLT\Retailer\Capabilities;
use PixelgradeLT\Retailer\Database\Rows\PurchasedSolution;
use PixelgradeLT\Retailer\PurchasedSolutionManager;
use PixelgradeLT\Retailer\SolutionType\SolutionTypes;
use PixelgradeLT\Retailer\Tests\Framework\PHPUnitUtil;

use Psr\Container\ContainerInterface;
use function PixelgradeLT\Retailer\plugin;

class PurchasedSolutionManagerTest extends TestCase {
	protected static $solutions_post_data;
	protected static $solutions_dep_post_data;
	protected static $solution_ids;
	protected static $user_ids;
	protected static $purchased_solution_ids;
	protected static $container;

	/**
	 * @param \WP_UnitTest_Factory $factory
	 */
	public static function wpSetUpBeforeClass( $factory ) {
		// Make sure that the administrator role has the needed capabilities.
		Capabilities::register();
		self::_flush_roles();

		// We need to set a user with sufficient privileges to create packages and edit them.
		wp_set_current_user( 1 );

		/** @var ContainerInterface $container */
		self::$container = plugin()->get_container();

		// Register ltsolution post type
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

		self::$solution_ids = [];

		// These are solutions that others depend upon.
		self::$solutions_dep_post_data = [
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

		// Create the test ltsolutions posts that will be dependencies to other posts that we test.
		foreach ( self::$solutions_dep_post_data as $key => $data ) {
			self::$solution_ids[ $key ] = $factory->post->create_object( $data );
		}

		// Requires the edd solution and excludes the blog one.
		self::$solutions_post_data              = [];
		self::$solutions_post_data['ecommerce'] = [
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
				'_solution_required_parts|package_name|0|0|value'  => 'pixelgradelt-records/part_yet-another',
				'_solution_required_parts|version_range|0|0|value' => '1.2.9',
				'_solution_required_parts|stability|0|0|value'     => 'stable',
				'_solution_required_solutions|||0|value'           => '_',
				'_solution_required_solutions|pseudo_id|0|0|value' => 'edd #' . self::$solution_ids['edd'],
				'_solution_excluded_solutions|||0|value'           => '_',
				'_solution_excluded_solutions|pseudo_id|0|0|value' => 'blog #' . self::$solution_ids['blog'],
			],
		];

		self::$solution_ids['ecommerce'] = $factory->post->create_object( self::$solutions_post_data['ecommerce'] );

		// Requires the blog solution and excludes the ecommerce and portfolio ones.
		// Presentation and portfolio are mutually exclusive.
		self::$solutions_post_data['presentation'] = [
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
				'_solution_required_parts|package_name|0|0|value'  => 'pixelgradelt-records/part_yet-another',
				'_solution_required_parts|version_range|0|0|value' => '^1',
				'_solution_required_parts|stability|0|0|value'     => 'stable',
				'_solution_required_solutions|||0|value'           => '_',
				'_solution_required_solutions|pseudo_id|0|0|value' => 'blog #' . self::$solution_ids['blog'],
				'_solution_excluded_solutions|||0|value'           => '_',
				'_solution_excluded_solutions|pseudo_id|0|0|value' => 'ecommerce #' . self::$solution_ids['ecommerce'],
			],
		];

		self::$solution_ids['presentation'] = $factory->post->create_object( self::$solutions_post_data['presentation'] );

		// Requires the blog solution and excludes the presentation one.
		// Presentation and portfolio are mutually exclusive.
		self::$solutions_post_data['portfolio'] = [
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
				'_solution_required_parts|package_name|0|0|value'  => 'pixelgradelt-records/part_yet-another',
				'_solution_required_parts|version_range|0|0|value' => '^2',
				'_solution_required_parts|stability|0|0|value'     => 'stable',
				'_solution_required_parts|package_name|1|0|value'  => 'pixelgradelt-records/part_test-test',
				'_solution_required_parts|version_range|1|0|value' => '^1.0',
				'_solution_required_parts|stability|1|0|value'     => 'stable',
				'_solution_required_solutions|||0|value'           => '_',
				'_solution_required_solutions|pseudo_id|0|0|value' => 'blog #' . self::$solution_ids['blog'],
				'_solution_excluded_solutions|||0|value'           => '_',
				'_solution_excluded_solutions|pseudo_id|0|0|value' => 'presentation #' . self::$solution_ids['presentation'],
			],
		];

		self::$solution_ids['portfolio'] = $factory->post->create_object( self::$solutions_post_data['portfolio'] );
		// Now that we have the portfolio post ID, add some more meta-data to the presentation solution to make them mutually exclusive.
		update_post_meta( self::$solution_ids['presentation'], '_solution_excluded_solutions|||1|value', '_' );
		update_post_meta( self::$solution_ids['presentation'], '_solution_excluded_solutions|pseudo_id|1|0|value', 'portfolio #' . self::$solution_ids['portfolio'] );

		/**
		 * CREATE CUSTOMER ACCOUNTS.
		 */

		self::$user_ids = [];

		self::$user_ids['customer1'] = $factory->user->create( [
			'user_pass'  => 'pass',
			'user_login' => 'customer1',
			'user_email' => 'customer1@lt-retailer.local',
			'first_name' => 'Customer1',
			'last_name'  => 'LTRetailer',
			'role'       => Capabilities::CUSTOMER_USER_ROLE,
		] );

		self::$user_ids['customer2'] = $factory->user->create( [
			'user_pass'  => 'pass',
			'user_login' => 'customer2',
			'user_email' => 'customer2@lt-retailer.local',
			'first_name' => 'Customer2',
			'last_name'  => 'LTRetailer',
			'role'       => Capabilities::CUSTOMER_USER_ROLE,
		] );

		self::$user_ids['customer3'] = $factory->user->create( [
			'user_pass'  => 'pass',
			'user_login' => 'customer3',
			'user_email' => 'customer3@lt-retailer.local',
			'first_name' => 'Customer3',
			'last_name'  => 'LTRetailer',
			'role'       => Capabilities::CUSTOMER_USER_ROLE,
		] );

		self::$user_ids['customer4'] = $factory->user->create( [
			'user_pass'  => 'pass',
			'user_login' => 'customer4',
			'user_email' => 'customer4@lt-retailer.local',
			'first_name' => 'Customer4',
			'last_name'  => 'LTRetailer',
			'role'       => Capabilities::CUSTOMER_USER_ROLE,
		] );

		self::$user_ids['customer5'] = $factory->user->create( [
			'user_pass'  => 'pass',
			'user_login' => 'customer5',
			'user_email' => 'customer5@lt-retailer.local',
			'first_name' => 'Customer5',
			'last_name'  => 'LTRetailer',
			'role'       => Capabilities::CUSTOMER_USER_ROLE,
		] );

		/**
		 * CREATE PURCHASED SOLUTIONS.
		 */

		$purchased_solutions          = new \PixelgradeLT\Retailer\Database\Queries\PurchasedSolution();
		self::$purchased_solution_ids = [];

		self::$purchased_solution_ids['customer1_ecommerce'] = $purchased_solutions->add_item( [
			'status'      => 'ready',
			'solution_id' => self::$solution_ids['ecommerce'],
			'user_id'     => self::$user_ids['customer1'],
		] );

		self::$purchased_solution_ids['customer1_presentation'] = $purchased_solutions->add_item( [
			'status'      => 'ready',
			'solution_id' => self::$solution_ids['presentation'],
			'user_id'     => self::$user_ids['customer1'],
		] );

		self::$purchased_solution_ids['customer2_portfolio'] = $purchased_solutions->add_item( [
			'status'      => 'ready',
			'solution_id' => self::$solution_ids['portfolio'],
			'user_id'     => self::$user_ids['customer2'],
		] );

		self::$purchased_solution_ids['customer5_portfolio'] = $purchased_solutions->add_item( [
			'status'      => 'retired',
			'solution_id' => self::$solution_ids['portfolio'],
			'user_id'     => self::$user_ids['customer5'],
		] );
	}

	public static function wpTearDownAfterClass() {
		foreach ( self::$user_ids as $user_id ) {
			self::delete_user( $user_id );
		}

		// Truncate purchased solutions table.
		self::$container['db.purchased_solutions']->truncate();
	}

	protected static function _flush_roles() {
		// We want to make sure we're testing against the DB, not just in-memory data.
		// This will flush everything and reload it from the DB.
		unset( $GLOBALS['wp_user_roles'] );
		global $wp_roles;
		$wp_roles = new \WP_Roles();
	}

	public function test_get_purchased_solution() {
		/** @var PurchasedSolutionManager $purchased_solution_manager */
		$purchased_solution_manager = self::$container['purchased_solution.manager'];

		/** @var PurchasedSolution $purchased_solution */
		$purchased_solution = $purchased_solution_manager->get_purchased_solution( self::$purchased_solution_ids['customer1_ecommerce'] );
		$this->assertInstanceOf( PurchasedSolution::class, $purchased_solution );
		$this->assertSame( self::$purchased_solution_ids['customer1_ecommerce'], $purchased_solution->id );
		$this->assertSame( 'ready', $purchased_solution->status );
		$this->assertSame( self::$solution_ids['ecommerce'], $purchased_solution->solution_id );
		$this->assertSame( self::$user_ids['customer1'], $purchased_solution->user_id );
		$this->assertSame( 0, $purchased_solution->composition_id );

		// Test non-existent purchased solution.
		$this->assertFalse( $purchased_solution_manager->get_purchased_solution( 231567 ) );
	}

	public function test_get_purchased_solution_by() {
		/** @var PurchasedSolutionManager $purchased_solution_manager */
		$purchased_solution_manager = self::$container['purchased_solution.manager'];

		/** @var PurchasedSolution $purchased_solution */
		$purchased_solution = $purchased_solution_manager->get_purchased_solution_by( 'id', self::$purchased_solution_ids['customer1_ecommerce'] );
		$this->assertInstanceOf( PurchasedSolution::class, $purchased_solution );
		$this->assertSame( self::$purchased_solution_ids['customer1_ecommerce'], $purchased_solution->id );
		$this->assertSame( 'ready', $purchased_solution->status );
		$this->assertSame( self::$solution_ids['ecommerce'], $purchased_solution->solution_id );
		$this->assertSame( self::$user_ids['customer1'], $purchased_solution->user_id );
		$this->assertSame( 0, $purchased_solution->composition_id );

		// Test non-existent purchased solution.
		$this->assertFalse( $purchased_solution_manager->get_purchased_solution_by( 'id', 231567 ) );
	}

	public function test_get_purchased_solutions() {
		/** @var PurchasedSolutionManager $purchased_solution_manager */
		$purchased_solution_manager = self::$container['purchased_solution.manager'];

		$purchased_solutions = $purchased_solution_manager->get_purchased_solutions( [ 'id' => self::$purchased_solution_ids['customer1_ecommerce'] ] );
		$this->assertCount( 1, $purchased_solutions );
		/** @var PurchasedSolution $purchased_solution */
		$purchased_solution = reset( $purchased_solutions );
		$this->assertInstanceOf( PurchasedSolution::class, $purchased_solution );
		$this->assertSame( self::$purchased_solution_ids['customer1_ecommerce'], $purchased_solution->id );
		$this->assertSame( 'ready', $purchased_solution->status );
		$this->assertSame( self::$solution_ids['ecommerce'], $purchased_solution->solution_id );
		$this->assertSame( self::$user_ids['customer1'], $purchased_solution->user_id );
		$this->assertSame( 0, $purchased_solution->composition_id );

		// Test non-existent purchased solution.
		$this->assertSame( [], $purchased_solution_manager->get_purchased_solutions( [ 'id' => 231567, ] ) );

		$purchased_solutions = $purchased_solution_manager->get_purchased_solutions( [ 'solution_id' => self::$solution_ids['portfolio'], ] );
		$this->assertCount( 2, $purchased_solutions );

		$purchased_solutions = $purchased_solution_manager->get_purchased_solutions( [ 'status' => 'ready', ] );
		$this->assertCount( 3, $purchased_solutions );
	}

	public function test_count_purchased_solutions() {
		/** @var PurchasedSolutionManager $purchased_solution_manager */
		$purchased_solution_manager = self::$container['purchased_solution.manager'];

		$this->assertSame( 1, $purchased_solution_manager->count_purchased_solutions( [ 'id' => self::$purchased_solution_ids['customer1_ecommerce'] ] ) );

		// Test non-existent purchased solution.
		$this->assertSame( 0, $purchased_solution_manager->count_purchased_solutions( [ 'id' => 231567, ] ) );

		$this->assertSame( 2, $purchased_solution_manager->count_purchased_solutions( [ 'solution_id' => self::$solution_ids['portfolio'], ] ) );

		$this->assertSame( 3, $purchased_solution_manager->count_purchased_solutions( [ 'status' => 'ready', ] ) );
		$this->assertSame( 1, $purchased_solution_manager->count_purchased_solutions( [ 'status' => 'retired', ] ) );
		$this->assertSame( 0, $purchased_solution_manager->count_purchased_solutions( [ 'status' => 'active', ] ) );
	}

	public function test_get_purchased_solution_counts() {
		/** @var PurchasedSolutionManager $purchased_solution_manager */
		$purchased_solution_manager = self::$container['purchased_solution.manager'];

		$counts = $purchased_solution_manager->get_purchased_solution_counts();

		$this->assertSame( 4, $counts['total'] );
		$this->assertSame( 3, $counts['ready'] );
		$this->assertSame( 1, $counts['retired'] );
		$this->assertArrayNotHasKey( 'active', $counts );

		$counts = $purchased_solution_manager->get_purchased_solution_counts( [
			'groupby' => 'solution_id',
		] );

		$this->assertSame( 4, $counts['total'] );
		$this->assertSame( 1, $counts[ self::$solution_ids['ecommerce'] ] );
		$this->assertSame( 1, $counts[ self::$solution_ids['presentation'] ] );
		$this->assertSame( 2, $counts[ self::$solution_ids['portfolio'] ] );

		$counts = $purchased_solution_manager->get_purchased_solution_counts( [
			'groupby' => 'user_id',
		] );

		$this->assertSame( 4, $counts['total'] );
		$this->assertSame( 2, $counts[ self::$user_ids['customer1'] ] );
		$this->assertSame( 1, $counts[ self::$user_ids['customer2'] ] );
		$this->assertSame( 1, $counts[ self::$user_ids['customer5'] ] );
		$this->assertArrayNotHasKey( self::$user_ids['customer3'], $counts );

		// Mix in queries.
		$counts = $purchased_solution_manager->get_purchased_solution_counts( [
			'user_id__in' => [ self::$user_ids['customer1'], self::$user_ids['customer2'], ],
			'groupby'     => 'user_id',
		] );

		$this->assertSame( 3, $counts['total'] );
		$this->assertSame( 2, $counts[ self::$user_ids['customer1'] ] );
		$this->assertSame( 1, $counts[ self::$user_ids['customer2'] ] );
		$this->assertArrayNotHasKey( self::$user_ids['customer5'], $counts );
	}

	public function test_add_purchased_solution() {
		/** @var PurchasedSolutionManager $purchased_solution_manager */
		$purchased_solution_manager = self::$container['purchased_solution.manager'];

		$expected = [
			'status'         => 'retired',
			'solution_id'    => self::$solution_ids['presentation'],
			'user_id'        => self::$user_ids['customer4'],
			'composition_id' => 123,
		];

		$result = $purchased_solution_manager->add_purchased_solution( $expected );
		$this->assertNotFalse( $result );

		/** @var PurchasedSolution $purchased_solution */
		$purchased_solution = $purchased_solution_manager->get_purchased_solution( $result );
		$this->assertInstanceOf( PurchasedSolution::class, $purchased_solution );
		$this->assertSame( $result, $purchased_solution->id );
		$this->assertSame( $expected['status'], $purchased_solution->status );
		$this->assertSame( $expected['solution_id'], $purchased_solution->solution_id );
		$this->assertSame( $expected['user_id'], $purchased_solution->user_id );
		$this->assertSame( $expected['composition_id'], $purchased_solution->composition_id );
	}

	public function test_update_purchased_solution() {
		/** @var PurchasedSolutionManager $purchased_solution_manager */
		$purchased_solution_manager = self::$container['purchased_solution.manager'];

		$expected = [
			'status'         => 'ready',
			'solution_id'    => self::$solution_ids['presentation'],
			'user_id'        => self::$user_ids['customer4'],
			'composition_id' => 456,
		];

		$result = $purchased_solution_manager->add_purchased_solution( $expected );
		$this->assertNotFalse( $result );

		/** @var PurchasedSolution $purchased_solution */
		$purchased_solution = $purchased_solution_manager->get_purchased_solution( $result );
		$this->assertInstanceOf( PurchasedSolution::class, $purchased_solution );
		$this->assertSame( $result, $purchased_solution->id );
		$this->assertSame( $expected['status'], $purchased_solution->status );
		$this->assertSame( $expected['solution_id'], $purchased_solution->solution_id );
		$this->assertSame( $expected['user_id'], $purchased_solution->user_id );
		$this->assertSame( $expected['composition_id'], $purchased_solution->composition_id );

		// Update it.
		$expected = [
			'status'         => 'active',
			'solution_id'    => self::$solution_ids['presentation'],
			'user_id'        => self::$user_ids['customer1'],
			'composition_id' => 789,
		];

		$result = $purchased_solution_manager->update_purchased_solution( $purchased_solution->id, $expected );
		$this->assertTrue( $result );

		/** @var PurchasedSolution $purchased_solution */
		$purchased_solution = $purchased_solution_manager->get_purchased_solution( $purchased_solution->id );
		$this->assertInstanceOf( PurchasedSolution::class, $purchased_solution );
		$this->assertSame( $expected['status'], $purchased_solution->status );
		$this->assertSame( $expected['solution_id'], $purchased_solution->solution_id );
		$this->assertSame( $expected['user_id'], $purchased_solution->user_id );
		$this->assertSame( $expected['composition_id'], $purchased_solution->composition_id );
	}

	public function test_invalidate_purchased_solution() {
		/** @var PurchasedSolutionManager $purchased_solution_manager */
		$purchased_solution_manager = self::$container['purchased_solution.manager'];

		$expected = [
			'status'         => 'ready',
			'solution_id'    => self::$solution_ids['presentation'],
			'user_id'        => self::$user_ids['customer4'],
			'composition_id' => 456,
		];

		$id = $purchased_solution_manager->add_purchased_solution( $expected );
		$this->assertNotFalse( $id );

		// Invalidate it.
		$result = $purchased_solution_manager->invalidate_purchased_solution( $id );

		$this->assertTrue( $result );

		/** @var PurchasedSolution $purchased_solution */
		$purchased_solution = $purchased_solution_manager->get_purchased_solution( $id );
		$this->assertInstanceOf( PurchasedSolution::class, $purchased_solution );
		$this->assertSame( 'invalid', $purchased_solution->status );
		$this->assertSame( $expected['solution_id'], $purchased_solution->solution_id );
		$this->assertSame( $expected['user_id'], $purchased_solution->user_id );
		$this->assertSame( $expected['composition_id'], $purchased_solution->composition_id );
	}

	public function test_ready_purchased_solution() {
		/** @var PurchasedSolutionManager $purchased_solution_manager */
		$purchased_solution_manager = self::$container['purchased_solution.manager'];

		$expected = [
			'status'         => 'invalid',
			'solution_id'    => self::$solution_ids['presentation'],
			'user_id'        => self::$user_ids['customer4'],
			'composition_id' => 456,
		];

		$id = $purchased_solution_manager->add_purchased_solution( $expected );
		$this->assertNotFalse( $id );

		// Ready it.
		$result = $purchased_solution_manager->ready_purchased_solution( $id );

		$this->assertTrue( $result );

		/** @var PurchasedSolution $purchased_solution */
		$purchased_solution = $purchased_solution_manager->get_purchased_solution( $id );
		$this->assertInstanceOf( PurchasedSolution::class, $purchased_solution );
		$this->assertSame( 'ready', $purchased_solution->status );
		$this->assertSame( $expected['solution_id'], $purchased_solution->solution_id );
		$this->assertSame( $expected['user_id'], $purchased_solution->user_id );
		$this->assertSame( $expected['composition_id'], $purchased_solution->composition_id );
	}

	public function test_activate_purchased_solution() {
		/** @var PurchasedSolutionManager $purchased_solution_manager */
		$purchased_solution_manager = self::$container['purchased_solution.manager'];

		$expected = [
			'status'         => 'ready',
			'solution_id'    => self::$solution_ids['presentation'],
			'user_id'        => self::$user_ids['customer4'],
			'composition_id' => 0,
		];

		$id = $purchased_solution_manager->add_purchased_solution( $expected );
		$this->assertNotFalse( $id );

		// Activate it.
		$result = $purchased_solution_manager->activate_purchased_solution( $id, 123 );
		$this->assertTrue( $result );

		/** @var PurchasedSolution $purchased_solution */
		$purchased_solution = $purchased_solution_manager->get_purchased_solution( $id );
		$this->assertInstanceOf( PurchasedSolution::class, $purchased_solution );
		$this->assertSame( 'active', $purchased_solution->status );
		$this->assertSame( $expected['solution_id'], $purchased_solution->solution_id );
		$this->assertSame( $expected['user_id'], $purchased_solution->user_id );
		$this->assertSame( 123, $purchased_solution->composition_id );

		// Only ready purchased solutions can be activated.
		$result = $purchased_solution_manager->invalidate_purchased_solution( $id );
		$this->assertTrue( $result );

		// Try to activate it. It should fail.
		$result = $purchased_solution_manager->activate_purchased_solution( $id, 87 );
		$this->assertFalse( $result );
	}

	public function test_deactivate_purchased_solution() {
		/** @var PurchasedSolutionManager $purchased_solution_manager */
		$purchased_solution_manager = self::$container['purchased_solution.manager'];

		$expected = [
			'status'         => 'active',
			'solution_id'    => self::$solution_ids['presentation'],
			'user_id'        => self::$user_ids['customer4'],
			'composition_id' => 123,
		];

		$id = $purchased_solution_manager->add_purchased_solution( $expected );
		$this->assertNotFalse( $id );

		// Deactivate it.
		$result = $purchased_solution_manager->deactivate_purchased_solution( $id );
		$this->assertTrue( $result );

		/** @var PurchasedSolution $purchased_solution */
		$purchased_solution = $purchased_solution_manager->get_purchased_solution( $id );
		$this->assertInstanceOf( PurchasedSolution::class, $purchased_solution );
		$this->assertSame( 'ready', $purchased_solution->status );
		$this->assertSame( $expected['solution_id'], $purchased_solution->solution_id );
		$this->assertSame( $expected['user_id'], $purchased_solution->user_id );
		$this->assertSame( 0, $purchased_solution->composition_id );

		// Only active purchased solutions can be activated.
		$result = $purchased_solution_manager->invalidate_purchased_solution( $id );
		$this->assertTrue( $result );

		// Try to deactivate it. It should fail.
		$result = $purchased_solution_manager->deactivate_purchased_solution( $id );
		$this->assertFalse( $result );

		// If we provide a composition ID, deactivation will take place only if the provided composition ID matched the current one.
		// Activate it.
		$result = $purchased_solution_manager->ready_purchased_solution( $id );
		$this->assertTrue( $result );
		$result = $purchased_solution_manager->activate_purchased_solution( $id, 123 );
		$this->assertTrue( $result );

		// Try to deactivate it. It should fail.
		$result = $purchased_solution_manager->deactivate_purchased_solution( $id, 456 );
		$this->assertFalse( $result );
	}

	public function test_retire_purchased_solution() {
		/** @var PurchasedSolutionManager $purchased_solution_manager */
		$purchased_solution_manager = self::$container['purchased_solution.manager'];

		$expected = [
			'status'         => 'active',
			'solution_id'    => self::$solution_ids['presentation'],
			'user_id'        => self::$user_ids['customer4'],
			'composition_id' => 97,
		];

		$id = $purchased_solution_manager->add_purchased_solution( $expected );
		$this->assertNotFalse( $id );

		// Retire it.
		$result = $purchased_solution_manager->retire_purchased_solution( $id );
		$this->assertTrue( $result );

		/** @var PurchasedSolution $purchased_solution */
		$purchased_solution = $purchased_solution_manager->get_purchased_solution( $id );
		$this->assertInstanceOf( PurchasedSolution::class, $purchased_solution );
		$this->assertSame( 'retired', $purchased_solution->status );
		$this->assertSame( $expected['solution_id'], $purchased_solution->solution_id );
		$this->assertSame( $expected['user_id'], $purchased_solution->user_id );
		$this->assertSame( $expected['composition_id'], $purchased_solution->composition_id );
	}

	public function test_delete_purchased_solution() {
		/** @var PurchasedSolutionManager $purchased_solution_manager */
		$purchased_solution_manager = self::$container['purchased_solution.manager'];

		$expected = [
			'status'         => 'active',
			'solution_id'    => self::$solution_ids['presentation'],
			'user_id'        => self::$user_ids['customer4'],
			'composition_id' => 97,
		];

		$id = $purchased_solution_manager->add_purchased_solution( $expected );
		$this->assertNotFalse( $id );

		// Delete it.
		$result = $purchased_solution_manager->delete_purchased_solution( $id );
		$this->assertTrue( $result );

		/** @var PurchasedSolution $purchased_solution */
		$purchased_solution = $purchased_solution_manager->get_purchased_solution( $id );
		$this->assertFalse( $purchased_solution );
	}
}
