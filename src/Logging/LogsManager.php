<?php
/**
 * Logs management routines.
 *
 * @package Pressody
 * @license GPL-2.0-or-later
 * @since 0.1.0
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

declare ( strict_types = 1 );

namespace Pressody\Retailer\Logging;

use Cedaro\WP\Plugin\AbstractHookProvider;
use Psr\Log\LoggerInterface;

/**
 * Class to manage logs.
 *
 * @since 0.1.0
 */
class LogsManager extends AbstractHookProvider {

	/**
	 * Logger.
	 *
	 * @since 0.1.0
	 *
	 * @var LoggerInterface
	 */
	protected LoggerInterface $logger;

	/**
	 * Constructor.
	 *
	 * @since 0.1.0
	 *
	 * @param LoggerInterface   $logger          Logger.
	 */
	public function __construct(
		LoggerInterface $logger
	) {
		$this->logger          = $logger;

		// Make sure that the needed custom DB tables are up-and-running.
		new \Pressody\Retailer\Database\Tables\Logs();
	}

	/**
	 * Register hooks.
	 *
	 * @since 0.1.0
	 */
	public function register_hooks() {
		$this->add_action( 'pressody_retailer/cleanup_logs', 'cleanup_logs' );
	}

	protected function cleanup_logs() {
		if ( is_callable( array( $this->logger, 'clear_expired_logs' ) ) ) {
			$this->logger->clear_expired_logs();
		}
	}
}
