<?php
if (isset($_SERVER['HTTP_REFERER']) && !empty($_SERVER['HTTP_REFERER'])
  && !preg_match('/^https?:\/\/[a-z0-9]+\.(4chan|4channel)\.org/', $_SERVER['HTTP_REFERER'])) {
  http_response_code(403);
  die();
}

if (!isset($_GET['url']) || empty($_GET['url'])) {
  die();
}

$url = $_GET['url'];

$domain = parse_url($url, PHP_URL_HOST);

if (!$domain) {
  die();
}

if (strpos($url, 'http') !== 0) {
  $url = 'http://' . $url;
}

$domain = htmlspecialchars($domain, ENT_QUOTES);
$url = htmlspecialchars(htmlspecialchars_decode($url, ENT_QUOTES), ENT_QUOTES);

header("Cache-Control: public, immutable");

?><!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <title>Redirecting...</title>
  <meta http-equiv="refresh" content="2; URL=<?php echo $url ?>">
  <style type="text/css">
    #msg {
      font-family: Arial, 'Helvetica Neue', Helvetica, sans-serif;
      text-align: center;
      font-weight: bold;
      font-size: 36pt;
      margin-top: 20%;
    }
  </style>
</head>
<body>
<div id="msg">Redirecting you to <i><?php echo $domain ?></i>...</div>
</body>
</html>
