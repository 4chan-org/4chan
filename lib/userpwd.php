<?php

final class UserPwd {
  // 32 bytes as hex
  const HMAC_SECRET = '1e1e4476a89b28307dd39a56df4223bad7fea2045283a65ac3092a7adbf7f546';
  
  // xor key, 128 bytes as hex, must be longer than the encrypted data
  const XOR_KEY = 'fc2417fb8df1d82889f42a40a6241ea9b29de83ed7bf58d0c3d26e39e2bec70af0b2b9451c4a42e79a02dc6b78f614bfa4b5a89657215f46e293858f8fb8959d6e2b2fc40ce26ab25c25bfca21deeaed70318d33f734cba5c92870aa42fa70145a11b44329e694f0eada34b2fa7e3f8bf78444bf7c28002f27a23ff40eb2c253';
  
  // size of the random nonce for xor encryption
  const NONCE_SIZE = 12;
  
  // Password length in bytes
  const PWD_SIZE = 16;
  // Length of non-pwd data in bytes (without sigs)
  const DATA_SIZE = 31;
  // Signature size in bytes
  const SIG_SIZE = 4;
  // Number of signatures
  const SIG_COUNT = 4;
  
  const MAX_B64_SIZE = 256;
  
  // Timestamps will be reset if idle time is longer than TTL
  const TTL = 604800; // 7 days
  
  // Action counts can only be incremented once every ACTION_DELAY seconds
  const ACTION_DELAY = 14400; // 4 hours
  
  // If the IP changes before this delay, the ip_change_score will be increased
  const IP_CHANGE_DELAY = 1800; // 30 minutes
  const IP_CHANGE_SCORE_MAX = 32;
  const IP_CHANGE_MASK_VAL = 3;
  const IP_CHANGE_IP_VAL = 1;
  
  const COOKIE_NAME = '4chan_pass';
  
  const COOKIE_TTL = 31536000; // 1 year
  
  const VERSION = 3;
  const VERSION_MIN = 2;
  
  const A_POST = 1;
  const A_IMG = 2;
  const A_THREAD = 4;
  const A_REPORT = 8;
  
  // password (raw)
  private $pwd_raw = null;
  // password (hex)
  private $pwd_hex = null;
  // password creation timestamp
  private $creation_ts = 0;
  // masked IP timestamp
  private $mask_ts = 0;
  // IP timestamp
  private $ip_ts = 0;
  // last activity timestamp
  private $activity_ts = 0;
  // last time action count was incremented
  private $action_ts = 0;
  // last time the environment changed (browser, country, etc)
  private $env_ts = 0;
  
  // Lvel of verification (currently unused)
  private $verified_level = 0;
  
  // Numbers of posts, image posts, threads and reports made (unsigned char)
  private $post_count = 0;
  private $img_count = 0;
  private $thread_count = 0;
  private $report_count = 0;
  
  private $action_buffer = 0;
  
  // If a an IP changes too soon, the score increases.
  // IP changes increase the value by 2
  // Mask changes increase the value by 4
  // Stable activity reduces the score by 1
  private $ip_change_score = 0; // unsigned char, 0-32
  
  // hmac hash for pwd: pwd + creation_ts + activity_ts + action_ts + counts + domain
  private $pwd_sig = null;
  // hmac hash for masked IP: pwd + mask_ts + masked_ip + domain
  private $mask_sig = null;
  // hmac hash for IP: pwd + ip_ts + ip + domain
  private $ip_sig = null;
  // hmac hash for environment: pwd + env_ts + env + domain
  private $env_sig = null;
  
  private $env_data = null;
  
  private $ip = null;
  private $domain = null;
  
  private $now = 0;
  
  public $errno = 0;
  
  private $version = 0;
  
  private static $session_instance = null;
  
  const
    E_CORRUPT_LEN = 1,
    E_CORRUPT_DEC = 2,
    E_ENC = 11,
    E_EXPIRED = 12,
    E_PWDSIG = 13,
    E_MASKSIG = 14,
    E_IPSIG = 15,
    E_ENVSIG = 16,
    E_VERSION = 99
  ;
  
