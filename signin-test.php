<?php

define('DEV_MODE', $_SERVER['REMOTE_ADDR'] === '195.154.113.119');

if (DEV_MODE) {
  ini_set('display_errors', 1);
  error_reporting(E_ALL & ~E_NOTICE);
  $mysql_suppress_err = false;
}
else {
  http_response_code(404);
  echo('File not found.');
  die();
}
/*
http_response_code(404);
echo('File not found.');
die();
*/
require_once 'lib/db.php';
require_once 'lib/userpwd.php';
require_once 'lib/geoip2.php';
require_once 'lib/ini.php';

// ---

load_ini_file('captcha_config.ini');

class App {
  protected
    // Routes
    $actions = array(
      'index',
      'request',
      'verify',
      'signout'
    )
  ;
  
  const WEB_PATH = '/signin-85c692c2.php';
  
  const TPL_ROOT = 'views/';
  
  const TBL = 'email_signins';
  const TBL_QUEUE = 'email_signins_queue';
  const TBL_BLACKLIST = 'email_signins_blacklist';
  
  const PWD_DOMAIN = '4chan.org';
  
  const HMAC_KEY_PATH = '/www/keys/2024_key64.key';
  
  const CSRF_ARG = 'csrf';
  
  const
    PWD_BYTES = 16,
    TOKEN_BYTES = 16,
    TOKEN_MAX_USAGES = 5,
    VERIFY_TOKEN_TTL = 86400, // 24 hours
    PRUNE_DAYS = 7,
    MAX_CAPTCHA_FAILURES = 6,
    MAX_CAPTCHA_FAILURES_SUSP = 3,
    BOT_SCORE_SUSP = 80
  ;
  
  // Cooldowns
  const
    REQ_CD = 150, // 2.5 minutes, base minimum cooldown
    REQ_CD_MAIL_PER_DAY = 3, // lockdown kicks in after this number of attempts
    REQ_CD_IP_PER_HOUR = 3 // lockdown kicks in after this number of attempts
  ;
  
  const VERIFIED_LEVEL = 1;
  
  const CAPTCHA_MODE = 2; // 1 for reCaptcha, 2 for hCaptcha
  
  const
    ERR_BAD_REQUEST = 'Bad Request.',
    ERR_BAD_CAPTCHA = 'Invalid captcha.',
    ERR_COOKIES = 'Cookies need to be enabled before continuing.',
    ERR_GENERIC = 'Internal Server Error (%s)',
    ERR_BAD_LINK = 'Invalid or expired link.',
    ERR_RANGEBAN = 'This ISP has been blocked due to abuse.',
    ERR_BAD_EMAIL = 'Invalid email address.',
    ERR_BAD_EMAIL_DOMAIN = 'This email provider is not allowed.',
    ERR_BAD_EMAIL_PLUS = 'Email subaddressing is not allowed',
    ERR_CD = 'You have to wait %d more %s before attempting this again.',
    ERR_EMAIL_QUEUED = 'You have to wait a while before attempting this again.',
    ERR_PASS_USER = 'Email verification is not required for 4chan Pass users.',
    ERR_DB = 'We are currently having database issues. Please try again later.'
  ;
  
  const
    EVT_BAD_COUNTRY = 1,
    EVT_MAX_USED = 2,
    EVT_BLACKLISTED = 3
  ;
  
  const VERIFY_EMAIL_DOMAIN = true;
  
  static $allowed_domains = [
    'gmail.com',
    'hotmail.com',
    'yahoo.com',
    'proton.me',
    'protonmail.com',
    'outlook.com',
    'live.com',
    'icloud.com',
    'yandex.com',
    'tutanota.com',
    'tutamail.com',
    'tuta.io'
  ];
  
  private function renderHTML($view) {
    include(self::TPL_ROOT . $view . '.tpl.php');
  }
  
  final protected function error($msg) {
    $this->mode = 'error';
    $this->msg = $msg;
    $this->renderHTML('signin-test');
    die();
  }
  
  final protected function error_generic($code) {
    $this->error(sprintf(self::ERR_GENERIC, $code));
  }
  
