<?php
declare ( strict_types=1 );

namespace Pressody\Retailer\Tests\Unit;

use Brain\Monkey\Functions;
use Composer\IO\NullIO;
use Composer\Semver\VersionParser;
use Pressody\Retailer\Client\ComposerClient;
use Pressody\Retailer\ComposerVersionParser;
use Pressody\Retailer\CompositionManager;
use Pressody\Retailer\PurchasedSolutionManager;
use Pressody\Retailer\Repository\Solutions;
use Pressody\Retailer\SolutionFactory;
use Pressody\Retailer\SolutionManager;
use Pressody\Retailer\StringHashes;

class CompositionManagerTest extends TestCase {
	protected $compositionManager = null;
	protected $solutions_repository = null;
	protected $purchased_solutions_manager = null;
	protected $composer_version_parser = null;
	protected $composer_client = null;
	protected $hasher = null;

	public function setUp(): void {
		parent::setUp();

		// Mock the WordPress translation functions.
		Functions\stubTranslationFunctions();

		$this->purchased_solutions_manager = $this->getMockBuilder( PurchasedSolutionManager::class )
		                                          ->disableOriginalConstructor()
		                                          ->getMock();

		$logger = new NullIO();

		$this->composer_version_parser = new ComposerVersionParser( new VersionParser() );
		$this->composer_client         = new ComposerClient();
		$this->hasher                  = new StringHashes();

		$solutionManager = new SolutionManager( $this->composer_client, $this->composer_version_parser, $logger );
		$solutionFactory = new SolutionFactory( $solutionManager, $logger );

		$this->solutions_repository = new Solutions( $solutionFactory, $solutionManager );

		$this->compositionManager = new CompositionManager(
			$this->solutions_repository,
			$this->purchased_solutions_manager,
			$this->composer_client,
			$this->composer_version_parser,
			$logger,
			$this->hasher
		);
	}

	public function test_statuses() {
		$this->assertIsArray( $this->compositionManager::$STATUSES );
	}

	public function test_cpt_args_methods() {
		$this->assertIsArray( $this->compositionManager->get_composition_post_type_args() );
		$this->assertIsArray( $this->compositionManager->get_solution_keyword_taxonomy_args() );
	}

	public function test_unique_required_solutions_list_single_solution() {
		$required_solutions = [
			'some_pseudo_id' => [
				'composer_package_name' => 'pixelgrade/test',
				'version_range'         => '*',
				'stability'             => 'stable',
				'managed_post_id'       => 123,
			],
		];
		$expected           = [
			[
				'composer_package_name' => 'pixelgrade/test',
				'version_range'         => '*',
				'stability'             => 'stable',
				'managed_post_id'       => 123,
			],
		];

		$this->assertSame( $expected, $this->compositionManager->unique_required_solutions_list( $required_solutions ) );
	}

	public function test_unique_required_solutions_list_order_by_managed_post_id() {
		$required_solutions = [
			[
				'composer_package_name' => 'pixelgrade/test123',
				'version_range'         => '*',
				'stability'             => 'stable',
				'managed_post_id'       => 123,
			],
			[
				'composer_package_name' => 'pixelgrade/test12',
				'version_range'         => '*',
				'stability'             => 'stable',
				'managed_post_id'       => 12,
			],
		];
		$expected           = [
			[
				'composer_package_name' => 'pixelgrade/test12',
				'version_range'         => '*',
				'stability'             => 'stable',
				'managed_post_id'       => 12,
			],
			[
				'composer_package_name' => 'pixelgrade/test123',
				'version_range'         => '*',
				'stability'             => 'stable',
				'managed_post_id'       => 123,
			],
		];

		$this->assertSame( $expected, $this->compositionManager->unique_required_solutions_list( $required_solutions ) );
	}

	public function test_unique_required_solutions_list_uniqueness() {
		$required_solutions = [
			[
				'composer_package_name' => 'pixelgrade/test123',
				'version_range'         => '*',
				'stability'             => 'stable',
				'managed_post_id'       => 123,
			],
			[
				'composer_package_name' => 'pixelgrade/test12',
				'version_range'         => '*',
				'stability'             => 'stable',
				'managed_post_id'       => 12,
			],
			[
				'composer_package_name' => 'pixelgrade/test123-second',
				'version_range'         => '*',
				'stability'             => 'stable',
				'managed_post_id'       => 123,
			],
		];
		$expected           = [
			[
				'composer_package_name' => 'pixelgrade/test12',
				'version_range'         => '*',
				'stability'             => 'stable',
				'managed_post_id'       => 12,
			],
			[
				'composer_package_name' => 'pixelgrade/test123-second',
				'version_range'         => '*',
				'stability'             => 'stable',
				'managed_post_id'       => 123,
			],
		];

		$this->assertSame( $expected, $this->compositionManager->unique_required_solutions_list( $required_solutions ) );
	}

	public function test_hash_encode_id() {
		$id       = 123;
		$expected = $this->hasher->encode( $id );

		$this->assertSame( $expected, $this->compositionManager->hash_encode_id( $id ) );
	}

	public function test_hash_decode_id() {
		$hashid   = $this->hasher->encode( 123 );
		$expected = 123;

		$this->assertSame( $expected, $this->compositionManager->hash_decode_id( $hashid ) );
	}

	public function test_normalize_version() {
		$version  = '1.0';
		$expected = '1.0.0.0';

		$this->assertSame( $expected, $this->compositionManager->normalize_version( $version ) );
	}

	public function test_get_composer_client() {
		$this->assertSame( $this->composer_client, $this->compositionManager->get_composer_client() );
	}

	public function test_get_composer_version_parser() {
		$this->assertSame( $this->composer_version_parser, $this->compositionManager->get_composer_version_parser() );
	}
}
