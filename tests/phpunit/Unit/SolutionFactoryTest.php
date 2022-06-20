<?php
declare ( strict_types=1 );

namespace Pressody\Retailer\Tests\Unit;

use Brain\Monkey\Functions;
use Composer\IO\NullIO;
use Composer\Semver\VersionParser;
use Pressody\Retailer\Client\ComposerClient;
use Pressody\Retailer\ComposerVersionParser;
use Pressody\Retailer\SolutionFactory;
use Pressody\Retailer\SolutionManager;

class SolutionFactoryTest extends TestCase {
	protected $solutionFactory = null;

	public function setUp(): void {
		parent::setUp();

		// Mock the WordPress translation functions.
		Functions\stubTranslationFunctions();

		$logger = new NullIO();

		$composer_version_parser = new ComposerVersionParser( new VersionParser() );
		$composer_client         = new ComposerClient();

		$solutionManager = new SolutionManager( $composer_client, $composer_version_parser, $logger );

		$this->solutionFactory = new SolutionFactory( $solutionManager, $logger );
	}

	public function test_create() {
		$this->assertInstanceOf( 'Pressody\Retailer\SolutionType\Builder\BaseSolutionBuilder', $this->solutionFactory->create( 'regular' ) );
	}
}
