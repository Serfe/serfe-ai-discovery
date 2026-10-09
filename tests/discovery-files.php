<?php

$base = sys_get_temp_dir() . '/serfe-discovery-files-' . bin2hex( random_bytes( 6 ) );
mkdir( $base );
mkdir( $base . '/wp' );
define( 'ABSPATH', $base . '/wp/' );

class WP_Error {
	private $code;

	public function __construct( $code, $message ) {
		$this->code = $code;
	}

	public function get_error_code() {
		return $this->code;
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
	$path = $GLOBALS['test_root'] . '/' . basename( $url );
	return array( 'code' => 200, 'body' => file_get_contents( $path ) );
}

function wp_remote_retrieve_response_code( $response ) {
	return $response['code'];
}

function wp_remote_retrieve_body( $response ) {
	return $response['body'];
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
if ( $failures ) {
	fwrite( STDERR, implode( "\n", $failures ) . "\n" );
	exit( 1 );
}
echo "All {$tests} discovery file checks passed.\n";
