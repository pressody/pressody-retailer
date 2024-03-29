<?php
/**
 * Base solution.
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

use Pressody\Retailer\Package;

/**
 * Base solution class.
 *
 * @since 0.1.0
 */
class BaseSolution implements \ArrayAccess, Package {
	/**
	 * Solution name.
	 *
	 * @var string
	 */
	protected string $name = '';

	/**
	 * Solution type.
	 *
	 * @see SolutionTypes
	 *
	 * @var string
	 */
	protected string $type = '';

	/**
	 * Solution slug.
	 *
	 * @var string
	 */
	protected string $slug = '';

	/**
	 * Solution authors, each, potentially, having: `name`, `email`, `homepage`, `role`.
	 *
	 * @var array
	 */
	protected array $authors = [];

	/**
	 * Description.
	 *
	 * @var string
	 */
	protected string $description = '';

	/**
	 * Solution homepage URL.
	 *
	 * @var string
	 */
	protected string $homepage = '';

	/**
	 * Solution license.
	 *
	 * @var string
	 */
	protected string $license = '';

	/**
	 * Solution categories.
	 *
	 * @var string[]
	 */
	protected array $categories = [];

	/**
	 * Solution keywords.
	 *
	 * @var string[]
	 */
	protected array $keywords = [];

	/**
	 * Is managed package?
	 *
	 * @var bool
	 */
	protected bool $is_managed = false;

	/**
	 * Package post ID if this is a managed package.
	 *
	 * @var int
	 */
	protected int $managed_post_id = 0;

	/**
	 * Package visibility.
	 *
	 * @var string
	 */
	protected string $visibility = '';

	/**
	 * PD Records Parts required by this solution.
	 *
	 * @var array
	 */
	protected array $required_pdrecords_parts = [];

	/**
	 * A Composer config `require` entry.
	 *
	 * This will be merged with the required solutions and other hard-coded packages to generate the final require config.
	 *
	 * @var array
	 */
	protected array $composer_require = [];

	/**
	 * Solutions required by this solution.
	 *
	 * @var array
	 */
	protected array $required_solutions = [];

	/**
	 * Solutions excluded when this solution is included/required in a composition.
	 *
	 * @var array
	 */
	protected array $excluded_solutions = [];

	/**
	 * The Composer package name (including vendor).
	 *
	 * @var string
	 */
	protected string $composer_package_name = '';

	/**
	 * Magic setter.
	 *
	 * @since 0.1.0
	 *
	 * @param string $name  Property name.
	 * @param mixed  $value Property value.
	 */
	public function __set( string $name, $value ) {
		// Don't allow undefined properties to be set.
	}

	/**
	 * Retrieve the name.
	 *
	 * @since 0.1.0
	 *
	 * @return string
	 */
	public function get_name(): string {
		return $this->name;
	}

	/**
	 * Retrieve the package type.
	 *
	 * @since 0.1.0
	 *
	 * @return string
	 */
	public function get_type(): string {
		return $this->type;
	}

	/**
	 * Retrieve the slug.
	 *
	 * @since 0.1.0
	 *
	 * @return string
	 */
	public function get_slug(): string {
		return $this->slug;
	}

	/**
	 * Retrieve the authors.
	 *
	 * @since 0.1.0
	 *
	 * @return array
	 */
	public function get_authors(): array {
		return $this->authors;
	}

	/**
	 * Retrieve the description.
	 *
	 * @since 0.1.0
	 *
	 * @return string
	 */
	public function get_description(): string {
		return $this->description;
	}

	/**
	 * Retrieve the homepage URL.
	 *
	 * @since 0.1.0
	 *
	 * @return string
	 */
	public function get_homepage(): string {
		return $this->homepage;
	}

	/**
	 * Retrieve the license.
	 *
	 * @since 0.1.0
	 *
	 * @return string
	 */
	public function get_license(): string {
		return $this->license;
	}

	/**
	 * Retrieve the categories.
	 *
	 * @since 0.1.0
	 *
	 * @return string[]
	 */
	public function get_categories(): array {
		return $this->categories;
	}

