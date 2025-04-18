<?php
http_response_code(404);
echo('File not found.');
die();
//define('DEV_MODE', $_SERVER['REMOTE_ADDR'] === '51.159.28.165');
//define('DEV_MODE', false);
/*
if (!DEV_MODE) {
  http_response_code(404);
  echo('File not found.');
  die();
}
else {
  ini_set('display_errors', 1);
  error_reporting(E_ALL & ~E_NOTICE);
}
*/
header('Content-Type: application/json');

require_once 'lib/db.php';
require_once 'lib/userpwd.php';

define('XA_DOMAIN', ($_SERVER['HTTP_HOST'] === 'sys.4chan.org') ? '4chan.org' : '4channel.org');
define('XA_PWD_TTL', 3600); // 1h
define('XA_COOKIE_TTL', 172800); // 48h

define('HMAC_SECRET', '58c7716fe310556e782b45610b4c1202f56df9f6de2bad92f7df17a7ae80a288');

define('ERR_BAD_REQ', 'Bad Request');
define('ERR_BAD_TKN', 'Cookies need to be enabled');
define('ERR_NO_PTS', 'Not enough points');
define('ERR_ALREADY_OWNED', 'You already have that emote');
define('ERR_GENERIC', 'Internal Server Error');

define('XA_ROLL_PRICE', 10);
define('XA_TICK_POINTS', 20);
define('XA_TICK_INTERVAL', 300);

// ------------

