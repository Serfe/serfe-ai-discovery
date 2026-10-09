<?php

define( 'ABSPATH', dirname( __DIR__ ) . '/' );
require_once dirname( __DIR__ ) . '/includes/class-discovery-files.php';
require_once dirname( __DIR__ ) . '/includes/class-discovery-files-admin.php';
require_once dirname( __DIR__ ) . '/includes/class-settings.php';

use Serfe\AI_Discovery\Settings;

class WP_Error {
	private $code;
	private $message;

	public function __construct( $code, $message = '' ) {
		$this->code    = $code;
		$this->message = $message;
	}

	public function get_error_code() {
		return $this->code;
	}

	public function get_error_message() {
		return $this->message;
	}
}

function is_wp_error( $value ) {
	return $value instanceof WP_Error;
}

class WP_Post {
	public $post_type;

	public function __construct( $post_type ) {
		$this->post_type = $post_type;
	}
}

$GLOBALS['test_options'] = array();
$GLOBALS['test_item_meta'] = array();
$GLOBALS['test_can_edit'] = true;
$GLOBALS['test_can_manage'] = true;
$GLOBALS['test_is_revision'] = false;

function get_option( $key, $default = false ) {
	return isset( $GLOBALS['test_options'][ $key ] ) ? $GLOBALS['test_options'][ $key ] : $default;
}

function update_option( $key, $value ) {
	$GLOBALS['test_options'][ $key ] = $value;
}

function post_type_exists( $type ) {
	if ( 'product' === $type && ! empty( $GLOBALS['test_woocommerce_inactive'] ) ) {
		return false;
	}
	return in_array( $type, array( 'post', 'page', 'product', 'book', 'private_record' ), true );
}

function get_post_types( $args, $output ) {
	return array( 'post', 'page', 'book' );
}

function get_post_type_object( $type ) {
	if ( ! post_type_exists( $type ) ) {
		return null;
	}
	return (object) array( 'public' => 'private_record' !== $type, 'publicly_queryable' => 'private_record' !== $type, 'has_archive' => in_array( $type, array( 'product', 'book' ), true ) );
}

function taxonomy_exists( $taxonomy ) {
	return in_array( $taxonomy, array( 'product_cat', 'product_tag' ), true );
}

function get_taxonomy( $taxonomy ) {
	return taxonomy_exists( $taxonomy ) ? (object) array( 'public' => true, 'publicly_queryable' => true ) : null;
}

function term_exists( $term_id, $taxonomy ) {
	return in_array( $term_id, array( 123, 456 ), true ) ? $term_id : null;
}

function home_url( $path = '' ) {
	return 'https://example.com' . $path;
}

function site_url( $path = '' ) {
	return 'https://example.com' . $path;
}

function wp_parse_url( $url ) {
	return parse_url( $url );
}

function wp_parse_args( $args, $defaults ) {
	return array_merge( $defaults, $args );
}

function get_post_meta( $post_id, $key, $single ) {
	return isset( $GLOBALS['test_item_meta'][ $post_id ] ) ? $GLOBALS['test_item_meta'][ $post_id ] : '';
}

function wp_unslash( $value ) {
	return $value;
}

function sanitize_text_field( $value ) {
	return $value;
}

function wp_verify_nonce( $nonce, $action ) {
	return 'valid' === $nonce && Settings::NONCE_ACTION === $action;
}

function current_user_can( $capability, $post_id = null ) {
	return ( 'edit_post' === $capability && $GLOBALS['test_can_edit'] )
		|| ( 'manage_options' === $capability && $GLOBALS['test_can_manage'] );
}

function wp_is_post_revision( $post_id ) {
	return $GLOBALS['test_is_revision'];
}

function update_post_meta( $post_id, $key, $value ) {
	$GLOBALS['test_item_meta'][ $post_id ] = $value;
}

function delete_post_meta( $post_id, $key ) {
	unset( $GLOBALS['test_item_meta'][ $post_id ] );
}

$failures = array();
$tests = 0;
$assert_same = function ( $expected, $actual, $name ) use ( &$failures, &$tests ) {
	$tests++;
	if ( $expected !== $actual ) {
		$failures[] = $name . ': expected ' . var_export( $expected, true ) . ', got ' . var_export( $actual, true );
	}
};

$settings = new Settings();
$assert_same( false, Settings::markdown_enabled_for( 'post' ), 'Default disables Markdown' );
$assert_same( false, Settings::archive_enabled_for( 'book' ), 'Default disables archives' );
$assert_same( false, Settings::api_catalog_enabled(), 'Default disables API Catalog' );

