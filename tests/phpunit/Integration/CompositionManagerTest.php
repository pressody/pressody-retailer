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

namespace Pressody\Retailer\Tests\Integration;

use Pressody\Retailer\Capabilities;
use Pressody\Retailer\CompositionManager;
use Pressody\Retailer\SolutionType\SolutionTypes;
use Pressody\Retailer\Tests\Framework\PHPUnitUtil;

use Psr\Container\ContainerInterface;
use function Pressody\Retailer\plugin;

use WP_Http as HTTP;

class CompositionManagerTest extends TestCase {
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
				'_composition_required_manual_solutions|||0|value'           => '_',
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

	public function test_get_composition_ids_by() {
		/** @var CompositionManager $composition_manager */
		$composition_manager = self::$container['composition.manager'];

		$this->assertEqualSets( array_values( self::$composition_ids ), $composition_manager->get_composition_ids_by( [] ) );
		$this->assertEqualSets( [ self::$composition_ids['first'], ], $composition_manager->get_composition_ids_by( [
			'post_author' => self::$user_ids['customer1'],
		] ) );
		$this->assertEqualSets( [ self::$composition_ids['third'], ], $composition_manager->get_composition_ids_by( [
			'post_author' => self::$user_ids['manager1'],
		] ) );

		$this->assertEqualSets( array_values( self::$composition_ids ), $composition_manager->get_composition_ids_by( [
			'post_ids' => array_values( self::$composition_ids ),
		] ) );
		$this->assertEqualSets( [ self::$composition_ids['first'], ], $composition_manager->get_composition_ids_by( [
			'post_ids' => self::$composition_ids['first'],
		] ) );

		$this->assertEqualSets( [ self::$composition_ids['third'], ], $composition_manager->get_composition_ids_by( [
			'exclude_post_ids' => [ self::$composition_ids['first'], self::$composition_ids['second'], ],
		] ) );
		$this->assertEqualSets( [
			self::$composition_ids['first'],
			self::$composition_ids['second'],
		], $composition_manager->get_composition_ids_by( [
			'exclude_post_ids' => self::$composition_ids['third'],
		] ) );

		$this->assertEqualSets( [ self::$composition_ids['third'] ], $composition_manager->get_composition_ids_by( [
			'slug' => [ self::$compositions_post_data['third']['post_name'], ],
		] ) );
		$this->assertEqualSets( [
			self::$composition_ids['first'],
			self::$composition_ids['second'],
		], $composition_manager->get_composition_ids_by( [
			'slug' => [
				self::$compositions_post_data['first']['post_name'],
				self::$compositions_post_data['second']['post_name'],
			],
		] ) );

		$this->assertEqualSets( array_values( self::$composition_ids ), $composition_manager->get_composition_ids_by( [
			'post_status' => 'private',
		] ) );
		$this->assertEqualSets( [], $composition_manager->get_composition_ids_by( [
			'post_status' => 'publish',
		] ) );

		$this->assertEqualSets( [ self::$composition_ids['first'], ], $composition_manager->get_composition_ids_by( [
			'status' => CompositionManager::DEFAUPD_STATUS,
		] ) );
		$this->assertEqualSets( [
			self::$composition_ids['second'],
			self::$composition_ids['third'],
		], $composition_manager->get_composition_ids_by( [
			'status' => 'ready',
		] ) );
		$this->assertEqualSets( array_values( self::$composition_ids ), $composition_manager->get_composition_ids_by( [
			'status' => [ CompositionManager::DEFAUPD_STATUS, 'ready', ],
		] ) );

		$this->assertEqualSets( [ self::$composition_ids['first'], ], $composition_manager->get_composition_ids_by( [
			'hashid' => self::$compositions_post_data['first']['meta_input']['_composition_hashid'],
		] ) );
		$this->assertEqualSets( [
			self::$composition_ids['second'],
			self::$composition_ids['third'],
		], $composition_manager->get_composition_ids_by( [
			'hashid' => [
				self::$compositions_post_data['second']['meta_input']['_composition_hashid'],
				self::$compositions_post_data['third']['meta_input']['_composition_hashid'],
			],
		] ) );

		$this->assertEqualSets( [ self::$composition_ids['first'], ], $composition_manager->get_composition_ids_by( [
			'userid' => self::$user_ids['customer1'],
		] ) );
		$this->assertEqualSets( [
			self::$composition_ids['first'],
			self::$composition_ids['second'],
		], $composition_manager->get_composition_ids_by( [
			'userid' => self::$user_ids['customer2'],
		] ) );
	}

