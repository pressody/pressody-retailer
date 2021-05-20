<?php
/**
 * Views: Settings page
 *
 * @package PixelgradeLT
 * @license GPL-2.0-or-later
 * @since 0.1.0
 */

declare ( strict_types = 1 );

namespace PixelgradeLT\Retailer;

use PixelgradeLT\Retailer\SolutionType\BaseSolution;

/**
 * @global BaseSolution[] $solutions
 * @global string         $solutions_permalink
 * @global array          $system_checks
 */

?>
<div class="wrap">
	<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
	<h2 class="nav-tab-wrapper">
		<a href="#pixelgradelt_retailer-settings" class="nav-tab nav-tab-active"><?php esc_html_e( 'Settings', 'pixelgradelt_retailer' ); ?></a>
		<a href="#pixelgradelt_retailer-solutions" class="nav-tab"><?php esc_html_e( 'Solutions', 'pixelgradelt_retailer' ); ?></a>
		<a href="#pixelgradelt_retailer-status" class="nav-tab"><?php esc_html_e( 'Status', 'pixelgradelt_retailer' ); ?></a>
	</h2>

	<div id="pixelgradelt_retailer-settings" class="pixelgradelt_retailer-tab-panel is-active">
		<p>
			<?php esc_html_e( 'Your PixelgradeLT Retailer repository is available at:', 'pixelgradelt_retailer' ); ?>
			<a href="<?php echo esc_url( $solutions_permalink ); ?>"><?php echo esc_html( $solutions_permalink ); ?></a>
		</p>
		<p>
			<?php
			// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Need to update global variable.
			$allowed_html = [ 'code' => [] ];
			printf(
				/* translators: 1: <code>repositories</code>, 2: <code>composer.json</code> */
				esc_html__( 'Add it to the %1$s list in your project\'s %2$s, like so:', 'pixelgradelt_retailer' ),
				'<code>repositories</code>',
				'<code>composer.json</code>'
			);
			?>
		</p>

		<pre class="pixelgradelt_retailer-repository-snippet"><code>{
	"repositories": [
		{
			"type": "composer",
			"url": "<?php echo esc_url( get_solutions_permalink( [ 'base' => true ] ) ); ?>"
		}
	]
}</code></pre>

		<form action="<?php echo esc_url( admin_url( 'options.php' ) ); ?>" method="post">
			<?php settings_fields( 'pixelgradelt_retailer' ); ?>
			<?php do_settings_sections( 'pixelgradelt_retailer' ); ?>
			<?php submit_button(); ?>
		</form>
	</div>

	<div id="pixelgradelt_retailer-solutions" class="pixelgradelt_retailer-tab-panel">
		<?php require $this->plugin->get_path( 'views/solutions.php' ); ?>
	</div>

	<div id="pixelgradelt_retailer-status" class="pixelgradelt_retailer-tab-panel">
		<?php require $this->plugin->get_path( 'views/status.php' ); ?>
	</div>
</div>
