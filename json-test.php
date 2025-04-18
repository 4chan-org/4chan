<?php
/**
 * JSON Dumper
 * Dumps a thread into a .json file.
 */

if( !isset( $in_imgboard ) ) die( 'No direct access' );

if( META_BOARD ) {
	include_once 'plugins/enhance_q.php';
}

function generate_thread_json( $threadid, $return = false, $replies = false, $for_catalogue = false, $tail = 0 )
{
	global $log, $thread_unique_ips;
	if( !$log[$threadid] ) log_cache( 0, $threadid );
	$carr = array();

	$op = $log[$threadid];
	foreach( $op['children'] as $key => $val ) {
		$carr[] = $key;
	}

	sort( $carr, SORT_NATURAL );

	$count      = count( $carr ) + 1;
	$replycount = $op['replycount'];

	$build      = '';
	$json       = array();
	$extra      = array();
	$imagecount = $op['imgreplycount'];
	$tail_images = 0;
  
  $extra['replies'] = $replycount;
  $extra['images']  = $imagecount;
  
  // Not inside a thread
  if ($replies !== false) {
    if ($replycount - $replies > 0) {
      for ($i = $replycount - $replies; $i < $replycount; $i++) {
        if ($log[$carr[$i]]['fsize'] && !$log[$carr[$i]]['filedeleted']) {
          $tail_images++;
        }
      }
      
      $extra['omitted_posts']  = $replycount - $replies;
      $extra['omitted_images'] = $imagecount - $tail_images;
    }
  }
  // Inside a thread
  else if (SHOW_THREAD_UNIQUES) {
    if ($thread_unique_ips) {
      $unique_ips = (int)$thread_unique_ips;
    }
    else {
      $unique_ips = get_unique_ip_count($threadid);
    }
    
    if ($unique_ips) {
      $extra['unique_ips'] = $unique_ips;
    }
  }
  
	//if( $replycount >= MAX_RES && !$op['permaage'] ) $extra['bumplimit'] = 1;
	//if( $imagecount >= MAX_IMGRES && !$op['sticky'] ) $extra['imagelimit'] = 1;
  
	$i = 1;
  
  if ($op['semantic_url'] !== '') {
    $extra['semantic_url'] = $op['semantic_url'];
  }
  
	if ($replies !== false) {
	  $i = $count - $replies;
	  
	  if ($i < 1) {
	    $i = 1;
	  }
  }

  if ($for_catalogue) {
    $json = generate_post_json( $op, $threadid, $extra ); // we do op before replies
    if ($replycount > 0) {
      $json['last_replies'] = array();
    	for( ; $i < $count; $i++ ) {
    		if( isset( $log[$carr[$i - 1]] ) ) {
    			$var    = $log[$carr[$i - 1]];
    			$json['last_replies'][] = generate_post_json( $var, $threadid );
    		}
    	}
			if (META_BOARD) {
				$capcode_replies = generate_capcode_replies($op['children']);
				
				if ($capcode_replies) {
					$json['capcode_replies'] = $capcode_replies;
				}
			}
		}
		
		$json['last_modified'] = (int)$log[$threadid]['last_modified'];
		
    return $json;
  }
  else if ($tail) {
    $json[] = generate_op_tail_json($op, $extra);
    
    $lim = $count - $tail;
    
    $tail_id = 0;
    
    for (; $i < $count; $i++ ) {
      if (isset($log[$carr[$i - 1]])) {
        $var = $log[$carr[$i - 1]];
        if ($i < $lim) {
          $tail_id = (int)$var['no'];
        }
        else {
          $json[] = generate_post_json($var, $threadid);
        }
      }
    }
    
    $json[0]['tail_size'] = $tail;
    $json[0]['tail_id'] = $tail_id;
    
    $temp = array('posts' => $json);
  }
  else {
    $json[] = generate_post_json( $op, $threadid, $extra ); // we do op before replies
    
    $tailSize = get_json_tail_size($threadid);
    
    if ($tailSize) {
      $json[0]['tail_size'] = $tailSize;
    }
    
		for( ; $i < $count; $i++ ) {
			if( isset( $log[$carr[$i - 1]] ) ) {
				$var    = $log[$carr[$i - 1]];
				$json[] = generate_post_json( $var, $threadid );
			}
  	}
  	
		if (META_BOARD) {
			$capcode_replies = generate_capcode_replies($op['children']);
			
			if ($capcode_replies) {
				$json[0]['capcode_replies'] = $capcode_replies;
			}
		}
		
    $temp = array('posts' => $json);
  }
  
	if( $return ) return $temp;

	unset( $json );
  
  if (!$tail) {
    $filename = RES_DIR . $threadid . '.json';
  }
  else {
    $filename = RES_DIR . $threadid . '-tail.json';
  }
	$page = json_encode($temp);
	print_page( $filename, $page, 0, 0 );

	return true;
}

