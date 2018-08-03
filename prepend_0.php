<?php

//Set the Stage
//define the LOCAL_SSL_PATH
define( 'LOCAL_SSL_PATH', __DIR__ . DIRECTORY_SEPARATOR );
//define OPENSSL location
if ( 'Darwin' !== PHP_OS ){
	// Windows
	define( 'OPENSSL_PATH', __DIR__ . '\\win32\\cygwin\\bin\\openssl.exe' );
} else {
	// Mac
	define( 'OPENSSL_PATH', '/usr/bin/openssl' );
}


// declare the functions

/**
 * Parse and rewrite the httpd configuration file
 */
function local_ssl_rewrite_vhosts()
{
	// Are we on Mac or PC
	if ( 'Darwin' === PHP_OS ) {
		$httpd_conf = '/Applications/XAMPP/xamppfiles/etc/extra/httpd-vhosts.conf';
		$cert_path = '/Applications/XAMPP/xamppfiles/etc/ssl.crt/';
		$key_path = '/Applications/XAMPP/xamppfiles/etc/ssl.key/';
	} else {
		$httpd_conf = 'C:\\xampplite\\apache\conf\\extra\\httpd-vhosts.conf';
		$cert_path = 'C:\\xampplite\\apache\\conf\\ssl.crt\\';
		$key_path = 'C:\\xampplite\\apache\\conf\ssl.key\\';
	}
	//load file into memory to edit
	$httpd_conf_array = file( $httpd_conf );
	$get_server_name = FALSE;
	$new_httpd_conf = '';
	foreach ( $httpd_conf_array as $httpd_line ) {
		if ( FALSE !== strpos( $httpd_line, ':443' ) ) {
			$get_server_name = TRUE;
		}
		if ( $get_server_name ) {
			if ( FALSE !== stripos( $httpd_line, 'ServerNam') ) {
				$servername = explode( ' ', $httpd_line );
				$domain = trim( end ( $servername ) );
				// if the cert doesn't exist, create it
				if ( ! file_exists( $cert_path . $domain . '.crt' ) ) {
					local_ssl_create_ssl( $domain, $key_path, $cert_path );
				}
			}
		}
		if ( FALSE !== stripos( $httpd_line, 'SLCertificat' ) ) {
			$httpd_line = str_replace( 'server', $domain, $httpd_line );
		}
		
		// reset for closed virtualhost
		if ( FALSE !== stripos( $httpd_line, '/VirtualHost' ) ) {
			unset( $get_server_name );
			unset( $servername );
			unset( $domain );
		}
		
		// post data to variable
		$new_httpd_conf .= $httpd_line . PHP_EOL;
	}
	
	file_put_contents( $httpd_conf, $new_httpd_conf );
}

/**
 * Create the root CA for Local SSL
 */
function local_ssl_create_root_ca()
{
local_ssl_debug(__FUNCTION__.'()');
	// If the RootCA doesn't exist, create it
	if ( ! file_exists ( LOCAL_SSL_PATH . 'ServerPressCA.crt' ) || ! file_exists ( LOCAL_SSL_PATH . 'ServerPressCA.key' ) ) {
		$cmd = OPENSSL_PATH . ' genrsa -out "' . LOCAL_SSL_PATH . 'ServerPressCA.key" 2048 2>&1';
local_ssl_debug(__FUNCTION__.'() exec: ' . $cmd);
		$res = shell_exec( $cmd );
local_ssl_debug(__FUNCTION__.'() res: ' . $res);

		$cmd = OPENSSL_PATH . ' req -x509 -new -nodes -key ' .
			'"' . LOCAL_SSL_PATH . 'ServerPressCA.key" -sha256 -days 3650 -out ' .
			'"' . LOCAL_SSL_PATH . 'ServerPressCA.crt" ' .
			'-subj "/C=US/ST=California/L=Los Angeles/O=ServerPress/OU=Customers/CN=Serverpress.localhost" 2>&1';
local_ssl_debug(__FUNCTION__.'() exec: ' . $cmd);
		$res = shell_exec( $cmd );
local_ssl_debug(__FUNCTION__.'() res: ' . $res);

		if ( 'Darwin' !== PHP_OS ) {
			// Windows
			// Try to Install the Root CA
			// TODO: do we need to pass the results of certutil back to the browser? Maybe use exec() instead of passthru()?
			$cmd = 'certutil -addstore "Root" "' . LOCAL_SSL_PATH . 'ServerPressCA.crt" 2>&1';
local_ssl_debug(__FUNCTION__.'() exec: ' . $cmd);
			$res = shell_exec( $cmd );
local_ssl_debug(__FUNCTION__.'() res: ' . $res);
			//die();
		} else {
			// Mac
			// Try to install the Root CA
			shell_exec( 'osascript ' . LOCAL_SSL_PATH . 'mac_root_ca_install.scpt' );
		}
	} else {
local_ssl_debug(__FUNCTION__.'() root CA exists');
	}
}

