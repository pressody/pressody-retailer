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
use PixelgradeLT\Retailer\Exception\RestException;
use PixelgradeLT\Retailer\Repository\SolutionRepository;
use PixelgradeLT\Retailer\Transformer\ComposerSolutionTransformer;
use WP_Error;
use WP_REST_Controller;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;
use WP_Http as HTTP;
use function PixelgradeLT\Retailer\is_debug_mode;
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
	 * The key in composer.json `extra` used to store the composer.json fingerprint.
	 *
	 * @since 0.10.0
	 *
	 * @var string
	 */
	const FINGERPRINT_KEY = 'lt-fingerprint';

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
	 * @since 0.10.0
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
	 * @since 0.10.0
	 *
	 * @see   register_rest_route()
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/check_user_details',
			[
				[
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'check_user_details' ],
					'permission_callback' => [ $this, 'check_items_details_permissions_check' ],
					'show_in_index'       => false,
					'args'                => [
						'context'     => $this->get_context_param( [ 'default' => 'view' ] ),
						'user' => [
							'description' => esc_html__( 'The user details to check (userid, siteid, orderid, etc.)', 'pixelgradelt_retailer' ),
							'type'        => 'object',
							'context'     => [ 'view', 'edit' ],
							'required'    => true,
						],
						'composer'    => [
							'type'        => 'object',
							'description' => __( 'composer.json project (root) properties according to the Composer 2.0 JSON schema.', 'pixelgradelt_retailer' ),
							'default'     => [],
							'context'     => [ 'view', 'edit' ],
						],
					],
				],
			]
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/details_to_update',
			[
				[
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'details_to_update_composition' ],
					'permission_callback' => [ $this, 'update_items_details_permissions_check' ],
					'show_in_index'       => false,
					'args'                => [
						'context'     => $this->get_context_param( [ 'default' => 'edit' ] ),
						'user' => [
							'description' => esc_html__( 'The decrypted user details from the composer contents (userid, siteid, orderid, etc.)', 'pixelgradelt_retailer' ),
							'type'        => 'object',
							'context'     => [ 'view', 'edit' ],
							'required'    => true,
						],
						'composer'    => [
							'description' => esc_html__( 'The current full composer.json contents.', 'pixelgradelt_retailer' ),
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
				esc_html__( 'Sorry, you are not allowed to check composition details.', 'pixelgradelt_retailer' ),
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
				esc_html__( 'Sorry, you are not allowed to check composition details.', 'pixelgradelt_retailer' ),
				[ 'status' => rest_authorization_required_code() ]
			);
		}

		return true;
	}

	/**
	 * Check a set of user details.
	 *
	 * @since 0.10.0
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 *
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function check_user_details( WP_REST_Request $request ) {
		/**
		 * Filter the check of a composition's user details.
		 *
		 * @since 0.10.0
		 *
		 * @see   CompositionsController::validate_user_details()
		 *
		 * Return true if the user details are valid, or a WP_Error in case we should reject them.
		 *
		 * @param bool  $valid        Whether the user details are valid.
		 * @param array $user_details The user details as decrypted from the composition details.
		 * @param array $composition  The full composition details.
		 */
		$valid = apply_filters( 'pixelgradelt_retailer/check_user_details', true, $request['user'], $request['composer'] );

		if ( is_wp_error( $valid ) || true !== $valid ) {
			$data = [
				'status' => HTTP::NOT_ACCEPTABLE,
			];

			if ( is_wp_error( $valid ) ) {
				$data['details'] = implode( ' ; ' . PHP_EOL, $valid->get_error_messages() );
			}

			return new WP_Error(
				'rest_invalid_user_details',
				esc_html__( 'The composition user details are invalid, according to LT Retailer.', 'pixelgradelt_retailer' ),
				$data
			);
		}

		// Return that all is OK.
		return rest_ensure_response( [] );
	}

	/**
	 * Determine what details should be updated in the received composition.
	 *
	 * The received user details and composition will be checked first.
	 *
	 * @since 0.10.0
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 *
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function details_to_update_composition( WP_REST_Request $request ) {
		$composition = $request['composer'];
		// Make sure we are dealing with an associative array.
		if ( is_object( $composition ) ) {
			$composition = $this->objectToArrayRecursive( $composition );
		}

		// First, validate the received composition's schema.
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

		// Second, validate/check the user details.
		if ( is_wp_error( $user_check_result = $this->check_user_details( $request ) ) ) {
			return $user_check_result;
		}

		// If we have made it thus far, the received composition is OK.
		// Proceed to determine if something needs updating in it.

		/**
		 * Provide new composition details that should be updated.
		 *
		 * @since 0.10.0
		 *
		 * @see   CompositionsController::refresh_item()
		 *
		 * @param bool|array $details_to_update The new composition details.
		 *                                      false if we should reject the request and error out.
		 *                                      An empty array if we should leave the composition unchanged.
		 * @param array $user_details The received user details, already checked.
		 * @param array $composition  The full composition details.
		 */
		$details_to_update = apply_filters( 'pixelgradelt_retailer/details_to_update_composition', [], $request['user'], $composition );
		if ( false === $details_to_update ) {
			return new WP_Error(
				'rest_rejected',
				esc_html__( 'Your attempt to determine details to update in the composition was rejected.', 'pixelgradelt_retailer' ),
				[ 'status' => HTTP::NOT_ACCEPTABLE, ]
			);
		}

		if ( ! is_array( $details_to_update ) || empty( $details_to_update ) ) {
			// There is nothing to update.
			$response = rest_ensure_response( [] );
			$response->set_status( HTTP::NO_CONTENT );

			return $response;
		}

		return rest_ensure_response( $details_to_update );
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
		$targetKeys = array(
			'require',
			'require-dev',
			'conflict',
			'replace',
			'provide',
			'repositories',
			'extra',
		);
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
			'config',
			'extra',
			'scripts',
			'support',
		];
		foreach ( $objectsKeys as $key ) {
			if ( empty( $compositionObject->$key ) ) {
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
		return apply_filters( 'pixelgradelt_records/composition_standardize_to_object', $compositionObject, $composition );
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
			$message = 'Unable to encode schema array as JSON';
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
			$message = 'Unable to encode schema array as JSON';
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
		$validator = new Validator();
		$composer_schema = $this->get_composer_schema();
		if ( empty( $composer_schema ) ) {
			// If we couldn't read the schema, let things pass.
			return true;
		}

		$validator->check( $composition, $composer_schema );
		if ( ! $validator->isValid() ) {
			$errors = array();
			foreach ( (array) $validator->getErrors() as $error ) {
				$errors[] = ( $error['property'] ? $error['property'] . ' : ' : '' ) . $error['message'];
			}
			throw new JsonValidationException( 'The composition does not match the expected JSON schema', $errors );
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
