<?

require_once "lib/rpc.php";
require_once 'lib/ini.php';
load_ini_file('captcha_config.ini');

$recaptcha_public_key  = RECAPTCHA_API_KEY_PUBLIC;
$recaptcha_private_key = RECAPTCHA_API_KEY_PRIVATE;

$hcaptcha_public_key  = HCAPTCHA_API_KEY_PUBLIC;
$hcaptcha_private_key = HCAPTCHA_API_KEY_PRIVATE;

// Parameter formats and other checks must much the formatting in /captcha
function is_twister_captcha_valid($memcached, $ip, $userpwd, $board = '!', $thread_id = 0, &$unsolved_count = null) {
  if (!$memcached) {
    return false;
  }
  
  if (defined('TEST_BOARD') && TEST_BOARD) {
    require_once 'lib/twister_captcha-test.php';
  }
  else {
    require_once 'lib/twister_captcha.php';
  }
  
  if (!isset($_POST['t-challenge']) || !$_POST['t-challenge']) {
    return false;
  }
  
  if (!isset($_POST['t-response']) || !$_POST['t-response']) {
    return false;
  }
  
  if (strlen($_POST['t-response']) > 24) {
    return false;
  }
  
  if (strlen($_POST['t-challenge']) > 255) {
    return false;
  }
  
  // User agent
  if (!isset($_SERVER['HTTP_USER_AGENT'])) {
    $user_agent = '!';
  }
  else {
    $user_agent = md5($_SERVER['HTTP_USER_AGENT']);
  }
  
  // Password
  $password = '!';
  
  if ($userpwd && !$userpwd->isNew()) {
    $password = $userpwd->getPwd();
  }
  
  // ---
  
  list($uniq_id, $challenge_hash) = explode('.', $_POST['t-challenge']);
  
  $long_ip = ip2long($ip);
  
  if (!$uniq_id || !$challenge_hash || !$long_ip) {
    return false;
  }
  
  $challenge_key = "ch$long_ip";
  
  $params = [$ip, $password, $user_agent, $board, $thread_id];
  
  $response = TwisterCaptcha::normalizeReponseStr($_POST['t-response']);
  
  $is_valid = TwisterCaptcha::verifyChallengeHash($challenge_hash, $uniq_id, $response, $params);
  
  if ($is_valid) {
    $active_uniq_id = $memcached->get($challenge_key);
    
    // Delete challenge
    $memcached->delete($challenge_key);
    
    if (!$active_uniq_id || $uniq_id !== $active_uniq_id) {
      return false;
    }
    
    // Return and decrement the unsolved session count
    $us = decrement_twister_captcha_session($memcached, $ip, $unsolved_count !== null);
    
    if ($unsolved_count !== null) {
      $unsolved_count = $us;
    }
    
    return true;
  }
  
  // Delete challenge
  $memcached->delete($challenge_key);
  
  return false;
}

// Decrements the unsolved count by 2 and returns the old count
function decrement_twister_captcha_session($memcached, $ip, $return_old = true) {
  if (!$memcached) {
    return false;
  }
  
  $long_ip = ip2long($ip);
  
  if (!$long_ip) {
    return false;
  }
  
  $key = "us$long_ip";
  
  if ($return_old) {
    $val = $memcached->get($key);
    
    if ($val === false) {
      $val = 0;
    }
  }
  else {
    $val = 0;
  }
  
  $memcached->decrement($key, 2);
  
  return $val;
}