function generate_capcode_replies($replies) {
	global $log;
	
	$capcode_replies = array(
		'admin' => array(),
		'developer' => array(),
		'mod' => array()
	);
	
	$has_capcode_replies = false;
	
	foreach ($replies as $no => $val) {
		if (!isset($log[$no])) {
			continue;
		}
		$json_post = $log[$no];
		if ($json_post['capcode'] === 'none') {
			continue;
		}
		if ($json_post['capcode'] === 'admin_highlight') {
			$json_capcode = 'admin';
		}
		else {
			$json_capcode = $json_post['capcode'];
		}
		$capcode_replies[$json_capcode][] = (int)$no;
		$has_capcode_replies = true;
	}
	
	if ($has_capcode_replies) {
		$ret = array();
		foreach ($capcode_replies as $key => $value) {
			if (empty($value)) {
				continue;
			}
			$ret[$key] = $value;
		}
		return $ret;
	}
	else {
		return null;
	}
}

function post_json_force_type( &$post )
{
	static $post_intern = array(
		'no'             => 'integer',
		'resto'          => 'integer',
		'sticky'         => 'integer',
		'closed'         => 'integer',
		'archived'       => 'integer',
		'now'            => 'string',
		'time'           => 'integer',
		'name'           => 'string',
		'trip'           => 'string',
		'id'             => 'string',
		'capcode'        => 'string',
		'country'        => 'string',
		'country_name'   => 'string',
		'sub'            => 'string',
		'com'            => 'string',
		'tim'            => 'integer',
		'filename'       => 'string',
		'ext'            => 'string',
		'fsize'          => 'integer',
		'md5'            => 'string',
		'w'              => 'integer',
		'h'              => 'integer',
		'tn_w'           => 'integer',
		'tn_h'           => 'integer',
		'filedeleted'    => 'integer',
		'spoiler'        => 'integer',
		'custom_spoiler' => 'integer',
		'omitted_posts'  => 'integer',
		'omitted_images' => 'integer',
		'replies'        => 'integer',
		'images'         => 'integer',
		'bumplimit'      => 'integer',
		'imagelimit'     => 'integer',
		'last_modified'  => 'integer',
		'archived_on'    => 'integer',
		'since4pass'     => 'integer',
		'm_img'          => 'integer'
	);


	foreach( $post as $key => $val) {
		if( isset( $post_intern[$key] ) ) {
			settype( $post[$key], $post_intern[$key] );
		}
	}
}

function generate_op_tail_json($op, $extra) {
  $ary = array();
  
  $ary['no'] = (int)$op['no'];
  
  $ary['bumplimit'] = (int)$op['bumplimit'];
  $ary['imagelimit'] = (int)$op['imagelimit'];
  
  if ($op['sticky']) {
    $ary['sticky'] = 1;
    if ($op['undead']) {
      $ary['sticky_cap'] = STICKY_CAP;
    }
  }
  
  if ($op['closed']) {
    $ary['closed'] = 1;
  }
  
  if ($op['archived']) {
    $ary['archived'] = 1;
  }
  
  $ary['replies'] = (int)$extra['replies'];
  $ary['images'] = (int)$extra['images'];
  
  if (isset($extra['unique_ips'])) {
    $ary['unique_ips'] = (int)$extra['unique_ips'];
  }
  
  if (SPOILERS) {
    $ary['custom_spoiler'] = (int)SPOILER_NUM;
  }
  
  return $ary;
}

