<?php
/**
 * MySQLi DB Handler
 * Drag and drop replacement for db.php!
 */
include_once 'config/config_db.php';
include_once 'lib/util.php';

if( !defined( 'S_SQLCONF' ) ) {
	define( 'S_SQLCONF', 'MySQL connection error' );
	define( 'S_SQLDBSF', 'MySQL database error' );
}

/**
 * @param PDO $con
 * @param PDO $gcon
 */
$con = $gcon = null;
$has_set_unbuffered = false;

function mysql_try_connect( $host, $user, $pass, $db, $pconnect = true )
{
	global $mysql_connect_opts;
	$tries = 1;
	
	do {
		$pconnect = ( $pconnect ) ? array(PDO::ATTR_PERSISTENT => true) : null;
	
		$con = @new PDO( "mysql:host=$host;dbname=$db", $user, $pass, $pconnect );
		$failed = 1; $pconnect = false;
		
		if( $con ) $failed = 0;
	} while( $failed && $tries-- );
	
	if( $failed ) {
		mysql_internal_err( NULL, "while connecting to $host", "", true );
	}
	
	// Set attributes
	$con->setAttribute( PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC );
	$con->setAttribute( PDO::ATTR_ERRMODE, PDO::ERRMODE_SILENT );
	$con->setAttribute( PDO::ATTR_ORACLE_NULLS, PDO::NULL_TO_STRING );
	//$con->setAttribute( PDO::ATTR_EMULATE_PREPARES, false );
	$con->setAttribute( PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, true );
	
	return $con;
}

/**
 * @param PDO $conn
 */
function mysql_internal_err($conn, $priverr, $query="", $die=false) {
	global $mysql_never_die;
	global $mysql_suppress_err;
	
	if ($mysql_suppress_err) return;
	
	$errInfo = $conn->errorInfo();
	$errInfo = $errInfo[2];
	
	$err = sprintf("%s error: %s - %d - %s%s", $query ? "query" : "connection",
					$priverr, $conn->errorCode(), $errInfo, $query ? " query: $query" : "");

	//internal_error_log("SQL", $err);
	
	echo $err;
	if ($die && !$mysql_never_die) die($query ? S_SQLDBSF : S_SQLCONF);
}


/**
 * @param PDO $con
 */
function mysql_internal_close( $con )
{
	$con->exec('UNLOCK TABLES');
	unset($con);
}

function mysql_board_lock( $local = false )
{
	global $board_lock_level, $con;
	
	if( $board_lock_level > 0 ) {
		mysql_internal_err( $con, "recursively locked table", "lock tables " . BOARD_DIR );
		return;
	}
	
	$board_lock_level++;
	
	$local = $local ? ' local' : '';
	$con->exec("LOCK TABLE " . BOARD_DIR . " READ $local");
}

function mysql_board_unlock( $ignore_error = false )
{
	global $board_lock_level, $con;
	if( $board_lock_level == 0 ) {
		if( !$ignore_error ) {
			mysql_internal_err( $con, "not already locked", "UNLOCK TABLES" );
		}
		
		return;
	}
	
	$board_lock_level--;
	$con->exec('UNLOCK TABLES');
}

function mysql_clear_locks()
{
	mysql_board_unlock(true);
}

/** CONNECTIONS **/
function mysql_global_connect()
{
	global $gcon;
	$gcon = mysql_try_connect(
		SQLHOST_GLOBAL,
		SQLUSER_GLOBAL,
		SQLPASS_GLOBAL,
		SQLDB_GLOBAL
	);
	
	return $gcon;
}

function mysql_board_connect( $board = '', $pconnect = true )
{
	global $con;
	
	if( !defined( 'SQLHOST' ) || ( constant( 'BOARD_DIR' ) != $board ) ) {
		if( !$board ) {
			if( !defined( 'BOARD_DIR' ) ) {
				mysql_internal_err( null, 'no board defined to connect to' );
			} else {
				$board = BOARD_DIR;
			}
			
			$db   = 1;
			$host = "db-ena.int";
			$db   = "img$db";
			
			define( 'SQLHOST',   $host );
			define( 'SQLDB',     $db );
			define( 'BOARD_DIR', $board );
		}
	} else {
		$host = SQLHOST;
		$db   = SQLDB;
	}
	
	$con = mysql_try_connect( $host, SQLUSER, SQLPASS, $db );
	register_shutdown_function( 'mysql_clear_locks' );
	
	return $con;
}

