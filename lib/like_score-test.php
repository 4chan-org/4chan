<?php

define('LIKE_MAX_LIKES', 1048576);
define('LIKE_LIKE_SCORE', 10);
define('LIKE_POST_SCORE', 2);
define('LIKE_GIVE_SCORE', 1);
define('LIKE_COOLDOWN_SEC', 120);

/**
 * April 2019
 */
function like_commit() {
  global $captcha_bypass, $passid;
  
  // FIXME
  //$_POST['post_id'] = $_GET['post_id'];
  
  // Basic validation of inputs
  if (!isset($_POST['post_id']) || !isset($_COOKIE['xa19'])) {
    die("0\nBad Request.");
  }

  $post_id = (int)$_POST['post_id'];


  if (!$post_id) {
    die("0\nBad Post ID.");
  }
  
  if ($_COOKIE['xa19'] !== $_POST['post_id']) {
    die("0\nBad Request.");
  }
  
  $is_suspicious = 0;
  
  $user_id = $_SERVER['REMOTE_ADDR'];
  
  // Check captcha
  if ($passid || isset($_COOKIE['4chan_auser']) || !like_is_ip_known($_SERVER['REMOTE_ADDR'])) {
    start_auth_captcha();
    
    
    if (!$captcha_bypass) {
      end_recaptcha_verify();
      /*
      if (like_is_ip_suspicious($ip)) {
        $is_suspicious = 1;
      }
      */
    }
    else if ($passid) {
      $user_id = $passid;
    }
  }
  
  // Check if post exists and not trying to like own posts
  $target_info = like_get_target_user_id(BOARD_DIR, $post_id);
  
  if (!$target_info) {
    die("0\nYou can't like this post.");
  }
  
  list($target_user_id, $target_ip) = $target_info;
  
  if ($target_user_id == $user_id || $target_ip == $_SERVER['REMOTE_ADDR']) {
    die("0\nYou can't like your own posts.");
  }
  
  // Check if duplicate
  if (like_is_duplicate($user_id, BOARD_DIR, $post_id)) {
    die("0\nYou already like this post.");
  }
  
  // Check cooldowns and other abuse
  if (like_is_abusive($_SERVER['REMOTE_ADDR'], $user_id, $target_user_id, $post_id, $captcha_bypass)) {
    die("0\nYou have to wait a while before liking this post.");
  }
  
  // Update the score
  $new_like_count = like_update_like_score($user_id, $target_user_id, BOARD_DIR, $post_id, $is_suspicious);
  
  if (!$new_like_count) {
    die("0\nInternal Server Error.");
  }
  
  $user_score = like_get_user_score();
  $user_perks = implode(' ', like_get_perks_state($user_score, true));
  
  log_cache(0, $post_id);
  updatelog($post_id, 1);
  
  echo "1\n$new_like_count\n$user_score\n$user_perks";
}

// Called after a user makes a new post
// Increases the user score by LIKE_POST_SCORE
function like_update_post_score() {
  global $captcha_bypass, $passid;
  
  $skip_boards = array('b', 'qa', 's4s', 'bant', 'vip');
  
  if (in_array(BOARD_DIR, $skip_boards)) {
    return false;
  }
  
  $add_score = LIKE_POST_SCORE;
  
  if ($captcha_bypass && $passid) {
    $user_id = $passid;
  }
  else {
    $user_id = $_SERVER['REMOTE_ADDR'];
  }
  
  $query = <<<SQL
INSERT INTO `like_user_scores` (`user_id`, `user_score`)
VALUES ('%s', $add_score)
ON DUPLICATE KEY UPDATE user_score = user_score + $add_score
SQL;
  
  $res = mysql_global_call($query, $user_id);
  
  if (!$res) {
    return false;
  }
  
  return true;
}

