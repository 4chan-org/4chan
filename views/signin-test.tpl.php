<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <title>4chan - Email Verification</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <meta name="robots" content="noindex">
  <meta http-equiv="Accept-CH" content="Sec-CH-UA-Model">
<?php if ($this->mode === 'index'): ?>
  <?php if (self::CAPTCHA_MODE === 2): ?>
  <script src="https://hcaptcha.com/1/api.js" async defer></script>
  <?php else: ?>
  <script src="https://www.google.com/recaptcha/api.js" async defer></script>
  <?php endif ?>
<?php elseif ($this->mode === 'verify'): ?>
  <?php if ($this->use_recaptcha): ?>
  <script src="https://www.google.com/recaptcha/api.js" async defer></script>
  <?php else: ?>
  <script src="https://s.4cdn.org/js/tcaptcha.js"></script>
  <style type="text/css">
    #t-root {
      background-color: #eee;
      overflow: hidden;
      margin-bottom: 3px;
      box-shadow: 0 0 4px rgba(0, 0, 0, 0.25);
      padding: 4px;
      border-radius: 6px;
    }
  </style>
  <?php endif ?>
<?php endif ?>
  <link rel="stylesheet" type="text/css" href="//s.4cdn.org/css/signin.css">
  <link rel="shortcut icon" href="//s.4cdn.org/image/favicon.ico" type="image/x-icon">
</head>
<body>
<header>
  <img id="logo" alt="4chan" src="//s.4cdn.org/image/fp/minileaf-transparent.png" width="46" height="47">
</header>
<div id="content">
<?php if ($this->mode === 'index'): ?>
<?php if ($this->authed): ?>
<div class="protip"><p>This session is already verified.</p><p>You can clear your verified status by clicking the <i>Logout</i> button below.</p></div>
<form id="logout-form" action="" method="POST">
  <button>Logout</button>
  <input type="hidden" name="<?php echo self::CSRF_ARG ?>" value="<?php echo $this->csrf_token ?>">
  <input type="hidden" name="action" value="signout">
</form>
<?php elseif ($this->pass_user): ?>
<h3 class="msg-success"><?php echo self::ERR_PASS_USER ?></h3>
<?php else: ?>
<h1 id="title">Email Verification</h1>
<div class="protip"><p>A verified email address may be used to bypass<br>anti-spam filters on some boards. If you are having trouble posting, try verifying your email.</p><p>Enter your email below and click <i>Send</i> to receive a verification link.</p><p>Your email address will be stored on our servers only briefly (usually for just a couple of minutes while the verification link is awaiting delivery).</p><p>Email verification is not required for <a href="https://www.4chan.org/pass">4chan Pass</a> users.</p></div>
<form id="auth-form" action="" method="POST">
  <div class="form-line"><div class="g-recaptcha" data-sitekey="<?php echo self::CAPTCHA_MODE === 2 ? HCAPTCHA_API_KEY_PUBLIC : RECAPTCHA_API_KEY_PUBLIC ?>"></div></div>
  <div class="form-line"><label for="email">Email</label><input id="email" <?php if (self::VERIFY_EMAIL_DOMAIN) { echo('pattern="[^@+]+@(' . implode('|', self::$allowed_domains) . ')"'); } ?> name="email" type="email" required><button>Send</button></div>
  <?php if (self::VERIFY_EMAIL_DOMAIN): ?><div class="form-line domain-list"><b>Allowed domains are:</b> <?php echo implode(', ', self::$allowed_domains) ?></div><?php endif; ?>
  <input type="hidden" name="<?php echo self::CSRF_ARG ?>" value="<?php echo $this->csrf_token ?>">
  <input type="hidden" name="action" value="request">
</form>
<?php endif ?>
<?php elseif ($this->mode === 'verify-captcha-failed'): ?>
<h3>You seem to have mistyped the CAPTCHA.</h3>
<h4><a href="?action=verify&amp;tkn=<?php echo htmlspecialchars($this->token) ?>">Click here</a> to try again.</h4>
<?php elseif ($this->mode === 'verify'): ?>
<div class="protip"><p>Please solve the CAPTCHA to finish the verification.</p><p>Make sure cookies are not blocked before continuing.</p></div>
<form id="auth-form" action="<?php echo self::WEB_PATH ?>" method="POST">
  <?php if (!$this->use_recaptcha): ?>
  <div class="form-line"><div id="t-root"></div></div>
  <script>
    TCaptcha.init(document.getElementById('t-root'), '!signin', 1);
    TCaptcha.onReloadClick();
    window.addEventListener('pageshow', (e) => {
      if (e.persisted) {
        TCaptcha.clearChallenge();
      }
    });
  </script>
  <?php else: ?>
  <div class="form-line"><div class="g-recaptcha" data-sitekey="<?php echo RECAPTCHA_API_KEY_PUBLIC ?>"></div></div>
  <?php endif ?>
  <button>Verify</button>
  <input type="hidden" name="<?php echo self::CSRF_ARG ?>" value="<?php echo $this->csrf_token ?>">
  <input type="hidden" name="action" value="verify">
  <input type="hidden" name="tkn" value="<?php echo htmlspecialchars($this->token) ?>">
</form>
<?php elseif ($this->mode === 'verify-done'): ?>
  <h3 class="msg-success">This session is now verified.</h3>
</div>
<?php elseif ($this->mode === 'request'): ?>
  <h3 class="msg-success">An email containing the verification link will be sent out shortly.</h3>
<?php elseif ($this->mode === 'signout'): ?>
  <h3 class="msg-success">Session cleared.</h3>
<?php elseif ($this->mode === 'error'): ?>
  <h3 class="msg-error"><?php echo $this->msg ?></h3>
<?php endif ?>
</div>
<footer></footer>
</body>
</html>
