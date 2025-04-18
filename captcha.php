<?php

require_once 'lib/userpwd.php';
require_once 'lib/twister_captcha.php';

define('TWISTER_FONT_PATH', '/usr/local/share/captcha/PCss_8x16_LE.gdf');

define('TWISTER_USE_TICKET_CAPTCHA', true);
define('TWISTER_HCAPTCHA_SITEKEY', '49d294fa-f15c-41fc-80ba-c2544c52ec2a');

// Validity period for captcha in seconds
define('TWISTER_TTL', 120);
// Delay in seconds before requesting a new captcha
define('TWISTER_COOLDOWN', 5);
// Long cooldown for unsolved captchas
define('TWISTER_COOLDOWN_LONG', 8);
// Duration of unsolved sessions
define('TWISTER_TTL_UNSOLVED', 600);
// Pre-cooldowns
define('TWISTER_HMAC_SECRET', 'y474c22ugpVtJD5gveDxcmMcL7DQFP+/w1FhVP9CYVM=');
define('TWISTER_TICKET_TTL', 3600); // 60 minutes
define('TWISTER_PRE_CD_THREAD', 300); // 5 minutes
define('TWISTER_PRE_CD_REPLY', 60);
define('TWISTER_PRE_CD_REPORT', 60);

define('TWISTER_PRE_CD_PENALITY', 60);

// Default number of characters for captchas
define('TWISTER_CHARS', 5);
define('TWISTER_CHARS_MIN', 4);
define('TWISTER_CHARS_MAX', 6);

// Userpwd reputation checks:
// Number of posts to get static captchas
define('TWISTER_PWD_STATIC_POSTS', 5);
// Known time in minutes to get static captchas
define('TWISTER_PWD_STATIC_KNOWN', 4320); // 3 days
// Idle time above which slider captchas will be served
define('TWISTER_PWD_SLIDER_IDLE', 86400); // 24 hours

// Whether or not to check for bypassing credits
define('TWISTER_ALLOW_NOOP', true);
// Number of posts to be eligible for captcha bypassing
define('TWISTER_PWD_NOOP_POSTS', 5);
// Known time in minutes to be eligible for captcha bypassing
define('TWISTER_PWD_NOOP_KNOWN', 4320); // 3 days

// List of boards where bypassing credits are disabled.
// Corresponds to the CAPTCHA_ALLOW_BYPASS board setting.
const TWISTER_NO_NOOP_BOARDS = [
  'bant', 'pol', 'trash'
];

// Covers 272814 (84.81 %) unique IPs and 10863 (79.15 %) unique bans
// IPs %: 0.05 | Bans %: 0 | GR1 all: 0 | Any EU: false
const TWISTER_WHITELIST_COUNTRIES = [
  'US', 'CA', 'GB', 'DE', 'AU', 'PL', 'IT', 'FR', 'FI', 'SE', 'ID', 'ES', 'NL',
  'PH', 'JP', 'MY', 'NO', 'IE', 'VN', 'NZ', 'HR', 'HU', 'AT', 'SG', 'PT', 'BE',
  'GR', 'DK', 'RS', 'CZ', 'TH', 'BG', 'CH', 'EE', 'LT', 'SK', 'SI', 'LV', 'TW',
  'IS',
];

// Covers 73407 (29.95 %) unique IPs and 2501 (16.7 %) unique bans
// IPs %: 2 | Bans %: 0 | GR1 all: 0 | Any EU: false
const TWISTER_WHITELIST_ASNS = [
  21928, 6167,
];

// Mobile ISPs
const TWISTER_MOBILE_ASNS = [
  21928,6167,20057,26599,35228,206067,4775,22085,8359,10139,30689,52876,27895,
  31615,11315,28403,6614,25135,10030,45143,4230,20365,6306,12929,21450,264731,
  203995,29465,38466,4818,15480,13280,13335,132618,25106,4657,10631,29247,12716,
  262210,138384,8953,9146,31213,2497,5578,21575,28036,28469,132061,29975
];

define('TWISTER_BAD_UA', '/headless|node-fetch|python-|java\/|jakarta|-perl|http-?client|-resty-|awesomium\//i');

// Memcached server
define('TWISTER_MEMCACHED_HOST', '127.0.0.1');
define('TWISTER_MEMCACHED_PORT', 11211);

// ---

define('TWISTER_DOMAIN', ($_SERVER['HTTP_HOST'] === 'sys.4chan.org') ? '4chan.org' : '4channel.org');

