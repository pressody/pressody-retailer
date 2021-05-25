<?php
/**
 * Views: Composer tab content.
 *
 * @since   1.0.0
 * @license GPL-2.0-or-later
 * @package PixelgradeLT
 */

declare ( strict_types = 1 );

namespace PixelgradeLT\Retailer;

use PixelgradeLT\Retailer\SolutionType\BaseSolution;

/**
 * @global string $solutions_permalink
 */
?>

<div class="pixelgradelt_retailer-card">
	<p>
		<?php esc_html_e( 'Your PixelgradeLT Retailer repository is available at:', 'pixelgradelt_retailer' ); ?>
		<a href="<?php echo esc_url( $solutions_permalink ); ?>"><?php echo esc_html( $solutions_permalink ); ?></a>. This includes <strong>all your packages, regardless of type.</strong>
	</p>
</div>
<p>
	<?php
	// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Need to update global variable.
	$allowed_html = [ 'code' => [] ];
	printf(
		/* translators: 1: <code>repositories</code>, 2: <code>composer.json</code> */
		esc_html__( 'Add it to the %1$s list in your %2$s:', 'pixelgradelt_retailer' ),
		'<code>repositories</code>',
		'<code>composer.json</code>'
	);
	?>
</p>

<pre class="pixelgradelt_retailer-composer-snippet"><code>{
	"repositories": {
		"pixelgradelt-retailer": {
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
	esc_html__( 'Or run the %1$s command:', 'pixelgradelt_retailer' ),
	'<code>config</code>'
);
?>

<p>
	<input
		type="text"
		class="pixelgradelt_retailer-cli-field large-text"
		readonly
		value="composer config repositories.pixelgradelt-retailer composer <?php echo esc_url( get_solutions_permalink( [ 'base' => true ] ) ); ?>"
		onclick="this.select();"
	>
</p>
