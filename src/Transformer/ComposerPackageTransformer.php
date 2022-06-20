<?php
/**
 * Package transformer.
 *
 * @package Pressody
 * @license GPL-2.0-or-later
 * @since 0.1.0
 */

declare ( strict_types = 1 );

namespace Pressody\Retailer\Transformer;

use Pressody\Retailer\Package;

/**
 * Package transformer interface.
 *
 * @since 0.1.0
 */
interface ComposerPackageTransformer {
	/**
	 * Transform a package.
	 *
	 * @since 0.1.0
	 *
	 * @param Package $package Package.
	 */
	public function transform( Package $package );
}
