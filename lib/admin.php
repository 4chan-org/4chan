<?
require_once 'db.php';
require_once 'rpc.php';

if( !defined( "SQLLOGMOD" ) ) {
	define( 'SQLLOGBAN', 'banned_users' ); // FIXME move to config_db.php?
	define( 'SQLLOGMOD', 'mod_users' );
}

// Parses the "email" field and returns a hash
function decode_user_meta($data) {
  if (!$data) {
    return [];
  }
  
  $data = explode(':', $data);
  
  $fields = [];
  
  $fields['browser_id'] = $data[0];
  $fields['is_mobile'] = $data[0] && $data[0][0] === '1';
  $fields['req_sig'] = $data[1];
  
  $known_status = (int)$data[2];
  
  $fields['verified_level'] = (int)$data[3];
  
  // Brand new user
  if ($known_status === 1) {
    $fields['is_new'] = true;
    $fields['is_known'] = false;
  }
  // Not new but not trusted yet
  else if ($known_status === 2) {
    $fields['is_new'] = false;
    $fields['is_known'] = false;
  }
  else {
    $fields['is_new'] = false;
    $fields['is_known'] = true;
  }
  
  $fields['known_status'] = $known_status;
  
  return $fields;
}

// Encodes email field data for storage in the database
// Entries are separates by ":"
// known status: 1 = new user, 2 unknown user
function encode_user_meta($browser_id, $req_sig, $userpwd) {
  $known_status = 0;
  $verified_level = 0;
  
  if ($userpwd) {
    if (!$userpwd->isUserKnown(60, 1)) { // 1h
      $known_status = 1; // New user
    }
    else if (!$userpwd->isUserKnown(1440)) { // 24h
      $known_status = 2; // Not yet trusted
    }
    
    if ($userpwd->verifiedLevel()) {
      $verified_level = 1;
    }
  }
  
  $data = [ $browser_id, $req_sig, $known_status, $verified_level ];
  $data = implode(':', $data);
  
  return $data;
}

function _grep_notjanitor( $a )
{
	return ( $a != 'janitor' );
}

function get_random_string( $len = 16 )
{
	$str = mt_rand( 1000000, 9999999 );
	$str = hash( 'sha256', $str );

	return substr( $str, -$len );
}

function derefer_url($url) {
  return 'https://www.4chan.org/derefer?url=' . rawurlencode($url);
}

function access_check()
{
	global $access;

	$user = $_COOKIE['4chan_auser'];
	$pass = $_COOKIE['apass'];

	if( !$user || !$pass ) return;

	$query = mysql_global_call( "SELECT allow,password_expired,level,flags,username,password,signed_agreement FROM mod_users WHERE username='%s' LIMIT 1", $user );
	
	if (!mysql_num_rows($query)) {
	  return '';
	}

	list($allow, $expired, $level, $flags, $username, $password, $signed_agreement) = mysql_fetch_row($query);
	
  $admin_salt = file_get_contents('/www/keys/2014_admin.salt');
  
  if (!$admin_salt) {
    die('Internal Server Error (s0)');
  }
  
  $hashed_admin_password = hash('sha256', $username . $password . $admin_salt);
	
  if ($hashed_admin_password !== $pass) {
    return '';
  }

	if( $expired ) {
		die( 'Your password has expired; check IRC for instructions on changing it.' );
	}
	
	if ($signed_agreement == 0 && basename($_SERVER['SELF_PATH']) !== 'agreement.php') {
		die('You must agree to the 4chan Volunteer Moderator Agreement in order to access moderation tools. Please check your e-mail for more information.');
	}
	
	if( $allow ) {
		if( $level == 'janitor' ) {
			$a          = $access['janitor'];
			$a['board'] = array_filter( explode( ',', $allow ), '_grep_notjanitor' );
			if( in_array( "all", $a['board'] ) )
				unset( $a['board'] );

			return $a;
		} elseif( $level == 'manager' ) {
			return $access['manager'];
		} elseif( $level == 'admin' ) {
			return $access['admin'];
		} elseif( $level == 'mod' ) {
		  if (is_array($access['mod'])) {
        $flags = explode(',', $flags);
        $access['mod']['is_developer'] = in_array('developer', $flags);
		  }
      return $access['mod'];
		} else {
			die( 'oh no you are not a right user!' );
		}
	} else {
		return '';
	}
}

