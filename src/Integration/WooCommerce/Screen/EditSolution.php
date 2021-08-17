<?php
/**
 * Edit Solution screen provider.
 *
 * @since   0.14.0
 * @license GPL-2.0-or-later
 * @package PixelgradeLT
 */

declare ( strict_types=1 );

namespace PixelgradeLT\Retailer\Integration\WooCommerce\Screen;

use Carbon_Fields\Carbon_Fields;
use Carbon_Fields\Container;
use Carbon_Fields\Field;
use Cedaro\WP\Plugin\AbstractHookProvider;
use PixelgradeLT\Retailer\SolutionManager;
use PixelgradeLT\Retailer\Repository\PackageRepository;
use PixelgradeLT\Retailer\Utils\ArrayHelpers;
use function Pixelgrade\WPPostNotes\create_note;

/**
 * Edit Solution screen provider class.
 *
 * @since 0.14.0
 */
class EditSolution extends AbstractHookProvider {

	/**
	 * Solution manager.
	 *
	 * @since 0.14.0
	 *
	 * @var SolutionManager
	 */
	protected SolutionManager $solution_manager;

	/**
	 * Solutions repository.
	 *
	 * @since 0.14.0
	 *
	 * @var PackageRepository
	 */
	protected PackageRepository $solutions;

	/**
	 * User messages to display in the WP admin.
	 *
	 * @since 0.14.0
	 *
	 * @var array
	 */
	protected array $user_messages = [
		'error'   => [],
		'warning' => [],
		'info'    => [],
	];

	/**
	 * Constructor.
	 *
	 * @since 0.14.0
	 *
	 * @param SolutionManager   $solution_manager Solutions manager.
	 * @param PackageRepository $solutions        Solutions repository.
	 */
	public function __construct(
		SolutionManager $solution_manager,
		PackageRepository $solutions
	) {
		$this->solution_manager = $solution_manager;
		$this->solutions        = $solutions;
	}

	/**
	 * Register hooks.
	 *
	 * @since 0.14.0
	 */
	public function register_hooks() {
		// Assets.
		add_action( 'load-post.php', [ $this, 'load_screen' ] );
		add_action( 'load-post-new.php', [ $this, 'load_screen' ] );

		// Logic.

		// ADD CUSTOM POST META VIA CARBON FIELDS.
		$this->add_action( 'plugins_loaded', 'carbonfields_load' );
		// Hook earlier than the main EditSolution screen provider to put the metabox above the rest.
		$this->add_action( 'carbon_fields_register_fields', 'attach_post_meta_fields', 9 );

		// Show edit post screen error messages.
		$this->add_action( 'edit_form_top', 'check_solution_post', 5 );
		$this->add_filter( 'pixelgradelt_retailer/editsolution_show_user_messages', 'filter_user_messages', 10, 2 );

		/*
		 * HANDLE POST UPDATE CHANGES.
		 */
		$this->add_action( 'pixelgradelt_retailer/ltsolution/update', 'handle_post_update', 10, 3 );

		/*
		 * HANDLE AUTOMATIC POST NOTES.
		 */
		$this->add_action( 'pixelgradelt_retailer/ltsolution/woocommerce_products_change', 'add_solution_products_change_note', 10, 3 );
	}

	/**
	 * Set up the screen.
	 *
	 * @since 0.14.0
	 */
	public function load_screen() {
		$screen = get_current_screen();
		if ( $this->solution_manager::POST_TYPE !== $screen->post_type ) {
			return;
		}

		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
	}

	/**
	 * Enqueue assets.
	 *
	 * @since 0.14.0
	 */
	public function enqueue_assets() {
		wp_enqueue_style( 'pixelgradelt_retailer-admin' );
	}

	/**
	 * @since 0.14.0
	 */
	protected function carbonfields_load() {
		Carbon_Fields::boot();
	}

	/**
	 * @since 0.14.0
	 */
	protected function attach_post_meta_fields() {
		// Register the metabox for managing the general details of the solution.
		Container::make( 'post_meta', 'carbon_fields_container_woocommerce_configuration_' . $this->solution_manager::POST_TYPE, esc_html__( 'WooCommerce Configuration', 'pixelgradelt_retailer' ) )
		         ->where( 'post_type', '=', $this->solution_manager::POST_TYPE )
		         ->set_context( 'normal' )
		         ->set_priority( 'core' )
		         ->add_fields( [
			         Field::make( 'html', 'solution_woocommerce_products_html', __( 'Section Description', 'pixelgradelt_retailer' ) )
			              ->set_html( sprintf( '<p class="description">%s</p>', __( 'Configure details about <strong>the solution\'s integration with WooCommerce products.</strong>', 'pixelgradelt_retailer' ) ) ),

			         Field::make( 'multiselect', 'solution_woocommerce_products', __( 'Linked Products', 'pixelgradelt_retailer' ) )
			              ->set_help_text( __( 'This could be a URL to a page that presents details about this solution.', 'pixelgradelt_retailer' ) )
			              ->set_options( [ $this, 'get_available_products_options' ] )
			              ->set_default_value( [] )
			              ->set_required( false )
			              ->set_width( 50 ),
		         ] );
	}

