<?php
/** User authentication / flag stuff */
$auth = array(
	'level' => false,
	'flags' => false,
	'allow' => false,
	'deny'  => false,
	'guest' => true,
);

$levelorder = array(
	1  => 'janitor',
	10 => 'mod',
	20 => 'manager',
	50 => 'admin'
);

$levelorderf = array(
	'janitor' => 1,
	'mod'     => 10,
	'manager' => 20,
	'admin'   => 50
);

if (!defined('SQLLOGMOD')) {
	define("SQLLOGMOD", "mod_users");
	define('PASS_TIMEOUT', 1800);
    define('LOGIN_FAIL_HOURLY', 5);
}

function csrf_tag() {
  if (isset($_COOKIE['_tkn'])) {
    return '<input type="hidden" value="' . htmlspecialchars($_COOKIE['_tkn'], ENT_QUOTES) . '" name="_tkn">';
  }
  else {
    return '';
  }
}

function csrf_attr() {
  if (isset($_COOKIE['_tkn'])) {
    return 'data-tkn="' . htmlspecialchars($_COOKIE['_tkn'], ENT_QUOTES) . '"';
  }
  else {
    return '';
  }
}

function auth_encrypt($data) {
  $key = file_get_contents('/www/keys/2015_enc.key');
  
  if (!$key) {
    return false;
  }
  
  $iv_size = mcrypt_get_iv_size(MCRYPT_RIJNDAEL_256, MCRYPT_MODE_CBC);
  $iv = mcrypt_create_iv($iv_size, MCRYPT_RAND);
  
  $encrypted = mcrypt_encrypt(MCRYPT_RIJNDAEL_256, $key, $data, MCRYPT_MODE_CBC, $iv);
  
  if ($encrypted === false) {
    return false;
  }
  
  return $iv . $encrypted;
}

function auth_decrypt($data) {
  $key = file_get_contents('/www/keys/2015_enc.key');
  
  if (!$key) {
    return false;
  }
  
  $iv_size = mcrypt_get_iv_size(MCRYPT_RIJNDAEL_256, MCRYPT_MODE_CBC);
  $iv_dec = substr($data, 0, $iv_size);
  
  $data = substr($data, $iv_size);
  
  $data = mcrypt_decrypt(MCRYPT_RIJNDAEL_256, $key, $data, MCRYPT_MODE_CBC, $iv_dec);
  
  if ($data === false) {
    return false;
  }
  
  return rtrim($data, "\0");
}

function verify_one_time_pwd($username, $otp) {
  if (!$otp) {
    return false;
  }
  
  $query = "SELECT auth_secret FROM mod_users WHERE username = '%s' LIMIT 1";
  
  $res = mysql_global_call($query, $username);
  
  if (!$res) {
    return false;
  }
  
  $enc_secret = mysql_fetch_row($res)[0];
  
  if (!$enc_secret) {
    return false;
  }
  
  require_once 'lib/GoogleAuthenticator.php';
  
  $ga = new PHPGangsta_GoogleAuthenticator();
  
  $dec_secret = auth_decrypt($enc_secret);
  
  if ($dec_secret === false) {
    return false;
  }
  
  if ($ga->verifyCode($dec_secret, $otp, 2)) {
    return true;
  }
  
  return false;
}

/**
 * Returns a hash containing implicit levels for the current authed level
 * ex: will return array('janitor' => true, 'mod' => true)
 * if the current level is 'mod'
 */
function get_level_map($level = null) {
	global $auth, $levelorderf;
	
  $map = array();
  
  if (!$level) {
    $level = $auth['level'];
  }
  
  if (!$level) {
    return $map;
  }
  
  $level_value = (int)$levelorderf[$level];
  
  foreach ($levelorderf as $k => $v) {
    if ($v <= $level_value) {
      $map[$k] = true;
    }
  }
  
  return $map;
}

function has_level( $level = 'mod', $board = false )
{
	if( is_local_auth() ) return YES;

	global $auth, $levelorder, $levelorderf;
	static $ourlevel = -1;


	//if( !$board && defined( 'BOARD_DIR' ) ) $board = BOARD_DIR;
	//if( !access_board($board) ) return false;
	if( $ourlevel < 0 ) $ourlevel = $levelorderf[$auth['level']];
  
  if (!isset($levelorderf[$level])) {
    return false;
  }
  
	if( $levelorderf[$level] <= $ourlevel ) return true;

	return false;
}

function has_flag( $flag, $board = false )
{
	if( is_local_auth() ) return YES;

	global $auth;
	if( $auth['guest'] ) return false;

	if( !access_board( $board ) ) return false;
	if( in_array( $flag, $auth['flags'] ) ) return true;

	return false;
}

