<?php
/**
 * Solution manager.
 *
 * @since   0.1.0
 * @license GPL-2.0-or-later
 * @package PixelgradeLT
 */

declare ( strict_types=1 );

namespace PixelgradeLT\Retailer;

use PixelgradeLT\Retailer\Authentication\ApiKey\Server;
use PixelgradeLT\Retailer\Client\ComposerClient;
use Psr\Log\LoggerInterface;

/**
 * Solution manager class.
 *
 * Handles the logic related to configuring solutions through a CPT.
 *
 * @since 0.1.0
 */
class SolutionManager {

	const POST_TYPE = 'ltsolution';
	const POST_TYPE_PLURAL = 'ltsolutions';

	const TYPE_TAXONOMY = 'ltsolution_types';
	const TYPE_TAXONOMY_SINGULAR = 'ltsolution_type';

	const CATEGORY_TAXONOMY = 'ltsolution_categories';
	const CATEGORY_TAXONOMY_SINGULAR = 'ltsolution_category';

	const KEYWORD_TAXONOMY = 'ltsolution_keywords';
	const KEYWORD_TAXONOMY_SINGULAR = 'ltsolution_keyword';

	/**
	 * External Composer repository client.
	 *
	 * @var ComposerClient
	 */
	protected ComposerClient $composer_client;

	/**
	 * Composer version parser.
	 *
	 * @var ComposerVersionParser
	 */
	protected ComposerVersionParser $composer_version_parser;

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
	 * @param ComposerClient        $composer_client
	 * @param ComposerVersionParser $composer_version_parser
	 * @param LoggerInterface       $logger Logger.
	 */
	public function __construct(
		ComposerClient $composer_client,
		ComposerVersionParser $composer_version_parser,
		LoggerInterface $logger
	) {

		$this->composer_client         = $composer_client;
		$this->composer_version_parser = $composer_version_parser;
		$this->logger                  = $logger;
	}

	/**
	 * @since 0.1.0
	 *
	 * @param array $args Optional. Any provided args will be merged and overwrite default ones.
	 *
	 * @return array
	 */
	public function get_solution_post_type_args( array $args = [] ): array {
		$labels = [
			'name'                  => esc_html__( 'LT Solutions', 'pixelgradelt_retailer' ),
			'singular_name'         => esc_html__( 'LT Solution', 'pixelgradelt_retailer' ),
			'menu_name'             => esc_html_x( 'LT Solutions', 'Admin Menu text', 'pixelgradelt_retailer' ),
			'add_new'               => esc_html_x( 'Add New', 'LT Solution', 'pixelgradelt_retailer' ),
			'add_new_item'          => esc_html__( 'Add New LT Solution', 'pixelgradelt_retailer' ),
			'new_item'              => esc_html__( 'New LT Solution', 'pixelgradelt_retailer' ),
			'edit_item'             => esc_html__( 'Edit LT Solution', 'pixelgradelt_retailer' ),
			'view_item'             => esc_html__( 'View LT Solution', 'pixelgradelt_retailer' ),
			'all_items'             => esc_html__( 'All Solutions', 'pixelgradelt_retailer' ),
			'search_items'          => esc_html__( 'Search Solutions', 'pixelgradelt_retailer' ),
			'not_found'             => esc_html__( 'No solutions found.', 'pixelgradelt_retailer' ),
			'not_found_in_trash'    => esc_html__( 'No solutions found in Trash.', 'pixelgradelt_retailer' ),
			'uploaded_to_this_item' => esc_html__( 'Uploaded to this solution', 'pixelgradelt_retailer' ),
			'filter_items_list'     => esc_html__( 'Filter solutions list', 'pixelgradelt_retailer' ),
			'items_list_navigation' => esc_html__( 'Solutions list navigation', 'pixelgradelt_retailer' ),
			'items_list'            => esc_html__( 'LT Solutions list', 'pixelgradelt_retailer' ),
		];

		return array_merge( [
			'labels'             => $labels,
			'description'        => esc_html__( 'Solutions to be purchased and used to determine the PixelgradeLT parts delivered to PixelgradeLT users.', 'pixelgradelt_retailer' ),
			'hierarchical'       => false,
			'public'             => false,
			'publicly_queryable' => false,
			'has_archive'        => false,
			'rest_base'          => static::POST_TYPE_PLURAL,
			'show_ui'            => true,
			'show_in_menu'       => true,
			'show_in_nav_menus'  => false,
			'show_in_rest'       => true,
			'map_meta_cap'       => true,
			'supports'           => [
				'title',
				'revisions',
				'custom-fields',
			],
		], $args );
	}

