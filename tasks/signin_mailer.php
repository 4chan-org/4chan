<?php

// Link expiration time. Will be isnerted the email message.
define('LINK_TTL', '24 hours');
// Time after which entries will be deleted even if the email wasn't sent
define('ENTRY_TTL', 3600); // in seconds
// Number of emails to send per run
define('BATCH_SIZE', 100);
// Table name
define('TBL', 'email_signins_queue');

// ---

echo "[" . date('r') . "] - Mailer run started\n";

// Only one mailer instance can run at a time
$run_lock = fopen(sys_get_temp_dir() . '/signin_mailer.lock', 'a');

if (!$run_lock) {
  echo "Couldn't create lock file. Aborting\n";
  exit(-1);
}

if (!flock($run_lock, LOCK_EX | LOCK_NB)) {
  echo "Previous run hasn't finished yet. Aborting\n";
  exit(0);
}

// ---

require_once 'lib/db.php';

set_time_limit(60);

function send_email($email, $token) {
  $ttl = LINK_TTL;
  
  $subject = "Email Verification Request";
  $message =<<<MSG
Hello,

We have received a request to verify this email address for use on 4chan. If you requested this verification, please go to the following URL:

https://sys.4chan.org/signin?action=verify&tkn=$token

This link will expire in $ttl. You can use it multiple times to authorize as many of your devices as needed.

If you did NOT request to verify this email address, do not click on the link.

Sincerely,

Team 4chan.
MSG;
  
  $headers = "From: 4chan <noreply@4chan.org>\r\n";
  $headers .= "MIME-Version: 1.0\r\n";
  $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
  
  $opts = '-f noreply@4chan.org';
  
  return mail($email, $subject, $message, $headers, $opts);
}

// ---

// Cleanup expired entries to not keep plaintext emails for too long.
$tbl = TBL;

$ttl = (int)ENTRY_TTL;

$sql = "DELETE FROM `$tbl` WHERE created_on <= DATE_SUB(NOW(), INTERVAL $ttl SECOND)";

$res = mysql_global_call($sql);

if (!$res) {
  echo "DB error while pruning stale entries. Aborting\n";
  exit(-1);
}

// Start sending mails
$batch_size = (int)BATCH_SIZE;

$sql = "SELECT id, email, token FROM `$tbl` ORDER BY id ASC LIMIT $batch_size";

$res = mysql_global_call($sql);

if (!$res) {
  echo "DB error while fetching entries. Aborting\n";
  exit(-1);
}

$sent_count = 0;
$error_count = 0;

while ($row = mysql_fetch_assoc($res)) {
  $id = (int)$row['id'];
  $email = $row['email'];
  $token = $row['token'];
  
  if (!$email || !$token) {
    $error_count++;
    continue;
  }
  
  $ret = send_email($email, $token);
  
  if ($ret) {
    $sent_count++;
    $sql = "DELETE FROM `$tbl` WHERE id = $id LIMIT 1";
    mysql_global_call($sql);
  }
  else {
    $error_count++;
  }
  
  //usleep(10000); // 10ms
}

// ---

fclose($run_lock);

echo "[" . date('r') . "] - Mailer run finished: sent $sent_count, errors $error_count\n";

exit(0);
