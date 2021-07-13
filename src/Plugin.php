<?php
/**
 * Main plugin class
 *
 * @since   0.1.0
 * @license GPL-2.0-or-later
 * @package PixelgradeLT
 */

declare ( strict_types=1 );

namespace PixelgradeLT\Retailer;

use Cedaro\WP\Plugin\Plugin as BasePlugin;
use Psr\Container\ContainerInterface;

/**
 * Main plugin class - composition root.
 *
 * @since 0.1.0
 */
class Plugin extends BasePlugin implements Composable {
	/**
	 * Compose the object graph.
	 *
	 * @since 0.1.0
	 */
	public function compose() {
		$container = $this->get_container();

		/**
		 * Start composing the object graph in PixelgradeLT Retailer.
		 *
		 * @since 0.1.0
		 *
		 * @param Plugin             $plugin    Main plugin instance.
		 * @param ContainerInterface $container Dependency container.
		 */
		do_action( 'pixelgradelt_retailer/compose', $this, $container );

		// Register hook providers.
		$this
			->register_hooks( $container->get( 'hooks.compositions' ) )
			->register_hooks( $container->get( 'hooks.composition_post_type' ) )
			->register_hooks( $container->get( 'hooks.i18n' ) )
			->register_hooks( $container->get( 'hooks.capabilities' ) )
			->register_hooks( $container->get( 'hooks.rewrite_rules' ) )
			->register_hooks( $container->get( 'hooks.custom_vendor' ) )
			->register_hooks( $container->get( 'hooks.health_check' ) )
			->register_hooks( $container->get( 'hooks.request_handler' ) )
			->register_hooks( $container->get( 'hooks.rest' ) )
			// Register the post type early.
			->register_hooks( $container->get( 'hooks.solution_post_type' ) )
			->register_hooks( $container->get( 'client.composer.custom_token_auth' ) )

			->register_hooks( $container->get( 'logs.manager' ) )

			->register_hooks( $container->get( 'screen.edit_composition' ) )
			->register_hooks( $container->get( 'screen.edit_solution' ) );


		if ( is_admin() ) {
			$this
				->register_hooks( $container->get( 'hooks.upgrade' ) )
				->register_hooks( $container->get( 'hooks.admin_assets' ) )
				->register_hooks( $container->get( 'screen.edit_user' ) )
				->register_hooks( $container->get( 'screen.list_wooproducts' ) )
				->register_hooks( $container->get( 'screen.settings' ) )

				->register_hooks( $container->get( 'screen.list_compositions' ) )
				->register_hooks( $container->get( 'screen.list_solutions' ) );
		}

		if ( \function_exists( 'members_plugin' ) ) {
			$this->register_hooks( $container->get( 'plugin.members' ) );
		}

		/**
		 * Finished composing the object graph in PixelgradeLT Retailer.
		 *
		 * @since 0.1.0
		 *
		 * @param Plugin             $plugin    Main plugin instance.
		 * @param ContainerInterface $container Dependency container.
		 */
		do_action( 'pixelgradelt_retailer/composed', $this, $container );
	}

	public function define_constants(): Plugin {
		$upload_dir = wp_upload_dir( null, false );

		if ( ! defined( 'PixelgradeLT\Retailer\LOG_DIR' ) ) {
			define( 'PixelgradeLT\Retailer\LOG_DIR', $upload_dir['basedir'] . '/pixelgradelt-retailer-logs/' );
		}

		return $this;
	}
}
