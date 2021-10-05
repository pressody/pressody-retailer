<?php
declare ( strict_types=1 );

namespace PixelgradeLT\Retailer\Tests\Unit;

use Brain\Monkey\Functions;
use Composer\IO\NullIO;
use Composer\Semver\VersionParser;
use PixelgradeLT\Retailer\Client\ComposerClient;
use PixelgradeLT\Retailer\ComposerVersionParser;
use PixelgradeLT\Retailer\SolutionManager;

class SolutionManagerTest extends TestCase {
	protected $solutionManager = null;
	protected $composer_version_parser = null;
	protected $composer_client = null;

	public function setUp(): void {
		parent::setUp();

		// Mock the WordPress translation functions.
		Functions\stubTranslationFunctions();

		$this->composer_version_parser = new ComposerVersionParser( new VersionParser() );
		$this->composer_client         = new ComposerClient();
		$logger                        = new NullIO();

		$this->solutionManager = new SolutionManager( $this->composer_client, $this->composer_version_parser, $logger );
	}

	public function test_cpt_methods() {
		$this->assertIsArray( $this->solutionManager->get_solution_post_type_args() );
		$this->assertIsArray( $this->solutionManager->get_solution_type_taxonomy_args() );
		$this->assertIsArray( $this->solutionManager->get_solution_category_taxonomy_args() );
		$this->assertIsArray( $this->solutionManager->get_solution_keyword_taxonomy_args() );
	}

	public function test_solution_name_to_composer_package_name() {
		$name     = 'test';
		$expected = 'pixelgradelt-retailer/test';

		$this->assertSame( $expected, $this->solutionManager->solution_name_to_composer_package_name( $name ) );
	}

	public function test_normalize_version() {
		$version  = '1.0';
		$expected = '1.0.0.0';

		$this->assertSame( $expected, $this->solutionManager->normalize_version( $version ) );
	}

	public function test_get_composer_client() {
		$this->assertSame( $this->composer_client, $this->solutionManager->get_composer_client() );
	}

	public function test_get_composer_version_parser() {
		$this->assertSame( $this->composer_version_parser, $this->solutionManager->get_composer_version_parser() );
	}
}
