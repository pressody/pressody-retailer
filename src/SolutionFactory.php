<?php
/**
 * Solution factory.
 *
 * @since   0.1.0
 * @license GPL-2.0-or-later
 * @package PixelgradeLT
 */

declare ( strict_types=1 );

namespace PixelgradeLT\Retailer;

use PixelgradeLT\Retailer\SolutionType\BaseSolution;
use PixelgradeLT\Retailer\SolutionType\Builder\BaseSolutionBuilder;
use PixelgradeLT\Retailer\SolutionType\SolutionTypes;
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