$emotes = [
  // Image emotes (type 2, 3): [ type, filename prefix, width, height ]
  // 81 emotes
  'AngryWojak' => [ 2, '03d18964', 27, 32 ],
  'Aquacry' => [ 2, '83cf2699', 30, 30 ],
  'AWOOOO' => [ 2, 'e2ad2cb7', 28, 32 ],
  'AYAYA' => [ 2, 'f93f9e5a', 32, 31 ],
  'AYAYAHyper' => [ 2, 'bc1ff2b8', 32, 32 ],
  'BOOBA' => [ 2, 'fb56168f', 32, 32 ],
  'BOOMER' => [ 2, '51ca59c2', 32, 32 ],
  'Bruh' => [ 2, '67905c4f', 32, 32 ],
  'Catcry' => [ 2, '967f06c9', 28, 28 ],
  'ChadYes' => [ 2, 'c7375c9d', 31, 32 ],
  'COPIUM' => [ 2, '4dfb5c71', 32, 31 ],
  'DontBully' => [ 2, '199f7d0e', 31, 32 ],
  'EZY' => [ 2, 'adf2d2f0', 28, 26 ],
  'FeelsBadMan' => [ 2, '59b6bba6', 30, 29 ],
  'FeelsGoodMan' => [ 2, 'cda7b2fb', 32, 24 ],
  'FeelsOkayMan' => [ 2, '08b66b75', 28, 27 ],
  'FeelsSpecialMan' => [ 2, '25086889', 28, 25 ],
  'FeelsStrongMan' => [ 2, '0ee6ba1c', 32, 31 ],
  'FeelsWeirdMan' => [ 2, 'ad2977e6', 28, 27 ],
  'gachiGASM' => [ 2, 'c291d202', 24, 28 ],
  'gachiHYPER' => [ 2, '634a21ba', 24, 28 ],
  'Gigachad' => [ 2, '7a95728b', 29, 32 ],
  'GoodNight' => [ 2, 'a6d16707', 31, 32 ],
  'Hahaa' => [ 2, 'af528e56', 28, 28 ],
  'HeavyBreathing' => [ 2, '4623886c', 32, 32 ],
  'KannaNom' => [ 2, '5de4addd', 32, 32 ],
  'KannaPolice' => [ 2, '98cf0be7', 32, 32 ],
  'KEKW' => [ 2, 'e54792d7', 32, 32 ],
  'KEKWait' => [ 2, 'c2cfb2e3', 32, 32 ],
  'MarisaFace' => [ 2, '857a9ea0', 24, 24 ],
  'MeguminHappy' => [ 2, 'a19762fc', 32, 32 ],
  'MikuStare' => [ 2, 'b674048b', 32, 32 ],
  'monkaChrist' => [ 2, '48c107b3', 28, 28 ],
  'monkaGIGA' => [ 2, 'de27847b', 28, 28 ],
  'monkaH' => [ 2, 'acb11630', 28, 28 ],
  'monkaHmm' => [ 2, 'd3c674ba', 32, 32 ],
  'monkaMEGA' => [ 2, '0b3318e4', 28, 28 ],
  'monkaOMEGA' => [ 2, 'bb299b4d', 32, 32 ],
  'monkaS' => [ 2, 'ed1cc57f', 28, 28 ],
  'monkaSpeed' => [ 2, '87c89650', 32, 30 ],
  'monkaW' => [ 2, 'b05923f5', 32, 32 ],
  'nepSmug' => [ 2, '57b01648', 32, 32 ],
  'OMEGALUL' => [ 2, 'c4035570', 31, 32 ],
  'peepoBlanket' => [ 2, '099390a2', 31, 32 ],
  'peepoClown' => [ 2, '8ea2d160', 32, 32 ],
  'peepoHappy' => [ 2, '68104e2a', 28, 20 ],
  'peepoWTF' => [ 2, '3d8675e9', 28, 19 ],
  'Pepega' => [ 2, '1ee7c5a1', 32, 25 ],
  'PepeHands' => [ 2, 'f2ecf801', 32, 32 ],
  'PepeLaugh' => [ 2, '51cbf903', 30, 29 ],
  'PepeLmao' => [ 2, '25908e08', 28, 28 ],
  'pepePoint' => [ 2, '90786369', 32, 32 ],
  'PepoG' => [ 2, '4459d60b', 32, 26 ],
  'pepoRope' => [ 2, '6ec0dd2c', 32, 31 ],
  'PepoThink' => [ 2, '9ecd704b', 32, 31 ],
  'pikachuS' => [ 2, '42faedcc', 32, 32 ],
  'PillowNo' => [ 2, '1e4d8dfa', 32, 32 ],
  'PillowYes' => [ 2, '6f5bc7e5', 32, 32 ],
  'Pog' => [ 2, 'fad6951c', 28, 28 ],
  'POGGERS' => [ 2, '6f0d4e37', 32, 32 ],
  'PressF' => [ 2, 'f0a256b9', 32, 30 ],
  'REEeee' => [ 2, 'b06b1566', 32, 32 ],
  'REEEEE' => [ 2, 'ba70c4d9', 32, 28 ],
  'ReimuGlare' => [ 2, 'bdf28159', 32, 32 ],
  'ReimuPalm' => [ 2, '41a37aa0', 32, 32 ],
  'SadCatW' => [ 2, 'd8f61d71', 32, 32 ],
  'Sadge' => [ 2, 'e024965e', 28, 22 ],
  'SeetheWojak' => [ 2, 'a9f848d3', 28, 32 ],
  'Stonks' => [ 2, '53478ca5', 31, 32 ],
  'ThisIsFine' => [ 2, 'd9bf8456', 28, 31 ],
  'Thonk' => [ 2, 'ec538b5c', 32, 27 ],
  'TooLewd' => [ 2, 'ddc55766', 32, 32 ],
  'Tuturu' => [ 2, 'c72e8e84', 32, 32 ],
  'umaruCry' => [ 2, '7242c342', 28, 28 ],
  'WanWan' => [ 2, '8a527ac8', 32, 31 ],
  'WeirdChamp' => [ 2, '3021a426', 31, 32 ],
  'weSmart' => [ 2, '6476e57d', 27, 28 ],
  'wojakNPC' => [ 2, '24edafcc', 32, 32 ],
  'wojakWithered' => [ 2, 'ed7d4c3a', 32, 30 ],
  'WTFF' => [ 2, '8b7cc3e0', 32, 32 ],
  'YEP' => [ 2, 'e1899bbe', 32, 31 ],
  'YesHoney' => [ 2, '2b414cf1', 31, 25 ],
  // 28 emotes
  'bane' => [ 3, 'c458ef22', 32, 32 ],
  'bog' => [ 3, 'c2e2602a', 32, 32 ],
  'cia' => [ 3, 'c69a1ef1', 32, 32 ],
  'cockmongler' => [ 3, 'eda6f332', 22, 32 ],
  'desu' => [ 3, '80692b94', 28, 32 ],
  'desusmirk' => [ 3, '72694e0e', 41, 32 ],
  'frodo' => [ 3, 'e9d526e8', 32, 32 ],
  'goldface' => [ 3, '7081142e', 32, 32 ],
  'happycat' => [ 3, '1d3f2a13', 27, 32 ],
  'happyn' => [ 3, 'afd49202', 25, 32 ],
  'jannydog' => [ 3, 'f0dcbf8a', 35, 32 ],
  'koiwai' => [ 3, '1d7e369a', 44, 32 ],
  'koiwaiwave' => [ 3, '0e313986', 31, 32 ],
  'konata' => [ 3, 'eb07a2c8', 32, 32 ],
  'laughingw' => [ 3, '6e6217c7', 32, 32 ],
  'longcat' => [ 3, '0ee48fb4', 37, 32 ],
  'longcata' => [ 3, '95c37417', 37, 30 ],
  'longcatb' => [ 3, 'e77bc341', 37, 32 ],
  'moetron' => [ 3, 'cf1d4b8d', 32, 32 ],
  'mudkip' => [ 3, 'a4b23eff', 31, 32 ],
  'shoopdw' => [ 3, '11339e7b', 23, 32 ],
  'shoopdw2' => [ 3, '49bde730', 100, 32 ],
  'troll' => [ 3, 'd89a0070', 37, 32 ],
  'trollface' => [ 3, '7b4acfbf', 32, 26 ],
  'yaranaika' => [ 3, 'a6955123', 32, 32 ],
  'yaranaika2' => [ 3, '4d00227b', 32, 32 ],
  
  // Unicode emojis (type 1): [ type, html entity ]
  // 58 emojis
  'happy' => [ 1, '&#x1F600;' ],
  'grin' => [ 1, '&#x1F604;' ],
  'xd' => [ 1, '&#x1F606;' ],
  'grinsweat' => [ 1, '&#x1F605;' ],
  'rofl' => [ 1, '&#x1F923;' ],
  'lmao' => [ 1, '&#x1F602;' ],
  'smile' => [ 1, '&#x1F642;' ],
  'wink' => [ 1, '&#x1F609;' ],
  'glad' => [ 1, '&#x1F60A;' ],
  'kiss' => [ 1, '&#x1F619;' ],
  'crazy' => [ 1, '&#x1F92A;' ],
  'think' => [ 1, '&#x1F914;' ],
  'wot' => [ 1, '&#x1F928;' ],
  'kay' => [ 1, '&#x1F610;' ],
  'yikes' => [ 1, '&#x1F612;' ],
  'eyeroll' => [ 1, '&#x1F644;' ],
  'confused' => [ 1, '&#x1F615;' ],
  'pensive' => [ 1, '&#x1F614;' ],
  'disgust' => [ 1, '&#x1F922;' ],
  'vomit' => [ 1, '&#x1F92E;' ],
  'dizzy' => [ 1, '&#x1F635;' ],
  'nerd' => [ 1, '&#x1F913;' ],
  'worry' => [ 1, '&#x1F61F;' ],
  'sad' => [ 1, '&#x1F641;' ],
  'frown' => [ 1, '&#x2639;&#xFE0F;' ],
  'wow' => [ 1, '&#x1F632;' ],
  'blush' => [ 1, '&#x1F633;' ],
  'cry' => [ 1, '&#x1F622;' ],
  'plead' => [ 1, '&#x1F97A;' ],
  'baw' => [ 1, '&#x1F62D;' ],
  'shock' => [ 1, '&#x1F631;' ],
  'anguish' => [ 1, '&#x1F627;' ],
  'devil' => [ 1, '&#x1F608;' ],
  'angry' => [ 1, '&#x1F620;' ],
  'struggle' => [ 1, '&#x1F623;' ],
  'proud' => [ 1, '&#x1F624;' ],
  'smirk' => [ 1, '&#x1F60F;' ],
  'drool' => [ 1, '&#x1F924;' ],
  'love' => [ 1, '&#x1F60D;' ],
  'skull' => [ 1, '&#x1F480;' ],
  'clown' => [ 1, '&#x1F921;' ],
  'alien' => [ 1, '&#x1F47D;' ],
  'robot' => [ 1, '&#x1F916;' ],
  'ok' => [ 1, '&#x1F44C;' ],
  'fu' => [ 1, '&#x1F595;' ],
  'thup' => [ 1, '&#x1F44D;' ],
  'thdown' => [ 1, '&#x1F44E;' ],
  'punch' => [ 1, '&#x1F44A;' ],
  'pray' => [ 1, '&#x1F64F;' ],
  'flex' => [ 1, '&#x1F4AA;' ],
  'eyes' => [ 1, '&#x1F440;' ],
  'drip' => [ 1, '&#x1F4A6;' ],
  'wind' => [ 1, '&#x1F4A8;' ],
  'fire' => [ 1, '&#x1F525;' ],
  'clover' => [ 1, '&#x1F340;' ],
  'anger' => [ 1, '&#x1F4A2;' ],
  'perfect' => [ 1, '&#x1F4AF;' ],
  'zzz' => [ 1, '&#x1F4A4;' ]
];