//based on team pages' valid(), need to merge with above!
//this sets different globals and respects deny
function access_check2( $func = 0 )
{
	global $is_admin, $user, $pass;
	$is_admin = 0;
	$user     = "";
	$pass     = "";
	if( isset( $_COOKIE['4chan_auser'] ) && isset( $_COOKIE['4chan_apass'] ) ) {
		$user = $_COOKIE['4chan_auser'];
		$pass = $_COOKIE['4chan_apass'];
	}
	if( isset( $user ) && $user && $pass ) {
		$result = mysql_global_call( "SELECT allow,deny,password_expired FROM " . SQLLOGMOD . " WHERE username='%s' and password='%s' limit 1", $user, $pass );
		if( mysql_num_rows( $result ) != 0 ) {
			list( $allowed, $denied, $expired ) = mysql_fetch_array( $result );
			if( $expired ) {
				die( 'Your password has expired; check IRC for instructions on changing it.' );
			}
			if( $func == "unban" ) {
				$deny_arr = explode( ",", $denied );
				if( in_array( "unban", $deny_arr ) ) die( "You do not have access to unban users." );
			}
			$allow_arr = explode( ",", $allowed );
			if( in_array( "admin", $allow_arr ) || in_array( "manager", $allow_arr ) ) $is_admin = 1;
		} else {
			die( "Please login via admin panel first. (admin user not found)" );
		}
		if( $user && !$pass ) {
			die( "Please login via admin panel first. (no pass specified)" );
		} elseif( !$user && $pass ) {
			die( "Please login via admin panel first. (no user specified)" );
		}
	} else {
		die( "Please login via admin panel first." );
	}
}

function form_post_values( $names )
{
	$a = array();

	foreach( $names as $n ) {
		$v = $_REQUEST[$n];
		if( $v ) $a[$n] = $v;
	}

	return $a;
}

//rebuild the bans for board $boards
function rebuild_bans( $boards )
{
	// run in background
	$cmd = "nohup /usr/local/bin/suid_run_global bin/rebuildbans $boards >/dev/null 2>&1 &";
//	print "<br>Rebuilding bans in $boards<br>";
	exec( $cmd );
}

//add list of bans to the file for $boards
function append_bans( $boards, $bans )
{
	$str = is_array( $bans ) ? implode( ",", $bans ) : $bans;
	$cmd = "nohup /usr/local/bin/suid_run_global bin/appendban $boards $str >/dev/null 2>&1 &";
//	print "<br>Added new bans to $boards<br>";
	exec( $cmd );
}

// IPs that can't be banned because they're known good proxy servers
// e.g. cloudflare, singapore
function whitelisted_ip( $ip = 0 )
{
	list( $ips ) = post_filter_get( "ipwhitelist" );
	if( $ip === 0 ) $ip = $_SERVER["REMOTE_ADDR"];

	return find_ipxff_in( ip2long( $ip ), 0, $ips );
}

// add a global ban (indefinite for now)
// returns true if it was new (not already inserted)
function add_ban( $ip, $reason, $days = -1, $zonly = false, $origname = 'Anonymous', &$error, $no = 0, $pass = '', $no_reverse = false )
{
	global $user;
	if( ip2long( $ip ) === false ) {
		$error = "invalid IP address";

		return false;
	}
	if( whitelisted_ip( $ip ) ) {
		$error = "IP is whitelisted";

		return false;
	}

	// FIXME add unique index to banned_users instead
	$prev = mysql_global_call( "SELECT COUNT(*)>0 FROM " . SQLLOGBAN . " WHERE active=1 AND global=1 AND host='%s'", $ip );
	list( $nprev ) = mysql_fetch_array( $prev );
	if( $nprev > 0 ) return false;
  
	if ($no_reverse) {
	  $rev = $ip;
	}
	else {
	  $rev   = gethostbyaddr( $ip );
	}
	
  $tripcode = '';
  
  $name_bits = explode('</span> <span class="postertrip">!', $origname);
  
  if ($name_bits[1]) {
    $tripcode = preg_replace('/<[^>]+>/', '', $name_bits[1]);
  }
	
	$origname = str_replace( '</span> <span class="postertrip">!', ' #', $origname );
	$origname = preg_replace( '/<[^>]+>/', '', $origname ); // remove all remaining html crap
	
	$board = defined( 'BOARD_DIR' ) ? BOARD_DIR : "";

	if( $days == -1 )
		$length = "00000000000000";
	else
		$length = date( "Ymd", time() + $days * ( 24 * 60 * 60 ) ) . '000000';

	echo "Banned $ip (" . htmlspecialchars( $rev ) . ")<br>\n";
	
	if (!isset($user)) {
		$banned_by = $_COOKIE['4chan_auser'];
	}
	else {
		$banned_by = $user;
	}
	
	mysql_global_do( "INSERT INTO " . SQLLOGBAN . " (global,board,host,reverse,reason,admin,zonly,length,name,tripcode,4pass_id,post_num,admin_ip) values (%d,'%s','%s','%s','%s','%s',%d,'%s','%s','%s','%s',%d,'%s')", !$zonly, $board, $ip, $rev, "$reason", $banned_by, $zonly, $length, $origname, $tripcode, $pass, $no, $_SERVER['REMOTE_ADDR'] );

	return true;
}

