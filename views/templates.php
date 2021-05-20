<?php
/**
 * Underscore.js templates.
 *
 * @package PixelgradeLT
 * @license GPL-2.0-or-later
 * @since 0.1.0
 */

declare( strict_types = 1 );
?>

<script type="text/html" id="tmpl-pixelgradelt_retailer-api-key-table">
	<table class="pixelgradelt_retailer-api-key-table widefat">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Name', 'pixelgradelt_retailer' ); ?></th>
				<th class="column-user"><?php esc_html_e( 'User', 'pixelgradelt_retailer' ); ?></th>
				<th><?php esc_html_e( 'API Key', 'pixelgradelt_retailer' ); ?></th>
				<th><?php esc_html_e( 'Last Used', 'pixelgradelt_retailer' ); ?></th>
				<th><?php esc_html_e( 'Created', 'pixelgradelt_retailer' ); ?></th>
				<th>&nbsp;</th>
			</tr>
		</thead>
		<tbody>
			<tr>
				<td colspan="6">
					<?php esc_html_e( 'Add an API Key to access the PixelgradeLT Retailer repository.', 'pixelgradelt_retailer' ); ?>
				</td>
			</tr>
		</tbody>
		<tfoot>
			<tr>
				<td colspan="6" class="pixelgradelt_retailer-create-api-key-form">
					<label>
						<span class="screen-reader-text"><?php esc_html_e( 'API Key Name', 'pixelgradelt_retailer' ); ?></span>
						<input type="text" id="pixelgradelt_retailer-create-api-key-name" placeholder="<?php esc_attr_e( 'Name', 'pixelgradelt_retailer' ); ?>" class="regular-text">
					</label>
					<button class="button"><?php esc_html_e( 'Create API Key', 'pixelgradelt_retailer' ); ?></button>
					<span class="pixelgradelt_retailer-create-api-key-feedback"></span>
				</td>
			</tr>
		</tfoot>
	</table>
</script>

<script type="text/html" id="tmpl-pixelgradelt_retailer-api-key-table-row">
	<th scope="row">{{ data.name }}</th>
	<td class="column-user">
		<# if ( data.user_edit_link ) { #>
			<a href="{{ data.user_edit_link }}">{{ data.user_login }}</a>
		<# } else { #>
			{{ data.user_login }}
		<# } #>
	</td>
	<td class="column-token">
		<input type="text" class="regular-text" value="{{ data.token }}" readonly>
	</td>
	<td class="column-last-used">{{ data.last_used }}</td>
	<td class="column-created">{{ data.created }}</td>
	<td class="column-actions">
		<div class="pixelgradelt_retailer-dropdown-group">
			<button type="button" class="pixelgradelt_retailer-dropdown-toggle">
				<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 20 20" role="img">
					<path d="M5 10c0 1.1-.9 2-2 2s-2-.9-2-2 .9-2 2-2 2 .9 2 2zm12-2c-1.1 0-2 .9-2 2s.9 2 2 2 2-.9 2-2-.9-2-2-2zm-7 0c-1.1 0-2 .9-2 2s.9 2 2 2 2-.9 2-2-.9-2-2-2z"/>
					<title><?php esc_html_e( 'Toggle dropdown', 'pixelgradelt_retailer' ); ?></title>
				</svg>
			</button>

			<div class="pixelgradelt_retailer-dropdown-group-items right">
				<ul>
					<li><button class="button-link button-link-delete js-revoke"><?php esc_html_e( 'Revoke', 'pixelgradelt_retailer' ); ?></button></li>
				</ul>
			</div>
		</div>
	</td>
</script>

<script type="text/html" id="tmpl-pixelgradelt_retailer-release-actions">
	<table>
		<tr>
			<th scope="row"><label for="pixelgradelt_retailer-release-action-download-url-{{ data.name }}"><?php esc_html_e( 'Download URL', 'pixelgradelt_retailer' ); ?></label></th>
			<td><input type="text" value="{{ data.download_url }}" class="regular-text" readonly="readonly" id="pixelgradelt_retailer-release-action-download-url-{{ data.name }}" /></td>
		</tr>
		<tr>
			<th scope="row"><label for="pixelgradelt_retailer-release-action-require-{{ data.name }}"><?php esc_html_e( 'Require', 'pixelgradelt_retailer' ); ?></label></th>
			<td>
				<input type="text" value='"{{ data.name }}": "{{ data.version }}"' class="regular-text" readonly="readonly" id="pixelgradelt_retailer-release-action-require-{{ data.name }}" /><br>
				<span class="description">
					<em>
						<?php
						/* translators: %s: <code>composer.json</code> */
						printf( esc_html__( 'Copy and paste into %s', 'pixelgradelt_retailer' ), '<code>composer.json</code>' );
						?>
					</em>
				</span>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="pixelgradelt_retailer-release-action-cli-{{ data.name }}"><?php esc_html_e( 'CLI Command', 'pixelgradelt_retailer' ); ?></label></th>
			<td><input type="text" value="composer require {{ data.name }}:{{ data.version }}" class="regular-text" readonly="readonly" id="pixelgradelt_retailer-release-action-cli-{{ data.name }}" /></td>
		</tr>
		<tr>
			<td colspan="2">
				<a href="{{ data.download_url }}" class="button button-primary">
					<?php
					/* translators: %s: version number */
					printf( esc_html__( 'Download %s', 'pixelgradelt_retailer' ), '{{ data.version }}' );
					?>
				</a>
			</td>
		</tr>
	</table>
</script>
