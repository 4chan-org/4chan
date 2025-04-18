<?php

// where is report_get_style i can't find it anywhere?
function report_get_style_new( $group )
{
	$style = ( $group == 'nws_style' ) ? 'yotsubanew' : 'yotsubluenew';

	return '//s.4cdn.org/css/' . $style . '.' . CSS_VERSION . '.css';
}

function report_head( $no, $success = 0, $altCaptcha = false )
{
	$defaultcss = DEFAULT_BURICHAN ? 'yotsubluenew' : 'yotsubanew';
	
  if (TEST_BOARD) {
    $cssVersion = CSS_VERSION_TEST;
    $core_js = 'test/core-8psvqAqszI.' . JS_VERSION_TEST;
  }
  else {
    $cssVersion =  CSS_VERSION;
    $core_js = 'core.min.' . JS_VERSION_CORE;
  }
  
	$sg         = style_group();

	$styles = array(
		'Yotsuba New'   => "yotsubanew.$cssVersion.css",
		'Yotsuba B New' => "yotsubluenew.$cssVersion.css",
		'Futaba New'    => "futabanew.$cssVersion.css",
		'Burichan New'  => "burichannew.$cssVersion.css",
		'Photon'        => "photon.$cssVersion.css",
		'Tomorrow'      => "tomorrow.$cssVersion.css"
	);
	
	if( !$no ) $no = $_GET['no'];
	
	$no = (int)$no;

	$css = '';

	if( isset( $_COOKIE[$sg] ) ) {
		if( isset( $styles[$_COOKIE[$sg]] ) ) {
			$css = '<link rel="stylesheet" title="switch" href="' . STATIC_SERVER . 'css/' . $styles[$_COOKIE[$sg]] . '">';
		}
	} else {
		$dcssl = $defaultcss . '.' . $cssVersion . '.css';
		$css   = '<link rel="stylesheet" title="switch" href="' . STATIC_SERVER . 'css/' . $dcssl . '">';
	}
	
	?>
<!DOCTYPE html>
<html>
<head>
	<title>Report Post No.<?=$no ? $no : ""?></title>
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
	<meta name="viewport" content="width=device-width,initial-scale=1">
	<?=$css?>
	<style>
	fieldset, #pass, button, .rules { margin-bottom: 10px; }
	fieldset { width: 320px; }
  fieldset legend { font-size: 11pt; font-weight: bold; }
	.pass-msg { font-size: smaller; }
	#cat-sel { width: 280px; }
  .rules { font-size: 10pt; }
  .tw:before {
    border-bottom: 1px solid;
    border-left: 1px solid;
    content: " ";
    display: inline-block;
    height: 8px;
    margin-bottom: 3px;
    margin-right: 3px;
    width: 8px;
  }
  .tw:before {
    margin-left: 5px;
  }
  .tw {
    margin-left: 5px;
    margin-top: 2px;
    margin-bottom: 12px;
  }
	</style>
	<script>var style_group = "<?=style_group()?>";</script>
	<script type="text/javascript" src="<?=STATIC_SERVER?>js/<?php echo $core_js ?>.js"></script>
	<script type="text/javascript">
		function get_cookie(name) {
			var nameEQ = name + "=";
			var ca = document.cookie.split(';');
			for (var i = 0; i < ca.length; i++) {
				var c = ca[i];
				while (c.charAt(0) == ' ') c = c.substring(1, c.length);
				if (c.indexOf(nameEQ) == 0) return c.substring(nameEQ.length, c.length);
			}
			return null;
		}
		
		function onReportKeyDown(e) {
		  if (e.keyCode == 27 && !e.ctrlKey && !e.altKey && !e.shiftKey && !e.metaKey) {
        self.close();
      }
	  }
		
		function onCatChange(e) {
			document.getElementById('cat-sel').disabled = !document.getElementById('cat1').checked;
		}
		
    function onDOMReady(e) {
      var el;
      
      if (el = document.getElementById('cat1')) {
        el.addEventListener('change', onCatChange, false);
        document.getElementById('cat2').addEventListener('change', onCatChange, false);
        
        onCatChange();
      }
      
      
      if (readCookie('pass_enabled') == 1 && (el = document.getElementById('pass'))) {
        el.innerHTML = '<strong>You are using a 4chan Pass.</strong>';
      }

      <?php if( $success ): ?>
      if (window.opener) {
        window.opener.postMessage('done-report-<?=$no?>-<?php echo BOARD_DIR ?>', '*');
      }
      <?php endif; ?>
    }
    
    document.addEventListener('DOMContentLoaded', onDOMReady, false);
    
    document.addEventListener('keydown', onReportKeyDown, false);

		function postBack() {
			if (window.opener) {
				window.opener.postMessage('done-report', '*');
			}

			self.close();
		}
	</script>
</head>
	<?
}