function generate_post_json( $var, $threadid, $extra = array(), $banskip = false )
{
	$COUNTRY_FLAG_ARR = array(
		'sp',
		'int',
	);

	$FORCED_ANON_ARR = array(
		'b',
		'soc'
	);

	$META_BOARD_ARR = array(
		'q'
	);
	
	if (UPLOAD_BOARD) {
		$FLASH_TAGS = array(
			0 => 'Hentai',
			1 => 'Japanese',
			2 => 'Anime',
			3 => 'Game',
			4 => 'Other',
			5 => 'Loop',
			6 => 'Porn',
		);
	}
	
	$SHOW_COUNTRY_FLAGS = $banskip ? in_array( $var['board'], $COUNTRY_FLAG_ARR ) : SHOW_COUNTRY_FLAGS;
	$ENABLE_BOARD_FLAGS = $banskip ? false : ENABLE_BOARD_FLAGS;
	$META_BOARD         = $banskip ? in_array( $var['board'], $META_BOARD_ARR ) : META_BOARD;
	$FORCED_ANON        = $banskip ? in_array( $var['board'], $FORCED_ANON_ARR ) : FORCED_ANON;
	
	if ($ENABLE_BOARD_FLAGS) {
		$board_flags_array = get_board_flags_array();
	}
	
	$country = $var['country'];
	
	if ($ENABLE_BOARD_FLAGS && isset($board_flags_array[$var['board_flag']])) {
		$board_flag = $var['board_flag'];
	}
	else {
	  $board_flag = '';
	}

	if( $banskip && $var['ext'] ) {
		$salt = file_get_contents( '/www/keys/legacy.salt' );
		$no   = $var['no'];

		$hash         = sha1( $var['board'] . $no . $salt );
		$var['thumb'] = $hash;
	}

	$intern_host = $var['host'];

	$unset = array(
		'permasage',
		'host',
		'pwd',
		'children',
    'imgreplycount',
    'replycount',
		'last_modified',
		'root',
		'4pass_id'
	);
  
  if ($banskip) {
    $unset[] = 'protected';
    $unset[] = 'ua';
  }
  
	$nunset = $unset;

	$var['tim'] = (int)$var['tim'];

	if( $var['filedeleted'] == 1 || !$var['ext'] ) {
		$arr = array(
			'tim',
			'w',
			'h',
			'tn_w',
			'tn_h',
			'filename',
			'ext',
			'md5',
			'fsize',
			'tmd5'
		);

		foreach( $arr as $key ) {
			unset( $var[$key] );
		}
	}
	else {
		// FIXME
		$var['filename'] = mb_convert_encoding($var['filename'], 'UTF-8', 'UTF-8');
	}
	
	// FIXME
	$var['com'] = mb_convert_encoding($var['com'], 'UTF-8', 'UTF-8');
	
	if( !$var['filedeleted'] ) $nunset[] = 'filedeleted';

	// trim it up
	foreach( $nunset as $key ) {
		unset( $var[$key] );
	}
	
	$is_archived = $var['archived'];

	if( $var['resto'] ) {
		unset( $var['sticky'] );
		unset( $var['closed'] );
		unset( $var['archived'] );
	}
	else {
		if (!$var['archived']) {
			unset($var['archived']);
			unset($var['archived_on']);
		}
		
		if (!$var['closed']) {
			unset($var['closed']);
		}
		
		if (!$var['sticky']) {
			unset($var['sticky']);
		}
		else {
		  if ($var['undead']) {
		    $var['sticky_cap'] = (int)STICKY_CAP;
		  }
			unset( $var['bumplimit'], $var['imagelimit'] );
		}
		
		if ($var['permaage']) {
			unset( $var['bumplimit'] );
		}
	}
	
	if (!$var['m_img']) {
	  unset($var['m_img']);
	}
	
	if (!$var['since4pass']) {
	  unset($var['since4pass']);
	}
	// April 2024
	else if ($var['since4pass'] >= 10000) {
		$_stock = april_2024_get_stock_from_s4p($var['since4pass']);
		
		if ($_stock) {
			$var['xa24'] = $_stock;
		}
		
		unset($var['since4pass']);
	}
  
	unset($var['permaage'], $var['undead']);
	
	// clean up names
	if( strpos( $var['name'], '</span> <span class="postertrip">' ) !== false ) {
		$name        = explode( '</span> <span class="postertrip">', $var['name'] );
		$var['name'] = $name[0];
		$var['trip'] = $name[1];

		if( $var['trip'] && !$var['name'] ) unset( $var['name'] );
	}

	if( !$banskip && SPOILERS && !$var['resto'] ) $var['custom_spoiler'] = (int)SPOILER_NUM;

	$var['spoiler'] = 0;
	if( strpos( $var['sub'], 'SPOILER<>' ) === 0 ) {
		$var['sub'] = substr( $var['sub'], 9 );
		if( !$var['sub'] ) $var['sub'] = '';
		$var['spoiler'] = 1;
	} else {
		unset( $var['spoiler'] );
	}

	if( $var['sub'] && !$var['resto'] && UPLOAD_BOARD ) {
		if( preg_match( '/^(\d+)\|/', $var['sub'], $tag_matches ) ) {
			$var['tag'] = $FLASH_TAGS[(int)$tag_matches[1]];
			$var['sub'] = preg_replace( '/^(\d+)\|/', '', $var['sub'] );
		}
	}

	if ( !$banskip ) {
		if( !$var['id'] || $is_archived ) {
			unset( $var['id'] );
		} elseif( $var['id'] && $var['no'] == $threadid && $var['capcode'] === 'none') {
			$var['id'] = generate_uid( $var['no'], $var['time'], $intern_host );
		}
	}

  if ($var['capcode'] == 'none') {
    if ($ENABLE_BOARD_FLAGS && $board_flag) {
      unset($var['country']);
      $var['flag_name'] = board_flag_code_to_name($board_flag);
    }
    else if ($SHOW_COUNTRY_FLAGS) {
      unset( $var['board_flag'] );
      $var['country_name'] = country_code_to_name($country);
    }
    else {
      unset($var['country']);
      unset($var['country_name']);
      unset($var['board_flag']);
    }
  }
  else {
    unset($var['country']);
    unset($var['country_name']);
    unset($var['board_flag']);
  }

	if( ( $FORCED_ANON || $META_BOARD ) && ( $var['capcode'] != 'admin' && $var['capcode'] != 'admin_hl' ) ) {
		unset( $var['trip'] );
		$var['name']  = 'Anonymous';
	}

	if( $var['capcode'] == 'none' ) unset( $var['capcode'] );
	if( !$banskip ) $var['com'] = auto_link( $var['com'], $threadid );
	if( !$banskip && isset( $var['md5'] ) ) $var['md5'] = base64_encode( pack( 'H*', $var['md5'] ) );

	if( $var['com'] == '' ) unset( $var['com'] );
	if(isset($var['email'])) unset( $var['email'] );
	if( $var['sub'] == '' ) unset( $var['sub'] );

	if( !empty( $extra ) ) {
		foreach( $extra as $key => $val ) {
			$var[$key] = $val;
		}
	}

	post_json_force_type( $var );

	/// XXX: CHANGE TO TRIM WHITESPACE
	return $var;
}

