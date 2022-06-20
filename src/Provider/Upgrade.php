<?php
/**
 * Upgrade routines.
 *
 * @package Pressody
 * @license GPL-2.0-or-later
 * @since 0.1.0
 */

declare ( strict_types = 1 );

namespace Pressody\Retailer\Provider;

use Cedaro\WP\Plugin\AbstractHookProvider;
use Psr\Log\LoggerInterface;
use Pressody\Retailer\Htaccess;
use Pressody\Retailer\Repository\PackageRepository;
use Pressody\Retailer\Storage\Storage;
use Pressody\Retailer\Capabilities as Caps;

use const Pressody\Retailer\VERSION;

/**
 * Class for upgrade routines.
 *
 * @since 0.1.0
 */
class Upgrade extends AbstractHookProvider {
	/**
	 * Version option name.
	 *
	 * @var string
	 */
	const VERSION_OPTION_NAME = 'pressody_retailer_version';

	/**
	 * Htaccess handler.
	 *
	 * @var Htaccess
	 */
	protected Htaccess $htaccess;

	/**
	 * Logger.
	 *
	 * @var LoggerInterface
	 */
	protected LoggerInterface $logger;

	/**
	 * Solution repository.
	 *
	 * @var PackageRepository
	 */
	protected PackageRepository $repository;

	/**
	 * Storage service.
	 *
	 * @var Storage
	 */
	protected Storage $storage;

	/**
	 * Constructor.
	 *
	 * @since 0.1.0
	 *
	 * @param PackageRepository $repository Solution repository.
	 * @param Storage           $storage    Storage service.
	 * @param Htaccess          $htaccess   Htaccess handler.
	 * @param LoggerInterface   $logger     Logger.
	 */
	public function __construct(
		PackageRepository $repository,
		Storage $storage,
		Htaccess $htaccess,
		LoggerInterface $logger
	) {
		$this->htaccess        = $htaccess;
		$this->repository      = $repository;
		$this->storage         = $storage;
		$this->logger          = $logger;
	}

	/**
	 * Register hooks.
	 *
	 * @since 0.1.0
	 */
	public function register_hooks() {
		add_action( 'admin_init', [ $this, 'maybe_upgrade' ] );
	}

	/**
	 * Upgrade when the database version is outdated.
	 *
	 * @since 0.1.0
	 */
	public function maybe_upgrade() {
		$saved_version = get_option( self::VERSION_OPTION_NAME, '0' );

		if ( version_compare( $saved_version, '0.16.0', '<' ) ) {
			Caps::register();
		}

		if ( version_compare( $saved_version, VERSION, '<' ) ) {
			update_option( self::VERSION_OPTION_NAME, VERSION );
		}
	}
}