	/**
	 * @since 0.1.0
	 *
	 * @param array $args Optional. Any provided args will be merged and overwrite default ones.
	 *
	 * @return array
	 */
	public function get_solution_type_taxonomy_args( array $args = [] ): array {
		$labels = [
			'name'                  => esc_html__( 'Solution Types', 'pixelgradelt_retailer' ),
			'singular_name'         => esc_html__( 'Solution Type', 'pixelgradelt_retailer' ),
			'add_new'               => esc_html_x( 'Add New', 'LT Solution Type', 'pixelgradelt_retailer' ),
			'add_new_item'          => esc_html__( 'Add New Solution Type', 'pixelgradelt_retailer' ),
			'update_item'           => esc_html__( 'Update Solution Type', 'pixelgradelt_retailer' ),
			'new_item_name'         => esc_html__( 'New Solution Type Name', 'pixelgradelt_retailer' ),
			'edit_item'             => esc_html__( 'Edit Solution Type', 'pixelgradelt_retailer' ),
			'all_items'             => esc_html__( 'All Solution Types', 'pixelgradelt_retailer' ),
			'search_items'          => esc_html__( 'Search Solution Types', 'pixelgradelt_retailer' ),
			'parent_item'           => esc_html__( 'Parent Solution Type', 'pixelgradelt_retailer' ),
			'parent_item_colon'     => esc_html__( 'Parent Solution Type:', 'pixelgradelt_retailer' ),
			'not_found'             => esc_html__( 'No solution types found.', 'pixelgradelt_retailer' ),
			'no_terms'              => esc_html__( 'No solution types.', 'pixelgradelt_retailer' ),
			'items_list_navigation' => esc_html__( 'Solution Types list navigation', 'pixelgradelt_retailer' ),
			'items_list'            => esc_html__( 'Solution Types list', 'pixelgradelt_retailer' ),
			'back_to_items'         => esc_html__( '&larr; Go to Solution Types', 'pixelgradelt_retailer' ),
		];

		return array_merge( [
			'labels'             => $labels,
			'show_ui'            => true,
			'show_in_quick_edit' => true,
			'show_admin_column'  => true,
			'hierarchical'       => true,
			'capabilities'       => [
				'manage_terms' => Capabilities::MANAGE_SOLUTION_TYPES,
				'edit_terms'   => Capabilities::MANAGE_SOLUTION_TYPES,
				'delete_terms' => Capabilities::MANAGE_SOLUTION_TYPES,
				'assign_terms' => 'edit_posts',
			],
		], $args );
	}

