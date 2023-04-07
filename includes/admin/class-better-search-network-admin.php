<?php
/**
 * Better Search Display statistics page.
 *
 * @package   Better_Search
 * @subpackage  Better_Search_Network_Admin
 */

/**** If this file is called directly, abort. ****/
if ( ! defined( 'WPINC' ) ) {
	die;
}


/**
 * Better_Search_Network_Admin class.
 *
 * Renders the network admin page.
 *
 * @since 3.2.0
 */
class Better_Search_Network_Admin {


	/**
	 * Prefix that is used for settings, filters, actions, etc.
	 *
	 * @var string Settings slug.
	 */
	protected $prefix;

	/**
	 * Name of the settings that will be saved in the database.
	 *
	 * @var string Settings slug.
	 */
	protected $settings_key;

	/**
	 * This will be used for the SubMenu URL in the settings page and to verify which variables to save.
	 *
	 * @var string Settings slug.
	 */
	protected $settings_page_url;

	/**
	 * Settings page.
	 *
	 * @var string Settings page.
	 */
	protected $settings_page;

	/**
	 * Class constructor.
	 *
	 * @param array $args Arguments array.
	 */
	public function __construct( $args = array() ) {
		$args = wp_parse_args(
			$args,
			array(
				'prefix'       => 'bsearch',
				'settings_key' => 'bsearch_network_settings',
			)
		);

		$this->settings_key      = $args['settings_key'];
		$this->settings_page_url = str_replace( '_', '-', $this->settings_key ) . '-page';

		add_action( 'network_admin_menu', array( $this, 'network_admin_menu' ) );
		add_action( 'admin_init', array( $this, 'admin_init' ) );
		add_action( 'network_admin_edit_' . $this->settings_page_url . '-update', array( $this, 'update_settings' ) );
	}

	/**
	 * Admin init function.
	 */
	public function admin_init() {
		$settings_key = $this->settings_key;

		foreach ( $this->get_registered_settings() as $section => $settings ) {

			add_settings_section(
				"{$settings_key}_{$section}", // ID used to identify this section and with which to register options.
				__return_null(), // No title, we will handle this via a separate function.
				'__return_false', // No callback function needed. We'll process this separately.
				"{$settings_key}_{$section}"  // Page on which these options will be added.
			);

			foreach ( $settings as $setting ) {

				$args = wp_parse_args(
					$setting,
					array(
						'section'          => $section,
						'id'               => null,
						'name'             => '',
						'desc'             => '',
						'type'             => null,
						'options'          => '',
						'max'              => null,
						'min'              => null,
						'step'             => null,
						'size'             => null,
						'field_class'      => '',
						'field_attributes' => '',
						'placeholder'      => '',
					)
				);

				$id       = $args['id'];
				$name     = $args['name'];
				$type     = isset( $args['type'] ) ? $args['type'] : 'text';
				$callback = method_exists( $this, "callback_{$type}" ) ? array( $this, "callback_{$type}" ) : array( $this, 'callback_missing' );

				add_settings_field(
					"{$settings_key}[{$id}]",     // ID of the settings field. We save it within the settings array.
					$name,                        // Label of the setting.
					$callback,                    // Function to handle the setting.
					"{$settings_key}_{$section}", // Page to display the setting. In our case it is the section as defined above.
					"{$settings_key}_{$section}", // Name of the section.
					$args
				);
			}
		}

		// Register the settings into the options table.
		register_setting( $settings_key, $settings_key, array( $this, 'settings_sanitize' ) );
	}

	/**
	 * Retrieve the array of plugin settings
	 *
	 * @return array Settings array
	 */
	public function get_registered_settings() {

		$settings = array(
			'general' => $this->settings_sites(),
		);
		return $settings;
	}

	/**
	 * Retrieve the array of General settings
	 *
	 * @return array General settings array
	 */
	public function settings_sites() {

		$settings = array(
			'sites' => array(
				'id'      => 'sites',
				'name'    => esc_html__( 'Enable seamless integration', 'better-search' ),
				'desc'    => esc_html__( "Complete integration with your theme. Enabling this option will ignore better-search-template.php. It will continue to display the search results sorted by relevance, although it won't display the percentage relevance.", 'better-search' ),
				'type'    => 'sites',
				'options' => true,
			),
		);
		return $settings;
	}

	/**
	 * Get field description for display.
	 *
	 * @param array $args settings Arguments array.
	 */
	public function get_field_description( $args ) {
		if ( ! empty( $args['desc'] ) ) {
			$desc = '<p class="description">' . wp_kses_post( $args['desc'] ) . '</p>';
		} else {
			$desc = '';
		}

		/**
		 * After Settings Output filter
		 *
		 * @param string $desc Description of the field.
		 * @param array Arguments array.
		 */
		$desc = apply_filters( $this->prefix . '_setting_field_description', $desc, $args );
		return $desc;
	}

