<?
include_once "yotsuba_config.php";
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
if( TEST_BOARD ) {
  ini_set('display_errors', '1');
	if( isset( $_REQUEST[ "profile" ] ) ) {
		xhprof_enable();
		register_shutdown_function( "xhprof_save" );
	}
}

include_once 'lib/rpc.php';
include_once 'lib/admin-test.php';
include_once 'lib/auth.php';
include_once 'lib/json.php';
include_once 'lib/geoip2.php';

//include "strings_e.php";		//String resource file
define( SQLLOGBAN, 'banned_users' ); //Table (NOT DATABASE) used for holding banned users
define( SQLLOGMOD, 'mod_users' ); //Table (NOT DATABASE) used for holding mod users
define( SQLLOGDEL, 'del_log' ); //Table (NOT DATABASE) used for holding deletion log

extract( $_POST, EXTR_SKIP );
extract( $_GET, EXTR_SKIP );
extract( $_COOKIE, EXTR_SKIP );

//if( isset( $_REQUEST['id'] ) ) $id = $_REQUEST['id']; // weird bug?

if( isset( $_POST[ 'id' ] ) && ctype_digit( $_POST[ 'id' ] ) ) $id = $_POST[ 'id' ];
if( isset( $_GET[ 'id' ] ) && ctype_digit( $_GET[ 'id' ] ) ) $id = $_GET[ 'id' ];

if( $argv[1] ) $admin = $argv[1];

// FIXME whitelist
unset( $dest );
unset( $log );
unset( $update_avg_secs );

$access_allow = '';
$access_deny  = '';

mysql_board_connect( BOARD_DIR );

function janitor_votes_left()
{
	$user = $_COOKIE[ '4chan_auser' ];

	$high = mysql_global_do( "SELECT count(id) FROM janitor_votes WHERE moderator = '%s'", $user );
	$high = mysql_result( $high, 0, 0 );

	$howmany = mysql_global_do( "SELECT COUNT(id) FROM janitor_apps WHERE closed=0 AND age>17", $high );

	return mysql_result( $howmany, 0, 0 ) - $high;
}

function append_ban( $board, $ip )
{
	// run in background
	$cmd = "nohup /usr/local/bin/suid_run_global bin/appendban $board $ip >/dev/null 2>&1 &";
	print "User banned from /$board/";
//	print $cmd . "<br>"; //disabling this because it's ugly and leaks filepaths
	exec( $cmd );
}

function https_self_url()
{
	return "/".BOARD_DIR."/admin";
}

// for lib/admin.php
function delete_uploaded_files()
{

}

function make_post_json($row)
{
	$nrow = array();

	foreach( $row as $key => $val ) {
		if( ctype_digit( $val ) || is_int( $val ) ) {
			$val = (int)$val;
		}

		$nrow[ $key ] = $val;
	}
	
	return json_encode( $nrow );
}

function get_board_list() {
  //mysql_global_call("SET character_set_results = 'utf8'");
  
  $query = "SELECT dir, name FROM boardlist ORDER BY dir ASC";
  
  $res = mysql_global_call($query);
  
  if (!$res) {
    return array();
  }
  
  $boards = array();
  
  while ($dir = mysql_fetch_row($res)) {
    $boards[$dir[0]] = $dir[1];
  }
  
  return $boards;
}

function is_board_valid($board, $allow_hidden = false) {
  if (!$allow_hidden && ($board === 'test' || $board === 'j')) {
    return false;
  }
  
  $query = "SELECT dir FROM boardlist WHERE dir = '%s' LIMIT 1";
  $res = mysql_global_call($query, $board);
  
  if (!$res) {
    return false;
  }
  
  if (mysql_num_rows($res) === 1) {
    return true;
  }
  
  return false;
}

function admin_clear_reports($board, $post_id) {
  $query = "UPDATE reports SET cleared = 1 WHERE board = '%s' AND no = %d";
  
  mysql_global_call($query, $board, $post_id);
  
  $query = <<<SQL
UPDATE reports_for_posts
SET cleared = 1, clearedby = 'Auto-clear'
WHERE board = '%s' AND postid = %d
SQL;
  
  mysql_global_call($query, $board, $post_id);
}

function get_bans_summary($value, $by_pass = false) {
	if (!$value) {
		return array();
	}
	
  if ($by_pass === false) {
    $col = 'host';
    $col_active = '';
  }
  else {
    $col = '4pass_id';
    $col_active = '(active = 0 OR active = 1) AND '; // pass column doesn't have an index for itself
  }
  
  $query = <<<SQL
SELECT UNIX_TIMESTAMP(`now`) as created_on,
UNIX_TIMESTAMP(`length`) as expires_on,
UNIX_TIMESTAMP(`unbannedon`) as unbanned_on
FROM banned_users
WHERE $col_active$col = '%s'
SQL;
  
  $res = mysql_global_call($query, $value);
  
  if (!$res) {
    return array();
  }
  
  $limit = $_SERVER['REQUEST_TIME'] - 31536000; // 1 year
  
  $total_count = mysql_num_rows($res);
  $recent_perma_count = 0;
  $recent_ban_count = 0;
  $recent_warn_count = 0;
  $recent_duration = 0; // in days
  
  while ($ban = mysql_fetch_assoc($res)) {
    if ($ban['created_on'] < $limit) {
      continue;
    }
    
    if (!$ban['expires_on']) {
      ++$recent_perma_count;
      continue;
    }
    
    $ban_len = $ban['expires_on'] - $ban['created_on'];
    
    if ($ban_len <= 10) {
      ++$recent_warn_count;
    }
    else {
      if ($ban['unbanned_on']) {
        $spent_len = $ban['unbanned_on'] - $ban['created_on'];
        
        if ($spent_len > $ban_len) {
          $spent_len = $ban_len;
        }
      }
      else {
        $spent_len = $ban_len;
      }
      
      $recent_duration += $spent_len;
      ++$recent_ban_count;
    }
  }
  
  if ($recent_duration) {
    $recent_duration = round($recent_duration / 86400.0);
  }
  
  return array(
    'total' => $total_count,
    'recent_bans' => $recent_ban_count,
    'recent_warns' => $recent_warn_count,
    'recent_days' => $recent_duration,
    'recent_permas' => $recent_perma_count
  );
}

// Counts recently made threads by IP
function admin_get_thread_history($ip) {
	$long_ip = ip2long($ip);
	
	if (!$long_ip) {
		return false;
	}
	
	$sql = "SELECT COUNT(*) FROM user_actions WHERE action = 'new_thread' AND ip = $long_ip AND time >= DATE_SUB(NOW(), INTERVAL 60 MINUTE)";
	
	$res = mysql_global_call($sql);
	
	if (!$res) {
		return false;
	}
	
	return (int)mysql_fetch_row($res)[0];
}

function admin_hash_4chan_pass($pass) {
  $salt = file_get_contents(SALTFILE);
  
  if (!$salt || !$pass) {
    return '';
  }
  
  return sha1($pass . $salt);
}

function get_ban_history_html($ban_summary, $host = false) {
  $ban_tip = array();
  
  if ($ban_summary['recent_bans'] > 0) {
    $ban_tip[] = $ban_summary['recent_bans'] . ' ban' . ($ban_summary['recent_bans'] > 1 ? 's' : '');
  }
  
  if ($ban_summary['recent_warns'] > 0) {
    $ban_tip[] = $ban_summary['recent_warns'] . ' warning' . ($ban_summary['recent_warns'] > 1 ? 's' : '');
  }
  
  if ($ban_summary['recent_days'] > 0) {
    $ban_tip[] = $ban_summary['recent_days'] . ' day'
      . ($ban_summary['recent_days'] > 1 ? 's' : '')
      . ' spent banned';
  }
  
  if ($ban_summary['recent_permas'] > 0) {
    $ban_tip[] = $ban_summary['recent_permas'] . ' permaban' . ($ban_summary['recent_permas'] > 1 ? 's' : '');
  }
  
  $ban_tip = "<strong>Past 12 months history</strong><ul class=\"ban-tip-cnt\"><li>" . implode('</li><li>', $ban_tip) . '</li></ul>';
  
  if ($host !== false) {
    return "<div id=\"ban-tip-ip\" style=\"display:none\">$ban_tip</div><small>[ <a data-tip data-tip-type=\"ip\" data-tip-cb=\"showBanTip\" href=\"https://team.4chan.org/bans?action=search&amp;ip=$host\" target=\"_blank\">{$ban_summary['total']} ban" .
      (($ban_summary['total'] > 1) ? 's' : '') . " for this IP</a> ]</small>";
  }
  else {
    return "<div id=\"ban-tip-pass\" style=\"display:none\">$ban_tip</div><small>[ <a data-tip data-tip-type=\"pass\" data-tip-cb=\"showBanTip\" href=\"https://team.4chan.org/bans?action=search&amp;pass_ref=%2F" . BOARD_DIR . "%2F" . (int)$_GET['id'] . "\" target=\"_blank\">{$ban_summary['total']} ban" .
      (($ban_summary['total'] > 1) ? 's' : '') . " for this Pass</a> ]</small>";
  }
}

function ban_post( $no, $globalban, $length, $reason, $is_threadban = 0 )
{
	$query = mysql_board_call( "SELECT HIGH_PRIORITY * FROM `" . SQLLOG . "` WHERE no=" . intval( $no ) ); //FIXME use assoc
	$row   = mysql_fetch_assoc( $query );
	if( !$row ) return "";
	extract( $row, EXTR_OVERWRITE );

	//list( $no, $sticky, $permasage, $closed, $now, $name, $email, $sub, $com, $host, $pwd, $filename, $ext, $w, $h, $tn_w, $tn_h, $tim, $time, $md5, $fsize, $root, $resto ) = $row;
	$name = str_replace( '</span> <span class="postertrip">!', ' #', $name );
	$name = preg_replace( '/<[^>]+>/', '', $name ); // remove all remaining html crap

	if( $host ) $reverse = gethostbyaddr( $host );
	$displayhost = ( $reverse && $reverse != $host ) ? "$reverse ($host)" : $host;
	$xff         = '';

	//$xffresult = mysql_board_call("select host from xff where board='%s' and postno=%d", BOARD_DIR, $no);
	//$xffresult = mysql_global_call( "SELECT xff from xff where board='%s' AND postno='%d'", BOARD_DIR, $no );
	//if( $xffrow = mysql_fetch_row( $xffresult ) ) {
	//	$xff = $xffrow[ 0 ];
		//	$xff_reverse = gethostbyaddr($xffrow[0]);
		//	$xff = ($xff_reverse && $xff_reverse!=$xffrow[0])?"$xff_reverse ($xff)":$xff;
	//}
	$board = BOARD_DIR;
	$zonly = 0;

	$bannedby = $_COOKIE[ '4chan_auser' ];
	$pass_id  = $row[ '4pass_id' ];
	$post_json = make_post_json($row);

	$result = mysql_global_do(
		"INSERT INTO " . SQLLOGBAN . "
	(board,global,zonly,name,host,reverse,xff,reason,length,admin,md5,4pass_id,post_num,post_time,post_json,admin_ip) 
	 VALUES 
	( '%s', %d, %d, '%s', '%s', '%s', '%s', '%s', %d, '%s', '%s', '%s', %d, FROM_UNIXTIME(%d), '%s', '%s')",
		$board, $globalban, $zonly, $name, $host, $reverse, $xff, $reason, $length, $bannedby, $md5, $pass_id, $no, $time, $post_json, $_SERVER['REMOTE_ADDR'] );

	//('" . $board . "','" . $globalban . "','" . $zonly . "','" . mysql_escape_string( $name ) . "','" . $host . "','" . mysql_escape_string( $reverse ) . "','" . mysql_escape_string( $xff ) . "','" . mysql_escape_string( $reason ) . "','$length','" . mysql_escape_string( $bannedby ) . "','$md5','$pass_id',$no,FROM_UNIXTIME('$time'), '%s')", $post_json ) ) {
	if( !$result ) {
		echo S_SQLFAIL;
	}

	/*if( $ext != '' ) {
		$salt = file_get_contents( SALTFILE );
		$hash = sha1( BOARD_DIR . $no . $salt );
		@copy( THUMB_DIR . "{$tim}s.jpg", BANTHUMB_DIR . "{$hash}s.jpg" );
	}*/
  /*
	$afsize = (int)( $fsize > 0 );
	validate_admin_cookies();
	if( $is_threadban ) mysql_global_do( "INSERT INTO " . SQLLOGDEL . " (imgonly,postno,resto,board,name,sub,com,img,filename,admin,admin_ip) values('0',%d,%d,'%s','%s','%s','%s','%d','%s','%s','%s')", $no, $resto, SQLLOG, $name, $sub, $com, $afsize, $filename.$ext, $bannedby, $_SERVER['REMOTE_ADDR'] ); // FIXME do all this in one insert outside the write lock
  */
	echo "$displayhost banned.<br>\n";

	return $host;
}

function cpban($no) {
  $no = (int)$no;
  
  if (!$no) {
    die('Invalid thread number.');
  }
  
  $op_reason = htmlspecialchars($_POST['op_reason']);
  $rep_reason = htmlspecialchars($_POST['rep_reason']);
  
  if (!$op_reason || !$rep_reason) {
    die('Ban reason cannot be empty.');
  }
  
  $op_days = (int)$_POST['op_days'];
  $rep_days = (int)$_POST['rep_days'];
  
  if ($op_days < 0 || $rep_days < 0 || $op_days > 9999 || $rep_days > 9999) {
    die('Invalid ban length.');
  }
  
  $op_ban_end = date('YmdHis', time() + $op_days * (24 * 60 * 60));
  $rep_ban_end = date('YmdHis', time() + $rep_days * (24 * 60 * 60));
  
  $op_host = ban_post($no, 1, $op_ban_end, "$op_reason<>Thread Ban No.$no", 1);
  
  if (!$op_host) {
    die("Thread $no doesn't exist.");
  }

  $query = mysql_board_call("SELECT no, host FROM `" . SQLLOG . "` WHERE resto = $no AND host != '$op_host' GROUP BY host");
  
  while ($row = mysql_fetch_assoc($query)) {
    ban_post($row['no'], 1, $rep_ban_end, "$rep_reason<>Thread Ban No.$no", 1);
  }
  
  delete_post($no, false, null, 'threadban');
  
  echo 'Done.<script language="JavaScript">setTimeout("self.close()", 3000); postBack("done-ban-' . SQLLOG . '-' . $no . '");</script><br>';
}

function delete_post($no, $imgonly, $template_id = null, $tool = null) {
	$url       = "/".BOARD_DIR."/post";

	$post = array(
		'mode' => 'usrdel',
		'onlyimgdel' => $imgonly ? 'on' : '',
		$no => 'delete',
		'remote_addr' => $_SERVER['REMOTE_ADDR']
	);
	
	if ($template_id) {
		$post['template_id'] = $template_id;
	}
	
	if ($tool) {
	  $post['tool'] = $tool;
	}
	
	rpc_start_request("https://sys.int$url", $post, $_COOKIE, true);
	
	// don't bother waiting to check for errors

	return true;
}

function archive_thread($thread_id) {
  $url       = "/".BOARD_DIR."/post";

  $post = array(
    'mode' => 'forcearchive',
    'id' => $thread_id
  );
  
  rpc_start_request("https://sys.int$url", $post, $_COOKIE, true);
  
  // don't bother waiting to check for errors

  return true;
}

function move_thread($thread_id, $board) {
	$url       = "/".BOARD_DIR."/post";

	$post = array(
		'mode' => 'movethread',
		'id' => $thread_id,
		'board' => $board
	);
	
	rpc_start_request("https://sys.int$url", $post, $_COOKIE, true);
	
	// don't bother waiting to check for errors

	return true;
}

function rebuild_thread($no, &$error = '', $is_archived = false) {
  $url = '/' . BOARD_DIR . '/post';
  
  if (!$is_archived) {
    $post = array(
      'mode' => 'rebuildadmin',
      'no'   => $no
    );
  }
  else {
    $post = array();
    $post['mode'] = 'rebuild_threads_by_id';
    $post['ids'] = array($no);
    $post = http_build_query($post);
  }
  
  rpc_start_request("https://sys.int$url", $post, $_COOKIE, true);
  
  return true;
}

function rebuild_all(&$error = '') {
  $url = '/' . BOARD_DIR . '/post';
  
  $post = array(
    'mode' => 'rebuildall'
  );
  
  rpc_start_request("https://sys.int$url", $post, $_COOKIE, true);
  
  return true;
}

function dir_contents( $dir )
{
	$d = opendir( $dir );
	$a = array();
	if( !$d ) return $a;

	while( ( $f = readdir( $d ) ) !== false ) {
		if( $f == "." || $f == ".."  || $f == "" ) continue;
		$a[ ] = $f;
	}

	closedir( $d );

	return $a;
}

