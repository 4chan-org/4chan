<?php
// haha i named this file catalog.php now everyone but me will find it
// awkward to type
// nvm moot is a nerd
function generate_catalog()
{
  global $log;

  if( !STATIC_REBUILD ) log_cache();
  $catalogjson = array();
  
  $i = 0;
  
  foreach( $log['THREADS'] as $thread ) {
    catalog_thread($log[$thread], $catalogjson, $i);
    ++$i;
  }
  
  $catalogjson = array(
    'threads'  => $catalogjson,
    
    'count'    => count( $log['THREADS'] ),
    'slug'     => BOARD_DIR,
    'anon'     => S_ANONAME,
    'mtime'    => time(),
    'pagesize' => DEF_PAGES
  );
  
  if (!REPLIES_SHOWN && IS_REBUILDD) {
    $catalogjson['no_lr'] = true;
  }
  
  if (SHOW_COUNTRY_FLAGS) {
    $catalogjson['flags'] = true;
  }
  
  if( SPOILERS ) $catalogjson['custom_spoiler'] = (int)SPOILER_NUM;

  $catalogjson = json_encode( $catalogjson );

  $catalog = catalog( $catalogjson );

  print_page( INDEX_DIR . 'catalog.html', $catalog );

  return true;
}

function catalog_thread($res, &$json, $pos)
{
  global $log;

  $reps = $res['replycount'];
  $sub  = $res['sub'];

  $imgs = $res['imgreplycount'];
  $last_reply_id = null;
  $capcodelist = array();
  
  if (TEXT_ONLY) {
    $time_prop = 'now';
  }
  else {
    $time_prop = 'time';
  }
  
  foreach( $res['children'] as $reply => $unused ) {
    $last_reply_id = $reply;
    
    if( META_BOARD && $log[$reply]['capcode'] != 'none' ) {
      $tCapcode = $log[$reply]['capcode'];
      if( $tCapcode == 'admin_highlight' ) $tCapcode = 'admin';

      if( $tCapcode != 'none' ) {
        $capcodelist[$tCapcode] = 1;
      }
    }
  }
  
  if ($last_reply_id === null) {
    $last_reply = array( 'id' => $res['no'] );
  }
  else {
    $lr_data = $log[$last_reply_id];
    
    $last_reply = array(
      'id'    => $last_reply_id,
      'date'  => $lr_data['time']
    );
    
    if( $lr_data['capcode'] != 'none' ) $last_reply['capcode'] = $lr_data['capcode'];
    
    $force_anon = ( ( FORCED_ANON || META_BOARD ) && $lr_data['capcode'] != 'admin' && $lr_data['capcode'] != 'admin_highlight' );
    
    if( !$force_anon ) {
      if( strpos( $lr_data['name'], '</span> <span class="postertrip">' ) !== false ) {
        list( $last_reply['author'], $last_reply['trip'] ) = explode( '</span> <span class="postertrip">', $lr_data['name'] );
      } else {
        $last_reply['author'] = $lr_data['name'];
      }
    } else {
      $last_reply['author'] = S_ANONAME;
    }
  }

  $json[$res['no']] = array(
    'date' => $res[$time_prop],
    'file' => mb_convert_encoding($res['filename'], 'UTF-8', 'UTF-8') . $res['ext'],
    'r'    => $reps,
    'i'    => $imgs,
    'lr'   => $last_reply,
    'b'    => $pos
  );
  /*
  if( META_BOARD && $capcodelist ) {
    $json[$res['no']]['capcodereps'] = implode(',', array_keys($capcodelist));
  }
  */
  if ($res['capcode'] == 'none') {
    if (SHOW_COUNTRY_FLAGS && (!ENABLE_BOARD_FLAGS || $res['board_flag'] == '')) {
      $json[$res['no']]['country'] = $res['country'];
    }
  }
  
  $com = $res['com'];

  if( strpos( $com, 'class="abbr"' ) !== false ) {
    $com = preg_replace( '#(<br>)+<span class="abbr">(.+)$#s', '', $com );
  }
  
  if (!TEXT_ONLY) {
    $com = preg_replace( '#(<br>)+#', ' ', $com );
  }
  else {
    $com = preg_replace( '#(<br>)+#', "\n", $com );
  }
  
  if (BOARD_DIR === 'b') { // fixme, hardcoded for now
    $com = truncate_comment($com, 300, true);
  }
  else {
    if (SJIS_TAGS) {
      $com = preg_replace('/<span class="sjis".+?<\/span>/', '[SJIS]', $com);
    }
    $com = strip_tags($com, '<s>');
  }
  
  $has_spoilers = (bool)SPOILERS;

  if (!$res['permaage'] && !$res['sticky']) {
    if( $reps >= MAX_RES ) $json[$res['no']]['bumplimit'] = 1;
    if( $imgs >= MAX_IMGRES ) $json[$res['no']]['imagelimit'] = 1;
  }

  if( $res['sticky'] ) $json[$res['no']]['sticky'] = 1;
  if( $res['closed'] ) $json[$res['no']]['closed'] = 1;

  if( $res['capcode'] != 'none' ) $json[$res['no']]['capcode'] = $res['capcode'];
  
  $force_anon = ( ( FORCED_ANON || META_BOARD ) && $res['capcode'] != 'admin' && $res['capcode'] != 'admin_highlight' );
  
  if( !$force_anon ) {
    if( strpos( $res['name'], '</span> <span class="postertrip">' ) !== false ) {
      list( $json[$res['no']]['author'], $json[$res['no']]['trip'] ) = explode( '</span> <span class="postertrip">', $res['name'] );
    } else {
      $json[$res['no']]['author'] = $res['name'];
    }
  } else {
    $json[$res['no']]['author'] = S_ANONAME;
  }

  if( $res['fsize'] != 0 && $res['filedeleted'] != 1 ) {
    $json[$res['no']]['imgurl'] = $res['tim'];
    $json[$res['no']]['tn_w'] = $res['tn_w'];
    $json[$res['no']]['tn_h'] = $res['tn_h'];
  }

  if( $res['filedeleted'] == 1 ) $json[$res['no']]['imgdel'] = true;

  if( strpos( $res['sub'], 'SPOILER<>' ) !== false ) {
    $json[$res['no']]['imgspoiler'] = true;
    $sub                            = substr( $res['sub'], 9 );
  }

  $json[$res['no']]['sub'] = $sub;
  $json[$res['no']]['teaser'] = $com;
}

