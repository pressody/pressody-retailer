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
use Pressody\Retailer\SolutionManager;

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
		$expected = 'pressody-retailer/test';

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
