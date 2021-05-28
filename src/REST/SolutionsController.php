<?php
/**
 * Packages REST controller.
 *
 * @since   1.0.0
 * @license GPL-2.0-or-later
 * @package PixelgradeLT
 */

declare ( strict_types=1 );

namespace PixelgradeLT\Retailer\REST;

use PixelgradeLT\Retailer\Capabilities;
use PixelgradeLT\Retailer\Package;
use PixelgradeLT\Retailer\Repository\ProcessedSolutions;
use PixelgradeLT\Retailer\Repository\SolutionRepository;
use PixelgradeLT\Retailer\SolutionType\SolutionTypes;
use PixelgradeLT\Retailer\Transformer\ComposerSolutionTransformer;
use WP_Error;
use WP_REST_Controller;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * Solutions REST controller class.
 *
 * @since 1.0.0
 */
class SolutionsController extends WP_REST_Controller {
	/**
	 * Package slug pattern.
	 *
	 * @var string
	 */
	const SLUG_PATTERN = '[^.\/]+(?:\/[^.\/]+)?';

	/**
	 * Composer package transformer.
	 *
	 * @var ComposerSolutionTransformer
	 */
	protected ComposerSolutionTransformer $composer_transformer;

	/**
	 * Solution repository.
	 *
	 * @var SolutionRepository
	 */
	protected SolutionRepository $repository;

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 *
	 * @param string                      $namespace            The namespace for this controller's route.
	 * @param string                      $rest_base            The base of this controller's route.
	 * @param SolutionRepository          $repository           Solution repository.
	 * @param ComposerSolutionTransformer $composer_transformer Package transformer.
	 */
	public function __construct(
		string $namespace,
		string $rest_base,
		SolutionRepository $repository,
		ComposerSolutionTransformer $composer_transformer
	) {
		$this->namespace            = $namespace;
		$this->rest_base            = $rest_base;
		$this->repository           = $repository;
		$this->composer_transformer = $composer_transformer;
	}

	/**
	 * Register the routes.
	 *
	 * @since 1.0.0
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
					'args'                => $this->get_collection_params(),
				],
				'schema' => [ $this, 'get_public_item_schema' ],
			]
		);

//		register_rest_route(
//			$this->namespace,
//			'/' . $this->rest_base . '/processed',
//			[
//				[
//					'methods'             => WP_REST_Server::READABLE,
//					'callback'            => [ $this, 'get_processed_items' ],
//					'permission_callback' => [ $this, 'get_items_permissions_check' ],
//					'args'                => $this->get_collection_params(),
//				],
//				'schema' => [ $this, 'get_public_item_schema' ],
//			]
//		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>[\d]+)',
			[
				'args'   => array(
					'id' => array(
						'description' => __( 'The solution post ID.', 'woocommerce' ),
						'type'        => 'integer',
					),
				),
				[
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_item' ],
					'permission_callback' => [ $this, 'get_item_permissions_check' ],
					'args'                => array(
						'context' => $this->get_context_param( array( 'default' => 'view' ) ),
					),
				],
				'schema' => [ $this, 'get_public_item_schema' ],
			]
		);
	}

	/**
	 * Check if a given request has access to view the resources.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 *
	 * @return true|WP_Error True if the request has read access, WP_Error object otherwise.
	 */
	public function get_items_permissions_check( $request ) {
		if ( ! current_user_can( Capabilities::VIEW_SOLUTIONS ) ) {
			return new WP_Error(
				'rest_cannot_read',
				esc_html__( 'Sorry, you are not allowed to view solutions.', 'pixelgradelt_retailer' ),
				[ 'status' => rest_authorization_required_code() ]
			);
		}

		return true;
	}

	/**
	 * Retrieve a collection of packages.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 *
	 * @return WP_Error|WP_REST_Response
	 */
	public function get_items( $request ) {
		$items = [];

		$repository = $this->repository->with_filter(
			function ( $package ) use ( $request ) {
				if ( ! empty( $request['type'] ) && ! in_array( $package->get_type(), $request['type'], true ) ) {
					return false;
				}

				if ( ! empty( $request['postId'] ) && $request['postId'] !== [0] && ! in_array( $package->get_managed_post_id(), $request['postId'] ) ) {
					return false;
				}

				if ( ! empty( $request['postSlug'] ) && ! in_array( $package->get_slug(), $request['postSlug'] ) ) {
					return false;
				}

				if ( ! empty( $request['packageName'] ) && ! in_array( $package->get_composer_package_name(), $request['packageName'] ) ) {
					return false;
				}

				return true;
			}
		);

		foreach ( $repository->all() as $slug => $package ) {
			$data    = $this->prepare_item_for_response( $package, $request );
			$items[] = $this->prepare_response_for_collection( $data );
		}

		return rest_ensure_response( $items );
	}

