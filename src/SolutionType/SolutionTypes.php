<?php
/**
 * Solution Types.
 *
 * The slugs MUST BE THE SAME as the types defined by composer/installers.
 * @link https://packagist.org/packages/composer/installers
 *
 * @since   0.1.0
 * @license GPL-2.0-or-later
 * @package PixelgradeLT
 */

declare ( strict_types=1 );

namespace PixelgradeLT\Retailer\SolutionType;

/**
 * Solution Types.
 *
 * @since 0.1.0
 */
final class SolutionTypes {
	/**
	 * Basic solution type ID.
	 *
	 * @var string
	 */
	const BASIC = 'basic';

	const DETAILS = [
		self::BASIC => [
			'name'        => 'Basic Solution',
			'description' => 'A basic LT solution.',
		],
	];
}