define('TWISTER_ERR_GENERIC', 'Internal Server Error');
define('TWISTER_ERR_COOLDOWN', 'You have to wait a while before doing this again');
define('TWISTER_ERR_PCD_THREAD', 'Please wait a while before making a thread');
define('TWISTER_ERR_PCD_REPLY', 'Please wait a while before making a post');
define('TWISTER_ERR_PCD_SIGNIN', 'Please wait for the timer<br>or verify your email address before making a post.<br><br><a href="https://sys.4chan.org/signin">Click here</a> for more information<br>or to verify your email.');

// ---

function twister_captcha_output_data($data) {
  if (isset($_GET['framed'])) {
    twister_captcha_output_html($data);
  }
  else {
    header('Content-Type: application/json');
    echo json_encode($data);
  }
}

function twister_captcha_output_html($data) {
  header('Content-Security-Policy: frame-ancestors https://*.' . TWISTER_DOMAIN . ';');
  $now = $_SERVER['REQUEST_TIME'];
?><!DOCTYPE html><html><head><meta charset="utf-8"><title></title>
<script>window.parent.postMessage(<?php echo json_encode(['twister' => $data]) ?>, '*');</script>
<script>document.cookie = `_tcs=${0|(Date.now()/1000)}.${new window.Intl.DateTimeFormat().resolvedOptions().timeZone}.<?php echo $now ?>.${window.eval.toString().length}; path=/; domain=sys.<?php echo TWISTER_DOMAIN ?>`;</script>
</head><body></body></html><?php
}

function twister_captcha_output_dummy() {
?><!DOCTYPE html><html><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0"><title></title></head><body>
<h3 style="text-align:center">You can now close this page and try getting a captcha again.<h3>
</body></html><?php
}

function twister_captcha_output_ticket_captcha() {
  twister_captcha_output_data([ 'mpcd' => true, 'ticket' => false ]);
}

function twister_captcha_get_ticket_captcha_response() {
  if (isset($_GET['ticket_resp'])) {
    return $_GET['ticket_resp'];
  }
  
  return false;
}

function twister_captcha_get_hcaptcha_private_key() {
  $path = '/www/global/yotsuba/config/captcha_config.ini';
  
  $cfg = file_get_contents($path);
  
  if (!$cfg) {
    return false;
  }
  
  $res = preg_match('/^HCAPTCHA_API_KEY_PRIVATE ?= ?([^\s]+)$/m', $cfg, $m);
  
  if (!$res || empty($m) || !$m[1]) {
    return false;
  }
  
  return $m[1];
}

function twister_captcha_verify_ticket_captcha() {
  $response = twister_captcha_get_ticket_captcha_response();
  
  if (!$response || strlen($response) > 4096) {
    return false;
  }
  
  $captcha_private_key = twister_captcha_get_hcaptcha_private_key();
  
  if (!$captcha_private_key) {
    // Don't block in case of misconfiguration on our end
    return true;
  }
  
  $url = 'https://hcaptcha.com/siteverify';
  
  $post = array(
    'secret' => $captcha_private_key,
    'response' => $response,
    'remoteip' => $_SERVER['REMOTE_ADDR'],
    'sitekey' => TWISTER_HCAPTCHA_SITEKEY,
  );
  
  $curl = curl_init();
  
  curl_setopt($curl, CURLOPT_URL, $url);
  curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
  curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 2);
  curl_setopt($curl, CURLOPT_TIMEOUT, 4);
  curl_setopt($curl, CURLOPT_USERAGENT, '4chan');
  curl_setopt($curl, CURLOPT_POSTFIELDS, $post);
  
  $resp = curl_exec($curl);
  
  if ($resp === false) {
    curl_close($curl);
    return false;
  }
  
  $resp_status = curl_getinfo($curl, CURLINFO_HTTP_CODE);
  
  if ($resp_status >= 300) {
    curl_close($curl);
    return false;
  }
  
  curl_close($curl);
  
  $json = json_decode($resp, true);
  
  // BAD
  if (json_last_error() !== JSON_ERROR_NONE) {
    return false;
  }
  
  // GOOD
  if ($json && isset($json['success']) && $json['success']) {
    return true;
  }
  
  // BAD
  return false;
}