$emote_pools = [
  // 58
  ['happy','grin','xd','grinsweat','rofl','lmao','smile','wink','glad','kiss',
  'crazy','think','wot','kay','yikes','eyeroll','confused','pensive','disgust',
  'vomit','dizzy','nerd','worry','sad','frown','wow','blush','cry','plead','baw',
  'shock','anguish','devil','angry','struggle','proud','smirk','drool','love',
  'skull','clown','alien','robot','ok','fu','thup','thdown','punch','pray',
  'flex','eyes','drip','wind','fire','clover','anger','perfect','zzz'],
  
  // 81
  ['03d18964','83cf2699','e2ad2cb7','f93f9e5a','bc1ff2b8','fb56168f','51ca59c2',
  '67905c4f','967f06c9','c7375c9d','4dfb5c71','199f7d0e','adf2d2f0','59b6bba6',
  'cda7b2fb','08b66b75','25086889','0ee6ba1c','ad2977e6','c291d202','634a21ba',
  '7a95728b','a6d16707','af528e56','4623886c','5de4addd','98cf0be7','e54792d7',
  'c2cfb2e3','857a9ea0','a19762fc','b674048b','48c107b3','de27847b','acb11630',
  'd3c674ba','0b3318e4','bb299b4d','ed1cc57f','87c89650','b05923f5','57b01648',
  'c4035570','099390a2','8ea2d160','68104e2a','3d8675e9','1ee7c5a1','f2ecf801',
  '51cbf903','25908e08','90786369','4459d60b','6ec0dd2c','9ecd704b','42faedcc',
  '1e4d8dfa','6f5bc7e5','fad6951c','6f0d4e37','f0a256b9','b06b1566','ba70c4d9',
  'bdf28159','41a37aa0','d8f61d71','e024965e','a9f848d3','53478ca5','d9bf8456',
  'ec538b5c','ddc55766','c72e8e84','7242c342','8a527ac8','3021a426','6476e57d',
  '24edafcc','ed7d4c3a','8b7cc3e0','e1899bbe','2b414cf1'],
  
  // 28
  ['c458ef22','c2e2602a','c69a1ef1','eda6f332','80692b94','72694e0e','e9d526e8',
  '7081142e','1d3f2a13','afd49202','f0dcbf8a','1d7e369a','0e313986','eb07a2c8',
  '6e6217c7','0ee48fb4','95c37417','e77bc341','cf1d4b8d','a4b23eff','11339e7b',
  '49bde730','d89a0070','7b4acfbf','a6955123','4d00227b']
];