/**
 * @param PDOStatement $query
 * @param PDOStatement $res
 * @param PDO $con
 */
function mysql_do_query( $query, $con, $querystr, $recon_func, $tries = 0 )
{
	global $mysql_unbuffered_reads, $mysql_suppress_err, $has_set_unbuffered;
	
	$querylog  = defined( 'QUERY_LOG' ) && QUERY_LOG;
	$is_select = strpos( $querystr, 'SELECT' ) === 0;
	
	if( $querylog ) $time = microtime(true);
	
	if( $mysql_unbuffered_reads ) $query->closeCursor(); // close anything open
	$con->setAttribute( PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, (bool)!$mysql_unbuffered_reads );
	$res = $query->execute();
	
	if( $res && $querylog ) {
		global $querylog_fd;
		$elapsed = microtime(true);
		
		$nr = $mysql_unbuffered_reads ? '?' : $query->rowCount();
		
		if( !$querylog_fd ) {
			$querylog_fd = fopen( '/www/perhost/querylog.log', 'a' );
			flock( $querylog_fd, LOCK_EX );
		}
		
		fprintf($querylog_fd, "%d query: %s\n%d rows, %f sec\n", getmypid(), $query, $nr, $elapsed / 1000000.);
	}
	
	if( $mysql_suppress_err ) return $query;
	
	if( $res === false || ( !$mysql_unbuffered_reads && $is_select && $res === false ) ) {
		if( $is_select && $tries > 0 ) {	
			mysql_internal_close( $con );
			$con = $recon_func();
			
			return mysql_do_query( $query, $con, $querystr, $recon_func, $tries-1 );
		} else {
			error_log( 'do_query res = ' . gettype($res) . ': ' . $res );
			mysql_internal_err( $con, 'in do_query', $querystr, $tries == 0 );
		}
	}
	
	return $query;
}

function mysql_global_call()
{
	global $gcon;
	if( !$gcon ) mysql_global_connect();
	
	$args = func_get_args();
	$querystr = array_shift($args);
	
	$query = $gcon->prepare($querystr);
	
	if( count( $args ) ) {
		$i = 1;
		
		foreach( $args as $arg ) {
			if( $arg == null ) $arg = '';
			$type = is_int($arg) || ctype_digit($arg) ? PDO::PARAM_INT : PDO::PARAM_STR;
			$query->bindValue( $i, $arg, $type );
			
			$i++;
		}
	}
	
	return mysql_do_query( $query, $gcon, $querystr, 'mysql_global_connect' );
}

function mysql_board_call()
{
	global $con;
	if( !$con ) mysql_board_connect();
	
	$args = func_get_args();
	$querystr = array_shift($args);
	
	$query = $con->prepare($querystr);
	
	if( count( $args ) ) {
		$i = 1;
		
		foreach( $args as $arg ) {
			if( $arg == null ) $arg = '';
			$type = is_int($arg) || ctype_digit($arg) ? PDO::PARAM_INT : PDO::PARAM_STR;
			$query->bindValue( $i, $arg, $type );
			
			$i++;
		}
	}
	
	return mysql_do_query( $query, $con, $querystr, 'mysql_global_connect' );
}

function mysql_board_get_post( $board, $no )
{
	global $con;
	mysql_board_connect( $board );

	/**
	 * @param PDOStatement $query
	 */
	$query = mysql_board_call( "SELECT HIGH_PRIORITY * FROM $board WHERE no=?", $no );
	$arr = $query->fetch();
	
	$con = null;
	return $arr;
}

/**
 * Utility function to make $query->fetch() on SELECT COUNT() not a pain in the ass
 * 
 * @param PDOStatement $query
 */
function mysql_fetch_count( $query )
{
	return $query->fetchColumn();
}

/**
 * Utility function mimicking mysql_free_result()
 * 
 * @param PDOStatement $query
 */
function mysql_close_result( $query )
{
	$query->closeCursor();
}

function mysql_column_array( $query, $field )
{
	$ret = array();
	while( $row = $query->fetch() ) {
		$ret[] = $row[$field];
	}
	
	return $ret;
}

function mysql_board_insert_id()
{
	global $con;
	return $con->lastInsertId();
}
