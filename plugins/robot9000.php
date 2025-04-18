<?php
# Parameters:
# com: user supplied comment field (before or after wordfilters? dunno)
# md5: md5 of the supplied image. null if no image.
# ip: the IP of the user, in integer (packed) form
#
# Return value: A string. If the string is "OK", the post should go through.
# If the string is anything else, abort posting and display that message
# to the user.
#
# Integration info;
# This should run before wordfilters (including >>num) and duplicate(md5) detection.
# It doesn't know about bans, so those need to be done seperately, but it
# doesn't care if that is before or after.
# It really should run after valid file checks (jpg/png/gif, >0x0, etc) but that's not critical.
#
# Synchronization: There is (in theory) a minor race condition because the tables are not locked.
# It's not exploitable for any useful purpose, and it's blocked by the floodcheck
#
# Changelog:
# 2008/02/20 04:20: Added changelog, fixed $txt error that killed all posts
# 2008/02/20 04:56: Fixed the signal-ratio filter to handle the stupid HTML
# 2008/02/20 05:21: Added a check for repeated characters
# 2008/02/20 07:05: Added a check for long spams
# 2008/02/20 13:02: Rearranged the filters for better results.
# 2008/02/20 23:58: Fixed a bug that broke posts with two quotes far apart
# 2008/02/21 01:57: Fixed a dumb bug with the number filter..
# 2008/02/21 02:06: Adding content-percentage info to the content filter.
# 2008/02/21 02:15: Adjusted long-text filter.
# 2008/02/21 02:18: Removed long-text filter.
# 2008/02/22 16:43: Added mute-expiring.
# 2008/02/22 17:54: Fixed mute-expiring.
# 2008/02/22 18:13: Added #nextnow and #muteinfo secret mod capcodes
# 2008/02/22 18:21: Fixed #muteinfo for mods.
# 2015/10/24 16:36: Cleanup the code and put the robot back.
#                   $email, $sub, $name fields aren't used anymore.
#                   removed $mod parameter.
# 2020/11/16 08:09: Update text hashes for every post to prune stale entries

define('R9K_SIGNAL_RATIO', 0.1);
define('R9K_MAX_DURATION', 31536000); // one year
define('R9K_DATE_FORMAT', '%m/%d/%y %H:%M:%S');
define('R9K_DEMUTE_PERIOD', 86400); // one day
define('R9K_SNR_MIN_LEN', 10); // minimum txt length for signal ratio check

define('R9K_OK', 'OK');
define('R9K_DB_ERROR', 'Database error.');
define('R9K_EMPTY_COM', 'Textless posts are not allowed.');
define('R9K_ASCII_ONLY', 'Non-ASCII text is not allowed.');
define('R9K_MUTED', "You're muted! You cannot post until %s, %s from now");
define('R9K_MUTE_ERROR', "You have been muted for %s, because %s");
define('R9K_LOW_SNR', 'your comment was too low in content (%0.2f%% content).');
define('R9K_DUP_TXT', 'your comment was not original.');
define('R9K_DUP_IMG', 'your image was not original.');

