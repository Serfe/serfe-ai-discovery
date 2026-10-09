<?php

define( 'ABSPATH', dirname( __DIR__ ) . '/' );

$GLOBALS['cache_test_options']    = array();
$GLOBALS['cache_test_transients'] = array();
$GLOBALS['cache_test_post_types'] = array(
	10 => 'post',
	20 => 'product',
	30 => 'nav_menu_item',
);

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
	return '2026-10-08 12:00:00';
}

function sanitize_key( $key ) {
	return strtolower( preg_replace( '/[^a-z0-9_\-]/i', '', $key ) );
}

function get_post_type( $id ) {
	return isset( $GLOBALS['cache_test_post_types'][ $id ] ) ? $GLOBALS['cache_test_post_types'][ $id ] : false;
}

function wp_is_post_revision() {
	return false;
}

function wp_is_post_autosave() {
	return false;
}

require_once dirname( __DIR__ ) . '/includes/class-settings.php';
require_once dirname( __DIR__ ) . '/includes/class-markdown-cache.php';

use Serfe\AI_Discovery\Markdown_Cache;
use Serfe\AI_Discovery\Settings;

$failures    = array();
$tests       = 0;
$assert_same = function ( $expected, $actual, $name ) use ( &$failures, &$tests ) {
	$tests++;
	if ( $expected !== $actual ) {
		$failures[] = $name . ': expected ' . var_export( $expected, true ) . ', got ' . var_export( $actual, true );
	}
};

$builds = 0;
$build  = function () use ( &$builds ) {
	++$builds;
	return "# Doc {$builds}\n";
};

$first  = Markdown_Cache::remember( 'post', '10', 'https://example.com/a/', $build );
$second = Markdown_Cache::remember( 'post', '10', 'https://example.com/a/', $build );
$assert_same( false, $first['hit'], 'First request generates Markdown' );
$assert_same( true, $second['hit'], 'Second request reuses stored Markdown' );
$assert_same( $first['markdown'], $second['markdown'], 'Stored Markdown is returned unchanged' );
$assert_same( 1, $builds, 'Generator runs once while the cache is valid' );

$other = Markdown_Cache::remember( 'post', '10', 'https://example.com/a/page/2/', $build );
$assert_same( false, $other['hit'], 'A different canonical URL never shares a cached document' );

$cache = new Markdown_Cache();
$cache->post_changed( 30 );
$assert_same( 1, Markdown_Cache::state()['generation'], 'Navigation menu changes do not invalidate Markdown' );

$cache->post_changed( 10 );
$assert_same( 2, Markdown_Cache::state()['generation'], 'Content changes advance the cache generation' );
$assert_same( 'content', Markdown_Cache::state()['reason'], 'Invalidation records its reason' );
$after = Markdown_Cache::remember( 'post', '10', 'https://example.com/a/', $build );
$assert_same( false, $after['hit'], 'Documents from an older generation are not served' );

$cache->meta_changed( 1, 10, '_edit_lock' );
$assert_same( 2, Markdown_Cache::state()['generation'], 'Editor lock metadata does not invalidate Markdown' );
$cache->meta_changed( 1, 10, 'unrelated_post_meta' );
$assert_same( 2, Markdown_Cache::state()['generation'], 'Unrelated editorial metadata does not invalidate Markdown' );
$cache->meta_changed( 1, 20, '_price' );
$assert_same( 3, Markdown_Cache::state()['generation'], 'Product price metadata invalidates Markdown' );
$cache->meta_changed( 1, 10, Settings::ITEM_META );
$assert_same( 4, Markdown_Cache::state()['generation'], 'Item approval changes invalidate Markdown' );

$cache->option_changed( '_transient_example' );
$cache->option_changed( 'cron' );
$cache->option_changed( 'woocommerce_db_version' );
$assert_same( 4, Markdown_Cache::state()['generation'], 'Transients and unrelated options do not invalidate Markdown' );
$cache->option_changed( Settings::OPTION );
$assert_same( 'settings', Markdown_Cache::state()['reason'], 'Plugin settings changes invalidate Markdown' );
$cache->option_changed( 'woocommerce_currency' );
$assert_same( 6, Markdown_Cache::state()['generation'], 'Store currency changes invalidate Markdown' );

$large = Markdown_Cache::remember(
	'post',
	'99',
	'https://example.com/large/',
	function () {
		return str_repeat( 'a', Markdown_Cache::MAX_BYTES + 1 );
	}
);
$again = Markdown_Cache::remember(
	'post',
	'99',
	'https://example.com/large/',
	function () {
		return 'rebuilt';
	}
);
$assert_same( false, $large['hit'], 'Oversized documents are generated' );
$assert_same( 'rebuilt', $again['markdown'], 'Oversized documents are not stored' );

if ( $failures ) {
	fwrite( STDERR, implode( "\n", $failures ) . "\n" );
	exit( 1 );
}

echo "All {$tests} Markdown cache tests passed.\n";
