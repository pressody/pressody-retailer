<?php
/**
 * Composition manager.
 *
 * @since   0.11.0
 * @license GPL-2.0-or-later
 * @package PixelgradeLT
 */

declare ( strict_types=1 );

namespace PixelgradeLT\Retailer;

use Env\Env;
use PixelgradeLT\Retailer\Authentication\ApiKey\Server;
use PixelgradeLT\Retailer\Client\ComposerClient;
use PixelgradeLT\Retailer\Repository\PackageRepository;
use PixelgradeLT\Retailer\Utils\ArrayHelpers;
use Psr\Log\LoggerInterface;

/**
 * Composition manager class.
 *
 * Handles the logic related to monitoring/configuring compositions through a CPT.
 *
 * @since 0.11.0
 */
class CompositionManager {

	const POST_TYPE = 'ltcomposition';
	const POST_TYPE_PLURAL = 'ltcompositions';

	const KEYWORD_TAXONOMY = 'ltcomposition_keywords';
	const KEYWORD_TAXONOMY_SINGULAR = 'ltcomposition_keyword';

	const LTRECORDS_API_PWD = 'pixelgradelt_records';

	/**
	 * Used to create the pseudo IDs saved as values for a composition's solutions.
	 * Don't change this without upgrading the data in the DB!
	 */
	const PSEUDO_ID_DELIMITER = ' #';

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
	 * Solutions repository.
	 *
	 * @var PackageRepository
	 */
	protected PackageRepository $solutions;

	/**
	 * Logger.
	 *
	 * @var LoggerInterface
	 */
	protected LoggerInterface $logger;

	/**
	 * Hasher.
	 *
	 * @var HasherInterface
	 */
	protected HasherInterface $hasher;

	/**
	 * Constructor.
	 *
	 * @since 0.11.0
	 *
	 * @param ComposerClient        $composer_client
	 * @param ComposerVersionParser $composer_version_parser
	 * @param PackageRepository     $solutions Solutions repository.
	 * @param LoggerInterface       $logger    Logger.
	 * @param HasherInterface       $hasher
	 */
	public function __construct(
		ComposerClient $composer_client,
		ComposerVersionParser $composer_version_parser,
		PackageRepository $solutions,
		LoggerInterface $logger,
		HasherInterface $hasher
	) {

		$this->composer_client         = $composer_client;
		$this->composer_version_parser = $composer_version_parser;
		$this->solutions               = $solutions;
		$this->logger                  = $logger;
		$this->hasher                  = $hasher;
	}

	/**
	 * @since 0.11.0
	 *
	 * @param array $args Optional. Any provided args will be merged and overwrite default ones.
	 *
	 * @return array
	 */
	public function get_composition_post_type_args( array $args = [] ): array {
		$labels = [
			'name'                  => esc_html__( 'LT Compositions', 'pixelgradelt_retailer' ),
			'singular_name'         => esc_html__( 'LT Composition', 'pixelgradelt_retailer' ),
			'menu_name'             => esc_html_x( 'LT Compositions', 'Admin Menu text', 'pixelgradelt_retailer' ),
			'add_new'               => esc_html_x( 'Add New', 'LT Composition', 'pixelgradelt_retailer' ),
			'add_new_item'          => esc_html__( 'Add New LT Composition', 'pixelgradelt_retailer' ),
			'new_item'              => esc_html__( 'New LT Composition', 'pixelgradelt_retailer' ),
			'edit_item'             => esc_html__( 'Edit LT Composition', 'pixelgradelt_retailer' ),
			'view_item'             => esc_html__( 'View LT Composition', 'pixelgradelt_retailer' ),
			'all_items'             => esc_html__( 'View Compositions', 'pixelgradelt_retailer' ),
			'search_items'          => esc_html__( 'Search Compositions', 'pixelgradelt_retailer' ),
			'not_found'             => esc_html__( 'No solutions found.', 'pixelgradelt_retailer' ),
			'not_found_in_trash'    => esc_html__( 'No solutions found in Trash.', 'pixelgradelt_retailer' ),
			'uploaded_to_this_item' => esc_html__( 'Uploaded to this solution', 'pixelgradelt_retailer' ),
			'filter_items_list'     => esc_html__( 'Filter solutions list', 'pixelgradelt_retailer' ),
			'items_list_navigation' => esc_html__( 'Compositions list navigation', 'pixelgradelt_retailer' ),
			'items_list'            => esc_html__( 'LT Compositions list', 'pixelgradelt_retailer' ),
		];

		return array_merge( [
			'labels'             => $labels,
			'description'        => esc_html__( 'Compositions are created when a user configures a new site out of PixelgradeLT solutions. E-commerce order items are linked to a composition.', 'pixelgradelt_retailer' ),
			'hierarchical'       => false,
			'public'             => false,
			'publicly_queryable' => false,
			'has_archive'        => false,
			'rest_base'          => static::POST_TYPE_PLURAL,
			'show_in_rest'       => false,
			'show_ui'            => true,
			'show_in_menu'       => true,
			'show_in_nav_menus'  => false,
			'supports'           => [
				'title',
				'custom-fields',
			],
			'capabilities'       => array(
				'edit_post'          => Capabilities::EDIT_COMPOSITION,
				'read_post'          => Capabilities::VIEW_COMPOSITION,
				'delete_post'        => Capabilities::EDIT_COMPOSITION,
				'edit_posts'         => Capabilities::EDIT_COMPOSITIONS,
				'edit_others_posts'  => Capabilities::EDIT_COMPOSITIONS,
				'delete_posts'       => Capabilities::EDIT_COMPOSITIONS,
				//				'publish_posts'      => 'do_not_allow', // Removes support for the post status and publish
				'read_private_posts' => Capabilities::VIEW_COMPOSITIONS,
				//				'create_posts'       => 'do_not_allow', // Removes support for the "Add New" function
			),
			'map_meta_cap'       => true,
		], $args );
	}

