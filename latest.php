<?
/* Alternate way of loading imgboard ?mode=latest.
   Temp hack because of difficulty of checking query strings in order to set auth_basic in nginx.
*/

	$mode = "latest";
	require_once "imgboard.php";
?>