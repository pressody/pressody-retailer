<?php
/**
 * Views: Repository tab content.
 *
 * @since   1.0.0
 * @license GPL-2.0-or-later
 * @package PixelgradeLT
 */

declare ( strict_types = 1 );

namespace PixelgradeLT\Retailer;

/**
 * @global string $solutions_permalink
 */
?>

<div class="pixelgradelt_retailer-card">
	<p>
		<?php
		printf( __( 'These are <strong>all the solutions</strong> that PixelgradeLT Retailer makes available as Composer packages, regardless of their configuration.<br>
This view is primarily available to assist in <strong>double-checking that things work properly.</strong><br>
If you want to <strong>dig deeper,</strong> check <a href="%s" target="_blank">the actual JSON</a> of the PixelgradeLT Retailer repo.', 'pixelgradelt_retailer' ), esc_url( $solutions_permalink ) ); ?>
	</p>
</div>

<div id="pixelgradelt_retailer-repository-container" class="pixelgradelt_retailer-repository-container"></div>
