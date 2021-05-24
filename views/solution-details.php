<?php
/**
 * Views: Solution details
 *
 * @package PixelgradeLT
 * @license GPL-2.0-or-later
 * @since 0.1.0
 */

declare ( strict_types = 1 );

namespace PixelgradeLT\Retailer;

use PixelgradeLT\Retailer\SolutionType\BaseSolution;

/**
 * @global BaseSolution $solution
 */

$solution_visibility = $solution->get_visibility();
?>

<table class="pixelgradelt_retailer-package widefat">
	<thead>
	<tr>
		<th colspan="2"><?php echo esc_html( $solution->get_name() ); echo 'public' !== $solution_visibility ? ' (' . ucfirst( $solution_visibility ) . ' solution)' : ''; ?></th>
	</tr>
	</thead>
	<tbody>
	<?php
	$description = $solution->get_description();
	if ( $description ) { ?>
		<tr>
			<td colspan="2"><?php echo esc_html( wp_strip_all_tags( $description ) ); ?></td>
		</tr>
	<?php }

	$homepage = $solution->get_homepage();
	if ( ! empty( $homepage ) ) { ?>
		<tr>
			<th><?php esc_html_e( 'Homepage', 'pixelgradelt_retailer' ); ?></th>
			<td><a href="<?php echo esc_url( $homepage ); ?>" target="_blank" rel="noopener noreferer"><?php echo esc_html( $homepage ); ?></a></td>
		</tr>
	<?php }

	$authors = $solution->get_authors();
	if ( ! empty( $authors ) ) { ?>
	<tr>
		<th><?php esc_html_e( 'Authors', 'pixelgradelt_retailer' ); ?></th>
		<td class="package-authors__list" >
		<?php foreach ( $authors as $author ) { ?>
			<a class="package-author" href="<?php echo isset( $author['homepage'] ) ? esc_url( $author['homepage'] ) : '#'; ?>" target="_blank" rel="noopener noreferer"><?php echo esc_html( $author['name'] ); ?></a>
		<?php } ?>
		</td>
	</tr>
	<?php } ?>

	<tr>
		<th><?php esc_html_e( 'Required Packages', 'pixelgradelt_retailer' ); ?></th>
		<td class="pixelgradelt_retailer-required-packages">
			<?php
			if ( $solution->has_required_solutions() ) {
				$requires = array_map(
						function( $required_package ) {
							$solution_name = $required_package['composer_package_name'] . ':' . $required_package['version_range'];
							if ( 'stable' !== $required_package['stability'] ) {
								$solution_name .= '@' . $required_package['stability'];
							}
							return sprintf(
									'<a href="%1$s" target="_blank" class="button pixelgradelt_retailer-required-package">%2$s</a>',
									esc_url( get_edit_post_link( $required_package['managed_post_id'] ) ),
									esc_html( $solution_name ),
							);
						},
						$solution->get_required_solutions()
				);

				echo wp_kses(
						implode( ' ', array_filter( $requires ) ),
						[
								'a' => [
										'class'        => true,
										'href'         => true,
										'target'       => true,
								],
						]
				);
			} else {
				esc_html_e( 'None', 'pixelgradelt_retailer' );
			}
			?>
		</td>
	</tr>
	<tr>
		<th><?php esc_html_e( 'Replaced Packages', 'pixelgradelt_retailer' ); ?></th>
		<td class="pixelgradelt_retailer-required-packages pixelgradelt_retailer-replaced-packages">
			<?php
			if ( $solution->has_replaced_solutions() ) {
				$replaces = array_map(
						function( $replaced_package ) {
							$solution_name = $replaced_package['composer_package_name'] . ':' . $replaced_package['version_range'];
							if ( 'stable' !== $replaced_package['stability'] ) {
								$solution_name .= '@' . $replaced_package['stability'];
							}
							return sprintf(
									'<a href="%1$s" target="_blank" class="button pixelgradelt_retailer-required-package pixelgradelt_retailer-replaced-package">%2$s</a>',
									esc_url( get_edit_post_link( $replaced_package['managed_post_id'] ) ),
									esc_html( $solution_name ),
							);
						},
						$solution->get_replaced_solutions()
				);

				echo wp_kses(
						implode( ' ', array_filter( $replaces ) ),
						[
								'a' => [
										'class'        => true,
										'href'         => true,
										'target'       => true,
								],
						]
				);
			} else {
				esc_html_e( 'None', 'pixelgradelt_retailer' );
			}
			?>
		</td>
	</tr>
	<tr>
		<th><?php esc_html_e( 'Package Type', 'pixelgradelt_retailer' ); ?></th>
		<td><code><?php echo esc_html( $solution->get_type() ); ?></code></td>
	</tr>
	</tbody>
</table>
