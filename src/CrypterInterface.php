<?php
/**
 * String crypter interface.
 *
 * @since   0.10.0
 * @license GPL-2.0-or-later
 * @package PixelgradeLT
 */

declare ( strict_types=1 );

namespace PixelgradeLT\Retailer;

use PixelgradeLT\Retailer\Exception\CrypterBadFormatException;
use PixelgradeLT\Retailer\Exception\CrypterEnvironmentIsBrokenException;
use PixelgradeLT\Retailer\Exception\CrypterWrongKeyOrModifiedCiphertextException;

/**
 * String crypter interface.
 *
 * @since 0.10.0
 */
interface CrypterInterface {

	/**
	 * Encode a given positive int into a string hash.
	 *
	 * @param string $secretString
	 *
	 * @throws CrypterEnvironmentIsBrokenException
	 * @return string The cipher text corresponding to the provided string.
	 */
	public function encrypt( string $secretString ): string;

	/**
	 * Decrypt a cipher text into the secret string.
	 *
	 * @param string $cipherText
	 *
	 * @throws CrypterBadFormatException
	 * @throws CrypterEnvironmentIsBrokenException
	 * @throws CrypterWrongKeyOrModifiedCiphertextException
	 * @return string The decrypted string.
	 */
	public function decrypt( string $cipherText ): string;
}