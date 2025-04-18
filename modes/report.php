<?php

	function report_get_style($board) {
		$styles = array(
					'Yotsuba' => STATIC_SERVER.'css/yotsuba.css',
					'Yotsuba B' => STATIC_SERVER.'css/yotsublue.css',
					'Futaba' => STATIC_SERVER.'css/futaba.css',
					'Burichan' => STATIC_SERVER.'css/burichan.css',
					);
		$board = mysql_real_escape_string($board);
		$query = mysql_global_call("SELECT domain FROM boardlist where dir='$board'");
		list($domain) = mysql_fetch_row($query);
		if(DEFAULT_BURICHAN == 1)
			$styletitle = ($_COOKIE['ws_style']?$_COOKIE['ws_style']:'Yotsuba B');
		elseif($domain == 'may')
			$styletitle = 'not4chan';
		else
			$styletitle = ($_COOKIE['nws_style']?$_COOKIE['nws_style']:'Yotsuba');
		return $styles[$styletitle];
	}

function log_cleared_reporter($long_ip, $pwd, $pass_id, $cat_id, $weight) {
    $sql = <<<SQL
INSERT INTO report_clear_log(long_ip, pwd, pass_id, category, weight)
VALUES(%d, '%s', '%s', %d, %F)
SQL;
    
  return !!mysql_global_call($sql, $long_ip, $pwd, $pass_id, $cat_id, $weight);
}

