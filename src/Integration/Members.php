<?php
/**
 * Members plugin integration.
 *
 * @package PixelgradeLT
 * @license GPL-2.0-or-later
 * @since 0.1.0
 */

declare ( strict_types = 1 );

namespace PixelgradeLT\Retailer\Integration;

use Cedaro\WP\Plugin\AbstractHookProvider;
use PixelgradeLT\Retailer\Capabilities;

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
			'pixelgradelt_retailer',
			[
				'label'    => esc_html__( 'PixelgradeLT Retailer', 'pixelgradelt_retailer' ),
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
				'label' => esc_html__( 'View Solutions', 'pixelgradelt_retailer' ),
				'group' => 'pixelgradelt_retailer',
			]
		);

		members_register_cap(
			Capabilities::MANAGE_OPTIONS,
			[
				'label' => esc_html__( 'Manage Options', 'pixelgradelt_retailer' ),
				'group' => 'pixelgradelt_retailer',
			]
		);

		members_register_cap(
			Capabilities::MANAGE_SOLUTION_TYPES,
			[
				'label' => esc_html__( 'Manage Solution Types', 'pixelgradelt_retailer' ),
				'group' => 'pixelgradelt_retailer',
			]
		);

		members_register_cap(
			Capabilities::MANAGE_SOLUTION_CATEGORIES,
			[
				'label' => esc_html__( 'Manage Solution Categories', 'pixelgradelt_retailer' ),
				'group' => 'pixelgradelt_retailer',
			]
		);
	}
}