function is_real_board( $board )
{
	// no board
	if( $board === "-" || $board === '' ) return true;

	$res = mysql_global_call( "select count(*) from boardlist where dir='%s'", $board );
	$row = mysql_fetch_row( $res );

	return ( $row[0] > 0 );
}

function remote_delete_things( $board, $nos, $tool = null )
{
	// see reports/actions.php, action_delete()
	$url = "https://sys.int/$board/";

	if( $board != 'f' ) // XXX dumb. :( XXX
		$url .= 'imgboard.php';
	else
		$url .= 'up.php';

	// Build the appropriate POST and cookie...
	$post               = array();
	$post['mode']       = 'usrdel';
	$post['onlyimgdel'] = ''; // never delete only img
	
	if ($tool) {
	  $post['tool'] = $tool;
	}
	
	// note multiple post number deletions
	foreach( $nos as $no )
		$post[$no] = 'delete';
	
	$post['remote_addr'] = $_SERVER['REMOTE_ADDR'];
	
	rpc_start_request($url, $post, $_COOKIE, true);
	
	return "";
}

function clear_cookies()
{
	if( strstr( $_SERVER["HTTP_HOST"], ".4chan.org" ) ) {
		setcookie( "4chan_auser", "", time() - 3600, "/", ".4chan.org", true );
		setcookie( "4chan_apass", "", time() - 3600, "/", ".4chan.org", true );
		setcookie( "4chan_aflags", "", time() - 3600, "/", ".4chan.org", true );

	} elseif( strstr( $_SERVER["HTTP_HOST"], ".4channel.org" ) ) {
		setcookie( "4chan_auser", "", time() - 24 * 3600, "/", ".4channel.org", true );
		setcookie( "4chan_apass", "", time() - 24 * 3600, "/", ".4channel.org", true );
	} else {
		setcookie( "4chan_auser", "", time() - 24 * 3600, "/", true );
		setcookie( "4chan_apass", "", time() - 24 * 3600, "/", true );
		setcookie( "4chan_aflags", "", time() - 24 * 3600, "/", true );
	}

	setcookie( 'extra_path', '', 1, '/', '.4chan.org' );
}

// record and autoban failed logins. assumes admin or imgboard.php as caller
function admin_login_fail()
{
	$ip = ip2long( $_SERVER["REMOTE_ADDR"] );
	clear_cookies();

	mysql_global_call( "insert into user_actions (ip,board,action,time) values (%d,'%s','fail_login',now())", $ip, BOARD_DIR );

	$query = mysql_global_call( "select count(*)>%d from user_actions where ip=%d and action='fail_login' and time >= subdate(now(), interval 1 hour)", LOGIN_FAIL_HOURLY, $ip );
	if( mysql_result( $query, 0, 0 ) ) {
		auto_ban_poster( "", -1, 1, "failed to login to /" . BOARD_DIR . "/admin.php " . LOGIN_FAIL_HOURLY . " times", "Repeated admin login failures." );
	}

	error( S_WRONGPASS );
}

// delete all posts everywhere by the poster's IP
// for autobans
function del_all_posts( $ip = false )
{
	$q      = mysql_global_call( "select sql_cache dir from boardlist" );
	$boards = mysql_column_array( $q );

	$host = $ip ? $ip : $_SERVER['REMOTE_ADDR'];

	foreach( $boards as $b ) {
		$q     = mysql_board_call( "select no from `%s` where host='%s'", $b, $host );
		$posts = mysql_column_array( $q );
		if( !count( $posts ) ) continue;
		remote_delete_things( $b, $posts );
	}
}

