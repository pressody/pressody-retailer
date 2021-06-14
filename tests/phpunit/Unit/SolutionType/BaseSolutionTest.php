<?php
declare ( strict_types=1 );

namespace PixelgradeLT\Retailer\Tests\Unit\SolutionType;

use PixelgradeLT\Retailer\Package;
use PixelgradeLT\Retailer\SolutionType\BaseSolution;
use PixelgradeLT\Retailer\SolutionType\SolutionTypes;
use PixelgradeLT\Retailer\Tests\Unit\TestCase;

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
		$expected            = 'PixelgradeLT Retailer';
		$this->package->name = $expected;

		$this->assertSame( $expected, $this->package->get_name() );
	}

	public function test_type() {
		$expected            = SolutionTypes::BASIC;
		$this->package->type = $expected;

		$this->assertSame( $expected, $this->package->get_type() );
	}

	public function test_slug() {
		$expected            = 'pixelgradelt_retailer';
		$this->package->slug = $expected;

		$this->assertSame( $expected, $this->package->get_slug() );
	}

	public function test_authors() {
		$expected               = [
			[
				'name'     => 'Pixelgrade',
				'email'    => 'contact@pixelgrade.com',
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
		$this->assertTrue( $this->package->has_required_solutions() );
	}
}