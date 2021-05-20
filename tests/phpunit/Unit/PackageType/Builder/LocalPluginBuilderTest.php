<?php
declare ( strict_types = 1 );

namespace PixelgradeLT\Retailer\Tests\Unit\PackageType\Builder;

use Composer\IO\NullIO;
use PixelgradeLT\Retailer\Archiver;
use PixelgradeLT\Retailer\Solution;
use PixelgradeLT\Retailer\SolutionManager;
use PixelgradeLT\Retailer\SolutionType\Builder\LocalBasePackageBuilder;
use PixelgradeLT\Retailer\SolutionType\Builder\LocalPluginBuilder;
use PixelgradeLT\Retailer\SolutionType\LocalPlugin;
use PixelgradeLT\Retailer\SolutionType\SolutionTypes;
use PixelgradeLT\Retailer\ReleaseManager;
use PixelgradeLT\Retailer\Tests\Unit\TestCase;
use Psr\Log\NullLogger;

class LocalPluginBuilderTest extends TestCase {
	protected $builder = null;

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

		$this->builder = new LocalPluginBuilder( new LocalPlugin(), $package_manager, $release_manager, $archiver, $logger );
	}

	public function test_extends_package_builder() {
		$this->assertInstanceOf( LocalBasePackageBuilder::class, $this->builder );
	}

	public function test_implements_package_interface() {
		$package = $this->builder->build();

		$this->assertInstanceOf( Solution::class, $package );
	}

	public function test_basename() {
		$expected = 'plugin/plugin.php';
		$package  = $this->builder->set_basename( $expected )->build();

		$this->assertSame( $expected, $package->get_basename() );
	}

	public function test_from_basename() {
		$plugin_file = 'plugin-name/plugin-name.php';
		$slug = 'plugin-name';

		$package = $this->builder->from_basename( $plugin_file )->build();

		$this->assertSame( SolutionTypes::PLUGIN, $package->get_type() );
		$this->assertSame( $slug, $package->get_slug() );
		$this->assertSame( 'local-plugin/' . $slug, $package->get_source_name() );
		$this->assertSame( 'local.plugin', $package->get_source_type() );
		$this->assertSame( $plugin_file, $package->get_basename() );
		$this->assertSame( WP_PLUGIN_DIR . '/' . 'plugin-name/', $package->get_directory() );
		$this->assertTrue( $package->is_installed() );
	}

	public function test_with_package() {
		$expected = 'plugin/plugin.php';
		$expected_package  = $this->builder->set_basename( $expected )->build();

		$package = $this->builder->with_package( $expected_package )->build();

		$this->assertSame( $expected, $package->get_basename() );
	}
}