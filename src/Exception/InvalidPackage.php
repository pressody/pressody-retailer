<?php
/**
 * Invalid package exception.
 *
 * @package PixelgradeLT
 * @license GPL-2.0-or-later
 * @since 0.1.0
 */

declare ( strict_types = 1 );

namespace PixelgradeLT\Retailer\Exception;

use PixelgradeLT\Retailer\Package;
use Throwable;

/**
 * Invalid package exception class.
 *
 * @since 0.1.0
 */
class InvalidPackage extends \RuntimeException implements PixelgradeltRetailerException {
	/**
	 * Create an exception for package that is missing the needed details to determine its storage relative directory.
	 *
	 * @since 0.1.0
	 *
	 * @param Package       $package
	 * @param int            $code     Optional. The Exception code.
	 * @param Throwable|null $previous Optional. The previous throwable used for the exception chaining.
	 *
	 * @return InvalidPackage
	 */
	public static function missingDetailsForStoreDir(
		Package $package,
		int $code = 0,
		Throwable $previous = null
	): InvalidPackage {
		$package_name = $package->get_name();
		$message = "Unable to determine the store dir for package '{$package_name}' since it is missing needed details.";

		return new static( $message, $code, $previous );
	}
}