function like_update_like_score($user_id, $target_user_id, $board, $post_id, $is_suspicious) {
  $add_score = LIKE_LIKE_SCORE;
  $add_score_giver = LIKE_GIVE_SCORE;
  
  // Insert log entry
  $query = <<<SQL
INSERT INTO `like_user_log` (`user_id`, `target_user_id`, `suspicious`, `board`, `post_id`)
VALUES ('%s', '%s', $is_suspicious, '$board', $post_id)
SQL;
  
  $res = mysql_global_call($query, $user_id, $target_user_id);
  
  if (!$res) {
    die("0\nDatabase Error (luls2).");
  }
  
  // Update post like count
  $query = "SELECT email, resto, archived FROM `$board` WHERE no = $post_id LIMIT 1";
  
  $res = mysql_board_call($query);
  
  if (!$res) {
    die("0\nDatabase Error (luls0).");
  }
  
  $post = mysql_fetch_assoc($res);
  
  if (!$post) {
    die("0\nThis post doesn't exist anymore.");
  }
  
  if ($post['resto'] == 0 || $post['archived']) {
    die("0\nYou can't like this post.");
  }
  
  list($user_score, $post_likes) = explode('.', $post['email']);
  
  $user_score = (int)$user_score;
  $post_likes = ((int)$post_likes) + 1;
  
  if ($post_likes >= LIKE_MAX_LIKES) {
    die("0\nYou can't like this post anymore.");
  }
  
  $email = "$user_score.$post_likes";
  
  $query = "UPDATE `$board` SET email = '$email' WHERE no = $post_id LIMIT 1";
  
  $res = mysql_board_call($query);
  
  if (!$res) {
    die("0\nDatabase Error (luls1).");
  }
  
  // ---
  
  $skip_boards = array('b', 'qa', 's4s', 'bant', 'vip');
  
  if (in_array($board, $skip_boards)) {
    return $post_likes;
  }
  
  // Update user score
  $query = <<<SQL
INSERT INTO `like_user_scores` (`user_id`, `user_score`)
VALUES ('%s', $add_score_giver)
ON DUPLICATE KEY UPDATE user_score = user_score + $add_score_giver
SQL;
  
  $res = mysql_global_call($query, $user_id);
  
  if (!$res) {
    die("0\nDatabase Error (luls2).");
  }
  
  // Update target user score
  $query = <<<SQL
INSERT INTO `like_user_scores` (`user_id`, `user_score`)
VALUES ('%s', $add_score)
ON DUPLICATE KEY UPDATE user_score = user_score + $add_score
SQL;
  
  $res = mysql_global_call($query, $target_user_id);
  
  if (!$res) {
    die("0\nDatabase Error (luls3).");
  }
  
  return $post_likes;
}

function like_is_abusive($ip, $user_id, $target_user_id, $post_id, $pass_user = false) {
  // Check cooldown
  if ($user_id !== $ip) {
    $or_clause = "OR user_id = '%s'";
  }
  else {
    $or_clause = '';
  }
  
  $cd = LIKE_COOLDOWN_SEC;
  
  $query = <<<SQL
SELECT id FROM like_user_log
WHERE (user_id = '%s' $or_clause)
AND created_on > DATE_SUB(NOW(), INTERVAL $cd SECOND)
LIMIT 1
SQL;
  
  if ($or_clause) {
    $res = mysql_global_call($query, $user_id, $ip);
  }
  else {
    $res = mysql_global_call($query, $user_id);
  }
  
  if (!$res) {
    die("0\nDabase Error (lia0)");
  }
  
  if (mysql_num_rows($res)) {
    return true;
  }
  
  // Rangebans
  if (!$pass_user) {
    $long_ip = ip2long($ip);
    
    if ($long_ip) {
      if (isIPRangeBanned($long_ip)) {
        return true;
      }
    }
  }
  
  // IP cycling
  /*
  $user_mask = explode('.', $ip);
  
  $user_mask = ((int)$user_mask[0]) . '.' . ((int)$user_mask[1]);
  
  $query = <<<SQL
SELECT COUNT(*) as cnt FROM like_user_log
WHERE post_id = $post_id AND user_id LIKE '$user_mask.%%'
AND created_on > DATE_SUB(NOW(), INTERVAL 10 MINUTE)
SQL;
  
  $res = mysql_global_call($query);
  
  if (!$res) {
    die("0\nDabase Error (lia1)");
  }
  
  $row = mysql_fetch_row($res);
  
  if ($row && (int)$row[0] > 10) {
    return true;
  }
  */
  // Proxies
  /*
  $query = <<<SQL
SELECT COUNT(*) as cnt FROM like_user_log
WHERE target_user_id = '%s' AND suspicious = 1
AND created_on > DATE_SUB(NOW(), INTERVAL 5 MINUTE)
SQL;
  
  $res = mysql_global_call($query, $target_user_id);
  
  if (!$res) {
    die("0\nDabase Error (lia1)");
  }
  
  $row = mysql_fetch_row($res);
  
  if ($row && (int)$row[0] > 10) {
    return true;
  }
  */
  return false;
}

