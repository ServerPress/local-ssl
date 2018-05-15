<?php
global $ds_runtime;

//Set the Stage
//define OPENSSL location
if ( PHP_OS !== 'Darwin' ){
	// Windows
	//define the local_ssl_path
	define("local_ssl_path",addslashes(__DIR__ . '\\'));
	define("openssl_path",addslashes(__DIR__ . '\openssl.exe'));
	define("returns","\r\n");
} else {
	//define the local_ssl_path
	define("local_ssl_path", __DIR__ . '/');
	define("openssl_path","/usr/bin/openssl");
	define("returns","\n");
}


//Prep the functions

//Parse and rewrite the httpd conf
function rewrite_vhosts() {
	//Are we on Mac or PC
	if ( PHP_OS === 'Darwin' ){
		$httpd_conf = '/Applications/XAMPP/xamppfiles/etc/extra/httpd-vhosts.conf';
		$cert_path = '/Applications/XAMPP/xamppfiles/etc/ssl.crt/';
		$key_path = '/Applications/XAMPP/xamppfiles/etc/ssl.key/';
	}else{
		$httpd_conf = 'C:\\xampplite\\apache\conf\\extra\\httpd-vhosts.conf';
		$cert_path = 'C:\\xampplite\\apache\\conf\\ssl.crt\\';
		$key_path = 'C:\\xampplite\\apache\\conf\ssl.key\\';
	}
	//load file into memory to edit
	$httpd_conf_data = file_get_contents($httpd_conf);
	$httpd_conf_array = explode(returns,$httpd_conf_data);
	
	foreach ($httpd_conf_array as $httpd_line) {
		if ( strpos($httpd_line,':443') != FALSE ) {
			
			$get_server_name = TRUE;
		}
		if ( $get_server_name == TRUE ) {
			if ( strpos($httpd_line,'erverNam') != FALSE ) {
				$servername = explode(" ", $httpd_line);
				$domain = trim(end($servername));
				//if the cert doesn't exist, create it
				if ( !file_exists($cert_path . $domain . ".crt" )) {
					create_ssl($domain, $key_path, $cert_path);
				}
			}
		}
		if ( strpos($httpd_line,'SLCertificat') !=FALSE ) {
			$httpd_line = str_replace("server",$domain,$httpd_line);
		}
		
		//reset for closed virtualhost
		if ( strpos($httpd_line,'/VirtualHost') != FALSE ) {
			unset($get_server_name);
			unset($servername);
			unset($domain);
		}
		
		//post data to variable
		$new_httpd_conf .= $httpd_line . returns;
	}
	
	
	file_put_contents($httpd_conf,$new_httpd_conf);

}

//create the root CA for Local SSL

function create_root_ca() {
	//If the RootCA doesn't Exist, Create it!
	if ( !file_exists ( local_ssl_path . "ServerPressCA.crt" ) ) {
		//Create the Root CA
		shell_exec(openssl_path . ' genrsa -out '. local_ssl_path . 'ServerPressCA.key 2048 2>&1');
		shell_exec(openssl_path . ' req -x509 -new -nodes -key ' . local_ssl_path . 'ServerPressCA.key -sha256 -days 3650 -out ' . local_ssl_path . 'ServerPressCA.crt  -subj "/C=US/ST=California/L=Los Angeles/O=ServerPress/OU=Customers/CN=Serverpress.localhost" 2>&1');	
if ( PHP_OS !== 'Darwin' ){
			// Windows
			//Try to Install the Root CA
			passthru('certutil -addstore "Root" "' . local_ssl_path . 'ServerPressCA.crt" 2>&1');
			//die();
		} else {
			//Mac
			//Try to install the Root CA
			shell_exec("osascript " . local_ssl_path . "mac_root_ca_install.scpt");
		}
	}
	
}

function create_ssl($domain = null, $keypath, $certpath) {
	global $ds_runtime;
	if ( $domain == null ) {
		die("Domain is not set");
	};
	$siteName = $domain;
	

	if ( $siteName !== '' ) {
		//Create the tmpfile
		$ssl_template = "authorityKeyIdentifier=keyid,issuer\nbasicConstraints=CA:FALSE\nkeyUsage = digitalSignature, nonRepudiation, keyEncipherment, dataEncipherment\nsubjectAltName = @alt_names\n\n[alt_names]\nDNS.1 = " . $siteName;
		$ssl_temp_file = fopen(local_ssl_path . '_' . $siteName . "_v3.ext", "w")or die("Unable to open file: " . local_ssl_path . '_' . $siteName . "_v3.ext" ." !");
		fwrite($ssl_temp_file, $ssl_template);
		fclose($ssl_temp_file);
		$SUBJECT="/C=US/ST=California/L=Los Angeles/O=DesktopServer/CN=$siteName";
		$NUM_OF_DAYS = 3650; //10 years
		if ( !file_exists($certpath . $siteName . '.crt')) {
			shell_exec(openssl_path . ' req -new -newkey rsa:2048 -sha256 -nodes -keyout ' . local_ssl_path . $siteName . '.key -subj "' . $SUBJECT . '" -out ' . local_ssl_path . $siteName . '.csr 2>&1');
			shell_exec(openssl_path . ' x509 -req -in ' . local_ssl_path  . $siteName . '.csr -CA ' . local_ssl_path  . 'ServerPressCA.crt -CAkey '  . local_ssl_path . 'ServerPressCA.key -CAcreateserial -out '. local_ssl_path . $siteName . '.crt -days ' . $NUM_OF_DAYS . ' -sha256 -extfile ' . local_ssl_path . '_' . $siteName . '_v3.ext 2>&1');
		}
		//Move the Key and Crt files
		rename(local_ssl_path . $siteName . '.crt',$certpath .$siteName . '.crt');
		rename(local_ssl_path . $siteName . '.key',$keypath .$siteName . '.key');
		//Cleanup After ourselves
		unlink(local_ssl_path . '_' . $siteName . "_v3.ext");
		unlink(local_ssl_path  . $siteName . '.csr');
	}
}

//The Setup is done, lets run.
if ( false === $ds_runtime->last_ui_event ) return;
if ( 'update_server' != $ds_runtime->last_ui_event->action ) return;
create_root_ca();
rewrite_vhosts();