function clean()
{
	// Survive oversized boards.
	set_time_limit(0);
	ini_set("memory_limit", "-1");
	
	$images     = array();
	$respages   = array();
	$indexpages = array();

	if( PAGE_MAX > 0 ) {
		print "<strong>Running cleanup...</strong><br>Pruning orphaned posts...<br>";
		$result = mysql_board_call( "select no from `%s` where resto>0 and resto not in (select no from `%s` where resto=0)", SQLLOG, SQLLOG );
		$nos    = mysql_column_array( $result );
		if( count( $nos ) ) {
			mysql_board_call( "delete from `" . SQLLOG . "` where no in (%s)", implode( $nos, "," ) );
			foreach( $nos as $no ) {
				print "$no pruned<br>";
			}
		}
	}

	//clearstatcache();

	// get list of images that should exist
	if (MOBILE_IMG_RESIZE) {
	  $cols = ',m_img'; // FIXME, only because not all boards have that column
	}
	$result = mysql_board_call( "select tim,filename,ext$cols from `" . SQLLOG . "` where ext != ''" );
	while( $row = mysql_fetch_array( $result ) ) {
		if( $row[ 'ext' ] == '.swf' ) {
			$images[ "{$row[ 'filename' ]}{$row[ 'ext' ]}" ] = 1;
		}
		else {
			$images[ "{$row[ 'tim' ]}{$row[ 'ext' ]}" ] = 1; // picture
			$images[ "{$row[ 'tim' ]}s.jpg" ]           = 1; // thumb
			
			if (ENABLE_OEKAKI_REPLAYS) {
				$images["{$row['tim']}.tgkr"] = 1; // oe animation
			}
			
			if (MOBILE_IMG_RESIZE) {
			  $images["{$row['tim']}m.jpg"] = 1; // resized
			}
		}
	}
  
	// get list of res pages that should exist
	$result = mysql_board_call( "select no from `" . SQLLOG . "` where resto=0" );
	while( $row = mysql_fetch_array( $result ) ) {
		if( USE_GZIP == 1 ) {
			$respages[ "{$row[ 'no' ]}.html.gz" ] = 1;
			
      if (ENABLE_JSON) {
        $respages[$row['no'] . '.json.gz'] = 1;
        
        if (JSON_TAIL_SIZE) {
          $respages[$row['no'] . '-tail.json.gz'] = 1;
        }
      }
		}
		else {
      $respages[ "{$row[ 'no' ]}.html" ] = 1;
      
      if (ENABLE_JSON) {
        $respages[$row['no'] . '.json'] = 1;
        
        if (JSON_TAIL_SIZE) {
          $respages[$row['no'] . '-tail.json'] = 1;
        }
      }
		}
    
		if( JANITOR_BOARD ) $respages[ $row[ 'no' ] . '.html.php' ] = 1;
	}
	
	print "Cleaning src dir...<br>";
	foreach( dir_contents( IMG_DIR ) as $filename ) {
		if( $images[ $filename ] != 1 && !preg_match('/dmca_/', $filename) && $filename !== 'src') {
			print "Deleted $filename<br>";
      //if (file_exists(IMG_DIR . "$filename")) {
        unlink(IMG_DIR . "$filename") or print "Couldn't delete!<br>";
      //}
		}
	}
  
	print "Cleaning thumb dir...<br>";
	foreach( dir_contents( THUMB_DIR ) as $filename ) {
		if( $images[ $filename ] != 1 && !preg_match('/dmca_/', $filename)) {
			print "Deleted $filename<br>";
      //if (file_exists(THUMB_DIR . "$filename")) {
        unlink(THUMB_DIR . "$filename") or print "Couldn't delete!<br>";
      //}
		}
	}

	print "Cleaning res dir...<br>";
	foreach( dir_contents( RES_DIR ) as $filename ) {
		if( $respages[ $filename ] != 1 ) {
			print "Deleted $filename<br>";
			unlink( RES_DIR . "$filename" ) or print "Couldn't delete!<br>";
		}
	}
	
	print "Cleaning index pages...<br>";
	$result   = mysql_board_call( "SELECT COUNT(*) from `" . SQLLOG . "` WHERE archived = 0 AND resto = 0" );
	$lastpage = PAGE_MAX + 1;//(mysql_result( $result, 0, 0 ) / DEF_PAGES) + 1;
	if( USE_GZIP == 1 ) {
		$indexpages[ SELF_PATH2_FILE . '.gz' ] = 1;
		
    if (USE_RSS) {
      $indexpages[INDEX_DIR . 'index.rss.gz'] = 1;
    }
    if (ENABLE_CATALOG) {
      $indexpages[INDEX_DIR . 'catalog.html.gz'] = 1;
    }
    if (ENABLE_JSON_CATALOG) {
      $indexpages[INDEX_DIR . 'catalog.json.gz'] = 1;
    }
    if (ENABLE_JSON_THREADS) {
      $indexpages[INDEX_DIR . 'threads.json.gz'] = 1;
      $indexpages[INDEX_DIR . 'archive.json.gz'] = 1;
    }
    if (ENABLE_ARCHIVE) {
      $indexpages[INDEX_DIR . 'archive.html.gz'] = 1;
    }
	}
	
  $indexpages[ SELF_PATH2_FILE ] = 1;
  
  if (USE_RSS) {
    $indexpages[INDEX_DIR . 'index.rss'] = 1;
  }
  if (ENABLE_CATALOG) {
    $indexpages[INDEX_DIR . 'catalog.html'] = 1;
  }
  if (ENABLE_JSON_CATALOG) {
    $indexpages[INDEX_DIR . 'catalog.json'] = 1;
  }
  if (ENABLE_JSON_THREADS) {
    $indexpages[INDEX_DIR . 'threads.json'] = 1;
    $indexpages[INDEX_DIR . 'archive.json'] = 1;
  }
  if (ENABLE_ARCHIVE) {
    $indexpages[INDEX_DIR . 'archive.html'] = 1;
  }
	
	for( $page = 1; $page < $lastpage; $page++ ) {
		if( USE_GZIP == 1 ) {
			$indexpages[ INDEX_DIR . $page . PHP_EXT . '.gz' ] = 1;
      if (ENABLE_JSON_INDEXES) {
        $indexpages[INDEX_DIR . $page . '.json.gz'] = 1;
      }
		}
    $indexpages[ INDEX_DIR . $page . PHP_EXT ] = 1;
    if (ENABLE_JSON_INDEXES) {
      $indexpages[INDEX_DIR . $page . '.json'] = 1;
    }
	}
	
	foreach( glob( INDEX_DIR . '*.{html,gz}', GLOB_BRACE ) as $filename ) {
		$bfilename = basename( $filename );
		if( $indexpages[ $filename ] != 1 ) {
			print "Deleted $bfilename<br>";
			unlink( $filename ) or print "Couldn't delete!<br>";
		}
	}

	print "Cleaning tmp uploads...<br>";
	$phptmp = ini_get( "upload_tmp_dir" );
	exec( "find $phptmp/ -mtime +2h -name php*", $tmpfiles );
	exec( "find -E " . INDEX_DIR . " -regex '.*/(gz)?tmp.*$' -mtime +2h", $indextmp );
	exec( "find -E " . RES_DIR . " -regex '.*/(gz)?tmp.*$' -mtime +2h", $restmp );

	$tmpfiles = array_merge( $tmpfiles, $indextmp );
	$tmpfiles = array_merge( $tmpfiles, $restmp );

	foreach( $tmpfiles as $filename ) {
		$safename = explode( '/' . BOARD_DIR . '/', $filename );
		$safename = end( $safename );

		print "Deleted $safename<br>";
		unlink( $filename ) or print "Couldn't delete!<br>";
	}
	/*print "Cleaning /var/tmp<br>";
	exec("find /var/tmp/ -mtime +2h -type f", $tmpfiles);
	foreach($tmpfiles as $filename) {
		print "Delete $filename<br>"; unlink($filename) or print "Couldn't delete!<br>";
	}*/
	print "Cleaning up side tables...<br>";

	mysql_global_call( "DELETE FROM user_actions WHERE time < DATE_SUB(NOW(), INTERVAL 7 DAY)" );
  mysql_global_call( "DELETE FROM event_log WHERE created_on < DATE_SUB(NOW(), INTERVAL 7 DAY)" );
	mysql_global_call( "DELETE FROM xff WHERE tim < (unix_timestamp(DATE_SUB(NOW(), INTERVAL 7 DAY))*1000)" );
	mysql_board_call( "DELETE FROM f_md5 WHERE now < DATE_SUB(NOW(), INTERVAL 2 DAY)" );
  mysql_board_call("DELETE FROM r9k_posts WHERE created_on < DATE_SUB(NOW(), INTERVAL 2 YEAR)");

	print "<strong>Cleanup complete!</strong>";
}

// Changes relative board urls to absolute //sys.4chan.org admin urls
function fix_board_nav($nav) {
  return preg_replace('/href="\/([a-z0-9]+)\/"/', "href=\"//sys." . L::d(BOARD_DIR) . "/$1/admin\"", $nav);
}

/* head */
function head( &$dat, $is_logged_in = false )
{
	global $admin, $access_allow, $access_deny;
	
	$allowed_modes = array('ban', 'delall', 'unban', 'opt', 'banreq', 'editop');
	
	if( !is_user() || ( is_user() && ( $admin != "ban" ) && ( $admin != "delall" ) && ( $admin != "unban" ) && ( $admin != "opt" ) && ( $admin != 'banreq' ) && ( $admin != 'editop' ) ) ) {
		$navinc = fix_board_nav(file_get_contents( NAV_TXT )) . '<br>';
		$navinc = str_replace( '[<a href="javascript:void(0);" id="settingsWindowLink">Settings</a>] ', '', $navinc );
	}
  
  if (DEFAULT_BURICHAN) {
    $style_cookie = 'ws_style';
    $ws = 'ws';
  }
  else {
    $style_cookie = 'nws_style';
    $ws = '';
  }
  
  $preferred_style = $_COOKIE[$style_cookie];
  
  switch ($preferred_style) {
    case 'Yotsuba New':
      $style = 'yotsubanew';
      break;
    case 'Yotsuba B New':
      $style = 'yotsubluenew';
      break;
    //case 'Futaba New':
    //  $style = 'futabanew';
    //  break;
    //case 'Burichan New':
    //  $style = 'burichannew';
    //  break;
    case 'Tomorrow':
      $style = 'tomorrow';
      break;
    case 'Photon':
      $style = 'photon';
      break;
    default:
      $style = DEFAULT_BURICHAN ? 'yotsubluenew' : 'yotsubanew';
      break;
  }
  
	if (!in_array($admin, $allowed_modes)) {
		$admin = '';
	}
	
	if ($admin == 'ban') {
	  $page_title = 'Ban No.' . (int)$_GET['id'] . ' on /' . BOARD_DIR . '/';
	  $no_header = true;
	}
	else if ($admin == 'banreq') {
	  $page_title = 'Ban request No.' . (int)$_GET['id'] . ' on /' . BOARD_DIR . '/';
	  $no_header = true;
	}
	else {
	  $page_title = TITLE;
	  $no_header = isset($_GET['noheader']);
	}
	
	$fb_js = <<<JS
Feedback = {
  showMessage: function(msg) {
    var el;
    
    Feedback.hideMessage();
    
    el = document.createElement('div');
    el.id = 'feedback';
    el.innerHTML = '<span class="feedback-error">' + msg + '</span>';
    
    document.body.insertBefore(el, document.body.firstElementChild);
  },
  
  hideMessage: function() {
    var el = document.getElementById('feedback');
    
    if (el) {
      document.body.removeChild(el);
    }
  },
  
  checkTemplate: function(id) {
    var tpl;
    
    Feedback.hideMessage();
    
    if (id < 0) {
      return;
    }
    
    tpl = window.templates[id];
    
    if (tpl.no == '1') {
      Feedback.showMessage('<u>Only</u> use this ban template for images depicting apparent child pornography. For links and non-pornographic images, please use the appropriate template(s).');
    }
    else if (tpl.no == '123' || tpl.no == '126') {
      Feedback.showMessage('Images depicting apparent child pornography should be banned using the "Child Pornography (Explicit Image)" template.');
    }
  }
};
JS;
  
$tooltip_js = <<<JS
var Tip = {
  node: null,
  timeout: null,
  delay: 150,
  
  init: function() {
    document.addEventListener('mouseover', this.onMouseOver, false);
    document.addEventListener('mouseout', this.onMouseOut, false);
  },
  
  onMouseOver: function(e) {
    var cb, data, t;
    
    t = e.target;
    
    if (Tip.timeout) {
      clearTimeout(Tip.timeout);
      Tip.timeout = null;
    }
    
    if (t.hasAttribute('data-tip')) {
      data = null;
      
      if (t.hasAttribute('data-tip-cb')) {
        cb = t.getAttribute('data-tip-cb');
        if (cb.indexOf('.') !== -1) {
          cb = cb.split('.');
          if (window[cb[0]] && (cb = window[cb[0]][cb[1]])) {
            data = cb(t);
          }
        }
        else if (window[cb]) {
          data = window[cb](t);
        }
        if (data === null) {
          return;
        }
      }
      Tip.timeout = setTimeout(Tip.show, Tip.delay, e.target, data);
    }
  },
  
  onMouseOut: function(e) {
    if (Tip.timeout) {
      clearTimeout(Tip.timeout);
      Tip.timeout = null;
    }
    
    Tip.hide();
  },
  
  show: function(t, data, pos) {
    var el, rect, style, left, top;
    
    rect = t.getBoundingClientRect();
    
    el = document.createElement('div');
    el.id = 'tooltip';
    
    if (data) {
      el.innerHTML = data;
    }
    else {
      el.textContent = t.getAttribute('data-tip');
    }
    
    if (!pos) {
      pos = 'top';
    }
    
    el.className = 'tip-' + pos;
    
    document.body.appendChild(el);
    
    left = rect.left - (el.offsetWidth - t.offsetWidth) / 2;
    
    if (left < 0) {
      left = rect.left + 2;
      el.className += '-right';
    }
    else if (left + el.offsetWidth > document.documentElement.clientWidth) {
      left = rect.left - el.offsetWidth + t.offsetWidth + 2;
      el.className += '-left';
    }
    
    top = rect.top - el.offsetHeight - 5;
    
    style = el.style;
    style.display = 'none';
    style.top = (top + window.pageYOffset) + 'px';
    style.left = left + window.pageXOffset + 'px';
    style.display = '';
    
    Tip.node = el;
  },
  
  hide: function() {
    if (Tip.node) {
      document.body.removeChild(Tip.node);
      Tip.node = null;
    }
  }
}

Tip.init();
JS;

	$dat .= '<!DOCTYPE html><html><head>
<meta http-equiv="content-type" content="text/html; charset=UTF-8">
<meta http-equiv="pragma" content="no-cache">
<meta name="viewport" content="width=device-width,initial-scale=1">
<link rel="icon" type="image/x-icon" href="//s.4cdn.org/image/favicon-team' . $ws . '.ico">
<link rel="stylesheet" style="text/css" href="//s.4cdn.org/css/' . $style . '.387.css">
<script type="text/javascript">' . $fb_js . '</script>
<style type="text/css">
table:not([class]) {
	border: 1px solid #fff;
	border-collapse: collapse;

	margin: 0 auto;
}

#tooltip {
  color: #dedede;
  text-align: left;
}

.ico-phone {
	opacity: 0.75;
	font-size: 12px;
	cursor: default;
}

.ban-tip-cnt {
  padding: 0 0 0 10px;
  margin: 2px 0 1px 0;
}

#js-move-board-sel {
  display: none;
  width: 100px;
}

#feedback {
  top: 0px;
  text-align: center;
  z-index: 9999;
  display: block;
  background-color: #C41E3A;
  margin: 0px;
  left: 0px;
  padding: 5px;
  box-shadow: 0 0 10px rgba(0, 0, 0, 0.2);
}
.feedback-error {
  color: #fff;
  font-size: 12px;
  text-shadow: 0 1px rgba(0, 0, 0, 0.2);
}

.bantable {
	width: 98%;
	margin: 0;
}

.bantable-extra input[type="text"] {
  font-size: 11px;
  white-space: nowrap;
  width: 260px;
}

.bantable-extra.bantable-tb input[type="text"] {
  width: 244px;
}

.bantable-extra td {
  font-size: 11px;
  white-space: nowrap;
}

.postblock {
  width: 80px;
}

#ban-days { width: 45px; }

#submit-ban-btn {
  position: absolute;
  right: 10px;
  margin-top: -2px;
}

.bantable tr td:first-child {
	font-weight: bold;
	padding-right: 5px;
}

table:not([class]) td {
padding: 3px;
border: 1px solid #fff;
}

input[type="text"], textarea {
	margin: 0px;
	margin-right: 2px;
	padding: 2px 4px 3px 4px;
	border: 1px solid #AAA;
	outline: none;
	font-family: arial,helvetica,sans-serif;
	font-size: 10pt;
}

.inputcenter { text-align: center; }

table {
  border-spacing: 1px;
}

.tomorrow textarea {
  background-color: #282a2e;
  color: #c5c8c6;
}

.tomorrow input[type="text"]:not(:focus),
.tomorrow textarea:not(:focus) {
  border-color: #000;
}

.glow-r { box-shadow: 0 0 4px 4px #e04000; }

*[disabled=disabled] {
	opacity: 0.5;
}
</style>
<script type="text/javascript">
' . ($is_logged_in ? '
/*localStorage.setItem("extra_path", "' . (has_level('mod') ? ADMIN_JS_PATH : JANITOR_JS_PATH) . '");*/
' : '') . '
function checkBrowser(){
	this.ver=navigator.appVersion
	this.dom=document.getElementById?1:0
	this.ie5=(this.ver.indexOf("MSIE 5")>-1 && this.dom)?1:0;
	this.ie4=(document.all && !this.dom)?1:0;
	this.ns5=(this.dom && parseInt(this.ver) >= 5) ?1:0;
	this.ns4=(document.layers && !this.dom)?1:0;
	this.bw=(this.ie5 || this.ie4 || this.ns4 || this.ns5)
	return this
}
bw=new checkBrowser()

function popup(vars) {
//490 - 220
	var pheight = /admin=opt/.test(vars) ? 220 : 490;
  day = new Date();
	id = day.getTime();
	var newWindow;
	var props = \'scrollBars=no,resizable=no,toolbar=no,menubar=no,location=no,directories=no,width=400,height=\' + pheight;
	eval(\'popup\'+id+\' = window.open("admin?mode=admin&"+vars, "\'+id+\'", props);\');
}

function more(div,div2,nest){
	obj=bw.dom?document.getElementById(div).style:bw.ie4?document.all[div].style:bw.ns4?nest?document[nest].document[div]:document[div]:0;
	obj2=bw.dom?document.getElementById(div2).style:bw.ie4?document.all[div2].style:bw.ns4?nest?document[nest].document[div2]:document[div2]:0;
	if(obj.display==\'\') {
		obj.display=\'none\';
		obj2.display=\'none\';
	} else {
		obj.display=\'\';
		obj2.display=\'\';
	}
}

function swap(div,div2) {
	var el = document.getElementById(div);
	var el2 = document.getElementById(div2);
	var tmp = el.style.display;
	el.style.display = el2.style.display;
	el2.style.display = tmp;
}

function postBack(msg) {
	if (self !== top) {
		window.parent.postMessage(msg, "*");
	}
}

document.addEventListener("DOMContentLoaded", onDOMReady, false);

function onDOMReady() {
  var el;
  
  document.removeEventListener("DOMContentLoaded", onDOMReady, false);
  
  el = document.getElementById("move-form");
  
  if (el) {
    el.addEventListener("submit", onMoveThread, false);
  };
  
  el = document.getElementById("autocomplete");
  
  if (el && !/Android|iPhone|iPad/.test(navigator.userAgent)) {
    el.focus();
  };
  
  el = document.getElementById("js-postban-sel");
  
  if (el) {
    el.addEventListener("change", onPostBanSelChange, false);
  };
  
  el = document.getElementById("js-sticky-cb");
  
  if (el) {
    el.addEventListener("change", onStickyChange, false);
  };
  
  adjustMobileMargin();
};

function adjustMobileMargin() {
  if (!window.matchMedia("(max-device-width: 480px)").matches) {
    return;
  }
  
  let oh = document.documentElement.clientHeight;
  let ih = document.body.clientHeight;
  
  if (oh - ih > 50) {
    document.body.style.paddingTop = (oh - ih - 50) + "px";
  }
}

function onStickyChange(e) {
  var el = document.getElementById("js-undead-cb");
  
  if (!this.checked) {
    el.checked = false;
  }
  
  if (this.dataset.cur === "0") {
    el = document.getElementById("js-set-btn");
    
    if (this.checked) {
      el.classList.add("glow-r");
    }
    else {
      el.classList.remove("glow-r");
    }
  }
}

function onMoveThread(e) {
  if (!checkSubmitConfirm(document.getElementById("js-move-btn"))) {
    e.preventDefault();
    return;
  }
}

function checkSubmitConfirm(el) {
	if (el.hasAttribute("data-js-confirming")) {
		clearSubmitConfirm(el);
		return true;
	}
	
	el.setAttribute("data-js-confirming", "1");
	
	if (el.tagName === "BUTTON") {
		el.setAttribute("data-js-label", el.textContent);
		el.textContent = "Confirm?";
	}
	else {
		el.setAttribute("data-js-label", el.value);
		el.value = "Confirm?";
	}
	
	let timeout = setTimeout(clearSubmitConfirm, 3000, el);
	
	el.setAttribute("data-js-to", timeout);
	
	return false;
}

function clearSubmitConfirm(el) {
	let label = el.getAttribute("data-js-label");
	
	if (label === null) {
		return;
	}
	
	let timeout = +el.getAttribute("data-js-to");
	clearTimeout(timeout);
	
	el.removeAttribute("data-js-confirming");
	el.removeAttribute("data-js-to");
	el.removeAttribute("data-js-label");
	
	if (el.tagName === "BUTTON") {
		el.textContent = label;
	}
	else {
		el.value = label;
	}
}

admin = "' . $admin . '";

document.addEventListener("keydown", onKeyDown, false);

function onKeyDown(e) {
	var board, postno;
	
	board = "' . BOARD_DIR . '";
	postno = ' . ((int)$_GET['id']) . ';
	
  if (e.keyCode == 27 && !e.ctrlKey && !e.altKey && !e.shiftKey && !e.metaKey) {
    postBack("cancel-ban-" + board + "-" + postno);
  }
  
  if( admin == "opt" ) {
  	//sticky 83
  	//permasage 80
  	//closed 67
  	//permaage 69
  	//undead 85
  	//spoiler 79
  	//enter 13
  	//S, P, C, E, U, O
  	//Also give the sticky checkbox focus so I can hit tab once to change the number,
  	//and then hit enter to submit.
  
  if (document.activeElement.nodeName == "INPUT" && document.activeElement.type === "text") {
    return;
  }
  
	if( e.ctrlkey || e.altkey || e.shiftkey || e.metakey ) return;
	
	var kc = e.keyCode;
	var toggle = "";
	
	switch(kc) {
		case 83:
			toggle = "sticky";
			break;
		case 80:
			toggle = "permasage";
			break;
		case 67:
			toggle = "closed";
			break;
		case 69:
			toggle = "permaage";
			break;
		case 85:
			toggle = "undead";
			break;
		case 79:
			toggle = "spoiler";
			break;		

		case 13:
			document.getElementById("mainform").submit();
			return true;
			
		default:
			// hello
			break;
	}
	
	if(toggle) toggleCheckbox(document.querySelector("input[name=" + toggle + "]"));
	
  }
}

' . $tooltip_js . '

function toggleCheckbox(elem) {
	elem.checked = !elem.checked;
  elem.focus();
}

function showBanTip(btn) {
  return document.getElementById("ban-tip-" + btn.getAttribute("data-tip-type")).innerHTML;
}
';

if ($admin == 'ban') {
  $dat .= <<<JS
function onPostBanSelChange(e) {
  var el, opt, to, i, o;
  
  el = document.getElementById('js-move-board-sel');
  
  if (!el) {
    return;
  }
  
  opt = this.options[this.selectedIndex];
  
  if (opt.value === 'move') {
    el.style.display = 'inline';
    el.disabled = false;
  }
  else {
    el.style.display = '';
    el.disabled = true;
  }
}
JS;
}

$dat .= '</script>
<title>' . $page_title . '</title>
</head>
<body class="' . $style . '">
';
  
	if (!$no_header) {
		$dat .= '
' . str_replace( "12pt", "10pt", $navinc ) . '
<div class="boardBanner"><div class="boardTitle" style="font-size: 18pt !important;">' . TITLE . '</div></div>
<hr width="90%" size=1>';
	}
}

/* Footer */
function foot( &$dat )
{
	$dat .= '
<center>
<small>' . S_FOOT . '
</small>
</center>wtf?
' . str_replace( "12pt", "10pt", $navinc2 ) . '
</body></html>';
}

function error( $mes, $dest = '' )
{
	global $upfile_name;
  if ($dest && file_exists($dest)) {
    unlink($dest);
  }
	head( $dat );
	echo $dat;
	echo "<br><br>
        <center><font color=\"red\" size=\"5\"><b>$mes</b></font><br><br><font size=\"5\"><b>[<a href=" . SELF_PATH2_ABS . ">" . S_RELOAD . "</a>]</b></font></center>";
	die( "</body></html>" );
}

/* text plastic surgery */
function sanitize_text( $str )
{
	global $admin;
	$str = trim( $str ); //blankspace removal
	if( get_magic_quotes_gpc() ) { //magic quotes is deleted (?)
		$str = stripslashes( $str );
	}
	if( $admin != $adminpass ) { //admins can use tags
		$str = htmlspecialchars( $str ); //remove html special chars
		$str = str_replace( "&amp;", "&", $str ); //remove ampersands
	}

	return str_replace( ",", "&#44;", $str ); //remove commas
}

//check for table existance
function table_exist( $table )
{
	$result = mysql_global_call( "show tables like '$table'" );
	if( !$result ) {
		return 0;
	}
	$a = mysql_fetch_row( $result );

	return $a;
}

function is_local()
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
		return true;
	}

	return false;
}

