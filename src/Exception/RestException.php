<?php
/**
 * REST exception.
 *
 * @since   0.10.0
 * @license GPL-2.0-or-later
 * @package Pressody
 */

declare ( strict_types=1 );

namespace Pressody\Retailer\Exception;

use Throwable;
use WP_Http as HTTP;

/**
 * REST exception class.
 *
 * @since 0.10.0
 */
class RestException extends \Exception implements PressodyRetailerException {
	/**
	 * HTTP status code.
	 *
	 * @var int
	 */
	protected int $status_code;

	/**
	 * Constructor.
	 *
	 * @since 0.10.0
	 *
	 * @param string         $message     Message.
	 * @param int            $status_code Optional. HTTP status code. Defaults to 500.
	 * @param int            $code        Exception code.
	 * @param Throwable|null $previous    Previous exception.
	 */
	public function __construct(
		string $message,
		int $status_code = HTTP::INTERNAL_SERVER_ERROR,
		int $code = 0,
		Throwable $previous = null
	) {
		$this->status_code = $status_code;
		$message           = $message ?: esc_html__( 'Internal Server Error', 'pressody_retailer' );

		parent::__construct( $message, $code, $previous );
	}

	/**
	 * Create an exception for invalid composition PD details.
	 *
	 * @since 0.10.0
	 *
	 * @param string         $message
	 * @param int            $code     Optional. The Exception code.
	 * @param Throwable|null $previous Optional. The previous throwable used for the exception chaining.
	 *
	 * @return RestException
	 */
	public static function forInvalidCompositionPDDetails(
		string $message = '',
		int $code = 0,
		Throwable $previous = null
	): RestException {
		if ( empty( $message ) ) {
			$message = esc_html__( 'The provided data has invalid composition PD details.', 'pressody_retailer' );
		}

		return new static( $message, HTTP::NOT_ACCEPTABLE, $code, $previous );
	}

	/**
	 * Create an exception for missing composition PD details.
	 *
	 * @since 0.10.0
	 *
	 * @param int            $code     Optional. The Exception code.
	 * @param Throwable|null $previous Optional. The previous throwable used for the exception chaining.
	 *
	 * @return RestException
	 */
	public static function forMissingCompositionPDDetails(
		int $code = 0,
		Throwable $previous = null
	): RestException {
		$message = esc_html__( 'The provided data is missing some composition PD details.', 'pressody_retailer' );

		return new static( $message, HTTP::NOT_ACCEPTABLE, $code, $previous );
	}

	/**
	 * Create an exception for missing user.
	 *
	 * @since 0.10.0
	 *
	 * @param int            $code     Optional. The Exception code.
	 * @param Throwable|null $previous Optional. The previous throwable used for the exception chaining.
	 *
	 * @return RestException
	 */
	public static function forUserNotFound(
		int $code = 0,
		Throwable $previous = null
	): RestException {
		$message = esc_html__( 'Could not find a valid user with the provided ID(s).', 'pressody_retailer' );

		return new static( $message, HTTP::NOT_ACCEPTABLE, $code, $previous );
	}

	/**
	 * Create an exception for missing composition.
	 *
	 * @since 0.10.0
	 *
	 * @param int            $code     Optional. The Exception code.
	 * @param Throwable|null $previous Optional. The previous throwable used for the exception chaining.
	 *
	 * @return RestException
	 */
	public static function forCompositionNotFound(
		int $code = 0,
		Throwable $previous = null
	): RestException {
		$message = esc_html__( 'Could not find a composition with the provided ID.', 'pressody_retailer' );

		return new static( $message, HTTP::NOT_ACCEPTABLE, $code, $previous );
	}

	/**
	 * Create an exception for broken encryption environment.
	 *
	 * @since 0.10.0
	 *
	 * @param int            $code     Optional. The Exception code.
	 * @param Throwable|null $previous Optional. The previous throwable used for the exception chaining.
	 *
	 * @return RestException
	 */
	public static function forBrokenEncryptionEnvironment(
		int $code = 0,
		Throwable $previous = null
	): RestException {
		$message = esc_html__( 'We could not run encryption. Please contact the administrator and let them know that something is wrong. Thanks in advance!', 'pressody_retailer' );

		return new static( $message, HTTP::INTERNAL_SERVER_ERROR, $code, $previous );
	}

	/**
	 * Retrieve the HTTP status code.
	 *
	 * @since 0.10.0
	 *
	 * @return int
	 */
	public function getStatusCode(): int {
		return $this->status_code;
	}
}
