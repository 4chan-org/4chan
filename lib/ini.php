<?php
// The description of the INI format
$INI_PATTERN = '/^[ \t]*([A-Z0-9_-]+)[ \t]*=[ \t]*((?:[^\r\n]|(?<=\\\\)[\r\n]{1,2})*)/m';

// Global config dir absolute folder (no trailing slash)
$configdir = '/www/global/yotsuba/config';
$yconfgdir = '/www/global/yotsuba/config';

function parse_ini( $filename )
{
	global $INI_PATTERN;
	$file = @file_get_contents( $filename );
	if( !$file ) return array();
	preg_match_all( $INI_PATTERN, $file, $matches, PREG_SET_ORDER );

	$ret = array();

	foreach( $matches as $match ) {
		// replace backslash-newlines with newlines
		$v = preg_replace( '|\\\\[\r\n]+|', "\n", $match[ 2 ] );
		$v = ltrim( $v );

		$ret[ $match[ 1 ] ] = $v;
	}

	return $ret;
}

function write_constants( $new )
{
	global $constants;

	foreach( $new as $k => $v ) {
		$constants[ $k ] = $v; // update the constants array
	}
}

function load_ini( $filename )
{
	global $constants, $INI_PATTERN, $loaded_files;
	$file = file_get_contents( $filename );
	preg_match_all( $INI_PATTERN, $file, $matches, PREG_SET_ORDER );

	foreach( $matches as $match ) {
		// replace backslash-newlines with newlines
		$match[ 2 ] = preg_replace( '|\\\\[\r\n]+|', "\n", $match[ 2 ] );
		$match[ 2 ] = ltrim( $match[ 2 ] );


		$constants[ $match[ 1 ] ] = $match[ 2 ]; // update the constants array
	}

	$loaded_files[$filename] = 1;
}

function special_callback( $matches )
{
	global $constants;
	$val = str_replace( "!file ", "", $matches[ 1 ], $is_file );
	if( $is_file ) {
		return @file_get_contents( $val );
	}
	$val = str_replace( "!rand ", "", $matches[ 1 ], $is_rand );
	if( $is_rand ) {
		$choices = explode( ",", $val );
		$val     = array_rand( array_flip( $choices ) );
		$val     = trim( $val );

		return $val;
	}

	return evaluate( $constants[ $val ] );
}

function evaluate( $val )
{
	if( $val === 'yes' ) {
		$val = true;
		settype($val, "bool");
	} else if( $val === 'no' ) {
		$val = false;
		settype($val, "bool");
	} else {
		$val = preg_replace_callback( '|\{\{(.*?)\}\}|', "special_callback", $val );
	
		// for numeric values, try to keep the PHP internal type as 'int'
		if (ctype_digit($val)) {
			settype($val, "int");
		}
	}
	
	return $val;
}

function finalize_constants()
{
	global $constants;
	
	$c2 = array();
	foreach( $constants as $key => $val ) {
		$val        = evaluate( $val );
		$c2[ $key ] = $val;
		if( !defined( $key ) ) {
			define( $key, $val );
		}
	}
	$constants = $c2;
}

$constants = array();
$loaded_files = array();

// quick wrapper function
function load_ini_file( $filename )
{
	global $loaded_files, $configdir, $constants;
	if( strpos( $filename, '/' ) !== 0 ) $filename = "$configdir/$filename";

	if( in_array( $filename, $loaded_files ) ) return;

	$constants = array();
	load_ini($filename);
	finalize_constants();
}
