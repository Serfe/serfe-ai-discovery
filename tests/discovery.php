<?php

define( 'ABSPATH', dirname( __DIR__ ) . '/' );

require_once dirname( __DIR__ ) . '/includes/class-product-generator.php';
require_once dirname( __DIR__ ) . '/includes/class-discovery.php';

use Serfe\AI_Discovery\Discovery;
use Serfe\AI_Discovery\Product_Generator;

final class WP_Post {
	public $ID;
	public $post_type;
	public $post_status;

	public function __construct( $id, $post_type, $post_status = 'publish' ) {
		$this->ID          = $id;
		$this->post_type   = $post_type;
		$this->post_status = $post_status;
	}
}

final class Test_Product {
	private $visibility;
	private $price;

	public function __construct( $visibility, $price ) {
		$this->visibility = $visibility;
		$this->price      = $price;
	}

	public function get_catalog_visibility() {
		return $this->visibility;
	}

	public function get_price() {
		return $this->price;
	}
}

function is_singular() {
	return $GLOBALS['hs_test_context']['singular'];
}

function is_feed() {
	return $GLOBALS['hs_test_context']['feed'];
}

function is_embed() {
	return $GLOBALS['hs_test_context']['embed'];
}

function get_queried_object() {
	return $GLOBALS['hs_test_context']['post'];
}

function wc_get_product( $product_id ) {
	return isset( $GLOBALS['hs_test_products'][ $product_id ] ) ? $GLOBALS['hs_test_products'][ $product_id ] : null;
}

function apply_filters( $hook, $value ) {
	return $value;
}

$GLOBALS['hs_test_context'] = array(
	'singular' => true,
	'feed'     => false,
	'embed'    => false,
	'post'     => null,
);
$GLOBALS['hs_test_products'] = array(
	2 => new Test_Product( 'visible', '10' ),
	3 => new Test_Product( 'hidden', '10' ),
	4 => new Test_Product( 'catalog', '' ),
);

$failures = array();
$tests    = 0;
$assert_same = function ( $expected, $actual, $name ) use ( &$failures, &$tests ) {
	$tests++;
	if ( $expected !== $actual ) {
		$failures[] = $name . ': expected ' . var_export( $expected, true ) . ', got ' . var_export( $actual, true );
	}
};

$discovery = new Discovery( new Product_Generator() );
$GLOBALS['hs_test_context']['post'] = new WP_Post( 1, 'post' );
$assert_same( true, $discovery->current_content_has_markdown(), 'Exposes published blog post' );
$GLOBALS['hs_test_context']['post'] = new WP_Post( 2, 'product' );
$assert_same( true, $discovery->current_content_has_markdown(), 'Exposes eligible product' );
$GLOBALS['hs_test_context']['post'] = new WP_Post( 3, 'product' );
$assert_same( false, $discovery->current_content_has_markdown(), 'Rejects hidden product' );
$GLOBALS['hs_test_context']['post'] = new WP_Post( 4, 'product' );
$assert_same( false, $discovery->current_content_has_markdown(), 'Rejects product without price' );
$GLOBALS['hs_test_context']['post'] = new WP_Post( 5, 'page' );
$assert_same( true, $discovery->current_content_has_markdown(), 'Exposes published page' );
$GLOBALS['hs_test_context']['post'] = new WP_Post( 5, 'attachment' );
$assert_same( false, $discovery->current_content_has_markdown(), 'Rejects unsupported post type' );
$GLOBALS['hs_test_context']['post'] = new WP_Post( 6, 'post', 'draft' );
$assert_same( false, $discovery->current_content_has_markdown(), 'Rejects unpublished post' );
$GLOBALS['hs_test_context']['post'] = new WP_Post( 1, 'post' );
$GLOBALS['hs_test_context']['feed'] = true;
$assert_same( false, $discovery->current_content_has_markdown(), 'Rejects feed' );
$GLOBALS['hs_test_context']['feed']  = false;
$GLOBALS['hs_test_context']['embed'] = true;
$assert_same( false, $discovery->current_content_has_markdown(), 'Rejects embed' );
$GLOBALS['hs_test_context']['embed']    = false;
$GLOBALS['hs_test_context']['singular'] = false;
$assert_same( false, $discovery->current_content_has_markdown(), 'Rejects archive' );
$assert_same( true, Discovery::is_api_catalog_request( '/.well-known/api-catalog' ), 'Detects the API catalog URI' );
$assert_same( true, Discovery::is_api_catalog_request( '/.well-known/api-catalog/?source=agent' ), 'Allows API catalog query strings' );
$assert_same( false, Discovery::is_api_catalog_request( '/.well-known/other' ), 'Rejects other well-known URIs' );
$assert_same( 'https://serfe.test/.md', Discovery::markdown_url( 'https://serfe.test/' ), 'Builds the homepage Markdown URL without changing the host' );
$assert_same(
	array(
		'linkset' => array(
			array(
				'anchor' => 'https://serfe.test/.well-known/api-catalog',
				'item'   => array(
					array(
						'href'  => 'https://serfe.test/wp-json/',
						'type'  => 'application/json',
						'title' => 'WordPress REST API',
					),
				),
			),
		),
	),
	Discovery::api_catalog_document( 'https://serfe.test/.well-known/api-catalog', 'https://serfe.test/wp-json/' ),
	'Builds an RFC 9727 Linkset API catalog'
);

if ( $failures ) {
	fwrite( STDERR, implode( "\n", $failures ) . "\n" );
	exit( 1 );
}

echo "All {$tests} discovery tests passed.\n";