  // Extract and return the hex pwd from a base64 string
  public static function decodePwd($b64_data) {
    if (!$b64_data || strlen($b64_data) > self::MAX_B64_SIZE) {
      return null;
    }
    
    $bin_data = self::b64_decode($b64_data);
    
    if (!$bin_data) {
      return null;
    }
    
    $version = unpack('C', $bin_data)[1];
    
    if (strlen($bin_data) < self::PWD_SIZE + 1) {
      return null;
    }
    
    $nonce = substr($bin_data, 1, self::NONCE_SIZE);
    $bin_data = substr($bin_data, 1 + self::NONCE_SIZE);
    $bin_data = self::decrypt($bin_data, $nonce);
    $pwd = substr($bin_data, 0, self::PWD_SIZE);
    
    if (!$pwd || strlen($pwd) !== self::PWD_SIZE) {
      return false;
    }
    
    return bin2hex($pwd);
  }
  
  public function version() {
    return $this->version;
  }
  
  public static function getSession() {
    return self::$session_instance;
  }
  
  public static function clearSession() {
    self::$session_instance = null;
  }
  
  private static function b64_encode($data) {
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
  }
  
  private static function b64_decode($data) {
    return base64_decode(strtr($data, '-_', '+/'));
  }
  
  // $b64_data is the url-safe version, see b64_encode method.
  public function __construct($ip, $domain, $b64_data = null, $start_session = true) {
    $this->now = time();
    
    if ($start_session) {
      self::$session_instance = $this;
    }
    
    $this->ip = $ip;
    $this->domain = $domain;
    
    $this->env_data = $this->collect_env_data();
    
    if ($b64_data === null) {
      $this->generate();
      return;
    }
    
    if (strlen($b64_data) > self::MAX_B64_SIZE) {
      $this->errno = self::E_CORRUPT_LEN;
      $this->generate();
      return;
    }
    
    $bin_data = self::b64_decode($b64_data);
    
    if (!$bin_data) {
      $this->errno = self::E_CORRUPT_DEC;
      $this->generate();
      return;
    }
    
    $version = unpack('C', $bin_data)[1];
    
    $this->version = $version;
    
    // Version check
    if ($version > self::VERSION || $version < self::VERSION_MIN) {
      $this->errno = self::E_VERSION;
      $this->generate();
      return;
    }
    
    $nonce = substr($bin_data, 1, self::NONCE_SIZE);
    $bin_data = substr($bin_data, 1 + self::NONCE_SIZE);
    $bin_data = self::decrypt($bin_data, $nonce);
    
    if (!$bin_data) {
      $this->errno = self::E_ENC;
      $this->generate();
      return;
    }
    
    // FIXME: Version 2
    if ($version === 2) {
      $_data_size = self::DATA_SIZE - 1 - 4 - 1;
      
      $full_size = self::PWD_SIZE + $_data_size + (self::SIG_SIZE * (self::SIG_COUNT - 1));
      
      if (strlen($bin_data) !== $full_size) {
        $this->errno = self::E_CORRUPT_LEN2;
        $this->generate();
        return;
      }
      
      $pwd_raw = substr($bin_data, 0, self::PWD_SIZE);
      
      list($creation_ts, $mask_ts, $ip_ts, $activity_ts, $action_ts,
            $post_count, $img_count, $thread_count, $report_count, $ip_change_score)
          = array_values(unpack('V5t/C5a', substr($bin_data, self::PWD_SIZE, $_data_size)));
      
      $env_ts = $this->now;
      $verified_level = 0;
      $action_buffer = 0;
      
      $sig_start = self::PWD_SIZE + $_data_size;
      
      $pwd_sig = substr($bin_data, $sig_start, self::SIG_SIZE);
      $mask_sig = substr($bin_data, $sig_start + self::SIG_SIZE, self::SIG_SIZE);
      $ip_sig = substr($bin_data, $sig_start + self::SIG_SIZE * 2, self::SIG_SIZE);
      $env_sig = null;
      
      // Password signature
      $valid_pwd_sig = $this->calc_sig(
        [
          $pwd_raw, $creation_ts, $activity_ts, $action_ts,
          $post_count, $img_count, $thread_count, $report_count, $ip_change_score,
          $domain
        ]
      );
    }
    // Current version
    else {
      $full_size = self::PWD_SIZE + self::DATA_SIZE + (self::SIG_SIZE * self::SIG_COUNT);
      
      if (strlen($bin_data) !== $full_size) {
        $this->errno = self::E_CORRUPT_LEN;
        $this->generate();
        return;
      }
      
      $pwd_raw = substr($bin_data, 0, self::PWD_SIZE);
      list($creation_ts, $mask_ts, $ip_ts, $activity_ts, $action_ts, $env_ts, $verified_level,
          $post_count, $img_count, $thread_count, $report_count, $action_buffer, $ip_change_score)
        = array_values(unpack('V6t/C7a', substr($bin_data, self::PWD_SIZE, self::DATA_SIZE)));
      
      $sig_start = self::PWD_SIZE + self::DATA_SIZE;
      
      $pwd_sig = substr($bin_data, $sig_start, self::SIG_SIZE);
      $mask_sig = substr($bin_data, $sig_start + self::SIG_SIZE, self::SIG_SIZE);
      $ip_sig = substr($bin_data, $sig_start + self::SIG_SIZE * 2, self::SIG_SIZE);
      $env_sig = substr($bin_data, $sig_start + self::SIG_SIZE * 3, self::SIG_SIZE);
      
      // Password signature
      $valid_pwd_sig = $this->calc_sig(
        [
          $pwd_raw, $creation_ts, $activity_ts, $action_ts, $env_ts, $verified_level,
          $post_count, $img_count, $thread_count, $report_count, $action_buffer, $ip_change_score,
          $domain
        ]
      );
    }
    
    if ($valid_pwd_sig && $valid_pwd_sig === $pwd_sig) {
      $this->pwd_raw = $pwd_raw;
      
      $this->pwd_hex = bin2hex($pwd_raw);
      
      if ($activity_ts > 0) {
        $_act_ts = $activity_ts;
      }
      else {
        $_act_ts = $creation_ts;
      }
      
      if ($this->now - $_act_ts >= self::TTL) {
        $this->errno = self::E_EXPIRED;
        $this->resetTimestamps();
        return;
      }
      else {
        $this->creation_ts = $creation_ts;
        $this->activity_ts = $activity_ts;
        $this->action_ts = $action_ts;
        $this->env_ts = $env_ts;
        
        // FIXME: Version 2
        if ($version !== 2) {
          $this->pwd_sig = $valid_pwd_sig;
        }
        
        $this->verified_level = $verified_level;
        
        $this->post_count = $post_count;
        $this->img_count = $img_count;
        $this->thread_count = $thread_count;
        $this->report_count = $report_count;
        $this->action_buffer = $action_buffer;
        
        $this->ip_change_score = $ip_change_score;
      }
    }
    else {
      $this->errno = self::E_PWDSIG;
      $this->generate();
      return;
    }
    
    // Environment signature
    $valid_env_sig = $this->calc_sig([ $pwd_raw, $env_ts, $this->env_data, $domain ]);
    
    if ($valid_env_sig && $valid_env_sig === $env_sig) {
      $this->env_ts = $env_ts;
      $this->env_sig = $valid_env_sig;
    }
    else {
      $this->errno = self::E_ENVSIG;
      $this->env_ts = $this->now;
      $this->pwd_sig = null; // FIXME, env_ts shouldn't be used in the pwd_sig
    }
    
    // Masked IP signature
    $valid_mask_sig = $this->calc_sig([ $pwd_raw, $mask_ts, $this->get_ip_mask($ip), $domain ]);
    
    if ($valid_mask_sig && $valid_mask_sig === $mask_sig) {
      $this->mask_ts = $mask_ts;
      $this->mask_sig = $valid_mask_sig;
    }
    else {
      $this->mask_ts = $this->now;
      $this->ip_ts = $this->now;
      
      $this->errno = self::E_MASKSIG;
      
      return; // bail out
    }
    
    // IP signature
    $valid_ip_sig = $this->calc_sig([ $pwd_raw, $ip_ts, $ip, $domain ]);
    
    if ($valid_ip_sig && $valid_ip_sig === $ip_sig) {
      $this->ip_ts = $ip_ts;
      $this->ip_sig = $valid_ip_sig;
    }
    else {
      $this->errno = self::E_IPSIG;
      $this->ip_ts = $this->now;
    }
  }
  