function access_board( $board )
{
	if( is_local_auth() ) return YES;

	global $auth;

	if( $auth['guest'] ) return false;

	$can_do = false;

	// See if we have access to this board or all
	if( in_array( 'all', $auth['allow'] ) || in_array( $board, $auth['allow'] ) ) $can_do = true;

	// Are we denied on this board?
	if( $board && in_array( $board, $auth['deny'] ) ) $can_do = false;

	// If we're not using a board, are we denied for no-board stuff?
	if( !$board && in_array( 'noboard', $auth['deny'] ) ) $can_do = false;

	return $can_do;
}

function is_user()
{
	if( is_local_auth() ) return YES;

	global $auth;
	if( $auth['guest'] ) return false;
	if( $auth['level'] ) return true;

	return false;
}

function auth_user($skip_agreement = false) {
	global $auth;

	$user = $_COOKIE['4chan_auser'];
	$pass = $_COOKIE['apass'];
	
	if( !$user || !$pass ) return false;

	$query = mysql_global_call("SELECT * FROM `%s` WHERE `username` = '%s' LIMIT 1", SQLLOGMOD, $user);
	
	if (!mysql_num_rows($query)) {
	  return false;
  }
  
	$fetch = mysql_fetch_assoc($query);
	
  $admin_salt = file_get_contents('/www/keys/2014_admin.salt');
  
  if (!$admin_salt) {
    die('Internal Server Error (s0)');
  }
  
  $hashed_admin_password = hash('sha256', $fetch['username'] . $fetch['password'] . $admin_salt);
	
  if ($hashed_admin_password !== $pass) {
    return false;
  }
  
	if ($fetch['password_expired'] == 1) {
		die('Your password has expired; check IRC for instructions on changing it.');
	}
	
	if (!$skip_agreement) {
  	if ($fetch['signed_agreement'] == 0 && basename($_SERVER['SELF_PATH']) !== 'agreement.php' && basename($_SERVER['SELF_PATH']) !== 'agreement_genkey.php') {
  		die('You must agree to the 4chan Volunteer Moderator Agreement in order to access moderation tools. Please check your e-mail for more information.');
  	}
	}
	
	$auth['level'] = $fetch['level'];
	$auth['flags'] = explode( ',', $fetch['flags'] );
	$auth['allow'] = explode( ',', $fetch['allow'] );
	$auth['deny']  = explode( ',', $fetch['deny'] );
	$auth['guest'] = false;

	$flags = array();

	if( has_level( 'admin' ) ) {
		$flags['forcedanonname'] = 2;
	}

	if( has_level( 'manager' ) || has_flag( 'html' ) ) {
		$flags['html'] = 1;
	}

	$flags = array_flip( $flags );
	$flags = implode( ',', $flags );
  
  $ips_array = json_decode($fetch['ips'], true);
  
  if (json_last_error() !== JSON_ERROR_NONE) {
    die('Database Error (1-0)');
  }
  
  $ips_array[$_SERVER['REMOTE_ADDR']] = $_SERVER['REQUEST_TIME'];
  
  if (count($ips_array) > 512) {
    asort($ips_array);
    array_shift($ips_array);
  }
  
  $ips_array = json_encode($ips_array);
  
  if (json_last_error() !== JSON_ERROR_NONE) {
    die('Database Error (1-1)');
  }
  
  if (mb_strlen($_SERVER['HTTP_USER_AGENT']) > 128) {
    $ua = mb_substr($_SERVER['HTTP_USER_AGENT'], 0, 128);
  }
  else {
    $ua = $_SERVER['HTTP_USER_AGENT'];
  }
  
  mysql_global_call("UPDATE `%s` SET ips = '$ips_array', last_ua = '%s' WHERE id = %d LIMIT 1", SQLLOGMOD, $ua, $fetch['id']);
  
	return true;
}
// OLD auth
/*
function auth_user( $login = false )
{
	global $auth;
	
	if( $login ) {
		$user = $_POST['userlogin'];
		$pass = $_POST['passlogin'];
	} else {
		$user = $_COOKIE['4chan_auser'];
		$pass = $_COOKIE['4chan_apass'];
	}

	if( !$user || !$pass ) return false;

	$query = mysql_global_call( "SELECT * FROM `%s` WHERE `username` = '%s' LIMIT 1", SQLLOGMOD, $user );
	if( !mysql_num_rows( $query ) ) return false;
	$fetch = mysql_fetch_assoc( $query );
	
	if( $fetch['password_expired'] == 1 ) {
		die( 'Your password has expired; check IRC for instructions on changing it.' );
	}
	
	if ($login) {
		if( !password_verify($pass, $fetch['password'])) return false;

		$pass = $fetch['password'];
	} else {
		if ($pass != $fetch['password']) return false;
	}

	$auth['level'] = $fetch['level'];
	$auth['flags'] = explode( ',', $fetch['flags'] );
	$auth['allow'] = explode( ',', $fetch['allow'] );
	$auth['deny']  = explode( ',', $fetch['deny'] );
	$auth['guest'] = false;

	$flags = array();

	if( has_level( 'admin' ) && $user == 'moot' ) {
		$flags['forcedanonname'] = 2;
	}

	if( has_level( 'manager' ) || has_flag( 'html' ) ) {
		$flags['html'] = 1;
	}

	$flags = array_flip( $flags );
	$flags = implode( ',', $flags );
  
  $ips_array = json_decode($fetch['ips'], true);
  
  if (json_last_error() !== JSON_ERROR_NONE) {
    die('Database Error (1-0)');
  }
  
  $ips_array[$_SERVER['REMOTE_ADDR']] = $_SERVER['REQUEST_TIME'];
  $ips_array = json_encode($ips_array);
  
  if (json_last_error() !== JSON_ERROR_NONE) {
    die('Database Error (1-1)');
  }
  
  if ($login) {
    $login_query = ", last_login = now()";
  }
  else {
    if (!isset($_COOKIE['apass'])) {
      return false;
    }
    
    $admin_salt = file_get_contents('/www/keys/2014_admin.salt');
    
    if (!$admin_salt) {
      die('Internal Server Error (s0)');
    }
    
    $hashed_admin_cookie = $_COOKIE['apass'];
    $hashed_admin_password = hash('sha256', $fetch['username'] . $fetch['password'] . $admin_salt);
    
    if ($hashed_admin_password !== $hashed_admin_cookie) {
      return false;
    }
    
    $login_query = '';
  }
  
  mysql_global_do("UPDATE `%s` SET ips = '$ips_array' $login_query WHERE id = %d", SQLLOGMOD, $fetch['id']);
  
	if( !isset( $_COOKIE['4chan_auser'] ) || !isset( $_COOKIE['4chan_apass'] ) ) {
		if( strstr( $_SERVER["HTTP_HOST"], ".4chan.org" ) ) {
			setcookie( "4chan_auser", $user, time() + 30 * 24 * 3600, "/", ".4chan.org", true, true );
			setcookie( "4chan_apass", $pass, time() + 30 * 24 * 3600, "/", ".4chan.org", true, true );
			setcookie( "4chan_aflags", $flags, time() + 30 * 24 * 3600, "/", ".4chan.org", true );

			$jspath = $auth['level'] == 'janitor' ? JANITOR_JS_PATH : ADMIN_JS_PATH;
			if( !isset( $_COOKIE['extra_path'] ) || !in_array( $_COOKIE['extra_path'], array(JANITOR_JS_PATH, ADMIN_JS_PATH) ) ) {
				setcookie( 'extra_path', $jspath, time() + ( 30 * 24 * 3600 ), '/', '.4chan.org' );
			}
		} elseif( strstr( $_SERVER["HTTP_HOST"], ".4channel.org" ) ) {
			setcookie( "4chan_auser", $user, time() + 30 * 24 * 3600, "/", ".4channel.org", true, true );
			setcookie( "4chan_apass", $pass, time() + 30 * 24 * 3600, "/", ".4channel.org", true, true );
		} else {
			die( 'Not 4chan.org' );
		}
		
	}
  
	return true;
}
*/
function is_local_auth()
{	
	if (!isset($_SERVER['REMOTE_ADDR'])) {
	  return true;
	}
	
	// local rpc can do anything
	$longip = ip2long( $_SERVER['REMOTE_ADDR'] );
	
	if(
		cidrtest( $longip, "10.0.0.0/24" ) ||
		cidrtest( $longip, "204.152.204.0/24" ) ||
		cidrtest( $longip, "127.0.0.0/24" )
	) {
		return YES;
	}

	return false;
}

