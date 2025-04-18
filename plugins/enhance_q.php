<?php
/**
 * Enhance meta - Yotsuba plugin
 * Enhances meta with various fabulous bits and bobs.
 */

function meta_is_thread_flagged( $resline )
{
	global $log;
	
	$rep = 0;
	$posts = array('admin' => '', 'developer' => '', 'mod' => '', 'manager' => '');
	
	while( list( $resrow ) = each( $resline ) ) {
		
		if( !$log[ $resrow ][ 'no' ] ) {
			break;
		}
		
		if( $log[ $resrow ][ 'capcode' ] === 'none' ) {
			continue;
		}
		
		$capcode = ( $log[ $resrow ][ 'capcode' ] == 'admin_highlight' ) ? 'admin' : $log[$resrow]['capcode'];
		$no = $log[$resrow]['no'];
		
		$posts[$capcode] .= "$no,";
		
	}
	
	unset( $posts['none'] );
	
	foreach( $posts as $key => $value ) {
		if( $posts[$key] != '' ) {
			$posts[$key] = substr($posts[$key], 0, -1);
		}
	}
	
	
	return array( $posts['admin'], $posts['developer'], $posts['mod'], $posts['manager'] );
}
?>