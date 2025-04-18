<?php

$rpc_internal_timeout = 10;
$rpc_external_timeout = 4;

function rpc_start_request_with_options($url, $options)
{
	global $rpc_internal_timeout, $rpc_external_timeout;
  
	// FIXME: $internal was undefined
	$internal = false;
	
	$curlopts = array(
		CURLOPT_FAILONERROR    => true, //...?
		CURLOPT_FOLLOWLOCATION => true,
		CURLOPT_RETURNTRANSFER => true,
		
		CURLOPT_CONNECTTIMEOUT => 2,
		CURLOPT_TIMEOUT    => $internal ? $rpc_internal_timeout : $rpc_external_timeout,
		CURLOPT_USERAGENT  => "4chan.org",
	);
	
	$curlopts = $options + $curlopts;
	
	global $rpc_mh;
	global $rpc_chs;
	
	$ch = curl_init($url);
	if (!$ch || !is_resource($ch)) {
		internal_error_log("rpc", "couldn't curl_init '$ch' '$url': '".curl_error($ch)."'");
		curl_close($ch);
		return null;
	}
	
	$optstr = print_r($curlopts, TRUE);
	// quick_log_to( "/www/perhost/curls.log", "curl: '$ch' URL: $url\n$optstr\n");
	
	foreach ($curlopts as $opt=>$value) {
		if (curl_setopt($ch, $opt, $value) === false) {
			internal_error_log("rpc", "couldn't curl_setopt '$ch' '$opt' '$value': '".curl_error($ch)."'");
			curl_close($ch);
			return null;
		}
	}
	
	if (!isset($rpc_mh)) {
		rpc_multi_init();
	}
	
	$ret = curl_multi_add_handle($rpc_mh, $ch);
	
	if ($ret != 0) {
		internal_error_log("rpc", "couldn't add curl handle $ch $ret: ".curl_error($ch));
		return null;
	}
	
	$rpc_chs[] = $ch;
		
	return $ch;
}

// returns a request ID, or null if it failed
function rpc_start_request($url, $post, $cookies, $internal)
{
  	global $rpc_internal_timeout, $rpc_external_timeout;
  
	$cookiestr = '';
	if ($cookies) {
		$carray = array();
		foreach($cookies as $name=>$value) {
			$name  = urlencode($name);
			$value = urlencode($value);
			$carray[] = "$name=$value";
		}
		$cookiestr = implode("; ", $carray).";";
	}
	
	if ($internal) {
		$curlopts[CURLOPT_SSL_VERIFYHOST] = false;
		$curlopts[CURLOPT_SSL_VERIFYPEER] = false;
	}
	else {
		$curlopts[CURLINFO_HEADER_OUT] = true;
		// $curlopts[CURLOPT_VERBOSE] = true;
	}
	
	if ($post) {
		$curlopts[CURLOPT_POSTFIELDS] = $post;
	}
	
	if ($cookiestr) {
		$curlopts[CURLOPT_COOKIE] = $cookiestr;
	}
	
	return rpc_start_request_with_options($url, $curlopts);
}

function rpc_start_captcha_request($url, $post, $cookies, $internal) {
  	global $rpc_internal_timeout, $rpc_external_timeout;
  
	$cookiestr = '';
	if ($cookies) {
		$carray = array();
		foreach($cookies as $name=>$value) {
			$name  = urlencode($name);
			$value = urlencode($value);
			$carray[] = "$name=$value";
		}
		$cookiestr = implode("; ", $carray).";";
	}
	
	if ($internal) {
		$curlopts[CURLOPT_SSL_VERIFYHOST] = false;
		$curlopts[CURLOPT_SSL_VERIFYPEER] = false;
	}
	else {
		$curlopts[CURLINFO_HEADER_OUT] = true;
		//$curlopts[CURLOPT_RESOLVE] = array('www.google.com:443:172.217.4.132');
		// $curlopts[CURLOPT_VERBOSE] = true;
	}
	
	if ($post) {
		$curlopts[CURLOPT_POSTFIELDS] = $post;
	}
	
	if ($cookiestr) {
		$curlopts[CURLOPT_COOKIE] = $cookiestr;
	}
	
	return rpc_start_request_with_options($url, $curlopts);
}

