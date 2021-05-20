<?php
declare ( strict_types = 1 );

namespace PixelgradeLT\Retailer\Tests\Unit\PackageType;

use Composer\Semver\Constraint\MatchAllConstraint;
use PixelgradeLT\Retailer\Exception\PackageNotInstalled;
use PixelgradeLT\Retailer\Solution;
use PixelgradeLT\Retailer\SolutionType\BaseSolution;
use PixelgradeLT\Retailer\SolutionType\ExternalBasePackage;
use PixelgradeLT\Retailer\Tests\Unit\TestCase;

class ExternalBasePackageTest extends TestCase {
	protected $package = null;

	public function setUp(): void {
		parent::setUp();

		$this->package = new class extends ExternalBasePackage {
			public function __set( $name, $value ) {
				$this->$name = $value;
			}
		};
	}

	public function test_implements_package_interface() {
		$this->assertInstanceOf( Solution::class, $this->package );
	}

	public function test_extends_base_package() {
		$this->assertInstanceOf( BaseSolution::class, $this->package );
	}

	public function test_source_constraint() {
		$expected = new MatchAllConstraint();
		$this->package->source_constraint = $expected;

		$this->assertSame( $expected, $this->package->get_source_constraint() );
		$this->assertTrue( $this->package->has_source_constraint() );
	}
}
