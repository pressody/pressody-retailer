<?php
/**
 * Views: Solutions tab
 *
 * @since   0.1.0
 * @license GPL-2.0-or-later
 * @package PixelgradeLT
 */

declare ( strict_types=1 );

namespace PixelgradeLT\Retailer;

use PixelgradeLT\Retailer\SolutionType\BaseSolution;

/**
 * @global BaseSolution[] $solutions
 * @global string         $solutions_permalink
 */

$allowed_tags = [
		'a'    => [
				'href' => true,
		],
		'em'   => [],
		'code' => [],
];

if ( ! empty( $solutions ) ) { ?>
	<div class="pixelgradelt_retailer-card">
		<p>
			<?php
			printf( __( 'These are <strong>all the solutions</strong> that PixelgradeLT Retailer makes available as Composer packages, regardless of their configuration.<br>
This view is primarily available to assist in <strong>double-checking that things work properly.</strong><br>
If you want to <strong>dig deeper,</strong> check <a href="%s" target="_blank">the actual JSON</a> of the PixelgradeLT Retailer repo.', 'pixelgradelt_retailer' ), esc_url( $solutions_permalink ) ); ?>
		</p>
	</div>
	<?php

	foreach ( $solutions as $solution ) {
		require $this->plugin->get_path( 'views/solution-details.php' );
	}
} else { ?>
	<div class="pixelgradelt_retailer-card">
		<h3><?php esc_html_e( 'No solutions defined', 'pixelgradelt_retailer' ); ?></h3>
		<p>
			<?php echo wp_kses( __( 'Go to <code>LT Solutions > Add New</code> and start managing your first solution.', 'pixelgradelt_retailer' ), $allowed_tags ); ?>
		</p>
	</div>
	<?php
}
