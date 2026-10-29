<?php

$root     = dirname( __DIR__ );
$main     = file_get_contents( $root . '/serfe-ai-discovery.php' );
$admin    = file_get_contents( $root . '/includes/class-discovery-files-admin.php' );
$files_js = file_get_contents( $root . '/assets/admin-files.js' );
$readme   = file_get_contents( $root . '/readme.txt' );
$failures = array();
$tests    = 0;

$assert_contains = function ( $needle, $haystack, $name ) use ( &$failures, &$tests ) {
	$tests++;
	if ( false === strpos( $haystack, $needle ) ) {
		$failures[] = $name;
	}
};

$assert_contains( 'Text Domain: serfe-ai-discovery', $main, 'Declares the WordPress.org plugin text domain' );
$assert_contains( 'Domain Path: /languages', $main, 'Declares the plugin translation directory' );
$assert_contains( 'locale/es/default/wp-plugins/serfe-ai-discovery/', $readme, 'Links to the Spanish translation project' );
$assert_contains( 'locale/es-ar/default/wp-plugins/serfe-ai-discovery/', $readme, 'Links to the Spanish (Argentina) translation project' );
$assert_contains( 'locale/pt-br/default/wp-plugins/serfe-ai-discovery/', $readme, 'Links to the Portuguese (Brazil) translation project' );

foreach ( array( 'copyLabel', 'copiedLabel', 'copiedStatus', 'protectLabel', 'protectedLabel', 'invalidPath', 'protectedStatus', 'copyFailed', 'templateLabel', 'templateError', 'releaseConfirm' ) as $key ) {
	$assert_contains( "'" . $key . "'", $admin, 'Localizes the ' . $key . ' JavaScript message' );
	$assert_contains( 'config.' . $key, $files_js, 'Uses the localized ' . $key . ' JavaScript message' );
}

foreach ( array( 'Copy to clipboard', 'Protect section from AI-Train', 'Enter a section path such as /blog/.', 'Copy failed. Select the content and copy it manually.', 'Release Serfe control?' ) as $message ) {
	$tests++;
	if ( false !== strpos( $files_js, $message ) ) {
		$failures[] = 'Does not leave the user-facing JavaScript message untranslated: ' . $message;
	}
}

if ( $failures ) {
	fwrite( STDERR, implode( "\n", $failures ) . "\n" );
	exit( 1 );
}

echo "All {$tests} internationalization checks passed.\n";