	public function test_get_composition_post_id_by() {
		/** @var CompositionManager $composition_manager */
		$composition_manager = self::$container['composition.manager'];

		$this->assertSame( self::$composition_ids['third'], $composition_manager->get_composition_post_id_by( [
			'slug' => [ self::$compositions_post_data['third']['post_name'], ],
		] ) );
		$this->assertSame( self::$composition_ids['first'], $composition_manager->get_composition_post_id_by( [
			'hashid' => self::$compositions_post_data['first']['meta_input']['_composition_hashid'],
		] ) );

		$this->assertSame( self::$composition_ids['first'], $composition_manager->get_composition_post_id_by( [
			'userid' => self::$user_ids['customer1'],
		] ) );
		$this->assertSame( self::$composition_ids['first'], $composition_manager->get_composition_post_id_by( [
			'userid' => self::$user_ids['customer2'],
		] ) );
	}

	public function test_get_composition_data_by() {
		/** @var CompositionManager $composition_manager */
		$composition_manager = self::$container['composition.manager'];

		$this->assertSame( self::$composition_ids['third'], $composition_manager->get_composition_data_by( [
			'slug' => [ self::$compositions_post_data['third']['post_name'], ],
		] )['id'] );
		$this->assertSame( self::$composition_ids['first'], $composition_manager->get_composition_data_by( [
			'hashid' => self::$compositions_post_data['first']['meta_input']['_composition_hashid'],
		] )['id'] );

		$this->assertSame( self::$composition_ids['first'], $composition_manager->get_composition_data_by( [
			'userid' => self::$user_ids['customer1'],
		] )['id'] );
		$this->assertSame( self::$composition_ids['first'], $composition_manager->get_composition_data_by( [
			'userid' => self::$user_ids['customer2'],
		] )['id'] );
	}

	public function test_get_composition_id_data() {
		/** @var CompositionManager $composition_manager */
		$composition_manager = self::$container['composition.manager'];

		$composition_data = $composition_manager->get_composition_id_data( self::$composition_ids['first'] );

		$this->assertSame( self::$composition_ids['first'], $composition_data['id'] );
		$this->assertSame( self::$compositions_post_data['first']['meta_input']['_composition_status'], $composition_data['status'] );
		$this->assertSame( self::$compositions_post_data['first']['meta_input']['_composition_hashid'], $composition_data['hashid'] );
		$this->assertSame( self::$compositions_post_data['first']['post_author'], $composition_data['author'] );
		$this->assertSame( self::$compositions_post_data['first']['post_title'], $composition_data['name'] );
		$this->assertEqualSets( [ 'keyword1', 'keyword2', 'keyword3', ], $composition_data['keywords'] );
		$this->assertEqualSets( [
			self::$user_ids['customer1'],
			self::$user_ids['customer2'],
		], array_values( \wp_list_pluck( $composition_data['users'], 'id' ) ) );
		$this->assertCount( 2, $composition_data['required_solutions'] );
		$this->assertEqualSets( [
			'ecommerce',
			'portfolio',
		], array_values( \wp_list_pluck( $composition_data['required_solutions'], 'slug' ) ) );
		$this->assertEqualSets( [
			'purchased',
			'manual',
		], array_values( \wp_list_pluck( $composition_data['required_solutions'], 'type' ) ) );
		$this->assertCount( 1, $composition_data['required_purchased_solutions'] );
		$this->assertCount( 1, $composition_data['required_manual_solutions'] );
		$this->assertSame( [], $composition_data['composer_require'] );
	}

