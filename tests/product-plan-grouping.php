<?php

define( 'ABSPATH', dirname( __DIR__ ) . '/' );

require_once dirname( __DIR__ ) . '/includes/class-markdown.php';
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

	public function __construct( $price ) {
		$this->price = $price;
	}

	public function get_price() {
		return $this->price;
	}

	public function is_in_stock() {
		return true;
	}

	public function get_review_count() {
		return 0;
	}

	public function get_average_rating() {
		return '0';
	}
}

final class WC_Product_Variable extends WC_Product {
	private $children;

	public function __construct( $price, $children ) {
		parent::__construct( $price );
		$this->children = $children;
	}

	public function get_children() {
		return $this->children;
	}
}

final class Test_Plan_Variation {
	private $attributes;
	private $price;
	private $status;
	private $purchasable;
	private $stock_status;

	public function __construct( $attributes, $price, $status, $purchasable, $stock_status ) {
		$this->attributes   = $attributes;
		$this->price        = $price;
		$this->status       = $status;
		$this->purchasable  = $purchasable;
		$this->stock_status = $stock_status;
	}

	public function get_attributes() {
		return $this->attributes;
	}

	public function get_price() {
		return $this->price;
	}

	public function get_status() {
		return $this->status;
	}

	public function is_purchasable() {
		return $this->purchasable;
	}

	public function get_stock_status() {
		return $this->stock_status;
	}
}

$GLOBALS['hs_grouping_products']  = array();
$GLOBALS['hs_grouping_languages'] = array();
$GLOBALS['hs_grouping_terms']     = array();

function __( $text, $domain ) {
	if ( 'Days' === $text && 'serfe-ai-discovery' === $domain ) {
		$translations = array( 'es' => 'Días', 'pt' => 'Dias' );
		$language     = isset( $GLOBALS['hs_grouping_current_language'] ) ? $GLOBALS['hs_grouping_current_language'] : 'en';
		return isset( $translations[ $language ] ) ? $translations[ $language ] : $text;
	}
	return $text;
}

function hs_register_grouping_product( $product_id, $language, $variations ) {
	$children = array();

	foreach ( $variations as $index => $data ) {
		$variation_id = ( $product_id * 100 ) + $index + 1;
		$attributes   = array( 'pa_plan-de-datos' => $data['slug'] );
		if ( ! empty( $data['other_attributes'] ) ) {
			$attributes = array_merge( $attributes, $data['other_attributes'] );
		}

		$GLOBALS['hs_grouping_products'][ $variation_id ] = new Test_Plan_Variation(
			$attributes,
			$data['price'],
			isset( $data['status'] ) ? $data['status'] : 'publish',
			isset( $data['purchasable'] ) ? $data['purchasable'] : true,
			isset( $data['stock_status'] ) ? $data['stock_status'] : 'instock'
		);
		$children[] = $variation_id;

		if ( isset( $data['label'] ) ) {
			$GLOBALS['hs_grouping_terms'][ $data['slug'] ] = (object) array( 'name' => $data['label'] );
		}
	}

	$GLOBALS['hs_grouping_products'][ $product_id ]  = new WC_Product_Variable( '1.00', $children );
	$GLOBALS['hs_grouping_languages'][ $product_id ] = $language;
}

function apply_filters( $hook, $value ) {
	$args = func_get_args();

	if ( 'wpml_post_language_details' === $hook ) {
		$product_id = isset( $args[2] ) ? $args[2] : 0;
		$GLOBALS['hs_grouping_current_language'] = isset( $GLOBALS['hs_grouping_languages'][ $product_id ] )
			? $GLOBALS['hs_grouping_languages'][ $product_id ]
			: 'es';
		return array(
			'language_code' => isset( $GLOBALS['hs_grouping_languages'][ $product_id ] )
				? $GLOBALS['hs_grouping_languages'][ $product_id ]
				: 'es',
		);
	}
	if ( 'wpml_default_language' === $hook ) {
		return 'es';
	}

	return $value;
}

function get_woocommerce_currency() {
	return 'USD';
}

function wc_get_product( $product_id ) {
	return isset( $GLOBALS['hs_grouping_products'][ $product_id ] )
		? $GLOBALS['hs_grouping_products'][ $product_id ]
		: null;
}

function get_the_title( $product_id ) {
	return 'Grouping product ' . $product_id;
}

function get_permalink( $product_id ) {
	return 'https://serfe.test/product-' . $product_id . '/';
}

function taxonomy_exists( $taxonomy ) {
	return 'pa_plan-de-datos' === $taxonomy;
}

function get_term_by( $field, $slug, $taxonomy ) {
	if ( 'slug' !== $field || 'pa_plan-de-datos' !== $taxonomy ) {
		return false;
	}

	return isset( $GLOBALS['hs_grouping_terms'][ $slug ] )
		? $GLOBALS['hs_grouping_terms'][ $slug ]
		: false;
}

function wp_json_encode( $value ) {
	return json_encode( $value );
}

