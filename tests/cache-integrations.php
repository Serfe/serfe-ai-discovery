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

function sanitize_text_field( $text ) {
	return trim( (string) $text );
}

function wp_unslash( $value ) {
	return $value;
}

function wp_parse_url( $url, $component = -1 ) {
	return parse_url( (string) $url, $component );
}

function home_url( $path = '' ) {
	return 'https://example.com' . $path;
}

function rest_url() {
	return 'https://example.com/wp-json/';
}

function esc_url_raw( $url ) {
	return (string) $url;
}

function wp_json_encode( $data ) {
	return json_encode( $data );
}

function post_type_exists( $type ) {
	return in_array( $type, array( 'post', 'page' ), true );
}

function get_post_types() {
	return array( 'post', 'page' );
}

function get_post_type_object() {
	return null;
}

function wp_parse_args( $args, $defaults = array() ) {
	return array_merge( $defaults, (array) $args );
}

function add_filter( $hook, $callback, $priority = 10, $accepted_args = 1 ) {}
function add_action( $hook, $callback, $priority = 10, $accepted_args = 1 ) {}
function do_action( $hook, $arg = '' ) {
	$GLOBALS['cache_test_actions'][] = array( $hook, $arg );
}

require_once dirname( __DIR__ ) . '/includes/class-settings.php';
require_once dirname( __DIR__ ) . '/includes/class-request-router.php';
require_once dirname( __DIR__ ) . '/includes/class-readiness.php';
require_once dirname( __DIR__ ) . '/includes/class-product-generator.php';
require_once dirname( __DIR__ ) . '/includes/class-discovery.php';
require_once dirname( __DIR__ ) . '/includes/class-markdown-cache.php';
require_once dirname( __DIR__ ) . '/includes/class-cache-integrations.php';

use Serfe\AI_Discovery\Cache_Integrations;
use Serfe\AI_Discovery\Markdown_Cache;
use Serfe\AI_Discovery\Readiness;

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

// 1. Check URI rejection for WP Rocket and LiteSpeed Cache.
$initial_uris  = array( '/sample-page/' );
$rejected_uris = Cache_Integrations::filter_rocket_reject_uri( $initial_uris );
$assert_true( in_array( '/(.*)\.md(?:/.*)?$', $rejected_uris, true ), 'Rejects .md URIs in WP Rocket' );
$assert_same( 2, count( $rejected_uris ), 'Appends to existing rejected URIs without duplicates' );
$rejected_again = Cache_Integrations::filter_rocket_reject_uri( $rejected_uris );
$assert_same( 2, count( $rejected_again ), 'Does not duplicate pattern when called repeatedly' );

$litespeed_uris = Cache_Integrations::filter_litespeed_exclude_uri( array( '/cart/' ) );
$assert_true( in_array( '/(.*)\.md$', $litespeed_uris, true ), 'Excludes .md URIs in LiteSpeed Cache' );
$assert_same( 2, count( Cache_Integrations::filter_litespeed_exclude_uri( $litespeed_uris ) ), 'Does not duplicate LiteSpeed URI exclusion' );

// 2. Check User Agent rejection for WP Rocket.
$initial_uas  = array( 'Mozilla/5.0' );
$rejected_uas = Cache_Integrations::filter_rocket_reject_ua( $initial_uas );
$assert_true( in_array( 'curl', $rejected_uas, true ), 'Rejects curl user agent' );
$assert_true( in_array( 'GPTBot', $rejected_uas, true ), 'Rejects GPTBot user agent' );
$assert_true( in_array( 'ClaudeBot', $rejected_uas, true ), 'Rejects ClaudeBot user agent' );
$assert_true( in_array( 'PerplexityBot', $rejected_uas, true ), 'Rejects PerplexityBot user agent' );

// 3. Check LiteSpeed / W3 Total Cache can_cache filter before DONOTCACHEPAGE is defined.
$_SERVER['REQUEST_URI'] = '/sample-page/';
$_SERVER['HTTP_ACCEPT'] = 'text/html,application/xhtml+xml';
$assert_same( true, Cache_Integrations::filter_can_cache_request( true ), 'Allows caching normal HTML requests in LiteSpeed and W3TC' );

$_SERVER['HTTP_ACCEPT'] = 'text/markdown, text/html;q=0.8';
$assert_same( false, Cache_Integrations::filter_can_cache_request( true ), 'Prevents caching Accept: text/markdown requests in LiteSpeed and W3TC' );

$_SERVER['HTTP_ACCEPT'] = 'text/html';
$_SERVER['REQUEST_URI'] = '/sample-page.md';
$assert_same( false, Cache_Integrations::filter_can_cache_request( true ), 'Prevents caching .md URI requests in LiteSpeed and W3TC' );