function report_can_bypass_captcha($ip, $userpwd, $post) {
  if (!$userpwd || !$post) {
    return false;
  }
  
  if ($userpwd->ipLifetime() < 604800) { // 7 days
    return false;
  }
  
  if (!$post['fsize']) { // only posts with images
    return false;
  }
  
  $allowance = 3;
  
  $long_ip = ip2long($ip);
  
  if (!$long_ip) {
    return false;
  }
  
  // Allow $allowance no-captcha reports for every hour of inactivity
  $sql = <<<SQL
SELECT COUNT(*) as cnt FROM user_actions WHERE ip = $long_ip
AND action = 'report'
AND time > DATE_SUB(NOW(), INTERVAL 1 HOUR)
SQL;

  $res = mysql_global_call($sql);
  
  if (!$res) {
    return false;
  }
  
  $row = mysql_fetch_row($res);
  
  if (!$row || $row[0] >= $allowance) {
    return false;
  }
  
  // Don't allow ips with 1 cleared reports in the past 72 hours
  $sql = <<<SQL
SELECT COUNT(*) FROM report_clear_log
WHERE long_ip = $long_ip AND created_on > DATE_SUB(NOW(), INTERVAL 72 HOUR)
SQL;
  
  $res = mysql_global_call($sql);
  
  if (!$res) {
    return false;
  }
  
  $count = (int)mysql_fetch_row($res)[0];
  
  if ($count >= 1) {
    return false;
  }
  
  // Don't allow ips with recent warn/ban history
  $sql = <<<SQL
SELECT no FROM banned_users
WHERE host = '%s'
AND now > DATE_SUB(NOW(), INTERVAL 30 DAY)
LIMIT 1
SQL;
  
  $res = mysql_global_call($sql, $ip);
  
  if (!$res) {
    return false;
  }
  
  if (mysql_num_rows($res) > 0) {
    return false;
  }
  
  return true;
}
  
  function report_check_ip($board, $no, $check_ban = false, $is_illegal = false) {
    global $captcha_bypass, $passid;
    
    $board = mysql_real_escape_string($board);
    
    $no = mysql_real_escape_string($no);
    
    $ip = ip2long($_SERVER['REMOTE_ADDR']);
    
    $pass_sql = false;
    
    $pwd_sql = false;
    
    // Check if already reported
    // by IP
    $rep_clauses = array("ip = '$ip'");
    
    // by 4chan pass
    if ($captcha_bypass && $passid) {
      $pass_sql = mysql_real_escape_string($passid);
      $rep_clauses[] = "4pass_id = '$pass_sql'";
    }
    
    // by password
    $userpwd = UserPwd::getSession();
    
    if ($userpwd && $userpwd->getPwd()) {
      $pwd_sql = mysql_real_escape_string($userpwd->getPwd());
      $rep_clauses[] = "pwd = '$pwd_sql'";
    }
    
    $rep_clauses_sql = implode(' OR ', $rep_clauses);
    
    $res = mysql_global_call("SELECT no FROM reports WHERE ($rep_clauses_sql) AND board = '$board' AND no = '$no'");
    
    if ($res && mysql_num_rows($res) > 0) {
      fancydie('You have already reported this post.');
    }
    
    // Check cooldown
    $res = mysql_global_call("SELECT no FROM reports WHERE ($rep_clauses_sql) AND ts > DATE_SUB(NOW(), INTERVAL 15 SECOND) LIMIT 1");
    
    if ($res && mysql_num_rows($res) > 0) {
      fancydie('You have to wait a while before reporting another post.');
    }
    
    // Check hourly limits
    $res = mysql_global_call("SELECT COUNT(*) FROM reports WHERE ($rep_clauses_sql) AND ts > DATE_SUB(NOW(), INTERVAL 1 HOUR) LIMIT 1");
    
    if ($res && mysql_fetch_row($res)[0] >= RENZOKU_REP_HOURLY) {
      fancydie('You have to wait a while before reporting another post.');
    }
    
    // Check daily limits
    $res = mysql_global_call("SELECT COUNT(*) FROM reports WHERE ($rep_clauses_sql) AND ts > DATE_SUB(NOW(), INTERVAL 24 HOUR) LIMIT 1");
    
    if ($res && mysql_fetch_row($res)[0] >= RENZOKU_REP_DAILY) {
      fancydie('You have to wait a while before reporting another post.');
    }
    
    // Check if banned
    if ($check_ban) {
      $ip_sql = mysql_real_escape_string($_SERVER['REMOTE_ADDR']);
      
      // by ip
      $ban_clauses = array("host = '$ip_sql'");
      
      // by 4chan pass
      if ($pass_sql) {
        $ban_clauses[] = "4pass_id = '$pass_sql'";
      }
      
      // by password
      if ($pwd_sql) {
        $ban_clauses[] = "password = '$pwd_sql'";
      }
      
      $ban_clauses_sql = implode(' OR ', $ban_clauses);
      
      $res = mysql_global_call("SELECT COUNT(*) FROM banned_users WHERE ($ban_clauses_sql) AND active = 1 AND (global = 1 OR board = '$board')");
      
      if ($res && mysql_fetch_row($res)[0] > 0) {
        fancydie('You can\'t report posts because you are <a href="https://www.' .
          L::d(BOARD_DIR) .
          '/banned" target="_blank">banned</a>.');
      }
      
      if ($captcha_bypass !== true) {
        $longip = ip2long($_SERVER['REMOTE_ADDR']);
        
        if (isset($_SERVER['HTTP_X_GEO_ASN'])) {
          $asn = (int)$_SERVER['HTTP_X_GEO_ASN'];
        }
        else {
          $_asninfo = GeoIP2::get_asn($_SERVER['REMOTE_ADDR']);
          
          if ($_asninfo) {
            $asn = (int)$_asninfo['asn'];
          }
          else {
            $asn = 0;
          }
        }
        
        if (isIPRangeBannedReport($longip, $asn, BOARD_DIR, $userpwd)) {
          fancydie('Reporting from this IP range has been blocked due to abuse. [<a href="//www.' .
            L::d(BOARD_DIR) .
            '/faq#blocked" target="_blank">More Info</a>]<br>4chan Pass users can bypass this block. [<a href="https://www.4chan.org/pass" target="_blank">Learn More</a>]');
        }
      }
    }
  }

	function report_increment_counter() {
		return; // broken lol
		$count = @file_get_contents('reports/report.count');
		if(!$count) $count = 0;
		$count++;
		file_put_contents('reports/report.count',$count);
	}

	function report_post_exists($no) {
		$query=mysql_board_call("SELECT COUNT(*) FROM `".SQLLOG."` WHERE no='$no'");
		return mysql_result($query,0,0);
	}

	function report_is_capcoded_post( $no )
	{
		$query = mysql_board_call( "SELECT COUNT(*) FROM `%s` WHERE capcode != 'none' AND no=%d", SQLLOG, $no );
		return mysql_result( $query, 0, 0 );
	}

	function report_check_autodelete($board,$no) {
		$query = mysql_global_do("SELECT COUNT(*) FROM reports WHERE board='$board' AND no='$no'");
		$count = mysql_result($query,0,0);

		if(defined('REPORTS_AUTODELETE') && $count >= REPORTS_AUTODELETE) {
			report_do_autodelete($board,$no,1);
			return;
		}

		$query = mysql_global_do("SELECT COUNT(*) FROM reports WHERE cat='2' AND board='$board' AND no='$no'");
		$count = mysql_result($query,0,0);
		if(defined('REPORTS_AUTODELETE_ILLEGAL') && $count >= REPORTS_AUTODELETE_ILLEGAL) {
			report_do_autodelete($board,$no,2);
			return;
		}
	}
	function report_do_autodelete($board,$no,$cat) {
		$query = mysql_board_call("SELECT * FROM `".SQLLOG."` WHERE no='$no'");
		$row = mysql_fetch_assoc($query);
		if(!$row) return;
		$auser = 'Auto-del';
		$adfsize=($row['fsize']>0)?1:0;
		$adname=str_replace('</span> <span class="postertrip">!','#',$row['name']);
		$imgonly = 0;
		$row['sub'] = mysql_escape_string($row['sub']);
		$row['com'] = mysql_escape_string($row['com']);
		$row['filename'] = mysql_escape_string($row['filename']);
		mysql_global_do("INSERT INTO ".SQLLOGDEL." (imgonly,postno,board,name,sub,com,img,filename,admin) values('$imgonly','$no','".SQLLOG."','$adname','{$row['sub']}','{$row['com']}','$adfsize','{$row['filename']}','$auser')");
		delete_post($no, '', 0, 1, 1);
	}
	function report_log_action($board,$no) {
		mysql_global_call("insert into user_actions (ip,board,action,postno,time) values (%d,'%s','report',%d,now())", ip2long($_SERVER["REMOTE_ADDR"]), $board, $no);
	}

	function report_post_sticky($no) {
		$query=mysql_board_call("SELECT sticky FROM `".SQLLOG."` WHERE no='$no'");
		return mysql_result($query,0,0);
	}

