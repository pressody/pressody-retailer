<?php
declare ( strict_types=1 );

namespace PixelgradeLT\Retailer\Tests\Unit\SolutionType;

use PixelgradeLT\Retailer\SolutionType\SolutionTypes;
use PixelgradeLT\Retailer\Tests\Unit\TestCase;

class SolutionTypesTest extends TestCase {

	public function test_constants() {
		$this->assertNotEmpty( SolutionTypes::REGULAR );
		$this->assertNotEmpty( SolutionTypes::HOSTING );
		$this->assertNotEmpty( SolutionTypes::DETAILS );
		$this->assertNotEmpty( SolutionTypes::DETAILS[ SolutionTypes::REGULAR ] );
		$this->assertNotEmpty( SolutionTypes::DETAILS[ SolutionTypes::HOSTING ] );
	}
}