function like_get_target_user_id($board, $post_id) {
  // board and post_id params should already be escaped
  $query = "SELECT host, 4pass_id FROM `$board` WHERE no = $post_id AND resto > 0 AND archived = 0 LIMIT 1";
  
  $res = mysql_board_call($query);
  
  if (!$res) {
    die("0\nDabase Error (lgtu)");
  }
  
  $row = mysql_fetch_row($res);
  
  if (!$row) {
    return false;
  }
  
  if ($row[1]) {
    return array($row[1], $row[0]);
  }
  else if ($row[0]) {
    return array($row[0], $row[0]);
  }
  
  return false;
}

function like_is_duplicate($user_id, $board, $post_id) {
  // board and post_id params should already be escaped
  $query = "SELECT id FROM like_user_log WHERE user_id = '%s' AND board = '$board' AND post_id = $post_id LIMIT 1";
  
  $res = mysql_global_call($query, $user_id);
  
  if (!$res) {
    die("0\nDabase Error (lid)");
  }
  
  return mysql_num_rows($res) === 1;
}

function like_is_ip_suspicious($ip) {
  $bot_countries = array(
    'AD','AE','AF','AG','AI','AL','AM','AN','AO','AR','AS','AW','AZ',
    'BB','BD','BF','BG','BH','BI','BJ','BM','BN','BO','BR','BS','BT','BV','BW','BY','BZ',
    'CC','CF','CG','CI','CK','CL','CM','CN','CO','CR','CU','CV','CX','CY','CZ',
    'DJ','DM','DO','DZ','EC','EE','EG','EH','ER','ET','FJ','FM','FO',
    'GA','GD','GE','GF','GH','GI','GL','GM','GN','GP','GQ','GR','GS','GT','GU','GY',
    'HK','HM','HN','HT','HU','HR','ID','IL','IN','IO','IQ','IR','IS','JM','JO','JP',
    'KE','KG','KH','KI','KM','KN','KR','KW','KY','KZ',
    'LA','LB','LC','LI','LK','LR','LS','LU','LY',
    'MA','MD','MG','MH','MK','ML','MM','MN','MO','MP','MQ','MR','MS','MT','MU','MV','MW','MY','MZ','NA',
    'NE','NF','NG','NI','NP','NR','NU','NZ','OM','PA','PE','PF','PG','PH','PK','PM','PN','PR','PS','PT','PW',
    'QA','RE','RS','RO','RU','RW','SA','SB','SC','SD','SH','SI','SJ','SK','SL','SM','SN','SO','SR','ST','SV','SY','SZ',
    'TC','TD','TF','TG','TJ','TM','TN','TO','TP','TR','TT','TV','TW','TZ','UG','UM','UY','UZ',
    'VA','VE','VC','VG','VI','VN','VU','WF','WS','YE','YT','ZA','ZM','ZR','ZW'
  );
  
  if (!isset($_COOKIE['__cfduid'])) {
    return true;
  }
  
  $country = geoip_country_code_by_addr($ip);
  
  if (!$country) {
    return true;
  }
  
  if (in_array($country, $bot_countries)) {
    return true;
  }
  
  return false;
}

function like_is_ip_known($ip) {
  $query = "SELECT id FROM like_user_log WHERE user_id = '%s' LIMIT 1";
  
  $res = mysql_global_call($query, $ip);
  
  if (!$res) {
    die("0\nDabase Error (lik)");
  }
  
  return mysql_num_rows($res) === 1;
}

