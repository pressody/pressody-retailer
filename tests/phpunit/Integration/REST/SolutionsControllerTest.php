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
use Pressody\Retailer\SolutionType\SolutionTypes;
use Pressody\Retailer\Tests\Framework\PHPUnitUtil;
use Pressody\Retailer\Tests\Integration\TestCase;

use Psr\Container\ContainerInterface;
use function Pressody\Retailer\local_rest_call;
use function Pressody\Retailer\plugin;

class SolutionsControllerTest extends TestCase {
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

	public function test_get_items() {
		wp_set_current_user( 1 );
		wp_set_auth_cookie( 1, true, false );

		/**
		 * Check for parameter validations.
		 */
		// Single int post ID should be allowed since it is converted to an array.
		$solutions = local_rest_call( '/pressody_retailer/v1/solutions', 'GET', [
			'postId' => self::$post_ids['blog'],
		] );

		$this->assertArrayNotHasKey( 'code', $solutions );
		$this->assertSame( [ 'blog', ], wp_list_pluck( $solutions, 'slug' ) );

		// Invalid post IDs get ignored since wp_parse_id_list() will transform them to 0. We will get back all solutions.
		$solutions = local_rest_call( '/pressody_retailer/v1/solutions', 'GET', [
			'postId' => 'bogus',
		] );

		$this->assertArrayNotHasKey( 'code', $solutions );
		$this->assertSame( [
			'blog',
			'ecommerce',
			'edd',
			'portfolio',
			'presentation',
		], wp_list_pluck( $solutions, 'slug' ) );

		// Use an invalid package name. Should get error since we pattern check.
		$solutions = local_rest_call( '/pressody_retailer/v1/solutions', 'GET', [
			'packageName' => [ 'ecommerce', ],
		] );

		// Should receive error about the parameter.
		$this->assertArrayHasKey( 'code', $solutions );
		$this->assertArrayHasKey( 'message', $solutions );
		$this->assertArrayHasKey( 'data', $solutions );
		$this->assertSame( 'rest_invalid_param', $solutions['code'] );

		/**
		 * Get all solutions.
		 */
		$solutions = local_rest_call( '/pressody_retailer/v1/solutions', 'GET', [] );

		$this->assertArrayNotHasKey( 'code', $solutions );
		$this->assertSame( [
			'blog',
			'ecommerce',
			'edd',
			'portfolio',
			'presentation',
		], wp_list_pluck( $solutions, 'slug' ) );

		/**
		 * Get all solutions via their post IDs.
		 */
		$solutions = local_rest_call( '/pressody_retailer/v1/solutions', 'GET', [
			'postId' => self::$post_ids,
		] );

		$this->assertArrayNotHasKey( 'code', $solutions );
		$this->assertSame( [
			'blog',
			'ecommerce',
			'edd',
			'portfolio',
			'presentation',
		], wp_list_pluck( $solutions, 'slug' ) );

		/**
		 * Get certain solutions via their post slug.
		 */
		$solutions = local_rest_call( '/pressody_retailer/v1/solutions', 'GET', [
			'postSlug' => [ 'edd', 'ecommerce', ],
		] );

		$this->assertArrayNotHasKey( 'code', $solutions );
		$this->assertSame( [ 'ecommerce', 'edd', ], wp_list_pluck( $solutions, 'slug' ) );

		/**
		 * Get solutions by their full Composer package name.
		 */
		$solutions = local_rest_call( '/pressody_retailer/v1/solutions', 'GET', [
			'packageName' => [ 'pressody-retailer/ecommerce', ],
		] );

		$this->assertArrayNotHasKey( 'code', $solutions );
		$this->assertSame( [ 'ecommerce', ], wp_list_pluck( $solutions, 'slug' ) );

		/**
		 * Get solutions by their type.
		 */
		$solutions = local_rest_call( '/pressody_retailer/v1/solutions', 'GET', [
			'type' => SolutionTypes::REGULAR,
		] );

		$this->assertArrayNotHasKey( 'code', $solutions );
		$this->assertSame( [
			'blog',
			'ecommerce',
			'edd',
			'portfolio',
			'presentation',
		], wp_list_pluck( $solutions, 'slug' ) );

		// Use bogus type. Should not get any solution.
		$solutions = local_rest_call( '/pressody_retailer/v1/solutions', 'GET', [
			'type' => [ 'bogus', ],
		] );

		$this->assertSame( [], $solutions );
	}

