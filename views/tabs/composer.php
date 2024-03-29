<?php
/**
 * Views: Composer tab content.
 *
 * @since   1.0.0
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

declare ( strict_types = 1 );

namespace Pressody\Retailer;

use Pressody\Retailer\SolutionType\BaseSolution;

/**
 * @global string $solutions_permalink
 */
?>

<div class="pressody_retailer-card">
	<p>
		<?php esc_html_e( 'Your Pressody Retailer repository is available at:', 'pressody_retailer' ); ?>
		<a href="<?php echo esc_url( $solutions_permalink ); ?>"><?php echo esc_html( $solutions_permalink ); ?></a>. This includes <strong>all your packages, regardless of type.</strong>
	</p>
</div>
<p>
	<?php
	// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Need to update global variable.
	$allowed_html = [ 'code' => [] ];
	printf(
		/* translators: 1: <code>repositories</code>, 2: <code>composer.json</code> */
		esc_html__( 'Add it to the %1$s list in your %2$s:', 'pressody_retailer' ),
		'<code>repositories</code>',
		'<code>composer.json</code>'
	);
	?>
</p>

<pre class="pressody_retailer-composer-snippet"><code>{
	"repositories": {
		"pressody-retailer": {
			"type": "composer",
			"url": "<?php echo esc_url( get_solutions_permalink( [ 'base' => true ] ) ); ?>"
		}
	}
}</code></pre>

<?php
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Need to update global variable.
$allowed_html = [ 'code' => [] ];
printf(
	/* translators: 1: <code>config</code> */
	esc_html__( 'Or run the %1$s command:', 'pressody_retailer' ),
	'<code>config</code>'
);
?>

<p>
	<input
		type="text"
		class="pressody_retailer-cli-field large-text"
		readonly
		value="composer config repositories.pressody-retailer composer <?php echo esc_url( get_solutions_permalink( [ 'base' => true ] ) ); ?>"
		onclick="this.select();"
	>
</p>
