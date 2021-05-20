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
use Carbon_Fields\Helper\Helper;
use Cedaro\WP\Plugin\AbstractHookProvider;
use PixelgradeLT\Retailer\Transformer\ComposerPackageTransformer;
use PixelgradeLT\Retailer\Package;
use PixelgradeLT\Retailer\SolutionManager;
use PixelgradeLT\Retailer\SolutionType\SolutionTypes;
use PixelgradeLT\Retailer\Repository\PackageRepository;
use function PixelgradeLT\Retailer\get_solutions_permalink;

/**
 * Edit Solution screen provider class.
 *
 * @since 0.1.0
 */
class EditSolution extends AbstractHookProvider {

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
	 * @var Package|null
	 */
	protected ?Package $pre_save_solution = null;

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
		$this->add_action( 'add_meta_boxes_' . $this->solution_manager::POST_TYPE, 'add_package_current_state_meta_box', 10 );
		$this->add_action( 'add_meta_boxes_' . $this->solution_manager::POST_TYPE, 'adjust_core_metaboxes', 99 );

		// ADD CUSTOM POST META VIA CARBON FIELDS.
		$this->add_action( 'plugins_loaded', 'carbonfields_load' );
		$this->add_action( 'carbon_fields_register_fields', 'attach_post_meta_fields' );
		// Fetch external packages releases and cache them (the artifacts will not be cached here).
		$this->add_action( 'carbon_fields_post_meta_container_saved', 'fetch_external_packages_on_post_save', 5, 2 );
		// Fill empty package details from source.
		$this->add_action( 'carbon_fields_post_meta_container_saved', 'fill_empty_package_config_details_on_post_save', 10, 2 );
		// Check that the package can be resolved with the required packages.
		$this->add_action( 'carbon_fields_post_meta_container_saved', 'check_required_packages', 20, 2 );

		// Handle post data transform before the post is updated in the DB (like changing the source type)
		$this->add_action( 'pre_post_update', 'remember_post_package', 10, 1 );
		$this->add_action( 'pre_post_update', 'maybe_clean_up_manual_release_post_data', 10, 1 );
		$this->add_action( 'carbon_fields_post_meta_container_saved', 'maybe_migrate_releases_on_manual_source_switch', 10, 2 );

		// Show edit post screen error messages.
		$this->add_action( 'edit_form_top', 'check_package_post', 5 );
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
		add_action( 'admin_footer', [ $this, 'print_templates' ] );
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