	/**
	 * @since 0.11.0
	 *
	 * @param array $args Optional. Any provided args will be merged and overwrite default ones.
	 *
	 * @return array
	 */
	public function get_solution_keyword_taxonomy_args( array $args = [] ): array {
		$labels = [
			'name'                       => esc_html__( 'Keywords', 'pixelgradelt_retailer' ),
			'singular_name'              => esc_html__( 'Composition Keyword', 'pixelgradelt_retailer' ),
			'add_new'                    => esc_html_x( 'Add New', 'LT Composition Keyword', 'pixelgradelt_retailer' ),
			'add_new_item'               => esc_html__( 'Add New Composition Keyword', 'pixelgradelt_retailer' ),
			'update_item'                => esc_html__( 'Update Composition Keyword', 'pixelgradelt_retailer' ),
			'new_item_name'              => esc_html__( 'New Composition Keyword Name', 'pixelgradelt_retailer' ),
			'edit_item'                  => esc_html__( 'Edit Composition Keyword', 'pixelgradelt_retailer' ),
			'all_items'                  => esc_html__( 'All Composition Keywords', 'pixelgradelt_retailer' ),
			'search_items'               => esc_html__( 'Search Composition Keywords', 'pixelgradelt_retailer' ),
			'not_found'                  => esc_html__( 'No solution keywords found.', 'pixelgradelt_retailer' ),
			'no_terms'                   => esc_html__( 'No solution keywords.', 'pixelgradelt_retailer' ),
			'separate_items_with_commas' => esc_html__( 'Separate keywords with commas.', 'pixelgradelt_retailer' ),
			'choose_from_most_used'      => esc_html__( 'Choose from the most used keywords.', 'pixelgradelt_retailer' ),
			'most_used'                  => esc_html__( 'Most used.', 'pixelgradelt_retailer' ),
			'items_list_navigation'      => esc_html__( 'Composition Keywords list navigation', 'pixelgradelt_retailer' ),
			'items_list'                 => esc_html__( 'Composition Keywords list', 'pixelgradelt_retailer' ),
			'back_to_items'              => esc_html__( '&larr; Go to Composition Keywords', 'pixelgradelt_retailer' ),
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
	 * Identify composition post IDs based on certain details.
	 *
	 * @param array $args Array of composition details to look for.
	 *
	 * @return int[] The composition post IDs list.
	 */
	public function get_composition_ids_by( array $args = [] ): array {
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

		if ( ! empty( $args['hashid'] ) ) {
			if ( is_string( $args['hashid'] ) ) {
				$args['hashid'] = [ $args['hashid'] ];
			}

			$query_args['meta_query'][] = [
				'key'     => '_composition_hashid',
				'value'   => $args['hashid'],
				'compare' => 'IN',
			];
		}

		$query       = new \WP_Query( $query_args );
		$package_ids = $query->get_posts();

		if ( empty( $package_ids ) ) {
			return [];
		}

		return $package_ids;
	}

	/**
	 * Gather all the data about a composition ID.
	 *
	 * @param int    $post_ID The composition post ID.
	 * @param bool   $include_context
	 * @param string $pseudo_id_delimiter
	 *
	 * @return array The composition data we have available.
	 */
	public function get_composition_id_data( int $post_ID, bool $include_context = false, string $pseudo_id_delimiter = '' ): array {
		$data = [];

		if ( empty( $pseudo_id_delimiter ) ) {
			$pseudo_id_delimiter = self::PSEUDO_ID_DELIMITER;
		}

		// First, some checking.
		if ( ! $this->check_post_id( $post_ID ) ) {
			return $data;
		}

		$data['id']     = $post_ID;
		$data['hashid'] = get_post_meta( $post_ID, '_composition_hashid', true );

		$data['name']     = $this->get_post_composition_name( $post_ID );
		$data['keywords'] = $this->get_post_composition_keywords( $post_ID );

		$data['user'] = $this->get_post_composition_user_details( $post_ID );

		$data['required_solutions'] = $this->get_post_composition_required_solutions( $post_ID, $include_context, $pseudo_id_delimiter );
		$data['composer_require']   = $this->get_post_composition_composer_require( $post_ID );

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
	 * Get a composition post ID based on certain details about it.
	 *
	 * @param array $args Array of package details to look for.
	 *
	 * @return integer The found post ID.
	 */
	public function get_composition_post_id_by( array $args ): int {
		$found_package_ids = $this->get_composition_ids_by( $args );
		if ( empty( $found_package_ids ) ) {
			return 0;
		}

		// Make sure we only tackle the first package found.
		return reset( $found_package_ids );
	}

	/**
	 * Identify a composition post ID based on certain details about it and return all configured data about it.
	 *
	 * @param array $args Array of package details to look for.
	 * @param bool  $include_context
	 *
	 * @return array The found package data.
	 */
	public function get_composition_data_by( array $args, bool $include_context = false  ): array {
		$found_package_id = $this->get_composition_ids_by( $args );
		if ( empty( $found_package_id ) ) {
			return [];
		}

		// Make sure we only tackle the first package found.
		$found_package_id = reset( $found_package_id );

		return $this->get_composition_id_data( $found_package_id, $include_context );
	}

	public function get_post_composition_name( int $post_ID ): string {
		$post = get_post( $post_ID );
		if ( empty( $post ) ) {
			return '';
		}

		return $post->post_title;
	}

	public function get_post_composition_keywords( int $post_ID ): array {
		$keywords = wp_get_post_terms( $post_ID, static::KEYWORD_TAXONOMY );
		if ( is_wp_error( $keywords ) || empty( $keywords ) ) {
			return [];
		}

		// We need to return the keywords slugs, not the WP_Term list.
		return array_map( function ( $term ) {
			if ( $term instanceof \WP_Term ) {
				$term = $term->slug;
			}

			return $term;
		}, $keywords );
	}

	public function set_post_composition_keywords( int $post_ID, array $keywords ): bool {
		$result = wp_set_post_terms( $post_ID, $keywords, static::KEYWORD_TAXONOMY );
		if ( false === $result || is_wp_error( $result ) ) {
			return false;
		}

		return true;
	}

	/**
	 * @param int    $post_ID The Composition post ID.
	 * @param string $container_id
	 *
	 * @return array
	 */
	public function get_post_composition_user_details( int $post_ID, string $container_id = '' ): array {
		$user_details = [
			'id'       => get_post_meta( $post_ID, '_composition_user_id', true ),
			'email'    => get_post_meta( $post_ID, '_composition_user_email', true ),
			'username' => get_post_meta( $post_ID, '_composition_user_username', true ),
		];

		// Make sure that the user ID is an int.
		if ( empty( $user_details['id'] ) ) {
			$user_details['id'] = 0;
		} else {
			$user_details['id'] = absint( $user_details['id'] );
		}

		return $user_details;
	}

	public function set_post_composition_user_details( int $post_ID, array $user_details, string $container_id = '' ) {
		if ( isset( $user_details['id'] ) ) {
			carbon_set_post_meta( $post_ID, 'composition_user_id', $user_details['id'], $container_id );
		}

		if ( isset( $user_details['email'] ) ) {
			carbon_set_post_meta( $post_ID, 'composition_user_email', $user_details['email'], $container_id );
		}

		if ( isset( $user_details['username'] ) ) {
			carbon_set_post_meta( $post_ID, 'composition_user_username', $user_details['username'], $container_id );
		}
	}

	/**
	 * @param array $composition_data The Composition data as returned by @see self::get_composition_id_data().
	 *
	 * @return string
	 */
	public function get_post_composition_encrypted_user_details( array $composition_data ): string {
		$encrypted = local_rest_call( '/pixelgradelt_retailer/v1/compositions/encrypt_user_details', 'POST', [], [
			'userid'        => $composition_data['user']['id'],
			'compositionid' => $composition_data['hashid'],
			'extra'         => [
				'email'    => $composition_data['user']['email'],
				'username' => $composition_data['user']['username'],
			],
		] );
		if ( ! is_string( $encrypted ) ) {
			// This means there was an error. Maybe the user details failed validation, etc.
			$encrypted = '';
		}

		return $encrypted;
	}

	/**
	 * @param int    $post_ID             The Composition post ID.
	 * @param bool   $include_context     Whether to include context data about each required solution (things like orders, timestamps, etc).
	 * @param string $pseudo_id_delimiter The delimiter used to construct each required solution's value.
	 *
	 * @return array
	 */
	public function get_post_composition_required_solutions( int $post_ID, bool $include_context = false, string $pseudo_id_delimiter = '' ): array {
		$required_solutions = carbon_get_post_meta( $post_ID, 'composition_required_solutions' );
		if ( empty( $required_solutions ) || ! is_array( $required_solutions ) ) {
			return [];
		}

		if ( empty( $pseudo_id_delimiter ) ) {
			$pseudo_id_delimiter = self::PSEUDO_ID_DELIMITER;
		}

		// Make sure only the fields we are interested in are left.
		$accepted_keys = array_fill_keys( [ 'pseudo_id', ], '' );
		$context_keys  = array_fill_keys( [ 'order_id', 'order_item_id', 'timestamp', ], '' );
		foreach ( $required_solutions as $key => $required_solution ) {
			$required_solutions[ $key ] = array_replace( $accepted_keys, array_intersect_key( $required_solution, $accepted_keys ) );

			if ( empty( $required_solution['pseudo_id'] ) || false === strpos( $required_solution['pseudo_id'], $pseudo_id_delimiter ) ) {
				unset( $required_solutions[ $key ] );
				continue;
			}

			// We will now split the pseudo_id in its components (slug and post_id with the delimiter in between).
			[ $slug, $post_id ] = explode( $pseudo_id_delimiter, $required_solution['pseudo_id'] );
			if ( empty( $post_id ) ) {
				unset( $required_solutions[ $key ] );
				continue;
			}

			$required_solutions[ $key ]['slug']            = $slug;
			$required_solutions[ $key ]['managed_post_id'] = intval( $post_id );

			if ( $include_context ) {
				$required_solutions[ $key ]['context'] = array_replace( $context_keys, array_intersect_key( $required_solution, $context_keys ) );
			}
		}

		return $required_solutions;
	}

	public function set_post_composition_required_solutions( int $post_ID, array $required_solutions, string $container_id = '' ) {
		carbon_set_post_meta( $post_ID, 'solution_required_solutions', $required_solutions, $container_id );
	}

	/**
	 * @param array $required_solutions The Composition's required solutions data.
	 *
	 * @return Package[]
	 */
	public function get_post_composition_required_solutions_packages( array $required_solutions ): array {
		$solutions = [];
		foreach ( $required_solutions as $required_solution ) {
			$package = $this->solutions->first_where( [
				'managed_post_id' => $required_solution['managed_post_id'],
			] );
			if ( empty( $package ) ) {
				continue;
			}

			$solutions[] = $package;
		}

		return $solutions;
	}

	/**
	 * @param array $required_solutions The Composition's required solutions data.
	 *
	 * @return integer[]
	 */
	public function get_post_composition_required_solutions_ids( array $required_solutions ): array {
		$solutions = $this->get_post_composition_required_solutions_packages( $required_solutions );

		return array_map( function ( $solution ) {
			return $solution->get_managed_post_id();
		}, $solutions );
	}

	/**
	 * @param array $required_solutions The Composition's required solutions data.
	 *
	 * @return Package[]
	 */
	public function get_post_composition_required_solutions_context( array $required_solutions ): array {
		$solutionsContext = [];
		foreach ( $required_solutions as $required_solution ) {
			$package = $this->solutions->first_where( [
				'managed_post_id' => $required_solution['managed_post_id'],
			] );
			if ( empty( $package ) ) {
				continue;
			}

			$solutionsContext[ $package->get_composer_package_name() ] = $required_solution['context'];
		}

		return $solutionsContext;
	}

	public function get_post_composition_composer_require( int $post_ID, string $pseudo_id_delimiter = ' #', string $container_id = '' ): array {
		// We don't currently allow defining a per-composition Composer require list.
		return [];
	}

	public function set_post_composition_composer_require( int $post_ID, array $composer_require, string $container_id = '' ) {
		// Nothing right now.
		//		carbon_set_post_meta( $post_ID, 'package_composer_require', $composer_require, $container_id );
	}

	/**
	 * Given a composition's contained/required solutions, dry-run a composer require of them (including their required packages - recursively) and see if all goes well.
	 *
	 * @param int       $composition_id
	 * @param Package[] $solutions
	 *
	 * @return \Exception|bool
	 */
	public function dry_run_composition_require( int $composition_id, array $solutions ) {
		$client = $this->get_composer_client();

		$composition = get_post( $composition_id );
		if ( empty( $composition ) || empty( $solutions ) ) {
			return false;
		}

		if ( empty( $ltrecords_repo_url = ensure_packages_json_url( get_setting( 'ltrecords-packages-repo-endpoint' ) ) )
		     || empty( $ltrecords_api_key = get_setting( 'ltrecords-api-key' ) ) ) {

			$this->logger->error(
				'Error during Composer require dry-run for composition "{name}" #{post_id}.' . PHP_EOL
				. esc_html__( 'Missing LT Records Repo URL and/or LT Records API key in Settings > LT Retailer.', 'pixelgradelt_retailer' ),
				[
					'name'    => $composition->post_title,
					'post_id' => $composition_id,
				]
			);

			return false;
		}

		try {
			$packages = $client->getPackages( [
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
								'header' => ! empty( Env::get( 'LTRETAILER_PHP_AUTH_USER' ) ) ? [
									'Authorization: Basic ' . base64_encode( Env::get( 'LTRETAILER_PHP_AUTH_USER' ) . ':' . Server::AUTH_PWD ),
								] : [],
							],
						],
					],
					[
						// The LT Records Repo (includes all LT Records packages and parts).
						'type'    => 'composer',
						'url'     => esc_url( $ltrecords_repo_url ),
						'options' => [
							'ssl'  => [
								'verify_peer' => ! is_debug_mode(),
							],
							'http' => [
								'header' => [
									'Authorization: Basic ' . base64_encode( $ltrecords_api_key . ':' . self::LTRECORDS_API_PWD ),
								],
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
				'require'                       => ArrayHelpers::array_map_assoc( function ( $key, $solution ) {
					// Require any solution version since we don't version solutions right now.
					return [ $solution->get_name() => '*' ];
				}, $solutions ),
				'minimum-stability-per-package' => ArrayHelpers::array_map_assoc( function ( $key, $solution ) {
					// The loosest stability since we want to be all encompassing.
					return [ $solution->get_name() => 'dev' ];
				}, $solutions ),
				// Since we are just simulating, it doesn't make sense to check the platform requirements (like PHP version, PHP extensions, etc).
				'ignore-platform-reqs'          => true,
			] );
		} catch ( \Exception $e ) {
			$this->logger->error(
				'Error during Composer require dry-run for composition "{name}" #{post_id}.' . PHP_EOL . $e->getMessage(),
				[
					'exception' => $e,
					'name'      => $composition->post_title,
					'post_id'   => $composition_id,
				]
			);

			return $e;
		}

		return true;
	}

	public function hash_encode_id( int $id ): string {
		return $this->hasher->encode( $id );
	}

	public function hash_decode_id( string $hash ): int {
		return $this->hasher->decode( $hash );
	}

	/**
	 * Normalize a version string according to Composer logic.
	 *
	 * @since 0.11.0
	 *
	 * @param string $version
	 *
	 * @throws \UnexpectedValueException
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
