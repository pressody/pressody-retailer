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

namespace Pressody\Retailer\Tests\Integration\Repository;

use Pressody\Retailer\SolutionType\BaseSolution;
use Pressody\Retailer\SolutionType\SolutionTypes;
use Pressody\Retailer\Tests\Framework\PHPUnitUtil;
use Pressody\Retailer\Tests\Integration\TestCase;

use Psr\Container\ContainerInterface;
use function Pressody\Retailer\plugin;

class SolutionsTest extends TestCase {
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
				'post_type'   => self::$container['solution.manager']::POST_TYPE,
				'tax_input'   => [
					self::$container['solution.manager']::TYPE_TAXONOMY    => [ $package_type->term_id ],
					self::$container['solution.manager']::KEYWORD_TAXONOMY => 'keyword1, keyword2, keyword3',
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

		// First, create the test pdsolutions posts that will be dependencies to other posts that we test.
		$dep_post_ids = [];
		foreach ( self::$dep_posts_data as $key => $data ) {
			$dep_post_ids[ $key ] = $factory->post->create_object( $data );
		}

		self::$posts_data = [
			'ecommerce' => [
				'post_title'  => 'Ecommerce',
				'post_status' => 'publish',
				'post_name'   => 'ecommerce',
				'post_type'   => self::$container['solution.manager']::POST_TYPE,
				'tax_input'   => [
					self::$container['solution.manager']::TYPE_TAXONOMY    => [ $package_type->term_id ],
					self::$container['solution.manager']::KEYWORD_TAXONOMY => 'keyword1, keyword2, keyword3',
				],
				'meta_input'  => [
					'_solution_details_description'                    => 'Package custom description.',
					'_solution_details_longdescription'                => '<h2>Awesome solution</h2>
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
					'_solution_excluded_solutions|||0|value'           => '_',
					'_solution_excluded_solutions|pseudo_id|0|0|value' => 'edd #' . $dep_post_ids['edd'],
				],
			],
		];

		$post_ids = [];
		foreach ( self::$posts_data as $key => $data ) {
			$post_ids[ $key ] = $factory->post->create_object( $data );
		}
	}

	public static function wpTearDownAfterClass() {
	}

	public function test_get_non_existent_solution() {
		/** @var \Pressody\Retailer\Repository\Solutions $repository */
		$repository = plugin()->get_container()['repository.solutions'];
		$repository->reinitialize();

		$package = $repository->first_where( [ 'slug' => 'something-not-here' ] );
		$this->assertNull( $package );
	}

	public function test_get_solution_with_required_parts() {
		/** @var \Pressody\Retailer\Repository\Solutions $repository */
		$repository = plugin()->get_container()['repository.solutions'];
		$repository->reinitialize();

		$package = $repository->first_where( [ 'slug' => 'ecommerce', ] );

		$this->assertInstanceOf( BaseSolution::class, $package );

		$this->assertCount( 0, $package->get_authors() );
		$this->assertSame( 'Package custom description.', $package->get_description() );
		$this->assertSame( 'https://package.homepage', $package->get_homepage() );
		$this->assertSame( SolutionTypes::REGULAR, $package->get_type() );
		$this->assertCount( 3, $package->get_keywords() );
		$this->assertTrue( $package->has_required_pdrecords_parts() );
		$this->assertCount( 1, $package->get_required_pdrecords_parts() );
		$this->assertTrue( $package->has_required_solutions() );
		$this->assertCount( 1, $package->get_required_solutions() );
		$this->assertTrue( $package->has_excluded_solutions() );
		$this->assertCount( 1, $package->get_excluded_solutions() );
	}
}
