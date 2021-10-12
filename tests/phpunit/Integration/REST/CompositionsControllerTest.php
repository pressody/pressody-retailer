<?php
declare ( strict_types=1 );

namespace PixelgradeLT\Retailer\Tests\Integration\REST;

use PixelgradeLT\Retailer\Capabilities;
use PixelgradeLT\Retailer\CompositionManager;
use PixelgradeLT\Retailer\SolutionType\SolutionTypes;
use PixelgradeLT\Retailer\Tests\Framework\PHPUnitUtil;
use PixelgradeLT\Retailer\Tests\Integration\TestCase;

use Psr\Container\ContainerInterface;
use function PixelgradeLT\Retailer\local_rest_call;
use function PixelgradeLT\Retailer\plugin;

use WP_Http as HTTP;

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
		self::$solution_ids['ecommerce']        = $factory->post->create_object( self::$solutions_post_data['ecommerce'] );

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
		self::$solution_ids['presentation']        = $factory->post->create_object( self::$solutions_post_data['presentation'] );

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
		self::$solution_ids['portfolio']        = $factory->post->create_object( self::$solutions_post_data['portfolio'] );
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

		self::$user_ids['subscriber'] = $factory->user->create( [
			'user_pass'  => 'pass',
			'user_login' => 'subscriber',
			'user_email' => 'subscriber@lt-retailer.local',
			'first_name' => 'Subscriber',
			'last_name'  => 'LTRetailer',
			'role'       => 'subscriber',
		] );

		self::$user_ids['manager1'] = $factory->user->create( [
			'user_pass'  => 'pass',
			'user_login' => 'manager1',
			'user_email' => 'manager1@lt-retailer.local',
			'first_name' => 'Manager1',
			'last_name'  => 'LTRetailer',
			'role'       => 'administrator',
		] );

		/**
		 * CREATE PURCHASED SOLUTIONS.
		 */

		self::$purchased_solution_ids = [];

		self::$purchased_solution_ids['customer1_ecommerce'] = self::$old_container['purchased_solution.manager']->add_purchased_solution( [
			'status'      => 'ready',
			'solution_id' => self::$solution_ids['ecommerce'],
			'user_id'     => self::$user_ids['customer1'],
		] );

		self::$purchased_solution_ids['customer1_presentation'] = self::$old_container['purchased_solution.manager']->add_purchased_solution( [
			'status'      => 'ready',
			'solution_id' => self::$solution_ids['presentation'],
			'user_id'     => self::$user_ids['customer1'],
		] );

		self::$purchased_solution_ids['customer2_portfolio'] = self::$old_container['purchased_solution.manager']->add_purchased_solution( [
			'status'      => 'ready',
			'solution_id' => self::$solution_ids['portfolio'],
			'user_id'     => self::$user_ids['customer2'],
		] );

		self::$purchased_solution_ids['customer5_portfolio'] = self::$old_container['purchased_solution.manager']->add_purchased_solution( [
			'status'      => 'ready',
			'solution_id' => self::$solution_ids['portfolio'],
			'user_id'     => self::$user_ids['customer5'],
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

		self::$compositions_post_data = [];

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
				'_composition_status'                                        => CompositionManager::DEFAULT_STATUS,
				'_composition_hashid'                                        => 'bogushashid1',
				// These are in the format CarbonFields saves in.
				'_composition_user_ids|||0|value'                            => 'user:user:' . self::$user_ids['customer1'],
				'_composition_user_ids|||0|type'                             => 'user',
				'_composition_user_ids|||0|subtype'                          => 'user',
				'_composition_user_ids|||0|id'                               => self::$user_ids['customer1'],
				// Add a second owner to this composition.
				'_composition_user_ids|||1|value'                            => 'user:user:' . self::$user_ids['customer2'],
				'_composition_user_ids|||1|type'                             => 'user',
				'_composition_user_ids|||1|subtype'                          => 'user',
				'_composition_user_ids|||1|id'                               => self::$user_ids['customer2'],
				// Purchased solutions that are included in the composition, from any of the owners.
				'_composition_required_purchased_solutions|||0|value'        => self::$purchased_solution_ids['customer1_ecommerce'],
				// Manually included solutions.
				'	_composition_required_manual_solutions|||0|value'       => '_',
				'_composition_required_manual_solutions|pseudo_id|0|0|value' => 'portfolio #' . self::$solution_ids['portfolio'],
				'_composition_required_manual_solutions|reason|0|0|value'    => 'Just because I can.',
			],
		];
		self::$composition_ids['first']        = $factory->post->create_object( self::$compositions_post_data['first'] );

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
				'_composition_status'                                 => 'ready',
				'_composition_hashid'                                 => 'bogushashid2',
				// These are in the format CarbonFields saves in.
				'_composition_user_ids|||0|value'                     => 'user:user:' . self::$user_ids['customer2'],
				'_composition_user_ids|||0|type'                      => 'user',
				'_composition_user_ids|||0|subtype'                   => 'user',
				'_composition_user_ids|||0|id'                        => self::$user_ids['customer2'],
				// Purchased solutions that are included in the composition, from any of the owners.
				'_composition_required_purchased_solutions|||0|value' => self::$purchased_solution_ids['customer2_portfolio'],
				// Manually included solutions. None.
			],
		];
		self::$composition_ids['second']        = $factory->post->create_object( self::$compositions_post_data['second'] );

		self::$compositions_post_data['third'] = [
			'post_author' => self::$user_ids['manager1'],
			'post_title'  => 'Third',
			'post_status' => 'private',
			'post_name'   => 'third',
			'post_type'   => self::$old_container['composition.manager']::POST_TYPE,
			'tax_input'   => [
				self::$old_container['composition.manager']::KEYWORD_TAXONOMY => 'keyword43, keyword53',
			],
			'meta_input'  => [
				'_composition_status'                                 => 'ready',
				'_composition_hashid'                                 => 'bogushashid3',
				// These are in the format CarbonFields saves in.
				'_composition_user_ids|||0|value'                     => 'user:user:' . self::$user_ids['customer5'],
				'_composition_user_ids|||0|type'                      => 'user',
				'_composition_user_ids|||0|subtype'                   => 'user',
				'_composition_user_ids|||0|id'                        => self::$user_ids['customer5'],
				// Purchased solutions that are included in the composition, from any of the owners.
				'_composition_required_purchased_solutions|||0|value' => self::$purchased_solution_ids['customer5_portfolio'],
				// Manually included solutions. None.
			],
		];
		self::$composition_ids['third']        = $factory->post->create_object( self::$compositions_post_data['third'] );
	}

	public static function wpTearDownAfterClass() {
		foreach ( self::$user_ids as $user_id ) {
			self::delete_user( $user_id );
		}
	}

	public function test_get_items_no_user() {
		\wp_set_current_user( 0 );
		\wp_clear_auth_cookie();

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
		\wp_set_current_user( self::$user_ids['subscriber'] );
		\wp_set_auth_cookie( self::$user_ids['subscriber'], true, false );

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

	public function test_get_items_user_is_author() {
		// Get compositions authored by customer1.
		\wp_set_current_user( self::$user_ids['customer1'] );
		\wp_set_auth_cookie( self::$user_ids['customer1'], true, false );
		$compositions = local_rest_call( '/pixelgradelt_retailer/v1/compositions', 'GET', [] );

		$this->assertArrayNotHasKey( 'code', $compositions );
		$this->assertCount( 1, $compositions );
		$composition = reset( $compositions );
		// This should only be returned in an edit context.
		$this->assertArrayNotHasKey( 'id', $composition );
		// This should only be returned in an edit context.
		$this->assertArrayNotHasKey( 'author', $composition );
		$this->assertSame( 'First', $composition['name'] );
		$this->assertSame( CompositionManager::DEFAULT_STATUS, $composition['status'] );
		$this->assertSame( 'bogushashid1', $composition['hashid'] );
		$this->assertCount( 2, $composition['users'] );
		// This should only be returned in an edit context.
		$this->assertArrayNotHasKey( 'userids', $composition );
		$this->assertCount( 2, $composition['required_solutions'] );
		$this->assertCount( 1, $composition['required_purchased_solutions'] );
		// This should only be returned in an edit context.
		$this->assertArrayNotHasKey( 'required_purchased_solutions_ids', $composition );
		$this->assertCount( 1, $composition['required_manual_solutions'] );
		$this->assertArrayHasKey( 'composer_require', $composition );
		$this->assertArrayHasKey( 'editLink', $composition );

		// Get compositions authored by customer3.
		\wp_set_current_user( self::$user_ids['customer2'] );
		\wp_set_auth_cookie( self::$user_ids['customer2'], true, false );
		$compositions = local_rest_call( '/pixelgradelt_retailer/v1/compositions', 'GET', [] );

		$this->assertArrayNotHasKey( 'code', $compositions );
		$this->assertCount( 1, $compositions );
		$composition = reset( $compositions );
		// This should only be returned in an edit context.
		$this->assertArrayNotHasKey( 'id', $composition );
		// This should only be returned in an edit context.
		$this->assertArrayNotHasKey( 'author', $composition );
		$this->assertSame( 'Second', $composition['name'] );
		$this->assertSame( 'ready', $composition['status'] );
		$this->assertSame( 'bogushashid2', $composition['hashid'] );
		$this->assertCount( 1, $composition['users'] );
		$this->assertSame( [ self::$user_ids['customer2'], ], array_keys( \wp_list_pluck( $composition['users'], 'id' ) ) );
		// This should only be returned in an edit context.
		$this->assertArrayNotHasKey( 'userids', $composition );
		$this->assertCount( 1, $composition['required_solutions'] );
		$this->assertCount( 1, $composition['required_purchased_solutions'] );
		// This should only be returned in an edit context.
		$this->assertArrayNotHasKey( 'required_purchased_solutions_ids', $composition );
		$this->assertCount( 0, $composition['required_manual_solutions'] );
		$this->assertArrayHasKey( 'composer_require', $composition );
		$this->assertArrayHasKey( 'editLink', $composition );
	}

	public function test_get_items_user_is_only_author() {
		// Get compositions authored by customer1.
		\wp_set_current_user( self::$user_ids['manager1'] );
		\wp_set_auth_cookie( self::$user_ids['manager1'], true, false );
		$compositions = local_rest_call( '/pixelgradelt_retailer/v1/compositions', 'GET', [] );

		$this->assertArrayNotHasKey( 'code', $compositions );
		$this->assertCount( 1, $compositions );
		$composition = reset( $compositions );
		// This should only be returned in an edit context.
		$this->assertArrayNotHasKey( 'id', $composition );
		// This should only be returned in an edit context.
		$this->assertArrayNotHasKey( 'author', $composition );
		$this->assertSame( 'Third', $composition['name'] );
		$this->assertSame( 'ready', $composition['status'] );
		$this->assertSame( 'bogushashid3', $composition['hashid'] );
		$this->assertCount( 1, $composition['users'] );
		// This should only be returned in an edit context.
		$this->assertArrayNotHasKey( 'userids', $composition );
		$this->assertCount( 1, $composition['required_solutions'] );
		$this->assertCount( 1, $composition['required_purchased_solutions'] );
		// This should only be returned in an edit context.
		$this->assertArrayNotHasKey( 'required_purchased_solutions_ids', $composition );
		$this->assertCount( 0, $composition['required_manual_solutions'] );
		$this->assertArrayHasKey( 'composer_require', $composition );
		$this->assertArrayHasKey( 'editLink', $composition );
	}

	public function test_get_items_user_is_owner() {
		// Get compositions customer2 is an user/owner of.
		\wp_set_current_user( self::$user_ids['customer2'] );
		\wp_set_auth_cookie( self::$user_ids['customer2'], true, false );
		$compositions = local_rest_call( '/pixelgradelt_retailer/v1/compositions', 'GET', [
			'userid' => self::$user_ids['customer2'],
		] );

		$this->assertArrayNotHasKey( 'code', $compositions );
		$this->assertCount( 2, $compositions );

		$found = array_search( 'bogushashid1', array_column( $compositions, 'hashid' ) );
		$this->assertNotFalse( $found );
		$composition = $compositions[ $found ];
		// This should only be returned in an edit context.
		$this->assertArrayNotHasKey( 'id', $composition );
		// This should only be returned in an edit context.
		$this->assertArrayNotHasKey( 'author', $composition );
		$this->assertSame( 'First', $composition['name'] );
		$this->assertSame( CompositionManager::DEFAULT_STATUS, $composition['status'] );
		$this->assertSame( 'bogushashid1', $composition['hashid'] );
		$this->assertCount( 2, $composition['users'] );
		// This should only be returned in an edit context.
		$this->assertArrayNotHasKey( 'userids', $composition );
		$this->assertCount( 2, $composition['required_solutions'] );
		$this->assertCount( 1, $composition['required_purchased_solutions'] );
		// This should only be returned in an edit context.
		$this->assertArrayNotHasKey( 'required_purchased_solutions_ids', $composition );
		$this->assertCount( 1, $composition['required_manual_solutions'] );
		$this->assertArrayHasKey( 'composer_require', $composition );
		$this->assertArrayHasKey( 'editLink', $composition );

		$found = array_search( 'bogushashid2', array_column( $compositions, 'hashid' ) );
		$this->assertNotFalse( $found );
		$composition = $compositions[ $found ];
		// This should only be returned in an edit context.
		$this->assertArrayNotHasKey( 'id', $composition );
		// This should only be returned in an edit context.
		$this->assertArrayNotHasKey( 'author', $composition );
		$this->assertSame( 'Second', $composition['name'] );
		$this->assertSame( 'ready', $composition['status'] );
		$this->assertSame( 'bogushashid2', $composition['hashid'] );
		$this->assertCount( 1, $composition['users'] );
		$this->assertSame( [ self::$user_ids['customer2'], ], array_keys( \wp_list_pluck( $composition['users'], 'id' ) ) );
		// This should only be returned in an edit context.
		$this->assertArrayNotHasKey( 'userids', $composition );
		$this->assertCount( 1, $composition['required_solutions'] );
		$this->assertCount( 1, $composition['required_purchased_solutions'] );
		// This should only be returned in an edit context.
		$this->assertArrayNotHasKey( 'required_purchased_solutions_ids', $composition );
		$this->assertCount( 0, $composition['required_manual_solutions'] );
		$this->assertArrayHasKey( 'composer_require', $composition );
		$this->assertArrayHasKey( 'editLink', $composition );
	}

	public function test_get_items_user_without_compositions() {
		// Get compositions of customer3.
		\wp_set_current_user( self::$user_ids['customer3'] );
		\wp_set_auth_cookie( self::$user_ids['customer3'], true, false );
		$compositions = local_rest_call( '/pixelgradelt_retailer/v1/compositions', 'GET', [] );

		$this->assertArrayNotHasKey( 'code', $compositions );
		$this->assertCount( 0, $compositions );
	}

	public function test_create_item_for_self() {
		\wp_set_current_user( self::$user_ids['customer4'] );
		\wp_set_auth_cookie( self::$user_ids['customer4'], true, false );

		$created_composition = local_rest_call( '/pixelgradelt_retailer/v1/compositions', 'POST', [
			'name'     => 'Composition for customer4',
			'keywords' => [ 'keyword41', 'keyword42', ],
		] );

		$this->assertArrayNotHasKey( 'code', $created_composition );
		$this->assertArrayHasKey( 'id', $created_composition );
		$this->assertSame( self::$user_ids['customer4'], $created_composition['author'] );
		$this->assertSame( 'Composition for customer4', $created_composition['name'] );
		$this->assertSame( CompositionManager::DEFAULT_STATUS, $created_composition['status'] );
		$this->assertArrayHasKey( 'hashid', $created_composition );
		$this->assertCount( 2, $created_composition['keywords'] );
		$this->assertSame( [ 'keyword41', 'keyword42', ], $created_composition['keywords'] );
		$this->assertCount( 1, $created_composition['users'] );
		$this->assertSame( [ self::$user_ids['customer4'], ], array_keys( $created_composition['users'] ) );
		$this->assertArrayHasKey( 'userids', $created_composition );
		$this->assertCount( 1, $created_composition['userids'] );
		$this->assertSame( [ self::$user_ids['customer4'], ], $created_composition['userids'] );
		$this->assertCount( 0, $created_composition['required_solutions'] );
		$this->assertCount( 0, $created_composition['required_purchased_solutions'] );
		$this->assertCount( 0, $created_composition['required_purchased_solutions_ids'] );
		$this->assertCount( 0, $created_composition['required_manual_solutions'] );
		$this->assertArrayHasKey( 'composer_require', $created_composition );
		$this->assertArrayHasKey( 'editLink', $created_composition );

		// Get the composition via the REST API.
		$compositions = local_rest_call( '/pixelgradelt_retailer/v1/compositions', 'GET', [
			'postId' => [ $created_composition['id'], ],
		] );

		$this->assertArrayNotHasKey( 'code', $compositions );
		$this->assertCount( 1, $compositions );
		$fetched_composition = reset( $compositions );
		// This should only be returned in an edit context.
		$this->assertArrayNotHasKey( 'id', $fetched_composition );
		// This should only be returned in an edit context.
		$this->assertArrayNotHasKey( 'author', $fetched_composition );
		$this->assertSame( $created_composition['name'], $fetched_composition['name'] );
		$this->assertSame( $created_composition['status'], $fetched_composition['status'] );
		$this->assertSame( $created_composition['hashid'], $fetched_composition['hashid'] );
		$this->assertSame( $created_composition['keywords'], $fetched_composition['keywords'] );
		$this->assertSame( $created_composition['users'], $fetched_composition['users'] );
		$this->assertArrayNotHasKey( 'userids', $fetched_composition );
		$this->assertSame( $created_composition['required_solutions'], $fetched_composition['required_solutions'] );
		$this->assertSame( $created_composition['required_purchased_solutions'], $fetched_composition['required_purchased_solutions'] );
		$this->assertSame( $created_composition['required_manual_solutions'], $fetched_composition['required_manual_solutions'] );
		$this->assertArrayNotHasKey( 'required_purchased_solutions_ids', $fetched_composition );
		$this->assertSame( $created_composition['composer_require'], $fetched_composition['composer_require'] );
		$this->assertSame( $created_composition['editLink'], $fetched_composition['editLink'] );
	}

	public function test_create_item_with_other_owners() {
		\wp_set_current_user( self::$user_ids['manager1'] );
		\wp_set_auth_cookie( self::$user_ids['manager1'], true, false );

		$created_composition = local_rest_call( '/pixelgradelt_retailer/v1/compositions', 'POST', [
			'name'     => 'Composition by manager1',
			'keywords' => [ 'keyword41', 'keyword42', ],
			'userids' => [ self::$user_ids['customer1'], self::$user_ids['customer2'], ]
		] );

		$this->assertArrayNotHasKey( 'code', $created_composition );
		$this->assertArrayHasKey( 'id', $created_composition );
		$this->assertSame( self::$user_ids['manager1'], $created_composition['author'] );
		$this->assertSame( 'Composition by manager1', $created_composition['name'] );
		$this->assertSame( CompositionManager::DEFAULT_STATUS, $created_composition['status'] );
		$this->assertArrayHasKey( 'hashid', $created_composition );
		$this->assertCount( 2, $created_composition['keywords'] );
		$this->assertSame( [ 'keyword41', 'keyword42', ], $created_composition['keywords'] );
		$this->assertCount( 2, $created_composition['users'] );
		$this->assertSame( [ self::$user_ids['customer1'], self::$user_ids['customer2'], ], array_keys( $created_composition['users'] ) );
		$this->assertArrayHasKey( 'userids', $created_composition );
		$this->assertCount( 2, $created_composition['userids'] );
		$this->assertEqualSets( [ self::$user_ids['customer1'], self::$user_ids['customer2'], ], $created_composition['userids'] );
		$this->assertCount( 0, $created_composition['required_solutions'] );
		$this->assertCount( 0, $created_composition['required_purchased_solutions'] );
		$this->assertCount( 0, $created_composition['required_purchased_solutions_ids'] );
		$this->assertCount( 0, $created_composition['required_manual_solutions'] );
		$this->assertArrayHasKey( 'composer_require', $created_composition );
		$this->assertArrayHasKey( 'editLink', $created_composition );

		// Get the composition via the REST API.
		$compositions = local_rest_call( '/pixelgradelt_retailer/v1/compositions', 'GET', [
			'postId' => [ $created_composition['id'], ],
		] );

		$this->assertArrayNotHasKey( 'code', $compositions );
		$this->assertCount( 1, $compositions );
		$fetched_composition = reset( $compositions );
		// This should only be returned in an edit context.
		$this->assertArrayNotHasKey( 'id', $fetched_composition );
		// This should only be returned in an edit context.
		$this->assertArrayNotHasKey( 'author', $fetched_composition );
		$this->assertSame( $created_composition['name'], $fetched_composition['name'] );
		$this->assertSame( $created_composition['status'], $fetched_composition['status'] );
		$this->assertSame( $created_composition['hashid'], $fetched_composition['hashid'] );
		$this->assertSame( $created_composition['keywords'], $fetched_composition['keywords'] );
		$this->assertSame( $created_composition['users'], $fetched_composition['users'] );
		$this->assertArrayNotHasKey( 'userids', $fetched_composition );
		$this->assertSame( $created_composition['required_solutions'], $fetched_composition['required_solutions'] );
		$this->assertSame( $created_composition['required_purchased_solutions'], $fetched_composition['required_purchased_solutions'] );
		$this->assertSame( $created_composition['required_manual_solutions'], $fetched_composition['required_manual_solutions'] );
		$this->assertArrayNotHasKey( 'required_purchased_solutions_ids', $fetched_composition );
		$this->assertSame( $created_composition['composer_require'], $fetched_composition['composer_require'] );
		$this->assertSame( $created_composition['editLink'], $fetched_composition['editLink'] );
	}

	public function test_create_item_no_user() {
		\wp_set_current_user( 0 );
		\wp_clear_auth_cookie();

		$created_composition = local_rest_call( '/pixelgradelt_retailer/v1/compositions', 'POST', [
			'name' => 'Composition for test',
		] );

		// Should receive error that one needs be logged it to create compositions.
		$this->assertArrayHasKey( 'code', $created_composition );
		$this->assertArrayHasKey( 'message', $created_composition );
		$this->assertArrayHasKey( 'data', $created_composition );
		$this->assertArrayHasKey( 'status', $created_composition['data'] );
		$this->assertSame( \rest_authorization_required_code(), $created_composition['data']['status'] );
		$this->assertSame( 'rest_cannot_create', $created_composition['code'] );
	}

	public function test_create_item_invalid_user_owner() {
		\wp_set_current_user( self::$user_ids['manager1'] );
		\wp_set_auth_cookie( self::$user_ids['manager1'], true, false );

		$created_composition = local_rest_call( '/pixelgradelt_retailer/v1/compositions', 'POST', [
			'name'    => 'Composition for other user',
			'userids' => [ 3478, ],
		] );

		// Should receive a new composition but with an invalid user.
		$this->assertArrayNotHasKey( 'code', $created_composition );
		$this->assertArrayHasKey( 'id', $created_composition );
		$this->assertSame( self::$user_ids['manager1'], $created_composition['author'] );
		$this->assertSame( 'Composition for other user', $created_composition['name'] );
		$this->assertSame( CompositionManager::DEFAULT_STATUS, $created_composition['status'] );
		$this->assertArrayHasKey( 'hashid', $created_composition );
		$this->assertCount( 0, $created_composition['keywords'] );
		$this->assertCount( 1, $created_composition['users'] );
		$this->assertSame( [ 3478, ], array_keys( $created_composition['users'] ) );
		$this->assertSame( 'invalid', $created_composition['users'][3478]['status'] );
		$this->assertArrayHasKey( 'userids', $created_composition );
		$this->assertCount( 1, $created_composition['userids'] );
		$this->assertSame( [ 3478, ], $created_composition['userids'] );
		$this->assertCount( 0, $created_composition['required_solutions'] );
		$this->assertCount( 0, $created_composition['required_purchased_solutions'] );
		$this->assertCount( 0, $created_composition['required_purchased_solutions_ids'] );
		$this->assertCount( 0, $created_composition['required_manual_solutions'] );
		$this->assertArrayHasKey( 'composer_require', $created_composition );
		$this->assertArrayHasKey( 'editLink', $created_composition );
	}

	public function test_create_item_user_without_caps() {
		\wp_set_current_user( self::$user_ids['subscriber'] );
		\wp_set_auth_cookie( self::$user_ids['subscriber'], true, false );

		$created_composition = local_rest_call( '/pixelgradelt_retailer/v1/compositions', 'POST', [
			'name' => 'Composition for subscriber',
		] );

		// Should receive error that one needs be logged it to create compositions.
		$this->assertArrayHasKey( 'code', $created_composition );
		$this->assertArrayHasKey( 'message', $created_composition );
		$this->assertArrayHasKey( 'data', $created_composition );
		$this->assertArrayHasKey( 'status', $created_composition['data'] );
		$this->assertSame( \rest_authorization_required_code(), $created_composition['data']['status'] );
		$this->assertSame( 'rest_cannot_create', $created_composition['code'] );
	}

	public function test_get_item() {
		\wp_set_current_user( self::$user_ids['manager1'] );
		\wp_set_auth_cookie( self::$user_ids['manager1'], true, false );

		$created_composition = local_rest_call( '/pixelgradelt_retailer/v1/compositions', 'POST', [
			'name' => 'Composition',
			'keywords' => [ 'keyword41', 'keyword42', ],
			'userids' => [ self::$user_ids['customer1'], self::$user_ids['customer2'], ]
		] );

		$this->assertArrayNotHasKey( 'code', $created_composition );
		$this->assertArrayHasKey( 'id', $created_composition );
		$this->assertSame( self::$user_ids['manager1'], $created_composition['author'] );
		$this->assertArrayHasKey( 'hashid', $created_composition );

		// Get the composition via the REST API.
		$fetched_composition = local_rest_call( '/pixelgradelt_retailer/v1/compositions/' . $created_composition['hashid'], 'GET', [] );

		$this->assertArrayNotHasKey( 'code', $fetched_composition );
		// This should only be returned in an edit context.
		$this->assertArrayNotHasKey( 'id', $fetched_composition );
		// This should only be returned in an edit context.
		$this->assertArrayNotHasKey( 'author', $fetched_composition );
		$this->assertSame( $created_composition['name'], $fetched_composition['name'] );
		$this->assertSame( $created_composition['status'], $fetched_composition['status'] );
		$this->assertSame( $created_composition['hashid'], $fetched_composition['hashid'] );
		$this->assertSame( $created_composition['keywords'], $fetched_composition['keywords'] );
		$this->assertSame( $created_composition['users'], $fetched_composition['users'] );
		$this->assertArrayNotHasKey( 'userids', $fetched_composition );
		$this->assertSame( $created_composition['required_solutions'], $fetched_composition['required_solutions'] );
		$this->assertSame( $created_composition['required_purchased_solutions'], $fetched_composition['required_purchased_solutions'] );
		$this->assertSame( $created_composition['required_manual_solutions'], $fetched_composition['required_manual_solutions'] );
		$this->assertArrayNotHasKey( 'required_purchased_solutions_ids', $fetched_composition );
		$this->assertSame( $created_composition['composer_require'], $fetched_composition['composer_require'] );
		$this->assertSame( $created_composition['editLink'], $fetched_composition['editLink'] );
	}

	public function test_edit_item() {
		\wp_set_current_user( self::$user_ids['manager1'] );
		\wp_set_auth_cookie( self::$user_ids['manager1'], true, false );

		$created_composition = local_rest_call( '/pixelgradelt_retailer/v1/compositions', 'POST', [
			'name' => 'Created composition title',
			'keywords' => [ 'keyword53', 'keyword54', ],
			'userids' => [ self::$user_ids['customer1'], self::$user_ids['customer2'], ]
		] );

		$this->assertArrayNotHasKey( 'code', $created_composition );
		$this->assertArrayHasKey( 'id', $created_composition );
		$this->assertSame( self::$user_ids['manager1'], $created_composition['author'] );
		$this->assertArrayHasKey( 'hashid', $created_composition );

		// Get the composition via the REST API.
		$fetched_composition = local_rest_call( '/pixelgradelt_retailer/v1/compositions/' . $created_composition['hashid'], 'GET', [] );

		$this->assertArrayNotHasKey( 'code', $fetched_composition );
		// This should only be returned in an edit context.
		$this->assertArrayNotHasKey( 'id', $fetched_composition );
		// This should only be returned in an edit context.
		$this->assertArrayNotHasKey( 'author', $fetched_composition );
		$this->assertSame( $created_composition['name'], $fetched_composition['name'] );
		$this->assertSame( $created_composition['status'], $fetched_composition['status'] );
		$this->assertSame( $created_composition['hashid'], $fetched_composition['hashid'] );
		$this->assertEqualSets( $created_composition['keywords'], $fetched_composition['keywords'] );
		$this->assertSame( $created_composition['users'], $fetched_composition['users'] );
		$this->assertArrayNotHasKey( 'userids', $fetched_composition );
		$this->assertSame( $created_composition['required_solutions'], $fetched_composition['required_solutions'] );
		$this->assertSame( $created_composition['required_purchased_solutions'], $fetched_composition['required_purchased_solutions'] );
		$this->assertSame( $created_composition['required_manual_solutions'], $fetched_composition['required_manual_solutions'] );
		$this->assertArrayNotHasKey( 'required_purchased_solutions_ids', $fetched_composition );
		$this->assertSame( $created_composition['composer_require'], $fetched_composition['composer_require'] );
		$this->assertSame( $created_composition['editLink'], $fetched_composition['editLink'] );

		// Edit the composition.
		$expected = [
			'name' => 'Edited composition title',
			'status' => 'ready',
			'keywords' => [ 'keyword53', 'keyword54', 'keyword55', ],
			'userids' => [ self::$user_ids['customer1'], self::$user_ids['customer2'], self::$user_ids['customer4'], ],
			'required_purchased_solutions_ids' => [ self::$purchased_solution_ids['customer1_ecommerce'], self::$purchased_solution_ids['customer2_portfolio'], ],
		];
		$edited_composition = local_rest_call( '/pixelgradelt_retailer/v1/compositions/' . $created_composition['hashid'], 'POST', $expected );

		$this->assertArrayNotHasKey( 'code', $edited_composition );
		$this->assertSame( $created_composition['id'], $edited_composition['id'] );
		$this->assertSame( $created_composition['author'], $edited_composition['author'] );
		$this->assertSame( $expected['name'], $edited_composition['name'] );
		$this->assertSame( $expected['status'], $edited_composition['status'] );
		$this->assertSame( $created_composition['hashid'], $edited_composition['hashid'] );
		$this->assertEqualSets( $expected['keywords'], $edited_composition['keywords'] );
		$this->assertEqualSets( $expected['userids'], array_keys( $edited_composition['users'] ) );
		$this->assertEqualSets( $expected['userids'], $edited_composition['userids'] );
		$this->assertCount( 2, $edited_composition['required_solutions'] );
		$this->assertCount( 2, $edited_composition['required_purchased_solutions'] );
		$this->assertSame( $edited_composition['required_solutions'], $edited_composition['required_purchased_solutions'] );
		$this->assertEqualSets( $expected['required_purchased_solutions_ids'], $edited_composition['required_purchased_solutions_ids'] );
		$this->assertSame( $created_composition['required_manual_solutions'], $edited_composition['required_manual_solutions'] );
		$this->assertSame( $created_composition['composer_require'], $edited_composition['composer_require'] );
		$this->assertSame( $created_composition['editLink'], $edited_composition['editLink'] );

		// Edit the composition with an invalid status. It should be rejected.
		$expected = [
			'status' => 'bogus_status',
		];
		$edited_composition = local_rest_call( '/pixelgradelt_retailer/v1/compositions/' . $created_composition['hashid'], 'POST', $expected );

		$this->assertArrayNotHasKey( 'code', $edited_composition );
		$this->assertNotEquals( $expected['status'], $edited_composition['status'] );
	}

	public function test_edit_item_failure() {
		\wp_set_current_user( self::$user_ids['manager1'] );
		\wp_set_auth_cookie( self::$user_ids['manager1'], true, false );

		// Try to edit a composition with invalid hashid.
		$expected = [
			'name' => 'Edited composition title',
		];
		$edited_composition = local_rest_call( '/pixelgradelt_retailer/v1/compositions/' . 'bogusHashid123', 'POST', $expected );

		// Should receive error.
		$this->assertArrayHasKey( 'code', $edited_composition );
		$this->assertArrayHasKey( 'message', $edited_composition );
		$this->assertArrayHasKey( 'data', $edited_composition );
		$this->assertArrayHasKey( 'status', $edited_composition['data'] );
		$this->assertSame( \rest_authorization_required_code(), $edited_composition['data']['status'] );
		$this->assertSame( 'rest_cannot_edit', $edited_composition['code'] );

		// Try to edit a composition with hashid that doesn't respect the pattern (`[A-Za-z0-9]*`).
		$expected = [
			'name' => 'Edited composition title',
		];
		$edited_composition = local_rest_call( '/pixelgradelt_retailer/v1/compositions/' . 'bogus_hashid-123', 'POST', $expected );

		// Should receive error that the route is not found.
		$this->assertArrayHasKey( 'code', $edited_composition );
		$this->assertArrayHasKey( 'message', $edited_composition );
		$this->assertArrayHasKey( 'data', $edited_composition );
		$this->assertArrayHasKey( 'status', $edited_composition['data'] );
		$this->assertSame( HTTP::NOT_FOUND, $edited_composition['data']['status'] );
		$this->assertSame( 'rest_no_route', $edited_composition['code'] );
	}

	public function test_delete_item() {
		\wp_set_current_user( self::$user_ids['manager1'] );
		\wp_set_auth_cookie( self::$user_ids['manager1'], true, false );

		$created_composition = local_rest_call( '/pixelgradelt_retailer/v1/compositions', 'POST', [
			'name' => 'Composition3',
			'keywords' => [ 'keyword53', 'keyword54', ],
			'userids' => [ self::$user_ids['customer1'], self::$user_ids['customer2'], ]
		] );

		$this->assertArrayNotHasKey( 'code', $created_composition );
		$this->assertArrayHasKey( 'id', $created_composition );
		$this->assertSame( self::$user_ids['manager1'], $created_composition['author'] );
		$this->assertArrayHasKey( 'hashid', $created_composition );

		// Delete the newly created composition.
		$deleted_composition_response = local_rest_call( '/pixelgradelt_retailer/v1/compositions/' . $created_composition['hashid'], 'DELETE', [] );

		$this->assertArrayNotHasKey( 'code', $deleted_composition_response );
		$this->assertSame( true, $deleted_composition_response['deleted'] );
		$this->assertArrayHasKey( 'previous', $deleted_composition_response );
		$this->assertSame( $created_composition['hashid'], $deleted_composition_response['previous']['hashid'] );
		// Test that the post was trashed.
		$this->assertSame( 'trash', get_post_status( $created_composition['id'] ) );

		// Try to delete it again.
		$deleted_composition_response = local_rest_call( '/pixelgradelt_retailer/v1/compositions/' . $created_composition['hashid'], 'DELETE', [] );

		// Should receive error.
		$this->assertArrayHasKey( 'code', $deleted_composition_response );
		$this->assertArrayHasKey( 'message', $deleted_composition_response );
		$this->assertArrayHasKey( 'data', $deleted_composition_response );
		$this->assertArrayHasKey( 'status', $deleted_composition_response['data'] );
		$this->assertSame( \rest_authorization_required_code(), $deleted_composition_response['data']['status'] );
		$this->assertSame( 'rest_cannot_delete', $deleted_composition_response['code'] );

	}

	public function test_delete_item_by_owners() {
		\wp_set_current_user( self::$user_ids['manager1'] );
		\wp_set_auth_cookie( self::$user_ids['manager1'], true, false );

		$created_composition = local_rest_call( '/pixelgradelt_retailer/v1/compositions', 'POST', [
			'name' => 'Composition31',
			'keywords' => [ 'keyword53', 'keyword54', ],
			'userids' => [ self::$user_ids['customer1'], self::$user_ids['customer2'], ]
		] );

		$this->assertArrayNotHasKey( 'code', $created_composition );
		$this->assertArrayHasKey( 'id', $created_composition );
		$this->assertSame( self::$user_ids['manager1'], $created_composition['author'] );
		$this->assertArrayHasKey( 'hashid', $created_composition );

		// Switch to one of the owners
		\wp_set_current_user( self::$user_ids['customer1'] );
		\wp_set_auth_cookie( self::$user_ids['customer1'], true, false );

		// Delete the newly created composition.
		$deleted_composition_response = local_rest_call( '/pixelgradelt_retailer/v1/compositions/' . $created_composition['hashid'], 'DELETE', [] );

		// The deletion should fail since only composition authors can delete a composition.
		// Users/Owners can edit it, but not delete it.
		$this->assertArrayHasKey( 'code', $deleted_composition_response );
		$this->assertArrayHasKey( 'message', $deleted_composition_response );
		$this->assertArrayHasKey( 'data', $deleted_composition_response );
		$this->assertArrayHasKey( 'status', $deleted_composition_response['data'] );
		$this->assertSame( \rest_authorization_required_code(), $deleted_composition_response['data']['status'] );
		$this->assertSame( 'rest_cannot_delete', $deleted_composition_response['code'] );
	}

	public function test_encrypt_ltdetails() {

	}

	public function test_check_ltdetails() {

	}

	public function test_instructions_to_update_composition() {

	}
}
