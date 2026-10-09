<?php

define( 'ABSPATH', dirname( __DIR__ ) . '/' );
require_once dirname( __DIR__ ) . '/includes/class-markdown.php';
require_once dirname( __DIR__ ) . '/includes/class-html-to-markdown.php';
require_once dirname( __DIR__ ) . '/includes/class-settings.php';
require_once dirname( __DIR__ ) . '/includes/class-exclusions.php';
require_once dirname( __DIR__ ) . '/includes/class-product-generator.php';
require_once dirname( __DIR__ ) . '/includes/class-catalog-generator.php';

use Serfe\AI_Discovery\Catalog_Generator;
use Serfe\AI_Discovery\Product_Generator;
use Serfe\AI_Discovery\Settings;

final class WP_Post {
	public $ID;
	public $post_type = 'product';
	public $post_status = 'publish';
	public $post_password = '';
	public $post_title;
	public $publicly_viewable = true;

	public function __construct( $id, $title ) {
		$this->ID = $id;
		$this->post_title = $title;
	}
}

final class WP_Term {
	public $name = 'Widgets';
	public $description = '<p>Public <strong>widgets</strong>.</p>';
}

class WC_Product {
	private $price;
	private $visibility;
	private $stock;

	public function __construct( $price, $visibility = 'visible', $stock = true ) {
		$this->price = $price;
		$this->visibility = $visibility;
		$this->stock = $stock;
	}
	public function get_price() { return $this->price; }
	public function get_catalog_visibility() { return $this->visibility; }
	public function is_in_stock() { return $this->stock; }
}

function get_option( $key, $default = false ) { return isset( $GLOBALS['catalog_test_options'][ $key ] ) ? $GLOBALS['catalog_test_options'][ $key ] : $default; }
function wp_parse_args( $args, $defaults ) { return array_merge( $defaults, $args ); }
function get_post_meta( $id, $key, $single ) { return isset( $GLOBALS['catalog_test_items'][ $id ] ) ? $GLOBALS['catalog_test_items'][ $id ] : ''; }
function get_permalink( $post ) { return 'https://example.com/product-' . $post->ID . '/'; }
function home_url( $path = '' ) { return 'https://example.com' . $path; }
function wp_parse_url( $url, $component = -1 ) { return parse_url( $url, $component ); }
function has_term( $term_id, $taxonomy, $post ) { return 6 === $post->ID && 123 === $term_id && 'product_cat' === $taxonomy; }
function is_post_publicly_viewable( $post ) { return $post->publicly_viewable; }
function wp_strip_all_tags( $text ) { return strip_tags( $text ); }
function esc_url_raw( $url ) { return $url; }
function get_woocommerce_currency() { return 'USD'; }
function wc_get_product( $id ) { return isset( $GLOBALS['catalog_test_products'][ $id ] ) ? $GLOBALS['catalog_test_products'][ $id ] : false; }
function get_pagenum_link( $page, $escape = true ) { return 'https://example.com/product-category/widgets/page/' . $page . '/'; }
function __( $text, $domain ) { return $text; }
function apply_filters( $hook, $value, $id = 0 ) { return $value; }

$GLOBALS['catalog_test_options'][ Settings::OPTION ] = array(
	'schema_version' => 2,
	'markdown_enabled' => 1,
	'blocked_post_types' => array(),
	'blocked_paths' => array( '/product-5' ),
	'blocked_terms' => array( 'product_cat:123' ),
);
$GLOBALS['catalog_test_items'] = array( 1 => '1', 2 => '1', 3 => '1', 5 => '1', 6 => '1', 7 => '1' );
$GLOBALS['catalog_test_products'] = array(
	1 => new WC_Product( '10' ),
	2 => new WC_Product( '3', 'hidden' ),
	3 => new WC_Product( '' ),
	4 => new WC_Product( '4' ),
	5 => new WC_Product( '5' ),
	6 => new WC_Product( '6' ),
	7 => new WC_Product( '15', 'catalog', false ),
);
$posts = array(
	new WP_Post( 1, 'Public [widget]' ),
	new WP_Post( 2, 'Hidden' ),
	new WP_Post( 3, 'No price' ),
	new WP_Post( 4, 'Not approved' ),
	new WP_Post( 5, 'Blocked path' ),
	new WP_Post( 6, 'Blocked category' ),
	new WP_Post( 7, 'Out of stock' ),
);
$context = array( 'type' => 'product_cat', 'page' => 2, 'url' => 'https://example.com/product-category/widgets/page/2/', 'term' => new WP_Term() );
$output = ( new Catalog_Generator( new Product_Generator() ) )->generate( $context, 3, $posts );
$failures = array();
foreach ( array(
	'# Widgets',
	'Public **widgets**.',
	'currency: "USD"',
	'- [Public \\[widget\\]](<https://example.com/product-1/>) — USD 10.00 — In stock',
	'- [Out of stock](<https://example.com/product-7/>) — USD 15.00 — Out of stock',
	'[Previous page](<https://example.com/product-category/widgets/page/1/>)',
	'[Next page](<https://example.com/product-category/widgets/page/3/>)',
) as $expected ) {
	if ( false === strpos( $output, $expected ) ) {
		$failures[] = 'Missing: ' . $expected;
	}
}
foreach ( array( 'Hidden', 'No price', 'Not approved', 'Blocked path', 'Blocked category' ) as $forbidden ) {
	if ( false !== strpos( $output, $forbidden ) ) {
		$failures[] = 'Unexpected: ' . $forbidden;
	}
}
if ( $failures ) {
	fwrite( STDERR, implode( "\n", $failures ) . "\n" );
	exit( 1 );
}
echo "All 12 catalog generator checks passed.\n";