function like_get_user_score($no_cache = false) {
  global $captcha_bypass, $passid;
  
  // FIXME
  return 0;
  
  static $current_score = -1;
  
  if ($current_score !== -1 && $no_cache !== true) {
    return $current_score;
  }
  
  if ($captcha_bypass && $passid) {
    $user_id = $passid;
  }
  else {
    $user_id = $_SERVER['REMOTE_ADDR'];
  }
  
  $query = "SELECT user_score FROM like_user_scores WHERE user_id = '%s'";
  
  $res = mysql_global_call($query, $user_id);
  
  if (!$res) {
    return 0;
  }
  
  $row = mysql_fetch_row($res);
  
  if ($row) {
    $current_score = (int)$row[0];
  }
  else {
    $current_score = 0;
  }
  
  return $current_score;
}

function like_decrease_user_score($ip, $passid, $multiplier) {
  if ($passid) {
    $user_id = $passid;
  }
  else {
    $user_id = $ip;
  }
  
  $query = <<<SQL
UPDATE like_user_scores SET user_score = CEIL(user_score * $multiplier)
WHERE user_id = '%s' LIMIT 1
SQL;

  return !!mysql_global_call($query, $user_id);
}

function like_get_perks_state($current_score = null, $only_unlocked = false) {
  if ($current_score === null) {
    $current_score = like_get_user_score();
  }
  
  $req_points = array(
    //'showscore'    => 0,
    'smiley'       => 0,     // single emoji
    'sad'          => 0,     // single emoji
    //'coinflip'     => 0,
    //'dice+1d6'     => 0,
    'ok'           => 0,     // single emoji
    'animal'       => 0,    // random animal emoji
    'food'         => 0,    // random food emoji
    'check'        => 0,
    'cross'        => 0,
    //'nofile'       => 0,
    //'card'         => 0,    // random playing card emoji
    //'wflag'        => 0,
    //'bflag'        => 0,
    'like'         => 0,    // red heart emoji
    //'rabbit'       => 0,    // single emoji
    'unlove'       => 0,    // broken heart emoji
    'rage'         => 0,
    'perfect'      => 0,
    //'fortune'      => 0,
    //'dice+1d100'   => 0,
    //'bricks'       => 0,
    'onsen'        => 0,
    //'party'        => 0,    // partyhat image
    //'verified'     => 0,
    //'partyhat'     => 0,    // partyhat image, adjusted
    //'pickle'       => 0,    // pickle rick image
    //'trash'        => 0,    // trashcan image
    'heart'        => 0,    // random heart emoji (different colors)
    //'santa'        => 0,    // santa hat image
    'joy'          => 0,    // single emoji
    //'marquee'      => 0,
    'pig'          => 0,    // single emoji
    'dog'          => 0,    // single emoji
    'cat'          => 0,    // single emoji
    'rainbow'      => 0,
    'frog'         => 0,    // single emoji
    //'dino'         => 750,    // dinosaur gif from /fit/
    //'spooky'       => 1000,   // random skeleton
  );
  
  $perks = array();
  
  if ($only_unlocked) {
    foreach ($req_points as $perk => $score) {
      if ($current_score >= $score) {
        $perks[] = $perk;
      }
    }
  }
  else {
    foreach ($req_points as $perk => $score) {
      $perks[$perk] = $current_score >= $score;
    }
  }
  
  return $perks;
}

function like_parse_options_field($options) {
  $show_score = false;
  $active_perk = null;
  
  if (strlen($options) > 100) {
    return array($show_score, $active_perk);
  }
  
  $user_perks = like_get_perks_state();
  
  $opts = explode(' ', $options);
  
  foreach ($opts as $opt) {
    if ($user_perks[$opt] === true) {
      if ($opt === 'showscore') {
        $show_score = true;
      }
      else {
        $active_perk = $opt;
        break;
      }
    }
  }
  
  return array($show_score, $active_perk);
}