function report_check_post($board, $post_id) {
  $sql = "SELECT * FROM `%s` WHERE no = %d";
  
  $res = mysql_board_call($sql, $board, $post_id);
  
  if (!$res) {
    fancydie(S_POST_DEAD);
  }
  
  $post = mysql_fetch_assoc($res);
  
  if (!$post) {
    fancydie(S_POST_DEAD);
  }
  
  if ($post['sticky']) {
    fancydie(S_CANNOTREPORTSTICKY);
  }
  
  if ($post['capcode'] !== 'none') {
    fancydie(S_CANNOTREPORT);
  }
  
  return $post;
}

function get_report_categories($board, $post_id, $is_worksafe) {
  $query = "SELECT * FROM report_categories ORDER BY board ASC";
  
  $res = mysql_global_call($query);
  
  if (!$res) {
    return false;
  }
  
  $query = "SELECT resto, fsize, filedeleted FROM `%s` WHERE no = %d";
  
  $res2 = mysql_board_call($query, $board, $post_id);
  
  if (!$res2) {
    return false;
  }
  
  $post = mysql_fetch_assoc($res2);
  
  if (!$post) {
    return false;
  }
  
  $is_op = !$post['resto'];
  $has_image = $post['fsize'] && !$post['filedeleted'];
  
  // ID of the category which will be used for the Illegal radio button
  $illegal_cat_id = 31;
  
  // Rule violations + one illegal category
  $data = array('rule' => null, 'illegal' => null);
  
  // Sorting, board specific categories go on top
  $data_rule_top = array();
  $data_rule_bottom = array();
  
  $match_board = ',' . $board . ',';
  
  while ($cat = mysql_fetch_assoc($res)) {
    if ($cat['id'] == $illegal_cat_id) {
      $data['illegal'] = $cat;
      continue;
    }
    
    if ($cat['board'] !== '') {
      if ($cat['board'] === '_ws_') {
        if (!$is_worksafe) {
          continue;
        }
      }
      else if ($cat['board'] === '_nws_') {
        if ($is_worksafe) {
          continue;
        }
      }
      else if ($cat['board'] !== $board) {
        continue;
      }
    }
    
    if ($cat['op_only'] && !$is_op) {
      continue;
    }
    
    if ($cat['reply_only'] && $is_op) {
      continue;
    }
    
    if ($cat['image_only'] && !$has_image) {
      continue;
    }
    
    if ($cat['exclude_boards'] && strpos(",{$cat['exclude_boards']},", $match_board) !== false) {
      continue;
    }
    
    if ($cat['board']) {
      $data_rule_top[$cat['id']] = $cat;
    }
    else {
      $data_rule_bottom[$cat['id']] = $cat;
    }
  }
  
  $data['rule'] = $data_rule_top + $data_rule_bottom;
  
  return $data;
}

