<?php
declare ( strict_types=1 );

namespace Pressody\Retailer\Tests\Unit\SolutionType\Builder;

use Brain\Monkey\Functions;
use Composer\IO\NullIO;
use Composer\Semver\VersionParser;
use Pressody\Retailer\Client\ComposerClient;
use Pressody\Retailer\ComposerVersionParser;
use Pressody\Retailer\Package;
use Pressody\Retailer\SolutionManager;
use Pressody\Retailer\SolutionType\BaseSolution;
use Pressody\Retailer\SolutionType\Builder\BaseSolutionBuilder;
use Pressody\Retailer\SolutionType\SolutionTypes;
use Pressody\Retailer\Tests\Unit\TestCase;

class BaseSolutionBuilderTest extends TestCase {
	protected ?BaseSolutionBuilder $builder = null;

	public function setUp(): void {
		parent::setUp();

		// Mock the WordPress sanitize_text_field() function.
		Functions\when( 'sanitize_text_field' )->returnArg( 1 );

		// Mock the WordPress get_post_status() function.
		Functions\when( 'get_post_status' )->justReturn( 'publish' );

		// Provide direct getters.
		$package = new class extends BaseSolution {
			public function __get( $name ) {
				return $this->$name;
			}
		};

		$composer_version_parser = new ComposerVersionParser( new VersionParser() );
		$composer_client         = new ComposerClient();
		$logger                  = new NullIO();

		$package_manager = new SolutionManager( $composer_client, $composer_version_parser, $logger );

		$this->builder = new BaseSolutionBuilder( $package, $package_manager, $logger );
	}

	public function test_implements_package_interface() {
		$package = $this->builder->build();

		$this->assertInstanceOf( Package::class, $package );
	}

	public function test_name() {
		$expected = 'Pressody Retailer';
		$package  = $this->builder->set_name( $expected )->build();

		$this->assertSame( $expected, $package->name );
	}

	public function test_type() {
		$expected = SolutionTypes::REGULAR;
		$package  = $this->builder->set_type( $expected )->build();

		$this->assertSame( $expected, $package->type );
	}

	public function test_slug() {
		$expected = 'pressody_retailer';
		$package  = $this->builder->set_slug( $expected )->build();

		$this->assertSame( $expected, $package->slug );
	}

	public function test_authors() {
		$expected = [
			[
				'name'     => 'Pressody',
				'email'    => 'contact@getpressody.com',
				'homepage' => 'https://pixelgrade.com',
				'role'     => 'Maker',
			],
		];
		$package  = $this->builder->set_authors( $expected )->build();

		$this->assertSame( $expected, $package->authors );
	}

	public function test_string_authors() {
		$expected = [
			[ 'name' => 'Pressody', ],
			[ 'name' => 'Wordpressorg', ],
		];

		$authors = [ 'Pixelgrade ', ' Wordpressorg', '' ];

		$package = $this->builder->set_authors( $authors )->build();

		$this->assertSame( $expected, $package->authors );
	}

	public function test_clean_authors() {
		$expected = [
			[ 'name' => 'Pressody', ],
			[ 'name' => 'Wordpressorg', ],
		];

		$authors = [
			'Pressody',
			[],
			'Wordpressorg',
			'',
			[ 'name' => '' ],
			[ 'homepage' => 'https://pixelgrade.com' ],
		];

		$package = $this->builder->set_authors( $authors )->build();

		$this->assertSame( $expected, $package->authors );
	}

	public function test_description() {
		$expected = 'A package description.';
		$package  = $this->builder->set_description( $expected )->build();

		$this->assertSame( $expected, $package->description );
	}

	public function test_homepage() {
		$expected = 'https://www.cedaro.com/';
		$package  = $this->builder->set_homepage( $expected )->build();

		$this->assertSame( $expected, $package->homepage );
	}

	public function test_license_standard() {
		$expected = 'GPL-2.0-only';
		$package  = $this->builder->set_license( $expected )->build();

		$this->assertSame( $expected, $package->license );
	}

	public function test_license_nonstandard() {
		// Some widely used licenses should be normalized to the SPDX format.
		$license_string = 'GNU GPLv2 or later';
		$expected       = 'GPL-2.0-or-later';
		$package        = $this->builder->set_license( $license_string )->build();

		$this->assertSame( $expected, $package->license );
	}