	/**
	 * Get the options list for the Linked Products multiselect.
	 *
	 * @since 0.14.0
	 *
	 * @return array
	 */
	public function get_available_products_options(): array {
		$options = [];

		/** @var \WC_Product $product */
		foreach ( $this->get_available_products() as $product ) {
			$options[ $product->get_id() ] = sprintf( __( '%s - #%s', 'pixelgradelt_retailer' ), $product->get_name(), $product->get_id() );
		}

		ksort( $options );

		return $options;
	}

	/**
	 * Get the products list used for the Linked Products multiselect.
	 *
	 * @since 0.14.0
	 *
	 * @return array
	 */
	protected function get_available_products(): array {

		// We exclude all the products that other solutions are already linked to,
		// since we want 1-to-many relationships between solutions and products.
		$exclude_post_ids = [ get_the_ID(), ];

		$query_args = [
			'status'  => 'publish',
			'exclude' => [],
			'limit'   => - 1,
			'orderby' => 'date',
			'order'   => 'DESC',
			'return'  => 'objects',
		];

		return wc_get_products( $query_args );
	}

	/**
	 * Check the solution post for possible issues, so the user is aware of them.
	 *
	 * @since 0.14.0
	 *
	 * @param \WP_Post The current post object.
	 */
	protected function check_solution_post( \WP_Post $post ) {
		if ( $this->solution_manager::POST_TYPE !== get_post_type( $post ) || 'auto-draft' === get_post_status( $post ) ) {
			return;
		}

		// Get the linked products and check if they are still OK.
		// We rely on the fact that CarbonFields will automatically clean/remove non-existent options on field load.
		$solution_data               = $this->solution_manager->get_solution_id_data( $post->ID );
		$invalid_linked_products_ids = array_diff( $solution_data['woocommerce_products_raw'], $solution_data['woocommerce_products'] );
		if ( count( $invalid_linked_products_ids ) ) {
			$message = '<p>' . __( 'Some <strong>WooCommerce linked products have become unavailable</strong> since the last save: %1$s.<br>Usually, this happens when a product is no longer "Published" (like being trashed or converted to draft or private).<br><strong>IMPORTANT: They have been automatically unlinked!</strong> If this is what you intended, not worries. Just hit "Update" to get rid of this warning.', 'pixelgradelt_retailer' ) . '</p>';

			$product_list = [];
			foreach ( $invalid_linked_products_ids as $invalid_linked_products_id ) {
				$product = wc_get_product( $invalid_linked_products_id );
				if ( empty( $product ) ) {
					// The product was probably trashed. Just include the post ID.
					$product_list[] = '#' . $invalid_linked_products_id;
				} else {
					$product_list[] = '<a href="' . esc_url( get_edit_post_link( $invalid_linked_products_id ) ) . '">' . $product->get_name() . ' #' . $invalid_linked_products_id . '</a>';
				}
			}

			$message = sprintf( $message, implode( ', ', $product_list ) );

			$this->add_user_message( 'warning', $message );
		}
	}

	/**
	 * Add a certain user message type to the list for later display.
	 *
	 * @since 0.14.0
	 *
	 * @param $type
	 * @param $message
	 */
	protected function add_user_message( $type, $message ) {
		if ( ! in_array( $type, [ 'error', 'warning', 'info' ] ) ) {
			return;
		}

		if ( empty( $this->user_messages[ $type ] ) ) {
			$this->user_messages[ $type ] = [];
		}
		$this->user_messages[ $type ][] = $message;
	}

