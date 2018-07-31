<?php
/**
 * Plugin Name: Local SSL
 * Plugin URL: https://cybercove.io/
 * Description: Allow DesktopServer to use SSL
 * Version: 2.0.21
 * Author: Joshua Knapp
 * Text Domain: local-ssl
 * Author URI: http://joshuaknapp.me
 */

//2.x Branch is developed to Work with DS 3.9

//stop if running in cli
if ( 'cli' === PHP_SAPI ) {
    // In cli-mode
	return;
}

// detect if we are in the ds-plugins folder
if ( FALSE === strpos( __DIR__, 'ds-plugins' ) ) {
	// detect if not in the ds-plugins folder
	if ( is_admin() ) {
		add_action( 'admin_notices', 'local_ssl_install_message' );
		return;		// do not initialize the rest of the plugin
	}
}

/**
 * Display Error Message for WordPress if LocalSSL is not where it should be
 */
function local_ssl_install_message()
{
	if ( 'Darwin' === PHP_OS )
		$correct_dir = '/Applications/XAMPP/ds-plugins/';		// mac directory
	else
		$correct_dir = 'C:\\xampplite\\ds-plugins\\';			// Windows directory

	echo '<div class="notice notice-error">',
		'<p>',
		sprintf( __('The Local SSL plugin needs to be installed in Desktop Server\'s ds-plugins directory.<br/>Please install in %1$slocal-ssl', 'local-ssl' ),
			$correct_dir),
		'</p>',
		'</div>';
}


/**
 * Prevent WordPress from trying to validate the SSL
 */
function local_ssl_https_verify()
{
	add_filter( 'https_ssl_verify', '__return_false' );
}

//add filter after loaded
add_action( 'init', 'local_ssl_https_verify' );

require(__DIR__ . '/lib/localssl_settings.php' );


/**
 * ReWrite http to https
 * @param string $buffer Content to perform rewrites on
 * @return string Content with http:// references changed to https://
 */
function local_ssl_callback_ssl_url( $buffer )
{
	return str_ireplace( array( 'http://', 'https:\\/\\/' ), 'https://', $buffer );
}

/**
 * Callback to kick off the output buffering
 */
function local_ssl_buffer_start_ssl_url()
{
	ob_start( 'local_ssl_callback_ssl_url' );
}

/**
 * Callback for 'shutdown' action. Used to close any active Output Buffers.
 */
function local_ssl_buffer_end_ssl_url()
{
	if ( ob_get_length() )
		ob_end_clean();
}

$options = get_option( 'localssl_settings' );
if ( isset( $options['localssl_https_upgrade'] ) && $options['localssl_https_upgrade'] ) {
	add_action( 'registered_taxonomy', 'local_ssl_buffer_start_ssl_url' );
	add_action( 'shutdown', 'local_ssl_buffer_end_ssl_url' );
}