// FIXME hack
function valid( $action = 'moderator', $no = 0 )
{
	return false;
}

/*password validation */
function adminvalid( $title = 'Manager Mode' )
{
	global $user, $pass, $access_allow, $access_deny, $admin;

	$level    = 0;
	$levelarr = array( 'janitor' => 1, 'mod' => 2, 'manager' => 3, 'admin' => 4 );

	ob_start();

	// 1 = janitor, 2 = mod, 3 = manager, 4 = admin

	if( is_local() ) {
		echo head( $dat );

		return;
	}

	$user = $_COOKIE[ '4chan_auser' ];
	$pass = $_COOKIE[ '4chan_apass' ];

	$valid = auth_user();

	if( $valid !== true ) {
	  error( 'You do not have permission to access this page.' );
  }

	//if( !$valid ) admin_login_fail();


	// Do we have permission for this board?
	if( $valid && ( $title !== 'Ban Request' && !access_board( BOARD_DIR ) ) ) {
		error( 'You do not have permission to access this board.' );

	}
  
	if ($title !== 'Ban Request' && !has_level('mod')) {
	  die();
	}
	
  if ($title === 'Board Cleanup' && !has_level('manager') && !has_flag('developer')) {
    error( 'You do not have permission to access this board.' );
  }
	
	if( $valid && has_level() && $_GET[ 'admin' ] == 'adminext' ) {
		return true;
	}


	head( $dat, $valid );
	echo $dat;

	$SELF_PATH2_ABS = SELF_PATH2_ABS;
	$S_RETURNS     = S_RETURNS;
	$SELF_PATH      = SELF_PATH;
	$S_LOGUPD      = S_LOGUPD;
	$S_LOGUPDALL   = S_LOGUPDALL;
	if (!isset($_GET['noheader']) && $title == 'Manager Mode') {

		if( $valid && has_level( 'mod' ) ) {
			echo '<div style="clear: both;">';
			echo '<span style="float:left;margin-bottom:3px;">[<a href="' . SELF_PATH2_ABS . '">' . S_RETURNS . '</a>] [<a href="' . SELF_PATH . '">' . S_LOGUPD . '</a>] [<a href="' . SELF_PATH . '?mode=rebuildall">' . S_LOGUPDALL . '</a>]';

			if( $_GET[ 'admin' ] == 'cleanup' ) {
				echo ' [<a href="admin">Admin</a>]';
			}
			else if (has_level('manager') || has_flag('developer')) {
				echo ' [<a href="admin?admin=cleanup">Cleanup</a>]';
			}

			if( $_GET[ 'admin' ] == 'ban' ) {
				echo ' [<a href="//www.' . L::d(BOARD_DIR) . '/rules#' . BOARD_DIR . '" target="_blank" title="Open the rules for this board in a new window">Rules</a>]';
			}

			echo '</span>';
		}
		elseif( $valid ) {
			//echo ' [<a href="//www.4chan.org/rules#' . BOARD_DIR . '" target="_blank" title="Open the rules for this board in a new window">Rules</a>] ';
		}

		if( ( !isset( $_GET[ 'admin' ] ) || $_GET[ 'admin' ] == 'cleanup' ) && has_level( 'mod' ) ) {
			echo "<select style=\"float:right;\" onchange=\"var x=this.options[this.selectedIndex].value;x&&(window.location=x)\">";
			$result = mysql_global_call( "select sql_cache domain,dir from boardlist order by dir" );
			while( $row = mysql_fetch_array( $result ) ) {
				$domain = 'https://sys.' . L::d(BOARD_DIR) . '/';
				if( $_GET[ 'admin' ] == 'cleanup' ) {
					$querystring = '?admin=cleanup';
				}
				else $querystring = '';
				if( $row[ 'dir' ] == SQLLOG ) {
					$selected = 'selected';
				}
				else $selected = '';
				echo "<option value=\"$domain{$row[ 'dir' ]}/admin$querystring\" $selected>{$row[ 'dir' ]}</option>\n";
			}
			echo "</select>";
		}
	}
  
	$no_header = isset($_GET['noheader']) || $_GET[ 'admin' ] == 'ban' || $_GET[ 'admin' ] == 'banreq';
	
	if( $valid && !has_level( 'mod' ) ) {
		if ($no_header) {
			return;
		}
		echo "<br><br>
        <center><font color=\"red\" size=\"5\"><b>You are logged in as a janitor.</b></font><br><br><font size=\"5\"><b>[<a href=\"//boards." . L::d(BOARD_DIR) . '/' . BOARD_DIR . "/\">Return</a>]</b></font></center>";
		die( '</body></html>' );
	}

	//if( $valid && (has_level('manager') || has_flag('developer')) ) {
		$GLOBALS[ 'b_sticky' ] = 1;
	//}

	if( !$valid ) $title = 'Manager Mode';
	if( !$valid ) echo '<div style="clear:both;">[<a href="//boards.' . L::d(BOARD_DIR) . '/' . BOARD_DIR . '" accesskey="a">' . S_RETURN . '</a>]</span></div>';
	if( !$no_header ) echo '<div class="postingMode" style="clear: both;">' . $title . '</div>';
	// Mana login form
	if( !$valid ) {
		echo "<form action=\"" . https_self_url() . "\" method=\"post\" style=\"margin-top: 5px;\">\n";

		echo "<center>";
		echo "<input type=\"hidden\" name=\"mode\" value=\"admin\">\n";
		echo "<table class=\"postForm\"><tbody>";
		// echo "<tr><td>ID(s):</td><td><input type=text name=res size=8 value=\"".$_GET['res']."\"></td></tr>";
		echo "<tr><td>Username</td><td><input type=\"text\" name=\"userlogin\" style=\"width: 120px; text-align: center;\" tabindex=\"1\"> <input type=\"submit\" value=\"" . S_MANASUB . "\" style=\"margin: 0;\" tabindex=\"3\"></td></tr>";
		echo "<tr><td>Password</td><td><input type=\"password\" name=\"passlogin\"  style=\"width: 120px; text-align: center;\" tabindex=\"2\"></td></tr>";
		echo "</tbody></table></form></center>\n";
		//echo file_get_contents( NAV2_TXT );
		echo '</body></html>';


		die();
	}
}

// FIXME
function adminreportclear() {
  if (!has_level('mod')) {
    die('404');
  }

  $pid = (int)$_GET['pid'];

  $board = $_GET['board'];
  
  if (!$pid || !$board) {
    die('404');
  }
  
  $query = "UPDATE reports SET cleared = 1, cleared_by = '%s' WHERE board = '%s' AND no = $pid";
  
  $res = mysql_global_call($query, $_COOKIE['4chan_auser'], $board);
  
  if (!$res) {
    die("DB error (2-1)");
  }
  
  $query = <<<SQL
UPDATE reports_for_posts
SET cleared = 1, clearedby = '%s'
WHERE board = '%s' AND postid = $pid
SQL;

  $res = mysql_global_call($query, $_COOKIE['4chan_auser'], $board);
  
  if (!$res) {
    die("DB error (2-2)");
  }
    
  echo "Done";
}

function adminreportqueue() {
  if (!has_level('mod')) {
    die('404');
  }

  $query = <<<SQL
SELECT *, SUM(weight) as total_weight, COUNT(reports.no) as cnt,
GROUP_CONCAT(DISTINCT report_category) as cats,
UNIX_TIMESTAMP(ts) as `time`
FROM reports
WHERE cleared = 0
GROUP BY reports.no, reports.board
ORDER BY total_weight DESC
LIMIT 200
SQL;

  $res = mysql_global_call($query);

  if (!$res) {
    die('DB error (1)');
  }

  while ($report = mysql_fetch_assoc($res)) {
    $post = json_decode($report['post_json'], true);

    echo '<div style="margin:12px;border:1px solid">';
    if ($post['fsize'] > 0) {
      echo '<img src="https://i.4cdn.org/' . $report['board'] . '/' . $post['tim'] . 's.jpg">';
    }

    $tid = $post['resto'] ? $post['resto'] : $report['no'];

    echo "<a target=\"_blank\" href=\"https://boards." . L::d($report['board']) . "/{$report['board']}/thread/$tid#p{$report['no']}\"><b>/{$report['board']}/{$report['no']} ({$report['total_weight']})</b></a> &mdash; ";
    
    echo '<a style="color:red" target="_blank" href="?admin=reportclear&amp;board=' . $report['board'] . '&amp;pid=' . $report['no'] . '">[CLEAR]</a>';

    echo "<p>{$post['com']}</p>";

    echo "</div>";
  }
}

