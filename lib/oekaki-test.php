<?php

// $file: $_FILES array entry for the .tgkr file
// returns true if the replay file is valid,
// false if the replay should be ignored, and errors out in all other cases.
function oekaki_validate_replay($file) {
  $max_size = 6 * 1024 * 1024;
  $max_data_size = 15 * 1024 * 1024; // uncompresed size
  
  if ($file['error'] > 0) {
    error(S_FAILEDUPLOAD);
  }
  
  if ($file['size'] === 0) {
    error(S_NOREC);
  }
  
  if ($file['size'] > $max_size) {
    error(S_TOOLARGE);
  }
  
  $tmp_file = $file['tmp_name'];
  
  if (is_uploaded_file($tmp_file) !== true) {
    error(S_FAILEDUPLOAD);
  }
  
  // Check the actual data now
  
  $f = fopen($tmp_file, 'rb');
  
  $magic = fread($f, 4);
  
  $decompressed_size = fread($f, 4);
  
  fread($f, 4); // version numbers
  
  $compressed_data = fread($f, $file['size'] - 12);
  
  fclose($f);
  
  if ($magic !== "\x54\x47\x4B\x01") { // TGK 0x01
    error(S_NOREC);
  }
  
  $decompressed_size = (int)unpack('N', $decompressed_size)[1];
  
  if (!$decompressed_size || $decompressed_size <= 0) {
    error(S_NOREC);
  }
  
  if ($decompressed_size > $max_data_size) {
    return false;
  }
  
  if (!$compressed_data) {
    error(S_NOREC);
  }
  
  $data = gzinflate($compressed_data, $decompressed_size);
  
  if ($data === false) {
    error(S_NOREC);
  }
  
  $meta_size = (int)unpack('n', $data)[1];
  
  if (!$meta_size) {
    return false;
  }
  
  // tool count (byte), tool entry size (byte)
  $tool_meta = unpack('C2', substr($data, $meta_size, 2));
  
  if (!$tool_meta || !(int)$tool_meta[1] || !(int)$tool_meta[2]) {
    return false;
  }
  
  $events_pos = $meta_size + ((int)$tool_meta[1]) * ((int)$tool_meta[2]) + 2;
  
  $event_count = (int)unpack('N', substr($data, $events_pos, 4))[1];
  
  if (!$event_count || $event_count > 8640000) {
    return false;
  }
  
  $prelude_type = unpack('C', substr($data, $events_pos + 4, 1));
  
  if ($prelude_type === false || (int)$prelude_type[1] !== 0) {
    return false;
  }
  
  $conclusion_type = unpack('C', substr($data, -5, 1));
  
  if ($conclusion_type === false || (int)$conclusion_type[1] !== 255) {
    return false;
  }
  
  return true;
}

function oekaki_get_valid_src_pid($src_pid, $board, $thread_id) {
  $src_pid = (int)$src_pid;

  if ($src_pid < 1) {
    return null;
  }

  $thread_id = (int)$thread_id;

  if ($thread_id < 1) {
    return null;
  }
  
  $sql = "SELECT no FROM `%s` WHERE no = $src_pid AND (resto = $thread_id OR resto = 0) AND tim != 0 LIMIT 1";
  
  $res = mysql_board_call($sql, $board);
  
  if (!$res || mysql_num_rows($res) !== 1) {
    return null;
  }
  
  return $src_pid;
}

function oekaki_format_info($time, $replay_tim, $src_pid) {
  $time = (int)$time;
  
  if ($time < 1 || $time > 5184000) { // 60 days
    return '';
  }
  
  if ($time < 60) {
    $time_str = $time . 's';
  }
  else if ($time < 3600) {
    $time_str = round($time / 60) . 'm';
  }
  else {
    $time_str = (int)($time / 3600) . 'h ' . round(($time % 3600) / 60) . 'm';
  }
  
  if ($replay_tim && !$src_pid) {
    $replay_tim = (int)$replay_tim;
    $replay_link = ", Replay: <a href=\"javascript:oeReplay($replay_tim);\">View</a>";
  }
  else {
    $replay_link = '';
  }
  
  if ($src_pid) {
    $src_pid = (int)$src_pid;
    $src_link = ", Source: &gt;&gt;$src_pid";
  }
  else {
    $src_link = '';
  }
  
  return "<br><br><small><b>Oekaki Post</b> (Time: $time_str" . $replay_link . $src_link . ")</small>";
}