	/**
	 * Retrieve the keywords.
	 *
	 * @since 0.1.0
	 *
	 * @return string[]
	 */
	public function get_keywords(): array {
		return $this->keywords;
	}

	/**
	 * Whether the package is managed by us.
	 *
	 * @since 0.1.0
	 *
	 * @return bool
	 */
	public function is_managed(): bool {
		return $this->is_managed;
	}

	/**
	 * Alias for self::is_managed().
	 *
	 * @since 0.1.0
	 *
	 * @return bool
	 */
	public function get_is_managed(): bool {
		return $this->is_managed();
	}

	/**
	 * Retrieve the managed post ID.
	 *
	 * @since 0.1.0
	 *
	 * @return int
	 */
	public function get_managed_post_id(): int {
		return $this->managed_post_id;
	}

	/**
	 * Get the visibility status of the package (public, draft, private).
	 *
	 * @since 0.1.0
	 *
	 * @return string The visibility status of the package. One of: public, draft, private.
	 */
	public function get_visibility(): string {
		return $this->visibility;
	}

	/**
	 * Retrieve the required PD Records Parts.
	 *
	 * @since 0.1.0
	 *
	 * @return array
	 */
	public function get_required_pdrecords_parts(): array {
		return $this->required_pdrecords_parts;
	}

	/**
	 * Whether the solution has any required PD Records Parts.
	 *
	 * @since 0.1.0
	 *
	 * @return bool
	 */
	public function has_required_pdrecords_parts(): bool {
		return ! empty( $this->required_pdrecords_parts );
	}

	/**
	 * Retrieve the Composer config `require` entry.
	 *
	 * @since 0.1.0
	 *
	 * @return array
	 */
	public function get_composer_require(): array {
		return $this->composer_require;
	}

	/**
	 * Retrieve the required solutions.
	 *
	 * @since 0.1.0
	 *
	 * @return array
	 */
	public function get_required_solutions(): array {
		return $this->required_solutions;
	}

	/**
	 * Retrieve the required packages.
	 *
	 * This is an alias for get_required_solutions()
	 *
	 * @since 0.1.0
	 *
	 * @return array
	 */
	public function get_required_packages(): array {
		return $this->get_required_solutions();
	}

	/**
	 * Whether the solution has any required solutions.
	 *
	 * @since 0.1.0
	 *
	 * @return bool
	 */
	public function has_required_solutions(): bool {
		return ! empty( $this->required_solutions );
	}

	/**
	 * Retrieve the excluded solutions.
	 *
	 * @since 0.1.0
	 *
	 * @return array
	 */
	public function get_excluded_solutions(): array {
		return $this->excluded_solutions;
	}

	/**
	 * Whether the solution has any excluded solutions.
	 *
	 * @since 0.1.0
	 *
	 * @return bool
	 */
	public function has_excluded_solutions(): bool {
		return ! empty( $this->excluded_solutions );
	}

	/**
	 * Retrieve the Composer package name.
	 *
	 * @since 0.1.0
	 *
	 * @return string
	 */
	public function get_composer_package_name(): string {
		return $this->composer_package_name;
	}

	/**
	 * Whether a property exists.
	 *
	 * Checks for an accessor method rather than the actual property.
	 *
	 * @since 0.1.0
	 *
	 * @param string $name Property name.
	 *
	 * @return bool
	 */
	public function offsetExists( $name ): bool {
		return method_exists( $this, "get_{$name}" );
	}

	/**
	 * Retrieve a property value.
	 *
	 * @since 0.1.0
	 *
	 * @param string $name Property name.
	 *
	 * @return mixed
	 */
	public function offsetGet( $name ) {
		$method = "get_{$name}";

		if ( ! method_exists( $this, $method ) ) {
			return null;
		}

		return $this->$method();
	}

	/**
	 * Set a property value.
	 *
	 * @since 0.1.0
	 *
	 * @param string $name  Property name.
	 * @param array  $value Property value.
	 */
	public function offsetSet( $name, $value ) {
		// Prevent properties from being modified.
	}

	/**
	 * Unset a property.
	 *
	 * @since 0.1.0
	 *
	 * @param string $name Property name.
	 */
	public function offsetUnset( $name ) {
		// Prevent properties from being modified.
	}
}
