<?php
declare ( strict_types=1 );

namespace PixelgradeLT\Retailer\Tests\Integration\REST;

use PixelgradeLT\Retailer\Capabilities;
use PixelgradeLT\Retailer\PurchasedSolutionManager;
use PixelgradeLT\Retailer\SolutionType\SolutionTypes;
use PixelgradeLT\Retailer\Tests\Framework\PHPUnitUtil;
use PixelgradeLT\Retailer\Tests\Integration\TestCase;

use Psr\Container\ContainerInterface;
use function PixelgradeLT\Retailer\local_rest_call;
use function PixelgradeLT\Retailer\plugin;

class CompositionsControllerTest extends TestCase {
	protected static $solutions_post_data;
	protected static $solutions_dep_post_data;
	protected static $solution_ids;
	protected static $compositions_post_data;
	protected static $composition_ids;
	protected static $user_ids;
	protected static $purchased_solution_ids;
	protected static $old_container;

	/**
	 * @param \WP_UnitTest_Factory $factory
	 */
	public static function wpSetUpBeforeClass( $factory ) {
		// Make sure that the administrator role has the needed capabilities.
		Capabilities::register();
		// We need to set a user with sufficient privileges to create packages and edit them.
		wp_set_current_user( 1 );

		/** @var ContainerInterface $old_container */
		self::$old_container = plugin()->get_container();

		/**
		 * CREATE SOLUTIONS IN THE DB.
		 */

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

		self::$solution_ids = [];

		// These are solutions that others depend upon.
		self::$solutions_dep_post_data = [
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
		foreach ( self::$solutions_dep_post_data as $key => $data ) {
			self::$solution_ids[ $key ] = $factory->post->create_object( $data );
		}

		// Requires the edd solution and excludes the blog one.
		self::$solutions_post_data              = [];
		self::$solutions_post_data['ecommerce'] = [
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
			'post_type'   => self::$old_container['solution.manager']::POST_TYPE,
			'tax_input'   => [
				self::$old_container['solution.manager']::TYPE_TAXONOMY    => [ $package_type->term_id ],
				self::$old_container['solution.manager']::KEYWORD_TAXONOMY => 'keyword4, keyword7, keyword9',
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

		self::$user_ids['customer1'] = wp_insert_user( [
			'user_pass'  => 'pass',
			'user_login' => 'customer1',
			'user_email' => 'customer1@lt-retailer.local',
			'first_name' => 'Customer1',
			'last_name'  => 'LTRetailer',
			'role'       => Capabilities::CUSTOMER_USER_ROLE,
		] );

		self::$user_ids['customer2'] = wp_insert_user( [
			'user_pass'  => 'pass',
			'user_login' => 'customer2',
			'user_email' => 'customer2@lt-retailer.local',
			'first_name' => 'Customer2',
			'last_name'  => 'LTRetailer',
			'role'       => Capabilities::CUSTOMER_USER_ROLE,
		] );

		self::$user_ids['customer3'] = wp_insert_user( [
			'user_pass'  => 'pass',
			'user_login' => 'customer3',
			'user_email' => 'customer3@lt-retailer.local',
			'first_name' => 'Customer3',
			'last_name'  => 'LTRetailer',
			'role'       => Capabilities::CUSTOMER_USER_ROLE,
		] );

		self::$user_ids['subscriber'] = wp_insert_user( [
			'user_pass'  => 'pass',
			'user_login' => 'subscriber',
			'user_email' => 'subscriber@lt-retailer.local',
			'first_name' => 'Subscriber',
			'last_name'  => 'LTRetailer',
			'role'       => 'subscriber',
		] );

		/**
		 * CREATE PURCHASED SOLUTIONS.
		 */

		self::$purchased_solution_ids = [];

		self::$purchased_solution_ids['customer1_ecommerce'] = self::$old_container['purchased_solution.manager']->add_purchased_solution( [
			'status' => 'ready',
			'solution_id' => self::$solution_ids['ecommerce'],
			'user_id' => self::$user_ids['customer1'],
		] );

		self::$purchased_solution_ids['customer1_presentation'] = self::$old_container['purchased_solution.manager']->add_purchased_solution( [
			'status' => 'ready',
			'solution_id' => self::$solution_ids['presentation'],
			'user_id' => self::$user_ids['customer1'],
		] );

		self::$purchased_solution_ids['customer2_portfolio'] = self::$old_container['purchased_solution.manager']->add_purchased_solution( [
			'status' => 'ready',
			'solution_id' => self::$solution_ids['portfolio'],
			'user_id' => self::$user_ids['customer2'],
		] );


		/**
		 * CREATE COMPOSITIONS IN THE DB, WITH THE SOLUTIONS.
		 */

		// Register ltcomposition post type
		$register_post_type = PHPUnitUtil::getProtectedMethod( self::$old_container['hooks.composition_post_type'], 'register_post_type' );
		$register_post_type->invoke( self::$old_container['hooks.composition_post_type'] );

		// Register and populate the taxonomies.
		$register_composition_keyword_taxonomy = PHPUnitUtil::getProtectedMethod( self::$old_container['hooks.composition_post_type'], 'register_composition_keyword_taxonomy' );
		$register_composition_keyword_taxonomy->invoke( self::$old_container['hooks.composition_post_type'] );

		self::$composition_ids = [];

		self::$compositions_post_data          = [];

		self::$compositions_post_data['first'] = [
			'post_author' => self::$user_ids['customer1'],
			'post_title'  => 'First',
			'post_status' => 'private',
			'post_name'   => 'first',
			'post_type'   => self::$old_container['composition.manager']::POST_TYPE,
			'tax_input'   => [
				self::$old_container['composition.manager']::KEYWORD_TAXONOMY => 'keyword1, keyword2, keyword3',
			],
			'meta_input'  => [
				'_composition_status' => 'not_ready',
				'_composition_hashid' => 'bogushashid1',
				// These are in the format CarbonFields saves in.
				'_composition_user_ids|||0|value' => 'user:user:' . self::$user_ids['customer1'],
				'_composition_user_ids|||0|type' => 'user',
				'_composition_user_ids|||0|subtype' => 'user',
				'_composition_user_ids|||0|id' => self::$user_ids['customer1'],
				// Add a second owner to this composition.
				'_composition_user_ids|||1|value' => 'user:user:' . self::$user_ids['customer2'],
				'_composition_user_ids|||1|type' => 'user',
				'_composition_user_ids|||1|subtype' => 'user',
				'_composition_user_ids|||1|id' => self::$user_ids['customer2'],
				// Purchased solutions that are included in the composition, from any of the owners.
				'_composition_required_purchased_solutions|||0|value' => self::$purchased_solution_ids['customer1_ecommerce'],
				// Manually included solutions.
				'	_composition_required_manual_solutions|||0|value' => '_',
				'_composition_required_manual_solutions|pseudo_id|0|0|value' => 'portfolio #' . self::$solution_ids['portfolio'],
				'_composition_required_manual_solutions|reason|0|0|value' => 'Just because I can.',
			],
		];
		self::$composition_ids['first'] = $factory->post->create_object( self::$compositions_post_data['first'] );

		self::$compositions_post_data['second'] = [
			'post_author' => self::$user_ids['customer2'],
			'post_title'  => 'Second',
			'post_status' => 'private',
			'post_name'   => 'second',
			'post_type'   => self::$old_container['composition.manager']::POST_TYPE,
			'tax_input'   => [
				self::$old_container['composition.manager']::KEYWORD_TAXONOMY => 'keyword4, keyword5',
			],
			'meta_input'  => [
				'_composition_status' => 'ready',
				'_composition_hashid' => 'bogushashid2',
				// These are in the format CarbonFields saves in.
				'_composition_user_ids|||0|value' => 'user:user:' . self::$user_ids['customer2'],
				'_composition_user_ids|||0|type' => 'user',
				'_composition_user_ids|||0|subtype' => 'user',
				'_composition_user_ids|||0|id' => self::$user_ids['customer2'],
				// Purchased solutions that are included in the composition, from any of the owners.
				'_composition_required_purchased_solutions|||0|value' => self::$purchased_solution_ids['customer2_portfolio'],
				// Manually included solutions. None.
			],
		];
		self::$composition_ids['second'] = $factory->post->create_object( self::$compositions_post_data['second'] );
	}

	public static function wpTearDownAfterClass() {
		foreach ( self::$user_ids as $user_id ) {
			self::delete_user( $user_id );
		}
	}

	public function test_get_items_no_user() {
		wp_set_current_user( 0 );

		// Get compositions.
		$compositions = local_rest_call( '/pixelgradelt_retailer/v1/compositions', 'GET', [] );

		// Should receive error that one needs be logged it to view compositions.
		$this->assertArrayHasKey( 'code', $compositions );
		$this->assertArrayHasKey( 'message', $compositions );
		$this->assertArrayHasKey( 'data', $compositions );
		$this->assertArrayHasKey( 'status', $compositions['data'] );
		$this->assertSame( \rest_authorization_required_code(), $compositions['data']['status'] );
		$this->assertSame( 'rest_cannot_read', $compositions['code'] );
	}

	public function test_get_items_user_without_caps() {
		wp_set_current_user( self::$user_ids['subscriber'] );

		// Get compositions.
		$compositions = local_rest_call( '/pixelgradelt_retailer/v1/compositions', 'GET', [] );

		// Should receive error that one needs be logged it to view compositions.
		$this->assertArrayHasKey( 'code', $compositions );
		$this->assertArrayHasKey( 'message', $compositions );
		$this->assertArrayHasKey( 'data', $compositions );
		$this->assertArrayHasKey( 'status', $compositions['data'] );
		$this->assertSame( \rest_authorization_required_code(), $compositions['data']['status'] );
		$this->assertSame( 'rest_cannot_read', $compositions['code'] );
	}

	public function test_get_items() {
		wp_set_current_user( self::$user_ids['customer1'] );

		// Get compositions.
		$compositions = local_rest_call( '/pixelgradelt_retailer/v1/compositions', 'GET', [] );

		$this->assertArrayNotHasKey( 'code', $compositions );

	}

	public function test_create_item() {

	}

	public function test_get_item() {

	}

	public function test_edit_item() {

	}

	public function test_delete_item() {

	}

	public function test_encrypt_ltdetails() {

	}

	public function test_check_ltdetails() {

	}

	public function test_instructions_to_update_composition() {

	}
}
