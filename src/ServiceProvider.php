<?php
/**
 * Plugin service definitions.
 *
 * @since   0.1.0
 * @license GPL-2.0-or-later
 * @package PixelgradeLT
 */

declare ( strict_types=1 );

namespace PixelgradeLT\Retailer;

use Cedaro\WP\Plugin\Provider\I18n;
use Composer\Semver\VersionParser;
use Env\Env;
use Pimple\Container as PimpleContainer;
use Pimple\Psr11\ServiceLocator;
use Pimple\ServiceIterator;
use Pimple\ServiceProviderInterface;
use PixelgradeLT\Retailer\Exception\PixelgradeltRetailerException;
use PixelgradeLT\Retailer\Transformer\ComposerSolutionsRepositoryTransformer;
use PixelgradeLT\Retailer\Logging\Handler\FileLogHandler;
use PixelgradeLT\Retailer\Logging\Logger;
use PixelgradeLT\Retailer\Logging\LogsManager;
use PixelgradeLT\Retailer\Transformer\ComposerSolutionTransformer;
use Psr\Log\LogLevel;
use PixelgradeLT\Retailer\Authentication\ApiKey;
use PixelgradeLT\Retailer\Authentication;
use PixelgradeLT\Retailer\HTTP\Request;
use PixelgradeLT\Retailer\Integration;
use PixelgradeLT\Retailer\Provider;
use PixelgradeLT\Retailer\Repository;
use PixelgradeLT\Retailer\Screen;
use PixelgradeLT\Retailer\Storage;

/**
 * Plugin service provider class.
 *
 * @since 0.1.0
 */
class ServiceProvider implements ServiceProviderInterface {
	/**
	 * Register services.
	 *
	 * @param PimpleContainer $container Container instance.
	 */
	public function register( PimpleContainer $container ) {
		$container['api_key.factory'] = function () {
			return new ApiKey\Factory();
		};

		$container['api_key.repository'] = function ( $container ) {
			return new ApiKey\Repository(
				$container['api_key.factory']
			);
		};

		$container['authentication.servers'] = function ( $container ) {
			$servers = \apply_filters(
				'pixelgradelt_retailer/authentication_servers',
				[
					20  => 'authentication.api_key',
					100 => 'authentication.unauthorized', // The last server to take action.
				]
			);

			return new ServiceIterator( $container, $servers );
		};

		$container['authentication.api_key'] = function ( $container ) {
			return new ApiKey\Server(
				$container['api_key.repository']
			);
		};

		$container['authentication.unauthorized'] = function () {
			return new Authentication\UnauthorizedServer();
		};

		$container['client.composer'] = function ( $container ) {
			return new Client\ComposerClient(
				$container['storage.composer_working_directory']
			);
		};

		$container['client.composer.custom_token_auth'] = function () {
			return new Client\CustomTokenAuthentication();
		};

		$container['composition.manager'] = function ( $container ) {
			return new CompositionManager(
				$container['repository.solutions'],
				$container['purchased_solution.manager'],
				$container['client.composer'],
				$container['version.parser'],
				$container['logs.logger'],
				$container['hash.generator']
			);
		};

		$container['crypter'] = function () {
			$crypter = new StringCrypter();
			// Load the encryption key from the environment.
			try {
				$crypter->loadEncryptionKey( Env::get('LTRETAILER_ENCRYPTION_KEY') );
			} catch ( PixelgradeltRetailerException $e ) {
				// Do nothing right now.
				// We should handle a failed encryption setup through health checks and when attempting to encrypt or decrypt.
			}

			return $crypter;
		};

		$container['db.purchased_solutions'] = function () {
			// Handle the purchased solution custom DB tables (create, upgrade, etc).
			// This just needs to be instantiated.
			return new Database\Tables\PurchasedSolutions();
		};

		$container['hash.generator'] = function ( $container ) {
			// We will use the randomly generated storage directory name as the salt,
			// so that if that changes the hashes are also invalidated.
			return new StringHashes(
				$container['storage.working_directory_name'],
				8
			);
		};

		$container['hooks.activation'] = function () {
			return new Provider\Activation();
		};

		$container['hooks.admin_assets'] = function () {
			return new Provider\AdminAssets();
		};

		$container['hooks.authentication'] = function ( $container ) {
			return new Provider\Authentication(
				$container['authentication.servers'],
				$container['http.request']
			);
		};

		$container['hooks.capabilities'] = function ( $container ) {
			return new Provider\Capabilities(
				$container['composition.manager']
			);
		};

		$container['hooks.composition_post_type'] = function ( $container ) {
			return new PostType\CompositionPostType(
				$container['composition.manager']
			);
		};

		$container['hooks.compositions'] = function ( $container ) {
			return new Provider\Compositions(
				$container['composition.manager'],
				$container['solution.manager'],
				$container['logs.logger']
			);
		};

		$container['hooks.custom_vendor'] = function () {
			return new Provider\CustomVendor();
		};

		$container['hooks.deactivation'] = function () {
			return new Provider\Deactivation();
		};

		$container['hooks.health_check'] = function ( $container ) {
			return new Provider\HealthCheck(
				$container['http.request']
			);
		};

		$container['hooks.i18n'] = function () {
			return new I18n();
		};

		$container['hooks.solution_post_type'] = function ( $container ) {
			return new PostType\SolutionPostType(
				$container['solution.manager']
			);
		};

		$container['hooks.request_handler'] = function ( $container ) {
			return new Provider\RequestHandler(
				$container['http.request'],
				$container['route.controllers']
			);
		};

		$container['hooks.rest'] = function ( $container ) {
			return new Provider\REST( $container['rest.controllers'] );
		};

		$container['hooks.rewrite_rules'] = function () {
			return new Provider\RewriteRules();
		};

		$container['hooks.upgrade'] = function ( $container ) {
			return new Provider\Upgrade(
				$container['repository.solutions'],
				$container['storage.packages'],
				$container['htaccess.handler'],
				$container['logs.logger']
			);
		};

		$container['htaccess.handler'] = function ( $container ) {
			return new Htaccess( $container['storage.working_directory'] );
		};

		$container['http.request'] = function () {
			$request = new Request( $_SERVER['REQUEST_METHOD'] ?? '' );

			// phpcs:ignore WordPress.Security.NonceVerification.NoNonceVerification
			$request->set_query_params( \wp_unslash( $_GET ) );
			$request->set_header( 'Authorization', get_authorization_header() );

			if ( isset( $_SERVER['PHP_AUTH_USER'] ) ) {
				$request->set_header( 'PHP_AUTH_USER', $_SERVER['PHP_AUTH_USER'] );
				$request->set_header( 'PHP_AUTH_PW', $_SERVER['PHP_AUTH_PW'] ?? null );
			}

			return $request;
		};

		$container['logs.logger'] = function ( $container ) {
			return new Logger(
				$container['logs.level'],
				[
					$container['logs.handlers.file'],
				]
			);
		};

		$container['logs.level'] = function () {
			// Log warnings and above when WP_DEBUG is enabled.
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				$level = LogLevel::DEBUG;
			}

			return $level ?? LogLevel::INFO;
		};