function catalog($catjson) {
  $nav  = file_get_contents_cached( NAV_TXT );
  $foot = file_get_contents_cached( NAV2_TXT );
  
  $nav  = preg_replace( '/href="(\/\/boards.(?:4chan|4channel).org)?\/([a-z0-9]+)\/"/', 'href="$1/$2/catalog"', $nav );
  $nav  = preg_replace( '/href="(\/\/boards.(?:4chan|4channel).org)?\/f\/catalog"/', 'href="$1/f/"', $nav );
  
  $title = strip_tags( TITLE );
  
  $js = '';

  // danbo ads start
  if (defined('ADS_DANBO') && ADS_DANBO) {
    $js .= '<script>';
    
    if (DEFAULT_BURICHAN) {
      $js .= "var danbo_rating = '__SFW__';";
    }
    else {
      $js .= "var danbo_rating = '__NSFW__';";
    }
    
    $_danbo_fallbacks = [];
    
    if (defined('AD_BIDGEAR_TOP') && AD_BIDGEAR_TOP)  {
      $_danbo_fallbacks['t_bg'] = AD_BIDGEAR_TOP;
    }
    else {
      if (defined('AD_ABC_TOP_DESKTOP') && AD_ABC_TOP_DESKTOP)  {
        $_danbo_fallbacks['t_abc_d'] = AD_ABC_TOP_DESKTOP;
      }
      
      if (defined('AD_ABC_TOP_MOBILE') && AD_ABC_TOP_MOBILE)  {
        $_danbo_fallbacks['t_abc_m'] = AD_ABC_TOP_MOBILE;
      }
    }
    
    if (defined('AD_BIDGEAR_BOTTOM') && AD_BIDGEAR_BOTTOM)  {
      $_danbo_fallbacks['b_bg'] = AD_BIDGEAR_BOTTOM;
    }
    else if (defined('AD_ABC_BOTTOM_MOBILE') && AD_ABC_BOTTOM_MOBILE)  {
      $_danbo_fallbacks['b_abc_m'] = AD_ABC_BOTTOM_MOBILE;
    }
    
    if (!$_danbo_fallbacks) {
      $_danbo_fallbacks = 'null';
    }
    else {
      $_danbo_fallbacks = json_encode($_danbo_fallbacks);
    }
    
    $js .= 'var danbo_fb = ' . $_danbo_fallbacks . ';';
    
    $js .= '</script>';
    
    $js .= '<script src="https://static.danbo.org/publisher/q2g345hq2g534-4chan/js/preload.4chan.js" defer></script>';
  }
  // danbo ads end
  
  // PubFuture
  if (DEFAULT_BURICHAN) {
    $js .= '<script async data-cfasync="false" src="https://cdn.pubfuture-ad.com/v2/unit/pt.js"></script>';
  }
  
  if (TEST_BOARD) {
    // Main catalog JS
    $js .= '<script type="text/javascript" src="' . STATIC_SERVER . 'js/test/catalog-8psvqAqszI.' . JS_VERSION_TEST . '.js"></script>';
    
    // Painter JS + CSS
    if (ENABLE_PAINTERJS) {
      $js .= '<script type="text/javascript" src="' . STATIC_SERVER . 'js/test/tegaki-8psvqAqszI.' . JS_VERSION_TEST . '.js"></script>'
        . '<link rel="stylesheet" href="' . STATIC_SERVER . 'css/tegaki-8psvqAqszI.' . CSS_VERSION_TEST . '.css">';
    }
    
    // Core JS
    $js .= '<script type="text/javascript" src="' . STATIC_SERVER . 'js/test/core-8psvqAqszI.' . JS_VERSION_TEST . '.js"></script>';
  }
  else {
    // Main catalog JS
    $js .= '<script type="text/javascript" src="' . STATIC_SERVER . 'js/catalog.min.' . JS_VERSION_CATALOG . '.js"></script>';
    
    // Painter JS + CSS
    if (ENABLE_PAINTERJS) {
      $js .= '<script type="text/javascript" src="' . STATIC_SERVER . 'js/tegaki.min.' . JS_VERSION_PAINTER . '.js"></script>'
        . '<link rel="stylesheet" href="' . STATIC_SERVER . 'css/tegaki.' . CSS_VERSION_PAINTER . '.css">';
    }
    
    // Core JS
    $js .= '<script type="text/javascript" src="' . STATIC_SERVER . 'js/core.min.' . JS_VERSION_CORE . '.js"></script>';
  }
  
  $css         = STATIC_SERVER . 'css';
  $cssv        = TEST_BOARD ? CSS_VERSION_TEST : CSS_VERSION_CATALOG;
  $style_group = style_group();

  $flags = SHOW_COUNTRY_FLAGS ? '<link rel="stylesheet" type="text/css" href="' . $css . '/flags.' . CSS_VERSION_FLAGS . '.css">' : '';

  $titlepart = $subtitle = '';
  
  if( TITLE_IMAGE_TYPE == 1 ) {
    $titleimg = rand_from_flatfile( YOTSUBA_DIR, 'title_banners.txt' );
    $titlepart .= '<div id="bannerCnt" class="title desktop" data-src="' . $titleimg . '"></div>';
  } elseif( TITLE_IMAGE_TYPE == 2 ) {
    $titlepart .= '<img class="title" src="' . TITLEIMG . '" onclick="this.src = this.src;">';
  }

  if( defined( 'SUBTITLE' ) ) {
    $subtitle = '<div class="boardSubtitle">' . SUBTITLE . '</div>';
  }
  
  /**
   * ADS
   */
  $topad = '';
  
  $bottomad = '';
  
  if (defined('AD_CUSTOM_BOTTOM') && AD_CUSTOM_BOTTOM) {
    $bottomad .= '<div>' . AD_CUSTOM_BOTTOM . '<hr></div>';
  }/*
  else if (defined('AD_ABC_BOTTOM_MOBILE') && AD_ABC_BOTTOM_MOBILE) {
    $bottomad .= '<div class="adg-rects mobile"><div class="adg-m adp-250" data-abc="' . AD_ABC_BOTTOM_MOBILE . '"></div><hr></div>';
  }
  else if (defined('AD_BIDGEAR_BOTTOM') && AD_BIDGEAR_BOTTOM)  {
    $bottomad .= '<div class="adc-resp-bg" data-ad-bg="' . AD_BIDGEAR_BOTTOM . '"></div>';
  }*/
  else if (defined('ADS_DANBO') && ADS_DANBO)  {
    $bottomad .= '<div id="danbo-s-b" class="danbo-slot"></div><div class="adl">[<a target="_blank" href="https://www.4channel.org/advertise">Advertise on 4chan</a>]</div><hr>';
  }

  $favicon          = FAVICON;
  $meta_robots      = META_ROBOTS;
  $meta_description = META_DESCRIPTION;
  $meta_keywords    = META_KEYWORDS;

  $body_class = explode('_', $style_group);
  $body_class = $body_class[0];
  $body_class .= ' is_catalog board_' . BOARD_DIR;

  $canonical = '<link rel="canonical" href="https://boards.4chan.org/'.BOARD_DIR.'/catalog">';
  
  $embedearly = EMBEDEARLY;
  $embedlate = EMBEDLATE;
  $adembedearly = AD_EMBEDEARLY;
  
  $start_thread = S_FORM_THREAD;
  
  $jsVersion = TEST_BOARD ? JS_VERSION_TEST : JS_VERSION;
  $comlen = MAX_COM_CHARS;
  $maxfs = MAX_KB * 1024;
  $jsCooldowns = json_encode(array(
    'thread' => RENZOKU3,
    'reply' => RENZOKU,
    'image' => RENZOKU2
  ));
  
  if (defined('CSS_EVENT_NAME') && CSS_EVENT_NAME) {
    $event_css_html = '<option value="_special">Special</option>';
    
    // Christmas 2021
    if (CSS_EVENT_NAME === 'tomorrow') {
      $js .= <<<JJS
<script src='//s.4cdn.org/js/snow.js'></script>
<script>
  function fc_tomorrow_init() {
    if (window.matchMedia && window.matchMedia('(min-width: 481px)').matches) {
      fc_spawn_snow(Math.floor(Math.random() * 50) + 50);
    }
  }
  function fc_tomorrow_cleanup() {
    fc_remove_snow();
  }
</script>
<style>
.boardBanner, #delform, .navLinksBot.desktop {
  border-image-slice: 50 0 50 0;
  border-image-width: 40px 0px 0px 0px;
  border-image-outset: 0px 0px 0px 0px;
  border-image-repeat: repeat repeat;
  border-image-source: url('https://s.4cdn.org/image/temp/garland.png');
  border-style: solid;
  padding-top: 50px;
}
</style>
JJS;
    }
    
    $js .= '<script>var css_event = "' . CSS_EVENT_NAME . '";';
    
    if (defined('CSS_EVENT_VERSION')) {
      $_css_event_version = (int)CSS_EVENT_VERSION;
    }
    else {
      $_css_event_version = 1;
    }
    
    $js .= 'var css_event_v = ' . $_css_event_version . ';';
    
    $js .= '</script>';
  }
  else {
    $event_css_html = '';
  }
  
  if (PARTY) {
    $partyHats = 'var partyHats = "' . PARTY_IMAGE . '";';
  }
  else {
    $partyHats = '';
  }
  
  if (ENABLE_ARCHIVE) {
    $archive_link =  ' <span class="btn-wrap"><a href="./archive" class="button">' . S_ARCHIVE . '</a></span>';
  }
  else {
    $archive_link = '';
  }
  
  if (TEXT_ONLY) {
    $text_only = 'var text_only = true;';
    $body_text_css = ' text_only';
    $ctrl_css = ' hidden';
  }
  else {
    $text_only = $body_text_css = '';
    $ctrl_css = '';
  }
  
  $adg_js = 'var _adg = 1;';
  
  $postform = '';
  form( $postform, 0, '', false, true );

  $cat = <<<HTML
