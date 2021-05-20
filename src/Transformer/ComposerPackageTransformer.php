<?php
/**
 * Package transformer.
 *
 * @package PixelgradeLT
 * @license GPL-2.0-or-later
 * @since 0.1.0
 */

declare ( strict_types = 1 );

namespace PixelgradeLT\Retailer\Transformer;

use PixelgradeLT\Retailer\Package;

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
	 * @param Package $solution Package.
	 */
	public function transform( Package $solution );
}