	/**
	 * Check if a given request has access to view the resource.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 *
	 * @return true|WP_Error True if the request has read access, WP_Error object otherwise.
	 */
	public function get_item_permissions_check( $request ) {
		if ( ! current_user_can( Capabilities::VIEW_SOLUTION, $request->get_param( 'id' ) ) ) {
			return new WP_Error(
				'rest_cannot_read',
				esc_html__( 'Sorry, you are not allowed to view the requested solution.', 'pixelgradelt_retailer' ),
				[ 'status' => rest_authorization_required_code() ]
			);
		}

		return true;
	}

	/**
	 * Retrieve and then process a collection of packages.
	 *
	 * The processing is mainly applying the exclude solutions logic.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 *
	 * @return WP_Error|WP_REST_Response
	 */
	public function get_processed_items( $request ) {
		$items = [];

		$filtered_repository = $this->repository->with_filter(
			function ( $package ) use ( $request ) {
				if ( ! empty( $request['type'] ) && ! in_array( $package->get_type(), $request['type'], true ) ) {
					return false;
				}

				if ( ! empty( $request['postId'] ) && ! in_array( $package->get_managed_post_id(), $request['postId'] ) ) {
					return false;
				}

				if ( ! empty( $request['postSlug'] ) && ! in_array( $package->get_slug(), $request['postSlug'] ) ) {
					return false;
				}

				if ( ! empty( $request['packageName'] ) && ! in_array( $package->get_composer_package_name(), $request['packageName'] ) ) {
					return false;
				}

				return true;
			}
		);

		// Make a processed repository out of the filtered repository.
		$processed_repository = new ProcessedSolutions( $filtered_repository, $this->repository->get_factory(), $this->repository->get_solution_manager() );

		// Prepare the processed solutions for response.
		foreach ( $processed_repository->all() as $slug => $package ) {
			$data    = $this->prepare_item_for_response( $package, $request );
			$items[] = $this->prepare_response_for_collection( $data );
		}

		return rest_ensure_response( $items );
	}

	/**
	 * Retrieve a single package.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 *
	 * @return WP_Error|WP_REST_Response
	 */
	public function get_item( $request ) {
		$package = $this->repository->first_where( [ 'managed_post_id' => $request->get_param( 'id' ) ] );
		if ( empty( $package ) ) {
			return new WP_Error( 'pixelgradelt_retailer_rest_invalid_id', __( 'Invalid solution post ID.', 'pixelgradelt_retailer' ), array( 'status' => 404 ) );
		}

		$data = $this->prepare_item_for_response( $package, $request );

		return rest_ensure_response( $data );
	}

	/**
	 * Retrieve the query parameters for collections of packages.
	 *
	 * @since 1.0.0
	 *
	 * @return array
	 */
	public function get_collection_params(): array {
		$params = [
			'context' => $this->get_context_param( [ 'default' => 'view' ] ),
		];

		$params['postId'] = [
			'description'       => esc_html__( 'Limit results to solutions by one or more (managed) post IDs.', 'pixelgradelt_retailer' ),
			'type'              => 'array',
			'items'             => [
				'type' => 'integer',
			],
			'default'           => [],
			'sanitize_callback' => 'wp_parse_id_list',
		];

		$params['postSlug'] = [
			'description'       => esc_html__( 'Limit results to solutions by one or more (managed) post slugs.', 'pixelgradelt_retailer' ),
			'type'              => 'array',
			'items'             => [
				'type' => 'string',
			],
			'default'           => [],
			'sanitize_callback' => 'wp_parse_slug_list',
		];

		$params['packageName'] = [
			'description'       => esc_html__( 'Limit results to solutions by one or more Composer package names (including the vendor). Use the "postSlug" parameter if you want to provide only the name, without the vendor.', 'pixelgradelt_retailer' ),
			'type'              => 'array',
			'items'             => [
				'type' => 'string',
			],
			'default'           => [],
			'sanitize_callback' => 'wp_parse_slug_list',
		];

		$params['type'] = [
			'description'       => esc_html__( 'Limit results to solutions of one or more types.', 'pixelgradelt_retailer' ),
			'type'              => 'array',
			'items'             => [
				'type' => 'string',
			],
			'default'           => [
				SolutionTypes::BASIC,
			],
			'sanitize_callback' => 'wp_parse_slug_list',
		];

		return $params;
	}