function twister_captcha_process_ticket_captcha($pcd, $now, $long_ip, $board, $msg = null, $bypassable = false) {
  // Captcha response was sent, verify it
  if (twister_captcha_get_ticket_captcha_response()) {
    if (twister_captcha_verify_ticket_captcha() === true) {
      // Captcha is valid, return a new ticket
      twister_captcha_output_new_ticket($pcd, $now, $long_ip, $board, $msg, $bypassable);
    }
    else {
      // Wrong captcha or captcha malfunction
      twister_captcha_error(TWISTER_ERR_GENERIC . ' (tcr0)');
    }
  }
  // No captcha reponse provided, tell the frontend to show a ticket captcha
  else {
    twister_captcha_output_ticket_captcha();
  }
}

function twister_captcha_output_new_ticket($pcd, $now, $long_ip, $board, $msg = null, $bypassable = false) {
  $ticket = twister_captcha_generate_ticket($now, $long_ip, $board);
  
  if (!$ticket) {
    twister_captcha_error(TWISTER_ERR_GENERIC . ' (gt1)');
  }
  
  twister_captcha_output_ticket_pcd($ticket, $pcd, $msg, $bypassable);
}

function twister_captcha_output_ticket_pcd($ticket, $pcd, $msg, $bypassable) {
  $data = [];
  
  if ($ticket) {
    $data['ticket'] = $ticket;
  }
  
  $data['pcd'] = $pcd;
  
  if ($msg) {
    $data['pcd_msg'] = $msg;
  }
  
  if ($bypassable) {
    $data['bpcd'] = true;
  }
  
  twister_captcha_output_data($data);
}

function twister_captcha_error($msg, $extra = null) {
  //http_response_code(500);
  $data = [ 'error' => $msg ];
  
  if ($extra) {
    $data = array_merge($data, $extra);
  }
  
  twister_captcha_output_data($data);
  
  die();
}

function twister_captcha_is_req_suspicious() {
  /*
  if (isset($_SERVER['HTTP_X_HTTP_VERSION'])) {
    if (strpos($_SERVER['HTTP_X_HTTP_VERSION'], 'HTTP/1') === 0) {
      return true;
    }
  }
  */
  $no_lang = isset($_SERVER['HTTP_ACCEPT_LANGUAGE']) === false;
  $no_accept = isset($_SERVER['HTTP_ACCEPT']) === false;
  
  if ($no_lang && $no_accept) {
    return true;
  }
  
  if ($no_lang && strpos($_SERVER['HTTP_USER_AGENT'], '; wv)') !== false) {
    return true;
  }
  
  if ($no_accept && strpos($_SERVER['HTTP_USER_AGENT'], 'iPhone') !== false && strpos($_SERVER['HTTP_USER_AGENT'], 'Mobile') === false) {
    return true;
  }
  
  if ($no_lang && isset($_SERVER['HTTP_REFERER'])) {
    $ref = $_SERVER['HTTP_REFERER'];
    
    if (strpos($ref, 'sys.4chan.org') !== false || strpos($ref, '/thread/') !== false) {
      return true;
    }
  }
  
  return false;
}

function twister_captcha_need_hcaptcha() {
  if (!isset($_SERVER['HTTP_X_BOT_SCORE'])) {
    return true;
  }
  
  $ua = $_SERVER['HTTP_USER_AGENT'];
  
  $score = (int)$_SERVER['HTTP_X_BOT_SCORE'];
  
  // Skip Android Webviews
  if ($score == 1 && strpos($ua, '; wv)') !== false) {
    return false;
  }
  
  return $score < 99;
}

function twister_captcha_check_likely_automated($memcached, $now, $threshold = 29) {
  if (!isset($_SERVER['HTTP_X_BOT_SCORE'])) {
    return false;
  }
  
  $ua = $_SERVER['HTTP_USER_AGENT'];
  
  // Skip Android Webviews
  if (strpos($ua, '; wv)') !== false) {
    return false;
  }
  
  // Skip iPhone Webviews
  if (preg_match('/iPhone|iPad/', $ua) && !preg_match('/Mobile|Safari/', $ua)) {
    return false;
  }
  
  $score = (int)$_SERVER['HTTP_X_BOT_SCORE'];
  
  if ($score > 1 && $score <= $threshold) {
    $key = 'bmbot' . $_SERVER['REMOTE_ADDR'];
    $memcached->set($key, 1, $now + 43200);
    return true;
  }
  
  return false;
}