	/**
	 * @since 0.1.0
	 *
	 * @param array $args Optional. Any provided args will be merged and overwrite default ones.
	 *
	 * @return array
	 */
	public function get_solution_category_taxonomy_args( array $args = [] ): array {
		$labels = [
			'name'                  => esc_html__( 'Solution Categories', 'pixelgradelt_retailer' ),
			'singular_name'         => esc_html__( 'Solution Category', 'pixelgradelt_retailer' ),
			'add_new'               => esc_html_x( 'Add New', 'LT Solution Category', 'pixelgradelt_retailer' ),
			'add_new_item'          => esc_html__( 'Add New Solution Category', 'pixelgradelt_retailer' ),
			'update_item'           => esc_html__( 'Update Solution Category', 'pixelgradelt_retailer' ),
			'new_item_name'         => esc_html__( 'New Solution Category Name', 'pixelgradelt_retailer' ),
			'edit_item'             => esc_html__( 'Edit Solution Category', 'pixelgradelt_retailer' ),
			'all_items'             => esc_html__( 'All Solution Categories', 'pixelgradelt_retailer' ),
			'search_items'          => esc_html__( 'Search Solution Categories', 'pixelgradelt_retailer' ),
			'parent_item'           => esc_html__( 'Parent Solution Category', 'pixelgradelt_retailer' ),
			'parent_item_colon'     => esc_html__( 'Parent Solution Category:', 'pixelgradelt_retailer' ),
			'not_found'             => esc_html__( 'No solution categories found.', 'pixelgradelt_retailer' ),
			'no_terms'              => esc_html__( 'No solution categories.', 'pixelgradelt_retailer' ),
			'items_list_navigation' => esc_html__( 'Solution Categories list navigation', 'pixelgradelt_retailer' ),
			'items_list'            => esc_html__( 'Solution Categories list', 'pixelgradelt_retailer' ),
			'back_to_items'         => esc_html__( '&larr; Go to Solution Categories', 'pixelgradelt_retailer' ),
		];

		return array_merge( [
			'labels'             => $labels,
			'show_ui'            => true,
			'show_in_quick_edit' => true,
			'show_admin_column'  => true,
			'hierarchical'       => true,
			'capabilities'       => [
				'manage_terms' => Capabilities::MANAGE_SOLUTION_CATEGORIES,
				'edit_terms'   => Capabilities::MANAGE_SOLUTION_CATEGORIES,
				'delete_terms' => Capabilities::MANAGE_SOLUTION_CATEGORIES,
				'assign_terms' => 'edit_posts',
			],
		], $args );
	}

	/**
	 * @since 0.1.0
	 *
	 * @param array $args Optional. Any provided args will be merged and overwrite default ones.
	 *
	 * @return array
	 */
	public function get_solution_keyword_taxonomy_args( array $args = [] ): array {
		$labels = [
			'name'                       => esc_html__( 'Solution Keywords', 'pixelgradelt_retailer' ),
			'singular_name'              => esc_html__( 'Solution Keyword', 'pixelgradelt_retailer' ),
			'add_new'                    => esc_html_x( 'Add New', 'LT Solution Keyword', 'pixelgradelt_retailer' ),
			'add_new_item'               => esc_html__( 'Add New Solution Keyword', 'pixelgradelt_retailer' ),
			'update_item'                => esc_html__( 'Update Solution Keyword', 'pixelgradelt_retailer' ),
			'new_item_name'              => esc_html__( 'New Solution Keyword Name', 'pixelgradelt_retailer' ),
			'edit_item'                  => esc_html__( 'Edit Solution Keyword', 'pixelgradelt_retailer' ),
			'all_items'                  => esc_html__( 'All Solution Keywords', 'pixelgradelt_retailer' ),
			'search_items'               => esc_html__( 'Search Solution Keywords', 'pixelgradelt_retailer' ),
			'not_found'                  => esc_html__( 'No solution keywords found.', 'pixelgradelt_retailer' ),
			'no_terms'                   => esc_html__( 'No solution keywords.', 'pixelgradelt_retailer' ),
			'separate_items_with_commas' => esc_html__( 'Separate keywords with commas.', 'pixelgradelt_retailer' ),
			'choose_from_most_used'      => esc_html__( 'Choose from the most used keywords.', 'pixelgradelt_retailer' ),
			'most_used'                  => esc_html__( 'Most used.', 'pixelgradelt_retailer' ),
			'items_list_navigation'      => esc_html__( 'Solution Keywords list navigation', 'pixelgradelt_retailer' ),
			'items_list'                 => esc_html__( 'Solution Keywords list', 'pixelgradelt_retailer' ),
			'back_to_items'              => esc_html__( '&larr; Go to Solution Keywords', 'pixelgradelt_retailer' ),
		];

		return array_merge( [
			'labels'             => $labels,
			'show_ui'            => true,
			'show_in_quick_edit' => true,
			'show_admin_column'  => true,
			'hierarchical'       => false,
		], $args );
	}

