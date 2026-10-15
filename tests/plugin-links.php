<?php

define( 'ABSPATH', dirname( __DIR__ ) . '/' );
define( 'SERFE_AI_DISCOVERY_PATH', dirname( __DIR__ ) . '/' );

function plugin_basename( $file ) {
	return 'serfe-ai-discovery/serfe-ai-discovery.php';
}

function admin_url( $path = '' ) {
	return 'https://example.test/wp-admin/' . $path;
}

function esc_url( $url ) {
	return $url;
}

function esc_html__( $text ) {
	return $text;
}

require_once dirname( __DIR__ ) . '/includes/class-settings.php';
require_once dirname( __DIR__ ) . '/includes/class-plugin.php';

use Serfe\AI_Discovery\Plugin;

$failures = array();
$tests    = 0;

$assert_same = function ( $expected, $actual, $name ) use ( &$failures, &$tests ) {
	$tests++;
	if ( $expected !== $actual ) {
		$failures[] = $name . "\nExpected: " . var_export( $expected, true ) . "\nActual:   " . var_export( $actual, true );
	}
};

$reflection = new ReflectionClass( Plugin::class );
$plugin     = $reflection->newInstanceWithoutConstructor();
$main_file  = file_get_contents( dirname( __DIR__ ) . '/serfe-ai-discovery.php' );

preg_match( '/\*\s*Author:\s*(.+)$/m', $main_file, $author );
preg_match( '/\*\s*Author URI:\s*(.+)$/m', $main_file, $author_uri );

$assert_same( 'Serfe', isset( $author[1] ) ? trim( $author[1] ) : '', 'Declares the expected plugin author' );
$assert_same( 'https://www.serfe.com/en/', isset( $author_uri[1] ) ? trim( $author_uri[1] ) : '', 'Declares the Serfe author URL' );

$assert_same(
	array(
		'<a href="https://example.test/wp-admin/admin.php?page=serfe-ai-discovery">Settings</a>',
		'<a href="#">Deactivate</a>',
	),
	$plugin->plugin_action_links( array( '<a href="#">Deactivate</a>' ) ),
	'Adds Settings as the first plugin action link'
);

$assert_same(
	array( '<a href="#">View details</a>' ),
	$plugin->plugin_row_meta( array( '<a href="#">View details</a>' ), 'another-plugin/another-plugin.php' ),
	'Does not change metadata for other plugins'
);

$assert_same(
	array(
		'<a href="#">View details</a>',
		'<a href="https://github.com/Serfe/serfe-ai-discovery" target="_blank" rel="noopener noreferrer">Community support</a>',
	),
	$plugin->plugin_row_meta( array( '<a href="#">View details</a>' ), 'serfe-ai-discovery/serfe-ai-discovery.php' ),
	'Adds the Community support metadata link'
);

if ( $failures ) {
	fwrite( STDERR, implode( "\n\n", $failures ) . "\n" );
	exit( 1 );
}

echo "All {$tests} tests passed.\n";