	/**
	 * Prepare a single package output for response.
	 *
	 * @since 1.0.0
	 *
	 * @param Package         $item    Package instance.
	 * @param WP_REST_Request $request Request instance.
	 *
	 * @return WP_REST_Response Response instance.
	 */
	public function prepare_item_for_response( $item, $request ): WP_REST_Response {
		$composer = $this->composer_transformer->transform( $item );

		$id = $item->get_slug();

		$data = [
			'id'          => $id,
			'slug'        => $item->get_slug(),
			'name'        => $item->get_name(),
			'description' => $item->get_description(),
			'homepage'    => $item->get_homepage(),
			'authors'     => $item->get_authors(),
			'type'        => $item->get_type(),
			'visibility'  => $item->get_visibility(),
		];

		$data['composer'] = [
			'name' => $composer->get_name(),
			'type' => $composer->get_type(),
		];

		$data['releases']         = [];
		$data['requiredPackages'] = $this->prepare_required_packages_for_response( $item, $request );
		$data['excludedPackages'] = $this->prepare_excluded_packages_for_response( $item, $request );

		$data = $this->filter_response_by_context( $data, $request['context'] );

		return rest_ensure_response( $data );
	}

	/**
	 * Prepare package required packages for response.
	 *
	 * @param Package         $package Package instance.
	 * @param WP_REST_Request $request WP request instance.
	 *
	 * @return array
	 */
	protected function prepare_required_packages_for_response( Package $package, WP_REST_Request $request ): array {
		$requiredPackages = [];

		$requires = [];
		$requires += $package->get_required_solutions();
		$requires += $package->get_required_ltrecords_parts();
		foreach ( $requires as $requiredPackage ) {
			$package_name = $requiredPackage['composer_package_name'] . ':' . $requiredPackage['version_range'];
			if ( 'stable' !== $requiredPackage['stability'] ) {
				$package_name .= '@' . $requiredPackage['stability'];
			}

			$edit_link = '#';
			if ( ! empty( $requiredPackage['managed_post_id'] ) ) {
				$edit_link = get_edit_post_link( $requiredPackage['managed_post_id'] );
			}

			$requiredPackages[] = [
				'name'        => $requiredPackage['composer_package_name'],
				'version'     => $requiredPackage['version_range'],
				'stability'   => $requiredPackage['stability'],
				'editLink'    => $edit_link,
				'displayName' => $package_name,
			];
		}

		return array_values( $requiredPackages );
	}

	/**
	 * Prepare package replaced packages for response.
	 *
	 * @param Package         $package Package instance.
	 * @param WP_REST_Request $request WP request instance.
	 *
	 * @return array
	 */
	protected function prepare_excluded_packages_for_response( Package $package, WP_REST_Request $request ): array {
		$excludedPackages = [];

		foreach ( $package->get_excluded_solutions() as $replacedPackage ) {
			$package_name = $replacedPackage['composer_package_name'] . ':' . $replacedPackage['version_range'];
			if ( 'stable' !== $replacedPackage['stability'] ) {
				$package_name .= '@' . $replacedPackage['stability'];
			}

			$edit_link = '#';
			if ( ! empty( $replacedPackage['managed_post_id'] ) ) {
				$edit_link = get_edit_post_link( $replacedPackage['managed_post_id'] );
			}

			$excludedPackages[] = [
				'name'        => $replacedPackage['composer_package_name'],
				'version'     => $replacedPackage['version_range'],
				'stability'   => $replacedPackage['stability'],
				'editLink'    => $edit_link,
				'displayName' => $package_name,
			];
		}

		return array_values( $excludedPackages );
	}