function generate_index_json( $return = false )
{

	global $log, $index_rbl;
	log_cache( 0, 0 ); // generate all threads

	$threads = $log['THREADS'];

	$threadcount = count( $threads );
  
  // figure out how many replies to print
  if (defined('REPLIES_SHOWN')) {
    $replies_shown = REPLIES_SHOWN;
  }
  else {
    $replies_shown = 5;
  }
  
	// Loop through every page
	for( $page = 0; $page < $threadcount; $page += DEF_PAGES ) {
	  $file_page_num = $page / DEF_PAGES + 1;
    
    if (PAGE_MAX && $file_page_num > PAGE_MAX) {
      break;
    }
    
		$thread = $page;
		$json   = array();

		if( floor( $page / DEF_PAGES ) > $index_rbl ) return;

		for( $i = $thread; $i < $thread + DEF_PAGES; $i++ ) {
			list( $_unused, $threadid ) = each( $threads );

			if( !$threadid ) break;

      if ($log[$threadid]['sticky'] == 1) {
        $replies = min(1, $replies_shown);
      }
      else {
        $replies = $replies_shown;
      }
      
			$json[] = generate_thread_json( $threadid, true, $replies );
		}

		if( empty( $json ) ) return true; // we've reached the point of no return

		$temp = json_encode( array('threads' => $json) );

		$filename = INDEX_DIR . ($page / DEF_PAGES + 1) . '.json';
		print_page( $filename, $temp, 0, 0 );
	}

	return true;
}