	/**
	 * Get the value of a settings field.
	 *
	 * @param string $option  Settings field name.
	 * @param string $default Default text if it's not found.
	 * @return string
	 */
	public function get_option( $option, $default = '' ) {

		$options = get_option( $this->settings_key );

		if ( isset( $options[ $option ] ) ) {
			return $options[ $option ];
		}

		return $default;
	}


	/**
	 * Sites Callback. Renders a multiple checkbox list of sites that can be selected.
	 *
	 * @param array $args Array of arguments.
	 * @return void
	 */
	public function callback_sites( $args ) {
		$html = '';

		$options = $this->get_option( $args['id'], $args['options'] );

		$sites = get_sites();

		foreach ( $sites as $site ) {

			$html .= sprintf(
				'<input name="%4$s[%1$s][%2$s]" id="%4$s[%1$s][%2$s]" type="checkbox" value="%2$s" %3$s /> ',
				sanitize_key( $args['id'] ),
				esc_attr( $site->blog_id ),
				checked( true, in_array( $site->blog_id, (array) $options, true ), false ),
				$this->settings_key
			);
			$html .= sprintf(
				'<label for="%4$s[%1$s][%2$s]">%2$s (%3$s)</label> <br />',
				sanitize_key( $args['id'] ),
				$site->blogname,
				$site->siteurl,
				$this->settings_key
			);

		}

		$html .= $this->get_field_description( $args );

		echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}




	/**
	 * Network Admin menu.
	 */
	public function network_admin_menu() {
		$this->settings_page = add_menu_page(
			esc_html__( 'Better Search Network Settings', 'top-10' ),
			esc_html__( 'Better Search', 'top-10' ),
			'manage_network_options',
			$this->settings_page_url,
			array( $this, 'plugin_settings_page' ),
			'dashicons-search'
		);
		add_action( 'load-' . $this->settings_page, array( $this, 'settings_help' ) );
	}

	/**
	 * Plugin settings page
	 */
	public function plugin_settings_page() {
		?>
		<div class="wrap">
			<h1><?php echo esc_attr( get_admin_page_title() ); ?></h1>

			<div id="poststuff">
				<div id="post-body" class="metabox-holder columns-2">
					<div id="post-body-content">
						<div class="meta-box-sortables ui-sortable">
							<form action="edit.php?action=<?php echo esc_attr( $this->settings_page_url ); ?>-update" method="POST">

							<?php
							settings_fields( $this->settings_key );
							do_settings_sections( $this->settings_page_url );
							submit_button();
							?>

							</form>
						</div>
					</div>
					<div id="postbox-container-1" class="postbox-container">
						<div id="side-sortables" class="meta-box-sortables ui-sortable">
							<?php include_once 'sidebar.php'; ?>
						</div><!-- /side-sortables -->
					</div><!-- /postbox-container-1 -->
				</div><!-- /post-body -->
				<br class="clear" />
			</div><!-- /poststuff -->
		</div>
		<?php
	}

	/**
	 * Multisite options require its own update function. Here we make the actual update.
	 *
	 * @return void
	 */
	public function update_settings() {
		\check_admin_referer( $this->settings_key . '-page-options' );
		global $new_allowed_options;

		$options = $new_allowed_options[ $this->settings_key ];

		foreach ( $options as $option ) {
			if ( isset( $_POST[ $option ] ) ) {
				update_site_option( $option, $_POST[ $option ] );
			} else {
				delete_site_option( $option );
			}
		}

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'    => $this->settings_page_url,
					'updated' => 'true',
				),
				network_admin_url( 'settings.php' )
			)
		);
		exit;
	}


	/**
	 * Function to add the content of the help tab.
	 */
	public function settings_help() {
		$screen = get_current_screen();

		if ( $screen->id !== $this->settings_page . '-network' ) {
			return;
		}

		$screen->set_help_sidebar(
			/* translators: 1: Support link. */
			'<p>' . sprintf( __( 'For more information or how to get support visit the <a href="%1$s">WebberZone support site</a>.', 'better-search' ), esc_url( 'https://webberzone.com/support/' ) ) . '</p>' .
				/* translators: 1: Forum link. */
				'<p>' . sprintf( __( 'Support queries should be posted in the <a href="%1$s">WordPress.org support forums</a>.', 'better-search' ), esc_url( 'https://wordpress.org/support/plugin/better-search' ) ) . '</p>' .
				'<p>' . sprintf(
					/* translators: 1: Github Issues link, 2: Github page. */
					__( '<a href="%1$s">Post an issue</a> on <a href="%2$s">GitHub</a> (bug reports only).', 'better-search' ),
					esc_url( 'https://github.com/WebberZone/better-search/issues' ),
					esc_url( 'https://github.com/WebberZone/better-search' )
				) . '</p>'
		);

		$screen->add_help_tab(
			array(
				'id'      => 'bsearch-settings-general',
				'title'   => __( 'General', 'better-search' ),
				'content' =>
				'<p>' . __( 'This screen allows you to select which sites will be included in multisite search.', 'better-search' ) . '</p>' .
					'<p>' . __( 'Multisite search will be activated for all the sites below.', 'better-search' ) . '</p>',
			)
		);
	}
}

new Better_Search_Network_Admin();
