<?php
/**
 * List Compositions (All Compositions) screen provider.
 *
 * @since   0.12.0
 * @license GPL-2.0-or-later
 * @package PixelgradeLT
 */

declare ( strict_types=1 );

namespace PixelgradeLT\Retailer\Screen;

use Cedaro\WP\Plugin\AbstractHookProvider;
use PixelgradeLT\Retailer\CompositionManager;
use PixelgradeLT\Retailer\SolutionManager;
use PixelgradeLT\Retailer\SolutionType\SolutionTypes;
use PixelgradeLT\Retailer\Utils\ArrayHelpers;

/**
 * List Compositions screen provider class.
 *
 * @since 0.12.0
 */
class ListCompositions extends AbstractHookProvider {

	/**
	 * Composition manager.
	 *
	 * @var CompositionManager
	 */
	protected CompositionManager $composition_manager;

	/**
	 * Solution manager.
	 *
	 * @var SolutionManager
	 */
	protected SolutionManager $solution_manager;

	/**
	 * Constructor.
	 *
	 * @since 0.12.0
	 *
	 * @param CompositionManager $composition_manager Compositions manager.
	 * @param SolutionManager    $solution_manager    Solutions manager.
	 */
	public function __construct(
		CompositionManager $composition_manager,
		SolutionManager $solution_manager
	) {

		$this->composition_manager = $composition_manager;
		$this->solution_manager    = $solution_manager;
	}

	/**
	 * Register hooks.
	 *
	 * @since 0.12.0
	 */
	public function register_hooks() {
		// Assets.
		add_action( 'load-edit.php', [ $this, 'load_screen' ] );

		// Add custom columns to post list.
		$this->add_action( 'manage_' . $this->composition_manager::POST_TYPE . '_posts_columns', 'add_custom_columns' );
		$this->add_action( 'manage_' . $this->composition_manager::POST_TYPE . '_posts_custom_column', 'populate_custom_columns', 10, 2 );
	}

	/**
	 * Set up the screen.
	 *
	 * @since 0.12.0
	 */
	public function load_screen() {
		$screen = get_current_screen();
		if ( $this->composition_manager::POST_TYPE !== $screen->post_type ) {
			return;
		}

		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
	}

	/**
	 * Enqueue assets.
	 *
	 * @since 0.12.0
	 */
	public function enqueue_assets() {
		wp_enqueue_script( 'pixelgradelt_retailer-admin' );
		wp_enqueue_style( 'pixelgradelt_retailer-admin' );
	}

	protected function add_custom_columns( array $columns ): array {
		$screen = get_current_screen();
		if ( $this->composition_manager::POST_TYPE !== $screen->post_type ) {
			return $columns;
		}

		// Insert after the title columns for dependencies.
		$columns = ArrayHelpers::insertAfterKey( $columns, 'title',
			[
				'composition_status' => esc_html__( 'Status', 'pixelgradelt_retailer' ),
				'composition_hashid' => esc_html__( 'Hashid', 'pixelgradelt_retailer' ),
				'composition_users' => esc_html__( 'Owner(s)', 'pixelgradelt_retailer' ),
				'composition_required_solutions' => esc_html__( 'Contained Solution(s)', 'pixelgradelt_retailer' ),
			]
		);

		return $columns;
	}

	protected function populate_custom_columns( string $column, int $post_id ): void {
		if ( ! in_array( $column, [
			'composition_status',
			'composition_hashid',
			'composition_users',
			'composition_required_solutions',
		] ) ) {
			return;
		}

		$output = '—';

		$composition_data = $this->composition_manager->get_composition_id_data( $post_id );

		if ( 'composition_status' === $column && ! empty( $composition_data['status'] ) ) {
			$output = $composition_data['status'];
		}

		if ( 'composition_hashid' === $column && ! empty( $composition_data['hashid'] ) ) {
			$output = $composition_data['hashid'];
		}

		if ( 'composition_users' === $column && ! empty( $composition_data['users'] ) ) {
			$list = [];
			foreach ( $composition_data['users'] as $composition_user ) {
				if ( 'valid' !== $composition_user['status'] ) {
					$list[] = '#' . $composition_user['id'] . '(invalid)';
					continue;
				}

				$user = get_user_by( 'id', $composition_user['id'] );
				if ( false !== $user ) {
					$list[] = '<a class="composition-user_link package-list_link" href="' . esc_url( get_edit_user_link( $user->ID ) ) . '" title="Edit User Profile">' . $user->get('display_name') . ' (' . $user->get('user_email') . ' #' . $user->ID. ')</a>';
				}
			}

			if ( ! empty( $list ) ) {
				$output = implode( '<br>' . PHP_EOL, $list );
			}
		}

		if ( 'composition_required_solutions' === $column && ! empty( $composition_data['required_solutions'] ) ) {
			$list = [];
			foreach ( $composition_data['required_solutions'] as $solution_details ) {
				$list[] = '<a class="package-list_link" href="' . esc_url( get_edit_post_link( $solution_details['managed_post_id'] ) ) . '" title="Edit Required LT Solution">' . get_the_title( $solution_details['managed_post_id'] ) . ' (' . $solution_details['pseudo_id'] . ')</a>';
			}

			$output = implode( '<br>' . PHP_EOL, $list );
		}

		echo $output;
	}
}
