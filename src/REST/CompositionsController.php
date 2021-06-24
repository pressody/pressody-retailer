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
use PixelgradeLT\Retailer\CrypterInterface;
use PixelgradeLT\Retailer\Exception\CrypterBadFormatException;
use PixelgradeLT\Retailer\Exception\CrypterEnvironmentIsBrokenException;
use PixelgradeLT\Retailer\Exception\CrypterWrongKeyOrModifiedCiphertextException;
use PixelgradeLT\Retailer\Exception\RestException;
use PixelgradeLT\Retailer\Repository\SolutionRepository;
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
	 * The key in composer.json `extra` used to store the encrypted user details.
	 *
	 * @since 0.10.0
	 *
	 * @var string
	 */
	const USER_DETAILS_KEY = 'lt-user';

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
	 * @param SolutionRepository          $repository           Solution repository.
	 * @param ComposerSolutionTransformer $composer_transformer Solution transformer.
	 * @param CrypterInterface            $crypter              String crypter.
	 */
	public function __construct(
		string $namespace,
		string $rest_base,
		SolutionRepository $repository,
		ComposerSolutionTransformer $composer_transformer,
		CrypterInterface $crypter
	) {

		$this->namespace            = $namespace;
		$this->rest_base            = $rest_base;
		$this->repository           = $repository;
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
			'/' . $this->rest_base . '/encrypt_user_details',
			[
				[
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'encrypt_user_details' ],
					'permission_callback' => [ $this, 'encrypt_user_details_permissions_check' ],
					'show_in_index'       => false,
					'args'                => [
						'context'       => $this->get_context_param( [ 'default' => 'edit' ] ),
						'userid'        => [
							'description' => esc_html__( 'The user ID.', 'pixelgradelt_retailer' ),
							'type'        => 'integer',
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
							'description' => esc_html__( 'Extra user details to encrypt besides the core details.', 'pixelgradelt_retailer' ),
							'default'     => [],
							'context'     => [ 'view', 'edit' ],
						],
					],
				],
			]
		);

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
						'context'  => $this->get_context_param( [ 'default' => 'view' ] ),
						'user'     => [
							'description' => esc_html__( 'The encrypted user details to check.', 'pixelgradelt_retailer' ),
							'type'        => 'string',
							'context'     => [ 'view', 'edit' ],
							'required'    => true,
						],
						'composer' => [
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
			'/' . $this->rest_base . '/details_to_update',
			[
				[
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'details_to_update_composition' ],
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
	 * Check if a given request has access to encrypt user details.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 *
	 * @return true|WP_Error True if the request has read access, WP_Error object otherwise.
	 */
	public function encrypt_user_details_permissions_check( WP_REST_Request $request ) {
		if ( ! current_user_can( Capabilities::VIEW_SOLUTIONS ) ) {
			return new WP_Error(
				'rest_cannot_read',
				esc_html__( 'Sorry, you are not allowed to encrypt user details.', 'pixelgradelt_retailer' ),
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
	 * Encrypt a set of user details.
	 *
	 * @since 0.10.0
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 *
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function encrypt_user_details( WP_REST_Request $request ) {
		// Gather the user details.
		$user_details = [
			'userid'        => $request['userid'],
			'compositionid' => $request['compositionid'],
			'extra'         => $request['extra'],
		];

		/**
		 * Filter the user details before encryption.
		 *
		 * @since 0.10.0
		 *
		 * @see   CompositionsController::encrypt_user_details()
		 *
		 * @param bool  $valid        Whether the user details are valid.
		 * @param array $user_details The user details as decrypted from the composition details.
		 * @param array $composition  The full composition details.
		 */
		$user_details = apply_filters( 'pixelgradelt_retailer/before_encrypt_user_details', $user_details, $request );

		try {
			// Validate the received user details.
			// In case of invalid user details, exceptions are thrown.
			$this->validate_user_details( $user_details );
		} catch ( RestException $e ) {
			return new WP_Error(
				'rest_invalid_user_details',
				$e->getMessage(),
				[ 'status' => $e->getStatusCode(), ]
			);
		}

		// Now encrypt them.
		try {
			$encrypted_user_details = $this->crypter->encrypt( json_encode( $user_details ) );
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

		// Return the encrypted user details (a string).
		return rest_ensure_response( $encrypted_user_details );
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
		 * Validate the encrypted user details in the composition.
		 */
		try {
			$user_details = $this->decrypt_user_details( $request['user'] );
		} catch ( CrypterBadFormatException | CrypterWrongKeyOrModifiedCiphertextException | RestException $e ) {
			return new WP_Error(
				'rest_invalid_user_details',
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
			// In case of invalid user details, exceptions are thrown.
			$this->validate_user_details( $user_details );
		} catch ( RestException $e ) {
			return new WP_Error(
				'rest_invalid_user_details',
				$e->getMessage(),
				[ 'status' => $e->getStatusCode(), ]
			);
		}

		// Return that all is OK.
		return rest_ensure_response( [] );
	}

	/**
	 * Validate the user details.
	 *
	 * Allow others to do further validations.
	 *
	 * @since 0.10.0
	 *
	 * @param array $user_details
	 *
	 * @throws RestException
	 * @return bool True on valid. Exceptions are thrown on invalid.
	 */
	protected function validate_user_details( array $user_details ): bool {

		if ( ! isset( $user_details['userid'] ) ) {
			throw RestException::forMissingComposerUserDetails();
		}

		// Check that the user ID actually belongs to a user.
		$user = get_user_by( 'id', $user_details['userid'] );
		if ( false === $user ) {
			throw RestException::forUserNotFound();
		}

		/**
		 * Filter the validation of user details.
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
		$valid = apply_filters( 'pixelgradelt_retailer/validate_user_details', true, $user_details );
		if ( is_wp_error( $valid ) ) {
			$message = esc_html__( 'Third-party user details checks have found them invalid. Here is what happened: ', 'pixelgradelt_retailer' ) . PHP_EOL;
			$message .= implode( ' ; ' . PHP_EOL, $valid->get_error_messages() );

			throw RestException::forInvalidComposerUserDetails( $message );
		} elseif ( true !== $valid ) {
			throw RestException::forInvalidComposerUserDetails();
		}

		return true;
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
		 * Second, decrypt and validate the user details.
		 */
		if ( empty( $composition['extra'][ self::USER_DETAILS_KEY ] ) ) {
			return new WP_Error(
				'rest_missing_user_details',
				esc_html__( 'The composition is missing the encrypted user details.', 'pixelgradelt_retailer' ),
				[ 'status' => HTTP::NOT_ACCEPTABLE, ]
			);
		}
		try {
			$user_details = $this->decrypt_user_details( $composition['extra'][ self::USER_DETAILS_KEY ] );
		} catch ( CrypterBadFormatException | CrypterWrongKeyOrModifiedCiphertextException | RestException $e ) {
			return new WP_Error(
				'rest_invalid_user_details',
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
			// In case of invalid user details, exceptions are thrown.
			$this->validate_user_details( $user_details );
		} catch ( RestException $e ) {
			return new WP_Error(
				'rest_invalid_user_details',
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
		 * @see   CompositionsController::details_to_update_composition()
		 *
		 * @param bool|array $details_to_update The new composition details.
		 *                                      false if we should reject the request and error out.
		 *                                      An empty array if we should leave the composition unchanged.
		 * @param array      $user_details      The decrypted user details, already checked.
		 * @param array      $composition       The full composition details.
		 */
		$details_to_update = apply_filters( 'pixelgradelt_retailer/details_to_update_composition', [], $user_details, $composition );
		if ( is_wp_error( $details_to_update ) ) {
			$message = esc_html__( 'Your attempt to determine details to update the composition with was rejected.. Here is what happened: ', 'pixelgradelt_retailer' ) . PHP_EOL;
			$message .= implode( ' ; ' . PHP_EOL, $details_to_update->get_error_messages() );

			return new WP_Error(
				'rest_rejected',
				$message,
				[ 'status' => HTTP::NOT_ACCEPTABLE, ]
			);
		} elseif ( false === $details_to_update ) {
			return new WP_Error(
				'rest_rejected',
				esc_html__( 'Your attempt to determine details to update the composition with was rejected.', 'pixelgradelt_retailer' ),
				[ 'status' => HTTP::NOT_ACCEPTABLE, ]
			);
		}

		if ( ! is_array( $details_to_update ) || empty( $details_to_update ) ) {
			// There is nothing to update. Respond accordingly.
			$response = rest_ensure_response( [] );
			$response->set_status( HTTP::NO_CONTENT );

			return $response;
		}

		return rest_ensure_response( $details_to_update );
	}

	/**
	 * Decrypt the encrypted composition user details.
	 *
	 * @since 0.10.0
	 *
	 * @param string $encrypted_user_details
	 *
	 * @throws CrypterBadFormatException
	 * @throws CrypterEnvironmentIsBrokenException
	 * @throws CrypterWrongKeyOrModifiedCiphertextException
	 * @throws RestException
	 * @return array
	 */
	protected function decrypt_user_details( string $encrypted_user_details ): array {
		$user_details = json_decode( $this->crypter->decrypt( $encrypted_user_details ), true );

		if ( null === $user_details ) {
			throw RestException::forInvalidComposerUserDetails();
		}

		return $user_details;
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
			$errors = array();
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
