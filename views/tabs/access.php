<?php
/**
 * Views: Access tab content.
 *
 * @since   1.0.0
 * @license GPL-2.0-or-later
 * @package PixelgradeLT
 */

declare ( strict_types = 1 );

namespace PixelgradeLT\Retailer;

?>
<div class="pixelgradelt_retailer-card">
	<p>
		<?php esc_html_e( 'API Keys are used to access your PixelgradeLT Records repository and download packages. Your personal API keys appear below or you can create keys for other users by editing their accounts.', 'pixelgradelt_retailer' ); ?>
	</p>

	<p>
		<?php
		/* translators: %s: <code>pixelgradelt_retailer</code> */
		printf( esc_html__( 'The password for all API Keys is %s. Use the API key as the username.', 'pixelgradelt_retailer' ), '<code>pixelgradelt_retailer</code>' );
		?>
	</p>
</div>

<div id="pixelgradelt_retailer-api-key-manager"></div>

<p>
	<a href="https://github.com/pixelgradelt/pixelgradelt-retailer/blob/develop/docs/security.md" target="_blank" rel="noopener noreferer"><em><?php esc_html_e( 'Read more about securing your PixelgradeLT Records repository.', 'pixelgradelt_retailer' ); ?></em></a>
</p>
