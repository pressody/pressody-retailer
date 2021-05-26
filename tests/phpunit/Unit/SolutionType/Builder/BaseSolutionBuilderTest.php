<?php
declare ( strict_types=1 );

namespace PixelgradeLT\Retailer\Tests\Unit\SolutionType\Builder;

use Brain\Monkey\Functions;
use Composer\IO\NullIO;
use Composer\Semver\VersionParser;
use PixelgradeLT\Retailer\Client\ComposerClient;
use PixelgradeLT\Retailer\ComposerVersionParser;
use PixelgradeLT\Retailer\Package;
use PixelgradeLT\Retailer\SolutionManager;
use PixelgradeLT\Retailer\SolutionType\BaseSolution;
use PixelgradeLT\Retailer\SolutionType\Builder\BaseSolutionBuilder;
use PixelgradeLT\Retailer\SolutionType\SolutionTypes;
use PixelgradeLT\Retailer\Tests\Unit\TestCase;

class BaseSolutionBuilderTest extends TestCase {
	protected $builder = null;

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
		$expected = 'PixelgradeLT Retailer';
		$package  = $this->builder->set_name( $expected )->build();

		$this->assertSame( $expected, $package->name );
	}

	public function test_type() {
		$expected = SolutionTypes::BASIC;
		$package  = $this->builder->set_type( $expected )->build();

		$this->assertSame( $expected, $package->type );
	}

	public function test_slug() {
		$expected = 'pixelgradelt_retailer';
		$package  = $this->builder->set_slug( $expected )->build();

		$this->assertSame( $expected, $package->slug );
	}

	public function test_authors() {
		$expected = [
			[
				'name'     => 'Pixelgrade',
				'email'    => 'contact@pixelgrade.com',
				'homepage' => 'https://pixelgrade.com',
				'role'     => 'Maker',
			],
		];
		$package  = $this->builder->set_authors( $expected )->build();

		$this->assertSame( $expected, $package->authors );
	}

	public function test_string_authors() {
		$expected = [
			[ 'name' => 'Pixelgrade', ],
			[ 'name' => 'Wordpressorg', ],
		];

		$authors = [ 'Pixelgrade ', ' Wordpressorg', '' ];

		$package = $this->builder->set_authors( $authors )->build();

		$this->assertSame( $expected, $package->authors );
	}

	public function test_clean_authors() {
		$expected = [
			[ 'name' => 'Pixelgrade', ],
			[ 'name' => 'Wordpressorg', ],
		];

		$authors = [
			'Pixelgrade',
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

	public function test_composer_require() {
		$expected = [ 'test/test' => '*' ];
		$package  = $this->builder->set_composer_require( $expected )->build();

		$this->assertSame( $expected, $package->composer_require );
	}

	public function test_from_package_data() {
		$expected['name']                     = 'Solution Name';
		$expected['slug']                     = 'slug';
		$expected['type']                     = SolutionTypes::BASIC;
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
		$expected['required_ltrecords_parts'] = [
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
		$this->assertSame( $expected['required_ltrecords_parts'], $package->required_ltrecords_parts );
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
		$expected->type             = SolutionTypes::BASIC;
		$expected->authors          = [
			[
				'name' => 'Some Theme Author',
			],
		];
		$expected->homepage         = 'https://pixelgradelt.com';
		$expected->description      = 'Some awesome description.';
		$expected->keywords         = [ 'keyword1', 'keyword2' ];
		$expected->license          = 'GPL-2.0-only';
		$expected->managed_post_id  = 123;
		$expected->visibility       = 'draft';
		$expected->composer_require = [ 'test/test' => '*' ];

		$package_data['name']             = 'Solution Name';
		$package_data['slug']             = 'slug';
		$package_data['type']             = SolutionTypes::BASIC;
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

		$package_data['name']              = 'Plugin Name';
		$package_data['slug']              = 'slug';
		$package_data['required_solutions'] = [
			'some_pseudo_id2' => [
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

		$package_data['name']              = 'Plugin Name';
		$package_data['slug']              = 'slug';
		$package_data['required_solutions'] = [
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
		$expected->type                     = SolutionTypes::BASIC;
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
		$expected->required_ltrecords_parts = [
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
		$this->assertSame( $expected->required_ltrecords_parts, $package->required_ltrecords_parts );
	}
}