	/**
	 * Get post ids for solutions by type.
	 *
	 * @param string $types Optional. Solution types. Default is to query for all solution types.
	 *
	 * @return int[] Solution post ids.
	 */
	public function get_solution_type_ids( string $types = 'all' ): array {

		return $this->get_solution_ids_by( [
			'solution_type' => $types,
		] );
	}

	/**
	 * Identify solution post IDs based on certain details.
	 *
	 * @param array $args Array of solution details to look for.
	 *
	 * @return int[] The solution post IDs list.
	 */
	public function get_solution_ids_by( array $args ): array {
		$query_args = [
			'post_type'        => static::POST_TYPE,
			'fields'           => 'ids',
			'post_status'      => [ 'publish', 'draft', 'private', ],
			'tax_query'        => [],
			'meta_query'       => [],
			'nopaging'         => true,
			'no_found_rows'    => true,
			'suppress_filters' => true,
		];

		// This allows us to query for specific package post types.
		if ( ! empty( $args['post_type'] ) ) {
			$query_args['post_type'] = $args['post_type'];
		}

		if ( ! empty( $args['solution_type'] ) && 'all' !== $args['solution_type'] ) {
			if ( is_string( $args['solution_type'] ) ) {
				$args['solution_type'] = [ $args['solution_type'] ];
			}

			$args['solution_type'] = array_filter( array_values( $args['solution_type'] ), 'is_string' );

			$query_args['tax_query'][] = [
				'taxonomy' => static::TYPE_TAXONOMY,
				'field'    => 'slug',
				'terms'    => $args['solution_type'],
				'operator' => 'IN',
			];
		}

		if ( ! empty( $args['solution_category'] ) && 'all' !== $args['solution_category'] ) {
			if ( is_string( $args['solution_category'] ) ) {
				$args['solution_category'] = [ $args['solution_category'] ];
			}

			$args['solution_category'] = array_filter( array_values( $args['solution_category'] ), 'is_string' );

			$query_args['tax_query'][] = [
				'taxonomy' => static::CATEGORY_TAXONOMY,
				'field'    => 'slug',
				'terms'    => $args['solution_category'],
				'operator' => 'IN',
			];
		}

		if ( ! empty( $args['post_ids'] ) ) {
			if ( ! is_array( $args['post_ids'] ) ) {
				$args['post_ids'] = [ intval( $args['post_ids'] ) ];
			}

			$query_args['post__in'] = $args['post_ids'];
		}

		if ( ! empty( $args['exclude_post_ids'] ) ) {
			if ( ! is_array( $args['exclude_post_ids'] ) ) {
				$args['exclude_post_ids'] = [ intval( $args['exclude_post_ids'] ) ];
			}

			$query_args['post__not_in'] = $args['exclude_post_ids'];
		}

		if ( ! empty( $args['slug'] ) ) {
			if ( is_string( $args['slug'] ) ) {
				$args['slug'] = [ $args['slug'] ];
			}

			$query_args['post_name__in'] = $args['slug'];
		}

		if ( ! empty( $args['post_status'] ) ) {
			$query_args['post_status'] = $args['post_status'];
		}

		$query       = new \WP_Query( $query_args );
		$package_ids = $query->get_posts();

		if ( empty( $package_ids ) ) {
			return [];
		}

		return $package_ids;
	}

