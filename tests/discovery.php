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

final class WP_Post_Type {
	public $name;

	public function __construct( $name ) {
		$this->name = $name;
	}
}

final class WP_Term {
	public $term_id;
	public $taxonomy;
	public $name;
	public $description;

	public function __construct( $term_id, $taxonomy, $name, $description = '' ) {
		$this->term_id = $term_id;
		$this->taxonomy = $taxonomy;
		$this->name = $name;
		$this->description = $description;
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

function is_post_type_archive() {
	return ! empty( $GLOBALS['discovery_test_context']['archive'] );
}

function is_home() {
	return ! empty( $GLOBALS['discovery_test_context']['home'] );
}

function is_front_page() {
	return ! empty( $GLOBALS['discovery_test_context']['front_page'] );
}

function is_category() {
	return ! empty( $GLOBALS['discovery_test_context']['category'] );
}

function is_tag() {
	return ! empty( $GLOBALS['discovery_test_context']['tag'] );
}

function is_tax() {
	return ! empty( $GLOBALS['discovery_test_context']['tax'] );
}

function is_shop() {
	return ! empty( $GLOBALS['discovery_test_context']['shop'] );
}

function is_product_category() {
	return ! empty( $GLOBALS['discovery_test_context']['product_cat'] );
}

function is_product_tag() {
	return ! empty( $GLOBALS['discovery_test_context']['product_tag'] );
}

function get_term_link( $term ) {
	if ( 'category' === $term->taxonomy ) {
		return 'https://example.com/category/news/';
	}
	if ( 'topic' === $term->taxonomy ) {
		return 'https://example.com/topic/research/';
	}
	return 'product_tag' === $term->taxonomy ? 'https://example.com/product-tag/featured/' : 'https://example.com/product-category/widgets/';
}

function is_404() {
	return ! empty( $GLOBALS['discovery_test_context']['not_found'] );
}

function is_search() {
	return ! empty( $GLOBALS['discovery_test_context']['search'] );
}

function get_query_var( $key ) {
	return isset( $GLOBALS['discovery_test_context'][ $key ] ) ? $GLOBALS['discovery_test_context'][ $key ] : 0;
}

function get_post_type_archive_link( $type ) {
	return 'https://example.com/' . $type . '/';
}

function get_pagenum_link( $page, $escape = true ) {
	if ( ! empty( $GLOBALS['discovery_test_context']['home'] ) ) {
		return 'https://example.com/blog/page/' . $page . '/';
	}
	if ( ! empty( $GLOBALS['discovery_test_context']['category'] ) ) {
		return 'https://example.com/category/news/page/' . $page . '/';
	}
	if ( ! empty( $GLOBALS['discovery_test_context']['tax'] ) ) {
		return 'https://example.com/topic/research/page/' . $page . '/';
	}
	if ( ! empty( $GLOBALS['discovery_test_context']['shop'] ) ) {
		return 'https://example.com/product/page/' . $page . '/';
	}
	return 'https://example.com/book/page/' . $page . '/';
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
	return (object) array( 'name' => $type, 'public' => true, 'publicly_queryable' => true, 'has_archive' => in_array( $type, array( 'book', 'product' ), true ) );
}

function taxonomy_exists( $taxonomy ) {
	return in_array( $taxonomy, array( 'category', 'post_tag', 'topic', 'product_cat', 'product_tag' ), true );
}

function get_taxonomy( $taxonomy ) {
	if ( ! taxonomy_exists( $taxonomy ) ) {
		return null;
	}
	return (object) array(
		'public'             => true,
		'publicly_queryable' => true,
		'object_type'        => in_array( $taxonomy, array( 'category', 'post_tag', 'topic' ), true ) ? array( 'post' ) : array( 'product' ),
	);
}

function wp_parse_args( $args, $defaults ) {
	return array_merge( $defaults, $args );
}

function get_permalink( $post ) {
	if ( is_int( $post ) || ( is_string( $post ) && ctype_digit( $post ) ) ) {
		return 7 === (int) $post ? 'https://example.com/blog/' : 'https://example.com/item-' . (int) $post . '/';
	}
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
	'archive'  => false,
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
$assert_same( true, $discovery->current_content_has_markdown(), 'Publishes public content after Markdown is enabled' );
$GLOBALS['discovery_test_enabled_items'][1] = '1';
$assert_same( true, $discovery->current_content_has_markdown(), 'Exposes published blog post' );
$GLOBALS['discovery_test_options'][ Settings::OPTION ] = array(
	'schema_version' => 3,
	'markdown_enabled' => 1,
	'blocked_post_types' => array( 'post' ),
	'blocked_paths' => array(),
);
$assert_same( false, $discovery->current_content_has_markdown(), 'Excluded type overrides item opt-in' );
$GLOBALS['discovery_test_options'][ Settings::OPTION ]['blocked_post_types'] = array();
$GLOBALS['discovery_test_options'][ Settings::OPTION ]['blocked_paths'] = array( '/item-1' );
$assert_same( false, $discovery->current_content_has_markdown(), 'Excluded path overrides item opt-in' );
$GLOBALS['discovery_test_options'][ Settings::OPTION ]['blocked_paths'] = array();
$GLOBALS['discovery_test_options'][ Settings::OPTION ]['blocked_terms'] = array( 'category:123' );
$GLOBALS['discovery_test_terms'][1] = array( 'category:123' );
$assert_same( true, $discovery->current_content_has_markdown(), 'Ignores retired term exclusions' );
$GLOBALS['discovery_test_options'][ Settings::OPTION ]['blocked_terms'] = array();
$GLOBALS['discovery_test_context']['post']->post_content = '<p>Account data</p>';
$assert_same( true, $discovery->current_content_has_markdown(), 'Publishes static content under the global setting' );
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
$GLOBALS['discovery_test_context']['front_page'] = true;
$assert_same( true, $discovery->current_content_has_markdown(), 'Exposes the static front page through the Pages setting' );
$GLOBALS['discovery_test_context']['front_page'] = false;
foreach ( array( 'cart', 'checkout', 'myaccount', 'pay', 'view_order', 'shop' ) as $wc_page ) {
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
$assert_same( true, $discovery->current_content_has_markdown(), 'Publishes all supported public content types' );
$GLOBALS['discovery_test_options'][ Settings::OPTION ]['detail_post_types'] = array( 'post', 'page', 'product' );
$GLOBALS['discovery_test_context']['post'] = new WP_Post( 5, 'attachment' );
$assert_same( false, $discovery->current_content_has_markdown(), 'Rejects unsupported post type' );
$GLOBALS['discovery_test_context']['post'] = new WP_Post( 6, 'book' );
$GLOBALS['discovery_test_enabled_items'][6] = '1';
$assert_same( true, $discovery->current_content_has_markdown(), 'Publishes public Custom Post Types' );
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
$GLOBALS['discovery_test_context']['home'] = true;
$GLOBALS['discovery_test_options']['page_for_posts'] = 7;
$GLOBALS['discovery_test_options']['show_on_front'] = 'page';
$assert_same( array( 'type' => 'post', 'page' => 1, 'url' => 'https://example.com/blog/' ), $discovery->current_archive_context(), 'Allows the assigned Posts page archive' );
$GLOBALS['discovery_test_context']['paged'] = 2;
$assert_same( array( 'type' => 'post', 'page' => 2, 'url' => 'https://example.com/blog/page/2/' ), $discovery->current_archive_context(), 'Allows Posts page archive pagination' );
$GLOBALS['discovery_test_context']['paged'] = 0;
$GLOBALS['discovery_test_options'][ Settings::OPTION ]['blocked_paths'] = array( '/blog' );
$assert_same( null, $discovery->current_archive_context(), 'Blocked Posts page path overrides publication' );
$GLOBALS['discovery_test_options'][ Settings::OPTION ]['blocked_paths'] = array();
$GLOBALS['discovery_test_options'][ Settings::OPTION ]['detail_post_types'] = array( 'page', 'product' );
$assert_same( array( 'type' => 'post', 'page' => 1, 'url' => 'https://example.com/blog/' ), $discovery->current_archive_context(), 'Posts page archive follows the global Markdown setting' );
$GLOBALS['discovery_test_options'][ Settings::OPTION ]['detail_post_types'] = array( 'post', 'page', 'product', 'book' );
$GLOBALS['discovery_test_options'][ Settings::OPTION ]['markdown_all_content'] = 1;
$assert_same( array( 'type' => 'post', 'page' => 1, 'url' => 'https://example.com/blog/' ), $discovery->current_archive_context(), 'Publishes the Posts page archive with full publication enabled' );
$GLOBALS['discovery_test_options'][ Settings::OPTION ]['markdown_all_content'] = 0;
$GLOBALS['discovery_test_context']['home'] = false;
$GLOBALS['discovery_test_context']['category'] = true;
$category = new WP_Term( 123, 'category', 'News' );
$GLOBALS['discovery_test_context']['post'] = $category;
$assert_same( array( 'type' => 'post', 'page' => 1, 'url' => 'https://example.com/category/news/', 'term' => $category, 'archive_type' => 'category' ), $discovery->current_archive_context(), 'Publishes a post category through the Posts setting' );
$GLOBALS['discovery_test_context']['paged'] = 2;
$assert_same( array( 'type' => 'post', 'page' => 2, 'url' => 'https://example.com/category/news/page/2/', 'term' => $category, 'archive_type' => 'category' ), $discovery->current_archive_context(), 'Publishes post category pagination through the Posts setting' );
$GLOBALS['discovery_test_context']['paged'] = 0;
$GLOBALS['discovery_test_options'][ Settings::OPTION ]['blocked_terms'] = array( 'category:123' );
$assert_same( array( 'type' => 'post', 'page' => 1, 'url' => 'https://example.com/category/news/', 'term' => $category, 'archive_type' => 'category' ), $discovery->current_archive_context(), 'Ignores retired term exclusions for post categories' );
$GLOBALS['discovery_test_options'][ Settings::OPTION ]['blocked_terms'] = array();
$GLOBALS['discovery_test_context']['category'] = false;
$GLOBALS['discovery_test_context']['tax'] = true;
$topic = new WP_Term( 124, 'topic', 'Research' );
$GLOBALS['discovery_test_context']['post'] = $topic;
$assert_same( array( 'type' => 'post', 'page' => 1, 'url' => 'https://example.com/topic/research/', 'term' => $topic, 'archive_type' => 'topic' ), $discovery->current_archive_context(), 'Publishes a public post taxonomy through the Posts setting' );
$GLOBALS['discovery_test_context']['tax'] = false;
$GLOBALS['discovery_test_context']['archive'] = true;
$GLOBALS['discovery_test_context']['post'] = new WP_Post_Type( 'book' );
$GLOBALS['discovery_test_options'][ Settings::OPTION ]['enabled_archives'] = array( 'book' );
$assert_same( array( 'type' => 'book', 'page' => 1, 'url' => 'https://example.com/book/' ), $discovery->current_archive_context(), 'Allows opted-in public CPT archive' );
$GLOBALS['discovery_test_context']['paged'] = 2;
$assert_same( array( 'type' => 'book', 'page' => 2, 'url' => 'https://example.com/book/page/2/' ), $discovery->current_archive_context(), 'Allows opted-in archive pagination' );
$GLOBALS['discovery_test_context']['paged'] = 0;
$GLOBALS['discovery_test_options'][ Settings::OPTION ]['blocked_paths'] = array( '/book' );
$assert_same( null, $discovery->current_archive_context(), 'Blocked archive path overrides opt-in' );
$GLOBALS['discovery_test_options'][ Settings::OPTION ]['blocked_paths'] = array();
$GLOBALS['discovery_test_options'][ Settings::OPTION ]['blocked_post_types'] = array( 'book' );
$assert_same( null, $discovery->current_archive_context(), 'Blocked archive type overrides opt-in' );
$GLOBALS['discovery_test_options'][ Settings::OPTION ]['blocked_post_types'] = array();
$GLOBALS['discovery_test_context']['logged_in'] = true;
$assert_same( null, $discovery->current_archive_context(), 'Rejects archive for authenticated visitor' );
$GLOBALS['discovery_test_context']['logged_in'] = false;
$GLOBALS['discovery_test_context']['not_found'] = true;
$assert_same( null, $discovery->current_archive_context(), 'Rejects 404 archive' );
$GLOBALS['discovery_test_context']['not_found'] = false;
$GLOBALS['discovery_test_options'][ Settings::OPTION ]['enabled_archives'] = array();
$assert_same( array( 'type' => 'book', 'page' => 1, 'url' => 'https://example.com/book/' ), $discovery->current_archive_context(), 'Publishes supported public archives with Markdown enabled' );
$GLOBALS['discovery_test_options'][ Settings::OPTION ]['enabled_archives'] = array( 'product' );
$GLOBALS['discovery_test_context']['post'] = new WP_Post_Type( 'product' );
$assert_same( null, $discovery->current_archive_context(), 'Product archive requires WooCommerce shop context' );
$GLOBALS['discovery_test_context']['shop'] = true;
$assert_same( array( 'type' => 'product', 'page' => 1, 'url' => 'https://example.com/product/' ), $discovery->current_archive_context(), 'Allows independently enabled WooCommerce shop archive' );
$GLOBALS['discovery_test_options'][ Settings::OPTION ]['blocked_paths'] = array( '/product' );
$assert_same( null, $discovery->current_archive_context(), 'Blocked shop path overrides opt-in' );
$GLOBALS['discovery_test_options'][ Settings::OPTION ]['blocked_paths'] = array();
$GLOBALS['discovery_test_context']['shop'] = false;
$GLOBALS['discovery_test_context']['archive'] = false;
$GLOBALS['discovery_test_context']['product_cat'] = true;
$term = new WP_Term( 123, 'product_cat', 'Widgets', '<p>Public description</p>' );
$GLOBALS['discovery_test_context']['post'] = $term;
$GLOBALS['discovery_test_options'][ Settings::OPTION ]['enabled_archives'] = array( 'product_cat' );
$assert_same( array( 'type' => 'product_cat', 'page' => 1, 'url' => 'https://example.com/product-category/widgets/', 'term' => $term ), $discovery->current_archive_context(), 'Allows enabled product category independently of shop' );
$GLOBALS['discovery_test_options'][ Settings::OPTION ]['blocked_terms'] = array( 'product_cat:123' );
$assert_same( array( 'type' => 'product_cat', 'page' => 1, 'url' => 'https://example.com/product-category/widgets/', 'term' => $term ), $discovery->current_archive_context(), 'Ignores retired term exclusions for product categories' );
$GLOBALS['discovery_test_options'][ Settings::OPTION ]['blocked_terms'] = array();
$GLOBALS['discovery_test_context']['product_cat'] = false;
$GLOBALS['discovery_test_context']['product_tag'] = true;
$tag = new WP_Term( 456, 'category', 'Featured' );
$GLOBALS['discovery_test_context']['post'] = $tag;
$GLOBALS['discovery_test_options'][ Settings::OPTION ]['enabled_archives'] = array( 'product_tag' );
$assert_same( null, $discovery->current_archive_context(), 'Rejects a mismatched taxonomy object' );
$tag->taxonomy = 'product_tag';
$assert_same( array( 'type' => 'product_tag', 'page' => 1, 'url' => 'https://example.com/product-tag/featured/', 'term' => $tag ), $discovery->current_archive_context(), 'Allows enabled product tag archive' );
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
