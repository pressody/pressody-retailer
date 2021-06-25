<?php
/**
 * Edit Composition screen provider.
 *
 * @since   0.11.0
 * @license GPL-2.0-or-later
 * @package PixelgradeLT
 */

declare ( strict_types=1 );

namespace PixelgradeLT\Retailer\Screen;

use Carbon_Fields\Carbon_Fields;
use Carbon_Fields\Container;
use Carbon_Fields\Field;
use Cedaro\WP\Plugin\AbstractHookProvider;
use PixelgradeLT\Retailer\CompositionManager;
use PixelgradeLT\Retailer\Transformer\ComposerPackageTransformer;
use PixelgradeLT\Retailer\SolutionManager;
use PixelgradeLT\Retailer\Repository\PackageRepository;
use PixelgradeLT\Retailer\Utils\ArrayHelpers;
use function PixelgradeLT\Retailer\ensure_packages_json_url;
use function PixelgradeLT\Retailer\get_setting;
use function PixelgradeLT\Retailer\get_solutions_permalink;
use function PixelgradeLT\Retailer\local_rest_call;
use function PixelgradeLT\Retailer\preload_rest_data;

/**
 * Edit Composition screen provider class.
 *
 * @since 0.11.0
 */
class EditComposition extends AbstractHookProvider {

	const LTRECORDS_API_PWD = 'pixelgradelt_records';

	const PSEUDO_ID_DELIMITER = ' #';

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
	 * Solutions repository.
	 *
	 * @var PackageRepository
	 */
	protected PackageRepository $solutions;

	/**
	 * Composer package transformer.
	 *
	 * @var ComposerPackageTransformer
	 */
	protected ComposerPackageTransformer $composer_transformer;

	/**
	 * User messages to display in the WP admin.
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
	 * @since 0.11.0
	 *
	 * @param CompositionManager         $composition_manager  Compositions manager.
	 * @param SolutionManager            $solution_manager     Solutions manager.
	 * @param PackageRepository          $solutions            Solutions repository.
	 * @param ComposerPackageTransformer $composer_transformer Solution transformer.
	 */
	public function __construct(
		CompositionManager $composition_manager,
		SolutionManager $solution_manager,
		PackageRepository $solutions,
		ComposerPackageTransformer $composer_transformer
	) {

		$this->composition_manager  = $composition_manager;
		$this->solution_manager     = $solution_manager;
		$this->solutions            = $solutions;
		$this->composer_transformer = $composer_transformer;
	}

	/**
	 * Register hooks.
	 *
	 * @since 0.11.0
	 */
	public function register_hooks() {
		// Assets.
		add_action( 'load-post.php', [ $this, 'load_screen' ] );
		add_action( 'load-post-new.php', [ $this, 'load_screen' ] );

		// Logic.

		// Rearrange the core metaboxes.
		$this->add_action( 'add_meta_boxes_' . $this->composition_manager::POST_TYPE, 'add_solution_current_state_meta_box', 10 );

		// ADD CUSTOM POST META VIA CARBON FIELDS.
		$this->add_action( 'plugins_loaded', 'carbonfields_load' );
		$this->add_action( 'carbon_fields_register_fields', 'attach_post_meta_fields' );

		// Check that the package can be resolved with the required packages.
		$this->add_action( 'carbon_fields_post_meta_container_saved', 'fill_hashid', 10, 2 );
		$this->add_action( 'carbon_fields_post_meta_container_saved', 'check_required', 20, 2 );

		// We get early so we can show error messages.
		//		$this->add_action( 'plugins_loaded', 'get_ltrecords_parts', 20 );
		// Show edit post screen error messages.
		$this->add_action( 'edit_form_top', 'check_composition_post', 5 );
		$this->add_action( 'edit_form_top', 'show_user_messages', 50 );

		// Add a message to the post publish metabox.
		$this->add_action( 'post_submitbox_start', 'show_publish_message' );
	}