/* Admin deletion */
// This might not be used anymore
function admin_delete()
{
	if( !has_level('mod') ) return true;

	global $admin, $onlyimgdel, $res, $thread, $ip, $user, $pass;

	if( ( $admin != "ban" ) && ( $admin != "delall" ) && ( $admin != "unban" ) ) {
		$navinc = ''; //file_get_contents( NAV_TXT );
	}
	if( !isset( $_POST[ 'p' ] ) ) {
		$p = 1;
	}
	else {
		$p = $_POST[ 'p' ];
	}
	$max_results = 30;
	$from        = ( ( $p * $max_results ) - $max_results );

	$board = explode( "/", $_SERVER[ 'SCRIPT_NAME' ] );
	$board = $board[ 1 ];

	$threadmode = $_REQUEST[ 'threadmode' ];
	if( !$threadmode ) { // threadmode uses table aliases, so don't bother locking
		if( $delflag ) mysql_board_call( "LOCK TABLES `" . SQLLOG . "` WRITE" );
	}

	$delno   = array();
	$delflag = false;
	reset( $_POST );
	while( $item = each( $_POST ) ) {
		if( $item[ 1 ] == 'delete' ) {
			array_push( $delno, intval( $item[ 0 ] ) );
			$delflag = true;
		}
	}
	if( $delflag ) {
		$resultstr = "(" . implode( ",", $delno ) . ")";
		if( $threadmode ) mysql_board_call( "LOCK TABLES `" . SQLLOG . "` WRITE" ); // can finally lock it now
		if( !$result = mysql_board_call( "select * from `" . SQLLOG . "` where no in %s or resto in %s", $resultstr, $resultstr ) ) {
			echo S_SQLFAIL;
		} //FIXME use assoc
		$find = false;
		while( $row = mysql_fetch_assoc( $result ) ) {
			//list( $no, $sticky, $permasage, $closed, $now, $name, $email, $sub, $com, $host, $pwd, $filename, $ext, $w, $h, $tn_w, $tn_h, $tim, $time, $md5, $fsize, $root, $resto ) = $row;
			extract( $row, EXTR_OVERWRITE );

			if( $onlyimgdel == 'on' ) {
				if( array_search( $no, $delno ) ) { //only a picture is deleted
					if( $board == "f" ) {
						$delfile = IMG_DIR . $filename . $ext;
					}
					else {
						$delfile = IMG_DIR . $tim . $ext; //only a picture is deleted
					}
					unlink( $delfile ); //delete
					unlink( THUMB_DIR . $tim . 's.jpg' ); //delete
				}
			}
			else {
				if( array_search( $no, $delno ) || array_search( $resto, $delno ) ) { //It is empty when deleting
					$find  = true;
					$auser = $_COOKIE[ '4chan_auser' ];
					$apass = $_COOKIE[ '4chan_apass' ];
					if( !mysql_board_call( "delete from `" . SQLLOG . "` where no=" . $no . " or resto=" . $no ) ) {
						echo S_SQLFAIL;
					} // FIXME can't this be atomic? (one statement)
					if( $board == "f" ) {
						$delfile = IMG_DIR . $filename . $ext;
					}
					else {
						$delfile = IMG_DIR . $tim . $ext;
					}
					unlink( $delfile ); //Delete
					unlink( THUMB_DIR . $tim . 's.jpg' ); //Delete
					if( $fsize > 0 ) $adfsize = 1;
					$adname = str_replace( '</span> <span class="postertrip">!', '#', $name );
					if( $onlyimgdel == "on" ) {
						$imgonly = 1;
					}
					else {
						$imgonly = 0;
					}
					validate_admin_cookies();
					mysql_global_do( "INSERT INTO " . SQLLOGDEL . " (imgonly,postno,resto,board,name,sub,com,img,filename,admin,admin_ip) values('$imgonly','$no',$resto,'" . SQLLOG . "','$adname','$sub','$com','$adfsize','$filename$ext','$auser', '" . mysql_real_escape_string($_SERVER['REMOTE_ADDR']) . "')" ); // FIXME do all this in one insert outside the write lock
				}
			}
		}
	}

	if( $delflag ) mysql_board_call( "UNLOCK TABLES" );

	// Deletion screen display
	echo "<center style=\"margin-top: 4px;\"><input type=\"hidden\" name=\"mode\" value=\"admin\">\n";
	echo "<input type=\"hidden\" name=\"admin\" value=\"del\">\n";
	echo "Go to ID(s): <input type=\"text\" name=\"res\" size=\"10\" maxlength=\"10\" class=\"inputcenter\">&nbsp;<input type=\"submit\" value=\"Go\">";
	if( $threadmode ) {
		echo "<input type=\"button\" onclick='location.search=\"\"' value=\"View by order posted\">";
	}
	else {
		echo "<input type=\"button\" onclick='location.search=\"?threadmode=1\"' value=\"Group by thread\">";
	}

	echo "</center></form>";
  
	echo "<center><p><form action=\"" . SELF_PATH . "\" method=\"POST\"><input type=\"hidden\" name=\"mode\" value=\"admindel\"><input type=\"hidden\" name=\"pwd\" value=\"" . ADMIN_PASS . "\"><input type=\"submit\" value=\"" . S_ITDELETES . "\"> ";
	echo "<input type=\"reset\" value=\"" . S_MDRESET . "\">";
	echo " [<input type=\"checkbox\" name=\"onlyimgdel\" value=\"on\"><!--checked-->" . S_MDONLYPIC . "]";

	echo "<table border=\"1\" cellspacing=\"0\">";
	if( $threadmode ) {
		echo "<tr bgcolor=\"#408040\" align=\"center\" style=\"color:#eef6ee\">";
	}
	else {
		echo "<tr bgcolor=\"#6080f6\" align=\"center\">";
	}
	echo '<td>&nbsp;</td><td><b>No.</b></td><td><b>Time</b></td><td><b>Name</b></td><td><b>Subject</b></td><td><b>Comment</b></td><td><b>Host</b></td><td>&nbsp;</td>';
	echo "</tr>\n";

	$resq = "`";
	if( $res ) {
		$resq     = "";
		$splitres = explode( ",", $res );
		foreach( $splitres as $line ) {
			$resq .= " no='" . mysql_escape_string( $line ) . "' OR";
		}
		$resq = rtrim( $resq, " OR" );
		$resq = "` WHERE" . $resq;

	}
	elseif( $thread && $ip ) {
		$max_results = 5000;
		$thread      = (int)$thread;
		$resq        = "` WHERE (no='$thread' OR resto='$thread') AND host='" . sprintf( "%s", long2ip( -( 4294967296 - $ip ) ) ) . "'";
	}
	elseif( $ip ) {
		$max_results = 5000;
		$resq        = "` WHERE host='" . sprintf( "%s", long2ip( -( 4294967296 - $ip ) ) ) . "'";
	}
	elseif( $thread ) {
		$max_results = 5000;
		$thread      = (int)$thread;
		$resq        = "` WHERE no='$thread' OR resto='$thread'";
	}

	if( $threadmode ) {
		if( !$result = mysql_board_call( "(SELECT child.*,parent.root proot from `" . SQLLOG . "` child LEFT OUTER JOIN `" . SQLLOG . "` parent ON child.resto=parent.no) UNION (SELECT *,root proot from `" . SQLLOG . "` parent WHERE resto=0) ORDER BY proot DESC, no ASC LIMIT " . $from . ", " . $max_results ) ) {
			echo S_SQLFAIL;
		}
	}
	else {
		if( !$result = mysql_board_call( "select * FROM `" . SQLLOG . $resq . " order BY no DESC LIMIT " . $from . ", " . $max_results ) ) {
			echo S_SQLFAIL;
		}
	}

	$j = 0;
	while( $row = mysql_fetch_assoc( $result ) ) { //FIXME use assoc
		$j++;
		$img_flag = false;
		extract( $row, EXTR_OVERWRITE );
		// Format
		//$now=preg_replace('@^(../..)/..@','$1',$now);
		//$now=preg_replace('/\(.*\)/','&nbsp;',$now);
		$fullname = str_replace( '</span> <span class="postertrip">!', ' #', $name );
		$name     = strip_tags( $name );
		$fullname = strip_tags( $name ); //for capcode cleaning

		if( strpos( $sub, 'SPOILER<>' ) !== false ) $sub = substr( $sub, 9 );

		$fullsub = $sub;
		if( strlen( $name ) > 14 ) $name = substr( $name, 0, 15 ) . "...";
		if( strlen( $sub ) > 14 ) $sub = substr( $sub, 0, 15 ) . "...";
		//if( $email ) $name = "<a href=\"mailto:$email\">$name</a>";
		$shortcom = html_entity_decode( preg_replace( "/<[^>]+>/", " ", $com ), ENT_QUOTES, "UTF-8" );
		if( strlen( $shortcom ) > 35 ) $shortcom = substr( $shortcom, 0, 36 ) . "...";
		// Link to the picture
		if( $ext ) {
			$img_flag = true;
			if( !$filedeleted ) {
				if( SQLLOG == "f" ) {
					$filelink = $filename . $ext;
				}
				else {
					$filelink = $tim . $ext;
				}
				$clip = "<a href=\"" . IMG_DIR2 . $filelink . "\" target=_blank>" . $filelink . "</a>";
			}
			else {
				$clip = "<s>" . $filelink . "</s>";
			}
			$size = $fsize / 1024;
			$size = round( $size, 2 ) . " KB";
			$all  = $all + $fsize; //total calculation
			$md5  = substr( $md5, 0, 10 );
		}
		else {
			$clip = "";
			$size = 0;
			$md5  = "";
		}
		$bg = ( $j % 2 ) ? "d0d0f0" : "f6f6f6"; //BG color
		if( $threadmode ) {
			$bg = ( !$resto ) ? "d0f0d0" : "eeffee";
		}

		$displayhost = $host;

		$cboard = explode( "/", $_SERVER[ 'SCRIPT_NAME' ] );
		$cboard = $cboard[ 1 ];

		$bantrue = 0;
		if( !$banned = mysql_global_call( "SELECT host,board,global,zonly,DATE_FORMAT(length, 'Until %W, %M %D, %Y.') AS buntil FROM " . SQLLOGBAN . " WHERE host='" . $host . "' AND active=1" ) ) {
			echo S_SQLFAIL;
		}
		$bannedrows = mysql_num_rows( $banned );
		if( $bannedrows > 0 ) {
			$row    = mysql_fetch_array( $banned );
			$buntil = $row[ 'buntil' ];
			if( $row[ 'board' ] == $cboard ) {
				$bg = "f0d0d0";
				if( $buntil == "" ) $buntil = "Indefinitely.";
				$bantrue = 1;
			}
			if( $row[ 'global' ] == 1 ) {
				$bg = "f0a0a0";
				if( $buntil == "" ) $buntil = "Indefinitely.";
				//$globally = " (Globally)";
				$bantrue = 1;
			}
			elseif( $row[ 'zonly' ] == 1 ) {
				$bg = "a0f0a0";
				if( $buntil == "" ) $buntil = "Indefinitely.";
				$bantrue = 0;
				//$globally = " (".$board.")";
			}
			else {
				//$globally = " (".$board.")";
			}
		}

		echo "<tr bgcolor=\"#$bg\"><td><input type=\"checkbox\" id=\"fake$no\" name=\"$no\" value=\"delete\"></td>";
		if( $resto == 0 ) {
			$spec = "";
			if( $sticky == 1 ) $spec = " color: #800080;";
			if( $permasage == 1 ) $spec = " text-decoration: underline;";
			if( $closed == 1 ) $spec = " color: #FF0000;";
			if( $sticky == 1 && $closed == 1 ) $spec = " color: #808080;";

			echo "<td><a href=\"" . SELF_PATH . "?res=$no\" target=\"_blank\" style=\"font-weight: bold;" . $spec . "\">$no</a></td>";
		}
		else {
			$parentline = ( $threadmode ? "&#x2514;" : "" );
			echo "<td><a href=\"" . SELF_PATH . "?res=$no#$no\" target=\"_blank\">$parentline$no</a></td>";
		}
		echo "<td>$now</td><td title=\"$fullname\" align=\"center\"><b>$name</b></td>";
		echo "<td title=\"$fullsub\">$sub</td><td><span id='short$no' ondblclick='swap(\"full$no\",\"short$no\")' title='Double-click to show full comment'>$shortcom</span><span id='full$no' ondblclick='swap(\"full$no\",\"short$no\")' style='display:none;'>$com</span></td>";
		echo "<td style=\"text-align: center\">$displayhost</td><td><input type=\"button\" value=\"More\" onClick=\"more('" . $no . "a','" . $no . "b');\"></td>\n";
		echo "</tr>\n";
		echo "<tr id=\"" . $no . "a\" bgcolor=\"#a0c0ff\" align=\"center\" style=\"display: none;\">";
		// echo "<td colspan=2>&nbsp;</td>";


		if( $size != 0 ) {
			echo "<td colspan=\"3\"><b>File</b></td>";
			echo "<td colspan=\"5\">&nbsp;</td>";
			echo "</tr>";
			echo "<tr id=\"" . $no . "b\" bgcolor=\"#$bg\" style=\"display: none;\">";
			// echo "<td colspan=2>&nbsp;</td>";
			echo "<td colspan=3 align=\"center\">$clip ($size)</td>";
		}
		elseif( $resto == 0 ) {
			//echo "<td colspan=3 align=\"left\"><b>Text-only thread</b></td>";
			//echo "<td colspan=2>";
			if( $bannedrows > 0 ) {
				echo '<td colspan="6" align="left"><b>Text-only thread</b></td>';
				echo '<td colspan="2"><b>Ban length</b></td>';
			}
			else {
				echo "<td colspan=8 align=\"left\"><b>Text-only thread</b></td>";
			}

			echo "</tr>";
			echo "<tr id=\"" . $no . "b\" bgcolor=\"#$bg\" style=\"display: none;\">";
			$span = 8;
		}
		else {
			echo "<td colspan=\"3\" align=\"center\"><b>Reply to thread</b></td>";
			echo "<td colspan=\"5\">&nbsp;</td>";
			echo "</tr>";
			echo "<tr id=\"" . $no . "b\" bgcolor=\"#$bg\" style=\"display: none;\">";
			//echo "<td colspan=3>&nbsp;</td>";
			echo "<td colspan=\"3\" align=\"center\"><a href=\"" . $SELF_PATH . "?thread=$resto\">$resto</a></td>";
			$span = 5;
		}
		echo "<td colspan=\"$span\" align=\"center\"><input type=\"button\" value=\"Display all posts by IP\" onClick=\"location.href='" . $SELF_PATH . "?ip=" . sprintf( "%u", ip2long( $host ) ) . "'\">&nbsp;&nbsp;&nbsp;&nbsp;<input type=\"button\" value=\"Delete all posts by IP\" onClick=\"popup('admin=delall&id=$no');\">";
		/*if ($bantrue) {
	   	echo "&nbsp;&nbsp;&nbsp;&nbsp;<input type=\"button\" value=\"Unban user\" onClick=\"popup('admin=unban&id=$no');\"></td>";
  	} else  {*/
		//  if (!$bantrue) {
		echo "&nbsp;&nbsp;&nbsp;&nbsp;<input type=\"button\" value=\"Ban user\" onClick=\"popup('admin=ban&id=$no');\">";
		//  }
		//}
		if( $resto == 0 ) {
			echo "&nbsp;&nbsp;&nbsp;&nbsp;<input type=\"button\" value=\"Thread options\" onClick=\"popup('admin=opt&id=$no');\">";
			if( !$thread ) {
				echo "&nbsp;&nbsp;&nbsp;&nbsp;<input type=\"button\" value=\"Replies\" onClick=\"location.href='$SELF_PATH?thread=$no'\"></td>";
			}
		}
		echo "</tr>";
	}

	echo "</table><p style=\"margin: 0px; padding: 0px; text-align: center;\"><input type=\"submit\" value=\"" . S_ITDELETES . "$msg\"> ";
	echo "<input type=\"reset\" value=\"" . S_RESET . "\">";
	echo " [<input type=\"checkbox\" name=\"onlyimgdel\" value=\"on\"><!--checked-->" . S_MDONLYPIC . "]</form><br>";

	//$all = (int)($all / 1024);

	//page stuff
	$total_results = mysql_result( mysql_board_call( "SELECT COUNT(*) as Num FROM `" . SQLLOG . "`" ), 0 );
	$total_pages   = ceil( $total_results / $max_results );

	echo "</form><form action=\"" . https_self_url() . "\" method=\"POST\">\n";
	echo "<input type=\"hidden\" name=\"mode\" value=\"admin\"><input type=\"hidden\" name=\"admin\" value=\"del\"><input type=\"hidden\" name=\"user\" value=\"$user\"><input type=\"hidden\" name=\"pass\" value=\"$pass\">\n";

	echo '<center><div class="pagelist" style="float: none!important; display: inline-block;"><div class="prev">';
	if( $p > 1 ) {
		$prev = ( $p - 1 );
		echo "<form action=\"" . https_self_url() . "\" method=\"POST\">\n";
		echo "<input type=\"hidden\" name=\"mode\" value=\"admin\"><input type=\"hidden\" name=\"admin\" value=\"del\"><input type=\"hidden\" name=\"user\" value=\"$user\"><input type=\"hidden\" name=\"pass\" value=\"$pass\">\n";
		echo "<input type=\"hidden\" name=\"p\" value=\"$prev\"><input type=\"submit\" value=\"Previous\"></form>";
	}
	else {
		echo "<span>Previous</span>";
	}

	echo "</div><div class=\"next\">";
	if( $p < $total_pages ) {
		$next = ( $p + 1 );
		echo "<form action=\"" . https_self_url() . "\" method=\"POST\">\n";
		echo "<input type=\"hidden\" name=\"mode\" value=\"admin\"><input type=\"hidden\" name=\"admin\" value=\"del\"><input type=\"hidden\" name=\"user\" value=\"$user\"><input type=\"hidden\" name=\"pass\" value=\"$pass\">\n";
		echo "<input type=\"hidden\" name=\"p\" value=\"$next\"><input type=\"submit\" value=\"Next\"><input type=\"hidden\" name=\"threadmode\" value=\"$threadmode\"></form>";
	}
	else {
		echo "<span>Next</span>";
	}
	echo "</div></div>";
	echo "</center></center>";
	echo str_replace( "12pt", "10pt", $navinc );

	die( "</body></html>" );
}

// return the images/thumbnails for a single post
// $row is an assoc. array representation of the post
function image_files_for( $row )
{
	$del_files = array();
	// we always need to delete the image
	$del_files[ IMG_DIR . $row[ 'tim' ] . $row[ 'ext' ] ] = 1;
	// and the thumbnail
	$del_files[ THUMB_DIR . $row[ 'tim' ] . 's.jpg' ] = 1;
	// and the oekaki replay
	if (ENABLE_OEKAKI_REPLAYS) {
		$del_files[IMG_DIR . $row['tim'] . '.tgkr'] = 1;
	}
	// and the resized mobile images
	if (MOBILE_IMG_RESIZE && $row['m_img']) {
	  $images["{$row['tim']}m.jpg"] = 1;
	}
	
	return $del_files;
}

// delete all posts from an IP, maintaining the consistency of the files and db
function delallbyip($ip, $imgonly, $replies_only = false)
{
	$ip = mysql_real_escape_string( $ip );
	
	if ($ip === '') {
	  error('Invalid IP');
	}
	
	if ($replies_only) {
		$_rep_sql = ' AND resto > 0';
	}
	else {
		$_rep_sql = '';
	}
	
	mysql_board_call( "LOCK TABLES `" . SQLLOG . "` WRITE" );
	$query          = mysql_board_call("SELECT * FROM `" . SQLLOG . "` WHERE archived = 0 AND host='$ip'" . $_rep_sql);
	$del_files      = array(); // keys = delete these files
	$update_threads = array(); // keys = update these threads' HTML
	$del_threads    = array(); // keys = delete replies to these thread numbers from db
	$del_all        = array(); //  keys = these are being deleted from the db (used to clean up reports etc.)

	while( $row = mysql_fetch_assoc( $query ) ) {
		// we always need to delete the image files
		$del_files += image_files_for( $row );

		if( !$imgonly ) // deleting this post from the db
		{
			$del_all[ $row[ 'no' ] ] = 1;
		}

		if( $row[ 'resto' ] ) { // it's a reply, need to update parent
			$update_threads[ $row[ 'resto' ] ] = 1;
		}
		elseif( !$imgonly ) { // it's a thread parent and it's getting deleted from db
			// need to delete thread html
			if( USE_GZIP == 1 ) {
				// HTML
				$del_files[ RES_DIR . $row[ 'no' ] . PHP_EXT ]         = 1;
				$del_files[ RES_DIR . $row[ 'no' ] . PHP_EXT . '.gz' ] = 1;
				// JSON
				$del_files[ RES_DIR . $row[ 'no' ] . '.json' ] = 1;
				$del_files[ RES_DIR . $row[ 'no' ] . '.json.gz' ] = 1;
			}
			else {
				// HTML
				$del_files [ RES_DIR . $row[ 'no' ] . PHP_EXT ] = 1;
				// JSON
				$del_files[ RES_DIR . $row[ 'no' ] . '.json' ] = 1;
			}

			$del_threads[ $row[ 'no' ] ] = 1;
			$replyquery                  = mysql_board_call( "SELECT * FROM `" . SQLLOG . "` WHERE resto='{$row[ 'no' ]}'" );
			while( $replyrow = mysql_fetch_assoc( $replyquery ) ) {
				$del_files += image_files_for( $replyrow );
				$del_all[ $replyrow[ 'no' ] ] = 1;
			}
			mysql_free_result( $replyquery );
		}

		{
			$auser   = $_COOKIE[ '4chan_auser' ];
			$adfsize = ( $row[ 'fsize' ] > 0 ) ? 1 : 0;
			$adname  = str_replace( '</span> <span class="postertrip">!', '#', $row[ 'name' ] );
			if( $imgonly ) {
				$imgonly = 1;
			}
			else {
				$imgonly = 0;
			}
			//$row['sub']      = mysql_escape_string( $row['sub'] );
			//$row['com']      = mysql_escape_string( $row['com'] );
			//$row['filename'] = mysql_escape_string( $row['filename'] );
			validate_admin_cookies();
			mysql_global_do( "INSERT INTO " . SQLLOGDEL . " (imgonly,postno,resto,board,name,sub,com,img,filename,admin,email,admin_ip,tool) values('%s',%d,%d,'%s','%s','%s','%s','%s','%s','%s','%s','%s', 'del-all-by-ip')", $imgonly, $row[ 'no' ], $row[ 'resto' ], SQLLOG, $adname, $row[ "sub" ], $row[ "com" ], $adfsize, $row[ "filename" ].$row['ext'], $auser, $row[ 'email' ], $_SERVER['REMOTE_ADDR']);
		}
	}
	mysql_free_result( $query );

	// delete IP's posts
	if( !$imgonly ) {
		mysql_board_call( "DELETE FROM `" . SQLLOG . "` WHERE host='$ip'" . $_rep_sql );
		// delete replies to IP's parent posts
		foreach( $del_threads as $parent => $unused ) {
			mysql_board_call( "DELETE FROM `" . SQLLOG . "` WHERE resto='$parent'" );
		}
	}
	else {
		mysql_board_call( "UPDATE `" . SQLLOG . "` SET filedeleted=1,root=root WHERE host='$ip'" . $_rep_sql );
	}
	mysql_board_call( "UNLOCK TABLES" );

	// delete all necessary files (images and HTML)
	foreach( $del_files as $file => $unused ) {
		@unlink( $file );

		if( CLOUDFLARE_PURGE_ON_DEL && strpos( $file, IMG_DIR ) !== false ) {
			$filename    = basename( $file );
			cloudflare_purge_by_basename(BOARD_DIR, $filename);
		}
	}

	// delete reports for deleted posts
	foreach( $del_all as $no => $unused ) {
		mysql_global_do( "DELETE FROM reports WHERE board='" . SQLLOG . "' AND no='$no'" );
		mysql_global_do( "DELETE FROM reports_for_posts WHERE board='" . SQLLOG . "' AND postid='$no'" );
	}

	echo "<br><strong>Deleting posts...</strong><br>";
	if( $imgonly ) {
		echo "All images deleted.<br><strong>Deletion successful!</strong><br>";
	}
	else {
		echo "All posts deleted.<br><strong>Deletion successful!</strong><br>";
	}

	// rebuild html
	if( count( $update_threads ) > 25 ) {
		echo "Rebuilding all pages...";
		echo ( rebuild_all( $error ) ? " OK!" : $error ); // at some number of threads, this must be faster...
	}
	else {
		foreach( $update_threads as $parent => $unused ) {
			if( $del_threads[ $parent ] ) continue; // this thread was deleted, forget it

			echo "Rebuilding No.$parent...";
			echo ( rebuild_thread( $parent, $error ) ? " OK!" : $error );
			echo "<br>";
		}
	}

	die( "<br><strong>Rebuild successful!</strong><span style=\"display:none\">Done.</span>" );
}

function admindelall()
{
	global $onlyimgdel, $onlyrepdel, $id, $user, $pass;
	$delno   = array();
	$delflag = false;
	reset( $_POST );
	while( $item = each( $_POST ) ) {
		if( $item[ 1 ] == 'delete' ) {
			array_push( $delno, intval( $item[ 0 ] ) );
			$delflag = true;
		}
	}
	if( $delflag ) {
		if( !$result = mysql_board_call( "SELECT host FROM `" . SQLLOG . "` WHERE archived = 0 AND no=" . mysql_real_escape_string( $id ) ) ) {
			echo S_SQLFAIL;
		}
		$row = mysql_fetch_row( $result );
		list( $host ) = $row;
		delallbyip($host, $onlyimgdel, $onlyrepdel === true);
	}
	echo "<input type=\"hidden\" name=\"mode\" value=\"admin\">\n";
	echo "<input type=\"hidden\" name=\"admin\" value=\"delall\">\n";
	echo "<input type=\"hidden\" name=\"user\" value=\"$user\">\n";
	echo "<input type=\"hidden\" name=\"pass\" value=\"$pass\">\n";
	echo "<input type=\"hidden\" name=\"id\" value=\"$id\">\n";
	echo "<input type=\"hidden\" name=\"$id\" value=\"delete\">\n";
	echo "<input type=\"hidden\" name=\"onlyimgdel\" value=\"on\">\n";
	echo "<input type=\"submit\" value=\"Delete images only\">\n";
	echo "</form><form \"action=\"" . https_self_url() . "\" method=\"POST\">\n";
	echo "<input type=\"hidden\" name=\"mode\" value=\"admin\">\n";
	echo "<input type=\"hidden\" name=\"admin\" value=\"delall\">\n";
	echo "<input type=\"hidden\" name=\"pass value=\"$pass\">\n";
	echo "<input type=\"hidden\" name=\"id\" value=\"$id\">\n";
	echo "<input type=\"hidden\" name=\"$id\" value=\"delete\">\n";
	echo "<input type=\"submit\" value=\"Delete all posts\">\n";
	echo "</form>\n";
	die( "</body></html>" );
}

