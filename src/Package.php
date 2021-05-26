<?php
/**
 * General package interface.
 *
 * @package PixelgradeLT
 * @license GPL-2.0-or-later
 * @since 0.1.0
 */

declare ( strict_types = 1 );

namespace PixelgradeLT\Retailer;

/**
 * Package interface.
 *
 * This is different from Composer\Package\PackageInterface. This is an interface for our internal use.
 *
 * @since 0.1.0
 */
interface Package {
	/**
	 * Retrieve the name.
	 *
	 * @since 0.1.0
	 *
	 * @return string
	 */
	public function get_name(): string;

	/**
	 * Retrieve the package type.
	 *
	 * @since 0.1.0
	 *
	 * @return string
	 */
	public function get_type(): string;

	/**
	 * Retrieve the slug.
	 *
	 * @since 0.1.0
	 *
	 * @return string
	 */
	public function get_slug(): string;

	/**
	 * Retrieve the authors.
	 *
	 * @since 0.1.0
	 *
	 * @return array
	 */
	public function get_authors(): array;

	/**
	 * Retrieve the description.
	 *
	 * @since 0.1.0
	 *
	 * @return string
	 */
	public function get_description(): string;

	/**
	 * Retrieve the homepage URL.
	 *
	 * @since 0.1.0
	 *
	 * @return string
	 */
	public function get_homepage(): string;

	/**
	 * Retrieve the package license.
	 *
	 * @since 0.1.0
	 *
	 * @return string
	 */
	public function get_license(): string;

	/**
	 * Retrieve the keywords.
	 *
	 * @since 0.1.0
	 *
	 * @return string[]
	 */
	public function get_keywords(): array;

	/**
	 * Whether the package is managed by us.
	 *
	 * @since 0.1.0
	 *
	 * @return bool
	 */
	public function is_managed(): bool;

	/**
	 * Retrieve the managed post ID.
	 *
	 * @since 0.5.0
	 *
	 * @return int
	 */
	public function get_managed_post_id(): int;

	/**
	 * Get the visibility status of the package (public, draft, private).
	 *
	 * @since 0.9.0
	 *
	 * @return string The visibility status of the package. One of: public, draft, private.
	 */
	public function get_visibility(): string;

	/**
	 * Retrieve the Composer config `require` entry.
	 *
	 * @since 0.9.0
	 *
	 * @return array
	 */
	public function get_composer_require(): array;

	/**
	 * Retrieve the managed required solutions.
	 *
	 * @since 0.8.0
	 *
	 * @return array
	 */
	public function get_required_solutions(): array;

	/**
	 * Whether the package has any managed required packages.
	 *
	 * @since 0.8.0
	 *
	 * @return bool
	 */
	public function has_required_solutions(): bool;
}