// ------------

function xa_error($msg, $extra = null) {
  $data = array('status' => 'error', 'msg' => $msg);
  
  if ($extra) {
    $data = array_merge($data, $extra);
  }
  
  echo json_encode($data);
  
  die();
}

function xa_success($data = null) {
  $ret = array('status' => 'success');
  
  if ($data) {
    $ret['data'] = $data;
  }
  
  echo json_encode($ret);
  
  die();
}

/**
 * Sessions
 */
function xa_start_session() {
  // Recover previous session by sid
  if (isset($_COOKIE['xa_sid']) && $_COOKIE['xa_sid']) {
    $data = xa_recover_session_by_sid($_COOKIE['xa_sid']);
    
    if ($data) {
      xa_success($data);
    }
  }
  
  $ip = $_SERVER['REMOTE_ADDR'];
  
  // Recover previous session by IP
  $sid = hash_hmac('sha1', $ip, HMAC_SECRET);
  
  if (!$sid) {
    xa_error(ERR_GENERIC . ' (ssx9)');
  }  
  
  $data = xa_recover_session_by_sid($sid);
  
  if ($data) {
    xa_set_sid_cookie($sid);
    xa_success($data);
  }
  
  // Create new session
  $data = [];
  
  if (isset($_COOKIE['4chan_pass'])) {
    $userpwd = new UserPwd($ip, XA_DOMAIN, $_COOKIE['4chan_pass']);
    
    if ($userpwd->maskLifetime() >= XA_PWD_TTL) {
      $balance = 100;
    }
  }
  else {
    $balance = 0;
  }
  
  $data['start_ts'] = $_SERVER['REQUEST_TIME'];
  $data['balance'] = $balance;
  $data['owned'] = [];
  
  $ret = xa_save_session($sid, $ip, $data);
  
  if (!$ret) {
    xa_error(ERR_GENERIC . ' (ssx5)');
  }
  
  xa_set_sid_cookie($sid);
  xa_success($data);
}