	/**
	 * Gather all the data about a solution ID.
	 *
	 * @param int $post_ID The solution post ID.
	 *
	 * @return array The solution data we have available.
	 */
	public function get_solution_id_data( int $post_ID ): array {
		$data = [];

		// First, some checking.
		if ( ! $this->check_post_id( $post_ID ) ) {
			return $data;
		}

		$data['is_managed']      = true;
		$data['managed_post_id'] = $post_ID;

		$data['name']        = $this->get_post_package_name( $post_ID );
		$data['type']        = $this->get_post_package_type( $post_ID );
		$data['slug']        = $this->get_post_package_slug( $post_ID );
		$data['keywords']    = $this->get_post_package_keywords( $post_ID );
		$data['description'] = get_post_meta( $post_ID, '_package_details_description', true );
		$data['homepage']    = get_post_meta( $post_ID, '_package_details_homepage', true );

		switch ( $data['source_type'] ) {
			case 'packagist.org':
			case 'wpackagist.org':
				break;
			case 'vcs':
				$data['vcs_url'] = get_post_meta( $post_ID, '_package_vcs_url', true );
				break;
			case 'local.plugin':
				$data['local_plugin_file'] = get_post_meta( $post_ID, '_package_local_plugin_file', true );

				// Determine if plugin is actually (still) installed.
				$data['local_installed'] = false;
				$installed_plugins       = get_plugins();
				if ( in_array( $data['local_plugin_file'], array_keys( $installed_plugins ) ) ) {
					$data['local_installed'] = true;
				}
				break;
			case 'local.theme':
				$data['local_theme_slug'] = get_post_meta( $post_ID, '_package_local_theme_slug', true );

				// Determine if theme is actually (still) installed.
				$data['local_installed'] = false;
				$installed_themes        = search_theme_directories();
				if ( is_array( $installed_themes ) && in_array( $data['local_theme_slug'], array_keys( $installed_themes ) ) ) {
					$data['local_installed'] = true;
				}
				break;
			case 'local.manual':
				break;
			default:
				// Nothing
				break;
		}

		if ( in_array( $data['source_type'], [ 'packagist.org', 'wpackagist.org', 'vcs', ] ) ) {
			$data['source_version_range'] = trim( get_post_meta( $post_ID, '_package_source_version_range', true ) );
			$data['source_stability']     = trim( get_post_meta( $post_ID, '_package_source_stability', true ) );

			// Get the source version/release packages data (fetched from the external repo) we have stored.
			$source_cached_release_packages = get_post_meta( $post_ID, '_package_source_cached_release_packages', true );
			if ( ! empty( $source_cached_release_packages[ $data['source_name'] ] ) ) {
				$data['source_cached_release_packages'] = $source_cached_release_packages[ $data['source_name'] ];
			}
		}

		$data['required_packages'] = $this->get_post_package_required_packages( $post_ID );
		$data['composer_require']  = $this->get_post_package_composer_require( $post_ID );

		return $data;
	}

	/**
	 * Check if a given post ID should be handled by the manager.
	 *
	 * @param int $post_ID
	 *
	 * @return bool
	 */
	protected function check_post_id( int $post_ID ): bool {
		if ( empty( $post_ID ) ) {
			return false;
		}
		$post = get_post( $post_ID );
		if ( empty( $post ) || static::POST_TYPE !== $post->post_type ) {
			return false;
		}

		return true;
	}

	/**
	 * Identify a package post ID based on certain details about it and return all configured data about it.
	 *
	 * @param array $args Array of package details to look for.
	 *
	 * @return array The found package data.
	 */
	public function get_managed_package_data_by( array $args ): array {
		$found_package_id = $this->get_solution_ids_by( $args );
		if ( empty( $found_package_id ) ) {
			return [];
		}

		// Make sure we only tackle the first package found.
		$found_package_id = reset( $found_package_id );

		return $this->get_solution_id_data( $found_package_id );
	}

