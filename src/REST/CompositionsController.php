<?php
/**
 * Compositions REST controller.
 *
 * @since   0.10.0
 * @license GPL-2.0-or-later
 * @package PixelgradeLT
 */

declare ( strict_types=1 );

namespace PixelgradeLT\Retailer\REST;

use Composer\Json\JsonFile;
use Composer\Json\JsonValidationException;
use InvalidArgumentException;
use JsonSchema\Validator;
use PixelgradeLT\Retailer\Capabilities;
use PixelgradeLT\Retailer\CompositionManager;
use PixelgradeLT\Retailer\CrypterInterface;
use PixelgradeLT\Retailer\Exception\CrypterBadFormatException;
use PixelgradeLT\Retailer\Exception\CrypterEnvironmentIsBrokenException;
use PixelgradeLT\Retailer\Exception\CrypterWrongKeyOrModifiedCiphertextException;
use PixelgradeLT\Retailer\Exception\RestException;
use PixelgradeLT\Retailer\Transformer\ComposerSolutionTransformer;
use WP_Error;
use WP_REST_Controller;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;
use WP_Http as HTTP;
use function PixelgradeLT\Retailer\plugin;

/**
 * Compositions REST controller class.
 *
 * @since 0.10.0
 */
class CompositionsController extends WP_REST_Controller {

	/**
	 * Composer package name pattern.
	 *
	 * This is the same pattern present in the Composer schema: https://getcomposer.org/schema.json
	 *
	 * @since 0.10.0
	 *
	 * @var string
	 */
	const PACKAGE_NAME_PATTERN = '^[a-z0-9]([_.-]?[a-z0-9]+)*/[a-z0-9](([_.]?|-{0,2})[a-z0-9]+)*$';

	/**
	 * Composition hashid pattern.
	 *
	 * @var string
	 */
	const HASHID_PATTERN = '[A-Za-z0-9]*';

	/**
	 * The key in composer.json `extra` used to store the encrypted composition LT details.
	 *
	 * @since 0.10.0
	 *
	 * @var string
	 */
	const LTDETAILS_KEY = 'lt-composition';

	/**
	 * The key in composer.json `extra` used to store the composer.json fingerprint.
	 *
	 * @since 0.10.0
	 *
	 * @var string
	 */
	const FINGERPRINT_KEY = 'lt-fingerprint';

	/**
	 * The key in composer.json `extra` used to store the composer.json LT version.
	 *
	 * We will use this in case we make breaking changes and wish to provide backwards compatibility.
	 *
	 * @since 0.10.0
	 *
	 * @var string
	 */
	const VERSION_KEY = 'lt-version';

	/**
	 * Composition manager.
	 *
	 * @var CompositionManager
	 */
	protected CompositionManager $composition_manager;

	/**
	 * Composer solution transformer.
	 *
	 * @var ComposerSolutionTransformer
	 */
	protected ComposerSolutionTransformer $composer_transformer;

	/**
	 * String crypter.
	 *
	 * @since 0.10.0
	 *
	 * @var CrypterInterface
	 */
	protected CrypterInterface $crypter;

	/**
	 * Constructor.
	 *
	 * @since 0.10.0
	 *
	 * @param string                      $namespace            The namespace for this controller's route.
	 * @param string                      $rest_base            The base of this controller's route.
	 * @param CompositionManager          $composition_manager  Composition manager.
	 * @param ComposerSolutionTransformer $composer_transformer Solution transformer.
	 * @param CrypterInterface            $crypter              String crypter.
	 */
	public function __construct(
		string $namespace,
		string $rest_base,
		CompositionManager $composition_manager,
		ComposerSolutionTransformer $composer_transformer,
		CrypterInterface $crypter
	) {

		$this->namespace            = $namespace;
		$this->rest_base            = $rest_base;
		$this->composition_manager  = $composition_manager;
		$this->composer_transformer = $composer_transformer;
		$this->crypter              = $crypter;
	}

