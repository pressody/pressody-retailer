<?php
/**
 * Capabilities.
 *
 * Meta capabilities are mapped to primitive capabilities in
 * \PixelgradeLT\Retailer\Provider\Capabilities.
 *
 * @since   0.1.0
 * @license GPL-2.0-or-later
 * @package PixelgradeLT
 */

declare ( strict_types=1 );

namespace PixelgradeLT\Retailer;

/**
 * Capabilities.
 *
 * @since 0.1.0
 */
final class Capabilities {

	/**
	 * Primitive capability for viewing solutions.
	 *
	 * @var string
	 */
	const VIEW_SOLUTIONS = 'pixelgradelt_retailer_view_solutions';

	/**
	 * Meta capability for viewing a specific solution.
	 *
	 * @var string
	 */
	const VIEW_SOLUTION = 'pixelgradelt_retailer_view_solution';

	/**
	 * Primitive capability for managing options.
	 *
	 * @var string
	 */
	const MANAGE_OPTIONS = 'pixelgradelt_retailer_manage_options';

	/**
	 * Primitive capability for managing solution types.
	 *
	 * @var string
	 */
	const MANAGE_SOLUTION_TYPES = 'pixelgradelt_retailer_manage_package_types';

	/**
	 * Primitive capability for managing solution categories.
	 *
	 * @var string
	 */
	const MANAGE_SOLUTION_CATEGORIES = 'pixelgradelt_retailer_manage_package_categories';

	/**
	 * Register roles and capabilities.
	 *
	 * @since 0.1.0
	 */
	public static function register() {
		$wp_roles = wp_roles();
		// Add all capabilities to the administrator role.
		$wp_roles->add_cap( 'administrator', self::VIEW_SOLUTIONS );
		$wp_roles->add_cap( 'administrator', self::MANAGE_OPTIONS );
		$wp_roles->add_cap( 'administrator', self::MANAGE_SOLUTION_TYPES );
		$wp_roles->add_cap( 'administrator', self::MANAGE_SOLUTION_CATEGORIES );

		// Create a special role for users intended to be used by clients.
		$wp_roles->add_role( 'pixelgradelt_retailer_client', 'LT Retailer Client', [
			self::VIEW_SOLUTIONS    => true,
		] );
	}
}
