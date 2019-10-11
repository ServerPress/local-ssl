<?php

global $ds_runtime;

//Add Debug Logging Flag to troubleshoot issues better
global $debug_local_ssl;
global $debug_local_ssl_path;

//Set the Stage
//define OPENSSL location
if ( PHP_OS !== 'Darwin' ){
	// Windows
	//define the LOCAL_SSL_PATH
	define("LOCAL_SSL_PATH",addslashes(__DIR__ . '\\'));
	define("OPENSSL_PATH",addslashes(__DIR__ . '\win32\cygwin\bin\openssl.exe'));
	define("RETURNS","\r\n");
} else {
	//define the LOCAL_SSL_PATH
	define("LOCAL_SSL_PATH", __DIR__ . '/');
	define("OPENSSL_PATH","/usr/bin/openssl");
	define("RETURNS","\n");
}

//Prep the functions

//Parse and rewrite the httpd conf
function rewrite_vhosts() {
	global $debug_local_ssl;
	global $debug_local_ssl_path;
	//Are we on Mac or PC
	if ( PHP_OS === 'Darwin' ){
		$httpd_conf = '/Applications/XAMPP/xamppfiles/etc/extra/httpd-vhosts.conf';
		$cert_path = '/Applications/XAMPP/xamppfiles/etc/ssl.crt/';
		$key_path = '/Applications/XAMPP/xamppfiles/etc/ssl.key/';
		if ( $debug_local_ssl == TRUE ){
			error_log("We are on a Mac". PHP_EOL,3,$debug_local_ssl_path);
		}		
	}else{
		$httpd_conf = 'C:\\xampplite\\apache\conf\\extra\\httpd-vhosts.conf';
		$cert_path = 'C:\\xampplite\\apache\\conf\\ssl.crt\\';
		$key_path = 'C:\\xampplite\\apache\\conf\ssl.key\\';
		if ( $debug_local_ssl == TRUE ){
			error_log("We are on a PC". PHP_EOL,3,$debug_local_ssl_path);
		}
	}
	
	//If debug is set to try, log the paths
	if ( $debug_local_ssl == TRUE) {
		error_log($httpd_conf . PHP_EOL . $cert_path . PHP_EOL . $key_path . PHP_EOL,3,$debug_local_ssl_path);
	}
	
	//load file into memory to edit
	$httpd_conf_data = file_get_contents($httpd_conf);
	$httpd_conf_array = explode(RETURNS,$httpd_conf_data);
	$get_server_name = FALSE;
	$new_httpd_conf = '';
	foreach ($httpd_conf_array as $httpd_line) {
		if ( strpos($httpd_line,':443') != FALSE ) {
			//We found a SSL (443) entry, lets collect the Domain
			$get_server_name = TRUE;
			if ( $debug_local_ssl == TRUE) {
				error_log("An Entry for a secure domain was found, now trying to determine the domain to generate the Key and Cert files". PHP_EOL,3,$debug_local_ssl_path);
			}
		}
		if ( $get_server_name == TRUE ) {
			if ( strpos($httpd_line,'erverNam') != FALSE ) {
				$servername = explode(" ", $httpd_line);
				$domain = trim(end($servername));
				if ( $debug_local_ssl == TRUE) {
					error_log("Domain Found : " . $domain . PHP_EOL,3,$debug_local_ssl_path);
				}
				//if the cert doesn't exist, create it
				if ( !file_exists($cert_path . $domain . ".crt" )) {
					if ( $debug_local_ssl == TRUE) {
						error_log("Cert and Key were not found, generating them...". PHP_EOL,3,$debug_local_ssl_path);
					}
					create_ssl($domain, $key_path, $cert_path);
				}
			}
		}
		if ( strpos($httpd_line,'SLCertificat') !=FALSE ) {
			$httpd_line = str_replace("server",$domain,$httpd_line);
			if ( $debug_local_ssl == TRUE) {
				error_log("New http conf line to be written : " . $httpd_line . PHP_EOL,3,$debug_local_ssl_path);
			}
		}
		
		//reset for closed virtualhost
		if ( strpos($httpd_line,'/VirtualHost') != FALSE ) {
			unset($get_server_name);
			unset($servername);
			unset($domain);
		}
		
		//post data to variable
		$new_httpd_conf .= $httpd_line . RETURNS;
	}
	
	
	file_put_contents($httpd_conf,$new_httpd_conf);
	//Debug the entire httpd.conf
	if ( $debug_local_ssl == TRUE) {
		error_log("Entire New httpd.conf".PHP_EOL . "******************************" . PHP_EOL . trim($new_httpd_conf) . PHP_EOL . "******************************" . PHP_EOL,3,$debug_local_ssl_path);
	}

}