	/**
	 * Register the routes.
	 *
	 * @since 0.10.0
	 *
	 * @see   register_rest_route()
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			[
				[
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_items' ],
					'permission_callback' => [ $this, 'get_items_permissions_check' ],
					'show_in_index'       => false,
					'args'                => $this->get_collection_params(),
				],
				[
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'create_item' ],
					'permission_callback' => [ $this, 'create_item_permissions_check' ],
					'show_in_index'       => false,
					'args'                => [
						'name'                            => [
							'description' => esc_html__( 'The composition\'s name/title.', 'pixelgradelt_retailer' ),
							'type'        => 'string',
							'context'     => [ 'view', 'edit', 'embed' ],
							'required'    => false,
						],
						'keywords'                        => [
							'description' => esc_html__( 'The composition\'s keywords.', 'pixelgradelt_retailer' ),
							'type'        => 'array',
							'items'       => [
								'type' => 'string',
							],
							'context'     => [ 'view', 'edit' ],
							'required'    => false,
						],
						'userids'                         => [
							'description' => esc_html__( 'The owner/user IDs list.', 'pixelgradelt_retailer' ),
							'type'        => 'array',
							'items'       => [
								'type' => 'integer',
							],
							'context'     => [ 'view', 'edit' ],
							'required'    => false,
						],
						'required_purchased_solution_ids' => [
							'description' => esc_html__( 'The composition\'s list of purchased required solutions IDs.', 'pixelgradelt_retailer' ),
							'type'        => 'array',
							'items'       => [
								'type' => 'integer',
							],
							'context'     => [ 'edit', ],
							'required'    => false,
						],
						'context'                         => $this->get_context_param( [ 'default' => 'view' ] ),
					],
				],
				'schema' => [ $this, 'get_public_item_schema' ],
			]
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<compositionid>' . self::HASHID_PATTERN . ')',
			[
				'args'   => [
					'compositionid' => [
						'description'       => __( 'The composition hashid.', 'pixelgradelt_retailer' ),
						'type'              => 'string',
						'pattern'           => self::HASHID_PATTERN,
						'required'          => true,
						'sanitize_callback' => function ( $value ) {
							return preg_replace( '/[^A-Za-z0-9]+/', '', $value );
						},
					],
				],
				[
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_item' ],
					'permission_callback' => [ $this, 'get_item_permissions_check' ],
					'args'                => [
						'context' => $this->get_context_param( [ 'default' => 'view' ] ),
					],
				],
				[
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => [ $this, 'edit_item' ],
					'permission_callback' => [ $this, 'edit_item_permissions_check' ],
					'show_in_index'       => false,
					'args'                => [
						'name'                            => [
							'description' => esc_html__( 'The composition\'s name/title.', 'pixelgradelt_retailer' ),
							'type'        => 'string',
							'context'     => [ 'view', 'edit', 'embed' ],
							'required'    => false,
						],
						'keywords'                        => [
							'description' => esc_html__( 'The composition\'s keywords.', 'pixelgradelt_retailer' ),
							'type'        => 'array',
							'items'       => [
								'type' => 'string',
							],
							'context'     => [ 'view', 'edit' ],
							'required'    => false,
						],
						'userids'                         => [
							'description' => esc_html__( 'The owner/user IDs list.', 'pixelgradelt_retailer' ),
							'type'        => 'array',
							'items'       => [
								'type' => 'integer',
							],
							'context'     => [ 'view', 'edit' ],
							'required'    => false,
						],
						'required_purchased_solution_ids' => [
							'description' => esc_html__( 'The composition\'s list of purchased required solutions IDs.', 'pixelgradelt_retailer' ),
							'type'        => 'array',
							'items'       => [
								'type' => 'integer',
							],
							'context'     => [ 'edit', ],
							'required'    => false,
						],
						'context'                         => $this->get_context_param( [ 'default' => 'view' ] ),
					],
				],
				[
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => [ $this, 'delete_item' ],
					'permission_callback' => [ $this, 'delete_item_permissions_check' ],
					'show_in_index'       => false,
				],
				'schema' => [ $this, 'get_public_item_schema' ],
			]
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/encrypt_ltdetails',
			[
				[
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'encrypt_ltdetails' ],
					'permission_callback' => [ $this, 'encrypt_ltdetails_permissions_check' ],
					'show_in_index'       => false,
					'args'                => [
						'context'       => $this->get_context_param( [ 'default' => 'edit' ] ),
						'userids'       => [
							'description' => esc_html__( 'The owner/user IDs list.', 'pixelgradelt_retailer' ),
							'type'        => 'array',
							'items'       => [
								'type' => 'integer',
							],
							'context'     => [ 'view', 'edit' ],
							'required'    => true,
						],
						'compositionid' => [
							'description' => esc_html__( 'The composition ID.', 'pixelgradelt_retailer' ),
							'type'        => 'string',
							'context'     => [ 'view', 'edit' ],
							'required'    => true,
						],
						'extra'         => [
							'type'        => 'object',
							'description' => esc_html__( 'Extra details to encrypt besides the core details.', 'pixelgradelt_retailer' ),
							'default'     => [],
							'context'     => [ 'view', 'edit' ],
						],
					],
				],
			]
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/check_ltdetails',
			[
				[
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'check_ltdetails' ],
					'permission_callback' => [ $this, 'check_items_details_permissions_check' ],
					'show_in_index'       => false,
					'args'                => [
						'context'   => $this->get_context_param( [ 'default' => 'view' ] ),
						'ltdetails' => [
							'description' => esc_html__( 'The encrypted LT details to check.', 'pixelgradelt_retailer' ),
							'type'        => 'string',
							'context'     => [ 'view', 'edit' ],
							'required'    => true,
						],
						'composer'  => [
							'type'        => 'object',
							'description' => esc_html__( 'composer.json project (root) properties according to the Composer 2.0 JSON schema.', 'pixelgradelt_retailer' ),
							'default'     => [],
							'context'     => [ 'view', 'edit' ],
						],
					],
				],
			]
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/instructions_to_update',
			[
				[
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'instructions_to_update_composition' ],
					'permission_callback' => [ $this, 'update_items_details_permissions_check' ],
					'show_in_index'       => false,
					'args'                => [
						'context'  => $this->get_context_param( [ 'default' => 'edit' ] ),
						'composer' => [
							'description' => esc_html__( 'The full composer.json contents to determine if they need updating.', 'pixelgradelt_retailer' ),
							'type'        => 'object',
							'context'     => [ 'view', 'edit' ],
							'required'    => true,
						],
					],
				],
			]
		);
	}

	/**
	 * Check if a given request has access to view the resource.
	 *
	 * @since 0.15.0
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 *
	 * @return true|WP_Error True if the request has read access, WP_Error object otherwise.
	 */
	public function get_items_permissions_check( $request ) {
		if ( ! current_user_can( Capabilities::VIEW_COMPOSITIONS ) ) {
			return new WP_Error(
				'rest_cannot_read',
				esc_html__( 'Sorry, you are not allowed to view compositions.', 'pixelgradelt_retailer' ),
				[ 'status' => rest_authorization_required_code() ]
			);
		}

		return true;
	}

	/**
	 * Retrieve a collection of compositions.
	 *
	 * @since 0.15.0
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 *
	 * @return WP_Error|WP_REST_Response
	 */
	public function get_items( $request ) {
		$current_user_id = get_current_user_id();
		if ( empty( $current_user_id ) ) {
			return new WP_Error(
				'rest_cannot_read',
				esc_html__( 'You need to be logged in to view compositions.', 'pixelgradelt_retailer' ),
				[ 'status' => rest_authorization_required_code() ]
			);
		}

		$items = [];

		$args            = [
			'post_ids' => $request['postId'] ?? [],
			'hashid'   => $request['hashid'] ?? [],
			'status'   => $request['status'] ?? [],
			'userid'   => $current_user_id,
		];
		$composition_ids = $this->composition_manager->get_composition_ids_by( $args );

		foreach ( $composition_ids as $composition_id ) {
			$composition_data = $this->composition_manager->get_composition_id_data( $composition_id );
			if ( empty( $composition_data ) ) {
				continue;
			}

			$data    = $this->prepare_item_for_response( $composition_data, $request );
			$items[] = $this->prepare_response_for_collection( $data );
		}

		return rest_ensure_response( $items );
	}