/**
 * Checks if the report should have a different priority
 * based on the number of cleared reports in the past X days and ban history.
 */
function is_report_filtered($filter_thres, $ip, $long_ip, $pass_id = null, $pwd = null) {
  if ($filter_thres < 1) {
    return false;
  }
  
  // only count reports made in the past X days
  $cleared_days_lim = 2;
  // number of cleared reports for the IP to be considered 'abusive'
  $cleared_count_lim = (int)$filter_thres;
  // only count bans made in the past X days
  $ban_days_lim = 30;
  // number of bans/warnings for the IP to be considered 'abusive'
  $ban_count_lim = 3;
  
  $rep_abuse_tpl = 190; // ban template for report abusing
  
  $long_ip = (int)$long_ip;
  
  $ban_clauses = array();
  $rep_clauses = array();
  
  // 4chan Pass
  if ($pass_id) {
    $pass_id_sql = mysql_real_escape_string($pass_id);
    $ban_clauses[] = "4pass_id = '$pass_id_sql'";
    $rep_clauses[] = "pass_id = '$pass_id_sql'";
    
    $pwd_and_ban = "4pass_id != '$pass_id_sql'";
    $pwd_and_rep = "pass_id != '$pass_id_sql'";
  }
  // IP
  else {
    $ip_sql = mysql_real_escape_string($ip);
    $ban_clauses[] = "host = '$ip_sql'";
    $rep_clauses[] = "long_ip = $long_ip";
    
    $pwd_and_ban = "host != '$ip_sql'";
    $pwd_and_rep = "long_ip != $long_ip";
  }

  // Password
  if ($pwd) {
    $pwd_sql = mysql_real_escape_string($pwd);
    $ban_clauses[] = "password = '$pwd_sql' AND $pwd_and_ban";
    $rep_clauses[] = "pwd = '$pwd_sql' AND $pwd_and_rep";
  }
  
  // ---
  // Check cleared reports
  // ---
  $clear_count = 0;
  
  foreach ($rep_clauses as $clause) {
    $query = <<<SQL
SELECT COUNT(*) FROM report_clear_log
WHERE $clause AND created_on > DATE_SUB(NOW(), INTERVAL $cleared_days_lim DAY)
SQL;
    
    $res = mysql_global_call($query);
    
    if (!$res) {
      return false;
    }
    
    $clear_count += (int)mysql_fetch_row($res)[0];
    
    if ($clear_count >= $cleared_count_lim) {
      return true;
    }
  }
  
  // ---
  // Check ban history
  // ---
  $ban_count = 0;
  
  foreach ($ban_clauses as $clause) {
    $query = <<<SQL
SELECT COUNT(*) FROM banned_users
WHERE active = 0 AND $clause AND template_id = $rep_abuse_tpl
AND now > DATE_SUB(NOW(), INTERVAL $ban_days_lim DAY)
SQL;
    
    $res = mysql_global_call($query);
    
    if (!$res) {
      return false;
    }
    
    $ban_count += (int)mysql_fetch_row($res)[0];
    
    if ($ban_count >= $ban_count_lim) {
      return true;
    }
  }
  
  return false;
}

function report_get_rel_sub($board, $thread_id) {
  if (!$board || !$thread_id) {
    return '';
  }
  
  $thread_id = (int)$thread_id;
  
  $query = "SELECT sub FROM `%s` WHERE no = $thread_id";
  
  $res = mysql_board_call($query, $board);
  
  if (!$res || mysql_num_rows($res) !== 1) {
    return '';
  }
  
  return mysql_fetch_row($res)[0];
}

