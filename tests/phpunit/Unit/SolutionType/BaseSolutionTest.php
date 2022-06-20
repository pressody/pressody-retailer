<?php
declare ( strict_types=1 );

namespace Pressody\Retailer\Tests\Unit\SolutionType;

use Pressody\Retailer\Package;
use Pressody\Retailer\SolutionType\BaseSolution;
use Pressody\Retailer\SolutionType\SolutionTypes;
use Pressody\Retailer\Tests\Unit\TestCase;

class BaseSolutionTest extends TestCase {
	protected $package = null;

	public function setUp(): void {
		parent::setUp();

		$this->package = new class extends BaseSolution {
			public function __set( $name, $value ) {
				$this->$name = $value;
			}
		};
	}

	public function test_implements_package_interface() {
		$this->assertInstanceOf( Package::class, $this->package );
	}

	public function test_name() {
		$expected            = 'Pressody Retailer';
		$this->package->name = $expected;

		$this->assertSame( $expected, $this->package->get_name() );
	}

	public function test_type() {
		$expected            = SolutionTypes::REGULAR;
		$this->package->type = $expected;

		$this->assertSame( $expected, $this->package->get_type() );
	}

	public function test_slug() {
		$expected            = 'pressody_retailer';
		$this->package->slug = $expected;

		$this->assertSame( $expected, $this->package->get_slug() );
	}

	public function test_authors() {
		$expected               = [
			[
				'name'     => 'Pressody',
				'email'    => 'contact@getpressody.com',
				'homepage' => 'https://pixelgrade.com',
				'role'     => 'Maker',
			],
		];
		$this->package->authors = $expected;

		$this->assertSame( $expected, $this->package->get_authors() );
	}

	public function test_description() {
		$expected                   = 'A package description.';
		$this->package->description = $expected;

		$this->assertSame( $expected, $this->package->get_description() );
	}

	public function test_homepage() {
		$expected                = 'https://www.cedaro.com/';
		$this->package->homepage = $expected;

		$this->assertSame( $expected, $this->package->get_homepage() );
	}

	public function test_license() {
		$expected               = 'GPL-2.0-only';
		$this->package->license = $expected;

		$this->assertSame( $expected, $this->package->get_license() );
	}

	public function test_categories() {
		$expected                  = [ 'key0', 'key1', 'key2', 'key3', ];
		$this->package->categories = $expected;

		$this->assertSame( $expected, $this->package->get_categories() );
	}

	public function test_keywords() {
		$expected                = [ 'key0', 'key1', 'key2', 'key3', ];
		$this->package->keywords = $expected;

		$this->assertSame( $expected, $this->package->get_keywords() );
	}

	public function test_is_managed() {
		$expected                  = true;
		$this->package->is_managed = $expected;

		$this->assertSame( $expected, $this->package->is_managed() );
		$this->assertSame( $expected, $this->package->get_is_managed() );
	}

	public function test_managed_post_id() {
		$expected                       = 123;
		$this->package->managed_post_id = $expected;

		$this->assertSame( $expected, $this->package->get_managed_post_id() );
	}

	public function test_visibility() {
		$expected                  = 'public';
		$this->package->visibility = $expected;

		$this->assertSame( $expected, $this->package->get_visibility() );
	}

	public function test_required_pdrecords_parts() {
		$expected                                = [
			'pixelgrade/test' => [
				'package_name'          => 'pixelgrade/test',
				'composer_package_name' => 'pixelgrade/test',
				'version_range'         => '*',
				'stability'             => 'stable',
			],
		];
		$this->package->required_pdrecords_parts = $expected;

		$this->assertSame( $expected, $this->package->get_required_pdrecords_parts() );
		$this->assertTrue( $this->package->has_required_pdrecords_parts() );
	}

	public function test_composer_require() {
		$expected                        = [ 'test/test' => '*' ];
		$this->package->composer_require = $expected;

		$this->assertSame( $expected, $this->package->get_composer_require() );
	}

	public function test_required_solutions() {
		$expected                          = [
			'some_pseudo_id' => [
				'composer_package_name' => 'pixelgrade/test',
				'version_range'         => '*',
				'stability'             => 'stable',
				'managed_post_id'       => 123,
			],
		];
		$this->package->required_solutions = $expected;

		$this->assertSame( $expected, $this->package->get_required_solutions() );
		$this->assertSame( $expected, $this->package->get_required_packages() );
		$this->assertTrue( $this->package->has_required_solutions() );
	}

	public function test_excluded_solutions() {
		$expected                          = [
			'some_pseudo_id' => [
				'composer_package_name' => 'pixelgrade/test',
				'version_range'         => '*',
				'stability'             => 'stable',
				'managed_post_id'       => 123,
			],
		];
		$this->package->excluded_solutions = $expected;

		$this->assertSame( $expected, $this->package->get_excluded_solutions() );
		$this->assertTrue( $this->package->has_excluded_solutions() );
	}

	public function test_composer_package_name() {
		$expected                             = 'pixelgrade/test';
		$this->package->composer_package_name = $expected;

		$this->assertSame( $expected, $this->package->get_composer_package_name() );
	}
}