function ban_template_js($post_has_file = true, $is_thread = false) {
  $templates = array();
  
  $level_map = get_level_map();
  
  $query = "SELECT * FROM ban_templates ORDER BY LENGTH(rule), rule ASC";
  $q = mysql_global_call($query);
  
  while ($r = mysql_fetch_assoc($q)) {
    if (!preg_match('#^(global|' . BOARD_DIR . ')[0-9]+$#', $r[ 'rule' ])) {
      continue;
    }
    
    if (($r['no'] == 1 || $r['no'] == 123 || $r['no'] == 213) && !$post_has_file) {
      continue;
    }
    
    if ($r['no'] == 6 && !DEFAULT_BURICHAN) {
      continue;
    }
    
    if ($r['no'] == 17 && (BOARD_DIR === 'mlp' || BOARD_DIR === 'trash')) {
      continue;
    }
    
    if ($r['no'] == 223 && BOARD_DIR === 'pol') {
      continue;
    }
    
    // Global 3 - Troll posts
    if ($r['no'] == 222 && BOARD_DIR === 's4s') {
      continue;
    }
    
    // Global 3
    if ((BOARD_DIR === 'b' || BOARD_DIR === 'bant') && strpos($r['rule'], 'global3') !== false) {
      continue;
    }
    
    // Skip OP-only templates
    if ($r['postban'] === 'move' && !$is_thread) {
      continue;
    }
    
    if ($level_map[$r['level']] !== true) {
      continue;
    }
    
    unset($r['special_action']);
    
    $templates[] = $r;
  }
  
  return '
  <script type="text/javascript" src="//s.4cdn.org/js/admin_autocomplete.10.js"></script>
  <script>
  var e_template = document.getElementsByName("template")[0];
  var globalTemplates = ' . json_encode( $templates, JSON_PARTIAL_OUTPUT_ON_ERROR ) . ';
  var templates = {};
  var localTemplates = {};

  function $(e) {return document.getElementsByName(e)[0];}
  function unhide(e) {$(e).style.visibility="visible";}

  function chooseTemplate() {
    var i = e_template.selectedIndex - 1;
    
    Feedback.checkTemplate(i);
    
    if (i < 0) {
      return;
    }

    var t = templates[i];

    $("pubreason").value = t.publicreason;
    $("pvtreason").value  = t.privatereason;
    $("days").value = (t.banlen == "" && t.days > 0) ? t.days : "";
    $("warn").checked = (t.banlen == "" && t.days == 0);
    $("indefinite").checked = (t.banlen == "indefinite");
    $("banmsg").checked = t.publicban==1;
    $("bantype").value = t.bantype;
    
    $("postban").value = t.postban;
    
    if (t.postban === "move") {
      document.getElementById("js-move-board-sel").value = t.postban_arg;
    }
    
    $("templateno").value = t.no;
    
    onPostBanSelChange.call(document.getElementById("js-postban-sel"));
    undisableForm();
  }

  function undisableForm() {
    var f = document.querySelectorAll("*[disabled=disabled]");
    var len = f.length;

    for( var i = 0; i < len; i++ ) {
      f[i].removeAttribute("disabled");
    }
  }

  function updateTemplate() {
    var t = {};
    var name = prompt("Enter a name for this template");

    t.banlen = $("indefinite").checked ? "indefinite" : "";
    t.bantype = $("bantype").value;
    //t.blacklist;
    t.days = ($("warn").checked) ? 0 : $("days").value;
    t.name = name;
    t.postban = $("postban").value;
    t.publicreason = $("pubreason").value;
    t.privatereason = $("pvtreason").value;

    localTemplates[name] = t;
    localStorage.setItem("ban_templates", JSON.stringify(localTemplates));

    initTemplate();

    return false;
  }

  function deleteTemplate() {
    var i = e_template.selectedIndex - 1;

    if (i >= globalTemplates.length)
      delete localTemplates[templates[i].name];

    localStorage.setItem("ban_templates", JSON.stringify(localTemplates));
    initTemplate();

    return false;
  }

  function initTemplate() {
    templates = globalTemplates;
    
    e_template.innerHTML = "<option value=\"-1\">None Selected (Required)</option>";

    for (var i=0;i<templates.length;i++) {
      var t = templates[i];
      //if( !t.name.match(/Global/) && !t.name.match(/\/' . BOARD_DIR . '\//) && i < globalTemplates.length ) continue;

      var o = document.createElement("option");
      o.value = i;
      o.innerHTML = t.name + ((i < globalTemplates.length) ? "" : " [Local]");
      e_template.appendChild(o);
    }
    unhide("template_row");
    if (localStorage && false == true) {
      unhide("local_template_row");
      unhide("deltemplate");
    }
  }

  initTemplate();
  </script>
  ';
}

function do_post_quarantine( $board, $post )
{
	/*
	Gathers -
	Current post, current post image
	All images of posts in the same thread
	*/

	mysql_board_lock( true );
	$host = $post[ "host" ];
	
	$post_json = make_post_json($post);
	$postno = $post["no"];
	
	$xffres = mysql_global_do("select xff from xff where board='%s' and postno=%d", $board, $postno);
	if (mysql_num_rows($xffres)) $xff = mysql_fetch_assoc($xffres)["xff"];
	$res = mysql_global_do("insert into ncmec_reports (board,post_num,post_json,xff) value ('%s',%d,'%s','%s')", $board, $postno, $post_json, $xff);
	$reportid = mysql_global_insert_id();
	
	$path = "/www/quarantine/$reportid";
	mkdir( $path );
	
	$image = $post[ "tim" ] . $post[ "ext" ];
	$dst_path = "$path/$image";
	$tmp_path = $dst_path.".tmp";
	@copy( IMG_DIR . "/$image", $tmp_path );
	@rename($tmp_path, $dst_path);
	
	if (!file_exists($dst_path)) {
		// guess we can't quarantine it after all
		mysql_global_do("delete from ncmec_reports where id=%d", $reportid);
	} else {
		$resto = $post["resto"];
		$respred = $resto ? "no=$resto or resto=$resto" : "resto=$postno";
		$q = mysql_board_call( "select * from `%s` where host='%s' and no!=%d and ($respred)", SQLLOG, $host, $postno );
		while( $p = mysql_fetch_assoc( $q ) ) {
			$i = $p[ "tim" ] . $p[ "ext" ];
			mkdir( "$path/images" );
			@copy( IMG_DIR . "/$i", "$path/images/$i" );
		}
	}
	mysql_board_unlock();
}

function do_template_special_action($template, $board, $row, $is_manager = false) {
  if ($template['special_action'] === 'quarantine') {
    do_post_quarantine($board, $row);
  }
  
  if ($is_manager) {
    if( $template['special_action'] === 'quarantine' || $template['special_action'] === 'revokepass_spam' || $template['special_action'] === 'revokepass_illegal') {
      $pass = $row['4pass_id'];
      $status = $template['special_action'] === 'revokepass_spam' ? 4 : 5;
      mysql_global_do("UPDATE pass_users SET status = %d WHERE user_hash = '%s' AND status = 0 LIMIT 1", $status, $pass);
    }
  }
}

/**
 * Auto-rangeban log entries
 * $tpl_id: ban or BR template id
 * $source: 1 = ban, 0 = ban request
 */
function process_auto_rangeban($ip, $browser_id, $thread_id, $post_id, $tpl_id, $source) {
  $thread_id = (int)$thread_id;
  
  if (!$browser_id) {
    return false;
  }
  
  // Prune stale entries
  $sql = "DELETE FROM event_log WHERE type = 'rangeban_hint' AND created_on < DATE_SUB(NOW(), INTERVAL 1 HOUR)";
  
  $res = mysql_global_call($sql);
  
  if (!$res) {
    return false;
  }
  
  $need_rangeban = false;
  
  // Check if should apply auto rangeban (2 strikes for BRs, immediate for Bans)
  $range_sql = explode('.', $ip);
  
  $range_sql = "{$range_sql[0]}.{$range_sql[1]}.%";
  
  // Ban
  if ($source === 1) {
    $need_rangeban = true;
  }
  // Ban Request
  else {
    $sql =<<<SQL
SELECT COUNT(DISTINCT ip) FROM event_log WHERE
type = 'rangeban_hint' AND board = '%s' AND thread_id = $thread_id AND ua_sig = '%s'
AND ip LIKE '%s'
SQL;
    
    $res = mysql_global_call($sql, BOARD_DIR, $browser_id, $range_sql);
    
    if (!$res) {
      return false;
    }
    
    $count = (int)mysql_fetch_row($res)[0];
    
    if ($count > 0) {
      $need_rangeban = true;
    }
  }
  
  if ($need_rangeban) {
    // Skip if a rangeban already exists
    $sql =<<<SQL
SELECT COUNT(*) FROM event_log WHERE
type = 'rangeban' AND board = '%s' AND thread_id = $thread_id AND ua_sig = '%s'
AND ip LIKE '%s' AND created_on > DATE_SUB(NOW(), INTERVAL 1 HOUR)
SQL;
  
    $res = mysql_global_call($sql, BOARD_DIR, $browser_id, $range_sql);
    
    if (!$res) {
      return false;
    }
    
    $count = (int)mysql_fetch_row($res)[0];
    
    if ($count > 0) {
      return true;
    }
    
    return add_auto_rangeban_log($ip, $browser_id, $thread_id, $post_id, true, $tpl_id, $source);
  }
  
  // Add hint entry
  return add_auto_rangeban_log($ip, $browser_id, $thread_id, $post_id, false, $tpl_id, $source);
}

function add_auto_rangeban_log($ip, $browser_id, $thread_id, $post_id, $is_ban = false, $tpl_id = 0, $source = 0) {
  if ($is_ban) {
    $type = 'rangeban';
  }
  else {
    $type = 'rangeban_hint';
  }
  
  return write_to_event_log($type, $ip, [
    'board' => BOARD_DIR,
    'thread_id' => $thread_id,
    'post_id' => $post_id,
    'ua_sig' => $browser_id,
    'arg_num' => $tpl_id,
    'arg_str' => (int)$source
  ]);
}

/**
 * Collects posts related to the provided Password.
 * This is used for banning people who hop between multiple IPs.
 * Only posts made from non-mobile devices are collected.
 */
function admin_collect_related($ip, $pwd) {
  if (!$pwd || !$ip) {
    return null;
  }
  
  $range_sql = explode('.', $ip);
  
  $range_sql[0] = (int)$range_sql[0];
  $range_sql[1] = (int)$range_sql[1];
  
  $range_sql = "{$range_sql[0]}.{$range_sql[1]}.";
  
  $sql = <<<SQL
SELECT SQL_NO_CACHE host, 4pass_id FROM `%s`
WHERE archived = 0 AND pwd = '%s'
AND host NOT LIKE '$range_sql%%'
AND email NOT LIKE '1%%'
GROUP BY host
SQL;
  
  $res = mysql_board_call($sql, BOARD_DIR, $pwd);
  
  if (!$res) {
    return null;
  }
  
  $data = [];
  
  while ($post = mysql_fetch_assoc($res)) {
    $data[] = $post;
  }
  
  return $data;
}

function admin_toggle_protected_thread($flag, $board, $thread_id, $duration = 1800) {
  if (!$board || !$thread_id || $duration <= 0) {
    return false;
  }
  
  $m = new Memcached();
  $m->setOption(Memcached::OPT_SERVER_FAILURE_LIMIT, 1);
  $m->setOption(Memcached::OPT_SEND_TIMEOUT, 500000); // 500ms
  $m->setOption(Memcached::OPT_RECV_TIMEOUT, 500000); // 500ms
  $m->addServer(MEMCACHED_HOST, MEMCACHED_PORT);
  
  if (!$m) {
    return false;
  }
  
  $key = "def.$board.$thread_id";
  
  if ($flag) {
    if ($duration > 43200) { // 12h
      $duration = 43200;
    }
    
    $now = $_SERVER['REQUEST_TIME'];
    
    return $m->set($key, 1, $now + $duration);
  }
  else {
    return $m->delete($key);
  }
}

function admin_get_template_by_id($tpl_id) {
  $tpl_id = (int)$tpl_id;
  $sql = "SELECT * FROM ban_templates WHERE no = $tpl_id LIMIT 1";
  $res = mysql_global_call($sql);
  if (!$res) {
    return false;
  }
  return mysql_fetch_assoc($res);
}

function admin_is_ip_rangebanned($ip) {
  require_once 'lib/geoip2.php';
  
  $_asninfo = GeoIP2::get_asn($ip);
  
  if ($_asninfo) {
    $asn = (int)$_asninfo['asn'];
  }
  else {
    $asn = 0;
  }
  
  if ($asn > 0) {
    $query =<<<SQL
SELECT id FROM iprangebans
WHERE asn = $asn
AND active = 1 AND boards = '' AND expires_on = 0 AND report_only = 0
LIMIT 1
SQL;
    
    $res = mysql_global_call($query);
    
    if (!$res) {
      return false;
    }
    
    if (mysql_num_rows($res) > 0) {
      return true;
    }
  }
  
  $long_ip = ip2long($ip);
  
  if (!$long_ip) {
    $this->error('Invalid IP.');
  }
  
  $query =<<<SQL
SELECT id FROM iprangebans
WHERE range_start <= $long_ip AND range_end >= $long_ip
AND active = 1 AND boards = '' AND expires_on = 0 AND report_only = 0
LIMIT 1
SQL;
  
  $res = mysql_global_call($query);
  
  if (!$res) {
    return false;
  }
  
  return mysql_num_rows($res) > 0;
}

function admin_protect_thread() {
  if (!isset($_GET['thread_id'])) {
    error('Bad Request.');
  }
  
  $thread_id = (int)$_GET['thread_id'];
  
  if ($thread_id <= 0) {
    error('Bad Request.');
  }
  
  if (isset($_GET['remove']) && $_GET['remove']) {
    $flag = false;
  }
  else {
    $flag = true;
  }
  
  $ret = admin_toggle_protected_thread($flag, BOARD_DIR, $thread_id);
  
  if (!$ret) {
    error('Something went wrong.');
  }
  else {
    echo "Done.";
  }
}

// Signs the ip + timestamp for authenticating reverse dns requests below
// FIXME: This is to avoid delaying ban panels
function admin_get_rev_ip_sig($ip, $t) {
  if (!$ip || !$t) {
    return false;
  }
  
  $secret = 'BusEFdduVhgVKIMAx1ndhvzrgMyA5uCcfRnvIKq4+0X2vL8elzf6wHZCpWS9fsTsNG/XdlwiIBV68hzlGm6sGQ==';
  $secret = base64_decode($secret);
  
  if (!$secret) {
    return false;
  }
  
  $msg = "$ip $t";
  
  return hash_hmac('sha256', $msg, $secret);
}

// Prints a JSON response with the hostname of the IP
// FIXME: This is to avoid delaying ban panels
// The IP needs to be in the long int format
function admin_reverse_ip() {
  if (!isset($_GET['ip']) || !isset($_GET['t']) || !isset($_GET['s'])) {
    die('N/A');
  }
  
  if (!$_GET['t'] || !$_GET['s']) {
    die('N/A');
  }
  
  $ip = long2ip($_GET['ip']);
  
  if (!$ip) {
    die('N/A');
  }
  
  if ($_SERVER['REQUEST_TIME'] - (int)$_GET['t'] > 3) {
    die('N/A');
  }
  
  $sig = admin_get_rev_ip_sig($_GET['ip'], $_GET['t']);
  
  if (!$sig) {
    die('N/A');
  }
  
  if (hash_equals($sig, $_GET['s']) !== true) {
    die('N/A');
  }
  
  $rev = gethostbyaddr($ip);
  
  if ($rev && $rev == $ip) {
    $rev = '';
  }
  
  header('Content-Type: application/json');
  echo json_encode(['rev' => $rev]);
}