	public function test_license_notknown() {
		// This license won't be normalized in the SPDX format. It will be kept the same.
		$expected = 'Some license 3.0';
		$package  = $this->builder->set_license( $expected )->build();

		$this->assertSame( $expected, $package->license );
	}

	public function test_categories_as_string() {
		$categories_comma_string = 'key1,key0, key2, key3   , ,,';

		// We expect the categories to be alphabetically sorted.
		$expected = [ 'key0', 'key1', 'key2', 'key3', ];
		$package  = $this->builder->set_categories( $categories_comma_string )->build();

		$this->assertSame( $expected, $package->categories );
	}

	public function test_categories_as_array() {
		$categories = [ 'first' => 'key2', 'key3 ', 'some' => 'key0', ' key1 ', ];

		// We expect the categories to be alphabetically sorted.
		$expected = [ 'key0', 'key1', 'key2', 'key3', ];
		$package  = $this->builder->set_categories( $categories )->build();

		$this->assertSame( $expected, $package->categories );
	}

	public function test_clean_categories() {
		$categories = [ 'first' => 'key2', '', 'key3 ', false, 'some' => 'key0', ' key1 ', ];

		// We expect the categories to be alphabetically sorted.
		$expected = [ 'key0', 'key1', 'key2', 'key3', ];
		$package  = $this->builder->set_categories( $categories )->build();

		$this->assertSame( $expected, $package->categories );
	}

	public function test_keywords_as_string() {
		$keywords_comma_string = 'key1,key0, key2, key3   , ,,';

		// We expect the keywords to be alphabetically sorted.
		$expected = [ 'key0', 'key1', 'key2', 'key3', ];
		$package  = $this->builder->set_keywords( $keywords_comma_string )->build();

		$this->assertSame( $expected, $package->keywords );
	}

	public function test_keywords_as_array() {
		$keywords = [ 'first' => 'key2', 'key3 ', 'some' => 'key0', ' key1 ', ];

		// We expect the keywords to be alphabetically sorted.
		$expected = [ 'key0', 'key1', 'key2', 'key3', ];
		$package  = $this->builder->set_keywords( $keywords )->build();

		$this->assertSame( $expected, $package->keywords );
	}

	public function test_clean_keywords() {
		$keywords = [ 'first' => 'key2', '', 'key3 ', false, 'some' => 'key0', ' key1 ', ];

		// We expect the keywords to be alphabetically sorted.
		$expected = [ 'key0', 'key1', 'key2', 'key3', ];
		$package  = $this->builder->set_keywords( $keywords )->build();

		$this->assertSame( $expected, $package->keywords );
	}

	public function test_is_managed() {
		$expected = true;
		$package  = $this->builder->set_is_managed( $expected )->build();

		$this->assertSame( $expected, $package->is_managed );
	}

	public function test_managed_post_id() {
		$expected = 123;
		$package  = $this->builder->set_managed_post_id( $expected )->build();

		$this->assertSame( $expected, $package->managed_post_id );
	}

	public function test_visibility() {
		$expected = 'draft';
		$package  = $this->builder->set_visibility( $expected )->build();

		$this->assertSame( $expected, $package->visibility );
	}

	public function test_required_pdrecords_parts() {
		$expected = [
			'pixelgrade/test' => [
				'package_name'          => 'pixelgrade/test',
				'composer_package_name' => 'pixelgrade/test',
				'version_range'         => '*',
				'stability'             => 'stable',
			],
		];
		$package  = $this->builder->set_required_pdrecords_parts( $expected )->build();

		$this->assertSame( $expected, $package->get_required_pdrecords_parts() );
		$this->assertTrue( $package->has_required_pdrecords_parts() );
	}

	public function test_normalize_required_pdrecords_parts() {
		$expected                 = [
			'pixelgrade/test' => [
				'package_name'          => 'pixelgrade/test',
				'composer_package_name' => 'pixelgrade/test',
				'version_range'         => '*',
				'stability'             => 'stable',
			],
		];
		$required_pdrecords_parts = [
			[ 'package_name' => 'pixelgrade/test', ],
			[ 'composer_package_name' => 'pixelgrade/test2', ],
			// This doesn't have 'package_name' so it should be ignored
		];

		$package = $this->builder->set_required_pdrecords_parts( $required_pdrecords_parts )->build();

		$this->assertSame( $expected, $package->get_required_pdrecords_parts() );
		$this->assertTrue( $package->has_required_pdrecords_parts() );
	}