/*
 * Real catalog shape: fixed-data plans commonly share one price for every
 * duration from 16 through 30 days. Input order must not affect the range.
 */
hs_register_grouping_product(
	100,
	'es',
	array(
		array( 'slug' => '18-dias-10gb', 'label' => '18 Días 10GB', 'price' => '23' ),
		array( 'slug' => '16-dias-10gb', 'label' => '16 Días 10GB', 'price' => '23' ),
		array( 'slug' => '17-dias-10gb', 'label' => '17 Días 10GB', 'price' => '23' ),
	)
);

/*
 * A range ends when there is a duration gap or any public property changes:
 * price, package identity or stock status.
 */
hs_register_grouping_product(
	200,
	'es',
	array(
		array( 'slug' => '1-dia-1gb-boundary', 'label' => '1 Día 1GB Boundary', 'price' => '10' ),
		array( 'slug' => '2-dias-1gb-boundary', 'label' => '2 Días 1GB Boundary', 'price' => '10' ),
		array( 'slug' => '4-dias-1gb-boundary', 'label' => '4 Días 1GB Boundary', 'price' => '10' ),
		array( 'slug' => '5-dias-1gb-boundary', 'label' => '5 Días 1GB Boundary', 'price' => '11' ),
		array( 'slug' => '6-dias-2gb-boundary', 'label' => '6 Días 2GB Boundary', 'price' => '10' ),
		array( 'slug' => '7-dias-1gb-boundary', 'label' => '7 Días 1GB Boundary', 'price' => '10', 'stock_status' => 'instock' ),
		array( 'slug' => '8-dias-1gb-boundary', 'label' => '8 Días 1GB Boundary', 'price' => '10', 'stock_status' => 'outofstock' ),
	)
);

/*
 * Slugs or labels outside the duration convention, and mismatching duration
 * values, remain as their original rows instead of being guessed.
 */
hs_register_grouping_product(
	300,
	'es',
	array(
		array( 'slug' => 'ilimitado', 'label' => 'Ilimitado', 'price' => '20' ),
		array( 'slug' => '2-dias-1gb-invalid', 'label' => '3 Días 1GB Invalid', 'price' => '20' ),
		array( 'slug' => '4-dias-1gb-missing-term', 'price' => '20' ),
	)
);

/* The range label follows the product language, even when terms are shared. */
hs_register_grouping_product(
	400,
	'en',
	array(
		array( 'slug' => '16-dias-10gb-en', 'label' => '16 Días 10GB', 'price' => '23' ),
		array( 'slug' => '17-dias-10gb-en', 'label' => '17 Días 10GB', 'price' => '23' ),
	)
);
hs_register_grouping_product(
	500,
	'pt',
	array(
		array( 'slug' => '16-dias-10gb-pt', 'label' => '16 Dias 10GB', 'price' => '23' ),
		array( 'slug' => '17-dias-10gb-pt', 'label' => '17 Dias 10GB', 'price' => '23' ),
	)
);

/* Different secondary attributes may never be collapsed into one row. */
hs_register_grouping_product(
	600,
	'es',
	array(
		array(
			'slug'             => '1-dia-1gb-network',
			'label'            => '1 Día 1GB Network',
			'price'            => '10',
			'other_attributes' => array( 'network' => '4g' ),
		),
		array(
			'slug'             => '2-dias-1gb-network',
			'label'            => '2 Días 1GB Network',
			'price'            => '10',
			'other_attributes' => array( 'network' => '5g' ),
		),
	)
);

/* Grouping happens before the final ascending-price sort. */
hs_register_grouping_product(
	700,
	'es',
	array(
		array( 'slug' => '16-dias-20gb', 'label' => '16 Días 20GB', 'price' => '37' ),
		array( 'slug' => '17-dias-20gb', 'label' => '17 Días 20GB', 'price' => '37' ),
		array( 'slug' => '16-dias-10gb', 'label' => '16 Días 10GB', 'price' => '23' ),
		array( 'slug' => '17-dias-10gb', 'label' => '17 Días 10GB', 'price' => '23' ),
	)
);

/* A bucket containing one duration remains a normal plan row. */
hs_register_grouping_product(
	800,
	'es',
	array(
		array( 'slug' => '30-dias-10gb', 'label' => '30 Días 10GB', 'price' => '23' ),
	)
);

/* Display-label inconsistencies must prevent grouping to avoid losing data. */
hs_register_grouping_product(
	900,
	'es',
	array(
		array( 'slug' => '1-dia-1gb-label', 'label' => '1 Día 1 GB Label', 'price' => '10' ),
		array( 'slug' => '2-dias-1gb-label', 'label' => '2 Días 1GB Label', 'price' => '10' ),
	)
);

$failures = array();
$tests    = 0;

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

$assert_before = function ( $first, $second, $haystack, $name ) use ( &$failures, &$tests ) {
	$tests++;
	$first_position  = strpos( $haystack, $first );
	$second_position = strpos( $haystack, $second );
	if ( false === $first_position || false === $second_position || $first_position >= $second_position ) {
		$failures[] = $name . ': rows are missing or out of order';
	}
};

