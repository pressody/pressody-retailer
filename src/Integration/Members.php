<?php
/**
 * Members plugin integration.
 *
 * @package Pressody
 * @license GPL-2.0-or-later
 * @since 0.1.0
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

declare ( strict_types = 1 );

namespace Pressody\Retailer\Integration;

use Cedaro\WP\Plugin\AbstractHookProvider;
use Pressody\Retailer\Capabilities;

/**
 * Members plugin integration provider class.
 *
 * @since 0.1.0
 */
class Members extends AbstractHookProvider {
	/**
	 * Register hooks.
	 *
	 * @since 0.1.0
	 */
	public function register_hooks() {
		add_action( 'members_register_cap_groups', [ $this, 'register_capability_group' ] );
		add_action( 'members_register_caps', [ $this, 'register_capabilities' ] );
	}

	/**
	 * Register a capability group for the Members plugin.
	 *
	 * @since 0.1.0
	 *
	 * @link https://wordpress.org/plugins/members/
	 */
	public function register_capability_group() {
		members_register_cap_group(
			'pressody_retailer',
			[
				'label'    => esc_html__( 'Pressody Retailer', 'pressody_retailer' ),
				'caps'     => [],
				'icon'     => 'dashicons-admin-generic',
				'priority' => 50,
			]
		);
	}

	/**
	 * Register capabilities for the Members plugin.
	 *
	 * @since 0.1.0
	 *
	 * @link https://wordpress.org/plugins/members/
	 */
	public function register_capabilities() {

		members_register_cap(
			Capabilities::VIEW_SOLUTIONS,
			[
				'label' => esc_html__( 'View Solutions', 'pressody_retailer' ),
				'group' => 'pressody_retailer',
			]
		);

		members_register_cap(
			Capabilities::MANAGE_OPTIONS,
			[
				'label' => esc_html__( 'Manage Options', 'pressody_retailer' ),
				'group' => 'pressody_retailer',
			]
		);

		members_register_cap(
			Capabilities::MANAGE_SOLUTION_TYPES,
			[
				'label' => esc_html__( 'Manage Solution Types', 'pressody_retailer' ),
				'group' => 'pressody_retailer',
			]
		);

		members_register_cap(
			Capabilities::MANAGE_SOLUTION_CATEGORIES,
			[
				'label' => esc_html__( 'Manage Solution Categories', 'pressody_retailer' ),
				'group' => 'pressody_retailer',
			]
		);
	}
}