function r9k_process($com, $md5, $ip) {
  // Blank file
  if ($md5 == 'd41d8cd98f00b204e9800998ecf8427e') {
    $md5 = null;
  }
  
  if ($com === ''){
    return R9K_EMPTY_COM;
  }
  
	if (preg_match('/[\\x80-\\xFF]/', $com)) {
		return R9K_ASCII_ONLY;
	}
  
  $table_mutes = ROBOT9000_MUTES;
  $table_posts = ROBOT9000_POSTS;
  
  $ip = (int)$ip;
  
  $mute = false;
  $demute = false;
  $timeout_power = 0;
  
  $query = <<<SQL
SELECT timeout_power,
UNIX_TIMESTAMP(mute_until) as mute_until,
UNIX_TIMESTAMP(next_expire) as next_expire
FROM `$table_mutes` WHERE ip = $ip
SQL;
  
  $res = mysql_board_call($query);
  
  if (!$res) {
    //return R9K_OK;
    return R9K_DB_ERROR;
  }
  
  $row = mysql_fetch_assoc($res);
  
  if ($row) {
    $now = time();
    $timeout_power = $row['timeout_power'];
    
    if ($row['mute_until'] > $now) {
      $duration = r9k_pretty_duration($row['mute_until'] - $now);
      $when = strftime(R9K_DATE_FORMAT, $row['mute_until']);
      return sprintf(R9K_MUTED, $when, $duration);
    }
    
    if ($row['next_expire'] < $now){
      $demute = true;
    }
  }
  
  $txt = strtolower($com);
  
  // Strip HTML
  $stxt=preg_replace('/<.*?>/s','', $txt);
  
  // Original byte length
  $olength = strlen($stxt);
  
  // Strip >>123 quotelinks
  $stxt = preg_replace('/&gt;&gt;\d+/', '', $stxt);
  
  // Strip html entities
  $stxt = preg_replace('/&#?\w+;/', '', $stxt);
  
  // Strip non-alnum chars
  $stxt = preg_replace('/[^a-z\d-]+/', '', $stxt);
  
  // Trim leading and trailing numeric characters
  $stxt = preg_replace('/^\d*(.*)\d*$/', '\1', $stxt);
  
  // Compress repeated characters: aaa -> a
  $stxt = preg_replace('/(.)\\1{2,}/', '\\1', $stxt);
  
  // Check signal ratio
  if (strlen($txt) > R9K_SNR_MIN_LEN) {
    $ratio = strlen($stxt) / $olength;
    
    if ($ratio < R9K_SIGNAL_RATIO) {
      $mute = sprintf(R9K_LOW_SNR, $ratio * 100.0);
    }
  }
  
  if ($mute === false) {
    $txt_hash = md5($stxt);
    
    // Check if hashes match
    $query = "SELECT text, image FROM `$table_posts` WHERE text = '%s'";
    /*
    if ($md5) {
      $query .= " OR image = '%s'";
      $res = mysql_board_call($query, $txt_hash, $md5);
    }
    else {*/
      $res = mysql_board_call($query, $txt_hash);
    //}
    
    if (!$res) {
      //return R9K_OK;
      return R9K_DB_ERROR;
    }
    
    // Post is good. Insert hashes.
    if (mysql_num_rows($res) < 1) {
      $query = "INSERT INTO `$table_posts` (text) VALUES('%s')";
      mysql_board_call($query, $txt_hash);
    }
    // Duplicates found.
    else {
      //$row = mysql_fetch_assoc($res);
      
      //if ($row['text'] === $txt_hash) {
        $mute = R9K_DUP_TXT;
      //}
      //else if ($md5 && $row['image'] === $md5) {
      //  $mute = R9K_DUP_IMG;
      //}
      
      // Update the hash with a new timestamp
      $query = "UPDATE `$table_posts` SET created_on = NOW() WHERE text = '%s' LIMIT 1";
      mysql_board_call($query, $txt_hash);
    }
  }
  
  // Muted
  if ($mute !== false) {
    ++$timeout_power;
    
    $mute_duration = pow(2, $timeout_power);
    
    if ($mute_duration > R9K_MAX_DURATION) {
      $timeout_power--;
      $mute_duration = R9K_MAX_DURATION;
    }
    
    $next_expire = R9K_DEMUTE_PERIOD;
    
    $query = <<<SQL
INSERT INTO `$table_mutes` (ip, timeout_power, mute_until, next_expire)
VALUES ($ip, $timeout_power, DATE_ADD(NOW(), INTERVAL $mute_duration SECOND),
DATE_ADD(NOW(), INTERVAL $mute_duration SECOND))
ON DUPLICATE KEY
UPDATE timeout_power = $timeout_power, mute_until = VALUES(mute_until),
next_expire = VALUES(next_expire)
SQL;
    
    $res = mysql_board_call($query);
    
    return sprintf(R9K_MUTE_ERROR, r9k_pretty_duration($mute_duration), $mute);
  }
  // Not muted
  else {
    if ($demute === true) {
      $next_expire = R9K_DEMUTE_PERIOD;
      
      $query = <<<SQL
UPDATE `$table_mutes` SET
timeout_power = IF(timeout_power > 0, timeout_power - 1, 0),
next_expire = DATE_ADD(NOW(), INTERVAL $next_expire SECOND)
WHERE ip = $ip
SQL;
      
      $res = mysql_board_call($query);
    }
    
    return R9K_OK;
  }
}

function r9k_pretty_duration($secs){
  $w = (int)($secs / 604800);
  $d = (int)($secs / 86400) % 7;
  $h = (int)($secs / 3600) % 24;
  $m = ((int)($secs / 60)) % 60;
  $s = ((int)$secs) % 60;
  $out = array();
  $pairs = array(
    array($w, 'week'),
    array($d, 'day'),
    array($h, 'hour'),
    array($m, 'minute'),
    array($s, 'second')
  );
  
  foreach($pairs as $v){
    if ($v[0] !== 0) {
      $out[] = $v[0] . ' ' . $v[1] . ($v[0] === 1 ? '' : 's');
    }
  }
  
  return implode(' ', $out);
}