  private function get_ip_mask($ip) {
    $ip_parts = explode('.', $ip, 3);
    return "{$ip_parts[0]}.{$ip_parts[1]}";
  }
  
  private function collect_env_data() {
    if (!isset($_SERVER)) {
      return 'noenv';
    }
    
    // Country
    if (isset($_SERVER['HTTP_X_GEO_COUNTRY'])) {
      $data = $_SERVER['HTTP_X_GEO_COUNTRY'];
    }
    else {
      $data = 'XX';
    }
    
    return $data;
  }
  
  private function calc_sig($arg_array) {
    return substr(hash_hmac('sha1', implode(' ', $arg_array), UserPwd::HMAC_SECRET, true), 0, self::SIG_SIZE);
  }
  
  public function getPwd() {
    return $this->pwd_hex;
  }
  
  public function pwdLifetime() {
    if ($this->creation_ts) {
      return $this->now - $this->creation_ts;
    }
    else {
      return 0;
    }
  }
  
  public function maskLifetime() {
    if ($this->mask_ts) {
      return $this->now - $this->mask_ts;
    }
    else {
      return 0;
    }
  }
  
  public function ipLifetime() {
    if ($this->ip_ts) {
      return $this->now - $this->ip_ts;
    }
    else {
      return 0;
    }
  }
  
