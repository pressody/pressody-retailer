<?php
/**
 * API Key authentication server.
 *
 * @package PixelgradeLT
 * @license GPL-2.0-or-later
 * @since 0.1.0
 */

declare ( strict_types = 1 );

namespace PixelgradeLT\Retailer\Authentication\ApiKey;

use PixelgradeLT\Retailer\Authentication\ServerInterface;
use PixelgradeLT\Retailer\Exception\AuthenticationException;
use PixelgradeLT\Retailer\HTTP\Request;

/**
 * API Key authentication server class.
 *
 * @since 0.1.0
 */
class Server implements ServerInterface {

	const AUTH_PWD = 'pixelgradelt_retailer';

	/**
	 * API Key repository.
	 *
	 * @var ApiKeyRepository
	 */
	protected ApiKeyRepository $repository;

	/**
	 * Constructor method.
	 *
	 * @since 0.1.0
	 *
	 * @param ApiKeyRepository $repository API Key repository.
	 */
	public function __construct( ApiKeyRepository $repository ) {
		$this->repository = $repository;
	}

	/**
	 * Check if the server should handle the current request.
	 *
	 * @since 0.1.0
	 *
	 * @param Request $request Request instance.
	 * @return bool
	 */
	public function check_scheme( Request $request ): bool {
		$header = $request->get_header( 'authorization' );

		// Bail if the authorization header doesn't exist.
		if ( null === $header || 0 !== stripos( $header, 'basic ' ) ) {
			return false;
		}

		// The password part of the authorization header isn't used for API Key authentication.
		// We use instead the PHP_AUTH_PW header.
		// The password is hardcoded because the username is a private API Key.
		$auth_password = $request->get_header( 'PHP_AUTH_PW' );

		// Bail if this isn't a PixelgradeLT Retailer authentication request.
		if ( self::AUTH_PWD !== $auth_password ) {
			return false;
		}

		return true;
	}

	/**
	 * Handle authentication.
	 *
	 * @since 0.1.0
	 *
	 * @param Request $request Request instance.
	 *
	 * @throws AuthenticationException If authentication fails.
	 * @return int A user ID.
	 */
	public function authenticate( Request $request ): int {
		$api_key_id = $request->get_header( 'PHP_AUTH_USER' );

		// Bail if an API Key wasn't provided.
		if ( null === $api_key_id ) {
			throw AuthenticationException::forMissingAuthorizationHeader();
		}

		$api_key = $this->repository->find_by_token( $api_key_id );

		// Bail if the API Key doesn't exist.
		if ( null === $api_key ) {
			throw AuthenticationException::forInvalidCredentials();
		}

		$user = $api_key->get_user();

		// Bail if the user couldn't be determined.
		if ( ! $this->validate_user( $user ) ) {
			throw AuthenticationException::forInvalidCredentials();
		}

		$this->maybe_update_last_used_time( $api_key );

		return $user->ID;
	}

	/**
	 * Update the last used time if it's been more than a minute.
	 *
	 * @since 0.1.0
	 *
	 * @param ApiKey $api_key API Key.
	 */
	protected function maybe_update_last_used_time( ApiKey $api_key ) {
		$timestamp = time();
		$last_used = $api_key['last_used'] ?? 0;

		if ( $timestamp - $last_used < MINUTE_IN_SECONDS ) {
			return;
		}

		$api_key['last_used'] = $timestamp;
		$this->repository->save( $api_key );
	}

	/**
	 * Whether a user is valid.
	 *
	 * @since 0.1.0
	 *
	 * @param mixed $user WordPress user instance.
	 * @return bool
	 */
	protected function validate_user( $user ): bool {
		return ! empty( $user ) && ! is_wp_error( $user ) && $user->exists();
	}
}