//create the root CA for Local SSL

function create_root_ca() {
	//If the RootCA doesn't Exist, Create it!
	global $debug_local_ssl;
	global $debug_local_ssl_path;
	if ( !file_exists ( LOCAL_SSL_PATH . "ServerPressCA.crt" ) ) {
		if ( $debug_local_ssl == TRUE) {
			error_log("No root certificate authority file found, attempting to create..." . PHP_EOL,3,$debug_local_ssl_path);
		}
		$return_data  = shell_exec(OPENSSL_PATH . ' genrsa -out '. LOCAL_SSL_PATH . 'ServerPressCA.key 2048 2>&1');
		if ( $debug_local_ssl == TRUE) {
			error_log("Root Certificate Authority Key Generation returned: " . $return_data . PHP_EOL,3,$debug_local_ssl_path);
		}
		$return_data = shell_exec(OPENSSL_PATH . ' req -x509 -new -nodes -key ' . LOCAL_SSL_PATH . 'ServerPressCA.key -sha256 -days 825 -out ' . LOCAL_SSL_PATH . 'ServerPressCA.crt  -subj "/C=US/ST=California/L=Los Angeles/O=ServerPress/OU=Customers/CN=Serverpress.localhost" 2>&1');	
		if ( $debug_local_ssl == TRUE) {
			error_log("Root Certificate Authority Cert Generation returned: " . $return_data . PHP_EOL,3,$debug_local_ssl_path);
		}		
		if ( PHP_OS !== 'Darwin' ){
			// Windows
			//Try to Install the Root CA
			$return_data = passthru('certutil -addstore "Root" "' . LOCAL_SSL_PATH . 'ServerPressCA.crt" 2>&1');
			if ( $debug_local_ssl == TRUE) {
				error_log("Attempt to install Root CA Returned: " . $return_data . PHP_EOL,3,$debug_local_ssl_path);
			}
		} else {
			//Mac
			//Try to install the Root CA
			$return_data = shell_exec("security add-trusted-cert -d -r trustRoot -k \"/Library/Keychains/System.keychain\" \"/Applications/XAMPP/ds-plugins/local-ssl/ServerPressCA.crt\"");
			if ( $debug_local_ssl == TRUE) {
				error_log("Attempt to install Root CA Returned: " . $return_data . PHP_EOL,3,$debug_local_ssl_path);
			}
		}
	}
	
}

