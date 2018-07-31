<?php

global $ds_runtime;

//Set the Stage
//define OPENSSL location
if ( 'Darwin' !== PHP_OS ){
	// Windows
	//define the LOCAL_SSL_PATH
	define( 'LOCAL_SSL_PATH', addslashes( __DIR__ . '\\' ) );
	define( 'OPENSSL_PATH', addslashes( __DIR__ . '\\win32\\cygwin\\bin\\openssl.exe' ) );
} else {
	// define the LOCAL_SSL_PATH
	define( 'LOCAL_SSL_PATH', __DIR__ . '/' );
	define( 'OPENSSL_PATH', '/usr/bin/openssl' );
}


// declare the functions

/**
 * Parse and rewrite the httpd configuration file
 */
function rewrite_vhosts()
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
					create_ssl( $domain, $key_path, $cert_path );
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
function create_root_ca()
{
	// If the RootCA doesn't exist, create it
	if ( ! file_exists ( LOCAL_SSL_PATH . 'ServerPressCA.crt' ) ) {
		shell_exec( OPENSSL_PATH . ' genrsa -out "' . LOCAL_SSL_PATH . 'ServerPressCA.key" 2048 2>&1' );
$cmd = OPENSSL_PATH . ' genrsa -out "' . LOCAL_SSL_PATH . 'ServerPressCA.key" 2048 2>&1';
error_log('OpenSSL exec: ' . $cmd);
		shell_exec( OPENSSL_PATH . ' req -x509 -new -nodes -key ' .
			'"' . LOCAL_SSL_PATH . 'ServerPressCA.key" -sha256 -days 3650 -out ' .
			'"' . LOCAL_SSL_PATH . 'ServerPressCA.crt" ' .
			'-subj "/C=US/ST=California/L=Los Angeles/O=ServerPress/OU=Customers/CN=Serverpress.localhost" 2>&1' );
$cmd = OPENSSL_PATH . ' req -x509 -new -nodes -key ' .
			'"' . LOCAL_SSL_PATH . 'ServerPressCA.key" -sha256 -days 3650 -out ' .
			'"' . LOCAL_SSL_PATH . 'ServerPressCA.crt" ' .
			'-subj "/C=US/ST=California/L=Los Angeles/O=ServerPress/OU=Customers/CN=Serverpress.localhost" 2>&1';
error_log('OpenSSL exec: ' . $cmd);
		if ( 'Darwin' !== PHP_OS ) {
			// Windows
			// Try to Install the Root CA
			passthru( 'certutil -addstore "Root" "' . LOCAL_SSL_PATH . 'ServerPressCA.crt" 2>&1' );
			//die();
		} else {
			//Mac
			//Try to install the Root CA
			shell_exec( 'osascript ' . LOCAL_SSL_PATH . 'mac_root_ca_install.scpt' );
		}
	}
	
}

/**
 * Create the SSL certificate
 * @param string $domain The domain name
 * @param string $keypath Path the the key store
 * @param string $certpath Path to the certificate
 */
function create_ssl( $domain, $keypath, $certpath )
{
	if ( NULL == $domain ) {
		die( 'Domain is not set' );
	}
	$siteName = $domain;

	if ( '' !== $siteName ) {
		//Create the tmpfile
		$ssl_template = 'authorityKeyIdentifier=keyid,issuer\nbasicConstraints=CA:FALSE\nkeyUsage = digitalSignature, nonRepudiation, keyEncipherment, dataEncipherment\nsubjectAltName = @alt_names\n\n[alt_names]\nDNS.1 = *.' . $siteName . "\nDNS.2 = " . $siteName;
		if (FALSE === ($ssl_temp_file = fopen( LOCAL_SSL_PATH . '_' . $siteName . '_v3.ext', 'w' )))
			die( 'Unable to open file: ' . LOCAL_SSL_PATH . '_' . $siteName . '_v3.ext!' );

		fwrite( $ssl_temp_file, $ssl_template );
		fclose( $ssl_temp_file );

		$SUBJECT = '/C=US/ST=California/L=Los Angeles/O=DesktopServer/CN=*.' . $siteName;
		$NUM_OF_DAYS = 3650; //10 years
		if ( ! file_exists( $certpath . $siteName . '.crt' ) ) {
			shell_exec( OPENSSL_PATH . ' req -new -newkey rsa:2048 -sha256 -nodes -keyout ' .
				LOCAL_SSL_PATH . $siteName . '.key -subj "' . $SUBJECT . '" -out ' .
				LOCAL_SSL_PATH . $siteName . '.csr 2>&1');
			shell_exec( OPENSSL_PATH . ' x509 -req -in ' . LOCAL_SSL_PATH  .
				$siteName . '.csr -CA ' . LOCAL_SSL_PATH  . 'ServerPressCA.crt -CAkey ' .
				LOCAL_SSL_PATH . 'ServerPressCA.key -CAcreateserial -out '. LOCAL_SSL_PATH .
				$siteName . '.crt -days ' . $NUM_OF_DAYS . ' -sha256 -extfile ' . LOCAL_SSL_PATH .
				'_' . $siteName . '_v3.ext 2>&1');
		}

		// Move the Key and Crt files
		rename( LOCAL_SSL_PATH . $siteName . '.crt', $certpath .$siteName . '.crt' );
		rename( LOCAL_SSL_PATH . $siteName . '.key', $keypath .$siteName . '.key' );

		// Cleanup After ourselves
		unlink( LOCAL_SSL_PATH . '_' . $siteName . '_v3.ext' );
		unlink( LOCAL_SSL_PATH  . $siteName . '.csr' );
	}
}

// The Setup is done, lets run.
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
if ( 'update_server' !== $ds_runtime->last_ui_event->action )
	return;

create_root_ca();
rewrite_vhosts();