		$container['logs.handlers.file'] = function () {
			return new FileLogHandler();
		};

		$container['logs.manager'] = function ( $container ) {
			return new LogsManager( $container['logs.logger'] );
		};

		$container['solution.factory'] = function ( $container ) {
			return new SolutionFactory(
				$container['solution.manager'],
				$container['logs.logger']
			);
		};

		$container['solution.manager'] = function ( $container ) {
			return new SolutionManager(
				$container['client.composer'],
				$container['version.parser'],
				$container['logs.logger']
			);
		};

		$container['purchased_solution.manager'] = function ( $container ) {
			return new PurchasedSolutionManager(
				$container['solution.manager'],
				$container['db.purchased_solutions'],
				$container['logs.logger']
			);
		};

		$container['repository.solutions'] = function ( $container ) {
			return new Repository\Solutions(
				$container['solution.factory'],
				$container['solution.manager']
			);
		};

		$container['rest.controller.api_keys'] = function ( $container ) {
			return new REST\ApiKeysController(
				'pixelgradelt_retailer/v1',
				'apikeys',
				$container['api_key.factory'],
				$container['api_key.repository']
			);
		};

		$container['rest.controller.compositions'] = function ( $container ) {
			return new REST\CompositionsController(
				'pixelgradelt_retailer/v1',
				'compositions',
				$container['composition.manager'],
				$container['transformer.composer_package'],
				$container['crypter']
			);
		};

		$container['rest.controller.solutions'] = function ( $container ) {
			return new REST\SolutionsController(
				'pixelgradelt_retailer/v1',
				'solutions',
				$container['repository.solutions'],
				$container['transformer.composer_package']
			);
		};

