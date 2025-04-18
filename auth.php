<?php

define('IS_4CHANNEL', preg_match('/(^|\.)4channel.org$/', $_SERVER['HTTP_HOST']));

if (IS_4CHANNEL) {
  define('THIS_DOMAIN', '4channel.org');
  define('OTHER_DOMAIN', '4chan.org');
}
else {
  define('THIS_DOMAIN', '4chan.org');
  define('OTHER_DOMAIN', '4channel.org');
}

define('PASS_TIMEOUT', 900); // 15 minutes
define('LOGIN_FAIL_HOURLY', 5);

require_once 'lib/db.php';
require_once 'lib/geoip2.php';

class App {
  protected
    // Routes
    $actions = array(
      'index'
    ),
    
    $is_xhr = false
  ;
  
  const VIEW_TPL = 'views/pass_auth.tpl.php';
  
  const PASS_TABLE = 'pass_users';
  
  const
    AUTH_NO = 0,
    AUTH_SUCCESS = 1,
    AUTH_YES = 2,
    AUTH_ERROR = -1,
    AUTH_OUT = 4
  ;
  
  const
    ERR_BAD_REQUEST = 'Bad Request.',
    ERR_GENERIC = 'Internal Server Error (%s)',
    ERR_FLOOD = 'You have to wait a while before attempting this again.',
    ERR_EMPTY_FIELD = 'You have left one or more fields blank.',
    ERR_TOKEN_LEN = 'Your Token must be exactly 10 characters.',
    ERR_DB = 'We are currently having database issues. Please try again later.',
    ERR_BAD_AUTH = 'Incorrect Token or PIN.',
    ERR_IN_USE = 'This Pass is already in use by another IP. Please wait %s and re-authorize by visiting this page again to change IPs.',
    ERR_EXPIRED = 'This Pass has expired. Please visit <a href="https://www.4chan.org/pass.php?renew=%s">this page</a> to renew it.', // status 1
    ERR_REFUNDED = 'This Pass has been refunded and disabled. You cannot use it anymore.', // status 2
    ERR_DISPUTED = 'This Pass has a disputed payment. You cannot use it until the dispute is resolved.', // status 3
    ERR_REVOKED_SPAM = 'This Pass has been revoked due to spamming, which is a violation of the <a href="https://www.4chan.org/pass#termsofuse">Terms of Use</a>.', // status 4
    ERR_REVOKED_ILLEGAL = 'This Pass has been revoked due to illegal content being posted, which is a violaton of the <a href="https://www.4chan.org/pass#termsofuse">Terms of Use</a>.' // status 5
  ;
  
  private function error($msg) {
    $this->renderResponse(self::AUTH_ERROR, $msg);
  }
  
  private function renderResponse($status, $msg = null) {
    if ($this->is_xhr) {
      header('Content-type: application/json');
      echo json_encode(array('status' => $status, 'message' => $msg));
    }
    else {
      $this->auth_status = $status;
      $this->message = $msg;
      require_once(self::VIEW_TPL);
    }
    die();
  }
  
  private function pretty_duration($sec) {
    $duration = '';
    
    $hours = (int)($sec / 3600);
    $minutes = (int)($sec / 60);
    
    if ($hours) {
      $duration .= str_pad($hours, 2, '0', STR_PAD_LEFT) . ' hour';
      
      if ($hours != 1) {
        $duration .= 's';
      }
      
      $duration .= ' ';
    }
    
    if ($minutes) {
      $minutes = (int)(($sec / 60) % 60);
      
      $duration .= str_pad($minutes, 2, '0', STR_PAD_LEFT). ' minute';
      
      if ($minutes != 1) {
        $duration .= 's';
      }
    }
    
    $seconds = intval($sec % 60);
    
    return $duration;
  }
  
  private function get_csrf_token() {
    return bin2hex(openssl_random_pseudo_bytes(16));
  }
  
  private function validate_referer() {
    if (!isset($_SERVER['HTTP_REFERER']) || $_SERVER['HTTP_REFERER'] === '') {
      return;
    }
    
    if (!preg_match('/^https:\/\/sys\.(4chan|4channel)\.org(\/|$)/', $_SERVER['HTTP_REFERER'])) {
      $this->error(self::ERR_BAD_REQUEST);
    }
  }
  
  private function validate_csrf() {
    if (!isset($_COOKIE['csrf']) || !isset($_POST['csrf'])
      || $_COOKIE['csrf'] === '' || $_POST['csrf'] === '') {
      $this->error(self::ERR_BAD_REQUEST);
    }
    
    if ($_COOKIE['csrf'] !== $_POST['csrf']) {
      $this->error(self::ERR_BAD_REQUEST);
    }
  }
  
