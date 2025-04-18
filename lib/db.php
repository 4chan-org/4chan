<?php
// generic db functions
require_once 'config/config_db.php';
require_once 'lib/util.php';

// define the error message strings in case this wasn't used in a file that
// uses the full yotsuba_config system...
if(!defined('S_SQLCONF')) {
	define('S_SQLCONF', 'MySQL connection error');
	define('S_SQLDBSF', 'MySQL database error');
}

//paranoid since i don't know when it fails, when it does fail
function mysql_try_connect($host,$usr,$pass,$db,$pconnect=true) {
	global $mysql_connect_opts;
	$tries = 1;

	do {
		$con = $pconnect ? @mysql_pconnect($host,$usr,$pass,$mysql_connect_opts) : @mysql_connect($host,$usr,$pass,1,$mysql_connect_opts);
		$failed = 1; $pconnect = false;

		if ($con) {
			if (mysql_select_db($db, $con)) {
				$failed = 0;
			}
		}
	} while ($failed && $tries--);

	if ($failed)
		mysql_internal_err(NULL, "while connecting to $host", "", true);

	return $con;
}

function mysql_internal_close($con) {
	mysql_query("UNLOCK TABLES", $con);
	mysql_close($con);
}

function mysql_board_lock($local=false) {
	global $board_lock_level, $con;

	if ($board_lock_level > 0) {
		mysql_internal_err($con, "recursively locked table", "lock tables ".BOARD_DIR);
		return;
	}

	$board_lock_level++;

	mysql_query("lock tables ".BOARD_DIR." read".($local ? " local" : ""), $con);
}

function mysql_board_unlock($ignore_error=false) {
	global $board_lock_level, $con;

	if ($board_lock_level == 0) {
		if (!$ignore_error)
			mysql_internal_err($con, "not already locked", "unlock tables");
		return;
	}

	$board_lock_level--;
	mysql_query("unlock tables", $con);
}

function mysql_clear_locks() {
	mysql_board_unlock(true);
}

function mysql_global_connect($pconnect=true) {
	global $gcon;
	$gcon = mysql_try_connect(SQLHOST_GLOBAL, SQLUSER_GLOBAL, SQLPASS_GLOBAL, SQLDB_GLOBAL, $pconnect);
	return $gcon;
}

// assumes BOARD_DIR is set
function mysql_check_connections() {
	global $gcon, $con;

	$gcon_res = mysql_ping($gcon);
	$con_res = mysql_ping($con);

	if ($gcon_res == false || $con_res == false) {
		mysql_internal_close($con);
		mysql_internal_close($gcon);
		$con = null;
		$gcon = null;
		mysql_board_connect(BOARD_DIR, false);
		mysql_global_connect(false);
	}
}

//really bad error system...
function mysql_internal_err($conn, $priverr, $query="", $die=false) {
	global $mysql_never_die;
	global $mysql_suppress_err;
		
	$err = sprintf("%s error: %s - %d - %s%s", $query ? "query" : "connection",
					$priverr, mysql_errno($conn), mysql_error($conn), $query ? " query: $query" : "");

    if (SQL_DEBUG && ini_get('display_errors')) echo $err."\n";

	internal_error_log("SQL", $err);

	if ($die && !$mysql_never_die) die($query ? S_SQLDBSF : S_SQLCONF);
}

//pconnect - call _pconnect, not safe if tables are locked
function mysql_board_connect($board="", $pconnect=true) {
	global $con;
	global $did_add_lockfunc;

	if (!defined('SQLHOST')) {
		$db = 1; // db is always 1 now
		$host = "db-ena.int"; // and always "db-ena" (this should never happen because we define SQLHOST)
		$db = "img$db";

		define('SQLHOST', $host);
		define('SQLDB', $db);
		if ($board) define('BOARD_DIR', $board);
	} else {
		$host = SQLHOST;
		$db = SQLDB;
	}

	$con = mysql_try_connect($host, SQLUSER, SQLPASS, $db, $pconnect);
    //if (SQL_DEBUG && ini_get('display_errors')) echo "connected to ".$host." SQLHOST is ".SQLHOST."\n";
	
	if (!$did_add_lockfunc) {
		$did_add_lockfunc = 1;
		register_shutdown_function("mysql_clear_locks");
	}
	return $con;
}

