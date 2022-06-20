<?php
/**
 * String hashes interface.
 *
 * @package Pressody
 * @license GPL-2.0-or-later
 * @since   0.11.0
 */

declare ( strict_types=1 );

namespace Pressody\Retailer;

/**
 * String hashes interface.
 *
 * @since 0.11.0
 */
interface HasherInterface {

	public function __construct(
		string $salt,
		int $minHashLength,
		string $alphabet
	);

	/**
	 * Encode a given positive int into a string hash.
	 *
	 * @param int $number
	 *
	 * @return string The string hash corresponding to the provided positive int.
	 */
	public function encode( int $number ): string;

	/**
	 * Decode a string hash into the original positive int.
	 *
	 * @param string $hash
	 *
	 * @return int The decoded number or zero on failure.
	 */
	public function decode( string $hash ): int;
}
