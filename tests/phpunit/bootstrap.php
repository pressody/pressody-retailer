<?php
declare ( strict_types = 1 );

use PixelgradeLT\Retailer\Tests\Framework\PHPUnitUtil;
use PixelgradeLT\Retailer\Tests\Framework\TestSuite;
use Psr\Log\NullLogger;

require dirname( __DIR__, 2 ) . '/vendor/autoload.php';

define( 'PixelgradeLT\Retailer\RUNNING_UNIT_TESTS', true );
define( 'PixelgradeLT\Retailer\TESTS_DIR', __DIR__ );
define( 'WP_PLUGIN_DIR', __DIR__ . '/Fixture/wp-content/plugins' );

if ( 'Unit' === PHPUnitUtil::get_current_suite() ) {
	// For the Unit suite we shouldn't need WordPress loaded.
	// This keeps them fast.
	return;
}

require_once dirname( __DIR__, 2 ) . '/vendor/antecedent/patchwork/Patchwork.php';

$suite = new TestSuite();

$GLOBALS['wp_tests_options'] = [
	'active_plugins'  => [ 'pixelgradelt-retailer/pixelgradelt-retailer.php' ],
	'timezone_string' => 'Europe/Bucharest',
	// We need this so the EditSolution `solution_required_parts` CarbonFields field will not strip values as non-existent.
	// @see EditSolution::get_ltrecords_parts()
	'_pixelgradelt_retailer_ltrecords_parts_timeout' => time() + 60*60,
	'_pixelgradelt_retailer_ltrecords_parts' => [
		'pixelgradelt-records/part_yet-another',
		'pixelgradelt-records/part_another-test',
		'pixelgradelt-records/part_test-test',
	],
];

// This is a standard function name that others might use to check for existence to determine if we are running tests.
// Best to play ball.
function _manually_load_plugin() {
	require dirname( __DIR__, 2 ) . '/pixelgradelt-retailer.php';
}
$suite->addFilter( 'muplugins_loaded', '_manually_load_plugin' );

$suite->addFilter( 'pixelgradelt_retailer/compose', function( $plugin, $container ) {
	$container['logger'] = new NullLogger();
	$container['storage.working_directory'] = \PixelgradeLT\Retailer\TESTS_DIR . '/Fixture/wp-content/uploads/pixelgradelt-retailer/';

	// Prevent the remote fetch of LT Records parts.
	$solution_manager = \Mockery::mock(
		'PixelgradeLT\Retailer\SolutionManager',
		'PixelgradeLT\Retailer\Manager',
		[
			$container['client.composer'],
			$container['version.parser'],
			$container['logs.logger'],
		] )->makePartial();
	$solution_manager->shouldReceive( 'get_ltrecords_parts' )
	                 ->andReturn( [
		                 'pixelgradelt-records/part_yet-another',
		                 'pixelgradelt-records/part_another-test',
		                 'pixelgradelt-records/part_test-test',
	                 ] );
	$container['solution.manager'] = $solution_manager;
}, 10, 2 );

$suite->bootstrap();