function twister_captcha_get_pcd_penalty($board, $thread_id) {
  $count = 0;
  
  return 0; // FIXME
  
  // Reports
  if ($thread_id === 1 && $board !== '!') {
    return 0;
  }
  
  if (isset($_SERVER['HTTP_X_GEO_ASN'])) {
    $asn = (int)$_SERVER['HTTP_X_GEO_ASN'];
  }
  else {
    $asn = 0;
  }
  
  if (isset($_SERVER['HTTP_X_GEO_COUNTRY'])) {
    $country = $_SERVER['HTTP_X_GEO_COUNTRY'];
  }
  else {
    $country = null;
  }
  
  // Mobile clients
  if ($asn > 0 && in_array($asn, TWISTER_MOBILE_ASNS)) {
    $count++;
  }
  else if (isset($_SERVER['HTTP_USER_AGENT']) && preg_match('/Mobile|iPhone|; wv/', $_SERVER['HTTP_USER_AGENT'])) {
    $count++;
  }
  
  // Rare countries
  if ($country && !in_array($country, TWISTER_WHITELIST_COUNTRIES)) {
    $count++;
  }
  
  // Rare ISPs
  if ($asn > 0 && !in_array($asn, TWISTER_WHITELIST_ASNS)) {
    $count++;
  }
  
  return $count * TWISTER_PRE_CD_PENALITY;
}

function twister_captcha_store_challenge($memcached, $challenge_uid, $long_ip, $expiration) {
  if (!$challenge_uid || !$long_ip || !$expiration) {
    return false;
  }
  
  return $memcached->set("ch$long_ip", $challenge_uid, $expiration);
}

// Cooldowns
function twister_captcha_store_cooldown($memcached, $long_ip, $expiration) {
  if (!$long_ip || !$expiration) {
    return false;
  }
  
  return $memcached->set("cd$long_ip", $expiration, $expiration);
}

function twister_captcha_get_cooldown($memcached, $long_ip) {
  if (!$long_ip) {
    return false;
  }
  
  $val = $memcached->get("cd$long_ip");
  
  if ($val === false && $memcached->getResultCode() === Memcached::RES_NOTFOUND) {
    return 0;
  }
  
  return $val;
}

function twister_captcha_generate_ticket($now, $long_ip, $board) {
  if (!$long_ip || !$now) {
    return false;
  }
  
  $hmac_secret = base64_decode(TWISTER_HMAC_SECRET);
  
  if (!$hmac_secret) {
    return false;
  }
  
  $hash = hash_hmac('sha256', "$now.$long_ip.$board", $hmac_secret);
  
  if (!$hash) {
    return false;
  }
  
  return "$now.$hash";
}

function twister_captcha_decode_ticket($ticket, $long_ip, $board) {
  if (!$long_ip || !$ticket) {
    return false;
  }
  
  $hmac_secret = base64_decode(TWISTER_HMAC_SECRET);
  
  if (!$hmac_secret) {
    return false;
  }
  
  list($ts, $hash) = explode('.', $ticket);
  
  $ts = (int)$ts;
  
  if (!$ts || !$hash) {
    return false;
  }
  
  $this_hash = hash_hmac('sha256', "$ts.$long_ip.$board", $hmac_secret);
  
  if ($this_hash === $hash) {
    return $ts;
  }
  
  return false;
}

function twister_captcha_should_purge_ticket($ticket, $now) {
  if (!$ticket || !$now) {
    return false;
  }
  
  list($ts, $hash) = explode('.', $ticket);
  
  if (!$ts) {
    return false;
  }
  
  return $ts + TWISTER_TICKET_TTL <= $now;
}

function twister_captcha_store_session($memcached, $long_ip, $count, $expiration) {
  if (!$long_ip || !$expiration || $count < 1) {
    return false;
  }
  
  $key = "us$long_ip";
  
  $res = $memcached->replace($key, $count, $expiration);
  
  if ($res === false) {
    if ($memcached->getResultCode() === Memcached::RES_NOTSTORED) {
      return $memcached->set($key, $count, $expiration);
    }
    else {
      return false;
    }
  }
  
  return true;
}

function twister_captcha_get_session($memcached, $long_ip) {
  if (!$long_ip) {
    return false;
  }
  
  $val = $memcached->get("us$long_ip");
  
  if ($val === false) {
    if ($memcached->getResultCode() === Memcached::RES_NOTFOUND) {
      return 0;
    }
    else {
      return false;
    }
  }
  
  return $val;
}

function twister_captcha_get_credits($memcached, $pwd) {
  if (!$pwd) {
    return false;
  }
  
  $key = "cr-$pwd";
  $val = $memcached->get($key);
  
  if ($val === false) {
    return false;
  }
  
  $val = explode('.', $val);
  
  $count = (int)$val[0];
  $ts = (int)$val[1];
  
  if ($count <= 0 || $ts <= 0) {
    $memcached->delete($key);
    return false;
  }
  
  return [ $count, $ts ];
}

