<?php
/**
 * Authentication server interface.
 *
 * @package PixelgradeLT
 * @license GPL-2.0-or-later
 * @since 0.1.0
 */

declare ( strict_types = 1 );

namespace PixelgradeLT\Retailer\Authentication;

use PixelgradeLT\Retailer\Exception\AuthenticationException;
use PixelgradeLT\Retailer\HTTP\Request;

/**
 * Authentication server interface.
 *
 * @since 0.1.0
 */
interface ServerInterface {
	/**
	 * Check if the server should handle the current request.
	 *
	 * @since 0.1.0
	 *
	 * @param Request $request Request instance.
	 * @return bool
	 */
	public function check_scheme( Request $request ): bool;

	/**
	 * Handle authentication.
	 *
	 * @since 0.1.0
	 *
	 * @param Request $request Request instance.
	 * @throws AuthenticationException If authentications fails.
	 * @return int A user ID.
	 */
	public function authenticate( Request $request ): int;
}
