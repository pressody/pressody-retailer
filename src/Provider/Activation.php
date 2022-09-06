<?php
/**
 * Plugin activation routines.
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

namespace Pressody\Retailer\Provider;

use Cedaro\WP\Plugin\AbstractHookProvider;

/**
 * Class to activate the plugin.
 *
 * @since 0.1.0
 */
class Activation extends AbstractHookProvider {

	/**
	 * Register hooks.
	 *
	 * @since 0.1.0
	 */
	public function register_hooks() {
		\register_activation_hook( $this->plugin->get_file(), [ __CLASS__, 'activate' ] );
	}

	/**
	 * Activate the plugin.
	 *
	 * - Sets a flag to flush rewrite rules after plugin rewrite rules have been
	 *   registered.
	 * - Create or update DB tables.
	 * - Registers capabilities for the admin role.
	 *
	 * @see \Pressody\Retailer\Provider\RewriteRules::maybe_flush_rewrite_rules()
	 *
	 * @since 0.1.0
	 */
	public function activate() {
		\update_option( 'pressody_retailer_flush_rewrite_rules', 'yes' );

		self::create_cron_jobs();
	}

	/**
	 * Create cron jobs (clear them first).
	 */
	private static function create_cron_jobs() {
		\wp_clear_scheduled_hook( 'pressody_retailer/cleanup_logs' );

		\wp_schedule_event( time() + ( 3 * HOUR_IN_SECONDS ), 'daily', 'pressody_retailer/cleanup_logs' );
	}
}