	public function test_composer_require() {
		$expected = [ 'test/test' => '*' ];
		$package  = $this->builder->set_composer_require( $expected )->build();

		$this->assertSame( $expected, $package->composer_require );
	}

	public function test_required_solutions() {
		$expected = [
			'some_pseudo_id' => [
				'composer_package_name' => 'pixelgrade/test',
				'version_range'         => '*',
				'stability'             => 'stable',
				'managed_post_id'       => 123,
				'pseudo_id'             => 'some_pseudo_id',
			],
		];

		// Provide direct getters.
		$package = new class extends BaseSolution {
			public function __get( $name ) {
				return $this->$name;
			}
		};

		// We need a new builder that uses a mocked SolutionManager since we need to mock its `get_solution_id_data` method.
		$composer_version_parser = new ComposerVersionParser( new VersionParser() );
		$composer_client         = new ComposerClient();
		$logger                  = new NullIO();

		$solution_manager = \Mockery::mock(
			'Pressody\Retailer\SolutionManager',
			'Pressody\Retailer\Manager',
			[ $composer_client, $composer_version_parser, $logger ] )->makePartial();
		$solution_manager->shouldReceive( 'get_solution_id_data' )
		                 ->andReturn( [
			                 'is_managed'      => true,
			                 'managed_post_id' => 123,
		                 ] );
		$builder = new BaseSolutionBuilder( $package, $solution_manager, $logger );

		$built_package = $builder->set_required_solutions( $expected )->build();

		$this->assertSame( $expected, $built_package->get_required_solutions() );
		$this->assertSame( $expected, $built_package->get_required_packages() );
		$this->assertTrue( $built_package->has_required_solutions() );
	}

	public function test_normalize_required_solutions_minimal_details() {
		$required_solutions = [
			[
				'managed_post_id' => 123,
				'pseudo_id'       => 'some_pseudo_id',
			],
		];
		$expected           = [
			'some_pseudo_id' => [
				'composer_package_name' => 'pressody-retailer/test',
				'version_range'         => '*',
				'stability'             => 'stable',
				'managed_post_id'       => 123,
				'pseudo_id'             => 'some_pseudo_id',
			],
		];

		// Provide direct getters.
		$package = new class extends BaseSolution {
			public function __get( $name ) {
				return $this->$name;
			}
		};

		// We need a new builder that uses a mocked SolutionManager since we need to mock its `get_solution_id_data` method.
		$composer_version_parser = new ComposerVersionParser( new VersionParser() );
		$composer_client         = new ComposerClient();
		$logger                  = new NullIO();

		$solution_manager = \Mockery::mock(
			'Pressody\Retailer\SolutionManager',
			'Pressody\Retailer\Manager',
			[ $composer_client, $composer_version_parser, $logger ] )->makePartial();
		$solution_manager->shouldReceive( 'get_solution_id_data' )
		                 ->andReturn( [
			                 'is_managed'      => true,
			                 'managed_post_id' => 123,
			                 'slug'            => 'test',
		                 ] );
		$builder = new BaseSolutionBuilder( $package, $solution_manager, $logger );

		$built_package = $builder->set_required_solutions( $required_solutions )->build();

		$this->assertSame( $expected, $built_package->get_required_solutions() );
		$this->assertSame( $expected, $built_package->get_required_packages() );
		$this->assertTrue( $built_package->has_required_solutions() );
	}

	public function test_normalize_required_solutions_missing_composer_package_name() {
		$required_solutions = [
			[
				'managed_post_id' => 123,
				'version_range'   => '^2.1',
				'stability'       => 'dev',
				'pseudo_id'       => 'some_pseudo_id',
			],
		];
		$expected           = [
			'some_pseudo_id' => [
				'composer_package_name' => 'pressody-retailer/test',
				'version_range'         => '^2.1',
				'stability'             => 'dev',
				'managed_post_id'       => 123,
				'pseudo_id'             => 'some_pseudo_id',
			],
		];

		// Provide direct getters.
		$package = new class extends BaseSolution {
			public function __get( $name ) {
				return $this->$name;
			}
		};

		// We need a new builder that uses a mocked SolutionManager since we need to mock its `get_solution_id_data` method.
		$composer_version_parser = new ComposerVersionParser( new VersionParser() );
		$composer_client         = new ComposerClient();
		$logger                  = new NullIO();

		$solution_manager = \Mockery::mock(
			'Pressody\Retailer\SolutionManager',
			'Pressody\Retailer\Manager',
			[ $composer_client, $composer_version_parser, $logger ] )->makePartial();
		$solution_manager->shouldReceive( 'get_solution_id_data' )
		                 ->andReturn( [
			                 'is_managed'      => true,
			                 'managed_post_id' => 123,
			                 'slug'            => 'Test*', // This should be normalized to
		                 ] );
		$builder = new BaseSolutionBuilder( $package, $solution_manager, $logger );

		$built_package = $builder->set_required_solutions( $required_solutions )->build();

		$this->assertSame( $expected, $built_package->get_required_solutions() );
		$this->assertSame( $expected, $built_package->get_required_packages() );
		$this->assertTrue( $built_package->has_required_solutions() );
	}

