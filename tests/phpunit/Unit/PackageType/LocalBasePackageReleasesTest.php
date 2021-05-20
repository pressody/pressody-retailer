<?php
declare ( strict_types = 1 );

namespace PixelgradeLT\Retailer\Tests\Unit\PackageType;

use Composer\IO\NullIO;
use Composer\Semver\VersionParser;
use PixelgradeLT\Retailer\Client\ComposerClient;
use PixelgradeLT\Retailer\ComposerVersionParser;
use PixelgradeLT\Retailer\SolutionManager;
use PixelgradeLT\Retailer\SolutionType\Builder\LocalBasePackageBuilder;
use PixelgradeLT\Retailer\SolutionType\LocalBasePackage;
use PixelgradeLT\Retailer\StringHashes;
use PixelgradeLT\Retailer\WordPressReadmeParser;
use Psr\Log\NullLogger;
use PixelgradeLT\Retailer\Archiver;
use PixelgradeLT\Retailer\Exception\InvalidReleaseVersion;
use PixelgradeLT\Retailer\Exception\PackageNotInstalled;
use PixelgradeLT\Retailer\Release;
use PixelgradeLT\Retailer\ReleaseManager;
use PixelgradeLT\Retailer\Storage\Local as LocalStorage;
use PixelgradeLT\Retailer\Tests\Unit\TestCase;

class LocalBasePackageReleasesTest extends TestCase {
	protected $builder = null;

	public function setUp(): void {
		parent::setUp();

		$archiver = new Archiver( new NullLogger() );
		$storage  = new LocalStorage( PIXELGRADELT_RETAILER_TESTS_DIR . '/Fixture/wp-content/uploads/pixelgradelt-retailer/packages' );
		$package  = new LocalBasePackage();
		$composer_version_parser = new ComposerVersionParser( new VersionParser() );
		$composer_client = new ComposerClient();
		$logger = new NullIO();

		$hasher = new StringHashes();
		$readme_parser = new WordPressReadmeParser();
		$package_manager = new SolutionManager( $composer_client, $composer_version_parser, $readme_parser, $logger, $hasher );

		$release_manager = new ReleaseManager( $storage, $archiver, $composer_version_parser, $composer_client, $logger );

		$this->builder = new LocalBasePackageBuilder( $package, $package_manager, $release_manager, $archiver, $logger );
	}

	public function test_package_has_no_releases() {
		$package = $this->builder->build();
		$this->assertFalse( $package->has_releases() );
	}

	public function test_package_has_releases() {
		$package = $this->builder->add_release( '1.0.0' )->build();
		$this->assertTrue( $package->has_releases() );
	}

	public function test_get_release_by_version() {
		$version = '1.0.0';
		$package = $this->builder->add_release( $version )->build();

		$this->assertSame( 1, count( $package->get_releases() ) );

		$release = $package->get_release( $version );
		$this->assertInstanceOf( Release::class, $release );
		$this->assertSame( $version, $release->get_version() );
	}

	public function test_get_installed_release() {
		$installed_version = '0.4.0';
		$latest_version    = '1.0.0';

		$package = $this->builder
			->set_installed( true )
			->set_installed_version( $installed_version )
			->add_release( $installed_version )
			->add_release( $latest_version )
			->build();

		$release = $package->get_installed_release();
		$this->assertInstanceOf( Release::class, $release );
		$this->assertTrue( $package->is_installed_release( $release ) );

		$release = $package->get_release( $latest_version );
		$this->assertFalse( $package->is_installed_release( $release ) );
	}

	public function test_get_latest_release() {
		$version = '0.4.0';
		$package = $this->builder
			->add_release( '0.3.2' )
			->add_release( $version )
			->add_release( '0.3.0' )
			->build();

		$release = $package->get_latest_release();

		$this->assertInstanceOf( Release::class, $release );
		$this->assertSame( $version, $release->get_version() );
	}

	public function test_is_update_available() {
		$installed_version = '0.4.0';
		$latest_version    = '1.0.0';

		$package = $this->builder
			->set_installed( true )
			->set_installed_version( $installed_version )
			->add_release( $installed_version )
			->add_release( $latest_version )
			->build();

		$this->assertSame( $installed_version, $package->get_installed_version() );
		$this->assertSame( $latest_version, $package->get_latest_version() );
		$this->assertTrue( $package->is_update_available() );
	}

	public function test_get_latest_release_throws_exception_when_there_are_no_releases() {
		$this->expectException( InvalidReleaseVersion::class );

		$package = $this->builder->build();
		$package->get_latest_release();
	}

	public function test_get_unknown_release_throws_exception() {
		$this->expectException( InvalidReleaseVersion::class );

		$package = $this->builder->build();
		$package->get_release( '0.4.0' );
	}

	public function test_get_not_installed_release_throws_exception() {
		$this->expectException( PackageNotInstalled::class );

		$package = $this->builder->build();
		$package->get_installed_release();
	}
}