function twister_captcha_get_userpwd($user_ip) {
  if (isset($_COOKIE['4chan_pass'])) {
    $_c = $_COOKIE['4chan_pass'];
  }
  else {
    $_c = null;
  }
  
  return new UserPwd($user_ip, TWISTER_DOMAIN, $_COOKIE['4chan_pass']);
}

// ---

// Dummy page for cloudflare challenges
if (isset($_GET['opened'])) {
  twister_captcha_output_dummy();
  die();
}

// ---

// Block TOR immediately
if ($_SERVER['HTTP_X_GEO_CONTINENT'] === 'T1') {
  twister_captcha_error(TWISTER_ERR_GENERIC . ' (T1)');
}

// Check parameters
if (isset($_GET['board']) && preg_match('/^[!0-9a-z]{1,10}$/', $_GET['board'])) {
  $param_board = $_GET['board'];
}
else {
  $param_board = '!';
}

if (isset($_GET['thread_id']) && $_GET['thread_id']) {
  $param_thread_id = (int)$_GET['thread_id'];
}
else {
  $param_thread_id = 0;
}

$now = $_SERVER['REQUEST_TIME'];
$user_ip = $_SERVER['REMOTE_ADDR'];
$user_long_ip = ip2long($user_ip);

if (!isset($_SERVER['HTTP_USER_AGENT'])) {
  $user_agent = '!';
}
else {
  $user_agent = md5($_SERVER['HTTP_USER_AGENT']);
}

$userpwd = twister_captcha_get_userpwd($user_ip);

if (!$userpwd) {
  twister_captcha_error(TWISTER_ERR_GENERIC . ' (GU1)');
}

if (!$userpwd->isNew() && $param_board !== '!signin') {
  $password = $userpwd->getPwd();
}
else {
  $password = '!';
}

// ---

$use_static = false;
$difficulty = TwisterCaptcha::LEVEL_NORMAL;
$char_count = TWISTER_CHARS_MAX;

// ---

$m = new Memcached();

// Only call the following once (when getServerList() is empty) if using persistent connections
//$m->setOption(Memcached::OPT_TCP_NODELAY, true);
$m->setOption(Memcached::OPT_SERVER_FAILURE_LIMIT, 1);
$m->setOption(Memcached::OPT_SEND_TIMEOUT, 500000); // 500ms
$m->setOption(Memcached::OPT_RECV_TIMEOUT, 500000); // 500ms

// Only use one server. Having multiple servers will break the captcha
// as "set" is used instead of "replace + add"
if ($m->addServer(TWISTER_MEMCACHED_HOST, TWISTER_MEMCACHED_PORT) === false) {
  twister_captcha_error(TWISTER_ERR_GENERIC . ' (c0)');
}

// ---

// If CF's bot score is low, store the information for 1h and use it during posting
twister_captcha_check_likely_automated($m, $now);

/**
 * Pre-cooldowns
 */
$pcd = 0;

// Posting a thread
if ($param_thread_id === 0) {
  if ($userpwd->threadCount() < 1 || ($userpwd->ipChanged() && $userpwd->postCount() < 2)) {
    $pcd = TWISTER_PRE_CD_THREAD;
  }
}
// Posting a reply (reporting uses a thread id of 1)
else if ($param_thread_id !== 1) {
  if ($userpwd->postCount() < 1 || ($userpwd->ipChanged() && $userpwd->postCount() < 2)) {
    $pcd = TWISTER_PRE_CD_REPLY;
  }
}
// Reporting a post
else if ($param_board !== '!') {
  if ($userpwd->reportCount() < 1 && $userpwd->postCount() < 1) {
    $pcd = TWISTER_PRE_CD_REPORT;
  }
}

if ($param_thread_id != 1 && !$userpwd->verifiedLevel()) {
  // Initial cooldown, bypassable by verifying your email
  if ($userpwd->postCount() < 1 || !$userpwd->isUserKnown(15)) {
    $pcd = 900;
  }
  // Cooldown for when the user is still new and the mask changes
  else if ($userpwd->postCount() > 0 && $userpwd->pwdLifetime() < 86400 && $userpwd->maskChanged()) { // 24h
    $pcd = 180;
  }
}

