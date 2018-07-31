<?php
class Custom_Settings_Page
{
	public function __construct()
	{
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
		add_action( 'admin_init', array( $this, 'init_settings'  ) );
	}

	public function add_admin_menu()
	{
		add_options_page(
			esc_html__( 'Local SSL Settings', 'local-ssl' ),
			esc_html__( 'Local SSL', 'local-ssl' ),
			'activate_plugins',
			'local_ssl',
			array( $this, 'localssl_page_layout' )
		);
	}

	public function init_settings()
	{
		register_setting(
			'settings_group',
			'localssl_settings'
		);

		add_settings_section(
			'localssl_settings_section',
			'',
			FALSE,
			'localssl_settings'
		);

		add_settings_field(
			'localssl_https_upgrade',
			__( 'Enable Upgrade All Connections to HTTPS', 'local-ssl' ),
			array( $this, 'render_localssl_https_upgrade_field' ),
			'localssl_settings',
			'localssl_settings_section'
		);
	}

	public function localssl_page_layout()
	{
		// Check required user capability
		if ( ! current_user_can( 'activate_plugins' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'local-ssl' ) );
		}

		// Admin Page Layout
		echo '<div class="wrap">' . "\n";
		echo '	<h1>', get_admin_page_title(), '</h1>', PHP_EOL;
		echo '	<form action="options.php" method="post">', PHP_EOL;

		settings_fields( 'settings_group' );
		do_settings_sections( 'localssl_settings' );
		submit_button();

		echo '	</form>', PHP_EOL;
		echo '</div>', PHP_EOL;
	}

	function render_localssl_https_upgrade_field()
	{
		// Retrieve data from the database.
		$options = get_option( 'localssl_settings' );

		// Set default value.
		$value = isset( $options['localssl_https_upgrade'] ) ? $options['localssl_https_upgrade'] : '0';

		// Field output.
		echo '<input type="checkbox" name="localssl_settings[localssl_https_upgrade]" class="localssl_https_upgrade_field" value="checked" ', checked( $value, 'checked', FALSE ), '> ';
		echo '<span class="description">', __( 'Activating this option will forces ALL instances of http:// to https://. This can cause issues with scripts loading from other locations.', 'local-ssl' ) . '</span>';
	}
}

new Custom_Settings_Page();
