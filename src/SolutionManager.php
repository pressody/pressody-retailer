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

use Env\Env;
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

	const LTRECORDS_API_PWD = 'pixelgradelt_records';

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
	public function get_solution_ids_by( array $args = [] ): array {
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

		$data['name']        = $this->get_post_solution_name( $post_ID );
		$data['type']        = $this->get_post_solution_type( $post_ID );
		$data['slug']        = $this->get_post_solution_slug( $post_ID );
		$data['categories']  = $this->get_post_solution_categories( $post_ID );
		$data['keywords']    = $this->get_post_solution_keywords( $post_ID );
		$data['description'] = get_post_meta( $post_ID, '_solution_details_description', true );
		$data['homepage']    = get_post_meta( $post_ID, '_solution_details_homepage', true );

		$data['required_ltrecords_parts'] = $this->get_post_solution_required_parts( $post_ID );
		$data['required_solutions']       = $this->get_post_solution_required_solutions( $post_ID );
		$data['excluded_solutions']       = $this->get_post_solution_excluded_solutions( $post_ID );
		$data['composer_require']         = $this->get_post_solution_composer_require( $post_ID );

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
	 * Identify a solution post ID based on certain details about it and return all configured data about it.
	 *
	 * @param array $args Array of solution details to look for.
	 *
	 * @return array The found solution data.
	 */
	public function get_solution_data_by( array $args ): array {
		$found_solution_id = $this->get_solution_ids_by( $args );
		if ( empty( $found_solution_id ) ) {
			return [];
		}

		// Make sure we only tackle the first solution found.
		$found_solution_id = reset( $found_solution_id );

		return $this->get_solution_id_data( $found_solution_id );
	}

	public function get_post_solution_name( int $post_ID ): string {
		$post = get_post( $post_ID );
		if ( empty( $post ) ) {
			return '';
		}

		return $post->post_title;
	}

	public function get_post_solution_type( int $post_ID ): string {
		/** @var \WP_Error|\WP_Term[] $package_type */
		$package_type = wp_get_post_terms( $post_ID, static::TYPE_TAXONOMY );
		if ( is_wp_error( $package_type ) || empty( $package_type ) ) {
			return '';
		}
		$package_type = reset( $package_type );

		return $package_type->slug;
	}

	public function get_post_solution_slug( int $post_ID ): string {
		$post = get_post( $post_ID );
		if ( empty( $post ) ) {
			return '';
		}

		return $post->post_name;
	}

	public function get_post_solution_categories( int $post_ID ): array {
		$categories = wp_get_post_terms( $post_ID, static::CATEGORY_TAXONOMY );
		if ( is_wp_error( $categories ) || empty( $categories ) ) {
			return [];
		}

		// We need to return the categories slugs, not the WP_Term list.
		return array_map( function ( $term ) {
			if ( $term instanceof \WP_Term ) {
				$term = $term->slug;
			}

			return $term;
		}, $categories );
	}

	public function set_post_solution_categories( int $post_ID, array $categories ): bool {
		$result = wp_set_post_terms( $post_ID, $categories, static::CATEGORY_TAXONOMY );
		if ( false === $result || is_wp_error( $result ) ) {
			return false;
		}

		return true;
	}

	public function get_post_solution_keywords( int $post_ID ): array {
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

	public function set_post_solution_keywords( int $post_ID, array $keywords ): bool {
		$result = wp_set_post_terms( $post_ID, $keywords, static::KEYWORD_TAXONOMY );
		if ( false === $result || is_wp_error( $result ) ) {
			return false;
		}

		return true;
	}

	public function get_post_solution_required_solutions( int $post_ID, string $pseudo_id_delimiter = ' #', string $container_id = '' ): array {
		$required_solutions = carbon_get_post_meta( $post_ID, 'solution_required_solutions', $container_id );
		if ( empty( $required_solutions ) || ! is_array( $required_solutions ) ) {
			return [];
		}

		// Make sure only the fields we are interested in are left.
		$accepted_keys = array_fill_keys( [ 'pseudo_id', ], '' );
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
		}

		return $required_solutions;
	}

	public function set_post_solution_required_solutions( int $post_ID, array $required_solutions, string $container_id = '' ) {
		carbon_set_post_meta( $post_ID, 'solution_required_solutions', $required_solutions, $container_id );
	}

	public function get_post_solution_excluded_solutions( int $post_ID, string $pseudo_id_delimiter = ' #', string $container_id = '' ): array {
		$excluded_solutions = carbon_get_post_meta( $post_ID, 'solution_excluded_solutions', $container_id );
		if ( empty( $excluded_solutions ) || ! is_array( $excluded_solutions ) ) {
			return [];
		}

		// Make sure only the fields we are interested in are left.
		$accepted_keys = array_fill_keys( [ 'pseudo_id', ], '' );
		foreach ( $excluded_solutions as $key => $replaced_solution ) {
			$excluded_solutions[ $key ] = array_replace( $accepted_keys, array_intersect_key( $replaced_solution, $accepted_keys ) );

			if ( empty( $replaced_solution['pseudo_id'] ) || false === strpos( $replaced_solution['pseudo_id'], $pseudo_id_delimiter ) ) {
				unset( $excluded_solutions[ $key ] );
				continue;
			}

			// We will now split the pseudo_id in its components (slug and post_id with the delimiter in between).
			[ $slug, $post_id ] = explode( $pseudo_id_delimiter, $replaced_solution['pseudo_id'] );
			if ( empty( $post_id ) ) {
				unset( $excluded_solutions[ $key ] );
				continue;
			}

			$excluded_solutions[ $key ]['slug']            = $slug;
			$excluded_solutions[ $key ]['managed_post_id'] = intval( $post_id );
		}

		return $excluded_solutions;
	}

	public function set_post_solution_excluded_solutions( int $post_ID, array $excluded_solutions, string $container_id = '' ) {
		carbon_set_post_meta( $post_ID, 'solution_excluded_solutions', $excluded_solutions, $container_id );
	}


	public function get_post_solution_required_parts( int $post_ID, string $container_id = '' ): array {
		$required_parts = carbon_get_post_meta( $post_ID, 'solution_required_parts', $container_id );

		if ( empty( $required_parts ) || ! is_array( $required_parts ) ) {
			return [];
		}

		// Make sure only the fields we are interested in are left.
		$accepted_keys = array_fill_keys( [ 'package_name', 'version_range', ], '' );
		foreach ( $required_parts as $key => $required_part ) {
			$required_parts[ $key ] = array_replace( $accepted_keys, array_intersect_key( $required_part, $accepted_keys ) );

			if ( empty( $required_part['package_name'] ) ) {
				unset( $required_parts[ $key ] );
			}

			// Since we don't manage the part stability, we will fill in the default one.
			// Parts don't have a stability options because we want to manage this at a global level (at a composition level).
			$required_parts[ $key ]['stability'] = 'stable';
		}

		return $required_parts;
	}

	public function set_post_solution_required_parts( int $post_ID, array $required_parts, string $container_id = '' ) {
		carbon_set_post_meta( $post_ID, 'solution_required_parts', $required_parts, $container_id );
	}

	public function get_post_solution_composer_require( int $post_ID, string $pseudo_id_delimiter = ' #', string $container_id = '' ): array {
		// We don't currently allow defining a per-solution Composer require list.
		return [];
	}

	public function set_post_solution_composer_require( int $post_ID, array $composer_require, string $container_id = '' ) {
		// Nothing right now.
		//		carbon_set_post_meta( $post_ID, 'package_composer_require', $composer_require, $container_id );
	}

	/**
	 * Given a solution, dry-run a composer require of it (including its required packages) and see if all goes well.
	 *
	 * @param Package $solution
	 *
	 * @return \Exception|bool
	 */
	public function dry_run_solution_require( Package $solution ) {
		$client = $this->get_composer_client();

		if ( empty( $ltrecords_repo_url = ensure_packages_json_url( get_setting( 'ltrecords-packages-repo-endpoint' ) ) )
		     || empty( $ltrecords_api_key = get_setting( 'ltrecords-api-key' ) ) ) {

			$this->logger->error(
				'Error during Composer require dry-run for solution "{package}" #{post_id}.' . PHP_EOL
				. esc_html__( 'Missing LT Records Repo URL and/or LT Records API key in Settings > LT Retailer.', 'pixelgradelt_retailer' ),
				[
					'package' => $solution->get_name(),
					'post_id' => $solution->get_managed_post_id(),
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
								'header' => ! empty( Env::get('LTRETAILER_PHP_AUTH_USER') ) ? [
									'Authorization: Basic ' . base64_encode( Env::get('LTRETAILER_PHP_AUTH_USER') . ':' . Server::AUTH_PWD ),
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
				'require'                       => [
					// Any solution version.
					$solution->get_name() => '*',
				],
				'minimum-stability-per-package' => [
					// The loosest stability.
					$solution->get_name() => 'dev',
				],
				// Since we are just simulating, it doesn't make sense to check the platform requirements (like PHP version, PHP extensions, etc).
				'ignore-platform-reqs'          => true,
			] );
		} catch ( \Exception $e ) {
			$this->logger->error(
				'Error during Composer require dry-run for solution "{package}" #{post_id}.' . PHP_EOL . $e->getMessage(),
				[
					'exception' => $e,
					'package'   => $solution->get_name(),
					'post_id'   => $solution->get_managed_post_id(),
				]
			);

			return $e;
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

	public function solution_name_to_composer_package_name( string $name ): ?string {
		/**
		 * Construct the Composer-like package name (the same way @see ComposerSolutionTransformer::transform() does it).
		 */
		$vendor = get_composer_vendor();

		if ( empty( $vendor ) || empty( $name ) ) {
			// Something is wrong. We will not include this required package.
			$this->logger->error(
				'Error generating the solution Composer package name for solution with name "{name}".',
				[
					'name' => $name,
				]
			);

			return null;
		}

		return $vendor . '/' . $name;
	}

	/**
	 * Normalize a version string according to Composer logic.
	 *
	 * @since 0.1.0
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