function xa_set_sid_cookie($sid) {
  setcookie('xa_sid', $sid, $_SERVER['REQUEST_TIME'] + XA_COOKIE_TTL, '/', '.' . XA_DOMAIN, true);
}

function xa_save_session($sid, $ip, $data) {
  $sql = "INSERT INTO april_emotes (session_id, ip, data) VALUES('%s', '%s', '%s')";
  
  $data = json_encode($data);
  
  if (!$data) {
    return false;
  }
  
  return mysql_global_call($sql, $sid, $ip, $data);
}

function xa_rebuild_owned_emotes($data) {
  global $emotes;
  
  $owned_meta = [];
  
  if (!isset($data['owned'])) {
    return $owned_meta;
  }
  
  foreach ($data['owned'] as $key) {
    $_e = $emotes[$key];
    
    if ($_e) {
      $owned_meta[] = [$key, $_e[0], $_e[1]];
    }
  }
  
  return $owned_meta;
}

function xa_recover_session_by_sid($sid) {
  $sql = "SELECT data FROM april_emotes WHERE session_id = '%s' LIMIT 1";
  
  $res = mysql_global_call($sql, $sid);
  
  if (!$res) {
    xa_error(ERR_GENERIC . ' (gsbs5)');
  }
  
  $data = mysql_fetch_assoc($res)['data'];
  
  if (!$data) {
    return null;
  }
  
  $data = json_decode($data, true);
  
  if (!$data) {
    xa_error(ERR_GENERIC . ' (gsbs4)');
  }
  
  $data['owned'] = xa_rebuild_owned_emotes($data);
  
  return $data;
}

