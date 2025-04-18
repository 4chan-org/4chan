<?

function upboard_tags()
{
	// if you add tags to this, make sure to keep the numbers the same
	// (they can be out of order)
	static $tags = array(
				  0 => array("long" => "Hentai",   "short" => "[H]"),
				  6 => array("long" => "Porn", 	   "short" => '[P]'),
				  1 => array("long" => "Japanese", "short" => "[J]"),
				  2 => array("long" => "Anime", "short" => "[A]"),
				  3 => array("long" => "Game",  "short" => "[G]"),
				  5 => array("long" => "Loop",  "short" => "[L]"),
				  4 => array("long" => "Other", "short" => "[?]")
	);
	
	return $tags;
}

function updatelog( $resno = 0, $rebuild = 0 )
{
	$tags = upboard_tags();

	$imgdir   = IMG_DIR2;
	$thumbdir = THUMB_DIR2;
	$imgurl   = STATIC_IMG_DIR2;
	$sqlog    = SQLLOG; // to make this a shite load easier

	$find  = false;
	$resno = (int)$resno;
	
	log_cache();
	
	if( $resno ) {
		$result = mysql_board_call( "SELECT `no` FROM `$sqlog` WHERE `root` > 0 AND `no` = '$resno'" );
		if( $result ) {
			$find = mysql_fetch_row( $result );
			mysql_free_result( $result );
		}

		if( !$find ) {
			$result2 = mysql_board_call( "SELECT `no`, `resto` FROM `$sqlog` WHERE `no` = '$resno'" );

			list( $chkno, $resto ) = mysql_fetch_row( $result2 );
			if( !$resto ) {
				error( S_REPORTERR );
			}

			mysql_free_result( $result2 );

			$result3 = mysql_board_call( "SELECT `no` FROM `$sqlog` WHERE `no` = '$resto'" );
			if( $result3 ) {
				$chkfind = mysql_fetch_row( $result3 );
				mysql_free_result( $result3 );
			}

			if( !$chkfind ) {
				error( S_REPORTERR );
			}
		}
	}

	if( $resno ) {
		if( !$treeline = mysql_board_call( "SELECT * FROM `$sqlog` WHERE `root` > 0 AND `no` = '$resno' ORDER BY `root` DESC" ) ) {
			echo S_SQLFAIL;
		}


	} else {
		if( !$treeline = mysql_board_call( "SELECT * FROM `$sqlog` WHERE `root` > 0 ORDER BY `root` DESC" ) ) {
			echo S_SQLFAIL;
		}
	}

	if( $resno ) {
		//logtime("Generating thread JSON");
		if( ENABLE_JSON ) generate_thread_json( $resno );

		//$treeline = array( $resno );
		//logtime("Formatting thread page");
	} else {
		//logtime("Generating index JSON");
		if( ENABLE_JSON_INDEXES ) generate_index_json();
		if( ENABLE_JSON_CATALOG ) generate_board_catalogue();
		if( ENABLE_JSON_THREADS ) generate_board_threads_json();

		//$treeline = $log['THREADS'];
		//logtime("Formatting index page");
	}

	if( !$result = mysql_board_call( "SELECT MAX(`no`) FROM `$sqlog`" ) ) {
		echo S_SQLFAIL;
	}

	$row    = mysql_fetch_array( $result );
	$lastno = (int)$row[0];
	mysql_free_result( $result );

	$counttree = mysql_num_rows( $treeline );

	if( !$counttree ) {
		$logfilename = SELF_PATH2_FILE;
		$dat         = '';

		head( $dat, $resno );
		form( $dat, $resno );
		print_page( $logfilename, $dat );
	}

	$page = 0;
	$dat  = '';

	head( $dat, $resno );
	form( $dat, $resno );

	if( !$resno ) {
		$st = $page;
	}

	$dat .= '<hr style="clear:both"><form name="delform" action="' . SELF_PATH_ABS . '" method="post" id="delform"><div class="board">';

	// here we go
	if( !$resno ) {
		$dat .= <<<HTML
	<table class="flashListing">
		<tr>
			<td class="postblock">No.</td>
			<td class="postblock">Name</td>
			<td class="postblock">File</td>
			<td class="postblock">Tag</td>
			<td class="postblock">Subject</td>
			<td class="postblock">Size</td>
			<td class="postblock">Date</td>
			<td class="postblock">Replies</td>
			<td class="postblock"></td>
		</tr>
HTML;
	}

	$delarr = array();
	$limit  = (int)round( DEF_PAGES * 0.83 );
	$lim    = DEF_PAGES - $limit;

	if( !$result = mysql_board_call( "SELECT COUNT(*) FROM `$sqlog` WHERE `resto` = '0'" ) ) {
		echo S_SQLFAIL;
	}

	$row     = mysql_fetch_array( $result );
	$countth = (int)$row[0];

	if( $limit < $countth ) {
		if( !$result = mysql_board_call( "SELECT `no` FROM `$sqlog` WHERE `resto` = '0' ORDER BY `no` ASC LIMIT $lim" ) ) {
			echo S_SQLFAIL;
		}

		while( $row = mysql_fetch_array( $result ) ) {
			$delarr[] = (int)$row[0];
		}
	}

	mysql_free_result( $result );

	for( $i = $st; $i < $st + DEF_PAGES; $i++ ) { // NO PAGES FOR /f/ (apparently!)
		//if( !mysql_fetch_assoc($treeline) ) continue;

		$thistree = mysql_fetch_assoc( $treeline );
		if( !$thistree ) break;

		extract( $thistree );
		//list($no,$sticky,$permasage,$closed,$now,$name,$email,$sub,$com,$host,$pwd,$filename,$ext,$w,$h,$tn_w,$tn_h,$tim,$time,$md5,$fsize,$root,$resto,$filedeleted,$tmd5,$id,$capcode)=mysql_fetch_row($treeline);

		$tag_matches = array();
		$tag         = 4;

		if( preg_match( '/^(\d+)\|/', $sub, $tag_matches ) ) {
			$tag = (int)( $tag_matches[1] );
			$sub = preg_replace( '/^(\d+)\|/', '', $sub );
		}

		if( $resno ) {
			if( !$no ) {
				break;
			}


			$sub = str_replace( '&#44;', ',', $sub );

			$emailstart = $emailend = '';
			//if( $email != '' ) {
			//	$email = emailencode($email);
			//	$emailstart = '<a href="mailto:' . $email . '" class="useremail">';
			//	$emailend   = '</a>';
			//}

			// NEW CAPCODE STUFF

			switch( $capcode ) {
				case 'admin':
					$capcodeStart  = ' <strong class="capcode">## Admin</strong>';
					$capcode_class = ' capcodeAdmin';

					$capcode   = ' <img src="' . $imgurl . 'adminicon.gif" alt="This user is the 4chan Administrator." title="This user is the 4chan Administrator." class="identityIcon">';
					$highlight = '';
					break;

				case 'admin_highlight':
					$capcodeStart  = ' <strong class="capcode">## Admin</strong>';
					$capcode_class = ' capcodeAdmin';

					$capcode   = ' <img src="' . $imgurl . 'adminicon.gif" alt="This user is the 4chan Administrator." title="This user is the 4chan Administrator." class="identityIcon">';
					$highlight = ' highlightPost';
					break;

				case 'mod':
					$capcodeStart  = ' <strong class="capcode">## Mod</strong>';
					$capcode_class = ' capcodeMod';

					$capcode   = ' <img src="' . $imgurl . 'modicon.gif" alt="This user is a 4chan Moderator." title="This user is a 4chan Moderator." class="identityIcon">';
					$highlight = '';
					break;

				case 'developer':
					$capcodeStart  = ' <strong class="capcode">## Developer</strong>';
					$capcode_class = ' capcodeDeveloper';

					$capcode   = ' <img src="' . $imgurl . 'developericon.gif" alt="This user is a 4chan Developer." title="This user is a 4chan Developer." class="identityIcon">';
					$highlight = '';
					break;
				
				case 'manager':
					$capcodeStart  = ' <strong class="capcode hand id_manager" title="Highlight posts by Managers">## Manager</strong>';
					$capcode_class = ' capcodeManager';

					$capcode   = ' <img src="' . $imgurl . 'managericon.gif" alt="Manager Icon" title="This user is a 4chan Manager." class="identityIcon retina">';
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

			$com = auto_link( $com, $resno );

			$subshort = $sub;
			if( mb_strlen( $sub ) > 28 ) {
				$subshort = '<span title="' . $sub . '">' . mb_substr( $sub, 0, 25, 'UTF-8'  ) . '(...)</span>';
			}

			$file = '';
			if( $ext ) {
				$img = IMG_DIR . $tim . $ext;

				$displaysrc = $imgdir . rawurlencode( $filename ) . $ext;

				$src = IMG_DIR . $filename . $ext;

				$longname = $filename . $ext;

				if( $fsize >= 1048576 ) {
					$size = round( $fsize / 1048576, 2 ) . ' M';
				} elseif( $fsize >= 1024 ) {
					$size = round( $fsize / 1048 ) . ' K';
				} else {
					$size = $fsize . ' ';
				}

				if( $filedeleted ) {
					$imgsrc = '[<b>File Deleted</b>]';
					$class  = '';
				} else {
					$imgsrc = '<span class="fileText" id="fT' . $no . '">' . S_PICNAME . ': <a data-width="' . $w . '" data-height="' . $h . '" href="' . $displaysrc . '" target="_blank">' . $longname . '</a>-(' . $size . 'B, ' . $w . 'x' . $h . ', ' . $tags[$tag]['long'] . ')';
					$class  = ' class="fileInfo"';
				}

				$imgClassStart = ( $imgsrc == '' ) ? '' : '<div class="fileInfo">';
				$imgClassEnd   = ( $imgsrc == '' ) ? '' : '</div>';

				$file = <<<HTML
				<div class="file" id="f$no">
					$imgClassStart
						$imgsrc
					$imgClassEnd
				</div>
HTML;
			}

			// Main creatio
			if( $sticky == 1 ) {
				$threadmodes .= ' <img src="' . $imgurl . 'sticky.gif" alt="Sticky" title="Sticky" style="height: 18px; width: 18px;"/>';
			}

			if( $closed == 1 ) {
				$threadmodes .= ' <img src="' . $imgurl . 'closed.gif" alt="Closed" title="Closed" style="height: 18px; width: 18px;"/>';
			}


			$href  = ( $resno ) ? $no . PHP_EXT2 : RES_DIR2 . $no . PHP_EXT2;
			$quote = ( $resno ) ? 'javascript:quote(\'' . $no . '\');' : $href . '#q' . $no;

			$extra = '';

			$stickies = array();
			if( !$result = mysql_board_call( "SELECT `no` FROM `$sqlog` WHERE `sticky` = '1'" ) ) {
				echo S_SQLFAIL;
			}

			while( $stickrow = mysql_fetch_row( $result ) ) {
				list( $stickno ) = $stickrow;
				$stickies[] = $stickno;
			}

			if( in_array( $no, $delarr ) ) {
				if( in_array( $no, $stickies ) ) {
					$stuck = 1;
				}

				if( $stuck != 1 ) {
					$extra = '<span class="oldpost">' . S_OLD . '</span>';
				}
			}


			$dat .= <<<HTML
	<div class="thread" id="t$no">
		<div class="postContainer opContainer" id="pc$no">
			<div id="p$no" class="post op$highlight">
				<div class="postInfoM mobile" id="pim$no">
					<span class="nameBlock$capcode_class">
						$emailstart<span class="name">$name</span>$capcodeStart$emailend$dispuid$countryFlag$threadmodes<br>
						<span class="subject">$subshort</span>
					</span>

					<span class="dateTime postNum" data-utc="$time">$now<br><em><a href="$href#p$no">No.</a><a href="$quote">$no</a></em></span>
				</div>

				$file

				<div class="postInfo desktop" id="pi$no">
					<input type="checkbox" name="$no" value="delete"> 
					<span class="subject">$sub</span> 
					<span class="nameBlock$capcode_class">
						$emailstart<span class="name">$name</span>$capcodeStart$emailend$dispuid$countryFlag
					</span> 

					<span class="dateTime" data-utc="$time">$now</span> 

					<span class="postNum">
						<a href="$href#p$no" title="Link to this post">No.</a><a href="$quote" title="Reply to this post">$no</a>$threadmodes$postinfo_extra
					</span>

				</div>

				<blockquote class="postMessage" id="m$no">$com</blockquote>
			</div>
			
			$postInfo
		</div>

		$extra
			
HTML;

			if( !$resline = mysql_board_call( "SELECT * FROM `$sqlog` WHERE `resto` = '$no' ORDER BY `no`" ) ) {
				echo S_SQLFAIL;
			}

			$countres = mysql_num_rows( $resline );
			$s        = 0;


			while( $resrow = mysql_fetch_assoc( $resline ) ) {
				extract( $resrow );
				//list($no,$sticky,$permasage,$closed,$now,$name,$email,$sub,$com,$host,$pwd,$filename,$ext,$w,$h,$tn_w,$tn_h,$tim,$time,$md5,$fsize,$root,$resto)=$resrow;


				if( !$no ) {
					break;
				}

				$emailstart = $emailend = '';
				//if( $email != '' ) {
				//	$email = emailencode($email);
				//	$emailstart = '<a href="mailto:' . $email . '" class="useremail">';
				//	$emailend   = '</a>';
				//}

				// NEW CAPCODE STUFF

				switch( $capcode ) {
					case 'admin':
						$capcodeStart  = ' <strong class="capcode">## Admin</strong>';
						$capcode_class = ' capcodeAdmin';

						$capcode   = ' <img src="' . $imgurl . 'adminicon.gif" alt="This user is the 4chan Administrator." title="This user is the 4chan Administrator." class="identityIcon">';
						$highlight = '';
						break;

					case 'admin_highlight':
						$capcodeStart  = ' <strong class="capcode">## Admin</strong>';
						$capcode_class = ' capcodeAdmin';

						$capcode   = ' <img src="' . $imgurl . 'adminicon.gif" alt="This user is the 4chan Administrator." title="This user is the 4chan Administrator." class="identityIcon">';
						$highlight = ' highlightPost';
						break;

					case 'mod':
						$capcodeStart  = ' <strong class="capcode">## Mod</strong>';
						$capcode_class = ' capcodeMod';

						$capcode   = ' <img src="' . $imgurl . 'modicon.gif" alt="This user is a 4chan Moderator." title="This user is a 4chan Moderator." class="identityIcon">';
						$highlight = '';
						break;

					case 'developer':
						$capcodeStart  = ' <strong class="capcode">## Developer</strong>';
						$capcode_class = ' capcodeDeveloper';

						$capcode   = ' <img src="' . $imgurl . 'developericon.gif" alt="This user is a 4chan Developer." title="This user is a 4chan Developer." class="identityIcon">';
						$highlight = '';
						break;
				
					case 'manager':
						$capcodeStart  = ' <strong class="capcode hand id_manager" title="Highlight posts by Managers">## Manager</strong>';
						$capcode_class = ' capcodeManager';
	
						$capcode   = ' <img src="' . $imgurl . 'managericon.gif" alt="Manager Icon" title="This user is a 4chan Manager." class="identityIcon retina">';
						$highlight = '';
						break;
	
					default:
						$capcode = $capcodeStart = $highlight = $capcode_class = '';
						break;
				}
        
				$com = auto_link( $com, $no );

				$subshort = $sub;

				if( mb_strlen( $sub ) > 28 ) {

					$subshort = '<span title="' . $sub . '">' . mb_substr( $sub, 0, 25, 'UTF-8'  ) . '(...)</span>';

				}

				$href  = ( $resno ) ? $resto . PHP_EXT2 : RES_DIR2 . $resto . PHP_EXT2;
				$quote = ( $resno ) ? 'javascript:quote(\'' . $no . '\');' : $href . '#q' . $no;


				$dat .= <<<HTML

				

		<div class="postContainer replyContainer" id="pc$no">
			<div class="sideArrows" id="sa$no">&gt;&gt;</div>
			<div id="p$no" class="post reply$highlight">
				<div class="postInfoM mobile" id="pim$no">
					<span class="nameBlock$capcode_class">
						$emailstart<span class="name">$name</span>$capcodeStart$emailend$dispuid$countryFlag$threadmodes<br>
						<span class="subject">$subshort</span> 
					</span>

					<span class="dateTime postNum" data-utc="$time">$now<br><em><a href="$href#p$no" title="Link to this post">No.</a><a href="$quote" title="Reply to this post">$no</a></em></span>
				</div>

				<div class="postInfo desktop" id="pi$no">
					<input type="checkbox" name="$no" value="delete"> 
					<span class="subject">$sub</span> 
					<span class="nameBlock$capcode_class">
						$emailstart<span class="name">$name</span>$capcodeStart$emailend $dispuid$countryFlag
					</span> 

					<span class="dateTime" data-utc="$time">$now</span> 

					<span class="postNum desktop">
						<a href="$href#p$no" title="Link to this post">No.</a><a href="$quote" title="Reply to this post">$no</a>
					</span>

				</div>
				<blockquote class="postMessage" id="m$no">$com</blockquote>
			</div>
		</div>
HTML;
			}

			// end thread
			$dat .= '</div><hr>';

			//clearstatcache();
			mysql_free_result( $resline );
			$p++;
			break;
		} else {

			/** BUILD /f/ INDEX **/

			if( !$resline = mysql_board_call( "SELECT * FROM `$sqlog` WHERE `resto` = '$no' ORDER BY `no`" ) ) {
				echo S_SQLFAIL;
			}

			$countres = mysql_num_rows( $resline );

			if( $fsize >= 1048576 ) {
				$kbsize = round( ( $fsize / 1048576 ), 2 ) . ' M';
			} elseif( $fsize >= 1024 ) {
				$kbsize = round( $fsize / 1024 ) . ' K';
			} else {
				$kbsize = $fsize . ' ';
			}
			$kbsize .= 'B';

			$emailstart = $emailend = '';


			//if( $email != '' ) {
			//	$email = emailencode($email);
			//	$emailstart = '<a href="mailto:' . $email . '" class="useremail">';
			//	$emailend   = '</a>';
			//}

			if( mb_strlen( $filename ) > 25 ) {
				$shortname = mb_substr( $filename, 0, 25, 'UTF-8'  ) . "(...)";
			} else {
				$shortname = $filename;
			}

			if( $kbsize != '0 B' ) {
				$class = '';
				if( in_array( $no, $delarr ) ) {
					$class = 'class="oldpost"';
				}


				$texttag = $tags[$tag]['short'];

				if ($sub != '') {
					$sub = str_replace('&#44;', ',', $sub);

					if (mb_strlen($sub) > 30) {
						$shortsub = mb_substr($sub, 0, 30, 'UTF-8') . '(...)';
					}
					else {
						$shortsub = $sub;
					}
				}
				else {
					$com = str_replace('&#44;', ',', $com);
					$com = explode('<', $com)[0];
					if (mb_strlen($com) > 30) {
						$shortsub = mb_substr($com, 0, 30, 'UTF-8') . '(...)';
					}
					else {
						$shortsub = $com;
					}
				}

				$replink  = RES_DIR2 . $no . PHP_EXT2;
				
				$semantic_url = generate_href_context($sub, $com);
				
				if ($semantic_url != '') {
					$semantic_url = "/$semantic_url";
				}
				
				$filelink = IMG_DIR2 . rawurlencode( $filename ) . '.swf';


				// NEW CAPCODE STUFF

				if( $capcode === 'admin' ) {

					$capcodeStart = ' <strong class="capcode capcodeAdmin">## Admin</strong>';

					$capcode_class = ' capcodeAdmin';


					$capcode = ' <img src="' . $imgurl . 'adminicon.gif" alt="This user is the 4chan Administrator." title="This user is the 4chan Administrator." class="identityIcon">';

					$highlight = '';

				} elseif( $capcode === 'mod' ) {

					$capcodeStart = ' <strong class="capcode capcodeAdmin">## Mod</strong>';

					$capcode_class = ' capcodeMod';


					$capcode = ' <img src="' . $imgurl . 'modicon.gif" alt="This user is a 4chan Moderator." title="This user is a 4chan Moderator." class="identityIcon">';

					$highlight = '';

				} elseif( $capcode === 'admin_highlight' ) {

					$capcodeStart = ' <strong class="capcode capcodeAdmin">## Admin</strong>';

					$capcode_class = ' capcodeAdmin';


					$capcode = ' <img src="' . $imgurl . 'adminicon.gif" alt="This user is the 4chan Administrator." title="This user is the 4chan Administrator." class="identityIcon">';

					$highlight = ' highlightPost';

				} else {

					$capcode = $capcodeStart = $highlight = $capcode_class = '';

				}

				$oldclass = in_array( $no, $delarr ) ? ' class="highlightPost"' : '';


				$dat .= <<<HTML

			<tr$oldclass>

				<td>$no</td>

				<td class="name-col$capcode_class">$emailStart<span class="name">$name</span>$emailEnd$capcodeStart$capcode</td>
				<td class="file-col">[<a href="$filelink" title="$filename" data-width="$w" data-height="$h" target="_blank">$shortname</a>]</td>
				<td>$texttag</td>
				<td class="subject"><span title="$sub">$shortsub</span></td>
				<td>$kbsize</td>
				<td>$now</td>
				<td>$countres</td>
				<td>[<a href="$replink$semantic_url">Reply</a>]</td>

			</tr>

HTML;
			}

			//clearstatcache();
			mysql_free_result( $resline );
		} // end /f/
	} // no pages for /f/

  $lang = $resno ? S_FORM_REPLY : S_FORM_THREAD;
  
	if( !$resno ) {
		$dat .= '</table><hr></div>';
	} else {
		$dat .= '<div class="navLinks navLinksBot desktop">[<a href="/' . BOARD_DIR . '/" accesskey="a">' . S_RETURN . '</a>] [<a href="#top">' . S_TOP . '</a>] </div><hr class="desktop">';
  	$dat .= '
  	<div class="mobile center"><a class="mobilePostFormToggle button" href="#">' . $lang . '</a></div>
  </div>';
	}
	

	if (!$resno) {
		$dat .= '<div class="navLinks navLinksBot mobile"><span class="mobileib button"><a href="#top">' . S_TOP . '</a></span> <span class="mobileib button"><a href="#bottom_r" id="refresh_bottom">' . S_REFRESH . '</a></span></div><hr class="mobile">';
	}
	else {
		$dat .= '<div class="navLinks mobile"><span class="mobileib button"><a href="/' . BOARD_DIR . '/" accesskey="a">' . S_RETURN . '</a></span> <span class="mobileib button"><a href="#top">' . S_TOP . '</a></span> <span class="mobileib button"><a href="#bottom_r" id="refresh_bottom">' . S_REFRESH . '</a></span></div><hr class="mobile">';
	}
	/*
	if( AD_BOTTOM_ENABLE == 1 ) {
		$bottomad = "";

		if( defined( "AD_BOTTOM_TEXT" ) && AD_BOTTOM_TEXT ) {
			$bottomad .= '<div class="bottomad center ad-cnt">' . ad_text_for( AD_BOTTOM_TEXT ) . '</div>' . (defined('AD_BOTTOM_PLEA') ? AD_BOTTOM_PLEA : '');
		} else if( defined( "AD_BOTTOM_TABLE" ) && AD_BOTTOM_TABLE ) {
			list( $bottomimg, $bottomlink ) = rid( AD_BOTTOM_TABLE, 1 );
			$bottomad .= "<div class=\"center\"><a href=\"$bottomlink\" target=\"_blank\"><img class=\"bottomad\" src=\"$bottomimg\" alt=\"\"></a></div>";
		}

		if( $bottomad ) {
			$dat .= "$bottomad<hr>";
		}
	}
  */
  
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
  
  if (defined('AD_RC_BOTTOM_MOBILE') && AD_RC_BOTTOM_MOBILE) {
    $dat .= '<div class="adg-rects mobile"><div class="adg-m adp-50" data-rc="' . AD_RC_BOTTOM_MOBILE . '" id="rcjsload_bottom_m"></div><hr></div>';
  }
  
  if (defined('AD_ADNIUM_BOTTOM_MOBILE') && AD_ADNIUM_BOTTOM_MOBILE) {
    $dat .= '<div class="adg-rects mobile"><div class="adg-m adp-250" id="adn-' . AD_ADNIUM_BOTTOM_MOBILE . '" data-adn></div><hr></div>';
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
				<option value="Photon">Photon</option>
			</select>
		</span>';
	}

	$dat .= '</div></form>';

	foot( $dat );

	if( $page == 0 ) {
		$logfilename = SELF_PATH2_FILE;
	}

	if( $resno ) {
		$logfilename = RES_DIR . $resno . PHP_EXT;
		print_page( $logfilename, $dat );

		if( !$rebuild ) {
			updatelog();
		}
	} else {
		print_page( $logfilename, $dat );
	}

	mysql_free_result( $treeline );
}

function form( &$dat, $resno, $admin = '' )
{
  global $thread_unique_ips;
  
	$maxbyte = MAX_KB * 1024;
	$no      = $resno;
	$closed  = 0;
	$msg     = $hidden = '';
	$tags    = upboard_tags();

	if( $resno ) {
		if( !$cchk = mysql_board_call( "select closed from `" . SQLLOG . "` where no=" . $resno ) ) {
			echo S_SQLFAIL;
		}
		list( $closed ) = mysql_fetch_row( $cchk );
		
		$msg .= '<div class="navLinks mobile">
	<span class="mobileib button"><a href="/' . BOARD_DIR . '/" accesskey="a">' . S_RETURN . '</a></span> <span class="mobileib button"><a href="#bottom">' . S_BOTTOM . '</a></span> <span class="mobileib button"><a href="#top_r" id="refresh_top">' . S_REFRESH . '</a></span>
</div>
<div id="mpostform"><a href="#" class="mobilePostFormToggle mobile hidden button">' . S_FORM_REPLY . '</a></div>';
	}
	else {
    $msg .= '
<div class="navLinks mobile">
	<span class="mobileib button"><a href="#bottom">' . S_BOTTOM . '</a></span> <span class="mobileib button"><a href="#top_r" id="refresh_top">' . S_REFRESH . '</a></span>
</div>
<div id="mpostform"><a href="#" class="mobilePostFormToggle mobile hidden button">' . S_FORM_THREAD . '</a></div>';
	}

	if( $admin ) {
		$hidden = '<input type="hidden" name="admin" value="' . ADMIN_PASS . '"/>';
		$msg    = '<h4>' . S_NOTAGS . '</h4>';
	}

	if( $closed != 1 ) {
		$dat .= $msg;
		form_ads( $dat );

		$dat .= '
<form name="post" action="' . SELF_PATH_POST . '" method="post" enctype="multipart/form-data">
<input type="hidden" name="MAX_FILE_SIZE" value="' . $maxbyte . '">
<input type="hidden" name="mode" value="regist">
<input id="postPassword" name="pwd" type="hidden">';

		if( $no ) {
			$dat .= '
<input type="hidden" name="resto" value="' . $no . '"/>
';
		}

		$dat .= '<div id="togglePostFormLink" class="desktop">[<a href="#">'
		  . ($resno ? S_FORM_REPLY : S_FORM_THREAD)
		  . '</a>]</div><noscript><style type="text/css">#postForm { display: table !important; }</style></noscript><table class="postForm hideMobile" id="postForm">
	<tbody>
';

		// TODO: ADS

		$spoilers = '';
		if( SPOILERS == 1 ) {
			$spoilers = '<span class="desktop">[<label><input type="checkbox" name="spoiler" value="on" tabindex="8">' . S_SPOILERS . '</label>]</span>';
		}

		if( FORCED_ANON == 1 ) {
			$dat .= '
		<tr data-type="Options">
			<td>' . S_EMAIL . '</td>
			<td><input type="hidden" name="name"><input type="hidden" name="sub"><input name="email" type="text" tabindex="2"><input type="submit" value="' . S_SUBMIT . '" tabindex="6"></td>
		</tr>
			';

			if( $spoilers ) {

				if( !$stripm ) $dat .= '

		<tr class="mobile" data-type="Spoilers">
			<td>Spoilers</td>
			<td class="mobileSpoiler">[<label><input type="checkbox" name="spoiler" value="on">' . S_SPOILERS . '</label>]</td>
		</tr>
			';
			}
		}
		else {
			$dat .= '
		<tr data-type="Name">
			<td>' . S_NAME . '</td>
			<td><input name="name" type="text" tabindex="1"></td>
    </tr>';
      if ($resno) {
        $dat .= '
        <tr data-type="Options">
          <td>' . S_EMAIL . '</td>
          <td><input name="email" type="text" tabindex="2"><input type="submit" value="' . S_SUBMIT . '" tabindex="6"></td>
        </tr>';
      }
      else {
        $dat .= '
        <tr data-type="Options">
          <td>' . S_EMAIL . '</td>
          <td><input name="email" type="text" tabindex="2"></td>
        </tr>
        <tr data-type="Subject">
          <td>' . S_SUBJECT . '</td>
          <td><input name="sub" type="text" tabindex="3"><input type="submit" value="' . S_SUBMIT . '" tabindex="6"></td>
        </tr>';
      }

			if( $spoilers ) {

				if( !$stripm ) $dat .= '
			<tr class="mobile" data-type="Spoilers">
				<td>' . S_SPOILERS . '</td>
				<td class="mobileSpoiler">[<label><input type="checkbox" name="spoiler" value="on">' . S_SPOILERS . '</label>]</td>
			</tr>

				';
			}
		}

		if( $admin ) {
			$dat .= '
		<tr>
			<td>Reply ID</td>
			<td><input name="resto" type="text"/> [<label><input type="checkbox" name="age" value="1"/>Age</label></td>
		</tr>
			';
		}

		//if( EXPANDING_POST_ )

		$dat .= '
		<tr data-type="Comment">
			<td>' . S_COMMENT . '</td>
			<td><textarea name="com" cols="48" rows="4" wrap="soft"></textarea></td>
		</tr>
		';

		if( CAPTCHA ) {
			$dat .= '
		<tr id="captchaFormPart">
			<td class="desktop">' . S_CAPTCHA . '</td>
			<td colspan="2">' . captcha_form() . '<div class="passNotice">' . S_PASS_NOTICE . '</div></td>
		</tr>
			';
		}

		if( !$resno ) {
			$dat .= '
		<tr>
			<td>' . S_UPLOADFILE . '</td>
			<td><input id="postFile" name="upfile" type="file"/><div id="fileError"></div></td>
		</tr>
		
		<tr>
			<td>Tag</td>
			<td><select name="filetag">
				<option value="9999" selected="selected">Choose one:</option>';

			foreach( upboard_tags() as $tagval => $tagnames ) {
				$dat .= '<option value="' . $tagval . '">' . $tagnames['long'] . '</option>';
			}

			$dat .= '</select></td></tr>';
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
		
    $blotter = $resno ? '' : get_blotter();
    
		$dat .= '
	</tbody>
</table>' . $blotter . '
</form>
' . DONATE . '
<script>with(document.post) {name.value=get_cookie("4chan_name"); email.value=get_cookie("4chan_email"); pwd.value=get_pass("4chan_pass"); }</script>		
';
	} else { // Closed thread
		form_ads( $dat );
		$dat .= '<div class="navLinks">
	[<a href="../' . SELF_PATH2 . '" accesskey="a">' . S_RETURN . '</a>] [<a href="#bottom">Bottom</a>]
</div>
<div class="closed">Thread closed.<br>You may not reply at this time.</div>';
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

	list($globalmsgtxt,$globalmsgdate) = global_msg_txt();
	
	if( $globalmsgtxt ) {
		$dat .= "\n<hr><a href=\"#\" class=\"button redButton mobile hidden\" id=\"globalToggle\">View Important Announcement</a><div class=\"globalMessage hideMobile\" id=\"globalMessage\" data-utc=\"$globalmsgdate\">" . $globalmsgtxt . "</div>\n";
	}
	
	if (defined('AD_ADGLARE_TOP') && AD_ADGLARE_TOP) {
    $dat .= '<div class="adg-rects desktop"><hr><div class="adg adp-90" id=zone' . AD_ADGLARE_TOP . '></div></div>';
  }
  else if (defined('AD_RC_TOP') && AD_RC_TOP) {
    $dat .= '<div class="adg-rects desktop"><hr><div class="adg adp-228" data-rc="' . AD_RC_TOP . '" id="rcjsload_top"></div></div>';
  }
  else if (defined('AD_ABC_TOP_DESKTOP') && AD_ABC_TOP_DESKTOP) {
    list($_abc_left, $_abc_right) = explode(',', AD_ABC_TOP_DESKTOP);
    $dat .= '<div class="adg-rects desktop"><hr><div class="adg adp-250 adp-row" data-abc="' . $_abc_left . '"></div><div class="adg adp-250 adp-row" data-abc="' . $_abc_right . '"></div></div>';
  }
  
	if ($resno) {
		$dat .= '<hr class="desktop" id="op">
<div class="navLinks desktop">
	[<a href="/' . BOARD_DIR . '/" accesskey="a">' . S_RETURN . '</a>] [<a href="#bottom">' . S_BOTTOM . '</a>]
</div>
		';
	}
}

?>