  final protected function error_cooldown($units_left, $units = 'minute') {
    if ($units_left > 1) {
      $units .= 's';
    }
    
    $this->error(sprintf(self::ERR_CD, $units_left, $units));
  }
  
  private function log_event($event_id, $token) {
    $sql = <<<SQL
INSERT INTO event_log(`type`, ip, arg_num, arg_str)
VALUES('signin_evt', '%s', '%d', '%s')
SQL;
    
    return mysql_global_call($sql, $_SERVER['REMOTE_ADDR'], $event_id, $token);
  }
  
  private function get_csrf_token() {
    return bin2hex(openssl_random_pseudo_bytes(8));
  }
  
  private function validate_csrf() {
    $arg = self::CSRF_ARG;
    
    if (!isset($_COOKIE[$arg]) || !isset($_POST[$arg])
      || $_COOKIE[$arg] === '' || $_POST[$arg] === '') {
      $this->error(self::ERR_COOKIES);
    }
    
    if ($_COOKIE[$arg] !== $_POST[$arg]) {
      $this->error(self::ERR_COOKIES);
    }
  }
  
  private function is_email_blacklisted($email) {
    $tbl = self::TBL_BLACKLIST;
    
    $sql = "SELECT 1 FROM `$tbl` WHERE email = '%s' LIMIT 1";
    
    $res = mysql_global_call($sql, $email);
    
    if (!$res) {
      return false;
    }
    
    return mysql_num_rows($res) === 1;
  }
  
  private function get_bot_score() {
    if (!isset($_SERVER['HTTP_X_BOT_SCORE'])) {
      return 100;
    }
    
    return (int)$_SERVER['HTTP_X_BOT_SCORE'];
  }
  
  private function get_token_bot_score($token) {
    $tbl = self::TBL;
    
    $sql =<<<SQL
SELECT bot_score FROM `$tbl` WHERE token = '%s'
LIMIT 1
SQL;
    
    $res = mysql_global_call($sql, $token);
    
    if (!$res) {
      return 100;
    }
    
    $score = mysql_fetch_row($res);
    
    if (!$score) {
      return 100;
    }
    
    $score = (int)$score[0];
    
    if ($score <= 0) {
      return 100;
    }
    
    return $score;
  }
  
  private function update_usage_count($token) {
    $tbl = self::TBL;
    
    $max_usages = (int)self::TOKEN_MAX_USAGES;
    
    if ($max_usages <= 0) {
      return true;
    }
    
    $max_usages += 1;
    
    $sql =<<<SQL
UPDATE `$tbl` SET used = LEAST(used + 1, $max_usages)
WHERE token = '%s' LIMIT 1
SQL;
    
    return !!mysql_global_call($sql, $token);
  }
  
