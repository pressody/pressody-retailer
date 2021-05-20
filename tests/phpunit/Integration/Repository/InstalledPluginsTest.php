<?php
declare ( strict_types = 1 );

namespace PixelgradeLT\Retailer\Tests\Integration\Repository;

use PixelgradeLT\Retailer\SolutionType\LocalPlugin;
use PixelgradeLT\Retailer\Tests\Integration\TestCase;

use function PixelgradeLT\Retailer\plugin;

class InstalledPluginsTest extends TestCase {

	public function test_get_plugin_from_source() {
		$repository = plugin()->get_container()['repository.local.plugins'];

		$package    = $repository->first_where( [ 'slug' => 'basic/basic.php', 'source_type' => 'local.plugin' ] );
		$this->assertInstanceOf( LocalPlugin::class, $package );

		$package    = $repository->first_where( [ 'slug' => 'unmanaged/unmanaged.php' ] );
		$this->assertInstanceOf( LocalPlugin::class, $package );

		$package    = $repository->first_where( [ 'basename' => 'unmanaged/unmanaged.php' ] );
		$this->assertInstanceOf( LocalPlugin::class, $package );

		$package    = $repository->first_where( [ 'slug' => 'unmanaged/unmanaged.php', 'source_type' => 'local.theme' ] );
		$this->assertNull( $package );
	}
}
