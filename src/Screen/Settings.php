<?php
/**
 * Settings screen provider.
 *
 * @since   0.1.0
 * @license GPL-2.0-or-later
 * @package PixelgradeLT
 */

declare ( strict_types=1 );

namespace PixelgradeLT\Retailer\Screen;

use Cedaro\WP\Plugin\AbstractHookProvider;
use PixelgradeLT\Retailer\Authentication\ApiKey\ApiKey;
use PixelgradeLT\Retailer\Authentication\ApiKey\ApiKeyRepository;
use PixelgradeLT\Retailer\Capabilities;
use PixelgradeLT\Retailer\Provider\HealthCheck;
use PixelgradeLT\Retailer\Repository\PackageRepository;
use PixelgradeLT\Retailer\Transformer\ComposerPackageTransformer;

use function PixelgradeLT\Retailer\get_solutions_permalink;

/**
 * Settings screen provider class.
 *
 * @since 0.1.0
 */
class Settings extends AbstractHookProvider {
	/**
	 * API Key repository.
	 *
	 * @var ApiKeyRepository
	 */
	protected ApiKeyRepository $api_keys;

	/**
	 * Composer package transformer.
	 *
	 * @var ComposerPackageTransformer
	 */
	protected ComposerPackageTransformer $composer_transformer;

	/**
	 * Solution repository.
	 *
	 * @var PackageRepository
	 */
	protected PackageRepository $solutions;

	/**
	 * Create the setting screen.
	 *
	 * @param PackageRepository          $packages             Solution repository.
	 * @param ApiKeyRepository           $api_keys             API Key repository.
	 * @param ComposerPackageTransformer $composer_transformer Package transformer.
	 */
	public function __construct(
			PackageRepository $packages,
			ApiKeyRepository $api_keys,
			ComposerPackageTransformer $composer_transformer
	) {

		$this->api_keys             = $api_keys;
		$this->solutions            = $packages;
		$this->composer_transformer = $composer_transformer;
	}

	/**
	 * Register hooks.
	 *
	 * @since 0.1.0
	 */
	public function register_hooks() {
		if ( is_multisite() ) {
			add_action( 'network_admin_menu', [ $this, 'add_menu_item' ] );
		} else {
			add_action( 'admin_menu', [ $this, 'add_menu_item' ] );
		}

		add_action( 'admin_init', [ $this, 'register_settings' ] );
		add_action( 'admin_init', [ $this, 'add_sections' ] );
		add_action( 'admin_init', [ $this, 'add_settings' ] );
	}

	/**
	 * Add the settings menu item.
	 *
	 * @since 0.1.0
	 */
	public function add_menu_item() {
		$parent_slug = 'options-general.php';
		if ( is_network_admin() ) {
			$parent_slug = 'settings.php';
		}

		$page_hook = add_submenu_page(
				$parent_slug,
				esc_html__( 'PixelgradeLT Retailer', 'pixelgradelt_retailer' ),
				esc_html__( 'LT Retailer', 'pixelgradelt_retailer' ),
				Capabilities::MANAGE_OPTIONS,
				'pixelgradelt_retailer',
				[ $this, 'render_screen' ]
		);

		add_action( 'load-' . $page_hook, [ $this, 'load_screen' ] );
	}

	/**
	 * Set up the screen.
	 *
	 * @since 0.1.0
	 */
	public function load_screen() {
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
		add_action( 'admin_notices', [ HealthCheck::class, 'display_authorization_notice' ] );
		add_action( 'admin_notices', [ HealthCheck::class, 'display_permalink_notice' ] );
	}

	/**
	 * Enqueue scripts and styles.
	 *
	 * @since 0.1.0
	 */
	public function enqueue_assets() {
		wp_enqueue_script( 'pixelgradelt_retailer-admin' );
		wp_enqueue_style( 'pixelgradelt_retailer-admin' );
		wp_enqueue_script( 'pixelgradelt_retailer-package-settings' );

		$api_keys = $this->api_keys->find_for_user( wp_get_current_user() );

		$items = array_map(
				function ( ApiKey $api_key ) {
					$data                   = $api_key->to_array();
					$data['user_edit_link'] = esc_url( get_edit_user_link( $api_key->get_user()->ID ) );

					return $data;
				},
				$api_keys
		);

		wp_enqueue_script( 'pixelgradelt_retailer-api-keys' );
		wp_localize_script(
				'pixelgradelt_retailer-api-keys',
				'_pixelgradelt_retailerApiKeysData',
				[
						'items'  => $items,
						'userId' => get_current_user_id(),
				]
		);
	}

	/**
	 * Register settings.
	 *
	 * @since 0.1.0
	 */
	public function register_settings() {
		register_setting( 'pixelgradelt_retailer', 'pixelgradelt_retailer', [ $this, 'sanitize_settings' ] );
	}