	/**
	 * Retrieve the query parameters for collections of solutions.
	 *
	 * @since 0.15.0
	 *
	 * @return array
	 */
	public function get_collection_params(): array {
		$params = [
			'context' => $this->get_context_param( [ 'default' => 'view' ] ),
		];

		$params['postId'] = [
			'description'       => esc_html__( 'Limit results to compositions by one or more post IDs.', 'pixelgradelt_retailer' ),
			'type'              => 'array',
			'items'             => [
				'type' => 'integer',
			],
			'default'           => [],
			'sanitize_callback' => 'wp_parse_id_list',
		];

		$params['hashid'] = [
			'description' => esc_html__( 'Limit results to compositions by one or more hashids.', 'pixelgradelt_retailer' ),
			'type'        => 'array',
			'items'       => [
				'type'    => 'string',
				'pattern' => self::HASHID_PATTERN,
			],
			'default'     => [],
		];

		$params['status'] = [
			'description'       => esc_html__( 'Limit results to compositions of one or more statuses.', 'pixelgradelt_retailer' ),
			'type'              => 'array',
			'items'             => [
				'type' => 'string',
			],
			'default'           => [],
			'sanitize_callback' => 'wp_parse_slug_list',
		];

		return $params;
	}

	/**
	 * Check if a given request has access to create a resource.
	 *
	 * @since 0.15.0
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 *
	 * @return true|WP_Error True if the request has access to create items, WP_Error object otherwise.
	 */
	public function create_item_permissions_check( $request ) {
		if ( ! current_user_can( Capabilities::CREATE_COMPOSITIONS ) ) {
			return new WP_Error(
				'rest_cannot_create',
				esc_html__( 'Sorry, you are not allowed to create compositions.', 'pixelgradelt_retailer' ),
				[ 'status' => rest_authorization_required_code() ]
			);
		}

		return true;
	}

	/**
	 * Create a composition.
	 *
	 * @since 0.15.0
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 *
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function create_item( $request ) {
		$args = [
			'name'                            => $request->get_param( 'name' ),
			'keywords'                        => $request->get_param( 'keywords' ),
			'user_ids'                        => $request->get_param( 'userids' ),
			'required_purchased_solution_ids' => $request->get_param( 'required_purchased_solution_ids' ),
		];

		// If no user IDs provided, ensure the current user is the owner.
		if ( empty( $args['user_ids'] ) ) {
			$args['user_ids'] = [ get_current_user_id() ];
		}

		$result = $this->composition_manager->save_composition( $args, false );
		if ( empty( $result ) ) {
			return new WP_Error(
				'pixelgradelt_retailer_rest_failed',
				__( 'Operation failed.', 'pixelgradelt_retailer' ),
				[ 'status' => HTTP::NOT_ACCEPTABLE ]
			);
		}

		$composition_data = $this->composition_manager->get_composition_id_data( $result );
		if ( empty( $composition_data ) ) {
			return new WP_Error(
				'pixelgradelt_retailer_rest_failed',
				__( 'Operation failed.', 'pixelgradelt_retailer' ),
				[ 'status' => HTTP::NOT_ACCEPTABLE ]
			);
		}

		$request->set_param( 'context', 'edit' );
		$response = $this->prepare_item_for_response( $composition_data, $request );
		$response = rest_ensure_response( $response );
		$response->set_status( HTTP::CREATED );

		return $response;
	}

	/**
	 * Check if a given request has access to view the resource.
	 *
	 * @since 0.15.0
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 *
	 * @return true|WP_Error True if the request has read access, WP_Error object otherwise.
	 */
	public function get_item_permissions_check( $request ) {
		if ( ! current_user_can( Capabilities::VIEW_COMPOSITION, $request->get_param( 'compositionid' ) ) ) {
			return new WP_Error(
				'rest_cannot_read',
				esc_html__( 'Sorry, you are not allowed to view the requested composition.', 'pixelgradelt_retailer' ),
				[ 'status' => rest_authorization_required_code() ]
			);
		}

		return true;
	}

	/**
	 * Retrieve a single composition.
	 *
	 * @since 0.15.0
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 *
	 * @return WP_Error|WP_REST_Response
	 */
	public function get_item( $request ) {
		$composition_data = $this->composition_manager->get_composition_data_by(
			[ 'hashid' => $request->get_param( 'compositionid' ) ]
		);
		if ( empty( $composition_data ) ) {
			return new WP_Error(
				'pixelgradelt_retailer_rest_invalid_id',
				__( 'Invalid composition ID.', 'pixelgradelt_retailer' ),
				[ 'status' => HTTP::NOT_FOUND ]
			);
		}

		$data = $this->prepare_item_for_response( $composition_data, $request );

		return rest_ensure_response( $data );
	}

	/**
	 * Check if a given request has access to edit the resource.
	 *
	 * @since 0.15.0
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 *
	 * @return true|WP_Error True if the request has read access, WP_Error object otherwise.
	 */
	public function edit_item_permissions_check( $request ) {
		if ( ! current_user_can( Capabilities::EDIT_COMPOSITION, $request->get_param( 'compositionid' ) ) ) {
			return new WP_Error(
				'rest_cannot_read',
				esc_html__( 'Sorry, you are not allowed to edit the requested composition.', 'pixelgradelt_retailer' ),
				[ 'status' => rest_authorization_required_code() ]
			);
		}

		return true;
	}

