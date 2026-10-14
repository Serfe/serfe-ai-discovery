<?php

define( 'ABSPATH', dirname( __DIR__ ) . '/' );
require_once dirname( __DIR__ ) . '/includes/class-markdown.php';
require_once dirname( __DIR__ ) . '/includes/class-html-to-markdown.php';
require_once dirname( __DIR__ ) . '/includes/class-product-generator.php';

use Serfe\AI_Discovery\Product_Generator;

final class WP_Post {
	public $ID;
	public function __construct( $id ) {
		$this->ID = $id;
	}
}

class WC_Product {
	private $price;
	private $visibility;
	private $regular_price;
	private $sale_price;
	public function __construct( $price, $visibility = 'visible', $regular_price = '', $sale_price = '' ) {
		$this->price         = $price;
		$this->visibility    = $visibility;
		$this->regular_price = '' !== $regular_price ? $regular_price : $price;
		$this->sale_price    = $sale_price;
	}
	public function get_price() { return $this->price; }
	public function get_regular_price() { return $this->regular_price; }
	public function get_sale_price() { return $this->sale_price; }
	public function is_on_sale() { return '' !== $this->sale_price && null !== $this->sale_price && (float) $this->sale_price < (float) $this->regular_price; }
	public function get_catalog_visibility() { return $this->visibility; }
	public function is_in_stock() { return true; }
	public function get_short_description() { return '<p>Public summary</p>'; }
	public function get_description() { return '<p>Public <strong>details</strong>.</p>'; }
}

final class WC_Product_Variable extends WC_Product {
	private $children;
	public function __construct( $price, $children, $regular_price = '', $sale_price = '' ) {
		parent::__construct( $price, 'visible', $regular_price, $sale_price );
		$this->children = $children;
	}
	public function get_children() { return $this->children; }
	public function is_on_sale() {
		foreach ( $this->children as $child_id ) {
			$child = wc_get_product( $child_id );
			if ( $child && $child->is_on_sale() ) {
				return true;
			}
		}
		return false;
	}
}

final class WC_Product_Variation extends WC_Product {
	private $attributes;
	private $status;
	private $visible;
	private $in_stock;
	public function __construct( $price, $attributes, $status = 'publish', $visible = true, $in_stock = true, $regular_price = '', $sale_price = '' ) {
		parent::__construct( $price, 'visible', $regular_price, $sale_price );
		$this->attributes = $attributes;
		$this->status     = $status;
		$this->visible    = $visible;
		$this->in_stock   = $in_stock;
	}
	public function get_attributes() { return $this->attributes; }
	public function get_status() { return $this->status; }
	public function variation_is_visible() { return $this->visible; }
	public function is_in_stock() { return $this->in_stock; }
}

$GLOBALS['test_products'] = array(
	1 => new WC_Product( '9.50' ),
	2 => new WC_Product_Variable( '15', array( 201, 202, 203, 204, 205 ) ),
	3 => new WC_Product( '10', 'hidden' ),
	4 => new WC_Product( '', 'catalog' ),
	5 => new WC_Product( '8.00', 'visible', '10.00', '8.00' ),
	6 => new WC_Product_Variable( '12', array( 301, 302 ) ),
	7 => new WC_Product_Variable( '', array( 301, 302 ) ),
	201 => new WC_Product_Variation( '20', array( 'pa_color' => 'red', 'size' => 'Large' ) ),
	202 => new WC_Product_Variation( '15', array( 'pa_color' => 'blue', 'size' => 'Small' ), 'publish', true, false ),
	203 => new WC_Product_Variation( '5', array( 'pa_color' => 'green' ), 'publish', false ),
	204 => new WC_Product_Variation( '8', array( 'pa_color' => 'yellow' ), 'draft' ),
	205 => new WC_Product( '4' ),
	301 => new WC_Product_Variation( '12', array( 'pa_color' => 'black' ), 'publish', true, true, '15.00', '12.00' ),
	302 => new WC_Product_Variation( '18', array( 'pa_color' => 'white' ), 'publish', true, true, '18.00', '' ),
);
$GLOBALS['test_filter_result'] = null;

function wc_get_product( $id ) {
	return isset( $GLOBALS['test_products'][ $id ] ) ? $GLOBALS['test_products'][ $id ] : null;
}
function get_the_title( $id ) { return 'Product ' . $id; }
function get_permalink( $id ) { return 'https://example.test/product-' . $id . '/'; }
function get_bloginfo( $key ) { return 'en-US'; }
function get_woocommerce_currency() { return 'USD'; }
function __( $text, $domain ) { return $text; }
function taxonomy_exists( $taxonomy ) { return 'pa_color' === $taxonomy; }
function get_term_by( $field, $slug, $taxonomy ) {
	$names = array( 'red' => 'Red', 'blue' => 'Blue', 'green' => 'Green', 'yellow' => 'Yellow', 'black' => 'Black', 'white' => 'White' );
	return isset( $names[ $slug ] ) ? (object) array( 'name' => $names[ $slug ] ) : false;
}
function apply_filters( $hook, $value ) {
	return null === $GLOBALS['test_filter_result'] ? $value : $GLOBALS['test_filter_result'];
}

