<?php
declare ( strict_types=1 );

namespace PixelgradeLT\Retailer\Tests\Unit\Repository;

use Brain\Monkey\Functions;
use Composer\IO\NullIO;
use Composer\Semver\VersionParser;
use PixelgradeLT\Retailer\Client\ComposerClient;
use PixelgradeLT\Retailer\ComposerVersionParser;
use PixelgradeLT\Retailer\Repository\Solutions;
use PixelgradeLT\Retailer\SolutionFactory;
use PixelgradeLT\Retailer\SolutionManager;
use PixelgradeLT\Retailer\Tests\Unit\TestCase;

class SolutionsTest extends TestCase {
	protected $solutionsRepository = null;
	protected $solutionManager = null;
	protected $solutionFactory = null;
	protected $composer_version_parser = null;
	protected $composer_client = null;

	public function setUp(): void {
		parent::setUp();

		// Mock the WordPress translation functions.
		Functions\stubTranslationFunctions();

		$logger = new NullIO();

		$this->composer_version_parser = new ComposerVersionParser( new VersionParser() );
		$this->composer_client         = new ComposerClient();

		$this->solutionManager = new SolutionManager( $this->composer_client, $this->composer_version_parser, $logger );
		$this->solutionFactory = new SolutionFactory( $this->solutionManager, $logger );

		$this->solutionsRepository = new Solutions( $this->solutionFactory, $this->solutionManager );
	}

	public function test_get_factory() {
		$this->assertSame( $this->solutionFactory, $this->solutionsRepository->get_factory() );
	}

	public function test_get_solution_manager() {
		$this->assertSame( $this->solutionManager, $this->solutionsRepository->get_solution_manager() );
	}
}
