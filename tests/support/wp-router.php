<?php

$path = parse_url( isset( $_SERVER['REQUEST_URI'] ) ? $_SERVER['REQUEST_URI'] : '/', PHP_URL_PATH );
$file = rtrim( isset( $_SERVER['DOCUMENT_ROOT'] ) ? $_SERVER['DOCUMENT_ROOT'] : '', '/' ) . $path;

if ( is_string( $path ) && '/' !== $path && is_file( $file ) ) {
	return false;
}

require rtrim( (string) $_SERVER['DOCUMENT_ROOT'], '/' ) . '/index.php';