	/**
	 * Edit a single composition.
	 *
	 * @since 0.15.0
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 *
	 * @return WP_Error|WP_REST_Response WP_Error on failure or the updated composition details on success.
	 */
	public function edit_item( $request ) {
		$composition_data = $this->composition_manager->get_composition_data_by(
			[ 'hashid' => $request->get_param( 'compositionid' ) ]
		);
		if ( empty( $composition_data ) ) {
			return new WP_Error(
				'pixelgradelt_retailer_rest_invalid_id',
				__( 'Invalid composition ID.', 'pixelgradelt_retailer' ),
				[ 'status' => HTTP::NOT_FOUND ]
			);
		}

		$args = [
			'post_id'                         => $composition_data['id'],
			'name'                            => $request->get_param( 'name' ),
			'keywords'                        => $request->get_param( 'keywords' ),
			'user_ids'                        => $request->get_param( 'userids' ),
			'required_purchased_solution_ids' => $request->get_param( 'required_purchased_solution_ids' ),
		];

		// Do the actual updating (since we already checked for existence, it is sure to be an update).
		$result = $this->composition_manager->save_composition( $args, true );
		if ( $result !== $composition_data['id'] ) {
			return new WP_Error(
				'pixelgradelt_retailer_rest_failed',
				__( 'Operation failed.', 'pixelgradelt_retailer' ),
				[ 'status' => HTTP::NOT_ACCEPTABLE ]
			);
		}

		$update_composition_data = $this->composition_manager->get_composition_id_data( $composition_data['id'] );

		$data = $this->prepare_item_for_response( $update_composition_data, $request );

		return rest_ensure_response( $data );
	}

	/**
	 * Check if a given request has access to delete a resource.
	 *
	 * @since 0.15.0
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 *
	 * @return true|WP_Error True if the request has access to delete the item, WP_Error object otherwise.
	 */
	public function delete_item_permissions_check( $request ) {
		if ( ! current_user_can( Capabilities::EDIT_COMPOSITION, $request->get_param( 'compositionid' ) ) ) {
			return new WP_Error(
				'rest_cannot_read',
				esc_html__( 'Sorry, you are not allowed to edit the requested composition.', 'pixelgradelt_retailer' ),
				[ 'status' => rest_authorization_required_code() ]
			);
		}

		return true;
	}

	/**
	 * Delete a composition.
	 *
	 * @since 0.15.0
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 *
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function delete_item( $request ) {
		$composition_data = $this->composition_manager->get_composition_data_by(
			[ 'hashid' => $request->get_param( 'compositionid' ) ]
		);
		if ( empty( $composition_data ) ) {
			return new WP_Error(
				'pixelgradelt_retailer_rest_invalid_id',
				__( 'Invalid composition ID.', 'pixelgradelt_retailer' ),
				[ 'status' => HTTP::NOT_FOUND ]
			);
		}

		// Trash the composition post to allow for revert, if the trash is active.
		// Otherwise, the post is permanently deleted.
		wp_trash_post( $composition_data['id'] );

		$request->set_param( 'context', 'edit' );
		$previous = $this->prepare_item_for_response( $composition_data, $request );

		$response = new WP_REST_Response();
		$response->set_data(
			[
				'deleted'  => true,
				'previous' => $previous->get_data(),
			]
		);

		return $response;
	}

	/**
	 * Prepare a single composition output for response.
	 *
	 * @since 0.15.0
	 *
	 * @param array           $item    Composition data.
	 * @param WP_REST_Request $request Request instance.
	 *
	 * @return WP_REST_Response Response instance.
	 */
	public function prepare_item_for_response( $item, $request ): WP_REST_Response {

		$data = [
			'id'     => $item['id'],
			'name'   => $item['name'] ?? '',
			'status' => $item['status'] ?? '',
			'hashid' => $item['hashid'] ?? '',

			'keywords' => $item['keywords'] ?? [],

			'users'   => $item['users'] ?? [],
			'userids' => $this->composition_manager->get_post_composition_user_ids( $item['id'] ),

			'required_solutions'               => $item['required_solutions'] ?? [],
			'required_purchased_solutions'     => $item['required_purchased_solutions'] ?? [],
			'required_purchased_solutions_ids' => $this->composition_manager->get_post_composition_required_purchased_solutions_ids( $item['id'] ),
			'required_manual_solutions'        => $item['required_manual_solutions'] ?? [],
			'composer_require'                 => $item['composer_require'] ?? [],

			'editLink' => get_edit_post_link( $item['id'], $request['context'] ),
		];


		//		$data['requiredPackages'] = $this->prepare_required_packages_for_response( $item, $request );
		//		$data['excludedPackages'] = $this->prepare_excluded_packages_for_response( $item, $request );

		$data = $this->filter_response_by_context( $data, $request['context'] );

		return rest_ensure_response( $data );
	}