// FIXME: The IP arg isn't used for now
function set_twister_captcha_credits($memcached, $ip, $userpwd, $current_time) {
  if (!$memcached || !$userpwd) {
    return false;
  }
  
  $current_time = (int)$current_time;
  
  if ($current_time <= 0) {
    return false;
  }
  
  //$long_ip = ip2long($ip);
  
  //if (!$long_ip) {
    //return false;
  //}
  
  $credits = 0;
  
  // Config
  // Stage 1 should match the check in use_twister_captcha_credit()
  // and captcha.php for optimisation purposes
  
  // Stage 1
  $noop_known_ttl_1 = 4320; // required user lifetime (3 days, in minutes)
  $noop_post_count_1 = 5; // required post count
  $noop_credits_1 = 1; // credits given
  $noop_duration_1 = 3600; // duration of the credits (1 hour, in seconds)
  
  // Stage 2
  $noop_known_ttl_2 = 21600; // 15 days
  $noop_post_count_2 = 20;
  $noop_credits_2 = 2;
  $noop_duration_2 = 7200; // 2 hours
  
  // Stage 3
  $noop_known_ttl_3 = 129600; // 90 days
  $noop_post_count_3 = 100;
  $noop_credits_3 = 3;
  $noop_duration_3 = 10800; // 3 hours
  
  // ---
  
  // The IP changed too recently
  if ($userpwd->ipLifetime() < 60) {
    return false;
  }
  
  // Stage 3
  if ($userpwd->isUserKnown($noop_known_ttl_3) && $userpwd->postCount() >= $noop_post_count_3) {
    $credits = $noop_credits_3;
    $duration = $noop_duration_3;
  }
  // Stage 2
  else if ($userpwd->isUserKnown($noop_known_ttl_2) && $userpwd->postCount() >= $noop_post_count_2) {
    $credits = $noop_credits_2;
    $duration = $noop_duration_2;
  }
  // Stage 1
  else if ($userpwd->isUserKnown($noop_known_ttl_1) && $userpwd->postCount() >= $noop_post_count_1) {
    $credits = $noop_credits_1;
    $duration = $noop_duration_1;
  }
  else {
    return false;
  }
  
  if (!$credits || $credits > 3) {
    return false;
  }
  
  $expiration_ts = $current_time + $duration;
  
  // Require no more than 5 actions in the past 8 minutes
  /*
  $query = <<<SQL
SELECT SQL_NO_CACHE COUNT(*) FROM user_actions
WHERE ip = $long_ip
AND time >= DATE_SUB(NOW(), INTERVAL 8 MINUTE)
SQL;
  
  $res = mysql_global_call($query);
  
  if (!$res) {
    return false;
  }
  
  $count = (int)mysql_fetch_row($res)[0];
  
  if ($count > 5) {
    return false;
  }
  */
  // Set credits
  
  $pwd = $userpwd->getPwd();
  
  if (!$pwd) {
    return false;
  }
  
  $key = "cr-$pwd";
  $val = "$credits.$expiration_ts";
  
  $res = $memcached->replace($key, $val, $expiration_ts);
  
  if ($res === false) {
    if ($memcached->getResultCode() === Memcached::RES_NOTSTORED) {
      return $memcached->set($key, $val, $expiration_ts);
    }
    else {
      return false;
    }
  }
  
  return true;
}

// FIXME: The IP arg isn't used for now
function use_twister_captcha_credit($memcached, $ip, $userpwd) {
  if (!$memcached || !$userpwd) {
    return false;
  }
  
  //$long_ip = ip2long($ip);
  
  //if (!$long_ip) {
    //return false;
  //}
  
  // Must match the check in set_twister_captcha_credits()
  $noop_known_ttl_1 = 4320; // required user lifetime (3 days, in minutes)
  $noop_post_count_1 = 5; // required post count
  
  if (!$userpwd->isUserKnown($noop_known_ttl_1) || $userpwd->postCount() < $noop_post_count_1) {
    return false;
  }
  
  $pwd = $userpwd->getPwd();
  
  if (!$pwd) {
    return false;
  }
  
  $key = "cr-$pwd";
  $credits = $memcached->get($key);
  
  if ($credits === false) {
    return false;
  }
  
  list($count, $ts) = explode('.', $credits);
  
  $count = (int)$count;
  $ts = (int)$ts;
  
  // No credits left
  if ($count <= 0 || $ts <= 0) {
    $memcached->delete($key);
    return false;
  }
  
  $count -= 1;
  
  $res = $memcached->replace($key, "$count.$ts", $ts);
  
  if ($res === false && $memcached->getResultCode() !== Memcached::RES_NOTSTORED) {
    return false;
  }
  
  return true;
}