function mysql_do_query($query, $con) {
	global $mysql_unbuffered_reads;
	global $mysql_suppress_err;
	global $mysql_query_log;
	global $mysql_debug_buf;
	static $querylog_fd;
	
	$querylog = (defined('QUERY_LOG') && constant('QUERY_LOG')) || $mysql_query_log == true;
	$is_select = stripos($query, "SELECT")===0;

	$time = 0;
	if ($querylog) {
		$time = microtime(true);
		
		if (!$querylog_fd) {
			$querylog_fd = fopen("/www/perhost/querylog.log", "a");
			flock($querylog_fd, LOCK_EX);
		}
	
		fprintf($querylog_fd, "%d query: %s\n", getmypid(), $query);
	}

	if ($mysql_unbuffered_reads)
		$ret = @mysql_unbuffered_query($query, $con);
	else
		$ret = @mysql_query($query, $con);

	if ($ret && $querylog) {
		$elapsed = microtime(true) - $time;

		if (!$mysql_unbuffered_reads) {
			$nr = @mysql_num_rows($ret);
			if (!$nr) $nr = @mysql_affected_rows($ret);
		} else
			$nr = "?";

		fprintf($querylog_fd, "%d rows, %f sec\n", $nr, $elapsed);
	}
	
	if (isset($mysql_debug_buf)) {
		if (!$mysql_unbuffered_reads) {
			$nr = @mysql_num_rows($ret);
			if (!$nr) $nr = @mysql_affected_rows($ret);
			if (!$nr) $nr = 0;
		} else
			$nr = "?";
			
		$mysql_debug_buf .= "Query: $query\nRows: $nr\n";
	}

	if ($ret === FALSE) {
		mysql_internal_err($con, "in do_query", $query);
	}

	return $ret;
}

function try_escape_string($string, $con, $recon_func, $tries=0)
{
	$res = mysql_real_escape_string($string, $con);

	if ($res === FALSE) {
		mysql_internal_err($con, "in escape_string", $string, $tries == 0);
	}

	return $res;
}

//note for use of db query functions
//old-style calls (escaping done manually beforehand)
//must connect manually too

//for read queries
function mysql_global_call() {
	global $gcon;
	if(!$gcon) mysql_global_connect();
	$args = func_get_args();
	$format = array_shift($args);

	if (count($args)) {
		foreach($args as &$arg)
			$arg = try_escape_string($arg, $gcon, "mysql_global_connect" );
		$query = vsprintf($format, $args);
	} else $query = $format;

	return mysql_do_query( $query, $gcon );
}

function mysql_global_error() {
	global $gcon;
	if(!$gcon) mysql_global_connect();
	
	return mysql_error($gcon);
}

//for r/w queries (historical, doesn't actually matter)
//TODO: remove
function mysql_global_do() {
	global $gcon;
	if(!$gcon) mysql_global_connect();
	$args = func_get_args();
	$format = array_shift($args);

	if (count($args)) {
		foreach($args as &$arg)
			$arg = try_escape_string($arg, $gcon, "mysql_global_connect" );
		$query = vsprintf($format, $args);
	} else $query = $format;

	return mysql_do_query( $query, $gcon );
}

function mysql_global_insert_id() {
	global $gcon;
	return mysql_insert_id($gcon);
}

function mysql_board_call() {
	global $con;
	if (!$con) mysql_board_connect();
	$args = func_get_args();
	$format = array_shift($args);

	if (count($args)) {
		foreach($args as &$arg)
			$arg = mysql_real_escape_string($arg, $con);
		$query = vsprintf($format, $args);
	} else $query = $format;

	return mysql_do_query( $query, $con );
}

function mysql_global_escape($string) {
	global $gcon;
	if(!$gcon) mysql_global_connect();

	return mysql_real_escape_string($string, $gcon);
}

function mysql_board_error() {
	global $con;
	if(!$con) mysql_board_connect();
	
	return mysql_error($con);
}

function mysql_board_escape($string) {
	global $con;
	if (!$con) mysql_board_connect();

	return mysql_real_escape_string($string, $con);
}

function mysql_board_get_post($board,$no) {
	global $con;
	mysql_board_connect($board);
	$query = mysql_board_call("SELECT HIGH_PRIORITY * from `%s` WHERE no=%d",$board,$no);
	$array = mysql_fetch_assoc($query);
	mysql_close($con);
	$con = NULL;
	return $array;
}

function mysql_board_get_post_lazy( $board, $no )
{
	global $con;
	mysql_board_connect($board);
	$query = mysql_board_call("SELECT * from `%s` WHERE no=%d",$board,$no);
	$array = mysql_fetch_assoc($query);
	mysql_close($con);
	$con = NULL;
	return $array;
}

function mysql_board_insert_id() {
	global $con;
	return mysql_insert_id($con);
}

function mysql_global_row($table, $col, $val)
{
	$q = mysql_global_call("select * from `%s` where $col='%s'", $table, $val);
	$r = mysql_fetch_assoc($q);
	mysql_free_result($q);
	return $r;
}

function mysql_board_row($table, $col, $val)
{
	$q = mysql_board_call("select * from `%s` where $col='%s'", $table, $val);
	$r = mysql_fetch_assoc($q);
	mysql_free_result($q);
	return $r;
}

// answer must be one column
function mysql_column_array($q) {
	$ret = array();
	while ($row = mysql_fetch_row($q))
		$ret[] = $row[0];
	return $ret;
}

// turn "" into NULL
// incompatible with escaping :(
function mysql_nullify($s) {
	return ($s || $s === '0') ? "'$s'" : "''"; //"NULL";
}

?>
