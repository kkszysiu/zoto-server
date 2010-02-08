<?php
	function urlpost($host, $port, $location, &$data) {
		/*
		 * This method sends a very basic HTTP POST to a host, port, and location,
		 * sending it the $data as a string. It's very basic. Returns a string of
		 * the resulting data.
		 *
		 * WARNING: Currently, this function dies upon failure to connect.
		 */
		$content_length = strlen($data);
		$headers = "POST $location HTTP/1.0\r\n" .
		           "Host: $host\r\n" .
		           "Connection: close\r\n" .
		           "User-Agent: Zoto PHP XMLRPC Client\r\n" .
		           "Content-Type: text/xml\r\n" .
		           "Content-Length: $content_length" . 
		           "\r\n\r\n";

		$c = fsockopen($host, $port);
		if (!$c) {
		    trigger_error("Unable to connect to AZTK Server: $host:$port");
		} else {
		    fputs($c, $headers);
		    fputs($c, $data);
		    
		    $returndata = "";
		    while(!feof($c)){
		        $returndata .= fgets($c, 1024);
		    }
		    fclose($c);
		    return $returndata;
		}
	}

	function xmlrpc_request($host, $port, $location, $function, &$request_data) {
		/*
		 * This method sends a very basic XML-RPC request. $host, $port, $location,
		 * and $function are pretty simple. $request_data can be any PHP type,
		 * and this function returns the PHP type of whatever the xmlrpc function
		 * returns.
		 *
		 * WARNING: This function dies upon failure.
		 */
		// Send request
		$request_xml = xmlrpc_encode_request($function, $request_data);

		// Split out response into headers, and xml
		$response = urlpost($host, $port, $location, $request_xml);
		$response_array = split("\r\n\r\n", $response, 2);
		$response_headers = split("\r\n", $response_array[0]);
		$response_xml = $response_array[1];

		$http_array = split(" ", $response_headers[0], 3);
		if ($http_array[1] != "200") {
			trigger_error("xmlrpc request failed: ({$http_array[1]}, {$http_array[2]}) at $host:$port using $location");
		} else {
			// Get native PHP types and return them for data.
			$response_data = xmlrpc_decode_request($response_xml, $function);
			return $response_data;
		}
	}

	function zapi_call($key, $auth, $function, $request_data) {
		global $zapi_hostname;
		global $zapi_port;
		global $zapi_location;

		$signature = array($key, $auth);
		foreach($request_data as $foo) {
			array_push($signature, $foo);
		}

		$return_data = xmlrpc_request($zapi_hostname, $zapi_port, $zapi_location, $function, $signature);

		if (is_array($return_data)) {
			if ($return_data['faultString']) {
				header("HTTP/1.0 500 Internal Server Error");
				print $return_data['faultString'];
				die();
			}
		}
		return $return_data;
	}

	/* get our username and token from the cookie */
	$auth = null;
	if(array_key_exists('auth_hash', $_COOKIE)){
		$auth = split("[:]", $_COOKIE['auth_hash']);
	} else if(array_key_exists('auth', $_GET)){
		$auth = split("[:]", $_GET['auth']);
	} else {
		die("Unable to auth user.");
	}
	$auth_username = $auth[0];
	$auth_token = $auth[2];

	/* set up our ZAPI call parameters */
	//$zapi_hostname = $_SERVER['SERVER_ADDR'];
	$zapi_hostname = "zoto.pl";
	//$zapi_hostname = "www.".$domain;
	$zapi_port = 80;
	$zapi_location = "/RPC2";
	$zapi_key = "5d4a65c46a072a4542a816f2f28bd01a";
	$zapi_auth = array("username"=>$auth_username, "token"=>$auth_token);
	$zapi_function = "images.add";

	$uploaddir = '/zoto/www/uploads/';

	/* here's where we actually do the work */
	if($auth[0] != null){
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" dir="ltr">
<head>
<meta http-equiv="content-type" content="text/html; charset=UTF-8"/>
<title>Summary</title>
</head>
<body>

<h1>Summary</h1>

<p>You've uploaded <?=$_POST['uploader_count']?> photos successfully.</p>

	<?php
	$i = 0;
	while(isset($_POST['uploader_'.$i.'_name'])) {
		@set_time_limit(5 * 60);
		
		if($_POST['uploader_'.$i.'_name'] and $_POST['uploader_'.$i.'_status'] == 'done') {
			$image_filename = $_POST['uploader_'.$i.'_name'];
			$uploadfile = $uploaddir.$_POST['uploader_'.$i.'_tmpname'];

			/* open and read in the image file data */
			$handle = fopen($uploadfile, "r");
			$media_binary = fread($handle, filesize($uploadfile));
			xmlrpc_set_type(&$media_binary, "base64");
			fclose($handle);
			unlink($uploadfile);

			/* build the args for the XML-RPC query */
			$zapi_query = array($image_filename, $image_filename, "", $media_binary);

			/* send it to the zoto server */
			$result = zapi_call($zapi_key, $zapi_auth, $zapi_function, $zapi_query);
			if (is_array($result)) {
				//var_dump($result);
				if($result[0] == '0'){
					print "<div>File '$image_filename' uploaded successfully.</div>";
				} else if($result[0] == '-1'){
					print "<div style='color: #FF3A3A;'>File '$image_filename' upload returned error: ".$result[1]."</div>";
				}
			}
		} // $_POST['uploader_'.$i.'_status'] == 'done'
		$i++;
	}
	?>

</body>
</html>
<?php
	}
?>
