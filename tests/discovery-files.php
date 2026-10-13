<?php

$base = sys_get_temp_dir() . '/serfe-discovery-files-' . bin2hex( random_bytes( 6 ) );
mkdir( $base );
mkdir( $base . '/wp' );
define( 'ABSPATH', $base . '/wp/' );

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

function __( $text ) {
	return $text;
}

function wp_parse_url( $url ) {
	return parse_url( $url );
}

function trailingslashit( $value ) {
	return rtrim( $value, '/' ) . '/';
}

function apply_filters( $hook, $value ) {
	return isset( $GLOBALS['test_size_limit'] ) ? $GLOBALS['test_size_limit'] : $value;
}

function home_url( $path = '' ) {
	return 'https://example.com' . $path;
}

function site_url( $path = '' ) {
	return 'https://example.com/wp' . $path;
}

function is_wp_error( $value ) {
	return $value instanceof WP_Error;
}

function current_user_can( $capability ) {
	return ! empty( $GLOBALS['test_can_manage'] );
}

function get_option( $name, $default = false ) {
	return array_key_exists( $name, $GLOBALS['test_options'] ) ? $GLOBALS['test_options'][ $name ] : $default;
}

function update_option( $name, $value, $autoload = null ) {
	$GLOBALS['test_options'][ $name ] = $value;
	return true;
}

function current_time( $type ) {
	return '2026-10-08 12:00:00';
}

function get_current_user_id() {
	return 7;
}

function wp_safe_remote_get( $url, $args ) {
	if ( false !== strpos( $url, 'sitemap.xml' ) ) {
		$GLOBALS['test_sitemap_gets'][] = $url;
		return array( 'code' => 200, 'body' => '<urlset/>' );
	}
	$path = $GLOBALS['test_root'] . '/' . basename( $url );
	$GLOBALS['test_public_gets'][] = $url;
	if ( ! file_exists( $path ) ) {
		return 'robots.txt' === basename( $url ) && isset( $GLOBALS['test_virtual_robots'] )
			? array( 'code' => 200, 'body' => $GLOBALS['test_virtual_robots'], 'content_type' => 'text/plain; charset=utf-8' )
			: array( 'code' => 404, 'body' => '', 'content_type' => 'text/html' );
	}
	return array( 'code' => 200, 'body' => ! empty( $GLOBALS['test_public_mismatch'] ) ? 'stale cache' : file_get_contents( $path ), 'content_type' => 'text/plain; charset=utf-8' );
}

function wp_safe_remote_head( $url, $args ) {
	$GLOBALS['test_head_calls'][] = $url;
	return array( 'code' => isset( $GLOBALS['test_sitemap_status'] ) ? $GLOBALS['test_sitemap_status'] : 200 );
}

function wp_remote_retrieve_response_code( $response ) {
	return $response['code'];
}

function wp_remote_retrieve_body( $response ) {
	return $response['body'];
}

function wp_remote_retrieve_header( $response, $header ) {
	return 'content-type' === $header ? $response['content_type'] : '';
}

require_once dirname( __DIR__ ) . '/includes/class-discovery-files.php';

use Serfe\AI_Discovery\Discovery_Files;

$GLOBALS['test_root']       = $base;
$GLOBALS['test_options']    = array();
$GLOBALS['test_can_manage'] = true;
$tests                     = 0;
$failures                  = array();
$assert_same               = function ( $expected, $actual, $name ) use ( &$tests, &$failures ) {
	++$tests;
	if ( $expected !== $actual ) {
		$failures[] = $name . ': expected ' . var_export( $expected, true ) . ', got ' . var_export( $actual, true );
	}
};
$assert_error = function ( $code, $actual, $name ) use ( $assert_same ) {
	$assert_same( $code, $actual instanceof WP_Error ? $actual->get_error_code() : null, $name );
};

$assert_same( $base, Discovery_Files::resolve_public_root( ABSPATH, home_url( '/' ), site_url( '/' ) ), 'Maps a WordPress subdirectory to the public root' );
$assert_same( realpath( ABSPATH ), Discovery_Files::resolve_public_root( ABSPATH, 'https://example.com/wp/', 'https://example.com/wp/' ), 'Maps matching site and home URLs to WordPress root' );
$assert_error( 'root_unresolved', Discovery_Files::resolve_public_root( ABSPATH, 'https://other.example/', site_url( '/' ) ), 'Rejects a different public host' );
$assert_error( 'root_unresolved', Discovery_Files::resolve_public_root( ABSPATH, home_url( '/' ), 'https://example.com/other/' ), 'Rejects mismatched directories' );
symlink( $base . '/wp', $base . '/linked-wp' );
$assert_error( 'root_unresolved', Discovery_Files::resolve_public_root( $base . '/linked-wp', 'https://example.com/wp/', 'https://example.com/wp/' ), 'Rejects a symlinked WordPress directory' );

