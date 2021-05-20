<?php
/**
 * List Solutions (All Solutions) screen provider.
 *
 * @since   0.1.0
 * @license GPL-2.0-or-later
 * @package PixelgradeLT
 */

declare ( strict_types=1 );

namespace PixelgradeLT\Retailer\Screen;

use Cedaro\WP\Plugin\AbstractHookProvider;
use PixelgradeLT\Retailer\SolutionManager;
use PixelgradeLT\Retailer\SolutionType\SolutionTypes;
use PixelgradeLT\Retailer\Utils\ArrayHelpers;

/**
 * List Solutions screen provider class.
 *
 * @since 0.1.0
 */
class ListSolutions extends AbstractHookProvider {

	/**
	 * Solution manager.
	 *
	 * @var SolutionManager
	 */
	protected SolutionManager $package_manager;

	/**
	 * Constructor.
	 *
	 * @since 0.1.0
	 *
	 * @param SolutionManager $package_manager Packages manager.
	 */
	public function __construct(
		SolutionManager $package_manager
	) {
		$this->package_manager   = $package_manager;
	}

	/**
	 * Register hooks.
	 *
	 * @since 0.1.0
	 */
	public function register_hooks() {
		// Assets.
		add_action( 'load-edit.php', [ $this, 'load_screen' ] );

		// Logic.
		// Show a dropdown to filter the posts list by the custom taxonomy.
		$this->add_action( 'restrict_manage_posts', 'output_admin_list_filters' );

		// Add custom columns to post list.
		$this->add_action( 'manage_' . $this->package_manager::POST_TYPE . '_posts_columns', 'add_custom_columns' );
		$this->add_action( 'manage_' . $this->package_manager::POST_TYPE . '_posts_custom_column', 'populate_custom_columns', 10, 2);
	}

	/**
	 * Output filters in the All Posts screen.
	 *
	 * @param string $post_type The current post type.
	 */
	protected function output_admin_list_filters( string $post_type ) {
		if ( $this->package_manager::POST_TYPE !== $post_type ) {
			return;
		}

		$taxonomy = get_taxonomy( $this->package_manager::TYPE_TAXONOMY );

		wp_dropdown_categories( array(
			'show_option_all' => sprintf( __( 'All %s', 'pixelgradelt_retailer' ), $taxonomy->label ),
			'orderby'         => 'term_id',
			'order'           => 'ASC',
			'hide_empty'      => false,
			'hide_if_empty'   => true,
			'selected'        => filter_input( INPUT_GET, $taxonomy->query_var, FILTER_SANITIZE_STRING ),
			'hierarchical'    => false,
			'name'            => $taxonomy->query_var,
			'taxonomy'        => $taxonomy->name,
			'value_field'     => 'slug',
		) );
	}

	/**
	 * Set up the screen.
	 *
	 * @since 0.1.0
	 */
	public function load_screen() {
		$screen = get_current_screen();
		if ( $this->package_manager::POST_TYPE !== $screen->post_type ) {
			return;
		}

		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
	}

	/**
	 * Enqueue assets.
	 *
	 * @since 0.1.0
	 */
	public function enqueue_assets() {
		wp_enqueue_script( 'pixelgradelt_retailer-admin' );
		wp_enqueue_style( 'pixelgradelt_retailer-admin' );
	}

	protected function add_custom_columns( array $columns ): array {
		$screen = get_current_screen();
		if ( $this->package_manager::POST_TYPE !== $screen->post_type ) {
			return $columns;
		}

		// Insert after the title a column for package source details.
		$columns = ArrayHelpers::insertAfterKey( $columns, 'title', [ 'package_source' => esc_html__( 'Package Source', 'pixelgradelt_retailer' ) ] );

		return $columns;
	}

	protected function populate_custom_columns( string $column, int $post_id ): void {
		// Package Source column
		if ( 'package_source' === $column ) {
			$source_output = '—';
			// Add details to the title regarding the package configured source.
			$package_data = $this->package_manager->get_solution_id_data( $post_id );
			if ( ! empty( $package_data ) && ! empty( $package_data['source_type'] ) ) {
				switch ( $package_data['source_type'] ) {
					case 'packagist.org':
						$source_output = 'Packagist.org - ' . $package_data['source_name'];
						break;
					case 'wpackagist.org':
						$source_output = 'WPackagist.org - ' . $package_data['source_name'];
						break;
					case 'vcs':
						if ( false !== strpos( $package_data['vcs_url'], 'github.com' ) ) {
							$source_output = 'Github - ';
						} else {
							$source_output = 'VCS - ';
						}

						$source_output .= $package_data['source_name'];
						break;
					case 'local.plugin':
						$source_output = 'Local Plugin - ' . $package_data['slug'];
						break;
					case 'local.theme':
						$source_output = 'Local Theme - ' . $package_data['slug'];
						break;
					case 'local.manual':
						if ( SolutionTypes::THEME === $package_data['type'] ) {
							$source_output = 'Manual Theme - ' . $package_data['slug'];
						} else {
							$source_output = 'Manual Plugin - ' . $package_data['slug'];
						}
						break;
					default:
						// Nothing
						break;
				}
			}

			echo $source_output;
		}
	}
}
