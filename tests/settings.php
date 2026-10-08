<?php

define( 'ABSPATH', dirname( __DIR__ ) . '/' );
require_once dirname( __DIR__ ) . '/includes/class-settings.php';

use Serfe\AI_Discovery\Settings;

class WP_Post {
	public $post_type;

	public function __construct( $post_type ) {
		$this->post_type = $post_type;
	}
}

$GLOBALS['test_options'] = array();
$GLOBALS['test_item_meta'] = array();
$GLOBALS['test_can_edit'] = true;
$GLOBALS['test_is_revision'] = false;

function get_option( $key, $default = false ) {
	return isset( $GLOBALS['test_options'][ $key ] ) ? $GLOBALS['test_options'][ $key ] : $default;
}

function post_type_exists( $type ) {
	return in_array( $type, array( 'post', 'page', 'product', 'book', 'private_record' ), true );
}

function get_post_types( $args, $output ) {
	return array( 'post', 'page', 'book' );
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

function current_user_can( $capability, $post_id ) {
	return 'edit_post' === $capability && $GLOBALS['test_can_edit'];
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
$assert_same( false, Settings::api_catalog_enabled(), 'Default disables API Catalog' );
$assert_same( false, Settings::item_enabled( 10 ), 'Default disables each item' );

$clean = $settings->sanitize( array(
	'markdown_enabled' => '1',
	'enabled_post_types' => array( 'post', 'post', 'book', 'private_record', array( 'page' ) ),
	'api_catalog_enabled' => '1',
) );
$assert_same( array( 'post', 'book' ), $clean['enabled_post_types'], 'Allows public CPT and rejects unsupported types' );
$GLOBALS['test_options'][ Settings::OPTION ] = $clean;
$assert_same( true, Settings::markdown_enabled_for( 'post' ), 'Enables selected post type' );
$assert_same( true, Settings::markdown_enabled_for( 'book' ), 'Enables selected public CPT' );
$assert_same( false, Settings::markdown_enabled_for( 'page' ), 'Keeps unselected post type disabled' );
$assert_same( true, Settings::api_catalog_enabled(), 'Enables API Catalog only when selected' );

$_POST = array( Settings::NONCE_NAME => 'valid', 'serfe_ai_discovery_item_enabled' => '1' );
$settings->save_item_choice( 10, new WP_Post( 'post' ) );
$assert_same( true, Settings::item_enabled( 10 ), 'Saves authorized item opt-in' );
$settings->save_item_choice( 11, new WP_Post( 'book' ) );
$assert_same( true, Settings::item_enabled( 11 ), 'Saves public CPT opt-in' );

$_POST[ Settings::NONCE_NAME ] = 'invalid';
unset( $_POST['serfe_ai_discovery_item_enabled'] );
$settings->save_item_choice( 10, new WP_Post( 'post' ) );
$assert_same( true, Settings::item_enabled( 10 ), 'Rejects invalid nonce' );

$_POST[ Settings::NONCE_NAME ] = 'valid';
$GLOBALS['test_can_edit'] = false;
$settings->save_item_choice( 10, new WP_Post( 'post' ) );
$assert_same( true, Settings::item_enabled( 10 ), 'Rejects user without edit permission' );
$GLOBALS['test_can_edit'] = true;

$GLOBALS['test_is_revision'] = true;
$settings->save_item_choice( 10, new WP_Post( 'post' ) );
$assert_same( true, Settings::item_enabled( 10 ), 'Rejects revisions' );
$GLOBALS['test_is_revision'] = false;

$settings->save_item_choice( 10, new WP_Post( 'attachment' ) );
$assert_same( true, Settings::item_enabled( 10 ), 'Rejects unsupported post type' );

$settings->save_item_choice( 10, new WP_Post( 'post' ) );
$assert_same( false, Settings::item_enabled( 10 ), 'Unchecking disables Markdown for item' );

if ( $failures ) {
	fwrite( STDERR, implode( "\n", $failures ) . "\n" );
	exit( 1 );
}

echo "All {$tests} settings tests passed.\n";
