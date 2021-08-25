<?php
/**
 * Edit Solution screen provider.
 *
 * @since   0.1.0
 * @license GPL-2.0-or-later
 * @package PixelgradeLT
 */

declare ( strict_types=1 );

namespace PixelgradeLT\Retailer\Screen;

use Carbon_Fields\Carbon_Fields;
use Carbon_Fields\Container;
use Carbon_Fields\Field;
use Cedaro\WP\Plugin\AbstractHookProvider;
use PixelgradeLT\Retailer\Transformer\ComposerPackageTransformer;
use PixelgradeLT\Retailer\SolutionManager;
use PixelgradeLT\Retailer\Repository\PackageRepository;
use PixelgradeLT\Retailer\Utils\ArrayHelpers;
use function Pixelgrade\WPPostNotes\create_note;
use function PixelgradeLT\Retailer\get_solutions_permalink;
use function PixelgradeLT\Retailer\preload_rest_data;

/**
 * Edit Solution screen provider class.
 *
 * @since 0.1.0
 */
class EditSolution extends AbstractHookProvider {

	const LTRECORDS_API_PWD = 'pixelgradelt_records';

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
	 * We will use this to remember the solution corresponding to a post before the save data is actually inserted into the DB.
	 *
	 * @var array|null
	 */
	protected ?array $pre_save_solution = null;

	/**
	 * Constructor.
	 *
	 * @since 0.1.0
	 *
	 * @param SolutionManager            $solution_manager     Solutions manager.
	 * @param PackageRepository          $solutions            Solutions repository.
	 * @param ComposerPackageTransformer $composer_transformer Solution transformer.
	 */
	public function __construct(
			SolutionManager $solution_manager,
			PackageRepository $solutions,
			ComposerPackageTransformer $composer_transformer
	) {

		$this->solution_manager     = $solution_manager;
		$this->solutions            = $solutions;
		$this->composer_transformer = $composer_transformer;
	}

	/**
	 * Register hooks.
	 *
	 * @since 0.1.0
	 */
	public function register_hooks() {
		// Assets.
		add_action( 'load-post.php', [ $this, 'load_screen' ] );
		add_action( 'load-post-new.php', [ $this, 'load_screen' ] );

		// Logic.
		// Make sure that the post has a title.
		$this->add_action( 'save_post_' . $this->solution_manager::POST_TYPE, 'prevent_post_save_without_title' );

		// Change the post title placeholder.
		$this->add_filter( 'enter_title_here', 'change_title_placeholder', 10, 2 );

		// Add a description to the slug
		$this->add_filter( 'editable_slug', 'add_post_slug_description', 10, 2 );
		// Make sure that the slug and other metaboxes are never hidden.
		$this->add_filter( 'hidden_meta_boxes', 'prevent_hidden_metaboxes', 10, 2 );
		// Rearrange the core metaboxes.
		$this->add_action( 'add_meta_boxes_' . $this->solution_manager::POST_TYPE, 'add_solution_current_state_meta_box', 10 );
		$this->add_action( 'add_meta_boxes_' . $this->solution_manager::POST_TYPE, 'adjust_core_metaboxes', 99 );

		// ADD CUSTOM POST META VIA CARBON FIELDS.
		$this->add_action( 'plugins_loaded', 'carbonfields_load' );
		$this->add_action( 'carbon_fields_register_fields', 'attach_post_meta_fields' );

		// Handle post data retention before the post is updated in the DB.
		$this->add_action( 'pre_post_update', 'remember_post_solution_data', 10, 1 );

		// Check that the package can be resolved with the required packages.
		$this->add_action( 'carbon_fields_post_meta_container_saved', 'check_required', 20, 2 );

		// We get early so we can show error messages.
		$this->add_action( 'plugins_loaded', 'get_ltrecords_parts', 20 );
		// Show edit post screen error messages.
		$this->add_action( 'edit_form_top', 'check_solution_post', 5 );
		$this->add_action( 'edit_form_top', 'show_user_messages', 50 );

		// Add a message to the post publish metabox.
		$this->add_action( 'post_submitbox_start', 'show_publish_message' );

		/*
		 * HANDLE POST UPDATE CHANGES.
		 */
		$this->add_action( 'wp_after_insert_post', 'handle_post_update', 10, 3 );
		// Handle changes to the slug - things that affect dependants (things like slug/package name change that is saved in pseudo_ids).
		$this->add_action( 'pixelgradelt_retailer/ltsolution/slug_change', 'on_slug_change', 5, 3 );

		/*
		 * HANDLE AUTOMATIC POST NOTES.
		 */
		$this->add_action( 'pixelgradelt_retailer/ltsolution/visibility_change', 'add_solution_visibility_change_note', 10, 3 );
		$this->add_action( 'pixelgradelt_retailer/ltsolution/type_change', 'add_solution_type_change_note', 10, 3 );
		$this->add_action( 'pixelgradelt_retailer/ltsolution/slug_change', 'add_solution_slug_change_note', 10, 3 );
		$this->add_action( 'pixelgradelt_retailer/ltsolution/required_solutions_change', 'add_solution_required_solutions_change_note', 10, 3 );
		$this->add_action( 'pixelgradelt_retailer/ltsolution/excluded_solutions_change', 'add_solution_excluded_solutions_change_note', 10, 3 );
	}