	public function test_normalize_required_solutions_missing_pseudo_id() {
		$required_solutions = [
			'some_pseudo_id' => [
				'composer_package_name' => 'pixelgrade/test',
				'version_range'         => '*',
				'stability'             => 'stable',
				'managed_post_id'       => 123,
			],
		];
		$expected           = [
			'some_pseudo_id' => [
				'composer_package_name' => 'pixelgrade/test',
				'version_range'         => '*',
				'stability'             => 'stable',
				'managed_post_id'       => 123,
				'pseudo_id'             => 'some_pseudo_id',
			],
		];

		// Provide direct getters.
		$package = new class extends BaseSolution {
			public function __get( $name ) {
				return $this->$name;
			}
		};

		// We need a new builder that uses a mocked SolutionManager since we need to mock its `get_solution_id_data` method.
		$composer_version_parser = new ComposerVersionParser( new VersionParser() );
		$composer_client         = new ComposerClient();
		$logger                  = new NullIO();

		$solution_manager = \Mockery::mock(
			'Pressody\Retailer\SolutionManager',
			'Pressody\Retailer\Manager',
			[ $composer_client, $composer_version_parser, $logger ] )->makePartial();
		$solution_manager->shouldReceive( 'get_solution_id_data' )
		                 ->andReturn( [
			                 'is_managed'      => true,
			                 'managed_post_id' => 123,
		                 ] );
		$builder = new BaseSolutionBuilder( $package, $solution_manager, $logger );

		$built_package = $builder->set_required_solutions( $required_solutions )->build();

		$this->assertSame( $expected, $built_package->get_required_solutions() );
		$this->assertSame( $expected, $built_package->get_required_packages() );
		$this->assertTrue( $built_package->has_required_solutions() );
	}

	public function test_normalize_required_solutions_missing_pseudo_id_failure() {
		$required_solutions = [
			[
				'composer_package_name' => 'pixelgrade/test',
				'version_range'         => '*',
				'stability'             => 'stable',
				'managed_post_id'       => 123,
			],
		];
		$expected           = [];

		// Provide direct getters.
		$package = new class extends BaseSolution {
			public function __get( $name ) {
				return $this->$name;
			}
		};

		// We need a new builder that uses a mocked SolutionManager since we need to mock its `get_solution_id_data` method.
		$composer_version_parser = new ComposerVersionParser( new VersionParser() );
		$composer_client         = new ComposerClient();
		$logger                  = new NullIO();

		$solution_manager = \Mockery::mock(
			'Pressody\Retailer\SolutionManager',
			'Pressody\Retailer\Manager',
			[ $composer_client, $composer_version_parser, $logger ] )->makePartial();
		$solution_manager->shouldReceive( 'get_solution_id_data' )
		                 ->andReturn( [
			                 'is_managed'      => true,
			                 'managed_post_id' => 123,
		                 ] );
		$builder = new BaseSolutionBuilder( $package, $solution_manager, $logger );

		$built_package = $builder->set_required_solutions( $required_solutions )->build();

		$this->assertSame( $expected, $built_package->get_required_solutions() );
		$this->assertSame( $expected, $built_package->get_required_packages() );
		$this->assertFalse( $built_package->has_required_solutions() );
	}

	public function test_excluded_solutions() {
		$expected = [
			'some_pseudo_id' => [
				'composer_package_name' => 'pixelgrade/test',
				'version_range'         => '*',
				'stability'             => 'stable',
				'managed_post_id'       => 123,
				'pseudo_id'             => 'some_pseudo_id',
			],
		];
		$package  = $this->builder->set_excluded_solutions( $expected )->build();

		$this->assertSame( $expected, $package->get_excluded_solutions() );
		$this->assertTrue( $package->has_excluded_solutions() );
	}