<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <title>$title - Catalog - 4chan</title>
  <meta name="robots" content="$meta_robots">
  <meta name="description" content="$meta_description">
  <meta name="keywords" content="$meta_keywords">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  $canonical
  <link id="mobile-css" rel="stylesheet" href="$css/catalog_mobile.$cssv.css" />
  $js
  $flags
  <script type="text/javascript">$partyHats
$text_only
$adg_js
var jsVersion = $jsVersion;
var comlen = $comlen;
var maxFilesize = $maxfs;
var cooldowns = $jsCooldowns;
var catalog = $catjson;
var style_group = "$style_group";
var check_for_block = true;
var fourcat = new FC();
fourcat.applyCSS(null, "$style_group", $cssv);
</script>
  <link rel="shortcut icon" href="$favicon" type="image/x-icon">
$embedearly 
$adembedearly
</head>
<body class="$body_class$body_text_css">
<div id="topnav" class="boardnav">$nav</div>
<div class="boardBanner">
  $titlepart
  <div class="boardTitle">$title</div>
  $subtitle
</div>
<hr class="abovePostForm">
$topad
<div id="togglePostForm" class="mobilebtn mobile"><span class="btn-wrap"><span id="togglePostFormLinkMobile" class="button">$start_thread</span></span></div>
$postform
<hr>
<div id="content">
<div id="ctrl">
  <div id="info">
    <span class="navLinks mobilebtn"><span class="btn-wrap"><a href="./" class="button">Return</a></span>$archive_link <span id="tobottom" class="btn-wrap"><span class="button">Bottom</span></span> <span class="btn-wrap"><a id="refresh-btn" href="./catalog" class="button">Refresh</a></span></span><span id="filtered-label"> &mdash; Filtered threads: <span id="filtered-count"></span></span><span id="hidden-label"> &mdash; Hidden threads: <span id="hidden-count"></span> <span class="btn-wrap"><a id="filters-clear-hidden" href="">Show</a></span></span><span id="search-label"> &mdash; Search results for: <span id="search-term"></span></span>
  </div>
  <hr class="mobile">
  <div id="settings" class="mobilebtn">
    <span class="ctrl-wrap">Sort By:
    <select id="order-ctrl" size="1">
      <option value="alt">Bump order</option>
      <option value="absdate">Last reply</option>
      <option value="date">Creation date</option>
      <option value="r">Reply count</option>
    </select></span>
    <span class="ctrl-wrap$ctrl_css">Image Size:
    <select id="size-ctrl" size="1">
      <option value="small">Small</option>
      <option value="large">Large</option>
    </select></span>
    <span class="ctrl-wrap$ctrl_css">Show OP Comment:
    <select id="teaser-ctrl" size="1">
      <option value="off">Off</option>
      <option value="on">On</option>
    </select></span>
    <span class="btn-wrap"><span id="filters-ctrl" class="button">Filters</span></span>
    <span class="btn-wrap"><span id="qf-ctrl" class="button">Search</span></span>
    <span style="display:none" id="qf-cnt">
      <input type="text" id="qf-box" name="qf-box"><span id="qf-clear" class="button">&#x2716;</span>
    </span>
  </div>
  <div class="clear"></div>