/**
 * Rolling
 */
function xa_roll($size) {
  global $emotes, $emote_pools;
  
  if (!isset($_COOKIE['xa_sid']) || !$_COOKIE['xa_sid']) {
    xa_error(ERR_BAD_REQ);
  }
  
  $sid = $_COOKIE['xa_sid'];
  
  $rolled_eids = [];
  
  for ($i = 0; $i < $size; $i++) { 
    $_r = mt_rand(0, 99);
    
    if ($_r >= 90) {
      // 28 emotes in pool 3
      $rolled_eids[] = $emote_pools[2][mt_rand(0, count($emote_pools[2]) - 1)];
    }
    else if ($_r >= 50) {
      // 81 emotes in pool 2
      $rolled_eids[] = $emote_pools[1][mt_rand(0, count($emote_pools[1]) - 1)];
    }
    else {
      // 58 emojis in pool 1
      $rolled_eids[] = $emote_pools[0][mt_rand(0, count($emote_pools[0]) - 1)];
    }
  }
  
  mysql_global_call('START TRANSACTION');
  
  $sql = "SELECT data FROM april_emotes WHERE session_id = '%s' FOR UPDATE";
  
  $res = mysql_global_call($sql, $sid);
  
  if (!$res) {
    mysql_global_call('COMMIT');
    xa_error(ERR_GENERIC . ' (lbr8)');
  }
  
  $data = mysql_fetch_assoc($res)['data'];
  
  if (!$data) {
    mysql_global_call('COMMIT');
    xa_error(ERR_BAD_REQ);
  }
  
  $data = json_decode($data, true);

  if (!$data) {
    mysql_global_call('COMMIT');
    xa_error(ERR_GENERIC . ' (lbr9)');
  }
  
  // Check if enough points
  $full_balance = xa_get_full_balance($data);
  $total_cost = $size * XA_ROLL_PRICE;
  
  if ($full_balance < $total_cost) {
    mysql_global_call('COMMIT');
    xa_error(ERR_NO_PTS, [ 'pts' => $data['balance'] ]);
  }
  
  // Roll results to return to the client for visual purposes
  $obtained = [];
  
  $recycled_points = 0;
  
  if (!isset($data['owned'])) {
    $data['owned'] = [];
  }
  
  foreach ($rolled_eids as $eid) {
    $_e_meta = xa_get_emote_by_id($eid);
    
    list($key, $kind, $arg) = $_e_meta;
    
    // Emote already owned, recycle it
    if (in_array($key, $data['owned'])) {
      if ($kind === 3) {
        $recycled_points += 10;
      }
      else {
        $recycled_points += 5;
      }
    }
    // New emote, add it to the owned list
    else {
      $data['owned'][] = $key;
    }
    
    $obtained[] = $_e_meta;
  }
  
  $data['balance'] += $recycled_points;
  $data['balance'] -= $total_cost;
  
  $balance = $data['balance'];
  
  $data = json_encode($data);
  
  $sql = "UPDATE april_emotes SET data = '%s' WHERE session_id = '%s' LIMIT 1";
  
  $res = mysql_global_call($sql, $data, $sid);
  
  if (!$res) {
    mysql_global_call('COMMIT');
    xa_error(ERR_GENERIC . ' (lbr6)');
  }
  
  mysql_global_call('COMMIT');
  
  xa_success(['obtained' => $obtained, 'balance' => $balance]);
}

function xa_get_full_balance($data) {
  $now = $_SERVER['REQUEST_TIME'];
  $start_ts = (int)$data['start_ts'];
  $tick_balance = floor(($now - $start_ts) / XA_TICK_INTERVAL) * XA_TICK_POINTS;
  return $tick_balance + $data['balance'];
}

