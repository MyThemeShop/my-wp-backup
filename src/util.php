<?php

if ( ! defined( 'ABSPATH' ) ) { die; }

/**
 * @param array $options
 * @param mixed $selected
 */
function wpb_select_options( array $options, $selected = array() ) {

	$selected = (array) $selected;

	foreach ( $options as $option => $title ) {
		echo '<option value="' . $option . '" ' . ( in_array( $option, $selected ) ? 'selected' : '' ) . '>' . esc_html( $title ) . '</option>';
	}

}

function wpb_array_merge_recursive_distinct( array &$array1, array &$array2 ) {
	$merged = $array1;

	foreach ( $array2 as $key => &$value ) {
		if ( is_array( $value ) && isset ( $merged [ $key ] ) && is_array( $merged [ $key ] ) ) {
			$merged [ $key ] = wpb_array_merge_recursive_distinct( $merged [ $key ], $value );
		} else {
			$merged [ $key ] = $value;
		}
	}

	return $merged;
}


function wpb_get_file_chunk( $handle, $chunkSize ) {
	$byteCount  = 0;
	$giantChunk = "";
	while ( ! feof( $handle ) ) {
		// fread will never return more than 8192 bytes if the stream is read buffered and it does not represent a plain file
		$chunk = fread( $handle, 8192 );
		$byteCount += strlen( $chunk );
		$giantChunk .= $chunk;
		if ( $byteCount >= $chunkSize ) {
			return $giantChunk;
		}
	}

	return $giantChunk;
}

/**
 * @param mixed $args,...
 *
 * @return string
 */
function wpb_join_remote_path( $args ) {

	return join( '/', func_get_args() );

}

/**
 * @param string $val
 *
 * @return int
 */
function wpb_return_bytes( $val ) {
	$val  = trim( $val );
	$last = strtolower( $val[ strlen( $val ) - 1 ] );
	switch ( $last ) {
		// The 'G' modifier is available since PHP 5.1.0
		case 'g':
			$val *= 1024;
		case 'm':
			$val *= 1024;
		case 'k':
			$val *= 1024;
	}

	return $val;
}
