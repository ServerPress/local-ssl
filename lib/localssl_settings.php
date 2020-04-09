<?php
class local_SSL_Custom_Settings_Page {

	public function __construct() {

		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
		add_action( 'admin_init', array( $this, 'init_settings'  ) );

	}

	public function add_admin_menu() {

		add_options_page(
			esc_html__( 'Local SSL Settings', 'text_domain' ),
			esc_html__( 'Local SSL', 'text_domain' ),
			'activate_plugins',
			'local_ssl',
			array( $this, 'localssl_page_layout' )
		);

	}

	public function init_settings() {

		register_setting(
			'settings_group',
			'localssl_settings'
		);

		add_settings_section(
			'localssl_settings_section',
			'',
			false,
			'localssl_settings'
		);

		add_settings_field(
			'localssl_https_upgrade',
			__( 'Enable Upgrade All Connections to HTTPS', 'text_domain' ),
			array( $this, 'render_localssl_https_upgrade_field' ),
			'localssl_settings',
			'localssl_settings_section'
		);

	}

	public function localssl_page_layout() {

		// Check required user capability
		if ( !current_user_can( 'activate_plugins' ) )  {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'text_domain' ) );
		}

		// Admin Page Layout
		echo '<div class="wrap">' . "\n";
		echo '	<h1>' . get_admin_page_title() . '</h1>' . "\n";
		echo '	<form action="options.php" method="post">' . "\n";

		settings_fields( 'settings_group' );
		do_settings_sections( 'localssl_settings' );
		submit_button();

		echo '	</form>' . "\n";
		echo '</div>' . "\n";

	}

	function render_localssl_https_upgrade_field() {

		// Retrieve data from the database.
		$options = get_option( 'localssl_settings' );

		// Set default value.
		$value = isset( $options['localssl_https_upgrade'] ) ? $options['localssl_https_upgrade'] : '0';

		// Field output.
		echo '<input type="checkbox" name="localssl_settings[localssl_https_upgrade]" class="localssl_https_upgrade_field" value="checked" ' . checked( $value, 'checked', false ) . '> ' . __( '', 'text_domain' );
		echo '<span class="description">' . __( 'Activating this option will force ALL instances of http:// to https://. This can cause issues with scripts loading from other locations.', 'text_domain' ) . '</span>';

	}

}

new local_SSL_Custom_Settings_Page;

?>