function xa_get_emote_by_id($id) {
  global $emotes;
  
  foreach ($emotes as $key => $meta) {
    $_kind = $meta[0];
    $_arg = $meta[1];
    
    // Emoji (type 1)
    if ($_kind === 1) {
      if ($key === $id) {
        return [$key, $_kind, $_arg];
      }
    }
    // Image emotes
    else {
      if ($_arg === $id) {
        return [$key, $_kind, $_arg];
      }
    }
  }
  
  return null;
}

/**
 * Buying
 */
function xa_buy() {
  global $emotes, $emote_pools;
  
  if (!isset($_COOKIE['xa_sid']) || !$_COOKIE['xa_sid']) {
    xa_error(ERR_BAD_REQ);
  }
  
  if (!isset($_POST['eid']) || !$_POST['eid']) {
    xa_error(ERR_BAD_REQ);
  }
  
  $obtained = xa_get_emote_by_id($_POST['eid']);
  
  if (!$obtained) {
    xa_error(ERR_BAD_REQ);
  }
  
  list($key, $kind, $arg) = $obtained;
  
  // Pool 1
  if ($kind === 1) {
    $total_cost = 50;
  }
  // Pool 2
  else if ($kind === 2) {
    $total_cost = 150;
  }
  // Pool 3
  else {
    $total_cost = 300;
  }
  
  $sid = $_COOKIE['xa_sid'];
  
  mysql_global_call('START TRANSACTION');
  
  $sql = "SELECT data FROM april_emotes WHERE session_id = '%s' FOR UPDATE";
  
  $res = mysql_global_call($sql, $sid);
  
  if (!$res) {
    mysql_global_call('COMMIT');
    xa_error(ERR_GENERIC . ' (be0)');
  }
  
  $data = mysql_fetch_assoc($res)['data'];
  
  if (!$data) {
    mysql_global_call('COMMIT');
    xa_error(ERR_BAD_REQ);
  }
  
  $data = json_decode($data, true);

  if (!$data) {
    mysql_global_call('COMMIT');
    xa_error(ERR_GENERIC . ' (be1)');
  }
  
  // Check if already owned
  if (in_array($key, $data['owned'])) {
    mysql_global_call('COMMIT');
    xa_error(ERR_ALREADY_OWNED);
  }
  
  // Check if enough points
  $full_balance = xa_get_full_balance($data);
  
  if ($full_balance < $total_cost) {
    mysql_global_call('COMMIT');
    xa_error(ERR_NO_PTS, [ 'pts' => $data['balance'] ]);
  }
  
  $data['owned'][] = $key;
  $data['balance'] -= $total_cost;
  
  $balance = $data['balance'];
  
  $data = json_encode($data);
  
  $sql = "UPDATE april_emotes SET data = '%s' WHERE session_id = '%s' LIMIT 1";
  
  $res = mysql_global_call($sql, $data, $sid);
  
  if (!$res) {
    mysql_global_call('COMMIT');
    xa_error(ERR_GENERIC . ' (lbr6)');
  }
  
  mysql_global_call('COMMIT');
  
  xa_success(['obtained' => $obtained, 'balance' => $balance]);
}

// --------------

if (!isset($_POST['action'])) {
  xa_error(ERR_BAD_REQ);
}

if (isset($_SERVER['HTTP_REFERER']) && $_SERVER['HTTP_REFERER'] != ''
  && !preg_match('/^https?:\/\/([_a-z0-9]+)\.(4chan|4channel)\.org(\/|$)/', $_SERVER['HTTP_REFERER'])) {
  xa_error(ERR_BAD_REQ . '(xr1)');
}

// -------------

$_action = $_POST['action'];

if ($_action === 'start') {
  xa_start_session();
}
else if ($_action === 'roll3') {
  xa_roll(3);
}
else if ($_action === 'roll10') {
  xa_roll(10);
}
else if ($_action === 'buy') {
  xa_buy();
}
else {
  xa_error(ERR_BAD_REQ);
}