	/**
	 * Add settings sections.
	 *
	 * @since 0.1.0
	 */
	public function add_sections() {
		add_settings_section(
				'default',
				esc_html__( 'General', 'pixelgradelt_retailer' ),
				'__return_null',
				'pixelgradelt_retailer'
		);

		add_settings_section(
				'ltrecords',
				esc_html__( 'LT Records Communication', 'pixelgradelt_retailer' ),
				'__return_null',
				'pixelgradelt_retailer'
		);

		add_settings_section(
				'access',
				esc_html__( 'Access', 'pixelgradelt_retailer' ),
				[ $this, 'render_section_access_description' ],
				'pixelgradelt_retailer'
		);
	}

	/**
	 * Register individual settings.
	 *
	 * @since 0.1.0
	 */
	public function add_settings() {
		add_settings_field(
				'vendor',
				'<label for="pixelgradelt_retailer-vendor">' . esc_html__( 'Vendor', 'pixelgradelt_retailer' ) . '</label>',
				[ $this, 'render_field_vendor' ],
				'pixelgradelt_retailer',
				'default'
		);

		add_settings_field(
				'github-oauth-token',
				'<label for="pixelgradelt_retailer-github-oauth-token">' . esc_html__( 'Github OAuth Token', 'pixelgradelt_retailer' ) . '</label>',
				[ $this, 'render_field_github_oauth_token' ],
				'pixelgradelt_retailer',
				'default'
		);

		add_settings_field(
				'ltrecords-packages-repo-endpoint',
				'<label for="pixelgradelt_retailer-ltrecords-packages-repo-endpoint">' . esc_html__( 'Packages Repository Endpoint', 'pixelgradelt_retailer' ) . '</label>',
				[ $this, 'render_field_ltrecords_packages_repo_endpoint' ],
				'pixelgradelt_retailer',
				'ltrecords'
		);

		add_settings_field(
				'ltrecords-parts-repo-endpoint',
				'<label for="pixelgradelt_retailer-ltrecords-parts-repo-endpoint">' . esc_html__( 'Parts Repository Endpoint', 'pixelgradelt_retailer' ) . '</label>',
				[ $this, 'render_field_ltrecords_parts_repo_endpoint' ],
				'pixelgradelt_retailer',
				'ltrecords'
		);

		add_settings_field(
				'ltrecords-api-key',
				'<label for="pixelgradelt_retailer-ltrecords-api-key">' . esc_html__( 'Access API Key', 'pixelgradelt_retailer' ) . '</label>',
				[ $this, 'render_field_ltrecords_api_key' ],
				'pixelgradelt_retailer',
				'ltrecords'
		);
	}

	/**
	 * Sanitize settings.
	 *
	 * @since 0.1.0
	 *
	 * @param array $value Settings values.
	 *
	 * @return array Sanitized and filtered settings values.
	 */
	public function sanitize_settings( array $value ): array {
		if ( ! empty( $value['vendor'] ) ) {
			$value['vendor'] = preg_replace( '/[^a-z0-9_\-\.]+/i', '', $value['vendor'] );
		}

		if ( ! empty( $value['github-oauth-token'] ) ) {
			$value['github-oauth-token'] = trim( $value['github-oauth-token'] );
		}

		if ( ! empty( $value['ltrecords-packages-repo-endpoint'] ) ) {
			$value['ltrecords-packages-repo-endpoint'] = esc_url( $value['ltrecords-packages-repo-endpoint'] );
		}

		if ( ! empty( $value['ltrecords-parts-repo-endpoint'] ) ) {
			$value['ltrecords-parts-repo-endpoint'] = esc_url( $value['ltrecords-parts-repo-endpoint'] );
		}

		if ( ! empty( $value['ltrecords-api-key'] ) ) {
			$value['ltrecords-api-key'] = trim( $value['ltrecords-api-key'] );
		}

		return (array) apply_filters( 'pixelgradelt_retailer_sanitize_settings', $value );
	}

	/**
	 * Display the screen.
	 *
	 * @since 0.1.0
	 */
	public function render_screen() {
		$solutions_permalink = esc_url( get_solutions_permalink() );
		$solutions           = array_map( [ $this->composer_transformer, 'transform' ], $this->solutions->all() );
		$system_checks       = [];

		include $this->plugin->get_path( 'views/screen-settings.php' );
		include $this->plugin->get_path( 'views/templates.php' );
	}

	/**
	 * Display the access section description.
	 *
	 * @since 0.1.0
	 */
	public function render_section_access_description() {
		printf(
				'<p>%s</p>',
				esc_html__( 'API Keys are used to access your PixelgradeLT Retailer repository. Your personal API keys appear below or you can create keys for other users by editing their accounts.', 'pixelgradelt_retailer' )
		);

		printf(
				'<p><strong>%s</strong></p>',
				/* translators: %s: <code>pixelgradelt_retailer</code> */
				sprintf( esc_html__( 'The password for all API Keys is %s. Use the API key as the username.', 'pixelgradelt_retailer' ), '<code>pixelgradelt_retailer</code>' )
		);

		echo '<div id="pixelgradelt_retailer-api-key-manager"></div>';

		printf(
				'<p><a href="https://github.com/pixelgradelt/pixelgradelt-retailer/blob/develop/docs/security.md" target="_blank" rel="noopener noreferer"><em>%s</em></a></p>',
				esc_html__( 'Read more about securing your PixelgradeLT Retailer repository.', 'pixelgradelt_retailer' )
		);
	}

