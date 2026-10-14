<?php

define( 'ABSPATH', dirname( __DIR__ ) . '/' );

$GLOBALS['cache_test_options']    = array();
$GLOBALS['cache_test_transients'] = array();

function get_option( $name, $fallback = false ) {
	return array_key_exists( $name, $GLOBALS['cache_test_options'] ) ? $GLOBALS['cache_test_options'][ $name ] : $fallback;
}

function update_option( $name, $value ) {
	$GLOBALS['cache_test_options'][ $name ] = $value;
	return true;
}

function get_transient( $key ) {
	return array_key_exists( $key, $GLOBALS['cache_test_transients'] ) ? $GLOBALS['cache_test_transients'][ $key ] : false;
}

function set_transient( $key, $value ) {
	$GLOBALS['cache_test_transients'][ $key ] = $value;
	return true;
}

function get_locale() {
	return 'en_US';
}

function current_time() {
	return '2026-10-14 12:00:00';
}

function sanitize_key( $key ) {
	return strtolower( preg_replace( '/[^a-z0-9_\-]/i', '', (string) $key ) );
}

function add_filter( $hook, $callback, $priority = 10, $accepted_args = 1 ) {}
function add_action( $hook, $callback, $priority = 10, $accepted_args = 1 ) {}

require_once dirname( __DIR__ ) . '/includes/class-settings.php';
require_once dirname( __DIR__ ) . '/includes/class-markdown-cache.php';
require_once dirname( __DIR__ ) . '/includes/class-cache-integrations.php';

use Serfe\AI_Discovery\Cache_Integrations;
use Serfe\AI_Discovery\Markdown_Cache;

$failures    = array();
$tests       = 0;
$assert_same = function ( $expected, $actual, $name ) use ( &$failures, &$tests ) {
	$tests++;
	if ( $expected !== $actual ) {
		$failures[] = $name . ': expected ' . var_export( $expected, true ) . ', got ' . var_export( $actual, true );
	}
};
$assert_true = function ( $condition, $name ) use ( &$failures, &$tests ) {
	$tests++;
	if ( ! $condition ) {
		$failures[] = $name . ': expected true, got false';
	}
};

// 1. Check URI rejection for WP Rocket.
$initial_uris = array( '/sample-page/' );
$rejected_uris = Cache_Integrations::filter_rocket_reject_uri( $initial_uris );
$assert_true( in_array( '/(.*)\.md(?:/.*)?$', $rejected_uris, true ), 'Rejects .md URIs in WP Rocket' );
$assert_same( 2, count( $rejected_uris ), 'Appends to existing rejected URIs without duplicates' );
$rejected_again = Cache_Integrations::filter_rocket_reject_uri( $rejected_uris );
$assert_same( 2, count( $rejected_again ), 'Does not duplicate pattern when called repeatedly' );

// 2. Check User Agent rejection for WP Rocket.
$initial_uas = array( 'Mozilla/5.0' );
$rejected_uas = Cache_Integrations::filter_rocket_reject_ua( $initial_uas );
$assert_true( in_array( 'curl', $rejected_uas, true ), 'Rejects curl user agent' );
$assert_true( in_array( 'GPTBot', $rejected_uas, true ), 'Rejects GPTBot user agent' );
$assert_true( in_array( 'ClaudeBot', $rejected_uas, true ), 'Rejects ClaudeBot user agent' );
$assert_true( in_array( 'PerplexityBot', $rejected_uas, true ), 'Rejects PerplexityBot user agent' );

// 3. Check constant definition.
Cache_Integrations::disable_page_caching();
$assert_true( defined( 'DONOTCACHEPAGE' ) && true === constant( 'DONOTCACHEPAGE' ), 'Defines DONOTCACHEPAGE' );
$assert_true( defined( 'DONOTROCKETOPTIMIZE' ) && true === constant( 'DONOTROCKETOPTIMIZE' ), 'Defines DONOTROCKETOPTIMIZE' );
$assert_true( defined( 'DONOTMINIFY' ) && true === constant( 'DONOTMINIFY' ), 'Defines DONOTMINIFY' );

// 4. Check cache invalidation synchronization on external cache purge.
$gen_before = Markdown_Cache::state()['generation'];
Cache_Integrations::on_cache_purged();
$gen_after = Markdown_Cache::state()['generation'];
$assert_same( $gen_before + 1, $gen_after, 'Advances Markdown cache generation on external cache purge' );

// 5. Check purge_page_caches does not fatal error without cache plugins active.
Cache_Integrations::purge_page_caches();
$assert_true( true, 'purge_page_caches executes safely when third party plugins are inactive' );

if ( $failures ) {
	fwrite( STDERR, implode( "\n", $failures ) . "\n" );
	exit( 1 );
}
echo "All {$tests} cache integration tests passed.\n";
