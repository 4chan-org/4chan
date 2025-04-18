<?php
include_once("rpc.php");
include_once("util.php");

function out_of_order_keys($post, $f1, $f2)
{
	$pkeys = array_keys($_POST);

	return array_search($f1, $pkeys) > array_search($f2, $pkeys);
}

// style 1 - no spaces, just letters+numbers in fields
function is_random_text($fields)
{
	foreach($fields as $field) {
		$num = preg_match('/[0-9]/', $field);
		$lc = preg_match('/[a-z]/', $field);
		$uc = preg_match('/[A-Z]/', $field);
		$spc = preg_match('/ /', $field);
		if ($spc || ($num + $lc + $uc)<2) return false;
	}

	return true;
}

// style 2 - uc+lc+spaces in fields
function is_random_word_text($fields)
{
        foreach($fields as $field) {
                $other = preg_match('/[^a-zA-Z \n]/', $field);
                $lc = preg_match('/[a-z]/', $field);
                $uc = preg_match('/[A-Z]/', $field);
                $spc = preg_match('/ /', $field);
                if ($other || !$spc || !$lc || !$uc) return false;
        }

        return true;
}

function has_cyrillic($txt)
{
	return preg_match('/[\xD0-\xD3][\x80-\xBF]/', $txt) > 0;
}

function expand_short_urls($com)
{
	$urls = array();
	$urltext = "";

	$com = preg_replace("/\(dot\)|DOT/", ".", $com);
	preg_replace("@(([A-Za-z0-9_-]+\.)?((tinyurl|go2cut|doiop|x2t|snipr|gorkk|veeox|gurlx|goshrink|shrink[a-z]+|shortmaker|notlong|2gohere|urlmr|adjix|icanhaz|linkbee|oeeq|url9|urlzen|cladpal|redirx|yuarel|llfk|as2h|at|url-go|shrten|eyodude|urlxp|myurlz|allno1|nlz2|ye-s|way|lymme|unrelo|qfwr|urluda|golinkgo|cnekt|12n3|peqno|pasukan|vktw|snipie|4gk|82au|[a-z]+url|tinyden)\\.com|(lix|urlm|t2w|trigg|flib)\\.in|j\\.mp|hub\\.tm|(smarturl|fogz|urlink)\\.eu|sn\\.vc|atu\\.ca|(a|is)\\.gd|(xrl|nuurl|kore|p3n|txtn|hepy|w95|freepl|0x3|2tu)\\.us|dduri\\.info|cc\\.st|mzan\\.si|(\xe2\x9d\xbd|cctv)\\.ws|(metamark|sogood|idek|2tr|ln-s|urlaxe|littleurl)\\.net|(fyad|linkmenow|linkplug)\\.org|ad\\.vu|(bit|xa|ow|3|smal|to)\\.ly|safe\\.mn|goo\\.gl|is\\.gd|zi\\.ma|(tr|sn|jar|gow)\\.im|twurl\\.(cc|nl)|(fon|cli|rod|mug)\\.gs|(urlenco|dboost)\\.de|(tiny|bizz|blu|juu)\\.cc|2big\\.at|(crum|sk9)\\.pl|(bloat|pnt|lnq)\\.me|kl\\.am|(showip|2so)\\.be|minilink\\.me|lurl\\.no|(jai|cvm)\\.biz|xs\\.md|short\\.to|(yep|trunc)\\.it|a\\.nf|shortlinks\\.co\\.uk|redir\\.ec|tim\\.pe)(/[?A-Za-z0-9_-]*)?)@ei", '$urls[] = "http://$1";', $com);

	foreach($urls as $url) {
		$com .= strtolower(rpc_find_real_url(str_replace("preview.tinyurl","tinyurl",$url)));
	}

	return $com;
}

function normalize_ascii($text, $preserve_case = 0)
{
	$text = preg_replace( '#[(\[={]dot[)\]=}]#i', '.', $text );

	$t = Transliterator::create("Any-Latin; nfd; [:nonspacing mark:] remove; nfkc; Latin-ASCII");
	if (!$t) return $text;
	$text = $t->transliterate($text);
	if (!$preserve_case) $text = strtolower($text);

	return $text;
}