  private function validate_auth_flood($long_ip) {
    if (!$long_ip) {
      return;
    }
    
    $query = "SELECT COUNT(ip) FROM user_actions WHERE ip = $long_ip AND action = 'fail_pass_auth' AND time >= DATE_SUB(NOW(), INTERVAL 1 HOUR)";
    
    $res = mysql_global_call($query);
    
    if (!$res) {
      return;
    }
    
    $count = (int)mysql_fetch_row($res)[0];
    
    if ($count >= LOGIN_FAIL_HOURLY) {
      $this->error(self::ERR_FLOOD);
    }
  }
  
  private function register_auth_failure($long_ip) {
    if (!$long_ip) {
      return;
    }
    
    $query = "INSERT INTO user_actions (ip, board, action, time) VALUES(%d, '', 'fail_pass_auth', NOW())";
    $res = mysql_global_call($query, $long_ip);
  }
  
  private function convert_new_pass_status($user_hash, $hashed_pin) {
    $table = self::PASS_TABLE;
    
    $query = "UPDATE $table SET pin = '%s', status = 0 WHERE user_hash = '%s' AND status = 6 LIMIT 1";
    
    mysql_global_call($query, $hashed_pin, $user_hash);
    
    $this->set_cookie('pass_email', '', -1);
  }
  
  private function convert_delayed_pass_status($user_hash, $hashed_pin) {
    $table = self::PASS_TABLE;
    
    $query = "UPDATE $table SET pin = '%s', status = 0, expiration_date = NOW() + INTERVAL 1 YEAR WHERE user_hash = '%s' AND status = 7 LIMIT 1";
    
    mysql_global_call($query, $hashed_pin, $user_hash);
  }
  
  private function set_cookie($name, $value, $ttl, $secure = false, $http_only = false) {
    $name = rawurlencode($name);
    $value = rawurlencode($value);
    
    $domain = '.' . THIS_DOMAIN;
    
    $flags = array();
    
    if ($secure) {
      $flags[] = 'Secure';
    }
    
    if ($http_only) {
      $flags[] = 'HttpOnly';
    }
    
    if (!empty($flags)) {
      $flags = '; ' . implode('; ', $flags);
    }
    else {
      $flags = '';
    }
    
    if ($ttl !== 0) {
      $max_age = " Max-Age=$ttl;";
    }
    else {
      $max_age = '';
    }
    
    header("Set-Cookie: $name=$value; Path=/;$max_age Domain=$domain; SameSite=None$flags", false);
  }
  
  private function clear_cookies() {
    $cookie_time = -3600;
    $this->set_cookie('pass_id', '', $cookie_time, true, true);
    $this->set_cookie('pass_enabled', '', $cookie_time, true);
  }
  
