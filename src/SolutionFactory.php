<?php
/**
 * Solution factory.
 *
 * @since   0.1.0
 * @license GPL-2.0-or-later
 * @package Pressody
 */

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

namespace Pressody\Retailer;

use Pressody\Retailer\SolutionType\BaseSolution;
use Pressody\Retailer\SolutionType\Builder\BaseSolutionBuilder;
use Pressody\Retailer\SolutionType\SolutionTypes;
use Psr\Log\LoggerInterface;

/**
 * Factory for creating solution builders.
 *
 * @since 0.1.0
 */
final class SolutionFactory {

	/**
	 * Solutions manager.
	 *
	 * @var SolutionManager
	 */
	private SolutionManager $solution_manager;

	/**
	 * Logger.
	 *
	 * @var LoggerInterface
	 */
	protected LoggerInterface $logger;

	/**
	 * Constructor.
	 *
	 * @since 0.1.0
	 *
	 * @param SolutionManager $solution_manager Solutions manager.
	 * @param LoggerInterface $logger           Logger.
	 */
	public function __construct(
		SolutionManager $solution_manager,
		LoggerInterface $logger
	) {
		$this->solution_manager = $solution_manager;
		$this->logger           = $logger;
	}

	/**
	 * Create a solution builder.
	 *
	 * @since 0.1.0
	 *
	 * @param string $solution_type Solution type.
	 *
	 * @return BaseSolutionBuilder Solution builder instance.
	 */
	public function create( string $solution_type ): BaseSolutionBuilder {
		return new BaseSolutionBuilder( new BaseSolution(), $this->solution_manager, $this->logger );
	}
}
