<?php
declare ( strict_types = 1 );

namespace PixelgradeLT\Retailer\Tests\Unit\Transformer;

use Composer\IO\NullIO;
use PixelgradeLT\Retailer\Archiver;
use PixelgradeLT\Retailer\SolutionFactory;
use PixelgradeLT\Retailer\SolutionManager;
use PixelgradeLT\Retailer\SolutionType\SolutionTypes;
use PixelgradeLT\Retailer\ReleaseManager;
use PixelgradeLT\Retailer\Transformer\ComposerPackageTransformer;
use PixelgradeLT\Retailer\Tests\Unit\TestCase;
use Psr\Log\NullLogger;

class ComposerPackageTransformerTest extends TestCase {
	protected $package = null;
	protected $transformer = null;

	public function setUp(): void {
		parent::setUp();

		$package_manager = $this->getMockBuilder( SolutionManager::class )
		                        ->disableOriginalConstructor()
		                        ->getMock();

		$release_manager = $this->getMockBuilder( ReleaseManager::class )
		                        ->disableOriginalConstructor()
		                        ->getMock();

		$archiver                = new Archiver( new NullLogger() );
		$logger = new NullIO();

		$factory = new SolutionFactory( $package_manager, $release_manager, $archiver, $logger );

		$this->package = $factory->create( SolutionTypes::PLUGIN )
			->set_slug( 'AcmeCode' )
			->build();

		$this->transformer = new ComposerPackageTransformer( $factory );
	}

	public function test_package_name_is_lowercased() {
		$package = $this->transformer->transform( $this->package );
		$this->assertSame( 'pixelgradelt_retailer/acmecode', $package->get_name() );
	}
}
