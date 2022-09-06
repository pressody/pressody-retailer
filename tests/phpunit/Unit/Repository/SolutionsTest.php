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

namespace Pressody\Retailer\Tests\Unit\Repository;

use Brain\Monkey\Functions;
use Composer\IO\NullIO;
use Composer\Semver\VersionParser;
use Pressody\Retailer\Client\ComposerClient;
use Pressody\Retailer\ComposerVersionParser;
use Pressody\Retailer\Repository\Solutions;
use Pressody\Retailer\SolutionFactory;
use Pressody\Retailer\SolutionManager;
use Pressody\Retailer\Tests\Unit\TestCase;

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