$assert_same( "Hello\nWorld\n", Discovery_Files::normalize_content( "Hello\r\nWorld\r" ), 'Normalizes line endings' );
$assert_error( 'invalid_encoding', Discovery_Files::normalize_content( "\xC3\x28" ), 'Rejects invalid UTF-8' );
$assert_error( 'invalid_control', Discovery_Files::normalize_content( "Hello\x00" ), 'Rejects null bytes' );
$GLOBALS['test_size_limit'] = 4;
$assert_error( 'file_too_large', Discovery_Files::normalize_content( 'Hello' ), 'Honors configured size limit' );
unset( $GLOBALS['test_size_limit'] );

$assert_error( 'invalid_name', Discovery_Files::inspect( '../secret' ), 'Restricts names to fixed public files' );
$GLOBALS['test_can_manage'] = false;
$assert_error( 'forbidden', Discovery_Files::publish( 'robots.txt', 'User-agent: *' ), 'Requires administrator capability' );
$GLOBALS['test_can_manage'] = true;
$GLOBALS['test_public_gets'] = array();
$GLOBALS['test_virtual_robots'] = "User-agent: *\nDisallow: /virtual/\n";
$state = Discovery_Files::inspect( 'robots.txt' );
$assert_same( false, $state['exists'], 'Distinguishes missing physical file from public response' );
$assert_same( array(), $GLOBALS['test_public_gets'], 'Does not make a loopback request on passive inspection' );
$virtual = Discovery_Files::validate_current( 'robots.txt' );
$assert_same( true, $virtual['virtual'], 'Validation detects virtual robots.txt' );
$assert_same( $GLOBALS['test_virtual_robots'], $virtual['content'], 'Validation returns the effective public content' );
$assert_error( 'takeover_required', Discovery_Files::publish( 'robots.txt', "User-agent: *\n", false, $virtual['checksum'] ), 'Rejects silent replacement of virtual robots.txt' );
$assert_error( 'stale_file', Discovery_Files::publish( 'robots.txt', "User-agent: *\n", true, 'wrong' ), 'Rejects stale virtual takeover confirmation' );
unset( $GLOBALS['test_virtual_robots'] );
$assert_error( 'stale_file', Discovery_Files::publish( 'robots.txt', "User-agent: *\n", true, $virtual['checksum'] ), 'Rejects takeover when the reviewed virtual response disappears' );
$GLOBALS['test_virtual_robots'] = "User-agent: *\nDisallow: /virtual/\n";
$result = Discovery_Files::publish( 'robots.txt', "User-agent: *\n", true, $virtual['checksum'] );
$assert_same( true, $result['verified'], 'Publishes a physical file after explicit virtual takeover' );
$assert_same( $GLOBALS['test_virtual_robots'], get_option( 'serfe_ai_discovery_robots_txt_backup' ), 'Preserves previous virtual response as backup' );
unlink( $base . '/robots.txt' );
unset( $GLOBALS['test_virtual_robots'], $GLOBALS['test_options']['serfe_ai_discovery_robots_txt_meta'], $GLOBALS['test_options']['serfe_ai_discovery_robots_txt_backup'] );
file_put_contents( $base . '/robots.txt', "User-agent: *\nDisallow: /old/\n" );
$current = Discovery_Files::inspect( 'robots.txt' );
$assert_same( true, $current['exists'], 'Reads the existing public file' );
$assert_error( 'takeover_required', Discovery_Files::publish( 'robots.txt', "User-agent: *\n" ), 'Requires first takeover of an existing file' );
$assert_error( 'stale_file', Discovery_Files::publish( 'robots.txt', "User-agent: *\n", true, 'wrong' ), 'Rejects stale takeover confirmation' );
$result = Discovery_Files::publish( 'robots.txt', "User-agent: *\r\n", true, $current['checksum'] );
$assert_same( true, $result['verified'], 'Confirms a published file through its public URL' );
$assert_same( "User-agent: *\n", file_get_contents( $base . '/robots.txt' ), 'Publishes normalized content' );
$assert_same( $current['content'], get_option( 'serfe_ai_discovery_robots_txt_backup' ), 'Keeps the previous public file' );
$assert_same( 7, get_option( 'serfe_ai_discovery_robots_txt_meta' )['user_id'], 'Stores publisher identity' );
$assert_same( true, get_option( 'serfe_ai_discovery_robots_txt_meta' )['verified'], 'Stores public response verification' );
$assert_same( true, in_array( 'No sitemap is declared in robots.txt.', $result['warnings'], true ), 'Warns when robots.txt has no sitemap' );
$GLOBALS['test_head_calls'] = array();
$warnings = Discovery_Files::robots_warnings( "Sitemap: not-a-url\n" );
$assert_same( true, in_array( 'A Sitemap URL is malformed. Review its absolute public URL.', $warnings, true ), 'Warns about a malformed sitemap URL' );
$assert_same( array(), $GLOBALS['test_head_calls'], 'Does not request malformed sitemap URLs' );
$GLOBALS['test_sitemap_status'] = 404;
$warnings = Discovery_Files::robots_warnings( "Sitemap: https://example.com/sitemap.xml\n" );
$assert_same( true, in_array( 'The Sitemap URL could not be reached: https://example.com/sitemap.xml', $warnings, true ), 'Warns about an unreachable sitemap' );
$assert_same( array( 'https://example.com/sitemap.xml' ), $GLOBALS['test_head_calls'], 'Uses a safe HEAD request for a sitemap' );
$GLOBALS['test_sitemap_status'] = 405;
$GLOBALS['test_sitemap_gets']   = array();
$warnings = Discovery_Files::robots_warnings( "Sitemap: https://example.com/sitemap.xml\n" );
$assert_same( array(), $warnings, 'Accepts a sitemap when HEAD is unsupported but GET succeeds' );
$assert_same( array( 'https://example.com/sitemap.xml' ), $GLOBALS['test_sitemap_gets'], 'Falls back to a bounded safe GET after HTTP 405' );
$GLOBALS['test_sitemap_status'] = 200;
$GLOBALS['test_head_calls'] = array();
$sitemap_lines = '';
for ( $i = 1; $i <= 6; $i++ ) {
	$sitemap_lines .= "Sitemap: https://example.com/sitemap-{$i}.xml\n";
}
Discovery_Files::robots_warnings( $sitemap_lines );
$assert_same( Discovery_Files::MAX_SITEMAPS, count( $GLOBALS['test_head_calls'] ), 'Bounds sitemap requests on validation' );
$GLOBALS['test_public_mismatch'] = true;
$validation = Discovery_Files::validate_current( 'robots.txt' );
$assert_same( false, $validation['verified'], 'Detects a stale public response' );
$assert_same( false, get_option( 'serfe_ai_discovery_robots_txt_meta' )['verified'], 'Stores failed public verification' );
$GLOBALS['test_public_mismatch'] = false;
$assert_same( true, Discovery_Files::validate_current( 'robots.txt' )['verified'], 'Recovers after the public response matches' );
$GLOBALS['test_can_manage'] = false;
$assert_error( 'forbidden', Discovery_Files::validate_current( 'robots.txt' ), 'Requires administrator capability for validation' );
$GLOBALS['test_can_manage'] = true;
$assert_error( 'stale_file', Discovery_Files::publish( 'robots.txt', "User-agent: *\nDisallow: /new/\n" ), 'Rejects an editor without the current checksum' );
file_put_contents( $base . '/llms.txt', "# Existing\n" );
$llms = Discovery_Files::inspect( 'llms.txt' );
$assert_error( 'takeover_required', Discovery_Files::publish( 'llms.txt', "# New\n" ), 'Requires separate takeover for llms.txt' );
$result = Discovery_Files::publish( 'llms.txt', "# New\n", true, $llms['checksum'] );
$assert_same( true, $result['verified'], 'Publishes llms.txt at the public root' );
$assert_same( $llms['content'], get_option( 'serfe_ai_discovery_llms_txt_backup' ), 'Keeps a separate llms.txt backup' );
file_put_contents( $base . '/robots.txt', "External change\n" );
$assert_error( 'takeover_required', Discovery_Files::restore( 'robots.txt' ), 'Prevents restore over an external change' );
$changed = Discovery_Files::inspect( 'robots.txt' );
$result  = Discovery_Files::restore( 'robots.txt', true, $changed['checksum'] );
$assert_same( true, $result['verified'], 'Restores after explicit takeover' );
$assert_same( $current['content'], file_get_contents( $base . '/robots.txt' ), 'Restores the previous content' );
$assert_error( 'invalid_name', Discovery_Files::restore( '../secret' ), 'Restricts restore to fixed public files' );

