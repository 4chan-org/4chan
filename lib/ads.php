<?

// pick a random row from tablename and return the img column
// FIXME this is a duplicate of the function below it...
// about 4 tables in global are the same thing with different schema

function rid($tablename,$usetext=0) {

	if ($usetext) {
		$fields = "img,link";
	} else {
		$fields = "img";
	}

	$ret = mysql_global_call("select $fields from `%s` join (select floor(1+rand()*(select max(id) from `%s`)) as id) as randid using (id)", $tablename, $tablename);


	if ($usetext) {
		return mysql_fetch_row($ret);
	} else {
		return mysql_result($ret,0,0);
	}
}

//file format:
//url (escaped for putting in a href)
//desc (not escaped)
//repeat
function text_link_ad($file) {
	global $text_ads;
	global $text_ads_n;

	if (!isset($text_ads[$file])) {
		$ads = @file($file, FILE_IGNORE_NEW_LINES);
		shuffle($ads);
		$text_ads[$file] = $ads;
		$num_ads = count($ads);
		$n = 0;
		$text_ads_n[$file] = $n;
	} else {
		$ads = $text_ads[$file];
		$n = $text_ads_n[$file];
		$num_ads = count($ads);
	}

	if (!$ads) return "";

	list($url,$desc) = explode("<>",$ads[$n]);

	if (!$url) return "";

	$text = "<strong><a href=\"$url\" rel=\"nofollow\" target=\"_blank\">".htmlspecialchars($desc)."</a></strong>";

	$n++;

	if ($n == $num_ads)
		$n = 0;

	$text_ads_n[$file] = $n;

	return $text;
}

//duplicate of rid.php
//dir - absolute path from web root to dir inc. trailing slash
//urlroot - what to append the name to to get the url
function rid_in_directory($dir,$urlroot) {
	global $document_root;
	$realdir = "/www/global/imgtop/dontblockthis/".$dir;
	$ft = "$realdir/files.txt";
	$names = file_array_cached($ft);

	if (!$names) {
		$arr = scandir($realdir);

		foreach ($arr as $fi) {
			if (preg_match("/\.(jpg|gif|png)$/", $fi)) {
				$names[] = $fi;
			}
		}

		file_put_contents($ft, join($names, "\n"));
	}

	return $urlroot.$names[rand(0, count($names)-1)];
}

// Takes a dir and a filename and uses file() to parse it
// then returns a random value.
function rand_from_flatfile( $dir, $filename )
{
	$file = $dir . $filename;
	$names = file_array_cached( $file );
	
	
	return $names[ rand( 0, count($names)-1 ) ];
}

function form_ads(&$dat) {
	$error = false; // unused, errors have ads too
	$dat .= "<div style='position:relative'>";
	/*if(!$error && FIXED_AD == 1) {
	$dat.='<a href="'.FIXED_LINK.'" target="_blank"><img src="//static.4chan.org/support/'.FIXED_IMG.'" width="120" height="240" border="0" style="position: absolute; top: '.$gtop.'px; right: 20px"></a>';
	}*/
	if(FIXED_LEFT_AD == 1) {
			if(defined('FIXED_LEFT_TXT') && FIXED_LEFT_TXT) {
				$dat.= ad_text_for(FIXED_LEFT_TXT);
			}
			else if(defined('FIXED_LEFT_TABLE')) {
				list($ldimg,$ldhref) = rid(FIXED_LEFT_TABLE,1);
				$dat.='<a href="'.$ldhref.'" target="_blank"><img src="'.$ldimg.'" width=120 height=240 border="0" style="position:absolute;left:10%"></a>';
			}
	}
	if(FIXED_RIGHT_AD == 1) {
				if(defined('FIXED_RIGHT_TXT') && FIXED_RIGHT_TXT) {
					$dat.= ad_text_for(FIXED_RIGHT_TXT);
				}
				else if(defined('FIXED_RIGHT_TABLE')) {
					list($ldimg,$ldhref) = rid(FIXED_RIGHT_TABLE,1);
					$dat.='<a href="'.$ldhref.'" target="_blank"><img src="'.$ldimg.'" border="0" style="position:absolute;right:10%"></a>';
				}
	}
	$dat .= "</div>";
}

function ad_text_for($path) {
	$txt = @file_get_contents_cached($path);

	if (!$txt) return $txt;

	return preg_replace_callback("@RANDOM@", "rand", $txt);
}

function global_msg_txt() {
	static $globalmsgtxt, $globalmsgdate;
	
	if (!$globalmsgdate) {
		$globalmsgtxt  = @file_get_contents(GLOBAL_MSG_FILE);
		$globalmsgdate = @filemtime(GLOBAL_MSG_FILE); 
	}
	
	return array($globalmsgtxt, $globalmsgdate);
}

?>