	public function test_composer_package_name() {
		$expected = 'pixelgrade/test';
		$package  = $this->builder->set_composer_package_name( $expected )->build();

		$this->assertSame( $expected, $package->get_composer_package_name() );
	}

	public function test_from_manager_missing_package_data() {
		// Provide direct getters.
		$package = new class extends BaseSolution {
			public function __get( $name ) {
				return $this->$name;
			}
		};

		// We need a new builder that uses a mocked SolutionManager since we need to mock its `get_solution_id_data` method.
		$composer_version_parser = new ComposerVersionParser( new VersionParser() );
		$composer_client         = new ComposerClient();
		$logger                  = new NullIO();

		$solution_manager = \Mockery::mock(
			'Pressody\Retailer\SolutionManager',
			'Pressody\Retailer\Manager',
			[ $composer_client, $composer_version_parser, $logger ] )->makePartial();
		$solution_manager->shouldReceive( 'get_solution_id_data' )
		                 ->andReturn( [] );
		$solution_manager->shouldReceive( 'get_solution_data_by' )
		                 ->andReturn( [] );
		$builder = new BaseSolutionBuilder( $package, $solution_manager, $logger );

		$built_package = $builder->from_manager( 123 )->build();

		$this->assertFalse( $built_package->is_managed() );
	}

	public function test_from_package_data() {
		$expected['name']                     = 'Solution Name';
		$expected['slug']                     = 'slug';
		$expected['type']                     = SolutionTypes::REGULAR;
		$expected['authors']                  = [
			[
				'name'     => 'Name',
				'email'    => 'email@example.com',
				'homepage' => 'https://pixelgrade.com',
				'role'     => 'Dev',
			],
		];
		$expected['homepage']                 = 'https://pixelgrade.com';
		$expected['description']              = 'Some description.';
		$expected['keywords']                 = [ 'keyword' ];
		$expected['license']                  = 'GPL-2.0-or-later';
		$expected['is_managed']               = true;
		$expected['managed_post_id']          = 234;
		$expected['visibility']               = 'draft';
		$expected['composer_require']         = [ 'test/test' => '*' ];
		$expected['required_solutions']       = [
			'some_pseudo_id' => [
				'composer_package_name' => 'pixelgrade/test',
				'version_range'         => '*',
				'stability'             => 'stable',
				'managed_post_id'       => 123,
				'pseudo_id'             => 'some_pseudo_id',
			],
		];
		$expected['excluded_solutions']       = [
			'some_pseudo_id2' => [
				'composer_package_name' => 'pixelgrade/test2',
				'version_range'         => '*',
				'stability'             => 'stable',
				'managed_post_id'       => 345,
				'pseudo_id'             => 'some_pseudo_id2',
			],
		];
		$expected['required_pdrecords_parts'] = [
			'pixelgrade/part2' => [
				'package_name'          => 'pixelgrade/part2',
				'composer_package_name' => 'pixelgrade/part2',
				'version_range'         => '*',
				'stability'             => 'stable',
			],
			'pixelgrade/part3' => [
				'package_name'          => 'pixelgrade/part3',
				'composer_package_name' => 'pixelgrade/part3',
				'version_range'         => '*',
				'stability'             => 'stable',
			],
		];

		$package = $this->builder->from_package_data( $expected )->build();

		$this->assertSame( $expected['name'], $package->name );
		$this->assertSame( $expected['slug'], $package->slug );
		$this->assertSame( $expected['type'], $package->type );
		$this->assertSame( $expected['authors'], $package->authors );
		$this->assertSame( $expected['homepage'], $package->homepage );
		$this->assertSame( $expected['description'], $package->description );
		$this->assertSame( $expected['keywords'], $package->keywords );
		$this->assertSame( $expected['license'], $package->license );
		$this->assertSame( $expected['is_managed'], $package->is_managed );
		$this->assertSame( $expected['managed_post_id'], $package->managed_post_id );
		$this->assertSame( $expected['visibility'], $package->visibility );
		$this->assertSame( $expected['composer_require'], $package->composer_require );
		$this->assertSame( $expected['required_solutions'], $package->required_solutions );
		$this->assertSame( $expected['excluded_solutions'], $package->excluded_solutions );
		$this->assertSame( $expected['required_pdrecords_parts'], $package->required_pdrecords_parts );
	}