  private function get_random_base64bytes($length = 64) {
    $data = openssl_random_pseudo_bytes($length);
    
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '='); 
  }
  
  private function get_salt() {
    $salt = file_get_contents('/www/keys/2014_admin.salt');
    
    if (!$salt) {
      $this->error(sprintf(self::ERR_GENERIC, 'gs'));
    }
    
    return $salt;
  }
  
  /**
   * Login
   */
  private function authenticate() {
    $this->validate_referer();
    
    $table = self::PASS_TABLE;
    
    $time_now = time();
    
    // Token
    if (!isset($_POST['id']) || $_POST['id'] === '') {
      $this->error(self::ERR_EMPTY_FIELD);
    }
    
    if (strlen($_POST['id']) != 10) {
      $this->error(self::ERR_TOKEN_LEN);
    }
    
    $id = $_POST['id'];
    
    // Pin
    if (!isset($_POST['pin']) || $_POST['pin'] === '') {
      $this->error(self::ERR_EMPTY_FIELD);
    }
    
    $pin = $_POST['pin'];
    
    // ---
    
    $ip = $_SERVER['REMOTE_ADDR'];
    $long_ip = ip2long($ip);
    
    $this->validate_auth_flood($long_ip);
    
    // ---
    
    $plain_pin = $pin;
    $pin = crypt($pin, substr($id, 4, 9));
    
    $query = "SELECT * FROM $table WHERE user_hash = '%s' AND (pin = '%s' OR pin = '%s') LIMIT 1";
    
    $res = mysql_global_call($query, $id, $pin, $plain_pin);
    
    if (!$res) {
      $this->error(self::ERR_DB);
    }
    
    if (mysql_num_rows($res) !== 1) {
      $this->register_auth_failure($long_ip);
      $this->error(self::ERR_BAD_AUTH);
    }
    
    $pass = mysql_fetch_assoc($res);
    
    if (!$pass) {
      $this->error(sprintf(self::ERR_GENERIC, 'mfa1'));
    }
    
    $last_used = strtotime($pass['last_used']);
    
    $last_ip_mask = ip2long($pass['last_ip']) & (~65535);
    
    $ip_mask = $long_ip & (~65535);
    
    if ($last_ip_mask !== 0 && ($time_now - $last_used) < PASS_TIMEOUT && $last_ip_mask != $ip_mask) {
      $remaining = $this->pretty_duration(PASS_TIMEOUT - ($time_now - $last_used));
      $this->error(sprintf(self::ERR_IN_USE, $remaining));
    }
    
    switch ($pass['status']){
      case 0:
        break;
        
      case 1:
        $this->clear_cookies();
        $this->error(sprintf(self::ERR_EXPIRED, $pass['pending_id']));
        break;
        
      case 2:
        $this->clear_cookies();
        $this->error(self::ERR_REFUNDED);
        break;
        
      case 3:
        $this->clear_cookies();
        $this->error(self::ERR_DISPUTED);
        break;
        
      case 4:
        $this->clear_cookies();
        $this->error(self::ERR_REVOKED_SPAM);
        break;
        
      case 5:
        $this->clear_cookies();
        $this->error(self::ERR_REVOKED_ILLEGAL);
        break;
        
      case 6:
        $this->convert_new_pass_status($pass['user_hash'], $pin);
        break;
        
      case 7:
        $this->convert_delayed_pass_status($pass['user_hash'], $pin);
        break;
    }
    
    // Update country
    $geo_data = GeoIP2::get_country($ip);
    
    if ($geo_data && isset($geo_data['country_code'])) {
      $country_code = mysql_real_escape_string($geo_data['country_code']);
    }
    else {
      $country_code = 'XX';
    }
    
    $update_country = ", last_country = '$country_code'";
    
    $query = "UPDATE $table SET last_ip = '%s', last_used = NOW() $update_country WHERE user_hash = '%s' AND last_ip != '%s' AND status = 0 LIMIT 1";
    
    mysql_global_call($query, $ip, $id, $ip);
    
    // Update session id
    if (!$pass['session_id']) {
      $pass_session = $this->get_random_base64bytes(32);
      
      if (!$pass_session) {
        $this->error(sprintf(self::ERR_GENERIC, 'grb'));
      }
      
      $query = "UPDATE $table SET session_id = '$pass_session' WHERE user_hash = '%s' AND status = 0 LIMIT 1";
      
      mysql_global_call($query, $id);
    }
    else {
      $pass_session = $pass['session_id'];
    }
    
    $admin_salt = $this->get_salt();
    
    $hashed_pass_session = substr(hash('sha256', $pass_session . $admin_salt), 0, 32);
    
    if (!$hashed_pass_session) {
      $this->error(sprintf(self::ERR_GENERIC, 'hps'));
    }
    
    if (isset($_POST['long_login'])) {
      $cookie_time = 31556900;
    }
    else {
      $cookie_time = 86400;
    }
    
    $this->set_cookie('pass_id', "$id.$hashed_pass_session", $cookie_time, true, true);
    $this->set_cookie('pass_enabled', '1', $cookie_time, true);
    
    $this->renderResponse(self::AUTH_SUCCESS);
  }
  
  /**
   * Index
   */
  public function index() {
    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
      if (isset($_POST['logout'])) {
        $this->validate_referer();
        $this->clear_cookies();
        $this->renderResponse(self::AUTH_OUT);
      }
      else {
        return $this->authenticate();
      }
    }
    
    if (isset($_COOKIE['pass_enabled'])) {
      $this->renderResponse(self::AUTH_YES);
    }
    else {
      $this->renderResponse(self::AUTH_NO);
    }
  }
  
  /**
   * Main
   */
  public function run() {
    $method = $_SERVER['REQUEST_METHOD'] === 'POST' ? $_POST : $_GET;
    
    if (isset($method['action'])) {
      $action = $method['action'];
    }
    else {
      $action = 'index';
    }
    
    if (in_array($action, $this->actions)) {
      if (isset($method['xhr'])) {
        /*
        if (isset($_SERVER['HTTP_ORIGIN']) && preg_match('/^https:\/\/sys\.(4chan|4channel)\.org$/', $_SERVER['HTTP_ORIGIN'])) {
          header('Access-Control-Allow-Origin: ' . $_SERVER['HTTP_ORIGIN']);
          header('Access-Control-Allow-Methods: OPTIONS, POST');
          header('Access-Control-Allow-Credentials: true');
        }
        */
        $this->is_xhr = true;
      }
      
      $this->$action();
    }
    else {
      $this->error('Bad request');
    }
  }
}

$ctrl = new App();
$ctrl->run();