function report_submit($board, $no, $cat_id) {
  global $log, $passid;
  
  $board = mysql_real_escape_string($board);
  $no = (int)$no;
  $long_ip = ip2long($_SERVER['REMOTE_ADDR']);
  
  // check if the category is valid
  $cats = get_report_categories($board, $no, DEFAULT_BURICHAN == 1);
  
  if ($cats['illegal']['id'] == $cat_id) {
    $old_cat = 2; // todo: remove later
    $old_field = 'num_illegal'; // todo: remove later
    $rep_cat = $cats['illegal'];
  }
  else if (isset($cats['rule'][$cat_id])) {
    $old_cat = 1;
    $old_field = 'num_rule';
    $rep_cat = $cats['rule'][$cat_id];
  }
  else {
    fancydie('Invalid category selected.');
  }
  
  if (!$no) {
    fancydie(S_POST_DEAD);
  }
  
  log_cache(0, $no, 2);
  
  if ($log[$no]['archived']) {
    $extra = array('archived' => 1);
  }
  else {
    $extra = array();
  }
  
  $resto = (int)$log[$no]['resto'];
  
  $post_data = generate_post_json($log[$no], $log[$no]['resto'] ? $log[$no]['resto'] : $no, $extra);
  
  if ($log[$no]['resto']) {
    $rel_sub = report_get_rel_sub($board, $log[$no]['resto']);
    
    if ($rel_sub !== '') {
      $post_data['rel_sub'] = $rel_sub;
    }
  }
  
  $json = json_encode($post_data);
  
  $weight = $rep_cat['weight'];
  
  $is_staff = has_level('janitor');
  
  $req_sig = spam_filter_get_req_sig();
  
  $userpwd = UserPwd::getSession();
  
  if ($userpwd) {
    $pwd = $userpwd->getPwd();
    $is_new_pwd = $userpwd->isNew();
    $is_known_pwd = $userpwd->isUserKnownOrVerified(60);
  }
  else {
    $pwd = null;
    $is_new_pwd = true;
    $is_known_pwd = false;
  }
  
  if (!$is_staff) {
    $ignore_reason = 0;
    
    $_threat_score = spam_filter_get_threat_score(null, true, false);
    
    if (!$is_known_pwd) {
      $ignore_reason = 1;
    }
    else if ($_threat_score >= 0.4) {
      $ignore_reason = 2;
    }
    else if ($rep_cat['filtered']) {
      if (is_report_filtered($rep_cat['filtered'], $_SERVER['REMOTE_ADDR'], $long_ip, $passid, $is_new_pwd ? null : $pwd)) {
        $ignore_reason = 3;
      }
    }
  }
  
  if ($ignore_reason > 0) {
    $weight = 0.5;
    if ($ignore_reason == 2) {
      $_bot_headers = spam_filter_format_http_headers($log[$no]['com'], '', '', $_threat_score, $req_sig);
      log_spam_filter_trigger('ignore_report_score', BOARD_DIR, $no, $_SERVER['REMOTE_ADDR'], $ignore_reason, $_bot_headers);
    }
  }
  
  // Check if the post was already reported and cleared
  $is_cleared = 0;
  $cleared_by = '';
  
  $query = "SELECT cleared_by FROM reports WHERE board = '$board' AND no = $no AND cleared = 1 LIMIT 1";
  
  $res = mysql_global_call($query);
  
  if ($res) {
    $row = mysql_fetch_row($res);
    
    if ($row) {
      $is_cleared = 1;
      $cleared_by = $row[0];
      log_cleared_reporter($long_ip, $pwd, $passid, $rep_cat['id'], $weight);
    }
  }
  
  $is_ws = DEFAULT_BURICHAN == 1 ? 1 : 0;
  
  $query = <<<SQL
INSERT IGNORE INTO reports
SET ip = %d, pwd = '%s', 4pass_id = '%s', req_sig = '%s', board = '%s', no = %d, resto = %d,
cat = %d, weight = %F, report_category = %d, ws = $is_ws, post_ip = %d, post_json = '%s',
cleared = $is_cleared, cleared_by = '%s'
SQL;
  
  $res = mysql_global_call($query,
    $long_ip, $pwd, $passid, $req_sig, $board, $no, $resto,
    $old_cat, $weight, $rep_cat['id'], ip2long($log[$no]['host']), $json, $cleared_by
  );
  
  if (!$res) {
    fancydie('There was an error submitting your report. Please try again.');
  }
  
  $query = <<<SQL
INSERT INTO `reports_for_posts` (`board`, `postid`, `threadid`, `$old_field`, `max_cat`)
VALUES ('$board', $no, $resto, 1, $old_cat)
ON DUPLICATE KEY UPDATE $old_field = $old_field + 1, max_cat = IF(num_illegal >= num_rule, 2, 1)
SQL;
  
  $res = mysql_global_call($query);
  
  report_log_action($board, $no);
  
  if ($userpwd) {
    $userpwd->updateReportActivity();
    $userpwd->setCookie('.' . MAIN_DOMAIN);
  }
  
  fancydie('Report submitted! This window will close in 3 seconds...', 1);
}
