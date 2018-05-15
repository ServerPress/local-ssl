<?php
/**
 * Plugin Name: Local SSL
 * Plugin URL: https://cybercove.io/
 * Description: Allow DesktopServer to use SSL
 * Version: 2.0.16
 * Author: Joshua Knapp
 * Author URI: http://joshuaknapp.me
 *
 */
 
 //2.x Branch is developed to Work with DS 3.9
 
 
 
 function ds_https_verify() {
	add_filter( 'https_ssl_verify', '__return_false' );
}

//add filter after loaded
add_action('init','ds_https_verify');


