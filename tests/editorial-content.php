<?php

define( 'ABSPATH', dirname( __DIR__ ) . '/' );

require_once dirname( __DIR__ ) . '/includes/class-markdown.php';
require_once dirname( __DIR__ ) . '/includes/class-html-to-markdown.php';
require_once dirname( __DIR__ ) . '/includes/class-blog-generator.php';
require_once dirname( __DIR__ ) . '/includes/class-editorial-content.php';

use Serfe\AI_Discovery\Blog_Generator;
use Serfe\AI_Discovery\Editorial_Content;
use Serfe\AI_Discovery\Html_To_Markdown;

class WP_Post {
	public $post_content;
	public $post_excerpt = '';
	public $post_title = 'Example';
	public $ID = 1;

	public function __construct( $content ) {
		$this->post_content = $content;
	}
}

class WP_Block_Type_Registry {
	public static function get_instance() {
		return new self();
	}

	public function get_registered( $name ) {
		if ( 'core/paragraph' === $name ) {
			return new Test_Block_Type( false );
		}
		if ( in_array( $name, array( 'core/gallery', 'core/heading', 'core/image', 'core/list', 'core/list-item', 'core/video', 'vendor/empty-block', 'vendor/saved-block' ), true ) ) {
			return new Test_Block_Type( true );
		}
		if ( 'core/latest-posts' === $name ) {
			return new Test_Block_Type( true );
		}
		return null;
	}
}

class Test_Block_Type {
	private $dynamic;

	public function __construct( $dynamic ) {
		$this->dynamic = $dynamic;
	}

	public function is_dynamic() {
		return $this->dynamic;
	}
}

function parse_blocks( $content ) {
	if ( strpos( $content, 'bound-image' ) !== false ) {
		return array( array( 'blockName' => 'core/image', 'attrs' => array( 'metadata' => array( 'bindings' => array( 'url' => array( 'source' => 'core/post-meta' ) ) ) ), 'innerHTML' => '<figure><img src="https://example.com/image.jpg" alt="Bound"></figure>' ) );
	}
	if ( strpos( $content, 'core/image' ) !== false ) {
		return array( array( 'blockName' => 'core/image', 'innerHTML' => '<figure><img src="https://example.com/image.jpg" alt="Example image"></figure>' ) );
	}
	if ( strpos( $content, 'core/gallery' ) !== false ) {
		return array( array( 'blockName' => 'core/gallery', 'innerBlocks' => array( array( 'blockName' => 'core/image', 'innerHTML' => '<figure><img src="https://example.com/image.jpg" alt="Gallery image"></figure>' ) ) ) );
	}
	if ( strpos( $content, 'core/video' ) !== false ) {
		return array( array( 'blockName' => 'core/video', 'innerHTML' => '<figure><video><source src="https://example.com/video.mp4"></video></figure>' ) );
	}
	if ( strpos( $content, 'vendor/saved-block' ) !== false ) {
		return array( array( 'blockName' => 'vendor/saved-block', 'innerHTML' => '<div><p>Saved plugin text</p></div>' ) );
	}
	if ( strpos( $content, 'vendor/empty-block' ) !== false ) {
		return array( array( 'blockName' => 'vendor/empty-block' ) );
	}
	if ( strpos( $content, 'latest-posts' ) !== false ) {
		return array( array( 'blockName' => 'core/paragraph', 'innerBlocks' => array( array( 'blockName' => 'core/latest-posts' ) ) ) );
	}
	if ( strpos( $content, 'unknown-block' ) !== false ) {
		return array( array( 'blockName' => 'vendor/unknown-block' ) );
	}
	if ( strpos( $content, 'core/paragraph' ) !== false ) {
		return array( array( 'blockName' => 'core/paragraph' ) );
	}
	if ( strpos( $content, 'core/heading' ) !== false ) {
		return array( array( 'blockName' => 'core/heading', 'innerHTML' => '<h2>Public</h2>' ) );
	}
	if ( strpos( $content, 'core/list' ) !== false ) {
		return array( array( 'blockName' => 'core/list', 'innerHTML' => '<ul><li>Public</li></ul>', 'innerBlocks' => array( array( 'blockName' => 'core/list-item', 'innerHTML' => '<li>Public</li>' ) ) ) );
	}
	return array( array( 'blockName' => null ) );
}

