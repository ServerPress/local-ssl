<?php

 //stop if running in cli
if (php_sapi_name() == "cli") {
    // In cli-mode
	return;
}

//detect if we are in the ds-plugins folder
$where_am_i = __DIR__;

if ( strpos($where_am_i,"ds-plugins") === FALSE ) {
	if ( PHP_OS !== 'Darwin' ){
		// Windows
		die("<h3>This plugin needs to be installed in to Desktop Server's ds-plugins folder.</h3><br><h5>C:\\xampplite\\ds-plugins\\</h5>");
	} else {
		//Mac
		die("<h3>This plugin needs to be installed in to Desktop Server's ds-plugins folder.</h3><br><h5>\\Applications\\XAMPP\\ds-plugins\\</h5>");
	}
}


//If I'm enabled, the show is SSL Only (with caveats) 
if ( strpos(strtolower($_SERVER['SCRIPT_NAME']),"xampp") == FALSE) {
	if ( !isset( $_SERVER['HTTP_X_ORIGINAL_HOST'] ) && !isset( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
		if ( $_SERVER['SERVER_NAME'] != 'localhost' && $_SERVER['SERVER_NAME'] != '127.0.0.1' && empty($_SERVER['HTTPS'])) {
			define('FORCE_SSL', TRUE);
			define('Force_SSL_ADMIN', TRUE);
			header("Location: https://" . $_SERVER["HTTP_HOST"] . $_SERVER["REQUEST_URI"]);
			exit();
		}
	}
}
?>