// Pre-cooldown needed
if ($pcd > 0 && $param_board !== '!signin') {
  // Extra pre-cooldown for unverified users
  if (!$userpwd->verifiedLevel()) {
    $pcd += twister_captcha_get_pcd_penalty($param_board, $param_thread_id);
  }
  
  $ticket_ts = twister_captcha_decode_ticket($_GET['ticket'], $user_long_ip, $param_board);
  
  $bypassable = false;
  
  if ($param_thread_id === 0) {
    $pcd_msg = TWISTER_ERR_PCD_THREAD;
  }
  else if ($param_thread_id !== 1 && $param_board !== '!') {
    $pcd_msg = TWISTER_ERR_PCD_REPLY;
  }
  
  if ($param_thread_id !== 1 && $pcd >= 900) {
    $pcd_msg = TWISTER_ERR_PCD_SIGNIN;
    $bypassable = true;
  }
  
  if (!$ticket_ts) {
    //if (TWISTER_USE_TICKET_CAPTCHA && !in_array((int)$_SERVER['HTTP_X_GEO_ASN'], TWISTER_WHITELIST_ASNS)) {
    if (TWISTER_USE_TICKET_CAPTCHA && twister_captcha_need_hcaptcha()) {
      twister_captcha_process_ticket_captcha($pcd, $now, $user_long_ip, $param_board, $pcd_msg, $bypassable);
    }
    else {
      twister_captcha_output_new_ticket($pcd, $now, $user_long_ip, $param_board, $pcd_msg, $bypassable);
    }
    
    die();
  }
  
  $ticket_lifetime = $now - $ticket_ts;
  
  if ($ticket_lifetime < $pcd) {
    $pcd = $pcd - $ticket_lifetime;
    twister_captcha_output_ticket_pcd(null, $pcd, $pcd_msg, $bypassable);
    die();
  }
  
  // Ticket expired
  if ($ticket_lifetime >= TWISTER_TICKET_TTL) {
    if (TWISTER_USE_TICKET_CAPTCHA && twister_captcha_need_hcaptcha()) {
      twister_captcha_process_ticket_captcha($pcd, $now, $user_long_ip, $param_board, $pcd_msg, $bypassable);
    }
    else {
      twister_captcha_output_new_ticket($pcd, $now, $user_long_ip, $param_board, $pcd_msg, $bypassable);
    }
    
    die();
  }
}

/**
 * Adjust difficulty
 */

$ip_ttl_static = TWISTER_PWD_TTL_IP_STATIC;
$mask_ttl_static = TWISTER_PWD_TTL_MASK_STATIC;

$ip_ttl_min = TWISTER_PWD_TTL_IP_MIN;
$idle_ttl = TWISTER_PWD_TTL_IDLE;

// Serve max len twister to bad actors
$bad_actor = false;

if (!isset($_SERVER['HTTP_USER_AGENT']) || !$_SERVER['HTTP_USER_AGENT']) {
  $bad_actor = true;
}
else if (preg_match(TWISTER_BAD_UA, $_SERVER['HTTP_USER_AGENT'])) {
  $bad_actor = true;
}
else if (twister_captcha_is_req_suspicious()) {
  $bad_actor = true;
}

// Serve static captcha to known users.
// Serve max length captchas for unknown users.
// Only applies to replies. Theads always use slider captchas.
if ($param_thread_id !== 0 && !$bad_actor) {
  // Known and post count check
  if ($userpwd->isUserKnown(TWISTER_PWD_STATIC_KNOWN) && $userpwd->postCount() >= TWISTER_PWD_STATIC_POSTS) {
    // Inactivity and IP change checks for unverified users
    if ($userpwd->verifiedLevel()) {
      $use_static = true;
      $char_count = TWISTER_CHARS_MIN;
    }
    else if ($userpwd->idleLifetime() <= TWISTER_PWD_SLIDER_IDLE && !$userpwd->ipChanged()) {
      $use_static = true;
      $char_count = TWISTER_CHARS;
    }
  }
}

// Check captcha bypassing credits
if (TWISTER_ALLOW_NOOP && !in_array($param_board, TWISTER_NO_NOOP_BOARDS) && !$bad_actor && $param_thread_id !== 0) {
  if ($userpwd->isUserKnown(TWISTER_PWD_NOOP_KNOWN) && $userpwd->postCount() >= TWISTER_PWD_NOOP_POSTS) {
    $credits = twister_captcha_get_credits($m, $userpwd->getPwd());
    
    if ($credits !== false && $credits[0] > 0) {
      $data = [
        'challenge' => 'noop',
        'ttl' => min(TWISTER_TTL, $credits[1] - $now),
        'cd' => TWISTER_COOLDOWN
      ];
      
      twister_captcha_output_data($data);
      
      die();
    }
  }
}