function create_ssl($domain = null, $keypath, $certpath) {
	global $ds_runtime;
	global $debug_local_ssl;
	global $debug_local_ssl_path;
	if ( $domain == null ) {
		die("Domain is not set");
	};
	if ( $debug_local_ssl == TRUE) {
		error_log("Attempting to create SSL for $domain..." . PHP_EOL,3,$debug_local_ssl_path);
	}
	$siteName = $domain;
	

	if ( $siteName !== '' ) {
		//Create the tmpfile
		$ssl_template = "authorityKeyIdentifier=keyid,issuer\nbasicConstraints=CA:FALSE\nkeyUsage = digitalSignature, nonRepudiation, keyEncipherment, dataEncipherment\nsubjectAltName = @alt_names\n\n[alt_names]\nDNS.1 = *." . $siteName . "\nDNS.2 = " . $siteName;
		$ssl_temp_file = fopen(LOCAL_SSL_PATH . '_' . $siteName . "_v3.ext", "w")or die("Unable to open file: " . LOCAL_SSL_PATH . '_' . $siteName . "_v3.ext" ." !");
		fwrite($ssl_temp_file, $ssl_template);
		fclose($ssl_temp_file);
		if ( $debug_local_ssl == TRUE) {
			error_log("SSL Request Data : " . PHP_EOL . $ssl_template . PHP_EOL,3,$debug_local_ssl_path);
		}
		$SUBJECT="/C=US/ST=California/L=Los Angeles/O=DesktopServer/CN=*.$siteName";
		$NUM_OF_DAYS = 825; //2 years
		if ( $debug_local_ssl == TRUE) {
			error_log("SSL Subject and number of days data : " . PHP_EOL . $SUBJECT . PHP_EOL . $NUM_OF_DAYS . PHP_EOL,3,$debug_local_ssl_path);
		}
		if ( !file_exists($certpath . $siteName . '.crt')) {
			$return_data = shell_exec(OPENSSL_PATH . ' req -new -newkey rsa:2048 -sha256 -nodes -keyout ' . LOCAL_SSL_PATH . $siteName . '.key -subj "' . $SUBJECT . '" -out ' . LOCAL_SSL_PATH . $siteName . '.csr 2>&1');
			if ( $debug_local_ssl == TRUE) {
				error_log("Domain key generation : " . $return_data . PHP_EOL,3,$debug_local_ssl_path);
			}
			$return_data = shell_exec(OPENSSL_PATH . ' x509 -req -in ' . LOCAL_SSL_PATH  . $siteName . '.csr -CA ' . LOCAL_SSL_PATH  . 'ServerPressCA.crt -CAkey '  . LOCAL_SSL_PATH . 'ServerPressCA.key -CAcreateserial -out '. LOCAL_SSL_PATH . $siteName . '.crt -days ' . $NUM_OF_DAYS . ' -sha256 -extfile ' . LOCAL_SSL_PATH . '_' . $siteName . '_v3.ext 2>&1');
			if ( $debug_local_ssl == TRUE) {
				error_log("Domain cert generation : " . $return_data . PHP_EOL,3,$debug_local_ssl_path);
			}		
		}
		//Move the Key and Crt files
		rename(LOCAL_SSL_PATH . $siteName . '.crt',$certpath .$siteName . '.crt');
		rename(LOCAL_SSL_PATH . $siteName . '.key',$keypath .$siteName . '.key');
		//Cleanup After ourselves
		unlink(LOCAL_SSL_PATH . '_' . $siteName . "_v3.ext");
		unlink(LOCAL_SSL_PATH  . $siteName . '.csr');
	}
}

//The Setup is done, lets run.
if ( false === $ds_runtime->last_ui_event ) return;

//If we are Removing a site, we should remove the file
if ( $ds_runtime->last_ui_event->action == "site_removed" ) {
	//Are we on Mac or PC
	if ( PHP_OS === 'Darwin' ){
		$cert_path = '/Applications/XAMPP/xamppfiles/etc/ssl.crt/';
		$key_path = '/Applications/XAMPP/xamppfiles/etc/ssl.key/';
	}else{
		$cert_path = 'C:\\xampplite\\apache\\conf\\ssl.crt\\';
		$key_path = 'C:\\xampplite\\apache\\conf\ssl.key\\';
	}
	//Remove the ssl file
	unlink ($cert_path . $ds_runtime->last_ui_event->info[0] . ".crt");
	unlink ($key_path . $ds_runtime->last_ui_event->info[0] . ".key");
	return;
}

//Create SSLs for new sites
if ( 'update_server' != $ds_runtime->last_ui_event->action ) return;
$debug_local_ssl = FALSE;
//Set the Debug File for logs
if ( $debug_local_ssl == TRUE ) {
	$debug_local_ssl_path = LOCAL_SSL_PATH . "debug_localssl.log";
	error_log(PHP_EOL . "Starting Debug Run : ". date("F j, Y, g:i a") . PHP_EOL,3,$debug_local_ssl_path);
}
create_root_ca();
rewrite_vhosts();