	public function test_get_processed_items() {
		wp_set_current_user( 1 );
		wp_set_auth_cookie( 1, true, false );

		/**
		 * Check for parameter validations.
		 */
		// Single int post ID should be allowed since it is converted to an array.
		$solutions = local_rest_call( '/pressody_retailer/v1/solutions/processed', 'GET', [
			'postId' => self::$post_ids['blog'],
		] );

		$this->assertArrayNotHasKey( 'code', $solutions );
		$this->assertSame( [ 'blog', ], wp_list_pluck( $solutions, 'slug' ) );

		// Invalid post IDs get ignored since wp_parse_id_list() will transform them to 0. We will get back all solutions processed.
		$solutions = local_rest_call( '/pressody_retailer/v1/solutions/processed', 'GET', [
			'postId' => 'bogus',
		] );

		$this->assertArrayNotHasKey( 'code', $solutions );
		$this->assertSame( [ 'ecommerce', 'edd', 'portfolio', ], wp_list_pluck( $solutions, 'slug' ) );

		// Use an invalid package name. Should get error since we pattern check.
		$solutions = local_rest_call( '/pressody_retailer/v1/solutions/processed', 'GET', [
			'packageName' => [ 'ecommerce', ],
		] );

		// Should receive error about the parameter.
		$this->assertArrayHasKey( 'code', $solutions );
		$this->assertArrayHasKey( 'message', $solutions );
		$this->assertArrayHasKey( 'data', $solutions );
		$this->assertSame( 'rest_invalid_param', $solutions['code'] );

		/**
		 * Get all solutions processed.
		 */
		$solutions = local_rest_call( '/pressody_retailer/v1/solutions/processed', 'GET', [] );

		// Should receive error that one needs to filter the solutions not just processed them whole.
		$this->assertArrayHasKey( 'code', $solutions );
		$this->assertArrayHasKey( 'message', $solutions );
		$this->assertArrayHasKey( 'data', $solutions );
		$this->assertSame( 'pressody_retailer_rest_no_list', $solutions['code'] );

		/**
		 * Get all solutions processed via their post IDs.
		 */
		$solutions = local_rest_call( '/pressody_retailer/v1/solutions/processed', 'GET', [
			'postId' => self::$post_ids,
		] );

		$this->assertArrayNotHasKey( 'code', $solutions );
		$this->assertSame( [ 'ecommerce', 'edd', 'portfolio', ], wp_list_pluck( $solutions, 'slug' ) );

		/**
		 * Get certain solutions via their post slug.
		 */
		// ecommerce requires edd.
		$solutions = local_rest_call( '/pressody_retailer/v1/solutions/processed', 'GET', [
			'postSlug' => [ 'ecommerce', ],
		] );

		$this->assertArrayNotHasKey( 'code', $solutions );
		$this->assertSame( [ 'ecommerce', 'edd', ], wp_list_pluck( $solutions, 'slug' ) );

		/**
		 * Get solutions by their full Composer package name.
		 */
		// ecommerce requires edd.
		$solutions = local_rest_call( '/pressody_retailer/v1/solutions/processed', 'GET', [
			'packageName' => [ 'pressody-retailer/ecommerce', ],
		] );

		$this->assertArrayNotHasKey( 'code', $solutions );
		$this->assertSame( [ 'ecommerce', 'edd', ], wp_list_pluck( $solutions, 'slug' ) );

		/**
		 * Get solutions by their type.
		 */
		$solutions = local_rest_call( '/pressody_retailer/v1/solutions/processed', 'GET', [
			'type' => SolutionTypes::REGULAR,
		] );

		// Should receive error that one needs to filter the solutions not just processed them whole.
		// type is not enough.
		$this->assertArrayHasKey( 'code', $solutions );
		$this->assertArrayHasKey( 'message', $solutions );
		$this->assertArrayHasKey( 'data', $solutions );
		$this->assertSame( 'pressody_retailer_rest_no_list', $solutions['code'] );

		// Use bogus type.
		$solutions = local_rest_call( '/pressody_retailer/v1/solutions/processed', 'GET', [
			'type' => [ 'bogus', ],
		] );

		// Should receive error that one needs to filter the solutions not just processed them whole.
		// type is not enough.
		$this->assertArrayHasKey( 'code', $solutions );
		$this->assertArrayHasKey( 'message', $solutions );
		$this->assertArrayHasKey( 'data', $solutions );
		$this->assertSame( 'pressody_retailer_rest_no_list', $solutions['code'] );

		/**
		 * Process empty list.
		 */
		$solutions = local_rest_call( '/pressody_retailer/v1/solutions/processed', 'GET', [
			'postId' => [ 123456789, ],
		] );

		$this->assertSame( [], $solutions );

		/**
		 * Get solutions with exclude.
		 */
		// ecommerce requires edd and excludes blog.
		// presentation requires blog and excludes ecommerce.
		// We should get back only blog and presentation.
		$solutions = local_rest_call( '/pressody_retailer/v1/solutions/processed', 'GET', [
			'postSlug' => [ 'blog', 'ecommerce', 'portfolio', 'presentation' ],
		] );

		$this->assertArrayNotHasKey( 'code', $solutions );
		$this->assertSame( [ 'ecommerce', 'edd', 'portfolio', ], wp_list_pluck( $solutions, 'slug' ) );

		/**
		 * Get solutions with exclude and context.
		 */
		// ecommerce requires edd and excludes blog.
		// presentation requires blog and excludes ecommerce.
		// The presentation solution should be the first to exclude, thus excluding the ecommerce solution.
		// We should get back only blog and presentation.
		$solutions = local_rest_call( '/pressody_retailer/v1/solutions/processed', 'GET', [
			'postSlug'         => [ 'blog', 'ecommerce', 'portfolio', 'presentation' ],
			'solutionsContext' => [
				'pressody-retailer/presentation' => [
					'timestamp' => 200,
				],
				'pressody-retailer/ecommerce'    => [
					'timestamp' => 100,
				],
				'pressody-retailer/portfolio'    => [
					'timestamp' => 50,
				],
			],
		] );

		$this->assertArrayNotHasKey( 'code', $solutions );
		$this->assertSame( [ 'blog', 'presentation', ], wp_list_pluck( $solutions, 'slug' ) );
	}