$generator = new Product_Generator();

$spanish = $generator->generate( new WP_Post( 100 ) );
$assert_contains( '| 16–18 Días 10GB | USD 23.00 |', $spanish, 'Groups consecutive equivalent Spanish plans' );
$assert_not_contains( '| 16 Días 10GB |', $spanish, 'Removes the first source row after grouping' );
$assert_not_contains( '| 17 Días 10GB |', $spanish, 'Removes an intermediate source row after grouping' );
$assert_not_contains( '| 18 Días 10GB |', $spanish, 'Removes the last source row after grouping' );
$boundaries = $generator->generate( new WP_Post( 200 ) );
$assert_contains( '| 1–2 Días 1GB Boundary | USD 10.00 |', $boundaries, 'Groups only the consecutive prefix' );
$assert_contains( '| 4 Días 1GB Boundary | USD 10.00 |', $boundaries, 'Starts a new sequence after a duration gap' );
$assert_contains( '| 5 Días 1GB Boundary | USD 11.00 |', $boundaries, 'Does not group a different price' );
$assert_contains( '| 6 Días 2GB Boundary | USD 10.00 |', $boundaries, 'Does not group a different package' );
$assert_contains( '| 7 Días 1GB Boundary | USD 10.00 |', $boundaries, 'Keeps the in-stock row separate' );
$assert_contains( '| 8 Días 1GB Boundary | USD 10.00 |', $boundaries, 'Keeps the out-of-stock row separate' );
$assert_not_contains( '1–4 Días', $boundaries, 'Never bridges a duration gap' );
$assert_not_contains( '7–8 Días', $boundaries, 'Never groups different stock states' );

$invalid = $generator->generate( new WP_Post( 300 ) );
$assert_contains( '| Ilimitado | USD 20.00 |', $invalid, 'Preserves a non-duration plan' );
$assert_contains( '| 3 Días 1GB Invalid | USD 20.00 |', $invalid, 'Preserves a slug-label duration mismatch' );
$assert_contains( '| 4-dias-1gb-missing-term | USD 20.00 |', $invalid, 'Preserves a plan whose taxonomy term is missing' );
$assert_not_contains( '–', $invalid, 'Does not invent ranges for unrecognized plans' );

$english = $generator->generate( new WP_Post( 400 ) );
$assert_contains( '| 16–17 Days 10GB | USD 23.00 |', $english, 'Translates the range unit to English' );

$portuguese = $generator->generate( new WP_Post( 500 ) );
$assert_contains( '| 16–17 Dias 10GB | USD 23.00 |', $portuguese, 'Translates the range unit to Portuguese' );

$secondary_attributes = $generator->generate( new WP_Post( 600 ) );
$assert_not_contains( '1–2 Días', $secondary_attributes, 'Does not group different secondary attributes' );
$assert_contains( '| 1 Día 1GB Network · 4g | USD 10.00 |', $secondary_attributes, 'Preserves the first secondary attribute' );
$assert_contains( '| 2 Días 1GB Network · 5g | USD 10.00 |', $secondary_attributes, 'Preserves the second secondary attribute' );

$sorted = $generator->generate( new WP_Post( 700 ) );
$assert_contains( '| 16–17 Días 10GB | USD 23.00 |', $sorted, 'Groups the lower-priced package' );
$assert_contains( '| 16–17 Días 20GB | USD 37.00 |', $sorted, 'Groups the higher-priced package' );
$assert_before( '16–17 Días 10GB', '16–17 Días 20GB', $sorted, 'Sorts grouped rows by ascending price' );
$assert_contains( "\nprice_from: 23.00\n", $sorted, 'Uses the lowest printed price as price_from' );
$assert_contains( "\nupdated: \"", $sorted, 'Adds the generation time to the front matter' );
$assert_contains( "\navailability: \"https://schema.org/InStock\"\n", $sorted, 'Adds the schema.org availability' );
$assert_not_contains( 'rating:', $sorted, 'Omits rating without reviews' );
$assert_not_contains( 'review_count:', $sorted, 'Omits review_count without reviews' );

$single = $generator->generate( new WP_Post( 800 ) );
$assert_contains( '| 30 Días 10GB | USD 23.00 |', $single, 'Leaves a single-duration bucket unchanged' );
$assert_not_contains( '–', $single, 'Does not format a singleton as a range' );

$different_labels = $generator->generate( new WP_Post( 900 ) );
$assert_contains( '| 1 Día 1 GB Label | USD 10.00 |', $different_labels, 'Preserves the first inconsistent package label' );
$assert_contains( '| 2 Días 1GB Label | USD 10.00 |', $different_labels, 'Preserves the second inconsistent package label' );
$assert_not_contains( '1–2 Días', $different_labels, 'Does not group inconsistent package labels' );

if ( $failures ) {
	fwrite( STDERR, implode( "\n", $failures ) . "\n" );
	exit( 1 );
}

echo "All {$tests} product plan grouping tests passed.\n";