	/**
	 * Given an external (packagist.org, wpackagist.org, or vcs) managed package post ID, fetch the remote releases data.
	 *
	 * @param int $post_ID
	 *
	 * @throws \Exception
	 * @return array
	 */
	public function fetch_external_package_remote_releases( int $post_ID ): array {
		$releases = [];

		$post = get_post( $post_ID );
		if ( empty( $post ) || ! in_array( $post->post_status, [ 'publish', 'draft', 'private', ] ) ) {
			return [];
		}

		$package_data = $this->get_solution_id_data( $post_ID );
		if ( empty( $package_data['source_type'] ) || ! in_array( $package_data['source_type'], [
				'packagist.org',
				'wpackagist.org',
				'vcs',
			] ) ) {
			return [];
		}

		$client = $this->get_composer_client();

		$version_range = ! empty( $package_data['source_version_range'] ) ? $package_data['source_version_range'] : '*';
		$stability     = ! empty( $package_data['source_stability'] ) ? $package_data['source_stability'] : 'stable';

		try {
			switch ( $package_data['source_type'] ) {
				case 'packagist.org':
					// Pass along a Satis configuration to get packages.
					$releases = $client->getPackages( [
						// The packagist.org repository is available by default.
						'require'                       => [
							$package_data['source_name'] => $version_range,
						],
						'minimum-stability-per-package' => [
							$package_data['source_name'] => $stability,
						],
					] );
					break;
				case 'wpackagist.org':
					// Pass along a Satis configuration to get packages.
					$releases = $client->getPackages( [
						'repositories'                  => [
							[
								// Disable the default packagist.org repo so we don't mistakenly fetch from there.
								'packagist.org' => false,
							],
							[
								'type' => 'composer',
								'url'  => 'https://wpackagist.org',
								'only' => [
									'wpackagist-plugin/*',
									'wpackagist-theme/*',
								],
							],
						],
						'require'                       => [
							$package_data['source_name'] => $version_range,
						],
						'minimum-stability-per-package' => [
							$package_data['source_name'] => $stability,
						],
					] );
					break;
				case 'vcs':
					if ( empty( $package_data['vcs_url'] ) ) {
						break;
					}
					// Pass along a Satis configuration to get packages.
					$releases = $client->getPackages( [
						'repositories'                  => [
							[
								// Disable the default packagist.org repo so we don't mistakenly fetch from there.
								'packagist.org' => false,
							],
							[
								'type' => 'vcs',
								'url'  => $package_data['vcs_url'],
							],
						],
						'require'                       => [
							$package_data['source_name'] => $version_range,
						],
						'minimum-stability-per-package' => [
							$package_data['source_name'] => $stability,
						],
					] );
					break;
				default:
					break;
			}
		} catch ( \Exception $e ) {
			$this->logger->error(
				'Error fetching external packages with the Composer Client for package "{package}" (source type {source_type}).',
				[
					'exception'   => $e,
					'package'     => $package_data['source_name'],
					'source_type' => $package_data['source_type'],
				]
			);
		}

		if ( ! empty( $releases ) ) {
			$releases = $client->standardizePackagesForJson( $releases, $stability );
		}

		return $releases;
	}

	public function get_managed_installed_plugins( array $query_args = [] ): array {
		$all_plugins_files = array_keys( get_plugins() );

		// Get all package posts that use installed plugins.
		$query       = new \WP_Query( array_merge( [
			'post_type'        => static::POST_TYPE,
			'fields'           => 'ids',
			'post_status'      => [ 'publish', 'draft', 'private', ],
			'meta_query'       => [
				[
					'key'     => '_package_source_type',
					'value'   => 'local.plugin',
					'compare' => '=',
				],
			],
			'nopaging'         => true,
			'no_found_rows'    => true,
			'suppress_filters' => true,
		], $query_args ) );
		$package_ids = $query->get_posts();

		// Go through all posts and gather all the plugin_file values.
		$used_plugin_files = [];
		foreach ( $package_ids as $package_id ) {
			$plugin_file = get_post_meta( $package_id, '_package_local_plugin_file', true );
			if ( ! empty( $plugin_file ) && in_array( $plugin_file, $all_plugins_files ) ) {
				$used_plugin_files[] = $plugin_file;
			}
		}

		return $used_plugin_files;
	}

