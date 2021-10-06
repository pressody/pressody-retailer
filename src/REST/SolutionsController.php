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
use PixelgradeLT\Retailer\Repository\PackageRepository;
use PixelgradeLT\Retailer\Repository\ProcessedSolutions;
use PixelgradeLT\Retailer\Repository\SolutionRepository;
use PixelgradeLT\Retailer\SolutionType\SolutionTypes;
use PixelgradeLT\Retailer\Transformer\ComposerSolutionTransformer;
use WP_Error;
use WP_REST_Controller;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;
use WP_Http as HTTP;

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
	 * Composer package name pattern.
	 *
	 * This is the same pattern present in the Composer schema: https://getcomposer.org/schema.json
	 *
	 * @var string
	 */
	const PACKAGE_NAME_PATTERN = '^[a-z0-9]([_.-]?[a-z0-9]+)*/[a-z0-9](([_.]?|-{0,2})[a-z0-9]+)*$';

	/**
	 * Solution repository.
	 *
	 * @var SolutionRepository
	 */
	protected SolutionRepository $repository;

	/**
	 * Composer solution transformer.
	 *
	 * @var ComposerSolutionTransformer
	 */
	protected ComposerSolutionTransformer $composer_transformer;

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 *
	 * @param string                      $namespace            The namespace for this controller's route.
	 * @param string                      $rest_base            The base of this controller's route.
	 * @param SolutionRepository          $repository           Solution repository.
	 * @param ComposerSolutionTransformer $composer_transformer Solution transformer.
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

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/processed',
			[
				[
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_processed_items' ],
					'permission_callback' => [ $this, 'get_items_permissions_check' ],
					'args'                => $this->get_collection_params(),
				],
				'schema' => [ $this, 'get_public_item_schema' ],
			]
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>[\d]+)',
			[
				'args'   => [
					'id' => [
						'description' => __( 'The solution post ID.', 'pixelgradelt_retailer' ),
						'type'        => 'integer',
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
				'schema' => [ $this, 'get_public_item_schema' ],
			]
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/parts',
			[
				[
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_items_parts' ],
					'permission_callback' => [ $this, 'get_items_permissions_check' ],
					'args'                => $this->get_collection_params(),
				],
				'schema' => [ $this, 'get_public_part_schema' ],
			]
		);

	}

	/**
	 * Retrieve a collection of solutions.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 *
	 * @return WP_Error|WP_REST_Response
	 */
	public function get_items( $request ) {
		$items = [];

		$filtered_repository = $this->get_filtered_repository( $request );
		foreach ( $filtered_repository->all() as $package ) {
			$data    = $this->prepare_item_for_response( $package, $request );
			$items[] = $this->prepare_response_for_collection( $data );
		}

		return rest_ensure_response( $items );
	}

	/**
	 * Retrieve and then process a collection of solutions.
	 *
	 * The processing is mainly applying the exclude-solutions logic.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 *
	 * @return WP_Error|WP_REST_Response
	 */
	public function get_processed_items( WP_REST_Request $request ) {
		$items = [];

		// Requests for processed items need to specify a list of items to process.
		// Otherwise, all the solutions are processed and that doesn't make any sense.
		if ( empty( $request['postId'] )
		     && empty( $request['postSlug'] )
		     && empty( $request['packageName'] ) ) {

			return new WP_Error(
				'pixelgradelt_retailer_rest_no_list',
				esc_html__( 'You need to define a subset of solutions to process.', 'pixelgradelt_retailer' ),
				[ 'status' => HTTP::NOT_ACCEPTABLE ]
			);
		}

		$filtered_repository = $this->get_filtered_repository( $request );

		$solutionsContext = [];
		if ( ! empty( $request['solutionsContext'] ) && is_array( $request['solutionsContext'] ) ) {
			$solutionsContext = $request['solutionsContext'];
		}

		// Make a processed repository out of the filtered repository.
		$processed_repository = new ProcessedSolutions( $filtered_repository, $solutionsContext, $this->repository->get_factory(), $this->repository->get_solution_manager() );

		// Prepare the processed solutions for response.
		foreach ( $processed_repository->all() as $package ) {
			$data    = $this->prepare_item_for_response( $package, $request );
			$items[] = $this->prepare_response_for_collection( $data );
		}

		return rest_ensure_response( $items );
	}

	/**
	 * Retrieve a collection of parts required by a collection of solutions.
	 *
	 * Please note that the specified solutions collection is processed before determining the collection of parts that it requires.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 *
	 * @return WP_Error|WP_REST_Response
	 */
	public function get_items_parts( WP_REST_Request $request ) {
		// Requests for processed items need to specify a list of items to process.
		// Otherwise, all the solutions as processed and that doesn't make any sense.
		if ( empty( $request['postId'] ) && empty( $request['postSlug'] ) && empty( $request['packageName'] ) ) {
			return new WP_Error(
				'pixelgradelt_retailer_rest_no_list',
				esc_html__( 'You need to define a subset of solutions to process.', 'pixelgradelt_retailer' ),
				[ 'status' => HTTP::NOT_ACCEPTABLE ]
			);
		}

		/**
		 * First, get the flattened, processed collection of solutions.
		 */
		$filtered_repository = $this->get_filtered_repository( $request );

		$solutionsContext = [];
		if ( ! empty( $request['solutionsContext'] ) && is_array( $request['solutionsContext'] ) ) {
			$solutionsContext = $request['solutionsContext'];
		}

		// Make a processed repository out of the filtered repository.
		$processed_repository = new ProcessedSolutions( $filtered_repository, $solutionsContext, $this->repository->get_factory(), $this->repository->get_solution_manager() );

		/**
		 * Second, gather all the required parts by the collection of solutions.
		 */
		$required_parts = [];
		foreach ( $processed_repository->all() as $solution ) {
			if ( ! $solution->has_required_ltrecords_parts() ) {
				continue;
			}

			foreach ( $solution->get_required_ltrecords_parts() as $part ) {
				if ( empty( $required_parts[ $part['composer_package_name'] ] ) ) {
					$required_parts[ $part['composer_package_name'] ] = [
						'composer_package_name' => $part['composer_package_name'],
						'version_ranges'        => [],
						'requiredBy'            => [],
					];
				}

				// Add the version constraint to the version contraints list.
				$required_parts[ $part['composer_package_name'] ]['version_ranges'][] = $part['version_range'];

				// Remember the solution that required this part.
				$required_parts[ $part['composer_package_name'] ]['requiredBy'][] = [
					'composer_package_name' => $solution->get_composer_package_name(),
					'part_version_range'    => $part['version_range'],
				];
			}
		}

		return rest_ensure_response( $this->prepare_items_parts_for_response( $required_parts, $request ) );
	}

	/**
	 * Return a standard, request filtered repository of the main repository.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request
	 *
	 * @return PackageRepository
	 */
	protected function get_filtered_repository( WP_REST_Request $request ): PackageRepository {
		return $this->repository->with_filter(
			function ( $package ) use ( $request ) {
				if ( ! empty( $request['type'] ) && ! in_array( $package->get_type(), $request['type'], true ) ) {
					return false;
				}

				if ( ! empty( $request['postId'] ) && $request['postId'] !== [ 0 ] && ! in_array( $package->get_managed_post_id(), $request['postId'] ) ) {
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
	}

	/**
	 * Prepare solutions' parts for response.
	 *
	 * @since 1.0.0
	 *
	 * @param array           $items_parts Parts list.
	 * @param WP_REST_Request $request     WP request instance.
	 *
	 * @return array
	 */
	protected function prepare_items_parts_for_response( array $items_parts, WP_REST_Request $request ): array {
		$parts = [];

		foreach ( $items_parts as $items_part ) {
			$version_range = '*';
			if ( ! empty( $items_part['version_ranges'] ) ) {
				// By merging the version ranges with `,` we ensure that all constraints need to be satisfied
				// (`,` functions as logical AND between constraints and has a higher precedence than `||` (logical OR) ).
				// @see https://getcomposer.org/doc/articles/versions.md#version-range
				$version_range = implode( ', ', array_unique( $items_part['version_ranges'] ) );
			}

			$requiredBy = [];
			foreach ( $items_part['requiredBy'] as $solution_that_required ) {
				$requiredBy[] = [
					'name'            => $solution_that_required['composer_package_name'],
					'requiredVersion' => $solution_that_required['part_version_range'],
				];
			}

			$parts[] = [
				'name'       => $items_part['composer_package_name'],
				'version'    => $version_range,
				'requiredBy' => $requiredBy,
			];
		}

		return array_values( $parts );
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
	 * Retrieve a single solution.
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
			return new WP_Error(
				'pixelgradelt_retailer_rest_invalid_id',
				__( 'Invalid solution post ID.', 'pixelgradelt_retailer' ),
				[ 'status' => HTTP::NOT_FOUND ]
			);
		}

		$data = $this->prepare_item_for_response( $package, $request );

		return rest_ensure_response( $data );
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
	 * Retrieve the query parameters for collections of solutions.
	 *
	 * @since 1.0.0
	 *
	 * @return array
	 */
	public function get_collection_params(): array {
		$params = [
			'context'          => $this->get_context_param( [ 'default' => 'view' ] ),
			'solutionsContext' => [
				'description' => esc_html__( 'Details about the solutions to limit the response by (enforced by post IDs, post slugs, package names). These details are related to the user actions in adding to a site\'s composition (a series of solutions); things like the timestamps of when a solution was added. Each solution details should sit under the solution\'s package name key.', 'pixelgradelt_retailer' ),
				'type'        => 'object',
				'default'     => [],
			],
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
			'description' => esc_html__( 'Limit results to solutions by one or more Composer package names (including the vendor). Use the "postSlug" parameter if you want to provide only the name, without the vendor.', 'pixelgradelt_retailer' ),
			'type'        => 'array',
			'items'       => [
				'type'    => 'string',
				'pattern' => self::PACKAGE_NAME_PATTERN,
			],
			'default'     => [],
		];

		$params['type'] = [
			'description'       => esc_html__( 'Limit results to solutions of one or more types.', 'pixelgradelt_retailer' ),
			'type'              => 'array',
			'items'             => [
				'type' => 'string',
			],
			'default'           => [
				SolutionTypes::REGULAR,
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
			'keywords'    => $item->get_keywords(),
			'categories'  => $item->get_categories(),
			'type'        => $item->get_type(),
			'visibility'  => $item->get_visibility(),
			'editLink'    => get_edit_post_link( $item->get_managed_post_id(), $request['context'] ),
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
	 * @since 1.0.0
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
				$edit_link = get_edit_post_link( $requiredPackage['managed_post_id'], $request['context'] );
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
	 * @since 1.0.0
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
				$edit_link = get_edit_post_link( $replacedPackage['managed_post_id'], $request['context'] );
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
	 * Get the solution schema, conforming to JSON Schema.
	 *
	 * @since 1.0.0
	 *
	 * @return array
	 */
	public function get_item_schema(): array {
		return [
			'$schema'    => 'http://json-schema.org/draft-04/schema#',
			'title'      => 'package',
			'type'       => 'object',
			'properties' => [
				'authors'          => [
					'description' => esc_html__( 'The package authors details.', 'pixelgradelt_retailer' ),
					'type'        => 'array',
					'context'     => [ 'view', 'edit', 'embed' ],
					'readonly'    => true,
				],
				'composer'         => [
					'description' => esc_html__( 'Package data formatted for Composer.', 'pixelgradelt_retailer' ),
					'type'        => 'object',
					'context'     => [ 'view', 'edit', 'embed' ],
					'readonly'    => true,
					'properties'  => [
						'name' => [
							'description' => __( 'Composer package name.', 'pixelgradelt_retailer' ),
							'type'        => 'string',
							'context'     => [ 'view', 'edit', 'embed' ],
							'readonly'    => true,
						],
						'type' => [
							'description' => __( 'Composer package type.', 'pixelgradelt_retailer' ),
							'type'        => 'string',
							'enum'        => [ 'wordpress-plugin', 'wordpress-theme' ],
							'context'     => [ 'view', 'edit', 'embed' ],
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
					'context'     => [ 'view', 'edit', 'embed' ],
					'readonly'    => true,
				],
				'keywords'         => [
					'description' => esc_html__( 'The package keywords.', 'pixelgradelt_retailer' ),
					'type'        => 'array',
					'items'       => [
						'type' => 'string',
					],
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
					'context'     => [ 'view', 'edit', 'embed' ],
					'readonly'    => true,
					'items'       => [
						'type'       => 'object',
						'readonly'   => true,
						'properties' => [
							'url'     => [
								'description' => esc_html__( 'A URL to download the release.', 'pixelgradelt_retailer' ),
								'type'        => 'string',
								'format'      => 'uri',
								'context'     => [ 'view', 'edit', 'embed' ],
								'readonly'    => true,
							],
							'version' => [
								'description' => esc_html__( 'The release version.', 'pixelgradelt_retailer' ),
								'type'        => 'string',
								'context'     => [ 'view', 'edit', 'embed' ],
								'readonly'    => true,
							],
						],
					],
				],
				'requiredPackages' => [
					'description' => esc_html__( 'A list of required packages.', 'pixelgradelt_retailer' ),
					'type'        => 'array',
					'context'     => [ 'view', 'edit', 'embed' ],
					'readonly'    => true,
					'items'       => [
						'type'       => 'object',
						'readonly'   => true,
						'properties' => [
							'name'        => [
								'description' => __( 'Composer package name.', 'pixelgradelt_retailer' ),
								'type'        => 'string',
								'context'     => [ 'view', 'edit', 'embed' ],
								'readonly'    => true,
							],
							'version'     => [
								'description' => esc_html__( 'The required package version constraint.', 'pixelgradelt_retailer' ),
								'type'        => 'string',
								'context'     => [ 'view', 'edit', 'embed' ],
								'readonly'    => true,
							],
							'stability'   => [
								'description' => esc_html__( 'The required package stability constraint.', 'pixelgradelt_retailer' ),
								'type'        => 'string',
								'context'     => [ 'view', 'edit', 'embed' ],
								'readonly'    => true,
							],
							'editLink'    => [
								'description' => esc_html__( 'The required package post edit link.', 'pixelgradelt_retailer' ),
								'type'        => 'string',
								'format'      => 'uri',
								'context'     => [ 'view', 'edit' ],
								'readonly'    => true,
							],
							'displayName' => [
								'description' => esc_html__( 'The required package display name/string.', 'pixelgradelt_retailer' ),
								'type'        => 'string',
								'context'     => [ 'view', 'edit', 'embed' ],
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
								'context'     => [ 'view', 'edit' ],
								'readonly'    => true,
							],
							'stability'   => [
								'description' => esc_html__( 'The excluded package stability constraint.', 'pixelgradelt_retailer' ),
								'type'        => 'string',
								'context'     => [ 'view', 'edit' ],
								'readonly'    => true,
							],
							'editLink'    => [
								'description' => esc_html__( 'The excluded package post edit link.', 'pixelgradelt_retailer' ),
								'type'        => 'string',
								'format'      => 'uri',
								'context'     => [ 'view', 'edit' ],
								'readonly'    => true,
							],
							'displayName' => [
								'description' => esc_html__( 'The excluded package display name/string.', 'pixelgradelt_retailer' ),
								'type'        => 'string',
								'context'     => [ 'view', 'edit' ],
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
						SolutionTypes::REGULAR,
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
				'editLink'         => [
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
	 * Get the part schema, conforming to JSON Schema.
	 *
	 * @since 1.0.0
	 *
	 * @return array
	 */
	public function get_part_schema(): array {
		return [
			'$schema'    => 'http://json-schema.org/draft-04/schema#',
			'title'      => 'part',
			'type'       => 'object',
			'properties' => [
				'name'       => [
					'description' => __( 'The part\'s Composer package name.', 'pixelgradelt_retailer' ),
					'type'        => 'string',
					'context'     => [ 'view', 'edit', 'embed' ],
					'readonly'    => true,
				],
				'version'    => [
					'description' => esc_html__( 'The part\'s version constraint(s).', 'pixelgradelt_retailer' ),
					'type'        => 'string',
					'context'     => [ 'view', 'edit', 'embed' ],
					'readonly'    => true,
				],
				'requiredBy' => [
					'description' => esc_html__( 'A list of solution package details that required this part.', 'pixelgradelt_retailer' ),
					'type'        => 'array',
					'items'       => [
						'type'       => 'object',
						'readonly'   => true,
						'properties' => [
							'name'            => [
								'description' => __( 'Solution composer package name.', 'pixelgradelt_retailer' ),
								'type'        => 'string',
								'context'     => [ 'view', 'edit' ],
								'readonly'    => true,
							],
							'requiredVersion' => [
								'description' => esc_html__( 'The solution\'s required part version constraint.', 'pixelgradelt_retailer' ),
								'type'        => 'string',
								'context'     => [ 'view', 'edit' ],
								'readonly'    => true,
							],
						],
					],
					'context'     => [ 'view', 'edit', 'embed' ],
					'readonly'    => true,
				],
			],
		];
	}

	/**
	 * Retrieves the part's schema for display / public consumption purposes.
	 *
	 * @since 1.0.0
	 *
	 * @return array Public part schema data.
	 */
	public function get_public_part_schema(): array {

		$schema = $this->get_part_schema();

		if ( ! empty( $schema['properties'] ) ) {
			foreach ( $schema['properties'] as &$property ) {
				unset( $property['arg_options'] );
			}
		}

		return $schema;
	}
}
