<?php

//If I'm enabled, the show is SSL Only (with caveats) 
if ( strpos(strtolower($_SERVER['SCRIPT_NAME']),"xampp") == FALSE) {
	if ( !isset( $_SERVER['HTTP_X_ORIGINAL_HOST'] ) ) {
		if ( $_SERVER['SERVER_NAME'] != 'localhost' && empty($_SERVER['HTTPS'])) {
			define('FORCE_SSL', TRUE);
			define('Force_SSL_ADMIN', TRUE);
			header("Location: https://" . $_SERVER["HTTP_HOST"] . $_SERVER["REQUEST_URI"]);
			exit();
		}
	}
}
?>