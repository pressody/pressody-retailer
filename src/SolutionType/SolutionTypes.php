<?php
/**
 * Solution Types.
 *
 * The slugs MUST BE THE SAME as the types defined by composer/installers.
 * @link https://packagist.org/packages/composer/installers
 *
 * @since   0.1.0
 * @license GPL-2.0-or-later
 * @package Pressody
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

namespace Pressody\Retailer\SolutionType;

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
			'description' => 'A regular PD solution that provides code-based functionality to a composition.',
		],
		self::HOSTING => [
			'name'        => 'Hosting Solution',
			'description' => 'A hosting PD solution that provides hosting services to a composition.',
		],
	];
}