//                             ↓ STICK IT TO THE MAN
function generate_board_catalogue()
{
	//$json = generate_index_json( true );

	global $log, $index_rbl;
	log_cache( 0, 0 ); // generate all threads
  
	$threads     = $log['THREADS'];
	$threadcount = count( $threads );
	$curpage     = 0;
  
	$json = array();
  
  if (defined('REPLIES_SHOWN')) {
    $replies_shown = REPLIES_SHOWN;
  }
  else {
    $replies_shown = 5;
  }
  
	for( $page = 0; $page < $threadcount; $page += DEF_PAGES ) {
		// Loop through each page...

		$thispage = $page;

		for( $i = $thispage; $i < $thispage + DEF_PAGES; $i++ ) {
			list( $_unused, $threadid ) = each( $threads );
      
			if( !$threadid ) break;
			
      if ($log[$threadid]['sticky'] == 1) {
        $replies = min(1, $replies_shown);
      }
      else {
        $replies = $replies_shown;
      }
			
			$json[$curpage][] = generate_thread_json( $threadid, false, $replies, true );

		}

		$curpage++;
	}

	$build = array();

	foreach( $json as $page => $threads ) {
		$thread = array(
			'page'    => $page + 1,
			'threads' => $threads
		);
		$build[] = $thread;
	}

	$page = json_encode($build);
	print_page( INDEX_DIR . 'catalog.json', $page, 0, 0 );
}

function generate_board_threads_json()
{
	global $log;
	log_cache( 0, 0 );

	$threads     = $log['THREADS'];
	$threadcount = count( $threads );
	$curpage     = 0;
	$json        = array();

	for( $page = 0; $page < $threadcount; $page += DEF_PAGES ) {
		$thispage = $page;

		for( $i = $thispage; $i < $thispage + DEF_PAGES; $i++ ) {
			list( $_unused, $threadid ) = each( $threads );
			
			if( !$threadid ) break;
      
			$arr = array(
        'no' => $threadid,
        'last_modified' => $log[$threadid]['last_modified'],
        'replies' => $log[$threadid]['replycount']
      );

			post_json_force_type($arr);

			$json[$curpage][] = $arr;

		}

		$curpage++;
	}

	foreach( $json as $page => $threads ) {

		$build[] = array(
			'page'    => $page + 1,
			'threads' => $threads
		);
	}

	$page = json_encode( $build );
	print_page( INDEX_DIR . 'threads.json', $page, 0, 0 );
}

function generate_board_archived_json() {
  $query = "SELECT no FROM `" . BOARD_DIR . "` WHERE archived = 1 AND resto = 0 ORDER BY no ASC";
  
  $res = mysql_board_call($query);
  
  if (!$res) {
    return false;
  }
  
  $threads = array();
  
  while ($tid = mysql_fetch_row($res)[0]) {
    $threads[] = $tid;
  }
  
  $page = '[' . implode(',', $threads) . ']';
  
	print_page(INDEX_DIR . 'archive.json', $page, 0, 0);
}
