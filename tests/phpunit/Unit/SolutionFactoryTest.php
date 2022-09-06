<?php
/*
 * This file is part of a Pressody module.
 *
 * This Pressody module is free software: you can redistribute it and/or modify it under the terms of the
 * GNU General Public License as published by the Free Software Foundation, either version 2 of the License,
 * or (at your option) any later version.
 *
 * This Pressody module is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY;
 * without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along with this Pressody module.
 * If not, see <https://www.gnu.org/licenses/>.
 *
 * Copyright (c) 2021, 2022 Vlad Olaru (vlad@thinkwritecode.com)
 */

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