function can_delete( $resno )
{
	if( !has_level( 'janitor' ) ) return false;
	if( has_level( 'janitor' ) && access_board( BOARD_DIR ) ) return true;
	//if( !access_board(BOARD_DIR) ) return false;

	$query         = mysql_global_do( "SELECT COUNT(*) from reports WHERE board='%s' AND no=%d AND cat=2", BOARD_DIR, $resno );
	$illegal_count = mysql_result( $query, 0, 0 );
	mysql_free_result( $query );

	return $illegal_count >= 3;
}

function start_auth_captcha($use_alt_captcha = false)
{
	if (valid_captcha_bypass() !== true) {
		if ($use_alt_captcha) {
			start_recaptcha_verify_alt();
		}
		else {
			start_recaptcha_verify();
		}
	}
}

function clear_pass_cookies() {
	setcookie('pass_id', null, 1, '/', 'sys.4chan.org', true, true);
	setcookie('pass_id', null, 1, '/', '.4chan.org', true, true);
	setcookie('pass_enabled', null, 1, '/', '.4chan.org');
}

function valid_captcha_bypass()
{
	global $captcha_bypass, $passid, $rangeban_bypass;
  
  $captcha_bypass = false;
  $rangeban_bypass = false;
  
  $passid = '';
  
	if (is_local_auth() || has_level('janitor')) { 
	  $captcha_bypass = true;
	  $rangeban_bypass = true;
	  return true;
  }
	
	if (CAPTCHA != 1) {
	  $captcha_bypass = true;
	}
  
	$time = $_SERVER['REQUEST_TIME'];
	$host = $_SERVER['REMOTE_ADDR'];
  
	// check for 4chan pass
	$pass_cookie = isset( $_COOKIE['pass_id'] ) ? $_COOKIE['pass_id'] : '';
	
	if (strlen($pass_cookie) == 10) {
    setcookie('pass_id', '0', 1, '/', '.4chan.org', true, true);
    setcookie('pass_enabled', '0', 1, '/', '.4chan.org');
		error(S_PASSFORMATCHANGED);
	}
	
	if ($pass_cookie) {
	  $pass_parts = explode('.', $pass_cookie);
	  
	  $pass_user = $pass_parts[0];
	  $pass_session = $pass_parts[1];
	  
	  if (!$pass_user || !$pass_session) {
		  error(S_INVALIDPASS);
	  }
	  
    // The column is case insensitive but all passes should be uppercase to avoid ban bypassing exploits.
    $pass_user = strtoupper($pass_user);
    
    $admin_salt = file_get_contents('/www/keys/2014_admin.salt');
    
    if (!$admin_salt) {
      die('Internal Server Error (s0)');
    }
    
		$passq = mysql_global_call("SELECT user_hash, session_id, last_ip, last_used, last_country, status, pending_id, UNIX_TIMESTAMP(expiration_date) as expiration_date FROM pass_users WHERE pin != '' AND user_hash = '%s'", $pass_user);
		
		if( !$passq ) error( S_INVALIDPASS );
		
		$res = mysql_fetch_assoc($passq);
		
		if (!$res || !$res['session_id']) {
		  clear_pass_cookies();
		  error(S_INVALIDPASS);
	  }
		
	  $hashed_pass_session = substr(hash('sha256', $res['session_id'] . $admin_salt), 0, 32);
	  
		if ($hashed_pass_session !== $pass_session) {
		  clear_pass_cookies();
		  error(S_INVALIDPASS);
		}
		
		if ((int)$res['expiration_date'] <= $time) {
		  clear_pass_cookies();
			error(sprintf(S_PASSEXPIRED, $res['pending_id']));
		}
		
		if ($res['status'] != 0) {
		  clear_pass_cookies();
		  error(S_PASSDISABLED);
	  }

		$lastused = strtotime( $res['last_used'] );
		$lastip_mask = ip2long( $res['last_ip'] ) & ( ~255 );
		$ip_mask     = ip2long( $host ) & ( ~255 );

		if( $lastip_mask !== 0 && ( $time - $lastused ) < PASS_TIMEOUT && $lastip_mask != $ip_mask ) {

			// old strict code, above is to match last octet
			//if( ( $time - $lastused ) < PASS_TIMEOUT && $res['last_ip'] != $host && $res['last_ip'] != '0.0.0.0' ) {
		  clear_pass_cookies();
			error( S_PASSINUSE );
		}
		
		$update_country = '';
		
    if ($res['last_ip'] !== $host) {
      $geo_data = GeoIP2::get_country($host);
      
      if ($geo_data && isset($geo_data['country_code'])) {
        $country_code = $geo_data['country_code'];
      }
      else {
        $country_code = 'XX';
      }
      
      $update_country = ", last_country = '" . mysql_real_escape_string($country_code) . "'";
    }
    
    $passid = $pass_user;
    
		$captcha_bypass = true;
		$rangeban_bypass = true;
		
		mysql_global_call( "UPDATE pass_users SET last_used = NOW(), last_ip = '%s' $update_country WHERE user_hash = '%s' AND status = 0 LIMIT 1", $host, $res['user_hash'], $host );
	}
	
	return $captcha_bypass;
}

// some code paths might think current admin name is 4chan_auser cookie
// when that's not set (e.g. local requests), assert out here
function validate_admin_cookies()
{
	if (!$_COOKIE['4chan_auser']) {
		error('Internal error (internal request missing name)');
	}
}
