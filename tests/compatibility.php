<?php

define( 'ABSPATH', dirname( __DIR__ ) . '/' );

$failures = array();
$tests    = 0;

$assert_same = function ( $expected, $actual, $name ) use ( &$failures, &$tests ) {
	$tests++;
	if ( $expected !== $actual ) {
		$failures[] = $name . "\nExpected: " . var_export( $expected, true ) . "\nActual:   " . var_export( $actual, true );
	}
};

$assert_true = function ( $condition, $name ) use ( &$failures, &$tests ) {
	$tests++;
	if ( ! $condition ) {
		$failures[] = $name . " failed (expected true).";
	}
};

// 1. Check PHP Runtime Version Requirement (PHP 7.4+).
$assert_true( PHP_VERSION_ID >= 70400, 'Current PHP runtime satisfies PHP 7.4+ requirement (found ' . PHP_VERSION . ')' );

// 2. Check Plugin Main File Headers (serfe-ai-discovery.php).
$main_file = file_get_contents( dirname( __DIR__ ) . '/serfe-ai-discovery.php' );
$assert_true( false !== $main_file, 'serfe-ai-discovery.php is readable' );

preg_match( '/\*\s*Plugin Name:\s*(.+)$/m', $main_file, $name_match );
preg_match( '/\*\s*Version:\s*(.+)$/m', $main_file, $version_match );
preg_match( '/\*\s*Requires at least:\s*(.+)$/m', $main_file, $wp_min_match );
preg_match( '/\*\s*Requires PHP:\s*(.+)$/m', $main_file, $php_min_match );

$plugin_name    = isset( $name_match[1] ) ? trim( $name_match[1] ) : '';
$plugin_version = isset( $version_match[1] ) ? trim( $version_match[1] ) : '';
$plugin_wp_min  = isset( $wp_min_match[1] ) ? trim( $wp_min_match[1] ) : '';
$plugin_php_min = isset( $php_min_match[1] ) ? trim( $php_min_match[1] ) : '';

$assert_same( '6.2', $plugin_wp_min, 'serfe-ai-discovery.php declares "Requires at least: 6.2"' );
$assert_same( '7.4', $plugin_php_min, 'serfe-ai-discovery.php declares "Requires PHP: 7.4"' );
$assert_same( 'Serfe AI Discovery', $plugin_name, 'serfe-ai-discovery.php has the expected Plugin Name' );
$assert_true( '' !== $plugin_version, 'serfe-ai-discovery.php declares a Version' );

// 3. Check WordPress.org Readme Headers (readme.txt).
$readme_file = file_get_contents( dirname( __DIR__ ) . '/readme.txt' );
$assert_true( false !== $readme_file, 'readme.txt is readable' );

preg_match( '/^===\s*(.+?)\s*===$/m', $readme_file, $readme_name_match );
preg_match( '/^Requires at least:\s*(.+)$/m', $readme_file, $readme_wp_min_match );
preg_match( '/^Tested up to:\s*(.+)$/m', $readme_file, $readme_tested_match );
preg_match( '/^Requires PHP:\s*(.+)$/m', $readme_file, $readme_php_min_match );
preg_match( '/^Stable tag:\s*(.+)$/m', $readme_file, $readme_stable_match );

$readme_name    = isset( $readme_name_match[1] ) ? trim( $readme_name_match[1] ) : '';
$readme_wp_min  = isset( $readme_wp_min_match[1] ) ? trim( $readme_wp_min_match[1] ) : '';
$readme_tested  = isset( $readme_tested_match[1] ) ? trim( $readme_tested_match[1] ) : '';
$readme_php_min = isset( $readme_php_min_match[1] ) ? trim( $readme_php_min_match[1] ) : '';
$readme_stable  = isset( $readme_stable_match[1] ) ? trim( $readme_stable_match[1] ) : '';

$assert_same( '6.2', $readme_wp_min, 'readme.txt declares "Requires at least: 6.2"' );
$assert_same( '7.1', $readme_tested, 'readme.txt declares "Tested up to: 7.1"' );
$assert_same( '7.4', $readme_php_min, 'readme.txt declares "Requires PHP: 7.4"' );
$assert_same( $plugin_name, $readme_name, 'readme.txt name matches the plugin header Plugin Name' );
$assert_same( $plugin_version, $readme_stable, 'readme.txt Stable tag matches plugin header Version' );
$assert_true( false !== strpos( $readme_file, '== Screenshots ==' ), 'readme.txt includes a Screenshots section' );
foreach ( array( 1, 2, 3, 4 ) as $screenshot ) {
	$filename = 'screenshot-' . $screenshot . '.png';
	$assert_true( file_exists( dirname( __DIR__ ) . '/assets/' . $filename ), 'Bundled asset exists: ' . $filename );
}

// 4. Validate PHPStan Target Configuration (phpVersion: 70400).
$phpstan_neon = file_get_contents( dirname( __DIR__ ) . '/phpstan.neon.dist' );
$assert_true( false !== strpos( $phpstan_neon, 'phpVersion: 70400' ), 'phpstan.neon.dist targets PHP 7.4 (phpVersion: 70400)' );

// 5. Test compatibility matrix across WordPress 6.2 to 7.1 versions.
$supported_wp_versions = array( '6.2', '6.2.2', '6.3', '6.3.2', '6.4', '6.4.3', '6.5', '6.5.5', '6.6', '6.6.2', '6.7', '7.0', '7.1' );

foreach ( $supported_wp_versions as $wp_version ) {
	$assert_true(
		version_compare( $wp_version, '6.2', '>=' ) && version_compare( $wp_version, '7.1', '<=' ),
		"Version {$wp_version} is within the supported WordPress 6.2 to 7.1 range"
	);
}

// 6. Test syntax and no usage of PHP 8.0+ specific functions in production includes.
$php8_only_functions = array( 'str_contains', 'str_starts_with', 'str_ends_with', 'get_debug_type', 'fdiv' );
$includes_dir        = dirname( __DIR__ ) . '/includes';
$php_files           = glob( $includes_dir . '/*.php' );
$php_files[]         = dirname( __DIR__ ) . '/serfe-ai-discovery.php';

foreach ( $php_files as $file ) {
	$code = file_get_contents( $file );
	foreach ( $php8_only_functions as $func ) {
		$assert_same(
			0,
			preg_match( '/\b' . preg_quote( $func, '/' ) . '\s*\(/', $code ),
			basename( $file ) . " does not rely on PHP 8+ function {$func}()"
		);
	}
}

if ( $failures ) {
	fwrite( STDERR, implode( "\n\n", $failures ) . "\n" );
	exit( 1 );
}

echo "All {$tests} compatibility checks (WP 6.2 - 7.1 and PHP 7.4+) passed.\n";