	/**
	 * Filter user messages for display at the top of the post edit screen.
	 *
	 * @since 0.14.0
	 *
	 * @param array|mixed $messages The filtered messages list.
	 * @param \WP_Post The current post object.
	 *
	 * @return array|mixed The modified user messages.
	 */
	protected function filter_user_messages( $messages, \WP_Post $post ) {
		// If somebody short-circuited before, oblige.
		if ( ! is_array( $messages ) ) {
			return $messages;
		}

		if ( $this->solution_manager::POST_TYPE !== get_post_type( $post ) || 'auto-draft' === get_post_status( $post ) ) {
			return $messages;
		}

		if ( empty( $this->user_messages ) ) {
			return $messages;
		}

		if ( ! empty( $this->user_messages['error'] ) ) {
			if ( empty( $messages['error'] ) ) {
				$messages['error'] = [];
			}

			array_push( $messages['error'], ...$this->user_messages['error'] );
		}

		if ( ! empty( $this->user_messages['warning'] ) ) {
			if ( empty( $messages['warning'] ) ) {
				$messages['warning'] = [];
			}

			array_push( $messages['warning'], ...$this->user_messages['warning'] );
		}

		if ( ! empty( $this->user_messages['info'] ) ) {
			if ( empty( $messages['info'] ) ) {
				$messages['info'] = [];
			}

			array_push( $messages['info'], ...$this->user_messages['info'] );
		}

		return $messages;
	}

	/**
	 * Handle post update changes.
	 *
	 * @since 0.14.0
	 *
	 * @param int   $post_id      The solution post ID.
	 * @param array $new_solution The new solution data.
	 * @param array $old_solution The old solution data.
	 */
	protected function handle_post_update( int $post_id, array $new_solution, array $old_solution ) {

		/*
		 * Handle solution products change.
		 */
		if ( ! empty( $old_solution['woocommerce_products'] ) ) {
			// We will use a serialized compare to make sure we catch actual changes.
			$old_products = $old_solution['woocommerce_products'];
			sort( $old_products );
			$old_products_raw = $old_solution['woocommerce_products_raw'];
			sort( $old_products_raw );
			$new_products = $new_solution['woocommerce_products'];
			sort( $new_products );

			// This means we've had some sort of automatic products removal (probably due to invalid products).
			if ( serialize( $old_products ) !== serialize( $old_products_raw ) ) {
				// We will use the raw from now on, so we account for automatically removed products.
				$old_products = $old_products_raw;
			}

			if ( serialize( $old_products ) !== serialize( $new_products ) ) {
				/**
				 * Fires on LT solution WooCommerce linked products change.
				 *
				 * @since 0.14.0
				 *
				 * @param int   $post_id         The solution post ID.
				 * @param array $new_product_ids The new solution WooCommerce product IDs.
				 * @param array $old_product_ids The old solution WooCommerce product IDs.
				 * @param array $new_solution    The new solution data.
				 */
				do_action( 'pixelgradelt_retailer/ltsolution/woocommerce_products_change',
					$post_id,
					$new_products,
					$old_products,
					$new_solution
				);
			}
		}
	}

	/**
	 * Add post note on LT solution products change.
	 *
	 * @since 0.14.0
	 *
	 * @param int   $post_id         The solution post ID.
	 * @param array $new_product_ids The new solution WooCommerce product IDs.
	 * @param array $old_product_ids The old solution WooCommerce product IDs.
	 */
	protected function add_solution_products_change_note( int $post_id, array $new_product_ids, array $old_product_ids ) {

		sort( $old_product_ids );
		sort( $new_product_ids );

		$added   = array_diff( $new_product_ids, $old_product_ids );
		$removed = array_diff( $old_product_ids, $new_product_ids );

		$note = '';
		if ( ! empty( $removed ) ) {
			$removed_list = [];
			foreach ( $removed as $removed_post_id ) {
				$product = wc_get_product( $removed_post_id );
				if ( empty( $product ) ) {
					// The product was probably trashed. Just include the post ID.
					$removed_list[] = '#' . $removed_post_id;
				} else {
					$removed_list[] = '<a href="' . esc_url( get_edit_post_link( $removed_post_id ) ) . '">' . $product->get_name() . ' #' . $removed_post_id . '</a>';
				}
			}

			$note .= sprintf(
				esc_html__( 'Removed these linked WooCommerce products: %1$s. ', 'pixelgradelt_retailer' ),
				'<strong>' . implode( ', ', $removed_list ) . '</strong>'
			);
		}

		if ( ! empty( $added ) ) {
			$added_list = [];
			foreach ( $added as $added_post_id ) {
				$product = wc_get_product( $added_post_id );
				if ( empty( $product ) ) {
					// The product was probably trashed. Just include the post ID.
					$added_list[] = '#' . $added_post_id;
				} else {
					$added_list[] = '<a href="' . esc_url( get_edit_post_link( $added_post_id ) ) . '">' . $product->get_name() . ' #' . $added_post_id . '</a>';
				}
			}

			$note .= sprintf(
				esc_html__( 'Added the following linked WooCommerce products: %1$s. ', 'pixelgradelt_retailer' ),
				'<strong>' . implode( ', ', $added_list ) . '</strong>'
			);
		}

		if ( ! empty( trim( $note ) ) ) {
			create_note( $post_id, $note, 'internal', true );
		}
	}
}