$_SERVER['REQUEST_URI'] = '/sample-page/';
$_GET['markdown']       = '1';
$assert_same( false, Cache_Integrations::filter_can_cache_request( true ), 'Prevents caching ?markdown=1 requests in LiteSpeed and W3TC' );
unset( $_GET['markdown'] );

// 4. Check Apache .htaccess rewrite rule filter for WP Rocket and W3 Total Cache Disk: Enhanced.
$sample_htaccess = "<IfModule mod_rewrite.c>\nRewriteEngine On\nRewriteCond %{REQUEST_METHOD} GET\nRewriteRule .* \"/cache/index.html\" [L]\n</IfModule>";
$filtered_rules  = Cache_Integrations::filter_htaccess_bypass_markdown( $sample_htaccess );
$assert_true( false !== strpos( $filtered_rules, 'RewriteCond %{HTTP:Accept} !text/markdown [NC]' ), 'Injects Accept: text/markdown bypass condition into .htaccess mod_rewrite rules' );
$assert_same( $filtered_rules, Cache_Integrations::filter_htaccess_bypass_markdown( $filtered_rules ), 'Does not duplicate .htaccess RewriteCond when called repeatedly' );

// 5. Check WP Rocket config file and .htaccess Link header generation.
update_option( \Serfe\AI_Discovery\Settings::OPTION, array( 'api_catalog_enabled' => 1, 'schema_version' => 3 ) );
set_transient(
	Readiness::LINK_HEADERS_CACHE_KEY,
	array(
		array(
			'url'  => 'https://example.com/llms.txt',
			'rel'  => 'describedby',
			'type' => 'text/plain',
		),
		array(
			'url'  => 'https://example.com/wp-sitemap.xml',
			'rel'  => 'related',
			'type' => 'application/xml',
		),
	)
);
$rocket_config = Cache_Integrations::filter_rocket_config_file( "<?php\n\$rocket_cache_reject_uri = '/(.*)\\.md(?:/.*)?$';\n" );
$assert_true( false !== strpos( $rocket_config, '$rocket_cache_reject_uri = \'.*\';' ), 'Injects Accept: text/markdown cache bypass into WP Rocket config file' );
$assert_true( false !== strpos( $rocket_config, 'rel="describedby"' ), 'Injects llms.txt Link header into WP Rocket config file' );
$assert_true( false !== strpos( $rocket_config, 'rel="api-catalog"' ), 'Injects homepage API catalog Link header into WP Rocket config file' );

$after_htaccess = Cache_Integrations::filter_rocket_after_htaccess_rules( '' );
$assert_true( false !== strpos( $after_htaccess, 'Header append Vary "Accept"' ), 'Adds Vary: Accept header rule for static WP Rocket HTML files' );
$assert_true( false !== strpos( $after_htaccess, 'rel=\"describedby\"' ), 'Adds Link header rule for static WP Rocket HTML files' );

// 6. Check constant definition and LiteSpeed nocache hook.
$GLOBALS['cache_test_actions'] = array();
Cache_Integrations::disable_page_caching();
$assert_true( defined( 'DONOTCACHEPAGE' ) && true === constant( 'DONOTCACHEPAGE' ), 'Defines DONOTCACHEPAGE' );
$assert_true( defined( 'DONOTROCKETOPTIMIZE' ) && true === constant( 'DONOTROCKETOPTIMIZE' ), 'Defines DONOTROCKETOPTIMIZE' );
$assert_true( defined( 'DONOTMINIFY' ) && true === constant( 'DONOTMINIFY' ), 'Defines DONOTMINIFY' );
$assert_same( 'litespeed_control_set_nocache', $GLOBALS['cache_test_actions'][0][0] ?? '', 'Triggers litespeed_control_set_nocache action when disabling page caching' );

// 7. Check cache invalidation synchronization on external cache purge.
$gen_before = Markdown_Cache::state()['generation'];
Cache_Integrations::on_cache_purged();
$gen_after = Markdown_Cache::state()['generation'];
$assert_same( $gen_before + 1, $gen_after, 'Advances Markdown cache generation on external cache purge' );

// 8. Check purge_page_caches does not fatal error without cache plugins active.
Cache_Integrations::purge_page_caches();
$assert_true( true, 'purge_page_caches executes safely when third party plugins are inactive' );

if ( $failures ) {
	fwrite( STDERR, implode( "\n", $failures ) . "\n" );
	exit( 1 );
}
echo "All {$tests} cache integration tests passed.\n";