	/**
	 * Get the composition schema, conforming to JSON Schema.
	 *
	 * @since 0.15.0
	 *
	 * @return array
	 */
	public function get_item_schema(): array {
		return [
			'$schema'    => 'http://json-schema.org/draft-04/schema#',
			'title'      => 'composition',
			'type'       => 'object',
			'properties' => [
				'id'       => [
					'description' => esc_html__( 'The composition\'s post ID.', 'pixelgradelt_retailer' ),
					'type'        => 'integer',
					'context'     => [ 'edit' ],
					'readonly'    => true,
				],
				'name'     => [
					'description' => esc_html__( 'The composition\'s name/title.', 'pixelgradelt_retailer' ),
					'type'        => 'string',
					'context'     => [ 'view', 'edit', 'embed' ],
					'readonly'    => false,
				],
				'status'   => [
					'description' => __( 'The composition\'s status.', 'pixelgradelt_retailer' ),
					'type'        => 'string',
					'context'     => [ 'view', 'edit', 'embed' ],
					'readonly'    => true,
				],
				'hashid'   => [
					'description' => esc_html__( 'The composition\'s hashid.', 'pixelgradelt_retailer' ),
					'type'        => 'string',
					'context'     => [ 'view', 'edit', 'embed' ],
					'readonly'    => true,
				],
				'keywords' => [
					'description' => esc_html__( 'The composition\'s keywords.', 'pixelgradelt_retailer' ),
					'type'        => 'array',
					'items'       => [
						'type' => 'string',
					],
					'context'     => [ 'view', 'edit' ],
					'readonly'    => false,
				],

				'users'   => [
					'description' => esc_html__( 'The composition users/owners details.', 'pixelgradelt_retailer' ),
					'type'        => 'array',
					'items'       => [
						'type'       => 'object',
						'readonly'   => true,
						'properties' => [
							'status'   => [
								'description' => esc_html__( 'The user status (valid/invalid).', 'pixelgradelt_retailer' ),
								'type'        => 'string',
								'context'     => [ 'view', 'edit', 'embed' ],
								'readonly'    => true,
							],
							'id'       => [
								'description' => esc_html__( 'The user ID.', 'pixelgradelt_retailer' ),
								'type'        => 'integer',
								'context'     => [ 'view', 'edit', 'embed' ],
								'readonly'    => true,
							],
							'email'    => [
								'description' => esc_html__( 'The user email.', 'pixelgradelt_retailer' ),
								'type'        => 'string',
								'context'     => [ 'view', 'edit', 'embed' ],
								'readonly'    => true,
							],
							'username' => [
								'description' => esc_html__( 'The user username.', 'pixelgradelt_retailer' ),
								'type'        => 'string',
								'context'     => [ 'view', 'edit', 'embed' ],
								'readonly'    => true,
							],
						],
					],
					'context'     => [ 'view', 'edit', 'embed' ],
					'readonly'    => true,
				],
				'userids' => [
					'description' => esc_html__( 'The composition\'s users/owners IDs.', 'pixelgradelt_retailer' ),
					'type'        => 'array',
					'items'       => [
						'type' => 'integer',
					],
					'context'     => [ 'edit', ],
					'readonly'    => false,
				],

				'required_solutions' => [
					'description' => esc_html__( 'The composition\'s final list of required solutions.', 'pixelgradelt_retailer' ),
					'type'        => 'array',
					'items'       => [
						'type'       => 'object',
						'readonly'   => true,
						'properties' => [
							'type'            => [
								'description' => esc_html__( 'The required solution\'s type.', 'pixelgradelt_retailer' ),
								'type'        => 'string',
								'context'     => [ 'view', 'edit', 'embed' ],
								'readonly'    => true,
							],
							'slug'            => [
								'description' => esc_html__( 'The required solution\'s slug.', 'pixelgradelt_retailer' ),
								'type'        => 'string',
								'context'     => [ 'view', 'edit', 'embed' ],
								'readonly'    => true,
							],
							'managed_post_id' => [
								'description' => esc_html__( 'The required solution\'s post ID.', 'pixelgradelt_retailer' ),
								'type'        => 'integer',
								'context'     => [ 'view', 'edit', 'embed' ],
								'readonly'    => true,
							],
							'context'         => [
								'description' => esc_html__( 'The required solution\'s context details.', 'pixelgradelt_retailer' ),
								'type'        => 'object',
								'context'     => [ 'view', 'edit', ],
								'readonly'    => true,
							],
						],
					],
					'context'     => [ 'view', 'edit', 'embed' ],
					'readonly'    => true,
				],

				'required_purchased_solutions'    => [
					'description' => esc_html__( 'The composition\'s list of purchased required solutions.', 'pixelgradelt_retailer' ),
					'type'        => 'array',
					'items'       => [
						'type'       => 'object',
						'readonly'   => true,
						'properties' => [
							'type'                  => [
								'description' => esc_html__( 'The required solution\'s type.', 'pixelgradelt_retailer' ),
								'type'        => 'string',
								'context'     => [ 'view', 'edit', ],
								'readonly'    => true,
							],
							'purchased_solution_id' => [
								'description' => esc_html__( 'The required purchased solution\'s ID.', 'pixelgradelt_retailer' ),
								'type'        => 'integer',
								'context'     => [ 'view', 'edit', ],
								'readonly'    => true,
							],
							'slug'                  => [
								'description' => esc_html__( 'The required solution\'s slug.', 'pixelgradelt_retailer' ),
								'type'        => 'string',
								'context'     => [ 'view', 'edit', ],
								'readonly'    => true,
							],
							'managed_post_id'       => [
								'description' => esc_html__( 'The required solution\'s post ID.', 'pixelgradelt_retailer' ),
								'type'        => 'integer',
								'context'     => [ 'view', 'edit', ],
								'readonly'    => true,
							],
							'context'               => [
								'description' => esc_html__( 'The required solution\'s context details.', 'pixelgradelt_retailer' ),
								'type'        => 'object',
								'context'     => [ 'view', 'edit', ],
								'readonly'    => true,
							],
						],
					],
					'context'     => [ 'view', 'edit' ],
					'readonly'    => true,
				],
				'required_purchased_solution_ids' => [
					'description' => esc_html__( 'The composition\'s list of purchased required solutions IDs.', 'pixelgradelt_retailer' ),
					'type'        => 'array',
					'items'       => [
						'type' => 'integer',
					],
					'context'     => [ 'edit', ],
					'readonly'    => false,
				],

				'required_manual_solutions' => [
					'description' => esc_html__( 'The composition\'s list of manually required solutions.', 'pixelgradelt_retailer' ),
					'type'        => 'array',
					'items'       => [
						'type'       => 'object',
						'readonly'   => true,
						'properties' => [
							'type'            => [
								'description' => esc_html__( 'The required solution\'s type.', 'pixelgradelt_retailer' ),
								'type'        => 'string',
								'context'     => [ 'view', 'edit', ],
								'readonly'    => true,
							],
							'slug'            => [
								'description' => esc_html__( 'The required solution\'s slug.', 'pixelgradelt_retailer' ),
								'type'        => 'string',
								'context'     => [ 'view', 'edit', ],
								'readonly'    => true,
							],
							'managed_post_id' => [
								'description' => esc_html__( 'The required solution\'s post ID.', 'pixelgradelt_retailer' ),
								'type'        => 'integer',
								'context'     => [ 'view', 'edit', ],
								'readonly'    => true,
							],
							'context'         => [
								'description' => esc_html__( 'The required solution\'s context details.', 'pixelgradelt_retailer' ),
								'type'        => 'object',
								'context'     => [ 'view', 'edit', ],
								'readonly'    => true,
							],
						],
					],
					'context'     => [ 'view', 'edit', ],
					'readonly'    => true,
				],

				'composer_require' => [
					'description' => esc_html__( 'The composition\'s Composer require list.', 'pixelgradelt_retailer' ),
					'type'        => 'object',
					'context'     => [ 'view', 'edit', ],
					'readonly'    => true,
				],

				'editLink' => [
					'description' => esc_html__( 'The package post edit link.', 'pixelgradelt_retailer' ),
					'type'        => 'string',
					'format'      => 'uri',
					'context'     => [ 'view', 'edit' ],
					'readonly'    => true,
				],
			],
		];
	}