/**
 * Create the SSL certificate
 * @param string $site_name The site name / domain name
 * @param string $keypath Path the the key store
 * @param string $certpath Path to the certificate
 */
function local_ssl_create_ssl( $site_name, $keypath, $certpath )
{
	if ( NULL === $site_name ) {
		die( 'Domain is not set' );
	}

	if ( '' !== $site_name ) {
		//Create the tmpfile
		$ssl_template = "authorityKeyIdentifier=keyid,issuer
basicConstraints=CA:FALSE
keyUsage = digitalSignature, nonRepudiation, keyEncipherment, dataEncipherment
subjectAltName = @alt_names

[alt_names]
DNS.1 = *.{$site_name}
DNS.2 = {$site_name}";
		if (FALSE === ($ssl_temp_file = fopen( LOCAL_SSL_PATH . '_' . $site_name . '_v3.ext', 'w' )))
			die( 'Unable to open file: ' . LOCAL_SSL_PATH . '_' . $site_name . '_v3.ext!' );

		fwrite( $ssl_temp_file, $ssl_template );
		fclose( $ssl_temp_file );

		$subject = '/C=US/ST=California/L=Los Angeles/O=DesktopServer/CN=*.' . $site_name;
		$num_of_daya = 3650; //10 years
		if ( ! file_exists( $certpath . $site_name . '.crt' ) ) {
			$cmd = OPENSSL_PATH . ' req -new -newkey rsa:2048 -sha256 -nodes -keyout ' .
				'"' . LOCAL_SSL_PATH . $site_name . '.key" -subj "' . $subject . '" -out ' .
				'"' . LOCAL_SSL_PATH . $site_name . '.csr" 2>&1';
local_ssl_debug(__FUNCTION__.'() exec: ' . $cmd);
			$res = shell_exec( $cmd );
local_ssl_debug(__FUNCTION__.'() res: ' . $res);

			$cmd = OPENSSL_PATH . ' x509 -req -in ' .
				'"' . LOCAL_SSL_PATH . $site_name . '.csr" -CA ' .
				'"' . LOCAL_SSL_PATH  . 'ServerPressCA.crt" -CAkey ' .
				'"' . LOCAL_SSL_PATH . 'ServerPressCA.key" -CAcreateserial -out '.
				'"' . LOCAL_SSL_PATH . $site_name . '.crt" -days ' . $num_of_daya . ' -sha256 -extfile ' .
				'"' . LOCAL_SSL_PATH . '_' . $site_name . '_v3.ext" 2>&1';
local_ssl_debug(__FUNCTION__.'() exec: ' . $cmd);
			$res = shell_exec( $cmd );
local_ssl_debug(__FUNCTION__.'() res: ' . $res);
		}

		// Move the Key and Crt files
		rename( LOCAL_SSL_PATH . $site_name . '.crt', $certpath . $site_name . '.crt' );
		rename( LOCAL_SSL_PATH . $site_name . '.key', $keypath . $site_name . '.key' );

		// Cleanup After ourselves
		unlink( LOCAL_SSL_PATH . '_' . $site_name . '_v3.ext' );
		unlink( LOCAL_SSL_PATH  . $site_name . '.csr' );
	}
}

/**
 * Function used for debugging
 * @param string $msg Message to output to trace window and log file
 */
function local_ssl_debug( $msg )
{
	if (function_exists('trace'))
		trace('localssl: ' . $msg);

	$file = __DIR__ . '/~log.txt';
	$fh = @fopen($file, 'a+');
	if (FALSE !== $fh) {
		if (NULL === $msg)
			fwrite($fh, date('Y-m-d H:i:s'));
		else
			fwrite($fh, date('Y-m-d H:i:s - ') . $msg . "\r\n");
		fclose($fh);
	}
}

// The Setup is done, lets run.
global $ds_runtime;
if ( FALSE === $ds_runtime->last_ui_event )
	return;

// If we are Removing a site, we should remove the file
if ( 'site_removed' === $ds_runtime->last_ui_event->action ) {
	// Are we on Mac or PC
	if ( 'Darwin' === PHP_OS ) {
		$cert_path = '/Applications/XAMPP/xamppfiles/etc/ssl.crt/';
		$key_path = '/Applications/XAMPP/xamppfiles/etc/ssl.key/';
	} else {
		$cert_path = 'C:\\xampplite\\apache\\conf\\ssl.crt\\';
		$key_path = 'C:\\xampplite\\apache\\conf\ssl.key\\';
	}
	// Remove the ssl file
	unlink( $cert_path . $ds_runtime->last_ui_event->info[0] . '.crt' );
	unlink( $key_path . $ds_runtime->last_ui_event->info[0] . '.key' );
	return;
}

// Create SSLs for new sites
if ( 'update_server' === $ds_runtime->last_ui_event->action ) {
	local_ssl_create_root_ca();
	local_ssl_rewrite_vhosts();
}