	/**
	 * Set up the screen.
	 *
	 * @since 0.11.0
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
	 * @since 0.11.0
	 */
	public function enqueue_assets() {
		wp_enqueue_script( 'pixelgradelt_retailer-admin' );
		wp_enqueue_style( 'pixelgradelt_retailer-admin' );

		wp_enqueue_script( 'pixelgradelt_retailer-edit-composition' );

		// Gather all the contained solutions in the composition.
		$solutions        = [];
		$solutionsContext = [];

		$composition_data = $this->composition_manager->get_composition_id_data( get_the_ID(), self::PSEUDO_ID_DELIMITER, true );
		foreach ( $composition_data['required_solutions'] as $required_solution ) {
			$package = $this->solutions->first_where( [
				'managed_post_id' => $required_solution['managed_post_id'],
			] );
			if ( empty( $package ) ) {
				continue;
			}

			$solutions[]                                               = $package;
			$solutionsContext[ $package->get_composer_package_name() ] = $required_solution['context'];
		}

		// Get the encrypted form of the composition user details.
		$encrypted_user = local_rest_call( '/pixelgradelt_retailer/v1/compositions/encrypt_user_details', 'POST', [], [
			'userid'        => $composition_data['user']['id'],
			'compositionid' => $composition_data['hashid'],
			'extra'         => [
				'email'    => $composition_data['user']['email'],
				'username' => $composition_data['user']['username'],
			],
		] );
		if ( ! is_string( $encrypted_user ) ) {
			// This means there was an error. Maybe the user details failed validation, etc.
			$encrypted_user = '';
		}

		wp_localize_script(
			'pixelgradelt_retailer-edit-composition',
			'_pixelgradeltRetailerEditCompositionData',
			[
				'editedPostId'     => get_the_ID(),
				'editedHashId'     => $composition_data['hashid'],
				'encryptedUser'    => $encrypted_user,
				'solutionIds'      => array_map( function ( $solution ) {
					return $solution->get_managed_post_id();
				}, $solutions ),
				'solutionContexts' => $solutionsContext,
				'ltrecordsCompositionsUrl' => 'https://lt-records.local/wp-json/pixelgradelt_records/v1/compositions',
				'ltrecordsApiKey' => get_setting( 'ltrecords-api-key' ),
				'ltrecordsApiPwd' => self::LTRECORDS_API_PWD,
			]
		);

		$preload_paths = [
			'/pixelgradelt_retailer/v1/solutions',
		];

		preload_rest_data( $preload_paths );
	}

	protected function carbonfields_load() {
		Carbon_Fields::boot();
	}

