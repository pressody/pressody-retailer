<?php
declare ( strict_types = 1 );

use Pressody\Retailer\Tests\Framework\PHPUnitUtil;
use Pressody\Retailer\Tests\Framework\TestSuite;
use Psr\Log\NullLogger;

require dirname( __DIR__, 2 ) . '/vendor/autoload.php';

define( 'Pressody\Retailer\RUNNING_UNIT_TESTS', true );
define( 'Pressody\Retailer\TESTS_DIR', __DIR__ );
define( 'WP_PLUGIN_DIR', __DIR__ . '/Fixture/wp-content/plugins' );

if ( 'Unit' === PHPUnitUtil::get_current_suite() ) {
	// For the Unit suite we shouldn't need WordPress loaded.
	// This keeps them fast.
	return;
}

require_once dirname( __DIR__, 2 ) . '/vendor/antecedent/patchwork/Patchwork.php';

$suite = new TestSuite();

$GLOBALS['wp_tests_options'] = [
	'active_plugins'  => [ 'pressody-retailer/pressody-retailer.php' ],
	'timezone_string' => 'Europe/Bucharest',
	// We need this so the EditSolution `solution_required_parts` CarbonFields field will not strip values as non-existent.
	// @see EditSolution::get_pdrecords_parts()
	'_pressody_retailer_pdrecords_parts_timeout' => time() + 60*60,
	'_pressody_retailer_pdrecords_parts' => [
		'pressody-records/part_yet-another',
		'pressody-records/part_another-test',
		'pressody-records/part_test-test',
	],
];

// This is a standard function name that others might use to check for existence to determine if we are running tests.
// Best to play ball.
function _manually_load_plugin() {
	require dirname( __DIR__, 2 ) . '/pressody-retailer.php';
}
$suite->addFilter( 'muplugins_loaded', '_manually_load_plugin' );

$suite->addFilter( 'pressody_retailer/compose', function( $plugin, $container ) {
	$container['logger'] = new NullLogger();
	$container['storage.working_directory'] = \Pressody\Retailer\TESTS_DIR . '/Fixture/wp-content/uploads/pressody-retailer/';

	// Prevent the remote fetch of PD Records parts.
	$solution_manager = \Mockery::mock(
		'Pressody\Retailer\SolutionManager',
		'Pressody\Retailer\Manager',
		[
			$container['client.composer'],
			$container['version.parser'],
			$container['logs.logger'],
		] )->makePartial();
	$solution_manager->shouldReceive( 'get_pdrecords_parts' )
	                 ->andReturn( [
		                 'pressody-records/part_yet-another',
		                 'pressody-records/part_another-test',
		                 'pressody-records/part_test-test',
	                 ] );
	$container['solution.manager'] = $solution_manager;
}, 10, 2 );

$suite->bootstrap();