function like_build_perk_html($perk) {
  $cnt_attrs = '';
  
  switch ($perk) {
    case 'animal':
      $ary = array('&#x1F42D;','&#x1F439;','&#x1F430;','&#x1F436;','&#x1F43A;','&#x1F98A;','&#x1F435;','&#x1F438;','&#x1F648;','&#x1F649;','&#x1F64A;','&#x1F42F;','&#x1F981;','&#x1F993;','&#x1F992;','&#x1F434;','&#x1F42E;','&#x1F437;','&#x1F43B;','&#x1F43C;','&#x1F432;','&#x1F984;','&#x1F431;','&#x1F638;','&#x1F639;','&#x1F63A;','&#x1F63B;','&#x1F63C;','&#x1F63D;','&#x1F63E;','&#x1F63F;','&#x1F640;','&#x1F405;','&#x1F406;','&#x1F418;','&#x1F98F;','&#x1F402;','&#x1F403;','&#x1F404;','&#x1F40E;','&#x1F98C;','&#x1F410;','&#x1F40F;','&#x1F411;','&#x1F416;','&#x1F417;','&#x1F42A;','&#x1F42B;','&#x1F98D;','&#x1F409;','&#x1F996;','&#x1F995;','&#x1F408;','&#x1F400;','&#x1F401;','&#x1F407;','&#x1F412;','&#x1F415;','&#x1F429;','&#x1F428;','&#x1F43F;','&#x1F994;','&#x1F987;','&#x1F40D;','&#x1F985;','&#x1F989;','&#x1F986;','&#x1F413;','&#x1F414;','&#x1F983;','&#x1F54A;','&#x1F423;','&#x1F424;','&#x1F425;','&#x1F426;','&#x1F427;','&#x1F40B;','&#x1F433;','&#x1F42C;','&#x1F988;','&#x1F41F;','&#x1F420;','&#x1F421;','&#x1F419;','&#x1F991;','&#x1F990;','&#x1F980;','&#x1F41A;','&#x1F40C;','&#x1F422;','&#x1F98E;','&#x1F40A;','&#x1F3C7;','&#x1F3A0;','&#x2658;','&#x265E;','&#x1F43D;','&#x1F43E;','&#x1F463;','&#x1F400;','&#x1F403;','&#x1F405;','&#x1F407;','&#x1F409;','&#x1F40D;','&#x1F40E;','&#x1F410;','&#x1F412;','&#x1F413;','&#x1F415;','&#x1F416;');
      $html = $ary[array_rand($ary)];
      break;
    case 'food':
      $ary = array('&#x1F9C0;','&#x1F95A;','&#x1F373;','&#x1F95E;','&#x1F360;','&#x1F35E;','&#x1F950;','&#x1F956;','&#x1F968;','&#x1F354;','&#x1F355;','&#x1F35D;','&#x1F35F;','&#x1F364;','&#x1F32D;','&#x1F32E;','&#x1F32F;','&#x1F35B;','&#x1F959;','&#x1F958;','&#x1F957;','&#x1F96A;','&#x1F96B;','&#x1F953;','&#x1F356;','&#x1F357;','&#x1F969;','&#x1F962;','&#x1F961;','&#x1F95F;','&#x1F35A;','&#x1F35C;','&#x1F372;','&#x1F960;','&#x1F358;','&#x1F359;','&#x1F363;','&#x1F365;','&#x1F371;','&#x1F361;','&#x1F362;','&#x1F347;','&#x1F348;','&#x1F349;','&#x1F34A;','&#x1F34B;','&#x1F34C;','&#x1F34D;','&#x1F34E;','&#x1F34F;','&#x1F350;','&#x1F351;','&#x1F352;','&#x1F353;','&#x1F95D;','&#x1F965;','&#x1F966;','&#x1F344;','&#x1F345;','&#x1F346;','&#x1F336;','&#x1F951;','&#x1F955;','&#x1F952;','&#x1F954;','&#x1F95C;','&#x1F370;','&#x1F382;','&#x1F967;','&#x1F368;','&#x1F366;','&#x1F369;','&#x1F36A;','&#x1F37F;','&#x1F36E;','&#x1F36F;','&#x1F367;','&#x1F36B;','&#x1F36C;','&#x1F36D;','&#x1F37A;','&#x1F37B;','&#x1F377;','&#x1F378;','&#x1F379;','&#x1F376;','&#x1F942;','&#x1F943;','&#x1F37E;','&#x2615;','&#x1F375;','&#x1F95B;','&#x1F37C;','&#x1F964;','&#x1F374;','&#x1F37D;','&#x1F963;','&#x1F944;');
      $html = $ary[array_rand($ary)];
      break;
    case 'marquee':
      $ary = array('&#x1F996;','&#x26BD;','&#x1F3C0;','&#x26BE;');
      $ico = $ary[array_rand($ary)];
      $html = <<<HTML
<marquee direction="left" width="250" height="50" behavior="alternate">
<marquee direction="down" height="50" behavior="alternate">$ico</marquee>
</marquee>
HTML;
      break;
    case 'rainbow':
      $html = '&#x1F308;';
      break;
    case 'wflag':
      $html = '&#x1F3F3;&#xFE0F;';
      break;
    case 'bflag':
      $html = '&#x1F3F4;';
      break;
    case 'onsen':
      $html = '&#x2668;&#xFE0F;';
      break;
    case 'rage':
      $html = '&#x1F4A2;';
      break;
    case 'perfect':
      $html = '&#x1F4AF;';
      break;
    case 'check':
      $html = '&#x2714;&#xFE0F;';
      break;
    case 'cross':
      $html = '&#x274C;';
      break;
    case 'heart':
      $ary = array('&#x2764;&#xFE0F;','&#x1F499;','&#x1F49C;','&#x1F49B;','&#x1F5A4;','&#x1F49A;');
      $html = $ary[array_rand($ary)];
      break;
    case 'card':
      $ary = array('&#x2660;&#xFE0F;','&#x2665;&#xFE0F;','&#x2666;&#xFE0F;','&#x2663;&#xFE0F;');
      $html = $ary[array_rand($ary)];
      break;
    case 'like':
      $html = '&#x2764;&#xFE0F;';
      break;
    case 'unlove':
      $html = '&#x1F494;';
      break;
    case 'smiley':
      $html = '&#x1F603;';
      break;
    case 'sad':
      $html = '&#x1F641;';
      break;
    case 'ok':
      $html = '&#x1F44C;';
      break;
    case 'coinflip':
      $html = '<b style="font-size: 14px">Coin Flip: ' . (mt_rand(0, 1) === 1 ? 'Heads' : 'Tails') . '</b>';
      break;
    case 'party':
      $html = '<img alt="" width="160" height="160" src="//s.4cdn.org/image/partyhat.gif">';
      break;
    case 'partyhat':
      $cnt_attrs = ' style="position:absolute"';
      $html = '<img alt="" style="position:absolute;margin-left:-25px;margin-top:-80px;pointer-events:none;" width="80" height="80" src="//s.4cdn.org/image/partyhat.gif">';
      break;
    case 'pickle':
      $html = '<img alt="" width="32" height="32" src="//s.4cdn.org/image/pckl.png">';
      break;
    case 'nofile':
      $html = '<img alt="" width="77" height="13" src="//s.4cdn.org/image/nofile.png">';
      break;
    case 'trash':
      $html = '<img alt="" width="32" height="32" src="//s.4cdn.org/image/trash@2x.gif">';
      break;
    case 'bricks':
      $html = '<img alt="" width="60" height="60" src="//s.4cdn.org/image/ba.gif">';
      break;
    case 'pig':
      $html = '&#x1F437;';
      break;
    case 'santa':
      $html = '<img alt="" width="160" height="160" src="//s.4cdn.org/image/xmashat.gif">';
      break;
    case 'verified':
      $html = '<div style="text-align:right"><img alt="" width="32" height="32" src="//s.4cdn.org/image/temp/verified.png"></div>';
      break;
    case 'joy':
      $html = '&#x1F602;';
      break;
    case 'rabbit':
      $html = '&#x1F430;';
      break;
    case 'frog':
      $html = '&#x1F438;';
      break;
   case 'dog':
      $html = '&#x1F436;';
      break;
    case 'cat':
      $html = '&#x1F431;';
      break;
    case 'dino':
      $html = '<img alt="" width="451" height="75" src="//s.4cdn.org/image/temp/dinosaur.gif">';
      break;
    case 'spooky':
      $id = mt_rand(1, 23);
      $html = '<img alt="" src="//s.4cdn.org/image/skeletons/' . $id . '.gif">';
      break;
    default:
      return null;
      break;
  }
  
  return '<div' . $cnt_attrs . ' class="like-perk-cnt">' . $html . '</div>';
}
