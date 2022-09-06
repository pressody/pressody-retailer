<?php
/*
 * This file is part of a Pressody module.
 *
 * This Pressody module is free software: you can redistribute it and/or modify it under the terms of the
 * GNU General Public License as published by the Free Software Foundation, either version 2 of the License,
 * or (at your option) any later version.
 *
 * This Pressody module is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY;
 * without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along with this Pressody module.
 * If not, see <https://www.gnu.org/licenses/>.
 *
 * Copyright (c) 2021, 2022 Vlad Olaru (vlad@thinkwritecode.com)
 */

declare ( strict_types=1 );

namespace Pressody\Retailer\Tests\Integration\REST;

use Pressody\Retailer\Capabilities;
use Pressody\Retailer\CompositionManager;
use Pressody\Retailer\SolutionType\SolutionTypes;
use Pressody\Retailer\Tests\Framework\PHPUnitUtil;
use Pressody\Retailer\Tests\Integration\TestCase;

use Psr\Container\ContainerInterface;
use function Pressody\Retailer\local_rest_call;
use function Pressody\Retailer\plugin;

use WP_Http as HTTP;

class CompositionsControllerTest extends TestCase {
	protected static $solutions_post_data;
	protected static $solutions_dep_post_data;
	protected static $solution_ids;
	protected static $compositions_post_data;
	protected static $composition_ids;
	protected static $user_ids;
	protected static $purchased_solution_ids;
	protected static $container;