  private function validate_cooldowns($email, $hashed_email) {
    $ip = $_SERVER['REMOTE_ADDR'];
    $now = $_SERVER['REQUEST_TIME'];
    
    if (!$ip || !$now || !$email || !$hashed_email) {
      $this->error_generic('vf');
    }
    
    $tbl = self::TBL;
    $tbl_queue = self::TBL_QUEUE;
    
    // ---
    // Base cooldown for IP
    // ---
    $query =<<<SQL
SELECT UNIX_TIMESTAMP(created_on) FROM `$tbl`
WHERE ip = '%s'
ORDER BY created_on DESC
LIMIT 1
SQL;
    
    $res = mysql_global_call($query, $ip);
    
    if (!$res) {
      $this->error(self::ERR_DB);
    }
    
    $last_ts = (int)mysql_fetch_row($res)[0];
    
    $delta = $now - $last_ts;
    
    if ($delta < self::REQ_CD) {
      $cd = ceil($delta / 60.0);
      $this->error_cooldown($cd);
    }
    
    // ---
    // Check if the email is already in the sending queue
    // ---
    $query = "SELECT 1 FROM `$tbl_queue` WHERE email = '%s' LIMIT 1";
    
    $res = mysql_global_call($query, $email);
    
    if (!$res) {
      $this->error(self::ERR_DB);
    }
    
    if (mysql_num_rows($res) > 0) {
      $this->error(self::ERR_EMAIL_QUEUED);
    }
    
    // ---
    // Check repeated requests for IP
    // ---
    $_ip_per_hour = (int)self::REQ_CD_IP_PER_HOUR;
    $cd = 3600;
    
    $query =<<<SQL
SELECT UNIX_TIMESTAMP(created_on) FROM `$tbl`
WHERE ip = '%s'
AND created_on > DATE_SUB(NOW(), INTERVAL 1 HOUR)
ORDER BY created_on ASC
LIMIT $_ip_per_hour
SQL;
    
    $res = mysql_global_call($query, $ip);
    
    if (!$res) {
      $this->error(self::ERR_DB);
    }
    
    if (mysql_num_rows($res) == $_ip_per_hour) {
      $last_ts = (int)mysql_fetch_row($res)[0];
      $cd = ceil(($last_ts - $now + $cd) / 60.0);
      $this->error_cooldown($cd);
    }
    
    // ---
    // Check repeated requests for email
    // ---
    $_email_per_day = (int)self::REQ_CD_MAIL_PER_DAY;
    $cd = 86400;
    
    $query =<<<SQL
SELECT UNIX_TIMESTAMP(created_on) FROM `$tbl`
WHERE hashed_email = '%s'
AND created_on > DATE_SUB(NOW(), INTERVAL 1 DAY)
ORDER BY created_on ASC
LIMIT $_email_per_day
SQL;
    
    $res = mysql_global_call($query, $hashed_email);
    
    if (!$res) {
      $this->error(self::ERR_DB);
    }
    
    if (mysql_num_rows($res) == $_email_per_day) {
      $last_ts = (int)mysql_fetch_row($res)[0];
      $cd = ($last_ts - $now + $cd) / 60.0;
      
      if ($cd > 60) {
        $cd = $cd / 60.0;
        $units = 'hour';
      }
      else {
        $units = 'minute';
      }
      
      $cd = ceil($cd);
      
      $this->error_cooldown($cd, $units);
    }
  }
  
  private function is_valid_captcha_t() {
    require_once 'lib/captcha.php';
    
    $m = new Memcached();
    //$m->setOption(Memcached::OPT_TCP_NODELAY, true);
    $m->setOption(Memcached::OPT_SERVER_FAILURE_LIMIT, 1);
    $m->setOption(Memcached::OPT_SEND_TIMEOUT, 500000); // 500ms
    $m->setOption(Memcached::OPT_RECV_TIMEOUT, 500000); // 500ms
    $m->addServer('localhost', 11211);
    
    return is_twister_captcha_valid($m, $_SERVER['REMOTE_ADDR'], null, '!signin', 1, $_uc);
  }
  
  private function validate_captcha($force_recaptcha = false) {
    if (!defined('RECAPTCHA_API_KEY_PRIVATE')) {
      $this->error_generic('nck');
    }
    
    if (!isset($_POST["g-recaptcha-response"])) {
      $this->error(self::ERR_BAD_CAPTCHA);
    }
    
    $response = $_POST["g-recaptcha-response"];
    
    if (!$response || strlen($response) > 4096) {
      $this->error(self::ERR_BAD_CAPTCHA);
    }
    
    if (self::CAPTCHA_MODE === 2 && !$force_recaptcha) {
      $url = 'https://hcaptcha.com/siteverify';
      $captcha_private_key = HCAPTCHA_API_KEY_PRIVATE;
      $captcha_public_key = HCAPTCHA_API_KEY_PUBLIC;
    }
    else {
      $url = 'https://www.google.com/recaptcha/api/siteverify';
      $captcha_private_key = RECAPTCHA_API_KEY_PRIVATE;
      $captcha_public_key = null;
    }
    
    $post = array(
      'secret' => $captcha_private_key,
      'response' => $response,
      'remoteip' => $_SERVER['REMOTE_ADDR']
    );
    
    if ($captcha_public_key) {
      $post['sitekey'] = $captcha_public_key;
    }
    
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
      $this->error_generic('cne0');
    }
    
    $resp_status = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    
    if ($resp_status >= 300) {
      curl_close($curl);
      $this->error_generic('cne1');
    }
    
    curl_close($curl);
    
    $json = json_decode($resp, true);
    