	public function get_managed_installed_themes( array $query_args = [] ): array {
		$all_theme_slugs = array_keys( wp_get_themes() );

		// Get all package posts that use installed themes.
		$query       = new \WP_Query( array_merge( [
			'post_type'        => static::POST_TYPE,
			'fields'           => 'ids',
			'post_status'      => [ 'publish', 'draft', 'private', ],
			'meta_query'       => [
				[
					'key'     => '_package_source_type',
					'value'   => 'local.theme',
					'compare' => '=',
				],
			],
			'nopaging'         => true,
			'no_found_rows'    => true,
			'suppress_filters' => true,
		], $query_args ) );
		$package_ids = $query->get_posts();

		// Go through all posts and gather all the theme_slug values.
		$used_theme_slugs = [];
		foreach ( $package_ids as $package_id ) {
			$theme_slug = get_post_meta( $package_id, '_package_local_theme_slug', true );
			if ( ! empty( $theme_slug ) && in_array( $theme_slug, $all_theme_slugs ) ) {
				$used_theme_slugs[] = $theme_slug;
			}
		}

		return $used_theme_slugs;
	}

	public function get_post_package_name( int $post_ID ): string {
		$post = get_post( $post_ID );
		if ( empty( $post ) ) {
			return '';
		}

		return $post->post_title;
	}

	public function get_post_package_type( int $post_ID ): string {
		/** @var \WP_Error|\WP_Term[] $package_type */
		$package_type = wp_get_post_terms( $post_ID, static::TYPE_TAXONOMY );
		if ( is_wp_error( $package_type ) || empty( $package_type ) ) {
			return '';
		}
		$package_type = reset( $package_type );

		return $package_type->slug;
	}

	public function get_post_package_slug( int $post_ID ): string {
		$post = get_post( $post_ID );
		if ( empty( $post ) ) {
			return '';
		}

		return $post->post_name;
	}

	public function get_post_package_keywords( int $post_ID ): array {
		$keywords = wp_get_post_terms( $post_ID, static::KEYWORD_TAXONOMY );
		if ( is_wp_error( $keywords ) || empty( $keywords ) ) {
			return [];
		}

		// We need to return the keywords slugs, not the WP_Term list.
		$keywords = array_map( function ( $term ) {
			if ( $term instanceof \WP_Term ) {
				$term = $term->slug;
			}

			return $term;
		}, $keywords );

		return $keywords;
	}

	public function set_post_package_keywords( int $post_ID, array $keywords ): bool {
		$result = wp_set_post_terms( $post_ID, $keywords, static::KEYWORD_TAXONOMY );
		if ( false === $result || is_wp_error( $result ) ) {
			return false;
		}

		return true;
	}

	public function get_post_package_required_packages( int $post_ID, string $pseudo_id_delimiter = ' #', string $container_id = '' ): array {
		$required_packages = carbon_get_post_meta( $post_ID, 'package_required_packages', $container_id );
		if ( empty( $required_packages ) || ! is_array( $required_packages ) ) {
			return [];
		}

		// Make sure only the fields we are interested in are left.
		$accepted_keys = array_fill_keys( [ 'pseudo_id', 'version_range', 'stability' ], '' );
		foreach ( $required_packages as $key => $required_package ) {
			$required_packages[ $key ] = array_replace( $accepted_keys, array_intersect_key( $required_package, $accepted_keys ) );

			if ( empty( $required_package['pseudo_id'] ) || false === strpos( $required_package['pseudo_id'], $pseudo_id_delimiter ) ) {
				unset( $required_packages[ $key ] );
				continue;
			}

			// We will now split the pseudo_id in its components (source_name and post_id with the delimiter in between).
			[ $source_name, $post_id ] = explode( $pseudo_id_delimiter, $required_package['pseudo_id'] );
			if ( empty( $post_id ) ) {
				unset( $required_packages[ $key ] );
				continue;
			}

			$required_packages[ $key ]['source_name']     = $source_name;
			$required_packages[ $key ]['managed_post_id'] = intval( $post_id );
		}

		return $required_packages;
	}

