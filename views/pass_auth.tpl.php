<!DOCTYPE html>
<html<?php if (IS_4CHANNEL) echo(' class="is_channel"'); ?>>
<head>
  <meta charset="utf-8">
  <title>4chan Pass - Authenticate</title>
  <link rel="stylesheet" type="text/css" href="//s.4cdn.org/css/pass_auth.css">
  <link rel="shortcut icon" href="//s.4cdn.org/image/favicon<?php if (IS_4CHANNEL) echo('-ws'); ?>.ico" type="image/x-icon">
</head>
<body>
<header>
  <h1 id="title">4chan Pass</h1>
</header>
<div id="content">
<?php if ($this->auth_status === self::AUTH_NO): ?>
<div id="auth-cnt">
<h3 id="xhr-error" class="msg-error hidden"></h3>
<form id="auth-form" action="" method="POST"><fieldset id="auth-fields">
  <table>
    <tr>
      <th>Token</th>
      <td><input id="field-id" name="id" type="text" required></td>
    </tr>
    <tr>
      <th>PIN</th>
      <td><input id="field-pin" name="pin" type="password" required></td>
    </tr>
    <tfoot>
      <tr class="row-space">
        <td colspan="2"><input id="field-long-login" type="checkbox" name="long_login" value="1"><label for="field-long-login">Remember this device for 1 year</label></td>
      </tr>
      <tr class="row-space">
        <td colspan="2"><button id="auth-btn" data-label="Submit" type="submit">Submit</button></td>
      </tr>
      <tr class="row-sep">
        <td colspan="2"><hr></td>
      </tr>
      <tr>
        <td colspan="2"><p>Forgot your 4chan Pass login details?<br><a href="https://www.4chan.org/pass?reset">Go here</a> to reset your PIN.</p><p>Don't have a 4chan Pass?<br><a href="https://www.4chan.org/pass">Click here</a> to learn more.</p></td>
      </tr>
    </tfoot>
  </table></fieldset>
</form>
</div>
<?php elseif ($this->auth_status === self::AUTH_YES): ?>
<div id="auth-cnt">
  <h3 id="xhr-error" class="msg-error hidden"></h3>
  <h2 class="msg-success">You are authenticated.</h2>
  <form method="POST" id="logout-form"><fieldset id="logout-fields"><button id="logout-btn" data-label="Logout" name="logout" value="1" type="submit">Logout</button></fieldset></form>
</div>
<?php elseif ($this->auth_status === self::AUTH_SUCCESS): ?>
<div id="auth-cnt">
  <h2 class="msg-success">Success! Your device is now authorized.</h2>
</div>
<?php elseif ($this->auth_status === self::AUTH_ERROR): ?>
<div id="auth-cnt">
  <h2 class="msg-error"><?php echo $this->message ? $this->message : 'Something went wrong.' ?></h2>
  <p>[<a href="https://sys.<?php echo THIS_DOMAIN ?>/auth">Return</a>]</p>
</div>
<?php elseif ($this->auth_status === self::AUTH_OUT): ?>
<div id="auth-cnt">
  <h2 class="msg-success">You are now logged out.</h2>
</div>
<?php endif ?>
</div>
<footer></footer>
</body>
</html>