function rpc_new_multi_handle()
{
	$mh = curl_multi_init();
	
	curl_multi_setopt($mh, CURLMOPT_PIPELINING, 1);
	curl_multi_setopt($mh, CURLMOPT_MAXCONNECTS, 16);
	
	return $mh;
}

function rpc_multi_init()
{
	global $rpc_mh;
	global $rpc_chs;
	
	$rpc_mh  = rpc_new_multi_handle();
	$rpc_chs = array();

	register_shutdown_function('rpc_finish_all');
}

// call at idle points, calls curl's task until it stops having immediate work
function rpc_task()
{
	global $rpc_mh;
	
	if (!is_resource($rpc_mh)) return false;
	
	$still_running = false;
	
	do {
	    $ret = curl_multi_exec($rpc_mh, $still_running);
	} while ($ret == CURLM_CALL_MULTI_PERFORM);
	
	return $still_running;
}

// blocks till all requests are no longer 'running' and clears rpc_mh
// this can block for a few seconds, watch out!
function rpc_finish_all()
{
	global $rpc_mh;
	global $rpc_chs;
	
	if (!is_resource($rpc_mh) || !count($rpc_chs)) return;
	
	flush_output_buffers();
	
	do {
		if (rpc_task() == false) break;
		curl_multi_select($rpc_mh);
	} while (true);
	
	// clear out the curl_multi handle
	foreach ($rpc_chs as $ch) {
		curl_multi_remove_handle($rpc_mh, $ch);
	}
	
	//quick_log_to("/www/perhost/rpc.log", getmypid()." $n rpcs finished in $rpc_mh\n");
	
	//deallocate all curl handles
	$rpc_chs = array();
	
	//hopefully rpc_mh is empty now.
	//we don't want to close it because curl uses the state for http pipelining
}

function rpc_close_multi()
{
	global $rpc_mh;
	global $rpc_chs;
	
	// explicitly close these objects since curl debug seems to not print otherwise?
	// don't wait for them to finish. this can be used to prevent double-submits. maybe...
	unset($rpc_chs);
	unset($rpc_mh);
}

function rpc_debug_fd()
{
	static $fd = -1;
	
	if ($fd == -1) {
		$fd = fopen( "/www/perhost/curl-debug.log", "a" );
		fwrite($fd, "--------------\n");
	}
	 
	return $fd;
}

function rpc_debug_request($ch)
{
	$errno	 = curl_errno($ch);
	$error 	 = curl_error($ch);
	$sent 	 = curl_getinfo($ch, CURLINFO_HEADER_OUT);
	$bsent   = curl_getinfo($ch, CURLINFO_SIZE_UPLOAD);
	$brec    = curl_getinfo($ch, CURLINFO_SIZE_DOWNLOAD);
	
	quick_log_to("/www/perhost/rpc-failures.log", getmypid()." curl error $errno '$error'\nbytes up $bsent down $brec\ndata: $sent");
}

// returns the response, or sets $error if null
// DANGER: if you call this on a curl after rpc_finish_all() it seems to send the POST over again
function rpc_finish_request($ch, &$error, &$httperror = null)
{
	rpc_task();
	
	// Move the request into the foreground and block (hopefully not actually blocking)
	global $rpc_mh;
	global $rpc_chs;
	
	if ($rpc_mh===null || !is_resource($ch)) {
		$error = "Connections not started ($rpc_mh $ch)";
		return null;
	}
	
	curl_multi_remove_handle($rpc_mh, $ch);
	$ret = curl_exec($ch);
		
	// Get contents
	if ($ret === false) {
		$errstr = curl_error($ch);
		$errno  = curl_errno($ch);
		$error = "Curl error: $errstr ($errno)";
		if ($httperror !== null) {
			$httperror = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		}
		rpc_debug_request($ch);
		$ret = null;
	}
	
	curl_close($ch);
	
	if (($pos = array_search($ch, $rpc_chs, true)) !== FALSE) {
		unset($rpc_chs[$pos]);
	}
	
	return $ret;
}

