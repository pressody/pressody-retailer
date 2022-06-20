<?php
declare ( strict_types=1 );

namespace Pressody\Retailer\Tests\Unit\Transformer;

use Composer\IO\NullIO;
use Pressody\Retailer\SolutionFactory;
use Pressody\Retailer\SolutionManager;
use Pressody\Retailer\SolutionType\SolutionTypes;
use Pressody\Retailer\Tests\Unit\TestCase;
use Pressody\Retailer\Transformer\ComposerSolutionTransformer;

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
		$this->assertSame( 'pressody-retailer/acmecode', $package->get_name() );
	}
}