	/**
	 * Check if a given request has access to encrypt composition LT details.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 *
	 * @return true|WP_Error True if the request has read access, WP_Error object otherwise.
	 */
	public function encrypt_ltdetails_permissions_check( WP_REST_Request $request ) {
		if ( ! current_user_can( Capabilities::VIEW_SOLUTIONS ) ) {
			return new WP_Error(
				'rest_cannot_read',
				esc_html__( 'Sorry, you are not allowed to encrypt composition LT details.', 'pixelgradelt_retailer' ),
				[ 'status' => rest_authorization_required_code() ]
			);
		}

		return true;
	}

	/**
	 * Check if a given request has access to check resources details.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 *
	 * @return true|WP_Error True if the request has read access, WP_Error object otherwise.
	 */
	public function check_items_details_permissions_check( WP_REST_Request $request ) {
		if ( ! current_user_can( Capabilities::VIEW_SOLUTIONS ) ) {
			return new WP_Error(
				'rest_cannot_read',
				esc_html__( 'Sorry, you are not allowed to check composition LT details.', 'pixelgradelt_retailer' ),
				[ 'status' => rest_authorization_required_code() ]
			);
		}

		return true;
	}

	/**
	 * Check if a given request has access to update resources details.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 *
	 * @return true|WP_Error True if the request has read access, WP_Error object otherwise.
	 */
	public function update_items_details_permissions_check( WP_REST_Request $request ) {
		if ( ! current_user_can( Capabilities::VIEW_SOLUTIONS ) ) {
			return new WP_Error(
				'rest_cannot_read',
				esc_html__( 'Sorry, you are not allowed to update composition details.', 'pixelgradelt_retailer' ),
				[ 'status' => rest_authorization_required_code() ]
			);
		}

		return true;
	}

	/**
	 * Encrypt a set of composition LT details.
	 *
	 * @since 0.10.0
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 *
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function encrypt_ltdetails( WP_REST_Request $request ) {
		// Gather the composition's LT details.
		$details = [
			'userids'       => $request['userids'],
			'compositionid' => $request['compositionid'],
			'extra'         => $request['extra'],
		];

		/**
		 * Filter the composition's LT details before encryption.
		 *
		 * @since 0.10.0
		 *
		 * @see   CompositionsController::encrypt_ltdetails()
		 *
		 * @param bool  $valid       Whether the composition LT details are valid.
		 * @param array $details     The composition's LT details as decrypted from the composition data.
		 * @param array $composition The full composition data.
		 */
		$details = apply_filters( 'pixelgradelt_retailer/before_encrypt_composition_ltdetails', $details, $request );

		try {
			// Validate the received details.
			// In case of invalid details, exceptions are thrown.
			$this->validate_ltdetails( $details );
		} catch ( RestException $e ) {
			return new WP_Error(
				'rest_invalid_composition_ltdetails',
				$e->getMessage(),
				[ 'status' => $e->getStatusCode(), ]
			);
		}

		// Now encrypt them.
		try {
			$encrypted_details = $this->crypter->encrypt( json_encode( $details ) );
		} catch ( CrypterEnvironmentIsBrokenException $e ) {
			return new WP_Error(
				'rest_unable_to_encrypt',
				esc_html__( 'We could not encrypt. Please contact the administrator and let them know that something is wrong. Thanks in advance!', 'pixelgradelt_retailer' ),
				[
					'status'  => HTTP::INTERNAL_SERVER_ERROR,
					'details' => $e->getMessage(),
				]
			);
		}