$failures = array();
$tests = 0;
$assert_same = function ( $expected, $actual, $name ) use ( &$failures, &$tests ) {
	$tests++;
	if ( $expected !== $actual ) {
		$failures[] = $name . ': expected ' . var_export( $expected, true ) . ', got ' . var_export( $actual, true );
	}
};
$assert_contains = function ( $needle, $haystack, $name ) use ( &$failures, &$tests ) {
	$tests++;
	if ( false === strpos( $haystack, $needle ) ) {
		$failures[] = $name . ': missing ' . var_export( $needle, true );
	}
};
$assert_not_contains = function ( $needle, $haystack, $name ) use ( &$failures, &$tests ) {
	$tests++;
	if ( false !== strpos( $haystack, $needle ) ) {
		$failures[] = $name . ': unexpectedly found ' . var_export( $needle, true );
	}
};

$generator = new Product_Generator();
$assert_same( true, $generator->is_exposable( 1 ), 'Exposes a visible priced product' );
$assert_same( false, $generator->is_exposable( 3 ), 'Rejects hidden products' );
$assert_same( false, $generator->is_exposable( 4 ), 'Rejects products without prices' );
$assert_same( true, $generator->is_exposable( 7 ), 'Exposes variable product even when parent get_price is empty' );
$GLOBALS['test_filter_result'] = true;
$assert_same( false, $generator->is_exposable( 3 ), 'Filter cannot expose a hidden product' );
$GLOBALS['test_filter_result'] = false;
$assert_same( false, $generator->is_exposable( 1 ), 'Filter can deny a visible product' );
$GLOBALS['test_filter_result'] = null;

$simple = $generator->generate( new WP_Post( 1 ) );
$assert_contains( "Public summary\n\nPublic **details**.", $simple, 'Uses public WooCommerce descriptions' );
$assert_contains( '**Price:** USD 9.50', $simple, 'Shows simple product price' );
$assert_contains( "\nprice_from: 9.50\n", $simple, 'Shows the public minimum price' );
$assert_contains( "\nlang: \"en-US\"\n", $simple, 'Uses the WordPress site language' );

$variable = $generator->generate( new WP_Post( 2 ) );
$assert_contains( '| Blue · Small | USD 15.00 | Out of stock |', $variable, 'Shows visible variation and stock state' );
$assert_contains( '| Red · Large | USD 20.00 | In stock |', $variable, 'Shows a second public variation' );
$assert_not_contains( 'Green', $variable, 'Omits hidden variations' );
$assert_not_contains( 'Yellow', $variable, 'Omits unpublished variations' );
$assert_not_contains( 'USD 4.00', $variable, 'Omits a child that is not a WooCommerce variation' );
$assert_contains( "\nprice_from: 15.00\n", $variable, 'Minimum price ignores hidden and unpublished variations' );

$discounted_simple = $generator->generate( new WP_Post( 5 ) );
$assert_contains( '**Price:** USD 8.00', $discounted_simple, 'Shows active discounted price' );
$assert_contains( '**Regular Price:** USD 10.00', $discounted_simple, 'Shows regular price for on-sale product' );
$assert_contains( '**Sale Price:** USD 8.00', $discounted_simple, 'Shows sale price for on-sale product' );
$assert_contains( "\nprice: 8.00\n", $discounted_simple, 'Front matter shows active price' );
$assert_contains( "\nregular_price: 10.00\n", $discounted_simple, 'Front matter shows regular price' );
$assert_contains( "\nsale_price: 8.00\n", $discounted_simple, 'Front matter shows sale price' );
$assert_contains( "\non_sale: true\n", $discounted_simple, 'Front matter shows on_sale flag' );

$discounted_variable = $generator->generate( new WP_Post( 6 ) );
$assert_contains( "\non_sale: true\n", $discounted_variable, 'Variable product front matter shows on_sale flag' );
$assert_contains( '| Option | Price | Regular Price | Sale Price | Availability |', $discounted_variable, 'Shows 5-column table when variations are on sale' );
$assert_contains( '| Black | USD 12.00 | USD 15.00 | USD 12.00 | In stock |', $discounted_variable, 'Shows on-sale variation with regular and sale prices' );
$assert_contains( '| White | USD 18.00 | USD 18.00 | - | In stock |', $discounted_variable, 'Shows standard variation with regular price and dash for sale' );

if ( $failures ) {
	fwrite( STDERR, implode( "\n", $failures ) . "\n" );
	exit( 1 );
}
echo "All {$tests} product generator tests passed.\n";