  public function envLifetime() {
    if ($this->env_ts) {
      return $this->now - $this->env_ts;
    }
    else {
      return 0;
    }
  }
  
  public function creationTs() {
    return $this->creation_ts;
  }
  
  public function ipTs() {
    return $this->ip_ts;
  }
  
  public function maskTs() {
    return $this->mask_ts;
  }
  
  public function idleLifetime() {
    if ($this->activity_ts) {
      return $this->now - $this->activity_ts;
    }
    else {
      return $this->creation_ts;
    }
  }
  
  public function lastActionLifetime() {
    if ($this->action_ts) {
      return $this->now - $this->action_ts;
    }
    else {
      return 0;
    }
  }
  
  public function verifiedLevel() {
    return $this->verified_level;
  }
  
  public function maskChanged() {
    return !$this->isNew() && $this->mask_ts === $this->now;
  }
  
  public function ipChanged() {
    return !$this->isNew() && $this->ip_ts === $this->now;
  }
  
  public function envChanged() {
    return !$this->isNew() && $this->env_ts === $this->now;
  }
  
  public function isUserKnown($for_minutes = 1440, $since_ts = 0) {
    // If the IP changes too often, enforce an IP lifetime of IP_CHANGE_DELAY
    if ($this->ipChangeScore() > self::IP_CHANGE_MASK_VAL * 3) {
      if ($this->maskLifetime() < self::IP_CHANGE_DELAY) {
        return false;
      }
    }
    
    // Mask is older than the required lifetime
    if ($this->maskLifetime() >= $for_minutes * 60) {
      return true;
    }
    
    // Mask was created before the reference time
    // ex: user was already posting when a new lenient rangeban was created
    if ($since_ts > 0 && $this->mask_ts <= $since_ts) {
      if ($this->postCount() > 0 || $this->reportCount() > 5) {
        return true;
      }
    }
    
    // Password isn't old enough
    if ($this->pwdLifetime() < $for_minutes * 60) {
      return false;
    }
    
    // Password is old enough
    
    // For lenient rangebans, this is enough
    if ($since_ts > 0) {
      return true;
    }
    
    // Otherwise, do some more checks
    
    // User has enough activity
    if ($this->postCount() >= 3 || $this->reportCount() >= 10) {
      // Check UA + country
      //if ($this->envLifetime() >= self::IP_CHANGE_DELAY) {
      //  return true;
      //}
      // Check the mask lifetime
      if ($this->maskLifetime() >= self::IP_CHANGE_DELAY) {
        return true;
      }
      // Otherwise do a more strict activity check
      if ($this->postCount() >= 9 || $this->reportCount() >= 20) {
        return true;
      }
    }
    
    // All checks failed
    return false;
  }
  
