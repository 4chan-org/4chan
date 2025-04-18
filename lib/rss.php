<?php
function summarize($com) {
	// strip out URLs...
	$com = preg_replace('{(https?:)?//[\S"\'<]+}','',$com);
	// remove linebreaks
	$com = preg_replace('{<br ?/?>}',"\n",$com);
	// take the first sentence that's longer than 6 letters...
	$sentences = preg_split('{[\n.]+}',$com);
	$com = '';
	foreach($sentences as $sent) {
		if(strlen($sent) > 6) {
			$com = $sent;
			break;
		}
	}
	// and get the first 60 chars of it, making sure that words don't get cut off
	$com = preg_replace('{^(.{60,}?)(?:[\s\n.]|$).*}','$1',$com);
	if(strlen($com) >= 60) $com .= '...';
	return $com;
}
function rss_dump() {
	global $log;
	$title = htmlspecialchars(TITLE);
	$link = "http:" . SELF_PATH2_ABS;
	$self = "http:" . DATA_SERVER . BOARD_DIR . '/index.rss';
	$output = "<?xml version=\"1.0\" encoding=\"UTF-8\" ?>\n<rss version=\"2.0\" xmlns:dc=\"http://purl.org/dc/elements/1.1/\" xmlns:atom=\"http://www.w3.org/2005/Atom\">\n";
	$output .= "<channel>\n<title>$title</title>\n<link>$link</link>\n<description>Threads on $title at 4chan.org.</description>\n";
	$output .= "<atom:link href=\"$self\" rel=\"self\" type=\"application/rss+xml\" />";
	$query = mysql_board_call("SELECT SQL_NO_CACHE * FROM `".SQLLOG."` WHERE archived=0 and resto=0 ORDER BY no DESC LIMIT 20");
	while($row = mysql_fetch_assoc($query)) {
		$output .= "<item>\n";
		$spacing = '';
		$sub = $row['sub'];

		$spoiler = 0;
		if(strpos($sub, "SPOILER<>")===0) {
			$sub = str_replace("SPOILER<>","",$sub);
			$spoiler = 1;
		}
		if(!$sub)
			$sub = strip_tags(summarize($row['com']));
		if(!$sub && $row['name']) { // if they have a name
			if(FORCED_ANON!=1) $by = " by ". strip_tags($row['name']); // forced anon doesn't need byline
			$sub = "No. {$row['no']}$by";
		}
		$link = "http:" . DATA_SERVER . BOARD_DIR . '/' . RES_DIR2 . $row['no'] . PHP_EXT2;

		if(strpos($row['com'], "[spoiler")!==false)
			$row['com'] = '(Spoilers)';
		$date = strftime("%a, %d %b %Y %X %Z", $row['time']);
		if($sub) // don't include title if empty
			$output .= "$spacing<title>$sub</title>\n";
		$output .= "$spacing<link>$link#{$row['no']}</link>\n";
		$output .= "$spacing<guid>$link</guid>\n";
		$output .= "$spacing<comments>$link</comments>\n";
		$output .= "$spacing<pubDate>$date</pubDate>\n";
		$srcurl = "http:" . IMG_DIR2 . $row['tim'] . $row['ext'];
		$thumburl = "http:" . THUMB_DIR2 . $row['tim'] . 's.jpg';
		if($spoiler) {
			$thumburl = "http:" . DATA_SERVER . 'spoiler.png';
		}
		$imglink = "<a href='$srcurl' target=_blank><img style='float:left;margin:8px' border=0 src='$thumburl'></a>";

		if(FORCED_ANON!=1)
			// $output .= "$spacing<author>".strip_tags($row['name'])."</author>\n";
			$output .= "$spacing<dc:creator>".strip_tags($row['name'])."</dc:creator>\n";
		$output .= "$spacing<description><![CDATA[ $imglink {$row['com']} ]]></description>\n";
		$output .= "</item>\n";
	}
	$output .= "</channel>\n</rss>";
	print_page(INDEX_DIR.'index.rss',$output);
}