</div><hr>
<div id="threads"></div>
<hr>
<span class="navLinks navLinksBottom mobilebtn"><span class="btn-wrap"><a href="./" class="button">Return</a></span>$archive_link <span id="totop" class="btn-wrap"><span class="button">Top</span></span> <span class="btn-wrap"><a href="./catalog" class="button">Refresh</a></span></span><span id="filtered-label-bottom"> &mdash; Filtered threads: <span id="filtered-count-bottom"></span></span><span id="hidden-label-bottom"> &mdash; Hidden threads: <span id="hidden-count-bottom"></span> <span class="btn-wrap"><a id="filters-clear-hidden-bottom" href="">Show</a></span></span><span id="search-label-bottom"> &mdash; Search results for: <span id="search-term-bottom"></span></span>
<hr>
$bottomad
<div id="styleSwitcher">Style: <select id="styleSelector" size="1">
  <option value="Yotsuba New">Yotsuba</option>
  <option value="Yotsuba B New">Yotsuba‌ B</option>
  <option value="Futaba New">Futaba</option>
  <option value="Burichan New">Burichan</option>
  <option value="Tomorrow">Tomorrow</option>
  <option value="Photon">Photon</option>$event_css_html
</select></div>
</div>
$foot
<div id="backdrop" class="hidden"></div>
<noscript>
  <style scoped type="text/css">
    #nojs {
      background-color: #000;
      text-align: center;
      position: fixed;
      top: 0px;
      left: 0px;
      width: 100%;
      height: 100%;
    }
    #nojs > span {
      color: #000;
      background-color: #8C92AC;
      padding: 5px;
      position: relative;
      top: 35%;
      font-size: 22px;
    }
  </style>
  <div id="nojs">
    <span>Your web browser must have JavaScript enabled in order for this site to display correctly.</span>
  </div>
</noscript>
<menu type="context" id="ctxmenu-main"></menu>
<menu type="context" id="ctxmenu-thread"></menu>
<script type="text/javascript">
  document.addEventListener('DOMContentLoaded', function() {
    initAnalytics();
    fourcat.init();
    fourcat.loadCatalog(catalog);
    $.on($.id('tobottom'), 'click', function() { window.scrollTo(0, document.documentElement.scrollHeight); });
    $.on($.id('totop'), 'click', function() { window.scrollTo(0, 0); });
  }, false);
</script>
$embedlate</body>
</html>
HTML;

  return $cat;

}