	public function test_from_package_data_do_not_overwrite() {
		$expected                   = new class extends BaseSolution {
			public function __get( $name ) {
				return $this->$name;
			}

			public function __set( $name, $value ) {
				$this->$name = $value;
			}
		};
		$expected->name             = 'Solution';
		$expected->slug             = 'solution-slug';
		$expected->type             = SolutionTypes::REGULAR;
		$expected->authors          = [
			[
				'name' => 'Some Theme Author',
			],
		];
		$expected->homepage         = 'https://pressody.com';
		$expected->description      = 'Some awesome description.';
		$expected->keywords         = [ 'keyword1', 'keyword2' ];
		$expected->license          = 'GPL-2.0-only';
		$expected->managed_post_id  = 123;
		$expected->visibility       = 'draft';
		$expected->composer_require = [ 'test/test' => '*' ];

		$package_data['name']             = 'Solution Name';
		$package_data['slug']             = 'slug';
		$package_data['type']             = SolutionTypes::REGULAR;
		$package_data['authors']          = [];
		$package_data['homepage']         = 'https://pixelgrade.com';
		$package_data['description']      = 'Some description.';
		$package_data['keywords']         = [ 'keyword' ];
		$package_data['license']          = 'GPL-2.0-or-later';
		$package_data['managed_post_id']  = 234;
		$package_data['visibility']       = 'public';
		$package_data['composer_require'] = [ 'test2/test2' => '*' ];

		$package = $this->builder->with_package( $expected )->from_package_data( $package_data )->build();

		$this->assertSame( $expected->name, $package->name );
		$this->assertSame( $expected->slug, $package->slug );
		$this->assertSame( $expected->type, $package->type );
		$this->assertSame( $expected->authors, $package->authors );
		$this->assertSame( $expected->homepage, $package->homepage );
		$this->assertSame( $expected->description, $package->description );
		$this->assertSame( $expected->keywords, $package->keywords );
		$this->assertSame( $expected->license, $package->license );
		$this->assertSame( $expected->managed_post_id, $package->managed_post_id );
		$this->assertSame( $expected->visibility, $package->visibility );
		$this->assertSame( $expected->composer_require, $package->composer_require );
	}

	public function test_from_package_data_merge_required_solutions() {
		$initial_package                     = new class extends BaseSolution {
			public function __get( $name ) {
				return $this->$name;
			}

			public function __set( $name, $value ) {
				$this->$name = $value;
			}
		};
		$initial_package->name               = 'Solution';
		$initial_package->slug               = 'solution-slug';
		$initial_package->required_solutions = [
			'some_pseudo_id' => [
				'composer_package_name' => 'pixelgrade/test',
				'version_range'         => '*',
				'stability'             => 'stable',
				'managed_post_id'       => 123,
				'pseudo_id'             => 'some_pseudo_id',
			],
		];

		$package_data['name']               = 'Plugin Name';
		$package_data['slug']               = 'slug';
		$package_data['required_solutions'] = [
			[
				'composer_package_name' => 'pixelgrade/test2',
				'version_range'         => '1.1',
				'stability'             => 'dev',
				'managed_post_id'       => 234,
				'pseudo_id'             => 'some_pseudo_id2',
			],
		];

		$expected = [
			'some_pseudo_id'  => [
				'composer_package_name' => 'pixelgrade/test',
				'version_range'         => '*',
				'stability'             => 'stable',
				'managed_post_id'       => 123,
				'pseudo_id'             => 'some_pseudo_id',
			],
			'some_pseudo_id2' => [
				'composer_package_name' => 'pixelgrade/test2',
				'version_range'         => '1.1',
				'stability'             => 'dev',
				'managed_post_id'       => 234,
				'pseudo_id'             => 'some_pseudo_id2',
			],
		];

		$package = $this->builder->with_package( $initial_package )->from_package_data( $package_data )->build();

		$this->assertSame( $expected, $package->required_solutions );
	}