	/**
	 * Set up the screen.
	 *
	 * @since 0.1.0
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
	 * @since 0.1.0
	 */
	public function enqueue_assets() {
		wp_enqueue_script( 'pixelgradelt_retailer-admin' );
		wp_enqueue_style( 'pixelgradelt_retailer-admin' );

		wp_enqueue_script( 'pixelgradelt_retailer-edit-solution' );

		wp_localize_script(
				'pixelgradelt_retailer-edit-solution',
				'_pixelgradeltRetailerEditSolutionData',
				[
						'editedPostId' => get_the_ID(),
				]
		);

		$preload_paths = [
				'/pixelgradelt_retailer/v1/solutions',
		];

		preload_rest_data( $preload_paths );
	}

	/**
	 * Prevent the package from being published on certain occasions.
	 *
	 * Save as draft instead.
	 *
	 * @param int $post_id The ID of the post that's being saved.
	 */
	protected function prevent_post_save_without_title( int $post_id ) {
		$post = get_post( $post_id );

		if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE )
		     || ( defined( 'DOING_AJAX' ) && DOING_AJAX )
		     || ! current_user_can( 'edit_post', $post_id )
		     || false !== wp_is_post_revision( $post_id )
		     || 'trash' == get_post_status( $post_id )
		     || isset( $post->post_status ) && 'auto-draft' == $post->post_status ) {
			return;
		}

		$package_title = isset( $_POST['post_title'] ) ? sanitize_text_field( $_POST['post_title'] ) : '';

		// A valid title is required, so don't let this get published without one
		if ( empty( $package_title ) ) {
			// unhook this function so it doesn't loop infinitely
			$this->remove_action( 'save_post_' . $this->solution_manager::POST_TYPE, 'prevent_post_save_without_title' );

			$postdata = array(
					'ID'          => $post_id,
					'post_status' => 'draft',
			);
			wp_update_post( $postdata );

			// This way we avoid the "published" admin message.
			unset( $_POST['publish'] );
		}
	}

	protected function change_title_placeholder( string $placeholder, \WP_Post $post ): string {
		if ( $this->solution_manager::POST_TYPE !== get_post_type( $post ) ) {
			return $placeholder;
		}

		return esc_html__( 'Add solution title', 'pixelgradelt_retailer' );
	}

	protected function add_post_slug_description( string $post_name, $post ): string {
		// We want this only on the edit post screen.
		if ( $this->solution_manager::POST_TYPE !== get_current_screen()->id ) {
			return $post_name;
		}

		// Only on our post type.
		if ( $this->solution_manager::POST_TYPE !== get_post_type( $post ) ) {
			return $post_name;
		}
		// Just output it since there is no way to add it other way. ?>
		<p class="description">
			<?php _e( '<strong>The post slug is, at the same time, the Composer PROJECT NAME.</strong><br>
In the end this will be joined with the vendor name (like so: <code>vendor/slug</code>) to form the package name to be used in composer.json.<br>
The slug/name must be lowercased and consist of words separated by <code>-</code> or <code>_</code>. It also must respect <a href="https://regexr.com/5sr9h" target="_blank">this regex</a>', 'pixelgradelt_retailer' ); ?>
		</p>
		<style>
			input#post_name {
				width: 20%;
			}
		</style>
		<?php

		// We must return the post slug.
		return $post_name;
	}

	/**
	 * @param string[]   $hidden
	 * @param \WP_Screen $screen
	 *
	 * @return string[]
	 */
	protected function prevent_hidden_metaboxes( array $hidden, \WP_Screen $screen ): array {
		if ( ! empty( $hidden ) && is_array( $hidden ) &&
		     ! empty( $screen->id ) &&
		     $this->solution_manager::POST_TYPE === $screen->id &&
		     ! empty( $screen->post_type ) &&
		     $this->solution_manager::POST_TYPE === $screen->post_type
		) {
			// Prevent the slug metabox from being hidden.
			if ( false !== ( $key = array_search( 'slugdiv', $hidden ) ) ) {
				unset( $hidden[ $key ] );
			}

			// Prevent the package type metabox from being hidden.
			if ( false !== ( $key = array_search( 'tagsdiv-ltsolution_types', $hidden ) ) ) {
				unset( $hidden[ $key ] );
			}
		}

		return $hidden;
	}

	protected function adjust_core_metaboxes( \WP_Post $post ) {
		global $wp_meta_boxes;

		if ( empty( $wp_meta_boxes[ $this->solution_manager::POST_TYPE ] ) ) {
			return;
		}

		// We will move the slug metabox at the very top.
		if ( ! empty( $wp_meta_boxes[ $this->solution_manager::POST_TYPE ]['normal']['core']['slugdiv'] ) ) {
			$tmp = $wp_meta_boxes[ $this->solution_manager::POST_TYPE ]['normal']['core']['slugdiv'];
			unset( $wp_meta_boxes[ $this->solution_manager::POST_TYPE ]['normal']['core']['slugdiv'] );

			$wp_meta_boxes[ $this->solution_manager::POST_TYPE ]['normal']['core'] = [ 'slugdiv' => $tmp ] + $wp_meta_boxes[ $this->solution_manager::POST_TYPE ]['normal']['core'];
		}

		// Since we are here, modify the package type title to be singular, rather than plural.
		if ( ! empty( $wp_meta_boxes[ $this->solution_manager::POST_TYPE ]['side']['core']['tagsdiv-ltsolution_types'] ) ) {
			$wp_meta_boxes[ $this->solution_manager::POST_TYPE ]['side']['core']['tagsdiv-ltsolution_types']['title'] = esc_html__( 'Solution Type', 'pixelgradelt_retailer' ) . '<span style="color: red; flex: auto">*</span>';
		}
	}

	protected function carbonfields_load() {
		Carbon_Fields::boot();
	}

	protected function attach_post_meta_fields() {
		// Register the metabox for managing the general details of the solution.
		Container::make( 'post_meta', 'carbon_fields_container_source_configuration_' . $this->solution_manager::POST_TYPE, esc_html__( 'General Configuration', 'pixelgradelt_retailer' ) )
		         ->where( 'post_type', '=', $this->solution_manager::POST_TYPE )
		         ->set_context( 'normal' )
		         ->set_priority( 'core' )
		         ->add_fields( [
				         Field::make( 'html', 'solution_details_html', __( 'Section Description', 'pixelgradelt_retailer' ) )
				              ->set_html( sprintf( '<p class="description">%s</p>', __( 'Configure details about <strong>the solution itself,</strong> as it will be exposed for consumption.', 'pixelgradelt_retailer' ) ) ),

				         Field::make( 'textarea', 'solution_details_description', __( 'Short Description', 'pixelgradelt_retailer' ) ),
				         Field::make( 'rich_text', 'solution_details_longdescription', __( 'Description', 'pixelgradelt_retailer' ) ),
				         Field::make( 'text', 'solution_details_homepage', __( 'Solution Homepage URL', 'pixelgradelt_retailer' ) )
				              ->set_help_text( __( 'This could be a URL to a page that presents details about this solution.', 'pixelgradelt_retailer' ) ),
		         ] );

		// Register the metabox for managing the parts the current solution depends on (dependencies that will translate in composer requires).
		Container::make( 'post_meta', 'carbon_fields_container_part_dependencies_configuration', esc_html__( 'Required Parts Configuration', 'pixelgradelt_retailer' ) )
		         ->where( 'post_type', '=', $this->solution_manager::POST_TYPE )
		         ->set_context( 'normal' )
		         ->set_priority( 'core' )
		         ->add_fields( [
				         Field::make( 'html', 'parts_dependencies_configuration_html', __( 'Required Parts Description', 'pixelgradelt_retailer' ) )
				              ->set_html( sprintf( '<p class="description">%s</p>', __( 'Here you edit and configure <strong>the list of LT Records parts</strong> this solution depends on.<br>
For each required part you can <strong>specify a version range</strong> to better control the part releases/versions required. Set to <code>*</code> to <strong>use the latest available required-part release that matches all constraints</strong> (other parts present on a site might impose stricter limits).<br>
Learn more about Composer <a href="https://getcomposer.org/doc/articles/versions.md#writing-version-constraints" target="_blank">versions</a> or <a href="https://semver.mwl.be/?package=madewithlove%2Fhtaccess-cli&constraint=%3C1.2%20%7C%7C%20%3E1.6&stability=stable" target="_blank">play around</a> with version ranges.', 'pixelgradelt_retailer' ) ) ),

				         Field::make( 'complex', 'solution_required_parts', __( 'Required Parts', 'pixelgradelt_retailer' ) )
				              ->set_help_text( __( 'The order is not important, from a logic standpoint. Also, if you add <strong>the same part multiple times</strong> only the last one will take effect since it will overwrite the previous ones.<br>
LT Records Parts don\'t have a <code>stability</code> field because we want to <strong>control the stability at a composition level</strong> (the global site level).<br>
<strong>FYI:</strong> Each required part label is comprised of the standardized <code>package_name</code> and the <code>version range</code>.', 'pixelgradelt_retailer' ) )
				              ->set_classes( 'solution-required-solutions solution-required-parts' )
				              ->set_collapsed( true )
				              ->add_fields( [
						              Field::make( 'select', 'package_name', __( 'Choose one of the LT Records Parts', 'pixelgradelt_retailer' ) )
						                   ->set_options( [ $this, 'get_available_required_parts_options' ] )
						                   ->set_default_value( null )
						                   ->set_required( true )
						                   ->set_width( 50 ),
						              Field::make( 'text', 'version_range', __( 'Version Range', 'pixelgradelt_retailer' ) )
						                   ->set_default_value( '*' )
						                   ->set_required( true )
						                   ->set_width( 25 ),
				              ] )
				              ->set_header_template( '
								    <% if (package_name) { %>
								        <%- package_name %> (version range: <%= version_range %>)
								    <% } %>
								' ),
		         ] );

		// Register the metabox for managing the solutions the current solution depends on (dependencies that will translate in composer requires).
		Container::make( 'post_meta', 'carbon_fields_container_dependencies_configuration_' . $this->solution_manager::POST_TYPE, esc_html__( 'Dependencies Configuration', 'pixelgradelt_retailer' ) )
		         ->where( 'post_type', '=', $this->solution_manager::POST_TYPE )
		         ->set_context( 'normal' )
		         ->set_priority( 'core' )
		         ->add_fields( [
				         Field::make( 'html', 'solutions_dependencies_configuration_html', __( 'Dependencies Description', 'pixelgradelt_retailer' ) )
				              ->set_html( sprintf( '<p class="description">%s</p>', __( 'Here you edit and configure <strong>the list of other solutions</strong> the current solution depends on (requires) or excludes.', 'pixelgradelt_retailer' ) ) ),

				         Field::make( 'complex', 'solution_required_solutions', __( 'Required Solutions', 'pixelgradelt_retailer' ) )
				              ->set_help_text( __( 'These are solutions that are <strong>automatically included in a site\'s composition</strong> together with the current solution. The order is not important, from a logic standpoint.<br>
<strong>FYI:</strong> Each required solution label is comprised of the solution <code>slug</code> and the <code>#post_id</code>.', 'pixelgradelt_retailer' ) )
				              ->set_classes( 'solution-required-solutions' )
				              ->set_collapsed( true )
				              ->add_fields( [
						              Field::make( 'select', 'pseudo_id', __( 'Choose one of the configured solutions', 'pixelgradelt_retailer' ) )
						                   ->set_options( [ $this, 'get_available_required_solutions_options' ] )
						                   ->set_default_value( null )
						                   ->set_required( true )
						                   ->set_width( 50 ),
				              ] )
				              ->set_header_template( '
								    <% if (pseudo_id) { %>
								        <%- pseudo_id %>
								    <% } %>
								' ),
				         Field::make( 'complex', 'solution_excluded_solutions', __( 'Excluded Solutions', 'pixelgradelt_retailer' ) )
				              ->set_help_text( __( 'These are solutions that are <strong>automatically removed from a site\'s composition</strong> when the current solution is included. The order is not important, from a logic standpoint.<br>
The excluded solutions only take effect in <strong>a purchase context (add to cart, etc.), not in a Composer context. When a solution is selected, its excluded solutions (and those of its required solutions) are removed from the customer\'s site selection.</strong><br>
<strong>FYI:</strong> Each replaced solution label is comprised of the solution <code>slug</code> and the <code>#post_id</code>.', 'pixelgradelt_retailer' ) )
				              ->set_classes( 'solution-required-solutions solution-excluded-solutions' )
				              ->set_collapsed( true )
				              ->add_fields( [
						              Field::make( 'select', 'pseudo_id', __( 'Choose one of the configured solutions', 'pixelgradelt_retailer' ) )
						                   ->set_options( [ $this, 'get_available_required_solutions_options' ] )
						                   ->set_default_value( null )
						                   ->set_required( true )
						                   ->set_width( 50 ),
				              ] )
				              ->set_header_template( '
								    <% if (pseudo_id) { %>
								        <%- pseudo_id %>
								    <% } %>
								' ),
		         ] );
	}

	public function add_solution_current_state_meta_box() {
		$post_type    = $this->solution_manager::POST_TYPE;
		$container_id = $post_type . '_current_state_details';
		add_meta_box(
				$container_id,
				esc_html__( 'Current Solution State Details', 'pixelgradelt_retailer' ),
				array( $this, 'display_solution_current_state_meta_box' ),
				$this->solution_manager::POST_TYPE,
				'normal',
				'core'
		);

		add_filter( "postbox_classes_{$post_type}_{$container_id}", [
				$this,
				'add_solution_current_state_box_classes',
		] );
	}

	/**
	 * Classes to add to the post meta box
	 *
	 * @param array $classes
	 *
	 * @return array
	 */
	public function add_solution_current_state_box_classes( array $classes ): array {
		$classes[] = 'carbon-box';

		return $classes;
	}

	/**
	 * Display Solution Current State meta box
	 *
	 * @param \WP_Post $post
	 */
	public function display_solution_current_state_meta_box( \WP_Post $post ) {
		// Wrap it for spacing.
		echo '<div class="cf-container"><div class="cf-field">';
		echo '<p>This is <strong>the same info</strong> shown in the full solution-details list available <a href="' . esc_url( admin_url( 'options-general.php?page=pixelgradelt_retailer#pixelgradelt_retailer-solutions' ) ) . '">here</a>. <strong>The definitive source of truth is the packages JSON</strong> available <a href="' . esc_url( get_solutions_permalink() ) . '">here</a>.</p>';
		require $this->plugin->get_path( 'views/solution-preview.php' );
		echo '</div></div>';
	}

	/**
	 * Given a post ID, find and remember the solution data corresponding to it.
	 *
	 * @since 0.14.0
	 *
	 * @param int $post_id
	 */
	protected function remember_post_solution_data( int $post_id ) {
		$solution_data = $this->solution_manager->get_solution_id_data( $post_id );
		if ( empty( $solution_data ) ) {
			return;
		}

		$this->pre_save_solution = $solution_data;
	}

	/**
	 * Check if the package can be resolved by Composer with the required solutions, excluded solutions, and required parts.
	 * Show a warning message if it can't be.
	 *
	 * @param int                           $post_ID
	 * @param Container\Post_Meta_Container $meta_container
	 */
	protected function check_required( int $post_ID, Container\Post_Meta_Container $meta_container ) {
		// At the moment, we are only interested in the source_configuration container.
		// This way we avoid running this logic unnecessarily for other containers.
		if ( empty( $meta_container->get_id() ) || 'carbon_fields_container_dependencies_configuration_' . $this->solution_manager::POST_TYPE !== $meta_container->get_id() ) {
			return;
		}

		$package = $this->solutions->first_where( [
				'managed_post_id' => $post_ID,
		] );
		if ( empty( $package ) ) {
			return;
		}

		// Transform the package in the Composer format.
		$package = $this->composer_transformer->transform( $package );

		if ( true !== ( $result = $this->solution_manager->dry_run_solution_require( $package ) ) ) {
			$message = '<p>';
			$message .= 'We could not resolve the solution dependencies. <strong>You should give the required parts and solutions a further look and then hit Update!</strong><br>';
			if ( $result instanceof \Exception ) {
				$message .= '<pre>' . $result->getMessage() . '</pre><br>';
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
	 * @since 0.1.0
	 *
	 * @return array
	 */
	public function get_available_required_solutions_options(): array {
		$options = [];

		// We exclude the current package post ID, of course.
		$exclude_post_ids = [ get_the_ID(), ];
		// We can't exclude the currently required packages because if we use carbon_get_post_meta()
		// to fetch the current complex field value, we enter an infinite loop since that requires the field options.
		// And to replicate the Carbon Fields logic to parse complex fields datastore is not fun.
		$solutions_ids = $this->solution_manager->get_solution_ids_by( [ 'exclude_post_ids' => $exclude_post_ids, ] );

		foreach ( $solutions_ids as $post_id ) {
			$solution_pseudo_id = $this->solution_manager->get_post_solution_slug( $post_id ) . $this->solution_manager::PSEUDO_ID_DELIMITER . $post_id;

			$options[ $solution_pseudo_id ] = sprintf( __( '%s - #%s', 'pixelgradelt_retailer' ), $this->solution_manager->get_post_solution_name( $post_id ), $post_id );
		}

		ksort( $options );

		// Prepend an empty option.
		$options = [ null => esc_html__( 'Pick a solution, carefully..', 'pixelgradelt_retailer' ) ] + $options;

		return $options;
	}

	/**
	 * Generate a list of select options from the LT Records available parts.
	 *
	 * @since 0.1.0
	 *
	 * @return array
	 */
	public function get_available_required_parts_options(): array {
		$options = [];

		foreach ( $this->get_ltrecords_parts() as $package_name ) {
			$options[ $package_name ] = $package_name;
		}

		ksort( $options );

		// Prepend an empty option.
		$options = [ null => esc_html__( 'Pick a part, carefully..', 'pixelgradelt_retailer' ) ] + $options;

		return $options;
	}

	/**
	 * @param bool $skip_cache
	 *
	 * @return array
	 */
	protected function get_ltrecords_parts( bool $skip_cache = false ): array {
		$parts = $this->solution_manager->get_ltrecords_parts( $skip_cache );
		if ( is_wp_error( $parts ) ) {
			$this->add_user_message( 'error', sprintf(
					'<p>%s</p>',
					$parts->get_error_message()
			) );

			return [];
		}

		if ( empty( $parts ) ) {
			$parts = [];
		}

		return $parts;
	}

	/**
	 * Check the solution post for possible issues, so the user is aware of them.
	 *
	 * @param \WP_Post The current post object.
	 */
	protected function check_solution_post( \WP_Post $post ) {
		if ( $this->solution_manager::POST_TYPE !== get_post_type( $post ) || 'auto-draft' === get_post_status( $post ) ) {
			return;
		}

		// Display an error regarding that the package title is required.
		if ( empty( $post->post_title ) ) {
			$this->add_user_message( 'error', sprintf(
					'<p>%s</p>',
					esc_html__( 'You MUST set a unique name (title) for creating a new package.', 'pixelgradelt_retailer' )
			) );
		}

		// Display an error regarding that the solution type is required.
		$solution_type = wp_get_object_terms( $post->ID, $this->solution_manager::TYPE_TAXONOMY, array(
				'orderby' => 'term_id',
				'order'   => 'ASC',
		) );
		if ( is_wp_error( $solution_type ) || empty( $solution_type ) ) {
			$taxonomy_args = $this->solution_manager->get_solution_type_taxonomy_args();
			$this->add_user_message( 'error', sprintf(
					'<p>%s</p>',
					sprintf( esc_html__( 'You MUST choose a %s for creating a new solution.', 'pixelgradelt_retailer' ), $taxonomy_args['labels']['singular_name'] )
			) );
		} else {
			$solution_type = reset( $solution_type );
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
	 * Add a certain user message type to the list for later display.
	 *
	 * @since 0.1.0
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
	 * @since 0.1.0
	 *
	 * @param \WP_Post The current post object.
	 */
	protected function show_user_messages( \WP_Post $post ) {
		if ( $this->solution_manager::POST_TYPE !== get_post_type( $post ) || 'auto-draft' === get_post_status( $post ) ) {
			return;
		}

		$messages = apply_filters( 'pixelgradelt_retailer/editsolution_show_user_messages', $this->user_messages, $post );
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
	 * @since 0.1.0
	 *
	 * @param \WP_Post The current post object.
	 */
	protected function show_publish_message( \WP_Post $post ) {
		if ( $this->solution_manager::POST_TYPE !== get_post_type( $post ) ) {
			return;
		}

		printf(
				'<div class="message patience"><p>%s</p></div>',
				wp_kses_post( __( 'Please bear in mind that Publish/Update may take a while since we do some heavy lifting behind the scenes.<br>Exercise patience 🦉', 'pixelgradelt_retailer' ) )
		);
	}

	/**
	 * Handle post update changes.
	 *
	 * @since 0.14.0
	 *
	 * @param int      $post_id Post ID.
	 * @param \WP_Post $post    Post object.
	 * @param bool     $update  Whether this is an existing post being updated.
	 */
	protected function handle_post_update( int $post_id, \WP_Post $post, bool $update ) {
		if ( $this->solution_manager::POST_TYPE !== $post->post_type ) {
			return;
		}

		/**
		 * Fires on LT solution post save.
		 *
		 * @since 0.14.0
		 *
		 * @param int      $post_id The solution post ID.
		 * @param \WP_Post $post    The post object.
		 * @param bool     $update  If the operation was an update.
		 */
		do_action( 'pixelgradelt_retailer/ltsolution/save',
				$post_id,
				$post,
				$update
		);

		if ( ! $update ) {
			return;
		}
		// If we don't have the pre-update solution data, we have nothing to compare to.
		if ( empty( $this->pre_save_solution ) ) {
			return;
		}
		$old_solution = $this->pre_save_solution;

		$current_solution = $this->solution_manager->get_solution_id_data( $post_id );
		if ( empty( $current_solution ) ) {
			return;
		}

		/*
		 * Handle solution visibility change.
		 */
		if ( ! empty( $old_solution['visibility'] ) && $old_solution['visibility'] !== $current_solution['visibility'] ) {
			/**
			 * Fires on LT solution visibility change.
			 *
			 * @since 0.14.0
			 *
			 * @param int    $post_id        The solution post ID.
			 * @param string $new_visibility The new solution visibility.
			 * @param string $old_visibility The old solution visibility.
			 * @param array  $new_solution   The new solution data.
			 */
			do_action( 'pixelgradelt_retailer/ltsolution/visibility_change',
					$post_id,
					$current_solution['visibility'],
					$old_solution['visibility'],
					$current_solution
			);
		}

		/*
		 * Handle solution type change.
		 */
		if ( ! empty( $old_solution['type'] ) && $old_solution['type'] !== $current_solution['type'] ) {
			/**
			 * Fires on LT solution type change.
			 *
			 * @since 0.14.0
			 *
			 * @param int    $post_id      The solution post ID.
			 * @param string $new_type     The new solution type.
			 * @param string $old_type     The old solution type.
			 * @param array  $new_solution The new solution data.
			 */
			do_action( 'pixelgradelt_retailer/ltsolution/type_change',
					$post_id,
					$current_solution['type'],
					$old_solution['type'],
					$current_solution
			);
		}

		/*
		 * Handle solution slug change.
		 */
		if ( ! empty( $old_solution['slug'] ) && $old_solution['slug'] !== $current_solution['slug'] ) {
			/**
			 * Fires on LT solution slug change.
			 *
			 * @since 0.14.0
			 *
			 * @param int    $post_id      The solution post ID.
			 * @param string $new_slug     The new solution slug.
			 * @param string $old_slug     The old solution slug.
			 * @param array  $new_solution The new solution data.
			 */
			do_action( 'pixelgradelt_retailer/ltsolution/slug_change',
					$post_id,
					$current_solution['slug'],
					$old_solution['slug'],
					$current_solution
			);
		}

		/*
		 * Handle solution required LT Parts changes.
		 */
		// We are only interested in actual LT Parts changes, not version range changes.
		// That is why we will only look at the required LT Part package name.
		$old_required_ltparts_package_name = \wp_list_pluck( $old_solution['required_ltrecords_parts'], 'package_name' );
		sort( $old_required_ltparts_package_name );

		$current_required_ltparts_package_name = wp_list_pluck( $current_solution['required_ltrecords_parts'], 'package_name' );
		sort( $current_required_ltparts_package_name );

		if ( serialize( $old_required_ltparts_package_name ) !== serialize( $current_required_ltparts_package_name ) ) {
			/**
			 * Fires on LT solution required solutions change (post ID changes).
			 *
			 * @since 0.14.0
			 *
			 * @param int   $post_id              The solution post ID.
			 * @param array $new_required_ltparts The new solution required_ltparts.
			 * @param array $old_required_ltparts The old solution required_ltparts.
			 * @param array $new_solution         The new solution data.
			 */
			do_action( 'pixelgradelt_retailer/ltsolution/required_ltparts_change',
					$post_id,
					$current_solution['required_ltrecords_parts'],
					$old_solution['required_ltrecords_parts'],
					$current_solution
			);
		}

		/*
		 * Handle solution required solutions changes.
		 */
		// We are only interested in actual solutions changes, not slug or context details changes.
		// That is why we will only look at the required solution post ID (managed_post_id).
		$old_required_solutions          = ArrayHelpers::array_map_assoc( function ( $key, $solution ) {
			// If we return the post ID as the key (as we would like), the key will be lost since it's numeric.
			return [ $solution['slug'] => $solution['managed_post_id'] ];
		}, $old_solution['required_solutions'] );
		$old_required_solutions          = array_flip( $old_required_solutions );
		$old_required_solutions_post_ids = array_keys( $old_required_solutions );
		sort( $old_required_solutions_post_ids );

		$current_required_solutions          = ArrayHelpers::array_map_assoc( function ( $key, $solution ) {
			// If we return the post ID as the key (as we would like), the key will be lost since it's numeric.
			return [ $solution['slug'] => $solution['managed_post_id'] ];
		}, $current_solution['required_solutions'] );
		$current_required_solutions          = array_flip( $current_required_solutions );
		$current_required_solutions_post_ids = array_keys( $current_required_solutions );
		sort( $current_required_solutions_post_ids );

		if ( serialize( $old_required_solutions_post_ids ) !== serialize( $current_required_solutions_post_ids ) ) {
			/**
			 * Fires on LT solution required solutions change (post ID changes).
			 *
			 * @since 0.14.0
			 *
			 * @param int   $post_id                The solution post ID.
			 * @param array $new_required_solutions The new solution required_solutions.
			 * @param array $old_required_solutions The old solution required_solutions.
			 * @param array $new_solution           The new solution data.
			 */
			do_action( 'pixelgradelt_retailer/ltsolution/required_solutions_change',
					$post_id,
					$current_required_solutions,
					$old_required_solutions,
					$current_solution
			);
		}

		/*
		 * Handle solution excluded solutions changes.
		 */
		// We are only interested in actual solutions changes, not slug or context details changes.
		// That is why we will only look at the excluded solution post ID (managed_post_id).
		$old_excluded_solutions          = ArrayHelpers::array_map_assoc( function ( $key, $solution ) {
			// If we return the post ID as the key (as we would like), the key will be lost since it's numeric.
			return [ $solution['slug'] => $solution['managed_post_id'] ];
		}, $old_solution['excluded_solutions'] );
		$old_excluded_solutions          = array_flip( $old_excluded_solutions );
		$old_excluded_solutions_post_ids = array_keys( $old_excluded_solutions );
		sort( $old_excluded_solutions_post_ids );

		$current_excluded_solutions          = ArrayHelpers::array_map_assoc( function ( $key, $solution ) {
			// If we return the post ID as the key (as we would like), the key will be lost since it's numeric.
			return [ $solution['slug'] => $solution['managed_post_id'] ];
		}, $current_solution['excluded_solutions'] );
		$current_excluded_solutions          = array_flip( $current_excluded_solutions );
		$current_excluded_solutions_post_ids = array_keys( $current_excluded_solutions );
		sort( $current_excluded_solutions_post_ids );

		if ( serialize( $old_excluded_solutions_post_ids ) !== serialize( $current_excluded_solutions_post_ids ) ) {
			/**
			 * Fires on LT solution excluded solutions change (post ID changes).
			 *
			 * @since 0.14.0
			 *
			 * @param int   $post_id                The solution post ID.
			 * @param array $new_excluded_solutions The new solution excluded_solutions.
			 * @param array $old_excluded_solutions The old solution excluded_solutions.
			 * @param array $new_solution           The new solution data.
			 */
			do_action( 'pixelgradelt_retailer/ltsolution/excluded_solutions_change',
					$post_id,
					$current_excluded_solutions,
					$old_excluded_solutions,
					$current_solution
			);
		}

		/**
		 * Fires on LT solution update, after the individual change hooks have been fired.
		 *
		 * @since 0.14.0
		 *
		 * @param int   $post_id      The solution post ID.
		 * @param array $new_solution The new solution data.
		 * @param array $old_solution The old solution data.
		 */
		do_action( 'pixelgradelt_retailer/ltsolution/update',
				$post_id,
				$current_solution,
				$old_solution
		);
	}

	/**
	 * If the slug/package name changes, we need to update things like the pseudo IDs meta-data for dependants.
	 *
	 * @since 0.13.0
	 *
	 * @param int    $post_id  The solution post ID.
	 * @param string $new_slug The new solution slug.
	 * @param string $old_slug The old solution slug.
	 */
	protected function on_slug_change( int $post_id, string $new_slug, string $old_slug ) {
		if ( $this->solution_manager::POST_TYPE !== get_post_type( $post_id ) ) {
			return;
		}

		// Determine if the slug hasn't changed, just in case. Bail if so.
		if ( $new_slug === $old_slug ) {
			return;
		}

		// We have work to do.

		// At the moment, we are only interested in certain meta entries.
		// Replace pseudo IDs.
		$prev_solution_pseudo_id = $old_slug . $this->solution_manager::PSEUDO_ID_DELIMITER . $post_id;
		$new_solution_pseudo_id  = $new_slug . $this->solution_manager::PSEUDO_ID_DELIMITER . $post_id;

		global $wpdb;
		$wpdb->get_results( $wpdb->prepare( "
UPDATE $wpdb->postmeta m
JOIN $wpdb->posts p ON m.post_id = p.ID
SET m.meta_value = REPLACE(m.meta_value, %s, %s)
WHERE m.meta_key LIKE '%pseudo_id%' AND p.post_type <> 'revision'", $prev_solution_pseudo_id, $new_solution_pseudo_id ) );

		// Flush the entire cache since we don't know what post IDs might have been affected.
		// It is OK since this is a rare operation.
		wp_cache_flush();
	}

	/**
	 * Add post note on LT solution visibility change.
	 *
	 * @since 0.14.0
	 *
	 * @param int    $post_id        The solution post ID.
	 * @param string $new_visibility The new solution visibility.
	 * @param string $old_visibility The old solution visibility.
	 */
	protected function add_solution_visibility_change_note( int $post_id, string $new_visibility, string $old_visibility ) {
		$note = sprintf(
				esc_html__( 'Solution visibility changed from %1$s to %2$s.', 'pixelgradelt_retailer' ),
				'<strong>' . $old_visibility . '</strong>',
				'<strong>' . $new_visibility . '</strong>'
		);

		create_note( $post_id, $note, 'internal', true );
	}

	/**
	 * Add post note on LT solution visibility change.
	 *
	 * @since 0.14.0
	 *
	 * @param int    $post_id  The solution post ID.
	 * @param string $new_type The new solution type slug.
	 * @param string $old_type The old solution type slug.
	 */
	protected function add_solution_type_change_note( int $post_id, string $new_type, string $old_type ) {
		$note = sprintf(
				esc_html__( 'Solution type changed from %1$s to %2$s.', 'pixelgradelt_retailer' ),
				'<strong>' . $old_type . '</strong>',
				'<strong>' . $new_type . '</strong>'
		);

		create_note( $post_id, $note, 'internal', true );
	}

	/**
	 * Add post note on LT solution slug change.
	 *
	 * @since 0.14.0
	 *
	 * @param int    $post_id  The solution post ID.
	 * @param string $new_slug The new solution slug.
	 * @param string $old_slug The old solution slug.
	 */
	protected function add_solution_slug_change_note( int $post_id, string $new_slug, string $old_slug ) {
		$note = sprintf(
				esc_html__( 'Solution slug (package name) changed from %1$s to %2$s.', 'pixelgradelt_retailer' ),
				'<strong>' . $old_slug . '</strong>',
				'<strong>' . $new_slug . '</strong>'
		);

		create_note( $post_id, $note, 'internal', true );
	}

	/**
	 * Add post note on LT solution required solutions change.
	 *
	 * @since 0.14.0
	 *
	 * @param int   $post_id                The solution post ID.
	 * @param array $new_required_solutions The new solution required_solutions.
	 * @param array $old_required_solutions The old solution required_solutions.
	 */
	protected function add_solution_required_solutions_change_note( int $post_id, array $new_required_solutions, array $old_required_solutions ) {
		$old_required_solutions_post_ids = array_keys( $old_required_solutions );
		sort( $old_required_solutions_post_ids );

		$new_required_solutions_post_ids = array_keys( $new_required_solutions );
		sort( $new_required_solutions_post_ids );

		$added   = array_diff( $new_required_solutions_post_ids, $old_required_solutions_post_ids );
		$removed = array_diff( $old_required_solutions_post_ids, $new_required_solutions_post_ids );

		$note = '';
		if ( ! empty( $removed ) ) {
			$removed_list = [];
			foreach ( $removed as $removed_post_id ) {
				$removed_list[] = $old_required_solutions[ $removed_post_id ] . $this->solution_manager::PSEUDO_ID_DELIMITER . $removed_post_id;
			}

			$note .= sprintf(
					esc_html__( 'Removed these required solutions: %1$s. ', 'pixelgradelt_retailer' ),
					'<strong>' . implode( ', ', $removed_list ) . '</strong>'
			);
		}

		if ( ! empty( $added ) ) {
			$added_list = [];
			foreach ( $added as $added_post_id ) {
				$added_list[] = $new_required_solutions[ $added_post_id ] . $this->solution_manager::PSEUDO_ID_DELIMITER . $added_post_id;
			}

			$note .= sprintf(
					esc_html__( 'Added the following required solutions: %1$s. ', 'pixelgradelt_retailer' ),
					'<strong>' . implode( ', ', $added_list ) . '</strong>'
			);
		}

		if ( ! empty( trim( $note ) ) ) {
			create_note( $post_id, $note, 'internal', true );
		}
	}

	/**
	 * Add post note on LT solution excluded solutions change.
	 *
	 * @since 0.14.0
	 *
	 * @param int   $post_id                The solution post ID.
	 * @param array $new_excluded_solutions The new solution excluded_solutions.
	 * @param array $old_excluded_solutions The old solution excluded_solutions.
	 */
	protected function add_solution_excluded_solutions_change_note( int $post_id, array $new_excluded_solutions, array $old_excluded_solutions ) {
		$old_excluded_solutions_post_ids = array_keys( $old_excluded_solutions );
		sort( $old_excluded_solutions_post_ids );

		$new_excluded_solutions_post_ids = array_keys( $new_excluded_solutions );
		sort( $new_excluded_solutions_post_ids );

		$added   = array_diff( $new_excluded_solutions_post_ids, $old_excluded_solutions_post_ids );
		$removed = array_diff( $old_excluded_solutions_post_ids, $new_excluded_solutions_post_ids );

		$note = '';
		if ( ! empty( $removed ) ) {
			$removed_list = [];
			foreach ( $removed as $removed_post_id ) {
				$removed_list[] = $old_excluded_solutions[ $removed_post_id ] . $this->solution_manager::PSEUDO_ID_DELIMITER . $removed_post_id;
			}

			$note .= sprintf(
					esc_html__( 'Removed these excluded solutions: %1$s. ', 'pixelgradelt_retailer' ),
					'<strong>' . implode( ', ', $removed_list ) . '</strong>'
			);
		}

		if ( ! empty( $added ) ) {
			$added_list = [];
			foreach ( $added as $added_post_id ) {
				$added_list[] = $new_excluded_solutions[ $added_post_id ] . $this->solution_manager::PSEUDO_ID_DELIMITER . $added_post_id;
			}

			$note .= sprintf(
					esc_html__( 'Added the following excluded solutions: %1$s. ', 'pixelgradelt_retailer' ),
					'<strong>' . implode( ', ', $added_list ) . '</strong>'
			);
		}

		if ( ! empty( trim( $note ) ) ) {
			create_note( $post_id, $note, 'internal', true );
		}
	}
}