$clean = $settings->sanitize( array(
	'markdown_enabled'   => '1',
	'blocked_post_types' => array( 'book', 'book', 'private_record', array( 'page' ) ),
	'blocked_paths'      => "/private-page/\nhttps://example.com/private-page/\nhttps://external.test/private/\n/../secrets\n/path?query=1",
	'api_catalog_enabled' => '1',
) );
$assert_same( 3, $clean['schema_version'], 'Stores versioned settings' );
$assert_same( array( 'book' ), $clean['blocked_post_types'], 'Validates excluded content types' );
$assert_same( array( '/private-page' ), $clean['blocked_paths'], 'Keeps only valid, same-site unique paths' );
$assert_same( '', Settings::normalize_blocked_path( 'https://example.com/path?query=1' ), 'Rejects URL query in exact path exclusion' );
$assert_same( '', Settings::normalize_blocked_path( '//external.test/path' ), 'Rejects protocol-relative URL' );
$GLOBALS['test_options'][ Settings::OPTION ] = $clean;
$assert_same( true, Settings::markdown_enabled_for( 'post' ), 'Enables public posts' );
$assert_same( true, Settings::markdown_enabled_for( 'page' ), 'Enables public pages' );
$assert_same( false, Settings::markdown_enabled_for( 'book' ), 'Excludes selected public CPTs' );
$assert_same( false, Settings::archive_enabled_for( 'book' ), 'Excludes matching CPT archives' );
$assert_same( true, Settings::archive_enabled_for( 'product_cat' ), 'Enables product category archives with Markdown' );
$assert_same( false, Settings::archive_enabled_for( 'page' ), 'Never enables a page archive' );
$GLOBALS['test_woocommerce_inactive'] = true;
$assert_same( false, Settings::archive_enabled_for( 'product' ), 'Product archive is unavailable without WooCommerce' );
$assert_same( false, Settings::archive_enabled_for( 'product_cat' ), 'Product taxonomy is unavailable without WooCommerce' );
$GLOBALS['test_woocommerce_inactive'] = false;
$assert_same( true, Settings::api_catalog_enabled(), 'Enables API Catalog only when selected' );

$GLOBALS['test_options'][ Settings::OPTION ] = array(
	'schema_version' => 2,
	'markdown_enabled' => 1,
	'detail_post_types' => array( 'post', 'book' ),
	'blocked_post_types' => array( 'book' ),
	'blocked_paths' => array( '/legacy-path' ),
	'api_catalog_enabled' => 1,
);
$assert_same( true, Settings::markdown_enabled_for( 'page' ), 'Legacy Markdown settings enable all public content before migration' );
$assert_same( false, Settings::archive_enabled_for( 'book' ), 'Legacy exclusions apply before migration' );
$GLOBALS['test_can_manage'] = false;
$settings->migrate_legacy_option();
$assert_same( 2, $GLOBALS['test_options'][ Settings::OPTION ]['schema_version'], 'User without manage_options cannot persist migration' );
$GLOBALS['test_can_manage'] = true;
$settings->migrate_legacy_option();
$migrated = $GLOBALS['test_options'][ Settings::OPTION ];
$assert_same( 3, $migrated['schema_version'], 'Persists schema migration' );
$assert_same( array( 'book' ), $migrated['blocked_post_types'], 'Preserves excluded content types' );
$assert_same( array( '/legacy-path' ), $migrated['blocked_paths'], 'Preserves excluded URL paths' );
$assert_same( 1, $migrated['api_catalog_enabled'], 'Preserves legacy catalog choice' );
$GLOBALS['test_options'][ Settings::OPTION ] = $clean;