	public function test_get_item() {
		wp_set_current_user( 1 );
		wp_set_auth_cookie( 1, true, false );

		/**
		 * Check for existing post ID.
		 */
		$solution = local_rest_call( '/pressody_retailer/v1/solutions/' . self::$post_ids['blog'], 'GET', [] );

		$this->assertArrayNotHasKey( 'code', $solution );
		$this->assertArrayHasKey( 'slug', $solution );
		$this->assertSame( 'blog', $solution['slug'] );

		/**
		 * Check for non-existing post ID.
		 */
		$solution = local_rest_call( '/pressody_retailer/v1/solutions/' . 9999999, 'GET', [] );

		// Should receive error about the parameter.
		$this->assertArrayHasKey( 'code', $solution );
		$this->assertArrayHasKey( 'message', $solution );
		$this->assertArrayHasKey( 'data', $solution );
		$this->assertArrayHasKey( 'status', $solution['data'] );
		$this->assertSame( \WP_Http::NOT_FOUND, $solution['data']['status'] );
		$this->assertSame( 'pressody_retailer_rest_invalid_id', $solution['code'] );

		/**
		 * Check for non-numeric post ID.
		 */
		$solution = local_rest_call( '/pressody_retailer/v1/solutions/asfasdf', 'GET', [] );

		// Should receive error about the parameter.
		$this->assertArrayHasKey( 'code', $solution );
		$this->assertArrayHasKey( 'message', $solution );
		$this->assertArrayHasKey( 'data', $solution );
		$this->assertArrayHasKey( 'status', $solution['data'] );
		$this->assertSame( \WP_Http::NOT_FOUND, $solution['data']['status'] );
		$this->assertSame( 'rest_no_route', $solution['code'] );
	}

