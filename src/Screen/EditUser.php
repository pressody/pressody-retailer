<?php
/**
 * Edit User screen provider.
 *
 * @package PixelgradeLT
 * @license GPL-2.0-or-later
 * @since 0.1.0
 */

declare ( strict_types = 1 );

namespace PixelgradeLT\Retailer\Screen;

use Cedaro\WP\Plugin\AbstractHookProvider;
use PixelgradeLT\Retailer\Authentication\ApiKey\ApiKey;
use PixelgradeLT\Retailer\Authentication\ApiKey\ApiKeyRepository;
use PixelgradeLT\Retailer\Capabilities;
use WP_User;

use function PixelgradeLT\Retailer\get_edited_user_id;

/**
 * Edit User screen provider class.
 *
 * @since 0.1.0
 */
class EditUser extends AbstractHookProvider {
	/**
	 * API Key repository.
	 *
	 * @var ApiKeyRepository
	 */
	protected $api_keys;

	/**
	 * Create the setting screen.
	 *
	 * @param ApiKeyRepository $api_keys API Key repository.
	 */
	public function __construct( ApiKeyRepository $api_keys ) {
		$this->api_keys = $api_keys;
	}

	/**
	 * Register hooks.
	 *
	 * @since 0.1.0
	 */
	public function register_hooks() {
		$user_id = get_edited_user_id();

		// Only load the screen for users that can view solutions.
		if ( ! user_can( $user_id, Capabilities::VIEW_SOLUTIONS ) ) {
			return;
		}

		add_action( 'load-profile.php', [ $this, 'load_screen' ] );
		add_action( 'load-user-edit.php', [ $this, 'load_screen' ] );
	}

	/**
	 * Set up the screen.
	 *
	 * @since 0.1.0
	 */
	public function load_screen() {
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
		add_action( 'edit_user_profile', [ $this, 'render_api_keys_section' ] );
		add_action( 'show_user_profile', [ $this, 'render_api_keys_section' ] );
		add_action( 'admin_footer', [ $this, 'print_templates' ] );
	}

	/**
	 * Enqueue assets.
	 *
	 * @since 0.1.0
	 */
	public function enqueue_assets() {
		wp_enqueue_script( 'pixelgradelt_retailer-admin' );
		wp_enqueue_style( 'pixelgradelt_retailer-admin' );

		$user_id  = get_edited_user_id();
		$user     = get_user_by( 'id', $user_id );
		$api_keys = $this->api_keys->find_for_user( $user );

		$items = array_map(
			function( ApiKey $api_key ) {
					return $api_key->to_array();
			},
			$api_keys
		);

		wp_enqueue_script( 'pixelgradelt_retailer-api-keys' );
		wp_localize_script(
			'pixelgradelt_retailer-api-keys',
			'_pixelgradelt_retailerApiKeysData',
			[
				'items'  => $items,
				'userId' => $user_id,
			]
		);
	}

	/**
	 * Display the API Keys section.
	 *
	 * @param WP_User $user WordPress user instance.
	 */
	public function render_api_keys_section( WP_User $user ) {
		printf( '<h2>%s</h2>', esc_html__( 'PixelgradeLT Retailer API Keys', 'pixelgradelt_retailer' ) );

		printf(
			'<p><strong>%s</strong></p>',
			/* translators: %s: <code>pixelgradelt_retailer</code> */
			sprintf( esc_html__( 'The password for all API Keys is %s. Use the API key as the username.', 'pixelgradelt_retailer' ), '<code>pixelgradelt_retailer</code>' )
		);

		echo '<div id="pixelgradelt_retailer-api-key-manager"></div>';
	}

	/**
	 * Print Underscore.js templates.
	 *
	 * @since 0.1.0
	 */
	public function print_templates() {
		include $this->plugin->get_path( 'views/templates.php' );
	}
}