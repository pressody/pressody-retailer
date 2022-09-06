<?php
/**
 * Capabilities.
 *
 * Meta capabilities are mapped to primitive capabilities in
 * \Pressody\Retailer\Provider\Capabilities.
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

namespace Pressody\Retailer;

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
	const VIEW_SOLUTIONS = 'pressody_retailer_view_solutions';

	/**
	 * Meta capability for viewing a specific solution.
	 *
	 * @var string
	 */
	const VIEW_SOLUTION = 'pressody_retailer_view_solution';

	/**
	 * Primitive capability for managing options.
	 *
	 * @var string
	 */
	const MANAGE_OPTIONS = 'pressody_retailer_manage_options';

	/**
	 * Primitive capability for managing solution types.
	 *
	 * @var string
	 */
	const MANAGE_SOLUTION_TYPES = 'pressody_retailer_manage_package_types';

	/**
	 * Primitive capability for managing solution categories.
	 *
	 * @var string
	 */
	const MANAGE_SOLUTION_CATEGORIES = 'pressody_retailer_manage_package_categories';

	/**
	 * Meta capability for administering compositions (aimed at administrators, not regular users).
	 *
	 * @var string
	 */
	const MANAGE_COMPOSITIONS = 'pressody_retailer_manage_compositions';

	/**
	 * Meta capability for creating compositions.
	 *
	 * @var string
	 */
	const CREATE_COMPOSITIONS = 'pressody_retailer_create_compositions';

	/**
	 * Primitive capability for viewing compositions.
	 *
	 * @var string
	 */
	const VIEW_COMPOSITIONS = 'pressody_retailer_view_compositions';

	/**
	 * Meta capability for viewing a specific composition.
	 *
	 * @var string
	 */
	const VIEW_COMPOSITION = 'pressody_retailer_view_composition';

	/**
	 * Primitive capability for editing compositions.
	 *
	 * @var string
	 */
	const EDIT_COMPOSITIONS = 'pressody_retailer_edit_compositions';

	/**
	 * Meta capability for editing a specific composition.
	 *
	 * @var string
	 */
	const EDIT_COMPOSITION = 'pressody_retailer_edit_composition';

	/**
	 * Primitive capability for deleting compositions.
	 *
	 * @var string
	 */
	const DELETE_COMPOSITIONS = 'pressody_retailer_delete_compositions';

	/**
	 * Meta capability for deleting a specific composition.
	 *
	 * @var string
	 */
	const DELETE_COMPOSITION = 'pressody_retailer_delete_composition';

	/**
	 * User role for users that are customers (create and manage their own compositions).
	 *
	 * @var string
	 */
	const CUSTOMER_USER_ROLE = 'pressody_retailer_customer';

	/**
	 * User role for users intended to be used by REST API clients (mainly to fetch solutions).
	 *
	 * @var string
	 */
	const CLIENT_USER_ROLE = 'pressody_retailer_client';

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
		$wp_roles->add_cap( 'administrator', self::DELETE_COMPOSITIONS );

		// Create a role for users that are customers (create and manage their own compositions).
		// First, make sure that we clean it up to avoid leftover capabilities.
		$wp_roles->remove_role( self::CUSTOMER_USER_ROLE );
		$wp_roles->add_role( self::CUSTOMER_USER_ROLE, esc_html__( 'PD Retailer Customer', 'pressody_retailer' ) );
		// Add the needed capabilities.
		$wp_roles->add_cap( self::CUSTOMER_USER_ROLE, self::VIEW_SOLUTIONS );

		$wp_roles->add_cap( self::CUSTOMER_USER_ROLE, self::CREATE_COMPOSITIONS );
		$wp_roles->add_cap( self::CUSTOMER_USER_ROLE, self::VIEW_COMPOSITIONS );
		$wp_roles->add_cap( self::CUSTOMER_USER_ROLE, self::EDIT_COMPOSITIONS );
		$wp_roles->add_cap( self::CUSTOMER_USER_ROLE, self::DELETE_COMPOSITIONS );

		// Create a special role for users intended to be used by REST API clients.
		// First, make sure that we clean it up to avoid leftover capabilities.
		$wp_roles->remove_role( self::CLIENT_USER_ROLE );
		$wp_roles->add_role( self::CLIENT_USER_ROLE, esc_html__( 'PD Retailer Client', 'pressody_retailer' ) );
		// Add the needed capabilities.
		$wp_roles->add_cap( self::CLIENT_USER_ROLE, self::VIEW_SOLUTIONS );
	}
}
