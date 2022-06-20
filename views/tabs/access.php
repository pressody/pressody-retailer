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

?>
<div class="pressody_retailer-card">
	<p>
		<?php esc_html_e( 'API Keys are used to access your Pressody Records repository and download packages. Your personal API keys appear below or you can create keys for other users by editing their accounts.', 'pressody_retailer' ); ?>
	</p>

	<p>
		<?php
		/* translators: %s: <code>pressody_retailer</code> */
		printf( esc_html__( 'The password for all API Keys is %s. Use the API key as the username.', 'pressody_retailer' ), '<code>pressody_retailer</code>' );
		?>
	</p>
</div>

<div id="pressody_retailer-api-key-manager"></div>

<p>
	<a href="https://github.com/pressody/pressody-retailer/blob/develop/docs/security.md" target="_blank" rel="noopener noreferer"><em><?php esc_html_e( 'Read more about securing your Pressody Records repository.', 'pressody_retailer' ); ?></em></a>
</p>
