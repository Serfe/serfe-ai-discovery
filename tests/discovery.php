<?php

define( 'ABSPATH', dirname( __DIR__ ) . '/' );

require_once dirname( __DIR__ ) . '/includes/class-product-generator.php';
require_once dirname( __DIR__ ) . '/includes/class-settings.php';
require_once dirname( __DIR__ ) . '/includes/class-exclusions.php';
require_once dirname( __DIR__ ) . '/includes/class-editorial-content.php';
require_once dirname( __DIR__ ) . '/includes/class-discovery.php';

use Serfe\AI_Discovery\Discovery;
use Serfe\AI_Discovery\Product_Generator;
use Serfe\AI_Discovery\Settings;

final class WP_Post {
	public $ID;
	public $post_type;
	public $post_status;
	public $post_password = '';
	public $post_content = '';

	public function __construct( $id, $post_type, $post_status = 'publish' ) {
		$this->ID          = $id;
		$this->post_type   = $post_type;
		$this->post_status = $post_status;
	}
}

final class WP_Block_Type_Registry {
	public static function get_instance() {
		return new self();
	}

	public function get_registered( $name ) {
		return null;
	}
}

function parse_blocks( $content ) {
	return array();
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
	return $GLOBALS['discovery_test_context']['singular'];
}

function is_feed() {
	return $GLOBALS['discovery_test_context']['feed'];
}

function is_embed() {
	return $GLOBALS['discovery_test_context']['embed'];
}

function is_user_logged_in() {
	return $GLOBALS['discovery_test_context']['logged_in'];
}

function is_post_publicly_viewable( $post ) {
	return $GLOBALS['discovery_test_context']['publicly_viewable'];
}

function get_option( $option, $default = false ) {
	return isset( $GLOBALS['discovery_test_options'][ $option ] ) ? $GLOBALS['discovery_test_options'][ $option ] : $default;
}

function post_type_exists( $type ) {
	return in_array( $type, array( 'post', 'page', 'product', 'book' ), true );
}

function get_post_types( $args, $output ) {
	return array( 'post', 'page', 'book' );
}

function get_post_type_object( $type ) {
	return (object) array( 'public' => true, 'publicly_queryable' => true, 'has_archive' => in_array( $type, array( 'book', 'product' ), true ) );
}

function taxonomy_exists( $taxonomy ) {
	return in_array( $taxonomy, array( 'product_cat', 'product_tag' ), true );
}

function get_taxonomy( $taxonomy ) {
	return taxonomy_exists( $taxonomy ) ? (object) array( 'public' => true, 'publicly_queryable' => true ) : null;
}

function wp_parse_args( $args, $defaults ) {
	return array_merge( $defaults, $args );
}

function get_permalink( $post ) {
	return 'https://example.com/item-' . $post->ID . '/';
}

function home_url( $path = '' ) {
	return ( isset( $GLOBALS['discovery_test_home_url'] ) ? $GLOBALS['discovery_test_home_url'] : 'https://example.com' ) . $path;
}

function wp_parse_url( $url, $component = -1 ) {
	return parse_url( $url, $component );
}

function has_term( $term_id, $taxonomy, $post ) {
	return isset( $GLOBALS['discovery_test_terms'][ $post->ID ] ) && in_array( $taxonomy . ':' . $term_id, $GLOBALS['discovery_test_terms'][ $post->ID ], true );
}

function get_post_meta( $post_id, $key, $single ) {
	return isset( $GLOBALS['discovery_test_enabled_items'][ $post_id ] ) ? $GLOBALS['discovery_test_enabled_items'][ $post_id ] : '';
}

function get_queried_object() {
	return $GLOBALS['discovery_test_context']['post'];
}

function wc_get_product( $product_id ) {
	return isset( $GLOBALS['discovery_test_products'][ $product_id ] ) ? $GLOBALS['discovery_test_products'][ $product_id ] : null;
}

function wc_get_page_id( $page ) {
	return isset( $GLOBALS['discovery_test_wc_pages'][ $page ] ) ? $GLOBALS['discovery_test_wc_pages'][ $page ] : -1;
}

function is_cart() {
	return ! empty( $GLOBALS['discovery_test_wc_conditions']['cart'] );
}

function is_checkout() {
	return ! empty( $GLOBALS['discovery_test_wc_conditions']['checkout'] );
}

function is_account_page() {
	return ! empty( $GLOBALS['discovery_test_wc_conditions']['account'] );
}