// some dumb shit for sending HTTP POST to another server.
// only use this function internally
function rpc_send_request($host, $url, $request, &$error, $internal=true) {
	$port = 80;
	$proto = 'tcp://';
	$internal_network = preg_match( '#\.int$#', $host ) || strpos( $host, '10.0' ) === 0;

	if(strpos($host, "4chan.org") !== false || $internal_network ) {
		if( strpos( $url, 'imgboard.php' ) !== false || strpos( $url, 'admin.php' ) !== false || strpos( $host, 'www.' ) !== false || $internal_network ) {
			$proto = 'ssl://';
			$port = 443;
		}
	}
	
	$timeout = $internal_network ? 60 : 4;

	$cookie = '';
	foreach($request['COOKIE'] as $name=>$value) {
		$name = urlencode($name);
		$value = urlencode($value);
		$cookie .= "$name=$value;";
	}

	$postbody = '';
	foreach($request['POST'] as $name=>$value) {
		$name = urlencode($name);
		$value = urlencode($value);
		$postbody .= "$name=$value&";
	}

	// POSTing with HTTP 1.1 tends to make responders send
	// back Transfer-encoding: chunked, so use 1.0
	
	$header  = "POST $url HTTP/1.0\r\n";
	$header .= "Host: $host\r\n";
	$header .= "Content-Type: application/x-www-form-urlencoded\r\n";
	$header .= "Content-Length: ". strlen($postbody) . "\r\n";
	$header .= "User-Agent: 4chan.org\r\n";
	if ($cookie && $internal) $header .= "Cookie: $cookie\r\n";
	$header .= "Connection: close\r\n";
	$header .= "\r\n";

	$header .= "$postbody\r\n";

	$rpc_start_time = microtime(true);
	$socket = fsockopen($proto.$host, $port, $errno, $errstr, $timeout);
	if(!$socket) {
		$error = $errstr; return;
	}
	
	if(fwrite($socket, $header) != strlen($header)) {
		$error = 'Could not write to socket'; return;
	}
	
	$rpc_connect_time = microtime(true);
	$rpc_connect_took = $rpc_connect_time - $rpc_start_time;
	stream_set_timeout($socket, $timeout - $rpc_connect_took);
	
	$response = '';
	do {
		$response .= fgets($socket, 1160);
		$info = stream_get_meta_data($socket);
	} while(!feof($socket) && !$info['timed_out']);

	fclose($socket);
	if(!preg_match('!^HTTP/1\.. 200 OK!', $response)) {
		$lines = explode("\n", $response);
		$error = 'Error response from server ('.strlen($response).' bytes): '. $lines[0];
		$response = null;
	}
	
	// $rpc_end_time = microtime(true);
	// $rpc_took = $rpc_end_time - $rpc_start_time;
	
	/*
	if ($error) {
		quick_log_to("/www/perhost/rpc-slow.log", "ERROR: $host$url ct $rpc_connect_took took $rpc_took error: ".implode("\n",$lines)."\n".$postbody);
	} else
	if ($rpc_took > 1) {
		quick_log_to("/www/perhost/rpc-slow.log", "SLOW: $host$url ct $rpc_connect_took took $rpc_took errored ".($response?0:1)."\n".$postbody);
	}
	*/
	
	return $response;
}

// a shortcut to create the request object (with cookie set and POST initialized)
function rpc_blank_request() {
	return array('COOKIE' => $_COOKIE, 'POST' => array() );
}

function rpc_log_url($s,$r) {
	$s = nl2br($s);
	$r = nl2br($r);
	$rh = fopen("/www/perhost/rpc.log", "a");
	flock($rh, LOCK_EX);
	fwrite($rh, "$s --> $r\n");
	fclose($rh);
}

// TODO: This isn't really used since we just block link shorteners instead.
// But it should be optimized into curl_multi if possible.
function rpc_find_real_url($short) {
	$cu = curl_init($short);
	curl_setopt($cu, CURLOPT_FOLLOWLOCATION, true);
	curl_setopt($cu, CURLOPT_MAXREDIRS, 4);
	curl_setopt($cu, CURLOPT_NOBODY, true);
	curl_setopt($cu, CURLOPT_TIMEOUT, 10);
	curl_setopt($cu, CURLOPT_USERAGENT, "4chan.org");

	if (curl_exec($cu)) {
		$ret = curl_getinfo($cu, CURLINFO_EFFECTIVE_URL);
	} else $ret = FALSE;

	curl_close($cu);
	//rpc_log_url($short,$ret);
	return $ret;
}
