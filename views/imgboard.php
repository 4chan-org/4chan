<?

function form( &$dat, $resno, $admin = '', $isreptemp = false, $stripm = false )
{
	global $log, $thread_unique_ips;
	log_cache( 0, $resno );

	$maxbyte = MAX_KB * 1024;
	$no      = $resno;
	$closed  = 0;
	$msg     = $hidden = '';
	$comlen  = MAX_COM_CHARS;
  
	if( $resno ) {
		$closed = $log[$resno]['closed'] || $log[$resno]['archived'];
    
		if( !$stripm ) {
			$msg .= '<div class="navLinks mobile">
	<span class="mobileib button"><a href="/' . BOARD_DIR . '/" accesskey="a">' . S_RETURN . '</a></span> <span class="mobileib button"><a href="/' . BOARD_DIR . '/catalog">' . S_CATALOG . '</a></span> <span class="mobileib button"><a href="#bottom">' . S_BOTTOM . '</a></span> <span class="mobileib button"><a href="#top_r" id="refresh_top">' . S_REFRESH . '</a></span>
</div>';
		}

		if( !$stripm ) $msg .= '<div id="mpostform"><a href="#" class="mobilePostFormToggle mobile hidden button">' . S_FORM_REPLY . '</a></div>';
	}
	else {
		if( !$stripm ) $msg .= '
<div class="navLinks mobile">
	<span class="mobileib button"><a href="#bottom">' . S_BOTTOM . '</a></span> <span class="mobileib button"><a href="/' . BOARD_DIR . '/catalog">' . S_CATALOG . '</a></span> <span class="mobileib button"><a href="#top_r" id="refresh_top">' . S_REFRESH . '</a></span>
</div>
<div id="mpostform"><a href="#" class="mobilePostFormToggle mobile hidden button">' . S_FORM_THREAD . '</a></div>';
	}

	if( $admin ) {
		$hidden = '<input type="hidden" name="admin" value="' . ADMIN_PASS . '">';
		$msg    = '<h4>' . S_NOTAGS . '</h4>';
	}

	if ($closed != 1 || (BOARD_DIR === 'qa' && $log[$resno]['capcode'] !== 'none')) {
		$dat .= $msg;
		form_ads( $dat );

		$hidden = STATS_USER_JS ? '<input type="hidden" name="hasjs" id="hasjs" class="hasjs" value="">' : '';

		$dat .= '
<form name="post" action="' . SELF_PATH_POST . '" method="post" enctype="multipart/form-data">
' . $hidden . ( !TEXT_ONLY ? ('
<input type="hidden" name="MAX_FILE_SIZE" value="' . $maxbyte . '">') : '') . '
<input type="hidden" name="mode" value="regist">
<input id="postPassword" name="pwd" type="hidden">';

		if( $no ) {
			$dat .= '
<input type="hidden" name="resto" value="' . $no . '">
';
		}
		
		if (FORCED_ANON) {
			$dat .= '
<input name="name" type="hidden">
';
		}
    
		$dat .= '<div id="togglePostFormLink" class="desktop">[<a href="#">'
		  . ($resno ? S_FORM_REPLY : S_FORM_THREAD)
		  . '</a>]</div><table class="postForm hideMobile" id="postForm">
	<tbody>
';

		$spoilers = '';
		if( SPOILERS == 1 ) {
			$spoilers = '<span class="desktop">[<label><input type="checkbox" name="spoiler" value="on" tabindex="9">' . S_SPOILERS . '</label>]</span>';
		}
		
	  if (!FORCED_ANON) {
			$dat .= '
		<tr data-type="Name">
			<td>' . S_NAME . '</td>
			<td><input name="name" type="text" tabindex="1" placeholder="' . S_ANONAME. '"></td>
    </tr>';
	  }
	  
		if ($spoilers && !$stripm && !TEXT_ONLY) {
			$dat .= '
		<tr class="mobile" data-type="Spoilers">
			<td>' . S_SPOILERS . '</td>
			<td class="mobileSpoiler">[<label><input type="checkbox" name="spoiler" value="on">' . S_SPOILERS . '</label>]</td>
		</tr>
		';
		}
    
    if (!$resno && !FORCED_ANON) {
      $attr = TEXT_ONLY ? ' required' : '';
      
      $dat .= '
      <tr data-type="Options">
        <td>' . S_EMAIL . '</td>
        <td><input name="email" type="text" tabindex="2"></td>
      </tr>
      <tr data-type="Subject">
        <td>' . S_SUBJECT . '</td>
        <td><input name="sub"' . $attr . ' type="text" tabindex="3"><input type="submit" value="' . S_SUBMIT . '" tabindex="10"></td>
      </tr>';
    }
    else {
      $dat .= '
      <tr data-type="Options">
        <td>' . S_EMAIL . '</td>
        <td><input name="email" type="text" tabindex="2"><input type="submit" value="' . S_SUBMIT . '" tabindex="10"></td>
      </tr>';
    }
    
		$dat .= '
		<tr data-type="Comment">
			<td>' . S_COMMENT . '</td>
			<td><textarea name="com" cols="48" rows="4" wrap="soft" tabindex="4"></textarea></td>
		</tr>
		';

		if (CAPTCHA == 1) {
			$dat .= '
		<tr id="captchaFormPart">
			<td class="desktop">' . S_CAPTCHA . '</td>
			<td colspan="2">' . (CAPTCHA_TWISTER ? twister_captcha_form() : captcha_form()) . '<div class="passNotice">' . S_PASS_NOTICE . '</div></td>
		</tr>
			';
		}
    
    if (ENABLE_BOARD_FLAGS) {
      $board_flags_selector = get_board_flags_selector();
      
      if (SHOW_COUNTRY_FLAGS) {
        $opts_html = '<option value="0">Geographic Location</option>';
      }
      else {
        $opts_html = '<option value="0">None</option>';
      }
      
      foreach ($board_flags_selector as $flag_code => $flag_name) {
        $opts_html .= '<option value="' . $flag_code . '">' . $flag_name . '</option>';
      }
      
      $dat .= '
		<tr data-type="Flag">
			<td>' . S_FLAG . '</td>
			<td><select name="flag" class="flagSelector">' . $opts_html . '</select></td>
		</tr>
		';
    }
    
    $need_file_form = false;
    
    if ($_GET['mode'] != 'oe_finish') {
      if ($resno) {
        if (!TEXT_ONLY && MAX_IMGRES != 0) {
          $need_file_form = true;
        }
      }
      else {
        $need_file_form = true;
      }
    }
    
		if ($need_file_form) {
			$dat .= '<tr data-type="File">
			<td>' . S_UPLOADFILE . '</td>
			<td><input id="postFile" name="upfile" type="file" tabindex="8">
			' . $spoilers;
			
			if( !$resno && NO_TEXTONLY != 1 ) {
				$dat .= '[<label><input type="checkbox" name="textonly" value="on">' . S_NOFILE . '</label>]';
			}
			
			$dat .= '</tr>';
			
			if (ENABLE_PAINTERJS) {
			  $dat .= '<tr data-type="Painter" class="desktop">
			  <td>Draw</td>
  			<td class="painter-ctrl">Size <input type="text" value="'
  			  . PAINTERJS_DIMS . '" maxlength="4"> &times; <input type="text" value="'
  			  . PAINTERJS_DIMS . '" maxlength="4"> ';
        
        if (ENABLE_OEKAKI_REPLAYS) {
          $dat .= '<label title="Generate a replay animation of your drawing"><input type="checkbox" checked class="oe-r-cb">Replay</label> ';
        }
        
        $dat .= '<button data-dims="'
  			  . PAINTERJS_DIMS . '" type="button">Draw</button> <button disabled type="button">Clear</button></td>
			  </tr>';
			}
		}
    
    if ($resno && SHOW_THREAD_UNIQUES) {
      $unique = $thread_unique_ips;
      if ($unique) {
        $unique_plural = $unique > 1 ? 'are' : 'is';
        $unique = sprintf("<li> There $unique_plural " . S_UNIQUE_POSTS_TH . '</li>', $unique, $unique > 1 ? 's' : '');
      }
      else {
        $unique = '';
      }
    }
    elseif (SHOW_UNIQUES) {
      $unique = sprintf('<li>' . S_UNIQUE_POSTS . '</li>', $GLOBALS['ipcount']);
    }
    else {
      $unique = '';
    }
    
    $blotter = get_blotter();
    
		// XXX: mode=regist moved to the top
		$dat .= '
		<tr class="rules">
			<td colspan="2">
				<ul class="rules">
					' . S_RULES . '
					' . $unique . '
				</ul>
			</td>
		</tr>
		';
		$dat .= '
	</tbody>
	<tfoot><tr><td colspan="2"><div id="postFormError"></div></td></tr></tfoot>
</table>' . $blotter . '
</form>
' . (!$resno ? EMBED_INDEX : '');
	} else { // Closed thread
		form_ads( $dat );
		if( !$stripm ) $dat .= '
<div class="navLinks mobile">
	<span class="mobileib button"><a href="/' . BOARD_DIR . '/" accesskey="a">' . S_RETURN . '</a></span> <span class="mobileib button"><a href="/' . BOARD_DIR . '/catalog">' . S_CATALOG . '</a></span> <span class="mobileib button"><a href="#bottom">' . S_BOTTOM . '</a></span> <span class="mobileib button"><a href="#top_r" id="refresh_top">' . S_REFRESH . '</a></span>
</div>
<hr class="mobile">';
    
    if ($log[$resno]['archived']) {
      $dat .= '<div class="closed">' . S_THREAD_ARCHIVED . '</div>';
    }
    else {
      $dat .= '<div class="closed">' . S_THREAD_CLOSED . '</div>';
    }
	}

	if( AD_MIDDLE_ENABLE == 1 ) {
		$middlead = "";

		if( defined( "AD_MIDDLE_TEXT" ) && AD_MIDDLE_TEXT ) {
			$middlead .= '<hr class="aboveMidAd"><div class="middlead center ad-cnt">' . ad_text_for( AD_MIDDLE_TEXT ) . '</div>' . (defined('AD_MIDDLE_PLEA') ? AD_MIDDLE_PLEA : '');
		} else if( defined( "AD_MIDDLE_TABLE" ) && AD_MIDDLE_TABLE ) {
			list( $middleimg, $middlelink ) = rid( AD_MIDDLE_TABLE, 1 );
			$middlead .= "<hr><div class=\"center\"><a href=\"$middlelink\" target=\"_blank\"><img class=\"middlead\" src=\"$middleimg\" alt=\"\"></a></div>";
		}

		if( $middlead ) {
			$dat .= "$middlead";
		}
	}
	else if (!$stripm) { // not for catalog
	  // Contest banners
	  $dat .= '<hr class="aboveMidAd"><div class="middlead center">' . get_contest_banner() . '</div>';
  }
  
  if (!$resno || !$closed) {
    list($globalmsgtxt,$globalmsgdate) = global_msg_txt();
    
    if( $globalmsgtxt ) {
      $dat .= "\n<hr><a href=\"#\" class=\"button redButton mobile hidden\" id=\"globalToggle\">" . S_VIEW_GMSG . "</a><div class=\"globalMessage hideMobile\" id=\"globalMessage\" data-utc=\"$globalmsgdate\">" . $globalmsgtxt . "</div>\n";
    }
  }
  
  // Catalog
  if ($stripm) {
    if (defined('AD_CUSTOM_TOP') && AD_CUSTOM_TOP) {
      $dat .= '<div><hr>' . AD_CUSTOM_TOP . '</div>';
    }
    /*else if (defined('AD_ABC_TOP_DESKTOP') && AD_ABC_TOP_DESKTOP) {
      $dat .= '<div class="adg-rects desktop"><hr><div class="adg adp-90" data-abc="' . AD_ABC_TOP_DESKTOP . '"></div></div>';
    }
    else if (defined('AD_BIDGEAR_TOP') && AD_BIDGEAR_TOP)  {
      $dat .= '<div class="adc-resp-bg" data-ad-bg="' . AD_BIDGEAR_TOP . '"><hr></div>';
    }*/
	  else if (defined('ADS_DANBO') && ADS_DANBO)  {
	    $dat .= '<hr><div id="danbo-s-t" class="danbo-slot"></div><div class="adl">[<a target="_blank" href="https://www.4chan.org/advertise">Advertise on 4chan</a>]</div>';
	  }
  }
  // Not catalog
  else if (defined('AD_CUSTOM_TOP') && AD_CUSTOM_TOP) {
    $dat .= '<div><hr>' . AD_CUSTOM_TOP . '</div>';
  }
  /*else if (defined('AD_ABC_TOP_DESKTOP') && AD_ABC_TOP_DESKTOP) {
    $dat .= '<div class="adg-rects desktop"><hr><div class="adg adp-90" data-abc="' . AD_ABC_TOP_DESKTOP . '"></div></div>';
  }
  else if (defined('AD_BIDGEAR_TOP') && AD_BIDGEAR_TOP)  {
    $dat .= '<div class="adc-resp-bg" data-ad-bg="' . AD_BIDGEAR_TOP . '"><hr></div>';
  }*/
  else if (defined('ADS_DANBO') && ADS_DANBO)  {
    $dat .= '<hr><div id="danbo-s-t" class="danbo-slot"></div><div class="adl">[<a target="_blank" href="https://www.4chan.org/advertise">Advertise on 4chan</a>]</div>';
  }
  
	if ($resno) {
		$dat .= '<hr class="desktop" id="op">
<div class="navLinks desktop">
	[<a href="/' . BOARD_DIR . '/" accesskey="a">' . S_RETURN . '</a>] [<a href="/' . BOARD_DIR . '/catalog">' . S_CATALOG . '</a>] [<a href="#bottom">' . S_BOTTOM . '</a>]
</div>
		';
	}
	
	if( JANITOR_BOARD == 1 ) {
		$dat = broomcloset_form( $dat );
	}
	
}