/* Admin banning */
function adminban()
{
  if (BOARD_DIR == 'j' && !has_level('manager')) {
    die();
  }
  
	global $id, $user, $pass;
	
  $by_tpl_mode = false;
  
  // for async calls from reports.4chan.org
  if (isset($_POST['by_tpl']) && $_POST['by_tpl']) {
    $template = admin_get_template_by_id($_POST['by_tpl']);
    
    if (!$template) {
      die('No such template');
    }
    
    $by_tpl_mode = true;
    
    $_POST['submit'] = 1;
    $_POST['pubreason'] = $template['publicreason'];
    $_POST['pvtreason'] = $template['privatereason'];
    $_POST['days'] = $template['days'];
    $_POST['warn'] = (int)($template['days'] == 0 && $template['banlen'] == '');
    $_POST['indefinite'] = (int)($template['banlen'] === 'indefinite');
    $_POST['banmsg'] = 0;
    $_POST['bantype'] = $template['bantype'];
    
    // This will be amended later for delall -> delallrep
    $_POST['postban'] = $template['postban'];
    
    if ($template['postban'] === 'move' && $template['postban_arg']) {
      $_POST['move-board'] = $template['postban_arg'];
    }
  }
  else {
    $template = null;
  }
  
	$submit        = $_POST[ 'submit' ];
	$start_time    = microtime( true );
	$xff           = htmlspecialchars($_POST[ 'xff' ], ENT_QUOTES);
	$pubreason     = nl2br( htmlspecialchars( $_POST[ 'pubreason' ] ), false );
	$pvtreason     = nl2br( htmlspecialchars( $_POST[ 'pvtreason' ] ), false );
	$reason        = "$pubreason<>$pvtreason";
	$bannedby      = $_COOKIE['4chan_auser'];
	$days          = $_POST[ 'days' ];
	$warn          = $_POST[ 'warn' ];
	$indefinite    = $_POST[ 'indefinite' ];
	$banmsg        = $_POST[ 'banmsg' ] == 1;
	$globalban     = $_POST[ 'bantype' ] == 'global';
	$zonly         = isset($_POST['zonly']) && $_POST['zonly'] === '1';
	//$pass_id       = htmlspecialchars($_POST[ 'pass_id' ], ENT_QUOTES);
	$board         = BOARD_DIR;
	$postid        = (int)$id;
  
  if ($by_tpl_mode) {
    $template_used = (int)$_POST['by_tpl'];
  }
  else {
    $template_used = (int)$_POST['templateno'];
  }
	
	if( !$result = mysql_board_call( "SELECT HIGH_PRIORITY * FROM `" . SQLLOG . "` WHERE no=" . $postid ) ) {
		die( 'Post no longer exists.<script language="JavaScript">setTimeout("self.close()", 3000); postBack("error-ban-' . $board . '-' . $postid . '");</script>' );
	}
	$row = mysql_fetch_assoc( $result );
	if( $row === false ) die( 'Post no longer exists.<script language="JavaScript">setTimeout("self.close()", 3000); postBack("error-ban-' . $board . '-' . $postid . '");</script>' );
	
	if ($row['archived']) {
	  die('This post is archived.<script language="JavaScript">setTimeout("self.close()", 3000); postBack("error-ban-' . $board . '-' . $postid . '");</script>');
	}
  
	$post_has_file = $row['ext'] && !$row['file_deleted'];
	
	//list( $no, $sticky, $permasage, $closed, $now, $name, $email, $sub, $com, $host, $pwd, $filename, $ext, $w, $h, $tn_w, $tn_h, $tim, $time, $md5, $fsize, $root, $resto ) = $row;
	extract( $row, EXTR_OVERWRITE );
  
  $password = $pwd;
  
  // insert tripcode (trip or !sectrip) if not warning
  $tripcode = '';
  
  if ($warn != 1) {
    $name_bits = explode('</span> <span class="postertrip">!', $name);
    
    if ($name_bits[1]) {
      $tripcode = preg_replace('/<[^>]+>/', '', $name_bits[1]); // fixme: why do we do that?
    }
  }
	
	$name = str_replace( '</span> <span class="postertrip">!', ' #', $name );
	$name = preg_replace( '/<[^>]+>/', '', $name ); // remove all remaining html crap

	if( !$result = mysql_board_call( "select COUNT(*) FROM `" . SQLLOG . "` WHERE host='$host' AND no=$resto" ) ) {
		echo S_SQLFAIL;
	}
	
	if (mysql_result($result, 0, 0) || $resto == 0) {
		$poster_is_op = true;
	}
	else {
		$poster_is_op = false;
	}

if( $submit != "" ) { // pressed submit
	if (!$host) {
    error('You cannot ban this post');
  }
  
	if ($host) {
		$reverse = gethostbyaddr($host);
	}
	else {
		$reverse = '';
	}
  
	$displayhost = ( $reverse && $reverse != $host ) ? "$reverse ($host)" : $host;
  
	if ($template_used > -1) {
    if (!$template) {
      $template = mysql_global_row("ban_templates", "no", $template_used);
    }
	  
	  if (!$template) {
      error('Invalid template');
	  }
	  
    if (!has_level($template['level'])) {
      error('You cannot use this template');
    }
    
    if (($template['no'] == 1 || $template['no'] == 123 || $template['no'] == 213) && !$post_has_file) {
      error('This template requires a post with a file');
    }
  }
	
	if( !$template_used ) {
		$rule = '';
	}
	else {
		$rule = $template[ 'rule' ];
	}
  
	if( !$row ) {
		echo "This post doesn't exist anymore.<br>";
		die( "[<a href=\"javascript:void(0)\" onclick=\"history.back()\">Back</a>]</body></html>" );
	}
	if( $pubreason == "" ) {
		echo "Public reason not specified.<br>";
		die( "[<a href=\"javascript:void(0)\" onclick=\"history.back()\">Back</a>]</body></html>" );
	}
	elseif( $bannedby == "" ) {
		echo "Admin name not specified.<br>";
		die( "[<a href=\"javascript:void(0)\" onclick=\"history.back()\">Back</a>]</body></html>" );
	}
	elseif( !is_numeric( $days ) && ( $indefinite != 1 ) && ( $warn != 1 ) ) {
		echo "Length of ban not specified.<br>";
		die( "[<a href=\"javascript:void(0)\" onclick=\"history.back()\">Back</a>]</body></html>" );
	}
	else {
		if( $warn != 1 ) {
			$ubd_ts = date( "Y-m-d H:i:s", time() + $days * ( 24 * 60 * 60 ) );
		}
		else {
			$ubd_ts = date( "Y-m-d H:i:s", time() );
		}
		if( $indefinite == 1 ) {
			$length = "00000000000000";
		}
		else {
			$length = $ubd_ts;
		}
	}
  
	$is_manager = has_level('manager');
	
	if (!$is_manager) {
	  $zonly = 0;
	}
	
	$nrow = array();

	foreach( $row as $key => $val ) {
		if( ctype_digit( $val ) || is_int( $val ) ) {
			$val = (int)$val;
		}
		$nrow[ $key ] = $val;
	}
	
  if ($row['resto']) {
    $sub_query = mysql_board_call("SELECT sub FROM `%s` WHERE no = %d", $board, $row['resto']);
    $sub_res = mysql_fetch_assoc($sub_query);
    if ($sub_res) {
      $rel_sub = $sub_res['sub'];
      
      if (strpos($rel_sub, 'SPOILER<>') === 0) {
        $rel_sub = substr($rel_sub, 9);
      }
      
      if ($rel_sub !== '') {
        $nrow['rel_sub'] = $rel_sub;
      }
    }
  }
	
  // FIXME: email field
  if (isset($row['email'])) {
    $nrow['ua'] = $row['email'];
    unset($nrow['email']);
  }
  
  $post_json = json_encode($nrow);
  $no_thumb = false;
  
  if ($template && $template['save_post'] !== 'everything') {
    $no_thumb = true;
  }
  
	$result = mysql_global_do( "INSERT INTO " . SQLLOGBAN . " (board,global,zonly,name,host,reverse,xff,reason,length,admin,md5,4pass_id,post_num,rule,post_time,post_json,template_id,admin_ip,tripcode,password) VALUES ('%s', %d, %d, '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', %d, '%s', FROM_UNIXTIME(%d), '%s', %d, '%s', '%s', '%s')", $board, $globalban, $zonly, $name, $host, $reverse, $xff, $reason, $length, $bannedby, $md5, $row[ '4pass_id' ], $no, $rule, $time, $post_json, $template_used, $_SERVER['REMOTE_ADDR'], $tripcode, $password );

	if( !$result ) {
		echo S_SQLFAIL;
	}
  
	if( $ext != '' && $template_used && !$no_thumb ) {
		$salt = file_get_contents( SALTFILE );
		$hash = sha1( BOARD_DIR . $no . $salt );
		@copy( THUMB_DIR . "{$tim}s.jpg", BANTHUMB_DIR . "{$hash}s.jpg" );
	}

	if( $banmsg ) {
		if( $warn ) {
			$samessage = S_USERWARNEDFORPOST;
		}
		else {
			$samessage = S_USERBANNEDFORPOST;
		}

		//if( isset( $_GET[ 'santa' ] ) && $_GET[ 'santa' ] == 'hohoho' ) $samessage = 'USER WAS GIVEN COAL FOR THIS POST';
		
		if (($is_manager || has_flag('banmsg')) && isset($_POST['custombanmsg']) && $_POST['custombanmsg'] != '') {
		  $samessage = mysql_real_escape_string(htmlspecialchars($_POST['custombanmsg'], ENT_QUOTES));
		}

		if( !$result = mysql_board_call( "UPDATE `" . SQLLOG . "` SET root=root,com=CONCAT(com,' <br><br><strong style=\"color: red;\">($samessage)</strong>') WHERE no=%d", $postid ) ) {
			echo S_SQLFAIL;
		}
		mysql_board_call("UPDATE `%s` SET root=root,last_modified=%d where no=%d", SQLLOG, (int)$_SERVER['REQUEST_TIME'], ($resto ? $resto : $postid));
		
		if ($_POST['postban'] !== 'delpost' && $_POST['postban'] !== 'move' && $_POST['postban'] !== 'archive') {
		  admin_clear_reports(BOARD_DIR, $postid);
		}
	}
	
	//print "\n<br>insert: " . (time()  - $start_time)."\n<br>"; //disabling this because again nobody needs to see it/leaks filepaths
	echo "<strong>Banning " . $displayhost . " from ";
	
	if( $globalban == 1 ) {
		echo "the entirety of 4chan...</strong><br>";
		//append_ban( "global", $host );
	}
	else {
		echo "/" . $board . "/...</strong><br>";
		//append_ban( $board, $host );
	}
	
	if( $length == "00000000000000" ) {
		echo " for an indefinite amount of time.";
	}
	else {
		echo " until " . date( 'l, F jS, Y', time() + $days * ( $warn ? 0 : 24 * 60 * 60 ) ) . ".<br><strong>Ban successful!</strong>";
	}
	//print "\n<br>rebuild : " . (microtime(1) - $start_time); //disabling because no point in showing it/leaks file paths
	if( $template ) {
    global $gcon;
    $inserted_ban_id = mysql_insert_id($gcon);
		do_template_special_action( $template, $board, $row, $is_manager );
		if( ( $template[ "blacklist" ] == "image" || $template[ 'blacklist' ] == 'rejectimage' ) && $md5 ) {
			$blban = (int)( $template[ 'blacklist' ] === 'image' );
			$len   = $blban ? $template['days'] : '0';
			mysql_global_do( "insert into blacklist (field,contents,description,addedby,ban,banlength,banreason)" .
					"values ('md5','%s','%s','%s','$blban','$len','%s')",
				$md5, $template[ "name" ] . " (via ban template, ban ID: $inserted_ban_id)", $bannedby, $template[ "publicreason" ] );
		}
	}
  
  // Auto-rangebans processing (bans)
  // FIXME: email field
  $_post_meta = decode_user_meta($row['email']);
  
  if (!$warn && $_post_meta && $_post_meta['is_mobile']) { // mobile devices only
    // global rules only
    if ($template && strpos($template['rule'], 'global') !== false) {
      process_auto_rangeban($host, $_post_meta['browser_id'], $row['resto'], $row['no'], $template['no'], 1);
    }
    
    // Collect and ban other IPs based on the password
    /*
    $related_posts = admin_collect_related($host, $password);
    
    if ($related_posts) {
      write_to_event_log('rel_posts', $host, [
        'board' => BOARD_DIR,
        'thread_id' => $row['resto'],
        'post_id' => $no,
        'pwd' => $password,
        'arg_str' => $rule,
        'meta' => json_encode($related_posts)
      ]);
    }
    */
  }
  
	$should_delete = $_POST[ 'postban' ] == 'delpost' || $_POST[ 'postban' ] == 'delfile';
	
  $skip_rebuild = false;
  
	if( $should_delete ) {
		echo "<br>";
		if (delete_post($no, $_POST[ 'postban' ] == 'delfile' ? 1 : 0, $template ? $template['no'] : false, 'ban')) {
			echo ( ( $_POST[ 'postban' ] == 'delfile' ) ? "<strong>File deleted.</strong>" : "<strong>Post deleted.</strong>" );
		}
    // Fixme, this is for the temporary is2/is3 cache purging api
    if ($ext != '' && $template_used && $template['rule'] == 'global1' && !UPLOAD_BOARD) {
      purge_cache_internal_temp(BOARD_DIR, "$tim$ext");
    }
		//print "\n<br>delete post: " . (microtime(true) - $start_time); //disabling, no point and leaks dirs
	}
	else if ($resto == 0) {
    if ($_POST['postban'] == 'move') {
      if (!isset($_POST['move-board']) || !is_board_valid($_POST['move-board'])) {
        echo ('<strong>Invalid destination board. The thread was not moved.</strong>');
      }
      else {
        move_thread($no, $_POST['move-board']);
        
        echo ('<br><strong>Thread moved to /' . htmlspecialchars($_POST['move-board']) . '/.</strong>');
        
        $skip_rebuild = true;
      }
    }
    else if ($_POST['postban'] === 'archive') {
      archive_thread($no);
      
      echo ('<br><strong>Thread archived.</strong>');
      
      $skip_rebuild = true;
    }
    else if ($_POST['postban'] === 'close') {
      if (mysql_board_call('UPDATE `%s` SET closed = 1 WHERE no = %d LIMIT 1', BOARD_DIR, $no)) {
        log_thread_opts_action($row, $row['sticky'], $row['permasage'], 1, $row['permaage'], $row['undead']);
        echo ('<br><strong>Thread closed.</strong>');
      }
      else {
        echo ('<br><strong>Could not close thread.</strong>');
      }
    }
    else if ($_POST['postban'] === 'permasage') {
      if (mysql_board_call('UPDATE `%s` SET permasage = 1 WHERE no = %d LIMIT 1', BOARD_DIR, $no)) {
        log_thread_opts_action($row, $row['sticky'], 1, $row['closed'], $row['permaage'], $row['undead']);
        echo ('<br><strong>Thread perma-saged.</strong>');
      }
      else {
        echo ('<br><strong>Could not perma-sage thread.</strong>');
      }
    }
	}
	
	echo '<script language="JavaScript">setTimeout("self.close()", 3000); postBack("done-ban-' . $board . '-' . $no . '");</script>';
	if( $banmsg && !$should_delete && !$skip_rebuild) { //need to update log because of the ban message
		rebuild_thread( ( $resto ) ? $resto : $no );
	}
	
  // Delete all posts by IP, including threads
  if ($_POST['postban'] === 'delall') {
    delallbyip($host, false);
  }
  // Delete only replies by IP
  else if ($_POST['postban'] === 'delallrep') {
    // Delete the thread if the target post is an OP
    if (!$resto) {
      delete_post($no, 0, $template ? $template['no'] : false, 'ban');
    }
    
    delallbyip($host, false, true);
  }
	
	//print "\n<br>total time: " . (microtime(1) - $start_time); //dont need to display this
} else {
	// Banning screen display
	$adminuser = mysql_real_escape_string( $_COOKIE[ '4chan_auser' ] );
	// see if user is banned
	$ban_summary = get_bans_summary($host);
	
	if( $ban_summary['total'] > 0 ) { // don't bother checking the active ban if there weren't ever any bans on this IP...
		if( !$banned = mysql_global_call( "SELECT host,board,global,zonly, DATE_FORMAT(length, 'Until %W, %M %D, %Y.') AS buntil FROM " . SQLLOGBAN . " WHERE host='" . $host . "' AND active=1" ) ) {
			echo S_SQLFAIL;
		}
		$bannedrows = mysql_num_rows( $banned );
		if( $bannedrows > 0 ) {
		  while ($ban_row = mysql_fetch_array($banned)) {
  			$buntil      = $ban_row[ 'buntil' ];
  			$gban        = $ban_row[ 'global' ];
  			$bannedboard = $ban_row[ 'board' ];
  			$bannedzonly = $ban_row[ 'zonly' ];
  			if( $bannedboard == BOARD_DIR ) {
  				$bg = "f0d0d0";
  				if( $buntil == "" ) $buntil = "Indefinitely.";
  				$bantrue = 1;
  			}
  			if( $gban == 1 ) {
  				$bg = "f0a0a0";
  				if( $buntil == "" ) $buntil = "Indefinitely.";
  				$globally = " (Globally)";
  				$bantrue  = 1;
  				break;
  			}
  			else {
  				$globally = " (" . $board . ")";
  			}
			}
		}
		if( $bantrue ) {
			echo "<style>body { background: #$bg; }</style>";
		}
	}
  
  $note = array();
  
  if ($poster_is_op) {
    $note[] = 'This poster is the OP';
    
    if ($resto == 0) {
      $_count = admin_get_thread_history($host);
      
      if ($_count > 1) {
        $note[0] .= ' <sup data-tip="Other threads made in the past hour">' . ($_count - 1) . '</sup>';
      }
    }
  }
  
  if ($row['4pass_id'] != '') {
    $has_4chan_pass = $row['4pass_id'];
    
    $note[] = 'This user is using a 4chan Pass';
    
    $ban_summary_pass = get_bans_summary($has_4chan_pass, true);
  }
  else {
    $has_4chan_pass = false;
    $ban_summary_pass = null;
  }
  
  if (!preg_match('/Android|iPhone|iPad/', $_SERVER['HTTP_USER_AGENT'])) {
    $autofocus_html = ' autofocus="autofocus"';
  }
  else {
    $autofocus_html = '';
  }
  
  if ($host) {
    $geoinfo = GeoIP2::get_country($host);
    $asninfo = GeoIP2::get_asn($host);
  }
  else {
  	$geoinfo = $asninfo = false;
  }
  
  if ($asninfo && isset($asninfo['aso'])) {
    $aso_formatted = ' (' . htmlspecialchars($asninfo['aso'], ENT_QUOTES) . ')';
  }
  else {
    $aso_formatted = '';
  }
  
  echo '<form action="" method="post">';
  echo csrf_tag();
	echo "<input type=\"hidden\" name=\"mode\" value=\"admin\">\n";
	echo "<input type=\"hidden\" name=\"admin\" value=\"ban\">\n";
	echo "<input type=\"hidden\" name=\"user\" value=\"$user\">\n";
	echo "<input type=\"hidden\" name=\"pass\" value=\"$pass\">\n";
	echo "<input type=\"hidden\" name=\"id\" value=\"$postid\">\n";
	echo '<input type="hidden" name="templateno" value="-1">' . "\n";
	echo "<table border=\"0\" cellspacing=\"0\" cellpadding=\"0\" class=\"bantable\">\n";
	echo "<tr><td class=\"postblock\">Autocomplete</td><td><input type=\"text\" name=\"autocomplete\" placeholder=\"Start typing...\" id=\"autocomplete\" size=\"40\"$autofocus_html autocomplete=\"off\" style=\"width: 100%;\"></td></tr>" . "\n";
	echo "<tr name=\"template_row\" style=\"visibility: hidden;\"><td style=\"height: 20px;\" class=\"postblock\">Template</td><td><select name=\"template\" style=\"width: 100%;\" onchange=\"chooseTemplate();\"></select></td></tr>\n";
	echo "<tr><td class=\"postblock\">Name</td><td><input type=\"text\" name=\"name\" value=\"$name\" size=\"40\" style=\"width: 100%;\" readonly=\"readonly\"></td></tr>\n";
  echo "<tr><td class=\"postblock\">IP</td><td><input type=\"text\" name=\"ip\" value=\"$host$aso_formatted\" size=\"40\" style=\"width: 100%;\" readonly=\"readonly\"></td></tr>\n";
  echo "<tr><td class=\"postblock\">Host</td><td><input type=\"text\" id=\"js-ip-rev\" name=\"reverse\" value=\"...\" size=\"40\" style=\"width: 100%;\" readonly=\"readonly\"></td></tr>\n";
  
  if ($geoinfo && isset($geoinfo['country_code'])) {
    $geo_loc = array();
    
    if (isset($geoinfo['city_name'])) {
      $geo_loc[] = $geoinfo['city_name'];
    }
    
    if (isset($geoinfo['state_code'])) {
      $geo_loc[] = $geoinfo['state_code'];
    }
    
    $geo_loc[] = $geoinfo['country_name'];
    
    $loc = htmlspecialchars(implode(', ', $geo_loc), ENT_QUOTES);
    
    echo '
      <tr>
        <td class="postblock">Location</td>
        <td><input type="text" value="' . $loc . '" readonly="readonly" style="width: 100%;"></td>
      </tr>
      ';
  }
  
  $ban_history_row = array();
  
  if ($ban_summary['total'] > 0) {
    $ban_history_row[] = get_ban_history_html($ban_summary, $host);
    
  }
	
  if ($ban_summary_pass['total'] > 0) {
    $ban_history_row[] = get_ban_history_html($ban_summary_pass);
  }
  
  if (!empty($ban_history_row)) {
    echo "<tr><td class=\"postblock\" style=\"height: 20px;\">Ban History</td><td style=\"padding-top: 4px; padding-bottom: 4px;\">" .
      implode(' ', $ban_history_row) . "</td></tr>\n";
  }
  
  // Browser ID
  $_post_meta = decode_user_meta($row['email']);
  
	$query = "SELECT warn_req, ban_templates.name FROM ban_requests LEFT JOIN ban_templates ON ban_template = ban_templates.no WHERE host='%s'";
	$result = mysql_global_call($query, $host);
	$brpending = array();
	while ($row = mysql_fetch_assoc($result)) {
	  $brpending[] = $row['name'] . ($row['warn_req'] ? ' [Warn]' : '');
  }
  $brtooltip = join("\n", $brpending);
	$pending = '';
  $brcount = count($brpending);
	if ($brcount > 0) {
		$plural = ($brcount > 1) ? 's' : '';
		$pending = <<<HTML
<tr>
	<td style="height: 20px;" class="postblock">Ban Requests</td>
	<td style="cursor:help;" title="$brtooltip">
		[$brcount pending ban request$plural]
	</td>
</tr>
HTML;
	}
	echo $pending;
	
	echo "<tr><td class=\"postblock\">Public Ban Reason</td><td><textarea disabled=\"disabled\" name=\"pubreason\" value=\"\" cols=\"30\" rows=\"3\" title=\"The banned user will see this message.\" style=\"width: 100%; margin-bottom: 0px !important;\"></textarea></td></tr>\n";
	
	echo "<tr><td style=\"height: 20px;\" class=\"postblock\">Private Info</td><td><input disabled=\"disabled\" type=\"text\" style=\"width: 100%;\" name=\"pvtreason\" value=\"\" size=\"40\" title=\"Optional extra information for 4chan moderators. This will show up on the ban list.\"></td></tr>\n";
	
	echo "<tr><td class=\"postblock\">Unban In</td><td><input id=\"ban-days\" disabled=\"disabled\" name=\"days\" type=\"number\" size=\"4\" min=\"0\" maxlength=\"4\" class=\"inputcenter\" /> days [<input type=\"checkbox\" name=\"warn\" value=\"1\">Warn] [<input type=\"checkbox\" name=\"indefinite\" value=\"1\">Perma]</tr>\n";
	
	echo "<tr id=\"more_file\"><td style=\"height: 20px;\" class=\"postblock\">More Info</td><td style=\"padding-top: 4px; padding-bottom: 4px;\">[<a href='javascript:more(\"more_info\",\"more_info\")'>View Info</a>] [<a target=\"_blank\" data-tip=\"Search posts by IP\" href=\"https://team.4chan.org/search#{&quot;ip&quot;:&quot;$host&quot;}\">Search</a>]" . ($_post_meta['is_mobile'] ? ' <span data-tip="Posted from a mobile device" class="ico-phone">&phone;</span>' : '') . "</td></tr>";

	if (!empty($note)) {
		$note = implode('<br>', $note);
		
		echo "<tr><td style=\"height: 20px;\" class=\"postblock\">Note</td><td><strong>$note</strong></td></tr>";
	}

	if (has_level('manager')/* || has_flag('developer')*/) {
	  $toz = ' [<input type="checkbox" name="zonly" value="1">Unappealable]';
  }
  else {
    $toz = '';
  }
  
  if (has_level('manager') || has_flag('banmsg')) {
    $ban_msg_row = "<tr style=\"display:none\" id=\"pub-ban-msg\"><td style=\"height: 20px;\" class=\"postblock\">Message</td><td><input name=\"custombanmsg\" placeholder=\"USER WAS BANNED FOR THIS POST\" type=\"text\" style=\"width: 100%;\" title=\"Custom public ban message\">";
    
    $ban_msg_row .= <<<JS
<script type="text/javascript">
  function toggleBanMsg(cb) {
    var el = document.getElementById('pub-ban-msg');
    if (!el) { return; }
    if (cb.checked) {
      el.style.display = '';
    }
    else {
      el.style.display = 'none';
    }
  }
</script></td></tr>
JS;
    
    $pub_ban_evt = ' onchange="toggleBanMsg(this)"';
  }
  else {
    $ban_msg_row = $pub_ban_evt = '';
  }
	
  if ($resto == 0 && ENABLE_ARCHIVE) {
    $_opt_archive = '<option value="archive">Archive</option>';
  }
  else {
    $_opt_archive = '';
  }
  
  if (!$host) {
  	$btn_disabled = ' disabled';
  }
  else {
  	$btn_disabled = '';
  }
  
	echo "<tr><td style=\"height: 20px;\" class=\"postblock\">Ban Scope</td><td><input$btn_disabled id=\"submit-ban-btn\" type=\"submit\" name=\"submit\" value=\"Submit\"><select name=\"bantype\"><option value=\"local\">Ban from /$board/</option><option value=\"global\">Global ban</option></select><div>[<span title=\"Display (USER WAS BANNED FOR THIS POST) message.\"><input type=\"checkbox\"$pub_ban_evt name=\"banmsg\" value=\"1\">Public Ban</span>]$toz</div></td></tr>$ban_msg_row";
  
  echo "<tr><td style=\"height: 20px;\" class=\"postblock\">Post-Ban</td><td><select id=\"js-postban-sel\" name=\"postban\"><option value=\"\">Nothing</option><option value=\"delpost\">Delete post</option><option value=\"delfile\">Delete file only</option>$_opt_archive<option value=\"delallrep\" style=\"color:red\">Delete all replies by IP</option><option value=\"delall\" style=\"font-weight:bold;color:red\">Delete all posts by IP</option>";
  
  if ($resto == 0) {
    echo "<option value=\"close\">Close</option><option value=\"permasage\">Perma-sage</option>";
    
    $board_sel = get_board_options_html();
    echo "<option value=\"move\">Move</option>";
    echo "</select><select id=\"js-move-board-sel\" name=\"move-board\">$board_sel</select></td></tr>";
  }
  else {
    echo "</select></td></tr>";
  }
 	
 	$can_thread_ban = false;
 	
	if ($resto == 0 && (has_level('manager') || has_flag('threadban'))) {
		echo "<tr><td style=\"height: 20px;\" class=\"postblock\">Ban Thread</td><td><input$btn_disabled type=\"button\" value=\"Ban Entire Thread\" style=\"margin-left: 0px;\" id=\"js-tb-btn\"></td></tr>";
		$can_thread_ban = true;
	}
	
	echo "</table>\n";
	echo "</form>";
	
  // Async reverse IP request
  if ($host) {
    $_rev_long_ip = ip2long($host);
    $_rev_ts = $_SERVER['REQUEST_TIME'];
    $_rev_sig = admin_get_rev_ip_sig($_rev_long_ip, $_rev_ts);
  ?>
    <script type="text/javascript">
      async function admin_rev_ip() {
        let el = document.getElementById('js-ip-rev');
        if (!el) { return; }
        let ip = <?php echo $_rev_long_ip ?>;
        let t = <?php echo $_rev_ts ?>;
        let s = '<?php echo $_rev_sig ?>';
        const resp = await fetch(`?admin=rev&ip=${ip}&t=${t}&s=${s}`);
        if (resp.ok) {
          const json = await resp.json();
          el.value = json.rev;
        }
      }
      admin_rev_ip();
    </script>
  <?php }
  
	if ($can_thread_ban) { ?>
		<div id="thread-ban-layer" style="position: absolute; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0, 0, 0, 0.6); display: none;">
			<div style="position:absolute; left: 50%; top: 50%; padding: 6px; margin-left: -185px; margin-top: -52px;" class="post reply preview">
				<form action="" method="POST">
					<?php echo csrf_tag(); ?>
					<input type="hidden" name="mode" value="admin">
					<input type="hidden" name="admin" value="cpban">
					<input type="hidden" name="no" value="<?php echo $no ?>">
					<table class="bantable bantable-extra bantable-tb">
						<tr><td data-tip="Public reason for the OP" class="postblock">OP Reason</td><td><input type="text" name="op_reason" value="Posting off-topic threads."></td></tr>
						<tr><td data-tip="Ban length in days for the OP" class="postblock">OP Ban Length</td><td><input type="text" autocomplete="off" name="op_days" value="3"></td></tr>
						<tr><td data-tip="Public reason for replies" class="postblock">Rep. Reason</td><td><input type="text" name="rep_reason" value="Replying to off-topic threads."></td></tr>
						<tr><td data-tip="Ban length in days for replies" class="postblock">Rep. Ban Length</td><td><input type="text" autocomplete="off" name="rep_days" value="0"></td></tr>
						<tr><td></td><td style="text-align: right; padding-top: 8px"><button type="submit">Ban</button><button type="button" style="margin-left: 20px" id="js-tb-cancel">Cancel</button></td></tr>
					</table>
				</form>
			</div>
		</div>
		<script type="text/javascript">
			document.getElementById('js-tb-btn').addEventListener('click', toggleThreadBanPanel, false);
			document.getElementById('js-tb-cancel').addEventListener('click', toggleThreadBanPanel, false);
			
			function toggleThreadBanPanel(e) {
				let el = document.getElementById('thread-ban-layer');
				
				if (el.style.display === 'none') {
					el.style.display = 'block';
				}
				else {
					el.style.display = 'none';
				}
			}
		</script>
	<?php
	}
	
	$html = <<<HTML
<script type="text/javascript">
	var el;
	
	function submitRequest(e) {
		var select, index;
		
		select = document.forms[0].template;
		index = select.selectedIndex;
		
		if (index === 0) {
			e.preventDefault();
			e.stopPropagation();
			alert("You forgot to select a template.");
		}
		else {
			if (/ Child |\[Perm\]/.test(select.options[index].textContent)) {
				if (!checkSubmitConfirm(this)) {
					e.preventDefault();
					e.stopPropagation();
					return;
				}
			}
			postBack("start-ban-$board-$no");
		}
	}
	
	if (el = document.getElementById("submit-ban-btn")) {
		el.addEventListener("click", submitRequest, false);
	}
</script>
HTML;
	
	echo $html;
	
	$is_manager = has_level('manager') || has_flag('developer');

	echo ban_template_js($post_has_file, $resto == 0);

	echo "<div id=\"more_info\" style=\"position: absolute; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0, 0, 0, 0.6); display: none;\"><div style=\"position:absolute; left: 50%; top: 50%; padding: 6px; margin-left: -185px; margin-top: -52px;\" class=\"post reply preview\"><table class=\"bantable bantable-extra\">";

  if ($has_4chan_pass && has_level('mod')) {
    if ($is_manager) {
      echo "<tr class=\"more_pass\"><td class=\"postblock\">4chan Pass</td><td><input type=\"text\" value=\"$has_4chan_pass\" readonly=\"readonly\"></td></tr>";
    }
    else {
      $hashed_4chan_pass = admin_hash_4chan_pass($has_4chan_pass);
      echo "<tr class=\"more_pass\"><td class=\"postblock\"><span data-tip=\"Hashed 4chan Pass\">Hashed Pass</span></td><td><input type=\"text\" value=\"$hashed_4chan_pass\" readonly=\"readonly\"></td></tr>";
    }
  }

  if ($md5) {
    echo "<tr class=\"more_file\"><td class=\"postblock\">MD5</td><td><input type=\"text\" name=\"md5_disp\" value=\"$md5\" size=\"34\" readonly=\"readonly\"></td></tr>";
    echo "<tr class=\"more_file\"><td class=\"postblock\">Filename</td><td><input type=\"text\" name=\"md5_disp\" value=\"$filename\" size=\"34\" readonly=\"readonly\"></td></tr>";
    
    echo "<tr><td data-tip=\"Perceptual hash\" class=\"postblock\">PHash</td><td><input type=\"text\" value=\"{$tmd5}\" size=\"34\" readonly=\"readonly\"></td></tr>";
  }
  
	echo "<tr id=\"more_pwd\"><td class=\"postblock\">Password</td><td><input type=\"text\" name=\"pwd_disp\" value=\"$pwd\" size=\"34\" readonly=\"readonly\"></td></tr>";
	
  if ($host && admin_is_ip_rangebanned($host)) {
    echo "<tr><td class=\"postblock\">Rangeban</td><td><input type=\"text\" name=\"rangeban_info\" value=\"Yes\" size=\"34\" readonly=\"readonly\"></td></tr>";
  }
  
  if ($_post_meta['req_sig']) {
    echo "<tr><td data-tip=\"Signature of the HTTP request\" class=\"postblock\">Req. Sig.</td><td><input type=\"text\" value=\"{$_post_meta['req_sig']}\" size=\"34\" readonly=\"readonly\"></td></tr>";
  }
  
  if ($_post_meta['browser_id']) {
    echo "<tr><td class=\"postblock\">Browser ID</td><td><input type=\"text\" value=\"{$_post_meta['browser_id']}\" size=\"34\" readonly=\"readonly\"></td></tr>";
  }
  
  $_user_status = user_known_status_to_str($_post_meta['known_status']);
  
  if ($_post_meta['verified_level']) {
    $_user_status .= ', Verified';
  }
  
  if ($_user_status) {
    echo "<tr><td class=\"postblock\">User Status</td><td><input type=\"text\" value=\"$_user_status\" size=\"34\" readonly=\"readonly\"></td></tr>";
  }
  
	echo "<tr><td></td><td>[<a href='javascript:more(\"more_info\",\"more_info\")'>Close</a>]</td></tr>";
	echo "</table></div></div>";

	die( "</body></html>" );
}
}