// Check cooldown
$should_cd_until = twister_captcha_get_cooldown($m, $user_long_ip);

if ($should_cd_until === false) {
  twister_captcha_error(TWISTER_ERR_GENERIC . ' (scdu)');
}

if ($should_cd_until > 1) {
  twister_captcha_error(TWISTER_ERR_COOLDOWN, ['cd' => $should_cd_until - $now]);
}

// Number of unsolved captchas requested recently
$unsolved_count = twister_captcha_get_session($m, $user_long_ip);

if ($unsolved_count === false) {
  twister_captcha_error(TWISTER_ERR_GENERIC . ' (gus1)');
}

if ($unsolved_count > 2) {
  $cooldown = TWISTER_COOLDOWN_LONG * min($unsolved_count, 20);
  
  if ($unsolved_count > 10) {
    $cooldown = 300;
  }
}
else {
  $cooldown = TWISTER_COOLDOWN;
}

if (twister_captcha_store_cooldown($m, $user_long_ip, $now + $cooldown) === false) {
  twister_captcha_error(TWISTER_ERR_GENERIC . ' (sc0)');
}

// Generate images
$c = new TwisterCaptcha(TWISTER_FONT_PATH);
$c->setDifficulty($difficulty);

// Adjust features
// User is suspicious
$should_harden = $bad_actor && ($userpwd->ipChanged() || !$userpwd->isUserKnownOrVerified(7200) || mt_rand(0, 9) === 9);

$should_fog = false;

if ($should_harden) {
  $use_static = false;
  
  if (mt_rand(0, 1)) {
    $c->useInkBlot(true);
    
    if (mt_rand(0, 1)) {
      $c->useNegateBlotFilter(true);
    }
  }
  else {
    $c->useEdgeBlock(true);
  }
  
  $c->useFakeCharPadding(true);
  $c->useJumpyMode(true);
  
  if (mt_rand(0, 9) === 9) {
    $c->setDifficulty(TwisterCaptcha::LEVEL_LUNATIC);
  }
  else {
    $c->setDifficulty(TwisterCaptcha::LEVEL_HARD);
  }
  
  if (mt_rand(0, 9) === 9) {
    $char_count = 8;
  }
  
  if (mt_rand(0, 1)) {
    $c->useGridLines(true);
  }
  else {
    $c->useScoreLines(true);
  }
}
// Other new users
else if ($userpwd->postCount() < 5 || $userpwd->maskLifetime() < 3600) {
  if (!$use_static) {
    $_boards = [ 'a', 'v', 'vg', 'co', 'vp', 'g', 'biz', 'b', 'vt', 'mu', 'pol', 'tv', 'sp', 'int', 'soc', 'test' ];
    
    if (true || in_array($param_board, $_boards) || $param_thread_id == 0) {
      $should_fog = true;
    }
    else {
      $c->useInkBlot(true);
      $c->useScoreLines(true);
    }
  }
  else {
    $c->useInkBlot(true);
    //$c->useFakeCharPadding(true);
    
    if (mt_rand(0, 1)) {
      $c->useJumpyMode(true);
    }
  }
  
  $char_count = mt_rand(TWISTER_CHARS, TWISTER_CHARS_MAX);
}

if ($param_board === '!signin') {
  $should_fog = true;
  $use_static = false;
}

