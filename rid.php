<?php

$names = @file("files.txt");

if (!$names) {
	$arr = scandir(".");

	foreach ($arr as $fi) {
		if (preg_match("/\.(jpg|gif|png)$/", $fi)) {
			$names[] = $fi;
		}
	}

	file_put_contents("files.txt", join($names, "\n"));
}

$dir = dirname($_SERVER['REQUEST_URI']);
$dir = str_replace("dontblockthis/", "", $dir);
$dir = str_replace( '/image', '', $dir );

$protocol = $_SERVER['SERVER_PORT'] == 443 ? "https" : "http";

header("Location: ".$protocol."://s.4cdn.org/image" . $dir ."/" . $names[rand(0, count($names)-1)]);

?>