	public function test_from_package_data_merge_overwrite_required_solutions() {
		$initial_package                     = new class extends BaseSolution {
			public function __get( $name ) {
				return $this->$name;
			}

			public function __set( $name, $value ) {
				$this->$name = $value;
			}
		};
		$initial_package->name               = 'Solution';
		$initial_package->slug               = 'solution-slug';
		$initial_package->required_solutions = [
			'some_pseudo_id' => [
				'composer_package_name' => 'pixelgrade/test',
				'version_range'         => '*',
				'stability'             => 'stable',
				'managed_post_id'       => 123,
				'pseudo_id'             => 'some_pseudo_id',
			],
		];

		$package_data['name']               = 'Plugin Name';
		$package_data['slug']               = 'slug';
		$package_data['required_solutions'] = [
			[
				'composer_package_name' => 'pixelgrade/test2',
				'version_range'         => '1.1',
				'stability'             => 'dev',
				'managed_post_id'       => 234,
				'pseudo_id'             => 'some_pseudo_id',
			],
			[
				'composer_package_name' => 'pixelgrade/test3',
				'version_range'         => '1.1',
				'stability'             => 'dev',
				'managed_post_id'       => 234,
				'pseudo_id'             => 'some_pseudo_id3',
			],
		];

		$expected = [
			'some_pseudo_id'  => [
				'composer_package_name' => 'pixelgrade/test2',
				'version_range'         => '1.1',
				'stability'             => 'dev',
				'managed_post_id'       => 234,
				'pseudo_id'             => 'some_pseudo_id',
			],
			'some_pseudo_id3' => [
				'composer_package_name' => 'pixelgrade/test3',
				'version_range'         => '1.1',
				'stability'             => 'dev',
				'managed_post_id'       => 234,
				'pseudo_id'             => 'some_pseudo_id3',
			],
		];

		$package = $this->builder->with_package( $initial_package )->from_package_data( $package_data )->build();

		$this->assertSame( $expected, $package->required_solutions );
	}

	public function test_with_package() {
		$expected                           = new class extends BaseSolution {
			public function __get( $name ) {
				return $this->$name;
			}

			public function __set( $name, $value ) {
				$this->$name = $value;
			}
		};
		$expected->name                     = 'Solution Name';
		$expected->slug                     = 'slug';
		$expected->type                     = SolutionTypes::REGULAR;
		$expected->authors                  = [];
		$expected->homepage                 = 'https://pixelgrade.com';
		$expected->description              = 'Some description.';
		$expected->keywords                 = [ 'keyword' ];
		$expected->license                  = 'GPL-2.0-or-later';
		$expected->is_managed               = true;
		$expected->managed_post_id          = 123;
		$expected->visibility               = 'draft';
		$expected->composer_require         = [ 'test/test' => '*' ];
		$expected->required_solutions       = [
			'some_pseudo_id' => [
				'composer_package_name' => 'pixelgrade/test',
				'version_range'         => '*',
				'stability'             => 'stable',
				'managed_post_id'       => 123,
				'pseudo_id'             => 'some_pseudo_id',
			],
		];
		$expected->excluded_solutions       = [
			'some_pseudo_id2' => [
				'composer_package_name' => 'pixelgrade/test2',
				'version_range'         => '*',
				'stability'             => 'stable',
				'managed_post_id'       => 345,
				'pseudo_id'             => 'some_pseudo_id2',
			],
		];
		$expected->required_pdrecords_parts = [
			'pixelgrade/part2' => [
				'package_name'          => 'pixelgrade/part2',
				'composer_package_name' => 'pixelgrade/part2',
				'version_range'         => '*',
				'stability'             => 'stable',
			],
			'pixelgrade/part3' => [
				'package_name'          => 'pixelgrade/part3',
				'composer_package_name' => 'pixelgrade/part3',
				'version_range'         => '*',
				'stability'             => 'stable',
			],
		];

		$package = $this->builder->with_package( $expected )->build();

		$this->assertSame( $expected->name, $package->name );
		$this->assertSame( $expected->slug, $package->slug );
		$this->assertSame( $expected->type, $package->type );
		$this->assertSame( $expected->authors, $package->authors );
		$this->assertSame( $expected->homepage, $package->homepage );
		$this->assertSame( $expected->description, $package->description );
		$this->assertSame( $expected->keywords, $package->keywords );
		$this->assertSame( $expected->license, $package->license );
		$this->assertSame( $expected->is_managed, $package->is_managed );
		$this->assertSame( $expected->managed_post_id, $package->managed_post_id );
		$this->assertSame( $expected->visibility, $package->visibility );
		$this->assertSame( $expected->composer_require, $package->composer_require );
		$this->assertSame( $expected->required_solutions, $package->required_solutions );
		$this->assertSame( $expected->excluded_solutions, $package->excluded_solutions );
		$this->assertSame( $expected->required_pdrecords_parts, $package->required_pdrecords_parts );
	}
}