  public function isUserKnownOrVerified($for_minutes = 1440, $since_ts = 0) {
    if ($this->verifiedLevel()) {
      return true;
    }
    
    return $this->isUserKnown($for_minutes, $since_ts);
  }
  
  public function updatePostActivity($is_thread, $has_file, $is_dummy = false) {
    $actions = self::A_POST;
    
    if ($is_thread) {
      $actions = $actions | self::A_THREAD;
    }
    
    if ($has_file) {
      $actions = $actions | self::A_IMG;
    }
    
    $this->updateActivity($actions, $is_dummy);
  }
  
  public function updateReportActivity($is_dummy = false) {
    $this->updateActivity(self::A_REPORT, $is_dummy);
  }
  
  public function updateActivity($kind, $is_dummy = false) {
    $this->action_buffer = $this->action_buffer | $kind;
    
    $ip_change_delta = -1;
    
    if ($this->idleLifetime() < self::IP_CHANGE_DELAY) {
      if ($this->maskChanged()) {
        $ip_change_delta = self::IP_CHANGE_MASK_VAL;
      }
      else if ($this->ipChanged()) {
        $ip_change_delta = self::IP_CHANGE_IP_VAL;
      }
    }
    
    $this->ip_change_score = min(max(0, $this->ip_change_score + $ip_change_delta), self::IP_CHANGE_SCORE_MAX);
    
    if ($this->ip_change_score >= self::IP_CHANGE_SCORE_MAX) {
      $this->resetActionCounts();
    }
    
    if ($this->action_ts === 0) {
      $this->action_ts = $this->now;
    }
    else if (!$is_dummy && $this->lastActionLifetime() >= self::ACTION_DELAY) {
      if ($this->action_buffer & self::A_REPORT) {
        $this->report_count = min($this->report_count + 1, 0xFF);
      }
      
      if ($this->action_buffer & self::A_POST) {
        $this->post_count = min($this->post_count + 1, 0xFF);
      }
      
      if ($this->action_buffer & self::A_IMG) {
        $this->img_count = min($this->img_count + 1, 0xFF);
      }
      
      if ($this->action_buffer & self::A_THREAD) {
        $this->thread_count = min($this->thread_count + 1, 0xFF);
      }
      
      $this->action_buffer = 0;
      
      $this->action_ts = $this->now;
    }
    
    $this->activity_ts = $this->now;
    
    $this->pwd_sig = null;
  }
  
  public function postCount() {
    return $this->post_count + ($this->action_buffer & self::A_POST ? 1 : 0);
  }
  
  public function imgCount() {
    return $this->img_count + ($this->action_buffer & self::A_IMG ? 1 : 0);
  }
  
  public function threadCount() {
    return $this->thread_count + ($this->action_buffer & self::A_THREAD ? 1 : 0);
  }
  
  public function reportCount() {
    return $this->report_count + ($this->action_buffer & self::A_REPORT ? 1 : 0);
  }
  
  public function ipChangeScore() {
    return $this->ip_change_score;
  }
  
  // Never used
  public function isNeverUsed() {
    return $this->activity_ts === 0;
  }
  