function twister_captcha_form() {
  return '<div id="t-root"></div>';
}

function log_failed_captcha($ip, $userpwd, $board, $thread_id, $is_quiet, $meta = null) {
  $data = [
    'board' => $board,
    'thread_id' => $thread_id,
  ];
  
  if ($userpwd) {
    $data['arg_num'] = $userpwd->pwdLifetime();
    $data['pwd'] = $userpwd->getPwd();
  }
  
  if ($meta) {
    $data['meta'] = $meta;
  }
  
  if ($is_quiet) {
    $type = 'failed_captcha_quiet';
  }
  else {
    $type = 'failed_captcha';
  }
  
  write_to_event_log($type, $ip, $data);
}

function h_captcha_form($autoload = false, $cb = 'onRecaptchaLoaded', $dark = false) {
  global $hcaptcha_public_key;
  
  $js_tag = '<script src="https://hcaptcha.com/1/api.js'
    . (!$autoload ? "?onload=$cb&amp;render=explicit" : '') . '" async defer></script>';
  
  if ($autoload) {
    $attrs = ' class="h-captcha" data-sitekey="' . $hcaptcha_public_key . '"';
    
    if ($dark) {
      $attrs .= ' data-theme="dark"';
    }
  }
  else {
    $attrs = '';
  }
  
  $container_tag = '<script>window.hcaptchaKey = "' . $hcaptcha_public_key
      . '";</script><div id="g-recaptcha"'
      . $attrs . '></div>';
  
  return $js_tag.$container_tag;
}

// Moves css out of the form for html validation
function captcha_form($autoload = false, $cb = 'onRecaptchaLoaded', $dark = false) {
  global $recaptcha_public_key;
  
  $js_tag = '<script src="https://www.google.com/recaptcha/api.js'
    . (!$autoload ? "?onload=$cb&amp;render=explicit" : '') . '" async defer></script>';
  
  if ($autoload) {
    $attrs = ' class="g-recaptcha" data-sitekey="' . $recaptcha_public_key . '"';
    
    if ($dark) {
      $attrs .= ' data-theme="dark"';
    }
  }
  else {
    $attrs = '';
  }
  
  $container_tag = '<script>window.recaptchaKey = "' . $recaptcha_public_key
      . '";</script><div id="g-recaptcha"'
      . $attrs . '></div>';
  
  $noscript_tag =<<<HTML
<noscript>
  <div style="width: 302px;">
    <div style="width: 302px; position: relative;">
      <div style="width: 302px; height: 422px;">
        <iframe src="https://www.google.com/recaptcha/api/fallback?k=$recaptcha_public_key" frameborder="0" scrolling="no" style="width: 302px; height:422px; border-style: none;"></iframe>
      </div>
      <div style="width: 300px; height: 60px; border-style: none;bottom: 12px; left: 25px; margin: 0px; padding: 0px; right: 25px;background: #f9f9f9; border: 1px solid #c1c1c1; border-radius: 3px;">
        <textarea id="g-recaptcha-response" name="g-recaptcha-response" class="g-recaptcha-response" style="width: 250px; height: 40px; border: 1px solid #c1c1c1;margin: 10px 25px; padding: 0px; resize: none;"></textarea>
      </div>
    </div>
  </div>
</noscript>
HTML;
  
  if (defined('NOSCRIPT_CAPTCHA_ONLY') && NOSCRIPT_CAPTCHA_ONLY == 1) {
    return $container_tag.$noscript_tag;
  }
  
  return $js_tag.$container_tag.$noscript_tag;
}

