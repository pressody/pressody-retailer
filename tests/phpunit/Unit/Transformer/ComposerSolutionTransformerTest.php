<?php
declare ( strict_types = 1 );

namespace PixelgradeLT\Retailer\Tests\Unit\Transformer;

use Composer\IO\NullIO;
use PixelgradeLT\Retailer\SolutionFactory;
use PixelgradeLT\Retailer\SolutionManager;
use PixelgradeLT\Retailer\SolutionType\SolutionTypes;
use PixelgradeLT\Retailer\Tests\Unit\TestCase;
use PixelgradeLT\Retailer\Transformer\ComposerSolutionTransformer;

class ComposerSolutionTransformerTest extends TestCase {
	protected $solution = null;
	protected $transformer = null;

	public function setUp(): void {
		parent::setUp();

		$solution_manager = $this->getMockBuilder( SolutionManager::class )
		                        ->disableOriginalConstructor()
		                        ->getMock();

		$logger = new NullIO();

		$factory = new SolutionFactory( $solution_manager, $logger );

		$this->solution = $factory->create( SolutionTypes::REGULAR )
		                          ->set_slug( 'AcmeCode' )
		                          ->build();

		$this->transformer = new ComposerSolutionTransformer( $factory );
	}

	public function test_package_name_is_lowercased() {
		$package = $this->transformer->transform( $this->solution );
		$this->assertSame( 'pixelgradelt-retailer/acmecode', $package->get_name() );
	}
}
