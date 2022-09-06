<?php
/**
 * Assets provider.
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

namespace Pressody\Retailer\Provider;

use Cedaro\WP\Plugin\AbstractHookProvider;

/**
 * Assets provider class.
 *
 * @since 0.1.0
 */
class AdminAssets extends AbstractHookProvider {
	/**
	 * Register hooks.
	 */
	public function register_hooks() {
		add_action( 'admin_enqueue_scripts', [ $this, 'register_assets' ], 1 );
		add_filter( 'script_loader_tag', [ $this, 'filter_script_type' ], 10, 3 );
	}

	/**
	 * Register scripts and styles.
	 *
	 * @since 0.1.0
	 */
	public function register_assets() {
		wp_register_script(
			'pressody_retailer-admin',
			$this->plugin->get_url( 'assets/js/admin.js' ),
			[ 'jquery' ],
			'20210524',
			true
		);

		wp_register_script(
			'pressody_retailer-access',
			$this->plugin->get_url( 'assets/js/access.js' ),
			[ 'wp-components', 'wp-data', 'wp-data-controls', 'wp-element', 'wp-i18n' ],
			'20210211',
			true
		);

		wp_set_script_translations(
			'pressody_retailer-access',
			'pressody_retailer',
			$this->plugin->get_path( 'languages' )
		);

		wp_register_script(
			'pressody_retailer-repository',
			$this->plugin->get_url( 'assets/js/repository.js' ),
			[ 'wp-components', 'wp-data', 'wp-data-controls', 'wp-element', 'wp-i18n' ],
			'20210211',
			true
		);

		wp_set_script_translations(
			'pressody_retailer-repository',
			'pressody_retailer',
			$this->plugin->get_path( 'languages' )
		);

		wp_register_script(
			'pressody_retailer-edit-solution',
			$this->plugin->get_url( 'assets/js/edit-solution.js' ),
			[ 'wp-components', 'wp-data', 'wp-data-controls', 'wp-element', 'wp-i18n' ],
			'20210524',
			true
		);

		wp_set_script_translations(
			'pressody_retailer-edit-solution',
			'pressody_retailer',
			$this->plugin->get_path( 'languages' )
		);

		wp_register_script(
			'pressody_retailer-edit-composition',
			$this->plugin->get_url( 'assets/js/edit-composition.js' ),
			[ 'wp-components', 'wp-data', 'wp-data-controls', 'wp-element', 'wp-i18n' ],
			'20210524',
			true
		);

		wp_set_script_translations(
			'pressody_retailer-edit-composition',
			'pressody_retailer',
			$this->plugin->get_path( 'languages' )
		);

		wp_register_style(
			'pressody_retailer-admin',
			$this->plugin->get_url( 'assets/css/admin.css' ),
			[ 'wp-components' ],
			'20210210'
		);
	}

	/**
	 * Filter script tag type attributes.
	 *
	 * @since 1.0.0
	 *
	 * @param string $tag    Script tag HTML.
	 * @param string $handle Script identifier.
	 * @return string
	 */
	public function filter_script_type( string $tag, string $handle ): string {
		$modules = [
			'pressody_retailer-access',
			'pressody_retailer-repository',
			'pressody_retailer-edit-solution',
			'pressody_retailer-edit-composition',
		];

		if ( in_array( $handle, $modules, true ) ) {
			$tag = str_replace( '<script', '<script type="module"', $tag );
		}

		return $tag;
	}
}
