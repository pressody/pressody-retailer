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
use Carbon_Fields\Helper\Helper;
use Cedaro\WP\Plugin\AbstractHookProvider;
use PixelgradeLT\Retailer\CompositionManager;
use PixelgradeLT\Retailer\PurchasedSolutionManager;
use PixelgradeLT\Retailer\Transformer\ComposerPackageTransformer;
use PixelgradeLT\Retailer\SolutionManager;
use PixelgradeLT\Retailer\Utils\ArrayHelpers;
use function Pixelgrade\WPPostNotes\create_note;
use function PixelgradeLT\Retailer\get_setting;
use function PixelgradeLT\Retailer\preload_rest_data;

/**
 * Edit Composition screen provider class.
 *
 * @since 0.11.0
 */
class EditComposition extends AbstractHookProvider {

	const LTRECORDS_API_PWD = 'pixelgradelt_records';

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
	 * Purchased-Solutions manager.
	 *
	 * @var PurchasedSolutionManager
	 */
	protected PurchasedSolutionManager $ps_manager;

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
	 * We will use this to remember the composition corresponding to a post before the save data is actually inserted into the DB.
	 *
	 * @var array|null
	 */
	protected ?array $pre_save_composition = null;

	/**
	 * Constructor.
	 *
	 * @since 0.11.0
	 *
	 * @param CompositionManager         $composition_manager        Compositions manager.
	 * @param SolutionManager            $solution_manager           Solutions manager.
	 * @param PurchasedSolutionManager   $purchased_solution_manager Purchased-Solutions manager.
	 * @param ComposerPackageTransformer $composer_transformer       Solution transformer.
	 */
	public function __construct(
		CompositionManager $composition_manager,
		SolutionManager $solution_manager,
		PurchasedSolutionManager $purchased_solution_manager,
		ComposerPackageTransformer $composer_transformer
	) {

		$this->composition_manager  = $composition_manager;
		$this->solution_manager     = $solution_manager;
		$this->ps_manager           = $purchased_solution_manager;
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

		// Handle post data retention before the post is updated in the DB (like changing the status).
		$this->add_action( 'pre_post_update', 'remember_post_composition_data', 10, 1 );
		$this->add_action( 'wp_trash_post', 'remember_post_composition_data', 10, 1 );
		$this->add_action( 'before_delete_post', 'remember_post_composition_data', 10, 1 );
		// These are programmatic changes.
		$this->add_action( 'pixelgradelt_retailer/ltcomposition/before_update', 'remember_post_composition_data', 10, 1 );

		// Check that the package can be resolved with the required packages.
		$this->add_action( 'carbon_fields_post_meta_container_saved', 'fill_hashid', 10, 2 );
		$this->add_action( 'carbon_fields_post_meta_container_saved', 'check_required', 20, 2 );

		// Show edit post screen error messages.
		$this->add_action( 'edit_form_top', 'check_composition_post', 5 );
		$this->add_action( 'edit_form_top', 'show_user_messages', 50 );

		// Add a message to the post publish metabox.
		$this->add_action( 'post_submitbox_start', 'show_publish_message' );

		/*
		 * HANDLE POST UPDATE CHANGES.
		 */
		// Just trigger the trash action.
		$this->add_action( 'wp_after_insert_post', 'do_update_action', 10, 3 );
		$this->add_action( 'pixelgradelt_retailer/ltcomposition/update', 'handle_composition_update', 10, 3 );

		/*
		 * HANDLE POST TRASH CHANGES.
		 */
		// Just trigger the update action.
		$this->add_action( 'trashed_post', 'do_trash_action', 10, 1 );
		$this->add_action( 'pixelgradelt_retailer/ltcomposition/trash', 'handle_composition_trash', 10, 1 );

		/*
		 * HANDLE POST DELETE CHANGES.
		 */
		// Just trigger the delete action.
		$this->add_action( 'deleted_post', 'do_delete_action', 10, 2 );
		$this->add_action( 'pixelgradelt_retailer/ltcomposition/delete', 'handle_composition_delete', 10, 1 );


		/*
		 * HANDLE PURCHASED SOLUTIONS DETAILS UPDATES.
		 */
		$this->add_action( 'pixelgradelt_retailer/ltcomposition/required_purchased_solutions_change', 'handle_required_purchased_solutions_details_update', 10, 3 );

		/*
		 * HANDLE AUTOMATIC POST NOTES.
		 */
		$this->add_action( 'pixelgradelt_retailer/ltcomposition/status_change', 'add_composition_status_change_note', 10, 3 );
		$this->add_action( 'pixelgradelt_retailer/ltcomposition/hashid_change', 'add_composition_hashid_change_note', 10, 3 );
		$this->add_action( 'pixelgradelt_retailer/ltcomposition/user_change', 'add_composition_user_change_note', 10, 3 );
		$this->add_action( 'pixelgradelt_retailer/ltcomposition/required_purchased_solutions_change', 'add_composition_required_purchased_solutions_change_note', 10, 3 );
		$this->add_action( 'pixelgradelt_retailer/ltcomposition/required_manual_solutions_change', 'add_composition_required_manual_solutions_change_note', 10, 3 );
	}

	/**
	 * Set up the screen.
	 *
	 * @since 0.11.0
	 */
	public function load_screen() {
		$screen = \get_current_screen();
		if ( $this->composition_manager::POST_TYPE !== $screen->post_type ) {
			return;
		}

		\add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
	}

