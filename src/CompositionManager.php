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

use Carbon_Fields\Value_Set\Value_Set;
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
	 * Used to create the pseudo IDs saved as values for a composition's contained solutions.
	 * Don't change this without upgrading the data in the DB!
	 */
	const PSEUDO_ID_DELIMITER = ' #';

	/**
	 * The possible composition statuses with details.
	 *
	 * The array keys are the status IDs.
	 *
	 * @since 0.12.0
	 *
	 * @var array
	 */
	public static array $STATUSES;

	/**
	 * Solutions repository.
	 *
	 * @var PackageRepository
	 */
	protected PackageRepository $solutions;

	/**
	 * The Purchased Solutions Manager.
	 *
	 * @since 0.14.0
	 *
	 * @var PurchasedSolutionManager
	 */
	protected PurchasedSolutionManager $ps_manager;

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
	 * @param PackageRepository        $solutions                  Solutions repository.
	 * @param PurchasedSolutionManager $purchased_solution_manager Purchased Solutions Manager.
	 * @param ComposerClient           $composer_client
	 * @param ComposerVersionParser    $composer_version_parser
	 * @param LoggerInterface          $logger                     Logger.
	 * @param HasherInterface          $hasher
	 */
	public function __construct(
		PackageRepository $solutions,
		PurchasedSolutionManager $purchased_solution_manager,
		ComposerClient $composer_client,
		ComposerVersionParser $composer_version_parser,
		LoggerInterface $logger,
		HasherInterface $hasher
	) {

		$this->solutions               = $solutions;
		$this->ps_manager              = $purchased_solution_manager;
		$this->composer_client         = $composer_client;
		$this->composer_version_parser = $composer_version_parser;
		$this->logger                  = $logger;
		$this->hasher                  = $hasher;

		self::$STATUSES = apply_filters( 'pixelgradelt_retailer/composition_statuses', [
			'not_ready' => [
				'id'       => 'not_ready',
				'label'    => esc_html__( 'Not Ready', 'pixelgradelt_retailer' ),
				'desc'     => esc_html__( 'The composition is not ready for use on a site. It needs more work to be ready.', 'pixelgradelt_retailer' ),
				'internal' => false,
			],
			'ready'     => [
				'id'       => 'ready',
				'label'    => esc_html__( 'Ready', 'pixelgradelt_retailer' ),
				'desc'     => esc_html__( 'The composition is ready for use on a site.', 'pixelgradelt_retailer' ),
				'internal' => false,
			],
			'active'    => [
				'id'       => 'active',
				'label'    => esc_html__( 'Active', 'pixelgradelt_retailer' ),
				'desc'     => esc_html__( 'The composition is being used on a site.', 'pixelgradelt_retailer' ),
				'internal' => false,
			],
			'retired'   => [
				'id'       => 'retired',
				'label'    => esc_html__( 'Retired', 'pixelgradelt_retailer' ),
				'desc'     => esc_html__( 'The composition has been retired and is no longer available for use.', 'pixelgradelt_retailer' ),
				'internal' => false,
			],
		] );
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
			'menu_icon'          => 'dashicons-playlist-audio',
			'show_in_nav_menus'  => false,
			'supports'           => [
				'title',
				'custom-fields',
			],
			'capabilities' => array(
				'edit_post'              => Capabilities::EDIT_COMPOSITION,
				'read_post'              => Capabilities::VIEW_COMPOSITION,
				'delete_post'            => Capabilities::EDIT_COMPOSITION,
				'edit_posts'             => Capabilities::EDIT_COMPOSITIONS,
				'edit_private_posts'     => Capabilities::EDIT_COMPOSITIONS,
				'edit_published_posts'   => Capabilities::EDIT_COMPOSITIONS,
				'edit_others_posts'      => Capabilities::EDIT_COMPOSITIONS, // Since all composition owners should be able to do this, we need to resolve it at mapping.
				'publish_posts'          => Capabilities::EDIT_COMPOSITIONS,
				'read_private_posts'     => Capabilities::VIEW_COMPOSITIONS,
				'delete_posts'           => Capabilities::EDIT_COMPOSITIONS,
				'delete_private_posts'   => Capabilities::EDIT_COMPOSITIONS,
				'delete_published_posts' => Capabilities::EDIT_COMPOSITIONS,
				'delete_others_posts'    => Capabilities::EDIT_COMPOSITIONS, // Since all composition owners should be able to do this, we need to resolve it at mapping.
				'create_posts'           => Capabilities::CREATE_COMPOSITIONS,
			),
			'map_meta_cap'       => false,
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

		if ( ! empty( $args['status'] ) ) {
			if ( is_string( $args['status'] ) ) {
				$args['status'] = [ $args['status'] ];
			}

			$query_args['meta_query'][] = [
				'key'     => '_composition_status',
				'value'   => $args['status'],
				'compare' => 'IN',
			];
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

		if ( ! empty( $args['userid'] ) ) {
			$args['userid'] = intval( $args['userid'] );

			// We rely on CarbonFields' integration with the WP_Query.
			$query_args['meta_query'][] = [
				'key'   => 'composition_user_ids',
				'value' => $args['userid'],
			];
		}

		$query    = new \WP_Query( $query_args );
		$post_ids = $query->get_posts();

		if ( empty( $post_ids ) ) {
			return [];
		}

		return $post_ids;
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

		$data['status'] = \get_post_meta( $post_ID, '_composition_status', true );
		$data['hashid'] = \get_post_meta( $post_ID, '_composition_hashid', true );

		$data['name']     = $this->get_post_composition_name( $post_ID );
		$data['keywords'] = $this->get_post_composition_keywords( $post_ID );

		$data['users'] = $this->get_post_composition_users_details( $post_ID );

		$data['required_solutions']           = $this->get_post_composition_required_solutions( $post_ID, $include_context, $pseudo_id_delimiter );
		$data['required_purchased_solutions'] = $this->get_post_composition_required_purchased_solutions( $post_ID, $include_context, $pseudo_id_delimiter );
		$data['required_manual_solutions']    = $this->get_post_composition_required_manual_solutions( $post_ID, $include_context, $pseudo_id_delimiter );
		$data['composer_require']             = $this->get_post_composition_composer_require( $post_ID );

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
	 * Insert a new composition in the DB or update an existing one.
	 *
	 * @since 0.14.0
	 *
	 * @param array $args                            {
	 *                                               List of composition details.
	 *
	 * @type int    $post_id                         The composition post ID to search for and update. Only used if $update is true.
	 * @type string $post_title                      The composition post title to set.
	 * @type string $post_status                     The composition post status to set. Defaults to 'private'.
	 * @type string $status                          The composition status. Should be a valid value from CompositionManager::STATUSES. Defaults to 'not_ready'.
	 * @type string $hashid                          The hashid to assign to the composition. Will be used to search for existing compositions if $update is true. Defaults to a generated hashid from the new post ID.
	 * @type int[]  $user_ids                        List of user IDs to assign the composition to. If a user with a provided user ID doesn't exist, the user ID will be ignored.
	 * @type int[]  $required_purchased_solution_ids List of required purchased-solution ids.
	 * @type array  $required_manual_solutions       List of required solution details: `post_id` or `pseudo_id`, `reason`.
	 * @type array  $keywords                        List of keywords to add to the composition.
	 * }
	 *
	 * @param bool  $update                          Whether to update if the composition already exists.
	 *                                               We will identify an existing composition by details such as post_id or hashid.
	 *
	 * @return int The newly created or updated composition post ID. 0 on failure.
	 */
	public function save_composition( array $args, bool $update = false ): int {
		// Let's see if we should update an existing composition.
		$post_to_update = false;
		if ( $update && ( ! empty( $args['post_id'] ) || ! empty( $args['hashid'] ) ) ) {
			// Try to get a post by the provided post ID.
			if ( ! empty( $args['post_id'] ) ) {
				$post_to_update = get_post( $args['post_id'] );
			}

			if ( empty( $post_to_update ) && ! empty( $args['hashid'] ) ) {
				$post_to_update = $this->get_composition_post_id_by( [ 'hashid' => $args['hashid'] ] );
			}
		}

		// Make sure we are dealing with a list of user IDs.
		if ( ! empty( $args['user_ids'] ) && ! is_array( $args['user_ids'] ) ) {
			$args['user_ids'] = [ $args['user_ids'] ];
		}

		$composition_author = false;
		// The first user in the owners list is the author.
		if ( ! empty( $args['user_ids'] ) ) {
			$args['user_ids']   = array_map( 'intval', $args['user_ids'] );
			$composition_author = get_user_by( 'id', reset( $args['user_ids'] ) );
		}

		// We need to create a new post.
		$created_new_post = false;
		if ( empty( $post_to_update ) ) {
			$post_title = $args['post_title'] ?? '';
			// Generate a title if not provided.
			if ( empty( $post_title ) ) {
				$post_title = 'Composition ' . \wp_generate_password( 6, false );
				if ( $composition_author instanceof \WP_User ) {
					$post_title .= ' of ' . $composition_author->display_name;
				}
			}
			$new_post_args = [
				'post_type'   => self::POST_TYPE,
				'post_title'  => $post_title,
				'post_status' => $args['post_status'] ?? 'private',
			];
			if ( $composition_author instanceof \WP_User ) {
				$new_post_args['post_author'] = $composition_author->ID;
			}


			$post_to_update = wp_insert_post( $new_post_args, false, false );
			if ( is_wp_error( $post_to_update ) ) {
				// We have failed to create a new composition post.
				$this->logger->error(
					'Error inserting a new composition post: {message}',
					[
						'message'     => $post_to_update->get_error_message(),
						'post_args'   => $new_post_args,
						'logCategory' => 'composition_manager',
					]
				);
			} else {
				$created_new_post = true;
			}
		}

		$post_to_update = get_post( $post_to_update );

		// Bail if we don't have a post.
		if ( empty( $post_to_update ) ) {
			return 0;
		}

		/*
		 * We need to update an existing composition post.
		 */

		/**
		 * Fires before LT composition update.
		 *
		 * @since 0.14.0
		 *
		 * @param int $post_id The composition post ID.
		 */
		do_action( 'pixelgradelt_retailer/ltcomposition/before_update', $post_to_update->ID );

		if ( ! empty( $args['status'] ) && in_array( $args['status'], array_keys( CompositionManager::$STATUSES ) ) ) {
			$this->set_post_composition_status( $post_to_update->ID, $args['status'], true );
		} else if ( $created_new_post ) {
			// Set the default status for newly created compositions.
			$this->set_post_composition_status( $post_to_update->ID, 'not_ready', true );
		}

		if ( ! empty( $args['hashid'] ) ) {
			$this->set_post_composition_hashid( $post_to_update->ID, $args['hashid'] );
		} else if ( $created_new_post ) {
			// Add a default hashid (generate from the post ID) for newly create compositions.
			$this->set_post_composition_hashid( $post_to_update->ID );
		}

		if ( isset( $args['user_ids'] ) && is_array( $args['user_ids'] ) ) {
			$this->set_post_composition_user_ids( $post_to_update->ID, $args['user_ids'], true );
		}

		if ( isset( $args['required_purchased_solution_ids'] ) && is_array( $args['required_purchased_solution_ids'] ) ) {
			$this->set_post_composition_required_purchased_solutions( $post_to_update->ID, $args['required_purchased_solution_ids'], true );
		}

		if ( isset( $args['required_manual_solutions'] ) && is_array( $args['required_manual_solutions'] ) ) {
			$this->set_post_composition_required_manual_solutions( $post_to_update->ID, $args['required_manual_solutions'], true );
		}

		if ( isset( $args['keywords'] ) && is_array( $args['keywords'] ) ) {
			$this->set_post_composition_keywords( $post_to_update->ID, $args['keywords'] );
		}

		/**
		 * Fires after update LT composition.
		 *
		 * @since 0.14.0
		 *
		 * @param int      $post_id The newly created or updated composition post ID
		 * @param \WP_Post $post    The composition post object.
		 * @param bool     $update  If this is an update.
		 */
		do_action( 'pixelgradelt_retailer/ltcomposition/update',
			$post_to_update->ID,
			$post_to_update,
			true
		);

		if ( $created_new_post ) {
			/**
			 * Fires after a new LT composition is created.
			 *
			 * @since 0.14.0
			 *
			 * @param int      $post_id The newly created composition post ID
			 * @param \WP_Post $post    The new composition post object.
			 */
			do_action( 'pixelgradelt_retailer/ltcomposition/new',
				$post_to_update->ID,
				$post_to_update
			);
		}

		return $post_to_update->ID;
	}

	/**
	 * Get a composition post ID based on certain details about it.
	 *
	 * @see CompositionManager::get_composition_ids_by()
	 *
	 * @param array $args Query args.
	 *
	 * @return int The found post ID.
	 */
	public function get_composition_post_id_by( array $args ): int {
		$found_composition_ids = $this->get_composition_ids_by( $args );
		if ( empty( $found_composition_ids ) ) {
			return 0;
		}

		// Make sure we only tackle the first package found.
		return reset( $found_composition_ids );
	}

	/**
	 * Identify a composition post ID based on certain details about it and return all configured data about it.
	 *
	 * @param array $args Array of package details to look for.
	 * @param bool  $include_context
	 *
	 * @return array The found package data.
	 */
	public function get_composition_data_by( array $args, bool $include_context = false ): array {
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

	/**
	 *
	 * @since 0.14.0
	 *
	 * @see   CompositionManager::$STATUSES
	 *
	 * @param int    $post_id The composition post ID.
	 * @param string $status  The status to set for the composition. Must be a valid value.
	 * @param bool   $silent  Optional. Whether to trigger action hooks. Default is to trigger the action hooks.
	 *
	 * @return bool
	 */
	public function set_post_composition_status( int $post_id, string $status, bool $silent = false ): bool {
		if ( ! in_array( $status, array_keys( CompositionManager::$STATUSES ) ) ) {
			return false;
		}

		if ( ! $silent ) {
			/**
			 * Fires before LT composition update.
			 *
			 * @since 0.14.0
			 *
			 * @param int $post_id The composition post ID.
			 */
			do_action( 'pixelgradelt_retailer/ltcomposition/before_update', $post_id );
		}

		$result = ! ! \update_post_meta( $post_id, '_composition_status', $status );

		if ( ! $silent ) {
			/**
			 * Fires after LT composition update.
			 *
			 * The provided parameters are compatible with the 'wp_after_insert_post' core action, so we can use the same handlers.
			 *
			 * @since 0.14.0
			 *
			 * @param int      $post_id The composition post ID.
			 * @param \WP_Post $post    The composition post object.
			 * @param bool     $update  If this is an update.
			 */
			do_action( 'pixelgradelt_retailer/ltcomposition/update',
				$post_id,
				get_post( $post_id ),
				true
			);
		}

		return $result;
	}

	/**
	 * @since 0.14.0
	 *
	 * @param int    $post_ID
	 * @param string $hashid Optional. Leave empty to generate a hashid from the post ID.
	 *
	 * @return bool
	 */
	public function set_post_composition_hashid( int $post_ID, string $hashid = '' ): bool {
		if ( empty( $hashid ) ) {
			$hashid = $this->hash_encode_id( $post_ID );
		}

		return ! ! \update_post_meta( $post_ID, '_composition_hashid', $hashid );
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
	 * Get the user IDs of all the composition owners (users).
	 *
	 * @param int $post_id The Composition post ID.
	 *
	 * @return int[] List of user IDs.
	 */
	public function get_post_composition_user_ids( int $post_id ): array {
		$composition_user_ids = carbon_get_post_meta( $post_id, 'composition_user_ids' );
		if ( empty( $composition_user_ids ) || ! is_array( $composition_user_ids ) ) {
			return [];
		}

		// Extract only the IDs from the value set CarbonFields returns.
		return array_map( 'intval', \wp_list_pluck( $composition_user_ids, 'id' ) );
	}

	/**
	 * Update a composition's owners/users.
	 *
	 * No validation is applied to the user IDs list.
	 *
	 * @param int   $post_id  The composition post ID.
	 * @param int[] $user_ids The users IDs list.
	 * @param bool  $silent   Optional. Whether to trigger action hooks. Default is to trigger the action hooks.
	 *
	 * @return bool True on success. False on failure.
	 */
	public function set_post_composition_user_ids( int $post_id, array $user_ids, bool $silent = false ): bool {
		if ( ! $silent ) {
			/**
			 * Fires before LT composition update.
			 *
			 * @since 0.14.0
			 *
			 * @param int $post_id The composition post ID.
			 */
			do_action( 'pixelgradelt_retailer/ltcomposition/before_update', $post_id );
		}

		// Decorate the user list according to the needs of the CarbonFields association field.
		$values = array_map( function ( $user_id ) {
			return [
				Value_Set::VALUE_PROPERTY => 'user:user:' . $user_id,
				'type'                    => 'user',
				'subtype'                 => 'user',
				'id'                      => intval( $user_id ),
			];
		}, $user_ids );

		// Update the DB value.
		carbon_set_post_meta( $post_id, 'composition_user_ids', $values );

		if ( ! $silent ) {
			/**
			 * Fires after LT composition update.
			 *
			 * The provided parameters are compatible with the 'wp_after_insert_post' core action, so we can use the same handlers.
			 *
			 * @since 0.14.0
			 *
			 * @param int      $post_id The composition post ID.
			 * @param \WP_Post $post    The composition post object.
			 * @param bool     $update  If this is an update.
			 */
			do_action( 'pixelgradelt_retailer/ltcomposition/update',
				$post_id,
				get_post( $post_id ),
				true
			);
		}

		return true;
	}

	/**
	 * Get the details of all the composition owners (users).
	 *
	 * @param int $post_id The Composition post ID.
	 *
	 * @return array List of user details keyed by their user ID.
	 */
	public function get_post_composition_users_details( int $post_id ): array {
		$list = [];

		$composition_user_ids = $this->get_post_composition_user_ids( $post_id );
		foreach ( $composition_user_ids as $composition_user_id ) {
			$user_details = [
				// `invalid` for non-existing users, `valid` for existing users.
				'status'   => 'invalid',
				'id'       => $composition_user_id,
				'email'    => '',
				'username' => '',
			];
			$user         = \get_userdata( $composition_user_id );
			if ( ! empty( $user ) ) {
				$user_details['status']   = 'valid';
				$user_details['email']    = $user->user_email;
				$user_details['username'] = $user->user_login;
			}

			// Make sure that the user ID is an int.
			if ( empty( $user_details['id'] ) ) {
				$user_details['id'] = 0;
			} else {
				$user_details['id'] = absint( $user_details['id'] );
			}

			$list[ $user_details['id'] ] = $user_details;
		}

		return $list;
	}

	/**
	 * @param array $composition_data The Composition data as returned by @see self::get_composition_id_data().
	 *
	 * @return string
	 */
	public function get_post_composition_encrypted_ltdetails( array $composition_data ): string {
		$encrypted = local_rest_call( '/pixelgradelt_retailer/v1/compositions/encrypt_ltdetails', 'POST', [], [
			'userids'       => array_keys( $composition_data['users'] ),
			'compositionid' => $composition_data['hashid'],
			'extra'         => [
				'users' => $composition_data['users'],
			],
		] );
		if ( ! is_string( $encrypted ) ) {
			// This means there was an error. Maybe the composition LT details failed validation, etc.
			$encrypted = '';
		}

		return $encrypted;
	}

	/**
	 * Get the final list of required solutions.
	 *
	 * The various types of required solutions will be merged.
	 * We will also ensure uniqueness by LT Solution.
	 *
	 * @param int    $post_ID             The Composition post ID.
	 * @param bool   $include_context     Whether to include context data about each required solution
	 *                                    (things like orders, timestamps, etc).
	 * @param string $pseudo_id_delimiter The delimiter used to construct each required solution's value.
	 *
	 * @return array List of composition required solutions.
	 *               Each solution has AT LEAST its type, slug, managed post ID, and maybe context information, if $include_context is true.
	 */
	public function get_post_composition_required_solutions( int $post_ID, bool $include_context = false, string $pseudo_id_delimiter = '' ): array {
		$purchased_solutions = $this->get_post_composition_required_purchased_solutions( $post_ID, $include_context, $pseudo_id_delimiter );
		$manual_solutions    = $this->get_post_composition_required_manual_solutions( $post_ID, $include_context, $pseudo_id_delimiter );

		// Merge the two lists by giving precedence to manual solutions:
		// - we want unique LT solutions (by their managed_post_id);
		// - if both a purchased and manual LT solution exist, we leave in only the manual one.

		// First, ensure that each holds unique LT solutions.
		$purchased_solutions = $this->unique_required_solutions_list( $purchased_solutions );
		$manual_solutions    = $this->unique_required_solutions_list( $manual_solutions );

		// Create a new list with the manual_solutions last.
		// This way self::unique_required_solutions_list() will allow them to take precedence (overwrite).
		return $this->unique_required_solutions_list( array_merge( $purchased_solutions, $manual_solutions ) );
	}

	/**
	 * Given a required solutions list (regardless of type), ensure uniqueness by the LT Solution managed_post_id entry.
	 *
	 * When required solutions with the same managed_post_id are encountered the last one takes precedence.
	 *
	 * @since 0.14.0
	 *
	 * @param array $required_solutions
	 *
	 * @return array
	 */
	public function unique_required_solutions_list( array $required_solutions ): array {
		// No point in doing anything fancy if we don't have more than 1 element.
		if ( count( $required_solutions ) < 2 ) {
			return array_values( $required_solutions );
		}

		$unique_solutions_with_keys = ArrayHelpers::array_map_assoc( function ( $key, $solution ) {
			// If we return the post ID as the key (as we would like), the key will be lost since it's numeric.
			// We use a string instead.
			return [ 'mpid_' . $solution['managed_post_id'] => $key ];
		}, $required_solutions );

		// Order the keys ascending according to the managed_post_id.
		uksort( $unique_solutions_with_keys, function ( $key1, $key2 ) {
			$key1 = intval( str_replace( 'mpid_', '', $key1 ) );
			$key2 = intval( str_replace( 'mpid_', '', $key2 ) );

			return ( $key1 < $key2 ) ? - 1 : 1;
		} );


		// Since the same key encountered would be overwritten, we now have as values to keys to keep.
		$unique_solutions = [];
		foreach ( $unique_solutions_with_keys as $key ) {
			$unique_solutions[] = $required_solutions[ $key ];
		}

		return array_values( $unique_solutions );
	}

	/**
	 * Get the list of required purchased-solutions details.
	 *
	 * @param int  $post_ID               The Composition post ID.
	 * @param bool $include_context       Whether to include context data about each required solution
	 *                                    (things like orders, timestamps, etc).
	 *
	 * @return array List of composition required purchased-solutions.
	 *               Each solution has its type, purchased-solution ID, slug, managed post ID and maybe context information, if $include_context is true.
	 */
	public function get_post_composition_required_purchased_solutions( int $post_ID, bool $include_context = false ): array {
		$purchased_solution_ids = carbon_get_post_meta( $post_ID, 'composition_required_purchased_solutions' );
		if ( empty( $purchased_solution_ids ) || ! is_array( $purchased_solution_ids ) ) {
			return [];
		}

		$purchased_solutions = $this->ps_manager->get_purchased_solutions( [
			'id__in' => $purchased_solution_ids,
			'number' => count( $purchased_solution_ids ),
		] );

		$required_purchased_solutions = [];
		foreach ( $purchased_solutions as $purchased_solution ) {
			$required_purchased_solutions[] = $this->normalize_purchased_solution( $purchased_solution, $include_context );
		}
		// Filter out any failed purchased-solutions (null or falsy).
		$required_purchased_solutions = array_filter( $required_purchased_solutions );

		// Maintain the order received.
		usort( $required_purchased_solutions, function ( $a, $b ) use ( $purchased_solution_ids ) {
			$a_key = array_search( $a['purchased_solution_id'], $purchased_solution_ids );
			$b_key = array_search( $b['purchased_solution_id'], $purchased_solution_ids );

			return ( $a_key < $b_key ) ? - 1 : 1;
		} );

		return $required_purchased_solutions;
	}

	/**
	 * Get the list of required purchased-solutions IDs.
	 *
	 * @param int  $post_ID               The Composition post ID.
	 *
	 * @return array List of composition required purchased-solutions IDs.
	 */
	public function get_post_composition_required_purchased_solutions_ids( int $post_ID ): array {
		$purchased_solution_ids = carbon_get_post_meta( $post_ID, 'composition_required_purchased_solutions' );
		if ( empty( $purchased_solution_ids ) || ! is_array( $purchased_solution_ids ) ) {
			return [];
		}

		return $purchased_solution_ids;
	}

	/**
	 * Get the details of a required purchased-solution ID.
	 *
	 * The purchased solution doesn't have to be part of a composition.
	 * This way one can obtain details in the same format as it would be required by a composition.
	 *
	 * @param int  $purchased_solution_id The purchased-solution ID.
	 * @param bool $include_context       Whether to include context data about the required purchased-solution
	 *                                    (things like orders, timestamps, etc).
	 *
	 * @return array|null Details of the required purchased-solution. Null if the purchased-solution was not found or its LT solution could not be found.
	 *               Each solution has its type, purchased-solution ID, slug, managed post ID and maybe context information, if $include_context is true.
	 */
	public function get_post_composition_required_purchased_solution( int $purchased_solution_id, bool $include_context = false ): ?array {

		$purchased_solution = $this->ps_manager->get_purchased_solution_by( 'id', $purchased_solution_id );
		if ( empty( $purchased_solution ) ) {
			return null;
		}

		return $this->normalize_purchased_solution( $purchased_solution );
	}

	/**
	 * @since 0.14.0
	 *
	 * @param PurchasedSolution $purchased_solution The purchased-solution object.
	 * @param bool              $include_context    Whether to include context data about the required purchased-solution
	 *                                              (things like orders, timestamps, etc).
	 *
	 * @return array|null The normalized details to be used in a composition data context.
	 *                    null if the LT solution post could not be found.
	 */
	protected function normalize_purchased_solution( PurchasedSolution $purchased_solution, bool $include_context = false ): ?array {
		$solution_post = \get_post( absint( $purchased_solution->solution_id ) );
		if ( empty( $solution_post ) ) {
			return null;
		}

		$purchased_solution_details = [
			'type'                  => 'purchased',
			'purchased_solution_id' => $purchased_solution->id,
			'slug'                  => $solution_post->post_name,
			'managed_post_id'       => $solution_post->ID,
		];

		if ( $include_context ) {
			$purchased_solution_details['context'] = [
				'purchased_solution' => [
					'id'             => $purchased_solution->id,
					'status'         => $purchased_solution->status,
					'solution_id'    => $purchased_solution->solution_id,
					'user_id'        => $purchased_solution->user_id,
					'order_id'       => $purchased_solution->order_id,
					'order_item_id'  => $purchased_solution->order_item_id,
					'composition_id' => $purchased_solution->composition_id,
					'date_created'   => $purchased_solution->date_created,
					'date_modified'  => $purchased_solution->date_modified,
				],
				'timestamp'          => '',
			];
		}

		return $purchased_solution_details;
	}

	/**
	 * Save the list of purchased solution IDs in the DB.
	 *
	 * No validation is made concerning the existence of the purchased-solutions in the list.
	 *
	 * @param int   $post_id                 The composition post ID.
	 * @param array $purchased_solutions_ids List of required purchased-solution ids.
	 * @param bool  $silent                  Optional. Whether to trigger action hooks. Default is to trigger the action hooks.
	 */
	public function set_post_composition_required_purchased_solutions( int $post_id, array $purchased_solutions_ids, bool $silent = false ) {
		if ( ! $silent ) {
			/**
			 * Fires before LT composition update.
			 *
			 * @since 0.14.0
			 *
			 * @param int $post_id The composition post ID.
			 */
			do_action( 'pixelgradelt_retailer/ltcomposition/before_update', $post_id );
		}

		// Make sure we have list of integers.
		$purchased_solutions_ids = array_map( 'intval', $purchased_solutions_ids );

		carbon_set_post_meta( $post_id, 'composition_required_purchased_solutions', $purchased_solutions_ids );

		if ( ! $silent ) {
			/**
			 * Fires after LT composition update.
			 *
			 * The provided parameters are compatible with the 'wp_after_insert_post' core action, so we can use the same handlers.
			 *
			 * @since 0.14.0
			 *
			 * @param int      $post_id The composition post ID.
			 * @param \WP_Post $post    The composition post object.
			 * @param bool     $update  If this is an update.
			 */
			do_action( 'pixelgradelt_retailer/ltcomposition/update',
				$post_id,
				get_post( $post_id ),
				true
			);
		}
	}

	/**
	 * Add a composition required purchased-solution to the list.
	 *
	 * @since 0.14.0
	 *
	 * @param int  $post_id               The composition post ID.
	 * @param int  $purchased_solution_id The purchased solution id.
	 * @param bool $update                Optional. Whether to update the details of an already present solution.
	 *                                    If false and the solution is already present, nothing is added or modified.
	 *                                    Default false.
	 * @param bool $process_solutions     Optional. Whether to run the solution list processing logic after adding the solution.
	 *                                    This way, solutions that are excluded by the newly added solution will be removed.
	 *                                    See \PixelgradeLT\Retailer\Repository\ProcessedSolutions::process_solutions()
	 *                                    The processing will run only when the solution is added to the list,
	 *                                    not when it is already present, regardless of the $update value.
	 *                                    Default false.
	 *
	 * @return bool True on success. False if the solution is already present and we haven't been instructed to update
	 *              or the data is invalid.
	 */
	public function add_post_composition_required_purchased_solution( int $post_id, int $purchased_solution_id, bool $update = false, bool $process_solutions = false ): bool {
		$composition_post = \get_post( $post_id );
		if ( empty( $composition_post ) ) {
			return false;
		}

		$required_purchased_solution_ids = carbon_get_post_meta( $composition_post->ID, 'composition_required_purchased_solutions' );
		if ( empty( $required_purchased_solution_ids ) || ! is_array( $required_purchased_solution_ids ) ) {
			$required_purchased_solution_ids = [];
		}

		// It is already part of the list.
		if ( in_array( $purchased_solution_id, $required_purchased_solution_ids ) ) {
			return false;
		}

		$old_required_solutions = $required_purchased_solution_ids;

		// Add it to the list.
		$required_purchased_solution_ids[] = $purchased_solution_id;

		// Run the solutions list processing if we have been instructed to do so,
		// but only if we actually added the solution to the list.
		// @todo We may need to process the entire composition required solutions list, rather than only the manual list.
		$did_process_solutions = false;
		if ( $process_solutions && ! empty( $required_purchased_solution_ids ) ) {
			// Gather all the required solutions IDs, the manual way.
			$required_purchased_solutions = array_map( function ( $ps_id ) {
				return $this->get_post_composition_required_purchased_solution( $ps_id );
			}, $required_purchased_solution_ids );
			$required_purchased_solutions = array_filter( $required_purchased_solutions );
			$solutionsIds                 = \wp_list_pluck( $required_purchased_solutions, 'managed_post_id' );

			if ( ! empty( $solutionsIds ) ) {
				$processed_required_solutions = local_rest_call( '/pixelgradelt_retailer/v1/solutions/processed', 'GET', [
					'postId' => array_values( $solutionsIds ),
				] );

				if ( isset( $processed_required_solutions['code'] ) || isset( $processed_required_solutions['message'] ) ) {
					// We have failed to get the processed solutions. Log and move on.
					$this->logger->error(
						'Error processing the composition required solutions list for the composition with post ID #{post_id}, before adding a new solution programmatically.',
						[
							'post_id'        => $composition_post->ID,
							'added_solution' => $purchased_solution_id,
							'response'       => $processed_required_solutions,
							'logCategory'    => 'composition_manager',
						]
					);
				} elseif ( ! empty( $processed_required_solutions ) ) {
					$did_process_solutions = true;
					// Leave only the required purchased-solutions with LT solutions that are still in the processed list.
					$required_purchased_solutions = array_filter( $required_purchased_solutions,
						function ( $ps ) use ( $processed_required_solutions ) {
							return false !== ArrayHelpers::findSubarrayByKeyValue( $processed_required_solutions, 'id', $ps['managed_post_id'] );
						}
					);

					// Replace the $required_purchased_solution_ids with what required_purchased_solutions remained.
					$required_purchased_solution_ids = array_values( \wp_list_pluck( $required_purchased_solutions, 'purchased_solution_id' ) );
				}
			}
		}

		/**
		 * Filters the new composition required purchased-solutions list after adding a new solution.
		 *
		 * @since 0.14.0
		 *
		 * @param array $new_required_solution_ids The new required solutions list.
		 * @param int   $post_id                   The composition post ID.
		 * @param array $old_required_solution_ids The old required solutions list.
		 * @param array $new_purchased_solution_id The added purchased-solution id.
		 * @param bool  $processed                 Whether we have run the solution list processing logic after adding the solution.
		 */
		$required_purchased_solution_ids = apply_filters( 'pixelgradelt_retailer/ltcomposition/add_required_purchased_solution',
			$required_purchased_solution_ids,
			$post_id,
			$old_required_solutions,
			$purchased_solution_id,
			$did_process_solutions
		);

		$this->set_post_composition_required_purchased_solutions( $composition_post->ID, $required_purchased_solution_ids );

		return true;
	}

	/**
	 * Remove a composition required purchased-solution from the list.
	 *
	 * @since 0.14.0
	 *
	 * @param int $post_id               The composition post ID.
	 * @param int $purchased_solution_id The purchased solution id to remove.
	 *
	 * @return bool True on success. False if the solution was not found in the list of the $purchased_solution_id is invalid.
	 */
	public function remove_post_composition_required_purchased_solution( int $post_id, int $purchased_solution_id ): bool {
		$composition_post = \get_post( $post_id );
		if ( empty( $composition_post ) ) {
			return false;
		}

		$required_solutions = carbon_get_post_meta( $composition_post->ID, 'composition_required_manual_solutions' );
		if ( empty( $required_solutions ) || ! is_array( $required_solutions ) ) {
			// Nothing to remove.
			return false;
		}

		$old_required_solutions = $required_solutions;

		$pseudo_id_to_remove = false;

		// Try a given pseudo_id.
		if ( is_string( $purchased_solution_id ) ) {
			$pseudo_id_to_remove = $purchased_solution_id;
		} else if ( is_numeric( $purchased_solution_id ) ) {
			$required_solution_post = \get_post( absint( $purchased_solution_id ) );
			if ( ! empty( $required_solution_post ) ) {
				$pseudo_id_to_remove = $required_solution_post->post_name . self::PSEUDO_ID_DELIMITER . $required_solution_post->ID;
			}
		}

		if ( empty( $pseudo_id_to_remove ) ) {
			return false;
		}

		// Search if the target solution is present in the list.
		$found_key = ArrayHelpers::findSubarrayByKeyValue( $required_solutions, 'pseudo_id', $pseudo_id_to_remove );
		if ( false === $found_key ) {
			return false;
		}

		// Remove it from the list.
		unset( $required_solutions[ $found_key ] );

		/**
		 * Filters the new composition required solutions list after removing a solution.
		 *
		 * @since 0.14.0
		 *
		 * @param array  $new_required_manual_solutions The new required manual-solutions list.
		 * @param int    $post_id                       The composition post ID.
		 * @param array  $old_required_manual_solutions The old required manual-solutions list.
		 * @param string $removed_solution_pseudo_id    The removed solution pseudo_id.
		 */
		$required_solutions = apply_filters( 'pixelgradelt_retailer/ltcomposition/remove_required_manual_solution',
			$required_solutions,
			$post_id,
			$old_required_solutions,
			$pseudo_id_to_remove
		);

		$this->set_post_composition_required_manual_solutions( $composition_post->ID, $required_solutions );

		return true;
	}

	/**
	 * Get the list of required manual-solutions (not attached to a solution purchase).
	 *
	 * @param int    $post_ID             The Composition post ID.
	 * @param bool   $include_context     Whether to include context data about each required solution
	 *                                    (things like reasons, timestamps, etc).
	 * @param string $pseudo_id_delimiter The delimiter used to construct each required solution's value.
	 *
	 * @return array List of composition required manual-solutions.
	 *               Each solution has its type, pseudo ID, slug, managed post ID and maybe context information, if $include_context is true.
	 */
	public function get_post_composition_required_manual_solutions( int $post_ID, bool $include_context = false, string $pseudo_id_delimiter = '' ): array {
		$manual_solutions = carbon_get_post_meta( $post_ID, 'composition_required_manual_solutions' );
		if ( empty( $manual_solutions ) || ! is_array( $manual_solutions ) ) {
			return [];
		}

		// Make sure only the fields we are interested in are left.
		$accepted_keys = array_fill_keys( [ 'pseudo_id', ], '' );
		$context_keys  = array_fill_keys( [ 'reason', 'timestamp', ], '' );
		foreach ( $manual_solutions as $key => $required_solution ) {
			$manual_solutions[ $key ] = array_replace( $accepted_keys, array_intersect_key( $required_solution, $accepted_keys ) );

			if ( empty( $required_solution['pseudo_id'] ) ) {
				unset( $manual_solutions[ $key ] );
				continue;
			}

			// We will now split the pseudo_id in its components (slug and post_id with the delimiter in between) and check them.
			$pseudo_id_components = $this->explode_pseudo_id( $required_solution['pseudo_id'], $pseudo_id_delimiter );
			if ( empty( $pseudo_id_components ) ) {
				unset( $manual_solutions[ $key ] );
				continue;
			}

			[ $slug, $post_id ] = $pseudo_id_components;

			$manual_solutions[ $key ]['type']            = 'manual';
			$manual_solutions[ $key ]['slug']            = $slug;
			$manual_solutions[ $key ]['managed_post_id'] = $post_id;

			if ( $include_context ) {
				$manual_solutions[ $key ]['context'] = array_replace( $context_keys, array_intersect_key( $required_solution, $context_keys ) );
			}
		}

		return $manual_solutions;
	}

	/**
	 * Explode a pseudo ID into its slug and post ID components.
	 *
	 * @since 0.14.0
	 *
	 * @param string $pseudo_id
	 * @param string $pseudo_id_delimiter Optional.
	 *
	 * @return array|null The slug and post ID. Null on invalid pseudo_id.
	 */
	protected function explode_pseudo_id( string $pseudo_id, string $pseudo_id_delimiter = '' ): ?array {
		if ( empty( $pseudo_id_delimiter ) ) {
			$pseudo_id_delimiter = self::PSEUDO_ID_DELIMITER;
		}

		if ( ! is_string( $pseudo_id ) || false === strpos( $pseudo_id, $pseudo_id_delimiter ) ) {
			return null;
		}

		$components = explode( $pseudo_id_delimiter, $pseudo_id );
		if ( count( $components ) < 2 || empty( $components[0] ) || empty( $components[1] ) || ! is_numeric( $components[1] ) ) {
			return null;
		}

		$components[1] = intval( $components[1] );

		return $components;
	}

	/**
	 * @param int   $post_id   The composition post ID.
	 * @param array $solutions List of required solution details: `post_id` or `pseudo_id`, `order_id`, `order_item_id`.
	 * @param bool  $silent    Optional. Whether to trigger action hooks. Default is to trigger the action hooks.
	 */
	public function set_post_composition_required_manual_solutions( int $post_id, array $solutions, bool $silent = false ) {
		if ( ! $silent ) {
			/**
			 * Fires before LT composition update.
			 *
			 * @since 0.14.0
			 *
			 * @param int $post_id The composition post ID.
			 */
			do_action( 'pixelgradelt_retailer/ltcomposition/before_update', $post_id );
		}

		// We need to normalize the received $required_solutions for the DB (the format that CarbonFields uses).
		foreach ( $solutions as $key => $solution ) {
			$normalized_solution = [
				'pseudo_id'     => '',
				'order_id'      => $solution['order_id'] ?? '',
				'order_item_id' => $solution['order_item_id'] ?? '',
			];

			// Try a given post ID entry.
			if ( ! empty( $solution['post_id'] ) ) {
				$solution_post = \get_post( absint( $solution['post_id'] ) );
				if ( ! empty( $solution_post ) ) {
					// If we have been given a valid post ID, this overwrites any pseudo_id.
					$normalized_solution['pseudo_id'] = $solution_post->post_name . self::PSEUDO_ID_DELIMITER . $solution_post->ID;
				}
			}

			// Try the pseudo_id entry.
			if ( empty( $normalized_solution['pseudo_id'] ) && ! empty( $solution['pseudo_id'] ) ) {
				$normalized_solution['pseudo_id'] = $solution['pseudo_id'];
			}

			// We reject a solution for which we don't have a pseudo_id.
			if ( empty( $normalized_solution['pseudo_id'] ) ) {
				unset( $solutions[ $key ] );
				continue;
			}

			$solutions[ $key ] = $normalized_solution;
		}

		carbon_set_post_meta( $post_id, 'composition_required_manual_solutions', $solutions );

		if ( ! $silent ) {
			/**
			 * Fires after LT composition update.
			 *
			 * The provided parameters are compatible with the 'wp_after_insert_post' core action, so we can use the same handlers.
			 *
			 * @since 0.14.0
			 *
			 * @param int      $post_id The composition post ID.
			 * @param \WP_Post $post    The composition post object.
			 * @param bool     $update  If this is an update.
			 */
			do_action( 'pixelgradelt_retailer/ltcomposition/update',
				$post_id,
				get_post( $post_id ),
				true
			);
		}
	}

	/**
	 * Add a composition required solution to the list.
	 *
	 * @since 0.14.0
	 *
	 * @param int   $post_id            The composition post ID.
	 * @param array $required_solution  The required solution details (post_id and/or pseudo_id, order_id, order_item_id).
	 * @param bool  $update             Optional. Whether to update the details of an already present solution.
	 *                                  If false and the solution is already present, nothing is added or modified.
	 *                                  Default false.
	 * @param bool  $process_solutions  Optional. Whether to run the solution list processing logic after adding the solution.
	 *                                  This way, solutions that are excluded by the newly added solution will be removed.
	 *                                  See \PixelgradeLT\Retailer\Repository\ProcessedSolutions::process_solutions()
	 *                                  The processing will run only when the solution is added to the list,
	 *                                  not when it is already present, regardless of the $update value.
	 *                                  Default false.
	 *
	 * @return bool True on success. False if the solution is already present and we haven't been instructed to update
	 *              or the data is invalid.
	 */
	public function add_post_composition_required_manual_solution( int $post_id, array $required_solution, bool $update = false, bool $process_solutions = false ): bool {
		$composition_post = \get_post( $post_id );
		if ( empty( $composition_post ) ) {
			return false;
		}

		$required_solutions = carbon_get_post_meta( $composition_post->ID, 'composition_required_manual_solutions' );
		if ( empty( $required_solutions ) || ! is_array( $required_solutions ) ) {
			$required_solutions = [];
		}

		$old_required_solutions = $required_solutions;

		$accepted_keys     = array_fill_keys( [ 'post_id', 'pseudo_id', 'reason' ], '' );
		$required_solution = array_replace( $accepted_keys, array_intersect_key( $required_solution, $accepted_keys ) );

		$required_solution_post_id = false;

		// Try a given post ID.
		if ( ! empty( $required_solution['post_id'] ) ) {
			$required_solution_post = \get_post( absint( $required_solution['post_id'] ) );
			if ( ! empty( $required_solution_post ) ) {
				$required_solution_post_id = $required_solution_post->ID;
				// If we have been given a valid post ID, this overwrites any pseudo_id.
				$required_solution['pseudo_id'] = $required_solution_post->post_name . self::PSEUDO_ID_DELIMITER . $required_solution_post_id;
			}
		}

		// Try the pseudo_id.
		if ( empty( $required_solution_post_id ) && ! empty( $required_solution['pseudo_id'] ) ) {

			// We will now split the pseudo_id in its components (slug and post_id with the delimiter in between) and check them.
			$pseudo_id_components = $this->explode_pseudo_id( $required_solution['pseudo_id'] );
			if ( ! empty( $pseudo_id_components ) ) {
				$required_solution_post_id = $pseudo_id_components[1];
			}
		}

		// Bail if we've failed to identify a solution post ID.
		if ( empty( $required_solution_post_id ) ) {
			return false;
		}

		// Check that the post ID corresponds to a valid solution package.
		$package = $this->solutions->first_where( [
			'managed_post_id' => $required_solution_post_id,
		] );
		if ( empty( $package ) ) {
			return false;
		}

		// Search if the provided solution is already present in the list.
		$did_update_existing_solution = false;
		$found_key                    = ArrayHelpers::findSubarrayByKeyValue( $required_solutions, 'pseudo_id', $required_solution['pseudo_id'] );
		if ( false === $found_key ) {
			// Recreate the solution data before adding it to the list to be sure that we only pass along data supported by our controls.
			$required_solutions[] = array_replace( $accepted_keys, array_intersect_key( $required_solution, array_fill_keys( [
				'pseudo_id',
				'reason',
			], '' ) ) );
		} else {
			if ( ! $update ) {
				return false;
			}

			$did_update_existing_solution = true;

			// Maybe update the reason.
			if ( ! empty( $required_solution['reason'] ) ) {
				$required_solutions[ $found_key ]['reason'] = $required_solution['reason'];
			}
		}

		// Run the solutions list processing if we have been instructed to do so,
		// but only if we actually added the solution to the list.
		// @todo We may need to process the entire composition required solutions list, rather than only the manual list.
		$did_process_solutions = false;
		if ( $process_solutions && ! $did_update_existing_solution && ! empty( $required_solutions ) ) {
			// Gather all the required solutions IDs, the manual way.
			$solutionsIds = array_filter( array_map( function ( $details ) {
				$pseudo_id_components = $this->explode_pseudo_id( $details['pseudo_id'] );
				if ( empty( $pseudo_id_components ) ) {
					return null;
				}

				return $pseudo_id_components[1];
			}, $required_solutions ) );

			if ( ! empty( $solutionsIds ) ) {
				$processed_required_solutions = local_rest_call( '/pixelgradelt_retailer/v1/solutions/processed', 'GET', [
					'postId' => $solutionsIds,
				] );

				if ( isset( $processed_required_solutions['code'] ) || isset( $processed_required_solutions['message'] ) ) {
					// We have failed to get the processed solutions. Log and move on.
					$this->logger->error(
						'Error processing the composition required solutions list for the composition with post ID #{post_id}, before adding a new solution programmatically.',
						[
							'post_id'        => $composition_post->ID,
							'added_solution' => $required_solution,
							'response'       => $processed_required_solutions,
							'logCategory'    => 'composition_manager',
						]
					);
				} elseif ( ! empty( $processed_required_solutions ) ) {
					$did_process_solutions = true;
					// Leave only the required solutions that are still in the processed list.
					$required_solutions = array_filter( $required_solutions,
						function ( $details ) use ( $processed_required_solutions ) {
							$pseudo_id_components = $this->explode_pseudo_id( $details['pseudo_id'] );

							return false !== ArrayHelpers::findSubarrayByKeyValue( $processed_required_solutions, 'id', $pseudo_id_components[1] );
						}
					);
				}
			}
		}

		/**
		 * Filters the new composition required solutions list after adding a new solution.
		 *
		 * @since 0.14.0
		 *
		 * @param array $new_required_solutions The new required solutions list.
		 * @param int   $post_id                The composition post ID.
		 * @param array $old_required_solutions The old required solutions list.
		 * @param array $new_solution           The new solution details (pseudo_id, order_id, order_item_id).
		 * @param bool  $updated                Whether we have updated the details of an already existing solution.
		 * @param bool  $processed              Whether we have run the solution list processing logic after adding the solution.
		 */
		$required_solutions = apply_filters( 'pixelgradelt_retailer/ltcomposition/add_required_manual_solution',
			$required_solutions,
			$post_id,
			$old_required_solutions,
			$required_solution,
			$did_update_existing_solution,
			$did_process_solutions
		);

		$this->set_post_composition_required_manual_solutions( $composition_post->ID, $required_solutions );

		return true;
	}

	/**
	 * Remove a composition required manual solution from the list.
	 *
	 * @since 0.14.0
	 *
	 * @param int        $post_id              The composition post ID.
	 * @param int|string $required_solution_id The required solution id to remove (post_id or pseudo_id).
	 *
	 * @return bool True on success. False if the solution was not found in the list of the $required_solution_id is invalid.
	 */
	public function remove_post_composition_required_manual_solution( int $post_id, $required_solution_id ): bool {
		$composition_post = \get_post( $post_id );
		if ( empty( $composition_post ) ) {
			return false;
		}

		$required_solutions = carbon_get_post_meta( $composition_post->ID, 'composition_required_manual_solutions' );
		if ( empty( $required_solutions ) || ! is_array( $required_solutions ) ) {
			// Nothing to remove.
			return false;
		}

		$old_required_solutions = $required_solutions;

		$pseudo_id_to_remove = false;

		// Try a given pseudo_id.
		if ( is_string( $required_solution_id ) ) {
			$pseudo_id_to_remove = $required_solution_id;
		} else if ( is_numeric( $required_solution_id ) ) {
			$required_solution_post = \get_post( absint( $required_solution_id ) );
			if ( ! empty( $required_solution_post ) ) {
				$pseudo_id_to_remove = $required_solution_post->post_name . self::PSEUDO_ID_DELIMITER . $required_solution_post->ID;
			}
		}

		if ( empty( $pseudo_id_to_remove ) ) {
			return false;
		}

		// Search if the target solution is present in the list.
		$found_key = ArrayHelpers::findSubarrayByKeyValue( $required_solutions, 'pseudo_id', $pseudo_id_to_remove );
		if ( false === $found_key ) {
			return false;
		}

		// Remove it from the list.
		unset( $required_solutions[ $found_key ] );

		/**
		 * Filters the new composition required solutions list after removing a solution.
		 *
		 * @since 0.14.0
		 *
		 * @param array  $new_required_manual_solutions The new required manual-solutions list.
		 * @param int    $post_id                       The composition post ID.
		 * @param array  $old_required_manual_solutions The old required manual-solutions list.
		 * @param string $removed_solution_pseudo_id    The removed solution pseudo_id.
		 */
		$required_solutions = apply_filters( 'pixelgradelt_retailer/ltcomposition/remove_required_manual_solution',
			$required_solutions,
			$post_id,
			$old_required_solutions,
			$pseudo_id_to_remove
		);

		$this->set_post_composition_required_manual_solutions( $composition_post->ID, $required_solutions );

		return true;
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
	public function extract_required_solutions_post_ids( array $required_solutions ): array {
		return \wp_list_pluck( $required_solutions, 'managed_post_id' );
	}

	/**
	 * @param array $required_solutions The Composition's required solutions data.
	 *
	 * @return Package[]
	 */
	public function extract_required_solutions_context( array $required_solutions ): array {
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

	public function get_post_composition_composer_require( int $post_ID, string $container_id = '', string $pseudo_id_delimiter = '' ): array {
		if ( empty( $pseudo_id_delimiter ) ) {
			$pseudo_id_delimiter = self::PSEUDO_ID_DELIMITER;
		}

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

		$composition = \get_post( $composition_id );
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