function updatelog_real( $resno = 0, $noidx = 0, $lazy_rebuild = false )
{
	global $log, $mode, $index_rbl, $board_flags_array;
	
	if (ENABLE_BOARD_FLAGS) {
	  $board_flags_array = get_board_flags_array();
  }
	
	if( !IS_REBUILDD ) set_time_limit( 60 );
	
	// DDOS Protection
	if( $_SERVER['REQUEST_METHOD'] == 'GET' && !has_level() ) {
		die();
	}

	if( STATIC_REBUILD && $mode != 'nothing' ) {
		$noidx = 1;
	}

	if( !$resno && $noidx ) {
		return;
	}
	
	log_cache( 0, $noidx ? $resno : 0 );
	
	// Image directories
	/*
	$imgdir = ( ( USE_SRC_CGI == 1 ) ? str_replace( 'src', 'src.cgi', IMG_DIR2 ) : IMG_DIR2 );
	if( defined( 'INTERSTITIAL_LINK' ) ) {
		$imgdir .= INTERSTITIAL_LINK;
	}
	*/
	
	$resno = (int)$resno;
  
  $inter_ad_html = null;
  
	if( $resno ) {
		if( !isset( $log[$resno] ) ) {
			updatelog_real( 0, $noidx );

			return;
		} elseif( $log[$resno]['resto'] ) {
			updatelog_real( $log[$resno]['resto'], $noidx );

			return;
		}
    
    // Inter-reply ads
		$inter_ad_html = '';
    
    if (defined('AD_ABC_TOP_MOBILE') && AD_ABC_TOP_MOBILE) {
      $inter_ad_html .= '<div class="adg-rects mobile"><hr><div class="adg-m adp-250" data-abc="' . AD_ABC_TOP_MOBILE . '"></div><hr></div>';
    }
		
		if (defined('AD_ABC_TOP_DESKTOP') && AD_ABC_TOP_DESKTOP) {
		  $inter_ad_html .= '<div class="adg-rects desktop adg-rep"><hr><div class="adg adp-90" data-abc="' . AD_ABC_TOP_DESKTOP . '"></div><hr></div>';
		}
		
		if ($inter_ad_html === '') {
			$inter_ad_html = null;
		}
	}
  
	if( $resno ) {
		logtime( "Generating thread JSON" );
		
    if (ENABLE_JSON) {
      $tailSize = get_json_tail_size($resno);
      
      if ($tailSize) {
        generate_thread_json($resno, false, false, false, $tailSize);
      }
      
      generate_thread_json($resno);
    }
	  
		$treeline = array($resno);
		logtime( "Formatting thread page" );
	} else {
		logtime( "Generating index JSON" );
		if( ENABLE_JSON_INDEXES ) generate_index_json();
		if( ENABLE_JSON_CATALOG ) generate_board_catalogue();
		if( ENABLE_JSON_THREADS ) {
		  generate_board_threads_json();
	  }

		$treeline = $log['THREADS'];
		logtime( "Formatting index page" );
	}

	$counttree = count( $treeline );
	if( !$counttree ) {
		$logfilename = SELF_PATH2_FILE;
		$dat         = '';
		head( $dat, $resno );
		form( $dat, $resno );
		print_page( $logfilename, $dat );
		$dat = '';
	}

	$st = 0;
	$p  = 0;
	
	if ($lazy_rebuild) {
		$start_page = $index_rbl * DEF_PAGES;
	}
	else {
		$start_page = 0;
	}
	
  $index_page_ad_pos = ceil(DEF_PAGES / 2);
  
  if (defined('REPLIES_SHOWN')) {
    $shown_replies_default = REPLIES_SHOWN;
  }
  else {
    $shown_replies_default = 5;
  }
  
	for( $page = $start_page; $page < $counttree; $page += DEF_PAGES ) {
	  $file_page_num = $page / DEF_PAGES + 1;
    
    if (PAGE_MAX && $file_page_num > PAGE_MAX) {
      break;
    }
    
		$dat = '';
		head( $dat, $resno, 0, $page, $counttree );
		form( $dat, $resno );
		if( !$resno ) {
			$st = $page;
      
      $dat .= '<div id="ctrl-top" class="desktop"><hr><input type="text" id="search-box" placeholder="'. S_SEARCH .
        '"> [<a href="' . SELF_PATH2 . 'catalog">' . S_CATALOG . '</a>]';
      
      if (ENABLE_ARCHIVE) {
        $dat .= ' [<a href="' . SELF_PATH2 . 'archive">' . S_ARCHIVE . '</a>]';
      }
      
      $dat .= '</div>';
      
			if (floor( $page / DEF_PAGES ) > $index_rbl) {
			  return;
		  }
		}
		
		// Post form / board container container start.
		$dat .= '<hr>
<form name="delform" id="delform" action="' . SELF_PATH_ABS . '" method="post">
<div class="board">
';
    
    $index_page_th_id = 0;
    
		for( $i = $st; $i < $st + DEF_PAGES; $i++ ) {
			$no = $treeline[$i];
			
			if (!$no) {
				break;
			}
			/*
			if (!isset($log[$no]['children'])) {
				log_bad_cache_entry($no);
			}
			*/
			$sorted_replies = $log[$no]['children'];
			ksort($sorted_replies);
			
			// Party hats
			$party = PARTY ? '<img src="' . STATIC_IMG_DIR2 . PARTY_IMAGE
				. '" class="party-hat">' : '';
			
			// Omitted replies
			$reply_count = $log[$no]['replycount'];
			
			if ($log[$no]['sticky'] == 1) {
				$shown_replies = min(1, $shown_replies_default);
			}
			else {
        $shown_replies = $shown_replies_default;
      }
      
			// Open thread tag and render OP
			$dat .= '<div class="thread" id="t' . $no . '">'
				. $party
				. renderPostHtml($no, $resno, $sorted_replies, $reply_count, $shown_replies);
			
			// Render replies
			if ($resno) {
				$s = 0;
			}
			else {
				$s = $reply_count - $shown_replies;
			}
			
			$repCount = 0;
			
			if ($inter_ad_html && $reply_count >= 100) {
				$middle_reply_idx = floor($reply_count / 2.0);
			}
			else {
				$middle_reply_idx = 0;
			}
			
			while (list($resrow) = each($sorted_replies)) {
				if( $s > 0 ) {
					$s--;
					continue;
				}
				
				if (!$log[$resrow]['no']) {
					break;
				}
				
				$dat .= renderPostHtml($resrow, $resno);
				
				$repCount++;
				
				if ($repCount == $middle_reply_idx) {
					$dat .= $inter_ad_html;
				}
			}
			
			// Close thread tag
			$dat .= '
	</div>
	<hr>
';
      ++$index_page_th_id;
      
      if (!$resno && AD_INTERTHREAD_ENABLED && $index_page_ad_pos == $index_page_th_id) {
        $dat .= AD_INTERTHREAD_TAG . '<hr>';
      }
      
			$p++;
			
			if ($resno) {
				break;
			}
		}
		
		if ($resno) {
			$dat .= '<div class="navLinks navLinksBot desktop">[<a href="/' . BOARD_DIR . '/" accesskey="a">' . S_RETURN . '</a>] [<a href="/' . BOARD_DIR . '/catalog">' . S_CATALOG . '</a>] [<a href="#top">' . S_TOP . '</a>] </div><hr class="desktop">';
		}
		
		// Close board tag
		$lang = $resno ? S_FORM_REPLY : S_FORM_THREAD;

		$dat .= '
		<div class="mobile center"><a class="mobilePostFormToggle button" href="#">' . $lang . '</a></div>
</div>';

		if (!$resno) {
			$dat .= '<div class="navLinks navLinksBot mobile"><span class="mobileib button"><a href="#top">' . S_TOP . '</a></span> <span class="mobileib button"><a href="#bottom_r" id="refresh_bottom">' . S_REFRESH . '</a></span></div><hr class="mobile">';
		}
		else {
			$dat .= '<div class="navLinks mobile"><span class="mobileib button"><a href="/' . BOARD_DIR . '/" accesskey="a">' . S_RETURN . '</a></span> <span class="mobileib button"><a href="/' . BOARD_DIR . '/catalog">' . S_CATALOG . '</a></span> <span class="mobileib button"><a href="#top">' . S_TOP . '</a></span> <span class="mobileib button"><a href="#bottom_r" id="refresh_bottom">' . S_REFRESH . '</a></span></div><hr class="mobile">';
		}
    
    /**
     * ADS
     */
    
    if (defined('AD_CUSTOM_BOTTOM') && AD_CUSTOM_BOTTOM) {
      $dat .= '<div>' . AD_CUSTOM_BOTTOM . '<hr></div>';
    }
    /*
    else if (defined('AD_ABC_BOTTOM_MOBILE') && AD_ABC_BOTTOM_MOBILE) {
      $dat .= '<div class="adg-rects mobile"><div class="adg-m adp-250" data-abc="' . AD_ABC_BOTTOM_MOBILE . '"></div><hr></div>';
    }
    else if (defined('AD_BIDGEAR_BOTTOM') && AD_BIDGEAR_BOTTOM)  {
      $dat .= '<div class="adc-resp-bg" data-ad-bg="' . AD_BIDGEAR_BOTTOM . '"></div>';
    }
    */
    else if (defined('ADS_DANBO') && ADS_DANBO)  {
      $dat .= '<div id="danbo-s-b" class="danbo-slot"></div><div class="adl">[<a target="_blank" href="https://www.4chan.org/advertise">Advertise on 4chan</a>]</div><hr>';
    }
    
		if( $resno ) {
			$resredir = '<input type="hidden" name="res" value="' . $resno . '">';
		} else {
			$resredir = '';
		}
    
		$dat .= '<div class="bottomCtrl desktop"><span class="deleteform"><input type="hidden" name="mode" value="usrdel">' . S_REPDEL . $resredir . ' [<input type="checkbox" name="onlyimgdel" value="on">' . S_DELPICONLY . ']<input type="hidden" id="delPassword" name="pwd"> <input type="submit" value="' . S_DELETE . '"><input id="bottomReportBtn" type="button" value="Report"></span>';
		
		if( !defined( 'CSS_FORCE' ) ) {
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
    
		if( !$resno ) {
			$prev = $st - DEF_PAGES;
			$next = $st + DEF_PAGES;

			$dat .= '<div class="pagelist desktop"><div class="prev">';
			$mpl = '<div class="mPagelist mobile">';

			if( $prev >= 0 ) {
				$link = ( $prev == 0 ) ? SELF_PATH2 : ( $prev / DEF_PAGES + 1 ) . PHP_EXT2;
				$dat .= '<form class="pageSwitcherForm" action="' . $link . '">';
				$dat .= '<input type="submit" value="' . S_PREV . '" accesskey="z"></form>';

				$mprev = '<div class="prev"><a href="' . $link . '" class="button">' . S_PREV . '</a></div>';
			} else {
				$dat .= '<span>' . S_FIRSTPG . '</span> ';
				$mprev = '';
			}

			$dat .= '</div><div class="pages">';
			$mpl .= '<div class="pages">';

			for( $i = 0; $i < $counttree; $i += DEF_PAGES ) {
			  $switcher_page_num = $i / DEF_PAGES + 1;
			  
			  if (PAGE_MAX && $switcher_page_num > PAGE_MAX) {
			    break;
			  }
			  
				if( $st == $i ) {
					$dat .= '[<strong><a href="">' . $switcher_page_num . '</a></strong>] ';

					$mpl .= '<span>[<strong><a href="">' . $switcher_page_num . '</a></strong>]</span> ';
				} else {
					if( $i == 0 ) {
						$dat .= '[<a href="' . SELF_PATH2 . '">1</a>] ';
						$mpl .= '<span>[<a href="' . SELF_PATH2 . '">1</a>]</span> ';
					} else {
						$dat .= '[<a href="' . $switcher_page_num . PHP_EXT2 . '">' . $switcher_page_num . '</a>] ';
						$mpl .= '<span>[<a href="' . $switcher_page_num . PHP_EXT2 . '">' . $switcher_page_num . '</a>]</span> ';
					}
				}
			}

			for( ; ( PAGE_MAX > 0 ) && $i < PAGE_MAX * DEF_PAGES; $i += DEF_PAGES ) {
				$dat .= '[' . ( $i / DEF_PAGES + 1 ) . '] ';
				$mpl .= '<span>[' . ( $i / DEF_PAGES + 1 ) . ']</span> ';
			}

			$dat .= '</div><div class="next">';
			$mpl .= '<div class="mobileCatalogLink">[<a href="' . SELF_PATH2 . 'catalog">' . S_CATALOG . '</a>]</div></div>' . $mprev;
			
			

			if( $p >= DEF_PAGES && $counttree > $next && $file_page_num != PAGE_MAX) {
				$dat .= '<form class="pageSwitcherForm" action="' . ($next / DEF_PAGES + 1) . PHP_EXT2 . '">';
				$dat .= '<input type="submit" value="' . S_NEXT . '" accesskey="x"></form>';

				$mpl .= '<div class="next"><a href="' . ($next / DEF_PAGES + 1) . PHP_EXT2 . '" class="button">' . S_NEXT . '</a></div>';
			} else {
				$dat .= '<span>' . S_LASTPG . '</span>';
			}

			$catanav = ENABLE_CATALOG ? '<div class="pages cataloglink"><a href="' . SELF_PATH2 . 'catalog">' . S_CATALOG . '</a></div>' : '';
			
			if (ENABLE_ARCHIVE) {
			  $catanav .= '<div class="pages cataloglink"><a href="'
			    . SELF_PATH2 . 'archive">' . S_ARCHIVE . '</a></div>';
			}
			
			// Close page navigator
			$dat .= '</div>' . $catanav . '</div>';
			$dat .= $mpl . '</div>';
		}
		
		foot( $dat );

		if( $resno ) {
			logtime( 'Printing thread ' . $resno . ' page' );
			$logfilename = RES_DIR . $resno . PHP_EXT;
			print_page( $logfilename, $dat );
			$dat = '';

			if( !$noidx ) {
				updatelog_real( 0 );
			}

			break;
		}

		logtime( "Printing index page" );
    if ($page == 0) {
      $logfilename = SELF_PATH2_FILE;
      print_page($logfilename, $dat);
    }
    else {
      $logfilename = INDEX_DIR . ($file_page_num) . PHP_EXT;
      print_page($logfilename, $dat);
    }

		$dat = '';

		if( !$resno && $page == 0 && USE_RSS == 1 ) {
			include_once 'lib/rss.php';
			rss_dump();
		}
	} // for
}

//wrapper function for forwarding updatelog calls
//resno - thread page to update (no of thread OP)
//noidx - don't rebuild page indexes
function updatelog( $resno = 0, $noidx = 0, $lazy_rebuild = false )
{
	updatelog_real( $resno, $noidx, $lazy_rebuild );

	if( !STATIC_REBUILD && ENABLE_CATALOG && !$noidx ) generate_catalog( true );
}

?>