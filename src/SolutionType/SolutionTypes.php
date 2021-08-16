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
	 * Regular solution type ID.
	 *
	 * @var string
	 */
	const REGULAR = 'regular';

	/**
	 * Hosting solution type ID.
	 *
	 * @var string
	 */
	const HOSTING = 'hosting';

	const DETAILS = [
		self::REGULAR => [
			'name'        => 'Regular Solution',
			'description' => 'A regular LT solution that provides code-based functionality to a composition.',
		],
		self::HOSTING => [
			'name'        => 'Hosting Solution',
			'description' => 'A hosting LT solution that provides hosting services to a composition.',
		],
	];
}