function fancydie($err, $success = 0) {
  report_head( 0, $success );
  
  $needs_back_button = in_array($err, array(S_NOCAPTCHA, S_BADCAPTCHA, S_CAPTCHATIMEOUT));
  
  $err = "<body><h3><font color='#FF0000'>$err</font></h3>";
  
  if ($needs_back_button) {
    $err .= "<br>[<a href=''>Back</a>]</body></html>";
  }
  else {
    if ($success) {
      $err .= "<script language=\"JavaScript\">setTimeout(\"self.close()\", 3000);</script>";
    }
    $err .= "<br>[<a href='javascript:postBack()'>Close</a>]</body></html>";
  }
  
  die($err);
}


function form_report($board, $no, $no_captcha = false) {
  report_head($no, 0);
  $cats = get_report_categories($board, $no, DEFAULT_BURICHAN == 1);
?>
<body>
<form action='' method="POST">
  <fieldset id="reportTypes" style="padding: 3px;">
    <legend>Report type</legend>
    <input type="radio" name="cat" id="cat1" value="" checked> <label for="cat1">This post violates a <a href='//www.<?php echo L::d(BOARD_DIR) ?>/rules#<?php echo $board?>' target="_blank">rule</a>.</label><div class="tw"><select name="cat_id" id="cat-sel"><option value=""></option><?php foreach ($cats['rule'] as $cat_id => $cat): ?>
    <option value="<?php echo $cat_id ?>"><?php echo $cat['title'] ?></option><?php endforeach ?></select></div>
    <input type="radio" name="cat" id="cat2" value="<?php echo $cats['illegal']['id'] ?>"> <label for="cat2"><?php echo $cats['illegal']['title'] ?></label><br/>
  </fieldset>
  <div id="pass">
  <?php
  if (!$no_captcha && !isset($_COOKIE['4chan_auser']) && !isset($_COOKIE['pass_enabled'])) {
    $style_group = style_group();
    
    $dark = isset($_COOKIE[$style_group]) && $_COOKIE[$style_group] === 'Tomorrow';
    
    if (CAPTCHA_TWISTER) {
    	echo twister_captcha_form();
    	echo "<script>document.addEventListener('DOMContentLoaded', function() { TCaptcha.init(document.getElementById('t-root'), '" . BOARD_DIR . "', 1); }, false);</script>";
    }
    else {
    	echo captcha_form(true, null, $dark);
    }
    ?>
    <span class="pass-msg">4chan Pass users can bypass this CAPTCHA. [<a href="https://www.<?php echo L::d(BOARD_DIR) ?>/pass" target="_blank">More Info</a>]</span>
    <?php
  }
  ?>
  </div>
  <div class="rules">Submitting <b>false</b> or <b>misclassified</b> reports will result in a ban.</div>
  <button type="submit">Submit</button>
  <input type="hidden" name="board" value="<?php echo htmlspecialchars($board) ?>">
  <input type="hidden" name="no" value="<?php echo((int)$no) ?>">
</form>
<?php
}
