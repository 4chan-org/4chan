<?php
require_once 'lib/db.php';

set_time_limit(0);

ini_set("memory_limit", "-1");

define(SLEEP_TIME, 3);

function send_email($email, $token, $pending_id, $reminder_interval, $expires_on, $isExpired) {
  // Subject / Message
  if ($isExpired) {
    $subject = "Your 4chan Pass has expired!";
    $message =<<<MSG
Your 4chan Pass (Token: $token) has expired.

In order to continue posting without typing a CAPTCHA, you must renew your Pass. Renewing your 
Pass will add 12 additional months from the date of your renewal payment.

You can renew your Pass by visiting the following link: https://www.4chan.org/pass?renew=$pending_id

If you have any questions or problems renewing, please e-mail 4chanpass@4chan.org

Thanks for your support!
MSG;
  }
  else {
    $subject = "Your 4chan Pass is about to expire";
    $message =<<<MSG
Your 4chan Pass (Token: $token) is due to expire in less than $reminder_interval days, on $expires_on.

To avoid any interruption, we recommend renewing your Pass now. Renewing your Pass will add 12 
additional months to your current expiration date.

You can renew your Pass by visiting the following link: https://www.4chan.org/pass?renew=$pending_id

If you have any questions or problems renewing, please e-mail 4chanpass@4chan.org

Thanks for your support!
MSG;
  }
  
  // From:
  $headers = "From: 4chan Pass <4chanpass@4chan.org>\r\n";
  $headers .= "MIME-Version: 1.0\r\n";
  $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
  
  // Envelope
  $opts = '-f 4chanpass@4chan.org';
  
  return mail($email, $subject, $message, $headers, $opts);
}

// Remindre interval in days
$reminder_interval = 7;

echo '### MAILER BEGIN RUN AT ' . date('r') . " ###\n";

$query =<<<SQL
SELECT *,
UNIX_TIMESTAMP(expiration_date) as expiration_timestamp,
DATE_FORMAT(expiration_date, '%m/%d/%y') as expires_on
FROM pass_users
WHERE expiration_date <= DATE_ADD(NOW(), INTERVAL $reminder_interval DAY)
AND (email_expired_sent = 0 OR email_reminder_sent = 0)
AND (status = 0 OR status = 6)
SQL;

$res = mysql_global_call($query);

if (!$res) {
  die('Database error');
}

if (mysql_num_rows($res) < 1) {
  die('Nothing to do');
}

$i = 0;
$expiration_count = 0;
$reminder_count = 0;

while ($row = mysql_fetch_assoc($res)) {
  if (!$row) {
    break;
  }
  
  if ($row['gift_email'] !== '') {
    $owner_email = $row['gift_email'];
  }
  else {
    $owner_email = $row['email'];
  }
  
  if ($row['email_expired_sent'] == '1') {
    echo "Nothing to do for $owner_email (already expired)\n";
    continue;
  }
  
  if ((int)$row['expiration_timestamp'] <= time()) {
    $isExpired = true;
  }
  else if ($row['email_reminder_sent'] != '1') {
    $isExpired = false;
  }
  else {
    echo "Nothing to do for $owner_email\n";
    continue;
  }
  
  $status = send_email($owner_email, $row['user_hash'], $row['pending_id'], $reminder_interval, $row['expires_on'], $isExpired);
  
  if ($status) {
    if ($isExpired) {
      ++$expiration_count;
      echo "Sent expiration notice to $owner_email\n";
      $query = "UPDATE pass_users SET email_expired_sent = 1, status = 1, last_status = status WHERE pending_id = '" . $row['pending_id'] . "' LIMIT 1";
    }
    else {
      ++$reminder_count;
      echo "Sent reminder to $owner_email\n";
      $query = "UPDATE pass_users SET email_reminder_sent = 1 WHERE pending_id = '" . $row['pending_id'] . "' LIMIT 1";
    }
    
    mysql_global_call($query);
  }
  else {
    echo "mail error $owner_mail";
  }
  
  ++$i;
  
  sleep(SLEEP_TIME);
}

echo "---------------------------------\n";
echo "Sent $i email(s): $reminder_count reminder(s), $expiration_count expiration(s)\n";
echo '### MAILER END RUN AT ' . date('r') . " ###\n";
