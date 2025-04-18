<?php
/* options:
name_edit: make Name field editable
host_edit: make Host field editable
name,host,reverse,xff = string: load values explicitly
load_reporter = numeric-ip: load reporter
load_ban_request = id: load ban request values
load_post = postno: use 'board' value and no to fetch info
public_reason = string: load public reason with string
private_reason = string: load private reason with string
length = string: load days with number
scope = local|global|zonly: load scope
postban = delpost|delfile|delall: load postban action

board = ''|string : name of local board

hide_postbans: hide post-ban action list

action = url of form action

*/

/*
Unban in ...
Ban Duration [x Use] 0v/0v/0v (D/W/M)
*/
function head() {
?><!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01//EN" "http://www.w3.org/TR/html4/strict.dtd">
<html><head><title>Ban form</title>
<meta http-equiv="Content-type" content="text/html;charset=utf-8">
<style type="text/css">
body {
	background: #ffe;
	font-family: Verdana;
	font-size: 10px;
	color: #000000;
	padding: 15px 10px;
	margin: 0;
}
table {
	border: 0px #606060 solid;
	border-spacing: 0px;
	padding: 5px;
	border-collapse:collapse;
}
td,th {
	font-family: Verdana;
	font-size: 10px;
	color: #000000;
	border: 1px #606060 solid;
	border-spacing: 0px;
	border-collapse:collapse;
	padding-top:2px;
	padding-bottom:2px;
}
th { background: #fca; }

.redbg { background: #ffe0e0; }

input,select,.fakebutton {
	font-family: Verdana;
	font-size: 9pt;
	color: #000000;
	background-color: #F8F8F8;
	border: 1px solid #808080;
	vertical-align: middle;
}
select { vertical-align:top; }
option,optgroup {
	font-family: Verdana;
	font-size: 9pt;
}

	td,th,body,input { font-family: Verdana,Tahoma,sans-serif; font-size: 12px; }
	td,th {  padding: 2px 2px; }
	th { text-align: left; font-weight: normal; }
	.title { background: #800; color: white; font-weight: bold; }
</style>
<script type="text/javascript">
function resizeToContent() {
	// resize inner height to fit content
	resizeTo(410, 400); // only way to know outer size for sure
	var innerHeight = (window.innerHeight)?window.innerHeight:document.documentElement.clientHeight;
	var outerHeight = 400;
	var docHeight = document.body.clientHeight;
	if(document.documentElement.clientHeight < docHeight) // e.g. opera?
		docHeight = document.documentElement.clientHeight;
	//alert(outerHeight);
	//alert(innerHeight);
	//alert(docHeight);
	resizeTo(410, docHeight + (outerHeight - innerHeight));
}
function toggle(name){var visible=((document.all)?"block":"table-row"); var a=document.getElementById(name); a.style.display = ((a.style.display!=visible)?visible:"none");}


//window.onload = resizeToContent;

function callInOpener(code) {
	if(window.opener && !window.opener.closed) {
		window.opener.setTimeout(code, 0);
	}
}
</script>
</head><body>
<?
}

function fancydie($err) {
	head();
	$err = "<h3><font color='#FF0000'>$err</font></h3>";
	$err .= "<br><a href='javascript:history.go(-1)'>Back</a></body></html>";
	die($err); // ok, not very fancy yet
}

function format_host($dec_ip,$reverse='') {
	if(!$reverse)
		$reverse = gethostbyaddr($dec_ip);
	if($reverse && $reverse != $dec_ip) {
		$reverse = htmlspecialchars($reverse);
		return "$reverse ($dec_ip)";
	}
	else return "$dec_ip";
}

function format_name($name) {
	$name = strip_tags($name);
	$name = strtr($name, '!', '#');
	$name = htmlspecialchars($name);
	return $name;
}



function ban_history($dec_ip) {
	$query = mysql_global_call("SELECT COUNT(*) as total,COUNT(active||NULL) as active FROM banned_users WHERE host='%s'", $dec_ip);
	$row = mysql_fetch_assoc($query);
	if(!$row)
		return '';
	if($row['total'] == 0)
		return '';
	if($row['active'] == 0)
		$linkdesc = sprintf("{$row['total']} past ban%s for this IP.", ($row['total']>1)?'s':'' );
	else if($row['active'] == $row['total'])
		$linkdesc = sprintf("{$row['active']} ban%s already active for this IP.", ($row['active']>1)?'s':'');
	else {
		$row['total'] -= $row['active'];
		$linkdesc = sprintf("{$row['total']} past ban%s and {$row['active']} ban%s already active for this IP.", ($row['total']>1)?'s':'' , ($row['active']>1)?'s':'');
	}
	$dec_ip = urlencode($dec_ip);
	return "<a href=\"http://team.4chan.org/bans.php?admin=hist&ip=$dec_ip\" target=\"_blank\">$linkdesc</a>";
}

function other_ban_requests($than,$dec_ip) {
	$query = mysql_global_call("SELECT COUNT(*) as total from ban_requests WHERE id!=%d AND host='%s'", $than, $dec_ip);
	$row = mysql_fetch_assoc($query);
	if(!$row)
		return 0;
	return $row['total'];
}

function get_xff($board,$tim) {
	$query = mysql_global_call("SELECT xff from xff where tim='%s' AND board='%s'", $board, $tim);
	$row = mysql_fetch_assoc($query);
	if(!$row)
		return '';
	return format_host($row['host']);
}

function form_ban($o) {
	head();
	if($o['load_reporter']) {
		$query = mysql_global_call("SELECT ip FROM reports where ip=%d LIMIT 1",$o['load_reporter']);
		if(!($row=mysql_fetch_assoc($query)))
			fancydie("No reports found with specified IP.");
		$form['load_name'] = 'load_reporter';
		$form['load_value'] = $o['load_reporter'];
		$form['name'] = 'Anonymous';
		$form['host'] = format_host(long2ip($row['ip']));
		$form['xff'] = '';
		$form['banhist'] = ban_history(long2ip($row['ip']));
		$form['board'] = '';
		$form['title'] = "Banning reporter " . long2ip($row['ip']);
		$o['hide_postbans'] = 1;
		$form['id'] = (int)$o['load_reporter'];
	}
	else if($o['load_ban_request']) {
		$query = mysql_global_call("SELECT * FROM ban_requests where id=%d", $o['load_ban_request']);
		if(!($row=mysql_fetch_assoc($query)))
			fancydie("Specified ban request does not exist.");
		$form['load_name'] = 'load_ban_request';
		$form['load_value'] = $o['load_ban_request'];
		$post = unserialize($row['spost']);
		$form['name'] = format_name($post['name']);
		$form['host'] = format_host($post['host'],$post['reverse']);
		$form['xff'] = htmlspecialchars($post['xff']);
		$form['banhist'] = ban_history($post['host']);
		$form['board'] = $row['board'];
		$form['title'] = htmlspecialchars("Filling {$row['janitor']}'s ban request for /{$row['board']}/{$post['no']}");
		//$form['public_reason'] = htmlspecialchars($row['reason']);
		//$form['private_reason'] = htmlspecialchars("requested by {$row['janitor']}");
		$form['other_ban_reqs'] = other_ban_requests($o['load_ban_request'], $post['host']);
		$o['hide_postbans'] = 1;
		$form['id'] = (int)$o['load_ban_request'];
	}
	else if($o['load_post']) {

	}
	else if($GLOBALS['my_access']['manual_ban']) {
		$o['name_edit'] = $o['host_edit'] = /*$o['bannedby_edit'] =*/ true;
		$form['load_name'] = 'manual';
		$form['load_value'] = 'yes';
	}

	// overrides
	if(isset($_COOKIE['4chan_bpubr']))
		$form['public_reason'] = htmlspecialchars($_COOKIE['4chan_bpubr']);
	if(isset($_COOKIE['4chan_bprvr']))
		$form['private_reason'] = htmlspecialchars($_COOKIE['4chan_bprvr']);
	if(isset($_COOKIE['4chan_blen'])) {
		$clen = (int)$_COOKIE['4chan_blen'];
		if($clen==0)
			$form['warn'] = 1;
		else if($clen==-1)
			$form['indef'] = 1;
		else
			$form['length'] = $clen;
		$form['remember'] = 1;
	}

	if($o['public_reason'])
		$form['public_reason'] = htmlspecialchars($o['public_reason']);
	if($o['private_reason'])
		$form['private_reason'] = htmlspecialchars($o['private_reason']);
	if($o['length'])
		$form['length'] = htmlspecialchars($o['length']);

	$form['modname'] = htmlspecialchars($_COOKIE['4chan_auser']);

?>
<form name="banform" method="POST">
<input type="hidden" name="<?=$form['load_name']?>" value="<?=$form['load_value']?>">
<table border=0 cellspacing=0 cellpadding=0>
<tr><td colspan=2 align=center class="title">
<a href="javascript:toggle('more');resizeToContent();" style="position:absolute;width:13px;height:13px;border:1px solid white;left:11px;color:white;text-decoration:none;font-size:11px;">&#x25BC;</a></div>
<?=$form['title']?></td></tr>
<tr id="more" style="display:none"><th>More:</th>
	<td>[<input type=checkbox name=remember value="1" <?= $form['remember']?'CHECKED':'' ?>> Remember ban reason and length]</td>
</tr>
<tr>	<th>Name:</th>
		<td><input type="text" name="name" value="<?=$form['name']?>" size=40 <?= $o['name_edit']?'':'DISABLED' ?>></td>
</tr>
<tr>	<th>Host:</th>
		<td><input type="text" name="host" value="<?=$form['host']?>" size=40 <?= $o['host_edit']?'':'DISABLED' ?>></td>
</tr>
<? if($form['xff']) { ?>
<tr>	<th>Proxy For:</th>
		<td><input type="text" name="xff" value="<?=$form['xff']?>" size=40 <?= $o['host_edit']?'':'DISABLED' ?> title="This is possibly the user's real IP, but only the above IP will be banned."></td>
</tr>
<? } ?>
<? if($form['banhist']) { ?>
<tr>	<th>Ban History:</th>
		<td><?= $form['banhist'] ?></td>
</tr>
<? } ?>
<tr>	<th>Public Ban Reason:</th>
		<td><textarea name="public_reason" cols=30 rows=2 title="This is the message that the user will see on the banned page."><?=$form['public_reason']?></textarea></td>
</tr>
<tr>	<th>Private Info:</th>
		<td><input type="text" name="private_reason" value="<?=$form['private_reason']?>" size=40 title="Additional info that will be not be shown to the user."></td>
</tr>
<tr>	<th>Unban in:</th>
		<td><input type="text" name="length" value="<?=$form['length']?>" size=3> days [<input type=checkbox name=warn value="1" title="Ban for 0 days" <?= $form['warn']?'CHECKED':'' ?>> Warn] [<input type=checkbox name=indefinite value="1" title="Ban forever" <?= $form['indef']?'CHECKED':'' ?>> Permanent]</td>
</tr>
<tr>	<th>Banned by:</th>
		<td><input type="text" name="modname" value="<?=$form['modname']?>" size=40 <?= $o['bannedby_edit']?'':'DISABLED' ?>></td>
</tr>
<tr>	<th>Ban options:</th>
		<td><select name="scope" style="float:left;">
<?
	if($form['board']) {
		?><option value="local" <?= ($o['scope']=='local')?'SELECTED':'' ?>>Ban from /<?=$form['board']?>/</option><?
	}
		?><option value="global" <?= ($o['scope']=='global')?'SELECTED':'' ?>>Global ban</option><?
		?><option value="zonly" <?= ($o['scope']=='zonly')?'SELECTED':'' ?>>Banish to /z/</option><?
?>
	</select>
	<? if(!$o['hide_postbans']) { ?>
	<span title="Display USER WAS BANNED... message" style="float:left;margin-left:5px">[<input type=checkbox name=banmsg value="1">msg]</span>
	<? } ?>
	<input type="submit" value="Ban" style="float:right;">
	</td>
</tr>
<?
	if(!$o['hide_postbans'] || $form['other_ban_reqs']) {
?>
<tr>	<th>Post-ban actions:</th>
		<td>
		<? if(!$o['hide_postbans']) { ?>
			<select name="postban">
			<option value="" <?= ($o['postban']=='')?'SELECTED':'' ?>>None</option>
			<option value="delpost" <?= ($o['postban']=='delpost')?'SELECTED':'' ?>>Delete post</option>
			<option value="delfile" <?= ($o['postban']=='delfile')?'SELECTED':'' ?>>Delete file only</option>
			<option value="delall" <?= ($o['postban']=='delall')?'SELECTED':'' ?>>Delete all by IP</option>
			</select>
		<? } ?>
		<? if($form['other_ban_reqs']) { ?>
		[<input type=checkbox name=clearbanreqs value=1 title="Clear ban reqs"> Clear <?= $form['other_ban_reqs'] ?> other ban request<?= ($form['other_ban_reqs']>1)?'s':'' ?> for this IP]
		<? } ?>
		</td>
</tr>
<?
	}
?>
</table>
</form>
<? if($form['id']) { ?>
<script>
window.onunload = function() {
	callInOpener("banCancel(<?=$form['id']?>)");
}
document.forms.banform.onsubmit = function() { window.onunload = function(){}; };
</script>
<? } ?>
</body></html>
<?
	return;
}
