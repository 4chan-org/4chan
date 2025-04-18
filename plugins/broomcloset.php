<?php
// broom closet - yotsuba plugin
// implementation of janitor discussion system.

/* Features:
 * - "latest" mode - display last post number, etc, so that it can be polled by a script.
 * - force posting with logged-in moderator/janitor name.
 * - never expire posts.
 * - allow no-file posts.
 * - emit PHP instead of html in order to do admin-validation checking.
 * - give everyone a capcode, and give janitors a tooltip saying which board they're in charge of.
 */

// Config enforcement... (too late to change it now)
if( NO_TEXTONLY == 1 ) die( 'Config NO_TEXTONLY should be turned off!' );
if( PHP_EXT == '.html' ) die( 'Config PHP_EXT should end in .php!' );
if( PAGE_MAX > 0 ) die( 'Config PAGE_MAX should be 0!' );


/*	register_callback('mode_default_case', 'broomcloset_mode');
	register_callback('regist_before', 'broomcloset_regist');
	register_callback('trim_db_before', 'broomcloset_trim');
	register_callback('head_before', 'broomcloset_head');
	register_callback('form_after', 'broomcloset_form');
	register_callback('post_before', 'broomcloset_post');
	register_callback('capcode', 'broomcloset_capcode');
*/

// add the 'latest' mode
function broomcloset_latest()
{
	//if (!valid('janitor_board')) die('');
	$query = mysql_board_call( "SELECT * FROM `" . SQLLOG . "` ORDER BY no DESC LIMIT 1" );
	if( $row = mysql_fetch_assoc( $query ) ) {
		foreach( $row as &$val ) $val = addslashes( $val );
		echo <<<EOJSON
{"no":{$row['no']}}
EOJSON;
	}
	die( '' );
}

function refresh_mod_cache()
{
	global $mod_cache;

	if( !isset( $mod_cache ) ) {
    $admin_salt = file_get_contents('/www/keys/2014_admin.salt');
    
    if (!$admin_salt) {
      die('Internal Server Error (rmc0)');
    }
    
		$query     = mysql_global_call( "SELECT id,username,allow,level from mod_users" );
		$mod_cache = array();
		while( list( $id, $username, $allow, $level ) = mysql_fetch_row( $query ) ) {
			if( $allow ) {
        $hashed_bits = hash_hmac('sha256', $username, $admin_salt, true);
        
        $username = base64_encode($hashed_bits);
			  
				$mod_cache[$username] = array();
				
				$board = '';
				
				if( $level == 'janitor' ) {
					$level = 'Janitor';
					$color = '#4169E1';
					$board = str_replace( ',janitor', '', $allow );
				} elseif( $id == 2 ) {
					$level = 'Admin';
					$color = '#FF0000';
				} elseif( $level == 'manager' ) { // disabled until mootapproval
					$level = 'Manager';
					$color = '#FF0080';
				} else {
					$level = 'Mod';
					$color = '#800080';
				}

				$mod_cache[$username]['level'] = $level;
				$mod_cache[$username]['color'] = $color;
				$mod_cache[$username]['id']    = $id;
				
				if( $board )
					$mod_cache[$username]['board'] = $board;
			}
		}
	}
}

function broomcloset_name( $name )
{
	global $mod_cache;
	refresh_mod_cache();
	
	if( !isset( $mod_cache[$name] ) ) { // user not found
		return 'Anonymous';
	}
	
	return 'Anonymous ## ' . $mod_cache[$name]['level'];
}


function broomcloset_style( $name )
{
	global $mod_cache;
	refresh_mod_cache();
	
	if( !isset( $mod_cache[$name] ) ) { // user not found
		return ' style="color:#aaa"';
	}
	
	if( $mod_cache[$name]['board'] ) {
		$tooltip = " style='color: {$mod_cache[$name]['color']}'";
	} else {
		$tooltip = " style='color: {$mod_cache[$name]['color']}'";
	}

	return $tooltip;
}

// auto-set name
function broomcloset_new_post( $caller )
{
	// set textonly to 1 - this is ok even if they're posting a picture
	// now imgboard won't complain about no picture EVER
	$caller['textonly'] = 1;

	$caller['name'] = $_COOKIE['4chan_auser'];
	if( !has_level( 'janitor' ) ) die;
}

function broomcloset_form( $dat )
{ // modify the form to hide name, email, and textonly
	$newform = str_replace( '<tr><td></td><td class="postblock" align="left"><b>Name</b></td><td><input type=text name=name size="28"><span id="tdname"></span></td></tr>', '<input type=hidden name=name>', $dat );
	$newform = str_replace( '<tr><td></td><td class="postblock" align="left"><b>E-mail</b></td><td><input type=text name=email size="28"><span id="tdemail"></span></td></tr>', '<input type=hidden name=email>', $newform );
	$newform = str_replace( '[<label><input type=checkbox name=textonly value=on>No File</label>]', '', $newform );
	$newform = str_replace( 'name=sub size="35">', 'name=sub size="35"><span id="tdname"></span><span id="tdemail"></span>', $newform ); // move admin ext. placeholders next to subject
	return $newform;
}

// this function is last because it screws up syntax coloring in my editor :(
function broomcloset_head( $dat )
{
	$dat .= <<<'BUTTCODE'
<?php if( !isset( $_COOKIE['4chan_auser'] ) || !isset( $_COOKIE['apass'] ) ) { http_response_code(403); die(); }

require_once 'lib/admin.php';
require_once 'lib/auth.php';

header('Content-Security-Policy: connect-src *.4chan.org *.4cdn.org');
header('X-Content-Security-Policy: connect-src *.4chan.org *.4cdn.org');

auth_user();

if( !has_level('janitor') ) { http_response_code(403); die(); } ?>
BUTTCODE;

	return $dat;
}
