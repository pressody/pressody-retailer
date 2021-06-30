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
use function PixelgradeLT\Retailer\ensure_packages_json_url;
use function PixelgradeLT\Retailer\get_setting;
use function PixelgradeLT\Retailer\get_solutions_permalink;
use function PixelgradeLT\Retailer\is_debug_mode;
use function PixelgradeLT\Retailer\is_dev_url;
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

		// Check that the package can be resolved with the required packages.
		$this->add_action( 'carbon_fields_post_meta_container_saved', 'check_required', 20, 2 );

		// We get early so we can show error messages.
		$this->add_action( 'plugins_loaded', 'get_ltrecords_parts', 20 );
		// Show edit post screen error messages.
		$this->add_action( 'edit_form_top', 'check_solution_post', 5 );
		$this->add_action( 'edit_form_top', 'show_user_messages', 50 );

		// Add a message to the post publish metabox.
		$this->add_action( 'post_submitbox_start', 'show_publish_message' );
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
	 * Instead save as draft.
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
<strong>FYI:</strong> Each required part label is comprised of the standardized <code>package_name</code> and the <code>#post_id</code>.', 'pixelgradelt_retailer' ) )
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

		$pseudo_id_delimiter = ' #';

		// We exclude the current package post ID, of course.
		$exclude_post_ids = [ get_the_ID(), ];
		// We can't exclude the currently required packages because if we use carbon_get_post_meta()
		// to fetch the current complex field value, we enter an infinite loop since that requires the field options.
		// And to replicate the Carbon Fields logic to parse complex fields datastore is not fun.
		$solutions_ids = $this->solution_manager->get_solution_ids_by( [ 'exclude_post_ids' => $exclude_post_ids, ] );

		foreach ( $solutions_ids as $post_id ) {
			$solution_pseudo_id = $this->solution_manager->get_post_solution_slug( $post_id ) . $pseudo_id_delimiter . $post_id;

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
	 * Display user messages at the top of the post edit screen.
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
}
