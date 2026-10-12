<?php

define( 'ABSPATH', dirname( __DIR__ ) . '/' );

function __( $text ) {
	return $text;
}

function home_url( $path = '' ) {
	return 'https://example.com' . $path;
}

function get_option( $option ) {
	return 'date_format' === $option ? 'd/m/Y' : 'H:i';
}

function wp_timezone() {
	return new DateTimeZone( 'America/Argentina/Buenos_Aires' );
}

function wp_date( $format, $timestamp, $timezone ) {
	return ( new DateTimeImmutable( '@' . $timestamp ) )->setTimezone( $timezone )->format( $format );
}

function wp_parse_url( $url, $component = -1 ) {
	return parse_url( $url, $component );
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
$assert_same( '12/10/2026 20:30', Readiness::format_checked_at( '2026-10-12 20:30:00' ), 'Formats check timestamps with the WordPress date and time settings' );
$assert_same(
	array(
		array(
			'url'  => 'https://example.com/llms.txt',
			'rel'  => 'describedby',
			'type' => 'text/plain',
		),
		array(
			'url'  => 'https://example.com/sitemap.xml',
			'rel'  => 'related',
			'type' => 'application/xml',
		),
	),
	Readiness::agent_discovery_links_from_checks(
		array(
			'status' => 'pass',
			'url'    => 'https://example.com/llms.txt',
		),
		array(
			'status' => 'pass',
			'url'    => 'https://example.com/sitemap.xml',
		)
	),
	'Builds RFC 8288 Link header values from validated resources'
);
$assert_same(
	array(),
	Readiness::agent_discovery_links_from_checks( array( 'status' => 'fail' ), array( 'status' => 'fail' ) ),
	'Does not announce unavailable discovery resources'
);
$assert_same( true, Readiness::is_same_origin_url( 'https://example.com/sitemap.xml' ), 'Allows same-origin validation' );
$assert_same( false, Readiness::is_same_origin_url( 'https://other.example/sitemap.xml' ), 'Rejects external validation' );
$assert_same( false, Readiness::is_same_origin_url( 'https://example.com:8443/sitemap.xml' ), 'Rejects a different port' );
$assert_same( false, Readiness::is_same_origin_url( 'http://example.com/sitemap.xml' ), 'Rejects a different scheme' );
$assert_same( false, Readiness::is_same_origin_url( 'https://user@example.com/sitemap.xml' ), 'Rejects credentials in validation URL' );

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