function strip_zerowidth($text) {
  $text = preg_replace('/
    [\x17\x8\x1f]|\x{0702}|\x{1D176}|\x{008D}|\x{00A0}|\x{205F}|\x{FEFF}|\x{11A6}|\x{00AD}|\x{3164}|\x{2800}|\x{180B}|\x{180C}|\x{180D}|
    \x{115F}|\x{1160}|\x{FFA0}|\x{034f}|\x{180e}|\x{17B4}|\x{17B5}|
    [\x{0001}-\x{0008}\x{000E}\x{000F}\x{0010}-\x{001F}\x{007F}-\x{009F}]|
    [\x{2000}-\x{200F}]|
    [\x{2028}-\x{202F}]|
    [\x{2060}-\x{206F}]|
    [\x{fe00}-\x{fe0f}]|
    [\x{FFF0}-\x{FFFB}]|
    [\x{E0100}-\x{E01EF}]|
    [\x{E0001}-\x{E007F}]
    /ux', '', $text);
  return $text;
}

/**
 * Removes codepoints above 3134F:
 * E0000..E007F; Tags
 * E0100..E01EF; Variation Selectors Supplement
 * F0000..FFFFF; Supplementary Private Use Area-A
 * 100000..10FFFF; Supplementary Private Use Area-B
 */
function strip_private_unicode($str) {
  if ($str === '') {
    return $str;
  }
  
  return preg_replace('/[^\x{0000}-\x{3134F}]/u', '', $str);
}

function strip_emoticons($text, $has_sjis_art = false) {
  $regex = '[\x{2300}-\x{2311}\x{2313}-\x{23FF}]|[\x{3200}-\x{32FF}\x{2190}-\x{21FF}\x{2580}-\x{259F}\x{2600}-\x{26FF}\x{2B00}-\x{2BFE}\x{1F700}-\x{1F77F}\x{1F780}-\x{1F7D8}\x{1F780}-\x{1F7D8}\x{1F800}-\x{1F8FF}\x{1F900}-\x{1F9FF}]|[\x{1F200}-\x{1F2FF}]|[\x{2460}-\x{24FF}]|[\x{1F100}-\x{1F1FF}]|[\x{1F600}-\x{1F64F}]|[\x{1F300}-\x{1F5FF}]|[\x{1F680}-\x{1F6FF}]|[\x{2600}-\x{26FF}]|[\x{2700}-\x{27BF}]|[\x{1F000}-\x{1F02F}]|[\x{1F0A0}-\x{1F0FF}]|[\x{2139}\x{23F2}]|[\x{1F910}-\x{1F9E6}]|[\x{0365}]|\x{FDFD}|[\x{0488}\x{0489}\x{1abe}\x{20dd}\x{20de}\x{20df}\x{20e0}\x{20e2}\x{20e3}\x{20e4}\x{a670}\x{a671}\x{a672}\x{061c}\x{070F}\x{0332}\x{0305}\x{2B55}]|[\x{202A}-\x{202E}\x{2060}-\x{206F}]|[\x{200E}\x{200F}\x{180e}\x{2b50}\x{23b3}\x{23F1}]|[\x{1F780}-\x{1F7FF}\x{1FA70}-\x{1FAFF}]|[\x{1D173}-\x{1D17A}\x{13000}-\x{1342F}\x{fe00}-\x{fe0f}]';
	
	if (!$has_sjis_art) {
		$regex .= '|[\x{2502}-\x{257F}]';
	}
	
  return preg_replace("/$regex/u", '', $text);
}

function strip_fake_capcodes($str) {
  // double FULLWIDTH NUMBER SIGN or #
  // PLACE OF INTEREST SIGN
  return preg_replace('/[\x{FF03}#]{2,}|\x{2318}/u', '', $str);
}

function normalize_text($text, $filter = '') {
  $text = normalize_ascii($text);
  $text = strip_zerowidth($text);
  
  //if ($filter) $text = $filter($text);
  
  $text = preg_replace('@[^a-zA-Z0-9.,/&:;?=~_-]@', '', $text);
  
  return $text;
}

function normalize_content( $name )
{
	// this needs some absolutely retarded shit to get this to not suck, however
	// it is an almost fool proof way of translating to ascii letters
	// without breaking kanji, cyrillic etc

	$name = preg_replace( '#[\x{2600}-\x{26FF}]#u', '', $name );

	// set internal incoding to utf-8
	//die($name);
	$oldEncoding = mb_internal_encoding();
	mb_internal_encoding('UTF-8');
	$name = convert_to_utf8($name);
	// Done, back to old encoding

	$newname = '';

	$len = mb_strlen( $name );
	for( $i = 0; $i < $len; $i++ ) {
		trans_similar_to_ascii( $newname, mb_substr( $name, $i, 1 ) );
	}

	mb_internal_encoding($oldEncoding);
	return $newname;
}

function convert_to_utf8( $content )
{
	if( !mb_check_encoding( $content, 'UTF-8' ) || !($content === mb_convert_encoding(mb_convert_encoding($content, 'UTF-32', 'UTF-8' ), 'UTF-8', 'UTF-32'))  ) {
		$content = mb_convert_encoding($content, 'UTF-8');
	}

	return $content;
}

function mb_ord( $char )
{
	mb_detect_order( array( 'UTF-8', 'ISO-8859-15', 'ISO-8859-1', 'ASCII' ) );
	$result = unpack( 'N', mb_convert_encoding( $char, 'UCS-4BE', 'UTF-8' ) );

	if( is_array( $result ) === true ) return $result[1];

	return ord($char);
}

function normalize_check($com,$sub,$f)
{
	$n = normalize_text($com.$sub, "expand_short_urls");
	$n2 = preg_replace("/[\x80-\xFF]/", "", html_entity_decode($com, ENT_QUOTES, "UTF-8"));

	record_post_info($f, "from: $com$sub\nto: $n\ndeutf8: $n2");
}

function match_banned_text($links, $text, $is_re)
{
	$badlink = "";
	$should_ban = false;

	foreach ($links as $l) {
		if ($l == '#') continue;

		$badlink = $l;

		if ($is_re) {
			$should_ban = preg_match($l, $text, $m) > 0;
			$badlink = TEST_BOARD ? "'$l' ({$m[0]})" : "'{$m[0]}'";
		} else {
			$should_ban = strpos($text, $l) !== FALSE;
		}

		if ($should_ban)
			break;
	}

	return $should_ban ? $badlink : "";
}

function check_banned_links($text, $links, $priv, $pub, $is_re, $name, $dest, $ban, $long, $perm = false)
{
	//check_banned_links($normalized_com, $sex, "sex spam links", S_BANNEDLINK, false, $name, $dest, true, false);
	$badlink = match_banned_text($links, $text, $is_re);
	$should_ban = ($badlink != "");
	$len = $long ? 14 : 1;
	$len = $perm ? -1 : $len;
	
	if ($should_ban == true) {
		$privres = sprintf("banned %s %s: %s", $is_re?"regex":"string",htmlspecialchars($badlink),$priv);
		if ($ban) {
			$pub = str_replace('Error: ', '', $pub);
			auto_ban_poster($name, $len, 1, $privres, $pub, true);
		}
		if (TEST_BOARD) $pub .= "<br>".$privres;
		error($pub, $dest);
	}
}

// this used to check the autobans table but now it just permabans
function auto_ban($name, $reason) {
	auto_ban_poster($name, 7, 1, $reason);
}

function get_jpeg_dimensions($contents)
{
	// this is faster than getimagesize

	$i = 0;
	$len = strlen($contents);

	if( ord($contents{0}) == 0xFF & ord($contents{1}) == 0xD8 && ord($contents{2}) == 0xFF & ord($contents{3}) == 0xE0 ) {
		$i = 4;

		if( $contents{$i+2} == 'J' && $contents{$i+3} == 'F' && $contents{$i+4} == 'I' && $contents{$i+5} == 'F' && ord($contents{$i+6}) == 0x00 ) {
			// valid image.
			$block_length = ord($contents{$i}) * 256 + ord($contents{$i+1});

			while( $i < $len ) {
				$i += $block_length;

				if( $i > $len ) {
					return false;
				}

				if( ord($contents{$i}) != 0xFF ) {
					return false;
				}


				if( ord($contents{$i+1}) == 0xC0  ) {
					$width = ord($contents{$i+7})*256 + ord($contents{$i+8});
					$height = ord($contents{$i+5})*256 + ord($contents{$i+6});

					return array($width, $height);
				} else {
					$i+=2;
					$block_length = ord($contents{$i}) * 256 + ord($contents{$i+1});
				}
			}
		}
	}

	return false;
}
	
function file_too_big_for_type( $ext, $w, $h, $fsize )
{	
	if ($ext === ".gif" || $ext === ".pdf" || $ext === '.webm' || $ext === '.mp4') {
		return NO;
	}
	
	$uncompressed_size = $w * $h * 4;
	
	return ($fsize > (3*$uncompressed_size)) ? YES : NO;
}

function regex_ignoring_nulls($words)
{
	$rwords = preg_replace("/./", "$0[^\\\\x01-\\\\xFF]*", $words);
	$rwords = str_replace(".", "\\.", $rwords);
	return "/".implode("|", $rwords)."/i";
}

/**
 * Strips exif from JPEG images
 * $file needs to be safe to use as shell argument
 */
function strip_jpeg_exif($file) {
  return system("/usr/local/bin/jpegtran -copy none -outfile '$file' '$file'") !== false;
}

/**
 * Strips non-whitelisted PNG chunks.
 * Returns an error if an animated PNG is detected.
 * Overwrites input file if modifications have been made.
 * $file needs to be safe to use as shell argument
 * Returns the number of chunks skipped or an error code (negative value).
 */
function strip_png_chunks($file, $max_chunk_len = 16 * 1024 * 1024) {
  $keep_chunks = [
    'ihdr',
    'plte',
    'idat',
    'iend',
    'trns',
    'gama',
    'sbit',
    'phys',
    'srgb',
    'bkgd',
    'time',
    'chrm',
    'iccp'
  ];
  
  $img = fopen($file, 'rb');
  
  if (!$img) {
    return -9;
  }
  
  $data = fread($img, 8);
  
  if ($data !== "\x89PNG\r\n\x1a\n") {
    fclose($img);
    return -1;
  }
  
  $output = '';
  
  $skip_count = 0;
  
  while (!feof($img)) {
    $chunk_len_buf = fread($img, 4);
    
    if (!$chunk_len_buf) {
      break;
    }
    
    if (strlen($chunk_len_buf) !== 4) {
      return -1;
    }
    
    $chunk_len = unpack('N', $chunk_len_buf)[1];
    
    if ($chunk_len > $max_chunk_len) {
      return -1;
    }
    
    $chunk_type_buf = fread($img, 4);
    
    if (strlen($chunk_type_buf) !== 4) {
      return -1;
    }
    
    $chunk_type = strtolower($chunk_type_buf);
    
    // aPNG is not supported
    if ($chunk_type === 'actl' || $chink_type === 'fctl' || $chink_type === 'fdat') {
      return -2;
    }
    
    if (in_array($chunk_type, $keep_chunks)) {
      if ($chunk_len > 0) {
        $data = fread($img, $chunk_len);
        
        if (strlen($data) !== $chunk_len) {
          return -1;
        }
      }
      else {
        $data = '';
      }
      
      $crc = fread($img, 4);
      
      if (strlen($crc) !== 4) {
        return -1;
      }
      
      $output .= $chunk_len_buf . $chunk_type_buf . $data . $crc;
      
      if ($chunk_type === 'iend') {
        fread($img, 1);
        if (!feof($img)) {
          $skip_count++;
        }
        break;
      }
    }
    else {
      fseek($img, $chunk_len + 4, SEEK_CUR);
      $skip_count++;
    }
  }
  
  fclose($img);
  
  if ($output === '') {
    return -1;
  }
  
  if ($skip_count === 0) {
    return 0;
  }
  
  $out_file = $file . '_pngtmp';
  
  $out = fopen($out_file, 'wb');
  
  if (!$out) {
    return -9;
  }
  
  if (fwrite($out, "\x89PNG\r\n\x1a\n") === false) {
    return -9;
  }
  
  if (fwrite($out, $output) === false) {
    return -9;
  }
  
  fclose($out);
  
  if (rename($out_file, $file) === false) {
    return -9;
  }
  
  return $skip_count;
}

// Calculates the actual image data inside a JPEG file and errors out
// if it's smaller than the reported size.
function validate_jpeg_size($file, $reported_size) {
  $eof = false;
  
  $img = fopen($file, 'rb');
  
  $data = fread($img, 2);
  
  if ($data !== "\xff\xd8") {
    fclose($img);
    return false;
  }
  
  while (!feof($img)) {
    $data = fread($img, 1);
    
    if ($data !== "\xff") {
      continue;
    }
    
    while (!feof($img)) {
      $data = fread($img, 1);
      
      if ($data !== "\xff") {
        break;
      }
    }
    
    if (feof($img)) {
      break;
    }
    
    $byte = unpack('C', $data)[1];
    
    if ($byte === 217) {
      $eof = ftell($img);
      break;
    }
    
    if ($byte === 0 || $byte === 1 || ($byte >= 208 && $byte <= 216)) {
      continue;
    }
    
    $data = fread($img, 2);
    
    $length = unpack('n', $data)[1];
    
    if ($length < 1) {
      break;
    }
    
    fseek($img, $length - 2, SEEK_CUR);
  }
  
  fclose($img);
  
  // 50 KB
  if ($reported_size - $eof >= 51200) {
    error(S_IMGCONTAINSFILE, $file);
  }
  
  return $eof;
}

/**
 * Checks for extensions and comments
 * and calculates actual GIF data.
 * Strips extra data if it exists.
 * $file needs to be safe to use as shell argument.
 */
function strip_gif_extra_data($file, $reported_size) {
  $binary = '/usr/local/bin/gifsicle';
  
  $res = shell_exec("$binary --sinfo \"$file\" 2>&1");
  
  if ($res !== null) {
    $size = 0;
    
    $need_strip = false;
    
    if (preg_match('/  extensions [0-9]+|    comment /', $res)) {
      $need_strip = true;
    }
    else if (preg_match_all('/compressed size ([0-9]+)/', $res, $m)) {
      foreach ($m[1] as $frame_size) {
        $size += (int)$frame_size;
      }
      
      // Strip if 50+ KB of extra data is found
      if ($reported_size - $size >= 51200) {
        $need_strip = true;
      }
    }
    
    if ($need_strip) {
      if (system("$binary --no-comments --no-extensions \"$file\" -o \"$file\" >/dev/null 2>&1") === false) {
        // gifsicle error
        return -1;
      }
      else {
        // file was modified
        return 1;
      }
    }
  }
  else {
    // gifsicle error
    return -1;
  }
  
  // nothing changed
  return 0;
}

// No longer used
function spam_filter_post_image($name, $dest, $md5, $upfile_name, $ext, $w, $h, $fsize)
{
	if( $upfile_name == '' ) error('Blank file names are not supported.');

	if (file_too_big_for_type($ext, $w, $h, $fsize) === YES) {
		$lim = 3*4*$w*$h;
		error(S_IMGCONTAINSFILE, $dest);
	}

	$img_bytes = file_get_contents($dest);
	$img_beginning = strlen($img_bytes) > 0x50000 ? substr($img_bytes, 0, 0x40000).substr($img_bytes, -(0x10000)) : $img_bytes;

	global $silent_reject;
	$silent_reject = 0;

	// protect against IE's retarded MIME-sniffing XSS vulnerability
	// by doing our own sniffing and rejecting exploitable files
	{
		$negative_match = regex_ignoring_nulls(array("minitokyonet", "urchin.js"));
		//except minitokyo from this, it causes false positives
		if (preg_match($negative_match, $img_beginning)===0)
		{
			// '<body', '<head', '<html', '<plaintext', '<pre', '<table', '<title', '<channel', '<scriptlet', '<span',
			// taken from URLMON.DLL in win2k...
			$positive_match = regex_ignoring_nulls(array('<a href', '<script', '<iframe', 'unescape', 'base64', 'charAt', 'del %0', 'WScript.Shell'));
			if (preg_match($positive_match, $img_beginning, $m))
			{
				$foundxss = true;
				$foundstr = htmlentities($m[0]);
//				$xssban   = true;
			}
		}

		if ($foundxss) {
			if ($xssban) auto_ban_poster($name, 7, 1, "script in image (from image, found $foundstr at $xsspos)", "Posting image with embedded virus.");
			if (TEST_BOARD) error("Script in image: '$foundstr' at $xsspos");
			error('Detected possible malicious code in image file.', $dest);
		}

/*		if (BOARD_DIR == 'b' && strpos($img_beginning, "AppleMark")) {
			record_post_info("/www/perhost/applemark.txt");
			$silent_reject = true;
			return;
		}  */
	}
	// don't allow embedded zips and rars
	{
		$rar2 = 'REs^';
		$rar1 = "Rar!\x1A\x07\x00";
		$zip = "PK\x03\x04";
		$sevenz = "7z\xBC\xAF'\x1C";
		$pfbind = "pFBind";
		$deny = array(
			'REs^',
			"Rar!\x1A\x07\x00",
			"PK\x03\x04",
			"7z\xBC\xAF'\x1C"/*,
			"pFBind",
			"RIFF",
			"matroska",
			
			// stupid ogg shit
			"OggS\x00",
			'libVorbis',
			"moot\x00",
  		"Krni\x00"*/
		);
		
		
		foreach($deny as $arc_string)
		{
			if(strpos($img_bytes, $arc_string) !== false)
			{
				error(S_IMGCONTAINSFILE, $dest);
			}
		}
	}
	// reject APNG
	if($ext == '.png')
	{
		if(strpos($img_bytes, 'acTL') !== false && strpos($img_bytes, 'fcTL') !== false && strpos($img_bytes, 'fdAT') !== false)
		{
			error('APNG format not supported.', $dest);
		}
	}
}

function register_postfilter_hit($filter_id) {
  $long_ip = (int)ip2long($_SERVER['REMOTE_ADDR']);
  
  $filter_id = (int)$filter_id;
  
  $query = <<<SQL
SELECT id FROM postfilter_hits
WHERE filter_id = $filter_id AND long_ip = $long_ip AND created_on > DATE_SUB(NOW(), INTERVAL 1 HOUR) LIMIT 1
SQL;
  
  $res = mysql_global_call($query);
  
  if ($res && mysql_num_rows($res) === 1) {
    return true;
  }
  
  $query = "INSERT INTO postfilter_hits (filter_id, board, long_ip) VALUES($filter_id, '%s', $long_ip)";
  
  return mysql_global_call($query, BOARD_DIR);
}

function log_postfilter_hit($filter, $board, $thread_id, $name, $sub, $com, $upfile_name) {
  $ip = $_SERVER['REMOTE_ADDR'];
  
  $country = $_SERVER['HTTP_X_GEO_COUNTRY'];
  
  $threat_score = spam_filter_get_threat_score($country, !$thread_id, true);
  
  $meta = spam_filter_format_http_headers("$name\n$sub\n$com", $country, $upfile_name, $threat_score);
  
  $action = "filter_{$filter['id']}";
  
  $query = <<<SQL
INSERT INTO event_log(`type`, `board`, `thread_id`, `ip`, `meta`)
VALUES('%s', '%s', %d, '%s', '%s')
SQL;
    
  mysql_global_call($query, $action, $board, $thread_id, $ip, $meta);
}

/**
 * New postfilter. Uses the database.
 * Errors-out if a Reject filter matches.
 * Returns true if an Autosage filter matches.
 * Otherwise returns false.
 */
function spam_filter_post_content_new($board, $resto, $com, $sub, $name, $upfile_name, $pwd = null, $pass_id = null) {
  if (preg_match('/^php../', $upfile_name) === 1 && strpos($upfile_name, '.') === false) {
    auto_ban_poster($name, 14, 1, 'PHP proxy (via filename check)', 'Proxy/Tor exit node.');
    error(S_GENERICERROR);
  }
  
  // Postfilter
  $tbl = 'postfilter';
  
  $query = <<<SQL
SELECT id, pattern, autosage, log, regex, quiet, lenient, ops_only, min_count,
board, ban_days, created_on, updated_on FROM $tbl
WHERE active = 1 AND (board = '' OR board = '%s')
SQL;
  
  $res = mysql_global_call($query, $board);
  
  if (!$res) {
    return false;
  }
  
  // Remove bbcode
  if (strpos($com, '[') !== false) {
    $com = preg_replace('/\[\/?(?:spoiler|code|sjis)\]/', '', $com);
  }
  
  // For string filters
  $normalized_com = normalize_text($name.$sub.$upfile_name.$com);
  // For regex filters
  $expanded_com = "$name $sub $com";
  // For autosage filters
  if (!$resto) {
    $normalized_com_sage = preg_replace('/[.,!:>\/]+|&gt;/', ' ', $sub . ' ' . $com . ' '. $name);
    $normalized_com_sage = ucwords(strtolower($normalized_com_sage));
    $normalized_com_sage = normalize_ascii($normalized_com_sage, 1);
  }
  
  $userpwd = UserPwd::getSession();
  
  $matched_filter = false;
  
  while ($filter = mysql_fetch_assoc($res)) {
    // Counter mode: triggers when the number of matches is at least $min_count
    $min_count = (int)$filter['min_count'];
    
    if ($min_count < 1) {
      $min_count = 1;
    }
    
    // Lenient filter
    if ($filter['lenient']) {
      if ($userpwd) {
        if ($filter['updated_on']) {
          $since_ts = (int)$filter['updated_on'];
        }
        else {
          $since_ts = (int)$filter['created_on'];
        }
        
        if ($userpwd->isUserKnownOrVerified(60, $since_ts)) { // 1 hour
          continue;
        }
      }
    }
    
    // OPs-only filter
    if ($filter['ops_only'] && $resto) {
      continue;
    }
    
    if ($filter['autosage']) {
      // Autosage filter but the post is a reply
      if ($resto) {
        continue;
      }
      // Regex filter
      if ($filter['regex']) {
        if ($min_count > 1) {
          if (preg_match_all($filter['pattern'], $expanded_com) >= $min_count) {
            $matched_filter = $filter;
            break;
          }
        }
        else {
          if (preg_match($filter['pattern'], $expanded_com) === 1) {
            $matched_filter = $filter;
            break;
          }
        }
      }
      // String filter for autosaging
      else {
        if ($min_count > 1) {
          if (substr_count($normalized_com_sage, $filter['pattern']) >= $min_count) {
            $matched_filter = $filter;
            break;
          }
        }
        else {
          if (strpos($normalized_com_sage, $filter['pattern']) !== false) {
            $matched_filter = $filter;
            break;
          }
        }
      }
    }
    // Regex filter
    if ($filter['regex']) {
      if ($min_count > 1) {
        if (preg_match_all($filter['pattern'], $expanded_com) >= $min_count) {
          $matched_filter = $filter;
          break;
        }
      }
      else {
        if (preg_match($filter['pattern'], $expanded_com) === 1) {
          $matched_filter = $filter;
          break;
        }
      }
    }
    // String filter
    else {
      if ($min_count > 1) {
        if (substr_count($normalized_com, $filter['pattern']) >= $min_count) {
          $matched_filter = $filter;
          break;
        }
      }
      else {
        if (strpos($normalized_com, $filter['pattern']) !== false) {
          $matched_filter = $filter;
          break;
        }
      }
    }
  }
  
  if ($matched_filter !== false) {
    // Update hit stats
    register_postfilter_hit($matched_filter['id']);
    
    // Autosage
    if ($matched_filter['autosage']) {
      return true;
    }
    // Log
    else if ($matched_filter['log']) {
      log_postfilter_hit($matched_filter, $board, $resto, $name, $sub, $com, $upfile_name);
    }
    // Reject
    else {
      if ($matched_filter['ban_days']) {
        $err = S_BANNEDTEXT;
        $ban_days = (int)$matched_filter['ban_days'];
        $private_reason = 'banned string in comment (filter ID: ' . $matched_filter['id'] . ')';
        $public_reason = $err;
        auto_ban_poster($name, $ban_days, 1, $private_reason, $public_reason, true, $pwd, $pass_id);
      }
      else {
        $err = S_REJECTTEXT;
      }
      
      if ($matched_filter['quiet']) {
        show_post_successful_fake($resto);
        die();
      }
      
      if (TEST_BOARD) {
        $err .= ' (filter ID: ' . $matched_filter['id'] . ')';
      }
      
      error($err);
    }
  }
  
  // Other
  if ($sub !== '') {
    $normalized_sub = normalize_text($sub);
    
    if (stripos($sub, 'moot') !== false) {
      error("You can't post with that subject.");
    }
    
    if (stripos($normalized_com, '##') !== false || stripos($sub, 'admin') !== false) {
      error("You can't post with that subject.");
    }
  }
	
  return false;
}

function isIPRangeBannedReport($long_ip, $asn, $board, $userpwd = null) {
	return isIPRangeBanned($long_ip, $asn,
    [
      'board' => $board,
      'is_report' => true,
      'userpwd' => $userpwd,
    ]
  );
}

// Checks if the IP is rangebanned
// options:
//    board(string), is_sfw(bool, requires board)
//    userpwd(UserPwd): instance of UserPwd or null,
//    is_report(bool), is_op(bool), has_img(bool),
//    browser_id(string),
//    op_content(string): content of the thread OP for per-thread bans (unused)
// returns the rangeban database entry if the IP is banned, false otherwise
function isIPRangeBanned($long_ip, $asn, $options = []) {
  $long_ip = (int)$long_ip;
  
  $asn = (int)$asn;
  
  $now = (int)$_SERVER['REQUEST_TIME'];
  
  $cols = 'created_on, updated_on, expires_on, active, boards, ops_only, img_only, lenient, report_only, ua_ids';
  
  $query = <<<SQL
(SELECT SQL_NO_CACHE $cols FROM iprangebans
WHERE range_start <= $long_ip AND range_end >= $long_ip AND active = 1
AND (expires_on = 0 OR expires_on > $now))
SQL;
  
  if ($asn > 0) {
    $query .= <<<SQL
UNION (SELECT $cols FROM iprangebans
WHERE asn = $asn AND active = 1 AND (expires_on = 0 OR expires_on > $now))
SQL;
  }
  
  $query .= ' ORDER BY lenient ASC';
  
  $res = mysql_global_call($query);
  
  if (!$res) {
    return false;
  }
  
  // Parameters
  if (isset($options['board'])) {
    $board = $options['board'];
    $is_sfw = isset($options['is_sfw']) && $options['is_sfw'];
  }
  else {
    $board = null;
    $is_sfw = false;
  }
  
  if (isset($options['browser_id'])) {
    $browser_id = $options['browser_id'];
  }
  else {
    $browser_id = null;
  }
  
  if (isset($options['req_sig'])) {
    $req_sig = $options['req_sig'];
  }
  else {
    $req_sig = null;
  }
  
  if (isset($options['op_content']) && $options['op_content'] !== '') {
    $op_content = $options['op_content'];
  }
  else {
    $op_content = null;
  }
  
  $is_op = isset($options['is_op']) && $options['is_op'];
  $is_report = isset($options['is_report']) && $options['is_report'];
  $has_img = isset($options['has_img']) && $options['has_img'];
  
  if (isset($options['userpwd']) && $options['userpwd'] && $options['userpwd'] instanceof UserPwd) {
    $userpwd = $options['userpwd'];
  }
  else {
    $userpwd = null;
  }
  
  // OP-only and Image-only lenient rangebans also require a certain number of posts
  $post_count_ok = $userpwd && $userpwd->postCount() >= 3 && ($userpwd->maskLifetime() > 900 || $userpwd->postCount() >= 15);
  
  while ($range = mysql_fetch_assoc($res)) {
    if ($range['boards']) {
      if ($board === null) {
        continue;
      }
      
      $board_matcher = ",{$range['boards']},";
      
      if (strpos($board_matcher, ",$board,") === false) {
        // _ws_ scope affects all work safe boards
        if ($is_sfw) {
          if (strpos($board_matcher, ",_ws_,") === false) {
            continue;
          }
        }
        else {
          continue;
        }
      }
    }
    
    $post_count_check = true;
    
    if ($range['report_only'] && !$is_report) {
      continue;
    }
    
    if ($range['ops_only']) {
      if (!$is_op) {
        continue;
      }
      else {
        $post_count_check = $post_count_ok;
      }
    }
    
    if ($range['img_only']) {
      if (!$has_img) {
        continue;
      }
      else {
        $post_count_check = $post_count_ok;
      }
    }
    
    if ($range['ua_ids']) {
      $_skip = true;
      
      if ($browser_id && strpos($range['ua_ids'], $browser_id) !== false) {
        $_skip = false;
      }
      
      if ($_skip && $req_sig && strpos($range['ua_ids'], $req_sig) !== false) {
        $_skip = false;
      }
      
      if ($_skip) {
        continue;
      }
    }
    
    if ($userpwd && $range['lenient']) {
      $lenient = (int)$range['lenient'];
      
      if ($range['updated_on']) {
        $since_ts = (int)$range['updated_on'];
      }
      else {
        $since_ts = (int)$range['created_on'];
      }
      
      // Mode 1: Known 24h or Verified
      if ($lenient === 1 && ($userpwd->verifiedLevel() || ($userpwd->isUserKnown(1440, $since_ts) && $post_count_check))) {
        continue;
      }
      // Mode 2: Known 24h only
      else if ($lenient === 2 && $userpwd->isUserKnown(1440, $since_ts) && $post_count_check) {
        continue;
      }
      // Mode 3: Verified only
      else if ($lenient === 3 && $userpwd->verifiedLevel()) {
        continue;
      }
    }
    
    return $range;
  }
  
  return false;
}

/**
 * Checks if the IP has enough posting history
 * $mode: 0 = check for replies, 1 = check for image replies, 2 = check of threads
 * Caches results.
 */
function spam_filter_is_ip_known($long_ip, $board = null, $mode = 0, $minutes_min = 0, $posts_min = 1) {
  static $cache = array();
  
  $long_ip = (int)$long_ip;
  
  if (!$long_ip) {
    return false;
  }
  
  $cache_key = "$long_ip.$board.$mode.$minutes_min.$posts_min";
  
  if (isset($cache[$cache_key])) {
    return $cache[$cache_key];
  }
  
  // Not after (3 days)
  $minutes_max = 4320;
  
  // Not before
  $minutes_min = (int)$minutes_min;
  
  // At least X replies
  $posts_min = (int)$posts_min;
  
  // Board
  if ($board) {
    $board_clause = "AND board = '" . mysql_real_escape_string($board) . "'";
  }
  else {
    $board_clause = '';
  }
  
  // Mode: 1 = image replies, 2 = threads, 0 = any reply
  if ($mode === 1) {
    $action_clause = "AND action = 'new_reply' AND had_image = 1";
  }
  else if ($mode === 2) {
    $action_clause = "AND action = 'new_thread'";
  }
  else {
    $action_clause = "AND action = 'new_reply'";
  }
  
  // Not before
  if (!$minutes_min) {
  	$time_clause = "time >= DATE_SUB(NOW(), INTERVAL $minutes_max MINUTE)";
  }
  else {
  	$time_clause = "(time BETWEEN DATE_SUB(NOW(), INTERVAL $minutes_max MINUTE) AND DATE_SUB(NOW(), INTERVAL $minutes_min MINUTE))";
  }
  
  // Check posting history
  $query = <<<SQL
SELECT SQL_NO_CACHE COUNT(*) FROM user_actions
WHERE ip = $long_ip $action_clause $board_clause
AND $time_clause
SQL;
  
  $res = mysql_global_call($query);
  
  if (!$res) {
    return false;
  }
  
  $count = (int)mysql_fetch_row($res)[0];
  
  if ($count < $posts_min) {
    $cache[$cache_key] = false;
    return false;
  }
  
  // Check deletion history
  /*
  $query = <<<SQL
SELECT SQL_NO_CACHE COUNT(*) FROM user_actions
WHERE ip = $long_ip AND action = 'delete'
AND time >= DATE_SUB(NOW(), INTERVAL 48 HOUR)
SQL;
  
  $res = mysql_global_call($query);
  
  if (!$res) {
    return false;
  }
  
  $count = (int)mysql_fetch_row($res)[0];
  
  if ($count > 0) {
    $cache[$cache_key] = false;
    return false;
  }
  */
  
  $cache[$cache_key] = true;
  
  return true;
}

/*
 * Checks if the user has a valid posting history to bypass a rangeban (FIXME: deprecated)
 */
function spam_filter_is_user_known($long_ip, $board = null, $pwd = null, $minutes = 15, $count = 1) {
  static $cache = array();
  
  $pwd = null; // FIXME
  
  $interval = (int)$minutes;

  $cache_key_ip = "{$long_ip}:{$interval}";

  if (isset($cache[$cache_key_ip])) {
    return $cache[$cache_key_ip];
  }
  
  if ($pwd && $board) {
    $cache_key_pwd = "{$board}:{$pwd}:{$interval}";
    
    if (isset($cache[$cache_key_pwd])) {
      return $cache[$cache_key_pwd];
    }
  }
  
  $count = (int)$count;
  
  if ($count < 1) {
    $count = 1;
  }
  
  // Check the IP
  $query = <<<SQL
SELECT 1 FROM user_actions
WHERE ip = %d AND action = 'new_reply'
AND time < DATE_SUB(NOW(), INTERVAL $interval MINUTE)
LIMIT $count
SQL;
  
  $res = mysql_global_call($query, $long_ip);
  
  if (!$res) {
    return true;
  }
  
  if (mysql_num_rows($res) === $count) {
    $cache[$cache_key_ip] = true;
    return true;
  }
  
  $cache[$cache_key_ip] = false;
  
  // Check the password
  if ($pwd && $board) {
    $time_lim = $_SERVER['REQUEST_TIME'] - ($interval * 60);
    
    $query = <<<SQL
SELECT 1 FROM `%s` WHERE pwd = '%s' AND time < $time_lim LIMIT 1
SQL;

    $res = mysql_board_call($query, $board, $pwd);
    
    if (!$res) {
      return true;
    }
    
    if (mysql_num_rows($res)) {
      $cache[$cache_key_pwd] = true;
      return true;
    }
    
    $cache[$cache_key_pwd] = false;
  }
  
  return false;
}

function spam_filter_is_common_ua($ua) {
  $major = '[1-9]\d+';
  $minor = '\.[.0-9]+';
  $moz5 = 'Mozilla/5\.0';
  $chrome = "Chrome/$major\.0\.0\.0";
  $firefox = "Firefox/$major$minor$";
  $gecko = 'Gecko/20100101';
  $safari = "Safari/$major$minor";
  $awk = "AppleWebKit/$major$minor";
  $osxv = '[1-9][_0-9]+';
  $iosv = 'Mobile/[1-9][E0-9]+';
  $khtml = '\(KHTML, like Gecko\)';

  $patterns = [
    "@^$moz5 \(Windows NT 10\.0; Win64; x64\) $awk $khtml $chrome $safari$@",
    "@^$moz5 \(Windows NT (?:10\.0|6\.[13]);(?: Win64; x64;)? rv:$major$minor\) $gecko $firefox@",
    "@^$moz5 \(Linux; Android 10; K\) $awk $khtml $chrome (?:Mobile )?$safari$@",
    "@^$moz5 \(iPhone; CPU iPhone OS $osxv like Mac OS X\) $awk $khtml Version/[1-9]+$minor $iosv $safari( OPX/[.0-9]+)?$@",
    "@^$moz5 \(Windows NT 10\.0; Win64; x64\) $awk $khtml $chrome $safari Edg/$major\.0\.0\.0$@",
    "@^$moz5 \(Windows NT 10\.0; Win64; x64\) $awk $khtml $chrome $safari OPR/$major\.0\.0\.0( \(Edition [^\)]{1,8}\))?$@",
    "@^$moz5 \(Macintosh; Intel Mac OS X 10_15_7\) $awk $khtml $chrome $safari$@",
    "@^$moz5 \(X11; Linux x86_64; rv:$major$minor\) $gecko $firefox$@",
    "@^$moz5 \(Android [1-9][0-9]{1,2}; Mobile; rv:$major$minor\) Gecko/$major$minor $firefox$@",
    "@^$moz5 \(Linux; Android 10; K\) $awk $khtml SamsungBrowser/[1-9][.0-9]+ $chrome (?:Mobile )?$safari$@",
    "@^$moz5 \(Macintosh; Intel Mac OS X 10\.15; rv:$major$minor\) $gecko $firefox$@",
    "@^$moz5 \(Macintosh; Intel Mac OS X 10_15_7\) $awk $khtml Version/$major$minor $safari$@",
    "@^$moz5 \(X11; Linux x86_64\) $awk $khtml $chrome $safari$@",
    "@^$moz5 \(Linux; Android $major; [^;]+; wv\) $awk $khtml Version/4\.0 Chrome/$major$minor Mobile $safari$@",
    "@^$moz5 \(Linux; Android 10; K\) $awk $khtml $chrome (?:Mobile )?$safari EdgA/$major$minor$@",
    "@^$moz5 \(iPhone; CPU iPhone OS $osxv like Mac OS X\) $awk $khtml CriOS/$major$minor $iosv $safari$@",
    "@^$moz5 \(Linux; Android 10; K\) $awk $khtml $chrome (?:Mobile )?$safari OPR/$major\.0\.0\.0$@",
  ];
  
  $val = false;
  
  foreach ($patterns as $r) {
    if (preg_match($r, $ua)) {
      $val = true;
      break;
    }
  }
  
  return $val;
}

/**
 * Generates the ID of the device from browser's user agent
 */
function spam_filter_get_browser_id() {
  static $cache = null;
  
  if ($cache !== null) {
    return $cache;
  }
  
  $ua = $_SERVER['HTTP_USER_AGENT'];
  
  if (!$ua) {
    return '0deadbeef';
  }
  
  if (preg_match('/Android|iPhone|iPad|Dalvik|Clover|Kuroba|ChanOS/', $ua)) {
    $is_mobile = 1;
  }
  else if (isset($_SERVER['HTTP_SEC_CH_UA_MOBILE']) && $_SERVER['HTTP_SEC_CH_UA_MOBILE'] === '?1') {
    $is_mobile = 1;
  }
  else {
    $is_mobile = 0;
  }
  
  $clean_ua = preg_replace('/(\/|:)[.0-9]+/', '', $ua);
  
  if (isset($_SERVER['HTTP_SEC_CH_UA_MODEL']) && $_SERVER['HTTP_SEC_CH_UA_MODEL'] && $_SERVER['HTTP_SEC_CH_UA_MODEL'] != '""') {
    $fmn = isset($_SERVER['HTTP_SEC_FETCH_MODE']) && $_SERVER['HTTP_SEC_FETCH_MODE'] === 'navigate';
    
    if (strpos($ua, '(Linux; Android 10; K)') !== false && !$fmn) {
      $clean_ua .= $_SERVER['HTTP_SEC_CH_UA_MODEL'];
    }
  }
  
  $hmac_secret = 'd8d9616bce7b0a8fc83b422a7001b0492e17b4c16debeb43b77cf0060d9bdae3';
  
  $sig = hash_hmac('sha1', $clean_ua, $hmac_secret, true);
  
  if (!$sig) {
    return '';
  }
  
  $cache = $is_mobile . bin2hex(substr($sig, 0, 4));
  
  return $cache;
}

/**
 * Checks for rangebans when posting
 */
function spam_filter_post_ip($userpwd = null, $thread_id = null, $has_img = false) {
  global $captcha_bypass, $rangeban_bypass;
  
  if (CAPTCHA) {
    if ($captcha_bypass === true) {
      return false;
    }
  }
  else {
    if ($rangeban_bypass) {
      return false;
    }
  }
  
  $ip = $_SERVER['REMOTE_ADDR'];
  
  if (isset($_SERVER['HTTP_X_GEO_ASN'])) {
    $asn = (int)$_SERVER['HTTP_X_GEO_ASN'];
  }
  else {
    $_asninfo = GeoIP2::get_asn($ip);
    
    if ($_asninfo) {
      $asn = (int)$_asninfo['asn'];
    }
    else {
      $asn = 0;
    }
  }
  
  $long_ip = ip2long($ip);
  
  if (!$long_ip) {
    return false;
  }
  
  $browser_id = spam_filter_get_browser_id();
  $req_sig = spam_filter_get_req_sig();
  
  $options = [
    'board' => BOARD_DIR,
    'is_sfw' => DEFAULT_BURICHAN,
    'userpwd' => $userpwd,
    'is_op' => $thread_id == 0,
    'has_img' => $has_img,
    'browser_id' => $browser_id,
    'req_sig' => $req_sig
  ];
  
  if ($range = isIPRangeBanned($long_ip, $asn, $options)) {
    if ($range['lenient'] && $userpwd) {
      $userpwd->setCookie('.' . L::d(BOARD_DIR));
    }
    
    // Images only
    if ($range['img_only']) {
      $_err = S_IPRANGE_BLOCKED_IMG;
    }
    // Threads only
    else if ($range['ops_only']) {
      $_err = S_IPRANGE_BLOCKED_OP;
    }
    else {
      $_err = S_IPRANGE_BLOCKED;
    }
    
    // Temporarily or Permanently
    if ($range['expires_on'] || $range['lenient']) {
      $_err .= ' ' . S_IPRANGE_BLOCKED_TEMP;
    }
    else {
      $_err .= ' ' . S_IPRANGE_BLOCKED_PERM;
    }
    
    // Bypassed by verified or known users
    if ($range['lenient'] == 1) {
      $_err .= S_IPRANGE_BLOCKED_L1;
    }
    // Bypassed by known users only
    else if ($range['lenient'] == 2) {
      $_err .= S_IPRANGE_BLOCKED_L2;
    }
    // Bypassed by verified users only
    else if ($range['lenient'] == 3) {
      $_err .= S_IPRANGE_BLOCKED_L3;
    }
    
    // 4chan pass mention
    $_err .= S_IPRANGE_BLOCKED_PASS;
    
    error($_err);
  }
  
  // Auto-rangebans, Mobile only
  // Bypassed by verified users or known users for at least 2h
  // or users who have made at least one post on the board 15 minutes ago
  if ($thread_id !== null && $browser_id[0] === '1') {
    $since_ts = 0;
    
    if ($userpwd) {
      $user_known = $userpwd->isUserKnownOrVerified(120, 1);
      
      $now = $_SERVER['REQUEST_TIME'];
      
      if ($userpwd->postCount() > 0 && $userpwd->maskTs() <= $now - 900) {
        $since_ts = $userpwd->maskTs();
      }
    }
    else {
      $user_known = false;
    }
    
    if (!$user_known) {
      if (is_ip_auto_rangebanned($ip, BOARD_DIR, $thread_id, $browser_id, $since_ts)) {
        write_to_event_log('auto_range_hit', $ip, [
          'board' => BOARD_DIR,
          'thread_id' => $thread_id,
          'ua_sig' => $browser_id
        ]);
        
        if ($userpwd) {
          $userpwd->setCookie('.' . L::d(BOARD_DIR));
        }
        
        // Temporary, bypassablee by known or verified users
        error(S_IPRANGE_BLOCKED . ' ' . S_IPRANGE_BLOCKED_TEMP . S_IPRANGE_BLOCKED_L1 . S_IPRANGE_BLOCKED_PASS);
      }
    }
  }
  
  return false;
}

function is_ip_auto_rangebanned($ip, $board, $thread_id, $browser_id, $since_ts = 0) {
  $range_sql = explode('.', $ip);
  
  $range_sql = "{$range_sql[0]}.{$range_sql[1]}.%";
  
  $thread_id = (int)$thread_id;
  
  if ($since_ts > 0) {
    $since_sql = ' AND created_on <= FROM_UNIXTIME(' . ((int)$since_ts) . ')';
  }
  else {
    $since_sql = '';
  }
  
  $sql =<<<SQL
SELECT id FROM event_log WHERE
type = 'rangeban' AND board = '%s' AND thread_id = $thread_id AND ua_sig = '%s'
AND ip LIKE '%s'
AND created_on > DATE_SUB(NOW(), INTERVAL 120 MINUTE)$since_sql
LIMIT 1
SQL;
  
  $res = mysql_global_call($sql, $board, $browser_id, $range_sql);
  
  if (!$res) {
    return false;
  }
  
  if (mysql_num_rows($res)) {
    return true;
  }
  
  return false;
}

/**
 * Dumps and formats HTTP headers and other request information for logging
 */
function spam_filter_format_http_headers($com = null, $country = null, $filename = null, $threat_score = null, $req_sig = null) {
    $bot_headers = '';
    
    foreach ($_SERVER as $_h_name => $_h_val) {
      if (substr($_h_name, 0, 5) == 'HTTP_') {
        if ($_h_name === 'HTTP_COOKIE') {
          $_cookies = array_keys($_COOKIE);
          $_cookies = array_intersect($_cookies, ['ws_style', 'nws_style', '4chan_pass', '_tcs', '_ga', 'cf_clearance' ]);
          $_cookie_count = count($_COOKIE);
          $bot_headers .= "HTTP_COOKIE: " . htmlspecialchars(implode(', ', $_cookies)) . " ($_cookie_count in total)\n";
        }
        else if (strpos($_h_name, 'AUTH') !== false) {
          continue;
        }
        else {
          $bot_headers .= "$_h_name: " . htmlspecialchars($_h_val) . "\n";
        }
      }
    }
    
    $bot_headers .= "_POST: " . htmlspecialchars(implode(', ', array_keys($_POST))) . "\n";
    
    if ($country !== null) {
      $bot_headers .= "_Country: $country\n";
    }
    
    if ($threat_score !== null) {
      $bot_headers .= "_Score: " . $threat_score . "\n";
    }
    
    if ($req_sig !== null) {
      $bot_headers .= "_Sig: " . $req_sig . "\n";
    }
    
    if (isset($_COOKIE['_tcs'])) {
      $bot_headers .= "_TCS: " . htmlspecialchars($_COOKIE['_tcs']) . "\n";
    }
    
    if (isset($_COOKIE['4chan_pass'])) {
      $userpwd = UserPwd::getSession();
      
      if ($userpwd) {
        $bot_headers .= "_Pwd: " . htmlspecialchars($userpwd->getPwd()) . "\n";
      }
    }
    
    if ($filename !== null) {
      $bot_headers .= "_File: " . htmlspecialchars($filename) . "\n";
    }
    
    if ($com !== null) {
      $bot_headers .= "_Comment: $com";
    }
    
    return $bot_headers;
}

function spam_filter_get_req_sig() {
  static $cache = null;
  
  if ($cache !== null) {
    return $cache;
  }
  
  $pick_headers = [
    'HTTP_SEC_CH_UA_PLATFORM',
    'HTTP_SEC_CH_UA_MOBILE',
    'HTTP_SEC_CH_UA_MODEL',
    'HTTP_USER_AGENT',
    'HTTP_ACCEPT_LANGUAGE',
    'HTTP_SEC_FETCH_SITE',
    'HTTP_SEC_FETCH_MODE',
    'HTTP_SEC_FETCH_DEST',
  ];
  
  $need_headers = [
    'HTTP_USER_AGENT',
    'HTTP_ACCEPT',
    'HTTP_REFERER',
    'HTTP_ACCEPT_LANGUAGE',
  ];
  
  $datapoints = [];
  
  $keys = [];
  
  foreach ($_SERVER as $k => $v) {
    if (in_array($k, $pick_headers)) {
      $keys[] = $k;
    }
  }
  
  $keyline = implode('.', $keys);
  
  $pointlines = [
    'fetch_smd' => 'HTTP_SEC_FETCH_SITE.HTTP_SEC_FETCH_MODE.HTTP_SEC_FETCH_DEST',
    'fetch_dms' => 'HTTP_SEC_FETCH_DEST.HTTP_SEC_FETCH_MODE.HTTP_SEC_FETCH_SITE',
    'fetch_any' => 'HTTP_SEC_FETCH_'
  ];
  
  foreach ($pointlines as $key => $value) {
    if (strpos($keyline, $value) !== false) {
      $datapoints[] = $key;
    }
  }
  
  if (isset($_SERVER['HTTP_SEC_GPC']) || isset($_SERVER['HTTP_DNT'])) {
    $datapoints[] = 'dnt_gpc';
  }
  
  if (isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
    $datapoints[] = 'xrw';
  }
  
  if (preg_match('/HTTP_SEC_CH_UA_[^.]+\.HTTP_SEC_CH_UA_[^.]+\.HTTP_SEC_CH_UA_/', $keyline)) {
    $datapoints[] = 'ch_ua_block';
  }
  
  foreach ($need_headers as $k) {
    if (!isset($_SERVER[$k])) {
      $datapoints[] = 'missing';
      break;
    }
  }
  
  $sig = implode('+', $datapoints);
  
  if (!$sig) {
    $sig = 'deadbeef';
  }
  
  $cache = substr(md5($sig), 0, 8);
  
  return $cache;
}

// Covers 251044 (79.14 %) unique IPs and 10454 (77.09 %) unique bans
// IPs %: 0.1 | Bans %: 0 | GR1 all: 0 | Any EU: false
function spam_filter_is_asn_whitelisted() {
  static $val = null;
  
  static $whitelist = [
    21928, 6167, 7922, 7018, 812, 701, 1221, 20115, 22773, 2856, 3320, 6805, 577,
    3209, 852, 20057, 20001, 5089, 4804, 10796, 8151, 5617, 7545, 11427, 209, 16086,
    719, 33363, 15557, 5650, 133612, 6327, 6128, 5607, 206067, 1267, 26599, 6830,
    8881, 2119, 14593, 28573, 3215, 26615, 3352, 1759, 7303, 11426, 35228, 55836,
    1136, 22085, 11351, 8708, 1257, 3269, 5410, 7418, 23693, 30722, 9299, 13285, 17676,
    3301, 7713, 5391, 33915, 44034, 8374, 4764, 51207, 29447, 27651, 7552, 12389,
    19108, 8359, 11315, 6057, 16591, 12479, 5769, 17072, 31615, 12271, 28403, 11664,
    6147, 15704, 10139, 39603, 12874, 25135, 5483, 5378, 4771, 4775, 12912, 6871,
    12322, 6614, 132199, 5432, 212238, 12929, 27699, 22927, 8473, 2860, 12430, 7029,
    6848, 5645, 8412, 62240, 8447, 15502, 174, 30036, 27747, 14638, 4230, 45727, 9009,
    17639, 4788, 10030, 11492, 45143, 18881, 2516, 21334, 15895, 4766, 16232, 6799,
    8400, 9443, 6079, 13999, 5610, 45899, 4761, 2586, 12353, 20845, 20365, 13280,
    22047, 3243, 4818, 27995, 20055, 24203, 9500, 25255, 45609, 29518, 7992, 39891,
    3303, 4773, 855, 8452, 136787, 18403, 3329, 52341,
  ];
  
  if ($val !== null) {
    return $val;
  }
  
  if (isset($_SERVER['HTTP_X_GEO_ASN'])) {
    $asn = (int)$_SERVER['HTTP_X_GEO_ASN'];
  }
  else {
    $asn = 0;
  }
  
  if (!$asn) {
    return true;
  }
  
  $val = in_array($asn, $whitelist);
  
  return $val;
}

function spam_filter_is_bad_actor() {
  static $cache = null;
  
  if ($cache !== null) {
    return $cache;
  }
  /*
  if (isset($_SERVER['HTTP_X_HTTP_VERSION'])) {
    if (strpos($_SERVER['HTTP_X_HTTP_VERSION'], 'HTTP/1') === 0) {
      $cache = true;
      return true;
    }
  }
  */
  $no_lang = isset($_SERVER['HTTP_ACCEPT_LANGUAGE']) === false;
  $no_accept = isset($_SERVER['HTTP_ACCEPT']) === false;
  
  if ($no_lang && $no_accept) {
    $cache = true;
    return true;
  }
  
  if ($no_lang && strpos($_SERVER['HTTP_USER_AGENT'], '; wv)') !== false) {
    $cache = true;
    return true;
  }
  
  if ($no_accept && strpos($_SERVER['HTTP_USER_AGENT'], 'iPhone') !== false && strpos($_SERVER['HTTP_USER_AGENT'], 'Mobile') === false) {
    $cache = true;
    return true;
  }
  
  if ($no_lang && isset($_SERVER['HTTP_REFERER'])) {
    $ref = $_SERVER['HTTP_REFERER'];
    
    if (strpos($ref, 'sys.4chan.org') !== false || strpos($ref, '/thread/') !== false) {
      $cache = true;
      return true;
    }
  }
  
  $cache = false;
  return false;
}

function spam_filter_get_threat_score($country = null, $is_op = false, $multipart = true/*, &$log = []*/) {
  $increase = [];
  $more = [];
  
  $domain = DEFAULT_BURICHAN ? '4channel' : '4chan';
  
  if (isset($_SERVER['HTTP_USER_AGENT'])) {
    $ua = $_SERVER['HTTP_USER_AGENT'];
  }
  else {
    $ua = '';
  }
  
  if (isset($_SERVER['HTTP_CONTENT_TYPE'])) {
    $content_type = $_SERVER['HTTP_CONTENT_TYPE'];
  }
  else {
    $content_type = '';
  }
  
  if (isset($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
    $accept_lang = $_SERVER['HTTP_ACCEPT_LANGUAGE'];
  }
  else {
    $accept_lang = '';
  }
  
  if (isset($_SERVER['HTTP_ACCEPT'])) {
    $accept_header = $_SERVER['HTTP_ACCEPT'];
  }
  else {
    $accept_header = '';
  }
  
  if (isset($_SERVER['HTTP_REFERER'])) {
    $referer_header = $_SERVER['HTTP_REFERER'];
  }
  else {
    $referer_header = '';
  }
  
  $header_keys = array_keys($_SERVER);
  
  $ua_is_webkit = false;
  
  $check_for_sec_headers = false;
  
  $is_mobile_ua = preg_match('/Android|Mobile/', $ua) || $_SERVER['HTTP_SEC_CH_UA_MOBILE'] === '?1';
  
  $is_brave = false;
  
  if (isset($_SERVER['HTTP_SEC_CH_UA']) && strpos($_SERVER['HTTP_SEC_CH_UA'], 'Brave') !== false) {
    if (isset($_SERVER['HTTP_SEC_GPC'])) {
      $is_brave = true;
    }
  }
  
  // Mobile app (webviews, etc)
  $is_webview = strpos($ua, '; wv') !== false;
  $is_mobile_app = !$accept_header && !$accept_lang && ($is_webview || strpos($referer_header, '/thread/') !== false);
  
  if (!$is_mobile_app && !$accept_header && $accept_lang && strpos($referer_header, 'sys.4chan.org') !== false) {
    $is_mobile_app = true;
  }
  
  if (!$is_mobile_app && strpos($ua, 'Mozilla/') === false && preg_match('/Android|Dalvik|iOS|iPhone/', $ua)) {
    $is_mobile_app = true;
  }
  
  if (!$is_mobile_app && isset($_SERVER['HTTP_X_REQUESTED_WITH']) && preg_match('/floens|adamantcheese|clover/', $_SERVER['HTTP_X_REQUESTED_WITH'])) {
    $is_mobile_app = true;
  }
  
  if (!$is_mobile_app && preg_match('/boundary=[0-9a-f]{8}\-[0-9a-f]{4}\-[0-9a-f]{4}\-[0-9a-f]{4}\-[0-9a-f]{12}$/', $content_type)) {
    if (strpos($ua, 'Android') !== false) {
      $is_mobile_app = true;
    }
    else if (strpos($ua, 'Firefox/') !== false && !$accept_lang) {
      $is_mobile_app = true;
    }
  }
  
  // No UA
  if (!$ua) {
    $increase[] = 0.25;
    //$log[] = 'NO_UA';
  }
  // Firefox
  else if ((strpos($ua, 'Firefox/') !== false || strpos($ua, 'FxiOS/') !== false) && strpos($ua, 'WebKit') === false) {
    // Suspicious Content-Type
    if ($multipart && !$is_mobile_app && !preg_match('/=-+([0-9]+|geckoformboundary[a-f0-9]+)$/i', $content_type)) {
      $increase[] = 0.25;
      //$log[] = 'BAD_CT_FF';
    }
    
    // Suspicious language
    if (!$accept_lang) {
      if (!$is_mobile_app) {
        $increase[] = 0.02;
        $more[] = 0.1;
        //$log[] = 'NO_LANG';
      }
    }
    else if (preg_match('/[a-z]-[a-z]/', $accept_lang)) {
      $increase[] = 0.1;
      //$log[] = 'LC_LANG';
    }
    
    if (isset($_SERVER['HTTP_PRAGMA']) && !isset($_SERVER['HTTP_CACHE_CONTROL'])) {
      $increase[] = 0.35;
      $more[] = 0.1;
      //$log[] = 'FF_PRAGMA';
    }
    
    // Wrong Accept header
    if (strpos($accept_header, 'application/signed-exchange') !== false) {
      $increase[] = 0.15;
      //$log[] = 'FF_SIGEX';
    }
    
    // Old and spoofed versions
    if ($accept_header && preg_match('/(?:Firefox)\/([0-9]+)[^0-9]/', $ua, $m) && strpos($ua, 'PaleMoon') === false) {
      $v = (int)$m[1];
      
      if ($v < 52) {
        $increase[] = 0.2;
        //$log[] = 'OLD_FF';
      }
      else if ($v < 60) {
        $increase[] = 0.1;
        //$log[] = 'OLD_FF';
      }
      else if ($v < 78) {
        $increase[] = 0.01;
        //$log[] = 'OLD_FF';
      }
      else if ($v > 500) {
        $increase[] = 0.5;
        //$log[] = 'FUTURE_FF';
      }
      
      if ($v > 110) {
        $check_for_sec_headers = true;
      }
    }
  }
  // Webkit
  else if (strpos($ua, 'WebKit') !== false) {
    $ua_is_webkit = true;
    $ua_is_chrome = strpos($ua, 'Chrome') !== false;
    
    // Suspicious Content-Type
    if ($multipart && !$is_mobile_app) {
      if (!strpos($content_type, 'WebKit')) {
        $increase[] = 0.25;
        //$log[] = 'BAD_CT_WK';
      }
      else if (strpos($content_type, '-') === false) {
        $increase[] = 0.50;
        //$log[] = 'BAD_CT_DASH';
      }
    }
    
    // Suspicious language
    if (!$accept_lang) {
      if (!$is_mobile_app) {
        $increase[] = 0.02;
        $more[] = 0.1;
        //$log[] = 'NO_LANG';
      }
    }
    else if ($ua_is_chrome && strpos($ua, 'Android') === false && preg_match('/[a-z]-[a-z]/', $accept_lang)) {
      $increase[] = 0.1;
      //$log[] = 'LC_LANG';
    }
    
    // Old and spoofed versions
    if (preg_match('/(?:Chrome)\/([0-9]+)[^0-9]/', $ua, $m)) {
      $v = (int)$m[1];
      
      if ($v < 60) {
        $increase[] = 0.2;
        //$log[] = 'OLD_WK';
      }
      else if ($v < 70) {
        $increase[] = 0.1;
        //$log[] = 'OLD_WK';
      }
      else if ($v < 80) {
        $increase[] = 0.05;
        //$log[] = 'OLD_WK';
      }
      else if ($v > 500) {
        $increase[] = 0.5;
        //$log[] = 'FUTURE_WK';
      }
      
      if ($v > 110) {
        $check_for_sec_headers = true;
      }
    }
    
    if (preg_match('/(?:Safari)\/([0-9]+)/', $ua, $m)) {
      $v = (int)$m[1];
      
      if ($v < 530) {
        $increase[] = 0.5;
        //$log[] = 'OLD_SAFARI';
      }
    }
    
    // iPhone UA too short
    if (strpos($ua, 'iPhone') !== false && strpos($ua, 'Mobile') === false) {
      $increase[] = 0.06;
      //$log[] = 'SHORT_IPHONE';
    }
  }
  // Other
  else {
    if (!$is_mobile_app && $multipart && preg_match('/boundary=[a-zA-Z0-9]+$/', $content_type)) {
      $increase[] = 0.5;
      //$log[] = 'STRANGE_CT';
    }
    
    if (preg_match('/Netscape\/|Opera\b|Camino\/|Trident\/|Presto\/|compatible; MSIE /', $ua)) {
      $increase[] = 0.75;
      //$log[] = 'OLD_UA';
    }
    else if (!$is_mobile_app && strpos($ua, 'Mozilla/') === false) {
      $increase[] = 0.15;
      $more[] = 0.1;
      //$log[] = 'STRANGE_UA';
    }
    
    if (!$is_mobile_app && !$accept_lang) {
      $more[] = 0.25;
      //$log[] = 'NO_LANG';
    }
    
    // UA too short
    if (!$is_mobile_app && (strlen($ua) < 25 || strpos($ua, ' ') === false)) {
      $increase[] = 0.25;
      //$log[] = 'UA_SPOOF';
    }
  }
  
  // Suspicious Content-Type
  if ($multipart) {
    if (strpos($content_type, 'WebKit') && !$ua_is_webkit) {
      $increase[] = 0.25;
      //$log[] = 'BAD_UA_CT_WK';
    }
  }
  
  // Sec-Fetch headers should be together
  // Some iPhones have those separated
  if (!$is_brave && !$is_webview && strpos($ua, 'Chrome') !== false) {
    $_sf_start = false;
    $_sf_end = false;
    
    foreach ($_SERVER as $_hdr => $_value) {
      if (strpos($_hdr, 'HTTP_SEC_FETCH_') === 0) {
        if ($_sf_start && $_sf_end) {
          $increase[] = 0.25;
          //$log[] = 'SPARSE_SEC_FETCH';
          break;
        }
        
        $_sf_start = true;
      }
      else if ($_sf_start) {
        $_sf_end = true;
      }
    }
  }
  
  // HTTP_SEC_FETCH_USER should always be ?1
  if (isset($_SERVER['HTTP_SEC_FETCH_USER']) && $_SERVER['HTTP_SEC_FETCH_USER'] !== '?1') {
    $increase[] = 0.15;
    //$log[] = 'BAD_SEC_FU';
  }
  
  // Unusual Accept header
  if ($accept_header) {
    if (strpos($accept_header, 'text/plain') !== false) {
      $increase[] = 0.05;
      //$log[] = 'ACCEPT_TP';
    }
  }
  
  // Referer is set but is empty
  if (isset($_SERVER['HTTP_REFERER']) && strpos($_SERVER['HTTP_REFERER'], '4chan.org') === false) {
    $increase[] = 0.1;
    //$log[] = 'BAD_REFERER';
  }
  
  // Platform mismatch: client hints vs user agent
  if (isset($_SERVER['HTTP_SEC_CH_UA_PLATFORM']) && $ua) {
    $_ch_platform = $_SERVER['HTTP_SEC_CH_UA_PLATFORM'];
    
    if (strpos($ua, 'Windows') !== false) {
      if (strpos($_ch_platform, 'Windows') === false) {
        $increase[] = 0.5;
        //$log[] = 'CH_BAD_PLATFORM';
      }
    }
    else if (strpos($ua, 'Mac OS') !== false) {
      if (strpos($_ch_platform, 'macOS') === false) {
        $increase[] = 0.5;
        //$log[] = 'CH_BAD_PLATFORM';
      }
    }
    else if (strpos($ua, 'Linux') !== false) {
      if (preg_match('/Linux|Android|BSD/', $_ch_platform) === false) {
        $increase[] = 0.5;
        //$log[] = 'CH_BAD_PLATFORM';
      }
    }
  }
  
  if (isset($_SERVER['HTTP_UPGRADE_INSECURE_REQUESTS']) && $accept_header === '*/*') {
    $increase[] = 0.2;
    $more[] = 0.1;
    //$log[] = 'ACCEPT_UIR';
  }
  
  if (!isset($_SERVER['HTTP_PRAGMA']) && strpos($_SERVER['HTTP_USER_AGENT'], 'iPhone') !== false && strpos($_SERVER['HTTP_USER_AGENT'], 'Safari') === false) {
    $increase[] = 0.09;
    //$log[] = 'BAD_IPHONE_WV';
  }
  
  // Suspicious OS
  if (strpos($ua, 'Windows NT ') !== false) {
    if (preg_match('/Windows NT ([0-9]+)/', $ua, $m) && strpos($ua, 'Mypal/') === false) {
      $v = (int)$m[1];
      
      if ($v < 6) {
        if (strpos($ua, 'Goanna') === false) {
          $increase[] = 0.25;
        }
        else {
          $increase[] = 0.03;
        }
        //$log[] = 'OLD_WIN';
      }
      else if ($v < 10) {
        $increase[] = 0.03;
        //$log[] = 'OLD_WIN';
      }
      else if ($v > 10) {
        $increase[] = 0.5;
        //$log[] = 'FUTURE_WIN';
      }
    }
  }
  else if (strpos($ua, 'Mac OS X ') !== false) {
    if (preg_match('/Mac OS X ([0-9]+)_([0-9]+)/', $ua, $m)) {
      $v_maj = (int)$m[1];
      $v_min = (int)$m[2];
      
      if ($v_maj < 10) {
        $increase[] = 0.5;
        //$log[] = 'OLD_OSX';
      }
      else if ($v_maj == 10) {
        if ($v_min < 7) {
          $increase[] = 0.25;
          //$log[] = 'OLD_OSX';
        }
        else if ($v_min < 12) {
          $increase[] = 0.05;
          //$log[] = 'OLD_OSX';
        }
      }
      else if ($v_maj > 10 && strpos($ua, 'Safari') !== false) {
        $increase[] = 0.30;
        //$log[] = 'FUTURE_OSX';
      }
    }
  }
  else if (strpos($ua, 'Android') !== false) {
    if (preg_match('/Android ([0-9]+)/', $ua, $m)) {
      $v = (int)$m[1];
      
      if ($v < 4) {
        $increase[] = 0.25;
        //$log[] = 'OLD_DROID';
      }
      else if ($v < 8) {
        $increase[] = 0.05;
        //$log[] = 'OLD_DROID';
      }
      else if ($v > 20) {
        $increase[] = 0.5;
        //$log[] = 'FUTURE_DROID';
      }
    }
    
    if (strpos($ua, 'Win64;') !== false) {
      $increase[] = 0.25;
      //$log[] = 'OS_SOUP';
    }
  }
  
  // Spoofed OS
  if (preg_match('/Mozilla|Firefox|Chrome/', $ua) && !preg_match('/Windows NT|Android|Linux|Mac|iOS|X11;|BSD|Nintendo|PlayStation|Steam/', $ua)) {
    $increase[] = 0.20;
    //$log[] = 'NO_OS';
  }
  
  // Non-browser user agents
  if (preg_match('/headless|node-fetch|python-|java\/|jakarta|-perl|http-?client|-resty-|awesomium\//i', $ua)) {
    $increase[] = 1.0;
    //$log[] = 'NOT_BROWSER';
  }
  
  // Wrong content type
  if ($multipart) {
    // Posting
    if ($_SERVER['HTTP_CONTENT_TYPE'] === 'application/x-www-form-urlencoded') {
      $increase[] = 0.75;
      //$log[] = 'BAD_CT_MP';
    }
  }
  else if (!$is_mobile_app) {
    // Reporting
    if ($_SERVER['HTTP_CONTENT_TYPE'] !== 'application/x-www-form-urlencoded') {
      $increase[] = 0.75;
      //$log[] = 'BAD_CT_NMP';
    }
  }
  
  // Unusual headers
  if (isset($_SERVER['HTTP_VARY'])) {
    $increase[] = 0.2;
    //$log[] = 'VARY_HDR';
  }
  
  if (isset($_SERVER['HTTP_PATH']) || isset($_SERVER['HTTP_SAME_ORIGIN']) || isset($_SERVER['HTTP_REFERRER_POLICY'])) {
    $increase[] = 0.8;
    //$log[] = 'USELESS_HDR';
  }
  
  if (!$is_mobile_app && !$is_webview) {
    if (isset($_SERVER['HTTP_SEC_FETCH_MODE']) && $_SERVER['HTTP_SEC_FETCH_MODE'] === 'navigate') {
      // Only threads should be posted using the default form
      if (!$is_op && !$is_mobile_ua) {
        $increase[] = 0.02;
        $more[] = 0.10;
        //$log[] = 'BAD_OP_SFM';
      }
      
      // Model hints are never sent when using the default form
      if (isset($_SERVER['HTTP_SEC_CH_UA_MODEL']) && !isset($_SERVER['HTTP_SEC_CH_UA_BITNESS'])) {
        $increase[] = 0.1;
        $more[] = 0.20;
        //$log[] = 'MODEL_NAV';
      }
    }
  }
  
  // iPhone fetch site none
  if (strpos($ua, 'like Mac OS') && isset($_SERVER['HTTP_SEC_FETCH_SITE']) && $_SERVER['HTTP_SEC_FETCH_SITE'] === 'none') {
    $increase[] = 0.08;
    $more[] = 0.10;
    //$log[] = 'IOS_FSN';
  }
  
  // No cookies
  if (!$is_mobile_app) {
    if (!isset($_SERVER['HTTP_COOKIE']) || !$_SERVER['HTTP_COOKIE']) {
      $increase[] = 0.05;
      $more[] = 0.25;
      //$log[] = 'NO_COOKIE';
    }
    else if (count($_COOKIE) === 1 && isset($_COOKIE['cf_clearance'])) {
      $increase[] = 0.05;
      $more[] = 0.25;
      //$log[] = 'NO_COOKIE';
    }
  }
  
  // Timezones and Time
  if (isset($_COOKIE['_tcs'])) {
    list($_time, $_tz, $_time_s, $_tcs_v) = explode('.', $_COOKIE['_tcs']);
    
    if (!$_tcs_v) {
      $increase[] = 0.09;
      //$log[] = 'BAD_TCS';
    }
    else {
      if (!$is_webview && strpos($ua, 'Chrome/') !== false && $_tcs_v != 33) {
        $increase[] = 0.09;
        //$log[] = 'BAD_TCS_CR';
      }
    }
    
    if ($_time_s && isset($_POST['t-challenge']) && $_POST['t-challenge'] !== 'noop' && $_POST['t-response']) {
      $_d = $_SERVER['REQUEST_TIME'] - $_time_s;
      
      if ($_d > 0 && $_d < 2) {
        $increase[] = 1.0;
        //$log[] = 'FAST_TCS';
      }
    }
    
    if (isset($_SERVER['HTTP_X_TIMEZONE'])) {
      $_tz0 = explode('/', $_tz, 2)[0];
      if ($_tz0) {
        if ($_tz0 === 'UTC') {
          $increase[] = 0.02;
          $more[] = 0.02;
          //$log[] = 'UTC_TZ';
        }
        else if ($_tz0 === 'Etc') {
          $increase[] = 0.01;
          $more[] = 0.01;
          //$log[] = 'ETC_TZ';
        }
        else if (strpos($_SERVER['HTTP_X_TIMEZONE'], $_tz0) !== 0) {
          if (strpos($_tz0, 'Atlantic') === false || strpos($_SERVER['HTTP_X_TIMEZONE'], 'Europe') === false) {
            $increase[] = 0.03;
            $more[] = 0.03;
            //$log[] = 'BAD_TZ';
          }
        }
      }
    }
  }
  
  // No Accept
  if (!$accept_header && !$is_mobile_app) {
    $increase[] = 0.15;
    //$log[] = 'NO_ACCEPT';
  }
  
  // No SEC
  if ($check_for_sec_headers && !$is_mobile_app) {
    if (!isset($_SERVER['HTTP_SEC_FETCH_DEST']) || !isset($_SERVER['HTTP_SEC_FETCH_MODE']) || !isset($_SERVER['HTTP_SEC_FETCH_SITE'])) {
      $increase[] = 0.15;
      //$log[] = 'NO_SEC';
    }
  }
  
  // HTTP 1.1
  if (isset($_SERVER['HTTP_X_HTTP_VERSION']) && $_SERVER['HTTP_X_HTTP_VERSION'] === 'HTTP/1.1') {
    $more[] = 0.1;
    //$log[] = 'HTTP1';
  }
  
  // Spoofed device model
  if (isset($_SERVER['HTTP_SEC_CH_UA_MODEL']) && isset($_SERVER['HTTP_SEC_CH_UA_PLATFORM'])) {
    $_k1 = (int)array_search('HTTP_SEC_CH_UA_MODEL', $header_keys);
    $_k2 = (int)array_search('HTTP_SEC_CH_UA_PLATFORM', $header_keys);
    
    if (abs($_k1 - $_k2) > 5) {
      $increase[] = 0.05;
      $more[] = 0.01;
      //$log[] = 'FAR_MODEL';
    }
  }
  
  if ($country) {
    // Language is set but doesn't match the country
    if ($accept_lang && strpos($accept_lang, 'en') !== 0) {
      $lang_regex = get_lang_regex_from_country($country);
      
      if ($lang_regex && !preg_match($lang_regex, $accept_lang)) {
        $more[] = 0.025;
        $increase[] = 0.025;
        //$log[] = 'LANG_MISMATCH';
      }
      
      // No quality in lang
      if (!preg_match('/iPhone|iPad/', $ua)) {
        if ($accept_lang && strpos($accept_lang, ';') === false) {
          $more[] = 0.025;
          $increase[] = 0.025;
          //$log[] = 'LANG_NOQ';
        }
      }
    }
    
    // Highly suspicious countries
    $countries_0 = array(
      'AD','AE','AF','AG','AI','AL','AM','AN','AO','AQ','AS','AW','AX','AZ',
      'BB','BD','BF','BH','BI','BJ','BL','BM','BN','BO','BQ','BS','BT','BV','BW','BZ',
      'CC','CD','CF','CG','CI','CK','CM','CN','CR','CU','CV','CW','CX',
      'DJ','DM','DO','DZ',
      'EC','EG','EH','ER','ET',
      'FJ','FK','FM','FO',
      'GA','GD','GF','GG','GH','GI','GM','GN','GP','GQ','GS','GT','GU','GW','GY',
      'HK','HM','HN','HT',
      'IM','IO','IQ','IR','JE','JM','JO','KE','KG','KH','KI','KM','KN','KP','KW','KY','KZ',
      'LA','LB','LC','LK','LR','LS','LY',
      'MA','MD','MF','MG','MH','ML','MM','MN','MO','MP','MQ','MR','MS','MU','MV','MW','MZ',
      'NA','NC','NE','NF','NG','NI','NP','NR','NU',
      'OM','PA','PF','PG','PK','PM','PN','PS','PW','PY','QA','RE','RW',
      'SA','SB','SC','SD','SH','SJ','SL','SM','SN','SO','SR','SS','ST','SV','SX','SY','SZ',
      'TC','TD','TF','TG','TJ','TK','TM','TN','TO','TP','TR','TT','TV','TZ',
      'UG','UM','UZ','VA','VC','VG','VI','VU','WF','WS','YE','YT','YU','ZM','ZW','XX'
    );
    
    // Less suspicious countries
    $countries_1 = array(
      'BR','VE','AR','CL','UY','CO','PE','MX',
      'UA','BA','RU','MC','MK','CY','MT','ME','KR','JP','TH','VN','ID'
    );
    
    if (in_array($country, $countries_0)) {
      $more[] = 0.30;
      //$log[] = 'SUSP_COUNTRY_0';
    }
    else if (in_array($country, $countries_1)) {
      $more[] = 0.10;
      //$log[] = 'SUSP_COUNTRY_1';
    }
  }
  
  $score = 0.0;
  
  if (!empty($increase)) {
    $score += array_sum($increase);
  }
  
  if ($score > 0.0 && !empty($more)) {
    foreach ($more as $r) {
      $score *= (1.0 + $r);
    }
  }
  
  return round($score, 2);
}

/**
 * Necrobumping prevention checks
 */
function spam_filter_can_bump_thread($thread_root) {
  $userpwd = UserPwd::getSession();
  
  if (!$userpwd || !$thread_root) {
    return true;
  }
  
  if ($userpwd->maskLifetime() >= 21600) { // 6 hours
    return true;
  }
  
  $thres = (int)(PAGE_MAX * DEF_PAGES * 0.85);
  
  if ($thres <= 0) {
    return true;
  }
  
  $sql = "SELECT COUNT(*) FROM `%s` WHERE resto = 0 AND archived = 0 AND root > '%s'";
  
  $res = mysql_board_call($sql, BOARD_DIR, $thread_root);
  
  if (!$res) {
    return true;
  }
  
  $pos = (int)mysql_fetch_row($res)[0];
  
  if ($pos < $thres) {
    return true;
  }
  
  // Check the IP if cookies are blocked
  if (spam_filter_is_ip_known(ip2long($_SERVER['REMOTE_ADDR']), BOARD_DIR, 0, 720)) {
    return true;
  }
  
  return false;
}

/**
 * Returns true if the uploaded file had multiple previous bans for it
 * and should be blocked
 */
function check_for_banned_upload($md5) {
  if (!$md5 || BOARD_DIR === 'b') {
    return false;
  }
  
  // 6 : Global 5 - NWS on Worksafe Board
  // 226 : Global 3 - Loli/shota pornography
  $template_clause = '226';
  
  if (DEFAULT_BURICHAN) {
    $template_clause .= ',6';
  }
  
  $sql = <<<SQL
SELECT COUNT(*) as cnt FROM banned_users WHERE md5 = '%s'
AND template_id IN($template_clause) LIMIT 1
SQL;

  $res = mysql_global_call($sql, $md5);
  
  if (!$res) {
    return false;
  }
  
  $count = (int)mysql_fetch_row($res)[0];
  
  if ($count >= 3) {
    return true;
  }
  
  return false;
}

function spam_filter_is_likely_automated($memcached = null, $threshold = 29) {
  if (!isset($_SERVER['HTTP_X_BOT_SCORE'])) {
    return false;
  }
  
  $ua = $_SERVER['HTTP_USER_AGENT'];
  
  // Skip Android Webviews
  if (strpos($ua, '; wv)') !== false) {
    return false;
  }
  
  // Skip iPhone Webviews
  if (preg_match('/iPhone|iPad/', $ua) && !preg_match('/Mobile|Safari/', $ua)) {
    return false;
  }
  
  $score = (int)$_SERVER['HTTP_X_BOT_SCORE'];
  
  if ($score > 0 && $score <= $threshold) {
    return true;
  }
  
  if ($memcached) {
    $key = 'bmbot' . $_SERVER['REMOTE_ADDR'];
    
    if ($memcached->get($key)) {
      return true;
    }
  }
  
  return false;
}

function spam_filter_is_pwd_blocked($pwd, $type, $hours = 24) {
  if (!$pwd || !$type || $hours <= 0 || $hours > 720) {
    return false;
  }
  
  $hours = (int)$hours;
  
  $sql =<<<SQL
SELECT 1 FROM event_log
WHERE `type` = '%s' AND pwd = '%s'
AND created_on > DATE_SUB(NOW(), INTERVAL $hours HOUR)
LIMIT 1
SQL;

  $res = mysql_global_call($sql, $type, $pwd);
  
  if (!$res) {
    return false;
  }
  
  return mysql_num_rows($res) === 1;
}

function spam_filter_has_country_changed($pwd) {
  if (!$pwd) {
    return false;
  }
  
  $sql =<<<SQL
SELECT 1 FROM event_log
WHERE `type` = 'country_changed' AND pwd = '%s'
AND created_on > DATE_SUB(NOW(), INTERVAL 24 HOUR)
LIMIT 1
SQL;

  $res = mysql_global_call($sql, $pwd);
  
  if (!$res) {
    return false;
  }
  
  return mysql_num_rows($res) === 1;
}

// Logs posts made by new users.
// Returns 1 if the posting rate is above limits.
function spam_filter_is_post_flood($ip, $userpwd, $board, $thread_id, $phash) {
  $user_is_known = $userpwd && $userpwd->isUserKnownOrVerified(60);
  
  if ($user_is_known) {
    return 0;
  }
  
  $thread_id = (int)$thread_id;
  
  // Per thread reply flood
  if ($thread_id) {
    $interval_minutes = (int)ANTIFLOOD_INTERVAL_REPLY;
    $threshold = (int)ANTIFLOOD_THRES_REPLY;
  }
  // OP flood
  else {
    $interval_minutes = (int)ANTIFLOOD_INTERVAL_OP;
    $threshold = (int)ANTIFLOOD_THRES_OP;
  }
  
  if (!$interval_minutes || !$threshold) {
    return 0;
  }
  
  $long_ip = ip2long($ip);
  
  if (!$long_ip) {
    return 0;
  }
  
  $tbl = 'flood_log';
  
  // Prune old entries
  $sql = "DELETE FROM `$tbl` WHERE created_on < DATE_SUB(NOW(), INTERVAL 24 HOUR)";
  mysql_global_call($sql);
  
  // Count flood entries
  $ret_val = 0;
  
  $sql = <<<SQL
SELECT COUNT(*) FROM `$tbl`
WHERE ip != '%s'
AND created_on >= DATE_SUB(NOW(), INTERVAL $interval_minutes MINUTE)
AND board = '%s'
AND thread_id = $thread_id
SQL;
  
  $res = mysql_global_call($sql, $ip, $board);
  
  if ($res) {
    $count = (int)mysql_fetch_row($res)[0];
    
    if ($count >= $threshold) {
      $ret_val = 1;
    }
  }
  
  // Insert new entry
  $ua_sig = spam_filter_get_browser_id();
  $req_sig = spam_filter_get_req_sig();
  
  $sql = <<<SQL
INSERT INTO `$tbl` (board, thread_id, ip, phash, ua_sig, req_sig)
VALUES ('%s', $thread_id, '%s', '%s', '%s', '%s')
SQL;
  
  $res = mysql_global_call($sql, $board, $ip, $phash, $ua_sig, $req_sig);
  
  return $ret_val;
}

function spam_filter_post_trip($name, $trip, $dest) {
	$normalized_name = normalize_text($name);
	if(stripos($normalized_name, 'moot') !== FALSE && ($trip == 'Ep8pui8Vw2' || stripos($normalized_name, 'ep8pui8vw2') !== FALSE) && !valid())
	{
		error(S_FAILEDUPLOAD, $dest);
	}

	if(preg_match("/[l|i|1][o|0][l|i|1]{2}c[o|0][n|m]/i", $trip))
	{
		error(S_BANNEDTEXT, $dest);
	}
}

// check a comment for fileshare content and put it in the rapidsearch queue
function rapidsearch_check($board, $no, $body) {
	if( strlen( $body ) < 25 ) return;
	
	$body = strtolower($body);
	$body = strip_tags($body);
	
	$matches = array(
		'rapidshare.de/',
		'rapidshare.com/',
		'rapidshit.com/files/',
		'savefile.com/files',
		'megaupload.com/',
		'sharebigfile.com/download.php',
		'sendspace.com/file',
		'turboupload.com/d',
		'bigupload.com',
		'filefactory.com',
		'bandongo',
		'easy-sharing',
		'easy-share',
		'sexuploader',
		'rapidsharing.com',
		'uploadgalaxy',
		'up-file.com',
		'yousendit',
		'gigeshare',
		'gigasize',
		'depositfiles',
		'megarotic.com',
		'filewind.com',
		'mediafire.com',
		'xtremeuploader.com',
		'fileden.com',
		'zenixstudios.com',
		'megashare.com',
		'zshare.net',
		'megashare.com',
		'massmirror'
	);
	
	// oh my god the code before this
	foreach( $matches as $match ) {
		if( strpos( $body, $match ) !== false ) {
			rapidsearch_insert($board, $no, $body);
			return;
		}
	}
}

function trans_similar_to_ascii(&$str, $char) {
  // UTF-16
	$ord = mb_ord($char);
	
	switch ($ord)
	{
		case 0x2070:
		case 0x2080:
		case 0xFF10:
			$str .= chr(0x30); 		/* 0 */
			break;
		case 0x00B9:
		case 0x2081:
		case 0xFF11:
			$str .= chr(0x31);		/* 1 */
			break;
		case 0x00B2:
		case 0x2082:
		case 0xFF12:
			$str .= chr(0x32);
			break;
		case 0x00B3:
		case 0x2083:
		case 0xFF13:
			$str .= chr(0x33);
			break;
		case 0x2074:
		case 0x2084:
		case 0xFF14:
			$str .= chr(0x34);
			break;
		case 0x2075:
		case 0x2085:
		case 0xFF15:
			$str .= chr(0x35);
			break;
		case 0x2076:
		case 0x2086:
		case 0xFF16:
			$str .= chr(0x36);
			break;
		case 0x2077:
		case 0x2087:
		case 0xFF17:
			$str .= chr(0x37);
			break;
		case 0x2078:
		case 0x2088:
		case 0xFF18:
			$str .= chr(0x38);
			break;
		case 0x2079:
		case 0x2089:
		case 0xFF19:
			$str .= chr(0x39);
			break;
		case 0xFE57:
		case 0xFF01:		/* Exclamation mark */
			$str .= chr(0x21);
			break;
		case 0xFF02:		/* Quotation mark */
			$str .= chr(0x22);
			break;
		case 0xFE5F:
		case 0xFF03:		/* Number sign */
			$str .= chr(0x23);
			break;
		case 0xFE69:
		case 0xFF04:		/* Dollar sign */
			$str .= chr(0x24);
			break;
		case 0xFE6A:
		case 0xFF05:		/* Percent sign */
			$str .= chr(0x25);
			break;
		case 0xFE60:
		case 0xFF06:		/* Ampersand	*/
			$str .= chr(0x26);
			break;
		case 0xFF07:		/* Apostrophe */
			$str .= chr(0x27);
			break;
		case 0x207D:
		case 0x208D:
		case 0xFE59:
		case 0xFF08:		/* Left parenthesis */
			$str .= chr(0x28);
			break;
		case 0x207E:
		case 0x208E:
		case 0xFE5A:
		case 0xFF09:		/* Right parenthesis */
			$str .= chr(0x29);
			break;
		case 0xFE61:
		case 0xFF0A:		/* Asterisk */
			$str .= chr(0x2A);
			break;
		case 0x207A:
		case 0x208A:
		case 0xFE62:
		case 0xFF0B:		/* Plus sign */
			$str .= chr(0x2B);
			break;
		case 0xFE50:
		case 0xFF0C:		/* Comma */
			$str .= chr(0x2C);
			break;
		case 0x207B:
		case 0x208B:
		case 0xFE63:
		case 0xFF0D:		/* Hyphen */
			$str .= chr(0x2D);
			break;
		case 0xFE52:
		case 0xFF0E:		/* Full stop */
			$str .= chr(0x2E);
			break;
		case 0xFF0F:		/* Solidus */
			$str .= chr(0x2F);
			break;
		case 0xFE55:
		case 0xFF1A:		/* Colon */
			$str .= chr(0x3A);
			break;
		case 0xFE54:
		case 0xFF1B:		/* Semicolon */
			$str .= chr(0x3B);
			break;
		case 0xFE64:
		case 0xFF1C:		/* Less than sign */
			$str .= chr(0x3C);
			break;
		case 0x207C:
		case 0x208C:
		case 0xFE66:
		case 0xFF1D:
			$str .= chr(0x3D);		/* Equals sign */
			break;
		case 0xFE65:
		case 0xFF1E:		/* Greater than sign */
			$str .= chr(0x3E);
			break;
		case 0xFE56:
		case 0xFF1F:		/* Question mark */
			$str .= chr(0x3F);
			break;
		case 0xFE6B:
		case 0xFF20:		/* At sign */
			$str .= chr(0x40);
			break;
		case 0x0410:
		case 0xFF21:
		case 0x1D400:
		case 0x1D434:
		case 0x1D468:
		case 0x1D49C:
		case 0x1D4D0:
		case 0x1D504:
		case 0x1D538:
		case 0x1D56C:
		case 0x1D5A0:
		case 0x1D5D4:
		case 0x1D63C:
		case 0x1D670:
			$str .= chr(0x41); /* A */
			break;
		case 0x0042:		/* B */
		case 0x0412:
		case 0x212C:
		case 0xFF22:
		case 0x1D401:
		case 0x1D435:
		case 0x1D469:
		case 0x1D4D1:
		case 0x1D505:
		case 0x1D539:
		case 0x1D56D:
		case 0x1D5A1:
		case 0x1D5D5:
		case 0x1D609:
		case 0x1D63D:
		case 0x1D671:
			$str .= chr(0x42);
			break;
		case 0x0421:
		case 0x2102:
		case 0x212D:
		case 0xFF23:
		case 0x1D402:
		case 0x1D436:
		case 0x1D46A:
		case 0x1D49E:
		case 0x1D4D2:
		case 0x1D56E:
		case 0x1D5A2:
		case 0x1D5D6:
		case 0x1D60A:
		case 0x1D63E:
		case 0x1D672:
			$str .= chr(0x43);		/* C */
			break;
		case 0x2145:
		case 0xFF24:
		case 0x1D403:
		case 0x1D437:
		case 0x1D46B:
		case 0x1D49F:
		case 0x1D4D3:
		case 0x1D507:
		case 0x1D53B:
		case 0x1D56F:
		case 0x1D5A3:
		case 0x1D5D7:
		case 0x1D60B:
		case 0x1D63F:
		case 0x1D673:
			$str .= chr(0x44);		/* D */
			break;
		case 0x2130:		/* E*/
		case 0x0415:
		case 0xFF25:
		case 0x1D404:
		case 0x1D438:
		case 0x1D46C:
		case 0x1D4D4:
		case 0x1D508:
		case 0x1D53C:
		case 0x1D570:
		case 0x1D5A4:
		case 0x1D5D8:
		case 0x1D60C:
		case 0x1D640:
		case 0x1D674:
			$str .= chr(0x45);
			break;
		case 0x2131:
		case 0x213F:
		case 0xFF26:
		case 0x1D405:
		case 0x1D439:
		case 0x1D46D:
		case 0x1D4D5:
		case 0x1D509:
		case 0x1D53D:
		case 0x1D571:
		case 0x1D5A5:
		case 0x1D5D9:
		case 0x1D60D:
		case 0x1D641:
		case 0x1D675:
			$str .= chr(0x46);		/* F */
			break;
		case 0x0262:
		case 0xFF27:
		case 0x1D406:
		case 0x1D43A:
		case 0x1D46E:
		case 0x1D4A2:
		case 0x1D4D6:
		case 0x1D50A:
		case 0x1D53E:
		case 0x1D572:
		case 0x1D5A6:
		case 0x1D5DA:
		case 0x1D60E:
		case 0x1D642:
		case 0x1D676:
			$str .= chr(0x47);		/* G */
			break;
		case 0x029C:
		case 0x041D:
		case 0x210B:
		case 0x210C:
		case 0x210D:
		case 0xFF28:
		case 0x1D407:
		case 0x1D43B:
		case 0x1D46F:
		case 0x1D4D7:
		case 0x1D573:
		case 0x1D5A7:
		case 0x1D5DB:
		case 0x1D60F:
		case 0x1D643:
		case 0x1D677:
			$str .= chr(0x48);		/* H */
			break;
		case 0x0049:
		case 0x0406:
		case 0x2110:
		case 0x2111:
		case 0x2160:
		case 0xFF29:
		case 0x1D408:
		case 0x1D43C:
		case 0x1D470:
		case 0x1D4D8:
		case 0x1D540:
		case 0x1D574:
		case 0x1D5A8:
		case 0x1D5DC:
		case 0x1D610:
		case 0x1D644:
		case 0x1D678:
			$str .= chr(0x49);		/* I */
			break;
		case 0x026A:
		case 0x0408:
		case 0xFF2A:
		case 0x1D409:
		case 0x1D43D:
		case 0x1D471:
		case 0x1D4D9:
		case 0x1D541:
		case 0x1D575:
		case 0x1D5A9:
		case 0x1D5DD:
		case 0x1D611:
		case 0x1D645:
		case 0x1D679:
			$str .= chr(0x4A);		/* J */
			break;
		case 0xFF2B:
		case 0x1D40A:
		case 0x1D43E:
		case 0x1D472:
		case 0x1D4A5:
		case 0x1D4DA:
		case 0x1D50D:
		case 0x1D542:
		case 0x1D576:
		case 0x1D5AA:
		case 0x1D5DE:
		case 0x1D612:
		case 0x1D646:
		case 0x1D67A:
			$str .= chr(0x4B);		/* K */
			break;
		case 0xFF2C:
		case 0x1D40B:
		case 0x1D43F:
		case 0x1D473:
		case 0x1D4A6:
		case 0x1D4DB:
		case 0x1D50E:
		case 0x1D543:
		case 0x1D577:
		case 0x1D5AB:
		case 0x1D5DF:
		case 0x1D613:
		case 0x1D647:
		case 0x1D67B:
			$str .= chr(0x4C);		/* L */
			break;
		case 0x029F:
		case 0x041C:
		case 0xFF2D:
		case 0x2112:
		case 0x1D40C:
		case 0x1D440:
		case 0x1D474:
		case 0x1D4DC:
		case 0x1D50F:
		case 0x1D544:
		case 0x1D578:
		case 0x1D5AC:
		case 0x1D5E0:
		case 0x1D614:
		case 0x1D648:
		case 0x1D67C:
			$str .= chr(0x4D);		/* M */
			break;
		case 0x2133:
		case 0xFF2E:
		case 0x1D40D:
		case 0x1D441:
		case 0x1D475:
		case 0x1D4A9:
		case 0x1D4DD:
		case 0x1D511:
		case 0x1D579:
		case 0x1D5AD:
		case 0x1D5E1:
		case 0x1D615:
		case 0x1D649:
		case 0x1D67D:
			$str .= chr(0x4E);		/* N */
			break;
		case 0x004F:
		//case 0x00D8:
		case 0x041E:
		case 0x2205:
		case 0xFF2F:
		case 0x1D40E:
		case 0x1D442:
		case 0x1D476:
		case 0x1D4AA:
		case 0x1D4DE:
		case 0x1D512:
		case 0x1D546:
		case 0x1D57A:
		case 0x1D5AE:
		case 0x1D5E2:
		case 0x1D616:
		case 0x1D64A:
		case 0x1D67E:
			$str .= chr(0x4F);		/* O */
			break;
		case 0x0420:
		case 0x2118:
		case 0x2119:
		case 0xFF30:
		case 0x1D40F:
		case 0x1D443:
		case 0x1D477:
		case 0x1D4AB:
		case 0x1D4DF:
		case 0x1D513:
		case 0x1D57B:
		case 0x1D5AF:
		case 0x1D5E3:
		case 0x1D617:
		case 0x1D64B:
		case 0x1D67F:
			$str .= chr(0x50);		/* P */
			break;
		case 0x211A:
		case 0xFF31:
		case 0x1D410:
		case 0x1D444:
		case 0x1D478:
		case 0x1D4AC:
		case 0x1D4E0:
		case 0x1D514:
		case 0x1D57C:
		case 0x1D5B0:
		case 0x1D5E4:
		case 0x1D618:
		case 0x1D64C:
		case 0x1D680:
			$str .= chr(0x51);		/* Q */
			break;
		case 0x0280:
		case 0x211B:
		case 0x211C:
		case 0x211D:
		case 0xFF32:
		case 0x1D411:
		case 0x1D445:
		case 0x1D479:
		case 0x1D4E1:
		case 0x1D57D:
		case 0x1D5B1:
		case 0x1D5E5:
		case 0x1D619:
		case 0x1D64D:
		case 0x1D681:
			$str .= chr(0x52);		/* R */
			break;
		case 0x0405:
		case 0xFF33:
		case 0x1D412:
		case 0x1D446:
		case 0x1D47A:
		case 0x1D4AE:
		case 0x1D4E2:
		case 0x1D516:
		case 0x1D54A:
		case 0x1D57E:
		case 0x1D5B2:
		case 0x1D5E6:
		case 0x1D61A:
		case 0x1D64E:
		case 0x1D682:
			$str .= chr(0x53);		/* S */
			break;
		case 0x0422:
		case 0xFF34:
		case 0x1D413:
		case 0x1D447:
		case 0x1D47B:
		case 0x1D4AF:
		case 0x1D4E3:
		case 0x1D517:
		case 0x1D54B:
		case 0x1D57F:
		case 0x1D5B3:
		case 0x1D5E7:
		case 0x1D61B:
		case 0x1D64F:
		case 0x1D683:
			$str .= chr(0x54);		/* T */
			break;
		case 0x0055:
		case 0xFF35:
		case 0x1D414:
		case 0x1D448:
		case 0x1D47C:
		case 0x1D4B0:
		case 0x1D4E4:
		case 0x1D518:
		case 0x1D54C:
		case 0x1D580:
		case 0x1D5B4:
		case 0x1D5E8:
		case 0x1D61C:
		case 0x1D650:
		case 0x1D684:
			$str .= chr(0x55);		/* U */
			break;
		case 0xFF36:
		case 0x1D415:
		case 0x1D449:
		case 0x1D47D:
		case 0x1D4B1:
		case 0x1D4E5:
		case 0x1D519:
		case 0x1D54D:
		case 0x1D581:
		case 0x1D5B5:
		case 0x1D5E9:
		case 0x1D61D:
		case 0x1D651:
		case 0x1D685:
			$str .= chr(0x56);		/* V */
			break;
		case 0x051C:
		case 0xFF37:
		case 0x1D416:
		case 0x1D44A:
		case 0x1D47E:
		case 0x1D4B2:
		case 0x1D4E6:
		case 0x1D51A:
		case 0x1D54E:
		case 0x1D582:
		case 0x1D5B6:
		case 0x1D5EA:
		case 0x1D61E:
		case 0x1D652:
		case 0x1D686:
			$str .= chr(0x57);		/* W */
			break;
		case 0xFF38:
		case 0x1D417:
		case 0x1D44B:
		case 0x1D47F:
		case 0x1D4B3:
		case 0x1D4E7:
		case 0x1D51B:
		case 0x1D54F:
		case 0x1D583:
		case 0x1D5B7:
		case 0x1D5EB:
		case 0x1D61F:
		case 0x1D653:
		case 0x1D687:
			$str .= chr(0x58);		/* X */
			break;
		case 0x0059:
		case 0x2144:
		case 0xFF39:
		case 0x1D418:
		case 0x1D44C:
		case 0x1D480:
		case 0x1D4B4:
		case 0x1D4E8:
		case 0x1D51C:
		case 0x1D550:
		case 0x1D584:
		case 0x1D5B8:
		case 0x1D5EC:
		case 0x1D620:
		case 0x1D654:
		case 0x1D688:
			$str .= chr(0x59);		/* Y */
			break;
		case 0x29F5:
		case 0x29F9:
		case 0xFE68:
			$str .= chr(0x5C);		/* Backslash */
			break;
		case 0x0430; // CYRILLIC SMALL LETTER A
		case 0x2124:
		case 0x2128:
		case 0xFF3A:
		case 0xFF41:
		case 0x1D419:
		case 0x1D44D:
		case 0x1D482:
		case 0x1D656:
		case 0x1D68A:
			$str .= chr(0x61);		/* a */
			break;
		case 0xFF42:		/* b */
		case 0x1D41B:
		case 0x1D44F:
		case 0x1D483:
		case 0x1D4B7:
		case 0x1D4EB:
		case 0x1D51F:
		case 0x1D553:
		case 0x1D587:
		case 0x1D5BB:
		case 0x1D5EF:
		case 0x1D623:
		case 0x1D657:
		case 0x1D68B:
			$str .= chr(0x62);
			break;
		case 0x0063:		/* c */
		case 0x1D04:
		case 0x441: //CYRILLIC SMALL LETTER ES
		case 0xFF43:
		case 0x1D41C:
		case 0x1D450:
		case 0x1D484:
		case 0x1D4B8:
		case 0x1D4EC:
		case 0x1D520:
		case 0x1D554:
		case 0x1D588:
		case 0x1D5BC:
		case 0x1D5F0:
		case 0x1D624:
		case 0x1D658:
		case 0x1D68C:
			$str .= chr(0x63);
			break;
		case 0x0064:		/* d */
		case 0x1D05:
		case 0x2146:
		case 0xFF44:
		case 0x1D41D:
		case 0x1D451:
		case 0x1D485:
		case 0x1D4B9:
		case 0x1D4ED:
		case 0x1D521:
		case 0x1D555:
		case 0x1D589:
		case 0x1D5BD:
		case 0x1D5F1:
		case 0x1D625:
		case 0x1D659:
		case 0x1D68D:
			$str .= chr(0x64);
			break;
		case 0x0065:
		case 0x0435: // CYRILLIC SMALL LETTER IE
		case 0x1D07:
		case 0x212F:
		case 0x2147:
		case 0xFF45:
		case 0x1D41E:
		case 0x1D452:
		case 0x1D486:
		case 0x1D4EE:
		case 0x1D522:
		case 0x1D556:
		case 0x1D5BE:
		case 0x1D5F2:
		case 0x1D626:
		case 0x1D65A:
		case 0x1D68E:
			$str .= chr(0x65); /* e */
			break;
		case 0x0066:		/* f */
		case 0xFF46:
		case 0x1D41F:
		case 0x1D453:
		case 0x1D487:
		case 0x1D4BB:
		case 0x1D4EF:
		case 0x1D523:
		case 0x1D557:
		case 0x1D58B:
		case 0x1D5BF:
		case 0x1D5F3:
		case 0x1D627:
		case 0x1D65B:
		case 0x1D68F:
			$str .= chr(0x66);
			break;
		case 0x0067:		/* g */
		case 0xFF47:
		case 0x210A:
		case 0x1D420:
		case 0x1D454:
		case 0x1D488:
		case 0x1D4F0:
		case 0x1D524:
		case 0x1D558:
		case 0x1D58C:
		case 0x1D5C0:
		case 0x1D5F4:
		case 0x1D628:
		case 0x1D65C:
		case 0x1D690:
			$str .= chr(0x67);
			break;
		case 0x0068:		/* h */
		case 0x04BB:
		case 0xFF48:
		case 0x1D421:
		case 0x1D489:
		case 0x1D4BD:
		case 0x1D4F1:
		case 0x1D525:
		case 0x1D559:
		case 0x1D58D:
		case 0x1D5C1:
		case 0x1D5F5:
		case 0x1D629:
		case 0x1D65D:
		case 0x1D691:
			$str .= chr(0x68);
			break;
		case 0x0456:
		case 0x1D09:
		case 0x2071:
		case 0xFF49:
		case 0x2148:
		case 0x1D422:
		case 0x1D456:
		case 0x1D48A:
		case 0x1D4BE:
		case 0x1D4F2:
		case 0x1D526:
		case 0x1D55A:
		case 0x1D58E:
		case 0x1D5C2:
		case 0x1D5F6:
		case 0x1D62A:
		case 0x1D65E:
		case 0x1D692:
			$str .= chr(0x69);		/* i */
			break;
		case 0x1D0A:
		case 0x2149:
		case 0xFF4A:
		case 0x1D423:
		case 0x1D457:
		case 0x1D48B:
		case 0x1D4BF:
		case 0x1D4F3:
		case 0x1D527:
		case 0x1D55B:
		case 0x1D58F:
		case 0x1D5C3:
		case 0x1D5F7:
		case 0x1D62B:
		case 0x1D65F:
		case 0x1D693:
			$str .= chr(0x6A);		/* j */
			break;
		case 0x006B:		/* k */
		case 0x1D0B:
		//case 0x03BA:
		case 0xFF4B:
		case 0x1D424:
		case 0x1D458:
		case 0x1D48C:
		case 0x1D4C0:
		case 0x1D4F4:
		case 0x1D528:
		case 0x1D55C:
		case 0x1D590:
		case 0x1D5C4:
		case 0x1D5F8:
		case 0x1D62C:
		case 0x1D660:
		case 0x1D694:
			$str .= chr(0x6B);
			break;
		case 0x006C:		/* l */
		case 0x2113:
		case 0xFF4C:
		case 0x1D425:
		case 0x1D459:
		case 0x1D48D:
		case 0x1D4C1:
		case 0x1D4F5:
		case 0x1D529:
		case 0x1D55D:
		case 0x1D591:
		case 0x1D5C5:
		case 0x1D5F9:
		case 0x1D62D:
		case 0x1D661:
		case 0x1D695:
			$str .= chr(0x6C);
			break;
		case 0x006D:		/* m */
		case 0x1D0D:
		case 0xFF4D:
		case 0x1D426:
		case 0x1D45A:
		case 0x1D48E:
		case 0x1D4C2:
		case 0x1D52A:
		case 0x1D55E:
		case 0x1D592:
		case 0x1D5C6:
		case 0x1D5FA:
		case 0x1D62E:
		case 0x1D662:
		case 0x1D696:
	case 0x1D4F6:
			$str .= chr(0x6D);
			break;
		case 0x207F:
		case 0xFF4E:
		case 0x1D427:
		case 0x1D45B:
		case 0x1D48F:
		case 0x1D4C3:
		case 0x1D4F7:
		case 0x1D52B:
		case 0x1D55F:
		case 0x1D593:
		case 0x1D5C7:
		case 0x1D5FB:
		case 0x1D62F:
		case 0x1D663:
		case 0x1D697:
			$str .= chr(0x6E);		/* n */
			break;
		case 0x006F:
		//case 0x00F8:
		case 0x043E: // CYRILLIC SMALL LETTER O
		case 0x1D0F:
		case 0x2134:
		case 0xFF4F:
		case 0x1D428:
		case 0x1D45C:
		case 0x1D490:
		case 0x1D4F8:
		case 0x1D52C:
		case 0x1D560:
		case 0x1D594:
		case 0x1D5C8:
		case 0x1D5FC:
		case 0x1D630:
		case 0x1D664:
		case 0x1D698:
			$str .= chr(0x6F);		/* o */
			break;
		case 0x0070:
		case 0x0440: // CYRILLIC SMALL LETTER ER
		case 0x1D18:
		case 0xFF50:
		case 0x213C:
		case 0x1D429:
		case 0x1D45D:
		case 0x1D491:
		case 0x1D4C5:
		case 0x1D4F9:
		case 0x1D52D:
		case 0x1D561:
		case 0x1D595:
		case 0x1D5C9:
		case 0x1D5FD:
		case 0x1D631:
		case 0x1D665:
		case 0x1D699:
			$str .= chr(0x70);		/* p */
			break;
		case 0x0071:		/* q */
		case 0x051B:
		case 0xFF51:
		case 0x1D42A:
		case 0x1D45E:
		case 0x1D492:
		case 0x1D4C6:
		case 0x1D4FA:
		case 0x1D52E:
		case 0x1D562:
		case 0x1D596:
		case 0x1D5CA:
		case 0x1D5FE:
		case 0x1D632:
		case 0x1D666:
		case 0x1D69A:
			$str .= chr(0x71);
			break;
		case 0x0072:		/* r */
		case 0xFF52:
		case 0x1D42B:
		case 0x1D45F:
		case 0x1D493:
		case 0x1D4C7:
		case 0x1D4FB:
		case 0x1D52F:
		case 0x1D563:
		case 0x1D597:
		case 0x1D5CB:
		case 0x1D5FF:
		case 0x1D633:
		case 0x1D667:
		case 0x1D69B:
			$str .= chr(0x72);
			break;
		case 0x0073:		/* s */
		case 0x0455:
		case 0xFF53:
		case 0x1D42C:
		case 0x1D460:
		case 0x1D494:
		case 0x1D4C8:
		case 0x1D4FC:
		case 0x1D530:
		case 0x1D564:
		case 0x1D598:
		case 0x1D5CC:
		case 0x1D600:
		case 0x1D634:
		case 0x1D668:
		case 0x1D69C:
			$str .= chr(0x73);
			break;
		case 0x0074:		/* t */
		case 0x1D1B:
		case 0xFF54:
		case 0x1D42D:
		case 0x1D461:
		case 0x1D495:
		case 0x1D4C9:
		case 0x1D4FD:
		case 0x1D531:
		case 0x1D565:
		case 0x1D599:
		case 0x1D5CD:
		case 0x1D601:
		case 0x1D635:
		case 0x1D669:
		case 0x1D69D:
			$str .= chr(0x74);
			break;
		case 0x0075:		/* u */
		case 0x1D1C:
		case 0xFF55:
		case 0x1D42E:
		case 0x1D462:
		case 0x1D496:
		case 0x1D4CA:
		case 0x1D4FE:
		case 0x1D532:
		case 0x1D566:
		case 0x1D59A:
		case 0x1D5CE:
		case 0x1D602:
		case 0x1D636:
		case 0x1D66A:
		case 0x1D69E:
			$str .= chr(0x75);
			break;
		case 0x0076:		/* v */
		case 0x1D20:
		case 0xFF56:
		case 0x1D42F:
		case 0x1D463:
		case 0x1D497:
		case 0x1D4CB:
		case 0x1D4FF:
		case 0x1D533:
		case 0x1D567:
		case 0x1D59B:
		case 0x1D5CF:
		case 0x1D603:
		case 0x1D637:
		case 0x1D66B:
		case 0x1D69F:
			$str .= chr(0x76);
			break;
		case 0x0077:		/* w */
		case 0x051D:
		case 0x1D21:
		case 0x24B2:
		case 0x24E6:
		case 0xFF57:
		case 0x1D430:
		case 0x1D464:
		case 0x1D498:
		case 0x1D4CC:
		case 0x1D500:
		case 0x1D534:
		case 0x1D568:
		case 0x1D59C:
		case 0x1D5D0:
		case 0x1D604:
		case 0x1D638:
		case 0x1D66C:
		case 0x1D6A0:
			$str .= chr(0x77);
			break;
		case 0x0078:		/* x */
		case 0xFF58:
		case 0x2718:
		case 0x1D431:
		case 0x1D465:
		case 0x1D499:
		case 0x1D4CD:
		case 0x1D501:
		case 0x1D535:
		case 0x1D569:
		case 0x1D59D:
		case 0x1D5D1:
		case 0x1D605:
		case 0x1D639:
		case 0x1D66D:
		case 0x1D6A1:
			$str .= chr(0x78);
			break;
		case 0x0443: // CYRILLIC SMALL LETTER U
		case 0xFF59:
		case 0x1D432:
		case 0x1D466:
		case 0x1D49A:
		case 0x1D4CE:
		case 0x1D502:
		case 0x1D536:
		case 0x1D56A:
		case 0x1D59E:
		case 0x1D5D2:
		case 0x1D606:
		case 0x1D63A:
		case 0x1D66E:
		case 0x1D6A2:
			$str .= chr(0x79);		/* y */
			break;
		case 0x007A:		/* z */
		case 0x1D22:
		case 0xFF5A:
		case 0x1D433:
		case 0x1D467:
		case 0x1D49B:
		case 0x1D4CF:
		case 0x1D503:
		case 0x1D537:
		case 0x1D56B:
		case 0x1D59F:
		case 0x1D5D3:
		case 0x1D607:
		case 0x1D63B:
		case 0x1D66F:
		case 0x1D6A3:
			$str .= chr(0x7A);
			break;
		case 0xFE5B:
		case 0xFF5B:		/* left curly bracket */
			$str .= chr(0x7B);
			break;
		case 0xFF5C:		/* pipe */
			$str .= chr(0x7C);
			break;
		case 0xFE5C:
		case 0xFF5D:		/* right curly bracket */
			$str .= chr(0x7D);
			break;
		case 0xFF5E:		/* tilde */
			$str .= chr(0x7E);
			break;

		default:
			$str .= $char;
			break;
	}
}

function get_lang_regex_from_country($country) {
  static $codes = [
    'AD' => '/\b(?:ca)\b/',
    'AE' => '/\b(?:ar|fa|hi|ur)\b/',
    'AF' => '/\b(?:fa|ps|uz|tk)\b/',
    'AL' => '/\b(?:sq|el)\b/',
    'AM' => '/\b(?:hy)\b/',
    'AO' => '/\b(?:pt)\b/',
    'AR' => '/\b(?:es|it|de|fr|gn)\b/',
    'AS' => '/\b(?:sm|to)\b/',
    'AT' => '/\b(?:de|hr|hu|sl)\b/',
    'AW' => '/\b(?:nl|pap|es)\b/',
    'AX' => '/\b(?:sv)\b/',
    'AZ' => '/\b(?:az|ru|hy)\b/',
    'BA' => '/\b(?:bs|hr|sr)\b/',
    'BD' => '/\b(?:bn)\b/',
    'BE' => '/\b(?:nl|fr|de)\b/',
    'BF' => '/\b(?:fr|mos)\b/',
    'BG' => '/\b(?:bg|tr|rom)\b/',
    'BH' => '/\b(?:ar|fa|ur)\b/',
    'BI' => '/\b(?:fr|rn)\b/',
    'BJ' => '/\b(?:fr)\b/',
    'BL' => '/\b(?:fr)\b/',
    'BM' => '/\b(?:pt)\b/',
    'BN' => '/\b(?:ms)\b/',
    'BO' => '/\b(?:es|qu|ay)\b/',
    'BQ' => '/\b(?:nl|pap)\b/',
    'BR' => '/\b(?:pt|es|fr)\b/',
    'BT' => '/\b(?:dz)\b/',
    'BW' => '/\b(?:tn)\b/',
    'BY' => '/\b(?:be|ru)\b/',
    'BZ' => '/\b(?:es)\b/',
    'CA' => '/\b(?:fr|iu)\b/',
    'CC' => '/\b(?:ms)\b/',
    'CD' => '/\b(?:fr|ln|ktu|kg|sw|lua)\b/',
    'CF' => '/\b(?:fr|sg|ln|kg)\b/',
    'CG' => '/\b(?:fr|kg|ln)\b/',
    'CH' => '/\b(?:de|fr|it|rm)\b/',
    'CI' => '/\b(?:fr)\b/',
    'CK' => '/\b(?:mi)\b/',
    'CL' => '/\b(?:es)\b/',
    'CM' => '/\b(?:fr)\b/',
    'CN' => '/\b(?:zh|yue|wuu|dta|ug|za)\b/',
    'CO' => '/\b(?:es)\b/',
    'CR' => '/\b(?:es)\b/',
    'CU' => '/\b(?:es|pap)\b/',
    'CV' => '/\b(?:pt)\b/',
    'CW' => '/\b(?:nl|pap)\b/',
    'CX' => '/\b(?:zh|ms)\b/',
    'CY' => '/\b(?:el|tr)\b/',
    'CZ' => '/\b(?:cs|sk)\b/',
    'DE' => '/\b(?:de)\b/',
    'DJ' => '/\b(?:fr|ar|so|aa)\b/',
    'DK' => '/\b(?:da|fo|de)\b/',
    'DO' => '/\b(?:es)\b/',
    'DZ' => '/\b(?:ar|fr)\b/',
    'EC' => '/\b(?:es)\b/',
    'EE' => '/\b(?:et|ru)\b/',
    'EG' => '/\b(?:ar|fr)\b/',
    'EH' => '/\b(?:ar|mey)\b/',
    'ER' => '/\b(?:aa|ar|tig|kun|ti)\b/',
    'ES' => '/\b(?:es|ca|gl|eu|oc)\b/',
    'ET' => '/\b(?:am|om|ti|so|sid)\b/',
    'FI' => '/\b(?:fi|sv|smn)\b/',
    'FJ' => '/\b(?:fj)\b/',
    'FM' => '/\b(?:chk|pon|yap|kos|uli|woe|nkr|kpg)\b/',
    'FO' => '/\b(?:fo|da)\b/',
    'FR' => '/\b(?:fr|frp|br|co|ca|eu|oc)\b/',
    'GA' => '/\b(?:fr)\b/',
    'GB' => '/\b(?:en)\b/',
    'GE' => '/\b(?:ka|ru|hy|az)\b/',
    'GF' => '/\b(?:fr)\b/',
    'GG' => '/\b(?:nrf)\b/',
    'GH' => '/\b(?:ak|ee|tw)\b/',
    'GI' => '/\b(?:es|it|pt)\b/',
    'GL' => '/\b(?:kl|da)\b/',
    'GM' => '/\b(?:mnk|wof|wo|ff)\b/',
    'GN' => '/\b(?:fr)\b/',
    'GP' => '/\b(?:fr)\b/',
    'GQ' => '/\b(?:es|fr)\b/',
    'GR' => '/\b(?:el|fr)\b/',
    'GT' => '/\b(?:es)\b/',
    'GU' => '/\b(?:ch)\b/',
    'GW' => '/\b(?:pt|pov)\b/',
    'HK' => '/\b(?:zh|yue|zh)\b/',
    'HN' => '/\b(?:es|cab|miq)\b/',
    'HR' => '/\b(?:hr|sr)\b/',
    'HT' => '/\b(?:ht|fr)\b/',
    'HU' => '/\b(?:hu)\b/',
    'ID' => '/\b(?:id|nl|jv)\b/',
    'IE' => '/\b(?:ga)\b/',
    'IL' => '/\b(?:he|ar)\b/',
    'IM' => '/\b(?:gv)\b/',
    'IN' => '/\b(?:hi|bn|te|mr|ta|ur|gu|kn|ml|or|pa|as|bh|sat|ks|ne|sd|kok|doi|mni|sit|sa|fr|lus|inc)\b/',
    'IQ' => '/\b(?:ar|ku|hy)\b/',
    'IR' => '/\b(?:fa|ku)\b/',
    'IS' => '/\b(?:is|de|da|sv|no)\b/',
    'IT' => '/\b(?:it|de|fr|sc|ca|co|sl)\b/',
    'JE' => '/\b(?:fr|nrf)\b/',
    'JO' => '/\b(?:ar)\b/',
    'JP' => '/\b(?:ja)\b/',
    'KE' => '/\b(?:sw)\b/',
    'KG' => '/\b(?:ky|uz|ru)\b/',
    'KH' => '/\b(?:km|fr)\b/',
    'KI' => '/\b(?:gil)\b/',
    'KM' => '/\b(?:ar|fr)\b/',
    'KP' => '/\b(?:ko)\b/',
    'KR' => '/\b(?:ko)\b/',
    'XK' => '/\b(?:sq|sr)\b/',
    'KW' => '/\b(?:ar)\b/',
    'KZ' => '/\b(?:kk|ru)\b/',
    'LA' => '/\b(?:lo|fr)\b/',
    'LB' => '/\b(?:ar|fr|hy)\b/',
    'LI' => '/\b(?:de)\b/',
    'LK' => '/\b(?:si|ta)\b/',
    'LS' => '/\b(?:st|zu|xh)\b/',
    'LT' => '/\b(?:lt|ru|pl)\b/',
    'LU' => '/\b(?:lb|de|fr)\b/',
    'LV' => '/\b(?:lv|ru|lt)\b/',
    'LY' => '/\b(?:ar|it)\b/',
    'MA' => '/\b(?:ar|ber|fr)\b/',
    'MC' => '/\b(?:fr|it)\b/',
    'MD' => '/\b(?:ro|ru|gag|tr)\b/',
    'ME' => '/\b(?:sr|hu|bs|sq|hr|rom)\b/',
    'MF' => '/\b(?:fr)\b/',
    'MG' => '/\b(?:fr|mg)\b/',
    'MH' => '/\b(?:mh)\b/',
    'MK' => '/\b(?:mk|sq|tr|rmm|sr)\b/',
    'ML' => '/\b(?:fr|bm)\b/',
    'MM' => '/\b(?:my)\b/',
    'MN' => '/\b(?:mn|ru)\b/',
    'MO' => '/\b(?:zh|zh|pt)\b/',
    'MP' => '/\b(?:fil|tl|zh|ch)\b/',
    'MQ' => '/\b(?:fr)\b/',
    'MR' => '/\b(?:ar|fuc|snk|fr|mey|wo)\b/',
    'MT' => '/\b(?:mt)\b/',
    'MU' => '/\b(?:bho|fr)\b/',
    'MV' => '/\b(?:dv)\b/',
    'MW' => '/\b(?:ny|yao|tum|swk)\b/',
    'MX' => '/\b(?:es)\b/',
    'MY' => '/\b(?:ms|zh|ta|te|ml|pa|th)\b/',
    'MZ' => '/\b(?:pt|vmw)\b/',
    'NA' => '/\b(?:af|de|hz|naq)\b/',
    'NC' => '/\b(?:fr)\b/',
    'NE' => '/\b(?:fr|ha|kr|dje)\b/',
    'NG' => '/\b(?:ha|yo|ig|ff)\b/',
    'NI' => '/\b(?:es)\b/',
    'NL' => '/\b(?:nl|fy)\b/',
    'NO' => '/\b(?:no|nb|nn|se|fi)\b/',
    'NP' => '/\b(?:ne)\b/',
    'NR' => '/\b(?:na)\b/',
    'NU' => '/\b(?:niu)\b/',
    'NZ' => '/\b(?:mi)\b/',
    'OM' => '/\b(?:ar|bal|ur)\b/',
    'PA' => '/\b(?:es)\b/',
    'PE' => '/\b(?:es|qu|ay)\b/',
    'PF' => '/\b(?:fr|ty)\b/',
    'PG' => '/\b(?:ho|meu|tpi)\b/',
    'PH' => '/\b(?:tl|fil|ceb|tgl|ilo|hil|war|pam|bik|bcl|pag|mrw|tsg|mdh|cbk|krj|sgd|msb|akl|ibg|yka|mta|abx)\b/',
    'PK' => '/\b(?:ur|pa|sd|ps|brh)\b/',
    'PL' => '/\b(?:pl)\b/',
    'PM' => '/\b(?:fr)\b/',
    'PR' => '/\b(?:es)\b/',
    'PS' => '/\b(?:ar)\b/',
    'PT' => '/\b(?:pt|mwl)\b/',
    'PW' => '/\b(?:pau|sov|tox|ja|fil|zh)\b/',
    'PY' => '/\b(?:es|gn)\b/',
    'QA' => '/\b(?:ar|es)\b/',
    'RE' => '/\b(?:fr)\b/',
    'RO' => '/\b(?:ro|hu|rom)\b/',
    'RS' => '/\b(?:sr|hu|bs|rom)\b/',
    'RU' => '/\b(?:ru|tt|xal|cau|ady|kv|ce|tyv|cv|udm|tut|mns|bua|myv|mdf|chm|ba|inh|tut|kbd|krc|av|sah|nog)\b/',
    'RW' => '/\b(?:rw|fr|sw)\b/',
    'SA' => '/\b(?:ar)\b/',
    'SB' => '/\b(?:tpi)\b/',
    'SC' => '/\b(?:fr)\b/',
    'SD' => '/\b(?:ar|fia)\b/',
    'SE' => '/\b(?:sv|se|sma|fi)\b/',
    'SG' => '/\b(?:cmn|ms|ta|zh)\b/',
    'SI' => '/\b(?:sl|sh)\b/',
    'SJ' => '/\b(?:no|ru)\b/',
    'SK' => '/\b(?:sk|hu)\b/',
    'SL' => '/\b(?:mtem)\b/',
    'SM' => '/\b(?:it)\b/',
    'SN' => '/\b(?:fr|wo|fuc|mnk)\b/',
    'SO' => '/\b(?:so|ar|it)\b/',
    'SR' => '/\b(?:nl|srn|hns|jv)\b/',
    'ST' => '/\b(?:pt)\b/',
    'SV' => '/\b(?:es)\b/',
    'SX' => '/\b(?:nl)\b/',
    'SY' => '/\b(?:ar|ku|hy|arc|fr)\b/',
    'SZ' => '/\b(?:ss)\b/',
    'TD' => '/\b(?:fr|ar|sre)\b/',
    'TF' => '/\b(?:fr)\b/',
    'TG' => '/\b(?:fr|ee|hna|kbp|dag|ha)\b/',
    'TH' => '/\b(?:th)\b/',
    'TJ' => '/\b(?:tg|ru)\b/',
    'TK' => '/\b(?:tkl)\b/',
    'TL' => '/\b(?:tet|pt|id)\b/',
    'TM' => '/\b(?:tk|ru|uz)\b/',
    'TN' => '/\b(?:ar|fr)\b/',
    'TO' => '/\b(?:to)\b/',
    'TR' => '/\b(?:tr|ku|diq|az|av)\b/',
    'TT' => '/\b(?:hns|fr|es|zh)\b/',
    'TV' => '/\b(?:tvl|sm|gil)\b/',
    'TW' => '/\b(?:zh|zh|nan|hak)\b/',
    'TZ' => '/\b(?:sw|ar)\b/',
    'UA' => '/\b(?:uk|ru|rom|pl|hu)\b/',
    'UG' => '/\b(?:lg|sw|ar)\b/',
    'US' => '/\b(?:en|es)\b/',
    'UY' => '/\b(?:es)\b/',
    'UZ' => '/\b(?:uz|ru|tg)\b/',
    'VA' => '/\b(?:la|it|fr)\b/',
    'VC' => '/\b(?:fr)\b/',
    'VE' => '/\b(?:es)\b/',
    'VN' => '/\b(?:vi|fr|zh|km)\b/',
    'VU' => '/\b(?:bi|fr)\b/',
    'WF' => '/\b(?:wls|fud|fr)\b/',
    'WS' => '/\b(?:sm)\b/',
    'YE' => '/\b(?:ar)\b/',
    'YT' => '/\b(?:fr)\b/',
    'ZA' => '/\b(?:zu|xh|af|nso|tn|st|ts|ss|ve|nr)\b/',
    'ZM' => '/\b(?:bem|loz|lun|lue|ny|toi)\b/',
    'ZW' => '/\b(?:sn|nr|nd)\b/',
    'CS' => '/\b(?:cu|hu|sq|sr)\b/',
    'AN' => '/\b(?:nl|es)\b/'
  ];
  
  if (isset($codes[$country])) {
    return $codes[$country];
  }
  
  return null;
}