function auto_ban_poster($nametrip, $banlength, $global, $reason, $pubreason = '', $is_filter = false, $pwd = null, $pass_id = null) {
	if (!$nametrip) {
		$nametrip = S_ANONAME;
	}
	
	if (strpos($nametrip, '</span> <span class="postertrip">!') !== false) {
		$nameparts = explode('</span> <span class="postertrip">!', $nametrip);
		$nametrip  = "{$nameparts[0]} #{$nameparts[1]}";
	}
	
	$host    = $_SERVER['REMOTE_ADDR'];
	$reverse = mysql_real_escape_string(gethostbyaddr($host));

	$nametrip  = mysql_real_escape_string($nametrip);
	$global    = ($global ? 1 : 0);
	$board     = defined( 'BOARD_DIR' ) ? BOARD_DIR : '';
	$reason    = mysql_real_escape_string($reason);
	$pubreason = mysql_real_escape_string($pubreason);
	
	if ($pubreason) {
		$pubreason .= "<>";
	}
  
  if ($pass_id) {
    $pass_id = mysql_real_escape_string($pass_id);
  }
  else {
    $pass_id = '';
  }
  
  if ($pwd) {
  	$pwd = mysql_real_escape_string($pwd);
  }
  else {
  	$pwd = '';
  }
  
	// check for whitelisted ban
	if( whitelisted_ip() ) return;

	//if they're already banned on this board, don't insert again
	//since this is just a spam post
	//i don't think it matters if the active ban is global=0 and this one is global=1
	/*
	if ($banlength == -1) {
		$existingq = mysql_global_do("select count(*)>0 from " . SQLLOGBAN . " where host='$host' and active=1 AND global = 1 AND length = 0");
	}
	else {
		$existingq = mysql_global_do("select count(*)>0 from " . SQLLOGBAN . " where host='$host' and active=1 and (board='$board' or global=1)");
	}
	$existingban = mysql_result( $existingq, 0, 0 );
	if( $existingban > 0 ) {
		delete_uploaded_files();
		die();
	}
	*/
	/*
	if( $banlength == 0 ) { // warning
		// check for recent warnings to punish spammers
		$autowarnq     = mysql_global_call( "SELECT COUNT(*) FROM " . SQLLOGBAN . " WHERE host='$host' AND admin='Auto-ban' AND now > DATE_SUB(NOW(),INTERVAL 3 DAY) AND reason like '%$reason'" );
		$autowarncount = mysql_result( $autowarnq, 0, 0 );
		if( $autowarncount > 3 ) {
			$banlength = 14;
		}
	}
	*/
	
	if ($banlength == -1) { // permanent
		$length = '0000' . '00' . '00'; // YYYY/MM/DD
	}
	else {
		$banlength = (int)$banlength;
		
		if ($banlength < 0) {
			$banlength = 0;
		}
		
		$length = date('Ymd', time() + $banlength * (24 * 60 * 60));
	}
	
	$length .= "00" . "00" . "00"; // H:M:S
	
	$sql = "INSERT INTO " . SQLLOGBAN . " (board,global,name,host,reason,length,admin,reverse,post_time,4pass_id,password) VALUES('$board','$global','$nametrip','$host','{$pubreason}Auto-ban: $reason','$length','Auto-ban','$reverse',NOW(),'$pass_id','$pwd')";
	
	$res = mysql_global_call($sql);
	
	if (!$res) {
		die(S_SQLFAIL);
	}
	
	//append_bans( $global ? "global" : $board, array($host) );
	
	//$child = stripos($pubreason, 'child') !== false || stripos($reason, 'child') !== false;
	
	//if ($global && $child && !$is_filter) {
	//	del_all_posts();
	//}
}