function is_wc_endpoint_url() {
	return ! empty( $GLOBALS['discovery_test_wc_conditions']['endpoint'] );
}

function apply_filters( $hook, $value ) {
	if ( 'serfe_ai_discovery_product_exposable' === $hook && ! empty( $GLOBALS['discovery_test_force_product_exposable'] ) ) {
		return true;
	}
	return $value;
}

$GLOBALS['discovery_test_context'] = array(
	'singular' => true,
	'feed'     => false,
	'embed'    => false,
	'logged_in' => false,
	'publicly_viewable' => true,
	'post'     => null,
);
$GLOBALS['discovery_test_options'] = array();
$GLOBALS['discovery_test_enabled_items'] = array();
$GLOBALS['discovery_test_terms'] = array();
$GLOBALS['discovery_test_wc_pages'] = array();
$GLOBALS['discovery_test_wc_conditions'] = array();
$GLOBALS['discovery_test_products'] = array(
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
$GLOBALS['discovery_test_context']['post'] = new WP_Post( 1, 'post' );
$assert_same( false, $discovery->current_content_has_markdown(), 'Keeps Markdown disabled by default' );
$GLOBALS['discovery_test_options'][ Settings::OPTION ] = array( 'markdown_enabled' => 1, 'enabled_post_types' => array( 'post', 'page', 'product' ) );
$assert_same( false, $discovery->current_content_has_markdown(), 'Requires item-level opt-in' );
$GLOBALS['discovery_test_enabled_items'][1] = '1';
$assert_same( true, $discovery->current_content_has_markdown(), 'Exposes published blog post' );
$GLOBALS['discovery_test_options'][ Settings::OPTION ] = array(
	'schema_version' => 2,
	'markdown_enabled' => 1,
	'detail_post_types' => array( 'post', 'page', 'product' ),
	'blocked_post_types' => array( 'post' ),
	'blocked_paths' => array(),
	'blocked_terms' => array(),
);
$assert_same( false, $discovery->current_content_has_markdown(), 'Excluded type overrides item opt-in' );
$GLOBALS['discovery_test_options'][ Settings::OPTION ]['blocked_post_types'] = array();
$GLOBALS['discovery_test_options'][ Settings::OPTION ]['blocked_paths'] = array( '/item-1' );
$assert_same( false, $discovery->current_content_has_markdown(), 'Excluded path overrides item opt-in' );
$GLOBALS['discovery_test_options'][ Settings::OPTION ]['blocked_paths'] = array();
$GLOBALS['discovery_test_options'][ Settings::OPTION ]['blocked_terms'] = array( 'category:123' );
$GLOBALS['discovery_test_terms'][1] = array( 'category:123' );
$assert_same( false, $discovery->current_content_has_markdown(), 'Excluded category overrides item opt-in' );
$GLOBALS['discovery_test_options'][ Settings::OPTION ]['blocked_terms'] = array();
$GLOBALS['discovery_test_context']['post']->post_content = '[account_data]';
$assert_same( false, $discovery->current_content_has_markdown(), 'Does not expose opted-in shortcode content' );
$GLOBALS['discovery_test_context']['post']->post_content = '';
$GLOBALS['discovery_test_context']['logged_in'] = true;
$assert_same( false, $discovery->current_content_has_markdown(), 'Never exposes Markdown to a logged-in visitor' );
$GLOBALS['discovery_test_context']['logged_in'] = false;
$GLOBALS['discovery_test_context']['publicly_viewable'] = false;
$assert_same( false, $discovery->current_content_has_markdown(), 'Rejects content that WordPress does not regard as public' );
$GLOBALS['discovery_test_context']['publicly_viewable'] = true;
$GLOBALS['discovery_test_context']['post']->post_password = 'protected';
$assert_same( false, $discovery->current_content_has_markdown(), 'Rejects password-protected content' );
$GLOBALS['discovery_test_context']['post']->post_password = '';
$GLOBALS['discovery_test_enabled_items'][2] = '1';
$GLOBALS['discovery_test_context']['post'] = new WP_Post( 2, 'product' );
$assert_same( true, $discovery->current_content_has_markdown(), 'Exposes eligible product' );
$GLOBALS['discovery_test_context']['post'] = new WP_Post( 3, 'product' );
$GLOBALS['discovery_test_enabled_items'][3] = '1';
$GLOBALS['discovery_test_force_product_exposable'] = true;
$assert_same( false, $discovery->current_content_has_markdown(), 'Rejects hidden product' );
$GLOBALS['discovery_test_force_product_exposable'] = false;
$GLOBALS['discovery_test_context']['post'] = new WP_Post( 4, 'product' );
$GLOBALS['discovery_test_enabled_items'][4] = '1';
$assert_same( false, $discovery->current_content_has_markdown(), 'Rejects product without price' );
$GLOBALS['discovery_test_context']['post'] = new WP_Post( 5, 'page' );
$GLOBALS['discovery_test_enabled_items'][5] = '1';
$assert_same( true, $discovery->current_content_has_markdown(), 'Exposes published page' );
foreach ( array( 'cart', 'checkout', 'myaccount', 'pay', 'view_order' ) as $wc_page ) {
	$GLOBALS['discovery_test_wc_pages'][ $wc_page ] = 5;
	$assert_same( false, $discovery->current_content_has_markdown(), 'Rejects opted-in WooCommerce ' . $wc_page . ' page' );
	unset( $GLOBALS['discovery_test_wc_pages'][ $wc_page ] );
}
foreach ( array( 'cart', 'checkout', 'account', 'endpoint' ) as $wc_condition ) {
	$GLOBALS['discovery_test_wc_conditions'][ $wc_condition ] = true;
	$assert_same( false, $discovery->current_content_has_markdown(), 'Rejects opted-in WooCommerce ' . $wc_condition . ' context' );
	unset( $GLOBALS['discovery_test_wc_conditions'][ $wc_condition ] );
}
$GLOBALS['discovery_test_options'][ Settings::OPTION ]['detail_post_types'] = array( 'post', 'product' );
$assert_same( false, $discovery->current_content_has_markdown(), 'Requires post-type opt-in' );
$GLOBALS['discovery_test_options'][ Settings::OPTION ]['detail_post_types'] = array( 'post', 'page', 'product' );
$GLOBALS['discovery_test_context']['post'] = new WP_Post( 5, 'attachment' );
$assert_same( false, $discovery->current_content_has_markdown(), 'Rejects unsupported post type' );
$GLOBALS['discovery_test_context']['post'] = new WP_Post( 6, 'book' );
$GLOBALS['discovery_test_enabled_items'][6] = '1';
$assert_same( false, $discovery->current_content_has_markdown(), 'Public CPT still requires type opt-in' );
$GLOBALS['discovery_test_options'][ Settings::OPTION ]['detail_post_types'][] = 'book';
$assert_same( true, $discovery->current_content_has_markdown(), 'Exposes enabled public CPT item' );
$GLOBALS['discovery_test_context']['publicly_viewable'] = false;
$assert_same( false, $discovery->current_content_has_markdown(), 'Rejects restricted CPT item' );
$GLOBALS['discovery_test_context']['publicly_viewable'] = true;
$GLOBALS['discovery_test_context']['post'] = new WP_Post( 6, 'post', 'draft' );
$GLOBALS['discovery_test_enabled_items'][6] = '1';
$assert_same( false, $discovery->current_content_has_markdown(), 'Rejects unpublished post' );
$GLOBALS['discovery_test_context']['post'] = new WP_Post( 1, 'post' );
$GLOBALS['discovery_test_context']['feed'] = true;
$assert_same( false, $discovery->current_content_has_markdown(), 'Rejects feed' );
$GLOBALS['discovery_test_context']['feed']  = false;
$GLOBALS['discovery_test_context']['embed'] = true;
$assert_same( false, $discovery->current_content_has_markdown(), 'Rejects embed' );
$GLOBALS['discovery_test_context']['embed']    = false;
$GLOBALS['discovery_test_context']['singular'] = false;
$assert_same( false, $discovery->current_content_has_markdown(), 'Rejects archive' );
$assert_same( true, Discovery::is_api_catalog_request( '/.well-known/api-catalog' ), 'Detects the API catalog URI' );
$assert_same( true, Discovery::is_api_catalog_request( '/.well-known/api-catalog/?source=agent' ), 'Allows API catalog query strings' );
$assert_same( false, Discovery::is_api_catalog_request( '/.well-known/other' ), 'Rejects other well-known URIs' );
$GLOBALS['discovery_test_home_url'] = 'https://example.com/blog';
$assert_same( true, Discovery::is_api_catalog_request( '/blog/.well-known/api-catalog' ), 'Detects API catalog under a site prefix' );
$assert_same( false, Discovery::is_api_catalog_request( '/.well-known/api-catalog' ), 'Rejects catalog outside configured site prefix' );
unset( $GLOBALS['discovery_test_home_url'] );
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
