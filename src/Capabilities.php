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
	 * Meta capability for administering compositions (aimed at administrators, not regular users).
	 *
	 * @var string
	 */
	const MANAGE_COMPOSITIONS = 'pixelgradelt_retailer_manage_compositions';

	/**
	 * Meta capability for creating compositions.
	 *
	 * @var string
	 */
	const CREATE_COMPOSITIONS = 'pixelgradelt_retailer_create_compositions';

	/**
	 * Primitive capability for viewing compositions.
	 *
	 * @var string
	 */
	const VIEW_COMPOSITIONS = 'pixelgradelt_retailer_view_compositions';

	/**
	 * Meta capability for viewing a specific composition.
	 *
	 * @var string
	 */
	const VIEW_COMPOSITION = 'pixelgradelt_retailer_view_composition';

	/**
	 * Primitive capability for editing compositions.
	 *
	 * @var string
	 */
	const EDIT_COMPOSITIONS = 'pixelgradelt_retailer_edit_compositions';

	/**
	 * Meta capability for editing a specific composition.
	 *
	 * @var string
	 */
	const EDIT_COMPOSITION = 'pixelgradelt_retailer_edit_composition';

	/**
	 * User role for users that are customers (create and manage their own compositions).
	 *
	 * @var string
	 */
	const CUSTOMER_USER_ROLE = 'pixelgradelt_retailer_customer';

	/**
	 * User role for users intended to be used by REST API clients (mainly to fetch solutions).
	 *
	 * @var string
	 */
	const CLIENT_USER_ROLE = 'pixelgradelt_retailer_client';

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
		$wp_roles->add_cap( 'administrator', self::MANAGE_COMPOSITIONS );
		$wp_roles->add_cap( 'administrator', self::CREATE_COMPOSITIONS );
		$wp_roles->add_cap( 'administrator', self::VIEW_COMPOSITIONS );
		$wp_roles->add_cap( 'administrator', self::EDIT_COMPOSITIONS );

		// Create a role for users that are customers (create and manage their own compositions).
		$wp_roles->add_role( self::CUSTOMER_USER_ROLE, 'LT Retailer Customer', [
			self::CREATE_COMPOSITIONS => true,
			self::VIEW_COMPOSITION    => true,
			self::EDIT_COMPOSITION    => true,
		] );

		// Create a special role for users intended to be used by REST API clients.
		$wp_roles->add_role( self::CLIENT_USER_ROLE, 'LT Retailer Client', [
			self::VIEW_SOLUTIONS    => true,
		] );
	}
}