function adminToggleSpoiler($post, $new_spoiler) {
  if (strpos($post['sub'], 'SPOILER<>') === 0) {
    $old_subject = substr($post['sub'], 9);
    $old_spoiler = true;
  }
  else {
    $old_subject = $post['sub'];
    $old_spoiler = false;
  }
  
  if ($old_spoiler == $new_spoiler) {
    return false;
  }
  
  if ($new_spoiler) {
    $subject = 'SPOILER<>' . $old_subject;
    $actionType = 1;
  }
  else {
    $subject = $old_subject;
    $actionType = 2;
  }
  
  $query = "UPDATE " . BOARD_DIR . " SET sub = '%s' WHERE no = %d LIMIT 1";
  $res = mysql_board_call($query, $subject, $post['no']);
  
  if (!$res) {
    die('Database error (ats).');
  }
  
  $maskShift = 128;
  $actionId = $maskShift + $actionType;
  
  $query =<<<SQL
INSERT INTO actions_log (oldmask, newmask, postno, board, name, sub, com, filename, admin)
VALUES (0, %d, %d, '%s', '%s', '%s', '%s', '%s', '%s')
SQL;
  
  mysql_global_call($query,
    $actionId,
    $post['no'],
    BOARD_DIR,
    $post['name'],
    $post['sub'],
    $post['com'],
    $post['filename'] . $post['ext'],
    $_COOKIE['4chan_auser']
  );
  
  return true;
}

function adminopt()
{
	global $id, $user, $pass;
	$submit         = $_POST[ 'submit' ];
	$post_sticky    = intval( $_POST[ 'sticky' ] );
	$post_sticknum  = intval( $_POST[ 'sticknum' ] );
	$post_permaage  = intval( $_POST[ 'permaage' ] );
	$post_undead    = intval( $_POST[ 'undead' ] );
	$post_permasage = intval( $_POST[ 'permasage' ] );
	$post_closed    = intval( $_POST[ 'closed' ] );
	$post_id        = (int)$id;

	$adminuser = mysql_real_escape_string( $_COOKIE[ '4chan_auser' ] );

	$is_managerplus = has_level( 'manager' ) || has_flag('developer');

	if( !$result = mysql_board_call( "SELECT * FROM `" . SQLLOG . "` WHERE archived = 0 AND no=" . intval( $id ) ) ) {
		echo S_SQLFAIL;
	}
	
	if (!mysql_num_rows($result)) {
	  die("Thread not found.");
	}

	$row = mysql_fetch_assoc( $result );
	
	if (!$row) {
	  die('Datbase error.');
	}
	
	extract( $row, EXTR_OVERWRITE );

	if( !$is_managerplus ) $post_permaage = $permaage; // force setting to old value to stop forgery
	//if( !$is_managerplus ) $post_undead = $undead; // force setting to old value to stop forgery

	if( $resto != 0 ) die();

	if( $submit != "" ) {		
		if( $post_sticky == 1 && ( $post_sticknum < 0 || $post_sticknum > 60 ) ) {
			echo "Sticky number must be between 0 and 59. Higher numbers appear above lower numbers.<br>";
			die( "[<a href=\"javascript:void(0)\" onclick=\"history.back()\">Back</a>]</body></html>" );
		}
		else {
			if( strlen( $post_sticknum ) == 1 ) $post_sticknum = "0" . $post_sticknum;
			$post_sticknum = "202701010000" . $post_sticknum;
		}
		
		echo "<script language=\"JavaScript\">setTimeout(\"self.close()\", 3000); postBack('done-threadopt');</script>";
		$vars = "";
		echo "Thread flag status:<ul>";
		if( $post_sticky == 1 ) {
			echo "<li>Sticky &check;</li>";
			$vars .= "sticky=1,root=" . $post_sticknum . ",";
		}
		else {
			if( $sticky == 1 ) {
				$sticktime = "now()";
			}
			else {
				$sticktime = "root";
			}
			
			echo '<li>Sticky &cross;</li>';
			$vars .= "sticky=0,root=" . $sticktime . ",";
		}
		if( $post_permasage == 1 ) {
			echo "<li>Perma-sage &check;</li>";
			$vars .= "permasage=1,";
		}
		else {
			echo '<li>Perma-sage &cross;</li>';
			$vars .= "permasage=0,";
		}
		if( $post_closed == 1 ) {
			echo "<li>Closed &check;</li>";
			$vars .= "closed=1,";
		}
		else {
			echo '<li>Closed &cross;</li>';
			$vars .= "closed=0,";
		}
		if( $post_permaage ) {
			if( $is_managerplus ) {
			  echo '<li>Perma-age &check;</li>';
			  $vars .= "permaage=1,";
		  }
		}
		else {
			if( $is_managerplus ) {
			  echo '<li>Perma-age &cross;</li>';
			  $vars .= "permaage=0,";
		  }
		}
		if( $post_undead ) {
			//if( $is_managerplus ) {
			  echo '<li>Undead &check;</li>';
			  $vars .= "undead=1,";
		  //}
		}
		else {
			//if( $is_managerplus ) {
			  echo '<li>Undead &cross;</li>';
			  $vars .= "undead=0,";
		  //}
		}
		
		// Clear the undead flag when a moderator modifies the sticky flag
		// so the thread doesn't turn into a rolling sticky or get stuck as undead
    /*
		if ($undead && !$is_managerplus && $post_sticky != $sticky) {
		  echo '<li>Undead &cross;</li>';
		  $vars .= "undead=0,";
		}
		*/
		$vars .= "last_modified=".$_SERVER['REQUEST_TIME']; // FIXME consider checking if we only change hidden vars and don't update this
		
		echo '</ul><script language="JavaScript">setTimeout("self.close()", 3000); postBack("done-threadopt");</script>';
		
		if( !$result = mysql_board_call( "UPDATE `" . SQLLOG . "` SET %s WHERE no=%d", $vars, $post_id ) ) {
			echo S_SQLFAIL;
		}
		
    log_thread_opts_action($row, $post_sticky, $post_permasage, $post_closed, $post_permaage, $post_undead);
		
		if( $post_sticky != $sticky || $post_closed != $closed) rebuild_thread( $post_id );
	}
	else {
	  echo '<form action="" method="post">';
    echo csrf_tag();
		echo "<input type=\"hidden\" name=\"mode\" value=\"admin\"><input type=\"hidden\" name=\"admin\" value=\"opt\"><input type=\"hidden\" name=\"user\" value=\"$user\"><input type=\"hidden\" name=\"pass\" value=\"$pass\"><input type=\"hidden\" name=\"id\" value=\"$post_id\">\n";
		echo "<table class=\"goawayborder\" style=\"width: 100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\">\n";

		if( BOARD_DIR != 'b' || $GLOBALS[ 'b_sticky' ] ) {
			echo "<tr><td class=\"postblock\" style=\"height: 20px; width: 80px;\"><u>S</u>ticky</td><td><input type=checkbox name=\"sticky\" id=\"js-sticky-cb\" value=\"1\"";
			if( $sticky == 1 ) {
				echo ' checked data-cur="1"';
				$sticknum = substr( $root, -2 );
				if( $sticknum{0} == "0" ) $sticknum = substr( $sticknum, -1 );
			}
			else {
				echo ' data-cur="0"';
				$sticknum = "0";
			}
			echo ">&nbsp;&nbsp;&nbsp;&nbsp;<input type=\"text\" name=\"sticknum\" value=\"$sticknum\" size=\"2\" maxlength=\"2\" class=\"inputcenter\" style=\"height: 20px; width: 40px;\"> (Order: 0-59)</td></tr>\n";
		}
		echo "<tr><td class=\"postblock\" style=\"height: 20px; width: 80px;\"><u>P</u>erma-sage</td><td><input type=\"checkbox\" name=\"permasage\" value=\"1\"";
		if( $permasage == 1 ) echo " CHECKED";
		echo "></td></tr>\n";
		echo "<tr><td class=\"postblock\" style=\"height: 20px; width: 80px;\"><u>C</u>losed</td><td><input type=\"checkbox\" name=\"closed\" value=\"1\"";
		if( $closed == 1 ) echo " CHECKED";
		echo "></td></tr>\n";

    if ($is_managerplus) {
  		echo "<tr><td class=\"postblock\" style=\"height: 20px; width: 80px;\">P<u>e</u>rma-age</td><td><input type=\"checkbox\" name=\"permaage\" value=\"1\"";
  		if( $permaage == 1 ) echo 'checked="checked"';
  		echo "></td></tr>\n";
		}
    
    //if ($is_managerplus) {
  		echo "<tr><td class=\"postblock\" style=\"height: 20px; width: 80px;\"><u>U</u>ndead</td><td><input type=\"checkbox\" name=\"undead\" id=\"js-undead-cb\" value=\"1\"";
  		if( $undead == 1 ) echo 'checked="checked"';
  		echo "></td></tr>\n";
    //}
    
  	echo "<tr><td></td><td><input style=\"width:100px;margin-top: -25px;position: absolute;right: 5px;\" id=\"js-set-btn\" type=\"submit\" name=\"submit\" value=\"Set Options\"></td></tr>\n";
    
		echo "</table>\n";
		echo "</form>";
		
		
		/**
		 * Thread moving form
		 */
		if (BOARD_DIR !== 'b' && !UPLOAD_BOARD && !JANITOR_BOARD) {
		  echo move_thread_form($post_id);
		}
		
    if (BOARD_DIR === 'test') {
      echo admin_protect_thread_form($post_id);
    }
    
		die( "</body></html>" );
	}
}

function log_thread_opts_action($post_data, $sticky, $permasage, $closed, $permaage, $undead) {
  if (!isset($post_data['no']) || !$post_data['no']) {
    die('Internal Server Error (ltoa)');
  }
  
  $new_mask = 0 + (($sticky) ? 1 : 0)
                + (($permasage) ? 2 : 0)
                + (($closed) ? 4 : 0)
                + (($permaage) ? 8 : 0)
                + ($undead ? 16 : 0);
  
  $old_mask = 0 + (($post_data['sticky']) ? 1 : 0)
                + (($post_data['permasage']) ? 2 : 0)
                + (($post_data['closed']) ? 4 : 0)
                + (($post_data['permaage']) ? 8 : 0)
                + ($post_data['undead'] ? 16 : 0);

  if ($new_mask == $old_mask) {
    return false;
  }
  
  $query = <<<SQL
INSERT INTO actions_log (oldmask,newmask,postno,board,name,sub,com,filename,admin)
VALUES (%d, %d, %d, '%s', '%s', '%s', '%s', '%s', '%s')
SQL;

  return !!mysql_global_call($query,
    $old_mask,
    $new_mask,
    $post_data['no'],
    BOARD_DIR,
    $post_data['name'],
    $post_data['sub'],
    $post_data['com'],
    $post_data['filename'] . $post_data['ext'],
    $_COOKIE['4chan_auser']
  );
}