	public function test_get_parts() {
		wp_set_current_user( 1 );
		wp_set_auth_cookie( 1, true, false );

		/**
		 * Check for parameter validations.
		 */
		// Single int post ID should be allowed since it is converted to an array.
		// The blog solutions doesn't have any required parts.
		$parts = local_rest_call( '/pressody_retailer/v1/solutions/parts', 'GET', [
			'postId' => self::$post_ids['blog'],
		] );

		$this->assertArrayNotHasKey( 'code', $parts );
		$this->assertSame( [], $parts );

		// Invalid post IDs get ignored since wp_parse_id_list() will transform them to 0.
		// We will get back required parts for all solutions processed.
		$parts = local_rest_call( '/pressody_retailer/v1/solutions/parts', 'GET', [
			'postId' => 'bogus',
		] );

		$this->assertArrayNotHasKey( 'code', $parts );
		$this->assertCount( 2, $parts );

		// Use an invalid package name. Should get error since we pattern check.
		$parts = local_rest_call( '/pressody_retailer/v1/solutions/parts', 'GET', [
			'packageName' => [ 'ecommerce', ],
		] );

		// Should receive error about the parameter.
		$this->assertArrayHasKey( 'code', $parts );
		$this->assertArrayHasKey( 'message', $parts );
		$this->assertArrayHasKey( 'data', $parts );
		$this->assertSame( 'rest_invalid_param', $parts['code'] );

		/**
		 * Get required parts for all solutions processed.
		 */
		$parts = local_rest_call( '/pressody_retailer/v1/solutions/parts', 'GET', [] );

		// Should receive error that one needs to filter the solutions not just processed them whole.
		$this->assertArrayHasKey( 'code', $parts );
		$this->assertArrayHasKey( 'message', $parts );
		$this->assertArrayHasKey( 'data', $parts );
		$this->assertSame( 'pressody_retailer_rest_no_list', $parts['code'] );

		/**
		 * Get required parts for all solutions processed via their post IDs.
		 */
		$parts = local_rest_call( '/pressody_retailer/v1/solutions/parts', 'GET', [
			'postId' => self::$post_ids,
		] );

		$this->assertArrayNotHasKey( 'code', $parts );
		$this->assertCount( 2, $parts );

		/**
		 * Get required parts for certain solutions via their post slug.
		 */
		// ecommerce requires edd.
		$parts = local_rest_call( '/pressody_retailer/v1/solutions/parts', 'GET', [
			'postSlug' => [ 'ecommerce', ],
		] );

		$this->assertArrayNotHasKey( 'code', $parts );
		$this->assertCount( 1, $parts );
		$this->assertSame( 'pressody-records/part_yet-another', $parts[0]['name'] );
		$this->assertSame( '1.2.9', $parts[0]['version'] );
		$this->assertArrayHasKey( 'requiredBy', $parts[0] );

		/**
		 * Get required parts for solutions by their full Composer package name.
		 */
		// ecommerce requires edd.
		$parts = local_rest_call( '/pressody_retailer/v1/solutions/parts', 'GET', [
			'packageName' => [ 'pressody-retailer/ecommerce', ],
		] );

		$this->assertArrayNotHasKey( 'code', $parts );
		$this->assertCount( 1, $parts );
		$this->assertSame( 'pressody-records/part_yet-another', $parts[0]['name'] );
		$this->assertSame( '1.2.9', $parts[0]['version'] );
		$this->assertArrayHasKey( 'requiredBy', $parts[0] );

		/**
		 * Get required parts for solutions by their type.
		 */
		$parts = local_rest_call( '/pressody_retailer/v1/solutions/parts', 'GET', [
			'type' => SolutionTypes::REGULAR,
		] );

		// Should receive error that one needs to filter the solutions not just processed them whole.
		// type is not enough.
		$this->assertArrayHasKey( 'code', $parts );
		$this->assertArrayHasKey( 'message', $parts );
		$this->assertArrayHasKey( 'data', $parts );
		$this->assertSame( 'pressody_retailer_rest_no_list', $parts['code'] );

		// Use bogus type.
		$parts = local_rest_call( '/pressody_retailer/v1/solutions/parts', 'GET', [
			'type' => [ 'bogus', ],
		] );

		// Should receive error that one needs to filter the solutions not just processed them whole.
		// type is not enough.
		$this->assertArrayHasKey( 'code', $parts );
		$this->assertArrayHasKey( 'message', $parts );
		$this->assertArrayHasKey( 'data', $parts );
		$this->assertSame( 'pressody_retailer_rest_no_list', $parts['code'] );

		/**
		 * Get required parts for non-existent solution.
		 */
		$parts = local_rest_call( '/pressody_retailer/v1/solutions/parts', 'GET', [
			'postId' => [ 123456789, ],
		] );

		$this->assertSame( [], $parts );

		/**
		 * Get required parts required by multiple solutions with different version ranges.
		 */
		$parts = local_rest_call( '/pressody_retailer/v1/solutions/parts', 'GET', [
			'postSlug' => [ 'ecommerce', 'portfolio' ],
		] );

		$this->assertArrayNotHasKey( 'code', $parts );
		$this->assertCount( 2, $parts );
		$this->assertSame( 'pressody-records/part_test-test', $parts[0]['name'] );
		$this->assertSame( '^1.0', $parts[0]['version'] );
		$this->assertArrayHasKey( 'requiredBy', $parts[0] );
		$this->assertCount( 1, $parts[0]['requiredBy'] );
		$this->assertSame( 'pressody-records/part_yet-another', $parts[1]['name'] );
		$this->assertSame( '1.2.9, ^2', $parts[1]['version'] );
		$this->assertArrayHasKey( 'requiredBy', $parts[1] );
		$this->assertCount( 2, $parts[1]['requiredBy'] );
	}
}