function wp_strip_all_tags( $text, $remove_breaks = false ) {
	return strip_tags( $text );
}
function get_bloginfo( $field ) { return 'en-US'; }
function get_the_title( $post ) { return $post->post_title; }
function get_permalink( $post ) { return 'https://example.com/example/'; }
function get_the_date( $format, $post ) { return '2026-10-08T00:00:00+00:00'; }
function has_excerpt( $post ) { return false; }
function apply_filters( $hook, $value ) { throw new RuntimeException( 'Content filters must not run' ); }

$failures = array();
$tests = 0;
$assert_same = function ( $expected, $actual, $name ) use ( &$failures, &$tests ) {
	$tests++;
	if ( $expected !== $actual ) {
		$failures[] = $name . ': expected ' . var_export( $expected, true ) . ', got ' . var_export( $actual, true );
	}
};

$assert_same( true, Editorial_Content::is_static( new WP_Post( '<p>Public text</p>' ) ), 'Allows saved HTML' );
$assert_same( true, Editorial_Content::is_static( new WP_Post( '<!-- wp:core/paragraph --><p>Public</p><!-- /wp:core/paragraph -->' ) ), 'Allows static block' );
$assert_same( true, Editorial_Content::is_static( new WP_Post( '<!-- wp:core/heading --><h2>Public</h2><!-- /wp:core/heading -->' ) ), 'Allows saved core heading markup' );
$assert_same( true, Editorial_Content::is_static( new WP_Post( '<!-- wp:core/list --><ul><li>Public</li></ul><!-- /wp:core/list -->' ) ), 'Allows saved core list markup' );
$assert_same( true, Editorial_Content::is_static( new WP_Post( '<!-- wp:core/image --><figure><img src="https://example.com/image.jpg" alt="Example image"></figure><!-- /wp:core/image -->' ) ), 'Allows saved Gutenberg image markup' );
$assert_same( true, Editorial_Content::is_static( new WP_Post( '<!-- wp:core/gallery --><figure><img src="https://example.com/image.jpg" alt="Gallery image"></figure><!-- /wp:core/gallery -->' ) ), 'Allows saved Gutenberg gallery images' );
$assert_same( true, Editorial_Content::is_static( new WP_Post( '<!-- wp:core/video --><figure><video><source src="https://example.com/video.mp4"></video></figure><!-- /wp:core/video -->' ) ), 'Allows saved Gutenberg video markup' );
$assert_same( false, Editorial_Content::is_static( new WP_Post( '<!-- wp:bound-image /-->' ) ), 'Rejects a Gutenberg image with dynamic block bindings' );
$assert_same( true, Editorial_Content::is_static( new WP_Post( '<!-- wp:vendor/saved-block --><div><p>Saved plugin text</p></div><!-- /wp:vendor/saved-block -->' ) ), 'Allows third-party blocks with saved editorial HTML' );
$assert_same( false, Editorial_Content::is_static( new WP_Post( '<!-- wp:vendor/empty-block /-->' ) ), 'Rejects third-party blocks without saved editorial content' );
$assert_same( false, Editorial_Content::is_static( new WP_Post( '[account_data customer="1"]' ) ), 'Rejects shortcode content' );
$assert_same( false, Editorial_Content::is_static( new WP_Post( '<!-- wp:core/latest-posts /-->' ) ), 'Rejects nested dynamic block' );
$assert_same( false, Editorial_Content::is_static( new WP_Post( '<!-- wp:vendor/unknown-block /-->' ) ), 'Rejects unknown block' );
$markdown = ( new Blog_Generator( new Html_To_Markdown() ) )->generate( new WP_Post( '<p>Saved public text</p>' ) );
$assert_same( true, strpos( $markdown, 'Saved public text' ) !== false, 'Converts saved editorial HTML without executing filters' );
$image_markdown = ( new Blog_Generator( new Html_To_Markdown() ) )->generate( new WP_Post( '<figure><img src="https://example.com/image.jpg" alt="Example image"></figure>' ) );
$assert_same( true, strpos( $image_markdown, '![Example image](https://example.com/image.jpg)' ) !== false, 'Converts saved Gutenberg image markup without rendering blocks' );

if ( $failures ) {
	fwrite( STDERR, implode( "\n", $failures ) . "\n" );
	exit( 1 );
}

echo "All {$tests} editorial content tests passed.\n";