unlink( $base . '/llms.txt' );
symlink( $base . '/robots.txt', $base . '/llms.txt' );
$assert_error( 'unsafe_file', Discovery_Files::inspect( 'llms.txt' ), 'Rejects symlinked public files' );
unlink( $base . '/llms.txt' );

unlink( $base . '/robots.txt' );
unlink( $base . '/linked-wp' );
rmdir( $base . '/wp' );
rmdir( $base );

require_once dirname( __DIR__ ) . '/includes/class-settings.php';
require_once dirname( __DIR__ ) . '/includes/class-discovery-files-admin.php';

use Serfe\AI_Discovery\Discovery_Files_Admin;
use Serfe\AI_Discovery\Settings;

if ( ! function_exists( 'wp_get_environment_type' ) ) {
	function wp_get_environment_type() {
		return 'production';
	}
}
if ( ! function_exists( 'esc_html__' ) ) {
	function esc_html__( $text ) {
		return $text;
	}
}
if ( ! function_exists( 'esc_url' ) ) {
	function esc_url( $url ) {
		return $url;
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
if ( ! function_exists( 'esc_attr__' ) ) {
	function esc_attr__( $text ) {
		return $text;
	}
}
if ( ! function_exists( 'esc_textarea' ) ) {
	function esc_textarea( $text ) {
		return is_string( $text ) ? htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' ) : '';
	}
}
if ( ! function_exists( 'sanitize_text_field' ) ) {
	function sanitize_text_field( $value ) {
		return is_string( $value ) ? trim( $value ) : '';
	}
}
if ( ! function_exists( 'sanitize_key' ) ) {
	function sanitize_key( $value ) {
		return is_string( $value ) ? strtolower( preg_replace( '/[^a-z0-9_\-]/i', '', $value ) ) : '';
	}
}
if ( ! function_exists( 'wp_unslash' ) ) {
	function wp_unslash( $value ) {
		return $value;
	}
}
if ( ! function_exists( 'wp_nonce_field' ) ) {
	function wp_nonce_field() {}
}
if ( ! function_exists( 'checked' ) ) {
	function checked( $checked, $current = true, $echo = true ) {
		$result = ( (string) $checked === (string) $current ) ? ' checked="checked"' : '';
		if ( $echo ) {
			echo $result;
		}
		return $result;
	}
}
if ( ! function_exists( 'wp_kses_post' ) ) {
	function wp_kses_post( $text ) {
		return $text;
	}
}
if ( ! function_exists( 'admin_url' ) ) {
	function admin_url( $path = '' ) {
		return 'https://example.com/wp-admin/' . $path;
	}
}
if ( ! function_exists( 'add_query_arg' ) ) {
	function add_query_arg( $args, $url = '' ) {
		return $url . '?' . http_build_query( $args );
	}
}
if ( ! function_exists( 'check_admin_referer' ) ) {
	function check_admin_referer() {
		return true;
	}
}
if ( ! function_exists( 'set_transient' ) ) {
	function set_transient( $key, $value ) {
		$GLOBALS['test_transients'][ $key ] = $value;
		return true;
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
if ( ! function_exists( 'wp_safe_redirect' ) ) {
	function wp_safe_redirect( $url ) {
		$GLOBALS['test_redirect_url'] = $url;
		throw new \Exception( 'redirect' );
	}
}

$admin = new Discovery_Files_Admin();

ob_start();
$admin->render_robots_tab();
$robots_output = ob_get_clean();
$assert_same( true, strpos( $robots_output, 'Why robots.txt matters' ) !== false, 'Renders dedicated Robots.txt tab heading' );
$assert_same( true, strpos( $robots_output, 'robots.txt' ) !== false, 'Includes robots.txt editor' );
$assert_same( false, strpos( $robots_output, 'Why llms.txt matters' ), 'Robots tab does not render llms.txt' );

ob_start();
$admin->render_llms_tab();
$llms_output = ob_get_clean();
$assert_same( true, strpos( $llms_output, 'Why llms.txt matters' ) !== false, 'Renders dedicated llms.txt tab heading' );
$assert_same( true, strpos( $llms_output, 'llms.txt' ) !== false, 'Includes llms.txt editor' );
$assert_same( false, strpos( $llms_output, 'Why robots.txt matters' ), 'llms tab does not render robots.txt' );

$_POST = array(
	'file' => 'robots.txt',
	'mode' => 'validate',
);
$GLOBALS['test_redirect_url'] = '';
try {
	$admin->handle_action();
} catch ( \Exception $e ) {}
$assert_same( true, strpos( $GLOBALS['test_redirect_url'], 'tab=robots' ) !== false, 'Action for robots.txt redirects to tab=robots' );

$_POST = array(
	'file' => 'llms.txt',
	'mode' => 'validate',
);
$GLOBALS['test_redirect_url'] = '';
try {
	$admin->handle_action();
} catch ( \Exception $e ) {}
$assert_same( true, strpos( $GLOBALS['test_redirect_url'], 'tab=llms' ) !== false, 'Action for llms.txt redirects to tab=llms' );
if ( $failures ) {
	fwrite( STDERR, implode( "\n", $failures ) . "\n" );
	exit( 1 );
}
echo "All {$tests} discovery file checks passed.\n";