    // BAD
    if (json_last_error() !== JSON_ERROR_NONE) {
      $this->error(self::ERR_BAD_CAPTCHA);
    }
    
    // GOOD
    if ($json && isset($json['success']) && $json['success']) {
      return true;
    }
    
    // BAD
    $this->error(self::ERR_BAD_CAPTCHA);
  }
  
  private function get_random_hex_bytes($length = 64) {
    $data = openssl_random_pseudo_bytes($length);
    
    if (!$data) {
      return false;
    }
    
    return bin2hex($data); 
  }
  
  private function generate_token() {
    return $this->get_random_hex_bytes(self::TOKEN_BYTES);
  }
  
  private function set_ev1_cookie($flag = true) {
    $cookie_name = '_ev1';
    
    if ($flag) {
      setcookie($cookie_name, '1', $_SERVER['REQUEST_TIME'] + 60, '/', '.' . self::PWD_DOMAIN, true, false);
    }
    else {
      setcookie($cookie_name, '', -1, '/', '.' . self::PWD_DOMAIN, true, false);
    }
  }
  
  private function prune_old_requests() {
    $tbl = self::TBL;
    $ttl = (int)self::PRUNE_DAYS;
    $sql = "DELETE FROM `$tbl` WHERE created_on <= DATE_SUB(NOW(), INTERVAL $ttl DAY)";
    return mysql_global_call($sql);
  }
  
  private function validate_email($email) {
    if (!preg_match('/^[^@]+@[^@]+\.[a-z]+$/', $email)) {
      $this->error(self::ERR_BAD_EMAIL);
    }
    
    if (strpos($email, '+') !== false) {
      $this->error(self::ERR_BAD_EMAIL_PLUS);
    }
    
    if (self::VERIFY_EMAIL_DOMAIN) {
      $flag = false;
      
      $domain = explode('@', $email)[1];
      
      if (!$domain) {
        $this->error(self::ERR_BAD_EMAIL);
      }
      
      foreach (self::$allowed_domains as $allowed_domain) {
        if ($allowed_domain === $domain) {
          $flag = true;
          break;
        }
      }
      
      if (!$flag) {
        $this->error(self::ERR_BAD_EMAIL_DOMAIN);
      }
    }
    
    return true;
  }
  
  private function is_pwd_banned($pwd) {
    if (!$pwd) {
      return false;
    }
    
    $sql =<<<SQL
SELECT 1 FROM banned_users
WHERE active = 1 AND password = '%s' AND length > NOW()
LIMIT 1
SQL;
    
    $res = mysql_global_call($sql, $pwd);
    
    if (!$res) {
      return false;
    }
    
    return (int)mysql_num_rows($res) > 0;
  }
  
  private function validate_rangeban() {
    $long_ip = ip2long($_SERVER['REMOTE_ADDR']);
    
    if (!$long_ip) {
      $this->error_generic('vri');
    }
    
    $asn = 0;
    
    if (isset($_SERVER['HTTP_X_GEO_ASN'])) {
      $asn = (int)$_SERVER['HTTP_X_GEO_ASN'];
    }
    else {
      $_asninfo = GeoIP2::get_asn($ip);
      
      if ($_asninfo) {
        $asn = (int)$_asninfo['asn'];
      }
    }
    
    $now = (int)$_SERVER['REQUEST_TIME'];
    
    $perma_clause =<<<SQL
expires_on = 0 AND boards = '' AND ops_only = 0 AND img_only = 0
AND lenient = 0 AND report_only = 0 AND ua_ids = ''
SQL;
    
    $query = <<<SQL
(SELECT SQL_NO_CACHE 1 FROM iprangebans
WHERE range_start <= $long_ip AND range_end >= $long_ip AND active = 1
AND $perma_clause)
SQL;
    
    if ($asn > 0) {
      $query .= <<<SQL
UNION (SELECT 1 FROM iprangebans
WHERE asn = $asn AND active = 1 AND $perma_clause)
SQL;
    }
    
    $res = mysql_global_call($query);
    
    if (!$res) {
      $this->error(self::ERR_DB);
    }
    
    if ((int)mysql_num_rows($res) > 0) {
      $this->error(self::ERR_RANGEBAN);
    }
  }
  
  private function get_country() {
    static $country = null;
    
    if ($country !== null) {
      return $country;
    }
    
    if (isset($_SERVER['HTTP_X_GEO_COUNTRY'])) {
      $country = $_SERVER['HTTP_X_GEO_COUNTRY'];
    }
    else {
      $geo_data = GeoIP2::get_country($_SERVER['REMOTE_ADDR']);
    
      if ($geo_data && isset($geo_data['country_code'])) {
        $country = $geo_data['country_code'];
      }
      else {
        $country = 'XX';
      }
    }
    
    return $country;
  }
  
  private function get_user_agent() {
    $ua = $_SERVER['HTTP_USER_AGENT'];
    
    if (isset($_SERVER['HTTP_SEC_CH_UA_MODEL']) && $_SERVER['HTTP_SEC_CH_UA_MODEL'] && $_SERVER['HTTP_SEC_CH_UA_MODEL'] != '""') {
      $model = $_SERVER['HTTP_SEC_CH_UA_MODEL'];
      $ua .= " ~[$model]";
    }
    
    return $ua;
  }
  
  private function normalize_gmail_address($email) {
    list($user, $domain) = explode('@', $email);
    $user = preg_replace('/\./', '', $user);
    return "$user@$domain";
  }
  
  private function hash_email($email) {
    $hmac_key = file_get_contents(self::HMAC_KEY_PATH);
    
    if (!$hmac_key) {
      return false;
    }
    
    $hashed_email = substr(hash_hmac('sha256', $email, $hmac_key, true), 0, self::PWD_BYTES);
    
    return bin2hex($hashed_email);
  }
  
  private function get_domain($email) {
    $parts = explode('@', $email);
    
    if (count($parts) !== 2) {
      return '';
    }
    
    return $parts[1];
  }
  
  private function create_request($email, $hashed_email) {
    $tbl = self::TBL;
    $tbl_queue = self::TBL_QUEUE;
    
    $token = $this->generate_token();
    
    if (!$token) {
      $this->error_generic('gt');
    }
    
    if (!$email || !$hashed_email) {
      $this->error_generic('crne');
    }
    
    $ip = $_SERVER['REMOTE_ADDR'];
    
    $ua = $this->get_user_agent();
    
    $country = $this->get_country();
    
    if ($country === 'T1') {
      $this->error(self::ERR_RANGEBAN);
    }
    
    $domain = $this->get_domain($email);
    
    if (isset($_SERVER['HTTP_X_BOT_SCORE'])) {
      $bot_score = (int)$_SERVER['HTTP_X_BOT_SCORE'];
    }
    else {
      $bot_score = 0;
    }
    
    // Insert the request
    $sql =<<<SQL
INSERT INTO `$tbl` (token, hashed_email, ip, domain, ua, country, bot_score)
VALUES ('%s', '%s', '%s', '%s', '%s', '%s', %d)
SQL;
    
    $res = mysql_global_call($sql, $token, $hashed_email, $ip, $domain, $ua, $country, $bot_score);
    
    if (!$res) {
      $this->error_generic('cr2');
    }
    
    // Add the email to the mailer job queue
    $sql =<<<SQL
INSERT INTO `$tbl_queue` (email, token)
VALUES ('%s', '%s')
SQL;
    
    $res = mysql_global_call($sql, $email, $token);
    
    if (!$res) {
      $this->error_generic('crq');
    }
    
    return $token;
  }
  
  private function register_captcha_failure($token) {
    $tbl = self::TBL;
    
    if (!$token) {
      return false;
    }
    
    $sql = "UPDATE `$tbl` SET failed_challenges = failed_challenges + 1 WHERE token = '%s' LIMIT 1";
    
    mysql_global_call($sql, $token);
  }
  
  private function get_captcha_failures($token) {
    $tbl = self::TBL;
    
    if (!$token) {
      return 0;
    }
    
    $sql = "SELECT failed_challenges FROM `$tbl` WHERE token = '%s' LIMIT 1";
    
    $res = mysql_global_call($sql, $token);
    
    if (!$res) {
      return 0;
    }
    
    return (int)mysql_fetch_row($res)[0];
  }
  
  public function request() {
    $this->mode = 'request';
    
    $this->validate_csrf();
    
    if (!isset($_POST['email']) || !$_POST['email']) {
      $this->error(self::ERR_BAD_EMAIL);
    }
    
    $email = strtolower(trim($_POST['email']));
    
    $this->validate_email($email);
    
    if ($this->get_domain($email) == 'gmail.com') {
      $clean_email = $this->normalize_gmail_address($email);
    }
    else {
      $clean_email = $email;
    }
    
    $hashed_email = $this->hash_email($clean_email);
    
    if (!$hashed_email) {
      $this->error_generic('nhe');
    }
    
    $this->validate_cooldowns($email, $hashed_email);
    
    $this->validate_captcha();
    
    $this->validate_rangeban();
    
    if (isset($_COOKIE['4chan_pass'])) {
      $pwd = UserPwd::decodePwd($_COOKIE['4chan_pass']);
      
      // Password has a ban, show fake success screen
      if ($pwd && $this->is_pwd_banned($pwd)) {
        return $this->renderHTML('signin-test');
      }
    }
    
    // Email is blacklisted, show fake success screen
    if ($this->is_email_blacklisted($email)) {
      $this->log_event(self::EVT_BLACKLISTED, $hashed_email);
      return $this->renderHTML('signin-test');
    }
    
    $token = $this->create_request($email, $hashed_email);
    
    $this->renderHTML('signin-test');
  }
  
  private function pre_verify() {
    if (!isset($_GET['tkn'])) {
      $this->error(self::ERR_BAD_REQUEST);
    }
    
    $this->token = trim($_GET['tkn']);
    
    if (!$this->token) {
      $this->error(self::ERR_BAD_REQUEST);
    }
    
    $_token_bot_score = $this->get_token_bot_score($this->token);
    
    if (false && $_token_bot_score < 90) {
      $this->use_recaptcha = true;
    }
    else {
      $this->use_recaptcha = false;
    }
    
    $this->csrf_token = $this->get_csrf_token();
    
    setcookie(self::CSRF_ARG, $this->csrf_token, 0, '/', $_SERVER['HTTP_HOST'], true, true);
    
    $this->renderHTML('signin-test');
  }
  
  public function verify() {
    if ($_SERVER['REQUEST_METHOD'] == 'GET') {
      $this->mode = 'verify';
      return $this->pre_verify();
    }
    else {
      $this->mode = 'verify-done';
    }
    
    $this->validate_csrf();
    
    if (!isset($_POST['tkn'])) {
      $this->error(self::ERR_BAD_REQUEST);
    }
    
    $token = trim($_POST['tkn']);
    
    if (!$token) {
      $this->error(self::ERR_BAD_REQUEST);
    }
    
    if (isset($_COOKIE['pass_enabled']) && $_COOKIE['pass_enabled']) {
      $this->error(self::ERR_PASS_USER);
    }
    
    $_bot_score = $this->get_bot_score();
    $_token_bot_score = $this->get_token_bot_score($token);
    
    if ($_bot_score < self::BOT_SCORE_SUSP || $_token_bot_score < self::BOT_SCORE_SUSP) {
      $_max_fails = self::MAX_CAPTCHA_FAILURES_SUSP;
    }
    else {
      $_max_fails = self::MAX_CAPTCHA_FAILURES;
    }
    
    if ($_token_bot_score < self::BOT_SCORE_SUSP) {
      $this->use_recaptcha = true;
    }
    else {
      $this->use_recaptcha = false;
    }
    
    if ($this->get_captcha_failures($token) >= $_max_fails) {
      $this->error(self::ERR_BAD_LINK);
    }
    
    if ($this->use_recaptcha == false) {
      if ($this->is_valid_captcha_t() !== true) {
        $this->register_captcha_failure($token);
        $this->mode = 'verify-captcha-failed';
        $this->token = $token;
        $this->renderHTML('signin-test');
        die();
      }
    }
    else {
      $this->validate_captcha(true);
    }
    
    $this->prune_old_requests();
    
    $this->update_usage_count($token);
    
    $tbl = self::TBL;
    
    $ttl = (int)self::VERIFY_TOKEN_TTL;
    
    $sql =<<<SQL
SELECT * FROM `$tbl` WHERE token = '%s'
AND created_on > DATE_SUB(NOW(), INTERVAL $ttl SECOND)
SQL;
    
    $res = mysql_global_call($sql, $token);
    
    if (!$res) {
      $this->error(self::ERR_DB);
    }
    
    $request = mysql_fetch_assoc($res);
    
    if (!$request) {
      $this->error(self::ERR_BAD_LINK);
    }
    
    if (!$request['hashed_email']) {
      $this->error_generic('hee');
    }
    
    // Validate usage count
    if ((int)$request['used'] > self::TOKEN_MAX_USAGES) {
      $this->log_event(self::EVT_MAX_USED, $request['token']);
      $this->error(self::ERR_BAD_LINK);
    }
    
    // Country must match the requester's country
    $country = $this->get_country();
    
    if ($country !== $request['country']) {
      $this->log_event(self::EVT_BAD_COUNTRY, $request['token']);
      $this->error(self::ERR_BAD_LINK);
    }
    
    $ip = $_SERVER['REMOTE_ADDR'];
    
    $userpwd = null;
    
    if (isset($_COOKIE['4chan_pass'])) {
      $userpwd = new UserPwd($ip, self::PWD_DOMAIN, $_COOKIE['4chan_pass']);
      
      if (!$userpwd) {
        $this->error_generic('nup');
      }
      
      // Password has a ban, show fake success screen
      if ($this->is_pwd_banned($userpwd->getPwd())) {
        return $this->renderHTML('signin-test');
      }
      
      // If already verified, make a brand new pwd
      if ($userpwd->verifiedLevel() > 0) {
        $userpwd = null;
      }
    }
    
    if (!$userpwd) {
      $userpwd = new UserPwd($ip, self::PWD_DOMAIN);
      
      if (!$userpwd) {
        $this->error_generic('nupn');
      }
    }
    
    $userpwd->setPwd($request['hashed_email']);
    $userpwd->setVerifiedLevel(self::VERIFIED_LEVEL);
    
    $userpwd->setCookie('.' . self::PWD_DOMAIN);
    
    // This is to let currently running cooldowns that the email was verified
    $this->set_ev1_cookie(true);
    
    $this->renderHTML('signin-test');
  }
  
  /**
   * Signout - deletes the password cookie
   */
  public function signout() {
    $this->mode = 'signout';
    
    $this->validate_csrf();
    
    $userpwd = null;
    
    if (isset($_COOKIE['4chan_pass'])) {
      $userpwd = new UserPwd($_SERVER['REMOTE_ADDR'], self::PWD_DOMAIN, $_COOKIE['4chan_pass']);
    }
    
    // If already verified, make a brand new pwd
    if ($userpwd && $userpwd->verifiedLevel() > 0) {
      setcookie(UserPwd::COOKIE_NAME, '', -1, '/', '.' . self::PWD_DOMAIN, true, true);
    }
    
    $this->set_ev1_cookie(false);
    
    $this->renderHTML('signin-test');
  }
  
  /**
   * Index
   */
  public function index() {
    $this->mode = 'index';
    
    if (isset($_COOKIE['pass_enabled']) && $_COOKIE['pass_enabled']) {
      $this->pass_user = true;
      return $this->renderHTML('signin-test');
    }
    
    $this->pass_user = false;
    
    $this->csrf_token = $this->get_csrf_token();
    
    $domain = $_SERVER['HTTP_HOST'];
    
    $userpwd = null;
    
    if (isset($_COOKIE['4chan_pass'])) {
      $userpwd = new UserPwd($_SERVER['REMOTE_ADDR'], self::PWD_DOMAIN, $_COOKIE['4chan_pass']);
    }
    
    $this->authed = $userpwd && $userpwd->verifiedLevel() > 0;
    
    setcookie(self::CSRF_ARG, $this->csrf_token, 0, '/', $domain, true, true);
    
    $this->renderHTML('signin-test');
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
      $this->$action();
    }
    else {
      $this->error('Bad request');
    }
  }
}

$ctrl = new App();
$ctrl->run();