		// Return the encrypted composition LT details (a string).
		return rest_ensure_response( $encrypted_details );
	}

	/**
	 * Check a set of composition LT details.
	 *
	 * @since 0.10.0
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 *
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function check_ltdetails( WP_REST_Request $request ) {
		/**
		 * Validate the encrypted LT details in the composition.
		 */
		try {
			$details = $this->decrypt_ltdetails( $request['ltdetails'] );
		} catch ( CrypterBadFormatException | CrypterWrongKeyOrModifiedCiphertextException | RestException $e ) {
			return new WP_Error(
				'rest_invalid_ltdetails',
				$e->getMessage(),
				[ 'status' => HTTP::NOT_ACCEPTABLE, ]
			);
		} catch ( CrypterEnvironmentIsBrokenException $e ) {
			return new WP_Error(
				'rest_unable_to_encrypt',
				esc_html__( 'We could not decrypt. Please contact the administrator and let them know that something is wrong. Thanks in advance!', 'pixelgradelt_retailer' ),
				[
					'status'  => HTTP::INTERNAL_SERVER_ERROR,
					'details' => $e->getMessage(),
				]
			);
		}

		try {
			// In case of invalid LT details, exceptions are thrown.
			$this->validate_ltdetails( $details );
		} catch ( RestException $e ) {
			return new WP_Error(
				'rest_invalid_ltdetails',
				$e->getMessage(),
				[ 'status' => $e->getStatusCode(), ]
			);
		}

		// Return that all is OK.
		return rest_ensure_response( [] );
	}

	/**
	 * Validate the composition's LT details.
	 *
	 * Allow others to do further validations.
	 *
	 * @since 0.10.0
	 *
	 * @param array $details
	 *
	 * @throws RestException
	 * @return bool True on valid. Exceptions are thrown on invalid.
	 */
	protected function validate_ltdetails( array $details ): bool {

		if ( ! isset( $details['userids'] ) ) {
			throw RestException::forMissingCompositionLTDetails();
		}

		// Check that AT LEAST a user ID actually belongs to a valid user.
		$valid_users = array_filter( $details['userids'], function ( $userid ) {
			return false !== get_user_by( 'id', $userid );
		} );
		if ( empty( $valid_users ) ) {
			throw RestException::forUserNotFound();
		}

		/**
		 * Filter the validation of the composition's LT details.
		 *
		 * @since 0.10.0
		 *
		 * @see   CompositionsController::validate_ltdetails()
		 *
		 * Return true if the composition details are valid, or a WP_Error in case we should reject them.
		 *
		 * @param bool  $valid   Whether the composition LT details are valid.
		 * @param array $details The composition details as decrypted from the composition data.
		 */
		$valid = apply_filters( 'pixelgradelt_retailer/validate_composition_ltdetails', true, $details );
		if ( is_wp_error( $valid ) ) {
			$message = esc_html__( 'Third-party composition\'s LT details checks have found them invalid. Here is what happened: ', 'pixelgradelt_retailer' ) . PHP_EOL;
			$message .= implode( ' ; ' . PHP_EOL, $valid->get_error_messages() );

			throw RestException::forInvalidCompositionLTDetails( $message );
		} elseif ( true !== $valid ) {
			throw RestException::forInvalidCompositionLTDetails();
		}

		return true;
	}

	/**
	 * Determine what details should be updated in the received composition.
	 *
	 * First, the received composition LT details and composition will be checked.
	 *
	 * @since 0.10.0
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 *
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function instructions_to_update_composition( WP_REST_Request $request ) {
		$composition = $request['composer'];
		// Make sure we are dealing with an associative array.
		if ( is_object( $composition ) ) {
			$composition = $this->objectToArrayRecursive( $composition );
		}

		/* ==============================
		 * First, validate the received composition's schema.
		 */
		try {
			// Validate the composition according to composer-schema.json rules.
			$this->validate_schema( $this->standardize_to_object( $composition ) );
		} catch ( JsonValidationException $e ) {
			return new WP_Error(
				'rest_json_invalid',
				esc_html__( 'Could not validate the received composition against the Composer JSON schema.', 'pixelgradelt_retailer' ),
				[
					'status'  => HTTP::NOT_ACCEPTABLE,
					'details' => $e->getErrors(),
				]
			);
		}

		/* ==============================
		 * Second, decrypt and validate the LT details.
		 */
		if ( empty( $composition['extra'][ self::LTDETAILS_KEY ] ) ) {
			return new WP_Error(
				'rest_missing_composition_ltdetails',
				esc_html__( 'The composition is missing the encrypted LT details.', 'pixelgradelt_retailer' ),
				[ 'status' => HTTP::NOT_ACCEPTABLE, ]
			);
		}
		try {
			$composition_ltdetails = $this->decrypt_ltdetails( $composition['extra'][ self::LTDETAILS_KEY ] );
		} catch ( CrypterBadFormatException | CrypterWrongKeyOrModifiedCiphertextException | RestException $e ) {
			return new WP_Error(
				'rest_invalid_composition_ltdetails',
				$e->getMessage(),
				[ 'status' => $e->getStatusCode(), ]
			);
		} catch ( CrypterEnvironmentIsBrokenException $e ) {
			return new WP_Error(
				'rest_unable_to_encrypt',
				esc_html__( 'We could not decrypt. Please contact the administrator and let them know that something is wrong. Thanks in advance!', 'pixelgradelt_retailer' ),
				[
					'status'  => HTTP::INTERNAL_SERVER_ERROR,
					'details' => $e->getMessage(),
				]
			);
		}

		try {
			// In case of invalid composition LT details, exceptions are thrown.
			$this->validate_ltdetails( $composition_ltdetails );
		} catch ( RestException $e ) {
			return new WP_Error(
				'rest_invalid_composition_ltdetails',
				$e->getMessage(),
				[ 'status' => $e->getStatusCode(), ]
			);
		}

		/* ==============================
		 * If we have made it thus far, the received composition is OK.
		 *
		 * Proceed to determine if something needs updating in it.
		 */

		/**
		 * Provide new composition details that should be updated.
		 *
		 * @since 0.10.0
		 *
		 * @see   CompositionsController::instructions_to_update_composition()
		 *
		 * @param bool|array $instructions_to_update The instructions to update the composition by.
		 *                                           false if we should reject the request and error out.
		 *                                           An empty array if we should leave the composition unchanged.
		 * @param array      $composition_ltdetails  The decrypted composition LT details, already checked.
		 * @param array      $composition            The full composition data.
		 */
		$instructions_to_update = apply_filters( 'pixelgradelt_retailer/instructions_to_update_composition', [], $composition_ltdetails, $composition );
		if ( is_wp_error( $instructions_to_update ) ) {
			$message = esc_html__( 'Your attempt to determine what to update in the composition was rejected. Here is what happened: ', 'pixelgradelt_retailer' ) . PHP_EOL;
			$message .= implode( ' ; ' . PHP_EOL, $instructions_to_update->get_error_messages() );

			return new WP_Error(
				'rest_rejected',
				$message,
				[ 'status' => HTTP::NOT_ACCEPTABLE, ]
			);
		} elseif ( false === $instructions_to_update ) {
			return new WP_Error(
				'rest_rejected',
				esc_html__( 'Your attempt to determine what to update in the composition was rejected.', 'pixelgradelt_retailer' ),
				[ 'status' => HTTP::NOT_ACCEPTABLE, ]
			);
		}

		if ( ! is_array( $instructions_to_update ) || empty( $instructions_to_update ) ) {
			// There is nothing to update. Respond accordingly.
			$response = rest_ensure_response( [] );
			$response->set_status( HTTP::NO_CONTENT );

			return $response;
		}

		return rest_ensure_response( $instructions_to_update );
	}

	/**
	 * Decrypt the encrypted composition LT details.
	 *
	 * @since 0.10.0
	 *
	 * @param string $encrypted_details
	 *
	 * @throws CrypterBadFormatException
	 * @throws CrypterEnvironmentIsBrokenException
	 * @throws CrypterWrongKeyOrModifiedCiphertextException
	 * @throws RestException
	 * @return array
	 */
	protected function decrypt_ltdetails( string $encrypted_details ): array {
		$details = json_decode( $this->crypter->decrypt( $encrypted_details ), true );

		if ( null === $details ) {
			throw RestException::forInvalidCompositionLTDetails();
		}

		return $details;
	}

	/**
	 * Order the composition data to a standard to ensure hashes can be trusted.
	 *
	 * @since 0.10.0
	 *
	 * @param array $composition The current composition details.
	 *
	 * @return array The updated composition details.
	 */
	protected function standard_order( array $composition ): array {
		$targetKeys = [
			'require',
			'require-dev',
			'conflict',
			'replace',
			'provide',
			'repositories',
			'extra',
		];
		foreach ( $targetKeys as $key ) {
			if ( ! empty( $composition[ $key ] ) && is_array( $composition[ $key ] ) ) {
				ksort( $composition[ $key ] );
			}
		}

		return $composition;
	}

	/**
	 * Make sure that the composition details have the right type (especially empty ones).
	 *
	 * @since 0.10.0
	 *
	 * @param array $composition The current composition details.
	 *
	 * @return object The standardized composition object.
	 */
	protected function standardize_to_object( array $composition ): object {
		// Ensure a standard order.
		$composition = $this->standard_order( $composition );

		// Convert to object.
		$compositionObject = $this->arrayToObjectRecursive( $composition );

		// Enforce empty properties that should be objects, not empty arrays.
		$objectsKeys = [
			'require',
			'require-dev',
			'conflict',
			'extra',
			'provide',
			'replace',
			'suggest',
			'config',
			'autoload',
			'autoload-dev',
			'scripts',
			'scripts-descriptions',
			'support',
		];
		foreach ( $objectsKeys as $key ) {
			if ( isset( $compositionObject->$key ) && empty( $compositionObject->$key ) ) {
				$compositionObject->$key = new \stdClass();
			}
		}

		/**
		 * Filter the standardized composition object.
		 *
		 * @since 0.10.0
		 *
		 * @see   CompositionsController::standardize_to_object()
		 *
		 * @param object $compositionObject The standardized composition object.
		 * @param array  $composition       The initial composition.
		 */
		return apply_filters( 'pixelgradelt_retailer/composition_standardize_to_object', $compositionObject, $composition );
	}

	/**
	 * Recursively cast an associative array to an object
	 *
	 * @since 0.10.0
	 *
	 * @param array $array
	 *
	 * @return object
	 */
	protected function arrayToObjectRecursive( array $array ): object {
		$json = json_encode( $array );
		if ( json_last_error() !== \JSON_ERROR_NONE ) {
			$message = esc_html__( 'Unable to encode schema array as JSON', 'pixelgradelt_retailer' );
			if ( function_exists( 'json_last_error_msg' ) ) {
				$message .= ': ' . json_last_error_msg();
			}
			throw new InvalidArgumentException( $message );
		}

		return (object) json_decode( $json );
	}

	/**
	 * Recursively cast an object to an associative array.
	 *
	 * @since 0.10.0
	 *
	 * @param object $object
	 *
	 * @return array
	 */
	protected function objectToArrayRecursive( object $object ): array {
		$json = json_encode( $object );
		if ( json_last_error() !== \JSON_ERROR_NONE ) {
			$message = esc_html__( 'Unable to encode schema array as JSON', 'pixelgradelt_retailer' );
			if ( function_exists( 'json_last_error_msg' ) ) {
				$message .= ': ' . json_last_error_msg();
			}
			throw new InvalidArgumentException( $message );
		}

		return (array) json_decode( $json, true );
	}

	/**
	 * Validate the given composition against the composer-schema.json rules.
	 *
	 * @since 0.10.0
	 *
	 * @param object $composition The current composition details in object.
	 *
	 * @throws JsonValidationException
	 * @return bool Success.
	 */
	protected function validate_schema( object $composition ): bool {
		$validator       = new Validator();
		$composer_schema = $this->get_composer_schema();
		if ( empty( $composer_schema ) ) {
			// If we couldn't read the schema, let things pass.
			return true;
		}

		$validator->check( $composition, $composer_schema );
		if ( ! $validator->isValid() ) {
			$errors = [];
			foreach ( (array) $validator->getErrors() as $error ) {
				$errors[] = ( $error['property'] ? $error['property'] . ' : ' : '' ) . $error['message'];
			}
			throw new JsonValidationException( esc_html__( 'The composition does not match the expected JSON schema', 'pixelgradelt_retailer' ), $errors );
		}

		return true;
	}

	/**
	 * Get the Composer JSON schema.
	 *
	 * @since 0.10.0
	 *
	 * @return array
	 */
	public function get_composer_schema(): array {
		$schema = [];

		try {
			$schemaJson = new JsonFile( plugin()->get_path( 'vendor/composer/composer/res/composer-schema.json' ) );
			$schema     = $schemaJson->read();
		} catch ( \Exception $e ) {
			// Do nothing.
		}

		return $schema;
	}
}