// Legacy captcha
// Uses recaptcha v2 for noscript captcha as the v1 seems to be broken currently.
function captcha_form_alt() {
  global $recaptcha_public_key;
  
  $html = <<<HTML
<div id="captchaContainerAlt"></div>
<script>
function onAltCaptchaClick() {
  Recaptcha.reload('t');
}
function onAltCaptchaReady() {
  var el;
  
  if (el = document.getElementById('recaptcha_image')) {
    el.title = 'Reload';
    el.addEventListener('click', onAltCaptchaClick, false);
  }
}
if (!window.passEnabled) {
  var el = document.createElement('script');
  el.type = 'text/javascript';
  el.src = '//www.google.com/recaptcha/api/js/recaptcha_ajax.js';
  el.onload = function() {
    Recaptcha.create('$recaptcha_public_key',
      'captchaContainerAlt',
      {
        theme: 'clean',
        tabindex: 3,
        callback: onAltCaptchaReady
      }
    );
  }
  document.head.appendChild(el);
}</script>
<noscript>
  <div style="width: 302px;">
    <div style="width: 302px; position: relative;">
      <div style="width: 302px; height: 422px;">
        <iframe src="https://www.google.com/recaptcha/api/fallback?k=$recaptcha_public_key" frameborder="0" scrolling="no" style="width: 302px; height:422px; border-style: none;"></iframe>
      </div>
      <div style="width: 300px; height: 60px; border-style: none;bottom: 12px; left: 25px; margin: 0px; padding: 0px; right: 25px;background: #f9f9f9; border: 1px solid #c1c1c1; border-radius: 3px;">
        <textarea id="g-recaptcha-response" name="g-recaptcha-response" class="g-recaptcha-response" style="width: 250px; height: 40px; border: 1px solid #c1c1c1;margin: 10px 25px; padding: 0px; resize: none;"></textarea>
      </div>
    </div>
  </div>
</noscript>
HTML;
  
  return $html;
}

function recaptcha_ban($n, $time, $return_error = 0, $length = 1)
{
  auto_ban_poster($name, $length, 1, "failed verification $n times per $time", "Possible spambot; repeatedly sent incorrect CAPTCHA verification.");
  if( $return_error == 1 ) {
    return S_GENERICERROR;
  }
  error(S_GENERICERROR);
}

/**
 * Works for both recaptcha and hcaptcha
 */
function recaptcha_bad_captcha($return_error = false, $codes = null) {
  $error = S_BADCAPTCHA;
  
  if (is_array($codes)) {
    if (in_array('missing-input-response', $codes)) {
      $error = S_NOCAPTCHA;
    }
    
    if ($return_error) {
      return $error;
    }
    else {
      error($error);
    }
  }
  else {
    if ($return_error) {
      return $error;
    }
    else {
      error($error);
    }
  }
}

// -----------
// hCaptcha
// -----------
function start_hcaptcha_verify($return_error = false) {
  global $hcaptcha_private_key, $hcaptcha_ch;
  
  $response = $_POST["h-captcha-response"];
  
  if (!$response) {
    if ($return_error == false) {
      error(S_NOCAPTCHA);
    }
    return S_NOCAPTCHA;
  }
  
  $response = urlencode($response);
  
  $rlen = strlen($response);
  
  if ($rlen > 32768) {
    return recaptcha_bad_captcha($return_error);
  }
  
  $api_url = 'https://hcaptcha.com/siteverify';
  
  $post = array(
    'secret' => $hcaptcha_private_key,
    'response' => $response
  );
  
  $hcaptcha_ch = rpc_start_captcha_request($api_url, $post, null, false);
}