	public function test_save_composition_creation() {
		/** @var CompositionManager $composition_manager */
		$composition_manager = self::$container['composition.manager'];

		$expected = [
			'post_title'  => 'Testing',
			'post_status' => 'private',
			'post_author' => self::$user_ids['customer1'],
		];

		$result = $composition_manager->save_composition( $expected );
		$this->assertNotSame( 0, $result );

		$composition_data = $composition_manager->get_composition_id_data( $result );
		$this->assertSame( $expected['post_title'], $composition_data['name'] );
		$this->assertSame( $expected['post_author'], $composition_data['author'] );
		$this->assertSame( [], $composition_data['keywords'] );
		$this->assertSame( [], array_values( \wp_list_pluck( $composition_data['users'], 'id' ) ) );
		$this->assertSame( [], $composition_data['required_solutions'] );
		$this->assertSame( [], $composition_data['required_purchased_solutions'] );
		$this->assertSame( [], $composition_data['required_manual_solutions'] );
		$this->assertSame( [], $composition_data['composer_require'] );
	}

	public function test_save_composition_update() {
		/** @var CompositionManager $composition_manager */
		$composition_manager = self::$container['composition.manager'];

		$created = [
			'post_title'  => 'Testing',
			'post_status' => 'private',
			'post_author' => self::$user_ids['customer1'],
		];

		$id = $composition_manager->save_composition( $created );
		$this->assertNotSame( 0, $id );

		// Now update it.
		$expected = [
			'post_id'                          => $id,
			'post_title'                       => 'Testing More',
			'post_status'                      => 'publish',
			'post_author'                      => self::$user_ids['customer2'],
			'status'                           => 'ready',
			'user_ids'                         => [ self::$user_ids['customer1'], self::$user_ids['customer3'], ],
			'required_purchased_solutions_ids' => [
				self::$purchased_solution_ids['customer1_ecommerce'],
				self::$purchased_solution_ids['customer1_presentation'],
			],
			'required_manual_solutions'        => [
				[
					'post_id' => self::$solution_ids['portfolio'],
				],
			],
			'keywords'                         => [ 'some-keyword', 'another-one', ],
		];

		// First try to save it, without the update flag. It should create a new post (ignoring the post_id), not update.
		$result = $composition_manager->save_composition( [
			'post_id'                          => $id,
			'post_title'                       => 'Testing even Moreeee',
			'post_status'                      => 'private',
			'post_author'                      => self::$user_ids['customer2'],
		] );
		$this->assertNotSame( $id, $result );

		// Not properly update it.
		$result = $composition_manager->save_composition( $expected, true );
		$this->assertSame( $id, $result );

		$composition_data = $composition_manager->get_composition_id_data( $id );
		$this->assertSame( $id, $composition_data['id'] );
		$this->assertSame( $expected['post_title'], $composition_data['name'] );
		$this->assertSame( $expected['post_author'], $composition_data['author'] );
		$this->assertEqualSets( $expected['keywords'], $composition_data['keywords'] );
		$this->assertEqualSets( $expected['user_ids'], array_values( \wp_list_pluck( $composition_data['users'], 'id' ) ) );
		$this->assertEqualSets(
			array_merge(
				[ self::$solution_ids['ecommerce'], self::$solution_ids['presentation'], ],
				array_values( \wp_list_pluck( $expected['required_manual_solutions'], 'post_id' ) )
			),
			array_values( \wp_list_pluck( $composition_data['required_solutions'], 'managed_post_id' ) )
		);
		$this->assertEqualSets(
			$expected['required_purchased_solutions_ids'],
			array_values( \wp_list_pluck( $composition_data['required_purchased_solutions'], 'purchased_solution_id' ) )
		);
		$this->assertEqualSets(
			array_values( \wp_list_pluck( $expected['required_manual_solutions'], 'post_id' ) ),
			array_values( \wp_list_pluck( $composition_data['required_manual_solutions'], 'managed_post_id' ) )
		);
		$this->assertSame( [], $composition_data['composer_require'] );
	}
}