	/**
	 * Print Underscore.js templates.
	 *
	 * @since 0.1.0
	 */
	public function print_templates() {
		include $this->plugin->get_path( 'views/templates.php' );
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
			<?php _e( '<strong>The post slug is, at the same time, the Composer PROJECT NAME.</strong> It is best to use <strong>the exact plugin or theme slug!</strong><br>
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
		// Register the metabox for managing the source details of the package.
		Container::make( 'post_meta', 'carbon_fields_container_source_configuration_' . $this->solution_manager::POST_TYPE, esc_html__( 'Source Configuration', 'pixelgradelt_retailer' ) )
		         ->where( 'post_type', '=', $this->solution_manager::POST_TYPE )
		         ->set_context( 'normal' )
		         ->set_priority( 'core' )
		         ->add_fields( [
				         Field::make( 'html', 'source_configuration_html', __( 'Section Description', 'pixelgradelt_retailer' ) )
				              ->set_html( sprintf( '<p class="description">%s</p>', __( 'First, configure details about <strong>where from should we get package/versions</strong> for this package.', 'pixelgradelt_retailer' ) ) ),

				         Field::make( 'select', 'package_source_type', __( 'Set the package source type', 'pixelgradelt_retailer' ) )
				              ->set_help_text( __( 'Composer works with packages and repositories to find the core to use for the defined dependencies. We will strive to keep as close to that in terms of concepts. Learn more about it <a href="https://getcomposer.org/doc/05-repositories.md#repository" target="_blank">here</a>.', 'pixelgradelt_retailer' ) )
				              ->set_options( [
						              null             => esc_html__( 'Pick your package source, carefully..', 'pixelgradelt_retailer' ),
						              'packagist.org'  => esc_html__( 'A Packagist.org public repo', 'pixelgradelt_retailer' ),
						              'wpackagist.org' => esc_html__( 'A WPackagist.org repo (mirror of wordpress.org)', 'pixelgradelt_retailer' ),
						              'vcs'            => esc_html__( 'A VCS repo (git, SVN, fossil or hg)', 'pixelgradelt_retailer' ),
						              'local.plugin'   => esc_html__( 'A plugin installed on this WordPress installation', 'pixelgradelt_retailer' ),
						              'local.theme'    => esc_html__( 'A theme installed on this WordPress installation', 'pixelgradelt_retailer' ),
						              'local.manual'   => esc_html__( 'A local repo: package releases/versions are managed here, manually', 'pixelgradelt_retailer' ),
				              ] )
				              ->set_default_value( null )
				              ->set_required( true )
				              ->set_width( 50 ),

				         Field::make( 'text', 'package_source_name', __( 'Package Source Name', 'pixelgradelt_retailer' ) )
				              ->set_help_text( __( 'Composer identifies a certain package (the package name) by its project name and vendor, resulting in a <code>vendor/projectname</code> package name. Learn more about it <a href="https://getcomposer.org/doc/04-schema.md#name" target="_blank">here</a>. Most often you will find the correct project name in the project\'s <code>composer.json</code> file, under the <code>"name"</code> JSON key.<br>The vendor and project name must be lowercased and consist of words separated by <code>-</code>, <code>.</code> or <code>_</code>.<br><strong>Provide the whole package name (e.g. <code>wp-media/wp-rocket</code>)!</strong>', 'pixelgradelt_retailer' ) )
				              ->set_width( 50 )
				              ->set_conditional_logic( [
						              'relation' => 'AND', // Optional, defaults to "AND"
						              [
								              'field'   => 'package_source_type',
							              // Optional, defaults to "". Should be an array if "IN" or "NOT IN" operators are used.
								              'value'   => [ 'packagist.org', 'vcs', ],
							              // Optional, defaults to "=". Available operators: =, <, >, <=, >=, IN, NOT IN
								              'compare' => 'IN',
						              ],
				              ] ),

				         Field::make( 'text', 'package_source_project_name', __( 'Package Source Project Name', 'pixelgradelt_retailer' ) )
				              ->set_help_text( __( 'Composer identifies a certain package by its project name and vendor, resulting in a <code>vendor/name</code> identifier. Learn more about it <a href="https://getcomposer.org/doc/04-schema.md#name" target="_blank">here</a>.<br>The project name must be lowercased and consist of words separated by <code>-</code>, <code>.</code> or <code>_</code>.<br><strong>Provide only the project name (e.g. <code>akismet</code>), not the whole package name (e.g. <code>wpackagist-plugin/akismet</code>)!</strong>', 'pixelgradelt_retailer' ) )
				              ->set_width( 50 )
				              ->set_conditional_logic( [
						              'relation' => 'AND', // Optional, defaults to "AND"
						              [
								              'field'   => 'package_source_type',
							              // Optional, defaults to "". Should be an array if "IN" or "NOT IN" operators are used.
								              'value'   => [ 'wpackagist.org', ],
							              // Optional, defaults to "=". Available operators: =, <, >, <=, >=, IN, NOT IN
								              'compare' => 'IN',
						              ],
				              ] ),

				         Field::make( 'text', 'package_source_version_range', __( 'Package Source Version Range', 'pixelgradelt_retailer' ) )
				              ->set_help_text( __( 'A certain source can contain tens or even hundreds of historical versions/releases. <strong>It is wasteful to pull all those in</strong> (and cache them) if we are only interested in the latest major version, for example.<br>
 Specify a version range to <strong>limit the available versions/releases for this package.</strong> Most likely you will only lower-bound your range (e.g. <code>>2.0</code>), but that is up to you.<br>
 Learn more about Composer <a href="https://getcomposer.org/doc/articles/versions.md#writing-version-constraints" target="_blank">versions</a> or <a href="https://semver.mwl.be/?package=madewithlove%2Fhtaccess-cli&constraint=%3C1.2%20%7C%7C%20%3E1.6&stability=stable" target="_blank">play around</a> with version ranges.', 'pixelgradelt_retailer' ) )
				              ->set_width( 75 )
				              ->set_conditional_logic( [
						              'relation' => 'AND', // Optional, defaults to "AND"
						              [
								              'field'   => 'package_source_type',
							              // Optional, defaults to "". Should be an array if "IN" or "NOT IN" operators are used.
								              'value'   => [ 'packagist.org', 'wpackagist.org', 'vcs' ],
							              // Optional, defaults to "=". Available operators: =, <, >, <=, >=, IN, NOT IN
								              'compare' => 'IN',
						              ],
				              ] ),
				         Field::make( 'select', 'package_source_stability', __( 'Package Source Stability', 'pixelgradelt_retailer' ) )
				              ->set_help_text( __( 'Limit the minimum stability required for versions. <code>Stable</code> is the most restrictive one, while <code>dev</code> the most all encompassing.<br><code>Stable</code> is the recommended (and default) one.', 'pixelgradelt_retailer' ) )
				              ->set_width( 25 )
				              ->set_options( [
						              'stable' => esc_html__( 'Stable', 'pixelgradelt_retailer' ),
					              /** The uppercase 'RC' key is important. @see BasePackage::$stabilities */
						              'RC'     => esc_html__( 'RC', 'pixelgradelt_retailer' ),
						              'beta'   => esc_html__( 'Beta', 'pixelgradelt_retailer' ),
						              'alpha'  => esc_html__( 'Alpha', 'pixelgradelt_retailer' ),
						              'dev'    => esc_html__( 'Dev', 'pixelgradelt_retailer' ),
				              ] )
				              ->set_default_value( 'stable' )
				              ->set_conditional_logic( [
						              'relation' => 'AND', // Optional, defaults to "AND"
						              [
								              'field'   => 'package_source_type',
							              // Optional, defaults to "". Should be an array if "IN" or "NOT IN" operators are used.
								              'value'   => [ 'packagist.org', 'wpackagist.org', 'vcs' ],
							              // Optional, defaults to "=". Available operators: =, <, >, <=, >=, IN, NOT IN
								              'compare' => 'IN',
						              ],
				              ] ),

				         Field::make( 'text', 'package_vcs_url', __( 'Package VCS URL', 'pixelgradelt_retailer' ) )
				              ->set_help_text( __( 'Just provide the full URL to your VCS repo (e.g. a Github repo URL like <code>https://github.com/pixelgradelt/satispress</code>). Learn more about it <a href="https://getcomposer.org/doc/05-repositories.md#vcs" target="_blank">here</a>.', 'pixelgradelt_retailer' ) )
				              ->set_conditional_logic( [
						              'relation' => 'AND', // Optional, defaults to "AND"
						              [
								              'field'   => 'package_source_type',
							              // Optional, defaults to "". Should be an array if "IN" or "NOT IN" operators are used.
								              'value'   => 'vcs',
							              // Optional, defaults to "=". Available operators: =, <, >, <=, >=, IN, NOT IN
								              'compare' => '=',
						              ],
				              ] ),

				         Field::make( 'select', 'package_local_plugin_file', __( 'Choose one of the installed plugins', 'pixelgradelt_retailer' ) )
				              ->set_help_text( __( 'Installed plugins that are already attached to a package are NOT part of the list of choices.', 'pixelgradelt_retailer' ) )
				              ->set_options( [ $this, 'get_available_installed_plugins_options' ] )
				              ->set_default_value( null )
				              ->set_required( true )
				              ->set_width( 50 )
				              ->set_conditional_logic( [
						              'relation' => 'AND', // Optional, defaults to "AND"
						              [
								              'field'   => 'package_source_type',
							              // Optional, defaults to "". Should be an array if "IN" or "NOT IN" operators are used.
								              'value'   => 'local.plugin',
							              // Optional, defaults to "=". Available operators: =, <, >, <=, >=, IN, NOT IN
								              'compare' => '=',
						              ],
				              ] ),
				         Field::make( 'select', 'package_local_theme_slug', __( 'Choose one of the installed themes', 'pixelgradelt_retailer' ) )
				              ->set_help_text( __( 'Installed themes that are already attached to a package are NOT part of the list of choices.', 'pixelgradelt_retailer' ) )
				              ->set_options( [ $this, 'get_available_installed_themes_options' ] )
				              ->set_default_value( null )
				              ->set_required( true )
				              ->set_width( 50 )
				              ->set_conditional_logic( [
						              'relation' => 'AND', // Optional, defaults to "AND"
						              [
								              'field'   => 'package_source_type',
							              // Optional, defaults to "". Should be an array if "IN" or "NOT IN" operators are used.
								              'value'   => 'local.theme',
							              // Optional, defaults to "=". Available operators: =, <, >, <=, >=, IN, NOT IN
								              'compare' => '=',
						              ],
				              ] ),

				         Field::make( 'complex', 'package_manual_releases', __( 'Package Releases', 'pixelgradelt_retailer' ) )
				              ->set_help_text( __( 'The manually uploaded package releases (zips).<br>
<strong>These zip files will be cached</strong> just like external or installed sources. If you remove a certain release and update the post, the cache will keep up and auto-clean itself.<br>
<strong>If you upload a different zip to a previously published release, the cache will not auto-update itself</strong> (for performance reasons). In this case, first delete the release, hit "Update" for the post and them add a new release.<br>
Also, bear in mind that <strong>we do not clean the Media Gallery of unused zip files.</strong> That is up to you, if you can\'t stand some mess.<br><br>
<em>TIP: If you <strong>switch the package type to manual entries,</strong> hit "Update" and all existing, stored releases will be migrated for you to manually manage.</em>', 'pixelgradelt_retailer' ) )
				              ->set_classes( 'package-manual-releases' )
				              ->set_collapsed( true )
				              ->add_fields( [
						              Field::make( 'text', 'version', __( 'Version', 'pixelgradelt_retailer' ) )
						                   ->set_help_text( __( 'Semver-formatted version string. Bear in mind that we currently don\'t do any check regarding the version. It is up to you to <strong>make sure that the zip file contents match the version specified.</strong>', 'pixelgradelt_retailer' ) )
						                   ->set_required( true )
						                   ->set_width( 25 ),
						              Field::make( 'file', 'file', __( 'Zip File', 'pixelgradelt_retailer' ) )
						                   ->set_type( 'zip' ) // The allowed mime-types (see wp_get_mime_types())
						                   ->set_value_type( 'id' ) // Change to 'url' to store the file/attachment URL instead of the attachment ID.
						                   ->set_required( true )
						                   ->set_width( 50 ),
				              ] )
				              ->set_header_template( '
								    <% if (version) { %>
								        Version: <%- version %>
								    <% } %>
								    <% if (file) { %>
								        (file ID or URL: <%- file %>)
								    <% } %>
								' )
				              ->set_conditional_logic( [
						              'relation' => 'AND', // Optional, defaults to "AND"
						              [
								              'field'   => 'package_source_type',
								              'value'   => 'local.manual',
								              'compare' => '=',
						              ],
				              ] ),


				         Field::make( 'separator', 'package_details_separator', '' )
				              ->set_conditional_logic( [
						              [
								              'field'   => 'package_source_type',
								              'value'   => [
										              'packagist.org',
										              'wpackagist.org',
										              'vcs',
										              'local.plugin',
										              'local.theme',
										              'local.manual',
								              ],
								              'compare' => 'IN',
						              ],
				              ] ),
				         Field::make( 'html', 'package_details_html', __( 'Section Description', 'pixelgradelt_retailer' ) )
				              ->set_html( sprintf( '<p class="description">%s</p>', __( 'Configure details about <strong>the package itself,</strong> as it will be exposed for consumption.<br><strong>Leave empty</strong> and we will try to figure them out on save; after that you can modify them however you like.', 'pixelgradelt_retailer' ) ) )
				              ->set_conditional_logic( [
						              [
								              'field'   => 'package_source_type',
								              'value'   => [
										              'packagist.org',
										              'wpackagist.org',
										              'vcs',
										              'local.plugin',
										              'local.theme',
										              'local.manual',
								              ],
								              'compare' => 'IN',
						              ],
				              ] ),
				         Field::make( 'textarea', 'package_details_description', __( 'Package Description', 'pixelgradelt_retailer' ) )
				              ->set_conditional_logic( [
						              [
								              'field'   => 'package_source_type',
								              'value'   => [
										              'packagist.org',
										              'wpackagist.org',
										              'vcs',
										              'local.plugin',
										              'local.theme',
										              'local.manual',
								              ],
								              'compare' => 'IN',
						              ],
				              ] ),
				         Field::make( 'text', 'package_details_homepage', __( 'Package Homepage URL', 'pixelgradelt_retailer' ) )
				              ->set_conditional_logic( [
						              [
								              'field'   => 'package_source_type',
								              'value'   => [
										              'packagist.org',
										              'wpackagist.org',
										              'vcs',
										              'local.plugin',
										              'local.theme',
										              'local.manual',
								              ],
								              'compare' => 'IN',
						              ],
				              ] ),
				         Field::make( 'text', 'package_details_license', __( 'Package License', 'pixelgradelt_retailer' ) )
				              ->set_help_text( __( 'The package license in a standard format (e.g. <code>GPL-3.0-or-later</code>). If there are multiple licenses, comma separate them. Learn more about it <a href="https://getcomposer.org/doc/04-schema.md#license" target="_blank">here</a>.', 'pixelgradelt_retailer' ) )
				              ->set_conditional_logic( [
						              [
								              'field'   => 'package_source_type',
								              'value'   => [
										              'packagist.org',
										              'wpackagist.org',
										              'vcs',
										              'local.plugin',
										              'local.theme',
										              'local.manual',
								              ],
								              'compare' => 'IN',
						              ],
				              ] ),
				         Field::make( 'complex', 'package_details_authors', __( 'Package Authors', 'pixelgradelt_retailer' ) )
				              ->set_help_text( __( 'The package authors details. Learn more about it <a href="https://getcomposer.org/doc/04-schema.md#authors" target="_blank">here</a>.', 'pixelgradelt_retailer' ) )
				              ->add_fields( [
						              Field::make( 'text', 'name', __( 'Author Name', 'pixelgradelt_retailer' ) )->set_required( true )->set_width( 50 ),
						              Field::make( 'text', 'email', __( 'Author Email', 'pixelgradelt_retailer' ) )->set_width( 50 ),
						              Field::make( 'text', 'homepage', __( 'Author Homepage', 'pixelgradelt_retailer' ) )->set_width( 50 ),
						              Field::make( 'text', 'role', __( 'Author Role', 'pixelgradelt_retailer' ) )->set_width( 50 ),
				              ] )
				              ->set_header_template( '
							    <% if (name) { %>
							        <%= name %>
							    <% } %>
							  ' )
				              ->set_conditional_logic( [
						              [
								              'field'   => 'package_source_type',
								              'value'   => [
										              'packagist.org',
										              'wpackagist.org',
										              'vcs',
										              'local.plugin',
										              'local.theme',
										              'local.manual',
								              ],
								              'compare' => 'IN',
						              ],
				              ] ),
		         ] );

		// Register the metabox for managing the packages the current package depends on (dependencies that will translate in composer `require`s).
		Container::make( 'post_meta', 'carbon_fields_container_dependencies_configuration_' . $this->solution_manager::POST_TYPE, esc_html__( 'Dependencies Configuration', 'pixelgradelt_retailer' ) )
		         ->where( 'post_type', '=', $this->solution_manager::POST_TYPE )
		         ->set_context( 'normal' )
		         ->set_priority( 'core' )
		         ->add_fields( [
				         Field::make( 'html', 'dependencies_configuration_html', __( 'Dependencies Description', 'pixelgradelt_retailer' ) )
				              ->set_html( sprintf( '<p class="description">%s</p>', __( 'Here you edit and configure <strong>the list of other managed packages</strong> the current package depends on (required packages that translate into entries in Composer\'s <code>require</code> entries).<br>
For each required package you can <strong>specify a version range</strong> to better control the package releases/versions required. Set to <code>*</code> to <strong>use the latest available required-package release that matches all constraints</strong> (other packages in a module might impose stricter limits).<br>
Learn more about Composer <a href="https://getcomposer.org/doc/articles/versions.md#writing-version-constraints" target="_blank">versions</a> or <a href="https://semver.mwl.be/?package=madewithlove%2Fhtaccess-cli&constraint=%3C1.2%20%7C%7C%20%3E1.6&stability=stable" target="_blank">play around</a> with version ranges.', 'pixelgradelt_retailer' ) ) ),

				         Field::make( 'complex', 'package_required_packages', __( 'Required Packages', 'pixelgradelt_retailer' ) )
				              ->set_help_text( __( 'The order is not important, from a logic standpoint. Also, if you add <strong>the same package multiple times</strong> only the last one will take effect since it will overwrite the previous ones.<br>
<strong>FYI:</strong> Each required package label is comprised of the standardized <code>source_name</code> and the <code>#post_id</code>.', 'pixelgradelt_retailer' ) )
				              ->set_classes( 'package-required-packages' )
				              ->set_collapsed( true )
				              ->add_fields( [
						              Field::make( 'select', 'pseudo_id', __( 'Choose one of the managed packages', 'pixelgradelt_retailer' ) )
						                   ->set_help_text( __( 'Packages that are already required by this package are NOT part of the list of choices.', 'pixelgradelt_retailer' ) )
						                   ->set_options( [ $this, 'get_available_required_packages_options' ] )
						                   ->set_default_value( null )
						                   ->set_required( true )
						                   ->set_width( 50 ),
						              Field::make( 'text', 'version_range', __( 'Version Range', 'pixelgradelt_retailer' ) )
						                   ->set_default_value( '*' )
						                   ->set_required( true )
						                   ->set_width( 25 ),
						              Field::make( 'select', 'stability', __( 'Stability', 'pixelgradelt_retailer' ) )
						                   ->set_options( [
								                   'stable' => esc_html__( 'Stable', 'pixelgradelt_retailer' ),
								                   'rc'     => esc_html__( 'RC', 'pixelgradelt_retailer' ),
								                   'beta'   => esc_html__( 'Beta', 'pixelgradelt_retailer' ),
								                   'alpha'  => esc_html__( 'Alpha', 'pixelgradelt_retailer' ),
								                   'dev'    => esc_html__( 'Dev', 'pixelgradelt_retailer' ),
						                   ] )
						                   ->set_required( true )
						                   ->set_default_value( 'stable' )
						                   ->set_width( 25 ),
				              ] )
				              ->set_header_template( '
								    <% if (pseudo_id) { %>
								        <%- pseudo_id %> (version range: <%= version_range %><% if ("stable" !== stability) { %>@<%= stability %><% } %>)
								    <% } %>
								' ),
		         ] );
	}

	public function add_package_current_state_meta_box() {
		$post_type    = $this->solution_manager::POST_TYPE;
		$container_id = $post_type . '_current_state_details';
		add_meta_box(
				$container_id,
				esc_html__( 'Current Package State Details', 'pixelgradelt_retailer' ),
				array( $this, 'display_package_current_state_meta_box' ),
				$this->solution_manager::POST_TYPE,
				'normal',
				'core'
		);

		add_filter( "postbox_classes_{$post_type}_{$container_id}", [
				$this,
				'add_package_current_state_box_classes',
		] );
	}

	/**
	 * Classes to add to the post meta box
	 *
	 * @param array $classes
	 *
	 * @return array
	 */
	public function add_package_current_state_box_classes( array $classes ): array {
		$classes[] = 'carbon-box';

		return $classes;
	}

	/**
	 * Display Package Current State meta box
	 *
	 * @param \WP_Post $post
	 */
	public function display_package_current_state_meta_box( \WP_Post $post ) {
		$package_data = $this->solution_manager->get_solution_id_data( (int) $post->ID );
		if ( empty( $package_data ) || empty( $package_data['slug'] ) || empty( $package_data['type'] ) ) {
			echo '<div class="cf-container"><div class="cf-field"><p>No current package details. Probably you need to do some configuring first.</p></div></div>';

			return;
		}

		$package = $this->solutions->first_where( [
				'slug' => $package_data['slug'],
				'type' => $package_data['type'],
		] );

		if ( empty( $package ) ) {
			echo '<div class="cf-container"><div class="cf-field"><p>No current package details. Probably you need to do some configuring first.</p></div></div>';

			return;
		}

		// Transform the package in the Composer format.
		// This variable will be available to the view.
		$package = $this->composer_transformer->transform( $package );

		// Wrap it for spacing.
		echo '<div class="cf-container"><div class="cf-field">';
		echo '<p>This is <strong>the same info</strong> shown in the full package-details list available <a href="' . esc_url( admin_url( 'options-general.php?page=pixelgradelt_retailer#pixelgradelt_retailer-packages' ) ) . '">here</a>. <strong>The definitive source of truth is the packages JSON</strong> available <a href="' . esc_url( get_solutions_permalink() ) . '">here</a>.</p>';
		require $this->plugin->get_path( 'views/package-details.php' );
		echo '</div></div>';
	}

	/**
	 * Attempt to fetch external packages on post save.
	 *
	 * @param int                           $post_ID
	 * @param Container\Post_Meta_Container $meta_container
	 *
	 * @throws \Exception
	 */
	protected function fetch_external_packages_on_post_save( int $post_ID, Container\Post_Meta_Container $meta_container ) {
		// At the moment, we are only interested in the source_configuration container.
		// This way we avoid running this logic unnecessarily for other containers.
		if ( empty( $meta_container->get_id() ) || 'carbon_fields_container_source_configuration_' . $this->solution_manager::POST_TYPE !== $meta_container->get_id() ) {
			return;
		}

		$packages = $this->solution_manager->fetch_external_package_remote_releases( $post_ID );

		// We will save the packages (these are actually releases considering we tackle a single package) in the database.
		// For actually caching the zips, we will rely on PixelgradeLT\Retailer\PackageType\Builder\PackageBuilder::build() to do the work.
		if ( ! empty( $packages ) ) {
			update_post_meta( $post_ID, '_package_source_cached_release_packages', $packages );
		}
	}

	/**
	 * Check if the package can be resolved by Composer with the required packages. Show a warning message if it can't be.
	 *
	 * @param int                           $post_ID
	 * @param Container\Post_Meta_Container $meta_container
	 */
	protected function check_required_packages( int $post_ID, Container\Post_Meta_Container $meta_container ) {
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

		if ( false === $this->solution_manager->dry_run_package_require( $package ) ) {
			update_post_meta( $post_ID, '_package_require_dry_run_result', [
					'type'    => 'error',
					'message' => '<p>We could not resolve the package dependencies. <strong>You should give the required packages a further look and then hit Update!</strong><br>There should be additional information in the PixelgradeLT Retailer logs.</p>',
			] );
		} else {
			update_post_meta( $post_ID, '_package_require_dry_run_result', '' );
		}
	}

	/**
	 * Given a post ID, find and remember the package instance corresponding to it.
	 *
	 * @param int $post_ID
	 */
	protected function remember_post_package( int $post_ID ) {
		$package = $this->solutions->first_where( [ 'managed_post_id' => $post_ID ] );
		if ( empty( $package ) ) {
			return;
		}

		$this->pre_save_solution = $package;
	}

	/**
	 * Since there some issues with the CarbonFields File field, do some cleanup to the $_POST data.
	 *
	 * @see  https://github.com/htmlburger/carbon-fields/issues/1007
	 * @todo If the issue above gets fixed, we might not need this.
	 *
	 * @param int $post_ID
	 */
	protected function maybe_clean_up_manual_release_post_data( int $post_ID ) {
		if ( empty( $_POST['carbon_fields_compact_input']['_package_manual_releases'] ) || ! is_array( $_POST['carbon_fields_compact_input']['_package_manual_releases'] ) ) {
			return;
		}

		// We don't want to send manual releases that are missing the version, but have the file.
		foreach ( $_POST['carbon_fields_compact_input']['_package_manual_releases'] as $key => $value ) {
			if ( ! isset( $value['_version'] ) && isset( $value['_file'] ) ) {
				unset( $_POST['carbon_fields_compact_input']['_package_manual_releases'][ $key ] );
			}
		}
	}

	/**
	 * If we are switching to a manual source type, attempt to transform any existing stored releases to manual ones
	 * so they can be managed, and not simply lost.
	 *
	 * @since 0.1.0
	 *
	 * @param int                           $post_ID
	 * @param Container\Post_Meta_Container $meta_container
	 */
	protected function maybe_migrate_releases_on_manual_source_switch( int $post_ID, Container\Post_Meta_Container $meta_container ) {
		// At the moment, we are only interested in the source_configuration container.
		// This way we avoid running this logic unnecessarily for other containers.
		if ( empty( $meta_container->get_id() ) || 'carbon_fields_container_dependencies_configuration_' . $this->solution_manager::POST_TYPE !== $meta_container->get_id() ) {
			return;
		}

		if ( $this->solution_manager::POST_TYPE !== get_post_type( $post_ID ) ) {
			return;
		}

		if ( empty( $this->pre_save_solution ) ) {
			return;
		}

		// Get the CarbonFields $_POST input.
		$cb_input = Helper::input();

		// We want to migrate already stored releases to manual managed ones when the package source changes to local.manual.
		if ( ! empty( $cb_input['_package_source_type'] )
		     && 'local.manual' === $cb_input['_package_source_type']
		     && 'local.manual' !== $this->pre_save_solution->get_source_type()
		     && $this->pre_save_solution->has_releases()
		) {
			// We have work to do.

			// Get all package releases and merge them with any existing manually managed releases.
			$manual_releases_data      = $this->solution_manager->get_post_package_manual_releases( $post_ID );
			$updated                   = false;
			$manual_releases_meta_data = carbon_get_post_meta( $post_ID, 'package_manual_releases' );
			foreach ( $this->pre_save_solution->get_releases() as $release ) {
				try {
					$normalized_version = $this->solution_manager->normalize_version( $release->get_version() );
				} catch ( \UnexpectedValueException $e ) {
					continue;
				}

				if ( in_array( $normalized_version, array_keys( $manual_releases_data ) ) ) {
					// We don't want to overwrite existing releases. Just add the existing data.
					continue;
				}

				$updated                     = true;
				$manual_releases_meta_data[] = [
						'version' => $release->get_version(),
						'file'    => $release->get_source_url(),
				];
			}

			if ( $updated ) {
				// Order the manual releases by version, descending.
				usort( $manual_releases_meta_data, function ( $a, $b ) {
					return version_compare( $b['version'], $a['version'] );
				} );

				carbon_set_post_meta( $post_ID, 'package_manual_releases', $manual_releases_meta_data );
			}
		}
	}

	public function get_available_installed_plugins_options(): array {
		$options = [];

		$used_plugin_files = $this->solution_manager->get_managed_installed_plugins( [ 'post__not_in' => [ get_the_ID(), ], ] );
		foreach ( get_plugins() as $plugin_file => $plugin_data ) {
			// Do not include plugins already attached to a package.
			if ( in_array( $plugin_file, $used_plugin_files ) ) {
				continue;
			}

			$options[ $plugin_file ] = sprintf( __( '%s (by %s) - %s', 'pixelgradelt_retailer' ), $plugin_data['Name'], $plugin_data['Author'], $this->get_slug_from_plugin_file( $plugin_file ) );
		}

		ksort( $options );

		// Prepend an empty option.
		$options = [ null => esc_html__( 'Pick your installed plugin, carefully..', 'pixelgradelt_retailer' ) ] + $options;

		return $options;
	}

	/**
	 *
	 * @since 0.1.0
	 *
	 * @return array
	 */
	public function get_available_required_packages_options(): array {
		$options = [];

		$pseudo_id_delimiter = ' #';

		// We exclude the current package post ID, of course.
		$exclude_post_ids = [ get_the_ID(), ];
		// We can't exclude the currently required packages because if we use carbon_get_post_meta()
		// to fetch the current complex field value, we enter an infinite loop since that requires the field options.
		// And to replicate the Carbon Fields logic to parse complex fields datastore is not fun.
		$package_ids = $this->solution_manager->get_solution_ids_by( [ 'exclude_post_ids' => $exclude_post_ids, ] );

		foreach ( $package_ids as $post_id ) {
			$package_pseudo_id = $this->solution_manager->get_post_package_source_name( $post_id ) . $pseudo_id_delimiter . $post_id;

			$options[ $package_pseudo_id ] = sprintf( __( '%s - %s', 'pixelgradelt_retailer' ), $this->solution_manager->get_post_package_name( $post_id ), $package_pseudo_id );
		}

		ksort( $options );

		// Prepend an empty option.
		$options = [ null => esc_html__( 'Pick your required package, carefully..', 'pixelgradelt_retailer' ) ] + $options;

		return $options;
	}

	/**
	 * Retrieve a plugin slug.
	 *
	 * @since 0.1.0
	 *
	 * @param string $plugin_file Plugin slug or relative path to the main plugin
	 *                            file from the plugins directory.
	 *
	 * @return string
	 */
	protected function get_slug_from_plugin_file( string $plugin_file ): string {
		$slug = \dirname( $plugin_file );

		// Account for single file plugins.
		$slug = '.' === $slug ? basename( $plugin_file, '.php' ) : $slug;

		return $slug;
	}

	public function get_available_installed_themes_options(): array {
		$options = [];

		$used_theme_slugs = $this->solution_manager->get_managed_installed_themes( [ 'post__not_in' => [ get_the_ID(), ], ] );

		foreach ( wp_get_themes() as $theme_slug => $theme_data ) {
			// Do not include themes already attached to a package.
			if ( in_array( $theme_slug, $used_theme_slugs ) ) {
				continue;
			}

			$options[ $theme_slug ] = sprintf( __( '%s (by %s) - %s', 'pixelgradelt_retailer' ), $theme_data->get( 'Name' ), $theme_data->get( 'Author' ), $theme_slug );
		}

		ksort( $options );

		// Prepend an empty option.
		$options = [ null => esc_html__( 'Pick your installed theme, carefully..', 'pixelgradelt_retailer' ) ] + $options;

		return $options;
	}

	/**
	 * Check the package post for possible issues, so the user is aware of them.
	 *
	 * @param \WP_Post The current post object.
	 */
	protected function check_package_post( \WP_Post $post ) {
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

		// Display an error regarding that the package type is required.
		$package_type = wp_get_object_terms( $post->ID, $this->solution_manager::TYPE_TAXONOMY, array(
				'orderby' => 'term_id',
				'order'   => 'ASC',
		) );
		if ( is_wp_error( $package_type ) || empty( $package_type ) ) {
			$taxonomy_args = $this->solution_manager->get_solution_type_taxonomy_args();
			$this->add_user_message( 'error', sprintf(
					'<p>%s</p>',
					sprintf( esc_html__( 'You MUST choose a %s for creating a new package.', 'pixelgradelt_retailer' ), $taxonomy_args['labels']['singular_name'] )
			) );
		} else {
			$package_type = reset( $package_type );
		}

		// WordPress Core packages can only use certain source types. Display a message regarding that.
		if ( ! is_wp_error( $package_type ) && ! empty( $package_type ) && SolutionTypes::WPCORE === $package_type->slug ) {
			$package_source_type = get_post_meta( $post->ID, '_package_source_type', true );
			if ( ! in_array( $package_source_type, [ 'packagist.org', 'vcs', 'local.manual' ] ) ) {
				$this->add_user_message( 'error', sprintf(
						'<p>%s</p>',
						esc_html__( 'For WordPress-Core-type packages, you can only use choose the following source types: Packagist.org, VCS (e.g. Github repo), or manually uploaded zips.', 'pixelgradelt_retailer' )
				) );
			}
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

		$messages = apply_filters( 'pixelgradelt_retailer_editpackage_show_user_messages', $this->user_messages, $post );
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
				wp_kses_post( __( 'Please bear in mind that Publish/Update may take a while since we do some heavy lifting behind the scenes.<br>Exercise patience 🦉<br><em>On trash the stored artifacts are deleted!</em>', 'pixelgradelt_retailer' ) )
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