	protected function attach_post_meta_fields() {
		// Register the metabox for managing the general details of the solution.
		Container::make( 'post_meta', 'carbon_fields_container_general_configuration_' . $this->composition_manager::POST_TYPE, esc_html__( 'General Configuration', 'pixelgradelt_retailer' ) )
		         ->where( 'post_type', '=', $this->composition_manager::POST_TYPE )
		         ->set_context( 'normal' )
		         ->set_priority( 'core' )
		         ->add_fields( [
			         Field::make( 'html', 'composition_details_html', __( 'Section Description', 'pixelgradelt_retailer' ) )
			              ->set_html( sprintf( '<p class="description">%s</p>', __( 'Configure details about <strong>the composition itself,</strong> as it will be exposed for consumption.<br>
Under normal circumstances, <strong>compositions are created and details updated programmatically</strong> on user actions (purchases, expirations, cancellations, etc.).<br><br>
<em><strong>FYI:</strong> These details are meta-details for <strong>the actual Composer compositions</strong> (<code>composer.json</code> contents) <strong>generated and updated by LT Records.</strong><br>
&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<strong>LT Records will query LT Retailer</strong> to check and provide update instructions, based on the information below.</em>', 'pixelgradelt_retailer' ) ) ),

			         Field::make( 'select', 'composition_status', __( 'Composition Status', 'pixelgradelt_retailer' ) )
			              ->set_help_text( __( 'The composition status will determine if and when this composition is available to be used on sites.<br>
The <code>ready</code> status denotes that all is good with the composition and it can be used on sites. The <code>active</code> status means that a <code>ready</code> composition is actively used on a site.', 'pixelgradelt_retailer' ) )
			              ->add_options( array(
				              'not_ready' => esc_html__( 'Not Ready', 'pixelgradelt_retailer' ),
				              'ready'     => esc_html__( 'Ready', 'pixelgradelt_retailer' ),
				              'active'    => esc_html__( 'Active', 'pixelgradelt_retailer' ),
				              'inactive'  => esc_html__( 'Inactive', 'pixelgradelt_retailer' ),
			              ) )
			              ->set_default_value( 'not_ready' )
			              ->set_required( true )
			              ->set_width( 50 ),

			         Field::make( 'text', 'composition_hashid', __( 'Composition HashID', 'pixelgradelt_retailer' ) )
			              ->set_help_text( __( 'This will be used to <strong>uniquely identify the composition throughout the ecosystem.</strong> Be careful when changing this after a composition is used on a site.<br>
<strong>Leave empty</strong> and we will fill it with a hash generated from the post ID. You should <strong>leave it as such</strong> if you don\'t have a good reason to change it!', 'pixelgradelt_retailer' ) ),

			         Field::make( 'html', 'composition_user_details_html', __( 'Section Description', 'pixelgradelt_retailer' ) )
			              ->set_html( sprintf( '<p class="description">%s</p>', __( 'Provide as many details for identifying the user this composition belongs to.', 'pixelgradelt_retailer' ) ) ),

			         Field::make( 'text', 'composition_user_id', __( 'Composition User ID', 'pixelgradelt_retailer' ) )
			              ->set_help_text( __( 'This is the numeric ID of the user this composition belongs to.', 'pixelgradelt_retailer' ) )
			              ->set_attribute( 'type', 'number' )
			              ->set_required( false )
			              ->set_width( 25 ),
			         Field::make( 'text', 'composition_user_email', __( 'Composition User Email', 'pixelgradelt_retailer' ) )
			              ->set_help_text( __( 'This is the email of the user this composition belongs to.', 'pixelgradelt_retailer' ) )
			              ->set_attribute( 'type', 'email' )
			              ->set_required( false )
			              ->set_width( 25 ),
			         Field::make( 'text', 'composition_user_username', __( 'Composition User Username', 'pixelgradelt_retailer' ) )
			              ->set_help_text( __( 'This is the username of the user this composition belongs to.', 'pixelgradelt_retailer' ) )
			              ->set_required( false )
			              ->set_width( 25 ),
		         ] );

		// Register the metabox for managing the solutions the current composition contains.
		Container::make( 'post_meta', 'carbon_fields_container_solutions_configuration_' . $this->composition_manager::POST_TYPE, esc_html__( 'Contained Solutions Configuration', 'pixelgradelt_retailer' ) )
		         ->where( 'post_type', '=', $this->composition_manager::POST_TYPE )
		         ->set_context( 'normal' )
		         ->set_priority( 'core' )
		         ->add_fields( [
			         Field::make( 'html', 'solutions_configuration_html', __( 'Solutions Description', 'pixelgradelt_retailer' ) )
			              ->set_html( sprintf( '<p class="description">%s</p>', __( 'Here you edit and configure <strong>the list of solutions</strong> the current composition contains/requires.', 'pixelgradelt_retailer' ) ) ),

			         Field::make( 'complex', 'composition_required_solutions', __( 'Contained Solutions', 'pixelgradelt_retailer' ) )
			              ->set_help_text( __( 'These are <strong>the LT solutions part of this composition.</strong> A composition is normally created/updated when <strong>a user purchases a set of solutions</strong> for a site.<br>
Since each solution is tied to a e-commerce product, each solution here is tied to <strong>an order and a specific item in that order.</strong><br>
<strong>FYI:</strong> Each solution label is comprised of the solution <code>slug</code> and the <code>#post_id</code>.', 'pixelgradelt_retailer' ) )
			              ->set_classes( 'composition-required-solutions' )
			              ->set_collapsed( true )
			              ->add_fields( [
				              Field::make( 'select', 'pseudo_id', __( 'Choose one of the configured solutions', 'pixelgradelt_retailer' ) )
				                   ->set_options( [ $this, 'get_available_required_solutions_options' ] )
				                   ->set_default_value( null )
				                   ->set_required( true )
				                   ->set_width( 50 ),
				              Field::make( 'text', 'order_id', __( 'Order ID', 'pixelgradelt_retailer' ) )
				                   ->set_help_text( __( 'This is the order ID through which this solution was purchased.', 'pixelgradelt_retailer' ) )
				                   ->set_attribute( 'type', 'number' )
				                   ->set_required( false )
				                   ->set_width( 25 ),
				              Field::make( 'text', 'order_item_id', __( 'Order Item ID', 'pixelgradelt_retailer' ) )
				                   ->set_help_text( __( 'This is the order item ID matching the e-commerce product tied to the solution.', 'pixelgradelt_retailer' ) )
				                   ->set_attribute( 'type', 'number' )
				                   ->set_required( false )
				                   ->set_width( 25 ),
			              ] )
			              ->set_header_template( '
										    <% if (pseudo_id) { %>
										        <%- pseudo_id %>
										        <% if (order_id) { %>
											        (Order #<%- order_id %> → item #<%= order_item_id %>)
											    <% } %>
										    <% } %>
										' ),
		         ] );
	}

	public function add_solution_current_state_meta_box() {
		$post_type    = $this->composition_manager::POST_TYPE;
		$container_id = $post_type . '_current_state_details';
		add_meta_box(
			$container_id,
			esc_html__( 'Current Composition State Details', 'pixelgradelt_retailer' ),
			array( $this, 'display_composition_current_state_meta_box' ),
			$this->composition_manager::POST_TYPE,
			'normal',
			'core'
		);

		add_filter( "postbox_classes_{$post_type}_{$container_id}", [
			$this,
			'add_composition_current_state_box_classes',
		] );
	}

	/**
	 * Classes to add to the post meta box
	 *
	 * @param array $classes
	 *
	 * @return array
	 */
	public function add_composition_current_state_box_classes( array $classes ): array {
		$classes[] = 'carbon-box';

		return $classes;
	}

	/**
	 * Display Composition Current State meta box
	 *
	 * @param \WP_Post $post
	 */
	public function display_composition_current_state_meta_box( \WP_Post $post ) {
		// Wrap it for spacing.
		echo '<div class="cf-container"><div class="cf-field">';
		echo '<p>The below information tries to paint a picture of <strong>how this composition will behave</strong> when used on an actual site.</p>';
		require $this->plugin->get_path( 'views/composition-state.php' );
		echo '</div></div>';
	}

	/**
	 * Fill the composition_hashid field if left empty.
	 *
	 * @param int                           $post_ID
	 * @param Container\Post_Meta_Container $meta_container
	 */
	protected function fill_hashid( int $post_ID, Container\Post_Meta_Container $meta_container ) {
		// At the moment, we are only interested in the source_configuration container.
		// This way we avoid running this logic unnecessarily for other containers.
		if ( empty( $meta_container->get_id() ) || 'carbon_fields_container_general_configuration_' . $this->composition_manager::POST_TYPE !== $meta_container->get_id() ) {
			return;
		}

		if ( empty( carbon_get_post_meta( $post_ID, 'composition_hashid' ) ) ) {
			carbon_set_post_meta( $post_ID, 'composition_hashid', $this->composition_manager->hash_encode_id( $post_ID ) );
		}
	}

	/**
	 * Check if the composition can be resolved by Composer with contained solutions.
	 * Show a warning message if it can't be.
	 *
	 * @param int                           $post_ID
	 * @param Container\Post_Meta_Container $meta_container
	 */
	protected function check_required( int $post_ID, Container\Post_Meta_Container $meta_container ) {
		// At the moment, we are only interested in the solutions_configuration container.
		// This way we avoid running this logic unnecessarily for other containers.
		if ( empty( $meta_container->get_id() ) || 'carbon_fields_container_solutions_configuration_' . $this->composition_manager::POST_TYPE !== $meta_container->get_id() ) {
			return;
		}

		// Gather all the contained solutions in the composition.
		$solutions                      = [];
		$composition_required_solutions = $this->composition_manager->get_post_composition_required_solutions( $post_ID, self::PSEUDO_ID_DELIMITER );
		foreach ( $composition_required_solutions as $composition_solution ) {
			$package = $this->solutions->first_where( [
				'managed_post_id' => $composition_solution['managed_post_id'],
			] );
			if ( empty( $package ) ) {
				continue;
			}

			$solutions[] = $package;
		}

		if ( empty( $solutions ) ) {
			update_post_meta( $post_ID, '_package_require_dry_run_result', '' );

			return;
		}

		// Transform the solutions in the Composer format.
		foreach ( $solutions as $key => $solution ) {
			$solutions[ $key ] = $this->composer_transformer->transform( $solution );
		}

		if ( true !== ( $result = $this->composition_manager->dry_run_composition_require( $post_ID, $solutions ) ) ) {
			$message = '<p>';
			$message .= 'We could not resolve the composition\'s dependencies. <strong>You should give the contained solutions (and their configuration) a further look and then hit Update!</strong><br>';
			if ( $result instanceof \Exception ) {
				$exception_message = $result->getMessage();
				// We need to replace links since Composer will output them in a format suitable for the command line.
				$exception_message = preg_replace( '/<(http[^>]*)>/i', '<a href="$1" target="_blank">$1</a>', $exception_message );
				$message           .= '<pre>' . $exception_message . '</pre><br>';
			}
			$message .= 'There should be additional information in the PixelgradeLT Retailer logs.';
			$message .= '</p>';
			update_post_meta( $post_ID, '_package_require_dry_run_result', [
				'type'    => 'error',
				'message' => $message,
			] );
		} else {
			update_post_meta( $post_ID, '_package_require_dry_run_result', '' );
		}
	}

	/**
	 *
	 * @since 0.11.0
	 *
	 * @return array
	 */
	public function get_available_required_solutions_options(): array {
		$options = [];

		// We can't exclude the currently required packages because if we use carbon_get_post_meta()
		// to fetch the current complex field value, we enter an infinite loop since that requires the field options.
		// And to replicate the Carbon Fields logic to parse complex fields datastore is not fun.
		$solutions_ids = $this->solution_manager->get_solution_ids_by();

		foreach ( $solutions_ids as $post_id ) {
			$solution_pseudo_id = $this->solution_manager->get_post_solution_slug( $post_id ) . self::PSEUDO_ID_DELIMITER . $post_id;

			$options[ $solution_pseudo_id ] = sprintf( __( '%s - #%s', 'pixelgradelt_retailer' ), $this->solution_manager->get_post_solution_name( $post_id ), $post_id );
		}

		ksort( $options );

		// Prepend an empty option.
		$options = [ null => esc_html__( 'Pick a solution, carefully..', 'pixelgradelt_retailer' ) ] + $options;

		return $options;
	}

	/**
	 * Check the composition post for possible issues, so the user is aware of them.
	 *
	 * @param \WP_Post The current post object.
	 */
	protected function check_composition_post( \WP_Post $post ) {
		if ( $this->composition_manager::POST_TYPE !== get_post_type( $post ) || 'auto-draft' === get_post_status( $post ) ) {
			return;
		}

		$dry_run_results = get_post_meta( $post->ID, '_package_require_dry_run_result', true );
		if ( ! empty( $dry_run_results ) ) {
			if ( is_string( $dry_run_results ) ) {
				$dry_run_results = [
					'type'    => 'warning',
					'message' => $dry_run_results,
				];
			}

			if ( is_array( $dry_run_results ) && ! empty( $dry_run_results['type'] ) && ! empty( $dry_run_results['message'] ) ) {
				$this->add_user_message( $dry_run_results['type'], $dry_run_results['message'] );
			}
		}
	}

	/**
	 * Display user messages at the top of the post edit screen.
	 *
	 * @param \WP_Post The current post object.
	 */
	protected function show_user_messages( \WP_Post $post ) {
		if ( $this->composition_manager::POST_TYPE !== get_post_type( $post ) || 'auto-draft' === get_post_status( $post ) ) {
			return;
		}

		$messages = apply_filters( 'pixelgradelt_retailer/editcomposition_show_user_messages', $this->user_messages, $post );
		if ( empty( $messages ) ) {
			return;
		}

		if ( ! empty( $messages['error'] ) ) {
			foreach ( $messages['error'] as $message ) {
				if ( ! empty( $message ) ) {
					printf( '<div class="%1$s">%2$s</div>', 'notice notice-error below-h2', $message );
				}
			}
		}

		if ( ! empty( $messages['warning'] ) ) {
			foreach ( $messages['warning'] as $message ) {
				if ( ! empty( $message ) ) {
					printf( '<div class="%1$s">%2$s</div>', 'notice notice-warning below-h2', $message );
				}
			}
		}

		if ( ! empty( $messages['info'] ) ) {
			foreach ( $messages['info'] as $message ) {
				if ( ! empty( $message ) ) {
					printf( '<div class="%1$s">%2$s</div>', 'notice notice-info below-h2', $message );
				}
			}
		}
	}

	/**
	 * Display a message above the post publish actions.
	 *
	 * @param \WP_Post The current post object.
	 */
	protected function show_publish_message( \WP_Post $post ) {
		if ( $this->composition_manager::POST_TYPE !== get_post_type( $post ) ) {
			return;
		}

		printf(
			'<div class="message patience"><p>%s</p></div>',
			wp_kses_post( __( 'Please bear in mind that Publish/Update may take a while since we do some heavy lifting behind the scenes.<br>Exercise patience 🦉', 'pixelgradelt_retailer' ) )
		);
	}

	/**
	 * Add a certain user message type to the list for later display.
	 *
	 * @since 0.11.0
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
}