	/**
	 * Get the package schema, conforming to JSON Schema.
	 *
	 * @since 1.0.0
	 *
	 * @return array
	 */
	public function get_item_schema() {
		return [
			'$schema'    => 'http://json-schema.org/draft-04/schema#',
			'title'      => 'package',
			'type'       => 'object',
			'properties' => [
				'authors'          => [
					'description' => esc_html__( 'The package authors details.', 'pixelgradelt_retailer' ),
					'type'        => 'array',
					'context'     => [ 'view', 'edit' ],
					'readonly'    => true,
				],
				'composer'         => [
					'description' => esc_html__( 'Package data formatted for Composer.', 'pixelgradelt_retailer' ),
					'type'        => 'object',
					'context'     => [ 'view', 'edit' ],
					'readonly'    => true,
					'properties'  => [
						'name' => [
							'description' => __( 'Composer package name.', 'pixelgradelt_retailer' ),
							'type'        => 'string',
							'context'     => [ 'view', 'edit' ],
							'readonly'    => true,
						],
						'type' => [
							'description' => __( 'Composer package type.', 'pixelgradelt_retailer' ),
							'type'        => 'string',
							'enum'        => [ 'wordpress-plugin', 'wordpress-theme' ],
							'context'     => [ 'view', 'edit' ],
							'readonly'    => true,
						],
					],
				],
				'description'      => [
					'description' => esc_html__( 'The package description.', 'pixelgradelt_retailer' ),
					'type'        => 'string',
					'context'     => [ 'view', 'edit', 'embed' ],
					'readonly'    => true,
				],
				'homepage'         => [
					'description' => esc_html__( 'The package URL.', 'pixelgradelt_retailer' ),
					'type'        => 'string',
					'format'      => 'uri',
					'context'     => [ 'view', 'edit' ],
					'readonly'    => true,
				],
				'name'             => [
					'description' => esc_html__( 'The name of the package.', 'pixelgradelt_retailer' ),
					'type'        => 'string',
					'context'     => [ 'view', 'edit', 'embed' ],
					'readonly'    => true,
				],
				'releases'         => [
					'description' => esc_html__( 'A list of package releases.', 'pixelgradelt_retailer' ),
					'type'        => 'array',
					'context'     => [ 'view', 'edit' ],
					'readonly'    => true,
					'items'       => [
						'type'       => 'object',
						'readonly'   => true,
						'properties' => [
							'url'     => [
								'description' => esc_html__( 'A URL to download the release.', 'pixelgradelt_retailer' ),
								'type'        => 'string',
								'format'      => 'uri',
								'readonly'    => true,
							],
							'version' => [
								'description' => esc_html__( 'The release version.', 'pixelgradelt_retailer' ),
								'type'        => 'string',
								'readonly'    => true,
							],
						],
					],
				],
				'requiredPackages' => [
					'description' => esc_html__( 'A list of required packages.', 'pixelgradelt_retailer' ),
					'type'        => 'array',
					'context'     => [ 'view', 'edit' ],
					'readonly'    => true,
					'items'       => [
						'type'       => 'object',
						'readonly'   => true,
						'properties' => [
							'name'        => [
								'description' => __( 'Composer package name.', 'pixelgradelt_retailer' ),
								'type'        => 'string',
								'context'     => [ 'view', 'edit' ],
								'readonly'    => true,
							],
							'version'     => [
								'description' => esc_html__( 'The required package version constraint.', 'pixelgradelt_retailer' ),
								'type'        => 'string',
								'readonly'    => true,
							],
							'stability'   => [
								'description' => esc_html__( 'The required package stability constraint.', 'pixelgradelt_retailer' ),
								'type'        => 'string',
								'readonly'    => true,
							],
							'editLink'    => [
								'description' => esc_html__( 'The required package post edit link.', 'pixelgradelt_retailer' ),
								'type'        => 'string',
								'readonly'    => true,
							],
							'displayName' => [
								'description' => esc_html__( 'The required package display name/string.', 'pixelgradelt_retailer' ),
								'type'        => 'string',
								'readonly'    => true,
							],
						],
					],
				],
				'excludedPackages' => [
					'description' => esc_html__( 'A list of excluded packages.', 'pixelgradelt_retailer' ),
					'type'        => 'array',
					'context'     => [ 'view', 'edit' ],
					'readonly'    => true,
					'items'       => [
						'type'       => 'object',
						'readonly'   => true,
						'properties' => [
							'name'        => [
								'description' => __( 'Composer package name.', 'pixelgradelt_retailer' ),
								'type'        => 'string',
								'context'     => [ 'view', 'edit' ],
								'readonly'    => true,
							],
							'version'     => [
								'description' => esc_html__( 'The excluded package version constraint.', 'pixelgradelt_retailer' ),
								'type'        => 'string',
								'readonly'    => true,
							],
							'stability'   => [
								'description' => esc_html__( 'The excluded package stability constraint.', 'pixelgradelt_retailer' ),
								'type'        => 'string',
								'readonly'    => true,
							],
							'editLink'    => [
								'description' => esc_html__( 'The excluded package post edit link.', 'pixelgradelt_retailer' ),
								'type'        => 'string',
								'readonly'    => true,
							],
							'displayName' => [
								'description' => esc_html__( 'The excluded package display name/string.', 'pixelgradelt_retailer' ),
								'type'        => 'string',
								'readonly'    => true,
							],
						],
					],
				],
				'slug'             => [
					'description' => esc_html__( 'The package slug.', 'pixelgradelt_retailer' ),
					'type'        => 'string',
					'pattern'     => self::SLUG_PATTERN,
					'context'     => [ 'view', 'edit', 'embed' ],
					'required'    => true,
				],
				'type'             => [
					'description' => esc_html__( 'Type of package.', 'pixelgradelt_retailer' ),
					'type'        => 'string',
					'enum'        => [
						SolutionTypes::BASIC,
					],
					'context'     => [ 'view', 'edit', 'embed' ],
					'required'    => true,
				],
				'visibility'       => [
					'description' => esc_html__( 'The package visibility (public, draft, private, etc.)', 'pixelgradelt_retailer' ),
					'type'        => 'string',
					'context'     => [ 'view', 'edit', 'embed' ],
					'readonly'    => true,
				],
			],
		];
	}
}