  // Used only once
  public function isUsedOnlyOnce() {
    return $this->activity_ts === $this->creation_ts;
  }
  
  // Just created
  public function isNew() {
    return $this->creation_ts === $this->now;
  }
  
  // Fake or spoofed
  public function isFake() {
    return $this->errno === self::E_PWDSIG;
  }
  
  public function getEncodedData() {
    if (!$this->domain || !$this->ip) {
      return false;
    }
    
    $data = [];
    
    // Raw password
    if ($this->pwd_raw) {
      $data[] = $this->pwd_raw;
    }
    else {
      return false;
    }
    
    // Creation timestamp
    if ($this->creation_ts > 0) {
      $data[] = pack('V', $this->creation_ts);
    }
    else {
      return false;
    }
    
    // Mask timestamp
    if ($this->mask_ts > 0) {
      $data[] = pack('V', $this->mask_ts);
    }
    else {
      return false;
    }
    
    // IP timestamp
    if ($this->ip_ts > 0) {
      $data[] = pack('V', $this->ip_ts);
    }
    else {
      return false;
    }
    
    // Last ativity timestamp
    if ($this->activity_ts < 0) {
      return false;
    }
    
    $data[] = pack('V', $this->activity_ts);
    
    // Last action increment timestamp
    if ($this->action_ts < 0) {
      return false;
    }
    
    $data[] = pack('V', $this->action_ts);
    
    // Env timestamp
    if ($this->env_ts > 0) {
      $data[] = pack('V', $this->env_ts);
    }
    else {
      return false;
    }
    
    // Verified level
    if ($this->verified_level < 0) {
      return false;
    }
    
    $data[] = pack('C', $this->verified_level);
    
    // Action counts
    $data[] = pack('C5', $this->post_count, $this->img_count, $this->thread_count, $this->report_count, $this->action_buffer);
    
    // IP change score
    $data[] = pack('C', $this->ip_change_score);
    
    // Password signature
    if ($this->pwd_sig) {
      $data[] = $this->pwd_sig;
    }
    else {
      $data[] = $this->calc_sig([
        $this->pwd_raw, $this->creation_ts, $this->activity_ts, $this->action_ts, $this->env_ts, $this->verified_level,
        $this->post_count, $this->img_count, $this->thread_count, $this->report_count, $this->action_buffer, $this->ip_change_score,
        $this->domain
      ]);
    }
    
    // Mask signature
    if ($this->mask_sig) {
      $data[] = $this->mask_sig;
    }
    else {
      $data[] = $this->calc_sig([ $this->pwd_raw, $this->mask_ts, $this->get_ip_mask($this->ip), $this->domain ]);
    }
    
    // IP signature
    if ($this->ip_sig) {
      $data[] = $this->ip_sig;
    }
    else {
      $data[] = $this->calc_sig([ $this->pwd_raw, $this->ip_ts, $this->ip, $this->domain ]);
    }
    
    // Env signature
    if ($this->env_sig) {
      $data[] = $this->env_sig;
    }
    else {
      $data[] = $this->calc_sig([ $this->pwd_raw, $this->env_ts, $this->env_data, $this->domain ]);
    }
    
    // ---
    
    $data = implode('', $data);
    
    list($data, $nonce) = self::encrypt($data);
    
    if (!$data) {
      return false;
    }
    
    // Version + Nonce
    $data = pack('C', self::VERSION) . $nonce . $data;
    
    return self::b64_encode($data);
  }
  
  private static function encrypt($data) {
    $data_len = strlen($data);
    
    $key = hex2bin(self::XOR_KEY);
    $nonce = openssl_random_pseudo_bytes(self::NONCE_SIZE);
    
    if (!$data_len || !$nonce || $data_len > strlen($key)) {
      return false;
    }
    
    $output_nonced = '';
    
    // Apply nonce
    $ni = 0;
    
    for ($di = 0; $di < $data_len; ++$di) {
      if ($ni >= self::NONCE_SIZE) {
        $ni = 0;
      }
      
      $output_nonced = $output_nonced . ($data[$di] ^ $nonce[$ni]);
      
      $ni++;
    }
    
    $output = '';
    
    // XOR Encrypt
    for ($i = 0; $i < $data_len; ++$i) {
      $output = $output . ($output_nonced[$i] ^ $key[$i]);
    }
    
    return [ $output, $nonce ];
  }
  
