<?
// rebuild daemon
// rebuilds HTML pages constantly, leaving only threads for the posting processes
// for each STATIC_REBUILD board, start one of these with the board as cwd

// setup (turn off die() and such)
$mode = "nothing";
define("IS_REBUILDD", true);
$mysql_never_die = true;
$mysql_suppress_err = false;
//$mysql_connect_opts = MYSQL_CLIENT_COMPRESS;

set_time_limit(0);
ini_set("memory_limit", "-1");
include("imgboard.php");
//if( ENABLE_CATALOG ) require_once 'catalog.php';
$count = 0;

$sec_in_us = 1000000;
$time_to_sleep_max = MAX_REBUILD_TIMER;
$time_to_sleep_min = MIN_REBUILD_TIMER;

$time_to_sleep  = $time_to_sleep_min;
$update_counter = 0;
$update_total_secs = 0.0;
$lastno = 0;
$lastthreadno = 0;
$do_trim_db = false;

file_put_contents("/www/perhost/rebuildd-".BOARD_DIR.".pid", getmypid());

function rebuildd_gate()
{
	global $lastno,$lastthreadno,$do_trim_db,$con,$gcon;
	
	$ret = 0;
	
	$ctype = get_resource_type($con); $gtype = get_resource_type($gcon);
	echo "last $lastno, last thread $lastthreadno, cons $con/$ctype $gcon/$gtype, ";
	
	mysql_check_connections();
	list($newlastno) = mysql_fetch_row(mysql_board_call("select max(no) from `".SQLLOG."`"));

	if ($newlastno > $lastno) {
		//may not be indexed...
		list($newthreadno) = mysql_fetch_row(mysql_board_call("select max(no) from `".SQLLOG."` where resto=0 and archived=0"));
		$do_trim_db = $newthreadno > $lastthreadno;
		
		$lastthreadno = $newthreadno;
		$lastno = $newlastno;
		$ret = 1;
	}

	echo "new thread $lastthreadno, new last $lastno, shouldRebuild $ret\n";
	return $ret;
}

function rebuildd_update_index()
{
	global $do_trim_db, $count;
	log_cache(1);
	if ($do_trim_db) {
	  trim_db();
	  trim_archive();
  }
	//updatelog();
	rebuild_indexes_daemon();
	rpc_task();
	if( ENABLE_CATALOG && $count == CATALOG_DAEMON_REBUILD_INTERVAL ) {
		generate_catalog();
		$count = 0;
	}
	
	if( ENABLE_CATALOG ) $count++;
}

function rebuildd_occasional_cleanup()
{
	global $rpc_mh; global $rpc_chs;
	
	$nrpcs = count($rpc_chs);
	echo "finishing $nrpcs RPCs\n";
	
	rpc_finish_all();
}

while (1) {
	$update_start_time = microtime(true);
	if (rebuildd_gate()) {
		rebuildd_update_index();
		$update_end_time = microtime(true);
		$update_counter++;
		$work_time = $update_this_secs = $update_end_time - $update_start_time;
		$update_total_secs += $update_this_secs;
		$update_avg_secs = $update_total_secs / $update_counter;
		$next_start_time = $update_start_time + $time_to_sleep_min;
		
		$to_sleep = $next_start_time - $update_end_time;
		if ($to_sleep <= 0) { // if we're late, skip to the next time
			rebuildd_occasional_cleanup();
			$to_sleep = $time_to_sleep_min;
			$work_time = microtime(true) - $update_start_time;
		}
		
		$sleepusec = $to_sleep * $sec_in_us;
		usleep($sleepusec);
		
		// reset long-sleep to shortest time
		$time_to_sleep = $time_to_sleep_min;
		
		// avoid overflow
		if ($update_counter == 65536) {
			$update_counter = 0;
			$update_total_secs = 0.0;
		}
		echo "$update_end_time update counter $update_counter slept $to_sleep update took $work_time\n";
	} else {
		// todo push-style rebuilds instead of polling for slower boards
		rebuildd_occasional_cleanup();
		$update_end_time = microtime(true);
		
		$sleepusec = $time_to_sleep * $sec_in_us;
		usleep($sleepusec);
		$time_to_sleep = min($time_to_sleep*1.25, $time_to_sleep_max);
		
		$update_this_secs = $update_end_time - $update_start_time;
		echo "$update_end_time update counter $update_counter slept $to_sleep cleanup took $update_this_secs\n";
	}	
}
?>