function get_board_options_html() {
  $boardlist = get_board_list();
  
  $board_sel = array('<option value=""> Board</option>');
  
  foreach ($boardlist as $b_dir => $b_title) {
    if ($b_dir === BOARD_DIR || $b_dir === 'f') {
      continue;
    }
    $board_sel[] = '<option value="' . $b_dir . '">' . $b_dir . ' - '
      . $b_title . '</option>';
  }
  
  return implode("\n", $board_sel);
}

function move_thread_form($post_id) {
    $csrf_tag = csrf_tag();
    
    $board_sel = get_board_options_html();
    
    if (!ENABLE_ARCHIVE) {
      $del_attrs = ' checked';
    }
    else {
      $del_attrs = '';
    }
    
    return <<<HTML
<hr>
<form id="move-form" action="post" method="POST">
$csrf_tag
<input type="hidden" name="id" value="$post_id">
<table class="goawayborder" style="width: 100%" border="0" cellspacing="0" cellpadding="0">
<tr>
  <td class="postblock" style="height: 20px; width: 80px;">Move to</td>
  <td><select name="board" required>$board_sel</select></td>
</tr>
<tr>
  <td class="postblock" style="height: 20px; width: 80px;"><label for="move_del">and delete</label></td>
  <td><input$del_attrs id="move_del" name="move_del" type="checkbox"></td>
</tr>
<tr>
  <td></td>
  <td>
    <button id="js-move-btn" style="margin-top: -25px;position: absolute;right: 5px;" type="submit" name="mode" value="movethread">Move</button>
  </td>
</tr>
</table>
</form>
HTML;
}

function admin_protect_thread_form($thread_id) {
    return <<<HTML
<hr>
<form id="protect-form" action="?" method="GET">
<input type="hidden" name="thread_id" value="$thread_id">
<button type="submit" name="admin" value="protectthread">Set Protected</button>
</form>
HTML;
}

function adminExt()
{
	global $thread;
	$where = '';

	if( isset( $_GET[ 'from' ] ) && ctype_digit( $_GET[ 'from' ] ) ) {
		$from  = intval( $_GET[ 'from' ] );
		$where = " AND no >= $from";
	}

	if( !$thread ) return false;

	$thread = (int)$thread;
	if( !$result = mysql_board_call( "SELECT `host`, `no` FROM `%s` WHERE (no=%d OR resto=%d)$where", SQLLOG, $thread, $thread ) ) {
		echo S_SQLFAIL;

		return false;
	}
	$json = array();
	
	$salt = file_get_contents('/www/keys/2014_admin.salt');
	
	if (!$salt) {
	  die('Internal Server Error');
	}
	
	while ($row = mysql_fetch_assoc($result)) {
    $hash = substr(base64_encode(pack( "H*", sha1($row['host'] . $salt))), 0, 8);
    
		$json[$row['no']] = $hash;
	}

	echo json_encode( $json, JSON_NUMERIC_CHECK );

	die();
}

function adminBanReq()
{
	$no      = (int)$_GET['id'];
	$board   = BOARD_DIR;
	$janitor = $_COOKIE['4chan_auser'];
	
	if ($board === 'j') {
	  die();
	}

	$result = mysql_board_call( "SELECT * FROM `%s` WHERE no=%d", $board, $no );
	
	if (!mysql_num_rows($result)) {
		echo '<script language="JavaScript">postBack("error-ban-' . $board . '-' . $no . '");</script>';
		error("This post doesn't exist anymore");
	}
	
	$post = mysql_fetch_assoc($result);
	
	if ($post['archived']) {
		echo '<script language="JavaScript">postBack("error-ban-' . $board . '-' . $no . '");</script>';
		error("This post is archived");
	}
	
  if ($post['host'] === '') {
    error('You cannot request a ban for this post.');
  }
	
	$post_has_file = $post['ext'] && !$post['file_deleted'];
	
  if (!access_board(BOARD_DIR)) {
    // Check if the report is unlocked, weight threshold is 1500
    $query = <<<SQL
SELECT CEIL(SUM(IF(resto > 0, weight, weight * 1.25))) as total_weight
FROM reports
WHERE board = '%s' AND no = %d
SQL;
    
    $result = mysql_global_call($query, $board, $no);
    
    if (!$result) {
      error('Database Error (abru1');
    }
    
    $total_weight = (int)mysql_fetch_row($result)[0];
    
    if (!$total_weight || $total_weight < 1500) {
      error('You do not have permission to access this board.');
    }
  }
	
  // for async calls from reports.4chan.org
  if (isset($_POST['by_tpl']) && $_POST['by_tpl']) {
    $_POST['template'] = $_POST['by_tpl'];
    unset($_POST['warn_req']);
  }
  
	if( $_POST[ 'template' ] ) {
		$template = (int)$_POST['template'];
		
		if ($template < 1) {
			error('You forgot to select a template.');
		}
		
		$bquery = mysql_global_call( "SELECT * FROM ban_templates WHERE no=%d", $template );
		$bres   = mysql_fetch_assoc( $bquery );
		
    if (!has_level($bres['level'])) {
      error('You cannot use this template');
    }
		
    if (($bres['no'] == 1 || $bres['no'] == 123 || $bres['no'] == 213) && !$post_has_file) {
      error('This template requires a post with a file');
    }
    
		$reason = $bres['publicreason'];
		
		$xffquery = mysql_global_call( "SELECT xff FROM xff WHERE board = '%s' AND postno = %d", $board, $no );
		$reverse  = gethostbyaddr( $post['host'] );
		
		if( $xffresult = mysql_fetch_row( $xffquery ) ) {
			if( !( $xff = gethostbyaddr( $xffresult[0] ) ) ) $xff = $xffresult[0];
		}
		
		if (isset($_POST['warn_req']) && $_POST['warn_req']) {
		  if (!$bres['can_warn']) {
		    error('You cannot issue warn requests using this template.');
		  }
		  $warn_req = 1;
		}
		else if ($bres['days'] === '0') {
		  $warn_req = 1;
	  }
		else {
		  $warn_req = 0;
		}
		
    // Fixme: for the cache purger below
    if ($post['ext'] != '') {
      $post_filename = "{$post['tim']}{$post['ext']}";
    }
    else {
      $post_filename = null;
    }
    
		// Make sure we don't have any illegal reports (stop illegal images being stored)
		$illegal = mysql_global_call( "SELECT COUNT(*) FROM reports WHERE board='%s' AND no=%d AND cat=2", $board, $_POST['no'] );
		if ((mysql_result($illegal, 0, 0) == 0) && $bres['save_post'] === 'everything') {
			$salt = file_get_contents( SALTFILE );
			$hash = sha1($board . $post['no'] . $salt);
			
			@copy(
				IMG_DIR . "{$post['tim']}{$post['ext']}",
				BANIMG_ROOT . "$board/$hash{$post['ext']}"
			);
			
			@copy(
				THUMB_DIR . "{$post['tim']}s.jpg",
				BANTHUMB_DIR . "{$hash}s.jpg"
			);
		}
		else {
			//unset($post['ext']);
			$post['raw_md5'] = $post['md5'];
		}
		
		if ($post['resto']) {
		  $sub_query = mysql_board_call("SELECT sub FROM `%s` WHERE no = %d", $board, $post['resto']);
		  $sub_res = mysql_fetch_assoc($sub_query);
		  if ($sub_res) {
		    $rel_sub = $sub_res['sub'];
		    
        if (strpos($rel_sub, 'SPOILER<>') === 0) {
          $rel_sub = substr($rel_sub, 9);
        }
        
        if ($rel_sub !== '') {
          $post['rel_sub'] = $rel_sub;
        }
		  }
		}
		
    $tpl_name = $bres['name'];
    $tpl_global = $bres['bantype'] !== 'local' ? 1 : 0;
    
		delete_post($no, false, $template, 'ban-req');
		
		$res = mysql_global_call("INSERT INTO ban_requests SET host='%s', reverse='%s', pwd='%s', xff='%s', reason='', global = $tpl_global, tpl_name = '%s', ban_template='%s', board='%s', janitor='%s', spost='%s', post_json='%s', warn_req = %d", $post['host'], $reverse, $post['pwd'], $xff, $tpl_name, $template, $board, $janitor, serialize( $post ), json_for_post($board, $post), $warn_req);
		
		if (!$res) {
			error('Database error.');
		}
    
    // Auto-rangebans processing (ban requests)
    // FIXME: email field
    $_post_meta = decode_user_meta($row['email']);
    
    if (!$warn_req && $_post_meta && $_post_meta['is_mobile']) { // mobile devices only
      // global rules only
      if ($bres && strpos($bres['rule'], 'global') !== false) {
        process_auto_rangeban($post['host'], $_post_meta['browser_id'], $post['resto'], $post['no'], $bres['no'], 0);
      }
    }
    
    // Fixme, this is for the temporary is2/is3 cache purging api
    if ($post_filename && $bres && $bres['rule'] == 'global1') {
      purge_cache_internal_temp(BOARD_DIR, $post_filename);
    }
		echo '<script language="JavaScript">setTimeout("self.close()", 3000); postBack("done-ban-' . $board . '-' . $no . '");</script>';
		die( ($warn_req ? 'Warn' : 'Ban') . ' request submitted! Window will now close...' );
	}
  
	$name = str_replace( '</span> <span class="postertrip">!', ' !', $post[ 'name' ] );

	$query = "SELECT warn_req, ban_templates.name FROM ban_requests LEFT JOIN ban_templates ON ban_template = ban_templates.no WHERE host='%s'";
	$result = mysql_global_call($query, $post['host']);
	$brpending = array();
	while ($row = mysql_fetch_assoc($result)) {
	  $brpending[] = $row['name'] . ($row['warn_req'] ? ' [Warn]' : '');
  }
  $brtooltip = join("\n", $brpending);
	$pending = '';
  $brcount = count($brpending);
	if ($brcount > 0) {
		$plural = ($brcount > 1) ? 's' : '';
		$pending = <<<HTML
<tr>
	<td style="height: 20px;" class="postblock">Note</td>
	<td colspan="2" style="cursor:help;" title="$brtooltip">
		[$brcount pending ban request$plural]
	</td>
</tr>
HTML;
	}
  
	$csrf_tag = csrf_tag();
	
  if (!preg_match('/Android|iPhone|iPad/', $_SERVER['HTTP_USER_AGENT'])) {
    $autofocus_html = ' autofocus="autofocus"';
  }
  else {
    $autofocus_html = '';
  }
  
	$html = <<<HTML
<form action="" method="post">$csrf_tag
<table border="0" cellspacing="0" cellpadding="0" class="bantable">
<tr>
	<td class="postblock">Autocomplete</td>
	<td colspan="2">
		<input type="text" name="autocomplete" placeholder="Start typing..." id="autocomplete" size="40"$autofocus_html autocomplete="off" style="width: 100%;">
	</td>
</tr>

<tr name="template_row" style="visibility: hidden;">
	<td style="height: 20px;" class="postblock">Template</td>
	<td colspan="2">
		<select name="template" style="width: 100%;" onchange="chooseTemplate();"></select>
	</td>
</tr>

$pending

<tr>
	<td class="postblock">Name</td>
	<td colspan="2">
		<input type="text" name="name" value="$name" size="40" style="width: 100%;" readonly="readonly">
	</td>
</tr>

<tr>
	<td class="postblock">Reason</td>
	<td colspan="2">
		<textarea id="reason" name="reason" value="" cols="30" rows="4" title="The banned user will see this message." style="width: 100%; margin-bottom: 0px !important;" readonly="readonly"></textarea>
	</td>
</tr>

<tr>
	<td class="postblock">Warn?</td>
	<td colspan="2">
		<input id="warn-req" type="checkbox" name="warn_req" value="1" title="Request the user be warned instead of banned.">
	</td>
</tr>

<tr>
	<td  class="postblock">Requested By</td>
	<td>
		<input style=" width: 100%;" type="text" name="bannedby" value="$janitor" readonly="readonly">
	</td>
	<td align="right">
		<input id="submit-br-btn" type="submit" value="Submit Request" style="margin: -1px">
		<script type="text/javascript">
			var el;
			
			function submitRequest(e) {
				var select, index;
				
				select = document.forms[0].template;
				index = select.selectedIndex;
				
				if (index === 0) {
					e.preventDefault();
					e.stopPropagation();
					alert("You forgot to select a template.");
				}
				else {
					if (/ Child |\[Perm\]/.test(select.options[index].textContent)) {
						if (!checkSubmitConfirm(this)) {
							e.preventDefault();
							e.stopPropagation();
							return;
						}
					}
					postBack("start-ban-$board-$no");
				}
			}
			
			if (el = document.getElementById("submit-br-btn")) {
				el.addEventListener("click", submitRequest, false);
			}
		</script>
	</td>
</tr>
</table>
</form>
HTML;

	echo $html;
	
  $templates = array();
  $level_map = get_level_map();
  
	$q = mysql_global_do("SELECT * FROM ban_templates ORDER BY length(rule), rule asc");
  
	while( $r = mysql_fetch_assoc( $q ) ) {
    if (!preg_match('#^(global|' . BOARD_DIR . ')[0-9]+$#', $r['rule'])) {
      continue;
    }
    
    if (($r['no'] == 1 || $r['no'] == 123 || $r['no'] == 213) && !$post_has_file) {
      continue;
    }
    
    if ($r['no'] == 6 && !DEFAULT_BURICHAN) { // NWS on Worksafe Board
      continue;
    }
    
    if ($r['no'] == 17 && (BOARD_DIR === 'mlp' || BOARD_DIR === 'trash')) { // Pony/Ponies Outside of /mlp/
      continue;
    }
    
    if ($r['no'] == 222 && (BOARD_DIR === 's4s' || BOARD_DIR === 'bant')) { // Global 3 - Troll posts
      continue;
    }
    
    if ($r['no'] == 223 && BOARD_DIR === 'pol') { // Global 3 - Racism
      continue;
    }
    
    // Global 3
    if ((BOARD_DIR === 'b' || BOARD_DIR === 'bant') && strpos($r['rule'], 'global3') !== false) {
      continue;
    }
    
    if ($r['no'] == 59 && $post['resto']) { // Request Thread Outside of /r/
      continue;
    }
    
	  if ($level_map[$r['level']] !== true) {
	    continue;
	  }
	  
		unset($r[ 'special_action' ], $r[ 'blacklist' ], $r[ 'bantype' ], $r[ 'postban' ], $r[ 'privatereason' ]);
		
		$templates[] = $r;
	}
	
	$encTemp = json_encode( $templates );

	$v = <<<HTML
<script type="text/javascript" src="//s.4cdn.org/js/admin_autocomplete.9.js"></script>
<script type="text/javascript">
var e_template = document.getElementsByName("template")[0];
	var globalTemplates = $encTemp;
	var templates = {};
	var localTemplates = {};

	function $(e) {return document.getElementsByName(e)[0];}
	function unhide(e) {
		$(e).style.visibility="visible";
	};

	function chooseTemplate() {
		var i = e_template.selectedIndex - 1;
    
    Feedback.checkTemplate(i);
    
		if (i < 0) {
			return;
		}

		var t = templates[i];
		document.getElementById('reason').innerHTML = t.publicreason;
		
		document.getElementById('warn-req').disabled = t.can_warn == '0';
		document.getElementById('warn-req').checked = (t.banlen == '' && t.days == 0);
		
		//undisableForm(t);
	}

	function undisableForm(t) {
		if( t.name != 'Other...' ) return;
		document.getElementById('reason').removeAttribute('disabled');
	}

	function initTemplate() {
		templates = globalTemplates;

		if (localStorage) {
			var lt = JSON.parse(localStorage.getItem("ban_templates"));
			if (lt) {
				localTemplates = lt;
				for (var t in localTemplates)
					templates = templates.concat(localTemplates[t]);
			}
		}

		e_template.innerHTML = '<option value="-1">None Selected (Required)</option>';

		for (var i=0;i<templates.length;i++) {
			var t = templates[i];
			//if( !t.name.match(/Global/) && !t.name.match(/\/' . BOARD_DIR . '\//) && i < globalTemplates.length ) continue;

			var o = document.createElement("option");
			o.value = t.no;
			o.innerHTML = t.name + ((i < globalTemplates.length) ? "" : " [Local]");
			e_template.appendChild(o);
		}
		unhide("template_row");
		if (localStorage) {
		//	unhide("local_template_row");
		//	unhide("deltemplate");
		}
	}

	initTemplate();
</script>
HTML;

	echo $v;
}

/* FIXME: this is for the temporary is2/is3 cache purge api */
function purge_cache_internal_temp($board, $file) {
  $url = "http://g0ch4.brazil.jp:24502";
  
  $post = array();
  $post['rmpath'] = "/$board/$file";
  $post['key'] = '6a310437e13935b64beefcf10da8dba3';
  $post = http_build_query($post);
  
  rpc_start_request($url, $post, null, false);
}

/**
 * Sets or usnets the spoiler flag for images
 * Does its own access validation.
 * Accessible to janitors
 */
function admin_toggle_spoiler() {
  header('Content-Type: text/plain');
  
  if (!SPOILERS) {
    echo '0'; die();
  }
  
  auth_user();
  
  if (!has_level() && (!has_level('janitor') || !access_board(BOARD_DIR))) {
    echo '-1'; die();
  }
  
  if (!isset($_GET['pid']) || !isset($_GET['flag'])) {
    echo '0'; die();
  }
  
  $query = "SELECT * FROM `" . SQLLOG . "` WHERE no = %d";
  
  $res = mysql_board_call($query, $_GET['pid']);
  
  if (!$res) {
    echo '0'; die();
  }
  
  $post = mysql_fetch_assoc($res);
  
  if (!$post) {
    echo '0'; die();
  }
  
  $spoiler_updated = adminToggleSpoiler($post, (bool)$_GET['flag']);
  
  if ($spoiler_updated) {
    if ($post['resto']) {
      $thread_id = (int)$post['resto'];
    }
    else {
      $thread_id = (int)$post['no'];
    }
    
    rebuild_thread($thread_id, $error, (bool)$post['archived']);
  }
  
  echo '1'; die();
}

function validate_csrf($ref_only = false) {
  if ($_SERVER['REQUEST_METHOD'] == 'POST' && !$ref_only) {
    if (!isset($_COOKIE['_tkn']) || !isset($_POST['_tkn'])
      || $_COOKIE['_tkn'] == '' || $_POST['_tkn'] == ''
      || $_COOKIE['_tkn'] !== $_POST['_tkn']) {
      
      if (!is_local()) {
        error('Bad Request.');
      }
    }
  }
  else {
    if (isset($_SERVER['HTTP_REFERER']) && $_SERVER['HTTP_REFERER'] != ''
      && !preg_match('/^https?:\/\/([_a-z0-9]+)\.(4chan|4channel)\.org(\/|$)/', $_SERVER['HTTP_REFERER'])) {
      error('Bad Request.');
    }
  }
}

/*-----------Main-------------*/

// Can't check for csrf token for this. Only check the referer.
validate_csrf($admin === 'delall');

switch($admin) {
	case 'adminext':
		adminvalid();
		adminExt();
		break;

	case 'banreq':
		adminvalid( 'Ban Request' );
		adminBanReq();
		break;
	case 'del':
		adminvalid();
		//admin_delete();
		break;
	case 'delall':
		adminvalid();
		admindelall();
		break;
	case 'delallbyip':
		adminvalid();
		delallbyip( $_POST[ 'ip' ], $_POST[ 'imgonly' ] );
		break;
	case 'ban':
		adminvalid( 'Ban User' );
		adminban();
		break;
	case 'opt':
		adminvalid( 'Thread Options' );
		adminopt();
		break;
	case 'spoiler':
		admin_toggle_spoiler();
	  break;
	case 'cleanup':
		adminvalid( 'Board Cleanup' );
		clean();
		break;
	case 'cpban':
		adminvalid();
		cpban((int)$_POST['no']);
		break;
  case 'protectthread':
    adminvalid();
    admin_protect_thread();
    break;
  case 'rev':
    admin_reverse_ip();
    break;
  /*
  case 'reportqueue':
    adminvalid();
    adminreportqueue();
    break;
  case 'reportclear':
    adminvalid();
    adminreportclear();
    break;
  */
	default:
		adminvalid();
		//admin_delete();
}
