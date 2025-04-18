<?

function json_for_post($board, $post) {
	global $in_imgboard;
	$in_imgboard = true;
	include_once "/www/global/yotsuba/json.php";
	
	$extra = array();
	
	$post['board'] = $board;
	return json_encode(generate_post_json( $post, $post['resto'] ? $post['resto'] : $post['no'],  $extra, true ));
}

?>