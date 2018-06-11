<?php

// if running in cli mode, exit now and avoid doing anything
if ( 'cli' === PHP_SAPI )
	return;

// If I'm enabled, the show is SSL Only (with caveats)
if ( FALSE === stripos( $_SERVER['SCRIPT_NAME'], 'xampp' ) ) {
	if ( !isset( $_SERVER['HTTP_X_ORIGINAL_HOST'] ) && !isset( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
		if ( 'localhost' !== $_SERVER['SERVER_NAME'] && '127.0.0.1' !== $_SERVER['SERVER_NAME'] && empty( $_SERVER['HTTPS'] ) ) {
			define('FORCE_SSL', TRUE);
			define('FORCE_SSL_ADMIN', TRUE);
			header('Location: https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'] );
			exit();
		}
	}
}
