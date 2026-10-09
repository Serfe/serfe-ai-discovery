<?php

define( 'ABSPATH', dirname( __DIR__ ) . '/' );
require_once dirname( __DIR__ ) . '/includes/class-discovery-files-admin.php';

use Serfe\AI_Discovery\Discovery_Files_Admin;

function wp_parse_url( $url ) {
	return parse_url( $url );
}

$cases = array(
	array( 'https://example.com/wordpress/?source=secret#fragment', 'production', 'https://isitagentready.com/example.com', true ),
	array( 'https://user:secret@example.com:8443/path', 'production', 'https://isitagentready.com/example.com', true ),
	array( 'https://example.com/', 'local', 'https://isitagentready.com/', false ),
	array( 'http://localhost:8080/', 'production', 'https://isitagentready.com/', false ),
	array( 'http://127.0.0.1:8080/', 'production', 'https://isitagentready.com/', false ),
	array( 'http://[::1]:8080/', 'production', 'https://isitagentready.com/', false ),
	array( 'http://192.168.1.5/', 'production', 'https://isitagentready.com/', false ),
	array( 'http://[fd12::1]/', 'production', 'https://isitagentready.com/', false ),
	array( 'http://127.1/', 'production', 'https://isitagentready.com/', false ),
	array( 'https://staging.example.test/', 'production', 'https://isitagentready.com/', false ),
	array( 'https://office.local./', 'production', 'https://isitagentready.com/', false ),
	array( 'https://8.8.8.8/', 'production', 'https://isitagentready.com/8.8.8.8', true ),
	array( 'https://[2001:4860:4860::8888]/', 'production', 'https://isitagentready.com/2001%3A4860%3A4860%3A%3A8888', true ),
);

foreach ( $cases as $case ) {
	$actual = Discovery_Files_Admin::agent_ready_target( $case[0], $case[1] );
	if ( $actual !== array( 'url' => $case[2], 'public' => $case[3] ) ) {
		fwrite( STDERR, 'Unexpected scanner link for ' . $case[0] . ': ' . var_export( $actual, true ) . "\n" );
		exit( 1 );
	}
}

echo 'All ' . count( $cases ) . " scanner link checks passed.\n";
