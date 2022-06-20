<?php
declare ( strict_types=1 );

namespace Pressody\Retailer\Tests\Unit\SolutionType;

use Pressody\Retailer\SolutionType\SolutionTypes;
use Pressody\Retailer\Tests\Unit\TestCase;

class SolutionTypesTest extends TestCase {

	public function test_constants() {
		$this->assertNotEmpty( SolutionTypes::REGULAR );
		$this->assertNotEmpty( SolutionTypes::HOSTING );
		$this->assertNotEmpty( SolutionTypes::DETAILS );
		$this->assertNotEmpty( SolutionTypes::DETAILS[ SolutionTypes::REGULAR ] );
		$this->assertNotEmpty( SolutionTypes::DETAILS[ SolutionTypes::HOSTING ] );
	}
}