if ( ! function_exists( '__' ) ) {
	function __( $text ) {
		return $text;
	}
}
if ( ! function_exists( 'esc_html__' ) ) {
	function esc_html__( $text ) {
		return $text;
	}
}
if ( ! function_exists( 'esc_attr__' ) ) {
	function esc_attr__( $text ) {
		return $text;
	}
}
if ( ! function_exists( 'sanitize_key' ) ) {
	function sanitize_key( $value ) {
		return is_string( $value ) ? strtolower( preg_replace( '/[^a-z0-9_\-]/i', '', $value ) ) : '';
	}
}
if ( ! function_exists( 'esc_html' ) ) {
	function esc_html( $text ) {
		return $text;
	}
}
if ( ! function_exists( 'esc_attr' ) ) {
	function esc_attr( $text ) {
		return $text;
	}
}
if ( ! function_exists( 'esc_url' ) ) {
	function esc_url( $url ) {
		return $url;
	}
}
if ( ! function_exists( 'wp_nonce_field' ) ) {
	function wp_nonce_field() {}
}
if ( ! function_exists( 'submit_button' ) ) {
	function submit_button( $text ) {
		echo '<button type="submit">' . $text . '</button>';
	}
}
if ( ! function_exists( 'admin_url' ) ) {
	function admin_url( $path = '' ) {
		return 'https://example.com/wp-admin/' . $path;
	}
}
if ( ! function_exists( 'get_transient' ) ) {
	function get_transient( $key ) {
		return isset( $GLOBALS['test_transients'][ $key ] ) ? $GLOBALS['test_transients'][ $key ] : false;
	}
}
if ( ! function_exists( 'delete_transient' ) ) {
	function delete_transient( $key ) {
		unset( $GLOBALS['test_transients'][ $key ] );
	}
}
if ( ! function_exists( 'set_transient' ) ) {
	function set_transient( $key, $value ) {
		$GLOBALS['test_transients'][ $key ] = $value;
		return true;
	}
}
if ( ! function_exists( 'get_current_user_id' ) ) {
	function get_current_user_id() {
		return 1;
	}
}
if ( ! function_exists( 'wp_get_environment_type' ) ) {
	function wp_get_environment_type() {
		return 'production';
	}
}
if ( ! function_exists( 'esc_textarea' ) ) {
	function esc_textarea( $text ) {
		return is_string( $text ) ? htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' ) : '';
	}
}
if ( ! function_exists( 'add_query_arg' ) ) {
	function add_query_arg( $args, $url = '' ) {
		return $url . '?' . http_build_query( $args );
	}
}

$_GET['tab'] = 'api-catalog';
ob_start();
$settings->render_page();
$api_catalog_page = ob_get_clean();
$assert_same( true, strpos( $api_catalog_page, 'tab=api-catalog' ) !== false, 'Nav tab includes API Catalog' );
$assert_same( true, strpos( $api_catalog_page, 'Disable API Catalog' ) !== false, 'API Catalog tab renders its disable action when enabled' );

$GLOBALS['test_options'][ Settings::OPTION ]['api_catalog_enabled'] = 0;
$_GET['tab'] = 'api-catalog';
ob_start();
$settings->render_page();
$api_catalog_disabled_page = ob_get_clean();
$assert_same( true, strpos( $api_catalog_disabled_page, 'Enable API Catalog' ) !== false, 'API Catalog tab renders its enable action when disabled' );

$state_icon   = new ReflectionMethod( Settings::class, 'state_icon' );
$check_state  = new ReflectionMethod( Settings::class, 'check_state' );
$checks_by_id = new ReflectionMethod( Settings::class, 'checks_by_id' );
if ( PHP_VERSION_ID < 80100 ) {
	$state_icon->setAccessible( true );
	$check_state->setAccessible( true );
	$checks_by_id->setAccessible( true );
}
$assert_same( '✅', $state_icon->invoke( null, 'ok' ), 'Overview icon for ready state' );
$assert_same( '⚠️', $state_icon->invoke( null, 'warn' ), 'Overview icon for attention state' );
$assert_same( '❌', $state_icon->invoke( null, 'fail' ), 'Overview icon for unavailable state' );
$assert_same( '➖', $state_icon->invoke( null, 'na' ), 'Overview icon for neutral state' );
$assert_same( '➖', $state_icon->invoke( null, 'unexpected' ), 'Unknown level falls back to neutral icon' );
$assert_same( array( 'ok', 'Available', 'detail', 'now' ), $check_state->invoke( null, array( 'status' => 'pass', 'detail' => 'detail' ), 'now' ), 'Passing check is ready' );
$assert_same( 'fail', $check_state->invoke( null, array( 'status' => 'fail' ), 'now' )[0], 'Failing check is unavailable' );
$assert_same( 'warn', $check_state->invoke( null, array( 'status' => 'unknown' ), 'now' )[0], 'Unverifiable check needs attention' );
$assert_same( 'na', $check_state->invoke( null, array( 'status' => 'na' ), 'now' )[0], 'Not applicable check is neutral' );
$assert_same( null, $check_state->invoke( null, null, 'now' ), 'Missing check yields no state' );
$indexed = $checks_by_id->invoke( null, array( 'checks' => array( array( 'id' => 'sitemap', 'status' => 'pass' ), array( 'status' => 'pass', 'label' => 'stale' ) ) ) );
$assert_same( array( 'sitemap' ), array_keys( $indexed ), 'Checks are indexed by stable id and stale entries are ignored' );
$assert_same( array(), $checks_by_id->invoke( null, false ), 'No cached report yields no checks' );

if ( $failures ) {
	fwrite( STDERR, implode( "\n", $failures ) . "\n" );
	exit( 1 );
}

echo "All {$tests} settings tests passed.\n";