	public function set_post_package_required_packages( int $post_ID, array $required_packages, string $container_id = '' ) {
		carbon_set_post_meta( $post_ID, 'package_required_packages', $required_packages, $container_id );
	}

	public function get_post_package_composer_require( int $post_ID, string $pseudo_id_delimiter = ' #', string $container_id = '' ): array {
		// We don't currently allow defining a per-package Composer require list.
		return [];
	}

	public function set_post_package_composer_require( int $post_ID, array $composer_require, string $container_id = '' ) {
		// Nothing right now.
		//		carbon_set_post_meta( $post_ID, 'package_composer_require', $composer_require, $container_id );
	}

	/**
	 * Given a package, dry-run a composer require of it (including its required packages) and see if all goes well.
	 *
	 * @param Package $package
	 *
	 * @return bool
	 */
	public function dry_run_package_require( Package $package ): bool {
		$client = $this->get_composer_client();

		try {
			$client->getPackages( [
				'repositories'                  => [
					[
						// Our very own Composer repo.
						'type'    => 'composer',
						'url'     => get_solutions_permalink( [ 'base' => true ] ),
						'options' => [
							'ssl'  => [
								'verify_peer' => ! is_debug_mode(),
							],
							'http' => [
								'header' => ! empty( $_ENV['PHP_AUTH_USER'] ) ? [
									'Authorization: Basic ' . base64_encode( $_ENV['PHP_AUTH_USER'] . ':' . Server::AUTH_PWD ),
								] : [],
							],
						],
					],
					[
						// We want the packagist.org repo since we are checking for package dependencies also.
						'type' => 'composer',
						'url'  => 'https://repo.packagist.org',
					],
				],
				'require-dependencies'          => true,
				'only-best-candidates'          => true,
				'require'                       => [
					// Any package version.
					$package->get_name() => '*',
				],
				'minimum-stability-per-package' => [
					// The loosest stability.
					$package->get_name() => 'dev',
				],
				// Since we are just simulating, it doesn't make sense to check the platform requirements (like PHP version, PHP extensions, etc).
				'ignore-platform-reqs'          => true,
			] );
		} catch ( \Exception $e ) {
			$this->logger->error(
				'Error during Composer require dry-run for package "{package}" (type {type}).' . PHP_EOL . $e->getMessage(),
				[
					'exception' => $e,
					'package'   => $package->get_name(),
					'type'      => $package->get_type(),
				]
			);

			return false;
		}

		return true;
	}

	/**
	 * Whether a solution is public (published).
	 *
	 * @since 0.1.0
	 *
	 * @param Package $solution
	 *
	 * @return bool
	 */
	public function is_solution_public( Package $solution ): bool {
		if ( ! $solution->is_managed() || ! $solution->get_managed_post_id() ) {
			return true;
		}

		if ( 'publish' === \get_post_status( $solution->get_managed_post_id() ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Get the visibility status of a solution (public, draft, private).
	 *
	 * @since 0.1.0
	 *
	 * @param Package $solution
	 *
	 * @return string The visibility status of the solution. One of: public, draft, private.
	 */
	public function get_solution_visibility( Package $solution ): string {
		if ( ! $solution->is_managed() || ! $solution->get_managed_post_id() ) {
			return 'public';
		}

		switch ( \get_post_status( $solution->get_managed_post_id() ) ) {
			case 'publish':
				return 'public';
			case 'draft':
				return 'draft';
			case 'private':
			default:
				return 'private';
		}
	}

	/**
	 * Normalize a version string according to Composer logic.
	 *
	 * @since 0.1.0
	 *
	 * @throws \UnexpectedValueException
	 * @param string $version
	 *
	 * @return string
	 */
	public function normalize_version( string $version ): string {
		return $this->composer_version_parser->normalize( $version );
	}

	public function get_composer_client(): ComposerClient {
		return $this->composer_client;
	}

	public function get_composer_version_parser(): ComposerVersionParser {
		return $this->composer_version_parser;
	}
}
