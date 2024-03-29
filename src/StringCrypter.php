<?php
/**
 * String hashes encode and decode provider.
 *
 * @link    https://github.com/vinkla/hashids
 *
 * @package Pressody
 * @license GPL-2.0-or-later
 * @since   0.10.0
 */

/*
 * This file is part of a Pressody module.
 *
 * This Pressody module is free software: you can redistribute it and/or modify it under the terms of the
 * GNU General Public License as published by the Free Software Foundation, either version 2 of the License,
 * or (at your option) any later version.
 *
 * This Pressody module is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY;
 * without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along with this Pressody module.
 * If not, see <https://www.gnu.org/licenses/>.
 *
 * Copyright (c) 2021, 2022 Vlad Olaru (vlad@thinkwritecode.com)
 */

declare ( strict_types=1 );

namespace Pressody\Retailer;

use Defuse\Crypto\Crypto;
use Defuse\Crypto\Key;
use Pressody\Retailer\Exception\CrypterBadFormatException;
use Pressody\Retailer\Exception\CrypterEnvironmentIsBrokenException;
use Pressody\Retailer\Exception\CrypterWrongKeyOrModifiedCiphertextException;

/**
 * String hashes encode and decode provider class.
 *
 * @since 0.9.0
 */
class StringCrypter implements CrypterInterface {

	/**
	 * Defuse encryption key.
	 *
	 * @var Key|null
	 */
	protected ?Key $key;

	public function __construct() {
	}

	/**
	 * Loads a Key from its encoded form.
	 *
	 * @param string $encodedKey The encoded encryption key generated by the `vendor/bin/generate-defuse-key` command.
	 *
	 * @throws CrypterBadFormatException
	 * @throws CrypterEnvironmentIsBrokenException
	 * @return Key
	 */
	public function loadEncryptionKey( string $encodedKey ): Key {
		try {
			$this->key = Key::loadFromAsciiSafeString( $encodedKey );
		} catch ( \Defuse\Crypto\Exception\BadFormatException $bad_format_exception ) {
			throw new CrypterBadFormatException(
				$bad_format_exception->getMessage(),
				$bad_format_exception->getCode(),
				$bad_format_exception
			);
		} catch ( \Defuse\Crypto\Exception\EnvironmentIsBrokenException $environment_is_broken_exception ) {
			throw new CrypterEnvironmentIsBrokenException(
				$environment_is_broken_exception->getMessage(),
				$environment_is_broken_exception->getCode(),
				$environment_is_broken_exception
			);
		}

		return $this->key;
	}

	/**
	 * Encode a given positive int into a string hash.
	 *
	 * @param string $secretString
	 *
	 * @throws CrypterEnvironmentIsBrokenException
	 * @return string The cipher text corresponding to the provided string.
	 */
	public function encrypt( string $secretString ): string {
		if ( empty( $this->key ) ) {
			throw new CrypterEnvironmentIsBrokenException( 'Encryption key not loaded. Load a valid encryption key before trying to encrypt!');
		}

		try {
			return Crypto::encrypt( $secretString, $this->key );
		} catch ( \Defuse\Crypto\Exception\EnvironmentIsBrokenException $environment_is_broken_exception ) {
			throw new CrypterEnvironmentIsBrokenException(
				$environment_is_broken_exception->getMessage(),
				$environment_is_broken_exception->getCode(),
				$environment_is_broken_exception
			);
		}
	}

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
	public function decrypt( string $cipherText ): string {
		if ( empty( $this->key ) ) {
			throw new CrypterEnvironmentIsBrokenException( 'Encryption key not loaded. Load a valid encryption key before trying to decrypt!');
		}

		try {
			return Crypto::decrypt( $cipherText, $this->key );
		} catch ( \Defuse\Crypto\Exception\BadFormatException $bad_format_exception ) {
			throw new CrypterBadFormatException(
				$bad_format_exception->getMessage(),
				$bad_format_exception->getCode(),
				$bad_format_exception
			);
		} catch ( \Defuse\Crypto\Exception\EnvironmentIsBrokenException $environment_is_broken_exception ) {
			throw new CrypterEnvironmentIsBrokenException(
				$environment_is_broken_exception->getMessage(),
				$environment_is_broken_exception->getCode(),
				$environment_is_broken_exception
			);
		} catch ( \Defuse\Crypto\Exception\WrongKeyOrModifiedCiphertextException $wrong_key_or_modified_ciphertext_exception ) {
			throw new CrypterWrongKeyOrModifiedCiphertextException(
				$wrong_key_or_modified_ciphertext_exception->getMessage(),
				$wrong_key_or_modified_ciphertext_exception->getCode(),
				$wrong_key_or_modified_ciphertext_exception
			);
		}
	}
}
