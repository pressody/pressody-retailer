<?php
/**
 * Pressody Retailer
 *
 * @package Pressody
 * @author  Vlad Olaru <vlad@thinkwritecode.com>
 * @license GPL-2.0-or-later
 *
 * @wordpress-plugin
 * Plugin Name: Pressody Retailer
 * Plugin URI: https://github.com/pressody/pressody-retailer
 * Description: Define and manage Pressody (PD) solutions to be purchased and used on customers' websites. Ensures the connection with WooCommerce.
 * Version: 0.16.0
 * Author: Pressody
 * Author URI: https://getpressody.com/
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: pressody_retailer
 * Domain Path: /languages
 * Requires PHP: 7.4
 * Network: false
 * GitHub Plugin URI: pressody/pressody-retailer
 * Release Asset: true
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

// Exit if accessed directly.
if ( ! \defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Plugin version.
 *
 * @var string
 */
const VERSION = '0.16.0';

// Load the Composer autoloader.
if ( file_exists( __DIR__ . '/vendor/autoload.php' ) ) {
	require __DIR__ . '/vendor/autoload.php';
}

// Display a notice and bail if dependencies are missing.
if ( ! function_exists( __NAMESPACE__ . '\autoloader_classmap' ) ) {
	require_once __DIR__ . '/src/functions.php';
	add_action( 'admin_notices', __NAMESPACE__ . '\display_missing_dependencies_notice' );

	return;
}

// Autoload mapped classes.
spl_autoload_register( __NAMESPACE__ . '\autoloader_classmap' );

// Load the environment variables.
// We use immutable since we don't want to overwrite variables already set.
$dotenv = \Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();
$dotenv->required( 'PDRETAILER_PHP_AUTH_USER' )->notEmpty();
$dotenv->required( 'PDRETAILER_ENCRYPTION_KEY' )->notEmpty();
// Read environment variables from the $_ENV array also.
\Env\Env::$options |= \Env\Env::USE_ENV_ARRAY;

// Load the WordPress plugin administration API.
require_once ABSPATH . 'wp-admin/includes/plugin.php';

// Create a container and register a service provider.
$pressody_retailer_container = new Container();
$pressody_retailer_container->register( new ServiceProvider() );

// Initialize the plugin and inject the container.
$pressody_retailer = plugin()
	->set_basename( plugin_basename( __FILE__ ) )
	->set_directory( plugin_dir_path( __FILE__ ) )
	->set_file( __DIR__ . '/pressody-retailer.php' )
	->set_slug( 'pressody-retailer' )
	->set_url( plugin_dir_url( __FILE__ ) )
	->define_constants()
	->set_container( $pressody_retailer_container )
	->register_hooks( $pressody_retailer_container->get( 'hooks.activation' ) )
	->register_hooks( $pressody_retailer_container->get( 'hooks.deactivation' ) )
	->register_hooks( $pressody_retailer_container->get( 'hooks.authentication' ) );

add_action( 'plugins_loaded', [ $pressody_retailer, 'compose' ], 5 );