if ($use_static) {
  if (mt_rand(0, 9) === 9) {
    $c->useScoreLines(true);
  }
  
  list($challenge_str, $img, $img_width, $img_height) = $c->generateStatic($char_count);
  $img_bg = null;
}
else {
  if ($should_fog) {
    if (false && $param_board == 'co' && $param_thread_id == 0) {
      $c->useEdgeDetect(true);
      $c->useSpecialRot(true);
      //$c->useOverlayId(5, true);
      $c->useInvert(true);
      //$c->useAltBlackWhite(true);
      $c->useGridLines(true);
      $c->useSimplexBg(true);
      //$c->useEmboss(true);
      //list($challenge_str, $img, $img_width, $img_height, $img_bg, $bg_width) = $c->generateTwisterHFogNew($char_count);
      list($challenge_str, $img, $img_width, $img_height, $img_bg, $bg_width) = $c->generateTwisterHFogNew(7, 28, 28);
      //list($challenge_str, $img, $img_width, $img_height, $img_bg, $bg_width) = $c->generateTwisterV(3);
    }
    else {
      $_olid = mt_rand(0, 6);

      if ($_olid) {
        $c->useOverlayId($_olid, (bool)mt_rand(0, 1));
      }
      
      if (mt_rand(0, 1)) {
        $c->useInvert(true);
      }
      
      if (mt_rand(0, 3) === 3) {
        $c->useFakeCharPadding(true);
      }
      
      if ($param_thread_id == 0) {
        $_olid = 2;
      }
      else {
        $_olid = mt_rand(1, 2);
      }
      
      $_olid = 2;
      
      // Simplex
      if ($_olid === 1) {
        if (mt_rand(0, 3) === 3) {
          $c->useScoreLines(true);
        }
        
        if (mt_rand(0, 1)) {
          $c->useInvert(true);
        }
        
        list($challenge_str, $img, $img_width, $img_height, $img_bg, $bg_width) = $c->generateTwisterHSimplex($char_count);
      }
      // Fog New
      else if ($_olid === 2) {
        if (mt_rand(0, 1)) {
          $c->useSimplexBg(true);
        }
        
        if (mt_rand(0, 1)) {
          $c->useEmboss(true);
        }
        else {
          $c->useEdgeDetect(true);
        }
        
        if (mt_rand(0, 1)) {
          $c->useAltBlackWhite(true);
        }
        
        if (mt_rand(0, 1)) {
          $c->useInvert(true);
        }
        
        //if (mt_rand(0, 3) === 3) {
          //$c->useSpecialRot(true);
          //$char_count = 5;
        //}
        
        if (isset($_SERVER['HTTP_X_BOT_SCORE'])) {
          $_bot_score = (int)$_SERVER['HTTP_X_BOT_SCORE'];
        }
        else {
          $_bot_score = 100;
        }
        
        if ($param_board === '!signin') {
          if ($_bot_score > 95) {
            list($challenge_str, $img, $img_width, $img_height, $img_bg, $bg_width) = $c->generateTwisterHFogNew(5, 30, 50);
          }
          else {
            list($challenge_str, $img, $img_width, $img_height, $img_bg, $bg_width) = $c->generateSimpleTask(2);
          }
        }
        else {
          //$c->useSpecialRot(true);
          
          if ($_bot_score <= 80 && mt_rand(0, 1) === 0) {
            list($challenge_str, $img, $img_width, $img_height, $img_bg, $bg_width) = $c->generateSimpleTask(2);
          }
          else {
            list($challenge_str, $img, $img_width, $img_height, $img_bg, $bg_width) = $c->generateTwisterHFogNew(5, 30, 50);
          }
        }
      }
      // Default
      else {
        list($challenge_str, $img, $img_width, $img_height, $img_bg, $bg_width) = $c->generateTwisterHFogNew($char_count);
      }
    }
  }
  else {
    list($challenge_str, $img, $img_width, $img_height, $img_bg, $bg_width) = $c->generateTwisterH($char_count);
  }
}

if (!$challenge_str) {
  twister_captcha_error(TWISTER_ERR_GENERIC . ' (ncs1)');
}

list($challenge_uid, $challenge_hash) = TwisterCaptcha::getChallengeHash(
  $challenge_str,
  [$user_ip, $password, $user_agent, $param_board, $param_thread_id]
);

if (!$challenge_uid || !$challenge_hash) {
  twister_captcha_error(TWISTER_ERR_GENERIC . ' (gch1)');
}

// Register challenge
if (twister_captcha_store_challenge($m, $challenge_uid, $user_long_ip, $now + TWISTER_TTL) === false) {
  twister_captcha_error(TWISTER_ERR_GENERIC . ' (sch1)');
}

// Register unsolved session
if (twister_captcha_store_session($m, $user_long_ip, $unsolved_count + 1, $now + TWISTER_TTL_UNSOLVED) === false) {
  twister_captcha_error(TWISTER_ERR_GENERIC . ' (sus1)');
}

// Generate base 64 urls of images
list($img_b64, $img_bg_b64) = TwisterCaptcha::getBase64Images($img, $img_bg);

$data = [
  'challenge' => "$challenge_uid.$challenge_hash",
  'ttl' => TWISTER_TTL,
  'cd' => $cooldown,
  'img' => $img_b64,
  'img_width' => $img_width,
  'img_height' => $img_height
];

if ($img_bg) {
  $data['bg'] = $img_bg_b64;
  $data['bg_width'] = $bg_width;
}

if (twister_captcha_should_purge_ticket($_GET['ticket'], $now)) {
  $data['ticket'] = false;
}

twister_captcha_output_data($data);
