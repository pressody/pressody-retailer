<?php
declare ( strict_types=1 );

namespace PixelgradeLT\Retailer\Tests\Unit;

use Brain\Monkey\Functions;
use Composer\IO\NullIO;
use Composer\Semver\VersionParser;
use PixelgradeLT\Retailer\Client\ComposerClient;
use PixelgradeLT\Retailer\ComposerVersionParser;
use PixelgradeLT\Retailer\PurchasedSolutionManager;
use PixelgradeLT\Retailer\SolutionManager;

class PurchasedSolutionManagerTest extends TestCase {
	protected $solutionManager = null;
	protected $purchasedSolutionManager = null;

	public function setUp(): void {
		parent::setUp();

		// Mock the WordPress translation functions.
		Functions\stubTranslationFunctions();

		$composer_version_parser = new ComposerVersionParser( new VersionParser() );
		$composer_client         = new ComposerClient();
		$logger                        = new NullIO();

		$this->solutionManager = new SolutionManager( $composer_client, $composer_version_parser, $logger );

		$this->purchasedSolutionManager = new PurchasedSolutionManager( $this->solutionManager, $logger );
	}

	public function test_statuses() {
		$this->assertIsArray( $this->purchasedSolutionManager::$STATUSES );
	}
}