function cloudflare_purge_url_old($file,$secondary = false)
{
	global $purges;
	
	if (!defined('CLOUDFLARE_API_TOKEN')) {
		internal_error_log('cf', "tried purging but token isn't set");
		return null;
	}
	
	$post = array(
		"tkn"   => CLOUDFLARE_API_TOKEN,
		"email" => CLOUDFLARE_EMAIL,
		"a"     => "zone_file_purge",
		"z"     => $secondary ? CLOUDFLARE_ZONE_2 : CLOUDFLARE_ZONE,
		"url"   => $file
	);
	
	//quick_log_to("/www/perhost/cf-purge.log", print_r($post, true));
	
	$ch = rpc_start_request("https://www.cloudflare.com/api_json.html", $post, array(), false);
	return $ch;
}

function write_to_event_log($event, $ip, $args = []) {
	$sql = <<<SQL
INSERT INTO event_log(`type`, ip, board, thread_id, post_id, arg_num,
arg_str, pwd, req_sig, ua_sig, meta)
VALUES('%s', '%s', '%s', '%d', '%d', '%d',
'%s', '%s', '%s', '%s', '%s')
SQL;

	return mysql_global_call($sql, $event, $ip,
		$args['board'], $args['thread_id'], $args['post_id'], $args['arg_num'],
		$args['arg_str'], $args['pwd'], $args['req_sig'], $args['ua_sig'], $args['meta']
	);
}

function log_staff_event($event, $username, $ip, $pwd, $board, $post) {
	$json_post = [];
	
	if ($post['sub'] !== '') {
		$json_post['sub'] = $post['sub'];
	}
	
	if ($post['name'] !== '') {
		$json_post['name'] = $post['name'];
	}
	
	if ($post['com'] !== '') {
		$json_post['com'] = $post['com'];
	}
	
	if ($post['fsize'] > 0) {
		$json_post['file'] = $post["filename"].$post["ext"];
		$json_post['md5'] = $post["md5"];
	}
	
	$json_post = json_encode($json_post, JSON_PARTIAL_OUTPUT_ON_ERROR);
	
	return write_to_event_log($event, $ip, [
    'board' => $board,
    'thread_id' => $post['resto'] ? $post['resto'] : $post['no'],
    'post_id' => $post['no'],
    'arg_str' => $username,
    'pwd' => $pwd,
    'meta' => $json_post
  ]);
}

function cloudflare_purge_url($files, $zone2 = false) {
  // 4cdn = ca66ca34d08802412ae32ee20b7e98af (zone2)
  // 4chan = 363d1b9b6be563ffd5143c8cfcc29d52
  
  $url = 'https://api.cloudflare.com/client/v4/zones/'
    . ($zone2 ? 'ca66ca34d08802412ae32ee20b7e98af' : '363d1b9b6be563ffd5143c8cfcc29d52')
    . '/purge_cache';
  
  $opts = array(
    CURLOPT_CUSTOMREQUEST => 'POST',
    CURLOPT_HTTPHEADER => array(
      'Authorization: Bearer iTf0pQMTvn0zSHAN9vg5S1m_tiwmPKYDjepq8za9',
      'Content-Type: application/json'
    )
  );
  
  // Multiple files
  if (is_array($files)) {
    // Batching
    if (count($files) > 30) {
      $files = array_chunk($files, 30);
      
      foreach ($files as $batch) {
        $opts[CURLOPT_POSTFIELDS] = '{"files":' . json_encode($batch, JSON_UNESCAPED_SLASHES) . '}';
        //print_r($opts[CURLOPT_POSTFIELDS]);
        rpc_start_request_with_options($url, $opts);
      }
    }
    else {
      $opts[CURLOPT_POSTFIELDS] = '{"files":' . json_encode($files, JSON_UNESCAPED_SLASHES) . '}';
      //print_r($opts[CURLOPT_POSTFIELDS]);
      rpc_start_request_with_options($url, $opts);
    }
  }
  // Single file
  else {
    $opts[CURLOPT_POSTFIELDS] = '{"files":["' . $files . '"]}';
    //print_r($opts[CURLOPT_POSTFIELDS]);
    rpc_start_request_with_options($url, $opts);
  }
}

function cloudflare_purge_by_basename($board, $basename) {
	preg_match("/([0-9]+)[sm]?\\.([a-z]{3,4})/", $basename, $m);
	$tim = $m[1];
	$ext = $m[2];
	
	cloudflare_purge_url("https://i.4cdn.org/$board/$tim.$ext", true);
	cloudflare_purge_url("https://i.4cdn.org/$board/${tim}s.jpg", true);
	cloudflare_purge_url("https://i.4cdn.org/$board/${tim}m.jpg", true);
}