	/**
	 * Display a field for defining the vendor.
	 *
	 * @since 0.1.0
	 */
	public function render_field_vendor() {
		$value = $this->get_setting( 'vendor', '' );
		?>
		<p>
			<input type="text" name="pixelgradelt_retailer[vendor]" id="pixelgradelt_retailer-vendor"
			       value="<?php echo esc_attr( $value ); ?>" placeholder="pixelgradelt-retailer"><br/>
			<span class="description">The default is <code>pixelgradelt-retailer</code><br>
			This is the general vendor that will be used when exposing all the packages for consumption.<br>
				<strong>For example:</strong> you have a managed package with the source on Packagist.org (say <a
						href="https://packagist.org/packages/yoast/wordpress-seo"><code>yoast/wordpress-seo</code></a>). You will expose it under a package name in the form <code>vendor/post_slug</code> (say <code>pixelgradelt-retailer/yoast-wordpress-seo</code>).</span>
		</p>
		<?php
	}

	/**
	 * Display a field for defining the Github OAuth Token.
	 *
	 * @since 0.1.0
	 */
	public function render_field_github_oauth_token() {
		$value = $this->get_setting( 'github-oauth-token', '' );
		?>
		<p>
			<input type="password" size="80" name="pixelgradelt_retailer[github-oauth-token]"
			       id="pixelgradelt_retailer-github-oauth-token" value="<?php echo esc_attr( $value ); ?>"><br/>
			<span class="description">Github has a rate limit of 60 requests/hour on their API for requests not using an OAuth Token.<br>
				Since most packages on Packagist.org have their source on Github, and you may be using actual Github repos as sources, <strong>you should definitely generate a token and save it here.</strong><br>
				Learn more about <strong>the steps to take <a
							href="https://getcomposer.org/doc/articles/authentication-for-private-packages.md#github-oauth">here</a>.</strong> <strong>Be careful about the permissions you grant on the generated token!</strong></span>
		</p>
		<?php
	}

	/**
	 * Display a field for defining the LT Records Packages Repository endpoint.
	 *
	 * @since 0.1.0
	 */
	public function render_field_ltrecords_packages_repo_endpoint() {
		$value = $this->get_setting( 'ltrecords-packages-repo-endpoint', '' );
		?>
		<p>
			<input type="url" size="80" name="pixelgradelt_retailer[ltrecords-packages-repo-endpoint]"
			       id="pixelgradelt_retailer-ltrecords-packages-repo-endpoint"
			       value="<?php echo esc_attr( $value ); ?>"><br/>
			<span class="description">Provide here the LT Records Packages Repository endpoint URL.</span>
		</p>
		<?php
	}

	/**
	 * Display a field for defining the LT Records Parts Repository endpoint.
	 *
	 * @since 0.1.0
	 */
	public function render_field_ltrecords_parts_repo_endpoint() {
		$value = $this->get_setting( 'ltrecords-parts-repo-endpoint', '' );
		?>
		<p>
			<input type="url" size="80" name="pixelgradelt_retailer[ltrecords-parts-repo-endpoint]"
			       id="pixelgradelt_retailer-ltrecords-parts-repo-endpoint"
			       value="<?php echo esc_attr( $value ); ?>"><br/>
			<span class="description">Provide here the LT Records Parts Repository endpoint URL.</span>
		</p>
		<?php
	}

	/**
	 * Display a field for defining the LT Records API Key.
	 *
	 * @since 0.1.0
	 */
	public function render_field_ltrecords_api_key() {
		$value = $this->get_setting( 'ltrecords-api-key', '' );
		?>
		<p>
			<input type="text" size="80" name="pixelgradelt_retailer[ltrecords-api-key]"
			       id="pixelgradelt_retailer-ltrecords-api-key" value="<?php echo esc_attr( $value ); ?>"><br/>
			<span class="description">Provide here a valid LT Records API key for Retailer to use to access the repositories above.</span>
		</p>
		<?php
	}

	/**
	 * Retrieve a setting.
	 *
	 * @since 0.1.0
	 *
	 * @param string $key     Setting name.
	 * @param mixed  $default Optional. Default setting value.
	 *
	 * @return mixed
	 */
	protected function get_setting( string $key, $default = null ) {
		$option = get_option( 'pixelgradelt_retailer' );

		return $option[ $key ] ?? $default;
	}
}
