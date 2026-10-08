<?php

define( 'ABSPATH', dirname( __DIR__ ) . '/' );

function __( $text ) {
	return $text;
}

require_once dirname( __DIR__ ) . '/includes/class-readiness.php';

use Serfe\AI_Discovery\Readiness;

$failures = array();
$tests    = 0;
$assert_same = function ( $expected, $actual, $name ) use ( &$failures, &$tests ) {
	$tests++;
	if ( $expected !== $actual ) {
		$failures[] = $name . ': expected ' . var_export( $expected, true ) . ', got ' . var_export( $actual, true );
	}
};

$assert_same(
	true,
	Readiness::valid_oauth_metadata(
		array(
			'resource'              => 'https://example.com/wp-json/serfe/v1/',
			'authorization_servers' => array( 'https://login.example.com' ),
		)
	),
	'Accepts OAuth metadata with a resource and HTTPS authorization server'
);
$assert_same(
	false,
	Readiness::valid_oauth_metadata( array( 'resource' => 'https://example.com', 'authorization_servers' => array( 'http://login.example.com' ) ) ),
	'Rejects OAuth metadata with a non-HTTPS authorization server'
);

$valid_catalog = array(
	'linkset' => array(
		array(
			'anchor' => 'https://example.com/.well-known/api-catalog',
			'item'   => array(
				array( 'href' => 'https://example.com/wp-json/', 'type' => 'application/json' ),
			),
		),
	),
);
$assert_same( true, Readiness::valid_api_catalog( $valid_catalog ), 'Accepts a valid RFC 9727 API catalog' );
$assert_same( false, Readiness::valid_api_catalog( array() ), 'Rejects an invalid API catalog' );

$valid_skill = array(
	'$schema' => Readiness::SKILLS_SCHEMA,
	'skills'  => array(
		array(
			'name'        => 'public-catalog',
			'type'        => 'skill-md',
			'description' => 'Use the public catalog.',
			'url'         => '/.well-known/agent-skills/public-catalog/SKILL.md',
			'digest'      => 'sha256:aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa',
		),
	),
);
$assert_same( true, Readiness::validate_skills_index( $valid_skill )['valid'], 'Accepts a valid Agent Skills v0.2.0 index' );
$valid_skill['skills'][0]['digest'] = 'sha256:not-a-digest';
$assert_same( false, Readiness::validate_skills_index( $valid_skill )['valid'], 'Rejects an invalid Agent Skills digest' );

if ( $failures ) {
	fwrite( STDERR, implode( "\n", $failures ) . "\n" );
	exit( 1 );
}

echo "All {$tests} readiness tests passed.\n";