  private static function decrypt($data, $nonce) {
    $data_len = strlen($data);
    
    $nonce_len = strlen($nonce);
    
    $key = hex2bin(self::XOR_KEY);
    
    if (!$data_len || !$nonce || $data_len > strlen($key)) {
      return false;
    }
    
    $output_nonced = '';
    
    // XOR Decrypt
    for ($i = 0; $i < $data_len; ++$i) {
      $output_nonced = $output_nonced . ($data[$i] ^ $key[$i]);
    }
    
    // Apply nonce
    $output = '';
    
    $ni = 0;
    
    for ($di = 0; $di < $data_len; ++$di) {
      if ($ni >= $nonce_len) {
        $ni = 0;
      }
      
      $output = $output . ($output_nonced[$di] ^ $nonce[$ni]);
      
      $ni++;
    }
    
    return $output;
  }
  
  private function generate() {
    if (!$this->ip || !$this->domain) {
      return false;
    }
    
    $pwd_raw = openssl_random_pseudo_bytes(self::PWD_SIZE);
    
    if (!$pwd_raw) {
      return false;
    }
    
    $this->version = self::VERSION;
    
    $this->pwd_raw = $pwd_raw;
    $this->pwd_hex = bin2hex($pwd_raw);
    $this->creation_ts = $this->now;
    $this->mask_ts = $this->now;
    $this->ip_ts = $this->now;
    $this->env_ts = $this->now;
    
    return true;
  }
  
  public function setPwd($pwd_hex) {
    if (!$pwd_hex) {
      return false;
    }
    
    $pwd_raw = hex2bin($pwd_hex);
    
    if (!$pwd_raw || strlen($pwd_raw) !== self::PWD_SIZE) {
      return false;
    }
    
    $this->pwd_raw = $pwd_raw;
    $this->pwd_hex = $pwd_hex;
    
    $this->resetSignatures();
    
    return true;
  }
  
  public function setVerifiedLevel($level) {
    if ($level < 0) {
      return false;
    }
    $this->verified_level = $level;
    $this->pwd_sig = null;
  }
  
  private function resetTimestamps() {
    $this->creation_ts = $this->now;
    $this->mask_ts = $this->now;
    $this->ip_ts = $this->now;
    $this->action_ts = $this->now;
    $this->activity_ts = 0;
    $this->env_ts = $this->now;
  }
  
  private function resetActionCounts() {
    $this->post_count = 0;
    $this->img_count = 0;
    $this->thread_count = 0;
    $this->report_count = 0;
    
    $this->action_buffer = 0;
  }
  
  private function resetSignatures() {
    $this->pwd_sig = null;
    $this->mask_sig = null;
    $this->ip_sig = null;
    $this->env_sig = null;
  }
  
  public function setCookie($domain) {
    $data = $this->getEncodedData();
    
    if ($data) {
      return setcookie(self::COOKIE_NAME, $data, $this->now + self::COOKIE_TTL, '/', $domain, true, true);
    }
    else {
      return false;
    }
  }
  
  public static function setFakeCookie($now, $domain) {
    $size = self::NONCE_SIZE + self::PWD_SIZE + self::DATA_SIZE + self::SIG_SIZE * self::SIG_COUNT;
    
    $data = openssl_random_pseudo_bytes($size);
    
    if (!$data) {
      return false;
    }
    
    $data = pack('C', self::VERSION) . $data;
    
    $data = self::b64_encode($data);
    
    return setcookie(self::COOKIE_NAME, $data, $now + self::COOKIE_TTL, '/', $domain, true);
  }
}
