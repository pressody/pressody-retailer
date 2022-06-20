<?php
/**
 * Settings screen provider.
 *
 * @since   0.1.0
 * @license GPL-2.0-or-later
 * @package Pressody
 */

declare ( strict_types=1 );

namespace Pressody\Retailer\Screen;

use Cedaro\WP\Plugin\AbstractHookProvider;
use Pressody\Retailer\Authentication\ApiKey\ApiKeyRepository;
use Pressody\Retailer\Capabilities;
use Pressody\Retailer\Provider\HealthCheck;
use Pressody\Retailer\Repository\PackageRepository;
use Pressody\Retailer\Transformer\ComposerPackageTransformer;

use function Pressody\Retailer\get_setting;
use function Pressody\Retailer\get_solutions_permalink;
use function Pressody\Retailer\preload_rest_data;

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
		if ( \is_multisite() ) {
			\add_action( 'network_admin_menu', [ $this, 'add_menu_item' ] );
		} else {
			\add_action( 'admin_menu', [ $this, 'add_menu_item' ] );
		}

		\add_action( 'admin_init', [ $this, 'register_settings' ] );
		\add_action( 'admin_init', [ $this, 'add_sections' ] );
		\add_action( 'admin_init', [ $this, 'add_settings' ] );
	}

	/**
	 * Add the settings menu item.
	 *
	 * @since 0.1.0
	 */
	public function add_menu_item() {
		$parent_slug = 'options-general.php';
		if ( \is_network_admin() ) {
			$parent_slug = 'settings.php';
		}

		$page_hook = \add_submenu_page(
				$parent_slug,
				esc_html__( 'Pressody Retailer', 'pressody_retailer' ),
				esc_html__( 'PD Retailer', 'pressody_retailer' ),
				Capabilities::MANAGE_OPTIONS,
				'pressody_retailer',
				[ $this, 'render_screen' ]
		);

		\add_action( 'load-' . $page_hook, [ $this, 'load_screen' ] );
	}

	/**
	 * Set up the screen.
	 *
	 * @since 0.1.0
	 */
	public function load_screen() {
		\add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
		\add_action( 'admin_notices', [ HealthCheck::class, 'display_authorization_notice' ] );
		\add_action( 'admin_notices', [ HealthCheck::class, 'display_permalink_notice' ] );
	}

	/**
	 * Enqueue scripts and styles.
	 *
	 * @since 0.1.0
	 */
	public function enqueue_assets() {
		\wp_enqueue_script( 'pressody_retailer-admin' );
		\wp_enqueue_style( 'pressody_retailer-admin' );
		\wp_enqueue_script( 'pressody_retailer-access' );
		\wp_enqueue_script( 'pressody_retailer-repository' );

		\wp_localize_script(
				'pressody_retailer-access',
				'_pressodyRetailerAccessData',
				[
						'editedUserId' => get_current_user_id(),
				]
		);

		\wp_localize_script(
				'pressody_retailer-repository',
				'_pressodyRetailerRepositoryData',
				[
						'addNewSolutionUrl' => admin_url('post-new.php?post_type=pdsolution'),
				]
		);

		$preload_paths = [
				'/pressody_retailer/v1/solutions',
		];

		if ( \current_user_can( Capabilities::MANAGE_OPTIONS ) ) {
			$preload_paths = array_merge(
					$preload_paths,
					[
							'/pressody_retailer/v1/apikeys?user=' . get_current_user_id(),
					]
			);
		}

		preload_rest_data( $preload_paths );
	}

	/**
	 * Register settings.
	 *
	 * @since 0.1.0
	 */
	public function register_settings() {
		\register_setting( 'pressody_retailer', 'pressody_retailer', [ $this, 'sanitize_settings' ] );
	}

	/**
	 * Add settings sections.
	 *
	 * @since 0.1.0
	 */
	public function add_sections() {
		\add_settings_section(
				'default',
				esc_html__( 'General', 'pressody_retailer' ),
				'__return_null',
				'pressody_retailer'
		);

		\add_settings_section(
				'pdrecords',
				esc_html__( 'PD Records Communication', 'pressody_retailer' ),
				'__return_null',
				'pressody_retailer'
		);
	}

	/**
	 * Register individual settings.
	 *
	 * @since 0.1.0
	 */
	public function add_settings() {
		\add_settings_field(
				'vendor',
				'<label for="pressody_retailer-vendor">' . esc_html__( 'Vendor', 'pressody_retailer' ) . '</label>',
				[ $this, 'render_field_vendor' ],
				'pressody_retailer',
				'default'
		);

		\add_settings_field(
				'github-oauth-token',
				'<label for="pressody_retailer-github-oauth-token">' . esc_html__( 'GitHub OAuth Token', 'pressody_retailer' ) . '</label>',
				[ $this, 'render_field_github_oauth_token' ],
				'pressody_retailer',
				'default'
		);

		\add_settings_field(
				'pdrecords-packages-repo-endpoint',
				'<label for="pressody_retailer-pdrecords-packages-repo-endpoint">' . esc_html__( 'Packages Repository Endpoint', 'pressody_retailer' ) . '</label>',
				[ $this, 'render_field_pdrecords_packages_repo_endpoint' ],
				'pressody_retailer',
				'pdrecords'
		);

		\add_settings_field(
				'pdrecords-parts-repo-endpoint',
				'<label for="pressody_retailer-pdrecords-parts-repo-endpoint">' . esc_html__( 'Parts Repository Endpoint', 'pressody_retailer' ) . '</label>',
				[ $this, 'render_field_pdrecords_parts_repo_endpoint' ],
				'pressody_retailer',
				'pdrecords'
		);

		\add_settings_field(
				'pdrecords-api-key',
				'<label for="pressody_retailer-pdrecords-api-key">' . esc_html__( 'Access API Key', 'pressody_retailer' ) . '</label>',
				[ $this, 'render_field_pdrecords_api_key' ],
				'pressody_retailer',
				'pdrecords'
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

		if ( ! empty( $value['pdrecords-packages-repo-endpoint'] ) ) {
			$value['pdrecords-packages-repo-endpoint'] = \esc_url( $value['pdrecords-packages-repo-endpoint'] );
		}

		if ( ! empty( $value['pdrecords-parts-repo-endpoint'] ) ) {
			$value['pdrecords-parts-repo-endpoint'] = \esc_url( $value['pdrecords-parts-repo-endpoint'] );
		}

		if ( ! empty( $value['pdrecords-api-key'] ) ) {
			$value['pdrecords-api-key'] = trim( $value['pdrecords-api-key'] );
		}

		return (array) \apply_filters( 'pressody_retailer/sanitize_settings', $value );
	}

	/**
	 * Display the screen.
	 *
	 * @since 0.1.0
	 */
	public function render_screen() {
		$solutions_permalink     = \esc_url( get_solutions_permalink() );

		$tabs = [
				'repository' => [
						'name'       => esc_html__( 'Repository', 'pressody_retailer' ),
						'capability' => Capabilities::VIEW_SOLUTIONS,
				],
				'access'     => [
						'name'       => esc_html__( 'Access', 'pressody_retailer' ),
						'capability' => Capabilities::MANAGE_OPTIONS,
						'is_active'  => false,
				],
				'composer'   => [
						'name'       => esc_html__( 'Composer', 'pressody_retailer' ),
						'capability' => Capabilities::VIEW_SOLUTIONS,
				],
				'settings'   => [
						'name'       => esc_html__( 'Settings', 'pressody_retailer' ),
						'capability' => Capabilities::MANAGE_OPTIONS,
				],
				'system-status'   => [
						'name'       => esc_html__( 'System Status', 'pressody_retailer' ),
						'capability' => Capabilities::MANAGE_OPTIONS,
				],
		];

		// By default, the Repository tabs is active.
		$active_tab = 'repository';

		include $this->plugin->get_path( 'views/screen-settings.php' );
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
			<input type="text" name="pressody_retailer[vendor]" id="pressody_retailer-vendor"
			       value="<?php echo \esc_attr( $value ); ?>" placeholder="pressody-retailer"><br/>
			<span class="description">The default is <code>pressody-retailer</code><br>
			This is the general vendor that will be used when exposing all the packages for consumption.<br>
				<strong>For example:</strong> you have a managed package with the source on Packagist.org (say <a
						href="https://packagist.org/packages/yoast/wordpress-seo"><code>yoast/wordpress-seo</code></a>). You will expose it under a package name in the form <code>vendor/post_slug</code> (say <code>pressody-retailer/yoast-wordpress-seo</code>).</span>
		</p>
		<?php
	}

	/**
	 * Display a field for defining the GitHub OAuth Token.
	 *
	 * @since 0.1.0
	 */
	public function render_field_github_oauth_token() {
		$value = $this->get_setting( 'github-oauth-token', '' );
		?>
		<p>
			<input type="password" size="80" name="pressody_retailer[github-oauth-token]"
			       id="pressody_retailer-github-oauth-token" value="<?php echo \esc_attr( $value ); ?>"><br/>
			<span class="description">GitHub has <strong>a rate limit of 60 requests/hour</strong> on their API for <strong>requests not using an OAuth Token.</strong><br>
				Since most packages on Packagist.org have their source on GitHub, and you may be using actual GitHub repos as sources, <strong>you should definitely generate a token and save it here.</strong><br>
				Learn more about <strong>the steps to take <a
							href="https://getcomposer.org/doc/articles/authentication-for-private-packages.md#github-oauth">here</a>.</strong> <strong>Be careful about the permissions you grant on the generated token!</strong></span>
		</p>
		<?php
	}

	/**
	 * Display a field for defining the PD Records Packages Repository endpoint.
	 *
	 * @since 0.1.0
	 */
	public function render_field_pdrecords_packages_repo_endpoint() {
		$value = $this->get_setting( 'pdrecords-packages-repo-endpoint', '' );
		?>
		<p>
			<input type="url" size="80" name="pressody_retailer[pdrecords-packages-repo-endpoint]"
			       id="pressody_retailer-pdrecords-packages-repo-endpoint"
			       value="<?php echo \esc_attr( $value ); ?>"><br/>
			<span class="description">Provide here the PD Records Packages Repository endpoint URL.</span>
		</p>
		<?php
	}

	/**
	 * Display a field for defining the PD Records Parts Repository endpoint.
	 *
	 * @since 0.1.0
	 */
	public function render_field_pdrecords_parts_repo_endpoint() {
		$value = $this->get_setting( 'pdrecords-parts-repo-endpoint', '' );
		?>
		<p>
			<input type="url" size="80" name="pressody_retailer[pdrecords-parts-repo-endpoint]"
			       id="pressody_retailer-pdrecords-parts-repo-endpoint"
			       value="<?php echo \esc_attr( $value ); ?>"><br/>
			<span class="description">Provide here the PD Records Parts Repository endpoint URL.</span>
		</p>
		<?php
	}

	/**
	 * Display a field for defining the PD Records API Key.
	 *
	 * @since 0.1.0
	 */
	public function render_field_pdrecords_api_key() {
		$value = $this->get_setting( 'pdrecords-api-key', '' );
		?>
		<p>
			<input type="text" size="80" name="pressody_retailer[pdrecords-api-key]"
			       id="pressody_retailer-pdrecords-api-key" value="<?php echo \esc_attr( $value ); ?>"><br/>
			<span class="description">Provide here <strong>a valid PD Records API key</strong> for PD Retailer to use to access the repositories above.</span>
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
		return get_setting( $key, $default );
	}
}