function end_hcaptcha_verify($return_error = false) {
  global $hcaptcha_ch;
  
  if (!$hcaptcha_ch) {
    return;
  }
  
  $ret = rpc_finish_request($hcaptcha_ch, $error, $httperror);
  
  // BAD
  // 413 Request Too Large is bad; it was caused intentionally by the user.
  if ($httperror == 413) {
    return recaptcha_bad_captcha($return_error);
  }
  
  // BAD
  if ($ret == null) {
    return recaptcha_bad_captcha($return_error);
  }
  
  $resp = json_decode($ret, true);
  
  // BAD
  // Malformed JSON response from Google
  if (json_last_error() !== JSON_ERROR_NONE) {
    return recaptcha_bad_captcha($return_error);
  }
  
  // GOOD
  if ($resp['success']) {
    return $resp;
  }
  
  // BAD
  return recaptcha_bad_captcha($return_error, $resp['error-codes']);
}

// -----------
// reCaptcha V2
// -----------
// FIXME $challenge_field is no longer used
function start_recaptcha_verify($return_error = false, $challenge_field = '') {
  global $recaptcha_private_key, $recaptcha_ch;
  
  $response = $_POST["g-recaptcha-response"];
  
  if (!$response) {
    if ($return_error == false) {
      error(S_NOCAPTCHA);
    }
    return S_NOCAPTCHA;
  }
  
  $response = urlencode($response);
  
  $rlen = strlen($response);
  
  if ($rlen > 4096) {
    return recaptcha_bad_captcha($return_error);
  }
  
  $api_url = 'https://www.google.com/recaptcha/api/siteverify';
  
  $post = array(
    'secret' => $recaptcha_private_key,
    'response' => $response
  );
  
  $recaptcha_ch = rpc_start_captcha_request($api_url, $post, null, false);
}

function end_recaptcha_verify($return_error = false) {
  global $recaptcha_ch;
  
  if (!$recaptcha_ch) {
    return;
  }
  
  $ret = rpc_finish_request($recaptcha_ch, $error, $httperror);
  
  // BAD
  // 413 Request Too Large is bad; it was caused intentionally by the user.
  if ($httperror == 413) {
    return recaptcha_bad_captcha($return_error);
  }
  
  // BAD
  if ($ret == null) {
    return recaptcha_bad_captcha($return_error);
  }
  
  $resp = json_decode($ret, true);
  
  // BAD
  // Malformed JSON response from Google
  if (json_last_error() !== JSON_ERROR_NONE) {
    return recaptcha_bad_captcha($return_error);
  }
  
  // GOOD
  if ($resp['success']) {
    return $resp;
  }
  
  // BAD
  return recaptcha_bad_captcha($return_error, $resp['error-codes']);
}

// -----------
// reCaptcha V1
// -----------
function start_recaptcha_verify_alt($return_error = false, $challenge_field = '') {
  global $recaptcha_private_key, $recaptcha_ch;
  
  $challenge = ( $challenge_field == '' ) ? $_POST["recaptcha_challenge_field"] : $challenge_field;
  $response  = $_POST["recaptcha_response_field"];
  if (!$challenge || !$response) {
    if( $return_error == false ) {
      error(S_NOCAPTCHA);
    }
    return S_NOCAPTCHA;
  }
  
  $num_words = 1 + preg_match_all('/\\s/', $response);
  $rlen = strlen($response);
  if ($num_words > 3 || $rlen > 128) {
    return recaptcha_bad_captcha($return_error);
  }
  
  $post = array(
    "privatekey" => $recaptcha_private_key,
    "challenge"  => $challenge,
    "remoteip"   => $_SERVER["REMOTE_ADDR"],
    "response"   => $response
  );
  
  $recaptcha_ch = rpc_start_request("https://www.google.com/recaptcha/api/verify", $post, null, false);
}

function end_recaptcha_verify_alt($return_error = false) {
  global $recaptcha_ch;
  
  if (!$recaptcha_ch) return;
  
  $ret = rpc_finish_request($recaptcha_ch, $error, $httperror);
  
  if ($httperror == 413) {
    return recaptcha_bad_captcha($return_error);
  }
  
  if ($ret) {
    $lines = explode("\n", $ret);
    if ($lines[0] === "true") {
      // GOOD
      return;
    }
  }
  
  // BAD
  return recaptcha_bad_captcha($return_error);
}

?>