	/**
	 * Enqueue assets.
	 *
	 * @since 0.11.0
	 */
	public function enqueue_assets() {
		\wp_enqueue_script( 'pixelgradelt_retailer-admin' );
		\wp_enqueue_style( 'pixelgradelt_retailer-admin' );

		\wp_enqueue_script( 'pixelgradelt_retailer-edit-composition' );

		// Gather all the contained solutions in the composition.
		$composition_data = $this->composition_manager->get_composition_id_data( get_the_ID(), true );
		$solutionsIds     = $this->composition_manager->extract_required_solutions_post_ids( $composition_data['required_solutions'] );
		$solutionsContext = $this->composition_manager->extract_required_solutions_context( $composition_data['required_solutions'] );

		// Get the encrypted form of the composition's LT details.
		$encrypted_ltdetails = $this->composition_manager->get_post_composition_encrypted_ltdetails( $composition_data );

		\wp_localize_script(
			'pixelgradelt_retailer-edit-composition',
			'_pixelgradeltRetailerEditCompositionData',
			[
				'editedPostId'             => \get_the_ID(),
				'editedHashId'             => $composition_data['hashid'],
				'encryptedLTDetails'       => $encrypted_ltdetails,
				'solutionIds'              => $solutionsIds,
				'solutionContexts'         => $solutionsContext,
				'ltrecordsCompositionsUrl' => 'https://lt-records.local/wp-json/pixelgradelt_records/v1/compositions',
				'ltrecordsApiKey'          => get_setting( 'ltrecords-api-key' ),
				'ltrecordsApiPwd'          => self::LTRECORDS_API_PWD,
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
			              ->set_help_text( __( 'The composition status will determine if and when this composition is available to be used on sites.', 'pixelgradelt_retailer' ) )
			              ->add_options( ArrayHelpers::array_map_assoc( function ( $key, $status ) {
				              // Construct the options from the global composition statuses.
				              return [ $status['id'] => $status['label'] . ' (' . $status['desc'] . ')' ];
			              }, CompositionManager::$STATUSES ) )
			              ->set_default_value( 'not_ready' )
			              ->set_required( true )
			              ->set_width( 50 ),

			         Field::make( 'text', 'composition_hashid', __( 'Composition HashID', 'pixelgradelt_retailer' ) )
			              ->set_help_text( __( 'This will be used to <strong>uniquely identify the composition throughout the ecosystem.</strong> Be careful when changing this after a composition is used on a site.<br>
<strong>Leave empty</strong> and we will fill it with a hash generated from the post ID. You should <strong>leave it as such</strong> if you don\'t have a good reason to change it!', 'pixelgradelt_retailer' ) ),

			         Field::make( 'association', 'composition_user_ids', __( 'Composition Owner(s)', 'pixelgradelt_retailer' ) )
			              ->set_duplicates_allowed( false )
			              ->set_help_text( sprintf( '<p class="description">%s</p>', __( 'These are registered users that act as <strong>owners of this composition.</strong> They can edit the composition and use it on sites.<br>
<strong>Only the owners\' purchased solutions</strong> can be included in the composition.<br>
<em>Note:</em> After modifying the owner list, update the post to make the new purchased solutions available.<br>
&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;Please bear in mind that already included purchased solutions may be removed from the composition if they are no longer available.', 'pixelgradelt_retailer' ) ) )
			              ->set_types( [
				              [
					              'type' => 'user',
				              ],
			              ] ),
		         ] );

		// Create a HTML list of the purchased solutions statuses to be used as help text.
		$purchased_solutions_statuses_help = [];
		foreach ( PurchasedSolutionManager::$STATUSES as $status => $status_details ) {
			if ( $status_details['internal'] ) {
				continue;
			}
			$purchased_solutions_statuses_help[] = sprintf( __( '<code>%1$s</code> : %2$s', 'pixelgradelt_retailer' ), $status, $status_details['desc'] );
		}

		// Register the metabox for managing the purchased or manual solutions the current composition includes.
		Container::make( 'post_meta', 'carbon_fields_container_solutions_configuration_' . $this->composition_manager::POST_TYPE, esc_html__( 'Included Solutions Configuration', 'pixelgradelt_retailer' ) )
		         ->where( 'post_type', '=', $this->composition_manager::POST_TYPE )
		         ->set_context( 'normal' )
		         ->set_priority( 'core' )
		         ->add_fields( [
			         Field::make( 'html', 'solutions_configuration_html', __( 'Solutions Description', 'pixelgradelt_retailer' ) )
			              ->set_html( sprintf( '<p class="description">%s</p>', __( 'Here you edit and configure <strong>the list of solutions</strong> the composition includes.<br>
The purchased solutions list will be <strong>merged</strong> with the manual solutions list to make up <strong>the final list.</strong><br>
If a <strong>manual solution</strong> is present then <strong>that LT solution will always be part of the composition,</strong> regardless of the status of a purchased solution matching the same LT Solution.', 'pixelgradelt_retailer' ) ) ),

			         Field::make( 'multiselect', 'composition_required_purchased_solutions', __( 'Purchased Solutions', 'pixelgradelt_retailer' ) )
			              ->set_help_text( __( 'These are <strong>purchased LT solutions that are included in this composition.</strong><br>
The options available are <strong>all the solutions purchased by the composition owners, combined.</strong> They are ordered by their purchased-solution ID, ascending.<br>
<strong>FYI:</strong> Each purchased-solution label is comprised of: <code>LT solution name</code>, <code>LT solution #post_id</code>, and purchased solution details like <code>customer username</code>, <code>order ID</code>, <code>purchased-solution status</code>.<br>
The statuses can be: <br>
<ul><li>' . implode( '</li><li>', $purchased_solutions_statuses_help ) . '</li></ul>', 'pixelgradelt_retailer' ) )
			              ->set_classes( 'composition-required-solutions composition_required_purchased_solutions' )
			              ->set_options( [ $this, 'get_available_required_purchased_solutions_options' ] )
			              ->set_default_value( [] )
			              ->set_required( false )
			              ->set_width( 50 ),

			         Field::make( 'complex', 'composition_required_manual_solutions', __( 'Manual Solutions', 'pixelgradelt_retailer' ) )
			              ->set_help_text( __( 'These are <strong>the manual LT solutions part of this composition.</strong> These are <strong>LT solutions that don\'t have a product purchase associated</strong> with them.<br>
Manually included solutions are <strong>for internal use only</strong> and are not accessible to composition users. That is why an optional "Reason" field is attached to each.<br>
<strong>FYI:</strong> Each solution label is comprised of the solution <code>slug</code> and the <code>#post_id</code>.', 'pixelgradelt_retailer' ) )
			              ->set_classes( 'composition-required-solutions composition_required_manual_solutions' )
			              ->set_collapsed( true )
			              ->add_fields( [
				              Field::make( 'select', 'pseudo_id', __( 'Choose one of the configured solutions', 'pixelgradelt_retailer' ) )
				                   ->set_options( [ $this, 'get_available_required_manual_solutions_options' ] )
				                   ->set_default_value( null )
				                   ->set_required( true )
				                   ->set_width( 50 ),
				              Field::make( 'text', 'reason', __( 'Reason', 'pixelgradelt_retailer' ) )
				                   ->set_help_text( __( 'The reason this LT solution is manually included in the composition.', 'pixelgradelt_retailer' ) )
				                   ->set_required( false )
				                   ->set_width( 50 ),
			              ] )
			              ->set_header_template( '
										    <% if (pseudo_id) { %>
										        <%- pseudo_id %>
										        <% if (reason) { %>
											        (Reason: <%- reason %>)
											    <% } %>
										    <% } %>
										' ),
		         ] );
	}

	public function add_solution_current_state_meta_box() {
		$post_type    = $this->composition_manager::POST_TYPE;
		$container_id = $post_type . '_current_state_details';
		\add_meta_box(
			$container_id,
			esc_html__( 'Current Composition State Details', 'pixelgradelt_retailer' ),
			[ $this, 'display_composition_current_state_meta_box' ],
			$this->composition_manager::POST_TYPE,
			'normal',
			'core'
		);

		\add_filter( "postbox_classes_{$post_type}_{$container_id}", [
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
		if ( empty( $meta_container->get_id() )
		     || 'carbon_fields_container_general_configuration_' . $this->composition_manager::POST_TYPE !== $meta_container->get_id() ) {
			return;
		}

		if ( empty( \carbon_get_post_meta( $post_ID, 'composition_hashid' ) ) ) {
			\carbon_set_post_meta( $post_ID, 'composition_hashid', $this->composition_manager->hash_encode_id( $post_ID ) );
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
		if ( empty( $meta_container->get_id() )
		     || 'carbon_fields_container_solutions_configuration_' . $this->composition_manager::POST_TYPE !== $meta_container->get_id() ) {
			return;
		}

		// Gather all the contained solutions in the composition.
		$composition_required_solutions = $this->composition_manager->get_post_composition_required_solutions( $post_ID );
		$solutions                      = $this->composition_manager->get_post_composition_required_solutions_packages( $composition_required_solutions );
		if ( empty( $solutions ) ) {
			\update_post_meta( $post_ID, '_package_require_dry_run_result', '' );

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
			\update_post_meta( $post_ID, '_package_require_dry_run_result', [
				'type'    => 'error',
				'message' => $message,
			] );
		} else {
			\update_post_meta( $post_ID, '_package_require_dry_run_result', '' );
		}
	}

	/**
	 * Given a post ID, find and remember the composition data corresponding to it.
	 *
	 * @since 0.14.0
	 *
	 * @param int $post_id
	 */
	protected function remember_post_composition_data( int $post_id ) {
		$composition_data = $this->composition_manager->get_composition_id_data( $post_id );
		if ( empty( $composition_data ) ) {
			return;
		}

		$this->pre_save_composition = $composition_data;
	}

	/**
	 * Get the select options for the required purchased-solutions.
	 *
	 * @since 0.11.0
	 *
	 * @return array
	 */
	public function get_available_required_purchased_solutions_options(): array {
		// Get the current composition owners (saved in the DB).
		$raw_owners = $this->get_cf_field_raw_value( get_the_ID(), 'composition_user_ids', 'carbon_fields_container_general_configuration_' . $this->composition_manager::POST_TYPE );
		if ( empty( $raw_owners ) || ! is_array( $raw_owners ) ) {
			return [ null => esc_html__( 'Set some composition owners first..', 'pixelgradelt_retailer' ) ];
		}

		// Process the raw values.
		$owner_ids = array_map( 'intval', \wp_list_pluck( $raw_owners, 'id' ) );

		// Get all solutions of all the current owners that are not attached to a composition, and exclude retired ones.
		// We leave invalid ones in to be more transparent about what is going on.
		$purchased_solutions = $this->ps_manager->get_purchased_solutions( [
			'user_id__in'        => $owner_ids,
			'status__not_in'     => [ 'retired', ],
			'composition_id__in' => [ 0, \get_the_ID(), ],
			'number'             => 100,
		] );

		if ( empty( $purchased_solutions ) ) {
			return [ null => esc_html__( 'There are no (usable) purchased solutions from the current composition owners..', 'pixelgradelt_retailer' ) ];
		}

		$options = [];
		/** @var \PixelgradeLT\Retailer\PurchasedSolution $ps */
		foreach ( $purchased_solutions as $ps ) {
			$customer = \get_userdata( $ps->user_id );
			if ( empty( $customer ) ) {
				continue;
			}
			$options[ $ps->id ] = sprintf(
				__( '%1$s - #%2$s (Customer: %3$s, Order: #%4$s, Status: %5$s)', 'pixelgradelt_retailer' ),
				$this->solution_manager->get_post_solution_name( $ps->solution_id ),
				$ps->solution_id,
				$customer->user_login,
				$ps->order_id,
				$ps->status
			);
		}

		ksort( $options );

		return $options;
	}

	/**
	 * Get the raw, unfiltered value of a CarbonFields fields to avoid the infinite loop implied by fetching the value
	 * through regular channels.
	 *
	 * @param int    $post_id
	 * @param string $field_name
	 * @param string $container_id
	 *
	 * @return array|null
	 */
	protected function get_cf_field_raw_value( int $post_id, string $field_name, string $container_id = '' ): ?array {
		$field = Helper::get_field( 'post_meta', $container_id, $field_name );
		if ( empty( $field ) ) {
			return null;
		}

		// No need to clone the field since we only read the value
		// and \Carbon_Fields\Datastore\Post_Meta_Datastore::load() doesn't have side effects.

		$field_datastore = $field->get_datastore();
		if ( empty( $field_datastore ) ) {
			return null;
		}
		// Remember the current datastore object ID so we can put it back.
		$datastore_object_id = $field_datastore->get_object_id();

		$field_datastore->set_object_id( $post_id );
		$result = $field_datastore->load( $field );

		// Put the previous object ID back.
		$field_datastore->set_object_id( $datastore_object_id );

		return $result;
	}

	/**
	 * Get the select options for the required manual-solutions (not customer purchased solutions).
	 *
	 * @since 0.11.0
	 *
	 * @return array
	 */
	public function get_available_required_manual_solutions_options(): array {
		$options = [];

		// We can't exclude the currently required solutions because if we use carbon_get_post_meta()
		// to fetch the current complex field value, we enter an infinite loop since that requires the field options.
		// And to replicate the Carbon Fields logic to parse complex fields datastore is not fun.
		$solutions_ids = $this->solution_manager->get_solution_ids_by();

		foreach ( $solutions_ids as $post_id ) {
			$solution_pseudo_id = $this->solution_manager->get_post_solution_slug( $post_id ) . $this->composition_manager::PSEUDO_ID_DELIMITER . $post_id;

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
		if ( $this->composition_manager::POST_TYPE !== \get_post_type( $post ) || 'auto-draft' === \get_post_status( $post ) ) {
			return;
		}

		$dry_run_results = \get_post_meta( $post->ID, '_package_require_dry_run_result', true );
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

	/**
	 * Display user messages at the top of the post edit screen.
	 *
	 * @since 0.11.0
	 *
	 * @param \WP_Post The current post object.
	 */
	protected function show_user_messages( \WP_Post $post ) {
		if ( $this->composition_manager::POST_TYPE !== get_post_type( $post ) || 'auto-draft' === \get_post_status( $post ) ) {
			return;
		}

		$messages = \apply_filters( 'pixelgradelt_retailer/editcomposition_show_user_messages', $this->user_messages, $post );
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
	 * @since 0.11.0
	 *
	 * @param \WP_Post The current post object.
	 */
	protected function show_publish_message( \WP_Post $post ) {
		if ( $this->composition_manager::POST_TYPE !== \get_post_type( $post ) ) {
			return;
		}

		printf(
			'<div class="message patience"><p>%s</p></div>',
			\wp_kses_post( __( 'Please bear in mind that Publish/Update may take a while since we do some heavy lifting behind the scenes.<br>Exercise patience 🦉', 'pixelgradelt_retailer' ) )
		);
	}

	/**
	 * Handle composition post update changes.
	 *
	 * @since 0.14.0
	 *
	 * @param int      $post_id Post ID.
	 * @param \WP_Post $post    Post object.
	 * @param bool     $update  Whether this is an existing post being updated.
	 */
	protected function do_update_action( int $post_id, \WP_Post $post, bool $update ) {
		if ( $this->composition_manager::POST_TYPE !== $post->post_type ) {
			return;
		}

		/**
		 * Fires after LT composition update.
		 *
		 * @since 0.14.0
		 *
		 * @param int      $post_id The newly created or updated composition post ID
		 * @param \WP_Post $post    The composition post object.
		 * @param bool     $update  If this is an update.
		 */
		\do_action( 'pixelgradelt_retailer/ltcomposition/update',
			$post_id,
			$post,
			$update
		);
	}

	/**
	 * Handle composition post update changes.
	 *
	 * @since 0.14.0
	 *
	 * @param int      $post_id Post ID.
	 * @param \WP_Post $post    Post object.
	 * @param bool     $update  Whether this is an existing post being updated.
	 */
	protected function handle_composition_update( int $post_id, \WP_Post $post, bool $update ) {
		if ( $this->composition_manager::POST_TYPE !== $post->post_type ) {
			return;
		}

		if ( ! $update ) {
			return;
		}
		// If we don't have the pre-update composition data, we have nothing to compare to.
		if ( empty( $this->pre_save_composition ) ) {
			return;
		}
		$old_composition = $this->pre_save_composition;

		$current_composition = $this->composition_manager->get_composition_id_data( $post_id );
		if ( empty( $current_composition ) ) {
			return;
		}

		// Handle composition status change.
		if ( ! empty( $old_composition['status'] ) && $old_composition['status'] !== $current_composition['status'] ) {
			/**
			 * Fires on LT composition status change.
			 *
			 * @since 0.14.0
			 *
			 * @param int    $post_id         The composition post ID.
			 * @param string $new_status      The new composition status.
			 * @param string $old_status      The old composition status.
			 * @param array  $new_composition The entire new composition data.
			 */
			\do_action( 'pixelgradelt_retailer/ltcomposition/status_change',
				$post_id,
				$current_composition['status'],
				$old_composition['status'],
				$current_composition
			);
		}

		// Handle composition hashid change.
		if ( ! empty( $old_composition['hashid'] ) && $old_composition['hashid'] !== $current_composition['hashid'] ) {
			/**
			 * Fires on LT composition hashid change.
			 *
			 * @since 0.14.0
			 *
			 * @param int    $post_id         The composition post ID.
			 * @param string $new_hashid      The new composition hashid.
			 * @param string $old_hashid      The old composition hashid.
			 * @param array  $new_composition The entire new composition data.
			 */
			\do_action( 'pixelgradelt_retailer/ltcomposition/hashid_change',
				$post_id,
				$current_composition['hashid'],
				$old_composition['hashid'],
				$current_composition
			);
		}

		/**
		 * Handle composition owners/users changes.
		 * @see CompositionManager::get_post_composition_users_details()
		 */
		// We are only interested in actual user IDs changes, not individual user details changes.
		$old_userids = array_keys( $old_composition['users'] );
		$new_userids = array_keys( $current_composition['users'] );
		sort( $old_userids );
		sort( $new_userids );
		if ( $old_userids != $new_userids ) {
			/**
			 * Fires on LT composition user details change.
			 *
			 * @since 0.14.0
			 *
			 * @see   CompositionManager::get_post_composition_users_details() For data details
			 *
			 * @param int   $post_id         The composition post ID.
			 * @param array $new_users       The new composition users details.
			 * @param array $old_users       The old composition users details.
			 * @param array $new_composition The entire new composition data.
			 */
			\do_action( 'pixelgradelt_retailer/ltcomposition/user_change',
				$post_id,
				$current_composition['users'],
				$old_composition['users'],
				$current_composition
			);
		}

		/**
		 * Handle composition required purchased-solutions changes.
		 * @see CompositionManager::get_post_composition_required_purchased_solutions()
		 */
		$old_required_purchased_solutions_ids = \wp_list_pluck( $old_composition['required_purchased_solutions'], 'purchased_solution_id' );
		sort( $old_required_purchased_solutions_ids );

		$current_required_purchased_solutions_ids = \wp_list_pluck( $current_composition['required_purchased_solutions'], 'purchased_solution_id' );
		sort( $current_required_purchased_solutions_ids );

		if ( serialize( $old_required_purchased_solutions_ids ) !== serialize( $current_required_purchased_solutions_ids ) ) {
			/**
			 * Fires on LT composition required purchased-solutions change (post ID changes).
			 *
			 * @since 0.14.0
			 *
			 * @see   CompositionManager::get_post_composition_required_purchased_solutions() For data details
			 *
			 * @param int   $post_id                          The composition post ID.
			 * @param array $new_required_purchased_solutions The new composition required_purchased_solutions details.
			 * @param array $old_required_purchased_solutions The old composition required_purchased_solutions details.
			 * @param array $new_composition                  The entire new composition data.
			 */
			\do_action( 'pixelgradelt_retailer/ltcomposition/required_purchased_solutions_change',
				$post_id,
				$current_composition['required_purchased_solutions'],
				$old_composition['required_purchased_solutions'],
				$current_composition
			);
		}

		/**
		 * Handle composition required manual-solutions changes.
		 * @see CompositionManager::get_post_composition_required_manual_solutions()
		 */
		// We are only interested in actual solutions changes, not slug or context details changes.
		// That is why we will only look at the required solution post ID (managed_post_id).
		$old_required_manual_solutions_post_ids = \wp_list_pluck( $old_composition['required_manual_solutions'], 'managed_post_id' );
		sort( $old_required_manual_solutions_post_ids );

		$current_required_manual_solutions_post_ids = \wp_list_pluck( $current_composition['required_manual_solutions'], 'managed_post_id' );
		sort( $current_required_manual_solutions_post_ids );

		if ( serialize( $old_required_manual_solutions_post_ids ) !== serialize( $current_required_manual_solutions_post_ids ) ) {
			/**
			 * Fires on LT composition required solutions change (post ID changes).
			 *
			 * @since 0.14.0
			 *
			 * @see   CompositionManager::get_post_composition_required_manual_solutions() For data details
			 *
			 * @param int   $post_id                       The composition post ID.
			 * @param array $new_required_manual_solutions The new composition required_manual_solutions details.
			 * @param array $old_required_manual_solutions The old composition required_manual_solutions details.
			 * @param array $new_composition               The entire new composition data.
			 */
			\do_action( 'pixelgradelt_retailer/ltcomposition/required_manual_solutions_change',
				$post_id,
				$current_composition['required_manual_solutions'],
				$old_composition['required_manual_solutions'],
				$current_composition
			);
		}

		/**
		 * Handle the final required LT solutions list that may or may not change on purchased or manual solutions list changes
		 * since this list is a merge of the two.
		 * @see CompositionManager::get_post_composition_required_solutions()
		 */
		// We are only interested in actual LT solutions changes, not slug or context details changes.
		// That is why we will only look at the required solution post ID (managed_post_id).
		$old_required_solutions_post_ids = \wp_list_pluck( $old_composition['required_solutions'], 'managed_post_id' );
		sort( $old_required_solutions_post_ids );

		$current_required_solutions_post_ids = \wp_list_pluck( $current_composition['required_solutions'], 'managed_post_id' );
		sort( $current_required_solutions_post_ids );

		if ( serialize( $old_required_solutions_post_ids ) !== serialize( $current_required_solutions_post_ids ) ) {
			/**
			 * Fires on LT composition required solutions final list change (post ID changes).
			 *
			 * Use the 'pixelgradelt_retailer/ltcomposition/required_purchased_solutions_change' or
			 * 'pixelgradelt_retailer/ltcomposition/required_manual_solutions_change' hooks for more specific actions.
			 *
			 * @since 0.14.0
			 *
			 * @see   CompositionManager::get_post_composition_required_solutions() For data details
			 *
			 * @param int   $post_id                The composition post ID.
			 * @param array $new_required_solutions The new composition required_solutions.
			 * @param array $old_required_solutions The old composition required_solutions.
			 * @param array $new_composition        The entire new composition data.
			 */
			\do_action( 'pixelgradelt_retailer/ltcomposition/required_solutions_change',
				$post_id,
				$current_composition['required_solutions'],
				$old_composition['required_solutions'],
				$current_composition
			);
		}

		/**
		 * Fires on LT composition update, after the individual change hooks have been fired.
		 *
		 * @since 0.14.0
		 *
		 * @see   CompositionManager::get_composition_id_data() For data details
		 *
		 * @param int   $post_id         The composition post ID.
		 * @param array $new_composition The entire new composition data.
		 * @param array $old_composition The entire old composition data.
		 */
		\do_action( 'pixelgradelt_retailer/ltcomposition/after_update',
			$post_id,
			$current_composition,
			$old_composition
		);
	}

	/**
	 * Handle purchased solutions details update on LT composition required purchased-solutions change.
	 *
	 * @since 0.15.0
	 *
	 * @param int   $post_id                          The composition post ID.
	 * @param array $new_required_purchased_solutions The new composition required_purchased_solutions data.
	 * @param array $old_required_purchased_solutions The old composition required_purchased_solutions data.
	 */
	protected function handle_required_purchased_solutions_details_update( int $post_id, array $new_required_purchased_solutions, array $old_required_purchased_solutions ) {
		$old_required_purchased_solutions_ids = \wp_list_pluck( $old_required_purchased_solutions, 'purchased_solution_id' );
		$new_required_purchased_solutions_ids = \wp_list_pluck( $new_required_purchased_solutions, 'purchased_solution_id' );

		// Activate the added purchased solutions (aka attach to the current composition).
		$added   = array_diff( $new_required_purchased_solutions_ids, $old_required_purchased_solutions_ids );
		foreach ( $added as $ps_id ) {
			$this->ps_manager->activate_purchased_solution( $ps_id, $post_id );
		}

		// Deactivate the removed purchased solutions (aka detach from the current composition and make them available for use in others).
		$removed = array_diff( $old_required_purchased_solutions_ids, $new_required_purchased_solutions_ids );
		foreach ( $removed as $ps_id ) {
			$this->ps_manager->deactivate_purchased_solution( $ps_id, $post_id );
		}
	}

	/**
	 * Do the composition post trash action.
	 *
	 * @since 0.15.0
	 *
	 * @param int      $post_id Post ID.
	 */
	protected function do_trash_action( int $post_id ) {
		if ( $this->composition_manager::POST_TYPE !== \get_post_type( $post_id ) ) {
			return;
		}

		/**
		 * Fires after a LT composition is trashed.
		 *
		 * @since 0.15.0
		 *
		 * @param int      $post_id The trashed composition post ID
		 */
		\do_action( 'pixelgradelt_retailer/ltcomposition/trash',
			$post_id,
		);
	}

	/**
	 * Handle composition post trashed changes.
	 *
	 * @since 0.15.0
	 *
	 * @param int      $post_id Post ID.
	 */
	protected function handle_composition_trash( int $post_id ) {
		// If we don't have the pre-trash composition data, we have nothing to compare to.
		if ( empty( $this->pre_save_composition ) ) {
			return;
		}

		// We want to make sure that the purchased solutions previously attached are detached.
		$purchased_solutions_ids = \wp_list_pluck( $this->pre_save_composition['required_purchased_solutions'], 'purchased_solution_id' );
		if ( ! empty( $purchased_solutions_ids ) ) {
			foreach ( $purchased_solutions_ids as $purchased_solutions_id ) {
				$this->ps_manager->deactivate_purchased_solution( $purchased_solutions_id, $post_id );
			}
		}
	}

	/**
	 * Do the composition post delete action.
	 *
	 * @since 0.15.0
	 *
	 * @param int      $post_id Post ID.
	 * @param \WP_Post $post   Post object.
	 */
	protected function do_delete_action( int $post_id, \WP_Post $post ) {
		if ( $this->composition_manager::POST_TYPE !== $post->post_type ) {
			return;
		}

		/**
		 * Fires after a LT composition is deleted.
		 *
		 * @since 0.15.0
		 *
		 * @param int      $post_id The deleted composition post ID
		 */
		\do_action( 'pixelgradelt_retailer/ltcomposition/delete',
			$post_id,
		);
	}

	/**
	 * Handle composition post deleted changes.
	 *
	 * @since 0.15.0
	 *
	 * @param int      $post_id Post ID.
	 */
	protected function handle_composition_delete( int $post_id ) {
		// If we don't have the pre-delete composition data, we have nothing to compare to.
		if ( empty( $this->pre_save_composition ) ) {
			return;
		}

		// We want to make sure that the purchased solutions previously attached are detached.
		$purchased_solutions_ids = \wp_list_pluck( $this->pre_save_composition['required_purchased_solutions'], 'purchased_solution_id' );
		if ( ! empty( $purchased_solutions_ids ) ) {
			foreach ( $purchased_solutions_ids as $purchased_solutions_id ) {
				$this->ps_manager->deactivate_purchased_solution( $purchased_solutions_id, $post_id );
			}
		}
	}

	/**
	 * Add post note on LT composition status change.
	 *
	 * @since 0.14.0
	 *
	 * @param int    $post_id    The composition post ID.
	 * @param string $new_status The new composition status.
	 * @param string $old_status The old composition status.
	 */
	protected function add_composition_status_change_note( int $post_id, string $new_status, string $old_status ) {
		$note = sprintf(
			esc_html__( 'Composition status changed from %1$s to %2$s.', 'pixelgradelt_retailer' ),
			'<strong>' . $old_status . '</strong>',
			'<strong>' . $new_status . '</strong>'
		);

		create_note( $post_id, $note, 'internal', true );
	}

	/**
	 * Add post note on LT composition hashid change.
	 *
	 * @since 0.14.0
	 *
	 * @param int    $post_id    The composition post ID.
	 * @param string $new_hashid The new composition hashid.
	 * @param string $old_hashid The old composition hashid.
	 */
	protected function add_composition_hashid_change_note( int $post_id, string $new_hashid, string $old_hashid ) {
		$note = sprintf(
			esc_html__( 'Composition hashid changed from %1$s to %2$s.', 'pixelgradelt_retailer' ),
			'<strong>' . $old_hashid . '</strong>',
			'<strong>' . $new_hashid . '</strong>'
		);

		create_note( $post_id, $note, 'internal', true );
	}

	/**
	 * Add post note on LT composition users change.
	 *
	 * @since 0.14.0
	 *
	 * @param int   $post_id   The composition post ID.
	 * @param array $new_users The new composition users details.
	 * @param array $old_users The old composition users details.
	 */
	protected function add_composition_user_change_note( int $post_id, array $new_users, array $old_users ) {
		$old_users_ids = array_keys( $old_users );
		sort( $old_users_ids );

		$new_users_ids = array_keys( $new_users );
		sort( $new_users_ids );

		$added   = array_diff( $new_users_ids, $old_users_ids );
		$removed = array_diff( $old_users_ids, $new_users_ids );

		$note = '';
		if ( ! empty( $removed ) ) {
			$removed_list = [];
			foreach ( $removed as $removed_user_id ) {
				$item = '#' . $removed_user_id;
				$user = \get_userdata( $removed_user_id );
				if ( false !== $user ) {
					$item = $user->user_login . ' ' . $item;
				}

				$removed_list[] = $item;
			}

			if ( count( $removed_list ) == 1 ) {
				$note .= sprintf(
					esc_html__( 'Removed a composition owner: %1$s. ', 'pixelgradelt_retailer' ),
					'<strong>' . implode( ', ', $removed_list ) . '</strong>'
				);
			} else {
				$note .= sprintf(
					esc_html__( 'Removed these composition owners: %1$s. ', 'pixelgradelt_retailer' ),
					'<strong>' . implode( ', ', $removed_list ) . '</strong>'
				);
			}
		}

		if ( ! empty( $added ) ) {
			$added_list = [];
			foreach ( $added as $added_user_id ) {
				$item = '#' . $added_user_id;
				$user = \get_userdata( $added_user_id );
				if ( false !== $user ) {
					$item = $user->user_login . ' ' . $item;
				}

				$added_list[] = $item;
			}

			if ( count( $added_list ) == 1 ) {
				$note .= sprintf(
					esc_html__( 'Added a composition owner: %1$s. ', 'pixelgradelt_retailer' ),
					'<strong>' . implode( ', ', $added_list ) . '</strong>'
				);
			} else {
				$note .= sprintf(
					esc_html__( 'Added the following composition owners: %1$s. ', 'pixelgradelt_retailer' ),
					'<strong>' . implode( ', ', $added_list ) . '</strong>'
				);
			}
		}

		if ( ! empty( trim( $note ) ) ) {
			create_note( $post_id, $note, 'internal', true );
		}
	}

	/**
	 * Add post note on LT composition required purchased-solutions change.
	 *
	 * @since 0.14.0
	 *
	 * @param int   $post_id                          The composition post ID.
	 * @param array $new_required_purchased_solutions The new composition required_purchased_solutions data.
	 * @param array $old_required_purchased_solutions The old composition required_purchased_solutions data.
	 */
	protected function add_composition_required_purchased_solutions_change_note( int $post_id, array $new_required_purchased_solutions, array $old_required_purchased_solutions ) {
		$old_required_purchased_solutions_ids = \wp_list_pluck( $old_required_purchased_solutions, 'purchased_solution_id' );
		sort( $old_required_purchased_solutions_ids );

		$new_required_purchased_solutions_ids = \wp_list_pluck( $new_required_purchased_solutions, 'purchased_solution_id' );
		sort( $new_required_purchased_solutions_ids );

		$added   = array_diff( $new_required_purchased_solutions_ids, $old_required_purchased_solutions_ids );
		$removed = array_diff( $old_required_purchased_solutions_ids, $new_required_purchased_solutions_ids );

		$note = '';
		if ( ! empty( $removed ) ) {
			$removed_list = [];
			foreach ( $removed as $removed_purchased_solution_id ) {
				$index = ArrayHelpers::findSubarrayByKeyValue( $old_required_purchased_solutions, 'purchased_solution_id', $removed_purchased_solution_id );
				$removed_list[] = $old_required_purchased_solutions[ $index ]['slug'] . $this->composition_manager::PSEUDO_ID_DELIMITER . $old_required_purchased_solutions[ $index ]['managed_post_id'] . ' (psID:#' . $removed_purchased_solution_id . ')';
			}

			if ( count( $removed_list ) == 1 ) {
				$note .= sprintf(
					esc_html__( 'Removed a purchased-solution: %1$s. ', 'pixelgradelt_retailer' ),
					'<strong>' . implode( ', ', $removed_list ) . '</strong>'
				);
			} else {
				$note .= sprintf(
					esc_html__( 'Removed these purchased-solutions: %1$s. ', 'pixelgradelt_retailer' ),
					'<strong>' . implode( ', ', $removed_list ) . '</strong>'
				);
			}
		}

		if ( ! empty( $added ) ) {
			$added_list = [];
			foreach ( $added as $added_purchased_solution_id ) {
				$index = ArrayHelpers::findSubarrayByKeyValue( $new_required_purchased_solutions, 'purchased_solution_id', $added_purchased_solution_id );
				$added_list[] = $new_required_purchased_solutions[ $index ]['slug'] . $this->composition_manager::PSEUDO_ID_DELIMITER . $new_required_purchased_solutions[ $index ]['managed_post_id'] . ' (psID:#' . $added_purchased_solution_id . ')';
			}

			if ( count( $added_list ) == 1 ) {
				$note .= sprintf(
					esc_html__( 'Added a purchased-solution: %1$s. ', 'pixelgradelt_retailer' ),
					'<strong>' . implode( ', ', $added_list ) . '</strong>'
				);
			} else {
				$note .= sprintf(
					esc_html__( 'Added the following purchased-solutions: %1$s. ', 'pixelgradelt_retailer' ),
					'<strong>' . implode( ', ', $added_list ) . '</strong>'
				);
			}
		}

		if ( ! empty( trim( $note ) ) ) {
			create_note( $post_id, $note, 'internal', true );
		}
	}

	/**
	 * Add post note on LT composition required manual-solutions change.
	 *
	 * @since 0.14.0
	 *
	 * @param int   $post_id                       The composition post ID.
	 * @param array $new_required_manual_solutions The new composition required_manual_solutions data.
	 * @param array $old_required_manual_solutions The old composition required_manual solutions data.
	 */
	protected function add_composition_required_manual_solutions_change_note( int $post_id, array $new_required_manual_solutions, array $old_required_manual_solutions ) {
		$old_required_solutions_post_ids = \wp_list_pluck( $old_required_manual_solutions, 'managed_post_id' );
		sort( $old_required_solutions_post_ids );

		$new_required_solutions_post_ids = \wp_list_pluck( $new_required_manual_solutions, 'managed_post_id' );
		sort( $new_required_solutions_post_ids );

		$added   = array_diff( $new_required_solutions_post_ids, $old_required_solutions_post_ids );
		$removed = array_diff( $old_required_solutions_post_ids, $new_required_solutions_post_ids );

		$note = '';
		if ( ! empty( $removed ) ) {
			$removed_list = [];
			foreach ( $removed as $removed_post_id ) {
				$index = ArrayHelpers::findSubarrayByKeyValue( $old_required_manual_solutions, 'managed_post_id', $removed_post_id );
				$removed_list[] = $old_required_manual_solutions[ $index ]['slug'] . $this->composition_manager::PSEUDO_ID_DELIMITER . $removed_post_id;
			}

			if ( count( $removed_list ) == 1 ) {
				$note .= sprintf(
					esc_html__( 'Removed a manual-solution: %1$s. ', 'pixelgradelt_retailer' ),
					'<strong>' . implode( ', ', $removed_list ) . '</strong>'
				);
			} else {
				$note .= sprintf(
					esc_html__( 'Removed these manual-solutions: %1$s. ', 'pixelgradelt_retailer' ),
					'<strong>' . implode( ', ', $removed_list ) . '</strong>'
				);
			}
		}

		if ( ! empty( $added ) ) {
			$added_list = [];
			foreach ( $added as $added_post_id ) {
				$index = ArrayHelpers::findSubarrayByKeyValue( $new_required_manual_solutions, 'managed_post_id', $added_post_id );
				$added_list[] = $new_required_manual_solutions[ $index ]['slug'] . $this->composition_manager::PSEUDO_ID_DELIMITER . $added_post_id;
			}

			if ( count( $added_list ) == 1 ) {
				$note .= sprintf(
					esc_html__( 'Added a manual-solution: %1$s. ', 'pixelgradelt_retailer' ),
					'<strong>' . implode( ', ', $added_list ) . '</strong>'
				);
			} else {
				$note .= sprintf(
					esc_html__( 'Added the following manual-solutions: %1$s. ', 'pixelgradelt_retailer' ),
					'<strong>' . implode( ', ', $added_list ) . '</strong>'
				);
			}
		}

		if ( ! empty( trim( $note ) ) ) {
			create_note( $post_id, $note, 'internal', true );
		}
	}
}