		$container['rest.controllers'] = function ( $container ) {
			return new ServiceIterator(
				$container,
				[
					'api_keys'     => 'rest.controller.api_keys',
					'compositions' => 'rest.controller.compositions',
					'solutions'    => 'rest.controller.solutions',
				]
			);
		};

		$container['route.composer.solutions'] = function ( $container ) {
			return new Route\ComposerPackages(
				$container['repository.solutions'],
				$container['transformer.composer_repository']
			);
		};

		$container['route.controllers'] = function ( $container ) {
			return new ServiceLocator(
				$container,
				[
					'composer_solutions' => 'route.composer.solutions',
				]
			);
		};

		$container['screen.edit_solution'] = function ( $container ) {
			return new Screen\EditSolution(
				$container['solution.manager'],
				$container['repository.solutions'],
				$container['transformer.composer_package']
			);
		};

		$container['screen.list_solutions'] = function ( $container ) {
			return new Screen\ListSolutions(
				$container['solution.manager']
			);
		};

		$container['screen.edit_composition'] = function ( $container ) {
			return new Screen\EditComposition(
				$container['composition.manager'],
				$container['solution.manager'],
				$container['purchased_solution.manager'],
				$container['transformer.composer_package']
			);
		};

		$container['screen.list_compositions'] = function ( $container ) {
			return new Screen\ListCompositions(
				$container['composition.manager'],
				$container['solution.manager']
			);
		};

		$container['screen.edit_user'] = function ( $container ) {
			return new Screen\EditUser(
				$container['api_key.repository']
			);
		};

		$container['screen.settings'] = function ( $container ) {
			return new Screen\Settings(
				$container['repository.solutions'],
				$container['api_key.repository'],
				$container['transformer.composer_package']
			);
		};

		$container['storage.packages'] = function ( $container ) {
			$path = \path_join( $container['storage.working_directory'], 'packages/' );

			return new Storage\Local( $path );
		};

		$container['storage.working_directory'] = function ( $container ) {
			if ( \defined( 'PixelgradeLT\Retailer\WORKING_DIRECTORY' ) ) {
				return \PixelgradeLT\Retailer\WORKING_DIRECTORY;
			}

			$upload_config = \wp_upload_dir();
			$path          = \path_join( $upload_config['basedir'], $container['storage.working_directory_name'] );

			return (string) \trailingslashit( \apply_filters( 'pixelgradelt_retailer/working_directory', $path ) );
		};

		$container['storage.working_directory_name'] = function () {
			$directory = \get_option( 'pixelgradelt_retailer_working_directory' );

			if ( ! empty( $directory ) ) {
				return $directory;
			}

			// Append a random string to help hide it from nosey visitors.
			$directory = sprintf( 'pixelgradelt_retailer-%s', generate_random_string() );

			// Save the working directory so we will always use the same directory.
			\update_option( 'pixelgradelt_retailer_working_directory', $directory );

			return $directory;
		};

		$container['storage.composer_working_directory'] = function ( $container ) {
			return \path_join( $container['storage.working_directory'], 'composer/' );
		};

		$container['transformer.composer_package'] = function ( $container ) {
			return new ComposerSolutionTransformer( $container['solution.factory'] );
		};

		$container['transformer.composer_repository'] = function ( $container ) {
			return new ComposerSolutionsRepositoryTransformer(
				$container['transformer.composer_package'],
				$container['solution.manager'],
				$container['version.parser'],
				$container['logs.logger']
			);
		};

		$container['version.parser'] = function () {
			return new ComposerVersionParser( new VersionParser() );
		};

		/*
		 * INTEGRATIONS
		 */

		$container['plugin.members'] = function () {
			return new Integration\Members();
		};

		$container['plugin.woocommerce'] = function ( $container ) {
			return new Integration\WooCommerce(
				$container['purchased_solution.manager'],
				$container['logs.logger']
			);
		};

		$container['plugin.woocommerce.screen.edit_solution'] = function ( $container ) {
			return new Integration\WooCommerce\Screen\EditSolution(
				$container['solution.manager'],
				$container['repository.solutions']
			);
		};

		$container['plugin.woocommerce.screen.list_solutions'] = function ( $container ) {
			return new Integration\WooCommerce\Screen\ListSolutions( $container['solution.manager'] );
		};

		$container['plugin.woocommerce.screen.list_wooproducts'] = function ( $container ) {
			return new Integration\WooCommerce\Screen\ListWooProducts( $container['solution.manager'] );
		};
	}
}
