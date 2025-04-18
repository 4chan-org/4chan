<?php
require_once 'lib/ini.php';
// Yotsuba configuration engine
// HOEHOEPA!

if ($use_pdo) {
	require_once 'lib/db_pdo.php';
} else {
	require_once 'lib/db.php';
}

//load config ini files for a board
//$board the board short name
//$subdomain the board's subdomain. it might not have one
//if CATEGORY is set in board config, we use that instead of the subdomain
function load_board_config( $board, $subdomain )
{
	global $configdir, $yconfgdir;
	//FIXME use apc cache to store parse results
	//FIXME consider include statements instead of CATEGORY
	//FIXME consider what boardlist.kind has to do with the last line

	if( strpos( $board, '.' ) !== false ) {
		die( "This board has an invalid name!" );
	}

	$board_config  = parse_ini( "$yconfgdir/boards/$board.config.ini" );
	$board_strings = parse_ini( "$yconfgdir/boards/$board.config.ini" );

	if( !$board_config ) {
		die( "This board doesn't exist!" );
	}

	if( isset( $board_config[ 'CATEGORY' ] ) ) {
		$category      = $board_config[ 'CATEGORY' ];
		$group_config  = parse_ini( "$yconfgdir/categories/$category.config.ini" );
		$group_strings = parse_ini( "$yconfgdir/categories/$category.strings.ini" );
	}
	else {
		$group_config  = parse_ini( "$yconfgdir/subdomains/$subdomain.config.ini" );
		$group_strings = parse_ini( "$yconfgdir/subdomains/$subdomain.strings.ini" );
	}

	if( !$group_config ) {
		die( "This board is on a different domain!" );
	}

	write_constants( $group_config );
	write_constants( $group_strings );
	write_constants( $board_config );
	write_constants( $board_strings );
}

//load the globals
load_ini( "$yconfgdir/global_config.ini" );
load_ini( "$yconfgdir/global_strings.ini" );
//load the Cloudflare API key
load_ini( "$configdir/cloudflare_config.ini" );

mysql_global_connect();

//split path into components
//reconstruct from url so rewriting works
if( isset( $manual_config_load ) && $manual_config_load && isset( $board ) ) {
}
else {
	if( isset( $_SERVER[ "argv" ] ) ) {
		$cwd           = getcwd();
		$document_root = dirname( $cwd );
	}
	else {
		$cwd           = dirname( $_SERVER[ "DOCUMENT_ROOT" ] . $_SERVER[ "SCRIPT_URL" ] );
		$document_root = $_SERVER[ "DOCUMENT_ROOT" ];
	}
	$pathcomps = explode( '/', $cwd );
	//subdomain is the first part (separated by periods) of the folder 2 up from the cwd
	//...if we're using subdomains, otherwise this is garbage
	$subdomain = explode( '.', $pathcomps[ count( $pathcomps ) - 3 ] );
	$subdomain = $subdomain[ 0 ];
	//the board is just the current directory name
	$board = end( $pathcomps );
}
//set our default board
$constants[ 'BOARD_DIR' ] = $board;

if( $using_pdo ) {
	$query = mysql_global_call( "SELECT name, db FROM boardlist WHERE dir=?", $board );
	$row   = $query->fetch();
}
else {
	$query = mysql_global_call( "SELECT name,db from boardlist WHERE dir = '%s'", $board );
	$row   = mysql_fetch_assoc( $query );
}

if( !$row ) {
	$row = array( 'db' => 1 );
}
//maybe get our default board title from DB
if( isset( $row[ 'name' ] ) ) {
	$title                = $row[ 'name' ];
	$constants[ 'TITLE' ] = "/$board/ - $title";
}
if( !defined( 'SQLHOST' ) ) {
	$constants[ 'SQLHOST' ] = "db-ena.int";
} // was db$row['db']
if( !defined( 'SQLDB' ) ) {
	$constants[ 'SQLDB' ] = "img{$row[ 'db' ]}";
}

load_board_config( $board, $subdomain );

finalize_constants();
define("YES", TRUE);
define("NO", FALSE);

if( basename( $_SERVER[ 'SCRIPT_NAME' ] ) == basename( __FILE__ ) ) {
	print "<xmp>";
	$cxs = get_defined_constants( true );
	print_r( $cxs[ 'user' ] );
}

if( !$no_unset ) unset( $title, $constants, $board, $subdomain, $pathcomps, $CONFIG_PATTERN, $query, $row, $fakecwd );
