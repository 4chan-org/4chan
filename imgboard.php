<?php
require_once 'lib/util.php';
/*
if( isset( $_REQUEST["profile"] ) ) {
	xhprof_enable( XHPROF_FLAGS_CPU | XHPROF_FLAGS_MEMORY );
	register_shutdown_function( "xhprof_save" );
}
if ( isset($_REQUEST["sqlprofile"] )) {
	$mysql_query_log = YES;
}
*/
require_once "yotsuba_config.php";

require_once( "lib/ads.php" );

if (TEST_BOARD) {
  require_once 'lib/admin-test.php'; 
  require_once( "lib/postfilter-test.php" );
  require_once 'lib/captcha-test.php';
  require_once 'lib/auth-test.php';
  require_once 'lib/geoip2-test.php';
  require_once 'lib/userpwd-test.php';
}
else {
  require_once 'lib/admin.php'; 
  require_once( "lib/postfilter.php" );
  require_once 'lib/captcha.php';
  require_once 'lib/auth.php';
  require_once 'lib/geoip2.php';
  require_once 'lib/userpwd.php';
}

require_once 'lib/phash.php';

if (ENABLE_PAINTERJS) {
  if (TEST_BOARD) {
    require_once('lib/oekaki-test.php');
  }
  else {
    require_once('lib/oekaki.php');
  }
}

// HTML rendering unique to imgboard and upload board
if (UPLOAD_BOARD) {
	require_once "views/upboard.php";
} else if (TEST_BOARD) {
	require_once "views/imgboard-test.php";
} else {
	require_once "views/imgboard.php";
}

if( !function_exists( 'generate_catalog' ) ) {
	if (TEST_BOARD) {
		require_once 'catalog-test.php';
	} else {
		require_once 'catalog.php';
	}
}

if( ENABLE_JSON ) {
	$in_imgboard = 1;
	
	if (TEST_BOARD) {
		require_once 'json-test.php';
	} else {
		require_once 'json.php';
	}
}

if (!defined('SQLLOGMOD')) {
  define( 'SQLLOGBAN', 'banned_users' ); //Table (NOT DATABASE) used for holding banned users
  define( 'SQLLOGMOD', 'mod_users' ); //Table (NOT DATABASE) used for holding mod users
}

define( 'SQLLOGDEL', 'del_log' ); //Table (NOT DATABASE) used for holding deletion log

auth_user();

if (LOCKDOWN) {
  if (($_POST['mode'] == 'usrdel' || $_POST['mode'] == 'arcdel' || $_GET['mode'] == 'latest') && has_level('janitor')) {
    // Allow janitors to delete posts and update mod tools
  }
  else if (has_level('manager') || has_flag('developer')) {
    // Allow the manager to do anything
  }
  else {
    die('<span style="color: red;" id="errmsg">' . LOCKDOWN_MSG . '</span>');
  }
}

if (TEST_BOARD && (!has_level() || !has_flag('developer'))) {
	die('');
}

// test
if( TEST_BOARD || has_flag('developer') ) {
	ini_set( 'display_errors', 1 );
	//error_reporting(E_ALL & ~E_NOTICE);
}

extract( $_POST, EXTR_SKIP );
extract( $_GET, EXTR_SKIP );
extract( $_COOKIE, EXTR_SKIP );

if (isset( $_COOKIE['4chan_pass']) && $_COOKIE['4chan_pass']) {
  $pwdc = $_COOKIE['4chan_pass'];
}
else {
  $pwdc = null;
}

// FIXME whitelist
unset( $dest );
unset( $log );
unset( $update_avg_secs );

if( $argv[1] ) $mode = $argv[1];
$id = intval( $id );

if( $_SERVER["REQUEST_METHOD"] == "POST" ) {
	// bust cache
	header( 'Cache-Control: private, no-cache, must-revalidate' );
	header( 'Expires: -1' );
	header( 'Vary: *' );
}

if( array_key_exists( 'upfile', $_FILES ) ) {
	$upfile_name = $_FILES["upfile"]["name"];
	$upfile      = $_FILES["upfile"]["tmp_name"];
} else {
	$upfile_name = $upfile = '';
}

$fwritetimer = 0.0;

ignore_user_abort( true );

$word_filters_enabled = false;
if (WORD_FILT) {
  $word_filt_root = '/www/global/yotsuba/wordfilters/';
  
  if (file_exists($word_filt_root . BOARD_DIR . '.php')) {
    include_once($word_filt_root . BOARD_DIR . '.php');
    $word_filters_enabled = true;
  }
  else if (file_exists($word_filt_root . 'global.php')) {
    include_once($word_filt_root . 'global.php');
    $word_filters_enabled = true;
  }
}

if( JANITOR_BOARD == 1 && !has_level( 'janitor' ) ) {
	die( '' );
}

if( JANITOR_BOARD == 1 )
	include_once 'plugins/broomcloset.php';

// QENHANCE
if( META_BOARD ) {
	include_once 'plugins/enhance_q.php';
}

$mysql_connect_opts = 0;
mysql_board_connect(BOARD_DIR);

$board_flags_array = null;

if (ENABLE_BOARD_FLAGS) {
  $_flags_type = (defined('BOARD_FLAGS_TYPE') && BOARD_FLAGS_TYPE) ? BOARD_FLAGS_TYPE : BOARD_DIR;
  $_board_flags_path = '/www/global/yotsuba/lib/board_flags_' . $_flags_type . '.php';
  if (file_exists($_board_flags_path)) {
    include_once($_board_flags_path);
    $board_flags_array = get_board_flags_array();
  }
}

$thread_unique_ips = 0;

$index_rbl         = PAGE_MAX;
$index_last_thread = 0;
$index_last_post   = 0;

if (JANITOR_BOARD && PAGE_MAX == 0) {
  $index_rbl = ceil(LOG_MAX / DEF_PAGES);
}

$valid_boards = "3|aco|adv|an|biz|diy|fa|fit|gd|gif|int|lit|hc|hr|a|b|ck|co|cm|c|d|e|f|g|h|i|k|lgbt|m|n|o|out|p|r|s|t|u|vp|vg|vr|v|w|x|y|wg|ic|cgl|hm|mlp|mu|pol|po|r9k|s4s|sci|soc|tg|tv|toy|trv|jp|sp|wsg|qa|qst|his|trash|news|wsr|vip|bant|vrpg|vmg|vst|vt|vm|pw|xs";

$boards_matching_arr = array();

$captcha_bypass = null;
$rangeban_bypass = false;
$passid = '';

// FIXME, this should be put somewhere else.
function is_local() {
	$longip = ip2long( $_SERVER[ 'REMOTE_ADDR' ] );

	return !$longip || cidrtest( $longip, "10.0.0.0/24" ) || cidrtest( $longip, "204.152.204.0/24" ) || cidrtest( $longip, "127.0.0.0/24" );
}

/**
 * Abbreviates posts on index pages.
 * Truncate $str to $max_lines lines and return $str and $abbr
 * where $abbr = whether or not $str was actually truncated.
 * Expects well-formed HTML.
 */
function abbreviate($str, $max_lines = 20) {
  $lines = explode('<br>', $str);
  
  if (count($lines) > $max_lines) {
    $abbr = 1;
    $lines = array_slice($lines, 0, $max_lines);
    $str = implode('<br>', $lines );
    
    $unpaired_tags = array(
      'img' => true,
      'br' => true,
      'input' => true,
      'hr' => true,
      'param' => true
    );
    
    preg_match_all('/<\/([^>]+)>/', $str, $closed_tags, PREG_SET_ORDER);
    $closed_count = count($closed_tags);
    
    $closed_map = array();
    
    foreach ($closed_tags as $m) {
      if (!isset($closed_map[$m[1]])) {
        $closed_map[$m[1]] = 1;
      }
      else {
        $closed_map[$m[1]] += 1;
      }
    }
    
    preg_match_all('/<([a-z0-9]+)(?: |>)/', $str, $open_tags, PREG_SET_ORDER);
    $open_count = count($open_tags);
    
    for ($i = 0; $i < $open_count; ++$i) {
      $tag = $open_tags[$i][1];
      
      if (isset($unpaired_tags[$tag])) {
        continue;
      }
      
      if (!isset($closed_map[$tag])) {
        $str .= "</$tag>";
      }
      else if ($closed_map[$tag] > 0) {
        $closed_map[$tag] -= 1;
      }
      else if ($closed_map[$tag] <= 0) {
        $str .= "</$tag>";
      }
    }
  }
  else {
    $abbr = 0;
  }
  
  return array($str, $abbr);
}

/**
 * Currently only used on /archive
   * strips html tags and replaces sjis art with [SJIS] placeholders
 */
function truncate_comment($str, $length, $keep_spoilers = false) {
  // remove sjis
  if (SJIS_TAGS && strpos($str, '<span class="sjis"') !== false) {
    $str = preg_replace('/<span class="sjis".+?<\/span>/', '[SJIS]', $str);
  }
  
  $len = mb_strlen($str);
  
  if ($len <= $length) {
    return $str;
  }
  
  if (!$keep_spoilers) {
    $str = strip_tags($str);
  }
  else {
    $str = strip_tags($str, '<s>');
  }
  
  if ($len <= $length) {
    return $str;
  }
  
  $str = mb_substr($str, 0, $length);
  
  // remove truncated html entities
  $str = preg_replace('/&[^;]*$/', '', $str);
  
  if ($keep_spoilers) {
    $str = preg_replace('/<[^>]*$/', '', $str);
    
    $oc = substr_count($str, '<s>');
    
    if ($oc) {
      $cc = substr_count($str, '</s>');
      $dc = $oc - $cc;
      if ($dc > 0) {
        $str .= str_repeat('</s>', $dc);
      }
    }
  }
  
  $str .= '…';
  
  return $str;
}

function paranoid_rename( $src, $dest )
{
	$across_devices = false; //keep around for future use
	$u              = false;

	if( $across_devices ) {
		// rename to dest dir, then over dest
		$dsrc = dirname( $dest ) . "/" . basename( $src );
		if( !@rename( $src, $dsrc ) ) $u = $src;
		else if( !@rename( $dsrc, $dest ) ) $u = $dsrc;
	} else {
		if( !@rename( $src, $dest ) ) $u = $src;
	}

	if( $u )
		unlink( $u );
}

function rename_across_device( $src, $dest )
{
	// FIXME: copy() does a chmod but we don't need that
	copy($src, $dest);
	unlink($src);
}

function getmypid_cached()
{
	static $pid = -1;
	
	if ($pid === -1) $pid = getmypid();
	
	return $pid;
}

// print $contents to $filename by using a temporary file and renaming it
// may destroy $contents in the process
// (makes *.html and *.gz if USE_GZIP is on)
function print_page( $filename, &$contents, $force_nogzip = 0, $trim_whitespace = 1 )
{
	global $fwritetimer;

	$timestarted = microtime( true );

	if( NEW_HTML == 1 && $trim_whitespace ) {
		$contents = str_replace( array("\r\n", "\n", "\t"), array('', '', ''), $contents );
	}

	$gzip = ( USE_GZIP == 1 && !$force_nogzip );

	if( $gzip ) {
		$tempname = dirname( $filename )."/gztmp".getmypid_cached();
		
		// FIXME: number of syscalls done by gzwrite is not optimal (it does a small one then 4KB writes after)
		// for small files (how small?) do gzencode() and file_put_contents() instead.
		
		$fp = gzopen($tempname, "wb9");
		if( $fp === false ) return;
		gzwrite($fp, $contents);
		gzclose($fp);
		// chmod( $tempname, 0664 ); //it was created 0600
		
		paranoid_rename( $tempname, $filename . ".gz" );
	} else {
		$tempname = dirname( $filename )."/tmp".getmypid_cached();
		if( file_put_contents( $tempname, $contents ) === false ) return;
		// chmod( $tempname, 0664 ); //it was created 0600
		paranoid_rename( $tempname, $filename );
	}

	$fwritetimer += ( microtime( true ) - $timestarted );
}

function file_get_contents_cached( $filename )
{
	static $cache = array();
	if( isset( $cache[$filename] ) )
		return $cache[$filename];
	$cache[$filename] = @file_get_contents( $filename );

	return $cache[$filename];
}

function file_array_cached( $filename )
{
	static $cache = array();
	if( isset( $cache[$filename] ) )
		return $cache[$filename];
	$cache[$filename] = @file( $filename, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES );

	return $cache[$filename];
}

function get_blotter() {
  if (!SHOW_BLOTTER) {
    return '';
  }
  
  $msg_limit = 3;
  
  $blotter = <<<HTML
<table id="blotter" class="desktop"><thead><tr><td colspan="2"><hr class="aboveMidAd"></td></tr></thead><tbody id="blotter-msgs">
HTML;
  
  $query = <<<SQL
SELECT sql_cache `date`, content 
FROM blotter_messages
ORDER BY id DESC LIMIT $msg_limit
SQL;
  
  $res = mysql_global_call($query);
  
  $mtime = 0;
  
  if ($res && mysql_num_rows($res) > 0) {
    while ($row = mysql_fetch_assoc($res)) {
      if ($mtime === 0) {
        $mtime = $row['date'];
      }
      
      $blotter .= '<tr><td data-utc="' .
        $row['date'] . '" class="blotter-date">' .
        date('m/d/y', $row['date']) . '</td><td class="blotter-content">' .
        $row['content'] . '</td></tr>';
    }
  }
  else {
    return '';
  }
  
  $blotter .= '</tbody><tfoot><tr><td colspan="2">[<a data-utc="' . $mtime
    . '" id="toggleBlotter" href="#">Hide</a>]<span> [<a href="//www.'
    . L::d(BOARD_DIR)
    . '/blotter" target="_blank">Show All</a>]</span></td></tr></tfoot></table>';
  
  return $blotter;
}

function blotter_contents()
{
	static $cache;
	if( isset( $cache ) ) return $cache;
	$ret      = "";
	$topN     = 4; //how many lines to print
	$bl_lines = file( BLOTTER_PATH );
	$bl_top   = array_slice( $bl_lines, 0, $topN );
	$date     = "";
	foreach( $bl_top as $line ) {
		if( !$date ) {
			$lineparts = explode( ' - ', $line );
			if( strpos( $lineparts[0], '<font' ) !== false ) {
				$dateparts = explode( '>', $lineparts[0] );
				$date      = $dateparts[1];
				$date      = "<li><font color=\"red\">Blotter updated: $date</font>";
			} else {
				$date = $lineparts[0];
				$date = "<li>Blotter updated: $date";
			}
		}
		$line = trim( $line );
		$line = str_replace( "\\", "\\\\", $line );
		$line = str_replace( "'", "\'", $line );
		$ret .= "'<li>$line'+\n";
	}
	$ret .= "''";
	$cache = array($date, $ret);

	return array($date, $ret);
}

function find_match_and_prefix( $regex, $str, $off, &$match )
{
	if( !preg_match( $regex, $str, $m, PREG_OFFSET_CAPTURE, $off ) ) return false;

	$moff  = $m[0][1];
	$match = array(substr( $str, $off, $moff - $off ), $m[0][0]);

	return true;
}

// skip_on_spoilers will stop parsing and return the unmodified comment
// if spoiler tags are found inside the string to wrap.
// This is to avoid sjis.spoiler tags mixing mostly.
function parse_bbcode_one( $com, $tn, $st, $et, $nest_limit = 2, $skip_on_spoilers = false )
{
	if( !find_match_and_prefix( "/\[$tn\]/", $com, 0, $m ) ) return $com;

	$bracket_tn = "[$tn]";
	$bl         = strlen( $bracket_tn );
	$el         = $bl + 1;
	$ret        = $m[0] . $st;
	$lev        = 1;
	$off        = strlen( $m[0] ) + $bl;

	while( 1 ) {
		if (!find_match_and_prefix( "@\[/?$tn\]@", $com, $off, $m)) break;
		list( $txt, $tag ) = $m;
    
    if (!$skip_on_spoilers || $tag === $bracket_tn) {
      $ret .= $txt;
    }
    else if (preg_match('/\[\/?spoiler\]/', $txt)) {
      return $com;
    }
		
		$off += strlen( $txt ) + strlen( $tag );

		if( $tag == $bracket_tn ) {
			if( $lev < $nest_limit )
				$ret .= $st;
			$lev++;
		} else if( $lev ) {
			if( $lev <= $nest_limit )
				$ret .= $et;
			$lev--;
		}
	}
  
  $tail = substr($com, $off, strlen($com) - $off);
	$ret .= $tail;
	
  $lev = min( $lev, $nest_limit );
  
  if ($lev > 0) {
    if ($skip_on_spoilers && preg_match('/\[\/?spoiler\]/', $tail)) {
      return $com;
    }
    
    $ret .= str_repeat( $et, $lev );
  }
  
	return $ret;
}

function spoiler_parse( $com )
{
	return parse_bbcode_one( $com, 'spoiler', '<s>', '</s>' );
}

function jsmath_parse( $com )
{
	$com = parse_bbcode_one( $com, "math", '<span class="math">', '</span>' );
	$com = parse_bbcode_one( $com, "eqn", '<div class="math">', '</div>' );

	return $com;
}

/* BBCode for bold, italic, and r/g/b color tags */
function parse_op_markup($com) {
  $com = parse_bbcode_one($com, 'b', '<span class="mu-s">', '</span>', 1);
  $com = parse_bbcode_one($com, 'i', '<span class="mu-i">', '</span>', 1);
  
  $com = parse_bbcode_one($com, 'red', '<span class="mu-r">', '</span>', 1);
  $com = parse_bbcode_one($com, 'green', '<span class="mu-g">', '</span>', 1);
  $com = parse_bbcode_one($com, 'blue', '<span class="mu-b">', '</span>', 1);
  
  return $com;
}

function code_parse( $com )
{
	return parse_bbcode_one( $com, 'code', '<pre class="prettyprint">', '</pre>' );
}

function sjis_parse($com) {
  $skip_on_spoilers = strpos($com, '[spoiler]') !== false;
  
  return parse_bbcode_one($com, 'sjis', '<span class="sjis">', '</span>', 1, $skip_on_spoilers);
}

// convenience function for wordfilters.
// text must be html escaped
function random_color( $str, $background = 1, $foreground = 1 )
{
	$style = "";

	if( $background ) {
		$r     = rand( 0, 255 );
		$g     = rand( 0, 255 );
		$b     = rand( 0, 255 );
		$style = $style . "background: #" . sprintf( "%02x%02x%02x", $r, $g, $b ) . "; ";
	}

	if( $foreground ) {
		$r     = rand( 0, 255 );
		$g     = rand( 0, 255 );
		$b     = rand( 0, 255 );
		$style = $style . "color: #" . sprintf( "%02x%02x%02x", $r, $g, $b ) . "; ";
	}

	if( $style ) {
		return "<span style=\"$style\">$str</span>";
	}

	return $str;
}

function append_ban( $board, $ip )
{
	$cmd = "nohup /usr/local/bin/suid_run_global bin/appendban $board $ip >/dev/null 2>&1 &";
	exec( $cmd );
}

// check whether the current user can perform $action (on $no, for some actions)
// board-level access is cached in $valid_cache.
// FIXME move to lib/admin.php
function valid( $action = 'moderator', $no = 0 )
{
	static $valid_cache, $can_post_html; // the access level of the user
	$access_level = array('none' => 0, 'janitor' => 1, 'janitor_this_board' => 2, 'moderator' => 5, 'manager' => 10, 'admin' => 20);
	if( !isset( $valid_cache ) ) {
		$valid_cache = $access_level['none'];
		if( isset( $_COOKIE['4chan_auser'] ) && isset( $_COOKIE['apass'] ) ) {
			$user = mysql_real_escape_string( $_COOKIE['4chan_auser'] );
			$pass = $_COOKIE['apass'];
		}
		if( $user && $pass ) {
			$result = mysql_global_call( "SELECT allow,deny,password_expired,username,password FROM " . SQLLOGMOD . " WHERE username='$user' LIMIT 1" );
			list( $allow, $deny, $expired, $username, $password ) = mysql_fetch_row( $result );
			mysql_free_result( $result );
			
      $admin_salt = file_get_contents('/www/keys/2014_admin.salt');
      
      if (!$admin_salt) {
        die('Internal Server Error (s0)');
      }
      
      $hashed_admin_password = hash('sha256', $username . $password . $admin_salt);
    	
      if ($hashed_admin_password !== $pass) {
        return false;
      }
      
			if( $expired ) {
				error( 'Your password has expired; check IRC for instructions on changing it.' );
			}
			
			if( $allow ) {
				$allows             = explode( ',', $allow );
				$seen_janitor_token = false;
				// each token can increase the access level,
				// except that we only know that they're a moderator or a janitor for another board
				// AFTER we read all the tokens
				$cphtml = false;

				foreach( $allows as $token ) {
					if( $token == 'janitor' ) {
						$seen_janitor_token = true;
					} else if( $token == 'manager' && $valid_cache < $access_level['manager'] ) {
						$valid_cache = $access_level['manager'];
					} else if( $token == 'admin' && $valid_cache < $access_level['admin'] ) {
						$valid_cache = $access_level['admin'];
					} else if( ( $token == BOARD_DIR || $token == 'all' ) && $valid_cache < $access_level['janitor_this_board'] ) {
						$valid_cache = $access_level['janitor_this_board']; // or could be moderator, will be increased in next step
					} elseif( $token == 'html' ) {
						$cphtml = true;
					}
				}

				$can_post_html = $cphtml;
				// now we can set moderator or janitor status
				if( !$seen_janitor_token ) {
					if( $valid_cache < $access_level['moderator'] )
						$valid_cache = $access_level['moderator'];
				} else {
					if( $valid_cache < $access_level['janitor'] )
						$valid_cache = $access_level['janitor'];
				}
				if( $deny ) {
					$denies = explode( ',', $deny );
					if( in_array( BOARD_DIR, $denies ) ) {
						$valid_cache = $access_level['none'];
					}
				}
			}
		}
	}
	{
		// local rpc can do anything
		$longip = ip2long( $_SERVER['REMOTE_ADDR'] );
		if( !$longip || cidrtest( $longip, "10.0.0.0/24" ) ||
			cidrtest( $longip, "204.152.204.0/24" ) || cidrtest( $longip, "127.0.0.0/24" )
		)
			return YES;
	}
	switch( $action ) {
		case 'moderator':
			return $valid_cache >= $access_level['moderator'];
		case 'textonly':
			return $valid_cache >= $access_level['moderator'];
		case 'htmlnopw':
			return $valid_cache >= $access_level['manager'];
		case 'htmlpost':
			return $can_post_html;
		case 'janitor_board':
			return $valid_cache >= $access_level['janitor'];
		case 'delete':
			if( $valid_cache >= $access_level['janitor_this_board'] ) {
				return true;
			} // if they're a janitor on another board, check for illegal post unlock
			else if( $valid_cache >= $access_level['janitor'] ) {
				$query         = mysql_global_do( "SELECT COUNT(*) from reports WHERE board='" . BOARD_DIR . "' AND no=$no AND cat=2" );
				$illegal_count = mysql_result( $query, 0, 0 );
				mysql_free_result( $query );

				return $illegal_count >= 3;
			}
		case 'reportflood':
			return $valid_cache >= $access_level['janitor'];
		case 'floodbypass':
			return $valid_cache >= $access_level['janitor'];
		case 'rebuild':
			return $valid_cache >= $access_level['janitor'];
		case 'admin':
			return $valid_cache >= $access_level['admin'];
		default: // unsupported action
			return false;
	}
}

function iplog_add( $board, $no, $ip, $time, $is_thread, $tim, $had_image )
{
	mysql_global_call( "INSERT INTO user_actions (board,postno,ip,time,uploaded,action,had_image) VALUES ('%s',%d,%d,from_unixtime(%d),%d,'%s',%d)",$board, $no, ip2long($ip), $time, $tim, $is_thread ? "new_thread" : "new_reply", $had_image);
}

function clean_log_bool( &$row )
{
	static $bool_cols = array('sticky', 'permasage', 'closed', 'filedeleted', 'permaage', 'undead', 'archived');

	foreach ($bool_cols as $col) {
		if (!isset($row[$col])) continue; // FIXME split this function up to avoid this test
		$c = &$row[$col];
		settype($c, "bool");
		// NOTES: $c = $c ? TRUE : FALSE allocates new bools each time
		// $c = $c ? &$itrue : &$ifalse causes them to be converted to strings(!)
		// Put this back to TRUE : FALSE instead of a settype call sometime so we can look at the php bytecodes
	}
}

function clean_log_int( &$row )
{
	static $int_cols = array('no', 'w', 'h', 'tn_w', 'tn_h', 'last_modified', 'time', 'fsize', 'resto');

	// turn columns into int (does this help?)
	foreach( $int_cols as $col ) {
		if (!isset($row[$col])) continue;
		
		$c = &$row[$col];
		settype($c, "int");
	}
}

function clean_log_delreply( &$row )
{	
	if (!$row['resto'])
		return;	
	
	static $del_cols = array('sticky', 'permasage', 'closed', 'last_modified', 'root', 'undead', 'permaage');
	
	// delete fields not used for replies
	foreach( $del_cols as $col ) {
		unset($row[$col]);
	}
}

function clean_log_intern( &$row )
{
	static $intern_cols = array('name', 'ext', 'capcode', 'country');

	// Intern repeated strings that are usually the same value.
	// In PHP 6 this... doesn't seem to do anything? Let's try again in 7.
	static $log_intern;
	if( !isset( $log_intern ) ) {
		$log_intern = array();
		foreach( $intern_cols as $col )
			$log_intern[$col] = array();
	}

	foreach( $intern_cols as $col ) {
		$intern_array = &$log_intern[$col];
		$c = &$row[$col];
		
		$v = $c;
		if( !isset($intern_array[$v]) )
			$intern_array[$v] = $v;
		$c = &$intern_array[$c];
	}
}

function clean_log_row( &$row )
{
	//static $rn = 0;
	clean_log_delreply( $row );
	clean_log_bool( $row );
	clean_log_int( $row );
	//clean_log_intern( $row );
	//if (++$rn == 100) {
	//	debug_zval_dump($row);
	//}
}

function log_bad_cache_entry($no)
{
	global $log_cache_level;
	global $log;
	
	internal_error_log("logcache", "missing children for OP no $no, cache level $log_cache_level, cache contents ".count($log));
}

function invalidate_log($thread)
{
	global $log_cache_level;
	global $log;
	
	if (isset($log[$thread])) {
		if ($log[$thread]['resto']) {
			die(S_ASSERT);
		}
		unset($log[$thread]);
	}
	
	if ($log_cache_level==2)
		$log_cache_level = 1;
}

// build a structure out of all the posts in the database.
// this lets us replace a LOT of queries with a simple array access.
// it only builds the first time it was called.
// rather than calling log_cache(1) to rebuild everything,
// you should just manipulate the structure directly.
// $thread may be any postno in a thread
// without a thread, $archive_mode fetches all live threads if 0 and all archived threads if 1
function log_cache($invalidate = 0, $thread = 0, $archive_mode = 0) {
  global $log_cache_level; 
  global $log, $ipcount, $mysql_unbuffered_reads;
  
  if (!isset($log) || $invalidate) {
    $log = array();
    $log_cache_level = 0;
  }
  
  // Optimisation for index rebuilding when REPLIES_SHOWN is 0.
  // No need to fetch the entire board in this case.
  $optimised_indexes = !$thread && !$archive_mode && !REPLIES_SHOWN && IS_REBUILDD;

  // Handle cache
  // 1 = Live OPs are cached, 2 = Some threads are cached, 3 = Whole live board is cached
  if ($optimised_indexes) {
    $nlog_cache_level = 1;
  }
  else {
    $nlog_cache_level = $thread ? 2 : 3;
  }
  
  // Whole board is cached, nothing to do.
  if ($log_cache_level == 3 && $archive_mode === 0) {
    return;
  }
  
  // Thread is cached, nothing to do.
  if ($log_cache_level == 2 && isset($log[$thread])) {
    return;
  }
  
  // Live OPs are cached, nothing to do.
  if ($log_cache_level == 1 && $optimised_indexes) {
    return;
  }
  
  if ($nlog_cache_level > $log_cache_level) {
    $log_cache_level = $nlog_cache_level;
  }

  $ips = array();
  
	mysql_board_call( "SET read_buffer_size=1048576" );
	$mysql_unbuffered_reads = 1;
	
	$query_archived = false;
  
	if ($thread) {
	  if ($archive_mode === 0) {
      $where = " WHERE archived = 0 AND (resto = $thread OR no = $thread)";
	  }
	  else if ($archive_mode === 1) {
      $where = " WHERE archived = 1 AND (resto = $thread OR no = $thread)";
	  }
	  else {
      $query_archived = true;
      $where = " WHERE (archived = 0 AND resto = $thread) OR (archived = 1 AND resto = $thread) OR no = $thread";
	  }
	}
	else {
	if ($archive_mode === 1) {
        $query_archived = true;
		$where = ' WHERE archived = 1';
	}
    else if ($optimised_indexes) {
      $where = ' WHERE archived = 0 AND resto = 0';
      
      $_thread_ids = array();
    }
    else {
      $where = ' WHERE archived = 0';
    }
	}
	
	$fields = "no,sticky,permasage,closed,now,name,sub,com,host,pwd,filename,ext,w,h,tn_w,tn_h,tim,time,md5,fsize,last_modified,root,resto,filedeleted,id,capcode,country,undead,permaage,since4pass";
	
	if ($query_archived) {
	  $fields .= ",archived";
  }
	
  if (MOBILE_IMG_RESIZE) {
    $fields .= ",m_img";
  }
	
  if (ENABLE_BOARD_FLAGS) {
    $fields .= ",board_flag";
  }
  
	$sql_cache = "sql_no_cache";
	
	$query  = mysql_board_call( "SELECT $sql_cache $fields FROM `" . SQLLOG . "`" . $where );
	$offset = 0;
	
	while( $row = mysql_fetch_assoc( $query ) ) {
		if (!$query_archived) $row['archived'] = $archive_mode;
		clean_log_row( $row );
		
		$row_no    = $row['no'];
		$row_resto = $row['resto'];
		
		// IF mysql returns rows in order by default then replies come after OP
		// so if OP doesn't exist, this post is orphaned and should be skipped
		// TODO: let's not skip for now, it seems more likely to cause bugs than anything
		//if ($fetching_whole_threads && $row_resto && !isset($log[$row_resto])) continue;
		
    if ($optimised_indexes) {
      $_thread_ids[] = (int)$row_no;
    }
    		
		$ips[$row['host']] = TRUE;
		
    if (!$row_resto) {
      $row['children'] = array();
      $row['imgreplycount'] = 0;
      $row['replycount'] = 0;
    }
    else {
      if (isset($log[$row_resto])) {
        $log[$row_resto]['children'][$row_no] = TRUE;
        
        if ($row['fsize'] && !$row['filedeleted']) {
          $log[$row_resto]['imgreplycount']++;
        }
        
        $log[$row_resto]['replycount']++;
      }
    }
		
		$log[$row_no] = $row;
	}
  
	$query = null;

	$nrows = count($log);
	//if (BOARD_DIR=="b" && !$thread) quick_log_to("/www/perhost/logcaches.log", "inefficient all-board log_cache run t=$thread r=$nrows inv=$invalidate lev=$log_cache_level", true);
	// if (!STATIC_REBUILD && $thread) quick_log_to("/www/perhost/logcaches.log", "inefficient? one-thread log_cache run t=$thread r=$nrows inv=$invalidate lev=$log_cache_level", true);
  
	$is_single_thread = $thread && isset($log[$thread]['children']);
	$ipcount = count( $ips );
	unset($ips);

	$mysql_unbuffered_reads = 0;
	mysql_board_call( "SET read_buffer_size=131072" );

	if (!$thread) {
    if ($optimised_indexes) {
      if (empty($_thread_ids)) {
        return;
      }
      
      $_thread_ids = implode(',', $_thread_ids);
      
      $query = mysql_board_call("SELECT resto, COUNT(*) AS r_count, SUM(IF(fsize > 0 AND filedeleted = 0, 1, 0)) AS i_count FROM `" . SQLLOG . "` WHERE resto IN($_thread_ids) GROUP BY resto");
      
      while ($row = mysql_fetch_assoc($query)) {
        $log[$row['resto']]['imgreplycount'] = (int)$row['i_count'];
        $log[$row['resto']]['replycount'] = (int)$row['r_count'];
      }
      
      $query = mysql_board_call("SELECT no FROM `" . SQLLOG . "` WHERE no IN($_thread_ids) ORDER BY root DESC");
    }
    else {
      if ($archive_mode === 1) {
        $archived = "archived = 1";
      } else {
        $archived = "archived = 0";
      }
      $query = mysql_board_call("SELECT no FROM `" . SQLLOG . "` WHERE $archived AND resto = 0 AND root > 0 ORDER BY root DESC");
    }
    
    $threads = array(); // IDs
    
    while ($row = mysql_fetch_row($query)) {
      $no = (int)$row[0];
      
      if (isset($log[$no])) {
        $threads[] = $no;
      }
    }
    
    $log['THREADS'] = $threads;
  
    foreach( $threads as $thread ) {
      $this_thread = $log[$thread];
      
      if (!$this_thread['permaage'] && !$this_thread['sticky'] && $this_thread['replycount'] >= MAX_RES) {
        $log[$thread]['bumplimit'] = TRUE;
      }
      else {
        $log[$thread]['bumplimit'] = FALSE;
      }
      
      if ($this_thread['archived']) {
        $log[$thread]['archived_on'] = strtotime($this_thread['root']);
      }
      
      if (!$this_thread['permaage'] && !$this_thread['sticky'] && !$log[$thread]['undead'] && $this_thread['imgreplycount'] >= MAX_IMGRES) {
        $log[$thread]['imagelimit'] = TRUE;
      }
      else {
        $log[$thread]['imagelimit'] = FALSE;
      }
      
      $log[$thread]['semantic_url'] = generate_href_context($this_thread['sub'], $this_thread['com']);
    }
  }
  else if ($is_single_thread) {
    if (!$log[$thread]['permaage'] && !$log[$thread]['sticky'] && $log[$thread]['replycount'] >= MAX_RES) {
      $log[$thread]['bumplimit'] = TRUE;
    }
    else {
      $log[$thread]['bumplimit'] = FALSE;
    }
    
    if ($log[$thread]['archived']) {
      $log[$thread]['archived_on'] = strtotime($log[$thread]['root']);
    }
    
    if (!$log[$thread]['permaage'] && !$log[$thread]['sticky'] && !$log[$thread]['undead'] && $log[$thread]['imgreplycount'] >= MAX_IMGRES) {
      $log[$thread]['imagelimit'] = TRUE;
    }
    else {
      $log[$thread]['imagelimit'] = FALSE;
    }
    
    $log[$thread]['semantic_url'] = generate_href_context($log[$thread]['sub'], $log[$thread]['com']);
  }

	// calculate old-status for PAGE_MAX mode
	//$threadcount = count( $threads );

	/*if(EXPIRE_NEGLECTED != 1) {
		rsort($threads, SORT_NUMERIC);

		if(PAGE_MAX > 0) // the lowest 5% of maximum threads get marked old
			for($i = floor(0.95*PAGE_MAX*DEF_PAGES); $i < $threadcount; $i++) {
				if(!$log[$threads[$i]]['sticky'])
					$log[$threads[$i]]['old'] = 1;
			}
		else { // threads w/numbers below 5% of LOG_MAX get marked old
			foreach($threads as $thread) {
				if($lastno-LOG_MAX*0.95>$thread)
					if(!$log[$thread]['sticky'])
						$log[$thread]['old'] = 1;
			}
		}
	} else {
		$rthreads = array();
		foreach ($threads as $t) {
			$root = $log[$t]['root'];
			$rthreads[$t] = $root;
		}
		
		arsort($rthreads);
		$rthreads = array_keys($rthreads);
		
		if (PAGE_MAX > 0) {
			$floor = (int)floor(0.95*PAGE_MAX*DEF_PAGES);
			for($i = $floor; $i < $threadcount; $i++) {
				if(!$log[$rthreads[$i]]['sticky'])
					$log[$rthreads[$i]]['old'] = 1;
			}
		}
	}*/
}

function rebuildallthumb($archiveonly = false)
{
	global $log;
	if( !has_level() ) return;
	$starttime = microtime( true );
	set_time_limit( 0 );
	if (!$archiveonly) {
		log_cache();
		$nposts = count($log);
		echo "Rebuilding $nposts live posts<br>\n";

		foreach( $log as $post ) {
			if( !$post["ext"] ) continue;

			$ext   = $post["ext"];
			$tim   = $post["tim"];
			$resto = $post["resto"];
			$fname = IMG_DIR . $tim . $ext;
			make_thumb( $fname, $tim, $ext, $resto, $TN_W, $TN_H, $tmd5 );
		}
		$totaltime = microtime( true ) - $starttime;
		echo "Took $totaltime seconds for live posts<br>\n";
	}
	
	// Run again for archived thumbs
	$starttime = microtime( true );
	mysql_check_connections();
	log_cache(1, 0, 1); // fetch archives instead
	$nposts = count($log);
	echo "Rebuilding $nposts archived posts<br>\n";
	
	foreach( $log as $post ) {
		if( !$post["ext"] ) continue;

		$ext   = $post["ext"];
		$tim   = $post["tim"];
		$resto = $post["resto"];
		$fname = IMG_DIR . $tim . $ext;
		make_thumb( $fname, $tim, $ext, $resto, $TN_W, $TN_H, $tmd5 );
	}
	$totaltime = microtime( true ) - $starttime;
	echo "Took $totaltime seconds for archived posts<br>\n";
}

/**
 * Displays some text on a lightweight page styled using the default stylesheet.
 * $message should be html-escaped
 */
function headless_message($message, $autoclose = false) {
  if (DEFAULT_BURICHAN) {
    $css = 'yotsubluenew';
    $ws = 'ws';
  }
  else {
    $css = 'yotsubanew';
    $ws = '';
  }
  
  $css_ver = TEST_BOARD ? CSS_VERSION_TEST : CSS_VERSION;
  
  if ($error) {
    $err_css = 'color: red;';
  }
  else {
    $err_css = '';
  }
  
  if ($autoclose) {
    $js = <<<JS
<script>setTimeout(function() { self.close(); }, 3000);</script>
JS;
  }
  else {
    $js = '';
  }
  
  $html = <<<HTML
<!DOCTYPE html><html><head>
<meta http-equiv="content-type" content="text/html; charset=UTF-8">
<meta http-equiv="pragma" content="no-cache">
<link rel="icon" type="image/x-icon" href="//s.4cdn.org/image/favicon-team$ws.ico">
<link rel="stylesheet" style="text/css" href="//s.4cdn.org/css/$css.$css_ver.css">
</head>
<body>
<table style="text-align: center; width: 100%; height: 150px; border: 0;">
  <tr valign="middle">
    <td align="center" style="font-size: x-large; font-weight: bold; border: 0;$err_css">
      <span>$message</span>
    </td>
  </tr>
</table>$js
</body>
</html>
HTML;
  
  return $html;
}

function do_move_thread() {
  if (!isset($_POST['id']) || !isset($_POST['board'])) {
    updating_index();
  }
  
  if (!has_level()) {
    updating_index();
  }
  
  $del = isset($_POST['move_del']) && $_POST['move_del'];
  
  $ret = move_thread($_POST['id'], $_POST['board'], $del);
  
  if (is_array($ret)) {
    $message = 'Thread moved to <a target="_blank" href="//boards.'
      . L::d($ret[0]) . '/' . $ret[0] . '/thread/' . $ret[1]
      . '">/' . $ret[0] . '/' . $ret[1] . '</a>';
    
    echo headless_message($message, true);
  }
  else {
    echo headless_message("<span style=\"color:red\">$ret</span>");
  }
}

function do_copy_threads() {
  if (!has_level()) {
    updating_index();
    return;
  }
  global $argv;
  
  $to_board = $_REQUEST["board"];
  if (!$to_board) $to_board = $argv[2];

  if (!$to_board) {
    updating_index();
    return;
  }

  set_time_limit( 0 );
  header( "Pragma: no-cache" );
  _print(fancystyle());

  if (UPLOAD_BOARD || JANITOR_BOARD) {
    $ret = "The current board doesn't support this feature.";
  } else if ($to_board === 'f' || $to_board === 'j') {
    $ret = "The destination board doesn't support this feature.";
  } else {
	$threads = mysql_column_array(mysql_board_call("SELECT no FROM `%s` WHERE resto = 0 AND archived = 0", BOARD_DIR));
	foreach ($threads as $thread) {$ret = copy_thread($thread, $to_board); _print("$thread<br>\n");}
  }
  
  if (is_array($ret)) {
    $message = 'Thread copied to <a target="_blank" href="//boards.'
      . L::d($ret[0]) . '/' . $ret[0] . '/thread/' . $ret[1]
      . '">/' . $ret[0] . '/' . $ret[1] . '</a>';
    
    echo headless_message($message, true);
  }
  else {
    echo headless_message("<span style=\"color:red\">$ret</span>");
  }
}

function copy_thread($thread_id, $to_board, $delete = false) {
  $thread_id = (int)$thread_id;
  
  if (!$thread_id) {
    return "Invalid thread ID.";
  }
  
  // Validate destination board
  if (!preg_match('/^[a-z0-9]+$/', $to_board)) {
    return 'Invalid destination board.';
  }
  
  $query = "SELECT COUNT(*) FROM boardlist WHERE dir = '%s' LIMIT 1";
  
  $res = mysql_global_call($query, $to_board);
  
  if (!$res) {
    return "Database Error (1)";
  }
  
  if (mysql_num_rows($res) < 1) {
    return "Destination board doesn't exist.";
  }
  
  // ---
    
  // Fetch the whole thread
  $posts = array();
  
  $res = mysql_board_call("SELECT * FROM `%s` WHERE no = %d AND resto = 0", BOARD_DIR, $thread_id);
  if (!$res) {
    return "Database Error (3)";
  }
  
  $row = mysql_fetch_assoc($res);
  if (!$row) {
    return "Thread not found.";
  }
  
  $posts[] = $row;
  $res = mysql_board_call("SELECT * FROM `%s` WHERE resto = %d", BOARD_DIR, $thread_id);
  if (!$res) {
    return "Database Error (4)";
  }
  
  while ($row = mysql_fetch_assoc($res)) {
    if (!$row) {
      continue;
    }
    $posts[] = $row;
  }
  
  // Copy posts to the other board
  mysql_board_call('START TRANSACTION');
  
  $new_resto = 0;
  
  $from_pids = array();
  $to_pids = array();
  
  foreach ($posts as &$post) {
    $comment = str_replace($from_pids, $to_pids, $post['com']);
    
    if ($new_resto === 0) {
      $root_time = $post['root'];
    }
    else {
      $root_time = 0;
    }
    
    $query = "INSERT INTO `$to_board`(now,name,sub,com,host,pwd,filename,ext,w,
h,tn_w,tn_h, tim,time,last_modified,md5,fsize,root,resto,capcode,
4pass_id,since4pass,filedeleted,tmd5,id,sticky,closed,country)
VALUE (" .
    "'" . $post['now'] . "'," .
    "'" . mysql_real_escape_string($post['name']) . "'," .
    "'" . mysql_real_escape_string($post['sub']) . "'," .
    "'" . mysql_real_escape_string($comment) . "'," .
    "'" . mysql_real_escape_string($post['host']) . "'," .
    "'" . mysql_real_escape_string($post['pwd']) . "'," .
    "'" . mysql_real_escape_string($post['filename']) . "'," .
    "'" . $post['ext'] . "'," .
    (int)$post['w'] . "," .
    (int)$post['h'] . "," .
    (int)$post['tn_w'] . "," .
    (int)$post['tn_h'] . "," .
    "'" . $post['tim'] . "'," .
    (int)$post['time'] . "," .
    (int)$post['time'] . "," .
    "'" . $post['md5'] . "'," .
    (int)$post['fsize'] . "," .
    "'" . $root_time . "'," .
    $new_resto . "," .
    "'" . $post['capcode'] . "'," .
    "'" . $post['4pass_id'] . "'," .
    (int)$post['since4pass'] . "," .
    (int)$post['filedeleted'] . "," .
    "'" . $post['tmd5'] . "'," .
    "'" . mysql_real_escape_string($post['id']) . "'," .
    (int)$post['sticky'] . "," .
    (int)$post['closed'] . "," .
    "'XX')";
    
    $res = mysql_board_call($query);
    if (!$res) {
      if ($new_resto === 0) {
        mysql_board_call('ROLLBACK');
        return 'Database Error (5)';
      }
      
      $post['ext'] = null;
      
      continue;
    }
    
    $new_pid = mysql_board_insert_id();
    
    if ($new_resto === 0) {
      $new_resto = $new_pid;
    }
    
    $from_pids[] = "&gt;&gt;{$post['no']}";
    $to_pids[] = "&gt;&gt;$new_pid";
    
    $post['new_id'] = $new_pid;
  }
  
  unset($post);
  
  mysql_board_call('COMMIT');
  
  // Copy files
  // If the file already exists, update the database and set it as "deleted"
  $to_img_dir = preg_replace('/' . BOARD_DIR . '\/$/', '', IMG_ROOT) . $to_board . '/';
  $to_thumb_dir = preg_replace('/' . BOARD_DIR . '\/$/', '', THUMB_ROOT) . $to_board . '/';
  
  $dup_pids = array();
  
  foreach ($posts as $post) {
    if (!$post['ext'] || $post['filedeleted']) {
      continue;
    }
    
    $src_thumb = THUMB_DIR . $post['tim'] . 's.jpg';
    $dest_thumb = $to_thumb_dir . $post['tim'] . 's.jpg';
    
    if (file_exists($dest_thumb)) {
      //$dup_pids[] = $post['new_id'];
      continue;
    }
    
    $src_img = IMG_DIR . $post['tim'] . $post['ext'];
    $dest_img = $to_img_dir . $post['tim'] . $post['ext'];
    
    @copy($src_thumb, $dest_thumb);
    @copy($src_img, $dest_img);
  }
  
  if (!empty($dup_pids)) {
    $dup_clause = implode(',', $dup_pids);
    $query = "UPDATE `$to_board` SET filedeleted = 1, ext = '' WHERE no IN($dup_clause)";
    $res = mysql_board_call($query);
  }
  
  // Ask the destination board to build the new thread
  remote_rebuild_live_thread($to_board, $new_resto);
  
  // Rebuild indexes
  if (!STATIC_REBUILD) {
    updatelog(0, 0);
  }
  
  return array($to_board, $new_resto);
}

function move_thread($thread_id, $to_board, $delete = false) {
  if (UPLOAD_BOARD || BOARD_DIR === 'b' || JANITOR_BOARD) {
    return "The current board doesn't support this feature.";
  }
  
  if ($to_board === 'f' || $to_board === 'j') {
    return "The destination board doesn't support this feature.";
  }
  
  if ($to_board === BOARD_DIR) {
    return "Invalid destination board.";
  }
  
  $thread_id = (int)$thread_id;
  
  if (!$thread_id) {
    return "Invalid thread ID.";
  }
  
  // Validate destination board
  if (!preg_match('/^[a-z0-9]+$/', $to_board)) {
    return 'Invalid destination board.';
  }
  
  $query = "SELECT COUNT(*) FROM boardlist WHERE dir = '%s' LIMIT 1";
  
  $res = mysql_global_call($query, $to_board);
  
  if (!$res) {
    return "Database Error (1)";
  }
  
  if (mysql_num_rows($res) < 1) {
    return "Destination board doesn't exist.";
  }
  
  // ---
  
  $board = mysql_real_escape_string(BOARD_DIR);
  
  // Lock the thread immediately
  $query = "UPDATE `$board` SET closed = 1 WHERE no = $thread_id";
  
  $res = mysql_board_call($query);
  
  if (!$res) {
    return "Database Error (2)";
  }
  
  if (mysql_affected_rows() !== 1) {
    return "This thread is locked.";
  }
  
  // Fetch the whole thread
  $posts = array();
  
  $query = "SELECT * FROM `$board` WHERE no = $thread_id AND resto = 0";
  
  $res = mysql_board_call($query);
  
  if (!$res) {
    return "Database Error (3)";
  }
  
  $row = mysql_fetch_assoc($res);
  
  if (!$row) {
    return "Thread not found.";
  }
  
  if ($row['archived'] !== '0') {
    return "You cannot move archived threads.";
  }
  
  $posts[] = $row;
  
  $query = "SELECT * FROM `$board` WHERE resto = $thread_id";
  
  $res = mysql_board_call($query);
  
  if (!$res) {
    return "Database Error (4)";
  }
  
  while ($row = mysql_fetch_assoc($res)) {
    if (!$row) {
      continue;
    }
    $posts[] = $row;
  }
  
  // Copy posts to the other board
  mysql_board_call('START TRANSACTION');
  
  $new_resto = 0;
  
  $from_pids = array();
  $to_pids = array();
  
  foreach ($posts as &$post) {
    $comment = str_replace($from_pids, $to_pids, $post['com']);
    
    if ($new_resto === 0) {
      $root_time = 'NOW()';
    }
    else {
      $root_time = 0;
    }
    
    if (SHOW_COUNTRY_FLAGS && $post['board_flag'] == '') {
      $flag_val = $post['country'];
    }
    else {
      $flag_val = 'XX';
    }
    
    $query = "INSERT INTO `$to_board`(now,name,sub,com,host,pwd,email,filename,ext,w,
h,tn_w,tn_h, tim,time,last_modified,md5,fsize,root,resto,capcode,
4pass_id,since4pass,filedeleted,tmd5,id,country)
VALUE (" .
    "'" . $post['now'] . "'," .
    "'" . mysql_real_escape_string($post['name']) . "'," .
    "'" . mysql_real_escape_string($post['sub']) . "'," .
    "'" . mysql_real_escape_string($comment) . "'," .
    "'" . mysql_real_escape_string($post['host']) . "'," .
    "'" . mysql_real_escape_string($post['pwd']) . "'," .
    "'" . mysql_real_escape_string($post['email']) . "'," .
    "'" . mysql_real_escape_string($post['filename']) . "'," .
    "'" . $post['ext'] . "'," .
    (int)$post['w'] . "," .
    (int)$post['h'] . "," .
    (int)$post['tn_w'] . "," .
    (int)$post['tn_h'] . "," .
    "'" . $post['tim'] . "'," .
    (int)$post['time'] . "," .
    (int)$post['time'] . "," .
    "'" . $post['md5'] . "'," .
    (int)$post['fsize'] . "," .
    $root_time . "," .
    $new_resto . "," .
    "'" . $post['capcode'] . "'," .
    "'" . $post['4pass_id'] . "'," .
    (int)$post['since4pass'] . "," .
    (int)$post['filedeleted'] . "," .
    "'" . $post['tmd5'] . "'," .
    "''," .
    "'" . $flag_val . "')";
    
    $res = mysql_board_call($query);
    
    if (!$res) {
      if ($new_resto === 0) {
        mysql_board_call('ROLLBACK');
        return 'Database Error (5)';
      }
      
      $post['ext'] = null;
      
      continue;
    }
    
    $new_pid = mysql_board_insert_id();
    
    if ($new_resto === 0) {
      $new_resto = $new_pid;
    }
    
    $from_pids[] = "&gt;&gt;{$post['no']}";
    $to_pids[] = "&gt;&gt;$new_pid";
    
    $post['new_id'] = $new_pid;
  }
  
  unset($post);
  
  mysql_board_call('COMMIT');
  
  // Log the action
  $thread = $posts[0];
  
  $log_com = "<b>From /$board/$thread_id to /$to_board/$new_resto</b>";
  
  if ($thread['com'] !== '') {
    $log_com = $thread['com'] . '<br><br>' . $log_com;
  }
  
  $action_log_post = array(
    'no' => $thread['no'],
    'name' => $thread['name'],
    'sub' => $thread['sub'],
    'com' => $log_com,
    'filename' => $thread['filename'],
    'ext' => $thread['ext']
  );
  
  log_mod_action(6, $action_log_post);
  
  // Copy files
  // If the file already exists, update the database and set it as "deleted"
  $to_img_dir = preg_replace('/' . BOARD_DIR . '\/$/', '', IMG_ROOT) . $to_board . '/';
  $to_thumb_dir = preg_replace('/' . BOARD_DIR . '\/$/', '', THUMB_ROOT) . $to_board . '/';
  
  $dup_pids = array();
  
  foreach ($posts as $post) {
    if (!$post['ext'] || $post['filedeleted']) {
      continue;
    }
    
    $src_thumb = THUMB_DIR . $post['tim'] . 's.jpg';
    $dest_thumb = $to_thumb_dir . $post['tim'] . 's.jpg';
    
    if (file_exists($dest_thumb)) {
      $dup_pids[] = $post['new_id'];
      continue;
    }
    
    $src_img = IMG_DIR . $post['tim'] . $post['ext'];
    $dest_img = $to_img_dir . $post['tim'] . $post['ext'];
    
    copy($src_thumb, $dest_thumb);
    copy($src_img, $dest_img);
  }
  
  if (!empty($dup_pids)) {
    $dup_clause = implode(',', $dup_pids);
    $query = "UPDATE `$to_board` SET filedeleted = 1, ext = '' WHERE no IN($dup_clause)";
    $res = mysql_board_call($query);
  }
  
  // Insert notification post if deletion is not requested
  if (!$delete) {
    $msg = sprintf(S_THREAD_MOVED, "&gt;&gt;&gt;/$to_board/$new_resto");
    
    $post_time = $_SERVER['REQUEST_TIME'];
    $tim = generate_tim();
    
    $query = "INSERT INTO `$board`(now,name,sub,com,host,pwd,filename,ext,w,
h,tn_w,tn_h, tim,time,last_modified,md5,fsize,resto,capcode,
4pass_id,tmd5,id)
VALUE (" .
  "'" . date('m/d/y(D)H:i:s', $post_time) . "'," .
  "'" . S_ANONAME . "'," .
  "''," .
  "'" . mysql_real_escape_string($msg) . "'," .
  "'', '', '', '', 0, 0, 0, 0, '" . $tim . "'," . $post_time . "," .
  $post_time . ", '',0," . $thread_id . ", 'mod', '', '', '')";
  
    $res = mysql_board_call($query);
  }
  
  // Ask the destination board to build the new thread
  remote_rebuild_live_thread($to_board, $new_resto);
  
  // Delete source thread if deletion is requested
  if ($delete) {
    // id, pwd, imgonly, auto, die
    delete_post($thread_id, 'trim', 0, 2, 1, 0);
  }
  // Archive source thread if archiving is enabled
  else if (ENABLE_ARCHIVE) {
    archive_thread($thread_id);
    
    if (!STATIC_REBUILD && ENABLE_JSON_THREADS) {
      generate_board_archived_json();
    }
  }
  // Rebuild source thread and indexes if archiving is disabled
  else {
    updatelog($thread_id, 1);
  }
  
  // Rebuild indexes
  if (!STATIC_REBUILD) {
    updatelog(0, 0);
  }
  
  return array($to_board, $new_resto);
}

function remote_rebuild_live_thread($board, $pid) {
  $post = array(
    'mode' => 'rebuildadmin',
    'no' => $pid
  );
  
  rpc_start_request("https://sys.int/$board/imgboard.php", $post, $_COOKIE, true);
  
  return true;
}

function archive_thread($thread_id) {
  global $log;
  
  $thread_id = (int)$thread_id;
  
  $board = mysql_real_escape_string(BOARD_DIR);
  
  if (!$thread_id) {
    return;
  }
  
  // Regenerate the user ID before clearing the IP
  $uid = '';
  
  if (DISP_ID && DISP_ID_PER_THREAD && !DISP_ID_RANDOM) {
    $th = null;
    
    if (!IS_REBUILDD && isset($log[$thread_id])) {
      $th = $log[$thread_id];
    }
    else {
      $query = 'SELECT id, host FROM `' . BOARD_DIR . "` WHERE no = $thread_id";
      
      $res = mysql_board_call($query);
      
      if ($res) {
        $th = mysql_fetch_assoc($res);
      }
    }
    
    if ($th && $th['id'] !== '' && $th['host']) {
      $uid = generate_uid($thread_id, $_SERVER['REQUEST_TIME'], $th['host']);
      $uid = ", id = '" . mysql_real_escape_string($uid) . "'";
    }
  }
  
  // Update the OP. "root" is used for archive pruning.
  $query = <<<SQL
UPDATE `$board`
SET archived = 1, closed = 1, sticky = 0, email = '', host = '', 4pass_id = '', pwd = '', root = NOW()$uid
WHERE no = $thread_id
LIMIT 1
SQL;
  
  $res = mysql_board_call($query);
  
  if (!$res) {
    return;
  }
  
  // Update replies
  $query = <<<SQL
UPDATE `$board`
SET archived = 1, email = '', host = '', 4pass_id = '', pwd = ''
WHERE resto = $thread_id
SQL;
  
  $res = mysql_board_call($query);
  
  // Update cached $log
  if (isset($log[$thread_id])) {
    $log[$thread_id]['archived'] = true;
    $log[$thread_id]['archived_on'] = time();
    
    $thread_key = array_search($thread_id, $log['THREADS']);
    
    if ($thread_key !== false) {
      unset($log['THREADS'][$thread_key]);
    }
  }
  
  // Rebuild the thread
  rebuild_archived_thread($thread_id);
  
  /**
   * Clear reports (only posts with less than 3 "illegal" reports)
   */
  // Get all post ids
  $query = "SELECT no FROM `$board` WHERE no = $thread_id OR resto = $thread_id";
  
  $res = mysql_board_call($query);
  
  if (!$res || !mysql_num_rows($res)) {
    return;
  }
  
  // Get ids of reported posts, with less than 3 "illegal" reports
  $post_ids = array();
  
  while ($row = mysql_fetch_row($res)) {
    $post_ids[] = $row[0];
  }
  
  $in_clause_all = implode(',', $post_ids);
  
  $query = <<<SQL
SELECT postid FROM reports_for_posts
WHERE board = '$board' AND num_illegal < 3 AND postid IN($in_clause_all)
SQL;
  
  $res = mysql_global_call($query);
  
  if (!$res || !mysql_num_rows($res)) {
    return;
  }
  
  // Delete reports for posts with less than 3 "illegal" reports
  $post_ids = array();
  
  while ($row = mysql_fetch_row($res)) {
    $post_ids[] = $row[0];
  }
  
  $in_clause_reports = implode(',', $post_ids);
  
  $query = "DELETE FROM reports WHERE board = '$board' AND no IN($in_clause_reports)";
  mysql_global_call($query);
  
  $query = "DELETE FROM reports_for_posts WHERE board = '$board' AND postid IN($in_clause_reports)";
  mysql_global_call($query);
  
  // Handle XFF entries
  if (SAVE_XFF) {
    $query = "UPDATE xff SET is_live = 0 WHERE board = '$board' AND postno IN($in_clause_all)";
    mysql_global_call($query);
  }
}

function thumb_url()
{
	return "//" . THUMB_DIR2_PART;
}

function display_no( $no )
{
	if (FAKE_DOUBLES) {
		$last_digit = $no % 10;
		return $no.$last_digit;
	}

	return $no;
}

function display_uid( $id, $capcode = '' )
{
	if( DISP_ID == 0 || !$id ) return "";
	$normid = $id; // preg_replace( "#[^A-Za-z0-9]#", "_", $id );


	if( $id == "Mod" ) {
		$id = '<span style="color: #800080; font-weight: bold;" class="posteruid id_mod">Mod</span>' . $capcode;
	} else if( $id == "Admin" ) {
		$id = '<span style="color: #F00000; font-weight: bold;" class="posteruid id_admin">Admin</span>' . $capcode;
	} else if( $id == 'Developer' ) {
		$id = '<span style="color: #0000F0; font-weight: bold;" class="posteruid id_developer">Developer</span>' . $capcode;
	} else if( $id == 'Manager' ) {
		$id = '<span style="color: #FF0080; font-weight: bold;" class="posteruid id_manager">Manager</span>' . $capcode;
	} else {
		$id = htmlspecialchars( $id );
	}

	return " <span class=\"posteruid id_$normid\">(ID: <span class=\"hand\" title=\"Highlight posts by this ID\">$id</span>)</span>";
}

function emailencode( $str )
{
	return str_replace( "%40", "@", rawurlencode( $str ) );
}

function renderPostHtml($no, $in_thread, $sorted_replies = null, $reply_count = null, $shown_replies = null, $is_archived = false) {
	global $log, $board_flags_array;
	
	extract($log[$no]);
	
	$namestyle = '';

	if( JANITOR_BOARD == 1 ) {
		$namestyle = broomcloset_style( $name );
		$name = broomcloset_name( $name );
	}
	
	$mname = $name;
	$mname_truncated = '';
	if( $capcode == 'none' && mb_strlen( $name ) > 30 ) {
		$mname = explode( '</span>', $name, 2 );
		if( mb_strlen( $mname[0] ) > 30 ) {
			$mname[0] = htmlspecialchars_decode($mname[0], ENT_QUOTES);
			$mname[0] = mb_substr( $mname[0], 0, 30 ) . '(...)';
			$mname[0] = htmlspecialchars($mname[0], ENT_QUOTES);
		  $mname_truncated = ' data-tip data-tip-cb="mShowFull"';
		}
		
		$mname = implode( '</span>', $mname );
	}
	
	$hasCapcode = $capcode === 'none' ? '' : ' capcode';
  
	// NEW CAPCODE STUFF
	switch( $capcode ) {
		case 'admin':
			$capcodeStart  = ' <strong class="capcode hand id_admin" title="Highlight posts by Administrators">## Admin</strong>';
			$capcode_class = ' capcodeAdmin';

			$capcode   = ' <img src="' . STATIC_IMG_DIR2 . 'adminicon.gif" alt="Admin Icon" title="This user is a 4chan Administrator." class="identityIcon retina">';
			$highlight = '';
			break;

		case 'founder':
			$capcodeStart  = ' <strong class="capcode hand" title="Highlight posts by the Founder">## Founder</strong>';
			$capcode_class = ' capcodeFounder';

			$capcode   = ' <img src="' . STATIC_IMG_DIR2 . 'foundericon.gif" alt="Founder Icon" title="This user is 4chan\'s Founder." class="identityIcon retina">';
			$highlight = '';
			break;

		case 'admin_highlight':
			$capcodeStart  = ' <strong class="capcode hand id_admin" title="Highlight posts by Administrators">## Admin</strong>';
			$capcode_class = ' capcodeAdmin';

			$capcode   = ' <img src="' . STATIC_IMG_DIR2 . 'adminicon.gif" alt="Admin Icon" title="This user is a 4chan Administrator." class="identityIcon retina">';
			$highlight = ' highlightPost';
			break;

		case 'mod':
			$capcodeStart  = ' <strong class="capcode hand id_mod" title="Highlight posts by Moderators">## Mod</strong>';
			$capcode_class = ' capcodeMod';

			$capcode   = ' <img src="' . STATIC_IMG_DIR2 . 'modicon.gif" alt="Mod Icon" title="This user is a 4chan Moderator." class="identityIcon retina">';
			$highlight = '';
			break;

		case 'developer':
			$capcodeStart  = ' <strong class="capcode hand id_developer" title="Highlight posts by Developers">## Developer</strong>';
			$capcode_class = ' capcodeDeveloper';

			$capcode   = ' <img src="' . STATIC_IMG_DIR2 . 'developericon.gif" alt="Developer Icon" title="This user is a 4chan Developer." class="identityIcon retina">';
			$highlight = '';
			break;
		
		case 'manager':
			$capcodeStart  = ' <strong class="capcode hand id_manager" title="Highlight posts by Managers">## Manager</strong>';
			$capcode_class = ' capcodeManager';

			$capcode   = ' <img src="' . STATIC_IMG_DIR2 . 'managericon.gif" alt="Manager Icon" title="This user is a 4chan Manager." class="identityIcon retina">';
			$highlight = '';
			break;
		
		case 'verified':
			$capcodeStart  = ' <strong class="capcode hand id_verified" title="Highlight posts by Verified Users">## Verified</strong>';
			$capcode_class = ' capcodeVerified';

			$capcode   = '';
			$highlight = '';
			break;

		default:
			$capcode = $capcodeStart = $highlight = $capcode_class = '';
			break;
	}

	$spoiler = 0;
	
	if( strpos( $sub, 'SPOILER<>' ) === 0 ) {
		$sub     = substr( $sub, strlen( 'SPOILER<>' ) );
		$spoiler = 1;
	}
	
  // Only OPs have subjects
  if ($sorted_replies !== null) {
    $subshortm = $sub;
    if( mb_strlen( $sub ) > 30 ) {
      $sub    = str_replace( array('&#44;'), ',', $sub );
      $cutsub = htmlspecialchars_decode( $sub, ENT_QUOTES );
      $cutsub = mb_substr( $cutsub, 0, 30 );
      $cutsub = htmlspecialchars( $cutsub, ENT_QUOTES );
      
      $subshortm = '<span data-tip data-tip-cb="mShowFull">' . $cutsub . '(...)</span>';
    }
    
    $subshortm = '<span class="subject">' . $subshortm . '</span> ';
		$sub = '<span class="subject">' . $sub . '</span> ';
  }
  else {
    $sub = $subshortm = '';
  }

	$com = auto_link( $com, $in_thread );

	if( !$in_thread ) {
		list( $com, $abbreviated ) = abbreviate( $com, MAX_LINES_SHOWN );
	}

	if( isset( $abbreviated ) && $abbreviated ) {
		$com .= '<br><br><span class="abbr">Comment too long. <a href="' . RES_DIR2 . ( $resto ? $resto : $no ) . PHP_EXT2 . '#p' . $no . '">Click here</a> to view the full text.</span>';
	}

	// Image tag creation
	$file = '';
	if( $ext ) {
		$img        = IMG_DIR . $tim . $ext;
		$displaysrc = IMG_DIR2 . $tim . $ext;
    
		//if ($ext !== ".swf" && BOARD_DIR !== 'j') {
			//if ($no % 100 >= 74) {
				//$displaysrc = "//is2.4chan.org/" . BOARD_DIR . "/" . $tim . $ext;
			//}
		//}
    
		$linksrc    = ( ( USE_SRC_CGI == 1 ) ? ( str_replace( '.cgi', '', IMG_DIR2 ) . $tim . $ext ) : $displaysrc );
		
		if( defined( 'INTERSTITIAL_LINK' ) ) {
			$linksrc = str_replace( INTERSTITIAL_LINK, '', $linksrc );
		}
		
		// Original filename truncation
		$unescaped_filename = htmlspecialchars_decode($filename, ENT_QUOTES);
		if( mb_strlen( $unescaped_filename, 'UTF-8' ) > 30 ) {
			$shortname = mb_substr($unescaped_filename, 0, 25, 'UTF-8');
			$shortname = htmlspecialchars($shortname, ENT_QUOTES). '(...)' . $ext;
			$longname = $filename . $ext;
			$need_file_tooltip = true;
		}
		else {
			$shortname = $longname = $filename . $ext;
			$need_file_tooltip = false;
		}
		
		if( THREAD_AD == 1 ) {
			if( defined( 'THREAD_AD_TXT' ) && THREAD_AD_TXT ) {
				$ad = text_link_ad( THREAD_AD_TXT );
				if( $ad )
					$dat .= "<span class=\"filesize\">" . S_ADNAME . " : $ad</span><br>";
			}
		}
		
		$s_src = IMG_DIR . $tim . $ext;
		
		// 32>24 byte ascii>base64 conversion
		$shortmd5 = base64_encode( pack( 'H*', $md5 ) );
		if( $fsize >= 1048576 ) {
			$size = round( ( $fsize / 1048576 ), 2 ) . ' M';
		}
		elseif( $fsize >= 1024 ) {
			$size = round( $fsize / 1024 ) . ' K';
		}
		else {
			$size = $fsize . ' ';
		}
		
		$ftype     = strtoupper( substr( $ext, 1 ) );
		$mFileInfo = '<div data-tip data-tip-cb="mShowFull" class="mFileInfo mobile">' . $size . 'B ' . $ftype . '</div>';
		
		if( !$tn_w && !$tn_h && $ext == '.gif' ) {
			$tn_w = $w;
			$tn_h = $h;
		}
		
		$class = '';
		if( $spoiler ) {
			$class = ' imgspoiler';
			// Replace 3 image tags with one, makes it easier to change in the future
			$imgthumb_src = SPOILER_THUMB;
			$tn_w         = '100';
			$tn_h         = '100';
		}
		else {
			//$imgthumb_src = thumb_url() . $tim . 's.jpg';
			$imgthumb_src = '//' . THUMB_DIR2_PART . $tim . 's.jpg';
		}
		
    if (MOBILE_IMG_RESIZE && $m_img) {
      $m_img_attr = ' data-m';
    }
    else {
      $m_img_attr = '';
    }
		
    $imgsrc = '<a class="fileThumb' . $class . '" href="' . $displaysrc . '" target="_blank"' . $m_img_attr . '><img src="' . $imgthumb_src . '" alt="' . $size . 'B" data-md5="' . $shortmd5 . '" style="height: ' . $tn_h . 'px; width: ' . $tn_w . 'px;" loading="lazy">' . $mFileInfo . '</a>';
		
		if( $filedeleted ) {
			$fileinfo = '<span class="fileThumb"><img src="' . STATIC_IMG_DIR2 . 'filedeleted-res.gif" alt="File deleted." class="fileDeletedRes retina"></span>';
			$imgsrc   = '';
		}
		else {
			$dimensions = ( $ext == '.pdf' ) ? 'PDF' : $w . 'x' . $h;
			if( !$spoiler ) {
				$fileinfo = '<div class="fileText" id="fT' . $no . '">' . S_PICNAME . ': <a' . ($need_file_tooltip ? (' title="' . $longname . '"') : '') . ' href="' . $linksrc . '" target="_blank">' . $shortname . '</a> (' . $size . 'B, ' . $dimensions . ')</div>';
			}
			else {
				$fileinfo = '<div class="fileText" id="fT' . $no . '" title="' . $longname . '">' . S_PICNAME . ': <a href="' . $linksrc . '" target="_blank">Spoiler Image</a> (' . $size . 'B, ' . $dimensions . ')</div>';
			}
		}
		
		$file = <<<HTML
	<div class="file" id="f$no">
		$fileinfo
		$imgsrc
	</div>
HTML;
	}
	
	/**
	 * OP specific html
	 */
	if ($sorted_replies !== null) {
		if ($in_thread) {
			$href = '';
			$postinfo_extra = '';
		}
		else {
			$href = RES_DIR2 . $no . PHP_EXT2;
			
			if ($semantic_url !== '') {
			  $semantic_url = "/$semantic_url";
			}
			
			$postinfo_extra = ' &nbsp; <span>[<a href="'
			  . $href . $semantic_url
			  . '" class="replylink">' . S_REPLY . '</a>]</span>';
		}
		
		$oldtext = '';
		$extra   = '';
		
		// Marked for deletion (old)
		if (isset($log[$no]['old'])) {
			$oldtext .= '<span class="oldpost">' . S_OLD . '</span><br>';
		}
		
		$postInfo = '';
		
		// Count omitted replies and images
		if (!$in_thread) {
      $s = $reply_count - $shown_replies;
      
      if ($shown_replies) {
        $t = 0;
        $total_t = 0;
        
        $cur = 1;
        
        while ($s >= $cur) {
          list($row) = each($sorted_replies);
          
          if ($log[$row]['fsize'] && !$log[$row]['filedeleted']) {
            $t++;
          }
          
          $cur++;
        }
        
        $total_t = $t;
        
        while ($reply_count >= $cur) {
          list($row) = each($sorted_replies);
          
          if ($log[$row]['fsize'] && !$log[$row]['filedeleted']) {
            $total_t++;
          }
          
          $cur++;
        }
        
        if ($reply_count != 0) {
          reset($sorted_replies);
        }
      }
      else {
        $total_t = $t = $imgreplycount;
      }
		  
  		// desktop
			$posts   = ( $s < 2 ) ? ' reply' : ' replies';
			$replies = ( $t < 2 ) ? ' image' : ' images';
			
			if (( $s > 0 ) && ( $t == 0 )) {
				$extra .= '<span class="summary desktop">' . $s . $posts . ' omitted. <a href="' . $href . '" class="replylink">Click here</a> to view.</span>';
			}
			elseif (( $s > 0 ) && ( $t > 0 )) {
				$extra .= '<span class="summary desktop">' . $s . $posts . ' and ' . $t . $replies . ' omitted. <a href="' . $href . '" class="replylink">Click here</a> to view.</span>';
			}
			
  		// mobile
  		$posts   = ( $reply_count < 2 ) ? ' Reply' : ' Replies';
  		$replies = ( $total_t < 2 ) ? ' Image' : ' Images';
  		
  		if (( $reply_count > 0 ) && ( $total_t == 0 )) {
  			// Text replies only
  			$info = '' . $reply_count . $posts . '';
  		}
  		elseif (( $reply_count > 0 ) && ( $total_t > 0 )) {
  			// Image replies
  			$info = '' . $reply_count . $posts . ' / ' . $total_t . $replies . '';
  		}
  		else {
  			// nothing
  			$info = '';
  		}
  		
  		$postInfo = <<<HTML
		<div class="postLink mobile">
			<span class="info">
				$info
			</span>
			
			<a href="$href" class="button">View Thread</a>
		</div>
HTML;
		}
		else {
			$s = 0;
		}
		
		// Sticky - Closed
		$threadmodes = '';
		
		if ($sticky == 1) {
			$threadmodes .= ' <img src="' . STATIC_IMG_DIR2 . 'sticky.gif" alt="Sticky" title="Sticky" class="stickyIcon retina">';
		}
		
    if ($closed == 1) {
      if ($archived) {
        $threadmodes .= ' <img src="' . STATIC_IMG_DIR2 . 'archived.gif" alt="Archived" title="Archived" class="archivedIcon retina">';
      }
      else {
        $threadmodes .= ' <img src="' . STATIC_IMG_DIR2 . 'closed.gif" alt="Closed" title="Closed" class="closedIcon retina">';
      }
    }
		
		// Staff replies indicator
		if (META_BOARD) {
			$posts = meta_is_thread_flagged($sorted_replies);
			
			// admin = 0
			// dev = 1
			// mod = 2
			// manager = 3
			
			// array (css_class, text_name)
			$larr = array(
				0 => array('Admin', 'Administrator'),
				1 => array('Developer', 'Developer'),
				2 => array('Mod', 'Moderator'),
				3 => array('Manager', 'Manager')
			);
			
			if ($posts[0] || $posts[1] || $posts[2] || $posts[3]) {
				$com .= '<br><br><span class="capcodeReplies">';
				
				foreach ($posts as $key => $postlist) {
					if (!$postlist) {
						continue;
					}
					
					$postlist = explode(',', $postlist);
					
					$replies = count($postlist) > 1 ? 'Replies' : 'Reply';
					$com .= '<span class="smaller"><span class="bold">' . $larr[$key][1] . ' ' . $replies . ':</span> ';
					
					foreach ($postlist as $postnum) {
						$com .= '<a href="' . $href . '#p' . $postnum . '" class="quotelink">&gt;&gt;' . $postnum . '</a> ';
					}
					
					$com .= '</span><br>';
				}
				
				$com .= '</span>';
				
			}
		}
		
		if (DISP_ID && DISP_ID_PER_THREAD && !$is_archived && !DISP_ID_RANDOM && $id !== '') {
		  $id = generate_uid($no, $time, $host);
		}
		
		$reply_file = '';
		$op_file = $file;
		$post_class = 'op';
		$sidearrows = '';
	}
	/**
	 * Reply
	 */
	else {
		$href = $in_thread ? '' : RES_DIR2 . $resto . PHP_EXT2;
		$threadmodes = $postinfo_extra = $oldtext = $postInfo = $extra = $op_file = '';
		$reply_file = $file;
		$post_class = 'reply';
		$sidearrows = '<div class="sideArrows" id="sa' . $no . '">&gt;&gt;</div>';
		
		$sub = '';
	}
	
	$dispuid = '';
	$dispuid = display_uid( $id, $capcode );
	if( $dispuid == '' || $capcode != '' ) {
		$dispuid = $capcode;
	}
	else {
		$capcodeStart = '';
		if (FORCED_ANON) {
			$capcode_class = '';
		}
	}

  $countryFlag = '';
  if ($capcode == '') {
    if (ENABLE_BOARD_FLAGS && $board_flag != '' && isset($board_flags_array[$board_flag])) {
      $cname = board_flag_code_to_name($board_flag);
      $countryFlag = ' <span title="' . $cname . '" class="bfl bfl-' . strtolower($board_flag) . '"></span>';
    }
    else if (SHOW_COUNTRY_FLAGS) {
      $cname = country_code_to_name($country);
      $countryFlag = ' <span title="' . $cname . '" class="flag flag-' . strtolower($country) . '"></span>';
    }
  }
  
	$quote = $in_thread ? 'javascript:quote(\'' . $no . '\');' : $href . '#q' . $no;
	
	$postM = '';
	
	// Forced anon on meta boards
	if (META_BOARD && $capcode_class != ' capcodeAdmin') {
		$name = $mname = S_ANONAME;
	}
	
	if (FORCE_COM && !$capcode) {
		$com = FORCE_COM_TEXT;
	}
	
	$display_no = display_no($no);
	
	if ($since4pass && $capcode == '' && $since4pass < 10000) {
	  $since4passTag = " <span title=\"Pass user since $since4pass\" class=\"n-pu\"></span>";
	}
	else {
	  $since4passTag = '';
	}
  
  // April 2024
  if ($since4pass && $capcode == '' && $since4pass >= 10000) {
    $since4passTag = april_2024_get_name_badge($since4pass);
    $_xa24_post_cls = april_2024_get_post_cls($since4pass);
  }
  else {
    $_xa24_post_cls = '';
  }
  
	return <<<HTML
		<div class="postContainer {$post_class}Container$postM$_xa24_post_cls" id="pc$no">$sidearrows
			<div id="p$no" class="post $post_class$highlight">
				<div class="postInfoM mobile" id="pim$no">
          <span class="nameBlock$capcode_class">
						<span$mname_truncated class="name$hasCapcode"$namestyle>$mname</span>$since4passTag$capcodeStart$dispuid$countryFlag$threadmodes<br>
						$subshortm
					</span>

					<span class="dateTime postNum" data-utc="$time">$now <a href="$href#p$no" title="Link to this post">No.</a><a href="$quote" title="Reply to this post">$display_no</a></span>
				</div>
				
				$op_file
				
				<div class="postInfo desktop" id="pi$no">
					<input type="checkbox" name="$no" value="delete"> 
					$sub
					<span class="nameBlock$capcode_class">
						<span class="name$hasCapcode"$namestyle>$name</span>$since4passTag$capcodeStart $dispuid$countryFlag
					</span> 

					<span class="dateTime" data-utc="$time">$now</span> 

					<span class="postNum desktop">
						<a href="$href#p$no" title="Link to this post">No.</a><a href="$quote" title="Reply to this post">$display_no</a>$threadmodes$postinfo_extra
					</span>

				</div>
				$reply_file
				<blockquote class="postMessage" id="m$no">$com</blockquote>
			</div>
		$postInfo
		$oldtext
		</div>
		$extra
HTML;
}

// deletes a post from the database
// imgonly: whether to just delete the file or to delete from the database as well
// automatic: always delete regardless of password/admin (for self-pruning)
// children: whether to delete just the parent post of a thread or also delete the children
// die: whether to die on error
// careful, setting children to 0 could leave orphaned posts.
function delete_post($resno, $pwd, $imgonly = 0, $automatic = 0, $children = 1, $die = 1, $lazy_rebuild = false, $archived_deletion = false, $tool = null, $user_is_known = true)
{
	global $log;

	$resno = intval( $resno );
	log_cache( 0, $resno, $archived_deletion ? 1 : 0 );
	
	$post_exists = true;
	
	if (!isset( $log[$resno])) {
		if ($die) {
			//error( "Can't find the post $resno." );
      updating_index();
      die();
		}
		else {
		  if ($automatic) {
		    return 0;
		  }
		  
			$post_exists = false;
		}
	}

	$row = $log[$resno];

  if (!$automatic && !has_level('janitor')) {
    $cant_del = $cant_del_old = false;
    
    if ($row['resto']) {
      $cant_del = NO_DELETE_REPLY;
    }
    else {
      $cant_del = NO_DELETE_OP;
    }
    
    if ($_SERVER['REQUEST_TIME'] - $log[$resno]['time'] >= RENZOKU_DEL_CANT_AFTER) {
      $cant_del = true;
      $cant_del_old = true;
    }
    
    if ($cant_del) {
      error($cant_del_old ? S_RENZOKU_DEL_CANT_AFTER : S_MAYNOTDEL);
    }
  }
	
	// if (!$row['pwd'] && BOARD_DIR=='c') {
	// 	echo "<!--";
	// 	var_dump($log);
	// 	echo "-->";
	// }
	
  if ($archived_deletion) {
    // Only authed users can delete archived posts
    $pass_ok = false;
    $host_ok = false;
  }
  else {
    $pass_ok = $pwd && $pwd === $row['pwd'];
    $host_ok = $row['host'] == $_SERVER['REMOTE_ADDR'];
  }
  
  $admin_ok         = has_level() || can_delete( $resno );
  $can_flood_delete = has_level( 'janitor' );

	$can_delete = $automatic || $pass_ok || $host_ok || $admin_ok;

	// quick_log_to( "/www/perhost/del.log", date( "r" ) . " deletion of #$resno by " . $_SERVER['REMOTE_ADDR'] . " pwd \"$pwd\" auto " . (int)$automatic . " adminok " . (int)$admin_ok . " hostok " . (int)$host_ok . " passok " . (int)$pass_ok . " orig ip " . $row['host'] . " pwd " . $row['pwd'] . " com " . substr( $row["com"], 0, 50 ) . " " . ( $can_delete ? "succeeded" : "failed" )."\n" );

	if( !$can_delete ) error( S_BADDELPASS );

	if( $row['sticky'] ) {
		if( has_level() && !has_level( 'admin' ) && !$automatic && !$archived_deletion) error( S_MAYNOTDELSTICKY );
		if( !has_level() ) error( S_MAYNOTDEL );
	}

	if( BOARD_DIR == 'vg' && !$row['resto'] && !$admin_ok && !$archived_deletion ) error( S_MAYNOTDEL );
	
	if( !$row['resto'] && !$automatic && !$admin_ok ) {
		foreach( $row['children'] as $child => $unused ) {
			if( $log[$child]['capcode'] != 'none' ) error( S_MAYNOTDEL );
		}
	}

	if( !$automatic && !$admin_ok && !$can_flood_delete ) {
    if ($user_is_known) {
      $_renzoku_del = RENZOKU_DEL;
    }
    else {
      $_renzoku_del = 600; // FIXME: 10 minutes
    }
		if( ( time() - (int)$row['time'] ) < $_renzoku_del ) {
			error(S_RENZOKU_DEL);
		}
	}
  
  // User is authed staff
  if ($admin_ok && !IS_REBUILDD) {
    $auser   = $_COOKIE['4chan_auser'];
    
    // Use POSTed IP instead of the local one if the deletion was triggered via RPC
    if (isset($_POST['remote_addr']) && is_local()) {
      $remote_addr = $_POST['remote_addr'];
    }
    else {
      $remote_addr = $_SERVER['REMOTE_ADDR'];
    }
    
    // Authed user is deleting a post that isn't his
    if (!$pass_ok && !$host_ok) {
      $adfsize = ( $row['fsize'] > 0 ) ? 1 : 0;
      $adname  = str_replace( '</span> <span class="postertrip">!', '#', $row['name'] );
      if( $imgonly ) {
        $imgonly = 1;
      } else {
        $imgonly = 0;
      }
      
      if (isset($_POST['template_id'])) {
        $template_id = (int)$_POST['template_id'];
      }
      else {
        $template_id = 0;
      }
      
      if ($post_exists && (!$automatic || $automatic === 2)) {
        validate_admin_cookies();
        
        if (!$tool || !in_array($tool, array('search', 'ban', 'ban-req', 'autopurge', 'threadban'))) {
          $tool = '';
        }
        
        mysql_global_do( "INSERT INTO " . SQLLOGDEL . " (imgonly,postno,resto,board,name,sub,com,img,filename,admin,admin_ip,template_id,tool) values('%s',%d, %d,'%s','%s','%s','%s','%s','%s','%s', '%s', %d, '%s')", $imgonly, $resno, $row['resto'], SQLLOG, $adname, $row["sub"], $row["com"], $adfsize, $row["filename"].$row["ext"], $auser, $remote_addr, $template_id, $tool );
      }
      
      // Clear the report queue if only the file is deleted
      if ($imgonly) {
        $query = "DELETE FROM reports WHERE board = '" . SQLLOG . "' AND no = " . (int)$resno;
        
        $res = mysql_global_call($query);
        
        $query = "DELETE FROM reports_for_posts WHERE board = '" . SQLLOG . "' AND postid = " . (int)$resno;
        
        $res = mysql_global_call($query);
      }
    }
    // Staff member is deleting a post that is his, or other type of deletions
    else if (!$automatic || $automatic === 2) {
      if ($auser) {
        log_staff_event('staff_self_del', $auser, $remote_addr, $pwd, BOARD_DIR, $row);
      }
      else {
        log_staff_event('staff_auto_del', 'Auto-ban', $remote_addr, $pwd, BOARD_DIR, $row);
      }
    }
	}
  
	$delete_children = $row['resto'] == 0 && $children && !$imgonly;

	$restoq = $delete_children ? "OR (archived = 0 and resto=$resno) OR (archived = 1 and resto=$resno)" : '';
	
  if (UPLOAD_BOARD) {
    $up_col = ',filename';
  }
  else {
    $up_col = '';
  }
  
  if (MOBILE_IMG_RESIZE) {
    $up_col .= ',m_img';
  }
  
	$result = mysql_board_call( "select no,resto,tim,ext$up_col from `" . SQLLOG . "` where no=$resno $restoq" );
	
	// Array of threads to update after one or more replies were deleted.
	$updated_threads = array();
	// Array of post number for report and xff clearing
	$deleted_threads = array();
	$deleted_replies = array();
		
	$purge_files = array();
	
	$img_webroot = 'http://i.4cdn.org/' . BOARD_DIR . '/';
	
	while( $delrow = mysql_fetch_array( $result ) ) {
		// delete
		if( $delrow['ext'] ) {
			if (UPLOAD_BOARD) {
				$delfile  = IMG_DIR . $delrow['filename'] . $delrow['ext']; //path to delete
				@unlink($delfile); // delete image
				if( CLOUDFLARE_PURGE_ON_DEL ) {
				  cloudflare_purge_url($img_webroot . rawurlencode($delrow['filename']) . $delrow['ext'], true);
				}
			}
			else {
				$delfile  = IMG_DIR . $delrow['tim'] . $delrow['ext']; //path to delete
				$delthumb = THUMB_DIR . $delrow['tim'] . 's.jpg';
				@unlink( $delfile ); // delete image
				@unlink( $delthumb ); // delete thumb
	      
        if (ENABLE_OEKAKI_REPLAYS && file_exists(IMG_DIR . $delrow['tim'] . '.tgkr')) {
          unlink(IMG_DIR . $delrow['tim'] . '.tgkr');
          
          if (CLOUDFLARE_PURGE_ON_DEL) {
            $purge_files[] = $img_webroot . $delrow['tim'] . '.tgkr';
          }
        }
        
        if (MOBILE_IMG_RESIZE && $delrow['m_img']) {
          @unlink(IMG_DIR . $delrow['tim'] . 'm.jpg'); // delete mobile
        }
				
				if (CLOUDFLARE_PURGE_ON_DEL) {
				  $purge_files[] = $img_webroot . $delrow['tim'] . $delrow['ext'];
				  $purge_files[] = $img_webroot . $delrow['tim'] . 's.jpg';
				  
				  if (MOBILE_IMG_RESIZE && $delrow['m_img']) {
				    $purge_files[] = $img_webroot . $delrow['tim'] . 'm.jpg';
				  }
				  
				}
			}
		}
		if( $imgonly ) {
			mysql_board_call( "UPDATE `" . SQLLOG . "` SET filedeleted=1,root=root,last_modified=%d WHERE no=%d", $_SERVER['REQUEST_TIME'], $delrow['no'] );
			$log[$delrow['no']]['filedeleted'] = TRUE;
			
			if ($delrow['resto']) {
				mysql_board_call( "UPDATE `" . SQLLOG . "` SET root=root,last_modified=%d WHERE no=%d", $_SERVER['REQUEST_TIME'], $delrow['resto'] );
				if (isset($log[$delrow['resto']]))
					$log[$delrow['resto']]['last_modified'] = (int)$_SERVER['REQUEST_TIME'];
			}
			
			//cloudflare_purge_by_basename(BOARD_DIR, $delrow['tim'] . $delrow['ext']);
		}
		else {
			// Thread
			if (!$delrow['resto']) {
				$thread_key = @array_search( $delrow['no'], $log['THREADS'] );
				if( $thread_key !== false ) {
					unset( $log['THREADS'][$thread_key] );
				}
				
				if( USE_GZIP == 1 ) {
					@unlink( RES_DIR . $delrow['no'] . PHP_EXT . '.gz' );
					@unlink( RES_DIR . $delrow['no'] . '.json.gz' );
				}
				else {
  				@unlink( RES_DIR . $delrow['no'] . PHP_EXT );
  				@unlink( RES_DIR . $delrow['no'] . '.json' );
				}
				
				update_json_tail_deletion($delrow['no'], true);
				
				$deleted_threads[] = (int)$delrow['no'];
			}
			// Reply. Thread's last_modified field will need to be updated
			else if (!isset($updated_threads[$delrow['resto']])) {
				$updated_threads[$delrow['resto']] = true;
				
				$deleted_replies[] = (int)$delrow['no'];
			}
			
			unset( $log[$delrow['no']] );
		}
	}
	
	if (!empty($purge_files)) {
	  cloudflare_purge_url($purge_files, true);
	}
	
	// Updating last_modified field (threads)
	foreach ($updated_threads as $thread_id => $true) {
		mysql_board_call("UPDATE `".SQLLOG."` set root=root,last_modified=%d where no=%d", $_SERVER['REQUEST_TIME'], $thread_id);
		
		if (isset($log[$thread_id]))
			$log[$thread_id]['last_modified'] = (int)$_SERVER['REQUEST_TIME'];
		
		unset( $log[$thread_id]['children'][$delrow['no']] );
	}
	
	// Clearing reports and xff
	if ($deleted_replies) {
		$in_clause = 'IN(' . implode(',', $deleted_replies) . ')';
		mysql_global_do("DELETE FROM reports WHERE board='" . BOARD_DIR . "' AND no " . $in_clause);
		mysql_global_do("DELETE FROM reports_for_posts WHERE board='" . BOARD_DIR . "' AND postid " . $in_clause);
		
    if (SAVE_XFF) {
      mysql_global_do("UPDATE xff SET is_live = 0 WHERE board='" . BOARD_DIR . "' AND postno " . $in_clause);
    }
	}
	
	if ($deleted_threads) {
		$in_clause = 'IN(' . implode(',', $deleted_threads) . ')';
		mysql_global_do("DELETE FROM reports WHERE board='" . BOARD_DIR . "' AND (no $in_clause OR resto $in_clause)");
		mysql_global_do("DELETE FROM reports_for_posts WHERE board='" . BOARD_DIR . "' AND (postid $in_clause OR threadid $in_clause)");
		
    if (SAVE_XFF) {
      mysql_global_do("UPDATE xff SET is_live = 0 WHERE board='" . BOARD_DIR . "' AND postno " . $in_clause);
    }
	}
  
  // Halloween 2017
  /*
  if ($tool && defined('CSS_EVENT_NAME') && CSS_EVENT_NAME === 'spooky2017') {
    if ($tool === 'ban') {
      decrease_halloween_score($resno);
    }
    else if ($tool === 'ban-req') {
      decrease_halloween_score($resno, 0.90);
    }
  }
  */
  
	//delete from DB
	if( $delete_children ) // delete thread and children
		$result = mysql_board_call( "delete from `" . SQLLOG . "` where no=$resno or resto=$resno" );
	elseif( !$imgonly ) // just delete the post
		$result = mysql_board_call( "delete from `" . SQLLOG . "` where no=$resno" );

	rpc_task();
	if( $imgonly && $row['resto'] == 0 ) {
		return $resno; // return thread number to stop deletion silliness
	}

	return $row['resto']; // so the caller can know what pages need to be rebuilt
}

function rebuild_deletions($rebuild, $lazy_rebuild = false)
{
  global $log;
  
	foreach( $rebuild as $key => $val ) {
		log_cache( 0, $key );
    if (!isset($log[$key]['children'])) {
      internal_error_log("rebuild_deletions", "missing children for OP /" . BOARD_DIR . "/$key");
      continue;
    }
		updatelog( $key, 1 ); // leaving the second parameter as 0 rebuilds the index each time!
		update_json_tail_deletion($key);
	}
  
	if( STATIC_REBUILD ) return;

	updatelog(0, 0, $lazy_rebuild); // update the index page last
}

/**
 * Removes old archived posts
 */
function trim_archive() {
  if (STATIC_REBUILD && !IS_REBUILDD) {
    return;
  }
  
  if (!ARCHIVE_MAX_AGE) {
    return;
  }
  
  $interval = (int)ARCHIVE_MAX_AGE;
  
  $query = <<<SQL
SELECT no FROM `%s`
WHERE archived = 1
AND resto = 0
AND root < DATE_SUB(NOW(), INTERVAL $interval HOUR)
SQL;
  
  $res = mysql_board_call($query, BOARD_DIR);
  
  if (!$res || !mysql_num_rows($res)) {
    return;
  }
  
  while ($row = mysql_fetch_row($res)) {
    delete_post((int)$row[0], '', 0, 1, 1, 0, false, true);
  }
}

// purge old posts
// should be called whenever a new post is added.
function trim_db()
{
	global $mode;
	if( JANITOR_BOARD == 1 ) return;
	if( STATIC_REBUILD && !IS_REBUILDD ) return;
  
  if (!IS_REBUILDD) {
    log_cache();
  }
  
	$maxposts = LOG_MAX;
	// max threads = max pages times threads-per-page
	$maxthreads = ( PAGE_MAX > 0 ) ? ( PAGE_MAX * DEF_PAGES ) : 0;

	$threads = array();
	
	$rebuild_archive_list = false;
	
  $rebuild_archive_json = false;
  
  if (ENABLE_ARCHIVE) {
    if (IS_REBUILDD) {
      clearstatcache(true, INDEX_DIR . 'archive' . PHP_EXT . '.gz');
    }
    
    if (filemtime(INDEX_DIR . 'archive' . PHP_EXT . '.gz') < time() - (int)ARCHIVE_REBUILD_DELAY) {
      $rebuild_archive_list = true;
    }
  }
	
	// New max-page method
	if( $maxthreads ) {
		$exp_order = 'no';
		if( EXPIRE_NEGLECTED == 1 ) $exp_order = 'root';
		//logtime( 'trim_db before select threads' );
		$result = mysql_board_call( "SELECT no FROM `" . SQLLOG . "` WHERE archived=0 AND sticky=0 AND undead=0 AND resto=0 ORDER BY $exp_order ASC" );
		//logtime( 'trim_db after select threads' );
		$threadcount = mysql_num_rows( $result );
		
		if (!$threadcount && $rebuild_archive_list) {
		  $rebuild_archive_list = false;
		}
		
		while( $row = mysql_fetch_array( $result ) and $threadcount > $maxthreads ) {
			if (ENABLE_ARCHIVE) {
        $rebuild_archive_json = true;
			  archive_thread($row['no']);
			}
			else {
			  delete_post( $row['no'], 'trim', 0, 1 ); // imgonly=0, automatic=1, children=1
		  }
			$threads[$row['no']] = 1;
			$threadcount--;
		}
    
		mysql_free_result( $result );
		
    if (ENABLE_ARCHIVE) {
      if ($rebuild_archive_list) {
        rebuild_archive_list();
      }
      
      if ($rebuild_archive_json && ENABLE_JSON_THREADS) {
        generate_board_archived_json();
      }
    }
		
		// Original max-posts method (note: cleans orphaned posts later than parent posts)
	} else {
		// make list of stickies
		$stickies = array(); // keys are stickied thread numbers
		$undead   = array();
		// COMBINE FOR MAXIMUM EFFICIENCY!
		$result = mysql_board_call( "SELECT no from `" . SQLLOG . "` where (sticky=1 OR undead=1) and resto=0" );
		while( $row = mysql_fetch_array( $result ) ) {
			if( $row['sticky'] ) $stickies[$row['no']] = 1;
			if( $row['undead'] ) $undead[$row['no']] = 1;
		}

		// FIXME these if ... continue checks need to be SQL conditions!
		$result    = mysql_board_call( "SELECT no,resto,sticky FROM `" . SQLLOG . "` ORDER BY no ASC" );
		$postcount = mysql_num_rows( $result );
		while( $row = mysql_fetch_array( $result ) and $postcount >= $maxposts ) {
			// don't delete if this is a sticky thread or is undeletable
			if( $row['sticky'] == 1 || $row['undead'] == 1 ) continue;
			// don't delete if this is a REPLY to a sticky or is in an undeletable thread
			if( $row['resto'] != 0 && ( $stickies[$row['resto']] == 1 || $undead[$row['resto']] == 1 ) ) continue;
			delete_post( $row['no'], 'trim', 0, 1, 0 ); // imgonly=0, automatic=1, children=0
			$threads[$row['no']] = 1;
			$postcount--;
		}
		mysql_free_result( $result );
	}
}

// FIXME archives
// debug function, deletes all archived threads
function purge_archive() {
  $query = "SELECT no FROM `test` WHERE archived = 1 AND resto = 0";
  
  $res = mysql_board_call($query);
  
  if (!$res) {
    return;
  }
  
  while ($thread = mysql_fetch_assoc($res)) {
    echo "Deleting {$thread['no']}<br>";
    delete_post((int)$thread['no'], '', 0, 0, 1, true, false, true);
  }
}

function rebuild_archived_thread($thread_id) {
  global $log;
  
  log_cache(0, $thread_id, 1);
  
  if (!isset($log[$thread_id])) {
    return false;
  }
  
  // Build the JSON
  if (ENABLE_JSON) {
    $tailSize = get_json_tail_size($thread_id);
    
    if ($tailSize) {
      generate_thread_json($thread_id, false, false, false, $tailSize);
    }
    else {
      update_json_tail_deletion($thread_id);
    }
    
    generate_thread_json($thread_id);
  }
  
  // Build the HTML
  $dat = '';
  
  head($dat, $thread_id);
  form($dat, $thread_id);
  
	$dat .= '<hr>
<form name="delform" id="delform" action="' . SELF_PATH_ABS . '" method="post">
<div class="board">
';
  
  $reply_count = $log[$thread_id]['replycount'];
  
  // Open thread tag and render OP
  $sorted_replies = $log[$thread_id]['children'];
  ksort($sorted_replies);
  
  $dat .= '<div class="thread" id="t' . $thread_id . '">'
    . renderPostHtml($thread_id, $thread_id, $sorted_replies, $reply_count, null, true);
  
  // Render replies
  $repCount = 0;
  
  while (list($resrow) = each($sorted_replies)) {
    if (!$log[$resrow]['no']) {
      break;
    }
    
    $dat .= renderPostHtml($resrow, $thread_id, null, null, null, true);
    
    $repCount++;
  }
  
  // Close thread tag
  $dat .= '
</div>
<hr>
';

  $dat .= '<div class="navLinks navLinksBot desktop">[<a href="/'
    . BOARD_DIR . '/" accesskey="a">' . S_RETURN . '</a>] [<a href="/'
    . BOARD_DIR . '/catalog">' . S_CATALOG . '</a>] [<a href="#top">'
    . S_TOP . '</a>] </div><hr class="desktop">';

  // Close board tag
  $lang = S_FORM_REPLY;

  $dat .= '
<div class="mobile center"><a class="mobilePostFormToggle button" href="#">'
  . $lang . '</a></div>
</div>';

  $dat .= '<div class="navLinks mobile"><span class="mobileib button"><a href="/'
    . BOARD_DIR . '/" accesskey="a">'
    . S_RETURN . '</a></span> <span class="mobileib button"><a href="/'
    . BOARD_DIR . '/catalog">'
    . S_CATALOG . '</a></span> <span class="mobileib button"><a href="#top">'
    . S_TOP . '</a></span> <span class="mobileib button"><a href="#bottom_r" id="refresh_bottom">'
    . S_REFRESH . '</a></span></div><hr class="mobile">';
  
  /**
   * ADS
   */
  
  if (defined('AD_ADGLARE_BOTTOM') && AD_ADGLARE_BOTTOM) {
    $dat .= '<div class="adg-rects desktop"><div class="adg adp-90" id=zone' . AD_ADGLARE_BOTTOM . '></div><hr></div>';
  }
  
  if (defined('AD_ADGLARE_BOTTOM_MOBILE') && AD_ADGLARE_BOTTOM_MOBILE) {
    $dat .= '<div class="adg-rects mobile"><div class="adg-m adp-250" id=zone' . AD_ADGLARE_BOTTOM_MOBILE . '></div><hr></div>';
  }
  
  if (defined('AD_RC_BOTTOM') && AD_RC_BOTTOM) {
    $dat .= '<div class="adg-rects desktop"><div class="adg adp-228" data-rc="' . AD_RC_BOTTOM . '" id="rcjsload_bottom"></div><hr></div>';
  }
  
  if (defined('AD_BSA_BOTTOM') && AD_BSA_BOTTOM) {
    $dat .= AD_BSA_BOTTOM;
  }
  
  if (defined('AD_RC_BOTTOM_MOBILE') && AD_RC_BOTTOM_MOBILE) {
    $dat .= '<div class="adg-rects mobile"><div class="adg-m adp-50" data-rc="' . AD_RC_BOTTOM_MOBILE . '" id="rcjsload_bottom_m"></div><hr></div>';
  }
  
  if (defined('AD_ADNIUM_BOTTOM_MOBILE') && AD_ADNIUM_BOTTOM_MOBILE) {
    $dat .= '<div class="adg-rects mobile"><div class="adg-m adp-250" id="adn-' . AD_ADNIUM_BOTTOM_MOBILE . '" data-adn></div><hr></div>';
  }
  
  if (defined('AD_ABC_BOTTOM_MOBILE') && AD_ABC_BOTTOM_MOBILE) {
    $dat .= '<div class="adg-rects mobile"><div class="adg-m adp-250" data-abc="' . AD_ABC_BOTTOM_MOBILE . '"></div><hr></div>';
  }
  /*
  if (defined('AD_BIDGEAR_BOTTOM') && AD_BIDGEAR_BOTTOM)  {
    $dat .= '<div class="adc-resp-bg" data-ad-bg="' . AD_BIDGEAR_BOTTOM . '"></div>';
  }
  else if (defined('AD_TERRA_BOTTOM_DESKTOP') && AD_TERRA_BOTTOM_DESKTOP) {
    $_ad_info = explode(',', AD_TERRA_BOTTOM_DESKTOP);
    $dat .= '<div class="adt-800" data-d="' . $_ad_info[0] . '" id="container-' . $_ad_info[1] . '" style="max-width:800px;margin:auto;"></div>';
  }
  */
  if (defined('ADS_DANBO') && ADS_DANBO)  {
    $dat .= '<div id="danbo-s-b" class="danbo-slot"></div><div class="adl">[<a target="_blank" href="https://www.4chan.org/advertise">Advertise on 4chan</a>]</div><hr>';
  }
  if (defined('AD_CUSTOM_BOTTOM') && AD_CUSTOM_BOTTOM) {
    $dat .= '<div>' . AD_CUSTOM_BOTTOM . '<hr></div>';
  }
  
  $resredir = '<input type="hidden" name="res" value="' . $thread_id . '">';
  
  // deletion mode is "arcdel" instead of "usrdel"
  $dat .= '<div class="bottomCtrl desktop"><span class="deleteform"><input type="hidden" name="mode" value="arcdel">'
    . S_REPDEL . $resredir . ' [<input type="checkbox" name="onlyimgdel" value="on">'
    . S_DELPICONLY . ']<input type="hidden" id="delPassword" name="pwd"> <input type="submit" value="'
    . S_DELETE . '"><input id="bottomReportBtn" type="button" value="Report"></span>';

  if (!defined('CSS_FORCE')) {
    $dat .= '<span class="stylechanger">Style: 
      <select id="styleSelector">
        <option value="Yotsuba New">Yotsuba</option>
        <option value="Yotsuba B New">Yotsuba B</option>
        <option value="Futaba New">Futaba</option>
        <option value="Burichan New">Burichan</option>
        <option value="Tomorrow">Tomorrow</option>
        <option value="Photon">Photon</option>';
    
    if (defined('CSS_EVENT_NAME') && CSS_EVENT_NAME) {
      $dat .= '<option value="_special">Special</option>';
    }
    
    $dat .= '</select>
    </span>';
  }
  
  $dat .= '</div></form>';
  
  foot($dat);
  
  // Write the page
  print_page(RES_DIR . $thread_id . PHP_EXT, $dat);
}

function calculate_indexes_to_rebuild( $updated_thread )
{
	global $index_rbl;
	$query     = mysql_board_call( "SELECT COUNT(no) FROM `%s` WHERE archived = 0 AND root > (SELECT root FROM `%s` WHERE no=%d)", SQLLOG, SQLLOG, $updated_thread );
	$index_rbl = floor( mysql_result( $query, 0, 0 ) / DEF_PAGES );
}

function rebuild_indexes_daemon()
{
	global $index_rbl, $index_last_thread, $index_last_post, $log;
	static $index_arr = array();

	$index_rbl = PAGE_MAX;

	// Get latest thread
	$query = mysql_board_call( "SELECT max(no) last_post, max(resto) last_thread FROM `%s` WHERE archived = 0", SQLLOG );
	$q = mysql_fetch_assoc( $query );

	$latest_thread = $q['last_thread'];
	$latest_post   = $q['last_post'];

	if( $index_last_thread != $latest_thread ) {
		// cry :(
		$index_last_thread = $latest_thread;
		$index_last_post   = $latest_post;
    
		updatelog();
    
    if (ENABLE_JSON_THREADS && ENABLE_ARCHIVE) {
      generate_board_archived_json();
    }
    
		return;
	}

	if( $index_last_post != $latest_post ) {
		$index_last_thread = $latest_thread;
		$index_last_post   = $latest_post;

		$post_arr = $log['THREADS'];

		// Now we know we're not going to have identical arrays.
		$count            = count( $post_arr );
		$last_seen_thread = 0;

		for( $i = 0; $i < $count; $i++ ) {
			if( $post_arr[$i] != $index_arr[$i] ) $last_seen_thread = $i;
		}

		$index_rbl = floor( $last_seen_thread / DEF_PAGES );
		$index_arr = $post_arr;

		updatelog();

		return;
	}

	// YAY NOTHING TO UPDATE!
	return;
}

function style_group()
{
	return ( DEFAULT_BURICHAN == 1 ) ? "ws_style" : "nws_style";
}

function rebuildd_stats()
{
	if (!IS_REBUILDD) return "";
	
	global $update_avg_secs;
	global $rpc_chs;
	
	$avgtime = $update_avg_secs;
	
	$memuse = (int)(memory_get_usage(true) / 1024);
	$peakmemuse = (int)(memory_get_peak_usage(true) / 1024);
	$rpccount = count($rpc_chs);
	
	return "<!-- t $avgtime m $memuse $peakmemuse rc $rpccount -->";
}

// Changes relative board urls to absolute //board.4chan.org urls
// mostly for /j/ and error pages on sys.4chan
function fix_board_nav($nav, $fix_protocol = false) {
  if ($fix_protocol) {
    $protocol = (stripos($_SERVER["HTTP_REFERER"], "https") === 0) ? 'https:' : 'http:';
  }
  else {
    $protocol = '';
  }
  
  return preg_replace('/href="\/([a-z0-9]+)\/"/', "href=\"$protocol//boards." . L::d(BOARD_DIR) . "/$1/\"", $nav);
}

// Same but for /archive lmao
function fix_board_nav_archive($nav) {
  $nav = preg_replace('/href="\/([a-z0-9]+)\/"/', 'href="/$1/archive"', $nav);
  $nav = preg_replace('/href="\/f\/archive"/', 'href="/f/"', $nav);
  $nav = preg_replace('/href="\/b\/archive"/', 'href="/b/"', $nav);
  
  return $nav;
}

function head( &$dat, $res, $error = 0, $page = 0, $npages = 0, $is_arclist = false )
{
	//( $dat, 0, 0, 0, 0, true )
	global $log, $thread_unique_ips;
  
	$titlepart = $rta = $favicon = $css = $rss = $subtitle = $extra = '';
	
	$includenav = file_get_contents_cached(NAV_TXT);
	
  if( JANITOR_BOARD == 1 ) {
    $dat .= broomcloset_head( $dat );
    $includenav = fix_board_nav($includenav);
  }
  else if ($error) {
    $includenav = fix_board_nav($includenav, true);
  }
  else if ($is_arclist) {
    $includenav = fix_board_nav_archive($includenav);
  }
	
	if( TITLE_IMAGE_TYPE == 1 ) {
		$titleimg = rand_from_flatfile( YOTSUBA_DIR, 'title_banners.txt' );
		//$titleimg = STATIC_SERVER . 'image/title/' . $titleimg;

		$titlepart .= '<div id="bannerCnt" class="title desktop" data-src="' . $titleimg . '"></div>';
	} elseif( TITLE_IMAGE_TYPE == 2 ) {
		$titlepart .= '<img class="title" src="' . TITLEIMG . '" onclick="this.src = this.src;">';
	}
	
	if( defined( 'SUBTITLE' ) ) {
		$subtitle = '<div class="boardSubtitle">' . SUBTITLE . '</div>';
	}
	
	// CSS Workings
	$cssVersion = TEST_BOARD ? CSS_VERSION_TEST : CSS_VERSION;
	$defaultcss = ( DEFAULT_BURICHAN == 1 ) ? 'yotsubluenew' : 'yotsubanew';
	$mobilecss  = ( ( DEFAULT_BURICHAN == 1 ) ? 'yotsublue' : 'yotsuba' ) . 'mobile.' . $cssVersion . '.css';
	
	$styles = array(
		'Yotsuba New'   => "yotsubanew.$cssVersion.css",
		//'Yotsuba' => "yotsuba.$cssVersion.css",
		'Yotsuba B New' => "yotsubluenew.$cssVersion.css",
		'Futaba New'    => "futabanew.$cssVersion.css",
		'Burichan New'  => "burichannew.$cssVersion.css",
		'Photon'        => "photon.$cssVersion.css",
		'Tomorrow'      => "tomorrow.$cssVersion.css"
	);

	// /j/ versioning fix
	if( BOARD_DIR == 'j' ) {
		$css = '<link rel="stylesheet" type="text/css" href="' . STATIC_SERVER . 'css/janichan.' . $cssVersion . '.css" title="Yotsuba New">';
    $extra = <<<JJS
<script type="text/javascript">
  document.addEventListener('mousedown', function(e) {
    var t = e.target;
    if (t === document) {
      return;
    }
    if (/^>>>\//.test(t.textContent) && /sys\.4chan/.test(t.href)) {
      t.href = t.href.replace('sys.4chan', 'boards.4chan');
    }
  }, false);
</script>
JJS;
	} else {
		if( defined( 'CSS_FORCE' ) ) {
			foreach( $styles as $style => $stylecss ) {
				$rel = ( $style == 'Yotsuba New' ) ? 'stylesheet' : 'alternate stylesheet';
				$css .= '<link rel="' . $rel . '" type="text/css" href="' . CSS_FORCE . '" title="' . $style . '">';
			}
		}
		else {
			$dcssl = $defaultcss . '.' . $cssVersion . '.css';
			$css .= '<link rel="stylesheet" title="switch" href="' . STATIC_SERVER . 'css/' . $dcssl . '">';
			foreach( $styles as $style => $stylecss ) {
				$css .= '<link rel="alternate stylesheet" style="text/css" href="' . STATIC_SERVER . 'css/' . $stylecss . '" title="' . $style . '">';
			}
      
      if (defined('CSS_EVENT_NAME') && CSS_EVENT_NAME) {
        $css .= '<link rel="alternate stylesheet" style="text/css" href="' . STATIC_SERVER . 'css/'
          . CSS_EVENT_NAME . '.' . $cssVersion . '.css" title="_special">';
      }
		}
    
    // Christmas 2021
    if (defined('CSS_EVENT_NAME') && CSS_EVENT_NAME === 'tomorrow') {
      $extra = <<<JJS
<script src='//s.4cdn.org/js/snow.js'></script>
<script>
  function fc_tomorrow_init() {
    if (window.matchMedia && window.matchMedia('(min-width: 481px)').matches) {
      fc_spawn_snow(Math.floor(Math.random() * 50) + 50);
    }
  }
  function fc_tomorrow_cleanup() {
    fc_remove_snow();
  }
</script>
<style>
.boardBanner, #delform, .navLinksBot.desktop {
  border-image-slice: 50 0 50 0;
  border-image-width: 40px 0px 0px 0px;
  border-image-outset: 0px 0px 0px 0px;
  border-image-repeat: repeat repeat;
  border-image-source: url('https://s.4cdn.org/image/temp/garland.png');
  border-style: solid;
  padding-top: 50px;
}
</style>
JJS;
    }
    
    // Halloween spooky.css
    if (defined('CSS_EVENT_NAME') && CSS_EVENT_NAME === 'spooky') {
      $extra = <<<JJS
<script>
  function fc_skelrot(e) {
    var el, idx, thres, max;
    if (e && e.detail && e.detail.count) {
      thres = 0.33;
    }
    else {
      thres = 0.0;
    }
    max = 23;
    if (Math.random() < thres) {
      return;
    }
    if (el = document.getElementById('skellington')) {
      el.parentNode.removeChild(el);
    }
    idx = 1 + Math.floor(Math.random() * max);
    el = document.createElement('img');
    el.id = 'skellington';
    el.className = 'desktop' + (Math.random() < 0.25 ? ' topskel' : '');
    el.alt = '';
    if (Math.random() < 0.01) {
      el.src = '//s.4cdn.org/image/temp/dinosaur.gif';
    }
    else {
      el.src = '//s.4cdn.org/image/skeletons/' + idx + '.gif';
    }
    document.body.insertBefore(el, document.body.firstElementChild);
  }
  function fc_spooky_init() {
    if (window.matchMedia && window.matchMedia('(min-width: 481px)').matches) {
      document.addEventListener('4chanThreadUpdated', fc_skelrot, false);
      window.dark_captcha = true;
      fc_skelrot();
    }
  }
  function fc_spooky_cleanup() {
    var el = document.getElementById('skellington');
    window.dark_captcha = false;
    document.removeEventListener('4chanThreadUpdated', fc_skelrot, false);
    el && el.parentNode.removeChild(el);
  }
</script>
JJS;
    }
	}

	$css .= '<link rel="stylesheet" href="' . STATIC_SERVER . 'css/' . $mobilecss . '">';
	
  // April 2024
  $css .= '<link rel="stylesheet" href="' . STATIC_SERVER . 'css/xa24extra.css">';
  
	if (SHOW_COUNTRY_FLAGS) {
		$css .= '<link rel="stylesheet" href="' . STATIC_SERVER . 'css/flags.' . CSS_VERSION_FLAGS . '.css">';
	}
  
  if (ENABLE_BOARD_FLAGS) {
    $_flags_type = (defined('BOARD_FLAGS_TYPE') && BOARD_FLAGS_TYPE) ? BOARD_FLAGS_TYPE : BOARD_DIR;
    $css .= '<link rel="stylesheet" href="' . STATIC_SERVER . 'image/flags/' . $_flags_type . '/flags.' . CSS_VERSION_BOARD_FLAGS . '.css">';
  }
  
	if( CODE_TAGS ) {
		$css .= '<link rel="stylesheet" href="' . STATIC_SERVER . 'js/prettify/prettify.' . CSS_VERSION . '.css">';
	}

	// Various optional tags
	if( USE_RSS == 1 ) {
		$rss = '<link rel="alternate" title="RSS feed" href="/' . BOARD_DIR . '/index.rss" type="application/rss+xml">';
	}

	if( RTA == 1 ) {
		$rta = '<meta name="rating" content="adult">';
	}

	if( defined( 'FAVICON' ) ) {
		$favicon = '<link rel="shortcut icon" href="' . FAVICON . '">';
	}
	
	$thread_unique_ips = 0;
	$jsUniqueIps = '';
	
	if (SHOW_THREAD_UNIQUES) {
    if ($res) {
      $thread_unique_ips = get_unique_ip_count($res);
    }
    
    if ($thread_unique_ips) {
      $jsUniqueIps = 'var unique_ips = ' . $thread_unique_ips . ';';
    }
	}
  
	// js tags
	$jsVersion   = TEST_BOARD ? JS_VERSION_TEST : JS_VERSION;
	$comLen      = MAX_COM_CHARS;
	$styleGroup  = style_group();
	$maxFilesize = MAX_KB * 1024;
	$maxLines    = MAX_LINES;
	$jsCooldowns = json_encode(array(
		'thread' => RENZOKU3,
		'reply' => RENZOKU,
		'image' => RENZOKU2
	));
  
	$tailSizeJs = '';
	
  if ($res) {
    $tailSize = get_json_tail_size($res);
    
    if ($tailSize) {
      $tailSizeJs = ",tailSize = $tailSize";
    }
  }
	
  $title = TITLE;
  
	$scriptjs = <<<JS
<script type="text/javascript">
var style_group = "$styleGroup",
cssVersion = $cssVersion,
jsVersion = $jsVersion,
comlen = $comLen,
maxFilesize = $maxFilesize,
maxLines = $maxLines,
clickable_ids = 1,
cooldowns = $jsCooldowns
$tailSizeJs;
$jsUniqueIps
JS;
  
  if (defined('CSS_EVENT_NAME') && CSS_EVENT_NAME) {
    $scriptjs .= 'var css_event = "' . CSS_EVENT_NAME . '";';
    
    if (defined('CSS_EVENT_VERSION')) {
      $_css_event_version = (int)CSS_EVENT_VERSION;
    }
    else {
      $_css_event_version = 1;
    }
    
    $scriptjs .= 'var css_event_v = ' . $_css_event_version . ';';
  }
  
  if ((int)MAX_WEBM_FILESIZE < (int)MAX_KB) {
    $scriptjs .= 'var maxWebmFilesize = ' . (MAX_WEBM_FILESIZE * 1024) . ';';
  }
  
  $_is_archived = false;
  
  if (ENABLE_ARCHIVE) {
    $scriptjs .= 'var board_archived = true;';
    
    if ($res && $log[$res]['archived']) {
      $scriptjs .= 'var thread_archived = true;';
      $_is_archived = true;
    }
  }
  
  if (DISP_ID) {
    $scriptjs .= 'var user_ids = true;';
  }
  
  if (JSMATH) {
    $scriptjs .= 'var math_tags = true;';
  }
  
  if (SJIS_TAGS) {
    $scriptjs .= 'var sjis_tags = true;';
  }
  
  if (SPOILERS) {
    $scriptjs .= 'var spoilers = true;';
  }
  
  if (CAPTCHA_TWISTER) {
    $scriptjs .= 'var t_captcha = true;';
  }
  
	if( $res && $log[$res]['bumplimit'] ) {
		$scriptjs .= 'var bumplimit = 1;';
	}

	if( $res && $log[$res]['imagelimit'] ) {
		$scriptjs .= 'var imagelimit = 1;';
	}

	if( AD_PLEA ) $scriptjs .= 'var check_for_block = ' . (int)AD_PLEA . ';';

	if( $error ) $scriptjs .= 'is_error = "true";';
  
  // Danbo ads
  if (defined('ADS_DANBO') && ADS_DANBO) {
    if (DEFAULT_BURICHAN) {
      $scriptjs .= "var danbo_rating = '__SFW__';";
    }
    else {
      $scriptjs .= "var danbo_rating = '__NSFW__';";
    }
    
    // Set up fallbacks
    $_danbo_fallbacks = [];
    
    if (defined('AD_BIDGEAR_TOP') && AD_BIDGEAR_TOP)  {
      $_danbo_fallbacks['t_bg'] = AD_BIDGEAR_TOP;
    }
    else {
      if (defined('AD_ABC_TOP_DESKTOP') && AD_ABC_TOP_DESKTOP)  {
        $_danbo_fallbacks['t_abc_d'] = AD_ABC_TOP_DESKTOP;
      }
      
      if (defined('AD_ABC_TOP_MOBILE') && AD_ABC_TOP_MOBILE)  {
        $_danbo_fallbacks['t_abc_m'] = AD_ABC_TOP_MOBILE;
      }
    }
    
    if (defined('AD_BIDGEAR_BOTTOM') && AD_BIDGEAR_BOTTOM)  {
      $_danbo_fallbacks['b_bg'] = AD_BIDGEAR_BOTTOM;
    }
    else if (defined('AD_ABC_BOTTOM_MOBILE') && AD_ABC_BOTTOM_MOBILE)  {
      $_danbo_fallbacks['b_abc_m'] = AD_ABC_BOTTOM_MOBILE;
    }
    
    if (!$_danbo_fallbacks) {
      $_danbo_fallbacks = 'null';
    }
    else {
      $_danbo_fallbacks = json_encode($_danbo_fallbacks);
    }
    
    $scriptjs .= 'var danbo_fb = ' . $_danbo_fallbacks . ';';
    
    // Tag closed further below
    $scriptjs .= '</script><script src="https://static.danbo.org/publisher/q2g345hq2g534-4chan/js/preload.4chan.js" defer>';
  }
  
  // Close the main script tag /!\
  $scriptjs .= '</script>';
  
  // PubFuture
  if (DEFAULT_BURICHAN) {
    $scriptjs .= '<script async data-cfasync="false" src="https://cdn.pubfuture-ad.com/v2/unit/pt.js"></script>';
  }
  
	$testjs    = ( TEST_BOARD ) ? 'test/core-8psvqAqszI.' . JS_VERSION_TEST . '.js' : 'core.min.' . JS_VERSION_CORE . '.js';
	$testextra = ( TEST_BOARD ) ? 'test/extension-8psvqAqszI.' . JS_VERSION_TEST . '.js' : 'extension.min.' . JS_VERSION_EXT . '.js';

	$scriptjs .= '<script type="text/javascript" data-cfasync="false" src="' . STATIC_SERVER . 'js/' . $testjs . '"></script>';
	
	if( !$error ) $scriptjs .= '<script type="text/javascript" data-cfasync="false" src="' . STATIC_SERVER . 'js/' . $testextra . '"></script>';
  
  // April 2022
  //$scriptjs .= '<script type="text/javascript" src="' . STATIC_SERVER . 'js/emotes2022.js?8"></script>';
  
  if (TEST_BOARD) {
    $stylejs = '';
  }
  else {
    $stylejs = '';
  }
  
  if (ENABLE_PAINTERJS && $_GET['mode'] != 'oe_finish') {
    if (TEST_BOARD) {
      $scriptjs .= '<script type="text/javascript" src="' . STATIC_SERVER . 'js/test/tegaki-8psvqAqszI.' . JS_VERSION_TEST . '.js"></script>';
      $css .= '<link rel="stylesheet" href="' . STATIC_SERVER . 'css/tegaki-8psvqAqszI.' . CSS_VERSION_TEST . '.css">';
    }
    else {
      $scriptjs .= '<script type="text/javascript" src="' . STATIC_SERVER . 'js/tegaki.min.' . JS_VERSION_PAINTER . '.js"></script>';
      $css .= '<link rel="stylesheet" href="' . STATIC_SERVER . 'css/tegaki.' . CSS_VERSION_PAINTER . '.css">';
    }
  }
  /*
  if (!$is_arclist && defined('CSS_MATERIAL') && CSS_MATERIAL) {
    $css .= '<link href="https://fonts.googleapis.com/css?family=Roboto" rel="stylesheet" type="text/css">';
  }
  */
	if( !$res ) {
		$prev = ( $page - DEF_PAGES ) / DEF_PAGES;
		$next = ( $page + DEF_PAGES ) / DEF_PAGES;

		if( $prev == 0 ) {
			$prev_link = SELF_PATH2;
		} else if( $prev > 0 ) {
			$prev_link = $prev . PHP_EXT2;
		}

		// maybe >= ?
		if( ( $npages - $page ) > DEF_PAGES ) {
			$next_link = $next . PHP_EXT2;
		}
	}
	
	if ($is_arclist) {
		$canonical = '<link rel="canonical" href="https://boards.' . L::d(BOARD_DIR) . '/' . BOARD_DIR.'/archive">';
  }
	else if (!$res) {
	  if ($page > 0) {
		  $canonical = '<link rel="canonical" href="https://boards.' . L::d(BOARD_DIR) . '/' . BOARD_DIR.'/' . (($page / DEF_PAGES) + 1) . '">';
	  }
	  else {
		  $canonical = '<link rel="canonical" href="https://boards.' . L::d(BOARD_DIR) . '/' .BOARD_DIR.'/">';
	  }
	}
	elseif ($res) {
    $href_context = $log[$res]['semantic_url'];
    
    if ($href_context !== '') {
      $href_context = "/$href_context";
    }
	  
		$canonical = '<link rel="canonical" href="https://boards.' . L::d(BOARD_DIR) . '/' .BOARD_DIR.'/thread/'.$res.$href_context . '">';
	}
	else {
	  $canonical = '';
	}
  
	$clean_title = strip_tags(TITLE);
	
  if ($res) {
    $page_metatags = generate_page_metatags($log[$res]['sub'], $log[$res]['com']);
    
    if ($page_metatags) {
      $page_description = $page_metatags[0] . ' - ' . META_DESCRIPTION;
      $page_keywords = META_KEYWORDS . $page_metatags[1];
    }
    else {
      $page_description = META_DESCRIPTION;
      $page_keywords = META_KEYWORDS;
    }
    
    $page_title = generate_page_title($res, $log[$res]['sub'], $log[$res]['com'])
      . ' - ' . preg_replace('/^[\[\/][a-z0-9]+[\]\/] - /i', '', $clean_title);
  }
  else {
    $page_description = META_DESCRIPTION;
    $page_keywords = META_KEYWORDS;
    
    $page_title = $clean_title;
    
    if ($is_arclist) {
      $page_title .= ' - Archive';
    }
    else if ($page > 0) {
      $page_title .= ' - Page ' . (($page / DEF_PAGES) + 1);
    }
  }
  
  $page_title .= ' - 4chan';
  
  if (!$_is_archived) {
    $_delegate_ch = '<meta http-equiv="Delegate-CH" content="Sec-CH-UA-Model https://sys.4chan.org">';
  }
  else {
    $_delegate_ch = '';
  }
  
	$dat .= '<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<meta name="robots" content="' . META_ROBOTS . '">
<meta name="description" content="' . $page_description . '">
<meta name="keywords" content="' . $page_keywords . '">
<meta name="viewport" content="width=device-width,initial-scale=1">
' . $rta . '
' . $favicon . '
' . $css . '
' . $canonical . '
' . $rss . $_delegate_ch . '
<title>' . $page_title . '</title>' . $scriptjs . $extra;

	$embedearly = EMBEDEARLY;

	$adembedearly = AD_EMBEDEARLY;
	
  if (AD_ADBLOCK_TEXT && (DEFAULT_BURICHAN || BOARD_DIR === 'pol' || BOARD_DIR === 'bant')) {
	  $adembedearly .= file_get_contents_cached(AD_ADBLOCK_TEXT);
	}
	
	$board_class = 'board_' . BOARD_DIR;
	
	if (!$res) {
	  if ($is_arclist) {
	    $board_class = 'is_arclist ' . $board_class;
	  }
	  else {
	    $board_class = 'is_index ' . $board_class;
	  }
	}
	else {
	  $board_class = 'is_thread ' . $board_class;
	}
	
	if (TEXT_ONLY) {
	  $board_class = 'text_only ' . $board_class;
	}
	
	if (!$is_arclist) {
	  $abovePostForm = '<hr class="abovePostForm">';
	}
	else {
	  $abovePostForm = '';
	}
  
	$dat .= <<<HTML
$embedearly
$adembedearly
<noscript><style type="text/css">#postForm { display: table !important; }#g-recaptcha { display: none; }</style></noscript>
</head>
<body class="$board_class">
<span id="id_css"></span>
$stylejs
$includenav

<div class="boardBanner">
	$titlepart
	<div class="boardTitle">$title</div>
	$subtitle
</div>
$abovePostForm
HTML;
  
  if (!$error && !$is_arclist) {
    /*
    if (defined('ADS_BIDGLASS_TOP_MOBILE') && ADS_BIDGLASS_TOP_MOBILE) {
      $dat .= '<div class="adg-rects mobile"><div class="ad-bgls adp-250 bidglass-unit-' . ADS_BIDGLASS_TOP_MOBILE . '" data-m data-u="' . ADS_BIDGLASS_TOP_MOBILE . '" style="pointer-events: none;"></div><div class="adl">[<a target="_blank" href="https://www.4chan.org/advertise">Advertise on 4chan</a>]</div><hr class="belowLeaderboard"></div>';
    }
    else if (defined('AD_ABC_TOP_MOBILE') && AD_ABC_TOP_MOBILE) {
      $dat .= '<div class="adg-rects mobile"><div class="adg-m adp-250" data-abc="' . AD_ABC_TOP_MOBILE . '"></div><hr class="belowLeaderboard"></div>';
    }*/
  }
}

function delete_uploaded_files()
{
	global $upfile_name, $upfile, $dest, $pchfile;
	if( $dest || $upfile ) {
		@unlink( $dest );
		@unlink( $upfile );
	}
}

/* Footer */
function foot( &$dat, $error = false, $is_arclist = false )
{
	global $update_avg_secs;
	
  $includenav = file_get_contents_cached(NAV2_TXT);
  
	$dat .= $includenav;
	$dat .= rebuildd_stats();

	if( CODE_TAGS ) {
		$dat .= '<script type="text/javascript" src="'
		. STATIC_SERVER . 'js/prettify/prettify.'
		. JS_VERSION . '.js"></script><script type="text/javascript">prettyPrint();</script>';
	}

	$dat .= EMBEDLATE . '</body></html>';
}

function error($mes, $unused = '') {
	global $mode;
	
  if (isset($_SERVER['HTTP_ACCEPT']) && $_SERVER['HTTP_ACCEPT'] === 'application/json') {
    error_json($mes);
  }
  
	if( $mode == "report" ) fancydie( $mes );
	
	delete_uploaded_files();
	
	head( $dat, 0, 1 );
	
	$protocol = (stripos($_SERVER["HTTP_REFERER"], "https") === 0) ? 'https:' : 'http:';
	
	$dat .= '<table style="text-align: center; width: 100%; height: 300px;"><tr valign="middle"><td align="center" style="font-size: x-large; font-weight: bold;"><span id="errmsg" style="color: red;">' . $mes . '</span><br><br>[<a href=';
	
	if (preg_match('#^' . $protocol . '//boards.' . L::d(BOARD_DIR) . '/' . BOARD_DIR . '/thread/([0-9]+)#', $_SERVER["HTTP_REFERER"], $m)) {
	  $thread_part = 'thread/' . (int)$m[1];
	}
	else {
	  $thread_part = '';
	}
	
	$dat .= $protocol . '//boards.' . L::d(BOARD_DIR) . '/' . BOARD_DIR . '/' . $thread_part . ">" . S_RELOAD . "</a>]</td></tr></table><br><br><hr size=1>";
	
	foot( $dat, true );
	
	if (TEST_BOARD==1) {
		internal_error_log("post error", $mes);
	}
	
	die( $dat );
}

function error_json($msg) {
  delete_uploaded_files();
  
  header('Content-Type: application/json');
  
  echo json_encode(['error' => $msg]);
  
  die();
}

function error_redirect($mes, $redirect, $timeout = 3000) {
	delete_uploaded_files();
	head( $dat, 0, 1 );
	$dat .= <<<HTML
<script type="text/javascript">
  setTimeout(function() { window.location = "$redirect"; }, $timeout);
</script>
HTML;
	$dat .= '<table style="text-align: center; width: 100%; height: 300px;"><tr valign="middle"><td align="center" style="font-size: x-large; font-weight: bold;"><span id="errmsg" style="color: red;">' . $mes . '</span><br><br>[<a href=';
	$dat .= '//boards.' . L::d(BOARD_DIR) . '/' . BOARD_DIR . "/>"
    . S_RELOAD . "</a>]</td></tr></table><br><br><hr size=1>";
	foot( $dat );
	
	if (TEST_BOARD==1) {
		internal_error_log("post error", $mes);
	}
	
	die( $dat );
}

/* Auto Linker */
function normalize_link_cb( $m )
{

	$subdomain = $m[1];
	$original  = $m[0];
	$board     = strtolower( $m[2] );
	$m[0]      = $m[1] = $m[2] = '';

	$count = count( $m ) - 1;
	for( $i = $count; $i > 2; $i-- ) {
		if( $m[$i] ) {
			$no = $m[$i];
			break;
		}
	}

	if( $subdomain != 'boards') {
		return $original;
	}

	if( stripos( $no, 'catalog' ) === 0 ) {

		if( ( $pos = stripos( $no, '#s=' ) ) !== false ) {
			$term = substr( $no, $pos + 3 );
		} else {
			$term = 'catalog';
		}

		return "&gt;&gt;&gt;/$board/$term";
	}

	if( $board == BOARD_DIR && $no && $no != 'catalog' ) {
		return "&gt;&gt;$no";
	} else {
		return "&gt;&gt;&gt;/$board/$no";
	}
}

function normalize_links( $proto )
{
	// change http://xxx.4chan.org/board/res/no links into plaintext >># or >>>/board/#
	$proto = preg_replace_callback( '@https?://([a-z]*)[.](?:4chan|4channel)[.]org/(\w+)/(?:(res|thread)/(\d+)(?:\/[-a-z0-9]+)?(?:#[qp]?(\d*))?|(catalog(?:#s=[a-z0-9+]+)?)|\w+.php[?]res=(\d+)(?:#[qp]?(\d*))?|)(?=[\s.<!?,]|$)@i', 'normalize_link_cb', $proto );

	return $proto;
}

// TODO merge with get_resto_for
function post_resto($no) {
  global $log;
  
  if (isset($log[$no])) {
    return $log[$no]['resto'];
  }
  
  $q = mysql_board_call('SELECT resto FROM `%s` WHERE no=%d', SQLLOG, $no);
  if (!mysql_num_rows($q)) {
    $log[$no] = array('resto' => false);
    return false;
  }
  $r = (int)mysql_fetch_row($q)[0];
  $log[$no] = array('resto' => $r);
  return $r;
  
  return false;
}

// FIXME
// This might not be used anywhere anymore
function intraboard_link_cb( $m )
{
	global $intraboard_cb_resno, $log;
	$no    = $m[1];
	$resno = $intraboard_cb_resno;
	$resto = post_resto( $no ); // doesn't like assignment in condition
	if( $resto !== false ) {
		$resdir = ( $resno ? '' : RES_DIR2 );
		$ext    = PHP_EXT2;
		$id     = NEW_HTML ? "p$no" : "$no";
		if( $resno && $resno == $resto ) // linking to a reply in the same thread
			return "<a href=\"#$id\" class=\"quotelink\">&gt;&gt;$no</a>";
		elseif( $resto == 0 ) // linking to a thread
			return "<a href=\"$resdir$no$ext#$id\" class=\"quotelink\">&gt;&gt;$no</a>"; else // linking to a reply in another thread
			return "<a href=\"$resdir$resto$ext#$id\" class=\"quotelink\">&gt;&gt;$no</a>";
	}

	return '<span class="deadlink">' . $m[0] . '</span>';
}

// FIXME
// This might not be used anywhere anymore, see parse_intraboard_link()
function intraboard_links( $proto, $resno )
{
	global $intraboard_cb_resno;

	$intraboard_cb_resno = $resno;

	$proto = preg_replace_callback( '/&gt;&gt;([0-9]+)/', 'intraboard_link_cb', $proto );

	return $proto;
}

function other_board_resto( $board, $resno )
{
	// this function is a little slow
	// board requirements - either is the current board (for /test/) or is public (in boardlist)
	// returns -
	// FALSE if resno does not exist
	// 0 if resno is a thread
	// an id if resno is a reply to a thread

	static $boardlist = array();

	if( !$boardlist )
		$boardlist = array_flip( mysql_column_array( mysql_global_call( "select sql_cache dir from boardlist" ) ) );

	if( $board != BOARD_DIR && !isset( $boardlist[$board] ) )
		return false;

	$q = mysql_board_call( "select resto from `%s` where no=%d", $board, $resno );
	if( !mysql_num_rows( $q ) )
		return false;
	$r = mysql_result( $q, 0 );

	return $r;
}

function interboard_link_cb( $m )
{
	// on one hand, we can link to imgboard.php, using any old subdomain,
	// and let apache & imgboard.php handle it when they click on the link
	// on the other hand, we can use the database to fetch the proper subdomain
	// and even the resto to construct a proper link to the html file (and whether it exists or not)

	// for now, we'll assume there's more interboard links posted than interboard links visited.
	$board      = '/';
	$otherboard = mb_strtolower( $m[1] );
	if( $m[2] ) {
		$resto = other_board_resto( $otherboard, $m[2] );
		$id    = "#p";

		if( $resto === false )
			$url = "";
		else if( $resto )
			$url = $board . $otherboard . '/thread/' . $resto . $id . $m[2];
		else
			$url = $board . $otherboard . '/thread/' . $m[2] . $id . $m[2];
	} else    $url = $board . $otherboard . '/';

	$b        = $m[1];
	$mlp_hack = BOARD_DIR == 'mlp' && ( $b == 'b' || $b == 'co' );
	$original = mb_strtolower( $m[0] );
	if( !$url || $mlp_hack )
		return '<span class="deadlink">' . $original . '</span>';
	else
		return "<a href=\"$url\" class=\"quotelink\">{$original}</a>";
}

function interboard_catalog_link_cb( $m )
{
	$board        = $m[1];
	if( $board == 'f' ) return $m[0];

	$lsearchquery = strtolower( urlencode( urldecode( $m[2] ) ) );
	$original     = mb_strtolower( str_replace( "&gt;", "&gt;&nbsp;", $m[0] ) );

	if( $lsearchquery == "catalog" ) {
		return "<a href=\"/$board/catalog\" class=\"quotelink\">$original</a>";
	} elseif( $lsearchquery == 'rules' ) {
		return '<a href="//www.' . L::d($board) . '/rules#' . $board . '" class="quotelink">' . $original . '</a>';
	} else {
		return "<a href=\"/$board/catalog#s=$lsearchquery\" class=\"quotelink\">$original</a>";
	}
}

function boards_matching_arr()
{
	static $boards_matching_arr;
	global $valid_boards;
	
	if( empty( $boards_matching_arr ) ) $boards_matching_arr = explode( '|', $valid_boards );

	return $boards_matching_arr;
}

// Normalize and linkify internal and non-quote links
// before inserting the post into the database.
function normalize_and_linkify($proto) {
  
	if (strpos($proto, "4chan") !== false || strpos($proto, "4cdn.org") !== false) {
		// normalize long links
		$proto = normalize_links($proto);
		
		// linkify other internal links
		if ((strpos($proto, "4chan") !== false && strpos($proto, "/derefer") === false) || strpos($proto, "4cdn.org") !== false) {
			$proto = preg_replace_callback( '/(https?:\/\/(?:[A-Za-z]*\.)?)(4chan|4channel|4cdn)(\.org)(\/[\w\-\.,@?^=%&;:\/~\+#\(\)]*[\w\-\@?^=%&;\/~\+#])?/i', 'clean_internal_link', $proto );
			$proto = preg_replace( '/([<][^>]*?)<a href="((https?:\/\/(?:[A-Za-z]*\.)?)(4chan|4channel|4cdn)(\.org)(\/[\w\-\.,@?^=%&:\/~\+#\(\)]*[\w\-\@?^=%&\/~\+#])?)" target="_blank">\\2<\/a>([^<]*?[>])/i', '\\1\\3\\4\\5\\6\\7\\8', $proto );
		}
	}
	
	if (strpos($proto, '&gt;&gt;&gt;') !== false) {
		$proto = preg_replace_callback('#&gt;&gt;&gt;/([a-z0-9]+)/([a-z0-9+/,l\-]*)#', 'auto_link_static_cb', $proto);
	}
	
	return $proto;
}

// Removes >> links from internal 4chan.org links
function clean_internal_link($matches) {
	$link = preg_replace('/&gt;&gt;&gt;|&gt;&gt;/', '', $matches[0]);
	return "<a href=\"$link\" target=\"_blank\">$link</a>";
}

function auto_link_static_cb($matches) {
	$post = $matches[0];
	$inter_board = $matches[1];
	$no = $matches[2];
	
	$boards_matching_arr = boards_matching_arr();
	
	$full_link = $post;
	
	$inter_board = strtolower( $inter_board );
	
	$is_board_link = ($no == '');

	$resno = $no;
	
	// Text boards
	// Catalog, rules, and /rs/ links
	if (!is_numeric( $resno ) || $is_board_link) {
		$url = urlencode( urldecode( $resno ) );
		
		$target = '';
		
		if( strpos( $resno, 'rules' ) === 0 ) {
			$ruleno  = '';
			$ruleloc = strpos( $resno, '/' );
			
			if( $ruleloc !== false ) {
				$ruleno = substr( $resno, $ruleloc + 1 );
			}
			
			$parsed_link = '//www.' . L::d($inter_board) . "/rules#$inter_board$ruleno";
			$target      = ' target="_blank"';
		}
		else if (in_array($inter_board, $boards_matching_arr)) {
			$parsed_link = '//boards.' . L::d($inter_board) . "/$inter_board/";
			
			if( $inter_board == 'f' && $url == 'catalog' ) return $full_link;
			
			if( !$is_board_link ) {
				$parsed_link .= ($url == 'catalog' ? 'catalog' : "catalog#s=$url");
			}
		}
		else {
		  return $full_link;
		}
		
		return '<a href="' . $parsed_link . '" class="quotelink"' . $target . '>' . $full_link . '</a>';
	}
	
	return $full_link;
}

function auto_link( $proto, $resno )
{
	global $current_resno;
	static $has_gen = 0;

	if( !$has_gen ) {
		boards_matching_arr();
		$has_gen = 1;
	}
		
	// The majority of posts don't contain links, so don't go there and waste time on preg junk
	if( strpos( $proto, '&gt;&gt;' ) !== false ) {
		$current_resno = $resno;
		$proto = preg_replace_callback( '#(&gt;&gt;[0-9]+|&gt;&gt;&gt;/[a-z0-9]+/[a-z0-9+/-]*)#', 'auto_link_cb', $proto );
	}
	
	return $proto;
}

function auto_link_cb( $post )
{
	global $current_resno;

	//var_dump($post);
	$is_inter = ( strpos( $post[0], '&gt;&gt;&gt;/' ) === 0 );
	$post     = $post[0];

	if( $is_inter ) {
		preg_match( '#&gt;&gt;&gt;/([a-z0-9]+)/(.*)#', $post, $match );
		
		return parse_interboard_link( $match[0], $match[1], $match[2] );
	} else {
		$no = explode( '&gt;&gt;', $post );

		return parse_intraboard_link( $post, $no[1], $current_resno );
	}
}

function parse_intraboard_link( $post, $no, $resno )
{
	$full_link = $post;
	//$no        = substr( $post, $i - $in_link_char, $in_link_char );
	$resto = post_resto( $no );

	if( $resto === false ) {
		$parsed_link = '<span class="deadlink">' . $full_link . '</span>';

		return $parsed_link;
	}

	$ext    = PHP_EXT2;
	$id     = NEW_HTML ? "p$no" : "$no";
  
  // linking to a reply or the OP in the same thread
  if ($resno && ($resno == $resto || $resno == $no)) { 
    $parsed_link = "<a href=\"#$id\" class=\"quotelink\">&gt;&gt;$no</a>";
  }
  // linking to an OP in another thread or from indexes
  elseif ($resto == 0) {
    $parsed_link = "<a href=\"/" . BOARD_DIR . "/" . RES_DIR2 . "$no$ext#$id\" class=\"quotelink\">&gt;&gt;$no</a>";
  }
  // linking to a reply in another thread or from indexes
  else {
    $parsed_link = "<a href=\"/" . BOARD_DIR . "/" . RES_DIR2 . "$resto$ext#$id\" class=\"quotelink\">&gt;&gt;$no</a>";
  }
  
	return $parsed_link;
}

function parse_interboard_link( $post, $inter_board, $no )
{
	global $valid_boards;

	// make sure to account for &gt; not >
	$full_link   = $post;
	$inter_board = strtolower( $inter_board );

	// Are we a board link?
	$is_board_link = ( $no == '' );

	// ... to get resno!
	$resno = $no;

	$valid_rule = $inter_board == 'global' && strpos( $resno, 'rules' ) !== false;
	
	// Skip static links (boards, catalog)
	if ($is_board_link
		|| $valid_rule
		|| !is_numeric($resno)
		|| !in_array($inter_board, boards_matching_arr())
		) {
		return $full_link;
	}
	
	// Valid board, now check post number
	$resto = other_board_resto( $inter_board, $resno );

	if( $resto === false ) { // dead link
		$url = '';
	} elseif( $resto ) { // different thread
		$url = '//boards.' . L::d($inter_board) . "/{$inter_board}/thread/$resto#p$resno";
	} else { // same thread
		$url = '//boards.' . L::d($inter_board) . "/{$inter_board}/thread/$resno#p$resno";
	}

	$disable = BOARD_DIR == 'mlp' && ( $inter_board == 'b' || $inter_board == 'co' );
	if( !$url || $disable ) {
		$parsed_link = '<span class="deadlink">' . $full_link . '</span>';
	} else {
		$parsed_link = "<a href=\"$url\" class=\"quotelink\">$full_link</a>";
	}

	return $parsed_link;
}

function trans_same_board_links( &$com )
{
	$match    = '>>>/' . BOARD_DIR . '/';
	$len      = strlen( $match );
	$i        = stripos( $com, $match );
	$boardlen = strlen( BOARD_DIR ) - 1;
	$dir      = BOARD_DIR;
	$com .= '~';

	while( isset( $com{$i} ) ) {

		if( is_numeric( $com{$i + $len} ) ) {
			// Match, replace out
			$com = substr_replace( $com, '>>', $i, $len );

		}

		$i = stripos( $com, $match, $i + $len );
		if( $i === false ) {
			$com = substr( $com, 0, strlen( $com ) - 1 );

			return;
		}
	}
}

function auto_link_parser( $post, $resno )
{
	$post .= '<';

	$i            = 0;
	$in_link      = false;
	$in_link_char = 0;

	$is_inter          = false;
	$inter_found_board = false;
	$inter_board       = '';
	$inter_is_catalog  = false;

	$is_intra = false;
	$gt_count = 0;
	$mbcl     = 10; // forgot about dis links :(

	$dbg = "";

	while( isset( $post{$i} ) ) {
		$seen_gt_this = false;
		$c            = $post{$i};

		if( !$in_link ) {
			// Not in a link, find &gt;

			if( $c == '&' ) {
				if( $post{$i + 1} == 'g' && $post{$i + 2} == 't' && $post{$i + 3} == ';' ) {
					if( $gt_count < 3 ) $gt_count++;

					$i = $i + 4;
					continue;
				}
			}

			if( ( $c == '/' && $gt_count == 3 ) || ( $gt_count > 1 && is_numeric( $c ) ) ) {
				$in_link_char = 0;
				$in_link      = true;
				$i--; // shift us back a char to get the right match...
			}

			$gt_count = 0;
		} else {
			// We can be sure we have a valid character for our link
			if( $in_link_char == 0 ) {

				if( $c == '/' ) {
					$is_inter = true;
					$is_intra = false;
				} else {
					$is_intra = true;
					$is_inter = false;
				}
			}

			if( $is_inter ) {

				if( $in_link_char == 0 ) {
					$in_link_char++;
					$i++;
					continue;
				}

				if( $in_link_char > $mbcl && $c != '/' && !$inter_found_board ) {
					// yup :(

					$in_link  = false;
					$is_inter = false;

					$i++;
					continue;
				}

				if( !$inter_found_board && $c == '/' ) {
					$inter_board = substr( $post, $i - ( $in_link_char - 1 ), $in_link_char - 1 );

					$inter_found_board = true;
					$in_link_char++;
					$i++;
					continue;
				}

				if( $inter_found_board ) {
					$match = (
						ctype_alnum( $c ) ||
							$c == '+' ||
							$c == '/' ||
							$c == '-'
					);

					if( $match ) {
						$in_link_char++;
						$i++;
						continue;
					}
				}

				if( !$inter_found_board ) {
					$in_link_char++;
					$i++;
					continue;
				}


				parse_interboard_link( $post, $i, $in_link_char, $inter_board );

				$is_inter          = false;
				$in_link           = false;
				$inter_found_board = false;
			}

			if( $is_intra ) {
				if( is_numeric( $c ) ) {
					$in_link_char++;
				} else {
					// reached the end
					parse_intraboard_link( $post, $i, $in_link_char, $resno );

					$in_link  = false;
					$is_intra = false;
				}
			}
		}

		$i++;
	}

	return substr( $post, 0, strlen( $post ) - 1 ) . $dbg;
}

/**
 * New version of check_blacklist()
 * $post must be an array with the following fields:
 * resto, filename, name, tripcode, password, 4pass_id
 */
function check_md5_blacklist($md5, $original_md5, $post, $dest) {
  if (!$md5) {
    return false;
  }
  
  $board = BOARD_DIR;
  
  if (DEFAULT_BURICHAN) {
    $ws_clause = " OR boardrestrict = '_ws_'";
  }
  else {
    $ws_clause = '';
  }
  
  $sql =<<<SQL
SELECT SQL_NO_CACHE * FROM blacklist
WHERE active = 1 AND (boardrestrict = '' OR boardrestrict = '$board'$ws_clause)
AND field = 'md5' AND contents = '%s' LIMIT 1
SQL;
  
  // Check MD5
  $query = mysql_global_call($sql, $md5);
  
  // Check original MD5 if provided
  if (!mysql_num_rows($query)) {
    if ($original_md5 && $original_md5 !== $md5) {
      $query = mysql_global_call($sql, $original_md5);
      
      if (!mysql_num_rows($query)) {
        return false;
      }
      
      $md5 = $original_md5;
    }
    else {
      return false;
    }
  }
  
  $row = mysql_fetch_assoc($query);
  
  if (!$row) {
    return false;
  }
  
  // Private reason
  $private_reason = "Blacklisted md5 - " . htmlspecialchars($md5)
    . ' - Filename: ' . htmlspecialchars($post['filename']);
  
  // Ban name
  if (isset($post['name'])) {
    $ban_name = $post['name'];
  }
  else {
    $ban_name = S_ANONAME;
  }
  
  if (isset($post['tripcode'])) {
    $ban_name .= " #{$post['tripcode']}";
  }
  
  // Ban password
  if (isset($post['password']) && $post['password']) {
    $pwd = $post['password'];
  }
  else {
    $pwd = null;
  }
  
  // Ban 4chan pass
  if (isset($post['4pass_id']) && $post['4pass_id']) {
    $pass_id = $post['4pass_id'];
  }
  else {
    $pass_id = null;
  }
  
  // Thread id for redirection
  if (isset($post['resto'])) {
    $resto = (int)$post['resto'];
  }
  else {
    $resto = 0;
  }
  
  // --------
  
  // Reject
  if (!$row['ban']) {
    if (TEST_BOARD) {
      error($private_reason, $dest);
    }
  }
  // Auto-ban
  else if ($row['ban'] == '1') {
    auto_ban_poster($ban_name, $row['banlength'], 1, $private_reason, $row['banreason'], false, $pwd, $pass_id);
  }
  // Show error (DMCA requests)
  else if ($row['ban'] == '2') {
    $ip = ip2long($_SERVER['REMOTE_ADDR']);
    
    $query = "SELECT ip FROM user_actions WHERE action = 'fail_dmca' AND ip = %d AND time >= DATE_SUB(NOW(), INTERVAL 1 DAY)";
    
    $res = mysql_global_call($query, $ip);
    
    if ($res && mysql_num_rows($res) > 0) {
      $private_reason = "DMCA complaint from {$row['description']} (blacklist ID: {$row['id']})";
      auto_ban_poster($ban_name, 3, 1, $private_reason, S_DMCABANREASON, true, $pwd, $pass_id);
      error_redirect(S_BANNED, 'https://www.' . L::d(BOARD_DIR) . '/banned');
    }
    else {
      $query = "INSERT INTO user_actions (board,postno,ip,time,uploaded,action) VALUES ('%s', %d, %d, NOW(), 0, 'fail_dmca')";
      mysql_global_call($query, $board, 0, $ip);
    }
    
    error(S_DMCAFAIL, $dest);
  }
  
  if ($row['quiet']) {
    show_post_successful_fake($resto);
    die();
  }
  
  error(S_FAILEDUPLOAD, $dest);
}

function check_blacklist($post, $dest, $file_ext = '', $resto = 0, $pwd = null, $pass_id = null) {
	//if( has_level() ) return;
	
	$board    = BOARD_DIR;
	
  if (DEFAULT_BURICHAN) {
    $ws_clause = " OR boardrestrict = '_ws_'";
  }
  else {
    $ws_clause = '';
  }
  
	$querystr = "SELECT SQL_NO_CACHE * FROM blacklist WHERE active=1 AND (boardrestrict='' or boardrestrict='$board'$ws_clause) AND (0 ";
	foreach( $post as $field => $contents ) {
		if( $contents ) {
			$contents = mysql_real_escape_string( html_entity_decode( $contents ) );
			$querystr .= "OR (field='$field' AND contents='$contents') ";
		}
	}
	$querystr .= ") LIMIT 1";
	
	$query = mysql_global_call( $querystr );
	if( mysql_num_rows( $query ) == 0 ) return false;
	
	$row       = mysql_fetch_assoc( $query );
	$prvreason = "Blacklisted ${row['field']} - " . htmlspecialchars( $row['contents'] );
	
	if ($row['field'] == 'md5') {
		$prvreason .= ' - Filename: ' . htmlspecialchars($post['filename']) . $file_ext;
	}
	
  if (!$row['ban']) {
    if (TEST_BOARD) {
      error( "Blacklisted: " . $prvreason, $dest );
    }
  }
  // Auto-ban
  else if ($row['ban'] == '1') {
    auto_ban_poster($post['trip'] ? $post['nametrip'] : $post['name'], $row['banlength'], 1, $prvreason, $row['banreason'], false, $pwd, $pass_id);
  }
  // Show error (DMCA requests)
  else if ($row['ban'] == '2') {
    $ip = ip2long($_SERVER['REMOTE_ADDR']);
    
    $query = "SELECT ip FROM user_actions WHERE action = 'fail_dmca' AND ip = %d AND time >= DATE_SUB(NOW(), INTERVAL 1 DAY)";
    
    $res = mysql_global_call($query, $ip);
    
    if ($res && mysql_num_rows($res) > 0) {
      $prvreason = "DMCA complaint from {$row['description']} (blacklist ID: {$row['id']})";
      
      auto_ban_poster($post['trip'] ? $post['nametrip'] : $post['name'], 3, 1, $prvreason, S_DMCABANREASON, true, $pwd, $pass_id);
      
      error_redirect(S_BANNED, 'https://www.' . L::d(BOARD_DIR) . '/banned');
    }
    else {
      $query = "INSERT INTO user_actions (board,postno,ip,time,uploaded,action) VALUES ('%s',%d,%d,NOW(),0,'fail_dmca')";
      mysql_global_call($query, $board, 0, $ip);
    }
    
    error(S_DMCAFAIL, $dest);
  }
  /*
	{
		$ip = $_SERVER['REMOTE_ADDR'];
		quick_log_to("/www/perhost/blacklist.log", "IP $ip board /$board/: $prvreason");
	}
  */
  
  if ($row['quiet']) {
    show_post_successful_fake($resto);
    die();
  }
  
	error(S_FAILEDUPLOAD, $dest);
}

// we've already failed the floodcheck, check if they're a repeat offender and ban them
function check_fail_floodcheck($info)
{
	$ip = ip2long($_SERVER['REMOTE_ADDR']);
	mysql_global_call("insert into user_actions (ip,board,action,time) values (%d,'%s','fail_floodcheck',now())", $ip, '');
	$query = mysql_global_call("select count(*)>%d from user_actions where ip=%d and action='fail_floodcheck' and time >= subdate(now(), interval 1 hour)", LOGIN_FAIL_HOURLY, $ip);
	quick_log_to("/www/perhost/floodchecks.log", $info);
	if(mysql_result($query,0,0)) {
		auto_ban_poster("Anonymous", 1, 1, "got a flood check warning 5 times in an hour", "Sending an excessive number of server requests");
	}
}

// word-wrap without touching things inside of tags
function wordwrap2( $str, $cols, $cut )
{
	// if there's no runs of $cols non-space characters, wordwrap is a no-op
	if( mb_strlen( $str ) < $cols || !preg_match( '/[^ <>]{' . $cols . '}/', $str ) ) {
		return $str;
	}
	$sections = preg_split( '/[<>]/', $str );
	$str      = '';
	for( $i = 0; $i < count( $sections ); $i++ ) {
		if( $i % 2 ) { // inside a tag
			$str .= '<' . $sections[$i] . '>';
		} else { // outside a tag
			$words   = explode( ' ', $sections[$i] );/*
			$exclude = array(
				'http://',
				'https://',
				'www.'
			);
*/
			foreach( $words as &$word ) {/*
				foreach( $exclude as $match ) {
					if( stripos( $word, $match ) === 0 && stripos( $word, '4chan.org' ) !== false ) continue 2;
				}*/

				$word = htmlspecialchars_decode( $word, ENT_QUOTES );
				$word = utf8_wordwrap( $word, $cols, $cut, true );
				$word = htmlspecialchars( $word, ENT_QUOTES );

			}

			$str .= implode( ' ', $words );
		}
	}

	return $str;
}

function logtime( $desc )
{
	static $run = -1;
	if( !PROFILING ) return;
	if( $run == -1 ) {
		$run = getmypid_cached();
	}
	$board = BOARD_DIR;
	$time  = microtime( true );
	mysql_global_call( "INSERT INTO profiling_times VALUES ('$board',$run,$time,'$desc')" );
}

function time_log($r) {
  if (TEST_BOARD && $_SERVER['HTTP_ACCEPT'] !== 'application/json') {
    echo "<!-- $r " . microtime(true) . " " . memory_get_usage(true) . " -->\n";
  }
}

function is_bad_xff( $xff )
{
  if ($xff === '8.8.8.8' || $xff === '62.210.138.29' || $xff === '212.129.0.228') {
    return true;
  }
  
	list( $xffs ) = post_filter_get( "xffwhitelist" );
	$ipnum = ip2long( $xff );

	if( !$ipnum ) return true; // text in xff field

	return find_ipxff_in( 0, $ipnum, $xffs );
}

function has_doubles( $id )
{
	if( $id % 1000 == 0 ) return false;

	$ones = $id % 10;
	$tens = ( $id / 10 ) % 10;

	return $ones == $tens;
}

function generate_uid($resto, $time, $ip = false) {
  if (DISP_ID_RANDOM) {
    $str = mt_rand();
  }
  else {
    $str = !$ip ? $_SERVER["REMOTE_ADDR"] : $ip;
    
    if (DISP_ID_PER_THREAD) {
      $str .= $resto ? $resto : date( 'Ymd', $time );
    } else {
      $str .= 'hats'; // we will put a hat on it to confuse people :)
    }
  }

	$salt = file_get_contents_cached( SALTFILE );
	$hash = base64_encode( pack( "H*", sha1( $str . $salt ) ) );

	return substr( $hash, 0, 8 );
}

function parse_vip_capcode($capcode) {
    // Flood check
    $longip = ip2long($_SERVER['REMOTE_ADDR']);
    
    $query = <<<SQL
SELECT COUNT(*) FROM user_actions
WHERE ip = %d AND action = 'fail_login'
AND time >= SUBDATE(NOW(), INTERVAL 1 HOUR)
SQL;
    
    $res = mysql_global_call($query, $longip);
    
    if (!$res) {
      return false;
    }
    
    $count = mysql_fetch_row($res)[0];
    
    if ($count >= 3) {
      return false;
    }
    
    // Now check the capcode
    list($_, $user_id, $user_key) = explode('!', $capcode, 3);
    
    if (!$user_id || !$user_key) {
      return false;
    }
    
    $query = "SELECT name, user_key FROM vip_capcodes WHERE active = 1 AND user_id = '%s' LIMIT 1";
    
    $res = mysql_global_call($query, $user_id);
    
    if (!$res) {
      return false;
    }
    
    $user = mysql_fetch_assoc($res);
    
    if ($user && password_verify($user_key, $user['user_key'])) {
      $query = "UPDATE vip_capcodes SET last_used = %d, last_ip = '%s' WHERE user_id = '%s' LIMIT 1";
      mysql_global_call($query, $_SERVER['REQUEST_TIME'], $_SERVER['REMOTE_ADDR'], $user_id);
      return $user['name'];
    }
    
    // Log the failure
    $query = <<<SQL
INSERT INTO user_actions (ip, board, action, time)
VALUES (%d, '', 'fail_login', NOW())
SQL;
    
    mysql_global_call($query, $longip);
    
    return false;
}

function parse_capcode($capcode, &$name = null)
{
  if ($name !== null && strpos($capcode, 'capcode_!') === 0) {
    $vip_name = parse_vip_capcode($capcode);
    
    if ($vip_name !== false) {
      $name = $vip_name;
      return 'verified';
    }
    
    return 'none';
  }
  
	if (!has_level()) {
	  return 'none';
	}
	
	$user = strtolower( $_COOKIE['4chan_auser'] );
	
	$is_developer = has_flag( 'developer' ) && $capcode == 'capcode_dev';
	$is_mod    = ( has_level() && $capcode == 'capcode_mod' );
	$is_manager = has_level('manager') && $capcode == 'capcode_manager';
	
	$is_admin   = ( has_level( 'admin' ) && $capcode == 'capcode_admin' );
	$is_founder = ( has_level( 'admin' ) && $capcode == 'capcode_founder' );
	$highlight = ( has_level( 'admin' ) && $capcode == 'capcode_admin_hl' );
  
	if ($is_founder) {
	  return 'founder';
	}
	
	if( $is_admin || $highlight ) {
		return ( $highlight ) ? 'admin_highlight' : 'admin';
	}

	if( $is_developer ) {
		return 'developer';
	}
  
	if ($is_manager) {
		return 'manager';
	}
  
  if (!has_flag('capcode') && !has_level('manager')) {
    if ($capcode !== '') {
      error(S_CANTCAPCODE);
    }
  }
  
	if( $is_mod ) {
		return 'mod';
	}

	return 'none';
}

function generate_tim() {
  $time = $_SERVER['REQUEST_TIME'];
  $micro = substr(microtime(), 2, 4);
  $tail = mt_rand(0, 99);
  
  if ($tail < 10) {
    $tail = "0$tail";
  }
  
  return "$time$micro$tail";
}

/* Regist */
function new_post( $name, $email, $sub, $com, $url, $pwd, $upfile, $upfile_name, $resto, $age, $filetag )
{
	global $pwdc, $textonly, $admin, $spoiler, $dest, $pchfile, $word_filters_enabled, $silent_reject;
	global $captcha_bypass, $passid;
	global $board_flags_array;
  
	// Fro HTML and capcode posting
	$log_mod_action = false;
	$log_html_post = false;
	$log_capcode_post = false;
	
	$mes = "";

	/* VARIOUS CHECKS BEFORE ANY POSTING TAKES PLACE */
	
	$oldbanbuster = ( $_SERVER['HTTP_USER_AGENT'] == 'Mozilla/5.0 (compatible; MSIE 9.0; Windows NT 6.1; Trident/5.0)' && $com == 'test' );
	$newbanbuster = ( $_SERVER['HTTP_USER_AGENT'] == 'Mozilla/5.0 (Windows NT 5.1) AppleWebKit/537.19 (KHTML, like Gecko) Chrome/25.0.1324.0 Safari/537.19' && ( preg_match( '#^[a-zA-Z]{15}$#', $com ) || $com == 'test' ) && !isset( $_COOKIE['4chan_pass'] ) );
	
	if( BOARD_DIR == 'b' && ( $oldbanbuster || $newbanbuster ) ) {
		$msg = $com == 'test' ? 'com = test' : 'com = 15 length string';
		auto_ban_poster( "", -1, 1, 'BanBuster proxy test match (Posting ' . $msg . ' from dodgy UA)', 'Proxy/Tor exit node.' );
		error( S_NOCAPTCHA );
	}

	if( $_SERVER["REQUEST_METHOD"] != "POST" ) error( S_UNJUST );
	
	/* END CHECKS */
	
	$hasjs     = isset( $_POST['hasjs'] ) && $_POST['hasjs'] === 'yes';
	$stats_all = $hasjs ? 'with_js_all' : 'without_js_all';
	$stats_ok  = $hasjs ? 'with_js_success' : 'without_js_success';


	$host = $_SERVER["REMOTE_ADDR"];

	time_log( "start" );
	
	// might be better to do this before the mysql connection
	if( !$upfile && !$resto ) { // allow textonly threads for moderators!
		if( has_level() ) $textonly = 1;
	}
	elseif( JANITOR_BOARD == 1 ) { // only allow mods/janitors to post, and textonly is always ok
		$textonly = 1;
		if( !has_level( 'janitor' ) )
			die();
	}
	
	if (TEXT_ONLY && $upfile && $resto) {
	  error(S_TEXT_ONLY);
	}
	
	if (UPLOAD_BOARD) {
	  if ($upfile && $resto) {
	    error(S_FAILEDUPLOAD);
	  }
	  
		$tags = upboard_tags();
		
		if( !$resto && !array_key_exists( (int)$filetag, $tags ) ) error( "Error: Invalid tag specified.", $filetag );
		if( !$resto && !is_numeric( $filetag ) ) error( "Error: Invalid tag specified.", $filetag );
		//if((int)$filetag==9999) error("No tag selected.",$filetag);
		if( !$resto ) $sub = (int)$filetag . '|' . $sub;
		
		//OPs must have files	
		if (!$upfile && !$resto) {
			error(S_NOUPLOAD);
		}
	}
	
  // Password session
  $userpwd = new UserPwd($host, MAIN_DOMAIN, $pwdc);
  
  $pass = $userpwd->getPwd();
  
  if (!$pass) {
    error(S_GENERICERROR, $dest);
  }
  
	$resto = (int)$resto;
	
	// time
	$time = $_SERVER['REQUEST_TIME'];
	$tim  = generate_tim();
  
  $captcha_bypass_allow_credits = false;
  
  $memcached = null;
  
  if (isset($_POST['recaptcha_challenge_field'])) {
    error('Legacy captcha is no longer supported.');
  }
  else if (isset($_POST['g-recaptcha-response'])) {
    if (CAPTCHA_TWISTER) {
      error('reCAPTCHA v2 is no longer supported.');
    }
    
    // Recaptcha v2
    start_auth_captcha();

    if (!$captcha_bypass) {
      $_c_ret = end_recaptcha_verify();
    }
	}
	else {
		if (valid_captcha_bypass() !== true) {
			$memcached = create_memcached_instance();	
			
			$_unsolved_count = 0;
			
			// Captcha bypass credits
			if ($resto && isset($_POST['t-challenge']) && $_POST['t-challenge'] === 'noop') {
				if (use_twister_captcha_credit($memcached, $host, $userpwd) === false) {
					error(S_CAPTCHATIMEOUT);
				}
			}
			// Captcha verification failed
      else if (is_twister_captcha_valid($memcached, $host, $userpwd, BOARD_DIR, $resto, $_unsolved_count) === false) {
        // Silent captcha failure for new suspicious users
        //$_bad_actor = spam_filter_is_bad_actor();
        //$_threat_score = spam_filter_get_threat_score($_SERVER['HTTP_X_GEO_COUNTRY'], !$resto, true);
        if (isset($_SERVER['HTTP_X_BOT_SCORE'])) {
          $_bot_score = (int)$_SERVER['HTTP_X_BOT_SCORE'];
        }
        else {
          $_bot_score = 100;
        }
        
        if (!$userpwd || $userpwd->isUserKnownOrVerified(1440, 1) === false) {
          if ($_bot_score > 1 && $_bot_score < 80) {
            $_meta = spam_filter_format_http_headers(htmlspecialchars($com), $_SERVER['HTTP_X_GEO_COUNTRY'], $upfile_name, $_threat_score);
            if (isset($_POST['t-challenge']) && $_POST['t-response'] && isset($_COOKIE['_tcs'])) {
              log_failed_captcha($host, $userpwd, BOARD_DIR, $resto, true, $_meta);
            }
            show_post_successful_fake($resto, false);
            die();
          }
        }
        error(S_BADCAPTCHA);
      }
			// Captcha verification succeeded
			else if ($_unsolved_count < 2 && CAPTCHA_ALLOW_BYPASS && spam_filter_is_bad_actor() === false) {
				$captcha_bypass_allow_credits = true;
			}
		}
	}
	/*
  if (spam_filter_is_bad_actor()) {
    $_bot_headers = spam_filter_format_http_headers($com, $country, "$insfile$ext", $_threat_score, $_req_sig);
    log_spam_filter_trigger('log_bad_actor', BOARD_DIR, $resto, $host, 1, $_bot_headers);
  }
  */
	if (PASS_POST_ONLY && !$captcha_bypass) {
	  error(S_PASS_POST_ONLY);
	}
	
	// Validate 2FA if posting html
  if ((isset($_POST['html']) && $_POST['html']) && (has_level('manager') || has_flag('html') || has_flag('developer'))) {
    validate_otp();
    $log_html_post = $log_mod_action = true;
  }
	
	$locked_time = $time;
	// check closed
	if( $resto ) {
		if( !$cchk = mysql_board_call( "select closed,sticky,undead,archived,sub,com from `" . SQLLOG . "` where no=" . $resto ) ) {
			echo S_SQLFAIL;
		}
		list( $closed, $sticky, $undead, $is_archived, $_thread_sub, $_thread_com ) = mysql_fetch_row( $cchk );
		if ($is_archived) {
		  error(S_MAYNOTREPLY, $upfile);
		}
    	$is_undead_sticky = $sticky == 1 && $undead == 1;
		if( $closed == 1 && !has_level() ) error( S_MAYNOTREPLY, $upfile );
		mysql_free_result( $cchk );
		
		$sub = '';
	}
	else {
    $is_undead_sticky = false;
	}
  
	$has_image = $upfile && file_exists( $upfile );

  $md5 = null;
  $original_md5 = null; // MD5 before exif and other metadata stripping

	if( $has_image ) {
		if (UPLOAD_BOARD) {			
			if( file_exists( IMG_DIR . $upfile_name ) ) error( "Filename already exists.", $upfile );

			$dest = $upfile;

			$upfile_name = sanitize_text( $upfile_name );
			if( !is_file( $dest ) ) error( S_FAILEDUPLOAD, $dest );
			if ($upfile_name[0] === '.') {
				error('Error: Invalid filename (first character cannot be a period).', $dest);
			}
			$size = getimagesize( $dest );
			if( !is_array( $size ) ) error( S_NOREC, $dest );

			$W     = $size[0];
			$H     = $size[1];
			$fsize = filesize( $dest );
			if( $fsize > MAX_KB * 1024 ) error( S_TOOLARGE, $dest );
			if( $size[2] == 6 || $size[2] == 5 ) {
				error( S_FAILEDUPLOAD, $dest );
			}
			switch( $size[2] ) {
			case 4 :
			case 13 :
				$ext = ".swf";
			break;
			default :
				$ext = ".xxx";
				error( S_FAILEDUPLOAD, $dest );
			break;
			}
			
			time_log( "sfpi" );
			rpc_task();
			
			$len = strlen( $ext );
		}
    else {
			// NOT upload board
			
			// check image limit
			if( $resto && !$sticky && !$undead && !has_level() ) {
				if( !$result = mysql_board_call( "SELECT COUNT(*) FROM `" . SQLLOG . "` WHERE archived = 0 AND resto=$resto AND fsize!=0 AND filedeleted=0" ) ) {
					echo S_SQLFAIL;
				}
				$countimgres = mysql_result( $result, 0, 0 );
				if( $countimgres >= MAX_IMGRES && !has_level() ) error(S_MAXIMAGESREACHED, $upfile );
				mysql_free_result( $result );
			}

			//upload processing
			$dest = $upfile;
      
			// TODO: what does that preg_replace do? those are probably utf8 codes
			$upfile_name = sanitize_text( preg_replace('/\xe2\x80(\xae|\xad|\x8f|\x8e)/', '', $upfile_name) );
      
      if (!is_file($dest)) {
        error(S_FAILEDUPLOAD, $dest);
      }
      
      // Use filesize() later as it's possible to trick jpegtrans to generate a much bigger file
      $fsize = $_FILES['upfile']['size'];
      
      if (!$fsize || $fsize > MAX_KB * 1024) {
        error( S_TOOLARGE, $dest );
      }
      
			$webm_sar = null;

			// PDF processing
			if( ENABLE_PDF == 1 && strcasecmp( '.pdf', substr( $upfile_name, -4 ) ) == 0 ) {
				$ext = '.pdf';
				$W   = $H = 1;
				// run through ghostscript to check for validity
				if( pclose( popen( "/usr/local/bin/gs -q -dSAFER -dNOPAUSE -dBATCH -sDEVICE=nullpage $dest", 'w' ) ) ) {
					error( S_FAILEDUPLOAD, $dest );
				}
			}
			// Webm / MP4
			else if (ENABLE_WEBM && preg_match('/\.(webm|mp4)$/i', $upfile_name)) {
				if ($fsize > MAX_WEBM_FILESIZE * 1024) {
					error(S_TOOLARGE, $dest);
				}
				
				$original_md5 = md5_file($dest);
				
				$ext = '.' . strtolower(pathinfo($upfile_name, PATHINFO_EXTENSION));

				//if ($ext == '.mp4' && BOARD_DIR != 'test') {
				//	error(S_NOREC, $dest);
				//}

				$size = validate_webm($dest, $ext);
				$W = $size[0];
				$H = $size[1];
				$webm_sar = $size[2];
			}
			// PNG, GIF, JPEG
			else {
				$size = getimagesize( $dest );
				if( !is_array( $size ) ) {
					quick_log_to( "/www/perhost/bad-upload.log", "unrecognized file $upfile_name");

					error( S_NOREC, $dest );
				}

				$W = $size[0];
				$H = $size[1];
				switch( $size[2] ) {
				case 1 :
				$ext = ".gif";
				break;
				case 2 :
				$ext = ".jpg";
				break;
				case 3 :
				$ext = ".png";
				break;
				case 4 :
				$ext = ".swf";
				error( S_FAILEDUPLOAD, $dest );
				break;
				case 5 :
				$ext = ".psd";
				error( S_FAILEDUPLOAD, $dest );
				break;
				case 6 :
				$ext = ".bmp";
				error( S_FAILEDUPLOAD, $dest );
				break;
				case 7 :
				$ext = ".tiff";
				error( S_FAILEDUPLOAD, $dest );
				break;
				case 8 :
				$ext = ".tiff";
				error( S_FAILEDUPLOAD, $dest );
				break;
				case 9 :
				$ext = ".jpc";
				error( S_FAILEDUPLOAD, $dest );
				break;
				case 10 :
				$ext = ".jp2";
				error( S_FAILEDUPLOAD, $dest );
				break;
				case 11 :
				$ext = ".jpx";
				error( S_FAILEDUPLOAD, $dest );
				break;
				case 13 :
				$ext = ".swf";
				error( S_FAILEDUPLOAD, $dest );
				break;
				default :
				$ext = ".xxx";
				error( S_FAILEDUPLOAD, $dest );
				break;
				}
				if (GIF_ONLY == 1 && $size[2] != 1 && $ext != '.webm') error(S_FAILEDUPLOAD, $dest);
			} // end PDF processing -else

			// This doesn't seem to use the $md5 arg
			if ($upfile_name === '') {
				error('Blank file names are not supported.', $dest);
			}
			
			time_log( "sfpi" );
			rpc_task();

			// Picture reduction
			if( !$resto ) {
				$maxw = MAX_W;
				$maxh = MAX_H;
			} else {
				$maxw = MAXR_W;
				$maxh = MAXR_H;
			}
			if( defined( 'MIN_W' ) && MIN_W > $W ) error( S_TOOSMALL, $dest );
			if( defined( 'MIN_H' ) && MIN_H > $H ) error( S_TOOSMALL, $dest );
			if( defined( 'MAX_DIMENSION' ) )
				$maxdimension = MAX_DIMENSION;
			else
				$maxdimension = 5000;
			if( $W > $maxdimension || $H > $maxdimension ) {
				error( S_TOOLARGERES, $dest );
			} elseif( $W > $maxw || $H > $maxh ) {
				$W2 = $maxw / $W;
				$H2 = $maxh / $H;
				( $W2 < $H2 ) ? $key = $W2 : $key = $H2;
				$TN_W = ceil( $W * $key ) + 1;
				$TN_H = ceil( $H * $key ) + 1;
			}
      
      // Strip metadata, exif, comments and other embeddded extra data
      if ($ext === '.jpg') {
        // Embed detection is done later below if STRIP_EXIF is disabled
        if (STRIP_EXIF) {
          $original_md5 = md5_file($dest);
          
          if (strip_jpeg_exif($dest) === false) {
            error(S_FAILEDUPLOAD, $dest);
          }
          
          clearstatcache(true, $dest);
        }
      }
      else if ($ext === '.png') {
        $original_md5 = md5_file($dest);
        $_ret = strip_png_chunks($dest, MAX_KB * 1024);
        if ($_ret < 0) {
          if ($_ret === -2) {
            error('APNG format not supported.', $dest);
          }
          else {
            error(S_FAILEDUPLOAD, $dest);
          }
        }
        else if ($_ret > 0) {
          clearstatcache(true, $dest);
        }
      }
      else if ($ext === '.gif') {
        $original_md5 = md5_file($dest);
        $_ret = strip_gif_extra_data($dest, $fsize);
        if ($_ret < 0) {
          error(S_FAILEDUPLOAD, $dest);
        }
        clearstatcache(true, $dest);
      }
      
      // It should be safe to check the filesize now
      // clearstatcache() must be called if the file was modified
      $fsize = filesize($dest);
      
      if (!$fsize) {
        error(S_TOOLARGE, $dest);
      }
      else if ($ext === '.webm' && $fsize > MAX_WEBM_FILESIZE * 1024) {
        error(S_TOOLARGE, $dest);
      }
      else if ($fsize > MAX_KB * 1024) {
        error(S_TOOLARGE, $dest);
      }
      
      // Check for JPEG embedded data. jpegtran seems to remove unknown data
      // so this only needs to be done if STRIP_EXIF is disabled
      if (!STRIP_EXIF && $ext === '.jpg' && $fsize > 204800) {
        validate_jpeg_size($dest, $fsize);
      }
		}
		
		$insfile = preg_replace('/\.[a-z0-9]+$/i', '', $upfile_name);

		$md5 = md5_file( $dest );
		$mes = $upfile_name . ' ' . S_UPGOOD;
	}

	if( $_FILES["upfile"]["error"] > 0 ) {
		if( $_FILES["upfile"]["error"] == UPLOAD_ERR_INI_SIZE )
			error( S_TOOLARGE, $dest );
		if( $_FILES["upfile"]["error"] == UPLOAD_ERR_FORM_SIZE )
			error( S_TOOLARGE, $dest );
		if( $_FILES["upfile"]["error"] == UPLOAD_ERR_PARTIAL )
			error( S_FAILEDUPLOAD, $dest );
		if( $_FILES["upfile"]["error"] == UPLOAD_ERR_CANT_WRITE )
			error( S_FAILEDUPLOAD, $dest );
	}

	if( $upfile_name && $_FILES["upfile"]["size"] == 0 ) {
		error( S_TOOLARGEORNONE, $dest );
	}
	
	if( ENABLE_EXIF == 1 ) {
		$exif = htmlspecialchars( shell_exec( "/usr/local/bin/exiftags $dest" ) );
	}

	$resto = (int)$resto;
	if( $resto ) {
		if( !mysql_result( mysql_board_call( "select count(no) from `" . SQLLOG . "` where root>0 and no=$resto" ), 0, 0 ) )
			error( S_NOTHREADERR, $dest );
	}
  
  $pass_is_bannable = !$userpwd->isNew();
  
	// Most common errors checked, now check for post-block from ban requests
  if (BLOCK_ON_BR && !has_level()) {
    check_for_ban_request($host, $pass_is_bannable ? $pass : null);
  }
  
	// Standardize new character lines
	$com = str_replace( "\r\n", "\n", $com );
	$com = str_replace( "\r", "\n", $com );

	$comlim  = has_level() ? MAX_COM_CHARS_AUTHED : MAX_COM_CHARS;
	$longlim = has_level() ? 255 : 100;

	if( mb_strlen( $com ) > $comlim ) error( S_TOOLONG, $dest );
	if( strlen( $name ) > $longlim ) error( S_TOOLONG, $dest );
	if( strlen( $email ) > $longlim ) error( S_TOOLONG, $dest );
	if( strlen( $sub ) > $longlim ) error( S_TOOLONG, $dest );
	if( strlen( $resto ) > 10 ) error( S_GENERICERROR, $dest );
	if( strlen( $url ) > 10 ) error( S_GENERICERROR, $dest );

	$sub = normalize_content( $sub );

	// start of some attempt to get rid of *all* zero width bollocks
	if( BOARD_DIR != 'jp' && BOARD_DIR != 'a' && !SJIS_TAGS) {
		$com = normalize_content( $com );
		$com = strip_zerowidth( $com );
	}
	
	// strip no break spaces and soft hyphens
	$com = str_replace(array("\xC2\xAD", "\xC2\xA0"), '', $com);
	
	// name/subject too!
	$sub  = strip_zerowidth( $sub );
	$name = strip_zerowidth( $name );
	
  // Strip unicode emoticons
  $name = strip_emoticons($name, SJIS_TAGS);
  
  if ($sub !== '') {
    $sub = strip_emoticons($sub, SJIS_TAGS);
  }
  
  if ($com !== '') {
    $com = strip_emoticons($com, SJIS_TAGS);
  }
	
	// strip out ltr junk from name
	$name = preg_replace( '#([\x{2000}-\x{200F}]|[\x{2028}-\x{202F}])#u', '', $name );
	
  if ($sub !== '') {
    $sub = strip_fake_capcodes($sub);
  }
  
	if( !strlen( $name ) || preg_match( "/^[ |　|]*$/", $name ) ) $name = "";
	if( !strlen( $com ) || preg_match( "/^[ |　|\t]*$/", $com ) ) $com = "";
	if( !strlen( $sub ) || preg_match( "/^[ |　|]*$/", $sub ) ) $sub = "";

	//$name = str_replace( S_MANAGEMENT, "\"" . S_MANAGEMENT . "\"", $name );
	//$name = str_replace( S_DELETION, "\"" . S_DELETION . "\"", $name );
  
  // Remove intra spoilers
	if (SPOILERS && stripos($com, '[spoiler]') !== false) {
		//$com = preg_replace( '/\[spoiler\]\s+\[\/spoiler\]/', '', $com );
		$com = preg_replace('/(\S)\[spoiler\](.*?)\[\/spoiler\](\S)/', '\\1\\2\\3', $com);
	}
  
	//lol /b/
	$xff = get_request_xff();
	//if( is_bad_xff( $xff ) ) $xff = "";

	$youbi  = array(S_SUN, S_MON, S_TUE, S_WED, S_THU, S_FRI, S_SAT);
	$yd     = $youbi[date( "w", $time )];
	if( SHOW_SECONDS == 1 ) {
		$now = date( "m/d/y", $time ) . "(" . (string)$yd . ")" . date( "H:i:s", $time );
	} else {
		$now = date( "m/d/y", $time ) . "(" . (string)$yd . ")" . date( "H:i", $time );
	}

	$c_name  = $name;
	$c_email = $email;

	if (JANITOR_BOARD == 1) {
		$name = get_hashed_mod_name($_COOKIE['4chan_auser']);
		$email = '';
	}

  // April 2023
  //$_has_xa23_content = $com && preg_match('/^[^>]{8,}/m', $com) > 0;

	$com = preg_replace( '#>>>/' . BOARD_DIR . '/([0-9]+)#', '>>$1', $com );

	$sub   = sanitize_text( $sub );
	$sub   = preg_replace( "/[\r\n]/", "", $sub );
	$sub   = strip_private_unicode($sub);
	$url   = sanitize_text( $url );
	$url   = preg_replace( "/[\r\n]/", "", $url );
	$resto = sanitize_text( $resto );
	$resto = preg_replace( "/[\r\n]/", "", $resto );
	$com   = sanitize_text( $com, 1, true );
	$com   = strip_private_unicode($com);
	
	if( FORCED_ANON == 1 ) {
		if( !has_level('admin') ) $name  = S_ANONAME;
		$sub = '';
	}
	
	if (UPLOAD_BOARD) {
		if( NO_TEXTONLY == 1 ) {
			if( !$resto && !$has_image ) error( S_NOPIC, $dest );
		} else {
			if( !$resto && !$textonly && !$has_image ) error( S_NOPIC, $dest );
		}
	} else {
	  if (!TEXT_ONLY) {
  		if( NO_TEXTONLY == 1 && (!has_level() || $email === '') ) {
  			if( !$resto && !$has_image ) error( S_NOPIC, $dest );
  		} else {
  			if( !$resto && !$textonly && !$has_image ) error( S_NOPIC, $dest );
  		}
	  }

		if( REQUIRE_SUBJECT && !$resto && !strlen( $sub ) ) error( S_NOSUB, $dest );
  }
  // Check for sage, nonoko and nonokosage
  $is_sage = false;
  
  if (stripos($email, 'sage') !== false) {
    $is_sage = true;
    $email = str_ireplace('sage', '', $email);
  }
  
  $email_lower = strtolower($email);
  
  if ($email_lower === 'nonoko') {
    $is_nonoko = true;
  }
  
	if( SPOILERS == 1 && $spoiler ) {
		$sub = "SPOILER<>$sub";
	}

	if( !has_level() ) {
		$match = array();
		if( substr_count( $com, "\n" ) > 6 ) preg_match_all( '#([^\n]+\n+)\1{5,}#', $com, $match );
		if( !empty( $match[0] ) ) {
			foreach( $match[1] as $key => $var ) {
				//auto_ban_poster( $name, 0, 1, 'Matched the same string 5 times or more in 1 post seperated by newlines (<b>Matched:</b> ' . $var . ')', 'Please do not spam.' );
				error( S_REJECTTEXTBAN );
			}
		}
	}

	// FIXME sanitize_text() replaces repeated \n too, is this duplicate?
	// disable on code tag boards (we replace multiple brs instead)
	if (!CODE_TAGS && !SJIS_TAGS) {
		$com = preg_replace( "/\n((　| )*\n){3,}/", "\n", $com );
	}

	if( !has_level() && substr_count( $com, "\n" ) > MAX_LINES ) error(S_TOOMANYLINES, $dest );

	if( ENABLE_EXIF == 1 && $exif ) {
		//turn exif into a table
		$exiflines = explode( "\n", $exif );
		$exif      = "<table class=\"exif\" id=\"exif$tim\">";
		foreach( $exiflines as $exifline ) {
			list( $exiftag, $exifvalue ) = explode( ': ', $exifline );
			if( $exifvalue != '' )
				$exif .= "<tr><td>$exiftag</td><td>$exifvalue</td></tr>";
			else
				$exif .= "<tr><td colspan=\"2\"><b>$exiftag</b></td></tr>";
		}
		$exif .= '</table>';
		$exiftext .= '<br><br><span class="abbr">' . sprintf(S_EXIF_TOGGLE, $tim) . '</span><br>';
		$exiftext .= "$exif";
	}

	$name  = preg_replace( "/[\r\n]/", "", $name );
	
	$names = mb_convert_encoding($name, 'CP932', 'UTF-8'); // convert to Windows Japanese #ｋａｍｉ
	
	//start new tripcode crap
	list ( $name ) = explode( "#", $name );
	
	// Strip unicode point-of-interest and # characters
  if (preg_match('/[\x{2318}\x{ff03}\x{FE5F}]/u', $name)) {
    $name = preg_replace('/[\x{2318}\x{ff03}\x{FE5F}]/u', '', $name);
  }
	
	$name = normalize_content( $name );
	$name = sanitize_text( $name );
	$name = strip_private_unicode($name);
  
  if (preg_match('/^\s+$/', $name)) {
    $name = '';
  }
  
	$name = str_replace('!', '', $name);
	
	if( preg_match( "/\#+$/", $names ) ) {
		$names = preg_replace( "/\#+$/", "", $names );
	}
	if( preg_match( "/\#/", $names ) ) {

		$names = str_replace( "&#", "&&", htmlspecialchars( $names, ENT_COMPAT | ENT_HTML401, 'Shift_JIS' ) ); # otherwise HTML numeric entities screw up explode()!

		list ( $nametemp, $trip, $sectrip ) = str_replace( "&&", "&#", explode( "#", $names, 3 ) );
		
		if ($sectrip != '') {
			$trip = '';
		}
		
		$names = $nametemp;
		if( STRIP_TRIPCODE == 0 ) $name .= "</span>";

		if ($trip != "" && STRIP_TRIPCODE == 0) {
			$salt = strtr( preg_replace( "/[^\.-z]/", ".", substr( $trip . "H.", 1, 2 ) ), ":;<=>?@[\\]^_`", "ABCDEFGabcdef" );
			$trip = substr( crypt( $trip, $salt ), -10 );
			$name .= " <span class=\"postertrip\">!" . $trip;
		}

		if( $sectrip != "" && STRIP_TRIPCODE == 0 ) {
			$salt = file_get_contents_cached( SALTFILE );
			$sha  = base64_encode( pack( "H*", sha1( $sectrip . $salt ) ) );
			$sha  = substr( $sha, 0, 11 );
			$name .= " <span class=\"postertrip\">!!" . $sha;
		}
	} //end new tripcode crap
  
  // Check the length of the name field again to prevent truncation in the middle of tripcode HTML
  if (strlen($name) > 255) {
    error(S_TOOLONG, $dest);
  }
  
	//Cookies
	$cookie_domain = '.' . L::d(BOARD_DIR);
	setrawcookie( "4chan_name", rawurlencode( $c_name ), $time + ( $c_name ? ( 7 * 24 * 3600 ) : -3600 ), '/', $cookie_domain );
  
	if( !strlen( $name ) ) $name = S_ANONAME;
	if( !strlen( $com ) ) $com = S_ANOTEXT;
	if( !strlen( $sub ) ) $sub = S_ANOTITLE;
  
	/* since4pass */
  if ($captcha_bypass && $passid && $email && strpos(" $email ", ' since4pass ') !== false) {
    $since4pass = get_since_4chan($passid);
  }
  else {
	  $since4pass = 0;
  }
	
  // April 2024
  /*
  if ($email) {
    $_xa24_since4pass = april_2024_parse_email($email);
    $since4pass = $_xa24_since4pass;
  }
  else {
    $_xa24_since4pass = false;
  }
  */
  if (FORTUNE_TRIP == 1 && $email == 'fortune') {
		$fortunes   = array("Bad Luck", "Average Luck", "Good Luck", "Excellent Luck", "Reply hazy, try again", "Godly Luck", "Very Bad Luck", "Outlook good", "Better not tell you now", "You will meet a dark handsome stranger", "ｷﾀ━━━━━━(ﾟ∀ﾟ)━━━━━━ !!!!", "（　´_ゝ`）ﾌｰﾝ ", "Good news will come to you by mail");
    // Christmas 2021
    /*
    $fortunes = array("You're on the nice list!", "You're on the naughty list!", "Krampus is coming to your house!", "It's going to be a white Christmas!", "Merry Christmas!", "Happy Hanukkah!", "Happy Kwanzaa!", "Feliz Navidad!", "Happy Festivus!", "You're getting a lump of coal in your stocking!", "Blessed Yule!", "You're standing under the mistletoe!", "The poster above you has been very very naughty!", "You got an extra thick slice of fruitcake.", "You're on the Elf Watchlist.", "Your heart is two sizes too small.", "Your heart grew three sizes!", "Bah! Humbug.");
    */
		$fortunenum = rand( 0, sizeof( $fortunes ) - 1 );
		$fortcol    = "#" . sprintf( "%02x%02x%02x",
			127 + 127 * sin( 2 * M_PI * $fortunenum / sizeof( $fortunes ) ),
			127 + 127 * sin( 2 * M_PI * $fortunenum / sizeof( $fortunes ) + 2 / 3 * M_PI ),
			127 + 127 * sin( 2 * M_PI * $fortunenum / sizeof( $fortunes ) + 4 / 3 * M_PI ) );
		$com        .= "<span class=\"fortune\" style=\"color:$fortcol\"><br><br><b>Your fortune: " . $fortunes[$fortunenum] . "</b></span>";
  }
  
	if (DICE_ROLL == 1) {
		if( $email ) {
			if( preg_match( "/dice[ +](\\d+)[ d+](\\d+)(([ +-]+?)(-?\\d+))?/", $email, $match ) ) {
				$dicetxt     = S_DICE_PFX . ' ';
				$dicenum     = min( 25, $match[1] );
				$diceside    = $match[2];
				$diceaddexpr = $match[3];
				$dicesign    = $match[4];
				$diceadd     = intval( $match[5] );

				for( $i = 0; $i < $dicenum; $i++ ) {
					$dicerand = mt_rand( 1, $diceside );
					if( $i ) $dicetxt .= ", ";
					$dicetxt .= $dicerand;
					$dicesum += $dicerand;
				}

				if( $diceaddexpr ) {
					if( strpos( $dicesign, "-" ) > 0 ) $diceadd *= -1;
					$diceadd_formatted = ( $diceadd >= 0 ? " + " : " - " ) . abs( $diceadd );
					$dicetxt .= $diceadd_formatted;
					$dicesum += $diceadd;
				}

				if ($dicenum > 1) {
				  $dicetxt .= " = $dicesum";
			  }
			  
			  $dicetxt .= " ({$dicenum}d{$diceside}" . ($diceaddexpr ? $diceadd_formatted : "") . ")<br><br>";
			  
				$com = "<b>$dicetxt</b>" . $com;
			}
		}
	}
	
	// fixme: this is needed for bypassing the r9k filter. $email gets reset below.
	$options_field = $email;
	
	$emails          = $email;
	$admin_highlight = false;
	$uid             = null;
	$capcode         = 'none';
	$delay_refresh   = false;
	if (strpos($email, 'capcode_') === 0) {
		// are we trying to capcode?
    if (!has_level('admin') && !has_flag('capcodename')) {
      $name = S_ANONAME;
    }
		// Only pass and authed users can use VIP capcodes
		if ($captcha_bypass) {
      $capcode = parse_capcode($email, $name);
		}
		else {
      $capcode = parse_capcode($email);
		}
		
		$ma      = ( $capcode == 'admin_highlight') ? 'admin' : $capcode;
		$ma      = ucfirst( $ma );

		if( DISP_ID == 1 && $ma != 'None' ) {
			$uid = $ma;
		}

		if( $ma != 'None' && STRIP_EXIF_ON_CAPCODE && $ext == ".jpg" ) {
			system( "/usr/local/bin/jpegtran -copy none -outfile '$dest' '$dest'" );
			$md5 = md5_file( $dest );
		}
		
    if ($capcode !== 'none') {
      $log_mod_action = $log_capcode_post = true;
    }
		
		setcookie('options', $email, $time + (7 * 24 * 3600), '/', $cookie_domain);
	}
	else if (isset($_COOKIE['options'])) {
		setcookie('options', null, $time -3600, '/', $cookie_domain);
	}
  
	$email = '';

	$nameparts = explode( '</span> <span class="postertrip">!', $name );

	time_log( "trip" );
  
	//logtime( "starting autoban checks" );
	
	/**
	 * Ban check step
	 */
	$ban_fields = array();
	
	if ($pass_is_bannable) {
	  $ban_fields['password'] = $pass;
	}
	/*
	if ($nameparts[1]) {
	  $ban_fields['tripcode'] = $nameparts[1];
	}
	*/
	if ($passid) {
	  $ban_fields['4pass_id'] = $passid;
	}
	
	$user_is_banned = check_for_ban($host, $ban_fields, $resto, $userpwd && $userpwd->verifiedLevel());
	
  if ($user_is_banned) {
    if (!$captcha_bypass) {
      mysql_global_call("INSERT INTO user_actions (board,ip,time,action) VALUES ('%s',%d,from_unixtime(%d),'%s')", BOARD_DIR, ip2long($host), $time, 'is_banned');
    }
    
    $redirect = 'https://www.' . L::d(BOARD_DIR) . '/banned';
    
    if ($user_is_banned == 1) {
      // Banned
      error_redirect(S_BANNED, $redirect);
    }
    else if ($user_is_banned == 2) {
      // Warned
      error_redirect(S_WARNED, $redirect);
    }
    else {
      // Ban evasion
      error("Error: $user_is_banned");
    }
  }
  
  /**
   * Validate the maximum number of allowed threads per user
   */
  if (!$resto) {
    validate_user_thread_limit(
      $_SERVER['REMOTE_ADDR'],
      isset($ban_fields['password']) ? $pass : null,
      $passid
    );
  }
  
  /*
   * Embedded data detection and banned re-post block
   */
  if ($has_image) {
    // Check if the file contains embedded data.
    if (false && CLEANUP_UPLOADS) {
      // Update the size if the file was modified
      if (cleanup_uploaded_file($dest, $ext)) {
        clearstatcache(true, $dest);
        $fsize = filesize($dest);
        if (!$fsize) {
          error(S_TOOLARGE, $dest);
        }
        $md5 = md5_file($dest);
      }
    }
    
    // Check if the uploaded file should be blocked because of previous bans
    if (check_for_banned_upload($md5)) {
      //log_spam_filter_trigger('block_banned_reupload', BOARD_DIR, $resto, $host, 1, $md5);
      error(S_FAILEDUPLOAD, $dest);
    }
  }
  
	// ---
	
	$autosage = false;
  
  // See bellow wordwrap2()
  if (strpos($com, 'rep?~') !== false) {
    $com = str_replace(array('~?rep?~', '~?erep?~'), '', $com);
  }
  
  if (!has_level() || $capcode === 'none' || BOARD_DIR == 'test') {
    $autosage = spam_filter_post_content_new(BOARD_DIR, $resto, $com, $sub, $name, $upfile_name, $pass, ($captcha_bypass && $passid) ? $passid : null);
    spam_filter_post_ip($userpwd, $resto, $has_image);
  }
  
  if (!$capcode || $capcode == 'none') {
    check_md5_blacklist($md5, $original_md5, [
      'resto' => $resto,
      'name' => $nameparts[0],
      'tripcode' => $nameparts[1],
      'filename' => "$insfile$ext",
      'password' => $pass,
      '4pass_id' => ($captcha_bypass && $passid) ? $passid : null
    ], $dest);
  }
  
	spam_filter_post_trip( $name, $trip, $dest );

	time_log( "ab" );
  
  // Only process linebreaks for non-html posts
  if (!$log_html_post) {
    $com = nl2br($com, false);
    $com = str_replace("\n", "", $com); //\n is erased
  }
  
	$com .= $exiftext; // must be done after spam filter, since it has a javascript: link
	
  if (SJIS_TAGS) {
    $com = sjis_parse($com);
  }
  
	if( SPOILERS == 1 ) {
		$com = spoiler_parse( $com );
		if (stripos( $com, '<s>') !== false) {
			$com = preg_replace('/<s>(\s|<br>|(?R))*<\/s>/', '', $com);
			//$com = preg_replace('/(\S)<s>(.*?)<\/s>(\S)/', '\\1\\2\\3', $com);
		}
	}
  /*
	if( JSMATH == 1 ) {
		$com = jsmath_parse( $com );
	}
  */
	if( CODE_TAGS ) {
		$com = preg_replace( '#(\[code\](.{0,6})\[\/code\])#', '\\2', $com );

		$com = code_parse( $com );

		$com = str_replace( '<pre class="prettyprint"><br>', '<pre class="prettyprint">', $com );
		$com = preg_replace( '#(<br>){4,}#', '<br><br><br>', $com );
	}
	
  if (OP_MARKUP && (!$resto || is_poster_op($host, $pass, $resto))) {
    $com = parse_op_markup($com);
  }
	
	// pull this down to here to get rid of any shenanigans
  if (!$resto) { // new threads require subject or comment
    if ($sub === '' && ($com === '' || preg_match('/^(?:<br>|\s)+$/', $com))) {
      if ($options_field === '' || !$has_image || !has_level()) {
        error(S_NOTEXT_OP, $dest);
      }
    }
    else if (TEXT_ONLY && $sub === '') {
      error(S_NOSUB, $dest);
    }
  }
  else if (!$has_image && ($com === '' || preg_match('/^(?:<br>|\s)+$/', $com))) { // replies without image
    error(S_NOTEXT, $dest);
  }
  
	if( WORD_FILT && $word_filters_enabled ) {
		$com = word_filter( $com, "com" );
		if( $sub )
			$sub = word_filter( $sub, "sub" );
		$namearr = explode( '</span> <span class="postertrip">', $name );
		if( strstr( $name, '</span> <span class="postertrip">' ) ) {
			$nametrip = '</span> <span class="postertrip">' . $namearr[1];
		} else {
			$nametrip = "";
		}
		if( $namearr[0] != S_ANONAME )
			$name = word_filter( $namearr[0], "name" ) . $nametrip;
	}

	/*if( $html != 1 || ( !has_level('manager') ) ) {
		$com = wordwrap2( $com, 35, "{{w_br}}" );
	}*/
  
  // April 2022
  /*
  if (strpos($com, ':') !== false) {
    $com = april_process_post_emotes($com, $_COOKIE['xa_sid']);
  }
  */
	$com = normalize_and_linkify($com);
	
	//$html = isset( $_POST['html'] ) && $_POST['html'] == 1;
	$html = 0;
	if( (!has_level('manager') && !has_flag('html')) || $html != 1 ) {
		$com = wordwrap2( $com, 35, "{{w_br}}" );
	}
  
	$com = preg_replace( '#(&gt;&gt;&gt;/[a-z0-9]+/[^ <$]*|&gt;&gt;[0-9]+)#', '~?rep?~\\1~?erep?~', $com );
	$com = preg_replace( "!(^|r>|r> )(&gt;[^<]*)!", "\\1<span class=\"quote\">\\2</span>", $com );
	$com = preg_replace( '#~?rep?~<span class="quote">(.+?)</span>~?erep?~#', '\\1', $com );
	
	$com = str_replace( array( '~?rep?~', '~?erep?~' ), '', $com );

	$com = str_replace( '{{w_br}}', '<wbr>', $com );

	$admin_style = "padding: 5px;margin-left: .5em;border-color: #faa;border: 2px dashed rgba(255,0,0,.1);border-radius: 2px"; //FIXME make it easier to edit css so this can go in it
	if( $admin_highlight ) {
		$com = "<div style=\"$admin_style\">$com</div>";
	}

	if( DISP_ID == 1 && !$uid ) {
		$uid = $is_sage && !META_BOARD && !DISP_ID_NO_HEAVEN ? "Heaven" : generate_uid( $resto, $time );
	}
	
	// Validate comment for OPs by regex
	if (!$resto && COM_REGEX) {
	  if (preg_match(COM_REGEX, $com) === 0) {
	    error(S_INVALID_COM);
	  }
	}
	
	//post text is now completely created, thumbnail not

	if( !$silent_reject ) {
		//logtime( "Before flood check" );
		$may_flood = has_level( 'janitor' );

		if (!$may_flood || (!has_level() && (META_BOARD || $_POST['name'] != ''))) {
			if( $com ) {
				// Check for duplicate comments
				$query  = "select sql_no_cache max(time) from `%s` where com='%s' " .
					"and host='%s' " .
					"and time>%d";
				$result = mysql_board_call( $query, SQLLOG, $com, $host, $time - RENZOKU_DUPE );
				if( $ltime = mysql_result( $result, 0, 0 ) ) {
					//check_fail_floodcheck($com);
					$str = sprintf(S_RENZOKU_DUP, sec2hms( ( $ltime + RENZOKU_DUPE ) - $time, false, true ) );
					error( $str, $dest );
				}
				mysql_free_result( $result );
			}
			
			/**
			 * Posting cooldowns
			 */
			if (!$resto) {
				/**
				 * New threads
				 */
				$query  = "select max(time) from `%s` where time>%d " .
					"and host='%s' and root>0"; //root>0 == non-sticky
				$result = mysql_board_call( $query, SQLLOG, ( $time - RENZOKU3 ), $host );
				if( $ltime = mysql_result( $result, 0, 0 ) ) {
					$str = sprintf(S_RENZOKU3, sec2hms( ( $ltime + RENZOKU3 ) - $time, false, true ) );
					error( $str, $dest );
				}
				mysql_free_result( $result );
				// Cross-board cooldown
				$query = "SELECT 1 FROM user_actions WHERE ip = %d AND action = 'new_thread' AND board != '%s' AND time >= DATE_SUB(NOW(), INTERVAL 5 MINUTE)";
				$result = mysql_global_call($query, ip2long($host), BOARD_DIR);
				if (mysql_num_rows($result) > 0) {
					error( S_RENZOKU3, $dest ); // You must wait longer before posting another thread
				}
			}
			else {
			  /**
			   * Replies
			   * Pass users have lower cooldowns
			   */
        
        // Check for same image flood first
        if ($has_image && $resto) {
          $query  = "SELECT time FROM `%s` WHERE host = '%s' AND md5 = '%s' AND resto != %d ORDER BY no DESC LIMIT 1";
          
          $result = mysql_board_call($query, SQLLOG, $host, $md5, $resto);
          
          if ($flood_row = mysql_fetch_assoc($result)) {
            $last_time = (int)$flood_row['time'];
            
            $cooldown = RENZOKU_DUPE;
            $cooldown_error = S_RENZOKU2_DUP;
            
            if ($captcha_bypass) {
              $cooldown = ceil((int)$cooldown / 2);
            }
            else {
              $cooldown_error .= S_RENZOKU_PASS;
            }
            
            if ($last_time > $time - $cooldown) {
              error(sprintf($cooldown_error, sec2hms($last_time + $cooldown - $time, false, true)), $dest);
            }
          }
        }
        
        // Now the standard cooldown
				$query  = "SELECT time, resto, fsize FROM `%s` WHERE host = '%s' AND resto > 0 ORDER BY no DESC LIMIT 1";
				
				$result = mysql_board_call($query, SQLLOG, $host);
				
				if ($flood_row = mysql_fetch_assoc($result)) {
					$last_time = (int)$flood_row['time'];
					
					if ($has_image) {
						$cooldown = RENZOKU2;
						$cooldown_error = S_RENZOKU2;
					}
					else {
						$cooldown = RENZOKU;
						$cooldown_error = S_RENZOKU;
					}
					
					if ($captcha_bypass) {
					  $cooldown = ceil((int)$cooldown / 2);
					}
					else {
					  $cooldown_error .= S_RENZOKU_PASS;
					}
					
					if ($last_time > $time - $cooldown) {
						error(sprintf($cooldown_error, sec2hms($last_time + $cooldown - $time, false, true)), $dest);
					}
				}
			}
			/*
			if (SAVE_XFF == 1 && $xff) {
				// Check for multiple ips with same xff
				$result = mysql_global_call( "select count(distinct ip)>2 from xff where xff='%s' and is_live=1", $xff );
				if( mysql_result( $result, 0, 0 ) ) {
					auto_ban_poster( $name, 14, 1, "Detected 3 proxies for same IP", "Proxy/Tor exit node." );
					error( S_GENERICERROR, $dest );
				}
				// Check for multiple xffs with same ip?
			}
      */
			// Check for OP bump limiting
      if ($resto && RENZOKU_OP) {
        $query = 'SELECT host, time FROM `%s` WHERE no = %d';
        $query = mysql_board_call($query, SQLLOG, $resto);
        if ($query) {
          $result = mysql_fetch_assoc($query);
          // Poster is OP
          if ($result && $result['host'] === $host) {
            // OP can only bump his thread RENZOKU_OP_TIME seconds after its creation
            if ($result['time'] > ($time - RENZOKU_OP_TIME)) {
              $is_sage = 1;
            }
            // OP can only bump his thread every RENZOKU_OP_TIME2 seconds
            else {
              $query2 = "SELECT time FROM `%s` WHERE host = '%s' AND resto = %d ORDER BY no DESC LIMIT 1";
              $query2 = mysql_board_call($query2, SQLLOG, $host, $resto);
              if ($query2) {
                $result = mysql_fetch_assoc($query2);
                if ($result && $result['time'] > ($time - RENZOKU_OP_TIME2)) {
                  $is_sage = 1;
                }
              }
              mysql_free_result($query2);
            }
          }
        }
        mysql_free_result($query);
      }
		}
		
    // Minimal cooldowns for authed users (3s)
    if ($may_flood) {
      $query = "SELECT time FROM `%s` WHERE host = '%s' ORDER BY no DESC LIMIT 1";
      
      $result = mysql_board_call($query, SQLLOG, $host);
      
      if ($flood_row = mysql_fetch_assoc($result)) {
        $last_time = (int)$flood_row['time'];
        
        $cooldown = 5;
        
        if ($last_time > $time - $cooldown) {
          error(sprintf(S_RENZOKU, sec2hms($last_time + $cooldown - $time, false, true)), $dest);
        }
      }
    }
    
    time_log( "fc" );
    
    $tensorchan_score = 0;
    
		// thumbnail
		$image_path = "";
		$m_img = false;
		if ($has_image) {
      if( USE_THUMB && !UPLOAD_BOARD ) {
        // Detect and block NSFW content
        $_need_inference = tensorchan_is_needed($userpwd, $resto, $W, $H, $ext);
        
        if ($_need_inference) {
          $_tensor_png = false;
        }
        else {
          $_tensor_png = null;
        }
        
        $ret = make_thumb( $dest, $tim, $ext, $resto, $TN_W, $TN_H, $tmd5, $webm_sar, $_tensor_png);
        
        if (!$ret && $ext != ".pdf") {
          error(S_IMGFAIL, $dest);
        }
        
        if ($_need_inference && $_tensor_png) {
          $tensorchan_score = tensorchan_check_nsfw($_tensor_png);
          unset($_tensor_png);
        }
      }
      
			$name_part = UPLOAD_BOARD ? $insfile : $tim;
			$image_path = IMG_DIR . $name_part . $ext;
			if( move_uploaded_file( $dest, $image_path ) === false ) {
				error( S_FAILEDUPLOAD, $dest );
			}
			chmod( $image_path, 0664 );
			
			if (MOBILE_IMG_RESIZE) {
			  $m_img = resize_mobile_image($image_path, $W, $H, $fsize, $tim, $ext);
			}
      
      // Oekaki
      if (ENABLE_PAINTERJS) {
        // Replays
        if (ENABLE_OEKAKI_REPLAYS) {
          $oe_replay_path = null;
          
          if (isset($_FILES['oe_replay']) && $_FILES['oe_replay']['name'] === 'tegaki.tgkr' && $insfile === 'tegaki') {
            if (oekaki_validate_replay($_FILES['oe_replay']) === true) {
              $oe_replay_path = IMG_DIR . $tim . '.tgkr';
              
              if (move_uploaded_file($_FILES['oe_replay']['tmp_name'], $oe_replay_path) === false) {
                error(S_FAILEDUPLOAD);
              }
              
              chmod($oe_replay_path, 0664);
            }
          }
          
          // Oekaki meta
          if (isset($_POST['oe_time'])) {
            if (isset($_POST['oe_src']) && $resto) {
              $oe_src_pid = oekaki_get_valid_src_pid($_POST['oe_src'], BOARD_DIR, $resto);
            }
            else {
              $oe_src_pid = null;
            }
            
            $com .= oekaki_format_info(
              $_POST['oe_time'],
              $oe_replay_path ? $tim : null,
              $oe_src_pid
            );
          }
        }
      }
		}
    
		//logtime( "Thumbnail created" );
		time_log( "t" );

		// Infrequent flood check (dupe image)
		if( $has_image && (!$capcode || $capcode === 'none')) {
			if ($resto) {
			  $result = mysql_board_call("SELECT sql_no_cache `no`,`resto` FROM `" . SQLLOG . "` WHERE archived = 0 and (resto = %d OR no = %d) AND `md5`='%s' AND filedeleted=0 limit 1", $resto, $resto, $md5);
			}
			else {
			  $result = mysql_board_call("SELECT sql_no_cache `no`,`resto` FROM `" . SQLLOG . "` WHERE archived = 0 AND resto = 0 AND `md5`='%s' AND filedeleted=0 limit 1", $md5);
			}
			
			if( mysql_num_rows( $result ) ) {
				list( $dupeno, $duperesto ) = mysql_fetch_row( $result );
				if( !$duperesto ) $duperesto = $dupeno;
				error( '' . S_DUPE . ' <a href="//boards.' . L::d(BOARD_DIR) . '/' . BOARD_DIR . "/thread/" . $duperesto . PHP_EXT2 . '#p' . $dupeno . '">here</a>.', $dest );
			}
			
			if ($resto && MAX_IMG_REPOST_COUNT > 0) {
				$_query = 'SELECT COUNT(*) FROM `' . SQLLOG . "` WHERE archived = 0 AND resto != 0 AND `md5` = '%s' AND filedeleted = 0";
				
				$result = mysql_board_call($_query, $md5);
				
				if ($result) {
					$_count = (int)mysql_fetch_row($result)[0];
					
					if ($_count >= MAX_IMG_REPOST_COUNT) {
						error(S_DUPE);
					}
				}
			}
			
			if ( defined('SQLLOGMD5') ) {
				// TODO: There's a race here. This should just be INSERT and check for failure!
				$result = mysql_board_call("SELECT sql_no_cache * FROM `%s` WHERE md5='%s' AND now > DATE_SUB(NOW(), INTERVAL 1 DAY) limit 1", SQLLOGMD5, $md5);
				if ( mysql_num_rows( $result ) ) {
					list( $dc_now, $dc_filename, $dc_md5p ) = mysql_fetch_row( $result );
					
					if( $dc_now ) {
						error('Error: You must wait longer before reposting this file.', $dest );
					}
				}
			}
		}

		$rootpredicate = $resto ? "0" : "now()";
    
		// ROBOT9000
    if (defined('ROBOT9000') && ROBOT9000) {
      // Logged in uses can bypass r9k by using the "bypass_r9k" command in the Options field.
      // Capcoded posts always bypass r9k.
      if (($options_field !== 'bypass_r9k' || !has_level('janitor')) && $capcode === 'none') {
        require_once 'plugins/robot9000.php';
        $r9k_status = r9k_process($com, $md5, ip2long($host));
        if ($r9k_status !== R9K_OK) {
          error($r9k_status, $dest );
        }
      }
    }
		
    // FIX ME, comments with html get truncated and break the layout, but only mods and janitors can bypass the regular limits
    if (strlen($com) > 65536) {
      error(S_TOOLONG, $dest);
    }
    
		//logtime( "Before insertion" );

		//find sticky & autosage
		// auto-sticky
		//$sticky   = false;
		// autosagin is now done in spam_filter_post_content
		//$autosage = spam_filter_should_autosage( $com, $sub, $name, $fsize, $resto, $W, $H, $dest, $insertid );

		//old auto-sticky code -- disabled
		// if(defined('AUTOSTICKY') && AUTOSTICKY) {
		// 	$autosticky = preg_split("/,\s*/", AUTOSTICKY);
		// if($resto == 0) {
		// if($insertid % 1000000 == 0 || in_array($insertid,$autosticky))
		// 	$sticky = true;
		// }
		// }

		$flag_cols = "";
		$flag_vals = "";

		if( $captcha_bypass ) {
			$flag_cols .= ',4pass_id';
			$flag_vals .= ",'" . $passid . "'";
		}
    
		if ($since4pass) {
			$flag_cols .= ',since4pass';
			$flag_vals .= ",$since4pass";
		}
		/*
		if( $sticky ) {
			$flag_cols .= ",sticky";
			$flag_vals .= ",1";
		}
    */
		//permasage just means "is sage" for replies
		if( $resto ? $is_sage : $autosage ) {
			$flag_cols .= ",permasage";
			$flag_vals .= ",1";
		}

		if( $capcode ) {
			$flag_cols .= ',capcode';
			$flag_vals .= ",'$capcode'";
		}
		
    if ($m_img) {
      $flag_cols .= ',m_img';
      $flag_vals .= ",1";
    }
    
		//$country = geoip_country_code_by_addr( $_SERVER['REMOTE_ADDR'] );
		//if( !$country ) $country = 'XX';
    $geo_data = GeoIP2::get_country($_SERVER['REMOTE_ADDR']);
    
    if ($geo_data && isset($geo_data['country_code'])) {
      $country = $geo_data['country_code'];
      
      // FIXME: football cups
      /*
      if (BOARD_DIR === 'sp' && $country === 'GB' && isset($geo_data['sub_code'])) {
        if ($geo_data['sub_code'] === 'WLS') {
          $country = 'XW';
        }
        else if ($geo_data['sub_code'] === 'SCT') {
          $country = 'XS';
        }
        else if ($geo_data['sub_code'] !== 'NIR') {
          $country = 'XE';
        }
      }
      */
    }
    else {
      $country = 'XX';
    }
    
    // User Agent ID
    $browser_id = spam_filter_get_browser_id();
    
    // Request Signature
    $_req_sig = spam_filter_get_req_sig();
    
    /**
     * Flood checks
     */
    $_threat_score = 0;
    
    if (!$captcha_bypass) {
      $_pwd_known = $userpwd && $userpwd->isUserKnownOrVerified(360);
      $_pwd_trusted = $userpwd && ($userpwd->postCount() > 10 || $userpwd->verifiedLevel());
      $_pwd_verified = $userpwd && $userpwd->verifiedLevel();
      
      if (!$_pwd_known) {
        $_threat_score = spam_filter_get_threat_score($country, !$resto, true);
      }
      
      // Sample the user agent of known  users
      if (false && $userpwd && $userpwd->isUserKnownOrVerified(4320) && $userpwd->postCount() > 10) {
        if (mt_rand(0, 9999) < 2000) {
          $_bot_headers = spam_filter_format_http_headers($com, $country, "$insfile$ext", $_threat_score, $_req_sig);
          log_spam_filter_trigger('log_safe_req', BOARD_DIR, $resto, $host, 1, $_bot_headers);
        }
      }
      
      if ($userpwd && !$userpwd->verifiedLevel() && $userpwd->postCount() > 0 && $userpwd->pwdLifetime() < 86400 && $userpwd->maskChanged()) {
        log_spam_filter_trigger('log_mask_changed', BOARD_DIR, $resto, $host, 1);
      }
      
      if (!$_pwd_known && ($_threat_score > 0.31)) {
        $_bot_headers = spam_filter_format_http_headers($com, $country, "$insfile$ext", $_threat_score, $_req_sig);
        log_spam_filter_trigger('block_txt_threat', BOARD_DIR, $resto, $host, 1, $_bot_headers);
        show_post_successful_fake($resto);
        return;
      }
      
      if (false && !$_pwd_known && $resto == 18361689 && BOARD_DIR === 'fa' && mt_rand(0, 9) >= 1 && $country != 'US') {
        $_bot_headers = spam_filter_format_http_headers($com, $country, "$insfile$ext", $_threat_score, $_req_sig);
        log_spam_filter_trigger('block_other', BOARD_DIR, $resto, $host, 1, $_bot_headers);
        error(S_IPRANGE_BLOCKED . ' ' . S_IPRANGE_BLOCKED_TEMP . S_IPRANGE_BLOCKED_L1);
      }
      
      if (!$_pwd_trusted && $resto && $has_image && BOARD_DIR === 'g' && strpos($_thread_sub, '/aicg/') !== false) {
        $_bot_headers = spam_filter_format_http_headers($com, $country, "$insfile$ext", $_threat_score, $_req_sig);
        log_spam_filter_trigger('block_aicg', BOARD_DIR, $resto, $host, 1, $_bot_headers);
        error(S_IPRANGE_BLOCKED_IMG . ' ' . S_IPRANGE_BLOCKED_TEMP . S_IPRANGE_BLOCKED_L1);
      }
      
      if (!$_pwd_trusted && $resto && $has_image && BOARD_DIR === 'vg' && strpos($_thread_com, '/lolg/') !== false) {
        $_bot_headers = spam_filter_format_http_headers($com, $country, "$insfile$ext", $_threat_score, $_req_sig);
        log_spam_filter_trigger('block_lolg', BOARD_DIR, $resto, $host, 1, $_bot_headers);
        error(S_IPRANGE_BLOCKED_IMG . ' ' . S_IPRANGE_BLOCKED_TEMP . S_IPRANGE_BLOCKED_L1);
        //show_post_successful_fake($resto);
        //return;
      }
      
      if (!$_pwd_trusted && $resto && $has_image && BOARD_DIR === 'vg' && strpos($_thread_com, '/overwatch') !== false) {
        $_bot_headers = spam_filter_format_http_headers($com, $country, "$insfile$ext", $_threat_score, $_req_sig);
        log_spam_filter_trigger('block_owg', BOARD_DIR, $resto, $host, 1, $_bot_headers);
        error(S_IPRANGE_BLOCKED_IMG . ' ' . S_IPRANGE_BLOCKED_TEMP . S_IPRANGE_BLOCKED_L1);
        //show_post_successful_fake($resto);
        //return;
      }
      
      if (false && !$_pwd_trusted && $resto && $has_image && BOARD_DIR === 'fa' && strpos($_thread_sub, 'Workwear General') !== false) {
        $_bot_headers = spam_filter_format_http_headers($com, $country, "$insfile$ext", $_threat_score, $_req_sig);
        log_spam_filter_trigger('block_denim', BOARD_DIR, $resto, $host, 1, $_bot_headers);
        error(S_IPRANGE_BLOCKED_IMG . ' ' . S_IPRANGE_BLOCKED_TEMP . S_IPRANGE_BLOCKED_L1);
        //show_post_successful_fake($resto);
        //return;
      }
      
      if (!$_pwd_known && $resto && $has_image && BOARD_DIR === 'vg' && strpos($_thread_sub, '/bag/') !== false && $browser_id === '04d2237a2') {
        $_bot_headers = spam_filter_format_http_headers($com, $country, "$insfile$ext", $_threat_score, $_req_sig);
        log_spam_filter_trigger('block_bag', BOARD_DIR, $resto, $host, 1, $_bot_headers);
        error(S_IPRANGE_BLOCKED_IMG . ' ' . S_IPRANGE_BLOCKED_TEMP . S_IPRANGE_BLOCKED_L1);
      }
      
      if (false && !$_pwd_known && !$resto && (BOARD_DIR === 'co' || BOARD_DIR === 'a') && $country !== 'XX' && $browser_id === '02b99990d' && ($country == 'GB' || $country == 'DE' || $country == 'AU' || strpos($_COOKIE['_tcs'], $_SERVER['HTTP_X_TIMEZONE']) === false)) {
        $_bot_headers = spam_filter_format_http_headers($com, $country, "$insfile$ext", $_threat_score, $_req_sig);
        log_spam_filter_trigger('block_peridot', BOARD_DIR, $resto, $host, 1, $_bot_headers);
        error(S_IPRANGE_BLOCKED_IMG . ' ' . S_IPRANGE_BLOCKED_TEMP . S_IPRANGE_BLOCKED_L1);
      }
      
      if (!$_pwd_trusted && $resto && $has_image && BOARD_DIR === 'vg' && strpos($_thread_sub, 'granblue') !== false) {
        $_bot_headers = spam_filter_format_http_headers($com, $country, "$insfile$ext", $_threat_score, $_req_sig);
        log_spam_filter_trigger('block_gbfg', BOARD_DIR, $resto, $host, 1, $_bot_headers);
        error(S_IPRANGE_BLOCKED_IMG . ' ' . S_IPRANGE_BLOCKED_TEMP . S_IPRANGE_BLOCKED_L1);
        //show_post_successful_fake($resto);
        //return;
      }
      
      if (!$_pwd_known && $resto && $has_image && BOARD_DIR === 'v' && strpos($_thread_sub, 'gamesdonequick') !== false && $_threat_score >= 0.09 && mt_rand(0, 9) >= 1) {
        $_bot_headers = spam_filter_format_http_headers($com, $country, "$insfile$ext", $_threat_score, $_req_sig);
        log_spam_filter_trigger('block_adgq', BOARD_DIR, $resto, $host, 1, $_bot_headers);
        show_post_successful_fake($resto);
        return;
      }
      
      if (!$_pwd_known && $resto && $has_image && BOARD_DIR === 'vg' && strpos($_thread_sub, '/zzz/') !== false && $_threat_score >= 0.09 && mt_rand(0, 9) >= 1) {
        $_bot_headers = spam_filter_format_http_headers($com, $country, "$insfile$ext", $_threat_score, $_req_sig);
        log_spam_filter_trigger('block_zzz', BOARD_DIR, $resto, $host, 1, $_bot_headers);
        show_post_successful_fake($resto);
        return;
      }
      
      if (!$_pwd_verified && $resto && $has_image && BOARD_DIR === 'vg' && strpos($_thread_sub, '/funkg/') !== false && $_threat_score >= 0.09) {
        $_bot_headers = spam_filter_format_http_headers($com, $country, "$insfile$ext", $_threat_score, $_req_sig);
        log_spam_filter_trigger('block_funkg', BOARD_DIR, $resto, $host, 1, $_bot_headers);
        show_post_successful_fake($resto);
        return;
      }
      
      if (!$has_image) {
        if (!$_pwd_verified && $_threat_score >= 0.09 && mt_rand(0, 9) >= 5 && BOARD_DIR !== 'f') {
          $_bot_headers = spam_filter_format_http_headers($com, $country, "$insfile$ext", $_threat_score, $_req_sig);
          log_spam_filter_trigger('block_pub_prox', BOARD_DIR, $resto, $host, 1, $_bot_headers);
          show_post_successful_fake($resto);
          return;
        }
      }
      else {
        if (!$resto) {
          $_thres = 3;
        }
        else {
          $_thres = 4;
        }
        
        if (!$_pwd_verified && $_threat_score >= 0.09 && mt_rand(0, 9) >= $_thres && BOARD_DIR !== 'f') {
          $_bot_headers = spam_filter_format_http_headers($com, $country, "$insfile$ext", $_threat_score, $_req_sig);
          log_spam_filter_trigger('block_pub_prox', BOARD_DIR, $resto, $host, 1, $_bot_headers);
          if (mt_rand(0, 1)) {
            error(S_IPRANGE_BLOCKED_IMG . ' ' . S_IPRANGE_BLOCKED_TEMP . S_IPRANGE_BLOCKED_L1);
          }
          else {
            show_post_successful_fake($resto);
            return;
          }
        }
      }
      
      if (!$_pwd_known && $resto && $has_image && BOARD_DIR === 'vg' && $country === 'US' && isset($_COOKIE['_tcs']) && strpos($_COOKIE['_tcs'], '.America/Bogota.') !== false) {
        $_bot_headers = spam_filter_format_http_headers($com, $country, "$insfile$ext", $_threat_score, $_req_sig);
        log_spam_filter_trigger('block_bag_scat', BOARD_DIR, $resto, $host, 1, $_bot_headers);
        show_post_successful_fake($resto);
        return;
      }
      
      // FLOOD CHECK
      $flood_status = 0;//spam_filter_is_post_flood($host, BOARD_DIR, $resto, null);

      if ($flood_status === 3) {
        $_bot_headers = spam_filter_format_http_headers($com, $country, "$insfile$ext", $_threat_score, $_req_sig);
        
        log_spam_filter_trigger('block_flood_check', BOARD_DIR, $resto, $host, $flood_status, $_bot_headers);
        
        // Raw thread flood
        if ($flood_status === 3) {
          error(S_FAILEDUPLOAD);
        }
        else {
          show_post_successful_fake($resto);
          return;
        }
      }
      
      // Check Cloudflare's bot score and block using the lenient rangeban message
      if ($userpwd && !$userpwd->isUserKnownOrVerified(1440) && isset($_SERVER['HTTP_X_BOT_SCORE'])) {
        if (spam_filter_is_pwd_blocked($userpwd->getPwd(), 'block_bm_bot', 24)) {
          log_spam_filter_trigger('block_bm_bot_pwd', BOARD_DIR, $resto, $host, $_SERVER['HTTP_X_BOT_SCORE']);
          error(S_IPRANGE_BLOCKED . ' ' . S_IPRANGE_BLOCKED_TEMP . S_IPRANGE_BLOCKED_L1);
        }
        
        if (spam_filter_is_likely_automated($memcached)) {
          $_bot_headers = spam_filter_format_http_headers($com, $country, "$insfile$ext", $_threat_score, $_req_sig);
          write_to_event_log('block_bm_bot', $host, [
            'pwd' => $userpwd->getPwd(),
            'arg_num' => $_SERVER['HTTP_X_BOT_SCORE'],
            'board' => BOARD_DIR,
            'thread_id' => $resto,
            'meta' => $_bot_headers
          ]);
          error(S_IPRANGE_BLOCKED . ' ' . S_IPRANGE_BLOCKED_TEMP . S_IPRANGE_BLOCKED_L1);
        }
      }
      
      // If the country has changed, log the pwd and then block it for the next 24h
      if ($userpwd && !$userpwd->verifiedLevel() && $userpwd->postCount() < 20) {
        if (spam_filter_has_country_changed($userpwd->getPwd())) {
          log_spam_filter_trigger('block_country_changed', BOARD_DIR, $resto, $host, 1);
          error(S_IPRANGE_BLOCKED . ' ' . S_IPRANGE_BLOCKED_TEMP . S_IPRANGE_BLOCKED_L1);
        }
        
        if ($userpwd->envChanged()) {
          write_to_event_log('country_changed', $host, [
            'pwd' => $userpwd->getPwd(),
            'arg_str' => $country,
            'board' => BOARD_DIR,
            'thread_id' => $resto
          ]);
          
          log_spam_filter_trigger('block_country_changed', BOARD_DIR, $resto, $host, 1);
          error(S_IPRANGE_BLOCKED . ' ' . S_IPRANGE_BLOCKED_TEMP . S_IPRANGE_BLOCKED_L1);
        }
      }
    }
    
    /**
     * Custom flag selection
     */
    if (ENABLE_BOARD_FLAGS) {
      if ($_POST['flag'] === '0' || !isset($board_flags_array[$_POST['flag']])) {
        $board_flag_code = '';
        
        // FIXME: remove this eventually as we use localStorage now
        if (isset($_COOKIE['4chan_flag'])) {
          setcookie('4chan_flag', '', $time - 3600, '/', $cookie_domain);
        }
      }
      else {
        $board_flag_code = mysql_real_escape_string($_POST['flag']);
      }
    }
    else {
      $board_flag_code = '';
    }
    
    if ($board_flag_code) {
      $board_flag_col = ',board_flag';
      $board_flag_val = ",'$board_flag_code'";
    }
    else {
      $board_flag_col = '';
      $board_flag_val = '';
    }
    
		if( $resto ) calculate_indexes_to_rebuild( $resto );
    
    // Remove old replies if the thread is sticky+undead
    if ($is_undead_sticky && STICKY_CAP > 1) {
      $query = "SELECT MIN(no) FROM (SELECT no FROM `" . BOARD_DIR . "` WHERE resto = $resto ORDER BY no DESC LIMIT " . (STICKY_CAP - 1) . ") as subsel";
      $result = mysql_board_call($query);
      if ($result) {
        $prune_row = mysql_fetch_row($result);
        
        mysql_free_result($result);
        
        $min_no = (int)$prune_row[0];
        
        if ($min_no > $resto) {
          $query = "SELECT no FROM `" . BOARD_DIR . "` WHERE resto = $resto AND no < $min_no";
          $result = mysql_board_call($query);
          
          if ($result) {
            while ($prune_row = mysql_fetch_assoc($result)) {
              delete_post((int)$prune_row['no'], '', 0, 1, 1, 0);
            }
            
            mysql_free_result($result);
          }
        }
      }
    }
    
    // April 2024
    /*
    if ($_xa24_since4pass && !UPLOAD_BOARD && !JANITOR_BOARD) {
      $name = april_2024_get_name();
      
      if ($emails == '$DESU' && strlen($com) < 10000) {
        $com .= '<div class="xa24desu"></div>';
      }
    }
    */
    $user_meta = encode_user_meta($browser_id, substr($_req_sig, 0, 8), $userpwd);
    
		$insert_tries = 2;
		do {
			if( SKIP_DOUBLES == 1 ) mysql_board_call( "START TRANSACTION" );
			$query = "insert into `" . SQLLOG . "` (now,name,sub,com,host,pwd,email,filename,ext,w,h,tn_w,tn_h,tim,time,last_modified,md5,fsize,root,resto$flag_cols,tmd5,id,country$board_flag_col) values (" .
				"'" . $now . "'," .
				"'" . mysql_real_escape_string( $name ) . "'," .
				mysql_nullify( mysql_real_escape_string( $sub ) ) . "," .
				"'" . mysql_real_escape_string( $com ) . "'," .
				"'" . mysql_real_escape_string( $host ) . "'," .
				"'" . mysql_real_escape_string( $pass ) . "'," .
				"'" . mysql_real_escape_string($user_meta) . "'," .
				"'" . mysql_real_escape_string( $insfile ) . "'," .
				mysql_nullify( $ext ) . "," .
				(int)$W . "," .
				(int)$H . "," .
				(int)$TN_W . "," .
				(int)$TN_H . "," .
				"'" . $tim . "'," .
				(int)$time . "," .
				(int)$time . "," .
				mysql_nullify( $md5 ) . "," .
				(int)$fsize . "," .
				$rootpredicate . "," .
				(int)$resto .
				$flag_vals . "," .
				mysql_nullify( $tmd5 ) . "," .
				mysql_nullify( $uid ) . "," .
				"'$country'$board_flag_val)";

			if( !$result = mysql_board_call( $query ) ) {
				echo S_SQLFAIL;
			} //post registration
			time_log( "i" );

			$insertid = mysql_board_insert_id();
			if( SKIP_DOUBLES == 1 ) {
				if( has_doubles( $insertid ) ) {
					mysql_board_call( "ROLLBACK" );
					// retry
				} else {
					mysql_board_call( "COMMIT" );
					$insert_tries = 0;
				}
			} else {
				$insert_tries = 0;
			}
		} while( $insert_tries-- );
    
    // Captcha bypass token
    if ($captcha_bypass_allow_credits && $_threat_score < 0.15) {
      set_twister_captcha_credits($memcached, $host, $userpwd, $time);
    }
    /*
    if (!$captcha_bypass) {
      if (mt_rand(0, 99) === 0) {
        write_to_event_log('known_sample', $host, [
          'board' => BOARD_DIR,
          'thread_id' => $resto,
          'arg_num' => $userpwd->isUserKnown(),
        ]);
      }
    }
    */
    
    $userpwd->updatePostActivity(!$resto, $has_image);
    
    $userpwd->setCookie($cookie_domain);
    
    // April 2019
    /*
    if (defined('LIKE_MAX_LIKES') && LIKE_MAX_LIKES > 0) {
      like_update_post_score();
    }
    */
    // Halloween 2017
    /*
    if ($resto && defined('CSS_EVENT_NAME') && CSS_EVENT_NAME === 'spooky2017') {
      process_halloween_score($com, $resto, $passid, $pass, $pass_is_bannable);
    }
    */
    // April 2018
    //update_april_team_scores();
    
    // Log mod action if posted with html
    if ($log_mod_action) {
      $action_log_post = array(
        'no' => $insertid,
        'name' => $name,
        'sub' => $sub,
        'com' => $com,
        'filename' => $insfile,
        'ext' => $ext
      );
      
      if ($log_html_post) {
        $action_log_post['com'] = htmlspecialchars($com, ENT_QUOTES);
        log_mod_action(4, $action_log_post);
      }
      // capcode posting
      if ($log_capcode_post) {
        $action_log_post['name'] .= ' ## ' . ucfirst($capcode);
        log_mod_action(5, $action_log_post, $capcode === 'verified');
      }
    }
    
		if( $resto ) { //sage or age action
			$resline  = mysql_board_call( "select count(no) from `" . SQLLOG . "` where archived=0 and resto=" . $resto );
			$countres = mysql_result( $resline, 0, 0 );
      
			$permasage_hours = (int)PERMASAGE_HOURS;
      
			if ($permasage_hours > 0) {
			  $time_col = 'time,';
			}
			else {
			  $time_col = '';
			}
			
      // FIXME: a similar query is done at line ~4723
			$resline = mysql_board_call( "select {$time_col}sticky,permasage,permaage,root from `" . SQLLOG . "` where no=" . $resto );
			$resline = mysql_fetch_assoc($resline);
			
			if ($resline['sticky'] || $resline['permasage']) {
			  $root_col = '';
			}
			else if ($resline['permaage']) {
			  $root_col = 'root=now(),';
			}
			else if ($is_sage || $countres >= MAX_RES) {
			  $root_col = '';
			}
			else if ($permasage_hours && ($time - ($permasage_hours * 3600) >= $resline['time'])) {
			  $root_col = '';
		  }
		  else {
			  $root_col = 'root=now(),';
        
        if (!$captcha_bypass && BOARD_DIR === 'jp') {
          if (!spam_filter_can_bump_thread($resline['root'])) {
            $root_col = '';
            $_bot_headers = spam_filter_format_http_headers($com, $country, "$insfile$ext", $_threat_score, $_req_sig);
            log_spam_filter_trigger('necrobump', BOARD_DIR, $resto, $host, 1, $_bot_headers);
          }
        }
		  }
		  
			mysql_board_call("update `" . SQLLOG . "` set {$root_col}last_modified=%d where no=%d", $_SERVER['REQUEST_TIME'], $resto);
		}

		if( defined( 'AUTOSTICKY' ) && AUTOSTICKY ) {
			$autosticky = preg_split( "/,\s*/", AUTOSTICKY );
			if( $resto == 0 ) {
				if( $insertid % 1000000 == 0 || in_array( $insertid, $autosticky ) ) {
					$sticky = true;
					mysql_board_call( "update " . SQLLOG . " set sticky=1,root=root where no=$insertid" );
				}
			}
		}
    
		if( SAVE_XFF == 1 && $xff ) {
			mysql_global_do( "INSERT INTO xff (tim,board,xff,ip,postno,is_live) VALUES ('%s','%s','%s',%d,%d,1)", $tim, BOARD_DIR, $xff, ip2long( $host ), $insertid );
		}
		
		if (UPLOAD_BOARD && $md5 ) {
			$result = mysql_board_call( "insert ignore into `%s` (filename,md5) values('%s','%s')", SQLLOGMD5, $insfile, $md5 );
		}
		
		// determine url to redirect to
		$proto = ( stripos( $_SERVER["HTTP_REFERER"], "https" ) !== false ) ? "https:" : "http:";
		if( !$is_nonoko && !$resto ) {
			$redirect = $proto . '//boards.' . L::d(BOARD_DIR) . '/' . BOARD_DIR . "/thread/" . $insertid . PHP_EXT2;
		} else if( !$is_nonoko ) {
			$redirect = $proto . '//boards.' . L::d(BOARD_DIR) . '/' . BOARD_DIR . "/thread/" . $resto . PHP_EXT2 . '#p' . $insertid;
		} else {
			$redirect = $proto . '//boards.' . L::d(BOARD_DIR) . '/' . BOARD_DIR . '/';
		}
		
		// To let the JavaScript thread watcher know the newly created thread ID
		if (!$resto && isset($_POST['awt'])) {
			setcookie('4chan_awt', $insertid, 0, '/' . BOARD_DIR . '/', $cookie_domain);
		}
		
		show_post_successful( $mes, $com, $insertid, $resto, $redirect, $delay_refresh );
		
		$static_rebuild = ( STATIC_REBUILD == 1 );
		//logtime( "Before trim_db" );

		// trim database
    if (!$resto) {
      
      if (!$static_rebuild) {
        trim_db();
        
        if (ENABLE_ARCHIVE && ARCHIVE_MAX_AGE) {
          trim_archive();
        }
      }
    }
    
		//logtime( "After trim_db" );
		//time_log( "tr" );
    
    $_need_updatelog = true;
    
    if (AUTOARCHIVE_CAP && $resto && !$sticky && !$undead && ENABLE_ARCHIVE) {
      if (count_thread_replies(BOARD_DIR, $resto) >= AUTOARCHIVE_CAP) {
        $_need_updatelog = false;
        archive_thread($resto);
      }
    }
    
		// update html
    if ($_need_updatelog) {
		  updatelog( $resto ? $resto : $insertid );
    }
		//logtime( "Pages rebuilt" );
		//time_log( "r" );

		// late tasks happen below here
		iplog_add( BOARD_DIR, $insertid, $host, $time, $resto == 0, $tim, $has_image );
		
    // Auto-report possibly nsfw post
    if ($tensorchan_score && $tensorchan_score > 0.5) {
      tensorchan_log(BOARD_DIR, $insertid, $resto, $tim, $ext, $tensorchan_score);
    }
	} else {
		// silent reject
		$insertid = 0;
		$noko     = 0;
	}
  /*
	if( STATS_USER_JS ) {
		mysql_global_do( "UPDATE `user_stats` SET `count` = `count`+1 WHERE name='%s'", $stats_ok );
	}
  */
}

// Redirects to the most rcently created thread
// This is to confuse spambots
function show_post_successful_fake($resto = 0, $captcha_passed = true) {
  $thread_id = (int)$resto;
  $insert_id = 0;
  
  if (!$resto) {
    $query = 'SELECT resto FROM `' . BOARD_DIR . '` WHERE resto != 0 ORDER BY resto DESC LIMIT 1';
    $res = mysql_board_call($query);
    if ($res) {
      $row = mysql_fetch_row($res);
      $insert_id = (int)$row[0];
    }
  }
  else {
    $query = 'SELECT no FROM `' . BOARD_DIR . '` ORDER BY no DESC LIMIT 1';
    $res = mysql_board_call($query);
    if ($res) {
      $row = mysql_fetch_row($res);
      $insert_id = (int)$row[0] + 1;
    }
  }
  
  if (!$thread_id) {
    $redirect = 'https://boards.' . L::d(BOARD_DIR) . '/' . BOARD_DIR . "/thread/" . $insert_id . PHP_EXT2;
  }
  else {
    $redirect = 'https://boards.' . L::d(BOARD_DIR) . '/' . BOARD_DIR . "/thread/" . $thread_id . PHP_EXT2 . '#p' . $insert_id;
  }
  
  $cookie_domain = '.' . L::d(BOARD_DIR);
  
  $now = $_SERVER['REQUEST_TIME'];
  
  // Name cookie
  $c_name = $_POST['name'];
  setrawcookie('4chan_name', rawurlencode($c_name), $now + ($c_name ? (7 * 24 * 3600) : -3600), '/', $cookie_domain);
  
  // Password cookie
  $userpwd = UserPwd::getSession();
  
  if ($userpwd) {
    if ($captcha_passed) {
      $userpwd->setCookie($cookie_domain);
    }
    else if (!$userpwd->isFake() && !$userpwd->isNew()) {
      $userpwd->setCookie($cookie_domain);
    }
    else {
      UserPwd::setFakeCookie($now, $cookie_domain);
    }
  }
  
  show_post_successful(null, null, $insert_id, $thread_id, $redirect);
}

function show_post_successful( $mes, $com, $insertid, $resto, $redirect, $delay_refresh = false ) {
  if (isset($_SERVER['HTTP_ACCEPT']) && $_SERVER['HTTP_ACCEPT'] === 'application/json') {
    return show_post_successful_json($insertid, $resto);
  }
  
	if( !$mes ) $mes = S_POSTING_DONE;
		$time_to_refresh = ( $delay_refresh ) ? 10 : 1;
		$script          = "<meta http-equiv=\"refresh\" content=\"$time_to_refresh;URL=$redirect\">";
	if( defined( 'POST_SUCCESSFUL_FILE' ) ) {
		$file    = file_get_contents( POST_SUCCESSFUL_FILE );
		$success = str_replace( "@REDIRECT@", $redirect, $file );
	} else {
		// FIXME templating
		$icon       = DEFAULT_BURICHAN ? 'favicon-ws.ico' : 'favicon.ico';
		$defaultcss = DEFAULT_BURICHAN ? 'yotsubluenew' : 'yotsubanew';
		$cssVersion = TEST_BOARD ? CSS_VERSION_TEST : CSS_VERSION;
		$sg         = style_group();

		$styles = array(
			'Yotsuba New'   => "yotsubanew.$cssVersion.css",
			'Yotsuba B New' => "yotsubluenew.$cssVersion.css",
			'Futaba New'    => "futabanew.$cssVersion.css",
			'Burichan New'  => "burichannew.$cssVersion.css",
			'Photon'        => "photon.$cssVersion.css",
			'Tomorrow'      => "tomorrow.$cssVersion.css"
		);

		$css = '';

		if( isset( $_COOKIE[$sg] ) ) {
			if( isset( $styles[$_COOKIE[$sg]] ) ) {
				$css = '<link rel="stylesheet" title="switch" href="' . STATIC_SERVER . 'css/' . $styles[$_COOKIE[$sg]] . '">';
			}
		} else {
			$dcssl = $defaultcss . '.' . $cssVersion . '.css';
			$css   = '<link rel="stylesheet" title="switch" href="' . STATIC_SERVER . 'css/' . $dcssl . '">';
		}
    
    if (defined('CSS_EVENT_NAME') && CSS_EVENT_NAME) {
      $css = '<link rel="stylesheet" style="text/css" href="' . STATIC_SERVER . 'css/'
        . CSS_EVENT_NAME . '.' . $cssVersion . '.css" title="switch">';
    }
    
		if( BOARD_DIR == 'j' ) {
			$css = '<link rel="stylesheet" type="text/css" href="' . STATIC_SERVER . 'css/janichan.' . $cssVersion . '.css" title="Yotsuba New">';
		}


		$script .= '<link rel="shortcut icon" href="//s.4cdn.org/image/' . $icon . '">';
		$success = "<!DOCTYPE html><head>$script<title>" . S_POSTING_DONE . "</title>$css</head><body style=\"margin-top: 20%; text-align: center;\"><h1 style=\"font-size:36pt;\">$mes</h1><!-- thread:$resto,no:$insertid --></body></html>";
	}
	echo $success;
	
	if ($resto) {
	  fastcgi_finish_request();
	}
}

function show_post_successful_json($post_id, $thread_id) {
  header('Content-Type: application/json');
  
  echo '{"tid":' . $thread_id . ',"pid":' . $post_id . '}';
  
  if ($thread_id) {
    fastcgi_finish_request();
  }
}

function resredir( $res, $delete = 0, $no_exit = false ) {
  if (!$_SERVER["HTTP_REFERER"]) {
    $proto = 'https:';
  }
	else {
    $proto = ( stripos( $_SERVER["HTTP_REFERER"], "https" ) !== false ) ? "https:" : "http:";
	}
	
	$res = (int)$res;
	//mysql_board_lock( true );
	if( !$redir = mysql_board_call( "select no,resto from `" . SQLLOG . "` where no=" . $res ) ) {
		echo S_SQLFAIL;
	}
	list( $no, $resto ) = mysql_fetch_row( $redir );
	
	// if we're deleting and no post/resto (thread gone)
	if( !$no && $delete ) {
		// send us back to the board
		updating_index();
		//mysql_board_unlock();
		if (!$no_exit) {
		  die;
		}
		else {
		  return;
		}
	}
	
	if( !JANITOR_BOARD ) {
		header("Cache-Control: public, max-age=2");
	}

	if( !$no ) {
		// If no < max(no) then this could be 410 Gone.
		http_response_code(404);
		error( S_NOTHREADERR, $dest );
	}

	if( $resto == "0" ) { // thread
		$redirect = $proto . '//boards.' . L::d(BOARD_DIR) . '/' . BOARD_DIR . "/thread/" . $no . PHP_EXT2 . '#p' . $no;
	} else {
		$redirect = $proto . '//boards.' . L::d(BOARD_DIR) . '/' . BOARD_DIR . "/thread/" . $resto . PHP_EXT2 . '#p' . $no;
	}

	$redirect = JANITOR_BOARD ? str_replace( 'boards.', 'sys.', $redirect ) : $redirect;

	header("Location: $redirect", true, 301);
	echo "<meta http-equiv=\"refresh\" content=\"0;URL=$redirect\">";
	//mysql_board_unlock();
}

function tensorchan_is_needed($userpwd, $resto, $W, $H, $ext) {
  // Inference is disabled
  if (!defined('TENSORCHAN_MODE') || !TENSORCHAN_MODE) {
    return false;
  }
  
  // Can't check
  if (!$userpwd) {
    return false;
  }
  
  // User is known or verified
  if ($userpwd->isUserKnownOrVerified(240)) { // 4 hours
    return false;
  }
  
  // OPs only but the post is a reply
  if (TENSORCHAN_MODE == 1 && $resto) {
    return false;
  }
  
  if ($W < 150 || $H < 150 || $ext == '.pdf') {
    return false;
  }
  
  return true;
}

function tensorchan_check_nsfw($tensor_png) {
  if (!$tensor_png) {
    return false;
  }
  
  $tensor_res = tensorchan_predict($tensor_png);
  
  if (!$tensor_res) {
    return false;
  }
  
  if (isset($tensor_res['error'])) {
    write_to_event_log('tensor_err', $_SERVER['REMOTE_ADDR'], [
      'board' => BOARD_DIR,
      'meta' => htmlspecialchars($tensor_res['error'])
    ]);
    
    return false;
  }
  else {
    if (!isset($tensor_res['nsfw'])) {
      return false;
    }
    
    return (float)$tensor_res['nsfw'];
  }
}

function tensorchan_log($board, $post_id, $thread_id, $file_id, $file_ext, $score) {
  $post_id = (int)$post_id;
  $thread_id = (int)$thread_id;
  $score = (float)$score;
  
  $sql =<<<SQL
INSERT INTO tensor_log(board, thread_id, post_id, file_id, file_ext, nsfw)
VALUES('%s', $thread_id, $post_id, '%s', '%s', $score)
SQL;
  
  return !!mysql_global_call($sql, $board, $file_id, $file_ext);
}

function tensorchan_predict($data) {
  if (!$data) {
    return false;
  }
  
  $curl = curl_init();
  
  $url = "http://danbo.int:8501/predict";
  
  curl_setopt($curl, CURLOPT_URL, $url);
  curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
  curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 2);
  curl_setopt($curl, CURLOPT_TIMEOUT, 4);
  
  curl_setopt($curl, CURLOPT_CUSTOMREQUEST , "POST");
  curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
  
  $headers = array(
    'Content-Type: application/octet-stream'
  );
  
  curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
  curl_setopt($curl, CURLOPT_USERAGENT, '4chan.org');
  
  $resp = curl_exec($curl);
  
  if ($resp === false) {
    if ($errno = curl_errno($curl)) {
      $_err = 'Error (' . $errno . '): ' . curl_strerror($errno);
    }
    else {
      $_err = 'Something went wrong';
    }
    
    return ["error" => $_err];
  }
  
  $resp_status = curl_getinfo($curl, CURLINFO_HTTP_CODE);
  
  if ($resp_status >= 300) {
    return ["error" => "HTTP $resp_status: $resp"];
  }
  
  curl_close($curl);
  
  if ($resp[0] == '{') {
    $resp = json_decode($resp, true);
  }
  else {
    return ["error" => "Not a JSON response"];
  }
  
  return $resp;
}

function background_color( $im, $is_thread )
{
	if( DEFAULT_BURICHAN == 0 ) {
		if( $is_thread ) {
			list( $r, $g, $b ) = array(0xFF, 0xFF, 0xEE);
		} else {
			list( $r, $g, $b ) = array(0xF0, 0xE0, 0xD6);
		}
	} else {
		if( $is_thread ) {
			list( $r, $g, $b ) = array(0xEE, 0xF2, 0xFF);
		} else {
			list( $r, $g, $b ) = array(0xD6, 0xDA, 0xF0);
		}
	}

	return imagecolorallocate( $im, $r, $g, $b );
}

function optimize_thumb($tmppath) {
  system("/usr/local/bin/jpegoptim -q --strip-all '$tmppath' >/dev/null 2>&1");
}

// Calculates perceptual hash for a thumbnail
// $img is a reference to a GD resource
function get_thumb_dhash(&$img, $width, $height) {
  if (!$img) {
    return false;
  }
  
  $data = imagecreatetruecolor(9, 8);
  imagecopyresampled($data, $img, 0, 0, 0, 0, 9, 8, $width, $height);
  imagefilter($data, IMG_FILTER_GRAYSCALE);
  
  $hash = 0;
  $bit = 1;
  
  for ($y = 0; $y < 8; $y++) {
    $previous = imagecolorat($data, 0, $y) & 0xFF;
    
    for ($x = 1; $x < 9; $x++) {
      $current = imagecolorat($data, $x, $y) & 0xFF;
      
      if ($previous > $current) {
        $hash |= $bit;
      }
      
      $bit = $bit << 1;
      $previous = $current;
    }
  }
  
  imagedestroy($data);
  
  return sprintf("%016x", $hash);
}

//thumbnails
function make_thumb( $fname, $tim, $ext, $resto, &$TN_W, &$TN_H, &$tmd5, $webm_sar = null, &$tensor_png = null )
{
	$thumb_dir = THUMB_DIR; //thumbnail directory
	$outpath   = $thumb_dir . $tim . 's.jpg';
	if( !$resto ) {
		$width  = MAX_W; //output width
		$height = MAX_H; //output height
		$jpeg_quality = 50;
	} else {
		$width  = MAXR_W; //output width (imgreply)
		$height = MAXR_H; //output height (imgreply)
		$jpeg_quality = 40;
	}

	if( ENABLE_PDF == 1 && $ext == '.pdf' ) {
		// create jpeg for the thumbnailer
		$pdfjpeg = IMG_DIR . $tim . '.pdf.tmp';
		@exec( "/usr/local/bin/gs -q -dSAFER -dNOPAUSE -dBATCH -sDEVICE=jpeg -sOutputFile=$pdfjpeg $fname" );
		if( !file_exists( $pdfjpeg ) ) unlink( $fname );
		$fname = $pdfjpeg;
	}
  else if (ENABLE_WEBM && ($ext == '.webm' || $ext == '.mp4')) {
    $webm_thumb = thumb_webm($fname, $ext);
    
    if (!$webm_thumb) {
      unlink($fname);
      return false;
    }
    
    $fname = $webm_thumb;
  }
	
	$size = @GetImageSize($fname);
  
	if ($size === false) {
		return;
	}
	
  // File size needs to be checked again because of cleanup_uploaded_file
  if (defined('MAX_DIMENSION') && $ext == '.gif') {
    if ($size[0] > MAX_DIMENSION || $size[1] > MAX_DIMENSION) {
      error(S_TOOLARGERES);
    }
  }
  
	$memory_limit_increased = false;
	$maybe_transparent      = true;
	// Don't increase memory limit on CLI so that the user can do it with a CLI parameter instead
	if( $size[0] * $size[1] > 3000000 && isset($_SERVER['REMOTE_ADDR']) ) {
		$memory_limit_increased = true;
		ini_set( 'memory_limit', memory_get_usage() + $size[0] * $size[1] * 15 ); // for huge images
	}
	switch( $size[2] ) {
		case 1 :
			$im_in = ImageCreateFromGIF( $fname );
			if (!$im_in) {
			  return;
		  }
			break;
		case 2 :
			$im_in = ImageCreateFromJPEG( $fname );
			if( !$im_in ) {
				return;
			}
			$maybe_transparent = false;
			break;
		case 3 :
			$im_in = ImageCreateFromPNG( $fname );
			if( !$im_in ) {
				return;
			}
			break;
		default :
			return;
	}
	
	$source_w = $size[0];
	$source_h = $size[1];
	
  if ($webm_sar) {
    if ($webm_sar > 1) {
      $size[1] = round($size[1] / $webm_sar);
      
      if ($size[1] == 0) {
        $size[1] = 1;
      }
    }
    else {
      $size[0] = round($size[0] * $webm_sar);
      
      if ($size[0] == 0) {
        $size[0] = 1;
      }
    }
  }
  
	// Resizing
	if( $size[0] > $width || $size[1] > $height ) {
		$key_w = $width / $size[0];
		$key_h = $height / $size[1];
		( $key_w < $key_h ) ? $keys = $key_w : $keys = $key_h;
		$out_w = floor( $size[0] * $keys );
		$out_h = floor( $size[1] * $keys );
	} else {
		$out_w = $size[0];
		$out_h = $size[1];
	}
	
	// the thumbnail is created
	$im_out = ImageCreateTrueColor( $out_w, $out_h );
	if( !$im_out ) return;
	if( $maybe_transparent ) {
		$background = background_color( $im_out, $resto == 0 );
		ImageFill( $im_out, 0, 0, $background );
	}
	// copy resized original
	ImageCopyResampled( $im_out, $im_in, 0, 0, 0, 0, $out_w, $out_h, $source_w, $source_h );
	$tmppath = tempnam( ini_get( "upload_tmp_dir" ), "thumb" );
	// thumbnail saved
	ImageJPEG( $im_out, $tmppath, $jpeg_quality );
  
  // Generate a perceptual hash from the original image
  $tmd5 = Phash::hash($im_in, $source_w, $source_h);
  
  if ($tmd5 === false) {
    $tmd5 = '';
  }
  
  // Create the PNG file for inference
  if ($tensor_png !== null) {
    $tensor_png_dim = TENSORCHAN_DIM;
    $tensor_png_out = ImageCreateTrueColor($tensor_png_dim, $tensor_png_dim);
    ImageCopyResampled($tensor_png_out, $im_in,
      0, 0, 0, 0,
      $tensor_png_dim, $tensor_png_dim, $source_w, $source_h
    );
    $stream = fopen('php://memory','r+');
    imagepng($tensor_png_out, $stream);
    rewind($stream);
    $tensor_png = stream_get_contents($stream);
    fclose($stream);
    ImageDestroy($tensor_png_out);
  }
  
  // Cleanup
	ImageDestroy( $im_in );
	ImageDestroy( $im_out );
	
  optimize_thumb($tmppath);
  
	//$tmd5 = md5_file( $tmppath );
	rename_across_device( $tmppath, $outpath );
  
	// if PDF was thumbnailed delete the orig jpeg
	if (isset($pdfjpeg)) {
		unlink($pdfjpeg);
	}
	// delete original webm frame
	else if (isset($webm_thumb)) {
	  unlink($webm_thumb);
	}
	
	if( $memory_limit_increased )
		ini_restore( 'memory_limit' );

	$TN_W = $out_w;
	$TN_H = $out_h;

	return $outpath;
}

/* text plastic surgery */
// you can call with skip_bidi=1 if cleaning a paragraph element (like $com)
function sanitize_text( $str, $skip_bidi = 0, $allow_html = false )
{
	global $admin, $html;
	// stupid unicode-hack removal
	if( BOARD_DIR != 'jp' && BOARD_DIR != 'a' && BOARD_DIR != 'b' && !SJIS_TAGS ) {
		$str = preg_replace( '#[\x{00A0}\x{3000}]#u', ' ', $str );

	}

	if( !CODE_TAGS && !SJIS_TAGS) {
		$str = preg_replace( "/([ \t\f]|\xE2\x80\x8B|\xE2\x80\xA9)+/", " ", $str ); //collapse multiple spaces like HTML does
	} else {
		// fix tabs for html compression
		$str = str_replace( "\t", "    ", $str );
	}

	$str = trim( $str ); //blankspace removal
	if( get_magic_quotes_gpc() ) { //magic quotes is deleted (?)
		$str = stripslashes( $str );
	}

	if ($allow_html && $html == 1 && (has_level('manager') || has_flag('html') || has_flag('developer'))) {
	  $str = purify_html($str);
	} else {
		$str = htmlspecialchars( $str, ENT_QUOTES );
	}

	if( $skip_bidi == 0 ) {
		// fix malformed bidirectional overrides - insert as many PDFs as RLOs
		//RLO
		$str .= str_repeat( "\xE2\x80\xAC", substr_count( $str, "\xE2\x80\xAE" /* U+202E */ ) );
		$str .= str_repeat( "&#8236;", substr_count( $str, "&#8238;" ) );
		$str .= str_repeat( "&#x202c;", substr_count( $str, "&#x202e;" ) );
		//RLE
		$str .= str_repeat( "\xE2\x80\xAC", substr_count( $str, "\xE2\x80\xAB" /* U+202B */ ) );
		$str .= str_repeat( "&#8236;", substr_count( $str, "&#8235;" ) );
		$str .= str_repeat( "&#x202c;", substr_count( $str, "&#x202b;" ) );
	}

	return $str;
}

// TODO: rewrite this to use less SQL queries
function report() {
  global $captcha_bypass;
  
  $host = $_SERVER['REMOTE_ADDR'];
  
  if (isset($_COOKIE['4chan_pass'])) {
    $userpwd = new UserPwd($host, MAIN_DOMAIN, $_COOKIE['4chan_pass']);
  }
  else {
    $userpwd = new UserPwd($host, MAIN_DOMAIN);
  }
  
  if (BOARD_DIR === 'test') {
    require_once('forms/report-test.php');
    require_once('modes/report-test.php');
  }
  else {
    require_once('forms/report.php');
    require_once('modes/report.php');
  }
  
  if (!CAN_REPORT_POSTS) {
    fancydie(S_CANNOTREPORTPOSTS);
  }
  
  $no = (int)$_GET['no'];
  
  if ($no <= 0) {
    fancydie(S_POST_DEAD);
  }
  
  $post = report_check_post(BOARD_DIR, $no);
  
  if (!isset($_COOKIE['4chan_auser']) && !isset($_COOKIE['pass_enabled'])) {
    $no_captcha = report_can_bypass_captcha($host, $userpwd, $post);
  }
  else {
    $no_captcha = false;
  }
  
  if ($_SERVER['REQUEST_METHOD'] == 'GET') {
    header( 'Cache-Control: private, no-cache, must-revalidate' );
    header( 'Expires: -1' );
    
    // Doesn't check bans here
    report_check_ip( BOARD_DIR, $no, false);
    
    form_report(BOARD_DIR, $no, $no_captcha);
  }
  else {
    if (valid_captcha_bypass() !== true && $no_captcha !== true) {
      if (CAPTCHA_TWISTER) {
        $_m = create_memcached_instance();
        
        if (isset($_POST['t-challenge']) && $_POST['t-challenge'] === 'noop') {
          if (use_twister_captcha_credit($_m, $host, $userpwd) === false) {
            error(S_CAPTCHATIMEOUT);
          }
        }
        else if (is_twister_captcha_valid($_m, $host, $userpwd, BOARD_DIR, 1) === false) {
          error(S_BADCAPTCHA);
        }
      }
      else {
        start_recaptcha_verify();
        
        if (!$captcha_bypass) {
          end_recaptcha_verify();
        }
      }
    }
    
    // Also checks for bans
    report_check_ip(BOARD_DIR, $no, true);
    
    if (!isset($_POST['cat']) && !isset($_POST['cat_id'])) {
      fancydie('Invalid category selected.');
    }
    
    if ($_POST['cat']) {
      $cat_id = (int)$_POST['cat'];
    }
    else if ($_POST['cat_id']) {
      $cat_id = (int)$_POST['cat_id'];
    }
    else {
      $cat_id = null;
    }
    
    if (!$cat_id) {
      fancydie('Invalid category selected.');
    }
    /*
    if ($no_captcha) {
      write_to_event_log('skip_rep_captcha', $host, [
        'board' => BOARD_DIR,
        'thread_id' => $post['resto'] ? $post['resto'] : $post['no'],
        'post_id' => $post['no']
      ]);
    }
    */
    report_submit(BOARD_DIR, $no, $cat_id); // script dies here
  }
  
  die( '</body></html>' );
}

/**
 * Archive deletion function
 * only works on archived posts, for authed users
 */
function arcdel($no, $redirect = false, $redirect_res = null) {
  global $onlyimgdel;

  $delno = array();
  $time = $_SERVER['REQUEST_TIME'];
  reset( $_POST );
  
  while ($item = each($_POST)) {
    if ($item[1] == 'delete') {
      $delno[] = $item[0];
    }
  }
  
  $numdeletions = count($delno);
  
  if (!$numdeletions) {
    return;
  }
  
  $rebuild_archive_json = false;
  
  $rebuild = array();
  
  for ($i = 0; $i < $numdeletions; $i++) {
    $resto = delete_post($delno[$i], '', $onlyimgdel, 0, 1, $numdeletions == 1, false, true);
    if ($resto) {
      $rebuild[$resto] = true;
    }
    else if (!$onlyimgdel) {
      $rebuild_archive_json = true;
    }
  }
  
  if (!has_level('janitor')) {
    mysql_global_call("INSERT INTO user_actions (ip,board,action,postno,time) VALUES (%d,'%s','delete',%d,now())", ip2long( $_SERVER["REMOTE_ADDR"] ), BOARD_DIR, $delno[0]);
  }
  
  if ($redirect) {
    if ($redirect_res) {
      resredir($redirect_res, 1, true);
    }
    else {
      updating_index();
    }
    
    fastcgi_finish_request();
  }
  
  foreach ($rebuild as $thread_id => $true) {
    rebuild_archived_thread($thread_id);
  }
  
  if ($rebuild_archive_json && ENABLE_JSON_THREADS) {
    generate_board_archived_json();
  }
}

function user_delete( $no, $pwd, $redirect = false, $redirect_res = null )
{
	global $pwdc, $onlyimgdel, $captcha_bypass;
	
	if (UPLOAD_BOARD && $onlyimgdel) {
		error("It doesn't make any sense to do a file-only delete on a file board!");
	}

	$delno = array();
	$time = $_SERVER['REQUEST_TIME'];
	$delflag = false;
	reset( $_POST );

	while( $item = each( $_POST ) ) {
		if( $item[1] == 'delete' ) {
			array_push( $delno, $item[0] );
			$delflag = true;
		}
	}
	
  $user_is_known = false;
  
  if ($pwdc) {
    $userpwd = new UserPwd($_SERVER['REMOTE_ADDR'], MAIN_DOMAIN, $pwdc);
    
    if ($userpwd) {
      $pwd = $userpwd->getPwd();
      $user_is_known = $userpwd->maskLifetime() >= 900;
    }
    else {
      $pwd = null;
    }
  }
  else {
    $pwd = null;
  }
  
	$numdeletions = count( $delno );
	if( !$numdeletions ) return;
	$flag = false;

	if( !has_level( 'janitor' ) ) {
		$n = mysql_global_call( "select count(*)>%d from user_actions where ip=%d and action='delete' and time >= subdate(now(), interval 1 hour)", RENZOKU_DEL_HOURLY, ip2long( $_SERVER['REMOTE_ADDR'] ) );
		list( $h ) = mysql_fetch_row( $n );

		if( $h ) {
			//check_fail_floodcheck($no);
			error(S_FLOOD_DEL);
		}

		$n = mysql_global_call( "select count(*)>%d from user_actions where ip=%d and action='delete' and time >= subdate(now(), interval 1 day)", RENZOKU_DEL_DAILY, ip2long( $_SERVER['REMOTE_ADDR'] ) );
		list( $h ) = mysql_fetch_row( $n );

		if( $h ) {
			//check_fail_floodcheck($no);
			error(S_FLOOD_DEL);
		}
	}

	$rebuild = array(); // keys are pages that need to be rebuilt (0 is index, of course)
	
	$lazy_rebuild = false;
	
	if (isset($_POST['tool']) && $_POST['tool']) {
	  $tool = $_POST['tool'];
	}
	else {
	  $tool = null;
	}
	
	// Manual, single post deletion. Only rebuilds one page if deleting a reply.
	if ($numdeletions == 1) {
		$resto = delete_post( $delno[0], $pwd, $onlyimgdel, 0, 1, $numdeletions == 1, false, false, $tool, $user_is_known );
		if ($resto) {
			$rebuild[$resto] = 1;
		  calculate_indexes_to_rebuild($resto);
  		$lazy_rebuild = true;
		}
	}
	// Other (multi, automatic, etc...)
	else {
		for( $i = 0; $i < $numdeletions; $i++ ) {
			$resto = delete_post( $delno[$i], $pwd, $onlyimgdel, 0, 1, $numdeletions == 1, false, false, $tool, $user_is_known );
			if( $resto ) {
				$rebuild[$resto] = 1;
			}
		}
	}
	
	if (!has_level('janitor')) {
		mysql_global_call("INSERT INTO user_actions (ip,board,action,postno,time) VALUES (%d,'%s','delete',%d,now())", ip2long( $_SERVER["REMOTE_ADDR"] ), BOARD_DIR, $delno[0]);
	}
	
  if ($redirect) {
    if ($redirect_res) {
      resredir($redirect_res, 1, true);
    }
    else {
      updating_index();
    }
    
    fastcgi_finish_request();
  }
	
	rebuild_deletions($rebuild, $lazy_rebuild);
}

function updatelog_remote( $no, $noidx )
{
	if( !has_level() ) die( '' ); // anti dos
	$no    = intval( $no );
	$noidx = !!$noidx;
  // FIXME, running this when $noidx is true breaks cross-thread quotelinks.
	updatelog( $no, $noidx );
}

function _print( $s, $echo = 1 )
{
	if( $echo ) {
		echo $s;

		return;
	}

	ob_flush();
	flush();

	echo $s;
	echo str_repeat( ' ', 256 ) . "\n";

	ob_flush();
	flush();
}

function fancystyle()
{
	$style = <<<HTML
<style type="text/css">
body {
	font-family: Helvetica, Arial, sans-serif;
	font-size: 12pt;
}

h1 {
	margin: 0;
	padding: 0;
}
</style>
HTML;


	return $style;
}

function rebuild_catalog( $shutup = false )
{
	if( !has_level() ) die();
	if( !$shutup ) {
		echo fancystyle();
		echo '<h1>Rebuilding catalog...</h1>';
	}

	$start = microtime( true );
	generate_catalog();
	$time = round( microtime( true ) - $start, 6 );

	if( !$shutup ) {
		echo 'Done!<br><br>Rebuilding took ' . $time . ' seconds.<br><br>Redirecting to catalog...<br><br><meta http-equiv="refresh" content="5;URL=//boards.' . L::d(BOARD_DIR) . '/' . BOARD_DIR .  '/catalog">';
		die();
	}
}

function rebuild_boards_json()
{
	if( !has_level() ) die();
	echo fancystyle();
	echo '<h1>Rebuilding boards.json...</h1>';

	$start = microtime( true );
	$query = mysql_global_call( "SELECT dir as board,name as title FROM boardlist ORDER BY board ASC" );

	$boards = array();

	$host = 'https://sys.int';

	$post = array(
	  'mode' => 'cataloginfo'
	);

	while( $row = mysql_fetch_assoc( $query ) ) {
		if( $row['board'] == 'vp' ) $row['title'] = 'Pokémon';
		//cataloginfo
		$url = "$host/{$row['board']}/imgboard.php";
		
		$ch = rpc_start_request($url, $post, null, true);
		
		$response = rpc_finish_request($ch, $error, $httperror);
		
		if (!$response) {
			die( 'Could not generate info for /' . $row['board'] . '/; ' . $error );
		}
		
		$json = json_decode($response, true);

		foreach( $json as $key => $val ) {
			if( $key != 'board' && ctype_digit( $val ) ) $val = (int)$val;
			$row[$key] = $val;
		}

		$boards['boards'][] = $row;
	}
	
	// Dump resulting json on /test/
  if (BOARD_DIR === 'test') {
    echo '<br>';
    echo json_encode($boards, JSON_HEX_AMP | JSON_PRETTY_PRINT);
    echo '<br>';
  }
  else {
    $_json = json_encode($boards, JSON_HEX_AMP);
    print_page(BOARDS_ROOT . 'boards.json', $_json);
  }

	$time = round( microtime( true ) - $start, 6 );
	echo 'Done!<br><br>Rebuilding took ' . $time . ' seconds.<br><br>No redirect here boss. Off you go.';
	die();
}

function rebuild( $all = 0 )
{
	global $rebuildall, $fwritetimer;
	if( !has_level() ) die( '' ); // anti dos
  
	if (has_flag('developer')) {
    error_reporting(E_ALL);
	}
	
	header( "Pragma: no-cache" );
	_print(fancystyle());
	$l = $all ? 'all' : 'missing';

	_print( "Rebuilding $l replies and pages... <a href=\"//boards." . L::d(BOARD_DIR) . '/' . BOARD_DIR . "/\">Go back</a><br><br>\n" );
	log_cache();
	trim_db();
	trim_archive();
	mysql_board_lock( true );
	$starttime = microtime( true );
	$query = "SELECT no, resto FROM `" . SQLLOG . "` WHERE resto = 0 AND archived = 0 ORDER BY root DESC";
	$treeline = mysql_board_call($query);
	if (!$treeline) {
		echo S_SQLFAIL;
	}
	mysql_board_unlock();
	_print( "Writing...<br>\n" );
	if( $all ) {
		while( list( $no, $resto ) = mysql_fetch_row( $treeline ) ) {
			if( !$resto ) {
				_print( "Writing No.$no... " );
				updatelog( $no, 1 );
				$ext = TEST_BOARD ? "rp cache: ".realpath_cache_size() : "";
				_print( "<b>DONE!</b><br> $ext\n" );
			}
		}
		_print( "Writing index pages... " );
		updatelog();
		_print( "<b>DONE!</b><br>" );

    if (ENABLE_CATALOG) {
      _print( "Writing catalog..." );
      generate_catalog( true );
      _print( "<b>DONE!</b><br>" );
    }
    
    if (ENABLE_ARCHIVE) {
      _print( "Writing archive..." );
      rebuild_archive_list();
      _print( "<b>DONE!</b><br>" );
    }
	}
  
	$totaltime = microtime( true ) - $starttime;
	$proctimer = $totaltime - $fwritetimer;
	
	$peakmem = memory_get_peak_usage(true) / (1024*1024.0);
	$usedmem = memory_get_usage(true) / (1024*1024.0);
	
	$redir = '//boards.' . L::d(BOARD_DIR) . '/' . BOARD_DIR . '/';
	
echo <<<END
<br>Total running time (lock excluded): $totaltime seconds.
<br>Composed of:
<br>Time spent writing files: $fwritetimer seconds.
<br>Time spent processing: $proctimer seconds.
<br>Memory: Peak: $peakmem MB Final: $usedmem MB
<br>Pages created.
<br><br>
END;
/*
if (!TEST_BOARD) {
echo <<<END
Redirecting back to board.
<meta http-equiv="refresh" content="10;URL=$redir">
END;
}
*/
}

function rebuild_after_deletion( $no )
{
	if( !has_level() ) die();

	mysql_board_lock( true );

	if( !$treeline = mysql_board_call( "SELECT no FROM `" . SQLLOG . "` WHERE no = %d", $no ) ) {
		mysql_board_unlock();
		die( S_POSTGONE );
	}

	log_cache( 0, $no );
	mysql_board_unlock();

	updatelog( $no, 1 );

	die( $no . ' Rebuilt OK!' );
}

function updating_index()
{
	$proto = ( stripos( $_SERVER["HTTP_REFERER"], "https" ) !== false ) ? "https:" : "http:";
	echo "<!doctype html><head><meta http-equiv=\"refresh\" content=\"2;URL=$proto"
    . '//boards.' . L::d(BOARD_DIR) . '/' . BOARD_DIR . "/\"><title>"
    . S_UPDATING_INDEX . "</title></head><body><table style=\"font-family:times,serif;font-size:36pt;text-align:center;width:100%;height:300px;\"><td><strong>"
    . S_UPDATING_INDEX . "</strong></td></table>";
}

function require_request_method( $method )
{
	if (!isset($_SERVER['REMOTE_ADDR'])) return;
	$umethod = $_SERVER["REQUEST_METHOD"];
	//$req     = htmlspecialchars( $_REQUEST["mode"] );
	if( $umethod == "OPTIONS" || ( $umethod == "HEAD" && $method == "GET" ) ) return;
	if( $umethod != $method ) {
		error( S_REJECTTEXTBAN );
	}
}

function get_catalog_info() {
  $arr = array(
    'ws_board'            => (int)(CATEGORY == 'ws'),
    'per_page'            => (int)DEF_PAGES,
    'pages'               => (int)PAGE_MAX,
    'max_filesize'        => ((int)MAX_KB) * 1024,
    'max_webm_filesize'   => ((int)MAX_WEBM_FILESIZE) * 1024,
    'max_comment_chars'   => (int)MAX_COM_CHARS,
    'max_webm_duration'   => (int)MAX_WEBM_DURATION,
    'bump_limit'          => (int)MAX_RES,
    'image_limit'         => (int)MAX_IMGRES,
    'cooldowns'           => array(
      'threads'          => (int)RENZOKU3,
      'replies'          => (int)RENZOKU,
      'images'           => (int)RENZOKU2
    )
  );
  
  if (defined('META_DESCRIPTION')) {
    $arr['meta_description'] = META_DESCRIPTION;
  }
  
  if (SPOILERS) {
    $arr['spoilers'] = 1;
    if (SPOILER_NUM) {
      $arr['custom_spoilers'] = (int)SPOILER_NUM;
    }
  }
  
  if (DISP_ID) {
    $arr['user_ids'] = 1;
  }
  
  if (ENABLE_ARCHIVE) {
    $arr['is_archived'] = 1;
  }
  
  if (CODE_TAGS) {
    $arr['code_tags'] = 1;
  }
  
  if (SJIS_TAGS) {
    $arr['sjis_tags'] = 1;
  }
  
  if (JSMATH) {
    $arr['math_tags'] = 1;
  }
  
  if (SHOW_COUNTRY_FLAGS) {
    $arr['country_flags'] = 1;
  }
  
  if (ENABLE_BOARD_FLAGS) {
    $arr['board_flags'] = get_board_flags_selector();
  }
  
  if (ENABLE_WEBM_AUDIO) {
    $arr['webm_audio'] = 1;
  }
  
  if (MIN_W > 1) {
    $arr['min_image_width'] = (int)MIN_W;
  }
  
  if (MIN_H > 1) {
    $arr['min_image_height'] = (int)MIN_H;
  }
  
  if (TEXT_ONLY) {
    $arr['text_only'] = 1;
    $arr['require_subject'] = 1;
  }
  
  if (FORCED_ANON) {
    $arr['forced_anon'] = 1;
  }
  
  if (REQUIRE_SUBJECT) {
    $arr['require_subject'] = 1;
  }
  
  if (ENABLE_PAINTERJS) {
    $arr['oekaki'] = 1;
  }
  
  die(json_encode($arr));
}

/**
 * Generates context to append to thread links.
 */
function generate_href_context($sub, $com) {
  if (JANITOR_BOARD) {
    return '';
  }
  
  $context = '';
  
  if (strpos($sub, 'SPOILER<>') === 0) {
    $sub = substr($sub, 9);
  }
  
  if ($sub !== '') {
    $context = cleanup_context_string($sub);
  }
  
  if ($context === '' && $com !== '') {
    $context = $com;
    
    if (strpos($context, '<br>') !== false) {
      $context = str_replace('<br>', "\n", $context);
      $has_br = true;
    }
    else {
      $has_br = false;
    }
    
    $context = preg_replace('/(^|\s)https?:\/\/[^\s]{4,}/', '', $context);
    
    if (strpos($context, '<span class="abbr">') !== false) {
      $context = preg_replace('/<span class="abbr">.*<\/table>/', '', $context); // ???
    }
    if (strpos($context, '<strong') !== false) {
      $context = preg_replace('/<strong [^>]+>.*<\/strong>/', '', $context); // ???
    }
    
    if ($has_br) {
      $context = ltrim($context);
      $context = explode("\n", $context)[0];
    }
    
    $context = preg_replace('/<[^>]+>/', ' ', $context);
    $context = cleanup_context_string($context);
  }

    
  return $context;
}

/**
 * Generates page title from subjects and comments
 * params must be already html-escaped.
 */
function generate_page_title($thread_id, $sub, $com) {
  if (JANITOR_BOARD) {
    return strip_tags(TITLE);
  }
  
  if (UPLOAD_BOARD) {
    $sub = preg_replace('/^(\d+)\|/', '', $sub);
  }
  
  $context = '';
  
  if (strpos($sub, 'SPOILER<>') === 0) {
    $sub = substr($sub, 9);
  }
  
  if ($sub !== '') {
    $context = $sub;
  }
  
  if ($context === '' && $com !== '') {
    if (SJIS_TAGS && strpos($com, '<span class="sjis"') !== false) {
      $com = preg_replace('/<span class="sjis".+?<\/span>/', '[SJIS]', $com);
    }
    $context = str_replace('<br>', ' ', $com);
    $context = htmlspecialchars_decode($context, ENT_QUOTES);
    $context = mb_substr(strip_tags($context), 0, 50);
    $context = htmlspecialchars($context, ENT_QUOTES);
  }
  
  if ($context === '') {
    $context = 'No.' . $thread_id;
  }
  
  if (BOARD_DIR === 's4s') {
    return '[' . BOARD_DIR . '] - ' . $context;
  }
  else {
    return '/' . BOARD_DIR . '/ - ' . $context;
  }
}

/**
 * Generates metatags from subjects and comments
 * params must be already html-escaped.
 */
function generate_page_metatags($sub, $com) {
  if (JANITOR_BOARD) {
    return null;
  }
  
  $context = '';
  
  if (strpos($sub, 'SPOILER<>') === 0) {
    $sub = substr($sub, 9);
  }
  
  if ($sub !== '') {
    $context = $sub;
  }
  
  $ell = '';
  
  if ($context === '' && $com !== '') {
    $context = preg_replace('/(<br>|\s)+/', ' ', $com);
    $context = htmlspecialchars_decode(strip_tags($context), ENT_QUOTES);
    
    if (mb_strlen($context) > 100) {
      $ell = '...';
      $context = mb_substr($context, 0, 100);
    }
  }
  else {
    $context = htmlspecialchars_decode($context, ENT_QUOTES);
  }
  
  if (empty($context)) {
    return null;
  }
  else {
    $words = preg_split('/[[:punct:]\s]+/', $context);
    $keywords = '';
    foreach ($words as $word) {
      if (strlen($word) > 3) {
        $keywords .= ',' . $word;
      }
    }
  }
  
  $context = htmlspecialchars($context, ENT_QUOTES);
  $keywords = htmlspecialchars($keywords, ENT_QUOTES);
  
  return array($context.$ell, $keywords);
}

function cleanup_context_string($context) {
  $context = htmlspecialchars_decode($context, ENT_QUOTES);
  
  $context = strtolower(preg_replace('/[^a-zA-Z0-9\s]+/', '', $context));
  
  $length = 0;
  
  $words = explode(' ', $context);
  
  $context = array();
  
  foreach ($words as $word) {
    if ($word === '') {
      continue;
    }
    
    $length += strlen($word) + 1;
    
    if ($length > 50) {
      break;
    }
    
    $context[] = $word;
  }
  
  $context = implode('-', $context);
  
  return htmlspecialchars($context, ENT_QUOTES);
}

/**
 * Embedded data detection
 * Dies if the PNG or JPG file contains embedded data.
 * Returns true if the GIF file was modified, otherwise returns false.
 */
function cleanup_uploaded_file($file, $type) {
  $full_size = filesize($file);
  
  // 50KB
  $max_delta = 51200;
  
  switch ($type) {
    case '.png':
      $clean_size = get_clean_png_size($file);
      break;
    case '.jpg':
      if ($full_size < 204800) {
        return false;
      }
      $clean_size = get_clean_jpg_size($file);
      break;
    case '.gif':
      if ($full_size < 204800) {
        return false;
      }
      $clean_size = get_clean_gif_size($file);
      break;
    case '.swf':
      return true; // Why?
    default:
      return false;
  }
  
  if ($clean_size === false) {
    return false;
  }
  
  // PNGs can still fail the check even if no size delta is found
  if ($clean_size === -1) {
  	if ($type === '.gif') {
      $file = escapeshellcmd($file);
      $res = system("/usr/local/bin/gifsicle --no-extensions \"$file\" -o \"$file\" >/dev/null 2>&1");
      if ($res !== false) {
        return true;
      }
      else {
      	return false;
      }
  	}
  	else {
    	error(S_IMGCONTAINSFILE, $file);
  	}
  }
  
  $delta_size = $full_size - $clean_size;
  
  if ($delta_size > $max_delta) {
    if ($type === '.gif') {
      $file = escapeshellcmd($file);
      $res = system("/usr/local/bin/gifsicle --no-extensions \"$file\" -o \"$file\" >/dev/null 2>&1");
      if ($res !== false) {
        return true;
      }
    }
    else {
      error(S_IMGCONTAINSFILE, $file);
    }
  }
  
  return false;
}

// Returns the size of critical data or -1 if the file contains extensions.
function get_clean_gif_size($file) {
  $file = escapeshellcmd($file);
  
  $binary = '/usr/local/bin/gifsicle';
  
  $res = shell_exec("$binary --sinfo \"$file\" 2>&1");
  
  if ($res !== null) {
    $size = 0;
    
    if (preg_match('/  extensions [0-9]+/', $res)) {
      return -1;
    }
    
    if (preg_match_all('/compressed size ([0-9]+)/', $res, $m)) {
      foreach ($m[1] as $frame_size) {
        $size += (int)$frame_size;
      }
      
      return $size;
    }
  }
  
  return false;
}

// Returns the number of bytes in critical chunks or -1 if too many IDAT chunks are found
// Returns false on error
function get_clean_png_size($file) {
  $file = escapeshellcmd($file);
  
  $binary = '/usr/local/bin/pngcrush';
  
  $res = shell_exec("$binary -m 1 -n -v \"$file\" 2>&1 1>/dev/null");
  
  if ($res !== null) {
    if (preg_match('/Reading (?:iTXt|tEXt|zTXt) chunk,/', $res, $m)) {
      if (preg_match('/  [a-z]oo: /i', $res)) {
        return -1;
      }
      else if (preg_match('/  (?:Software|Creation Time): ([=a-zA-Z0-9]{10,})\n/', $res, $ct)) {
        $_b64 = base64_decode($ct[0]);
        if ($_b64 && preg_match('/[0-9]/', $_b64)) {
          write_to_event_log('png_link', $_SERVER['REMOTE_ADDR'], [
            'board' => BOARD_DIR,
            'meta' => htmlspecialchars($res)
          ]);
          return -1;
        }
      }
    }
    
    if (preg_match('/in critical chunks\s+=\s+([0-9]+)/', $res, $m)) {
      return (int)$m[1];
    }
  }
  
  return false;
}

function get_clean_jpg_size($file) {
  $eof = false;
  
  $img = fopen($file, 'rb');
  
  $data = fread($img, 2);
  
  if ($data !== "\xff\xd8") {
    fclose($img);
    return false;
  }
  
  while (!feof($img)) {
    $data = fread($img, 1);
    
    if ($data !== "\xff") {
      continue;
    }
    
    while (!feof($img)) {
      $data = fread($img, 1);
      
      if ($data !== "\xff") {
        break;
      }
    }
    
    if (feof($img)) {
      break;
    }
    
    $byte = unpack('C', $data)[1];
    
    if ($byte === 217) {
      $eof = ftell($img);
      break;
    }
    
    if ($byte === 0 || $byte === 1 || ($byte >= 208 && $byte <= 216)) {
      continue;
    }
    
    $data = fread($img, 2);
    
    $length = unpack('n', $data)[1];
    
    if ($length < 1) {
      break;
    }
    
    fseek($img, $length - 2, SEEK_CUR);
  }
  
  fclose($img);
  
  return $eof;
}

/**
 * Generates a "Pass User Since YEAR" string for pass users
 */
function get_since_4chan($pass_id) {
  $query = "SELECT UNIX_TIMESTAMP(purchase_date) FROM pass_users WHERE user_hash = '%s' ORDER BY purchase_date ASC LIMIT 1";
  
  $res = mysql_global_call($query, $pass_id);
  
  if (!$res) {
    return 0;
  }
  
  $row = mysql_fetch_row($res);
  
  $ts = (int)$row[0];
  
  if (!$ts) {
    return 0;
  }
  
  $ts = date('Y', $ts);
  
  if (!$ts) {
    return 0;
  }
  
  return (int)$ts;
}
/*
// Halloween 2017
function get_halloween_score($pass_id) {
  $query = "SELECT score FROM halloween_tricks WHERE user_hash = '%s' LIMIT 1";
  
  $res = mysql_global_call($query, $pass_id);
  
  if (!$res) {
    return 0;
  }
  
  $row = mysql_fetch_row($res);
  
  if (!$row) {
    return 0;
  }
  
  return (int)$row[0];
}

// Halloween 2017
/*
function get_halloween_dummy_pass($name) {
  if (!$name) {
    return '';
  }
  
  $hashed_bits = hash_hmac('sha1', $name, 'CIaPCUkJq9n3fskmKC06tquCV/2edWqbgBeY9pk7RlQ', true);
  
  $hashed_name = base64_encode($hashed_bits);
  
  if (!$hashed_name) {
    return '';
  }
  
  return '_' . substr($hashed_name, 0, 9);
}

// Halloween 2017
function process_halloween_score($com, $thread_id, $this_pass_id, $this_pwd, $pass_is_bannable) {
  if ($com === '') {
    return;
  }
  
  $thread_id = (int)$thread_id;
  
  if (!$thread_id) {
    return;
  }
  
  if (preg_match_all('/&gt;&gt;([0-9]{4,})/', $com, $m) === 1) {
    $post_id = (int)$m[1][0];
    
    if (!$post_id || $post_id == $thread_id) {
      return;
    }
    
    $query = 'SELECT host, pwd, 4pass_id FROM `' . SQLLOG . '` WHERE no = ' . $post_id . ' AND resto = ' . $thread_id;
    
    $res = mysql_board_call($query);
    
    if (!$res) {
      return;
    }
    
    $row = mysql_fetch_assoc($res);
    
    if (!$row) {
      return;
    }
    
    // Check if same person
    if (!$row['4pass_id'] || $row['4pass_id'] == $this_pass_id || $row['pwd'] == $this_pwd || $row['host'] == $_SERVER['REMOTE_ADDR']) {
      return;
    }
    
    $long_ip = ip2long($_SERVER['REMOTE_ADDR']);
    
    if (!$long_ip) {
      return;
    }
    
    // Check if already gave points
    $query = "SELECT 1 FROM `halloween_votes` WHERE long_ip = $long_ip AND board = '" . BOARD_DIR . "' AND post_id = $post_id";
    
    $res = mysql_global_call($query);
    
    if (!$res) {
      return;
    }
    
    if (mysql_num_rows($res) > 0) {
      return;
    }
    
    // Check if the user is known
    if (!spam_filter_is_user_known($long_ip, BOARD_DIR, $pass_is_bannable ? $this_pwd : null)) {
      return;
    }
    
    // Good to go
    $query = <<<SQL
INSERT INTO halloween_tricks (user_hash, score) VALUES ('%s', 1)
ON DUPLICATE KEY UPDATE score = score + 1
SQL;
    
    mysql_global_call($query, $row['4pass_id']);
    
    $query = "INSERT INTO halloween_votes (long_ip, board, post_id) VALUES ($long_ip, '" . BOARD_DIR . "', $post_id)";
    
    mysql_global_call($query);
  }
}

// Halloween 2017
function decrease_halloween_score($post_id, $ratio = 0.75) {
  $post_id = (int)$post_id;
  
  if (!$post_id) {
    return;
  }
  
  $query = 'SELECT 4pass_id FROM `' . SQLLOG . '` WHERE no = ' . $post_id;
  
  $res = mysql_board_call($query);
  
  if (!$res) {
    return;
  }
  
  $pass_id = mysql_fetch_row($res)[0];
  
  if (!$pass_id) {
    return;
  }
  
  $query = "UPDATE halloween_tricks SET score = FLOOR(score * %.2f) WHERE user_hash = '%s' LIMIT 1";
  
  mysql_global_call($query, $ratio, $pass_id);
}

// Halloween 2017
function get_halloween_css_cls($trick_count) {
  if ($trick_count >= 1000) {
    return " n-jol-6";
  }
  if ($trick_count >= 500) {
    return " n-jol-5";
  }
  if ($trick_count >= 200) {
    return " n-jol-4";
  }
  if ($trick_count >= 100) {
    return " n-jol-3";
  }
  if ($trick_count >= 50) {
    return " n-jol-2";
  }
  if ($trick_count >= 25) {
    return " n-jol-1";
  }
  return '';
}
*/

/**
 * Checks if the user has a recent ban request.
 * dies with an error if user needs to be blocked.
 */
function check_for_ban_request($ip, $pwd = null) {
  $time_lim = BLOCK_ON_BR_LEN;
  
  $clauses = [];
  
  $clauses[] = "host = '" . mysql_real_escape_string($ip) . "'";
  
  if ($pwd) {
    $clauses[] = "pwd = '" . mysql_real_escape_string($pwd) . "'";
  }
  
  $board_sql = mysql_real_escape_string(BOARD_DIR);
  
  $clauses = implode(' OR ', $clauses);
  
  $query = <<<SQL
SELECT tpl_name, board, TIMESTAMPDIFF(MINUTE, NOW() - $time_lim, ts) diff
FROM `ban_requests`
WHERE ($clauses)
AND (board = '$board_sql' OR global = 1)
AND warn_req = 0
AND (ts > DATE_SUB(NOW(), $time_lim) OR ban_template IN (1, 2, 123, 126))
LIMIT 1
SQL;
  
  $res = mysql_global_call($query);
  
  if (!$res || mysql_num_rows($res) !== 1) {
    return false;
  }
  
  $row = mysql_fetch_assoc($res);
  
  $time = (int)$row['diff'];
  
  $tpl_name = rtrim(preg_replace('/\[[^\]]+\]/', '', $row['tpl_name']));
  
  // Non-expiring blocks for some global templates
  if ($time < 0) {
    error(sprintf(S_BRBLOCKED_2, $tpl_name));
  }
  
  $str = $time <= 1 ? '1 minute' : "$time minutes";
  
  error(sprintf(S_BRBLOCKED, $tpl_name, $str));
}

/**
 * Checks if the IP is banned, also checks for ban evasion
 * return 0 for not banned, 1 for banned, 2 for warned
 * If the ban has expired, delete the banned thumbnail and de-activate the ban
 */
function check_for_ban($ip, $fields = array(), $thread_id = 0, $user_verified = false) {
  global $captcha_bypass;
  
  // Skip IP bans when the user has a valid 4chan Pass
  $skip_ip_bans = isset($fields['4pass_id']) && $captcha_bypass;
  
  if (!$skip_ip_bans) {
    $fields['host'] = $ip;
  }
  
  $expired = [];
  
  $is_banned = 0;
  
  foreach ($fields as $key => $value) {
$query =<<<SQL
SELECT no, global, board, post_num, template_id, 4pass_id, admin, reason,
UNIX_TIMESTAMP(now) as starts_on, UNIX_TIMESTAMP(length) as ends_on
FROM banned_users
WHERE active = 1 AND $key = '%s'
SQL;
    
    $result = mysql_global_call($query, $value);
    
    // Not banned
    if (mysql_num_rows($result) < 1) {
      continue;
    }
    
    while ($ban = mysql_fetch_assoc($result)) {
      $end = (int)$ban['ends_on'];
      
      // Warning
      if ($end && ($end - (int)$ban['starts_on'] < 1)) {
        $is_banned = 2;
        break 2;
      }
      
      // Ban has expired
      if ($end && $end <= $_SERVER['REQUEST_TIME']) {
        $expired[] = $ban;
        continue;
      }
      
      // Skip GR14 bans for pass users
      if ($key == '4pass_id') {
        if ($ban['template_id'] == 124) { // Global 14 - Proxy, VPN, or Tor Node
          continue;
        }
      }
      
      // Skip proxy autobans for verified users
      if ($user_verified && $key == 'host' && $ban['admin'] === 'Auto-ban') {
        if (strpos($ban['reason'], 'Proxy') !== false) {
          continue;
        }
      }
      
      if ($ban['global'] || $ban['board'] == BOARD_DIR) {
        $is_banned = 1;
        break 2;
      }
    }
  }
  
  // Cleanup expired bans
  if (!empty($expired)) {
    $salt = file_get_contents_cached(SALTFILE);
    
    $expired_ids = [];
    
    foreach ($expired as $ban) {
      $expired_ids[] = (int)$ban['no'];
      
      $hash = sha1($ban['board'] . $ban['post_num'] . $salt);
      
      $file_path = BANTHUMB_ROOT . $ban['board'] . '/' . $hash . 's.jpg';
      
      if (file_exists($file_path)) {
        unlink($file_path);
      }
    }
    
    $lim = count($expired_ids);
    
    $expired_ids = implode(',', $expired_ids);
    $query = "UPDATE banned_users SET active = 0, unbannedon = NOW(), unbannedby = 'expiration' WHERE no IN($expired_ids) LIMIT $lim";
    $result = mysql_global_do($query);
  }
  
  return $is_banned;
}

/**
 * Strips tags from webms and repairs broken streams.
 * This is needed to prevent people from bypassing duration limits.
 * $file must be safe to use as shell argument
 */
function remux_webm($file, $format, $strip_metadata = true) {
  $binary = '/usr/local/bin/ffmpeg-mp4';
  
  $out_file = $file . '_tmpff';
  
  if ($strip_metadata) {
    $map_meta = '-map_metadata -1 ';
  }
  else {
    $map_meta = '';
  }
  
  if ($format !== 'webm' && $format !== 'mp4') {
    return false;
  }
  
  // $file and $format must be safe for shell_exec
  shell_exec("$binary -f $format -i \"$file\" $map_meta-bitexact -c copy -f $format -y \"$out_file\"");
  
  if (!file_exists($out_file)) {
    return false;
  }
  
  $ret = rename($out_file, $file);
  
  clearstatcache(true, $file);
  
  return $ret;
}

/**
 * Remuxes and validates webm file
 * Returns video info on success array(width, height, sar, extension)
 * Dies if the file is invalid or not acceptable
 * $file must be safe to use as shell argument
 */
function validate_webm($file, $ext) {
  $binary = '/usr/local/bin/ffprobe-mp4';
  
  if ($ext == '.webm') {
    $format = 'webm';
  }
  else if ($ext == '.mp4') {
    $format = 'mp4';
  }
  else {
    error(S_NOREC, $file);
  }
  
  // Remux webm to strip extra data and repair broken streams
  if (!remux_webm($file, $format)) {
    error(S_NOREC, $file);
  }
  
  // $file and $format must be safe for proc_open
  $cmd = "$binary -f $format -i \"$file\" -hide_banner -show_streams -show_format -of json";
  
  $desc = [ 1 => ['pipe', 'w'], 2 => ['pipe', 'w'] ];
  
  $pipes = [];
  
  $proc = proc_open($cmd, $desc, $pipes);
  
  if ($proc === false || !is_resource($proc)) {
    error(S_FAILEDUPLOAD, $file);
  }
  
  $stdout = stream_get_contents($pipes[1]);
  fclose($pipes[1]);
  
  $stderr = stream_get_contents($pipes[2]);
  fclose($pipes[2]);
  
  $status = proc_close($proc);
  
  if ($status !== 0) {
    error(S_FAILEDUPLOAD, $file);
  }
  
  // Check stderr
  if (stripos($stderr, 'invalid') !== false) {
    error(S_NOREC, $file);
  }
  
  // Check stdout
  $res = json_decode($stdout, true);
  
  if (json_last_error() !== JSON_ERROR_NONE) {
    error(S_FAILEDUPLOAD, $file);
  }
  
  //print_r($res);
  
  if ($res['format']['format_name'] === 'matroska,webm') {
    $format = 'webm';
  }
  else if ($res['format']['format_name'] === 'mov,mp4,m4a,3gp,3g2,mj2') {
    $format = 'mp4';
  }
  else {
    error(S_NOREC, $file);
  }
  
  // container duration, can be forged but we remux the file to fix this
  $duration = (float)$res['format']['duration'];
  
  if ($duration <= 0 || $duration > MAX_WEBM_DURATION) {
    error(S_VIDEOTOOLONG, $file); // Duration too long
  }
  
  $has_audio = false;
  $video_dims = false;
  
  foreach ($res['streams'] as $stream) {
    $type = $stream['codec_type'];
    
    if ($type === 'audio') {
      if (!ENABLE_WEBM_AUDIO) {
        error(S_AUDIODISABLED, $file); // Audio streams are not allowed
      }
      
      // Vorbis or Opus for webm audio
      if ($format === 'webm') {
        if ($stream['codec_name'] !== 'vorbis' && $stream['codec_name'] !== 'opus') {
          error(S_BADAUDIO, $file); // Bad audio stream
        }
      }
      // AAC for mp4 audio
      else if ($format === 'mp4') {
        if ($stream['codec_name'] !== 'aac') {
          error(S_BADAUDIO, $file); // Bad audio stream
        }
      }
      else {
        error(S_BADAUDIO, $file); // Bad audio stream
      }
      
      $has_audio = true;
    }
    else if ($type === 'video') {
      if ($video_dims) {
        error(S_BADSTREAM, $file); // Too many video streams
      }
      
      // VP8 or VP9 for webm video
      if ($format === 'webm') {
        if ($stream['codec_name'] !== 'vp8' && $stream['codec_name'] !== 'vp9') {
          error(S_BADVIDEO, $file); // Bad video stream
        }
      }
      // H264 for mp4 video
      else if ($format === 'mp4') {
        if ($stream['codec_name'] !== 'h264') {
          error(S_BADVIDEO, $file); // Bad video stream
        }
        
        // Reject 10 bit streams
        if ($stream['bits_per_raw_sample'] > 8) {
          error(S_NOREC, $file);
        }
        
        // Only accept yuv420p streams
        if ($stream['pix_fmt'] !== 'yuv420p') {
          error(S_NOREC, $file);
        }
      }
      else {
        error(S_BADVIDEO, $file); // Bad video stream
      }
      
      $width = (int)$stream['width'];
      $height = (int)$stream['height'];
      
      if (!$width || !$height || $width > MAX_WEBM_DIMENSION || $height > MAX_WEBM_DIMENSION) {
        error(S_TOOLARGERES, $file); // Dimensions too big
      }
      
      $sar = null;
      
      if (isset($stream['sample_aspect_ratio'])) {
        $tmp_sar = explode(':', $stream['sample_aspect_ratio']);
        
        $tmp_sar[0] = (int)$tmp_sar[0];
        $tmp_sar[1] = (int)$tmp_sar[1];
        
        if ($tmp_sar[1] && $tmp_sar[0] !== $tmp_sar[1]) {
          $tmp_sar = $tmp_sar[0] / $tmp_sar[1];
          
          if ($tmp_sar < 2 && $tmp_sar > 0.5) {
            $sar = $tmp_sar;
          }
        }
      }
      
      $video_dims = array($width, $height, $sar);
    }
    else {
      error(S_BADSTREAM, $file); // Bad stream
    }
  }
  
  if (!$video_dims) {
    error(S_NOVIDEOSTREAM, $file); // No video streams
  }
  
  return $video_dims;
}

/**
 * Generates thumbnails for webm files
 * Returns the thumbnail filename on success.
 * Returns false if the thumbnail couldn't be generated.
 */
function thumb_webm($file, $ext) {
  $binary = '/usr/local/bin/ffmpeg-mp4';
  
  $out_file = $file . '.tmp.jpg';
  
  if ($ext === '.webm') {
    $format = 'webm';
  }
  else if ($ext === '.mp4') {
    $format = 'mp4';
  }
  else {
    return false;
  }
  
  // $file and $format must be safe for shell_exec
  $res = shell_exec("$binary -f $format -i \"$file\" -vframes 1 -an -y \"$out_file\" 2>&1");
  
  if (file_exists($out_file)) {
    return $out_file;
  }
  
  quick_log_to( "/www/perhost/bad-upload.log", "webm failure on $file:\n$res");

  return false;
}

/**
 * Contest banners 468x60
 */
function get_contest_banner() {
  $query = "SELECT file_id, file_ext, board FROM contest_banners WHERE is_live = 1 ORDER BY RAND() LIMIT 1";
  
  $res = mysql_global_call($query);
  
  if (!$res) {
    return '';
  }
  
  $banner = mysql_fetch_assoc($res);
  
  if (!$banner) {
    return '';
  }
  
  $img_url = STATIC_SERVER . "image/contest_banners/{$banner['file_id']}.{$banner['file_ext']}";
  $link_url = '//boards.' . L::d($banner['board']) . '/' . $banner['board'] . '/';
  
  return '<div><a href="' . $link_url . '"><img alt="" src="' . $img_url . '"></a></div>';
}

/**
 * Get latest post number from /j/
 * Returns a json { "no": 123 }
 */

function get_last_post_no() {
  $no = 0;
  $query = "SELECT no FROM `j` ORDER BY no DESC LIMIT 1";
  $res = mysql_board_call($query);
  if ($res) {
    if ($row = mysql_fetch_row($res)) {
      $no = (int)$row[0];
    }
  }
  echo "{\"no\":$no}";
}

/**
 * Deletes partial jsons for all live threads
 */
function purge_json_tails() {
  $query = 'SELECT no FROM `' . SQLLOG . '` WHERE resto = 0 AND archived = 0';
  
  $res = mysql_board_call($query);
  
  if (!$res) {
    return false;
  }
  
  while ($row = mysql_fetch_row($res)) {
    $thread_id = (int)$row[0];
    update_json_tail_deletion($thread_id, true);
  }
  
  return true;
}

/**
 * Deletes, if necessary, the partial json tail file after post deletion.
 */
function update_json_tail_deletion($thread_id, $force = false) {
  if (!JSON_TAIL_SIZE && !$force) {
    return false;
  }
  
  $tail_size = $force ? 0 : get_json_tail_size($thread_id);
  
  if (!$tail_size) {
    $fname = RES_DIR . $thread_id . '-tail.json';
    
    if (USE_GZIP) {
      $fname = "$fname.gz";
    }
    
    if (file_exists($fname)) {
      return unlink($fname);
    }
  }
  
  return false;
}

/**
 * Returns the number of posts in the partial -tail json.
 * 0 if no tail json is available
 */
function get_json_tail_size($thread_id) {
  global $log;
  
  $tail_size = (int)JSON_TAIL_SIZE;
  
  if (!$tail_size || !isset($log[$thread_id])) {
    return 0;
  }
  
  $th = $log[$thread_id];
  
  if ($th['sticky'] && $th['undead']) {
    $tail_size = $tail_size * 2;
  }
  
  $post_count = count($th['children']);
  
  if ($post_count >= $tail_size * 2) {
    return $tail_size;
  }
  else {
    return 0;
  }
}

/**
 * Test function for mobile image resizing
 */
function resize_mobile_image($path, $w, $h, $fsize, $tim, $ext) {
  if ($ext !== '.jpg' && $ext !== '.png') {
    return false;
  }
  
  $MAX_W = 1024;
  $MAX_H = 1024;
  $MAX_PXL = 524288;
  $MAX_PNG_BYTES = 524288;
  
  if ($ext === '.png' && $fsize <= $MAX_PNG_BYTES) {
    return;
  }
  
  if (($w > $MAX_W || $h > $MAX_H) && $w * $h > $MAX_PXL) {
    $jpeg_quality = 80;
    
    $memory_limit_increased = false;
    
    if ($w * $h > 3000000) {
      $memory_limit_increased = true;
      ini_set('memory_limit', memory_get_usage() + $w * $h * 15);
    }
    
    if ($ext === '.jpg') {
      $img_in = ImageCreateFromJPEG($path);
    }
    else {
      $img_in = ImageCreateFromPNG($path);
    }
    
    if (!$img_in) {
      error(S_FAILEDUPLOAD . ' (rmi)', $path);
    }
    
    $ratio = $w / $h;
    
    if ($ratio > 1) {
      $out_w = $MAX_W;
      $out_h = round($MAX_W / $ratio);
    }
    else {
      $out_w = round($MAX_H * $ratio);
      $out_h = $MAX_H;
    }
    
    $img_out = ImageCreateTrueColor($out_w, $out_h);
    
    ImageCopyResampled($img_out, $img_in, 0, 0, 0, 0, $out_w, $out_h, $w, $h);
    ImageDestroy($img_in);
    
    $out_path = IMG_DIR . $tim . 'm.jpg';
    ImageJPEG($img_out, $out_path, $jpeg_quality);
    ImageDestroy($img_out);
    
    if ($memory_limit_increased) {
      ini_restore('memory_limit');
    }
    
    return $out_path;
  }
}

/**
 * Returns the number of unique IPs for a given thread id
 * $thread_id needs to be cached in $log.
 */
function get_unique_ip_count($thread_id) {
  global $log;
  
  if (!isset($log[$thread_id]) || $log[$thread_id]['archived']) {
    return false;
  }
  
  $posts = $log[$thread_id]['children'];
  
  if (empty($posts)) {
    return 1;
  }
  
  $ip_map = array();
  $ip_count = 1;
  $ip_map[$log[$thread_id]['host']] = true;
  
  foreach ($posts as $pid => $val) {
    if (!isset($log[$pid])) {
      continue;
    }
    $post = $log[$pid];
    if (!isset($ip_map[$post['host']])) {
      ++$ip_count;
      $ip_map[$post['host']] = true;
    }
  }
  
  return $ip_count;
}

function generate_del_pwd() {
  return '_' . substr(str_shuffle('abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'), 0, 32);
}

function get_hashed_mod_name($name) {
  if (!$name) {
    die('Internal Server Error (ghmn0)');
  }
  
  $admin_salt = file_get_contents('/www/keys/2014_admin.salt');
  
  if (!$admin_salt) {
    die('Internal Server Error (ghmn1)');
  }
  
  $hashed_bits = hash_hmac('sha256', $name, $admin_salt, true);
  
  $hashed_name = base64_encode($hashed_bits);
  
  if (!$hashed_name) {
    die('Internal Server Error (ghmn2)');
  }
  
  return $hashed_name;
}

function create_memcached_instance() {
  $m = new Memcached();
  //$m->setOption(Memcached::OPT_TCP_NODELAY, true);
  $m->setOption(Memcached::OPT_SERVER_FAILURE_LIMIT, 1);
  $m->setOption(Memcached::OPT_SEND_TIMEOUT, 500000); // 500ms
  $m->setOption(Memcached::OPT_RECV_TIMEOUT, 500000); // 500ms
  $m->addServer(MEMCACHED_HOST, MEMCACHED_PORT);
  return $m;
}

function forcearchive() {
  if (!has_level()) {
    error("Can't let you do that.");
  }
  
  if (!ENABLE_ARCHIVE) {
    error('Archives are disabled on this board.');
  }
  
  if (!isset($_POST['id'])) {
    error('Bad Request.');
  }
  
  $tid = (int)$_POST['id'];
  
  $query = 'SELECT resto, sticky, archived, no, name, sub, com, filename, ext FROM `%s` WHERE no = %d';
  $res = mysql_board_call($query, BOARD_DIR, $tid);
  
  if (!$res) {
    error('Database error.');
  }
  
  $thread = mysql_fetch_assoc($res);
  
  if (!$thread || $thread['resto']) {
    error('Thread not found.');
  }
  
  if ($thread['archived']) {
    error('This thread is already archived.');
  }
  
  if ($thread['sticky']) {
    error(S_MAYNOTDELSTICKY);
  }
  
  archive_thread($tid);
  
  // Log the action
  $action_log_post = array(
    'no' => $thread['no'],
    'name' => $thread['name'],
    'sub' => $thread['sub'],
    'com' => $thread['com'],
    'filename' => $thread['filename'],
    'ext' => $thread['ext']
  );
  
  log_mod_action(3, $action_log_post);
  
  if (ENABLE_JSON_THREADS) {
    generate_board_archived_json();
  }
  
  updating_index();
}

// Called remotely by other tools
function rebuild_threads_by_id() {
  header('Content-Type: text/plain');
  
  if (!isset($_POST['ids']) || !is_array($_POST['ids']) || empty($_POST['ids'])) {
    echo '0';
    return;
  }
  
  $live_ids = array();
  
  // Rebuild archived threads first
  foreach ($_POST['ids'] as $id) {
    $id = (int)$id;
    
    if (!$id) {
      continue;
    }
    
    $query = "SELECT archived FROM `" . SQLLOG . "` WHERE no = $id LIMIT 1";
    $res = mysql_board_call($query);
    
    if (!$res) {
      echo '0';
      return;
    }
    
    if (mysql_fetch_row($res)[0] === '1') {
      rebuild_archived_thread($id);
    }
    else {
      $live_ids[] = $id;
    }
  }
  
  // Rebuild live threads
  if (!empty($live_ids)) {
	    
    foreach ($live_ids as $id) {
      updatelog($id, 1);
    }
    
    if (STATIC_REBUILD) {
      return;
    }
    
    updatelog(0, 0); // rebuild indexes
  }
  
  echo '1';
}

function rebuild_archive_list($print = false) {
  $board = BOARD_DIR;
  
  $html = '';
  
  $maxlen = 100;
  
  $max_age_in_days = 3;
  
  $hour_clause = $max_age_in_days * 24;
  
  $thread_limit = 3000;
  
  $query = <<<SQL
SELECT no, sub, com
FROM `$board`
WHERE archived = 1 AND resto = 0 AND root >= DATE_SUB(NOW(), INTERVAL $hour_clause HOUR)
ORDER BY root DESC
LIMIT $thread_limit
SQL;
  
  $res = mysql_board_call($query);
  
  $thread_count = mysql_num_rows($res);
  
  head($html, 0, 0, 0, 0, true);
  
  $html .= '<div class="navLinks mobile">
    <span class="mobileib button"><a href="/' . BOARD_DIR . '/" accesskey="a">' . S_RETURN . '</a></span> <span class="mobileib button"><a href="/' . BOARD_DIR . '/catalog">' . S_CATALOG . '</a></span> <span class="mobileib button"><a href="#bottom">' . S_BOTTOM . '</a></span>
  </div>';
  
  $html .= '<hr class="desktop">
    <div class="navLinks desktop">
    [<a href="/' . BOARD_DIR . '/" accesskey="a">' . S_RETURN . '</a>] [<a href="/' . BOARD_DIR . '/catalog">' . S_CATALOG . '</a>] [<a href="#bottom">' . S_BOTTOM . '</a>]
  </div><hr>';
  
  $html .= '<h4 class="center">Displaying ' . number_format($thread_count) . ' expired thread'
    . (!$thread_count || $thread_count > 1 ? 's' : '') . ' from the past ' . $max_age_in_days . ' day'
    . ($max_age_in_days > 1 ? 's' : '') . '</h4>
  <table id="arc-list" class="flashListing"><thead><tr>
    <td class="postblock">No.</td>
    <td class="postblock">Excerpt</td>
    <td class="postblock"></td>
  </tr></thead><tbody>';
  
  while ($row = mysql_fetch_assoc($res)) {
    if (strpos($row['sub'], 'SPOILER<>') === 0) {
      $row['sub'] = substr($row['sub'], 9);
    }
    
    if (!empty($row['sub'])) {
      if ($row['com'] !== '') {
        $teaser = '<b>' . $row['sub'] . ':</b> ' . $row['com'];
      }
      else {
        $teaser = $row['sub'];
      }
    }
    else {
      $teaser = $row['com'];
    }
    
    $teaser = preg_replace('/(?:<br>)+/', ' ', str_replace('&quot;', "'", $teaser));
    
    $href_context = generate_href_context($row['sub'], $row['com']);
    
    if ($href_context !== '') {
      $href_context = "/$href_context";
    }
    
    $html .= '<tr>
<td>' . $row['no'] . '</td>
<td class="teaser-col">'
  . truncate_comment($teaser, $maxlen) .
'</td>
<td>[<a class="quotelink" href="/' . $board . '/thread/'
  . $row['no'] . $href_context . '">View</a>]
</td>
</tr>';
  }
  
  $html .= '</tbody></table><hr>';
  
  $html .= '<div class="navLinks navLinksBot desktop">[<a href="/'
      . BOARD_DIR . '/" accesskey="a">' . S_RETURN . '</a>] [<a href="/'
      . BOARD_DIR . '/catalog">' . S_CATALOG . '</a>] [<a href="#top">'
      . S_TOP . '</a>] </div><hr class="desktop">';
  
  $html .= '<div class="navLinks mobile"><span class="mobileib button"><a href="/'
    . BOARD_DIR . '/" accesskey="a">'
    . S_RETURN . '</a></span> <span class="mobileib button"><a href="/'
    . BOARD_DIR . '/catalog">'
    . S_CATALOG . '</a></span> <span class="mobileib button"><a href="#top">'
    . S_TOP . '</a></span></div><hr class="mobile">';
  
  if (AD_BOTTOM_ENABLE == 1) {
    $bottomad = '';
    
    if (defined('AD_BOTTOM_TEXT') && AD_BOTTOM_TEXT) {
      $bottomad .= '<div class="bottomad center ad-cnt">'
        . ad_text_for(AD_BOTTOM_TEXT) . '</div>'
        . (defined('AD_BOTTOM_PLEA') ? AD_BOTTOM_PLEA : '');
    }
    
    if ($bottomad) {
      $html .= "$bottomad<hr>";
    }
  }
  
  $html .= '<div class="bottomCtrl desktop">';
  
  if (!defined('CSS_FORCE')) {
    $html .= '<span class="stylechanger">Style: 
      <select id="styleSelector">
        <option value="Yotsuba New">Yotsuba</option>
        <option value="Yotsuba B New">Yotsuba B</option>
        <option value="Futaba New">Futaba</option>
        <option value="Burichan New">Burichan</option>
        <option value="Tomorrow">Tomorrow</option>
        <option value="Photon">Photon</option>';
    
    if (defined('CSS_EVENT_NAME') && CSS_EVENT_NAME) {
      $html .= '<option value="_special">Special</option>';
    }
    
    $html .= '</select>
    </span>';
  }
  
  $html .= '</div>';
  
  foot($html, false, true);
  
  if ($print) {
    echo $html;
  }
  else {
    print_page(INDEX_DIR . 'archive'. PHP_EXT, $html);
  }
}

function rebuild_syncframe_page($print = false) {
  $tpl_file = YOTSUBA_DIR . 'views/syncframe.html';
  
  if (!file_exists($tpl_file)) {
    die('Template file not found');
  }
  
  $html = file_get_contents($tpl_file);
  
  if ($print) {
    die($html);
  }
  else {
    print_page(BOARDS_ROOT . 'syncframe' . PHP_EXT, $html);
  }
}

function rebuild_search_page($print = false) {
  $html = '';
  
  // board select box
  
  $board_select_html = '<select class="g-search-ctrl" id="js-sf-bf"><option value="">All Boards</option>';
  
  $query = 'SELECT dir, name FROM boardlist ORDER BY dir ASC';
  
  $res = mysql_global_call($query);
  
  if (!$res) {
    error('Database Error (rsp0)');
  }
  
  while ($row = mysql_fetch_assoc($res)) {
    $board_select_html .= '<option value="' . $row['dir'] . '">/' . $row['dir'] . '/ - ' . htmlspecialchars($row['name'], ENT_QUOTES) . '</option>';
  }
  
  $board_select_html .= '</select>';
  
  // header ---
  
  $cssVersion = $print ? CSS_VERSION_TEST : CSS_VERSION;
  $defaultcss = 'yotsubanew';
  $mobilecss  = 'yotsubamobile.' . $cssVersion . '.css';
  
  $styles = array(
    'Yotsuba New'   => "yotsubanew.$cssVersion.css",
    'Yotsuba B New' => "yotsubluenew.$cssVersion.css",
    'Futaba New'    => "futabanew.$cssVersion.css",
    'Burichan New'  => "burichannew.$cssVersion.css",
    'Photon'        => "photon.$cssVersion.css",
    'Tomorrow'      => "tomorrow.$cssVersion.css"
  );
  
  $dcssl = $defaultcss . '.' . $cssVersion . '.css';
  
  $css = '<link rel="stylesheet" title="switch" href="' . STATIC_SERVER . 'css/' . $dcssl . '">';
  
  foreach ($styles as $style => $stylecss) {
    $css .= '<link rel="alternate stylesheet" style="text/css" href="' . STATIC_SERVER . 'css/' . $stylecss . '" title="' . $style . '">';
  }
  
  $css .= '<link rel="stylesheet" href="' . STATIC_SERVER . 'css/' . $mobilecss . '">';
  
  $scriptjs = '<script type="text/javascript">var style_group = "nws_style";</script>';
  
  $testjs    = $print ? 'test/core-8psvqAqszI.' . JS_VERSION_TEST . '.js' : 'core.min.' . JS_VERSION_CORE . '.js';
  $testextra = $print ? 'test/extension-8psvqAqszI.' . JS_VERSION_TEST . '.js' : 'extension.min.' . JS_VERSION_EXT . '.js';

  $scriptjs .= '<script type="text/javascript" data-cfasync="false" src="' . STATIC_SERVER . 'js/' . $testjs . '"></script>';
  $scriptjs .= '<script type="text/javascript" data-cfasync="false" src="' . STATIC_SERVER . 'js/' . $testextra . '"></script>';
  
  if (defined('FAVICON')) {
    $favicon = '<link rel="shortcut icon" href="' . FAVICON . '">';
  }
  else {
    $favicon = '';
  }
  
  $includenav = file_get_contents_cached(NAV_TXT);
  
  $html .= '<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<meta name="robots" content="' . META_ROBOTS . '">
<meta name="description" content="4chan Search">
<meta name="keywords" content="4chan,search">
<meta name="viewport" content="width=device-width,initial-scale=1">
' . $favicon . '
' . $css . '
<title>Search 4chan</title>' . $scriptjs .
'</head>
<body class="is_search">' . $stylejs . $includenav .
'<div class="boardBanner">
  <div class="boardTitle">4chan Search</div>
</div>';

  // ---
  
  $html .= '<hr class="desktop"><form action="#" id="g-search-form"><input placeholder="Search" class="g-search-ctrl" id="js-sf-qf" type="text">' . $board_select_html . '<button class="g-search-ctrl" id="js-sf-btn">Search</button></form>';
  
  $html .= '<hr>
  <form name="delform" id="delform">
  <div class="board">';
  
  $html .= '</div><hr>';
  
  $html .= '<div class="bottomCtrl desktop">';
  
  if (!defined('CSS_FORCE')) {
    $html .= '<span class="stylechanger">Style: 
      <select id="styleSelector">
        <option value="Yotsuba New">Yotsuba</option>
        <option value="Yotsuba B New">Yotsuba B</option>
        <option value="Futaba New">Futaba</option>
        <option value="Burichan New">Burichan</option>
        <option value="Tomorrow">Tomorrow</option>
        <option value="Photon">Photon</option>';
    
    if (defined('CSS_EVENT_NAME') && CSS_EVENT_NAME) {
      $html .= '<option value="_special">Special</option>';
    }
    
    $html .= '</select>
    </span>';
  }
  
  $html .= '</div></form>';
  
  foot($html);
  
  if ($print) {
    echo $html;
  }
  else {
    print_page(BOARDS_ROOT . 'globalsearch' . PHP_EXT, $html);
  }
}

/**
 * Enforce the maximum number of allowed threads per user, per board.
 * error() if limit has been reached
 */
function validate_user_thread_limit($ip, $password = null, $pass_id = null) {
  $clauses = array();
  
  $clauses[] = "host = '" . mysql_real_escape_string($ip) . "'";
  
  if ($password) {
    $clauses[] = "pwd = '" . mysql_real_escape_string($password) . "'";
  }
  
  if ($pass_id) {
    $clauses[] = "4pass_id = '" . mysql_real_escape_string($pass_id) . "'";
  }
  
  $ts = $_SERVER['REQUEST_TIME'] - ((int)MAX_USER_THREADS_PERIOD * 3600);
  
  $clauses = implode(' OR ', $clauses);
  
  $query = 'SELECT COUNT(*) FROM `' . SQLLOG
    . "` WHERE resto = 0 AND archived = 0 AND time > $ts AND ($clauses)";
  
  $res = mysql_board_call($query);
  
  if (!$res) {
    return true;
  }
  
  $count = (int)mysql_fetch_row($res)[0];
  
  if ($count >= (int)MAX_USER_THREADS) {
    $plural = MAX_USER_THREADS > 1 ? 's' : '';
    error(sprintf(S_TOOMANYTHREADS, MAX_USER_THREADS, $plural));
  }
  
  return true;
}

function is_poster_op($host, $hashed_pwd, $resto) {
  $query = 'SELECT host, pwd FROM `%s` WHERE no = %d';
  $res = mysql_board_call($query, SQLLOG, $resto);
  
  if (!$res) {
    return false;
  }
  
  $post = mysql_fetch_assoc($res);

  if (!$post) {
    return false;
  }
  
  return $post['host'] === $host || $post['pwd'] === $hashed_pwd;
}

function spam_filter_check_qa_bot($board, $resto, $ip, $country, $com, $captcha_resp) {
  if (preg_match('/Edge|Safari|WebKit|Firefox|Mozilla/', $_SERVER['HTTP_USER_AGENT']) && $captcha_resp['hostname'] === 'boards.4chan.org') {
    return true;
  }
  
  // Check if IP is known
  $long_ip = ip2long($ip);
  
  if (spam_filter_is_user_known($long_ip)) {
    return false;
  }
  
  if (!preg_match('/Edge|Mobile/', $_SERVER['HTTP_USER_AGENT']) && preg_match('/WebKit/', $_SERVER['HTTP_USER_AGENT']) !== preg_match('/WebKit/', $_SERVER['HTTP_CONTENT_TYPE'])) {
    return true;
  }
  
  $bot_countries = array(
    'AD','AE','AF','AG','AI','AL','AM','AN','AO','AR','AS','AW','AZ',
    'BB','BD','BF','BG','BH','BI','BJ','BM','BN','BO','BR','BS','BT','BV','BW','BY','BZ',
    'CC','CF','CG','CH','CI','CK','CL','CM','CN','CO','CR','CU','CV','CX','CY','CZ',
    'DJ','DM','DO','DZ','EC','EE','EG','EH','ER','ET','FJ','FM','FO',
    'GA','GD','GE','GF','GH','GI','GL','GM','GN','GP','GQ','GR','GS','GT','GU','GY',
    'HK','HM','HN','HT','HU','HR','ID','IL','IN','IO','IQ','IR','IS','JM','JO',
    'KE','KG','KH','KI','KM','KN','KR','KW','KY','KZ',
    'LA','LB','LC','LI','LK','LR','LS','LU','LY',
    'MA','MD','MG','MH','MK','ML','MM','MN','MO','MP','MQ','MR','MS','MT','MU','MV','MW','MY','MZ','NA',
    'NE','NF','NG','NI','NP','NR','NU','NZ','OM','PA','PE','PF','PG','PH','PK','PM','PN','PR','PS','PT','PW',
    'QA','RE','RS','RO','RU','RW','SA','SB','SC','SD','SH','SI','SJ','SK','SL','SM','SN','SO','SR','ST','SV','SY','SZ',
    'TC','TD','TF','TG','TJ','TM','TN','TO','TP','TR','TT','TV','TW','TZ','UG','UM','UY','UZ',
    'VA','VE','VC','VG','VI','VN','VU','WF','WS','YE','YT','ZA','ZM','ZR','ZW'
  );
  
  // Check country
  if (!in_array($country, $bot_countries)) {
    return false;
  }
  
  if ($resto) {
    $query = 'SELECT time FROM %s WHERE resto = %d ORDER BY no DESC LIMIT 1';
    
    $res = mysql_board_call($query, $board, $resto);
    
    if (!$res) {
      return false;
    }
    
    $last_time = mysql_fetch_row($res);
    
    if (!$last_time) {
      return false;
    }
    
    $last_time = (int)$last_time[0];
    
    if ($_SERVER['REQUEST_TIME'] - $last_time < 14400) {
      return false;
    }
  }
  
  return true;
}

function log_qa_spam_filter($is_hit, $thread_id, $ip, $country, $captcha_resp) {
  $_bot_headers = '';
  
  foreach ($_SERVER as $_h_name => $_h_val) {
    if (substr($_h_name, 0, 5) == 'HTTP_') {
      $_bot_headers .= "$_h_name: $_h_val\n";
    }
  }
  
  $_bot_headers .= "_Captcha: " . $captcha_resp['hostname'] . "\n";
  
  $_bot_headers .= "_Country: $country\n";
	
	log_spam_filter_trigger($is_hit ? 'blocked_qa' : 'ok_qa', BOARD_DIR, $thread_id, $ip, 1, $_bot_headers);
}

function log_spam_filter_trigger($action, $board, $thread_id, $ip, $arg_num, $meta = '') {
  $query = <<<SQL
INSERT INTO event_log(`type`, `board`, `arg_num`, `thread_id`, `ip`, `meta`)
VALUES('%s', '%s', %d, %d, '%s', '%s')
SQL;
    
  mysql_global_call($query, $action, $board, $arg_num, $thread_id, $ip, $meta);
}

function preview_html() {
  if ($_SERVER['REQUEST_METHOD'] != 'POST') {
    updating_index();
    return;
  }
  
  if (!has_level('mod')) {
    updating_index();
    return;
  }
  
  if (!has_flag('html') && !has_flag('developer')) {
    updating_index();
    return;
  }
  
  header('Content-type: application/json');
  
  if (isset($_POST['com'])) {
    $html = $_POST['com'];
  }
  else {
    $data = array('status' => 'error', 'message' => 'Nothing to do.');
    echo json_encode($data);
    return;
  }
  
  $html = purify_html($html);
  
  $data = array('status' => 'success', 'data' => $html);
  
  echo json_encode($data);
}

function purify_html($html) {
  static $purifier = null;
  
  if ($purifier === null) {
    require_once 'lib/htmlpurifier/HTMLPurifier.standalone.php';
    
    $config = HTMLPurifier_Config::createDefault();
    $config->set('Cache.DefinitionImpl', null);
    
    $config->set('HTML.Doctype', 'HTML 4.01 Transitional');
    
    $config->set('URI.AllowedSchemes', array('http' => true, 'https' => true));
    $config->set('HTML.SafeIframe', true);
    $config->set('URI.SafeIframeRegexp', HTML_IFRAME_WHITELIST);
    $config->set('HTML.Allowed', HTML_WHITELIST);
    $config->set('CSS.AllowTricky', true);
    $config->set('CSS.Trusted', true);
    $config->set('Attr.AllowedFrameTargets', array('_blank'));
    
    $def = $config->getHTMLDefinition(true);
    $def->addAttribute('iframe', 'allowfullscreen', 'Bool');
    
    $def->addElement('video', 'Block', 'Flow', 'Common', array(
      'controls'  => 'Bool',
      'height'    => 'Length',
      'width'     => 'Length',
      'poster'    => 'URI',
      'autoplay'  => 'Bool',
      'loop'      => 'Bool',
      'muted'     => 'Bool',
      'src'       => 'URI'
    ));
    
    $css_def = $config->getDefinition('CSS');
    
    $css_def->info['color'] = new HTMLPurifier_AttrDef_CSS_Composite(
      array(
        new HTMLPurifier_AttrDef_Enum(array('transparent')),
        new HTMLPurifier_AttrDef_CSS_Color()
      )
    );
    
    $purifier = new HTMLPurifier($config);
    
    if (!$purifier) {
      error('Internal Server Error');
    }
  }
  
  return $purifier->purify($html);
}

function count_thread_replies($board, $thread_id) {
  $thread_id = (int)$thread_id;
  
  if ($thread_id <= 0) {
    return 0;
  }
  
  $sql = "SELECT COUNT(*) as cnt FROM `%s` WHERE resto = $thread_id";
  
  $res = mysql_board_call($sql, $board);
  
  if (!$res) {
    return 0;
  }
  
  return (int)mysql_fetch_row($res)[0];
}

// TODO: remove later
function check_safe_ua_sig($ua, $sig) {
  if (!$ua || !$sig) {
    return true;
  }
  
  $thres = 3;
  
  $ua_sig = "$ua.$sig";
  
  $sql = "SELECT 1 FROM event_log WHERE type = 'log_safe_ua' AND ua_sig = '%s' LIMIT $thres";
  
  $res = mysql_global_call($sql, $ua_sig);
  
  if (!$res) {
    return true;
  }
  
  if (mysql_num_rows($res) < $thres) {
    return false;
  }
  
  return true;
}

// April 2024
function april_2024_parse_email($email) {
  if ($email[0] != '$') {
    return 0;
  }
  
  $tag = substr(trim($email), 1);
  
  $stocks = april_2024_get_stock_list();
  
  $idx = array_search($tag, $stocks);
  
  if ($idx === false) {
    return 0;
  }
  
  $count = april_2024_get_stock_count($tag);
  
  if ($count < 10) {
    return 0;
  }
  
  return 10000 + $idx;
}

function april_2024_get_stock_list() {
  static $stocks = [
    'PEPE', 'WOJK', 'ANIME', 'CHAD', 'CLOWN', 'LOL', 'SICP', 'AUTSM', 'BANE',
    'CIA', 'BOOB', 'RDDT', 'DESU', 'JANNY', 'GME', 'CHUCK', 'YTSB', 'GACHI'
  ];
  
  return $stocks;
}

function april_2024_get_stock_from_s4p($since4pass) {
  if ($since4pass < 10000) {
    return false;
  }
  
  $val = $since4pass - 10000;
  
  if ($val < 0) {
    return false;
  }
  
  $badges = april_2024_get_stock_list();
  
  if ($val >= 0 && $val < count($badges)) {
    return $badges[$val];
  }
  else {
    return false;
  }
}

function april_2024_get_name() {
  $net_worth = april_2024_get_net_worth();
  
  if ($net_worth < 500) {
    return 'Destitute Investor';
  }
  else if ($net_worth < 1500) {
    return 'Helpless Investor';
  }
  else if ($net_worth < 5000) {
    return 'Poor Investor';
  }
  else if ($net_worth < 50000) {
    return 'Fledgling Investor';
  }
  else if ($net_worth < 500000) {
    return 'Aspiring Investor';
  }
  else if ($net_worth < 2000000) {
    return 'Rich Investor';
  }
  else if ($net_worth < 5000000) {
    return 'Anonymous Magnate';
  }
  else {
    return 'Anonymous Mogul';
  }
}

function april_2024_get_post_cls($since4pass) {
  $stock = april_2024_get_stock_from_s4p($since4pass);
  
  if ($stock) {
    return " p-xa24-$stock";
  }
  else {
    return '';
  }
}

function april_2024_get_name_badge($since4pass) {
  $stock = april_2024_get_stock_from_s4p($since4pass);
  
  if ($stock) {
    return " <span data-tip=\"$stock\" class=\"n-xa24 n-xa24-$stock\"></span>";
  }
  else {
    return '';
  }
}

function april_2024_get_stock_count($stock) {
  $userpwd = UserPwd::getSession();
  
  if (!$userpwd || $userpwd->isNew()) {
    return 0;
  }
  
  $user_id = $userpwd->getPwd();
  
  $sql =<<<SQL
SELECT SUM(amount) as amount FROM april_stock_users
WHERE user_id = '%s' AND stock = '%s'
SQL;

  $res = mysql_global_call($sql, $user_id, $stock);
  
  if (!$res) {
    return 0;
  }
  
  $val = (int)mysql_fetch_row($res)[0];
  
  if ($val < 0) {
    $val = 0;
  }
  
  return $val;
}

function april_2024_get_net_worth() {
  $userpwd = UserPwd::getSession();
  
  if (!$userpwd || $userpwd->isNew()) {
    return 0;
  }
  
  $user_id = $userpwd->getPwd();
  
  $sql =<<<SQL
SELECT stock, SUM(amount) as amount FROM april_stock_users
WHERE user_id = '%s' GROUP BY stock HAVING amount > 0
SQL;

  $res = mysql_global_call($sql, $user_id);
  
  if (!$res) {
    return 0;
  }
  
  $stocks = [];
  
  while ($row = mysql_fetch_row($res)) {
    $stocks[$row[0]] = (int)$row[1];
  }
  
  $sql =<<<SQL
SELECT stock, price FROM april_stock_prices
ORDER BY id DESC LIMIT 30
SQL;
  
  $res = mysql_global_call($sql);
  
  if (!$res) {
    return 0;
  }
  
  $prices = [];
  
  while ($row = mysql_fetch_row($res)) {
    if (isset($prices[$row[0]])) {
      continue;
    }
    $prices[$row[0]] = (int)$row[1];
  }
  
  $net_worth = $stocks['_'];
  
  foreach ($stocks as $stock => $count) {
    if (isset($prices[$stock])) {
      $net_worth += ($prices[$stock] * $count);
    }
  }
  
  return $net_worth;
}

// ---

function clear_no_captcha_token() {
  setcookie('_ct', null, -3600, '/', '.' . L::d(BOARD_DIR));
}

function generate_no_captcha_token() {
  if (BOARD_DIR === 'pol' || BOARD_DIR === 'b' || BOARD_DIR === 'r9k' || BOARD_DIR === 'bant') {
    return false;
  }
  
  $long_ip = ip2long($_SERVER['REMOTE_ADDR']);
  
  if (!$long_ip) {
    return false;
  }
  
  if (!spam_filter_is_user_known($long_ip, BOARD_DIR, null, 15)) {
    return false;
  }
  
  $salt = file_get_contents_cached(SALTFILE);
  
  if (!$salt) {
    return false;
  }
  
  $time = $_SERVER['REQUEST_TIME'];
  
  $msg = $_SERVER['REMOTE_ADDR'] . '.' . $time;
  
  $msg = hash_hmac('sha1', $msg, $salt);
  
  if (!$msg) {
    return false;
  }
  
  $msg = substr($msg, 0, 20) . '.' . $time;
  
  setcookie('_ct', $msg, $time + 300, '/', '.' . L::d(BOARD_DIR)); // 5 minutes
}

function verify_no_captcha_token($token) {
  list($hash, $ts) = explode('.', $token);
  
  $ts = (int)$ts;
  
  if (!$hash || !$ts) {
    return false;
  }
  
  if ($ts < $_SERVER['REQUEST_TIME'] - 300) { // 5 minutes
    return false;
  }
  
  $salt = file_get_contents_cached(SALTFILE);
  
  if (!$salt) {
    return false;
  }
  
  $msg = $_SERVER['REMOTE_ADDR'] . '.' . $ts;
  
  if (substr(hash_hmac('sha1', $msg, $salt), 0, 20) === $hash) {
    return true;
  }
  
  return false;
}

function get_random_real_name() {
  $first_name_nid = mt_rand(1, 1000);
  
  if (mt_rand(0, 999) < 10) {
    $type = 2;
  }
  else {
    $type = 1;
  }
  
  $query = "SELECT data FROM april_names WHERE nid = $first_name_nid AND type = $type";
  $res = mysql_global_call($query);
  $first_name = mysql_fetch_row($res)[0];
  
  if (!$first_name) {
    $first_name = 'Alberto';
  }
  
  $last_name_nid = mt_rand(1, 1000);
  
  $query = "SELECT data FROM april_names WHERE nid = $last_name_nid AND type = 3";
  $res = mysql_global_call($query);
  $last_name = mysql_fetch_row($res)[0];
  
  if (!$last_name) {
    $last_name = 'Barbosa';
  }
  
  return "$first_name $last_name";
}


function log_mod_action($action_type, $post, $vip_capcode = false) {
  $mask_shift = 128;
  $action_id = $mask_shift + $action_type;
  
  $query =<<<SQL
INSERT INTO actions_log (oldmask, newmask, postno, board, name, sub, com, filename, admin)
VALUES (0, %d, %d, '%s', '%s', '%s', '%s', '%s', '%s')
SQL;
  
  mysql_global_call($query,
    $action_id,
    $post['no'],
    BOARD_DIR,
    $post['name'],
    $post['sub'],
    $post['com'],
    $post['filename'] . $post['ext'],
    $vip_capcode === false ? $_COOKIE['4chan_auser'] : ''
  );
  
  return true;
}

function get_request_xff() {
  $xff = $_SERVER['HTTP_X_FORWARDED_FOR'];
  
  if (!$xff) {
    return false;
  }
  
  if ($xff === $_SERVER['REMOTE_ADDR']) {
    return false;
  }
  
  // For Cloudflare
  if (strpos($xff, ',') !== false) {
    $xff = explode(',', $xff);
    return end($xff);
  }
  else {
    return $xff;
  }
}

function validate_otp() {
  if (!isset($_POST['otp']) || $_POST['otp'] == '') {
    error("Incorrect or expired OTP.");
  }
  
  $otp = $_POST['otp'];
  
  $query = "SELECT auth_secret FROM mod_users WHERE username = '%s' LIMIT 1";
  
  $res = mysql_global_call($query, $_COOKIE['4chan_auser']);
  
  if (!$res) {
    error("Database error.");
  }
  
  $user = mysql_fetch_assoc($res);
  
  if (!$user || !$user['auth_secret']) {
    error("Incorrect or expired OTP.");
  }
  
  require_once 'lib/GoogleAuthenticator.php';
  
  $ga = new PHPGangsta_GoogleAuthenticator();
  
  $dec_secret = auth_decrypt($user['auth_secret']);
  
  if ($dec_secret === false) {
    error('Internal Server Error.');
  }
  
  if (!$ga->verifyCode($dec_secret, $otp, 1)) {
    error("Incorrect or expired OTP.");
  }
}

function validate_csrf() {
  if ($_SERVER['REQUEST_METHOD'] != 'POST') {
    error('Bad Request.');
  }
  
  if (!isset($_COOKIE['_tkn']) || !isset($_POST['_tkn'])
    || $_COOKIE['_tkn'] == '' || $_POST['_tkn'] == ''
    || $_COOKIE['_tkn'] !== $_POST['_tkn']) {
    
    if (!is_local()) {
      error('Bad Request.');
    }
  }
}

function validate_referer($strict = false) {
  if (!$strict && (!isset($_SERVER['HTTP_REFERER']) || $_SERVER['HTTP_REFERER'] == '')) {
    return;
  }
  
  if (!preg_match('/^https?:\/\/([_a-z0-9]+)\.(4chan|4channel)\.org(\/|$)/', $_SERVER['HTTP_REFERER'])) {
    error('Bad Request.');
  }
}

function dev_make_remote_thumbnail() {
  if (!is_local()) {
    die('403');
  }
  
  $infile = $_FILES['file']['tmp_name'];
  $file_ext = $_POST['file_ext'];
  $src_width = $_POST['src_width'];
  $src_height = $_POST['src_height'];
  $th_width = $_POST['th_width'];
  $th_height = $_POST['th_height'];
  
  if (!$infile || !$file_ext || !$src_width || !$src_height || !$th_width || !$th_height) {
    return;
  }
  
  $jpeg_quality = 65;
  
  switch ($file_ext) {
    case 'gif':
      $img_in = ImageCreateFromGIF($infile);
      break;
    case 'jpg':
      $img_in = ImageCreateFromJPEG($infile);
      break;
    case 'png':
      $img_in = ImageCreateFromPNG($infile);
      break;
    default :
      return;
  }
  
  if (!$img_in) {
    return;
  }
  
  $img_out = ImageCreateTrueColor($th_width, $th_height);
  
  if (!$img_out) {
    return;
  }
  
  ImageCopyResampled($img_out, $img_in, 0, 0, 0, 0, $th_width, $th_height, $src_width, $src_height);
  
  ImageDestroy($img_in);
  
  ImageJPEG($img_out, NULL, $jpeg_quality);
  
  ImageDestroy($img_out);
}

/*-----------Main-------------*/
switch( $mode ) {
  case 'make_remote_thumbnail':
    dev_make_remote_thumbnail();
    die();
  case 'purgejsontails':
    if (has_flag('developer')) {
      echo purge_json_tails() ? 'OK' : 'ERROR';
    }
    die();
  case 'listarchive':
    if (has_flag('developer')) {
      rebuild_archive_list(true);
    }
    die();
  case 'search':
    if (has_flag('developer')) {
      rebuild_search_page(true);
    }
    die();
  case 'rebuildsearchpage':
    if (has_flag('developer') || has_level('manager')) {
      rebuild_search_page();
      echo 'done';
    }
    die();
  case 'rebuildsyncframepage':
    if (has_flag('developer') || has_level('manager')) {
      rebuild_syncframe_page(isset($_GET['print']));
      echo 'done';
    }
    die();
  case 'rebuildarchivedthread':
    if (has_flag('developer') || has_level('manager')) {
      rebuild_archived_thread((int)$_GET['id']);
      echo 'done';
    }
    die();
  
	case 'regist':
	case 'post':
		require_request_method( "POST" );
		validate_referer();
		new_post( $name, $email, $sub, $com, '', $pwd, $upfile, $upfile_name, $resto, $age, $filetag );
		break;
	case 'report':
		report();
		break;
	
	case 'preview_html':
	  preview_html();
	  break;
	
	case 'rebuild':
		require_request_method( "GET" );
		rebuild();
		break;
	case 'rebuildall':
		rebuild( 1 );
		break;

	case 'rebuildadmin':
		rebuild_deletions( array($no => '1') );
		echo '<span style="display: none;">Rebuilt OK!</span>'; // shut rpc up
		break;

	case 'rebuildcatalog':
	  if (ENABLE_CATALOG) {
      rebuild_catalog();
	  }
		break;

	case 'rebuildboardsjson':
    if (has_flag('developer')) {
		  rebuild_boards_json();
	  }
		break;

	case 'rebuildthumb':
		rebuildallthumb(isset($_GET['archiveonly']) || isset($_ENV['archiveonly']));
		break;

	case 'cataloginfo':
		get_catalog_info();
		break;

	case 'admindel': case 'admindelete':
		user_delete( $no, $pwd );
		echo "<meta http-equiv=\"refresh\" content=\"0;URL=admin.php\">";
		break;
	case 'updatelog':
		updatelog_remote( $no, $noidx );
		break;
	case 'nothing':
		break;
  case 'arcdel':
    require_request_method( "POST" );
		validate_referer();
    arcdel($no, true, $res);
    break;
	case 'usrdel': case 'delete':
		require_request_method( "POST" );
		validate_referer();
		user_delete( $no, $pwd, true, $res );
		break;
	case 'rebuild_threads_by_id':
    require_request_method( "POST" );
    if (is_local()) {
      rebuild_threads_by_id();
    }
    else {
      updating_index();
    }
	  break;
	case 'forcearchive':
		require_request_method( "POST" );
		validate_referer(); //validate_csrf();
	  forcearchive();
	  break;
	case 'copythreads':
	require_request_method( "GET" );
	do_copy_threads();
	break;
	case 'movethread':
		validate_csrf();
	  do_move_thread();
	  break;
	case 'latest':
	  if (has_level('janitor')) {
	    get_last_post_no();
	  }
	  die();
  case 'rake_post':
  	//april_rake_commit();
    die('0\nNo');
    break;
	default:
		require_request_method( "GET" );
		if( JANITOR_BOARD == 1 && !has_level( 'janitor' ) ) {
			die( '' );
		}
		if( $res ) {
			resredir( $res );
		} else {
			updating_index();
		}
}
