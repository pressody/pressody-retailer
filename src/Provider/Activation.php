<?php
/**
 * Plugin activation routines.
 *
 * @package PixelgradeLT
 * @license GPL-2.0-or-later
 * @since 0.1.0
 */

declare ( strict_types = 1 );

namespace PixelgradeLT\Retailer\Provider;

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
	 * @see \PixelgradeLT\Retailer\Provider\RewriteRules::maybe_flush_rewrite_rules()
	 *
	 * @since 0.1.0
	 */
	public function activate() {
		\update_option( 'pixelgradelt_retailer_flush_rewrite_rules', 'yes' );

		self::create_cron_jobs();
	}

	/**
	 * Create cron jobs (clear them first).
	 */
	private static function create_cron_jobs() {
		\wp_clear_scheduled_hook( 'pixelgradelt_retailer/cleanup_logs' );

		\wp_schedule_event( time() + ( 3 * HOUR_IN_SECONDS ), 'daily', 'pixelgradelt_retailer/cleanup_logs' );
	}
}