	/**
	 * @param \WP_UnitTest_Factory $factory
	 */
	public static function wpSetUpBeforeClass( $factory ) {
		// Make sure that the user roles and capabilities are properly registered.
		Capabilities::register();
		self::_flush_roles();

		// We need to set a user with sufficient privileges to create packages and edit them.
		// Use the site super admin.
		wp_set_current_user( 1 );

		/** @var ContainerInterface $container */
		self::$container = plugin()->get_container();

		/**
		 * CREATE SOLUTIONS IN THE DB.
		 */

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

		// Create the test pdsolutions posts that will be dependencies to other posts that we test.
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
				'_solution_required_parts|package_name|0|0|value'  => 'pressody-records/part_yet-another',
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
			'user_email' => 'customer1@pd-retailer.local',
			'first_name' => 'Customer1',
			'last_name'  => 'PDRetailer',
			'role'       => Capabilities::CUSTOMER_USER_ROLE,
		] );

		self::$user_ids['customer2'] = $factory->user->create( [
			'user_pass'  => 'pass',
			'user_login' => 'customer2',
			'user_email' => 'customer2@pd-retailer.local',
			'first_name' => 'Customer2',
			'last_name'  => 'PDRetailer',
			'role'       => Capabilities::CUSTOMER_USER_ROLE,
		] );

		self::$user_ids['customer3'] = $factory->user->create( [
			'user_pass'  => 'pass',
			'user_login' => 'customer3',
			'user_email' => 'customer3@pd-retailer.local',
			'first_name' => 'Customer3',
			'last_name'  => 'PDRetailer',
			'role'       => Capabilities::CUSTOMER_USER_ROLE,
		] );

		self::$user_ids['customer4'] = $factory->user->create( [
			'user_pass'  => 'pass',
			'user_login' => 'customer4',
			'user_email' => 'customer4@pd-retailer.local',
			'first_name' => 'Customer4',
			'last_name'  => 'PDRetailer',
			'role'       => Capabilities::CUSTOMER_USER_ROLE,
		] );

		self::$user_ids['customer5'] = $factory->user->create( [
			'user_pass'  => 'pass',
			'user_login' => 'customer5',
			'user_email' => 'customer5@pd-retailer.local',
			'first_name' => 'Customer5',
			'last_name'  => 'PDRetailer',
			'role'       => Capabilities::CUSTOMER_USER_ROLE,
		] );

		self::$user_ids['subscriber'] = $factory->user->create( [
			'user_pass'  => 'pass',
			'user_login' => 'subscriber',
			'user_email' => 'subscriber@pd-retailer.local',
			'first_name' => 'Subscriber',
			'last_name'  => 'PDRetailer',
			'role'       => 'subscriber',
		] );

		self::$user_ids['manager1'] = $factory->user->create( [
			'user_pass'  => 'pass',
			'user_login' => 'manager1',
			'user_email' => 'manager1@pd-retailer.local',
			'first_name' => 'Manager1',
			'last_name'  => 'PDRetailer',
			'role'       => 'administrator',
		] );

		self::$user_ids['client1'] = $factory->user->create( [
			'user_pass'  => 'pass',
			'user_login' => 'client1',
			'user_email' => 'client1@pd-retailer.local',
			'first_name' => 'Client1',
			'last_name'  => 'PDRetailer',
			'role'       => Capabilities::CLIENT_USER_ROLE,
		] );

		/**
		 * CREATE PURCHASED SOLUTIONS.
		 */

		self::$purchased_solution_ids = [];

		self::$purchased_solution_ids['customer1_ecommerce'] = self::$container['purchased_solution.manager']->add_purchased_solution( [
			'status'      => 'ready',
			'solution_id' => self::$solution_ids['ecommerce'],
			'user_id'     => self::$user_ids['customer1'],
		] );

		self::$purchased_solution_ids['customer1_presentation'] = self::$container['purchased_solution.manager']->add_purchased_solution( [
			'status'      => 'ready',
			'solution_id' => self::$solution_ids['presentation'],
			'user_id'     => self::$user_ids['customer1'],
		] );

		self::$purchased_solution_ids['customer2_portfolio'] = self::$container['purchased_solution.manager']->add_purchased_solution( [
			'status'      => 'ready',
			'solution_id' => self::$solution_ids['portfolio'],
			'user_id'     => self::$user_ids['customer2'],
		] );

		self::$purchased_solution_ids['customer5_portfolio'] = self::$container['purchased_solution.manager']->add_purchased_solution( [
			'status'      => 'ready',
			'solution_id' => self::$solution_ids['portfolio'],
			'user_id'     => self::$user_ids['customer5'],
		] );


		/**
		 * CREATE COMPOSITIONS IN THE DB, WITH THE SOLUTIONS.
		 */

		// Register pdcomposition post type
		$register_post_type = PHPUnitUtil::getProtectedMethod( self::$container['hooks.composition_post_type'], 'register_post_type' );
		$register_post_type->invoke( self::$container['hooks.composition_post_type'] );

		// Register and populate the taxonomies.
		$register_composition_keyword_taxonomy = PHPUnitUtil::getProtectedMethod( self::$container['hooks.composition_post_type'], 'register_composition_keyword_taxonomy' );
		$register_composition_keyword_taxonomy->invoke( self::$container['hooks.composition_post_type'] );

		self::$composition_ids = [];

		self::$compositions_post_data = [];

		self::$compositions_post_data['first'] = [
			'post_author' => self::$user_ids['customer1'],
			'post_title'  => 'First',
			'post_status' => 'private',
			'post_name'   => 'first',
			'post_type'   => self::$container['composition.manager']::POST_TYPE,
			'tax_input'   => [
				self::$container['composition.manager']::KEYWORD_TAXONOMY => 'keyword1, keyword2, keyword3',
			],
			'meta_input'  => [
				'_composition_status'                                        => CompositionManager::DEFAUPD_STATUS,
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
				'_composition_required_manual_solutions|||0|value'       => '_',
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
			'post_type'   => self::$container['composition.manager']::POST_TYPE,
			'tax_input'   => [
				self::$container['composition.manager']::KEYWORD_TAXONOMY => 'keyword4, keyword5',
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
			'post_type'   => self::$container['composition.manager']::POST_TYPE,
			'tax_input'   => [
				self::$container['composition.manager']::KEYWORD_TAXONOMY => 'keyword43, keyword53',
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

	public function test_get_items_no_user() {
		\wp_set_current_user( 0 );
		\wp_clear_auth_cookie();

		// Get compositions.
		$compositions = local_rest_call( '/pressody_retailer/v1/compositions', 'GET', [] );

		// Should receive error that one needs be logged it to view compositions.
		$this->assertArrayHasKey( 'code', $compositions );
		$this->assertArrayHasKey( 'message', $compositions );
		$this->assertArrayHasKey( 'data', $compositions );
		$this->assertArrayHasKey( 'status', $compositions['data'] );
		$this->assertSame( \rest_authorization_required_code(), $compositions['data']['status'] );
		$this->assertSame( 'pressody_retailer_rest_cannot_read', $compositions['code'] );
	}

	public function test_get_items_user_without_caps() {
		\wp_set_current_user( self::$user_ids['subscriber'] );
		\wp_set_auth_cookie( self::$user_ids['subscriber'], true, false );

		// Get compositions.
		$compositions = local_rest_call( '/pressody_retailer/v1/compositions', 'GET', [] );

		// Should receive error that one needs be logged it to view compositions.
		$this->assertArrayHasKey( 'code', $compositions );
		$this->assertArrayHasKey( 'message', $compositions );
		$this->assertArrayHasKey( 'data', $compositions );
		$this->assertArrayHasKey( 'status', $compositions['data'] );
		$this->assertSame( \rest_authorization_required_code(), $compositions['data']['status'] );
		$this->assertSame( 'pressody_retailer_rest_cannot_read', $compositions['code'] );
	}

	public function test_get_items_user_is_author() {
		// Get compositions authored by customer1.
		\wp_set_current_user( self::$user_ids['customer1'] );
		\wp_set_auth_cookie( self::$user_ids['customer1'], true, false );
		$compositions = local_rest_call( '/pressody_retailer/v1/compositions', 'GET', [] );

		$this->assertArrayNotHasKey( 'code', $compositions );
		$this->assertCount( 1, $compositions );
		$composition = reset( $compositions );
		// This should only be returned in an edit context.
		$this->assertArrayNotHasKey( 'id', $composition );
		// This should only be returned in an edit context.
		$this->assertArrayNotHasKey( 'author', $composition );
		$this->assertSame( 'First', $composition['name'] );
		$this->assertSame( CompositionManager::DEFAUPD_STATUS, $composition['status'] );
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

		// Get compositions authored by customer2.
		\wp_set_current_user( self::$user_ids['customer2'] );
		\wp_set_auth_cookie( self::$user_ids['customer2'], true, false );
		$compositions = local_rest_call( '/pressody_retailer/v1/compositions', 'GET', [] );

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
		$this->assertSame( [ self::$user_ids['customer2'], ], array_values( \wp_list_pluck( $composition['users'], 'id' ) ) );
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
		$compositions = local_rest_call( '/pressody_retailer/v1/compositions', 'GET', [] );

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
		$compositions = local_rest_call( '/pressody_retailer/v1/compositions', 'GET', [
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
		$this->assertSame( CompositionManager::DEFAUPD_STATUS, $composition['status'] );
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
		$this->assertSame( [ self::$user_ids['customer2'], ], array_values( \wp_list_pluck( $composition['users'], 'id' ) ) );
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
		$compositions = local_rest_call( '/pressody_retailer/v1/compositions', 'GET', [] );

		$this->assertArrayNotHasKey( 'code', $compositions );
		$this->assertCount( 0, $compositions );
	}

	public function test_create_item_for_self() {
		\wp_set_current_user( self::$user_ids['customer4'] );
		\wp_set_auth_cookie( self::$user_ids['customer4'], true, false );

		$created_composition = local_rest_call( '/pressody_retailer/v1/compositions', 'POST', [
			'name'     => 'Composition for customer4',
			'keywords' => [ 'keyword41', 'keyword42', ],
		] );

		$this->assertArrayNotHasKey( 'code', $created_composition );
		$this->assertArrayHasKey( 'id', $created_composition );
		$this->assertSame( self::$user_ids['customer4'], $created_composition['author'] );
		$this->assertSame( 'Composition for customer4', $created_composition['name'] );
		$this->assertSame( CompositionManager::DEFAUPD_STATUS, $created_composition['status'] );
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
		$compositions = local_rest_call( '/pressody_retailer/v1/compositions', 'GET', [
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

		$created_composition = local_rest_call( '/pressody_retailer/v1/compositions', 'POST', [
			'name'     => 'Composition by manager1',
			'keywords' => [ 'keyword41', 'keyword42', ],
			'userids'  => [ self::$user_ids['customer1'], self::$user_ids['customer2'], ],
		] );

		$this->assertArrayNotHasKey( 'code', $created_composition );
		$this->assertArrayHasKey( 'id', $created_composition );
		$this->assertSame( self::$user_ids['manager1'], $created_composition['author'] );
		$this->assertSame( 'Composition by manager1', $created_composition['name'] );
		$this->assertSame( CompositionManager::DEFAUPD_STATUS, $created_composition['status'] );
		$this->assertArrayHasKey( 'hashid', $created_composition );
		$this->assertCount( 2, $created_composition['keywords'] );
		$this->assertSame( [ 'keyword41', 'keyword42', ], $created_composition['keywords'] );
		$this->assertCount( 2, $created_composition['users'] );
		$this->assertSame( [
			self::$user_ids['customer1'],
			self::$user_ids['customer2'],
		], array_keys( $created_composition['users'] ) );
		$this->assertArrayHasKey( 'userids', $created_composition );
		$this->assertCount( 2, $created_composition['userids'] );
		$this->assertEqualSets( [
			self::$user_ids['customer1'],
			self::$user_ids['customer2'],
		], $created_composition['userids'] );
		$this->assertCount( 0, $created_composition['required_solutions'] );
		$this->assertCount( 0, $created_composition['required_purchased_solutions'] );
		$this->assertCount( 0, $created_composition['required_purchased_solutions_ids'] );
		$this->assertCount( 0, $created_composition['required_manual_solutions'] );
		$this->assertArrayHasKey( 'composer_require', $created_composition );
		$this->assertArrayHasKey( 'editLink', $created_composition );

		// Get the composition via the REST API.
		$compositions = local_rest_call( '/pressody_retailer/v1/compositions', 'GET', [
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

		$created_composition = local_rest_call( '/pressody_retailer/v1/compositions', 'POST', [
			'name' => 'Composition for test',
		] );

		// Should receive error that one needs be logged it to create compositions.
		$this->assertArrayHasKey( 'code', $created_composition );
		$this->assertArrayHasKey( 'message', $created_composition );
		$this->assertArrayHasKey( 'data', $created_composition );
		$this->assertArrayHasKey( 'status', $created_composition['data'] );
		$this->assertSame( \rest_authorization_required_code(), $created_composition['data']['status'] );
		$this->assertSame( 'pressody_retailer_rest_cannot_create', $created_composition['code'] );
	}

	public function test_create_item_invalid_user_owner() {
		\wp_set_current_user( self::$user_ids['manager1'] );
		\wp_set_auth_cookie( self::$user_ids['manager1'], true, false );

		$created_composition = local_rest_call( '/pressody_retailer/v1/compositions', 'POST', [
			'name'    => 'Composition for other user',
			'userids' => [ 3478, ],
		] );

		// Should receive a new composition but with an invalid user.
		$this->assertArrayNotHasKey( 'code', $created_composition );
		$this->assertArrayHasKey( 'id', $created_composition );
		$this->assertSame( self::$user_ids['manager1'], $created_composition['author'] );
		$this->assertSame( 'Composition for other user', $created_composition['name'] );
		$this->assertSame( CompositionManager::DEFAUPD_STATUS, $created_composition['status'] );
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

		$created_composition = local_rest_call( '/pressody_retailer/v1/compositions', 'POST', [
			'name' => 'Composition for subscriber',
		] );

		// Should receive error that one needs be logged it to create compositions.
		$this->assertArrayHasKey( 'code', $created_composition );
		$this->assertArrayHasKey( 'message', $created_composition );
		$this->assertArrayHasKey( 'data', $created_composition );
		$this->assertArrayHasKey( 'status', $created_composition['data'] );
		$this->assertSame( \rest_authorization_required_code(), $created_composition['data']['status'] );
		$this->assertSame( 'pressody_retailer_rest_cannot_create', $created_composition['code'] );
	}

	public function test_get_item() {
		\wp_set_current_user( self::$user_ids['manager1'] );
		\wp_set_auth_cookie( self::$user_ids['manager1'], true, false );

		$created_composition = local_rest_call( '/pressody_retailer/v1/compositions', 'POST', [
			'name'     => 'Composition',
			'keywords' => [ 'keyword41', 'keyword42', ],
			'userids'  => [ self::$user_ids['customer1'], self::$user_ids['customer2'], ],
		] );

		$this->assertArrayNotHasKey( 'code', $created_composition );
		$this->assertArrayHasKey( 'id', $created_composition );
		$this->assertSame( self::$user_ids['manager1'], $created_composition['author'] );
		$this->assertArrayHasKey( 'hashid', $created_composition );

		// Get the composition via the REST API.
		$fetched_composition = local_rest_call( '/pressody_retailer/v1/compositions/' . $created_composition['hashid'], 'GET', [] );

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

	public function test_edit_item_by_authors() {
		\wp_set_current_user( self::$user_ids['customer4'] );
		\wp_set_auth_cookie( self::$user_ids['customer4'], true, false );

		$created_composition = local_rest_call( '/pressody_retailer/v1/compositions', 'POST', [
			'name'     => 'Created composition title',
			'keywords' => [ 'keyword53', 'keyword54', ],
			'userids'  => [ self::$user_ids['customer1'], self::$user_ids['customer2'], ],
		] );

		$this->assertArrayNotHasKey( 'code', $created_composition );
		$this->assertArrayHasKey( 'id', $created_composition );
		$this->assertSame( self::$user_ids['customer4'], $created_composition['author'] );
		$this->assertArrayHasKey( 'hashid', $created_composition );

		// Get the composition via the REST API.
		$fetched_composition = local_rest_call( '/pressody_retailer/v1/compositions/' . $created_composition['hashid'], 'GET', [] );

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
		$expected           = [
			'author'                           => self::$user_ids['customer1'],
			'name'                             => 'Edited composition title',
			'keywords'                         => [ 'keyword53', 'keyword54', 'keyword55', ],
			'userids'                          => [
				self::$user_ids['customer1'],
				self::$user_ids['customer2'],
				self::$user_ids['customer4'],
			],
			'required_purchased_solutions_ids' => [
				self::$purchased_solution_ids['customer1_ecommerce'],
				self::$purchased_solution_ids['customer2_portfolio'],
			],
		];
		$edited_composition = local_rest_call( '/pressody_retailer/v1/compositions/' . $created_composition['hashid'], 'POST', $expected );

		$this->assertArrayNotHasKey( 'code', $edited_composition );
		$this->assertSame( $created_composition['id'], $edited_composition['id'] );
		$this->assertSame( $expected['author'], $edited_composition['author'] );
		$this->assertSame( $expected['name'], $edited_composition['name'] );
		$this->assertSame( $created_composition['status'], $edited_composition['status'] );
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

		// Changing the composition status should fail since only compositions managers can do that.
		// Switch to the new composition author.
		\wp_set_current_user( self::$user_ids['customer1'] );
		\wp_set_auth_cookie( self::$user_ids['customer1'], true, false );
		$expected           = [
			'status' => 'some_status',
		];
		$edited_composition = local_rest_call( '/pressody_retailer/v1/compositions/' . $created_composition['hashid'], 'POST', $expected );

		// Should receive error that only composition managers can modify the status.
		$this->assertArrayHasKey( 'code', $edited_composition );
		$this->assertArrayHasKey( 'message', $edited_composition );
		$this->assertArrayHasKey( 'data', $edited_composition );
		$this->assertArrayHasKey( 'status', $edited_composition['data'] );
		$this->assertSame( \rest_authorization_required_code(), $edited_composition['data']['status'] );
		$this->assertSame( 'pressody_retailer_rest_cannot_edit', $edited_composition['code'] );
	}

	public function test_edit_item_by_managers() {
		\wp_set_current_user( self::$user_ids['manager1'] );
		\wp_set_auth_cookie( self::$user_ids['manager1'], true, false );

		$created_composition = local_rest_call( '/pressody_retailer/v1/compositions', 'POST', [
			'name'     => 'Created composition title',
			'keywords' => [ 'keyword53', 'keyword54', ],
			'userids'  => [ self::$user_ids['customer1'], self::$user_ids['customer2'], ],
		] );

		$this->assertArrayNotHasKey( 'code', $created_composition );
		$this->assertArrayHasKey( 'id', $created_composition );
		$this->assertSame( self::$user_ids['manager1'], $created_composition['author'] );
		$this->assertArrayHasKey( 'hashid', $created_composition );

		// Get the composition via the REST API.
		$fetched_composition = local_rest_call( '/pressody_retailer/v1/compositions/' . $created_composition['hashid'], 'GET', [] );

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
		$expected           = [
			'author'                           => self::$user_ids['customer4'],
			'name'                             => 'Edited composition title',
			'status'                           => 'ready',
			'keywords'                         => [ 'keyword53', 'keyword54', 'keyword55', ],
			'userids'                          => [
				self::$user_ids['customer1'],
				self::$user_ids['customer2'],
				self::$user_ids['customer4'],
			],
			'required_purchased_solutions_ids' => [
				self::$purchased_solution_ids['customer1_ecommerce'],
				self::$purchased_solution_ids['customer2_portfolio'],
			],
		];
		$edited_composition = local_rest_call( '/pressody_retailer/v1/compositions/' . $created_composition['hashid'], 'POST', $expected );

		$this->assertArrayNotHasKey( 'code', $edited_composition );
		$this->assertSame( $created_composition['id'], $edited_composition['id'] );
		$this->assertSame( $expected['author'], $edited_composition['author'] );
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

		// Edit the composition with an invalid status. It should be rejected and the composition left untouched.
		$expected           = [
			'status' => 'bogus_status',
		];
		$edited_composition = local_rest_call( '/pressody_retailer/v1/compositions/' . $created_composition['hashid'], 'POST', $expected );

		$this->assertArrayNotHasKey( 'code', $edited_composition );
		$this->assertNotEquals( $expected['status'], $edited_composition['status'] );
	}

	public function test_edit_item_by_owners() {
		\wp_set_current_user( self::$user_ids['manager1'] );
		\wp_set_auth_cookie( self::$user_ids['manager1'], true, false );

		$created_composition = local_rest_call( '/pressody_retailer/v1/compositions', 'POST', [
			'name'     => 'Created composition title2',
			'keywords' => [ 'keyword531', 'keyword541', ],
			'userids'  => [ self::$user_ids['customer1'], self::$user_ids['customer2'], ],
		] );

		$this->assertArrayNotHasKey( 'code', $created_composition );
		$this->assertArrayHasKey( 'id', $created_composition );
		$this->assertSame( self::$user_ids['manager1'], $created_composition['author'] );
		$this->assertArrayHasKey( 'hashid', $created_composition );

		// Edit the composition by one of the owners.
		// It should be allowed for `name`, `keywords`, `required_purchased_solutions_ids`.
		// Only composition authors can change the `userids` list and `author`.
		// Only compositions managers can change the `status`.
		\wp_set_current_user( self::$user_ids['customer1'] );
		\wp_set_auth_cookie( self::$user_ids['customer1'], true, false );

		// We only modify the name and keywords. The rest of the details are the same.
		$expected           = [
			'name'                             => 'Edited composition title by owner',
			'keywords'                         => [ 'keyword541', 'keyword551', ],
			'required_purchased_solutions_ids' => [
				self::$purchased_solution_ids['customer1_ecommerce'],
				self::$purchased_solution_ids['customer2_portfolio'],
			],
		];
		$edited_composition = local_rest_call( '/pressody_retailer/v1/compositions/' . $created_composition['hashid'], 'POST', $expected );

		$this->assertArrayNotHasKey( 'code', $edited_composition );
		$this->assertSame( $created_composition['id'], $edited_composition['id'] );
		$this->assertSame( $created_composition['author'], $edited_composition['author'] );
		$this->assertSame( $expected['name'], $edited_composition['name'] );
		$this->assertSame( $created_composition['hashid'], $edited_composition['hashid'] );
		$this->assertEqualSets( $expected['keywords'], $edited_composition['keywords'] );
		$this->assertEqualSets( $created_composition['userids'], array_keys( $edited_composition['users'] ) );
		$this->assertEqualSets( $created_composition['userids'], $edited_composition['userids'] );
		$this->assertCount( 2, $edited_composition['required_solutions'] );
		$this->assertCount( 2, $edited_composition['required_purchased_solutions'] );
		$this->assertSame( $edited_composition['required_solutions'], $edited_composition['required_purchased_solutions'] );
		$this->assertEqualSets( $expected['required_purchased_solutions_ids'], $edited_composition['required_purchased_solutions_ids'] );
		$this->assertSame( $created_composition['required_manual_solutions'], $edited_composition['required_manual_solutions'] );
		$this->assertSame( $created_composition['composer_require'], $edited_composition['composer_require'] );
		$this->assertSame( $created_composition['editLink'], $edited_composition['editLink'] );

		// Try to change the status.
		$expected           = [
			'status' => 'some_status',
		];
		$edited_composition = local_rest_call( '/pressody_retailer/v1/compositions/' . $created_composition['hashid'], 'POST', $expected );

		// Should receive error that only composition managers can modify the status.
		$this->assertArrayHasKey( 'code', $edited_composition );
		$this->assertArrayHasKey( 'message', $edited_composition );
		$this->assertArrayHasKey( 'data', $edited_composition );
		$this->assertArrayHasKey( 'status', $edited_composition['data'] );
		$this->assertSame( \rest_authorization_required_code(), $edited_composition['data']['status'] );
		$this->assertSame( 'pressody_retailer_rest_cannot_edit', $edited_composition['code'] );

		// Try to change the user IDs list.
		$expected           = [
			'userids' => [ self::$user_ids['customer1'], self::$user_ids['customer2'], self::$user_ids['customer4'], ],
		];
		$edited_composition = local_rest_call( '/pressody_retailer/v1/compositions/' . $created_composition['hashid'], 'POST', $expected );

		// Should receive error that only composition authors can modify the user IDs list.
		$this->assertArrayHasKey( 'code', $edited_composition );
		$this->assertArrayHasKey( 'message', $edited_composition );
		$this->assertArrayHasKey( 'data', $edited_composition );
		$this->assertArrayHasKey( 'status', $edited_composition['data'] );
		$this->assertSame( \rest_authorization_required_code(), $edited_composition['data']['status'] );
		$this->assertSame( 'pressody_retailer_rest_cannot_edit', $edited_composition['code'] );

		// Try to change the author.
		$expected           = [
			'author' => self::$user_ids['customer4'],
		];
		$edited_composition = local_rest_call( '/pressody_retailer/v1/compositions/' . $created_composition['hashid'], 'POST', $expected );

		// Should receive error that only composition authors can modify the user IDs list.
		$this->assertArrayHasKey( 'code', $edited_composition );
		$this->assertArrayHasKey( 'message', $edited_composition );
		$this->assertArrayHasKey( 'data', $edited_composition );
		$this->assertArrayHasKey( 'status', $edited_composition['data'] );
		$this->assertSame( \rest_authorization_required_code(), $edited_composition['data']['status'] );
		$this->assertSame( 'pressody_retailer_rest_cannot_edit', $edited_composition['code'] );
	}

	public function test_edit_item_by_other_users() {
		\wp_set_current_user( self::$user_ids['manager1'] );
		\wp_set_auth_cookie( self::$user_ids['manager1'], true, false );

		$created_composition = local_rest_call( '/pressody_retailer/v1/compositions', 'POST', [
			'name'     => 'Created composition title3',
			'keywords' => [ 'keyword531', 'keyword541', ],
			'userids'  => [ self::$user_ids['customer1'], self::$user_ids['customer2'], ],
		] );

		$this->assertArrayNotHasKey( 'code', $created_composition );
		$this->assertArrayHasKey( 'id', $created_composition );
		$this->assertSame( self::$user_ids['manager1'], $created_composition['author'] );
		$this->assertArrayHasKey( 'hashid', $created_composition );

		// Edit the composition by a user that is an PD customer but not a composition user or author.
		\wp_set_current_user( self::$user_ids['customer3'] );
		\wp_set_auth_cookie( self::$user_ids['customer3'], true, false );

		// We only modify the name and keywords. The rest of the details are the same.
		$expected           = [
			'name'                             => 'Edited composition title by other',
			'keywords'                         => [ 'keyword541', 'keyword551', ],
			'required_purchased_solutions_ids' => [
				self::$purchased_solution_ids['customer1_ecommerce'],
				self::$purchased_solution_ids['customer2_portfolio'],
			],
		];
		$edited_composition = local_rest_call( '/pressody_retailer/v1/compositions/' . $created_composition['hashid'], 'POST', $expected );

		// Should receive error.
		$this->assertArrayHasKey( 'code', $edited_composition );
		$this->assertArrayHasKey( 'message', $edited_composition );
		$this->assertArrayHasKey( 'data', $edited_composition );
		$this->assertArrayHasKey( 'status', $edited_composition['data'] );
		$this->assertSame( \rest_authorization_required_code(), $edited_composition['data']['status'] );
		$this->assertSame( 'pressody_retailer_rest_cannot_edit', $edited_composition['code'] );
	}

	public function test_edit_item_general_failures() {
		\wp_set_current_user( self::$user_ids['manager1'] );
		\wp_set_auth_cookie( self::$user_ids['manager1'], true, false );

		// Try to edit a composition with invalid hashid.
		$expected           = [
			'name' => 'Edited composition title',
		];
		$edited_composition = local_rest_call( '/pressody_retailer/v1/compositions/' . 'bogusHashid123', 'POST', $expected );

		// Should receive error.
		$this->assertArrayHasKey( 'code', $edited_composition );
		$this->assertArrayHasKey( 'message', $edited_composition );
		$this->assertArrayHasKey( 'data', $edited_composition );
		$this->assertArrayHasKey( 'status', $edited_composition['data'] );
		$this->assertSame( \rest_authorization_required_code(), $edited_composition['data']['status'] );
		$this->assertSame( 'pressody_retailer_rest_cannot_edit', $edited_composition['code'] );

		// Try to edit a composition with hashid that doesn't respect the pattern (`[A-Za-z0-9]*`).
		$expected           = [
			'name' => 'Edited composition title',
		];
		$edited_composition = local_rest_call( '/pressody_retailer/v1/compositions/' . 'bogus_hashid-123', 'POST', $expected );

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

		$created_composition = local_rest_call( '/pressody_retailer/v1/compositions', 'POST', [
			'name'     => 'Composition3',
			'keywords' => [ 'keyword53', 'keyword54', ],
			'userids'  => [ self::$user_ids['customer1'], self::$user_ids['customer2'], ],
		] );

		$this->assertArrayNotHasKey( 'code', $created_composition );
		$this->assertArrayHasKey( 'id', $created_composition );
		$this->assertSame( self::$user_ids['manager1'], $created_composition['author'] );
		$this->assertArrayHasKey( 'hashid', $created_composition );

		// Delete the newly created composition.
		$deleted_composition_response = local_rest_call( '/pressody_retailer/v1/compositions/' . $created_composition['hashid'], 'DELETE', [] );

		$this->assertArrayNotHasKey( 'code', $deleted_composition_response );
		$this->assertSame( true, $deleted_composition_response['deleted'] );
		$this->assertArrayHasKey( 'previous', $deleted_composition_response );
		$this->assertSame( $created_composition['hashid'], $deleted_composition_response['previous']['hashid'] );
		// Test that the post was trashed.
		$this->assertSame( 'trash', get_post_status( $created_composition['id'] ) );

		// Try to delete it again.
		$deleted_composition_response = local_rest_call( '/pressody_retailer/v1/compositions/' . $created_composition['hashid'], 'DELETE', [] );

		// Should receive error.
		$this->assertArrayHasKey( 'code', $deleted_composition_response );
		$this->assertArrayHasKey( 'message', $deleted_composition_response );
		$this->assertArrayHasKey( 'data', $deleted_composition_response );
		$this->assertArrayHasKey( 'status', $deleted_composition_response['data'] );
		$this->assertSame( \rest_authorization_required_code(), $deleted_composition_response['data']['status'] );
		$this->assertSame( 'pressody_retailer_rest_cannot_delete', $deleted_composition_response['code'] );

	}

	public function test_delete_item_by_owners() {
		\wp_set_current_user( self::$user_ids['manager1'] );
		\wp_set_auth_cookie( self::$user_ids['manager1'], true, false );

		$created_composition = local_rest_call( '/pressody_retailer/v1/compositions', 'POST', [
			'name'     => 'Composition31',
			'keywords' => [ 'keyword53', 'keyword54', ],
			'userids'  => [ self::$user_ids['customer1'], self::$user_ids['customer2'], ],
		] );

		$this->assertArrayNotHasKey( 'code', $created_composition );
		$this->assertArrayHasKey( 'id', $created_composition );
		$this->assertSame( self::$user_ids['manager1'], $created_composition['author'] );
		$this->assertArrayHasKey( 'hashid', $created_composition );

		// Switch to one of the owners
		\wp_set_current_user( self::$user_ids['customer1'] );
		\wp_set_auth_cookie( self::$user_ids['customer1'], true, false );

		// Delete the newly created composition.
		$deleted_composition_response = local_rest_call( '/pressody_retailer/v1/compositions/' . $created_composition['hashid'], 'DELETE', [] );

		// The deletion should fail since only composition authors can delete a composition.
		// Users/Owners can edit it, but not delete it.
		$this->assertArrayHasKey( 'code', $deleted_composition_response );
		$this->assertArrayHasKey( 'message', $deleted_composition_response );
		$this->assertArrayHasKey( 'data', $deleted_composition_response );
		$this->assertArrayHasKey( 'status', $deleted_composition_response['data'] );
		$this->assertSame( \rest_authorization_required_code(), $deleted_composition_response['data']['status'] );
		$this->assertSame( 'pressody_retailer_rest_cannot_delete', $deleted_composition_response['code'] );
	}

	public function test_delete_item_by_other_users() {
		\wp_set_current_user( self::$user_ids['manager1'] );
		\wp_set_auth_cookie( self::$user_ids['manager1'], true, false );

		$created_composition = local_rest_call( '/pressody_retailer/v1/compositions', 'POST', [
			'name'     => 'Composition32',
			'keywords' => [ 'keyword53', 'keyword54', ],
			'userids'  => [ self::$user_ids['customer1'], self::$user_ids['customer2'], ],
		] );

		$this->assertArrayNotHasKey( 'code', $created_composition );
		$this->assertArrayHasKey( 'id', $created_composition );
		$this->assertSame( self::$user_ids['manager1'], $created_composition['author'] );
		$this->assertArrayHasKey( 'hashid', $created_composition );

		// Switch to other user that is not an owner.
		\wp_set_current_user( self::$user_ids['customer3'] );
		\wp_set_auth_cookie( self::$user_ids['customer3'], true, false );

		// Delete the newly created composition.
		$deleted_composition_response = local_rest_call( '/pressody_retailer/v1/compositions/' . $created_composition['hashid'], 'DELETE', [] );

		// The deletion should fail since only composition authors can delete a composition.
		// Users/Owners can edit it, but not delete it.
		$this->assertArrayHasKey( 'code', $deleted_composition_response );
		$this->assertArrayHasKey( 'message', $deleted_composition_response );
		$this->assertArrayHasKey( 'data', $deleted_composition_response );
		$this->assertArrayHasKey( 'status', $deleted_composition_response['data'] );
		$this->assertSame( \rest_authorization_required_code(), $deleted_composition_response['data']['status'] );
		$this->assertSame( 'pressody_retailer_rest_cannot_delete', $deleted_composition_response['code'] );
	}

	public function test_encrypt_pddetails_permissions() {
		// No logged in user.
		\wp_set_current_user( 0 );
		\wp_clear_auth_cookie();

		$response = local_rest_call( '/pressody_retailer/v1/compositions/encrypt_pddetails', 'POST', [
			'userids'       => [],
			'compositionid' => 'bogushashid',
		] );

		// Should return an error since we need an user who can view solutions, at least.
		$this->assertArrayHasKey( 'code', $response );
		$this->assertArrayHasKey( 'message', $response );
		$this->assertArrayHasKey( 'data', $response );
		$this->assertArrayHasKey( 'status', $response['data'] );
		$this->assertSame( \rest_authorization_required_code(), $response['data']['status'] );
		$this->assertSame( 'pressody_retailer_rest_cannot_encrypt', $response['code'] );

		// User without proper permissions.
		\wp_set_current_user( self::$user_ids['subscriber'] );
		\wp_set_auth_cookie( self::$user_ids['subscriber'], true, false );

		$response = local_rest_call( '/pressody_retailer/v1/compositions/encrypt_pddetails', 'POST', [
			'userids'       => [],
			'compositionid' => 'bogushashid',
		] );

		// Should return an error since we need an user who can view solutions, at least.
		$this->assertArrayHasKey( 'code', $response );
		$this->assertArrayHasKey( 'message', $response );
		$this->assertArrayHasKey( 'data', $response );
		$this->assertArrayHasKey( 'status', $response['data'] );
		$this->assertSame( \rest_authorization_required_code(), $response['data']['status'] );
		$this->assertSame( 'pressody_retailer_rest_cannot_encrypt', $response['code'] );

		// User with minimum permissions.
		\wp_set_current_user( self::$user_ids['client1'] );
		\wp_set_auth_cookie( self::$user_ids['client1'], true, false );

		$response = local_rest_call( '/pressody_retailer/v1/compositions/encrypt_pddetails', 'POST', [
			'userids'       => [],
			'compositionid' => 'bogushashid',
		] );

		// Should return an error since the pddetails are invalid, but not a permissions error.
		$this->assertArrayHasKey( 'code', $response );
		$this->assertArrayHasKey( 'message', $response );
		$this->assertArrayHasKey( 'data', $response );
		$this->assertArrayHasKey( 'status', $response['data'] );
		$this->assertNotSame( \rest_authorization_required_code(), $response['data']['status'] );
		$this->assertSame( 'pressody_retailer_rest_invalid_composition_pddetails', $response['code'] );

		// User with customer permissions.
		\wp_set_current_user( self::$user_ids['customer1'] );
		\wp_set_auth_cookie( self::$user_ids['customer1'], true, false );

		$response = local_rest_call( '/pressody_retailer/v1/compositions/encrypt_pddetails', 'POST', [
			'userids'       => [],
			'compositionid' => 'bogushashid',
		] );

		// Should return an error since the pddetails are invalid, but not a permissions error.
		$this->assertArrayHasKey( 'code', $response );
		$this->assertArrayHasKey( 'message', $response );
		$this->assertArrayHasKey( 'data', $response );
		$this->assertArrayHasKey( 'status', $response['data'] );
		$this->assertNotSame( \rest_authorization_required_code(), $response['data']['status'] );
		$this->assertSame( 'pressody_retailer_rest_invalid_composition_pddetails', $response['code'] );

		// User with manager permissions.
		\wp_set_current_user( self::$user_ids['manager1'] );
		\wp_set_auth_cookie( self::$user_ids['manager1'], true, false );

		$response = local_rest_call( '/pressody_retailer/v1/compositions/encrypt_pddetails', 'POST', [
			'userids'       => [],
			'compositionid' => 'bogushashid',
		] );

		// Should return an error since the pddetails are invalid, but not a permissions error.
		$this->assertArrayHasKey( 'code', $response );
		$this->assertArrayHasKey( 'message', $response );
		$this->assertArrayHasKey( 'data', $response );
		$this->assertArrayHasKey( 'status', $response['data'] );
		$this->assertNotSame( \rest_authorization_required_code(), $response['data']['status'] );
		$this->assertSame( 'pressody_retailer_rest_invalid_composition_pddetails', $response['code'] );
	}

	public function test_encrypt_pddetails() {
		// Log in a client.
		\wp_set_current_user( self::$user_ids['client1'] );
		\wp_set_auth_cookie( self::$user_ids['client1'], true, false );

		$composition_data = self::$container['composition.manager']->get_composition_id_data( self::$composition_ids['second'] );

		$response = local_rest_call( '/pressody_retailer/v1/compositions/encrypt_pddetails', 'POST', [
			'userids'       => array_values( \wp_list_pluck( $composition_data['users'], 'id' ) ),
			'compositionid' => $composition_data['hashid'],
			'extra'         => [
				'users' => $composition_data['users'],
			],
		] );

		$this->assertIsString( $response );
	}

	public function test_encrypt_pddetails_failure() {
		// Log in a client.
		\wp_set_current_user( self::$user_ids['client1'] );
		\wp_set_auth_cookie( self::$user_ids['client1'], true, false );

		$composition_data = self::$container['composition.manager']->get_composition_id_data( self::$composition_ids['first'] );

		$response = local_rest_call( '/pressody_retailer/v1/compositions/encrypt_pddetails', 'POST', [
			'userids'       => array_values( \wp_list_pluck( $composition_data['users'], 'id' ) ),
			'compositionid' => $composition_data['hashid'],
			'extra'         => [
				'users' => $composition_data['users'],
			],
		] );

		// Should return an error since the composition has a `not_ready` status.
		$this->assertArrayHasKey( 'code', $response );
		$this->assertArrayHasKey( 'message', $response );
		$this->assertArrayHasKey( 'data', $response );
		$this->assertArrayHasKey( 'status', $response['data'] );
		$this->assertSame( HTTP::NOT_ACCEPTABLE, $response['data']['status'] );
		$this->assertSame( 'pressody_retailer_rest_invalid_composition_pddetails', $response['code'] );

		$response = local_rest_call( '/pressody_retailer/v1/compositions/encrypt_pddetails', 'POST', [
			'compositionid' => $composition_data['hashid'],
			'extra'         => [
				'users' => $composition_data['users'],
			],
		] );

		// Should return an error since we haven't provided any userids.
		$this->assertArrayHasKey( 'code', $response );
		$this->assertSame( 'rest_missing_callback_param', $response['code'] );
		$this->assertStringContainsString( 'userids', $response['message'] );

		$response = local_rest_call( '/pressody_retailer/v1/compositions/encrypt_pddetails', 'POST', [
			'userids' => array_values( \wp_list_pluck( $composition_data['users'], 'id' ) ),
			'extra'   => [
				'users' => $composition_data['users'],
			],
		] );

		// Should return an error since we haven't provided a compositionid.
		$this->assertArrayHasKey( 'code', $response );
		$this->assertSame( 'rest_missing_callback_param', $response['code'] );
		$this->assertStringContainsString( 'compositionid', $response['message'] );

		// Test a broken encryption setup.

		// Get a good composition.
		$composition_data = self::$container['composition.manager']->get_composition_id_data( self::$composition_ids['second'] );

		// Temporarily break the crypter.
		$temp_key = PHPUnitUtil::getProtectedProperty( self::$container['crypter'], 'key' );
		try {
			PHPUnitUtil::setProtectedProperty( self::$container['crypter'], 'key', null );
		} catch ( \Exception $e ) {
			// Do nothing.
		}

		$response = local_rest_call( '/pressody_retailer/v1/compositions/encrypt_pddetails', 'POST', [
			'userids'       => array_values( \wp_list_pluck( $composition_data['users'], 'id' ) ),
			'compositionid' => $composition_data['hashid'],
			'extra'         => [
				'users' => $composition_data['users'],
			],
		] );

		// Should return an error about not being able to encrypt.
		$this->assertArrayHasKey( 'code', $response );
		$this->assertArrayHasKey( 'message', $response );
		$this->assertArrayHasKey( 'data', $response );
		$this->assertArrayHasKey( 'status', $response['data'] );
		$this->assertSame( HTTP::INTERNAL_SERVER_ERROR, $response['data']['status'] );
		$this->assertSame( 'pressody_retailer_rest_unable_to_encrypt', $response['code'] );

		// Put back the proper key.
		try {
			PHPUnitUtil::setProtectedProperty( self::$container['crypter'], 'key', $temp_key );
		} catch ( \Exception $e ) {
			// Do nothing.
		}
	}

	public function test_check_pddetails_permissions() {
		// No logged in user.
		\wp_set_current_user( 0 );
		\wp_clear_auth_cookie();

		$response = local_rest_call( '/pressody_retailer/v1/compositions/check_pddetails', 'POST', [
			'pddetails' => '',
			'composer'  => [],
		] );

		// Should return an error since we need an user who can view solutions, at least.
		$this->assertArrayHasKey( 'code', $response );
		$this->assertArrayHasKey( 'message', $response );
		$this->assertArrayHasKey( 'data', $response );
		$this->assertArrayHasKey( 'status', $response['data'] );
		$this->assertSame( \rest_authorization_required_code(), $response['data']['status'] );
		$this->assertSame( 'pressody_retailer_rest_cannot_check', $response['code'] );

		// User without proper permissions.
		\wp_set_current_user( self::$user_ids['subscriber'] );
		\wp_set_auth_cookie( self::$user_ids['subscriber'], true, false );

		$response = local_rest_call( '/pressody_retailer/v1/compositions/check_pddetails', 'POST', [
			'pddetails' => '',
			'composer'  => [],
		] );

		// Should return an error since we need an user who can view solutions, at least.
		$this->assertArrayHasKey( 'code', $response );
		$this->assertArrayHasKey( 'message', $response );
		$this->assertArrayHasKey( 'data', $response );
		$this->assertArrayHasKey( 'status', $response['data'] );
		$this->assertSame( \rest_authorization_required_code(), $response['data']['status'] );
		$this->assertSame( 'pressody_retailer_rest_cannot_check', $response['code'] );

		// User with minimum permissions.
		\wp_set_current_user( self::$user_ids['client1'] );
		\wp_set_auth_cookie( self::$user_ids['client1'], true, false );

		$response = local_rest_call( '/pressody_retailer/v1/compositions/check_pddetails', 'POST', [
			'pddetails' => 'rsreytetrtet',
			'composer'  => [],
		] );

		// Should return an error since the encrypted pddetails are invalid, but not a permissions error.
		$this->assertArrayHasKey( 'code', $response );
		$this->assertArrayHasKey( 'message', $response );
		$this->assertArrayHasKey( 'data', $response );
		$this->assertArrayHasKey( 'status', $response['data'] );
		$this->assertNotSame( \rest_authorization_required_code(), $response['data']['status'] );
		$this->assertSame( 'pressody_retailer_rest_invalid_pddetails', $response['code'] );

		// User with customer permissions.
		\wp_set_current_user( self::$user_ids['customer1'] );
		\wp_set_auth_cookie( self::$user_ids['customer1'], true, false );

		$response = local_rest_call( '/pressody_retailer/v1/compositions/check_pddetails', 'POST', [
			'pddetails' => 'rsreytetrtet',
			'composer'  => [],
		] );

		// Should return an error since the encrypted pddetails are invalid, but not a permissions error.
		$this->assertArrayHasKey( 'code', $response );
		$this->assertArrayHasKey( 'message', $response );
		$this->assertArrayHasKey( 'data', $response );
		$this->assertArrayHasKey( 'status', $response['data'] );
		$this->assertNotSame( \rest_authorization_required_code(), $response['data']['status'] );
		$this->assertSame( 'pressody_retailer_rest_invalid_pddetails', $response['code'] );

		// User with manager permissions.
		\wp_set_current_user( self::$user_ids['manager1'] );
		\wp_set_auth_cookie( self::$user_ids['manager1'], true, false );

		$response = local_rest_call( '/pressody_retailer/v1/compositions/check_pddetails', 'POST', [
			'pddetails' => 'rsreytetrtet',
			'composer'  => [],
		] );

		// Should return an error since the encrypted pddetails are invalid, but not a permissions error.
		$this->assertArrayHasKey( 'code', $response );
		$this->assertArrayHasKey( 'message', $response );
		$this->assertArrayHasKey( 'data', $response );
		$this->assertArrayHasKey( 'status', $response['data'] );
		$this->assertNotSame( \rest_authorization_required_code(), $response['data']['status'] );
		$this->assertSame( 'pressody_retailer_rest_invalid_pddetails', $response['code'] );
	}

	public function test_check_pddetails() {
		// Log in a client.
		\wp_set_current_user( self::$user_ids['client1'] );
		\wp_set_auth_cookie( self::$user_ids['client1'], true, false );

		$composition_data = self::$container['composition.manager']->get_composition_id_data( self::$composition_ids['second'] );

		$encrypted_data = local_rest_call( '/pressody_retailer/v1/compositions/encrypt_pddetails', 'POST', [
			'userids'       => array_values( \wp_list_pluck( $composition_data['users'], 'id' ) ),
			'compositionid' => $composition_data['hashid'],
			'extra'         => [
				'users' => $composition_data['users'],
			],
		] );

		$this->assertIsString( $encrypted_data );

		$response = local_rest_call( '/pressody_retailer/v1/compositions/check_pddetails', 'POST', [
			'pddetails' => $encrypted_data,
			'composer'  => [],
		] );

		$this->assertSame( [], $response );
	}

	public function test_check_pddetails_failure() {
		// Log in a client.
		\wp_set_current_user( self::$user_ids['client1'] );
		\wp_set_auth_cookie( self::$user_ids['client1'], true, false );

		$response = local_rest_call( '/pressody_retailer/v1/compositions/check_pddetails', 'POST', [
			'pddetails' => 'sdfgsdqwerwer',
			'composer'  => [],
		] );

		// Should return an error due to decryption error.
		$this->assertArrayHasKey( 'code', $response );
		$this->assertArrayHasKey( 'message', $response );
		$this->assertArrayHasKey( 'data', $response );
		$this->assertArrayHasKey( 'status', $response['data'] );
		$this->assertSame( HTTP::NOT_ACCEPTABLE, $response['data']['status'] );
		$this->assertSame( 'pressody_retailer_rest_invalid_pddetails', $response['code'] );

		// Test a broken encryption setup.

		// Get a good composition.
		$composition_data = self::$container['composition.manager']->get_composition_id_data( self::$composition_ids['second'] );
		// Encrypt it.
		$encrypted_data = local_rest_call( '/pressody_retailer/v1/compositions/encrypt_pddetails', 'POST', [
			'userids'       => array_values( \wp_list_pluck( $composition_data['users'], 'id' ) ),
			'compositionid' => $composition_data['hashid'],
			'extra'         => [
				'users' => $composition_data['users'],
			],
		] );

		$this->assertIsString( $encrypted_data );

		// Temporarily break the crypter.
		$temp_key = PHPUnitUtil::getProtectedProperty( self::$container['crypter'], 'key' );
		try {
			PHPUnitUtil::setProtectedProperty( self::$container['crypter'], 'key', null );
		} catch ( \Exception $e ) {
			// Do nothing.
		}

		$response = local_rest_call( '/pressody_retailer/v1/compositions/check_pddetails', 'POST', [
			'pddetails' => $encrypted_data,
			'composer'  => [],
		] );

		// Should return an error about not being able to encrypt.
		$this->assertArrayHasKey( 'code', $response );
		$this->assertArrayHasKey( 'message', $response );
		$this->assertArrayHasKey( 'data', $response );
		$this->assertArrayHasKey( 'status', $response['data'] );
		$this->assertSame( HTTP::INTERNAL_SERVER_ERROR, $response['data']['status'] );
		$this->assertSame( 'pressody_retailer_rest_unable_to_encrypt', $response['code'] );

		// Put back the proper key.
		try {
			PHPUnitUtil::setProtectedProperty( self::$container['crypter'], 'key', $temp_key );
		} catch ( \Exception $e ) {
			// Do nothing.
		}
	}

	public function test_instructions_to_update_composition_permissions() {
		// No logged in user.
		\wp_set_current_user( 0 );
		\wp_clear_auth_cookie();

		$response = local_rest_call( '/pressody_retailer/v1/compositions/instructions_to_update', 'POST', [
			'composer' => [],
		] );

		// Should return an error since we need an user who can view solutions, at least.
		$this->assertArrayHasKey( 'code', $response );
		$this->assertArrayHasKey( 'message', $response );
		$this->assertArrayHasKey( 'data', $response );
		$this->assertArrayHasKey( 'status', $response['data'] );
		$this->assertSame( \rest_authorization_required_code(), $response['data']['status'] );
		$this->assertSame( 'pressody_retailer_rest_cannot_update', $response['code'] );

		// User without proper permissions.
		\wp_set_current_user( self::$user_ids['subscriber'] );
		\wp_set_auth_cookie( self::$user_ids['subscriber'], true, false );

		$response = local_rest_call( '/pressody_retailer/v1/compositions/instructions_to_update', 'POST', [
			'composer' => [],
		] );

		// Should return an error since we need an user who can view solutions, at least.
		$this->assertArrayHasKey( 'code', $response );
		$this->assertArrayHasKey( 'message', $response );
		$this->assertArrayHasKey( 'data', $response );
		$this->assertArrayHasKey( 'status', $response['data'] );
		$this->assertSame( \rest_authorization_required_code(), $response['data']['status'] );
		$this->assertSame( 'pressody_retailer_rest_cannot_update', $response['code'] );

		// User with minimum permissions.
		\wp_set_current_user( self::$user_ids['client1'] );
		\wp_set_auth_cookie( self::$user_ids['client1'], true, false );

		$response = local_rest_call( '/pressody_retailer/v1/compositions/instructions_to_update', 'POST', [
			'composer' => [],
		] );

		// Should return an error since the composer details are invalid, but not a permissions error.
		$this->assertArrayHasKey( 'code', $response );
		$this->assertArrayHasKey( 'message', $response );
		$this->assertArrayHasKey( 'data', $response );
		$this->assertArrayHasKey( 'status', $response['data'] );
		$this->assertNotSame( \rest_authorization_required_code(), $response['data']['status'] );
		$this->assertSame( 'pressody_retailer_rest_missing_composition_pddetails', $response['code'] );

		// User with customer permissions.
		\wp_set_current_user( self::$user_ids['customer1'] );
		\wp_set_auth_cookie( self::$user_ids['customer1'], true, false );

		$response = local_rest_call( '/pressody_retailer/v1/compositions/instructions_to_update', 'POST', [
			'composer' => [],
		] );

		// Should return an error since the composer details are invalid, but not a permissions error.
		$this->assertArrayHasKey( 'code', $response );
		$this->assertArrayHasKey( 'message', $response );
		$this->assertArrayHasKey( 'data', $response );
		$this->assertArrayHasKey( 'status', $response['data'] );
		$this->assertNotSame( \rest_authorization_required_code(), $response['data']['status'] );
		$this->assertSame( 'pressody_retailer_rest_missing_composition_pddetails', $response['code'] );

		// User with manager permissions.
		\wp_set_current_user( self::$user_ids['manager1'] );
		\wp_set_auth_cookie( self::$user_ids['manager1'], true, false );

		$response = local_rest_call( '/pressody_retailer/v1/compositions/instructions_to_update', 'POST', [
			'composer' => [],
		] );

		// Should return an error since the composer details are invalid, but not a permissions error.
		$this->assertArrayHasKey( 'code', $response );
		$this->assertArrayHasKey( 'message', $response );
		$this->assertArrayHasKey( 'data', $response );
		$this->assertArrayHasKey( 'status', $response['data'] );
		$this->assertNotSame( \rest_authorization_required_code(), $response['data']['status'] );
		$this->assertSame( 'pressody_retailer_rest_missing_composition_pddetails', $response['code'] );
	}

	public function test_instructions_to_update_composition() {
		// Log in a client.
		\wp_set_current_user( self::$user_ids['client1'] );
		\wp_set_auth_cookie( self::$user_ids['client1'], true, false );

		$composition_data = self::$container['composition.manager']->get_composition_id_data( self::$composition_ids['second'] );

		$encrypted_data = local_rest_call( '/pressody_retailer/v1/compositions/encrypt_pddetails', 'POST', [
			'userids'       => array_values( \wp_list_pluck( $composition_data['users'], 'id' ) ),
			'compositionid' => $composition_data['hashid'],
			'extra'         => [
				'users' => $composition_data['users'],
			],
		] );

		$this->assertIsString( $encrypted_data );

		// Use the starter composition from PD Records.
		$response = local_rest_call( '/pressody_retailer/v1/compositions/instructions_to_update', 'POST', [
			'composer' => [
				'name'              => 'pressody/site',
				'type'              => 'project',
				'license'           => 'MIT',
				'description'       => 'A Pressody WordPress site.',
				'homepage'          => 'https://pressody.com',
				'time'              => "2021-07-13T14:28:26+00:00",
				'authors'           => [
					[
						'name'     => 'Vlad Olaru',
						'email'    => 'vlad@thinkwritecode.com',
						'homepage' => 'https://thinkwritecode.com',
						'role'     => 'Development, infrastructure, and product development',
					],
					[
						'name'     => 'George Olaru',
						'email'    => 'george@pixelgrade.com',
						'homepage' => 'https://pixelgrade.com',
						'role'     => 'Design and product development',
					],
					[
						'name'     => 'Răzvan Onofrei',
						'email'    => 'razvan@pixelgrade.com',
						'homepage' => 'https://pixelgrade.com',
						'role'     => 'Development and product development',
					],
				],
				'keywords'          => [
					'pressody',
					'bedrock',
					'composer',
					'roots',
					'wordpress',
					'wp',
					'wp-config',
				],
				'support'           => [
					'issues' => 'https://pressody.com',
					'forum'  => 'https://pressody.com',
				],
				'repositories'      => [
					[
						// Our very own Composer repo.
						'type'    => 'composer',
						'url'     => 'https://pd-records.local/pdpackagist/',
						'options' => [
							'ssl' => [
								'verify_peer' => false,
							],
						],
					],
					[
						'type' => 'vcs',
						'url'  => 'https://github.com/pressody/pressody-conductor',
					],
					[
						// The Packagist repo.
						'type' => 'composer',
						'url'  => 'https://repo.packagist.org',
					],
				],
				'require'           => [
					'ext-json'                            => '*',
					'gordalina/cachetool'                 => '~6.3',
					'php'                                 => '>=7.1',
					'oscarotero/env'                      => '^2.1',
					'pressody/pressody-conductor' => 'dev-main',
					'roots/bedrock-autoloader'            => '^1.0',
					'roots/wordpress'                     => '*',
					'roots/wp-config'                     => '1.0.0',
					'roots/wp-password-bcrypt'            => '1.0.0',
					'vlucas/phpdotenv'                    => '^5.3',
				],
				'require-dev'       => [
					'squizlabs/php_codesniffer' => '^3.5.8',
					'roave/security-advisories' => 'dev-latest',
				],
				'config'            => [
					// Lock the vendor directory name so we don't get any surprises.
					'vendor-dir'          => 'vendor',
					'optimize-autoloader' => true,
					'preferred-install'   => 'dist',
					'sort-packages'       => true,
				],
				'minimum-stability' => 'dev',
				'prefer-stable'     => true,
				'extra'             => [
					// @see https://packagist.org/packages/composer/installers
					'installer-paths'       => [
						// Since the ActionScheduler is of the wordpress-plugin type, but we don't use it as such,
						// we want it placed in the vendor directory. This rule needs to come first to take priority.
						'vendor/{$vendor}/{$name}/'   => [ 'woocommerce/action-scheduler', ],
						'web/app/mu-plugins/{$name}/' => [ 'type:wordpress-muplugin' ],
						'web/app/plugins/{$name}/'    => [ 'type:wordpress-plugin' ],
						'web/app/themes/{$name}/'     => [ 'type:wordpress-theme' ],
					],
					'pd-composition'        => $encrypted_data,
					// @see https://packagist.org/packages/roots/wordpress-core-installer
					'wordpress-install-dir' => 'web/wp',
					// PD Composition version
					'pd-version'            => '1.1.0',
					'pd-fingerprint'        => "somefingerprint",
				],
				'scripts'           => [
					'cache:schedule:clear'   => [
						'Pressody\Conductor\Cache\CacheDispatcher::schedule_cache_clear',
					],
					// CacheTool wrapper commands. See https://github.com/gordalina/cachetool
					'cache:opcache:status'   => [
						'./vendor/bin/cachetool opcache:status',
					],
					'cache:opcache:clear'    => [
						'./vendor/bin/cachetool opcache:reset',
					],
					'cache:opcache:warm'     => [
						'./vendor/bin/cachetool opcache:compile:scripts -q ./web/',
					],
					// Allow the CatchDispatcher to take action on package modifications.
					'pre-package-install'    => [
						'Pressody\Conductor\Cache\CacheDispatcher::handle_event',
					],
					'post-package-install'   => [
						'Pressody\Conductor\Cache\CacheDispatcher::handle_event',
					],
					'pre-package-update'     => [
						'Pressody\Conductor\Cache\CacheDispatcher::handle_event',
					],
					'post-package-update'    => [
						'Pressody\Conductor\Cache\CacheDispatcher::handle_event',
					],
					'pre-package-uninstall'  => [
						'Pressody\Conductor\Cache\CacheDispatcher::handle_event',
					],
					'post-package-uninstall' => [
						'Pressody\Conductor\Cache\CacheDispatcher::handle_event',
					],
				],
			],
		] );

		// The response should be about adding some required parts since we had none and the composition has solutions in it.
		$this->assertArrayNotHasKey( 'code', $response );
		$this->assertArrayHasKey( 'remove', $response );
		$this->assertSame( [], $response['remove'] );
		$this->assertArrayHasKey( 'require', $response );
		$this->assertCount( 2, $response['require'] );
		$this->assertEqualSets( [
			'pressody-records/part_test-test',
			'pressody-records/part_yet-another',
		], array_values( \wp_list_pluck( $response['require'], 'name' ) ) );
		$this->assertArrayHasKey( 'version', reset( $response['require'] ) );
		$this->assertArrayHasKey( 'requiredBy', reset( $response['require'] ) );
	}

	public function test_instructions_to_update_composition_failure() {
		// Log in a client.
		\wp_set_current_user( self::$user_ids['client1'] );
		\wp_set_auth_cookie( self::$user_ids['client1'], true, false );

		// Pass Composer config that should not validate the schema (like a wrong entry value).
		$response = local_rest_call( '/pressody_retailer/v1/compositions/instructions_to_update', 'POST', [
			'composer' => [
				'name'         => 'wrong_name',
				'type'         => 'project',
				'license'      => 'MIT',
				'description'  => 'A Pressody WordPress site.',
				'homepage'     => 'https://pressody.com',
				'time'         => "2021-07-13T14:28:26+00:00",
				'authors'      => [
					[
						'name'     => 'Vlad Olaru',
						'email'    => 'vlad@thinkwritecode.com',
						'homepage' => 'https://thinkwritecode.com',
						'role'     => 'Development, infrastructure, and product development',
					],
				],
				'repositories' => [
					[
						// Our very own Composer repo.
						'type'    => 'composer',
						'url'     => 'https://pd-records.local/pdpackagist/',
						'options' => [
							'ssl' => [
								'verify_peer' => false,
							],
						],
					],
					[
						'type' => 'vcs',
						'url'  => 'https://github.com/pressody/pressody-conductor',
					],
					[
						// The Packagist repo.
						'type' => 'composer',
						'url'  => 'https://repo.packagist.org',
					],
				],
				'require'      => [
					'ext-json'                            => '*',
					'gordalina/cachetool'                 => '~6.3',
					'php'                                 => '>=7.1',
					'oscarotero/env'                      => '^2.1',
					'pressody/pressody-conductor' => 'dev-main',
					'roots/bedrock-autoloader'            => '^1.0',
					'roots/wordpress'                     => '*',
					'roots/wp-config'                     => '1.0.0',
					'roots/wp-password-bcrypt'            => '1.0.0',
					'vlucas/phpdotenv'                    => '^5.3',
				],
			],
		] );

		// Should return an error since the composer details are invalid.
		$this->assertArrayHasKey( 'code', $response );
		$this->assertArrayHasKey( 'message', $response );
		$this->assertArrayHasKey( 'data', $response );
		$this->assertArrayHasKey( 'status', $response['data'] );
		$this->assertNotSame( \rest_authorization_required_code(), $response['data']['status'] );
		$this->assertSame( 'pressody_retailer_rest_json_invalid', $response['code'] );

		// Pass Composer config with missing encrypted PD details in the `extra` root entry.
		$response = local_rest_call( '/pressody_retailer/v1/compositions/instructions_to_update', 'POST', [
			'composer' => [
				'name'         => 'pressody/site',
				'type'         => 'project',
				'license'      => 'MIT',
				'description'  => 'A Pressody WordPress site.',
				'homepage'     => 'https://pressody.com',
				'time'         => "2021-07-13T14:28:26+00:00",
				'authors'      => [
					[
						'name'     => 'Vlad Olaru',
						'email'    => 'vlad@thinkwritecode.com',
						'homepage' => 'https://thinkwritecode.com',
						'role'     => 'Development, infrastructure, and product development',
					],
				],
				'repositories' => [
					[
						// Our very own Composer repo.
						'type'    => 'composer',
						'url'     => 'https://pd-records.local/pdpackagist/',
						'options' => [
							'ssl' => [
								'verify_peer' => false,
							],
						],
					],
					[
						'type' => 'vcs',
						'url'  => 'https://github.com/pressody/pressody-conductor',
					],
					[
						// The Packagist repo.
						'type' => 'composer',
						'url'  => 'https://repo.packagist.org',
					],
				],
				'require'      => [
					'ext-json'                            => '*',
					'gordalina/cachetool'                 => '~6.3',
					'php'                                 => '>=7.1',
					'oscarotero/env'                      => '^2.1',
					'pressody/pressody-conductor' => 'dev-main',
					'roots/bedrock-autoloader'            => '^1.0',
					'roots/wordpress'                     => '*',
					'roots/wp-config'                     => '1.0.0',
					'roots/wp-password-bcrypt'            => '1.0.0',
					'vlucas/phpdotenv'                    => '^5.3',
				],
			],
		] );

		// Should return an error since the composer details are invalid.
		$this->assertArrayHasKey( 'code', $response );
		$this->assertArrayHasKey( 'message', $response );
		$this->assertArrayHasKey( 'data', $response );
		$this->assertArrayHasKey( 'status', $response['data'] );
		$this->assertNotSame( \rest_authorization_required_code(), $response['data']['status'] );
		$this->assertSame( 'pressody_retailer_rest_missing_composition_pddetails', $response['code'] );

		// Pass Composer config with invalid encrypted PD details in the `extra` root entry.
		$response = local_rest_call( '/pressody_retailer/v1/compositions/instructions_to_update', 'POST', [
			'composer' => [
				'name'         => 'pressody/site',
				'type'         => 'project',
				'license'      => 'MIT',
				'description'  => 'A Pressody WordPress site.',
				'homepage'     => 'https://pressody.com',
				'time'         => "2021-07-13T14:28:26+00:00",
				'authors'      => [
					[
						'name'     => 'Vlad Olaru',
						'email'    => 'vlad@thinkwritecode.com',
						'homepage' => 'https://thinkwritecode.com',
						'role'     => 'Development, infrastructure, and product development',
					],
				],
				'repositories' => [
					[
						// Our very own Composer repo.
						'type'    => 'composer',
						'url'     => 'https://pd-records.local/pdpackagist/',
						'options' => [
							'ssl' => [
								'verify_peer' => false,
							],
						],
					],
					[
						'type' => 'vcs',
						'url'  => 'https://github.com/pressody/pressody-conductor',
					],
					[
						// The Packagist repo.
						'type' => 'composer',
						'url'  => 'https://repo.packagist.org',
					],
				],
				'require'      => [
					'ext-json'                            => '*',
					'gordalina/cachetool'                 => '~6.3',
					'php'                                 => '>=7.1',
					'oscarotero/env'                      => '^2.1',
					'pressody/pressody-conductor' => 'dev-main',
					'roots/bedrock-autoloader'            => '^1.0',
					'roots/wordpress'                     => '*',
					'roots/wp-config'                     => '1.0.0',
					'roots/wp-password-bcrypt'            => '1.0.0',
					'vlucas/phpdotenv'                    => '^5.3',
				],
				'extra'             => [
					// @see https://packagist.org/packages/composer/installers
					'installer-paths'       => [
						// Since the ActionScheduler is of the wordpress-plugin type, but we don't use it as such,
						// we want it placed in the vendor directory. This rule needs to come first to take priority.
						'vendor/{$vendor}/{$name}/'   => [ 'woocommerce/action-scheduler', ],
						'web/app/mu-plugins/{$name}/' => [ 'type:wordpress-muplugin' ],
						'web/app/plugins/{$name}/'    => [ 'type:wordpress-plugin' ],
						'web/app/themes/{$name}/'     => [ 'type:wordpress-theme' ],
					],
					'pd-composition'        => 'afsdgwerwerwerwe',
					// @see https://packagist.org/packages/roots/wordpress-core-installer
					'wordpress-install-dir' => 'web/wp',
					// PD Composition version
					'pd-version'            => '1.1.0',
					'pd-fingerprint'        => "somefingerprint",
				],
			],
		] );

		// Should return an error since the composer details are invalid.
		$this->assertArrayHasKey( 'code', $response );
		$this->assertArrayHasKey( 'message', $response );
		$this->assertArrayHasKey( 'data', $response );
		$this->assertArrayHasKey( 'status', $response['data'] );
		$this->assertNotSame( \rest_authorization_required_code(), $response['data']['status'] );
		$this->assertSame( 'pressody_retailer_rest_invalid_composition_pddetails', $response['code'] );
	}
}
