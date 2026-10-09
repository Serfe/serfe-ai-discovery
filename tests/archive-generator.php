<?php

define( 'ABSPATH', dirname( __DIR__ ) . '/' );
require_once dirname( __DIR__ ) . '/includes/class-markdown.php';
require_once dirname( __DIR__ ) . '/includes/class-settings.php';
require_once dirname( __DIR__ ) . '/includes/class-exclusions.php';
require_once dirname( __DIR__ ) . '/includes/class-archive-generator.php';

use Serfe\AI_Discovery\Archive_Generator;
use Serfe\AI_Discovery\Settings;

final class WP_Post {
	public $ID;
	public $post_type;
	public $post_status;
	public $post_password;
	public $post_title;
	public $publicly_viewable;

	public function __construct( $id, $title, $type = 'book', $status = 'publish', $password = '', $publicly_viewable = true ) {
		$this->ID = $id;
		$this->post_title = $title;
		$this->post_type = $type;
		$this->post_status = $status;
		$this->post_password = $password;
		$this->publicly_viewable = $publicly_viewable;
	}
}

function get_option( $key, $default = false ) {
	return isset( $GLOBALS['archive_test_options'][ $key ] ) ? $GLOBALS['archive_test_options'][ $key ] : $default;
}
function wp_parse_args( $args, $defaults ) { return array_merge( $defaults, $args ); }
function get_post_meta( $id, $key, $single ) { return isset( $GLOBALS['archive_test_items'][ $id ] ) ? $GLOBALS['archive_test_items'][ $id ] : ''; }
function get_post_type_object( $type ) { return (object) array( 'labels' => (object) array( 'name' => 'Books' ) ); }
function wp_strip_all_tags( $text ) { return strip_tags( $text ); }
function is_post_publicly_viewable( $post ) { return $post->publicly_viewable; }
function get_permalink( $post ) { return 'https://example.com/book-' . $post->ID . '/'; }
function esc_url_raw( $url ) { return $url; }
function get_pagenum_link( $page, $escape = true ) { return 'https://example.com/book/page/' . $page . '/'; }
function __( $text, $domain ) { return $text; }
function home_url( $path = '' ) { return 'https://example.com' . $path; }
function wp_parse_url( $url, $component = -1 ) { return parse_url( $url, $component ); }
function has_term( $term_id, $taxonomy, $post ) { return 4 === $post->ID && 123 === $term_id && 'category' === $taxonomy; }

$GLOBALS['archive_test_options'][ Settings::OPTION ] = array(
	'schema_version' => 3,
	'markdown_enabled' => 1,
	'blocked_post_types' => array(),
	'blocked_paths' => array( '/book-3' ),
);
$GLOBALS['archive_test_items'] = array( 1 => '1', 2 => '0', 3 => '1', 4 => '1', 5 => '1', 6 => '1', 7 => '1', 8 => '1' );
$posts = array(
	new WP_Post( 1, 'Public [book]' ),
	new WP_Post( 2, 'Not approved' ),
	new WP_Post( 3, 'Blocked path' ),
	new WP_Post( 4, 'Blocked category' ),
	new WP_Post( 5, 'Protected', 'book', 'publish', 'secret' ),
	new WP_Post( 6, 'Draft', 'book', 'draft' ),
	new WP_Post( 7, 'Restricted', 'book', 'publish', '', false ),
	new WP_Post( 8, 'Wrong type', 'product' ),
);

$output = ( new Archive_Generator() )->generate( 'book', 'https://example.com/book/page/2/', 2, 3, $posts );
$failures = array();
foreach ( array(
	'page: 2',
	'# Books',
	'- [Public \\[book\\]](<https://example.com/book-1/>)',
	'[Previous page](<https://example.com/book/page/1/>)',
	'[Next page](<https://example.com/book/page/3/>)',
) as $expected ) {
	if ( false === strpos( $output, $expected ) ) {
		$failures[] = 'Missing: ' . $expected;
	}
}
foreach ( array( 'Blocked path', 'Protected', 'Draft', 'Restricted', 'Wrong type' ) as $forbidden ) {
	if ( false !== strpos( $output, $forbidden ) ) {
		$failures[] = 'Unexpected: ' . $forbidden;
	}
}
$taxonomy_output = ( new Archive_Generator() )->generate( 'post', 'https://example.com/category/news/', 1, 1, $posts, 'News', 'category' );
foreach ( array( 'title: "News"', 'type: "category-archive"', '# News' ) as $expected ) {
	if ( false === strpos( $taxonomy_output, $expected ) ) {
		$failures[] = 'Missing taxonomy metadata: ' . $expected;
	}
}
if ( $failures ) {
	fwrite( STDERR, implode( "\n", $failures ) . "\n" );
	exit( 1 );
}
echo "All 15 archive generator checks passed.\n";
