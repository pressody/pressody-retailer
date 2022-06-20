<?php
/**
 * Views: Access tab content.
 *
 * @since   1.0.0
 * @license GPL-2.0-or-later
 * @package Pressody
 */

declare ( strict_types = 1 );

namespace Pressody\Retailer;

$allowed_tags = [
		'a'    => [
				'href' => true,
		],
		'em'   => [],
		'strong'   => [],
		'code' => [],
];
?>
<div class="pressody_retailer-card">
	<p>
		<?php echo wp_kses( __( 'These are a series of system checks to reassure or warn you of <strong>how fit is the webserver for running Pressody Retailer.</strong>', 'pressody_retailer' ), $allowed_tags ); ?>
	</p>
</div>

<div class="pressody_retailer-card">
	<p>
		<?php echo wp_kses( __( 'None right now.', 'pressody_retailer' ), $allowed_tags ); ?>
	</p>
</div>

<div id="pressody_retailer-status"></div>
